<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /finalproject/auth/login.php');
    exit;
}
$userName = $_SESSION['username'] ?? $_SESSION['fname'] ?? 'Admin';

// Get date range from GET parameters (default to last 30 days if not set)
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Fetch patient statistics with date range filter
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM patients WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$totalPatients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COUNT(*) as new_patients FROM patients WHERE DATE(created_at) BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$newPatients = $stmt->fetch(PDO::FETCH_ASSOC)['new_patients'];

// Fetch patient details - FIXED: Use 'program' as 'course' with date range filter
$stmt = $pdo->prepare("
    SELECT p.sr_code, p.full_name, p.program AS course, p.year_level
    FROM patients p
    WHERE DATE(p.created_at) BETWEEN ? AND ?
    ORDER BY p.created_at DESC
    LIMIT 10
");
$stmt->execute([$startDate, $endDate]);
$patientDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch inventory data (no date filter needed for current inventory)
$stmt = $pdo->query("
    SELECT 
        i.id,
        i.item_code,
        i.item_name,
        i.batch_number,
        i.quantity as total_quantity,
        i.dispensed,
        (i.quantity - COALESCE(i.dispensed, 0)) as available,
        i.expiry_date,
        i.status
    FROM inventory i
    WHERE i.status = 'active'
    ORDER BY i.item_name ASC
");
$inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch dispensed history with date range filter
$stmt = $pdo->prepare("
    SELECT md.dispensed_date, 
           md.dispensed_time,
           p.full_name as patient_name,
           i.item_name as medicine,
           md.quantity,
           COALESCE(CONCAT(u.fname, ' ', u.lname), CONCAT(e.first_name, ' ', e.last_name), 'Unknown') as dispensed_by,
           md.purpose
    FROM medicine_dispensed md
    JOIN patients p ON md.patient_id = p.id
    JOIN inventory i ON md.inventory_id = i.id
    LEFT JOIN employees e ON md.dispensed_by = e.id
    LEFT JOIN users u ON e.user_id = u.id
    WHERE DATE(md.dispensed_date) BETWEEN ? AND ?
    ORDER BY md.dispensed_date DESC, md.dispensed_time DESC
");
$stmt->execute([$startDate, $endDate]);
$dispensedHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch current inventory stock (no date filter needed - shows current stock)
$stmt = $pdo->query("
    SELECT 
        i.id,
        i.item_code,
        i.item_name,
        i.quantity,
        i.dispensed,
        (i.quantity - COALESCE(i.dispensed, 0)) as available,
        i.expiry_date,
        i.batch_number,
        i.status
    FROM inventory i
    WHERE i.status = 'active'
    ORDER BY i.item_name ASC
");
$currentStock = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate dispensed totals with date range filter
$stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) as total FROM medicine_dispensed WHERE DATE(dispensed_date) BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$totalDispensed = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) as today FROM medicine_dispensed WHERE DATE(dispensed_date) BETWEEN ? AND ?");
$stmt->execute([$startDate, $endDate]);
$todayDispensed = $stmt->fetch(PDO::FETCH_ASSOC)['today'];

// Current stock statistics (no date filter - shows current inventory)
$stmt = $pdo->query("SELECT COUNT(*) as count FROM inventory WHERE status = 'active'");
$totalStockItems = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->query("SELECT COALESCE(SUM(quantity - COALESCE(dispensed, 0)), 0) as total_available FROM inventory WHERE status = 'active'");
$totalAvailableStock = $stmt->fetch(PDO::FETCH_ASSOC)['total_available'];

// Fetch patient visits by day of week (completed appointments within date range)
$stmt = $pdo->prepare("
    SELECT 
        DAYOFWEEK(appointment_date) as day_of_week,
        COUNT(*) as visit_count
    FROM appointments
    WHERE status = 'completed'
    AND DATE(appointment_date) BETWEEN ? AND ?
    GROUP BY DAYOFWEEK(appointment_date)
    ORDER BY DAYOFWEEK(appointment_date)
");
$stmt->execute([$startDate, $endDate]);
$visitsByDay = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize array for all days of week (Monday=2, Sunday=1 in MySQL DAYOFWEEK)
$patientVisitsData = [0, 0, 0, 0, 0, 0, 0]; // [Mon, Tue, Wed, Thu, Fri, Sat, Sun]
foreach ($visitsByDay as $visit) {
    $dayIndex = $visit['day_of_week'] - 2; // Convert MySQL day (1=Sunday, 2=Monday) to array index (0=Monday)
    if ($dayIndex < 0) $dayIndex = 6; // Sunday becomes index 6
    $patientVisitsData[$dayIndex] = (int)$visit['visit_count'];
}

// Fetch medicine usage trend by month (last 10 months from current date)
$medicineUsageData = [];
$medicineUsageLabels = [];
$currentMonth = date('Y-m');
for ($i = 9; $i >= 0; $i--) {
    $monthDate = date('Y-m', strtotime("-$i months"));
    $monthName = date('M', strtotime("-$i months"));
    $medicineUsageLabels[] = $monthName;
    
    // Get dispensed quantity for this month
    $monthStart = date('Y-m-01', strtotime("-$i months"));
    $monthEnd = date('Y-m-t', strtotime("-$i months"));
    
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(quantity), 0) as total_dispensed
        FROM medicine_dispensed
        WHERE DATE(dispensed_date) BETWEEN ? AND ?
    ");
    $stmt->execute([$monthStart, $monthEnd]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $medicineUsageData[] = (int)$result['total_dispensed'];
}

// Fetch stock distribution by item name (grouped by item_name, sum of available quantity)
$stmt = $pdo->query("
    SELECT 
        item_name,
        SUM(quantity - COALESCE(dispensed, 0)) as available_quantity
    FROM inventory
    WHERE status = 'active'
    GROUP BY item_name
    ORDER BY available_quantity DESC
");
$stockDistributionRaw = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Prepare stock distribution data (top 4 items + others)
$stockDistributionLabels = [];
$stockDistributionData = [];
$colorPalette = ['#6b0000', '#dc3545', '#ffc107', '#28a745', '#17a2b8', '#6f42c1', '#fd7e14', '#20c997'];
$othersTotal = 0;
$itemCount = 0;

// Handle empty inventory
if (empty($stockDistributionRaw)) {
    $stockDistributionLabels = ['No Items'];
    $stockDistributionData = [0];
    $stockDistributionColors = ['#cccccc'];
} else {
    foreach ($stockDistributionRaw as $index => $item) {
        if ($index < 4) {
            // Top 4 items get individual slices
            $stockDistributionLabels[] = $item['item_name'];
            $stockDistributionData[] = (int)$item['available_quantity'];
            $itemCount++;
        } else {
            // Remaining items grouped as "Others"
            $othersTotal += (int)$item['available_quantity'];
        }
    }

    // Add "Others" if there are more than 4 items
    if (count($stockDistributionRaw) > 4 && $othersTotal > 0) {
        $stockDistributionLabels[] = 'Others';
        $stockDistributionData[] = $othersTotal;
        $itemCount++;
    }

    // Generate color array matching the number of items
    $stockDistributionColors = array_slice($colorPalette, 0, $itemCount);
}

// Generate real analytics data based on activity logs
$analyticsData = [];
$totalSessions = 0;
$totalPageviews = 0;
$totalUsers = 0;

// Generate dates between start and end date
$start = new DateTime($startDate);
$end = new DateTime($endDate);
$interval = new DateInterval('P1D');
$endDateCopy = clone $end;
$dateRange = new DatePeriod($start, $interval, $endDateCopy->modify('+1 day'));

// Get all activity logs for the date range
try {
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as log_date,
            COUNT(*) as pageviews,
            COUNT(DISTINCT user_id) as unique_users,
            COUNT(DISTINCT CONCAT(user_id, '-', DATE(created_at), '-', ip_address)) as sessions
        FROM user_activity_logs
        WHERE DATE(created_at) BETWEEN ? AND ?
        GROUP BY DATE(created_at)
        ORDER BY log_date ASC
    ");
    $stmt->execute([$startDate, $endDate]);
    $activityStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create a map of date => stats for quick lookup
    $statsMap = [];
    foreach ($activityStats as $stat) {
        $statsMap[$stat['log_date']] = [
            'sessions' => (int)$stat['sessions'],
            'pageviews' => (int)$stat['pageviews'],
            'users' => (int)$stat['unique_users']
        ];
    }
    
    // Generate data for each date in range
    foreach ($dateRange as $date) {
        $dateStr = $date->format('Y-m-d');
        
        // Get stats for this date or use 0 if no data
        if (isset($statsMap[$dateStr])) {
            $sessions = $statsMap[$dateStr]['sessions'];
            $pageviews = $statsMap[$dateStr]['pageviews'];
            $users = $statsMap[$dateStr]['users'];
        } else {
            $sessions = 0;
            $pageviews = 0;
            $users = 0;
        }
        
        $totalSessions += $sessions;
        $totalPageviews += $pageviews;
        // Don't sum users here - we'll calculate unique users across entire period separately
        
        $analyticsData[] = [
            'date' => $dateStr,
            'sessions' => $sessions,
            'pageviews' => $pageviews,
            'users' => $users
        ];
    }
    
    // Calculate total unique users across entire period (not sum of daily unique users)
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT user_id) as total_unique_users
        FROM user_activity_logs
        WHERE DATE(created_at) BETWEEN ? AND ?
    ");
    $stmt->execute([$startDate, $endDate]);
    $uniqueUsersResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalUsers = (int)$uniqueUsersResult['total_unique_users'];
    
} catch (PDOException $e) {
    // Fallback to empty data if table doesn't exist or query fails
    error_log("Analytics query error: " . $e->getMessage());
    foreach ($dateRange as $date) {
        $dateStr = $date->format('Y-m-d');
        $analyticsData[] = [
            'date' => $dateStr,
            'sessions' => 0,
            'pageviews' => 0,
            'users' => 0
        ];
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reports & Analytics</title>

  <!-- Stylesheets -->
  <link href="../admin/css/reports.css" rel="stylesheet" />
  <link href="../admin/css/responsive.css" rel="stylesheet">
  <link href="/finalproject/css/nav.css" rel="stylesheet" />
  <link href="../admin/css/notifications.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Text:ital@0;1&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  
  <!-- DataTables CDN -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />
  <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  
  <!-- Print Stylesheet -->
  <style>
    /* Print Styles - Only show active tab content */
    @media print {
      /* Hide all UI elements - ensure no space taken */
      .header,
      .sidebar,
      .header-icons,
      .user-profile,
      .report-tabs,
      .date-range-filters,
      .btn,
      button,
      .mb-3:has(.btn),
      .print-controls,
      .nav-tabs,
      .dataTables_wrapper .dataTables_filter,
      .dataTables_wrapper .dataTables_length,
      .dataTables_wrapper .dataTables_paginate,
      .dataTables_wrapper .dataTables_info {
        display: none !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        visibility: hidden !important;
      }
      
      /* Reset body and page layout */
      body {
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        font-size: 12pt;
        line-height: 1.4;
        color: #000;
      }
      
      /* Show only dashboard content - remove all spacing */
      .main-container {
        display: block !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        height: auto !important;
      }
      
      .dashboard-content {
        width: 100% !important;
        margin: 0 !important;
        padding: 0 !important;
        background: white !important;
        overflow: visible !important;
        height: auto !important;
        min-height: auto !important;
      }
      
      /* Hide all tabs except active one - ensure no space taken */
      .tab-content {
        display: none !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
      }
      
      .tab-content.active {
        display: block !important;
        height: auto !important;
        margin: 0 !important;
        padding: 0 !important;
        page-break-inside: auto !important;
      }
      
      /* Hide print header - redundant */
      .print-header {
        display: none !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        visibility: hidden !important;
      }
      
      /* Hide welcome text - redundant */
      .welcome-text {
        display: none !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        visibility: hidden !important;
      }
      
      /* Hide redundant report titles */
      h5.mt-4 {
        display: none !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        visibility: hidden !important;
      }
      
      /* Chart and analytics box titles - center aligned, reduced padding */
      .chart-box h6,
      h6 {
        font-size: 14pt;
        font-weight: bold;
        margin-top: 10px !important;
        margin-bottom: 8px !important;
        padding-top: 0 !important;
        color: #000;
        page-break-after: avoid;
        text-align: center !important;
      }
      
      /* Stats row - organized layout */
      .stats-row {
        display: flex;
        gap: 15px;
        margin: 20px 0 25px 0;
        page-break-inside: avoid;
        width: 100%;
        clear: both;
      }
      
      .stat-card {
        background: #f5f5f5 !important;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        flex: 1;
        text-align: center;
        page-break-inside: avoid;
        min-width: 0;
      }
      
      .stat-card h2 {
        font-size: 24pt;
        font-weight: bold;
        color: #000;
        margin: 0;
        text-align: center;
        line-height: 1.2;
      }
      
      .stat-card p {
        font-size: 10pt;
        color: #333;
        margin: 5px 0 0 0;
        text-align: center;
      }
      
      /* Charts - full width, stacked, organized */
      .chart-box {
        background: white !important;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        margin: 0 0 20px 0;
        page-break-inside: avoid;
        height: auto !important;
        min-height: 200px;
        width: 100% !important;
        max-width: 100% !important;
        display: block !important;
        clear: both !important;
        position: relative;
      }
      
      .chart-box h6 {
        font-size: 14pt;
        font-weight: bold;
        margin: 0 0 15px 0;
        padding: 0;
        color: #000;
        text-align: center !important;
        page-break-after: avoid;
      }
      
      .chart-box canvas {
        width: 100% !important;
        height: auto !important;
        max-height: 300px;
        page-break-inside: avoid;
        display: block;
        margin: 0 auto;
      }
      
      /* Chart filter select - hide in print */
      .chart-box .chart-filter,
      .chart-box select {
        display: none !important;
      }
      
      /* Ensure Chart.js charts are visible when printing */
      canvas {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
      }
      
      /* Tables - organized layout */
      .table-container {
        background: white !important;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 15px;
        margin: 20px 0 25px 0;
        page-break-inside: auto;
        overflow: visible !important;
        width: 100%;
        clear: both;
      }
      
      .table-container h6 {
        font-size: 12pt;
        font-weight: bold;
        margin: 0 0 12px 0;
        padding: 0;
        color: #000;
        page-break-after: avoid;
      }
      
      table {
        width: 100% !important;
        border-collapse: collapse;
        font-size: 10pt;
        page-break-inside: auto;
      }
      
      table thead {
        display: table-header-group;
        background: #f5f5f5 !important;
      }
      
      table thead th {
        background: #e0e0e0 !important;
        color: #000 !important;
        font-weight: bold;
        padding: 8px;
        border: 1px solid #000;
        text-align: left;
      }
      
      table tbody tr {
        page-break-inside: avoid;
        page-break-after: auto;
      }
      
      table tbody td {
        padding: 6px;
        border: 1px solid #ddd;
        color: #000;
      }
      
      table tbody tr:nth-child(even) {
        background: #f9f9f9 !important;
      }
      
      /* Ensure DataTables doesn't interfere */
      .dataTables_wrapper {
        overflow: visible !important;
      }
      
      .dataTables_wrapper table {
        width: 100% !important;
      }
      
      /* Row layout - organized stacking for print */
      .row {
        display: block !important;
        margin: 0 0 20px 0 !important;
        padding: 0 !important;
        page-break-inside: auto;
        width: 100% !important;
        clear: both;
      }
      
      .row:last-child {
        margin-bottom: 0 !important;
      }
      
      .col-md-4, .col-md-6, .col-md-12 {
        width: 100% !important;
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 0 20px 0 !important;
        display: block !important;
        float: none !important;
        clear: both !important;
      }
      
      .col-md-4:last-child,
      .col-md-6:last-child,
      .col-md-12:last-child {
        margin-bottom: 0 !important;
      }
      
      /* Remove any Bootstrap spacing that might cause issues */
      .mt-4 {
        margin-top: 0 !important;
      }
      
      .mb-3 {
        margin-bottom: 0 !important;
      }
      
      .mb-2 {
        margin-bottom: 0 !important;
      }
      
      /* Form elements */
      .form-select,
      select {
        display: none !important;
      }
      
      /* Remove shadows and backgrounds */
      * {
        box-shadow: none !important;
        text-shadow: none !important;
      }
      
      /* Page breaks - organized flow */
      .stats-row {
        page-break-inside: avoid;
        page-break-after: auto;
      }
      
      .chart-box {
        page-break-inside: avoid;
        page-break-after: auto;
      }
      
      .table-container {
        page-break-inside: auto;
        page-break-after: auto;
      }
      
      /* Prevent orphaned rows - but allow table to break */
      table tbody tr {
        page-break-inside: avoid;
        page-break-after: auto;
      }
      
      /* Keep table headers on each page */
      table thead {
        display: table-header-group;
      }
      
      table tfoot {
        display: table-footer-group;
      }
      
      /* Only avoid breaking within individual stat cards */
      .stat-card {
        page-break-inside: avoid;
      }
      
      /* Ensure proper element flow */
      .tab-content.active {
        display: block !important;
      }
      
      /* Consistent spacing between sections */
      .tab-content.active > * {
        margin-top: 0;
        margin-bottom: 20px;
      }
      
      .tab-content.active > *:last-child {
        margin-bottom: 0;
      }
      
      /* Specific spacing for organized layout */
      .tab-content.active .print-report-title {
        margin-bottom: 20px !important;
      }
      
      .tab-content.active .stats-row {
        margin-top: 0 !important;
        margin-bottom: 25px !important;
      }
      
      .tab-content.active .row {
        margin-top: 0 !important;
        margin-bottom: 20px !important;
      }
      
      .tab-content.active .table-container {
        margin-top: 0 !important;
        margin-bottom: 0 !important;
      }
      
      /* Print page setup with space for header and footer */
      @page {
        margin: 1.5cm 0.8cm 1.5cm 0.8cm;
        size: A4;
      }
      
      /* Print header - date only, appears on each page */
      .print-header {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        display: block !important;
        font-size: 9pt;
        color: #000;
        text-align: center;
        padding: 5px 0;
        border-bottom: 1px solid #ccc;
        background: white;
        z-index: 1000;
        height: auto;
        margin: 0;
      }
      
      /* Print footer - page number only, appears on each page */
      .print-footer {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        display: block !important;
        font-size: 9pt;
        color: #000;
        text-align: center;
        padding: 5px 0;
        border-top: 1px solid #ccc;
        background: white;
        z-index: 1000;
        height: auto;
        margin: 0;
      }
      
      /* Add top padding to content to account for header */
      .dashboard-content {
        padding-top: 25px !important;
      }
      
      /* Add bottom padding to content to account for footer */
      .tab-content.active {
        padding-bottom: 25px !important;
      }
      
      /* Report title - show at top of print */
      .print-report-title {
        display: block !important;
        font-size: 20pt;
        font-weight: bold;
        text-align: center;
        margin: 0 0 20px 0;
        padding: 0 0 12px 0;
        border-bottom: 2px solid #000;
        color: #000;
        page-break-after: avoid;
        width: 100%;
        clear: both;
      }
      
      /* Ensure proper spacing after title */
      .print-report-title + .stats-row,
      .print-report-title + .row,
      .print-report-title + .table-container {
        margin-top: 20px !important;
      }
      
      /* Remove any top margin from first visible element if no title */
      .tab-content.active > .stats-row:first-child:not(:has(+ .print-report-title)),
      .tab-content.active > .chart-box:first-child:not(:has(+ .print-report-title)),
      .tab-content.active > .table-container:first-child:not(:has(+ .print-report-title)),
      .tab-content.active > .row:first-child:not(:has(+ .print-report-title)) {
        margin-top: 0 !important;
      }
      
      /* Reduce padding near headings for cleaner look */
      .chart-box h6,
      h6 {
        margin-top: 10px !important;
        margin-bottom: 8px !important;
        padding-top: 0 !important;
      }
      
      .chart-box {
        padding-top: 10px !important;
      }
      
      .table-container h6 {
        margin-top: 10px !important;
        margin-bottom: 8px !important;
      }
      
      /* Date/time stamp - hidden in print */
      .print-footer {
        display: none !important;
        height: 0 !important;
        margin: 0 !important;
        padding: 0 !important;
        visibility: hidden !important;
      }
      
      /* Ensure visibility */
      .tab-content.active * {
        visibility: visible !important;
        opacity: 1 !important;
      }
    }
    
    /* Screen-only styles for print button visibility */
    @media screen {
      .print-only {
        display: none;
      }
    }
    
    /* Date range filter styles */
    .date-range-filters {
      background: #f8f9fa;
      padding: 15px;
      border-radius: 5px;
      margin-bottom: 20px;
    }
    
    .date-range-filters .form-label {
      font-weight: 600;
      margin-bottom: 5px;
      color: #333;
    }
    
    /* Analytics Tab - Uniform Layout Styles */
    #analyticsTab {
      display: flex;
      flex-direction: column;
      gap: 0;
      width: 100%;
      box-sizing: border-box;
    }
    
    /* Stats Row - Uniform Layout */
    #analyticsTab .stats-row {
      display: flex;
      gap: 20px;
      margin: 25px 0;
      align-items: stretch;
      width: 100%;
      flex-wrap: wrap;
      box-sizing: border-box;
    }
    
    /* Stat Cards - Uniform Sizing */
    #analyticsTab .stat-card {
      background: #d9d9d9;
      border-radius: 8px;
      padding: 30px;
      flex: 1 1 0;
      min-width: 200px;
      text-align: center;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-height: 150px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      margin: 0;
      box-sizing: border-box;
      position: relative;
      overflow: hidden;
    }
    
    #analyticsTab .stat-card h2 {
      font-weight: 700;
      font-size: 36px;
      color: #000;
      margin: 0;
      line-height: 1.2;
      flex-shrink: 0;
    }
    
    #analyticsTab .stat-card p {
      font-size: 16px;
      color: #333;
      margin: 10px 0 0 0;
      flex-shrink: 0;
    }
    
    /* Chart Box - Fixed Container */
    #analyticsTab .chart-box {
      background-color: #fff;
      padding: 25px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      margin: 20px 0;
      display: flex;
      flex-direction: column;
      min-height: 400px;
      height: 400px;
      max-height: 400px;
      position: relative;
      width: 100%;
      box-sizing: border-box;
      overflow: hidden;
    }
    
    #analyticsTab .chart-box h6 {
      margin-bottom: 20px;
      font-weight: 600;
      color: #333;
      font-size: 18px;
      text-align: center;
      flex-shrink: 0;
      padding: 0;
      margin-top: 0;
    }
    
    /* Chart Wrapper Container */
    #analyticsTab .chart-box .chart-wrapper {
      flex: 1 1 auto;
      position: relative;
      width: 100%;
      min-height: 0;
      max-height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      box-sizing: border-box;
    }
    
    /* Chart Canvas - Responsive Sizing */
    #analyticsTab .chart-box canvas {
      position: relative;
      max-width: 100%;
      width: 100% !important;
      height: 100% !important;
      min-height: 0;
      max-height: 100%;
      display: block;
      margin: 0 auto;
      box-sizing: border-box;
    }
    
    /* Direct canvas in chart-box (without wrapper) */
    #analyticsTab .chart-box > canvas {
      position: absolute;
      top: 60px;
      left: 25px;
      right: 25px;
      bottom: 25px;
      width: calc(100% - 50px) !important;
      height: calc(100% - 85px) !important;
      max-width: calc(100% - 50px);
      max-height: calc(100% - 85px);
    }
    
    /* Table Container - Consistent Spacing */
    #analyticsTab .table-container {
      margin-top: 30px;
      background-color: #fff;
      padding: 25px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      width: 100%;
      box-sizing: border-box;
    }
    
    #analyticsTab .table-container h6 {
      margin-bottom: 15px;
      font-weight: 600;
      color: #333;
      font-size: 18px;
      margin-top: 0;
      padding: 0;
    }
    
    /* Row and Column Layout */
    #analyticsTab .row {
      margin: 0;
      width: 100%;
      box-sizing: border-box;
    }
    
    #analyticsTab .row .col-md-12 {
      padding: 0;
      width: 100%;
      box-sizing: border-box;
    }
    
    /* Ensure proper spacing between sections */
    #analyticsTab h5.mt-4 {
      margin-top: 0;
      margin-bottom: 20px;
      font-weight: 600;
      color: #333;
    }
    
    /* Responsive Design */
    @media (max-width: 768px) {
      #analyticsTab .stats-row {
        flex-direction: column;
        gap: 15px;
      }
      
      #analyticsTab .stat-card {
        min-width: 100%;
        flex: 1 1 auto;
      }
      
      #analyticsTab .chart-box {
        min-height: 300px;
        height: 300px;
        max-height: 300px;
        padding: 20px;
      }
      
      #analyticsTab .chart-box > canvas {
        top: 50px;
        left: 20px;
        right: 20px;
        bottom: 20px;
        width: calc(100% - 40px) !important;
        height: calc(100% - 70px) !important;
      }
    }
    
    @media (min-width: 769px) and (max-width: 1024px) {
      #analyticsTab .stat-card {
        min-width: calc(33.333% - 14px);
      }
    }
    
    /* ============================================
       GLOBAL STYLES FOR ALL TABS
       ============================================ */
    
    /* Tab Content Base Styles - Only show active tab */
    .tab-content {
      display: none;
      width: 100%;
      box-sizing: border-box;
      position: relative;
    }
    
    .tab-content.active {
      display: block;
    }
    
    /* Force hide inactive tabs to prevent any visibility issues */
    .tab-content:not(.active) {
      display: none !important;
    }
    
    /* Stats Row - Global */
    .stats-row {
      display: flex;
      gap: 20px;
      margin: 20px 0;
      align-items: stretch;
      width: 100%;
      flex-wrap: wrap;
      box-sizing: border-box;
    }
    
    /* Stat Card - Global */
    .stat-card {
      background: #d9d9d9;
      border-radius: 8px;
      padding: 25px;
      flex: 1 1 0;
      min-width: 200px;
      text-align: center;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
      min-height: 120px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      margin: 0;
      box-sizing: border-box;
    }
    
    .stat-card h2 {
      font-weight: 700;
      font-size: 32px;
      color: #000;
      margin: 0;
      line-height: 1.2;
    }
    
    .stat-card p {
      font-size: 14px;
      color: #333;
      margin: 8px 0 0 0;
    }
    
    /* Chart Box - Global Base */
    .chart-box {
      background-color: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      margin: 0;
      display: flex;
      flex-direction: column;
      position: relative;
      width: 100%;
      box-sizing: border-box;
      overflow: hidden;
    }
    
    .chart-box h6 {
      margin: 0 0 15px 0;
      padding: 0;
      font-weight: 600;
      color: #333;
      font-size: 16px;
      text-align: center;
      flex-shrink: 0;
      line-height: 1.2;
    }
    
    .chart-box canvas {
      position: absolute;
      box-sizing: border-box;
      display: block;
    }
    
    /* Table Container - Global */
    .table-container {
      margin-top: 20px;
      background-color: #fff;
      padding: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
      width: 100%;
      box-sizing: border-box;
    }
    
    .table-container h6 {
      margin: 0 0 15px 0;
      padding: 0;
      font-weight: 600;
      color: #333;
      font-size: 16px;
    }
    
    /* Row Layout - Global */
    .row {
      margin: 0;
      width: 100%;
      box-sizing: border-box;
      display: flex;
      flex-wrap: wrap;
    }
    
    /* ============================================
       PATIENTS TAB STYLES
       ============================================ */
    
    #patientsTab .row .col-md-4 {
      padding: 0 10px;
      margin-bottom: 20px;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
    }
    
    #patientsTab .chart-box {
      min-height: 350px;
      height: 350px;
      max-height: 350px;
    }
    
    #patientsTab .chart-box canvas {
      top: 50px;
      left: 20px;
      right: 20px;
      bottom: 20px;
      width: calc(100% - 40px) !important;
      height: calc(100% - 70px) !important;
      max-width: calc(100% - 40px) !important;
      max-height: calc(100% - 70px) !important;
    }
    
    /* Chart with filter select */
    #patientsTab .chart-box .chart-filter {
      flex-shrink: 0;
      margin-bottom: 10px;
      width: 100%;
      max-width: 150px;
      margin-left: auto;
      margin-right: auto;
      position: relative;
      z-index: 1;
    }
    
    #patientsTab .chart-box:has(.chart-filter) canvas {
      top: 90px;
      height: calc(100% - 110px) !important;
    }
    
    /* ============================================
       INVENTORY TAB STYLES
       ============================================ */
    
    #inventoryTab .row .col-md-6 {
      padding: 0 10px;
      margin-bottom: 20px;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
    }
    
    #inventoryTab .chart-box {
      min-height: 350px;
      height: 350px;
      max-height: 350px;
    }
    
    #inventoryTab .chart-box canvas {
      top: 50px;
      left: 20px;
      right: 20px;
      bottom: 20px;
      width: calc(100% - 40px) !important;
      height: calc(100% - 70px) !important;
      max-width: calc(100% - 40px) !important;
      max-height: calc(100% - 70px) !important;
    }
    
  
    
    @media (max-width: 768px) {
      /* Global responsive */
      .stats-row {
        flex-direction: column;
        gap: 15px;
      }
      
      .stat-card {
        min-width: 100%;
        flex: 1 1 auto;
      }
      
      .chart-box {
        padding: 15px;
      }
      
      /* Patients Tab Responsive */
      #patientsTab .row .col-md-4 {
        padding: 0 5px;
        margin-bottom: 15px;
        width: 100% !important;
        flex: 0 0 100%;
      }
      
      #patientsTab .chart-box {
        min-height: 300px;
        height: 300px;
        max-height: 300px;
      }
      
      #patientsTab .chart-box canvas {
        top: 45px;
        left: 15px;
        right: 15px;
        bottom: 15px;
        width: calc(100% - 30px) !important;
        height: calc(100% - 60px) !important;
      }
      
      #patientsTab .chart-box:has(.chart-filter) canvas {
        top: 85px;
        height: calc(100% - 100px) !important;
      }
      
      /* Inventory Tab Responsive */
      #inventoryTab .row .col-md-6 {
        padding: 0 5px;
        margin-bottom: 15px;
        width: 100% !important;
        flex: 0 0 100%;
      }
      
      #inventoryTab .chart-box {
        min-height: 300px;
        height: 300px;
        max-height: 300px;
      }
      
      #inventoryTab .chart-box canvas {
        top: 45px;
        left: 15px;
        right: 15px;
        bottom: 15px;
        width: calc(100% - 30px) !important;
        height: calc(100% - 60px) !important;
      }
    }
    
    @media (min-width: 769px) and (max-width: 1024px) {
      #patientsTab .chart-box {
        min-height: 320px;
        height: 320px;
        max-height: 320px;
      }
      
      #inventoryTab .chart-box {
        min-height: 320px;
        height: 320px;
        max-height: 320px;
      }
    }
  </style>
</head>

<body>
  <!-- HEADER -->
  <div class="header">
    <div class="logo-section">
      <div class="logo">
        <img src="../img/bsu-logo.png" alt="University Logo" />
      </div>
      <div class="university-name">
        <h1>Batangas State</h1>
        <h1>University</h1>
      </div>
    </div>

    <!-- Right-side icons -->
    <div class="header-icons">
      <!-- Mobile Menu Icon -->
      <button type="button" class="mobile-menu-icon" id="mobileMenuBtn" aria-label="Toggle navigation menu" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>
      <?php include 'notification_component.php'; ?>
      <div class="logout-icon" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></div>
    </div>
  </div>

  <!-- SIDEBAR + MAIN CONTENT -->
  <div class="main-container">
    <div class="sidebar">
      <div>
        <a href="../admin/adminDashboard.php" class="menu-item">Dashboard</a>
        <a href="../admin/patients.php" class="menu-item">Patient</a>
        <a href="../admin/userManagement.php" class="menu-item">User Management</a>
        <a href="../admin/inventory.php" class="menu-item">Inventory</a>
        <a href="../admin/activity_logs.php" class="menu-item">Activity Logs</a>
        <a href="../admin/reports.php" class="menu-item active">Reports & Analytics</a>
      </div>
      <div class="user-profile">
        <span><?php echo htmlspecialchars($userName); ?></span>
      </div>
    </div>

      <!-- MAIN DASHBOARD -->
    <main class="dashboard-content" data-report-title="Reports" data-print-date="<?php echo date('Y-m-d H:i:s'); ?>">
      <h2 class="welcome-text">Reports</h2>

      <div class="mb-3">
        <button class="btn btn-light btn-sm" onclick="printCurrentTab()"><i class="bi bi-printer"></i> Print Current Tab</button>
      </div>

      <ul class="nav nav-tabs report-tabs" id="reportTabs">
        <li class="nav-item"><a class="nav-link active" data-target="patientsTab">Patients</a></li>
        <li class="nav-item"><a class="nav-link" data-target="inventoryTab">Inventory</a></li>
        <li class="nav-item"><a class="nav-link" data-target="dispensedTab">Dispensed</a></li>
        <li class="nav-item"><a class="nav-link" data-target="analyticsTab">Analytics</a></li>
      </ul>

      <!-- PATIENTS TAB -->
      <div id="patientsTab" class="tab-content active">
        <div class="date-range-filters mb-3">
          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label for="startDate" class="form-label">Start Date</label>
              <input type="date" class="form-control" id="startDate" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
            </div>
            <div class="col-md-4">
              <label for="endDate" class="form-label">End Date</label>
              <input type="date" class="form-control" id="endDate" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
            <div class="col-md-4">
              <button type="button" class="btn btn-primary" onclick="applyDateRange()">Apply Filter</button>
            </div>
          </div>
        </div>

        <h5 class="mt-4">Patients Report</h5>

        <div class="stats-row">
          <div class="stat-card">
            <h2 id="totalPatients"><?php echo number_format($totalPatients); ?></h2>
            <p>Total Patients</p>
          </div>
          <div class="stat-card">
            <h2 id="newPatients"><?php echo number_format($newPatients); ?></h2>
            <p>New Patients (Last 30 Days)</p>
          </div>
        </div>

        <div class="row mt-4">
          <div class="col-md-6">
            <div class="chart-box">
              <h6>Patient Visits</h6>
              <canvas id="patientVisitsChart"></canvas>
            </div>
          </div>

          <div class="col-md-6">
            <div class="chart-box">
              <h6>Monthly Appointments Trend</h6>
              <select class="form-select form-select-sm chart-filter mb-2" id="yearFilter">
                <option value="2025">2025</option>
                <option value="2024">2024</option>
                <option value="2023">2023</option>
              </select>
              <canvas id="lineChart"></canvas>
            </div>
          </div>
        </div>

        <div class="table-container">
          <h6 class="mb-3">Patient Details</h6>
          <table class="table table-bordered" id="patientsTable">
            <thead class="table-light">
              <tr>
                <th>Patient ID</th>
                <th>Name</th>
                <th>Course</th>
                <th>Year Level</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($patientDetails as $patient): ?>
              <tr>
                <td><?php echo htmlspecialchars($patient['sr_code']); ?></td>
                <td><?php echo htmlspecialchars($patient['full_name']); ?></td>
                <td><?php echo htmlspecialchars($patient['course'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($patient['year_level'] ?? 'N/A'); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- INVENTORY TAB -->
      <div id="inventoryTab" class="tab-content">
        <div class="date-range-filters mb-3">
          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label for="startDateInventory" class="form-label">Start Date</label>
              <input type="date" class="form-control" id="startDateInventory" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
            </div>
            <div class="col-md-4">
              <label for="endDateInventory" class="form-label">End Date</label>
              <input type="date" class="form-control" id="endDateInventory" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
            <div class="col-md-4">
              <button type="button" class="btn btn-primary" onclick="applyDateRange()">Apply Filter</button>
            </div>
          </div>
        </div>

        <h5 class="mt-4">Inventory Report</h5>

        <div class="stats-row">
          <div class="stat-card">
            <h2><?php echo number_format($totalStockItems); ?></h2>
            <p>Total Stock Items</p>
          </div>
          <div class="stat-card">
            <h2><?php echo number_format($totalAvailableStock); ?></h2>
            <p>Total Available Stock</p>
          </div>
        </div>

        <div class="row mt-4">
          <div class="col-md-6">
            <div class="chart-box" style="height: 350px;">
              <h6>Medicine Usage Trend</h6>
              <canvas id="medicineUsageChart"></canvas>
            </div>
          </div>
          <div class="col-md-6">
            <div class="chart-box" style="height: 350px;">
              <h6>Stock Distribution</h6>
              <canvas id="stockDistributionChart"></canvas>
            </div>
          </div>
        </div>

        <div class="table-container">
          <h6 class="mb-3">Inventory Details</h6>
          <table class="table table-bordered" id="inventoryTable">
            <thead class="table-light">
              <tr>
                <th>Item Code</th>
                <th>Item Name</th>
                <th>Batch Number</th>
                <th>Total Quantity</th>
                <th>Dispensed</th>
                <th>Available</th>
                <th>Expiry Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($inventoryItems as $item): ?>
              <tr>
                <td><?php echo htmlspecialchars($item['item_code'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($item['item_name'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($item['batch_number'] ?? 'N/A'); ?></td>
                <td><?php echo number_format($item['total_quantity'] ?? 0); ?></td>
                <td><?php echo number_format($item['dispensed'] ?? 0); ?></td>
                <td><strong><?php echo number_format($item['available'] ?? 0); ?></strong></td>
                <td><?php echo !empty($item['expiry_date']) ? date('Y-m-d', strtotime($item['expiry_date'])) : 'N/A'; ?></td>
                <td>
                  <span class="badge bg-<?php echo ($item['status'] ?? '') === 'active' ? 'success' : 'secondary'; ?>">
                    <?php echo htmlspecialchars(ucfirst($item['status'] ?? 'N/A')); ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- DISPENSED TAB -->
      <div id="dispensedTab" class="tab-content">
        <div class="date-range-filters mb-3">
          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label for="startDateDispensed" class="form-label">Start Date</label>
              <input type="date" class="form-control" id="startDateDispensed" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
            </div>
            <div class="col-md-4">
              <label for="endDateDispensed" class="form-label">End Date</label>
              <input type="date" class="form-control" id="endDateDispensed" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
            <div class="col-md-4">
              <button type="button" class="btn btn-primary" onclick="applyDateRange()">Apply Filter</button>
            </div>
          </div>
        </div>

        <h5 class="mt-4">Dispensed Medicine Report</h5>

        <div class="stats-row">
          <div class="stat-card">
            <h2><?php echo number_format($totalDispensed); ?></h2>
            <p>Total Dispensed</p>
          </div>
          <div class="stat-card">
            <h2><?php echo number_format($todayDispensed); ?></h2>
            <p>Today's Dispensed</p>
          </div>
        </div>

        <div class="table-container">
          <h6 class="mb-3">Dispensed History</h6>
          <table class="table table-bordered" id="dispensedTable">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Patient Name</th>
                <th>Medicine</th>
                <th>Quantity</th>
                <th>Dispensed By</th>
                <th>Purpose</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dispensedHistory as $record): ?>
              <tr>
                <td><?php 
                  $dispensedDateTime = $record['dispensed_date'];
                  if (!empty($record['dispensed_time'])) {
                    $dispensedDateTime .= ' ' . $record['dispensed_time'];
                  }
                  echo date('Y-m-d H:i', strtotime($dispensedDateTime)); 
                ?></td>
                <td><?php echo htmlspecialchars($record['patient_name']); ?></td>
                <td><?php echo htmlspecialchars($record['medicine']); ?></td>
                <td><?php echo number_format($record['quantity']); ?></td>
                <td><?php echo htmlspecialchars($record['dispensed_by']); ?></td>
                <td><?php echo htmlspecialchars($record['purpose'] ?? 'N/A'); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ANALYTICS TAB -->
      <div id="analyticsTab" class="tab-content">
        <div class="date-range-filters mb-3">
          <div class="row g-3 align-items-end">
            <div class="col-md-4">
              <label for="startDateAnalytics" class="form-label">Start Date</label>
              <input type="date" class="form-control" id="startDateAnalytics" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>">
            </div>
            <div class="col-md-4">
              <label for="endDateAnalytics" class="form-label">End Date</label>
              <input type="date" class="form-control" id="endDateAnalytics" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
            <div class="col-md-4">
              <button type="button" class="btn btn-primary" onclick="applyDateRange()">Apply Filter</button>
            </div>
          </div>
        </div>

        <h5 class="mt-4">System Analytics Report</h5>

        <!-- Summary Statistics -->
        <div class="stats-row">
            <div class="stat-card">
                <h2><?php echo number_format($totalSessions); ?></h2>
                <p>Total Sessions</p>
            </div>
            <div class="stat-card">
                <h2><?php echo number_format($totalPageviews); ?></h2>
                <p>Page Views</p>
            </div>
            <div class="stat-card">
                <h2><?php echo number_format($totalUsers); ?></h2>
                <p>Total Users</p>
            </div>
        </div>

        <!-- Analytics Chart -->
        <div class="row mt-4">
            <div class="col-md-12">
                <div class="chart-box">
                    <h6>Sessions & Page Views Trend</h6>
                    <canvas id="analyticsChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Detailed Table -->
        <div class="table-container mt-4">
            <h6 class="mb-3">Analytics Details (<?php echo date('M d, Y', strtotime($startDate)); ?> - <?php echo date('M d, Y', strtotime($endDate)); ?>)</h6>
            <table class="table table-bordered" id="analyticsTable">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Sessions</th>
                        <th>Page Views</th>
                        <th>Users</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($analyticsData as $row): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['date']); ?></td>
                        <td><?php echo number_format($row['sessions']); ?></td>
                        <td><?php echo number_format($row['pageviews']); ?></td>
                        <td><?php echo number_format($row['users']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script>
  <script src="../admin/js/notifications.js"></script>
  
  <script>
    // Function to get active date range from current tab
    function getActiveDateRange() {
      const activeTab = document.querySelector('.tab-content.active');
      if (!activeTab) return '';
      
      // Find date inputs in the current tab
      let startDateInput, endDateInput;
      
      if (activeTab.id === 'patientsTab') {
        startDateInput = document.getElementById('startDate');
        endDateInput = document.getElementById('endDate');
      } else if (activeTab.id === 'inventoryTab') {
        startDateInput = document.getElementById('startDateInventory');
        endDateInput = document.getElementById('endDateInventory');
      } else if (activeTab.id === 'dispensedTab') {
        startDateInput = document.getElementById('startDateDispensed');
        endDateInput = document.getElementById('endDateDispensed');
      } else if (activeTab.id === 'analyticsTab') {
        startDateInput = document.getElementById('startDateAnalytics');
        endDateInput = document.getElementById('endDateAnalytics');
      }
      
      if (startDateInput && endDateInput && startDateInput.value && endDateInput.value) {
        const startDate = new Date(startDateInput.value);
        const endDate = new Date(endDateInput.value);
        const startFormatted = startDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        const endFormatted = endDate.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
        return ' - ' + startFormatted + ' to ' + endFormatted;
      }
      
      return '';
    }
    
    // Function to apply date range filter
    function applyDateRange() {
      const activeTab = document.querySelector('.tab-content.active');
      if (!activeTab) return;
      
      let startDateInput, endDateInput;
      
      if (activeTab.id === 'patientsTab') {
        startDateInput = document.getElementById('startDate');
        endDateInput = document.getElementById('endDate');
      } else if (activeTab.id === 'inventoryTab') {
        startDateInput = document.getElementById('startDateInventory');
        endDateInput = document.getElementById('endDateInventory');
      } else if (activeTab.id === 'dispensedTab') {
        startDateInput = document.getElementById('startDateDispensed');
        endDateInput = document.getElementById('endDateDispensed');
      } else if (activeTab.id === 'analyticsTab') {
        startDateInput = document.getElementById('startDateAnalytics');
        endDateInput = document.getElementById('endDateAnalytics');
      }
      
      if (startDateInput && endDateInput) {
        const startDate = startDateInput.value;
        const endDate = endDateInput.value;
        
        if (!startDate || !endDate) {
          alert('Please select both start and end dates');
          return;
        }
        
        if (new Date(startDate) > new Date(endDate)) {
          alert('Start date cannot be after end date');
          return;
        }
        
        // Reload page with date range parameters
        window.location.href = '?start_date=' + startDate + '&end_date=' + endDate;
      }
    }
    
    // Sync date inputs across all tabs when changed
    function syncDateInputs() {
      const allStartInputs = [
        document.getElementById('startDate'),
        document.getElementById('startDateInventory'),
        document.getElementById('startDateDispensed'),
        document.getElementById('startDateAnalytics')
      ];
      
      const allEndInputs = [
        document.getElementById('endDate'),
        document.getElementById('endDateInventory'),
        document.getElementById('endDateDispensed'),
        document.getElementById('endDateAnalytics')
      ];
      
      allStartInputs.forEach(input => {
        if (input) {
          input.addEventListener('change', function() {
            allStartInputs.forEach(inp => {
              if (inp && inp !== this) inp.value = this.value;
            });
          });
        }
      });
      
      allEndInputs.forEach(input => {
        if (input) {
          input.addEventListener('change', function() {
            allEndInputs.forEach(inp => {
              if (inp && inp !== this) inp.value = this.value;
            });
          });
        }
      });
    }
    
    // Print function - add report title, header, and footer
    function printCurrentTab() {
      // Get tab name for report title
      let tabName = 'Reports';
      const activeTabLink = document.querySelector('#reportTabs .nav-link.active');
      if (activeTabLink) {
        const tabText = activeTabLink.textContent.trim();
        // Map tab names to report titles
        const titleMap = {
          'Patients': 'Patients Report',
          'Inventory': 'Inventory Report',
          'Dispensed': 'Dispensed Medicine Report',
          'Analytics': 'System Analytics Report'
        };
        tabName = titleMap[tabText] || tabText + ' Report';
      }
      
      // Get active date range
      const dateRange = getActiveDateRange();
      
      // Create or update print report title
      let printTitle = document.querySelector('.print-report-title');
      const activeTab = document.querySelector('.tab-content.active');
      if (activeTab) {
        if (!printTitle) {
          printTitle = document.createElement('div');
          printTitle.className = 'print-report-title';
          if (activeTab.firstChild) {
            activeTab.insertBefore(printTitle, activeTab.firstChild);
          } else {
            activeTab.appendChild(printTitle);
          }
        }
        // Include date range in title
        printTitle.textContent = tabName + dateRange;
      }
      
      // Create or update print header (date only)
      let printHeader = document.querySelector('.print-header');
      const dashboardContent = document.querySelector('.dashboard-content');
      if (dashboardContent) {
        if (!printHeader) {
          printHeader = document.createElement('div');
          printHeader.className = 'print-header';
          dashboardContent.insertBefore(printHeader, dashboardContent.firstChild);
        }
        const printDate = new Date().toLocaleDateString('en-US', { 
          year: 'numeric', 
          month: 'long', 
          day: 'numeric' 
        });
        printHeader.textContent = printDate;
      }
      
      // Create or update print footer (page number)
      let printFooter = document.querySelector('.print-footer');
      if (!printFooter) {
        printFooter = document.createElement('div');
        printFooter.className = 'print-footer';
        document.body.appendChild(printFooter);
      }
      // Page number - browser will handle this, but we set placeholder
      printFooter.textContent = 'Page 1';
      
      // Trigger print
      window.print();
    }
    
    // Update print elements when printing
    window.addEventListener('beforeprint', function() {
      // Get tab name for report title
      let tabName = 'Reports';
      const activeTabLink = document.querySelector('#reportTabs .nav-link.active');
      if (activeTabLink) {
        const tabText = activeTabLink.textContent.trim();
        // Map tab names to report titles
        const titleMap = {
          'Patients': 'Patients Report',
          'Inventory': 'Inventory Report',
          'Dispensed': 'Dispensed Medicine Report',
          'Analytics': 'System Analytics Report'
        };
        tabName = titleMap[tabText] || tabText + ' Report';
      }
      
      // Get active date range
      const dateRange = getActiveDateRange();
      
      // Create or update print report title
      let printTitle = document.querySelector('.print-report-title');
      const activeTab = document.querySelector('.tab-content.active');
      if (activeTab) {
        if (!printTitle) {
          printTitle = document.createElement('div');
          printTitle.className = 'print-report-title';
          if (activeTab.firstChild) {
            activeTab.insertBefore(printTitle, activeTab.firstChild);
          } else {
            activeTab.appendChild(printTitle);
          }
        }
        // Include date range in title
        printTitle.textContent = tabName + dateRange;
      }
      
      // Create or update print header (date only)
      let printHeader = document.querySelector('.print-header');
      const dashboardContent = document.querySelector('.dashboard-content');
      if (dashboardContent) {
        if (!printHeader) {
          printHeader = document.createElement('div');
          printHeader.className = 'print-header';
          dashboardContent.insertBefore(printHeader, dashboardContent.firstChild);
        }
        const printDate = new Date().toLocaleDateString('en-US', { 
          year: 'numeric', 
          month: 'long', 
          day: 'numeric' 
        });
        printHeader.textContent = printDate;
      }
      
      // Create or update print footer (page number)
      let printFooter = document.querySelector('.print-footer');
      if (!printFooter) {
        printFooter = document.createElement('div');
        printFooter.className = 'print-footer';
        document.body.appendChild(printFooter);
      }
      // Page number - browser will handle this
      printFooter.textContent = 'Page 1';
    });
    
    // Remove print elements after printing (for screen view)
    window.addEventListener('afterprint', function() {
      const printTitle = document.querySelector('.print-report-title');
      const printHeader = document.querySelector('.print-header');
      const printFooter = document.querySelector('.print-footer');
      if (printTitle) printTitle.remove();
      if (printHeader) printHeader.remove();
      if (printFooter) printFooter.remove();
    });
  
   // Tab switching
document.querySelectorAll('#reportTabs .nav-link').forEach(tab => {
  tab.addEventListener('click', function (e) {
    e.preventDefault();
    // Remove active class sa lahat ng links
    document.querySelectorAll('#reportTabs .nav-link').forEach(link => link.classList.remove('active'));
    this.classList.add('active');
    // Remove active class sa lahat ng tab content
    document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
    // Activate current tab
    const targetTab = document.getElementById(this.getAttribute('data-target'));
    if (targetTab) {
      targetTab.classList.add('active');
      // Resize charts after tab switch to ensure proper rendering
      setTimeout(() => {
        window.dispatchEvent(new Event('resize'));
        // Resize specific chart instances if they exist
        if (targetTab.id === 'patientsTab') {
          if (window.patientVisitsChart) window.patientVisitsChart.resize();
          if (window.lineChart) window.lineChart.resize();
        }
        if (targetTab.id === 'inventoryTab') {
          if (window.medicineUsageChart) window.medicineUsageChart.resize();
          if (window.stockDistributionChart) window.stockDistributionChart.resize();
        }
      }, 100);
    }
  });
});

    // Sync date inputs on page load
    syncDateInputs();

    // Initialize DataTables
    $(document).ready(function() {
      $('#patientsTable').DataTable({ pageLength: 5 });
      $('#inventoryTable').DataTable({ pageLength: 5 });
      $('#dispensedTable').DataTable({ pageLength: 5 });
      $('#analyticsTable').DataTable({ 
        pageLength: 10,
        order: [[0, 'desc']]
      });
    });

    // Patient Visits Chart (Bar)
    const patientVisitsCtx = document.getElementById('patientVisitsChart').getContext('2d');
    const patientVisitsData = <?php echo json_encode($patientVisitsData); ?>;
    window.patientVisitsChart = new Chart(patientVisitsCtx, {
      type: 'bar',
      data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
          label: 'Visits',
          data: patientVisitsData,
          backgroundColor: '#6b0000',
          borderRadius: 5
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: {
            top: 5,
            bottom: 5,
            left: 5,
            right: 5
          }
        },
        plugins: { 
          legend: { display: false },
          tooltip: {
            mode: 'index',
            intersect: false
          }
        },
        scales: {
          x: {
            grid: {
              display: false
            }
          },
          y: { 
            beginAtZero: true, 
            ticks: { stepSize: 5 },
            grid: {
              color: 'rgba(0, 0, 0, 0.05)'
            }
          }
        }
      }
    });

    // Monthly Appointments Trend (Line Chart)
    const lineChartCtx = document.getElementById('lineChart').getContext('2d');
    window.lineChart = new Chart(lineChartCtx, {
      type: 'line',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
          label: 'Appointments',
          data: [65, 59, 80, 81, 56, 75, 70, 85, 78, 90, 95, 88],
          borderColor: '#6b0000',
          backgroundColor: 'rgba(107, 0, 0, 0.1)',
          tension: 0.4,
          fill: true,
          pointBackgroundColor: '#6b0000',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 3,
          pointHoverRadius: 5
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: {
            top: 5,
            bottom: 5,
            left: 5,
            right: 5
          }
        },
        plugins: { 
          legend: { 
            display: true,
            position: 'top',
            labels: {
              padding: 10,
              usePointStyle: true,
              font: {
                size: 11
              }
            }
          },
          tooltip: {
            mode: 'index',
            intersect: false
          }
        },
        scales: {
          x: {
            grid: {
              display: false
            },
            ticks: {
              maxRotation: 45,
              minRotation: 0,
              font: {
                size: 9
              }
            }
          },
          y: { 
            beginAtZero: true,
            ticks: {
              stepSize: 20,
              font: {
                size: 9
              }
            },
            grid: {
              color: 'rgba(0, 0, 0, 0.05)'
            }
          }
        }
      }
    });

    // Medicine Usage Chart (Line)
    const medicineUsageCtx = document.getElementById('medicineUsageChart').getContext('2d');
    const medicineUsageLabels = <?php echo json_encode($medicineUsageLabels); ?>;
    const medicineUsageData = <?php echo json_encode($medicineUsageData); ?>;
    window.medicineUsageChart = new Chart(medicineUsageCtx, {
      type: 'line',
      data: {
        labels: medicineUsageLabels,
        datasets: [{
          label: 'Medicines Dispensed',
          data: medicineUsageData,
          borderColor: '#6b0000',
          backgroundColor: 'rgba(107, 0, 0, 0.1)',
          tension: 0.4,
          fill: true,
          pointRadius: 3,
          pointHoverRadius: 5
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: {
            top: 5,
            bottom: 5,
            left: 5,
            right: 5
          }
        },
        plugins: { 
          legend: { 
            display: true, 
            position: 'top',
            labels: {
              padding: 10,
              usePointStyle: true,
              font: {
                size: 11
              }
            }
          },
          tooltip: {
            mode: 'index',
            intersect: false
          }
        },
        scales: {
          x: {
            grid: {
              display: false
            }
          },
          y: { 
            beginAtZero: true,
            grid: {
              color: 'rgba(0, 0, 0, 0.05)'
            }
          }
        }
      }
    });

    // Stock Distribution Chart (Pie)
    const stockDistributionCtx = document.getElementById('stockDistributionChart').getContext('2d');
    const stockDistributionLabels = <?php echo json_encode($stockDistributionLabels); ?>;
    const stockDistributionData = <?php echo json_encode($stockDistributionData); ?>;
    const stockDistributionColors = <?php echo json_encode($stockDistributionColors); ?>;
    window.stockDistributionChart = new Chart(stockDistributionCtx, {
      type: 'pie',
      data: {
        labels: stockDistributionLabels,
        datasets: [{
          data: stockDistributionData,
          backgroundColor: stockDistributionColors.slice(0, stockDistributionLabels.length)
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        layout: {
          padding: {
            top: 5,
            bottom: 5,
            left: 5,
            right: 5
          }
        },
        plugins: {
          legend: { 
            position: 'bottom',
            labels: {
              padding: 15,
              usePointStyle: true,
              font: {
                size: 11
              }
            }
          },
          tooltip: {
            mode: 'index',
            intersect: false
          }
        }
      }
    });

    // Analytics Chart - Using PHP generated data
    let analyticsChartInstance = null;
    
    function initAnalyticsChart() {
      const analyticsCanvas = document.getElementById('analyticsChart');
      if (!analyticsCanvas) return;
      
      // Destroy existing chart if it exists
      if (analyticsChartInstance) {
        analyticsChartInstance.destroy();
      }
      
      const analyticsCtx = analyticsCanvas.getContext('2d');
      const analyticsData = <?php echo json_encode($analyticsData); ?>;
      
      // Get container dimensions
      const chartContainer = analyticsCanvas.closest('.chart-box');
      if (!chartContainer) return;
      
      analyticsChartInstance = new Chart(analyticsCtx, {
        type: 'line',
        data: {
          labels: analyticsData.map(row => row.date),
          datasets: [
            {
              label: 'Sessions',
              data: analyticsData.map(row => row.sessions),
              borderColor: '#6b0000',
              backgroundColor: 'rgba(107, 0, 0, 0.1)',
              tension: 0.4,
              fill: true,
              pointRadius: 3,
              pointHoverRadius: 5
            },
            {
              label: 'Page Views',
              data: analyticsData.map(row => row.pageviews),
              borderColor: '#dc3545',
              backgroundColor: 'rgba(220, 53, 69, 0.1)',
              tension: 0.4,
              fill: true,
              pointRadius: 3,
              pointHoverRadius: 5
            }
          ]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          layout: {
            padding: {
              top: 10,
              bottom: 10,
              left: 10,
              right: 10
            }
          },
          plugins: {
            legend: {
              display: true,
              position: 'top',
              align: 'center',
              labels: {
                padding: 15,
                usePointStyle: true,
                font: {
                  size: 12
                }
              }
            },
            tooltip: {
              mode: 'index',
              intersect: false
            }
          },
          scales: {
            x: {
              display: true,
              grid: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)'
              },
              ticks: {
                maxRotation: 45,
                minRotation: 0,
                font: {
                  size: 10
                }
              }
            },
            y: {
              beginAtZero: true,
              grid: {
                display: true,
                color: 'rgba(0, 0, 0, 0.05)'
              },
              ticks: {
                font: {
                  size: 10
                }
              }
            }
          }
        }
      });
    }
    
    // Initialize chart when analytics tab is active or on page load
    function checkAndInitAnalyticsChart() {
      const analyticsTab = document.getElementById('analyticsTab');
      if (analyticsTab && analyticsTab.classList.contains('active')) {
        setTimeout(initAnalyticsChart, 100);
      }
    }
    
    // Initialize on page load if analytics tab is active
    checkAndInitAnalyticsChart();
    
    // Reinitialize chart when switching to analytics tab
    document.querySelectorAll('#reportTabs .nav-link').forEach(tab => {
      tab.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        if (targetId === 'analyticsTab') {
          setTimeout(checkAndInitAnalyticsChart, 150);
        }
      });
    });
    
    // Handle window resize for chart responsiveness
    let resizeTimeout;
    window.addEventListener('resize', function() {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(function() {
        // Resize Analytics chart
        if (analyticsChartInstance && document.getElementById('analyticsTab') && 
            document.getElementById('analyticsTab').classList.contains('active')) {
          analyticsChartInstance.resize();
        }
        
        // Resize Patients tab charts
        const patientsTab = document.getElementById('patientsTab');
        if (patientsTab && patientsTab.classList.contains('active')) {
          if (window.patientVisitsChart) window.patientVisitsChart.resize();
          if (window.lineChart) window.lineChart.resize();
        }
        
        // Resize Inventory tab charts
        const inventoryTab = document.getElementById('inventoryTab');
        if (inventoryTab && inventoryTab.classList.contains('active')) {
          if (window.medicineUsageChart) window.medicineUsageChart.resize();
          if (window.stockDistributionChart) window.stockDistributionChart.resize();
        }
      }, 250);
    });

    
  </script>
  <script src="../js/mobile-menu.js"></script>
</body>
</html>
<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$view = $_GET['view'] ?? 'today';
// Always use current date as default if no date is provided
$date = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$search = $_GET['search'] ?? '';
$department = $_GET['department'] ?? 'all';

// Build WHERE clause for schedules
$scheduleConditions = [];
$params = [];

// Date filtering
if ($view === 'today') {
    $scheduleConditions[] = "ds.schedule_date = ?";
    $params[] = $date;
    $dateRangeInfo = date('l, F j, Y', strtotime($date));
} elseif ($view === 'week') {
    // Get start of week (Monday) and end of week (Sunday)
    $today = new DateTime();
    $dayOfWeek = $today->format('N'); // 1 (Monday) to 7 (Sunday)
    $daysToMonday = $dayOfWeek - 1; // Days to subtract to get to Monday
    $monday = clone $today;
    $monday->modify("-{$daysToMonday} days");
    $sunday = clone $monday;
    $sunday->modify('+6 days');
    
    $scheduleConditions[] = "ds.schedule_date BETWEEN ? AND ?";
    $params[] = $monday->format('Y-m-d');
    $params[] = $sunday->format('Y-m-d');
    $dateRangeInfo = "This Week's Schedule (" . $monday->format('M j') . " - " . $sunday->format('M j, Y') . ")";
} elseif ($view === 'month') {
    $scheduleConditions[] = "MONTH(ds.schedule_date) = ? AND YEAR(ds.schedule_date) = ?";
    $params[] = date('m');
    $params[] = date('Y');
    $dateRangeInfo = date('F Y') . " Schedule";
} elseif ($view === 'year') {
    $scheduleConditions[] = "YEAR(ds.schedule_date) = ?";
    $params[] = date('Y');
    $dateRangeInfo = date('Y') . " Schedule";
} else {
    $scheduleConditions[] = "ds.schedule_date >= ?";
    $params[] = date('Y-m-d');
    $dateRangeInfo = "All Upcoming Schedules";
}

// Build WHERE clause for users - Doctors, dentists, and nurses
$userConditions = ["u.role IN ('doctor', 'dentist', 'nurse')"];
$userParams = [];

// Department filtering
if ($department !== 'all') {
    if ($department === 'dental') {
        $userConditions[] = "u.role = ?";
        $userParams[] = 'dentist';
    } elseif ($department === 'medical') {
        $userConditions[] = "u.role = ?";
        $userParams[] = 'doctor';
    }
}

// Search filtering
if (!empty($search)) {
    $userConditions[] = "(CONCAT(u.fname, ' ', IFNULL(u.mname, ''), ' ', u.lname) LIKE ? OR u.email LIKE ? OR u.role LIKE ?)";
    $searchParam = "%$search%";
    $userParams[] = $searchParam;
    $userParams[] = $searchParam;
    $userParams[] = $searchParam;
}

$scheduleWhereClause = !empty($scheduleConditions) ? 'AND ' . implode(' AND ', $scheduleConditions) : '';
$userWhereClause = implode(' AND ', $userConditions);

// Build attendance date condition based on view type
// For nurses, we want to show attendance for the selected date (or date range)
$attendanceDateCondition = '';
$attendanceDateParams = [];

if ($view === 'today') {
    // For "today" view, use the selected date
    $attendanceDateCondition = 'AND att.date = ?';
    $attendanceDateParams[] = $date;
} elseif ($view === 'week') {
    // For "week" view, use the date range
    $today = new DateTime();
    $dayOfWeek = $today->format('N');
    $daysToMonday = $dayOfWeek - 1;
    $monday = clone $today;
    $monday->modify("-{$daysToMonday} days");
    $sunday = clone $monday;
    $sunday->modify('+6 days');
    $attendanceDateCondition = 'AND att.date BETWEEN ? AND ?';
    $attendanceDateParams[] = $monday->format('Y-m-d');
    $attendanceDateParams[] = $sunday->format('Y-m-d');
} elseif ($view === 'month') {
    $attendanceDateCondition = 'AND MONTH(att.date) = ? AND YEAR(att.date) = ?';
    $attendanceDateParams[] = date('m');
    $attendanceDateParams[] = date('Y');
} elseif ($view === 'year') {
    $attendanceDateCondition = 'AND YEAR(att.date) = ?';
    $attendanceDateParams[] = date('Y');
} else {
    // For "all upcoming", show attendance from today onwards
    $attendanceDateCondition = 'AND att.date >= ?';
    $attendanceDateParams[] = date('Y-m-d');
}

// FIXED QUERY: Show doctors, dentists, and nurses
// Doctors/dentists show schedules, nurses show attendance
// Always show all (even if no schedule, to show "No Schedule" for weekends)
$sql = "
    SELECT 
        u.id as user_id,
        u.fname as first_name,
        u.mname as middle_name,
        u.lname as last_name,
        u.email,
        u.role,
        ds.schedule_date,
        ds.start_time as time_in,
        ds.end_time as time_out,
        ds.is_available,
        ds.schedule_type,
        ds.reason,
        att.date as attendance_date,
        att.status as current_status,
        att.time_in as attendance_time_in_raw,
        att.time_out as attendance_time_out_raw,
        CASE 
            WHEN att.time_in IS NOT NULL AND att.time_out IS NULL THEN 'in'
            WHEN att.time_out IS NOT NULL THEN 'out'
            ELSE 'out'
        END as availability_status,
        DATE_FORMAT(ds.start_time, '%h:%i %p') as time_in_formatted,
        DATE_FORMAT(ds.end_time, '%h:%i %p') as time_out_formatted,
        -- Format attendance time_in (handle both TIME and DATETIME formats)
        CASE 
            WHEN att.time_in IS NOT NULL THEN 
                TIME_FORMAT(att.time_in, '%h:%i %p')
            ELSE NULL
        END as attendance_time_in,
        -- Format attendance time_out (handle both TIME and DATETIME formats)
        CASE 
            WHEN att.time_out IS NOT NULL THEN 
                TIME_FORMAT(att.time_out, '%h:%i %p')
            ELSE NULL
        END as attendance_time_out,
        CASE
            WHEN ds.start_time IS NOT NULL AND ds.end_time IS NOT NULL THEN
                CONCAT(
                    TIMESTAMPDIFF(HOUR, ds.start_time, ds.end_time), 'h ',
                    MOD(TIMESTAMPDIFF(MINUTE, ds.start_time, ds.end_time), 60), 'm'
                )
            ELSE NULL
        END as duration
    FROM users u
    LEFT JOIN employees e ON u.id = e.user_id
    LEFT JOIN doctor_schedules ds ON u.id = ds.user_id $scheduleWhereClause
    LEFT JOIN attendance att ON u.id = att.user_id $attendanceDateCondition
    WHERE $userWhereClause
    ORDER BY u.lname ASC, u.fname ASC
";

try {
    // Build parameters array: schedule params + user params + attendance params
    // Important: The order matters! Schedule params come first, then user params, then attendance params
    $allParams = array_merge($params, $userParams, $attendanceDateParams);
    
    // Debug logging
    error_log("Refresh Schedule - View: $view, Date: $date");
    error_log("Schedule params: " . json_encode($params));
    error_log("User params: " . json_encode($userParams));
    error_log("Attendance params: " . json_encode($attendanceDateParams));
    error_log("All params: " . json_encode($allParams));
    error_log("SQL: " . $sql);
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($allParams);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if viewing a weekend for the date info
    $isWeekend = false;
    if ($view === 'today' && isset($date)) {
        $dayOfWeek = date('w', strtotime($date)); // 0 = Sunday, 6 = Saturday
        $isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);
    }
    
    // Log results for debugging
    error_log("Schedule results count: " . count($schedules));
    
    echo json_encode([
        'success' => true,
        'schedules' => $schedules,
        'count' => count($schedules),
        'dateRangeInfo' => $dateRangeInfo,
        'view' => $view,
        'date' => $date,
        'isWeekend' => $isWeekend
    ]);
    
} catch (PDOException $e) {
    error_log("Schedule refresh error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
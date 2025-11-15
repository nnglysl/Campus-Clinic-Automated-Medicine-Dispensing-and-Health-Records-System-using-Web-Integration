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
    $scheduleConditions[] = "ds.schedule_date BETWEEN ? AND ?";
    $params[] = date('Y-m-d');
    $params[] = date('Y-m-d', strtotime('+7 days'));
    $dateRangeInfo = "This Week's Schedule";
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

// Build WHERE clause for users
$userConditions = ["u.role IN ('doctor', 'dentist', 'nurse', 'staff', 'employee')"];
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

// FIXED QUERY: Removed ds.is_available = 1 filter to show ALL schedules
$sql = "
    SELECT 
        u.id as user_id,
        u.fname as first_name,
        u.mname as middle_name,
        u.lname as last_name,
        u.email,
        u.role,
        u.photo,
        ds.schedule_date,
        ds.start_time as time_in,
        ds.end_time as time_out,
        ds.is_available,
        ds.schedule_type,
        ds.reason,
        att.status as current_status,
        att.time_in as last_check_in,
        CASE 
            WHEN att.time_in IS NOT NULL AND att.time_out IS NULL THEN 'in'
            WHEN att.time_out IS NOT NULL THEN 'out'
            ELSE 'out'
        END as availability_status,
        DATE_FORMAT(ds.start_time, '%h:%i %p') as time_in_formatted,
        DATE_FORMAT(ds.end_time, '%h:%i %p') as time_out_formatted,
        CASE
            WHEN ds.start_time IS NOT NULL AND ds.end_time IS NOT NULL THEN
                CONCAT(
                    TIMESTAMPDIFF(HOUR, ds.start_time, ds.end_time), 'h ',
                    MOD(TIMESTAMPDIFF(MINUTE, ds.start_time, ds.end_time), 60), 'm'
                )
            ELSE NULL
        END as duration,
        DATE_FORMAT(att.time_in, '%h:%i %p') as last_check_in_formatted
    FROM users u
    LEFT JOIN doctor_schedules ds ON u.id = ds.user_id $scheduleWhereClause
    LEFT JOIN attendance att ON u.id = att.user_id AND att.date = CURDATE()
    WHERE $userWhereClause
    AND ds.schedule_date IS NOT NULL
    ORDER BY ds.schedule_date ASC, u.lname ASC, u.fname ASC
";

try {
    $allParams = array_merge($params, $userParams);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($allParams);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'schedules' => $schedules,
        'count' => count($schedules),
        'dateRangeInfo' => $dateRangeInfo,
        'view' => $view,
        'date' => $date
    ]);
    
} catch (PDOException $e) {
    error_log("Schedule refresh error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
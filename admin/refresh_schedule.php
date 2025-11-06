<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    // Get view parameters
    $date = $_GET['date'] ?? date('Y-m-d');
    $view = $_GET['view'] ?? 'today';
    
    // Build date condition based on view type
    $dateCondition = '';
    $params = [];
    
    switch ($view) {
        case 'week':
            // Get this week's schedules (Monday to Sunday)
            $startOfWeek = date('Y-m-d', strtotime('monday this week'));
            $endOfWeek = date('Y-m-d', strtotime('sunday this week'));
            $dateCondition = "AND ds.schedule_date BETWEEN ? AND ?";
            $params = [$startOfWeek, $endOfWeek];
            break;
        
        case 'month':
            // Get this month's schedules
            $startOfMonth = date('Y-m-01');
            $endOfMonth = date('Y-m-t');
            $dateCondition = "AND ds.schedule_date BETWEEN ? AND ?";
            $params = [$startOfMonth, $endOfMonth];
            break;
        
        case 'year':
            // Get this year's schedules
            $startOfYear = date('Y-01-01');
            $endOfYear = date('Y-12-31');
            $dateCondition = "AND ds.schedule_date BETWEEN ? AND ?";
            $params = [$startOfYear, $endOfYear];
            break;
            
        case 'all':
            // Get all upcoming schedules
            $dateCondition = "AND ds.schedule_date >= ?";
            $params = [date('Y-m-d')];
            break;
            
        case 'today':
        default:
            // Get specific date or today
            $dateCondition = "AND ds.schedule_date = ?";
            $params = [$date];
            break;
    }
    
    $sql = "
        SELECT 
            u.id as user_id,
            u.fname as first_name,
            u.mname as middle_name,
            u.lname as last_name,
            u.email,
            u.role,
            u.photo,
            ds.id as schedule_id,
            ds.schedule_date,
            ds.start_time as time_in,
            ds.end_time as time_out,
            ds.is_available,
            DATE_FORMAT(ds.start_time, '%h:%i %p') as time_in_formatted,
            DATE_FORMAT(ds.end_time, '%h:%i %p') as time_out_formatted,
            att.status as current_status,
            DATE_FORMAT(att.time_in, '%h:%i %p') as last_check_in,
            CASE 
                WHEN att.time_in IS NOT NULL AND att.time_out IS NULL THEN 'in'
                WHEN att.time_out IS NOT NULL THEN 'out'
                ELSE 'out'
            END as availability_status,
            CONCAT(
                TIMESTAMPDIFF(HOUR, ds.start_time, ds.end_time), 'h ',
                MOD(TIMESTAMPDIFF(MINUTE, ds.start_time, ds.end_time), 60), 'm'
            ) as duration
        FROM users u
        INNER JOIN doctor_schedules ds ON u.id = ds.user_id 
            AND ds.is_available = 1
            $dateCondition
        LEFT JOIN attendance att ON u.id = att.user_id 
            AND att.date = ds.schedule_date
        WHERE u.role IN ('doctor', 'nurse', 'staff', 'employee')
        ORDER BY ds.schedule_date ASC, ds.start_time ASC, u.lname ASC, u.fname ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'schedules' => $schedules,
        'view' => $view,
        'date' => $date,
        'count' => count($schedules),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    
} catch (PDOException $e) {
    error_log('Refresh Schedule Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred: ' . $e->getMessage()
    ]);
}
?>
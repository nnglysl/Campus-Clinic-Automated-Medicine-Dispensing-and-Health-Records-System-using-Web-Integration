<?php
session_start();
require_once('../db.php');

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'create':
            $date = $input['date'] ?? '';
            $scheduleType = $input['scheduleType'] ?? '';
            
            if (empty($date)) {
                throw new Exception('Date is required');
            }

            // Check if schedule already exists for this date
            $checkStmt = $pdo->prepare("
                SELECT id FROM doctor_schedules 
                WHERE user_id = ? AND schedule_date = ?
            ");
            $checkStmt->execute([$user_id, $date]);
            $existing = $checkStmt->fetch();

            if ($scheduleType === 'available') {
                // Whole day available
                $startTime = $input['startTime'] ?? '08:00';
                $endTime = $input['endTime'] ?? '18:00';
                
                if ($existing) {
                    // Update existing
                    $stmt = $pdo->prepare("
                        UPDATE doctor_schedules 
                        SET start_time = ?, end_time = ?, schedule_type = 'available', 
                            is_available = 1, reason = NULL, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$startTime, $endTime, $existing['id']]);
                } else {
                    // Insert new
                    $stmt = $pdo->prepare("
                        INSERT INTO doctor_schedules 
                        (user_id, schedule_date, start_time, end_time, schedule_type, is_available, created_at, updated_at)
                        VALUES (?, ?, ?, ?, 'available', 1, NOW(), NOW())
                    ");
                    $stmt->execute([$user_id, $date, $startTime, $endTime]);
                }
                
            } elseif ($scheduleType === 'unavailable') {
                // Whole day unavailable
                $reason = $input['reason'] ?? '';
                
                if ($existing) {
                    $stmt = $pdo->prepare("
                        UPDATE doctor_schedules 
                        SET start_time = NULL, end_time = NULL, schedule_type = 'unavailable', 
                            is_available = 0, reason = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$reason, $existing['id']]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO doctor_schedules 
                        (user_id, schedule_date, start_time, end_time, schedule_type, is_available, reason, created_at, updated_at)
                        VALUES (?, ?, NULL, NULL, 'unavailable', 0, ?, NOW(), NOW())
                    ");
                    $stmt->execute([$user_id, $date, $reason]);
                }
                
            } elseif ($scheduleType === 'custom') {
                // Custom time slots
                $slots = $input['slots'] ?? [];
                
                // Delete existing schedules for this date
                $deleteStmt = $pdo->prepare("
                    DELETE FROM doctor_schedules 
                    WHERE user_id = ? AND schedule_date = ?
                ");
                $deleteStmt->execute([$user_id, $date]);
                
                // Group consecutive available/unavailable slots
                $timeRanges = [];
                $currentRange = null;
                
                foreach ($slots as $hour => $status) {
                    $time = sprintf('%02d:00', $hour);
                    
                    if ($currentRange === null) {
                        $currentRange = [
                            'start' => $time,
                            'end' => sprintf('%02d:00', $hour + 1),
                            'type' => $status
                        ];
                    } elseif ($currentRange['type'] === $status) {
                        // Extend current range
                        $currentRange['end'] = sprintf('%02d:00', $hour + 1);
                    } else {
                        // Save current range and start new one
                        $timeRanges[] = $currentRange;
                        $currentRange = [
                            'start' => $time,
                            'end' => sprintf('%02d:00', $hour + 1),
                            'type' => $status
                        ];
                    }
                }
                
                // Don't forget the last range
                if ($currentRange !== null) {
                    $timeRanges[] = $currentRange;
                }
                
                // Insert each time range
                foreach ($timeRanges as $range) {
                    $schedType = ($range['type'] === 'available') ? 'available' : 'unavailable';
                    $isAvailable = ($range['type'] === 'available') ? 1 : 0;
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO doctor_schedules 
                        (user_id, schedule_date, start_time, end_time, schedule_type, is_available, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $stmt->execute([
                        $user_id, 
                        $date, 
                        $range['start'], 
                        $range['end'], 
                        $schedType, 
                        $isAvailable
                    ]);
                }
            }
            
            echo json_encode(['success' => true, 'message' => 'Schedule saved successfully']);
            break;
            
        case 'list':
            // Load all schedules for the current user
            $stmt = $pdo->prepare("
                SELECT * FROM doctor_schedules 
                WHERE user_id = ? 
                ORDER BY schedule_date, start_time
            ");
            $stmt->execute([$user_id]);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['success' => true, 'data' => $schedules]);
            break;
            
        case 'delete':
            $scheduleId = $input['schedule_id'] ?? '';
            
            if (empty($scheduleId)) {
                throw new Exception('Schedule ID is required');
            }
            
            $stmt = $pdo->prepare("
                DELETE FROM doctor_schedules 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$scheduleId, $user_id]);
            
            echo json_encode(['success' => true, 'message' => 'Schedule deleted']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log("Schedule handler error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => $e->getMessage()
    ]);
}
?>
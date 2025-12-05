<?php
session_start();
<<<<<<< HEAD
require_once('../db.php');

header('Content-Type: application/json');

// Check if user is logged in
=======
require_once(__DIR__ . '/../db.php');

header('Content-Type: application/json');

// Check authentication
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

<<<<<<< HEAD
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
=======
$userId = $_SESSION['user_id'];

try {
    // Handle GET request (list schedules)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        
        if ($action === 'list') {
            // Fetch all schedules for the current user
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    schedule_date as start,
                    schedule_type,
                    start_time,
                    end_time,
                    reason,
                    notes,
                    CASE 
                        WHEN schedule_type = 'unavailable' THEN 'Unavailable'
                        ELSE CONCAT('Available: ', 
                            DATE_FORMAT(start_time, '%h:%i %p'), ' - ', 
                            DATE_FORMAT(end_time, '%h:%i %p'))
                    END as title,
                    is_available
                FROM doctor_schedules
                WHERE user_id = ? AND is_available = 1
                ORDER BY schedule_date ASC, start_time ASC
            ");
            $stmt->execute([$userId]);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format for FullCalendar
            $events = [];
            foreach ($schedules as $schedule) {
                $isUnavailable = $schedule['schedule_type'] === 'unavailable';
                
                $events[] = [
                    'id' => $schedule['id'],
                    'title' => $schedule['title'],
                    'start' => $schedule['start'],
                    'allDay' => $isUnavailable,
                    'backgroundColor' => $isUnavailable ? '#dc3545' : '#198754',
                    'borderColor' => $isUnavailable ? '#dc3545' : '#198754',
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'type' => $schedule['schedule_type'],
                        'reason' => $schedule['reason'],
                        'notes' => $schedule['notes'],
                        'startTime' => $schedule['start_time'],
                        'endTime' => $schedule['end_time']
                    ]
                ];
            }
            
            echo json_encode(['success' => true, 'data' => $events]);
            exit;
        }
    }
    
    // Handle POST request (create/delete schedules)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            throw new Exception('Invalid data received');
        }
        
        $action = $data['action'] ?? '';
        
        // CREATE SCHEDULE
        if ($action === 'create') {
            $date = $data['date'] ?? '';
            $scheduleType = $data['scheduleType'] ?? 'available';
            $startTime = $data['startTime'] ?? null;
            $endTime = $data['endTime'] ?? null;
            $reason = $data['reason'] ?? null;
            
            // Validate inputs
            if (empty($date)) {
                throw new Exception('Date is required');
            }
            
            // For available schedules, times are required
            if ($scheduleType === 'available' && (empty($startTime) || empty($endTime))) {
                throw new Exception('Start time and end time are required for available schedules');
            }
            
            // Check if date is valid
            $scheduleDate = new DateTime($date);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            
            if ($scheduleDate < $today) {
                throw new Exception('Cannot schedule in the past');
            }
            
            // Check if it's a weekend
            $dayOfWeek = $scheduleDate->format('N'); // 1 (Monday) to 7 (Sunday)
            if ($dayOfWeek >= 6) {
                throw new Exception('Cannot schedule on weekends');
            }
            
            // Validate time format for available schedules
            if ($scheduleType === 'available') {
                if (!preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $startTime) || 
                    !preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $endTime)) {
                    throw new Exception('Invalid time format');
                }
                
                // Check if end time is after start time
                $start = strtotime($startTime);
                $end = strtotime($endTime);
                
                if ($end <= $start) {
                    throw new Exception('End time must be after start time');
                }
            }
            
            // Check for existing schedules on this date
            $stmt = $pdo->prepare("
                SELECT id, schedule_type, start_time, end_time
                FROM doctor_schedules
                WHERE user_id = ? 
                AND schedule_date = ?
                AND is_available = 1
            ");
            $stmt->execute([$userId, $date]);
            $existingSchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // If marking as unavailable, check if there are any existing schedules
            if ($scheduleType === 'unavailable' && count($existingSchedules) > 0) {
                throw new Exception('Cannot mark as unavailable - there are existing schedules on this date');
            }
            
            // If adding available schedule, check for unavailability
            if ($scheduleType === 'available') {
                foreach ($existingSchedules as $existing) {
                    if ($existing['schedule_type'] === 'unavailable') {
                        throw new Exception('This date is marked as unavailable');
                    }
                    
                    // Check for time overlaps with other available schedules
                    if ($existing['schedule_type'] === 'available') {
                        $existingStart = $existing['start_time'];
                        $existingEnd = $existing['end_time'];
                        
                        if (
                            ($startTime >= $existingStart && $startTime < $existingEnd) ||
                            ($endTime > $existingStart && $endTime <= $existingEnd) ||
                            ($startTime <= $existingStart && $endTime >= $existingEnd)
                        ) {
                            throw new Exception('Time slot overlaps with existing schedule');
                        }
                    }
                }
            }
            
            // Insert the schedule
            $stmt = $pdo->prepare("
                INSERT INTO doctor_schedules 
                (user_id, schedule_date, start_time, end_time, schedule_type, reason, is_available, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
            ");
            
            $stmt->execute([
                $userId, 
                $date, 
                $startTime, 
                $endTime, 
                $scheduleType,
                $reason
            ]);
            $scheduleId = $pdo->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'message' => $scheduleType === 'unavailable' 
                    ? 'Marked as unavailable successfully' 
                    : 'Schedule created successfully',
                'id' => $scheduleId
            ]);
            exit;
        }
        
        // DELETE SCHEDULE
        if ($action === 'delete') {
            $scheduleId = $data['id'] ?? '';
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            
            if (empty($scheduleId)) {
                throw new Exception('Schedule ID is required');
            }
            
<<<<<<< HEAD
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
=======
            // Verify the schedule belongs to the current user
            $stmt = $pdo->prepare("
                SELECT id, schedule_type FROM doctor_schedules 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$scheduleId, $userId]);
            $schedule = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$schedule) {
                throw new Exception('Schedule not found or unauthorized');
            }
            
            // Check if there are any appointments for this schedule (only for available schedules)
            if ($schedule['schedule_type'] === 'available') {
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM appointments a
                    INNER JOIN doctor_schedules ds ON DATE(a.appointment_date) = ds.schedule_date
                    WHERE ds.id = ? 
                    AND a.status NOT IN ('cancelled', 'completed')
                ");
                $stmt->execute([$scheduleId]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    throw new Exception('Cannot delete schedule with existing appointments');
                }
            }
            
            // Soft delete by setting is_available to 0
            $stmt = $pdo->prepare("
                UPDATE doctor_schedules 
                SET is_available = 0, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            
            $stmt->execute([$scheduleId, $userId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Schedule deleted successfully'
            ]);
            exit;
        }
        
        throw new Exception('Invalid action');
    }
    
    throw new Exception('Invalid request method');
    
} catch (PDOException $e) {
    error_log('Schedule Handler Database Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('Schedule Handler Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        'error' => $e->getMessage()
    ]);
}
?>
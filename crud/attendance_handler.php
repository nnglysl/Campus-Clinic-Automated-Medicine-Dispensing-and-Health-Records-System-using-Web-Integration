<?php
session_start();
require_once(__DIR__ . '/../db.php');
<<<<<<< HEAD
date_default_timezone_set('Asia/Manila'); // or your local timezone
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Handle GET request (list attendance)
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
        
        if ($action === 'list') {
            // Fetch all attendance records for the current user
            $stmt = $pdo->prepare("
                SELECT 
                    id,
                    date,
                    DATE_FORMAT(date, '%m/%d/%Y') as formatted_date,
                    DATE_FORMAT(time_in, '%h:%i %p') as timeIn,
                    DATE_FORMAT(time_out, '%h:%i %p') as timeOut,
                    status,
                    notes
                FROM attendance
                WHERE user_id = ?
                ORDER BY date DESC, time_in DESC
                LIMIT 30
            ");
            $stmt->execute([$userId]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format records for JavaScript
            $formattedRecords = array_map(function($record) {
                return [
                    'id' => $record['id'],
                    'date' => $record['formatted_date'],
                    'rawDate' => $record['date'],
                    'timeIn' => $record['timeIn'] ?? '--:-- --',
                    'timeOut' => $record['timeOut'] ?? '--:-- --',
                    'status' => $record['status'],
                    'notes' => $record['notes']
                ];
            }, $records);
            
            echo json_encode(['success' => true, 'data' => $formattedRecords]);
            exit;
        }
    }
    
    // Handle POST request (time in/out)
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if (!$data) {
            throw new Exception('Invalid data received');
        }
        
        $action = $data['action'] ?? '';
        
        // TIME IN
        if ($action === 'timeIn') {
            $today = date('Y-m-d');
<<<<<<< HEAD
            $currentTime = date('Y-m-d H:i:s');

=======
            $currentTime = date('H:i:s');
            
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            // Check if already timed in today
            $stmt = $pdo->prepare("
                SELECT id, time_in, time_out 
                FROM attendance 
                WHERE user_id = ? AND date = ?
            ");
            $stmt->execute([$userId, $today]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing && $existing['time_in']) {
                throw new Exception('You have already timed in today');
            }
            
            // Determine status based on time
            $timeInHour = (int)date('H');
            $status = 'present';
            
            // Consider late if after 8:00 AM
            if ($timeInHour >= 8) {
                $timeInMinute = (int)date('i');
                if ($timeInHour > 8 || ($timeInHour == 8 && $timeInMinute > 0)) {
                    $status = 'late';
                }
            }
            
<<<<<<< HEAD
            // Extract time portion from datetime
            $timeOnly = date('H:i:s', strtotime($currentTime));
            
            // Use INSERT ... ON DUPLICATE KEY UPDATE to handle unique constraint robustly
            // Only update if time_in is NULL (hasn't been set yet)
            $stmt = $pdo->prepare("
                INSERT INTO attendance 
                (user_id, date, time_in, status, created_at, updated_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE
                time_in = COALESCE(time_in, VALUES(time_in)),
                status = COALESCE(status, VALUES(status)),
                updated_at = NOW()
            ");
            $result = $stmt->execute([$userId, $today, $timeOnly, $status]);
            
            if (!$result) {
                throw new Exception('Failed to save attendance record');
            }
            
            // Get the record ID (either newly inserted or existing)
            $recordId = $pdo->lastInsertId();
            if (!$recordId && $existing) {
                $recordId = $existing['id'];
            }
            
            error_log("Attendance SAVED: Record ID $recordId, User ID $userId, Date $today, Time In: $timeOnly, Status: $status");
                
=======
            if ($existing) {
                // Update existing record
                $stmt = $pdo->prepare("
                    UPDATE attendance 
                    SET time_in = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$currentTime, $status, $existing['id']]);
            } else {
                // Insert new record
                $stmt = $pdo->prepare("
                    INSERT INTO attendance 
                    (user_id, date, time_in, status, created_at, updated_at)
                    VALUES (?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([$userId, $today, $currentTime, $status]);
            }
            
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            echo json_encode([
                'success' => true,
                'message' => 'Timed in successfully',
                'time' => date('h:i A'),
                'status' => $status
            ]);
            exit;
        }
        
        // TIME OUT
        if ($action === 'timeOut') {
            $today = date('Y-m-d');
<<<<<<< HEAD
            $currentTime = date('Y-m-d H:i:s');

            // Check if there's a time in record for today
=======
            $currentTime = date('H:i:s');
            
            // Check if timed in today
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            $stmt = $pdo->prepare("
                SELECT id, time_in, time_out 
                FROM attendance 
                WHERE user_id = ? AND date = ?
            ");
            $stmt->execute([$userId, $today]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing || !$existing['time_in']) {
<<<<<<< HEAD
                throw new Exception('You must time in first before timing out');
=======
                throw new Exception('You need to time in first');
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            }
            
            if ($existing['time_out']) {
                throw new Exception('You have already timed out today');
            }
            
<<<<<<< HEAD
            // Extract time portion from datetime
            $timeOnly = date('H:i:s', strtotime($currentTime));
            
            // Update the record with time out
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET time_out = ?, updated_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $result = $stmt->execute([$timeOnly, $existing['id'], $userId]);
            
            if (!$result) {
                throw new Exception('Failed to update time out');
            }
            
            $affectedRows = $stmt->rowCount();
            if ($affectedRows === 0) {
                throw new Exception('No attendance record was updated. Please try again.');
            }
            
            error_log("Attendance TIME OUT: User ID $userId, Date $today, Time Out: $timeOnly, Rows Affected: $affectedRows");
=======
            // Calculate if it's a half day
            $timeIn = strtotime($today . ' ' . $existing['time_in']);
            $timeOut = strtotime($today . ' ' . $currentTime);
            $hoursWorked = ($timeOut - $timeIn) / 3600;
            
            $status = $existing['status'] ?? 'present';
            if ($hoursWorked < 4) {
                $status = 'half_day';
            }
            
            // Update time out
            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET time_out = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$currentTime, $status, $existing['id']]);
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            
            echo json_encode([
                'success' => true,
                'message' => 'Timed out successfully',
<<<<<<< HEAD
                'time' => date('h:i A')
=======
                'time' => date('h:i A'),
                'hoursWorked' => round($hoursWorked, 2)
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            ]);
            exit;
        }
        
        throw new Exception('Invalid action');
    }
    
    throw new Exception('Invalid request method');
    
} catch (PDOException $e) {
    error_log('Attendance Handler Database Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log('Attendance Handler Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
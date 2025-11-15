<?php
/**
 * Doctor Schedule Availability API
 * Provides real-time doctor schedule data for student appointment booking
 * File: crud/get_doctor_availability.php
 */

require_once '../config/database.php';
header('Content-Type: application/json');

session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDB();
$action = $_GET['action'] ?? 'get_availability';

try {
    switch ($action) {
        case 'get_availability':
            // Get parameters
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-t', strtotime('+2 months'));
            $department = $_GET['department'] ?? 'medical';
            
            // Map department to doctor role
            $doctorRole = ($department === 'dental') ? 'dentist' : 'doctor';
            
            // Fetch all doctor schedules for the date range
            $stmt = $pdo->prepare("
                SELECT DISTINCT 
                    ds.id,
                    ds.user_id,
                    ds.schedule_date,
                    ds.start_time,
                    ds.end_time,
                    ds.schedule_type,
                    ds.is_available,
                    u.fname,
                    u.lname,
                    u.role
                FROM doctor_schedules ds
                INNER JOIN users u ON ds.user_id = u.id
                WHERE ds.schedule_date BETWEEN ? AND ?
                    AND ds.is_available = 1
                    AND ds.schedule_type = 'available'
                    AND u.role = ?
                    AND ds.start_time IS NOT NULL
                    AND ds.end_time IS NOT NULL
                ORDER BY ds.schedule_date, ds.start_time
            ");
            $stmt->execute([$startDate, $endDate, $doctorRole]);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Initialize data structures
            $calendarDates = [];
            $timeSlots = [];
            
            // Process each schedule
            foreach ($schedules as $schedule) {
                $date = $schedule['schedule_date'];
                $doctorName = trim($schedule['fname'] . ' ' . $schedule['lname']);
                
                // Initialize calendar date if not exists
                if (!isset($calendarDates[$date])) {
                    $calendarDates[$date] = [
                        'date' => $date,
                        'total_slots' => 0,
                        'available_slots' => 0,
                        'booked_slots' => 0,
                        'status' => 'available'
                    ];
                }
                
                // Generate 30-minute time slots
                $startTime = new DateTime($date . ' ' . $schedule['start_time']);
                $endTime = new DateTime($date . ' ' . $schedule['end_time']);
                
                while ($startTime < $endTime) {
                    $timeStr = $startTime->format('H:i:00');
                    
                    // Check if this slot is already booked
                    $bookingStmt = $pdo->prepare("
                        SELECT COUNT(*) as count
                        FROM appointments
                        WHERE appointment_date = ?
                            AND appointment_time = ?
                            AND appointment_type = ?
                            AND status IN ('scheduled', 'confirmed')
                    ");
                    $bookingStmt->execute([$date, $timeStr, $department]);
                    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $isAvailable = ($booking['count'] < 1);
                    
                    // Add to time slots array
                    $timeSlots[] = [
                        'date' => $date,
                        'time' => $timeStr,
                        'formatted_time' => $startTime->format('g:i A'),
                        'available' => $isAvailable,
                        'doctor' => $doctorName,
                        'doctor_id' => $schedule['user_id']
                    ];
                    
                    // Update calendar date stats
                    $calendarDates[$date]['total_slots']++;
                    if ($isAvailable) {
                        $calendarDates[$date]['available_slots']++;
                    } else {
                        $calendarDates[$date]['booked_slots']++;
                    }
                    
                    $startTime->modify('+30 minutes');
                }
            }
            
            // Determine status for each calendar date
            foreach ($calendarDates as $date => &$data) {
                if ($data['available_slots'] === 0) {
                    $data['status'] = 'fully-booked';
                } elseif ($data['booked_slots'] > 0) {
                    $data['status'] = 'partially-available';
                } else {
                    $data['status'] = 'available';
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'calendar_dates' => $calendarDates,
                    'time_slots' => $timeSlots,
                    'last_updated' => date('Y-m-d H:i:s'),
                    'department' => $department,
                    'doctor_role' => $doctorRole
                ]
            ]);
            break;
            
        case 'get_date_slots':
            // Get time slots for a specific date
            $date = $_GET['date'] ?? date('Y-m-d');
            $department = $_GET['department'] ?? 'medical';
            $doctorRole = ($department === 'dental') ? 'dentist' : 'doctor';
            
            $stmt = $pdo->prepare("
                SELECT 
                    ds.start_time,
                    ds.end_time,
                    u.id as doctor_id,
                    u.fname,
                    u.lname
                FROM doctor_schedules ds
                INNER JOIN users u ON ds.user_id = u.id
                WHERE ds.schedule_date = ?
                    AND ds.is_available = 1
                    AND ds.schedule_type = 'available'
                    AND u.role = ?
                    AND ds.start_time IS NOT NULL
                    AND ds.end_time IS NOT NULL
            ");
            $stmt->execute([$date, $doctorRole]);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $slots = [];
            foreach ($schedules as $schedule) {
                $startTime = new DateTime($date . ' ' . $schedule['start_time']);
                $endTime = new DateTime($date . ' ' . $schedule['end_time']);
                
                while ($startTime < $endTime) {
                    $timeStr = $startTime->format('H:i:00');
                    
                    // Check booking status
                    $bookingStmt = $pdo->prepare("
                        SELECT COUNT(*) as count
                        FROM appointments
                        WHERE appointment_date = ?
                            AND appointment_time = ?
                            AND appointment_type = ?
                            AND status IN ('scheduled', 'confirmed')
                    ");
                    $bookingStmt->execute([$date, $timeStr, $department]);
                    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
                    
                    $slots[] = [
                        'time' => $timeStr,
                        'formatted_time' => $startTime->format('g:i A'),
                        'available' => ($booking['count'] < 1),
                        'doctor' => trim($schedule['fname'] . ' ' . $schedule['lname'])
                    ];
                    
                    $startTime->modify('+30 minutes');
                }
            }
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'date' => $date,
                    'slots' => $slots
                ]
            ]);
            break;
            
        case 'check_availability':
            // Check if a specific date/time is still available
            $date = $_GET['date'] ?? '';
            $time = $_GET['time'] ?? '';
            $department = $_GET['department'] ?? 'medical';
            $doctorRole = ($department === 'dental') ? 'dentist' : 'doctor';
            
            if (empty($date) || empty($time)) {
                echo json_encode(['success' => false, 'error' => 'Date and time required']);
                exit;
            }
            
            // Check doctor schedule exists
            $scheduleStmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM doctor_schedules ds
                INNER JOIN users u ON ds.user_id = u.id
                WHERE ds.schedule_date = ?
                    AND ds.is_available = 1
                    AND ds.schedule_type = 'available'
                    AND u.role = ?
                    AND ? BETWEEN ds.start_time AND ds.end_time
            ");
            $scheduleStmt->execute([$date, $doctorRole, $time]);
            $scheduleExists = $scheduleStmt->fetch()['count'] > 0;
            
            if (!$scheduleExists) {
                echo json_encode([
                    'success' => true,
                    'available' => false,
                    'reason' => 'No doctor scheduled'
                ]);
                exit;
            }
            
            // Check if already booked
            $bookingStmt = $pdo->prepare("
                SELECT COUNT(*) as count
                FROM appointments
                WHERE appointment_date = ?
                    AND appointment_time = ?
                    AND appointment_type = ?
                    AND status IN ('scheduled', 'confirmed')
            ");
            $bookingStmt->execute([$date, $time, $department]);
            $isBooked = $bookingStmt->fetch()['count'] > 0;
            
            echo json_encode([
                'success' => true,
                'available' => !$isBooked,
                'reason' => $isBooked ? 'Already booked' : 'Available'
            ]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Database error in get_doctor_availability.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'details' => $e->getMessage()
    ]);
} catch (Exception $e) {
    error_log("Error in get_doctor_availability.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred',
        'details' => $e->getMessage()
    ]);
}
<?php
/**
 * Real-time Appointment Synchronization System
 * Handles all appointment operations with instant updates across dashboards
 */

session_start();
require_once(__DIR__ . '/../config/database.php');

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

// Authentication check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDB();
$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'] ?? 'patient';

// ============================================
// HELPER FUNCTIONS
// ============================================

/**
 * Get last update timestamp for change detection
 */
function getLastUpdateTimestamp($pdo) {
    $stmt = $pdo->query("
        SELECT MAX(GREATEST(
            COALESCE(MAX(a.updated_at), '1970-01-01'),
            COALESCE(MAX(ds.updated_at), '1970-01-01')
        )) as last_update
        FROM appointments a
        CROSS JOIN doctor_schedules ds
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return strtotime($result['last_update'] ?? 'now');
}

/**
 * Convert time to 24-hour format
 */
function convertTo24Hour($time) {
    if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
        return $time;
    }
    
    if (strpos($time, ' ') === false) {
        return $time;
    }
    
    list($timePart, $modifier) = explode(' ', trim($time));
    list($hours, $minutes) = explode(':', $timePart);
    $hours = (int)$hours;
    
    if ($hours == 12) {
        $hours = $modifier === 'AM' ? 0 : 12;
    } elseif ($modifier === 'PM') {
        $hours += 12;
    }
    
    return sprintf('%02d:%02d:00', $hours, $minutes);
}

/**
 * Check if a time slot is available (no double booking)
 */
function isSlotAvailable($pdo, $date, $time, $excludeId = null) {
    $time24 = convertTo24Hour($time);
    
    $sql = "SELECT COUNT(*) as count 
            FROM appointments 
            WHERE appointment_date = ? 
            AND appointment_time = ? 
            AND status IN ('scheduled', 'confirmed')";
    
    $params = [$date, $time24];
    
    if ($excludeId) {
        $sql .= " AND id != ?";
        $params[] = $excludeId;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] == 0;
}

/**
 * Get available time slots for a specific date
 */
function getAvailableSlots($pdo, $date) {
    // Get doctor schedules for the date
    $stmt = $pdo->prepare("
        SELECT DISTINCT ds.start_time, ds.end_time, ds.user_id
        FROM doctor_schedules ds
        WHERE ds.schedule_date = ?
        AND ds.is_available = 1
        AND ds.schedule_type = 'available'
    ");
    $stmt->execute([$date]);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($schedules)) {
        return [];
    }
    
    // Get all booked appointments for the date
    $stmt = $pdo->prepare("
        SELECT appointment_time
        FROM appointments
        WHERE appointment_date = ?
        AND status IN ('scheduled', 'confirmed')
    ");
    $stmt->execute([$date]);
    $bookedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Generate available slots (hourly slots between start and end time)
    $availableSlots = [];
    foreach ($schedules as $schedule) {
        $start = new DateTime($schedule['start_time']);
        $end = new DateTime($schedule['end_time']);
        
        while ($start < $end) {
            $timeSlot = $start->format('H:i:s');
            if (!in_array($timeSlot, $bookedSlots)) {
                $availableSlots[] = [
                    'time' => $start->format('h:i A'),
                    'time24' => $timeSlot,
                    'available' => true
                ];
            }
            $start->modify('+1 hour');
        }
    }
    
    return $availableSlots;
}

/**
 * Sync appointment to Google Calendar
 */
function syncToCalendar($pdo, $appointmentId) {
    require_once(__DIR__ . '/calendar_api.php');
    return createCalendarEvent($pdo, $appointmentId);
}

/**
 * Send notification to affected users
 */
function notifyUsers($pdo, $appointmentId, $action) {
    // Get appointment details
    $stmt = $pdo->prepare("
        SELECT a.*, u.fname, u.lname, u.email, u.phone
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) return;
    
    // Log notification (implement email/SMS sending as needed)
    error_log("Notification: {$action} - Appointment #{$appointmentId} for {$appointment['fname']} {$appointment['lname']}");
}

// ============================================
// API ACTIONS
// ============================================

$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch ($action) {
        
        // ========== POLL FOR UPDATES ==========
        case 'poll':
            $lastKnown = $_GET['last_update'] ?? 0;
            $currentUpdate = getLastUpdateTimestamp($pdo);
            
            echo json_encode([
                'success' => true,
                'has_updates' => $currentUpdate > $lastKnown,
                'timestamp' => $currentUpdate
            ]);
            break;
            
        // ========== GET CALENDAR DATA ==========
        case 'get_calendar':
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');
            
            // Get all schedules for the month
            $stmt = $pdo->prepare("
                SELECT 
                    schedule_date,
                    COUNT(*) as schedule_count,
                    GROUP_CONCAT(CONCAT(start_time, '-', end_time)) as time_slots
                FROM doctor_schedules
                WHERE MONTH(schedule_date) = ?
                AND YEAR(schedule_date) = ?
                AND is_available = 1
                AND schedule_type = 'available'
                GROUP BY schedule_date
            ");
            $stmt->execute([$month, $year]);
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get all appointments for the month
            $stmt = $pdo->prepare("
                SELECT 
                    a.appointment_date,
                    a.appointment_time,
                    a.appointment_type,
                    a.status,
                    a.id,
                    u.fname,
                    u.lname
                FROM appointments a
                JOIN users u ON a.patient_id = u.id
                WHERE MONTH(a.appointment_date) = ?
                AND YEAR(a.appointment_date) = ?
                AND a.status != 'deleted'
                ORDER BY a.appointment_time
            ");
            $stmt->execute([$month, $year]);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Get unavailable dates
            $stmt = $pdo->prepare("
                SELECT schedule_date, reason
                FROM doctor_schedules
                WHERE MONTH(schedule_date) = ?
                AND YEAR(schedule_date) = ?
                AND schedule_type = 'unavailable'
            ");
            $stmt->execute([$month, $year]);
            $unavailable = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'schedules' => $schedules,
                'appointments' => $appointments,
                'unavailable' => $unavailable,
                'timestamp' => getLastUpdateTimestamp($pdo)
            ]);
            break;
            
        // ========== GET AVAILABLE SLOTS FOR DATE ==========
        case 'get_slots':
            $date = $_GET['date'] ?? null;
            if (!$date) {
                throw new Exception('Date required');
            }
            
            $slots = getAvailableSlots($pdo, $date);
            
            echo json_encode([
                'success' => true,
                'date' => $date,
                'slots' => $slots,
                'timestamp' => getLastUpdateTimestamp($pdo)
            ]);
            break;
            
        // ========== BOOK APPOINTMENT (Student) ==========
        case 'book':
            if ($userRole !== 'patient') {
                throw new Exception('Only patients can book appointments');
            }
            
            $date = $_POST['date'] ?? null;
            $time = $_POST['time'] ?? null;
            $type = $_POST['type'] ?? 'medical';
            $notes = $_POST['notes'] ?? '';
            
            if (!$date || !$time) {
                throw new Exception('Date and time required');
            }
            
            $time24 = convertTo24Hour($time);
            
            // Check if slot is available (prevent double booking)
            if (!isSlotAvailable($pdo, $date, $time24)) {
                echo json_encode([
                    'success' => false,
                    'error' => 'This time slot is no longer available',
                    'error_type' => 'slot_taken'
                ]);
                exit;
            }
            
            // Begin transaction
            $pdo->beginTransaction();
            
            try {
                // Create appointment
                $stmt = $pdo->prepare("
                    INSERT INTO appointments 
                    (patient_id, appointment_date, appointment_time, appointment_type, status, notes)
                    VALUES (?, ?, ?, ?, 'scheduled', ?)
                ");
                $stmt->execute([$userId, $date, $time24, $type, $notes]);
                $appointmentId = $pdo->lastInsertId();
                
                // Sync to calendar
                $calendarResult = syncToCalendar($pdo, $appointmentId);
                
                $pdo->commit();
                
                // Send notifications
                notifyUsers($pdo, $appointmentId, 'booked');
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Appointment booked successfully',
                    'appointment_id' => $appointmentId,
                    'calendar_synced' => $calendarResult['success'] ?? false,
                    'timestamp' => getLastUpdateTimestamp($pdo)
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        // ========== CANCEL APPOINTMENT ==========
        case 'cancel':
            $appointmentId = $_POST['id'] ?? null;
            if (!$appointmentId) {
                throw new Exception('Appointment ID required');
            }
            
            // Verify ownership or admin rights
            if ($userRole === 'patient') {
                $stmt = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND patient_id = ?");
                $stmt->execute([$appointmentId, $userId]);
                if (!$stmt->fetch()) {
                    throw new Exception('Appointment not found or access denied');
                }
            }
            
            // Begin transaction
            $pdo->beginTransaction();
            
            try {
                // Update appointment status
                $stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET status = 'cancelled', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$appointmentId]);
                
                // Delete from calendar
                require_once(__DIR__ . '/calendar_api.php');
                deleteCalendarEvent($pdo, $appointmentId);
                
                $pdo->commit();
                
                // Send notifications
                notifyUsers($pdo, $appointmentId, 'cancelled');
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Appointment cancelled successfully',
                    'timestamp' => getLastUpdateTimestamp($pdo)
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        // ========== ADD/UPDATE SCHEDULE (Employee) ==========
        case 'update_schedule':
            if (!in_array($userRole, ['doctor', 'nurse', 'staff', 'admin'])) {
                throw new Exception('Access denied');
            }
            
            $date = $_POST['date'] ?? null;
            $startTime = $_POST['start_time'] ?? null;
            $endTime = $_POST['end_time'] ?? null;
            $scheduleType = $_POST['schedule_type'] ?? 'available';
            $reason = $_POST['reason'] ?? '';
            
            if (!$date) {
                throw new Exception('Date required');
            }
            
            $pdo->beginTransaction();
            
            try {
                if ($scheduleType === 'unavailable') {
                    // Mark date as unavailable
                    $stmt = $pdo->prepare("
                        INSERT INTO doctor_schedules 
                        (user_id, schedule_date, schedule_type, is_available, reason)
                        VALUES (?, ?, 'unavailable', 0, ?)
                        ON DUPLICATE KEY UPDATE
                        schedule_type = 'unavailable',
                        is_available = 0,
                        reason = VALUES(reason),
                        updated_at = NOW()
                    ");
                    $stmt->execute([$userId, $date, $reason]);
                    
                    // Cancel any existing appointments for that date
                    $stmt = $pdo->prepare("
                        UPDATE appointments
                        SET status = 'cancelled', notes = CONCAT(notes, ' [Cancelled: Doctor unavailable]')
                        WHERE appointment_date = ?
                        AND status IN ('scheduled', 'confirmed')
                    ");
                    $stmt->execute([$date]);
                    
                } else {
                    // Add available schedule
                    if (!$startTime || !$endTime) {
                        throw new Exception('Start and end time required for available schedule');
                    }
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO doctor_schedules 
                        (user_id, schedule_date, start_time, end_time, schedule_type, is_available)
                        VALUES (?, ?, ?, ?, 'available', 1)
                        ON DUPLICATE KEY UPDATE
                        start_time = VALUES(start_time),
                        end_time = VALUES(end_time),
                        schedule_type = 'available',
                        is_available = 1,
                        updated_at = NOW()
                    ");
                    $stmt->execute([$userId, $date, $startTime, $endTime]);
                }
                
                $pdo->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Schedule updated successfully',
                    'timestamp' => getLastUpdateTimestamp($pdo)
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        // ========== GET APPOINTMENTS LIST ==========
        case 'get_appointments':
            $filter = $_GET['filter'] ?? 'all'; // today, upcoming, cancelled
            $date = $_GET['date'] ?? null;
            
            $sql = "SELECT 
                        a.*,
                        u.fname,
                        u.lname,
                        u.email,
                        u.phone
                    FROM appointments a
                    JOIN users u ON a.patient_id = u.id
                    WHERE 1=1";
            
            $params = [];
            
            // Apply filters
            if ($userRole === 'patient') {
                $sql .= " AND a.patient_id = ?";
                $params[] = $userId;
            }
            
            switch ($filter) {
                case 'today':
                    $sql .= " AND a.appointment_date = CURDATE()";
                    break;
                case 'upcoming':
                    $sql .= " AND a.appointment_date >= CURDATE() AND a.status IN ('scheduled', 'confirmed')";
                    break;
                case 'cancelled':
                    $sql .= " AND a.status = 'cancelled'";
                    break;
            }
            
            if ($date) {
                $sql .= " AND a.appointment_date = ?";
                $params[] = $date;
            }
            
            $sql .= " AND a.status != 'deleted'";
            $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'appointments' => $appointments,
                'timestamp' => getLastUpdateTimestamp($pdo)
            ]);
            break;
            
        // ========== UPDATE APPOINTMENT STATUS (Admin/Staff) ==========
        case 'update_status':
            if (!in_array($userRole, ['admin', 'doctor', 'nurse', 'staff'])) {
                throw new Exception('Access denied');
            }
            
            $appointmentId = $_POST['id'] ?? null;
            $status = $_POST['status'] ?? null;
            
            if (!$appointmentId || !$status) {
                throw new Exception('Appointment ID and status required');
            }
            
            $validStatuses = ['scheduled', 'confirmed', 'completed', 'cancelled'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Invalid status');
            }
            
            $stmt = $pdo->prepare("
                UPDATE appointments
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $appointmentId]);
            
            notifyUsers($pdo, $appointmentId, "status_changed_to_{$status}");
            
            echo json_encode([
                'success' => true,
                'message' => 'Appointment status updated',
                'timestamp' => getLastUpdateTimestamp($pdo)
            ]);
            break;
            
        // ========== CHECK SLOT AVAILABILITY ==========
        case 'check_availability':
            $date = $_GET['date'] ?? null;
            $time = $_GET['time'] ?? null;
            
            if (!$date || !$time) {
                throw new Exception('Date and time required');
            }
            
            $available = isSlotAvailable($pdo, $date, convertTo24Hour($time));
            
            echo json_encode([
                'success' => true,
                'available' => $available,
                'timestamp' => getLastUpdateTimestamp($pdo)
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    error_log('Appointment Sync Error: ' . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
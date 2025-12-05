<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== NULL && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        error_log("Fatal error in appointment_sync.php: " . $error['message'] . " in " . $error['file'] . " on line " . $error['line']);
        
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        echo json_encode([
            'success' => false,
            'error' => 'An internal server error occurred. Please try again.',
            'error_type' => 'fatal_error'
        ]);
        exit;
    }
});

if (ob_get_level() === 0) {
    ob_start();
}

if (!headers_sent()) {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-cache, must-revalidate');
}

session_start();

try {
    require_once(__DIR__ . '/../config/database.php');
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log("Database connection error: " . $e->getMessage());
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed. Please contact support.',
        'error_type' => 'database_error'
    ]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $pdo = getDB();
    if (!$pdo) {
        throw new Exception('Database connection is null');
    }
} catch (Throwable $e) {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    error_log("Failed to get database connection: " . $e->getMessage());
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed. Please try again.',
        'error_type' => 'database_error'
    ]);
    exit;
}

$userId = $_SESSION['user_id'] ?? null;
$userRole = $_SESSION['role'] ?? 'patient';

if (!$userId) {
    // Clean output buffer before sending JSON
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode(['success' => false, 'error' => 'Unauthorized - Session expired']);
    exit;
}

function getLastUpdateTimestamp($pdo) {
    $stmt = $pdo->query("
        SELECT MAX(last_update) as last_update
        FROM (
            SELECT MAX(updated_at) as last_update FROM appointments
            UNION ALL
            SELECT MAX(updated_at) as last_update FROM doctor_schedules
        ) AS updates
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return strtotime($result['last_update'] ?? 'now');
}

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

function isSlotAvailable($pdo, $date, $time, $excludeId = null, $type = null) {
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
    
    if ($type) {
        $sql .= " AND appointment_type = ?";
        $params[] = $type;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    return $result['count'] == 0;
}

function getAvailableSlots($pdo, $date, $type = null) {
    $roleFilter = null;
    if ($type) {
        $roleFilter = ($type === 'dental') ? 'dentist' : 'doctor';
    }
    
    $sql = "
        SELECT DISTINCT ds.start_time, ds.end_time, ds.user_id, u.fname, u.lname
        FROM doctor_schedules ds
        INNER JOIN users u ON ds.user_id = u.id
        WHERE ds.schedule_date = ?
        AND ds.is_available = 1
        AND ds.schedule_type = 'available'
    ";
    $params = [$date];
    if ($roleFilter) {
        $sql .= " AND u.role = ?";
        $params[] = $roleFilter;
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($schedules)) {
        return [];
    }
    
    $bookingSql = "
        SELECT appointment_time
        FROM appointments
        WHERE appointment_date = ?
        AND status IN ('scheduled', 'confirmed')
    ";
    $bookingParams = [$date];
    if ($type) {
        $bookingSql .= " AND appointment_type = ?";
        $bookingParams[] = $type;
    }
    $stmt = $pdo->prepare($bookingSql);
    $stmt->execute($bookingParams);
    $bookedSlots = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
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
                    'available' => true,
                    'department' => $type,
                    'doctor' => trim(($schedule['fname'] ?? '') . ' ' . ($schedule['lname'] ?? ''))
                ];
            }
            $start->modify('+1 hour');
        }
    }
    
    return $availableSlots;
}

function syncToCalendar($pdo, $appointmentId) {
    require_once(__DIR__ . '/../api/calendar_api.php');
    
    $stmt = $pdo->prepare("
        SELECT 
            a.*,
            u.fname,
            u.lname
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointmentId]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        return [
            'success' => false,
            'error' => 'Appointment not found'
        ];
    }
    
    $appointmentData = [
        'appointment_id' => $appointment['id'],
        'date' => $appointment['appointment_date'],
        'time' => $appointment['appointment_time'],
        'type' => $appointment['appointment_type'],
        'notes' => $appointment['notes'] ?? '',
        'name' => trim(($appointment['fname'] ?? '') . ' ' . ($appointment['lname'] ?? '')),
        'patient_id' => $appointment['patient_id']
    ];
    
    return createCalendarEvent($appointmentData);
}

function notifyUsers($pdo, $appointmentId, $action) {
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
    
    // Create database notifications
    try {
        if ($action === 'booked') {
            // Notify medical/dental staff when appointment is booked
            $patientName = trim($appointment['fname'] . ' ' . $appointment['lname']);
            $appointmentDate = date('F j, Y', strtotime($appointment['appointment_date']));
            $appointmentTime = date('g:i A', strtotime($appointment['appointment_time']));
            $appointmentType = $appointment['appointment_type'];
            
            $message = "New {$appointmentType} appointment booked by {$patientName} on {$appointmentDate} at {$appointmentTime}";
            
            $data = json_encode([
                'appointment_id' => $appointmentId,
                'appointment_type' => $appointmentType,
                'patient_name' => $patientName,
                'appointment_date' => $appointment['appointment_date'],
                'appointment_time' => $appointment['appointment_time'],
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $stmt = $pdo->prepare("
                INSERT INTO notifications (type, message, data, status, created_at) 
                VALUES ('appointment_booked', ?, ?, 'unread', NOW())
            ");
            $stmt->execute([$message, $data]);
            
        } elseif ($action === 'cancelled') {
            // Notify all students when any appointment is cancelled - slot is now available
            $patientName = trim($appointment['fname'] . ' ' . $appointment['lname']);
            $appointmentDate = date('F j, Y', strtotime($appointment['appointment_date']));
            $appointmentTime = date('g:i A', strtotime($appointment['appointment_time']));
            $appointmentType = ucfirst($appointment['appointment_type']);
            $dayOfWeek = date('l', strtotime($appointment['appointment_date']));
            $deptName = ($appointment['appointment_type'] === 'dental') ? 'Dental Department' : 'Medical Department';
            $deptIcon = ($appointment['appointment_type'] === 'dental') ? '🦷' : '🩺';
            
            // Create an actionable message indicating slot is available
            $message = "{$deptIcon} Appointment slot available: {$deptName} on {$appointmentDate} ({$dayOfWeek}) at {$appointmentTime}. Click to book now!";
            
            $data = json_encode([
                'appointment_id' => $appointmentId,
                'appointment_type' => $appointment['appointment_type'],
                'patient_name' => $patientName,
                'appointment_date' => $appointment['appointment_date'],
                'appointment_time' => $appointment['appointment_time'],
                'department' => $deptName,
                'formatted_date' => $appointmentDate,
                'formatted_time' => $appointmentTime,
                'day_of_week' => $dayOfWeek,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            // Create notification for all students (they will see it in their notification system)
            // This notification is clickable and will mark as read when clicked
            $stmt = $pdo->prepare("
                INSERT INTO notifications (type, message, data, status, created_at) 
                VALUES ('appointment_cancelled', ?, ?, 'unread', NOW())
            ");
            $stmt->execute([$message, $data]);
        }
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
    }
}

// ============================================
// API ACTIONS
// ============================================

$action = $_GET['action'] ?? $_POST['action'] ?? 'poll';

// Ensure action is always a string
if (!is_string($action)) {
    $action = 'poll';
}

try {
    switch ($action) {
        
        // ========== POLL FOR UPDATES ==========
        case 'poll':
            $lastKnown = $_GET['last_update'] ?? 0;
            $currentUpdate = getLastUpdateTimestamp($pdo);
            
            // Clean output buffer before sending JSON
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
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
            $appointmentType = $_GET['appointment_type'] ?? null; // Filter by type if provided
            
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
            
            // Get appointments for the month (filtered by type if provided)
            // Include all relevant statuses: scheduled, confirmed, completed, and cancelled
            // Explicitly include these statuses to ensure all appointments are shown (except deleted)
            $sql = "
                SELECT 
                    a.id,
                    a.patient_id,
                    a.appointment_date,
                    a.appointment_time,
                    a.appointment_type,
                    a.status,
                    a.notes,
                    u.fname,
                    u.lname,
                    u.email,
                    u.phone
                FROM appointments a
                JOIN users u ON a.patient_id = u.id
                WHERE MONTH(a.appointment_date) = ?
                AND YEAR(a.appointment_date) = ?
                AND COALESCE(a.status, '') IN ('scheduled', 'confirmed', 'completed', 'cancelled')";
            
            $params = [$month, $year];
            
            if ($appointmentType) {
                $sql .= " AND a.appointment_type = ?";
                $params[] = $appointmentType;
            }
            
            $sql .= " ORDER BY a.appointment_date, a.appointment_time";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
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
            
            // Clean output buffer before sending JSON
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
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
            $slotType = $_GET['type'] ?? $_GET['department'] ?? null;
            
            $slots = getAvailableSlots($pdo, $date, $slotType);
            
            // Clean output buffer before sending JSON
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
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
            if (!isSlotAvailable($pdo, $date, $time24, null, $type)) {
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
                
                // Log appointment creation
                try {
                    require_once(__DIR__ . '/../includes/appointment_logger.php');
                    // Get appointment details for logging
                    $stmt = $pdo->prepare("
                        SELECT 
                            a.*,
                            u.fname,
                            u.lname
                        FROM appointments a
                        JOIN users u ON a.patient_id = u.id
                        WHERE a.id = ?
                    ");
                    $stmt->execute([$appointmentId]);
                    $appointmentData = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($appointmentData) {
                        logAppointmentCreated($pdo, $appointmentId, $userId, $appointmentData);
                    }
                } catch (Exception $e) {
                    error_log("Failed to log appointment creation: " . $e->getMessage());
                    // Continue even if logging fails
                }
                
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
            try {
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
                
                // Fetch appointment details BEFORE cancellation for email notification
                $stmt = $pdo->prepare("
                    SELECT appointment_date, appointment_time, appointment_type 
                    FROM appointments 
                    WHERE id = ?
                ");
                $stmt->execute([$appointmentId]);
                $appointmentDetails = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Begin transaction
                if (!$pdo->inTransaction()) {
                    $pdo->beginTransaction();
                }
            
            try {
                // Fetch calendar event id if exists
                $stmt = $pdo->prepare("SELECT calendar_event_id FROM appointments WHERE id = ?");
                $stmt->execute([$appointmentId]);
                $calendarEventId = $stmt->fetchColumn();
                
                // Get appointment details before cancellation for logging
                $stmt = $pdo->prepare("
                    SELECT 
                        a.*,
                        u.fname,
                        u.lname
                    FROM appointments a
                    JOIN users u ON a.patient_id = u.id
                    WHERE a.id = ?
                ");
                $stmt->execute([$appointmentId]);
                $appointmentBeforeCancel = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Update appointment status
                $stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET status = 'cancelled', updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$appointmentId]);
                
                // Log appointment cancellation (fast operation)
                if ($appointmentBeforeCancel) {
                    try {
                        require_once(__DIR__ . '/../includes/appointment_logger.php');
                        logAppointmentCancelled($pdo, $appointmentId, $userId, $appointmentBeforeCancel);
                    } catch (Exception $e) {
                        error_log("Failed to log appointment cancellation: " . $e->getMessage());
                        // Continue even if logging fails
                    }
                }
                
                $pdo->commit();
                
                // Return response immediately (< 1 second)
                $responseData = [
                    'success' => true,
                    'message' => 'Appointment cancelled successfully. Processing notifications...',
                    'timestamp' => time()
                ];
                                
                // Clean output buffers before sending JSON
                while (ob_get_level() > 0) {
                    ob_end_clean();
                }
                
                // Send response immediately
                if (!headers_sent()) {
                    header('Content-Type: application/json; charset=utf-8');
                    header('Cache-Control: no-cache, must-revalidate');
                }
                
                echo json_encode($responseData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                
                // Close connection and continue processing in background
                if (function_exists('fastcgi_finish_request')) {
                    fastcgi_finish_request();
                                } else {
                    // Fallback: close connection manually
                    ignore_user_abort(true);
                    header('Connection: close');
                    header('Content-Length: ' . strlen(json_encode($responseData)));
                    flush();
                    if (function_exists('session_write_close')) {
                        session_write_close();
                                }
                }
                
                // Process notifications, email, and calendar deletion in background
                try {
                    notifyUsers($pdo, $appointmentId, 'cancelled');
                    error_log("✓ Background: Notifications sent for cancelled appointment {$appointmentId}");
                } catch (Exception $notifyError) {
                    error_log("✗ Background: Notification error - " . $notifyError->getMessage());
                        }
                        
                // Delete calendar event in background
                if ($calendarEventId) {
                    try {
                        require_once(__DIR__ . '/../api/calendar_api.php');
                        $deleteResult = deleteCalendarEvent($calendarEventId);
                        
                        if ($deleteResult && isset($deleteResult['success']) && $deleteResult['success']) {
                            error_log("✓ Background: Calendar event deleted - {$calendarEventId}");
                            // Clear calendar_event_id
                            $stmt = $pdo->prepare("UPDATE appointments SET calendar_event_id = NULL WHERE id = ?");
                            $stmt->execute([$appointmentId]);
                        } else {
                            error_log("✗ Background: Calendar deletion failed - {$calendarEventId}");
                            // Still clear calendar_event_id
                        $stmt = $pdo->prepare("UPDATE appointments SET calendar_event_id = NULL WHERE id = ?");
                        $stmt->execute([$appointmentId]);
                        }
                    } catch (Exception $calendarError) {
                        error_log("✗ Background: Calendar deletion error - " . $calendarError->getMessage());
                        // Still clear calendar_event_id
                        try {
                            $stmt = $pdo->prepare("UPDATE appointments SET calendar_event_id = NULL WHERE id = ?");
                            $stmt->execute([$appointmentId]);
                        } catch (Exception $e) {
                            error_log("Failed to clear calendar_event_id: " . $e->getMessage());
                        }
                    }
                }
                
                // Send email notification in background
                if ($appointmentDetails) {
                    try {
                        require_once(__DIR__ . '/../student/includes/email_helper.php');
                        $emailResult = sendCancellationNotificationToStudents($pdo, [
                            'date' => $appointmentDetails['appointment_date'],
                            'time' => $appointmentDetails['appointment_time'],
                            'type' => $appointmentDetails['appointment_type']
                        ]);
                        
                        if ($emailResult['success']) {
                            error_log("✓ Background: Cancellation emails sent to {$emailResult['sent_count']} student(s)");
                        } else {
                            error_log("✗ Background: Email error - " . $emailResult['message']);
                        }
                    } catch (Exception $emailError) {
                        error_log("✗ Background: Email error - " . $emailError->getMessage());
                    }
                }
                
                exit();
                
            } catch (Exception $e) {
                // Rollback transaction if active
                if ($pdo->inTransaction()) {
                    try {
                        $pdo->rollBack();
                    } catch (Exception $rollbackError) {
                        error_log("Rollback error: " . $rollbackError->getMessage());
                    }
                }
                // Re-throw to be caught by outer handler
                throw $e;
            } catch (Throwable $e) {
                // Rollback transaction if active
                if ($pdo->inTransaction()) {
                    try {
                        $pdo->rollBack();
                    } catch (Exception $rollbackError) {
                        error_log("Rollback error: " . $rollbackError->getMessage());
                    }
                }
                // Re-throw to be caught by outer handler
                throw $e;
            }
            } catch (Exception $e) {
                // Outer catch for any errors before inner try
                if ($pdo->inTransaction()) {
                    try {
                        $pdo->rollBack();
                    } catch (Exception $rollbackError) {
                        error_log("Rollback error: " . $rollbackError->getMessage());
                    }
                }
                throw $e;
            } catch (Throwable $e) {
                // Outer catch for fatal errors
                if ($pdo->inTransaction()) {
                    try {
                        $pdo->rollBack();
                    } catch (Exception $rollbackError) {
                        error_log("Rollback error: " . $rollbackError->getMessage());
                    }
                }
                throw $e;
            }
            break;
        
        // ========== SYNC APPOINTMENT TO CALENDAR ==========
        case 'sync':
            $appointmentId = $_POST['id'] ?? null;
            if (!$appointmentId) {
                throw new Exception('Appointment ID required');
            }
            
            if ($userRole === 'patient') {
                $stmt = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND patient_id = ?");
                $stmt->execute([$appointmentId, $userId]);
                if (!$stmt->fetch()) {
                    throw new Exception('Appointment not found or access denied');
                }
            }
            
            $result = syncToCalendar($pdo, $appointmentId);
            if (!($result['success'] ?? false)) {
                throw new Exception($result['error'] ?? 'Failed to sync appointment');
            }
            
            notifyUsers($pdo, $appointmentId, 'synced_to_calendar');
            
            // Clean output buffer before sending JSON
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Appointment synced to calendar',
                'timestamp' => getLastUpdateTimestamp($pdo)
            ]);
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
            $typeFilter = $_GET['type'] ?? $_GET['category'] ?? null;
            $requestedPatientId = $_GET['patient_id'] ?? null;
            
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
            } elseif (!empty($requestedPatientId)) {
                $sql .= " AND a.patient_id = ?";
                $params[] = $requestedPatientId;
            }
            
            if ($typeFilter) {
                $allowedTypes = ['medical', 'dental'];
                if (!in_array($typeFilter, $allowedTypes, true)) {
                    throw new Exception('Invalid appointment category');
                }
                $sql .= " AND a.appointment_type = ?";
                $params[] = $typeFilter;
            }
            
            switch ($filter) {
                case 'today':
                    $sql .= " AND a.appointment_date = CURDATE()";
                    // For today, show all statuses except deleted
                    break;
                case 'upcoming':
                    $sql .= " AND a.appointment_date >= CURDATE() AND a.status IN ('scheduled', 'confirmed')";
                    break;
                case 'cancelled':
                    $sql .= " AND a.status = 'cancelled'";
                    break;
                case 'completed':
                    $sql .= " AND a.status = 'completed'";
                    break;
                case 'all':
                    // For 'all' filter, return ALL appointments including completed
                    // This is used for stats calculation
                    // No status filter - show everything
                    break;
            }
            
            if ($date) {
                $sql .= " AND a.appointment_date = ?";
                $params[] = $date;
            }
            
            $sql .= " AND a.status != 'deleted'";
            $sql .= " ORDER BY a.appointment_date DESC, a.appointment_time DESC";
            
            try {
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Database error in get_appointments: " . $e->getMessage());
                throw new Exception('Failed to fetch appointments: ' . $e->getMessage());
            }
            
            // Clean output buffer before sending JSON
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            echo json_encode([
                'success' => true,
                'appointments' => $appointments,
                'timestamp' => getLastUpdateTimestamp($pdo)
            ]);
            break;
            
        // ========== UPDATE APPOINTMENT STATUS (Admin/Staff) ==========
        case 'update_status':
            if (!in_array($userRole, ['admin', 'doctor', 'nurse', 'staff', 'dentist'])) {
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
            
            // Begin transaction for atomic operations
            $pdo->beginTransaction();
            
            try {
                // Get appointment details before updating
                $stmt = $pdo->prepare("
                    SELECT 
                        a.*,
                        u.fname,
                        u.lname,
                        u.email
                    FROM appointments a
                    JOIN users u ON a.patient_id = u.id
                    WHERE a.id = ?
                ");
                $stmt->execute([$appointmentId]);
                $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$appointment) {
                    throw new Exception('Appointment not found');
                }
                
                $oldStatus = $appointment['status'] ?? 'scheduled';
                
                // Update appointment status
                $stmt = $pdo->prepare("
                    UPDATE appointments
                    SET status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$status, $appointmentId]);
                
                // Log appointment status change
                try {
                    require_once(__DIR__ . '/../includes/appointment_logger.php');
                    logAppointmentStatusChange($pdo, $appointmentId, $userId, $oldStatus, $status, $appointment);
                } catch (Exception $e) {
                    error_log("Failed to log appointment status change: " . $e->getMessage());
                    // Continue even if logging fails
                }
                
                // Handle CANCEL: Free up the time slot
                if ($status === 'cancelled') {
                    // Delete calendar event if exists
                    if (!empty($appointment['calendar_event_id'])) {
                        try {
                            require_once(__DIR__ . '/../api/calendar_api.php');
                            deleteCalendarEvent($appointment['calendar_event_id']);
                            $stmt = $pdo->prepare("UPDATE appointments SET calendar_event_id = NULL WHERE id = ?");
                            $stmt->execute([$appointmentId]);
                        } catch (Exception $e) {
                            error_log("Calendar deletion error: " . $e->getMessage());
                            // Continue even if calendar deletion fails
                        }
                    }
                    
                    // The time slot is automatically freed since the appointment is cancelled
                    // and isSlotAvailable() checks for status IN ('scheduled', 'confirmed')
                }
                
                // Handle COMPLETE: Auto-generate medical record
                if ($status === 'completed') {
                    // Check if medical record already exists for this appointment
                    $stmt = $pdo->prepare("
                        SELECT id FROM medical_records 
                        WHERE patient_id = ? 
                        AND visit_date = ? 
                        AND visit_time = ?
                        LIMIT 1
                    ");
                    $stmt->execute([
                        $appointment['patient_id'],
                        $appointment['appointment_date'],
                        $appointment['appointment_time']
                    ]);
                    
                    if (!$stmt->fetch()) {
                        // Create medical record from appointment
                        // Note: This is a placeholder record created when appointment is completed
                        // without a proper medical/dental record. These fields should be NULL or empty
                        // as actual clinical data should be entered through the proper forms.
                        // Use appointment notes if available, otherwise set to NULL (not a system message)
                        $chiefComplaint = !empty($appointment['notes']) ? $appointment['notes'] : null;
                        $diagnosis = 'Appointment completed - ' . ucfirst($appointment['appointment_type']) . ' consultation';
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO medical_records 
                            (patient_id, employee_id, visit_date, visit_time, chief_complaint, 
                             diagnosis, treatment_instructions, created_at, updated_at)
                            VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ");
                        
                        $employeeId = $userId; // Current doctor/dentist
                        // Set treatment_instructions to NULL instead of a completion message
                        // Actual treatment data should be entered through proper medical/dental record forms
                        $treatmentInstructions = null;
                        
                        $stmt->execute([
                            $appointment['patient_id'],
                            $employeeId,
                            $appointment['appointment_date'],
                            $appointment['appointment_time'],
                            $chiefComplaint,
                            $diagnosis,
                            $treatmentInstructions
                        ]);
                        
                        $recordId = $pdo->lastInsertId();
                        
                        // Create visit log entry
                        try {
                            $stmt = $pdo->prepare("
                                SELECT CONCAT(fname, ' ', lname) as name FROM users WHERE id = ?
                            ");
                            $stmt->execute([$employeeId]);
                            $physician = $stmt->fetch(PDO::FETCH_ASSOC);
                            $physicianName = $physician ? 'Dr. ' . $physician['name'] : 'Staff';
                            
                            $stmt = $pdo->prepare("
                                INSERT INTO visit_logs 
                                (patient_id, medical_record_id, purpose, physician_name, visit_date)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $appointment['patient_id'],
                                $recordId,
                                $chiefComplaint,
                                $physicianName,
                                $appointment['appointment_date']
                            ]);
                        } catch (Exception $e) {
                            error_log("Visit log creation error: " . $e->getMessage());
                            // Continue even if visit log creation fails
                        }
                    }
                }
                
                $pdo->commit();
                
                // Send notifications
                notifyUsers($pdo, $appointmentId, "status_changed_to_{$status}");
                
                echo json_encode([
                    'success' => true,
                    'message' => $status === 'completed' 
                        ? 'Appointment completed and medical record created' 
                        : ($status === 'cancelled' 
                            ? 'Appointment cancelled and time slot freed' 
                            : 'Appointment status updated'),
                    'timestamp' => getLastUpdateTimestamp($pdo),
                    'appointment_id' => $appointmentId,
                    'status' => $status
                ]);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
            break;
            
        // ========== CHECK SLOT AVAILABILITY ==========
        case 'check_availability':
            $date = $_GET['date'] ?? null;
            $time = $_GET['time'] ?? null;
            $slotType = $_GET['type'] ?? $_GET['department'] ?? null;
            
            if (!$date || !$time) {
                throw new Exception('Date and time required');
            }
            
            $available = isSlotAvailable($pdo, $date, convertTo24Hour($time), null, $slotType);
            
            echo json_encode([
                'success' => true,
                'available' => $available,
                'timestamp' => getLastUpdateTimestamp($pdo)
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    // Clean output buffer before sending any JSON response
    // This ensures no PHP warnings/notices break the JSON
    if (ob_get_level() > 0) {
        ob_end_clean();
    }
    
} catch (Exception $e) {
    // Clean ALL output buffers that might have been generated (PHP warnings, notices, etc.)
    while (ob_get_level() > 0) {
        $bufferContent = ob_get_contents();
        ob_end_clean();
        if (!empty($bufferContent) && trim($bufferContent) !== '') {
            error_log("Unexpected output in buffer (Exception catch): " . substr($bufferContent, 0, 500));
        }
    }
    
    error_log('Appointment Sync Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Ensure we return valid JSON with proper headers
    if (!headers_sent()) {
        header_remove('Content-Type');
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
    }
    
    $response = json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if ($response === false) {
        $response = json_encode([
            'success' => false,
            'error' => 'An error occurred while processing your request'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    echo $response;
    exit;
} catch (Error $e) {
    // Catch fatal errors and PHP errors
    // Clean ALL output buffers
    while (ob_get_level() > 0) {
        $bufferContent = ob_get_contents();
        ob_end_clean();
        if (!empty($bufferContent) && trim($bufferContent) !== '') {
            error_log("Unexpected output in buffer (Error catch): " . substr($bufferContent, 0, 500));
        }
    }
    
    error_log('Appointment Sync Fatal Error: ' . $e->getMessage());
    error_log('File: ' . $e->getFile() . ' Line: ' . $e->getLine());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // Ensure we return valid JSON with proper headers
    if (!headers_sent()) {
        header_remove('Content-Type');
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
    }
    
    $response = json_encode([
        'success' => false,
        'error' => 'An internal server error occurred. Please try again.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
    if ($response === false) {
        $response = json_encode([
            'success' => false,
            'error' => 'An error occurred while processing your request'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    
    echo $response;
    exit;
}
<?php
session_start();
require_once(__DIR__ . '/../db.php');
require_once(__DIR__ . '/../config/sms.php');

header('Content-Type: application/json');

// ==================================================
// 🔒 AUTHENTICATION CHECK
// ==================================================
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// ==================================================
// 🔧 CALENDAR API CONFIGURATION
// ==================================================
define('CALENDAR_API_URL', 'https://v1.nocodeapi.com/nnglysl04/calendar/DvmKqOkWtGBoIMAL');

// ==================================================
// 📡 CALENDAR REQUEST FUNCTION
// ==================================================
function calendarRequest($endpoint, $method = 'GET', $data = null) {
    $url = CALENDAR_API_URL . $endpoint;
    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => true
    ]);

    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    error_log("Calendar API - Method: $method, Endpoint: $endpoint, HTTP Code: $httpCode");

    if ($error) {
        error_log("Calendar API Error: $error");
        return ['success' => false, 'error' => $error];
    }

    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $result];
    }

    error_log("Calendar API Failed: " . json_encode($result));
    return [
        'success' => false, 
        'error' => $result['error']['message'] ?? 'Calendar API Error', 
        'code' => $httpCode
    ];
}

// ==================================================
// ⏰ TIME CONVERSION
// ==================================================
function convertTo24Hour($time) {
    // If already in 24-hour format, return as is
    if (strpos($time, ' ') === false && strlen($time) === 8) {
        return $time;
    }
    
    // Handle 12-hour format
    if (strpos($time, ' ') === false) return $time;

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

function convertTo12Hour($time) {
    list($hours, $minutes) = explode(':', $time);
    $hours = (int)$hours;
    $ampm = $hours >= 12 ? 'PM' : 'AM';
    $hours = $hours % 12 ?: 12;
    return sprintf('%d:%02d %s', $hours, (int)$minutes, $ampm);
}

// ==================================================
// 📅 CREATE CALENDAR EVENT
// ==================================================
function createCalendarEvent($pdo, $appointmentId) {
    try {
        // Get appointment details with patient info
        $stmt = $pdo->prepare("
            SELECT a.*, u.fname, u.lname, u.email, u.phone 
            FROM appointments a
            JOIN users u ON a.patient_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$appointmentId]);
        $apt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$apt) {
            return ['success' => false, 'error' => 'Appointment not found'];
        }

        // Build event details
        $patientName = trim($apt['fname'] . ' ' . $apt['lname']);
        $startDateTime = $apt['appointment_date'] . 'T' . $apt['appointment_time'];
        $endDateTime = (new DateTime($startDateTime))->modify('+1 hour')->format('Y-m-d\TH:i:s');

        $eventData = [
            'summary' => sprintf(
                '%s %s - %s',
                $apt['appointment_type'] === 'medical' ? '🏥' : '🦷',
                $patientName,
                strtoupper($apt['appointment_type'])
            ),
            'location' => 'BSU Clinic, Batangas State University',
            'description' => sprintf(
                "BSU Clinic Appointment\n\n" .
                "Patient: %s\n" .
                "Email: %s\n" .
                "Phone: %s\n" .
                "Type: %s\n" .
                "Status: %s\n\n" .
                "Appointment ID: %d",
                $patientName,
                $apt['email'],
                $apt['phone'] ?? 'N/A',
                ucfirst($apt['appointment_type']),
                ucfirst($apt['status']),
                $appointmentId
            ),
            'start' => [
                'dateTime' => $startDateTime,
                'timeZone' => 'Asia/Manila'
            ],
            'end' => [
                'dateTime' => $endDateTime,
                'timeZone' => 'Asia/Manila'
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'email', 'minutes' => 1440], // 24 hours
                    ['method' => 'popup', 'minutes' => 30]
                ]
            ],
            'attendees' => [
                ['email' => $apt['email'], 'responseStatus' => 'accepted']
            ],
            'sendNotifications' => true,
            'colorId' => $apt['appointment_type'] === 'medical' ? '11' : '9' // Red for medical, Blue for dental
        ];

        // Create event in Google Calendar
        $result = calendarRequest('/event', 'POST', $eventData);

        if ($result['success']) {
            $eventId = $result['data']['id'];
            
            // Store event ID in database
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET calendar_event_id = ?, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$eventId, $appointmentId]);

            // Log to history
            $stmt = $pdo->prepare("
                INSERT INTO appointment_history 
                (appointment_id, action, changed_by, notes, created_at)
                VALUES (?, 'calendar_created', ?, 'Synced to Google Calendar', NOW())
            ");
            $stmt->execute([$appointmentId, $_SESSION['user_id']]);

            // Send SMS notification if available
            if (!empty($apt['phone']) && function_exists('sendSms')) {
                $smsMessage = sprintf(
                    "BSU Clinic: Your %s appointment is confirmed!\n\n" .
                    "📅 %s at %s\n" .
                    "📍 BSU Clinic\n\n" .
                    "See you soon!",
                    $apt['appointment_type'],
                    date('M d, Y', strtotime($apt['appointment_date'])),
                    convertTo12Hour($apt['appointment_time'])
                );
                sendSms($apt['phone'], $smsMessage);
            }

            return [
                'success' => true,
                'event_id' => $eventId,
                'message' => 'Appointment synced to Google Calendar'
            ];
        }

        return $result;

    } catch (Exception $e) {
        error_log("Create Calendar Event Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ==================================================
// 📝 UPDATE CALENDAR EVENT
// ==================================================
function updateCalendarEvent($pdo, $appointmentId) {
    try {
        // Get appointment details
        $stmt = $pdo->prepare("
            SELECT a.*, u.fname, u.lname, u.email, u.phone 
            FROM appointments a
            JOIN users u ON a.patient_id = u.id
            WHERE a.id = ?
        ");
        $stmt->execute([$appointmentId]);
        $apt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$apt) {
            return ['success' => false, 'error' => 'Appointment not found'];
        }

        // If no calendar event exists, create new one
        if (empty($apt['calendar_event_id'])) {
            return createCalendarEvent($pdo, $appointmentId);
        }

        // Build updated event data
        $patientName = trim($apt['fname'] . ' ' . $apt['lname']);
        $startDateTime = $apt['appointment_date'] . 'T' . $apt['appointment_time'];
        $endDateTime = (new DateTime($startDateTime))->modify('+1 hour')->format('Y-m-d\TH:i:s');

        $eventData = [
            'summary' => sprintf(
                '%s %s - %s',
                $apt['appointment_type'] === 'medical' ? '🏥' : '🦷',
                $patientName,
                strtoupper($apt['appointment_type'])
            ),
            'location' => 'BSU Clinic, Batangas State University',
            'description' => sprintf(
                "BSU Clinic Appointment\n\n" .
                "Patient: %s\n" .
                "Email: %s\n" .
                "Phone: %s\n" .
                "Type: %s\n" .
                "Status: %s\n\n" .
                "Appointment ID: %d",
                $patientName,
                $apt['email'],
                $apt['phone'] ?? 'N/A',
                ucfirst($apt['appointment_type']),
                ucfirst($apt['status']),
                $appointmentId
            ),
            'start' => [
                'dateTime' => $startDateTime,
                'timeZone' => 'Asia/Manila'
            ],
            'end' => [
                'dateTime' => $endDateTime,
                'timeZone' => 'Asia/Manila'
            ]
        ];

        // Update event in Google Calendar
        $result = calendarRequest(
            '/event?eventId=' . urlencode($apt['calendar_event_id']),
            'PUT',
            $eventData
        );

        if ($result['success']) {
            // Log to history
            $stmt = $pdo->prepare("
                INSERT INTO appointment_history 
                (appointment_id, action, changed_by, notes, created_at)
                VALUES (?, 'calendar_updated', ?, 'Updated in Google Calendar', NOW())
            ");
            $stmt->execute([$appointmentId, $_SESSION['user_id']]);

            return [
                'success' => true,
                'message' => 'Calendar event updated successfully'
            ];
        }

        return $result;

    } catch (Exception $e) {
        error_log("Update Calendar Event Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ==================================================
// ❌ DELETE CALENDAR EVENT
// ==================================================
function deleteCalendarEvent($pdo, $appointmentId) {
    try {
        $stmt = $pdo->prepare("SELECT calendar_event_id FROM appointments WHERE id = ?");
        $stmt->execute([$appointmentId]);
        $apt = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$apt || empty($apt['calendar_event_id'])) {
            return ['success' => true, 'message' => 'No calendar event to delete'];
        }

        // Delete from Google Calendar
        $result = calendarRequest(
            '/event?eventId=' . urlencode($apt['calendar_event_id']),
            'DELETE'
        );

        if ($result['success']) {
            // Clear event ID from database
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET calendar_event_id = NULL 
                WHERE id = ?
            ");
            $stmt->execute([$appointmentId]);

            // Log to history
            $stmt = $pdo->prepare("
                INSERT INTO appointment_history 
                (appointment_id, action, changed_by, notes, created_at)
                VALUES (?, 'calendar_deleted', ?, 'Removed from Google Calendar', NOW())
            ");
            $stmt->execute([$appointmentId, $_SESSION['user_id']]);
        }

        return $result;

    } catch (Exception $e) {
        error_log("Delete Calendar Event Error: " . $e->getMessage());
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

// ==================================================
// 🚀 REQUEST HANDLING
// ==================================================
$action = $_GET['action'] ?? $_POST['action'] ?? null;

try {
    switch ($action) {
        case 'list':
            $stmt = $pdo->query("
                SELECT a.*, 
                       u.fname, u.lname, u.email, u.phone,
                       a.calendar_event_id
                FROM appointments a
                JOIN users u ON a.patient_id = u.id
                WHERE a.status != 'deleted'
                ORDER BY a.appointment_date DESC, a.appointment_time DESC
            ");
            echo json_encode([
                'success' => true, 
                'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
            ]);
            break;

        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            if (empty($data['patient_id']) || empty($data['date']) || empty($data['time'])) {
                throw new Exception('Missing required fields');
            }

            // Insert appointment
            $stmt = $pdo->prepare("
                INSERT INTO appointments 
                (patient_id, appointment_date, appointment_time, appointment_type, status, notes, created_by, created_at)
                VALUES (?, ?, ?, ?, 'scheduled', ?, ?, NOW())
            ");
            $stmt->execute([
                $data['patient_id'],
                $data['date'],
                convertTo24Hour($data['time']),
                $data['type'] ?? 'medical',
                $data['notes'] ?? '',
                $_SESSION['user_id']
            ]);

            $appointmentId = $pdo->lastInsertId();
            
            // Sync to Google Calendar
            $calendarResult = createCalendarEvent($pdo, $appointmentId);
            
            echo json_encode([
                'success' => true,
                'appointment_id' => $appointmentId,
                'calendar_sync' => $calendarResult
            ]);
            break;

        case 'update':
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (empty($data['id'])) {
                throw new Exception('Appointment ID required');
            }

            // Update appointment
            $stmt = $pdo->prepare("
                UPDATE appointments
                SET appointment_date = ?, 
                    appointment_time = ?, 
                    appointment_type = ?, 
                    notes = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['date'],
                convertTo24Hour($data['time']),
                $data['type'],
                $data['notes'] ?? '',
                $data['id']
            ]);

            // Update calendar
            $calendarResult = updateCalendarEvent($pdo, $data['id']);
            
            echo json_encode([
                'success' => true,
                'calendar_sync' => $calendarResult
            ]);
            break;

        case 'cancel':
            $id = $_GET['id'] ?? $_POST['id'] ?? null;
            if (!$id) throw new Exception('Appointment ID required');

            // Delete from calendar first
            deleteCalendarEvent($pdo, $id);
            
            // Update appointment status
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET status = 'cancelled', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            // Log cancellation
            $stmt = $pdo->prepare("
                INSERT INTO appointment_history 
                (appointment_id, action, changed_by, notes, created_at)
                VALUES (?, 'cancelled', ?, 'Appointment cancelled', NOW())
            ");
            $stmt->execute([$id, $_SESSION['user_id']]);

            echo json_encode(['success' => true]);
            break;

        case 'sync':
            $id = $_GET['id'] ?? $_POST['id'] ?? null;
            if (!$id) throw new Exception('Appointment ID required');
            
            $result = updateCalendarEvent($pdo, $id);
            echo json_encode($result);
            break;

        case 'sync_all':
            $stmt = $pdo->query("
                SELECT id FROM appointments
                WHERE status = 'scheduled' 
                AND appointment_date >= CURDATE()
            ");
            
            $synced = 0;
            $failed = 0;
            
            while ($apt = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $result = updateCalendarEvent($pdo, $apt['id']);
                if ($result['success']) {
                    $synced++;
                } else {
                    $failed++;
                }
            }
            
            echo json_encode([
                'success' => true,
                'synced' => $synced,
                'failed' => $failed
            ]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (Exception $e) {
    error_log('Appointment Handler Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
<?php
/**
 * Enhanced Calendar API with Doctor Schedule Synchronization
 * Integrates with appointment system and doctor availability
 */

require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

// Calendar API Configuration
define('CALENDAR_API_URL', 'https://v1.nocodeapi.com/nnglysl04/calendar/DvmKqOkWtGBoIMAL');
define('CALENDAR_ID', 'primary');

function makeCalendarRequest($endpoint, $method = 'GET', $data = null) {
    $url = CALENDAR_API_URL . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($data !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error) {
        return [
            'success' => false,
            'error' => 'Connection error: ' . $error
        ];
    }
    
    $result = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        return [
            'success' => true,
            'data' => $result
        ];
    } else {
        return [
            'success' => false,
            'error' => $result['error'] ?? 'Unknown error',
            'http_code' => $httpCode
        ];
    }
}

/**
 * Convert 12-hour time format to 24-hour
 */
function convertTo24Hour($time12h) {
    $parts = explode(' ', $time12h);
    $time = $parts[0];
    $modifier = $parts[1] ?? 'AM';
    
    list($hours, $minutes) = explode(':', $time);
    
    if ($hours == 12) {
        $hours = $modifier == 'AM' ? '00' : '12';
    } elseif ($modifier == 'PM') {
        $hours = str_pad((int)$hours + 12, 2, '0', STR_PAD_LEFT);
    }
    
    return sprintf('%02d:%s', $hours, $minutes);
}

/**
 * Get doctor info for appointment
 */
function getDoctorForAppointment($pdo, $appointmentDate, $appointmentTime, $department) {
    $stmt = $pdo->prepare("
        SELECT 
            u.id,
            CONCAT(u.fname, ' ', u.lname) as name,
            u.email,
            ds.id as schedule_id
        FROM doctor_schedules ds
        INNER JOIN users u ON ds.user_id = u.id
        WHERE ds.schedule_date = ?
        AND ds.is_available = 1
        AND ds.schedule_type = 'available'
        AND ? >= ds.start_time
        AND ? < ds.end_time
        AND u.role IN ('doctor', 'nurse')
        ORDER BY u.id ASC
        LIMIT 1
    ");
    
    $stmt->execute([$appointmentDate, $appointmentTime, $appointmentTime]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

/**
 * Create a calendar event with doctor schedule validation
 */
function createCalendarEvent($appointmentData) {
    global $pdo;
    
    // Validate doctor availability
    $doctor = getDoctorForAppointment(
        $pdo,
        $appointmentData['date'],
        $appointmentData['time'],
        $appointmentData['type'] ?? null
    );
    
    if (!$doctor) {
        return [
            'success' => false,
            'error' => 'No doctor available for this time slot'
        ];
    }
    
    // Convert time to 24-hour format if needed
    $timeStr = $appointmentData['time'];
    if (strpos($timeStr, 'AM') !== false || strpos($timeStr, 'PM') !== false) {
        $timeStr = convertTo24Hour($timeStr);
    }
    
    $dateTime = $appointmentData['date'] . 'T' . $timeStr . ':00';
    $endDateTime = new DateTime($dateTime);
    $endDateTime->modify('+1 hour');
    
    $eventData = [
        'summary' => sprintf(
            '%s %s - %s Appointment',
            $appointmentData['type'] === 'medical' ? '🏥' : '🦷',
            $appointmentData['name'],
            ucfirst($appointmentData['type'])
        ),
        'location' => 'BSU Clinic, Batangas State University',
        'description' => sprintf(
            "📋 Appointment Details\n\n" .
            "Patient: %s\n" .
            "Patient ID: %s\n" .
            "Type: %s\n" .
            "Doctor: %s\n" .
            "Notes: %s\n\n" .
            "⚠️ Please arrive 10 minutes early",
            $appointmentData['name'],
            $appointmentData['patient_id'] ?? 'N/A',
            ucfirst($appointmentData['type']),
            $doctor['name'],
            $appointmentData['notes'] ?? 'No additional notes'
        ),
        'start' => [
            'dateTime' => $dateTime,
            'timeZone' => 'Asia/Manila'
        ],
        'end' => [
            'dateTime' => $endDateTime->format('Y-m-d\TH:i:s'),
            'timeZone' => 'Asia/Manila'
        ],
        'reminders' => [
            'useDefault' => false,
            'overrides' => [
                ['method' => 'email', 'minutes' => 24 * 60],
                ['method' => 'popup', 'minutes' => 30]
            ]
        ],
        'attendees' => [
            ['email' => $doctor['email'], 'displayName' => 'Dr. ' . $doctor['name']]
        ],
        'sendNotifications' => true,
        'colorId' => $appointmentData['type'] === 'dental' ? '7' : '10' // Blue for dental, Green for medical
    ];
    
    $result = makeCalendarRequest('/event', 'POST', $eventData);
    
    // If successful, log the sync
    if ($result['success'] && isset($appointmentData['appointment_id'])) {
        try {
            $stmt = $pdo->prepare("
                UPDATE appointments 
                SET calendar_event_id = ?, last_synced = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$result['data']['id'], $appointmentData['appointment_id']]);
        } catch (PDOException $e) {
            error_log("Failed to update calendar_event_id: " . $e->getMessage());
        }
    }
    
    return $result;
}

/**
 * Create doctor schedule blocks in calendar
 */
function createDoctorScheduleBlock($scheduleData) {
    global $pdo;
    
    // Get doctor info
    $stmt = $pdo->prepare("
        SELECT CONCAT(fname, ' ', lname) as name, email
        FROM users WHERE id = ?
    ");
    $stmt->execute([$scheduleData['doctor_id']]);
    $doctor = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$doctor) {
        return ['success' => false, 'error' => 'Doctor not found'];
    }
    
    $dateTime = $scheduleData['date'] . 'T' . $scheduleData['start_time'] . ':00';
    $endDateTime = $scheduleData['date'] . 'T' . $scheduleData['end_time'] . ':00';
    
    if ($scheduleData['schedule_type'] === 'available') {
        // Create availability block
        $eventData = [
            'summary' => '🩺 Dr. ' . $doctor['name'] . ' - Available',
            'location' => 'BSU Clinic',
            'description' => sprintf(
                "Doctor Availability\n\n" .
                "Doctor: Dr. %s\n" .
                "Status: Available for appointments\n" .
                "Notes: %s",
                $doctor['name'],
                $scheduleData['notes'] ?? 'Standard clinic hours'
            ),
            'start' => [
                'dateTime' => $dateTime,
                'timeZone' => 'Asia/Manila'
            ],
            'end' => [
                'dateTime' => $endDateTime,
                'timeZone' => 'Asia/Manila'
            ],
            'colorId' => '2', // Green for available
            'transparency' => 'transparent' // Shows as available
        ];
    } else {
        // Create unavailable block
        $eventData = [
            'summary' => '🚫 Dr. ' . $doctor['name'] . ' - Unavailable',
            'location' => 'BSU Clinic',
            'description' => sprintf(
                "Doctor Unavailable\n\n" .
                "Doctor: Dr. %s\n" .
                "Reason: %s",
                $doctor['name'],
                $scheduleData['reason'] ?? 'Not available'
            ),
            'start' => [
                'date' => $scheduleData['date']
            ],
            'end' => [
                'date' => $scheduleData['date']
            ],
            'colorId' => '11', // Red for unavailable
            'transparency' => 'opaque' // Shows as busy
        ];
    }
    
    return makeCalendarRequest('/event', 'POST', $eventData);
}

/**
 * Update a calendar event
 */
function updateCalendarEvent($eventId, $appointmentData) {
    // Convert time if needed
    $timeStr = $appointmentData['time'];
    if (strpos($timeStr, 'AM') !== false || strpos($timeStr, 'PM') !== false) {
        $timeStr = convertTo24Hour($timeStr);
    }
    
    $dateTime = $appointmentData['date'] . 'T' . $timeStr . ':00';
    $endDateTime = new DateTime($dateTime);
    $endDateTime->modify('+1 hour');
    
    $eventData = [
        'summary' => sprintf(
            '%s %s - %s',
            $appointmentData['type'] === 'medical' ? '🏥' : '🦷',
            $appointmentData['name'],
            strtoupper($appointmentData['type'])
        ),
        'location' => 'BSU Clinic, Batangas State University',
        'description' => sprintf(
            "Appointment Details\n\nPatient: %s\nPatient ID: %s\nType: %s\nNotes: %s",
            $appointmentData['name'],
            $appointmentData['patient_id'] ?? 'N/A',
            ucfirst($appointmentData['type']),
            $appointmentData['notes'] ?? 'No additional notes'
        ),
        'start' => [
            'dateTime' => $dateTime,
            'timeZone' => 'Asia/Manila'
        ],
        'end' => [
            'dateTime' => $endDateTime->format('Y-m-d\TH:i:s'),
            'timeZone' => 'Asia/Manila'
        ]
    ];
    
    return makeCalendarRequest('/event?eventId=' . $eventId, 'PUT', $eventData);
}

/**
 * Delete a calendar event
 */
function deleteCalendarEvent($eventId) {
    return makeCalendarRequest('/event?eventId=' . $eventId, 'DELETE');
}

/**
 * Get all events from calendar
 */
function getCalendarEvents($timeMin = null, $timeMax = null) {
    $params = [];
    if ($timeMin) $params[] = 'timeMin=' . urlencode($timeMin);
    if ($timeMax) $params[] = 'timeMax=' . urlencode($timeMax);
    
    $queryString = !empty($params) ? '?' . implode('&', $params) : '';
    return makeCalendarRequest('/listEvents' . $queryString, 'GET');
}

/**
 * Get single event
 */
function getCalendarEvent($eventId) {
    return makeCalendarRequest('/event?eventId=' . $eventId, 'GET');
}

/**
 * Sync all unsynced appointments to calendar
 */
function syncUnsyncedAppointments() {
    global $pdo;
    
    $stmt = $pdo->query("
        SELECT 
            a.id,
            a.appointment_date as date,
            a.appointment_time as time,
            a.appointment_type as type,
            a.notes,
            CONCAT(u.fname, ' ', u.lname) as name,
            u.id as patient_id
        FROM appointments a
        INNER JOIN users u ON a.patient_id = u.id
        WHERE a.calendar_event_id IS NULL
        AND a.status IN ('scheduled', 'confirmed')
        AND a.appointment_date >= CURDATE()
    ");
    
    $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $synced = 0;
    $failed = 0;
    
    foreach ($appointments as $apt) {
        $apt['appointment_id'] = $apt['id'];
        $result = createCalendarEvent($apt);
        
        if ($result['success']) {
            $synced++;
        } else {
            $failed++;
            error_log("Failed to sync appointment {$apt['id']}: " . ($result['error'] ?? 'Unknown error'));
        }
    }
    
    return [
        'success' => true,
        'synced' => $synced,
        'failed' => $failed,
        'total' => count($appointments)
    ];
}

// Initialize PDO
try {
    $pdo = getDB();
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Handle API requests
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    echo json_encode(['success' => false, 'error' => 'No action specified']);
    exit;
}

try {
    switch ($action) {
        case 'create':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                echo json_encode(['success' => false, 'error' => 'Invalid data']);
                exit;
            }
            $result = createCalendarEvent($data);
            echo json_encode($result);
            break;
            
        case 'create_schedule_block':
            $data = json_decode(file_get_contents('php://input'), true);
            if (!$data) {
                echo json_encode(['success' => false, 'error' => 'Invalid data']);
                exit;
            }
            $result = createDoctorScheduleBlock($data);
            echo json_encode($result);
            break;
            
        case 'update':
            $data = json_decode(file_get_contents('php://input'), true);
            $eventId = $_GET['eventId'] ?? $data['eventId'] ?? null;
            
            if (!$eventId || !$data) {
                echo json_encode(['success' => false, 'error' => 'Invalid data or event ID']);
                exit;
            }
            $result = updateCalendarEvent($eventId, $data);
            echo json_encode($result);
            break;
            
        case 'delete':
            $eventId = $_GET['eventId'] ?? null;
            if (!$eventId) {
                echo json_encode(['success' => false, 'error' => 'Event ID required']);
                exit;
            }
            $result = deleteCalendarEvent($eventId);
            echo json_encode($result);
            break;
            
        case 'list':
            $timeMin = $_GET['timeMin'] ?? null;
            $timeMax = $_GET['timeMax'] ?? null;
            $result = getCalendarEvents($timeMin, $timeMax);
            echo json_encode($result);
            break;
            
        case 'get':
            $eventId = $_GET['eventId'] ?? null;
            if (!$eventId) {
                echo json_encode(['success' => false, 'error' => 'Event ID required']);
                exit;
            }
            $result = getCalendarEvent($eventId);
            echo json_encode($result);
            break;
            
        case 'sync_unsynced':
            // Sync all appointments that don't have calendar events
            $result = syncUnsyncedAppointments();
            echo json_encode($result);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    error_log("Calendar API Error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'An error occurred: ' . $e->getMessage()
    ]);
}
?>
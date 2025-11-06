<?php

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
 * Create a calendar event
 */
function createCalendarEvent($appointmentData) {
    $dateTime = $appointmentData['date'] . 'T' . convertTo24Hour($appointmentData['time']) . ':00';
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
        ],
        'reminders' => [
            'useDefault' => false,
            'overrides' => [
                ['method' => 'email', 'minutes' => 24 * 60],
                ['method' => 'popup', 'minutes' => 30]
            ]
        ],
        'attendees' => [],
        'sendNotifications' => true
    ];
    
    return makeCalendarRequest('/event', 'POST', $eventData);
}

/**
 * Update a calendar event
 */
function updateCalendarEvent($eventId, $appointmentData) {
    $dateTime = $appointmentData['date'] . 'T' . convertTo24Hour($appointmentData['time']) . ':00';
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

// Handle API requests
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    echo json_encode(['success' => false, 'error' => 'No action specified']);
    exit;
}

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
        
    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>
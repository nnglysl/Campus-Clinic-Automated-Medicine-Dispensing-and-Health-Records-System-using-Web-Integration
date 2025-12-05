<?php
<<<<<<< HEAD
require_once __DIR__ . '/../config/database.php';

if (basename($_SERVER['PHP_SELF']) === 'calendar_api.php') {
    session_start();
    header('Content-Type: application/json');
}

define('CALENDAR_API_URL', 'https://v1.nocodeapi.com/nnglysl04/calendar/DvmKqOkWtGBolMAL');
define('CALENDAR_ID', 'primary');

=======

header('Content-Type: application/json');

// Calendar API Configuration
define('CALENDAR_API_URL', 'https://v1.nocodeapi.com/nnglysl04/calendar/DvmKqOkWtGBoIMAL');
define('CALENDAR_ID', 'primary');


>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
function makeCalendarRequest($endpoint, $method = 'GET', $data = null) {
    $url = CALENDAR_API_URL . $endpoint;
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
<<<<<<< HEAD
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    if ($data !== null && ($method === 'POST' || $method === 'PUT')) {
=======
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($data !== null) {
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
<<<<<<< HEAD
    // Log the request and response for debugging
    error_log("Calendar API Request: {$method} {$endpoint}");
    if ($data !== null) {
        error_log("Calendar API Request Data: " . json_encode($data));
    }
    error_log("Calendar API Response Code: {$httpCode}");
    error_log("Calendar API Response: " . substr($response, 0, 1000)); // First 1000 chars
    
    if ($error) {
        error_log("Calendar API cURL Error: " . $error);
=======
    if ($error) {
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        return [
            'success' => false,
            'error' => 'Connection error: ' . $error
        ];
    }
    
    $result = json_decode($response, true);
    
<<<<<<< HEAD
    // Handle different response formats from NoCodeAPI
    if ($httpCode >= 200 && $httpCode < 300) {
        // NoCodeAPI might return the event directly or wrapped in a data property
        $eventData = $result['data'] ?? $result;
        return [
            'success' => true,
            'data' => $eventData,
            'raw_response' => $result
        ];
    } else {
        error_log("Calendar API Error Response: " . json_encode($result));
        $errorMessage = $result['error'] ?? $result['message'] ?? 'Unknown error';
        if (is_array($errorMessage)) {
            $errorMessage = json_encode($errorMessage);
        }
        return [
            'success' => false,
            'error' => $errorMessage,
            'http_code' => $httpCode,
            'response' => $response,
            'raw_response' => $result
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        ];
    }
}

/**
<<<<<<< HEAD
 * Generate Google Calendar link for manual addition
 */
function generateGoogleCalendarLink($appointmentData, $startDateTime, $endDateTime) {
    $start = urlencode($startDateTime->format('Ymd\THis'));
    $end = urlencode($endDateTime->format('Ymd\THis'));
    $title = urlencode(sprintf(
        '%s %s - %s Appointment',
        $appointmentData['type'] === 'medical' ? '🏥' : '🦷',
        $appointmentData['name'],
        ucfirst($appointmentData['type'])
    ));
    $details = urlencode(sprintf(
        "Appointment Details\n\nPatient: %s\nType: %s\nNotes: %s",
        $appointmentData['name'],
        ucfirst($appointmentData['type']),
        $appointmentData['notes'] ?? 'No additional notes'
    ));
    $location = urlencode('BSU Clinic, Batangas State University');
    
    return "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$title}&dates={$start}/{$end}&details={$details}&location={$location}";
}

/**
 * Convert 12-hour time format to 24-hour
 * Only declare if not already declared (to avoid redeclaration errors)
 */
if (!function_exists('convertTo24Hour')) {
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


function createCalendarEvent($appointmentData) {
    global $pdo;
    
    // Get patient email for calendar invitation - CRITICAL for automatic sync
    $patientEmail = null;
    $patientId = $appointmentData['patient_id'] ?? null;
    
    if ($patientId) {
        try {
            // Try to get email from users table
            $stmt = $pdo->prepare("SELECT email, fname, lname FROM users WHERE id = ?");
            $stmt->execute([$patientId]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($patient && !empty($patient['email'])) {
                $patientEmail = trim($patient['email']);
                error_log("✓ Retrieved patient email from users table: {$patientEmail} (ID: {$patientId})");
            } else {
                // Try alternative: get from appointments table join
                error_log("⚠ Patient email not found in users table, trying alternative method");
                if (isset($appointmentData['appointment_id'])) {
                    $stmt = $pdo->prepare("
                        SELECT u.email 
                        FROM appointments a
                        INNER JOIN users u ON a.patient_id = u.id
                        WHERE a.id = ?
                    ");
                    $stmt->execute([$appointmentData['appointment_id']]);
                    $altPatient = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($altPatient && !empty($altPatient['email'])) {
                        $patientEmail = trim($altPatient['email']);
                        error_log("✓ Retrieved patient email from appointments join: {$patientEmail}");
                    }
                }
            }
            
            if (empty($patientEmail)) {
                error_log("✗ ERROR: Patient email not found for patient_id: {$patientId}");
                error_log("  This will prevent automatic calendar sync. Patient must have a valid email in the users table.");
            }
        } catch (PDOException $e) {
            error_log("✗ Failed to get patient email: " . $e->getMessage());
        }
    } else {
        error_log("✗ ERROR: No patient_id provided in appointmentData");
    }
    
    // Determine appointment type and corresponding practitioner role
    // IMPORTANT: Only add practitioners of the correct type (doctor for medical, dentist for dental)
    $appointmentTypeRaw = $appointmentData['type'] ?? 'medical';
    $appointmentType = strtolower(trim($appointmentTypeRaw));
    
    // Normalize appointment type - handle variations like "Dental", "dental", "DENTAL", etc.
    if (strpos($appointmentType, 'dental') !== false) {
        $appointmentType = 'dental';
        $practitionerRole = 'dentist';
    } else {
        $appointmentType = 'medical';
        $practitionerRole = 'doctor';
    }
    
    // Log appointment type for debugging
    error_log("=== CALENDAR EVENT CREATION ===");
    error_log("Raw Appointment Type: " . var_export($appointmentTypeRaw, true));
    error_log("Normalized Appointment Type: {$appointmentType}");
    error_log("Expected Practitioner Role: {$practitionerRole}");
    error_log("CRITICAL: Only {$practitionerRole}(s) will be added as attendees for this {$appointmentType} appointment");
    
    // Get the specific practitioner assigned to this appointment (if any)
    // CRITICAL: Only get practitioners of the correct type (doctor for medical, dentist for dental)
    $assignedPractitioner = null;
    try {
        $stmt = $pdo->prepare("
            SELECT 
                u.id,
                CONCAT(u.fname, ' ', u.lname) as name,
                u.email,
                u.role,
                ds.id as schedule_id
            FROM doctor_schedules ds
            INNER JOIN users u ON ds.user_id = u.id
            WHERE ds.schedule_date = ?
            AND ds.is_available = 1
            AND ds.schedule_type = 'available'
            AND ? >= ds.start_time
            AND ? < ds.end_time
            AND u.role = ?
            ORDER BY u.id ASC
            LIMIT 1
        ");
        $stmt->execute([$appointmentData['date'], $appointmentData['time'], $appointmentData['time'], $practitionerRole]);
        $assignedPractitioner = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($assignedPractitioner) {
            error_log("✓ Found assigned " . $practitionerRole . ": {$assignedPractitioner['name']} ({$assignedPractitioner['email']}, role: {$assignedPractitioner['role']})");
            // Verify the role matches
            if ($assignedPractitioner['role'] !== $practitionerRole) {
                error_log("⚠ WARNING: Assigned practitioner role mismatch! Expected: {$practitionerRole}, Got: {$assignedPractitioner['role']}");
                $assignedPractitioner = null; // Don't use if role doesn't match
            }
        } else {
            error_log("⚠ No assigned " . $practitionerRole . " found for this appointment slot");
        }
    } catch (PDOException $e) {
        error_log("✗ Failed to get assigned " . $practitionerRole . ": " . $e->getMessage());
    }
    
    // If no assigned practitioner found, try to get the first available practitioner of the correct type
    // This ensures we only add practitioners of the correct role
    // IMPORTANT: For dental appointments, ONLY get dentists. For medical, ONLY get doctors.
    if (!$assignedPractitioner) {
        try {
            // Use strict role matching - no fallback to wrong roles
            $stmt = $pdo->prepare("
                SELECT DISTINCT 
                    u.id,
                    u.email,
                    CONCAT(u.fname, ' ', u.lname) as name,
                    u.role
                FROM users u
                WHERE u.role = ?
                AND u.email IS NOT NULL
                AND u.email != ''
                AND u.role = ?  -- Double-check: role must match exactly
                ORDER BY u.id
                LIMIT 1
            ");
            $stmt->execute([$practitionerRole, $practitionerRole]);
            $assignedPractitioner = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($assignedPractitioner) {
                // CRITICAL: Verify role matches - reject if it doesn't
                $actualRole = strtolower(trim($assignedPractitioner['role'] ?? ''));
                $expectedRole = strtolower(trim($practitionerRole));
                
                if ($actualRole === $expectedRole) {
                    error_log("✓ Using first available " . $practitionerRole . ": {$assignedPractitioner['name']} ({$assignedPractitioner['email']}, role: {$assignedPractitioner['role']})");
                } else {
                    error_log("✗ CRITICAL ERROR: Practitioner role mismatch in fallback query!");
                    error_log("  Expected: {$expectedRole} (for {$appointmentType} appointment)");
                    error_log("  Got: {$actualRole}");
                    error_log("  Practitioner: {$assignedPractitioner['name']} ({$assignedPractitioner['email']})");
                    error_log("  REJECTING this practitioner - will create event with patient only");
                    $assignedPractitioner = null; // Don't use if role doesn't match
                }
            } else {
                error_log("⚠ No available " . $practitionerRole . " found in database for this {$appointmentType} appointment");
            }
        } catch (PDOException $e) {
            error_log("✗ Failed to get available " . $practitionerRole . ": " . $e->getMessage());
        }
    }
    
    if (!$assignedPractitioner) {
        error_log("⚠ Warning: No " . $practitionerRole . " available for appointment, creating event with patient only");
    }
    
    // Convert time to 24-hour format if needed
    $timeStr = $appointmentData['time'];
    if (strpos($timeStr, 'AM') !== false || strpos($timeStr, 'PM') !== false) {
        $timeStr = convertTo24Hour($timeStr);
    }
    
    // Ensure time format is HH:MM:SS (add seconds if missing)
    $timeParts = explode(':', $timeStr);
    if (count($timeParts) === 2) {
        // Time is in HH:MM format, add seconds
        $timeStr = $timeStr . ':00';
    } elseif (count($timeParts) === 3) {
        // Time already has seconds (HH:MM:SS), use as is
        $timeStr = $timeStr;
    } else {
        // Invalid format, default to adding :00
        $timeStr = $timeStr . ':00';
    }
    
    $dateTime = $appointmentData['date'] . 'T' . $timeStr;
    $endDateTime = new DateTime($dateTime);
    $endDateTime->modify('+1 hour');
    
    // Build attendees list - PATIENT EMAIL IS REQUIRED FOR AUTOMATIC SYNC
    $attendees = [];
    
    // Patient MUST be added as attendee for automatic sync to work
    // NoCodeAPI format: attendees array with objects containing only 'email' property
    if ($patientEmail) {
        // Patient should be first in the list for priority
        $attendees[] = [
            'email' => $patientEmail
        ];
        error_log("✓ Patient email added to attendees: {$patientEmail}");
    } else {
        error_log("✗ ERROR: Patient email not found for patient_id: " . ($appointmentData['patient_id'] ?? 'N/A'));
        error_log("  Cannot create calendar event without patient email for automatic sync");
    }
    
    // Add ONLY the assigned practitioner (if found) - NOT all practitioners
    // CRITICAL: Only add practitioners of the correct type (doctor for medical, dentist for dental)
    $addedEmails = []; // Track added emails to avoid duplicates
    
    // Add assigned practitioner if available (only if it matches the appointment type)
    if ($assignedPractitioner && !empty($assignedPractitioner['email'])) {
        // QUADRUPLE-CHECK: Verify role matches appointment type
        $practitionerRoleActual = strtolower(trim($assignedPractitioner['role'] ?? ''));
        $expectedRole = strtolower(trim($practitionerRole));
        
        if ($practitionerRoleActual === $expectedRole) {
            $email = strtolower(trim($assignedPractitioner['email']));
            if (!in_array($email, $addedEmails)) {
                $attendees[] = [
                    'email' => $assignedPractitioner['email']
                ];
                $addedEmails[] = $email;
                error_log("✓ " . ucfirst($practitionerRole) . " added to attendees: {$assignedPractitioner['email']} ({$assignedPractitioner['name']}, role: {$assignedPractitioner['role']})");
                error_log("  ✓ Role verification passed: {$practitionerRoleActual} === {$expectedRole}");
            }
        } else {
            error_log("✗ ERROR: REJECTED practitioner - role mismatch!");
            error_log("  Expected Role: {$expectedRole} (for {$appointmentType} appointment)");
            error_log("  Actual Role: {$practitionerRoleActual}");
            error_log("  Practitioner: {$assignedPractitioner['name']} ({$assignedPractitioner['email']})");
            error_log("  This practitioner will NOT be added to the calendar event");
            // DO NOT add this practitioner - they are the wrong role
            $assignedPractitioner = null;
        }
    } else {
        if (!$assignedPractitioner) {
            error_log("⚠ No " . $practitionerRole . " found for this {$appointmentType} appointment");
            error_log("  Calendar event will be created with patient only (no practitioner attendee)");
        } else {
            error_log("⚠ Assigned practitioner found but has no email address");
        }
    }
    
    // Final verification: Ensure NO wrong-role practitioners are in attendees
    $wrongRolePractitioners = [];
    foreach ($attendees as $attendee) {
        if ($attendee['email'] !== $patientEmail) {
            // This is a practitioner - verify their role
            // We can't check here without a DB query, but we've already filtered above
            // Just log for verification
        }
    }
    
    // Log final attendees list for verification
    error_log("=== FINAL ATTENDEES LIST ===");
    error_log("Total attendees: " . count($attendees));
    foreach ($attendees as $idx => $attendee) {
        $isPatient = ($attendee['email'] === $patientEmail);
        error_log("  Attendee " . ($idx + 1) . ": {$attendee['email']} " . ($isPatient ? "(PATIENT)" : "({$practitionerRole})"));
    }
    error_log("=== END ATTENDEES LIST ===");
    
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
            "%s" .
            "Notes: %s\n\n" .
            "⚠️ Please arrive 10 minutes early",
            $appointmentData['name'],
            $appointmentData['patient_id'] ?? 'N/A',
            ucfirst($appointmentData['type']),
            $assignedPractitioner ? ucfirst($practitionerRole) . ": " . $assignedPractitioner['name'] . "\n" : "",
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
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
<<<<<<< HEAD
        'attendees' => $attendees,
        // Try to make patient the organizer so event appears automatically in their calendar
        // If patient email is available, set them as organizer
        'organizer' => $patientEmail ? [
            'email' => $patientEmail,
            'displayName' => $appointmentData['name']
        ] : null,
        // Send notifications to practitioners so they receive the event in their calendars
        // Patient (as organizer) will see it automatically, practitioners will receive invitations
        'sendNotifications' => !empty($attendees), // Send notifications if there are attendees
        'sendUpdates' => !empty($attendees) ? 'all' : 'none', // Send to all attendees (practitioners)
        'colorId' => $appointmentData['type'] === 'dental' ? '7' : '10' // Blue for dental, Green for medical
    ];
    
    // Ensure patient email is required for automatic sync
    if (empty($patientEmail)) {
        error_log("ERROR: Patient email is required for automatic calendar sync!");
        $calendarLink = generateGoogleCalendarLink($appointmentData, new DateTime($dateTime), $endDateTime);
        return [
            'success' => false,
            'error' => 'Patient email is required for automatic calendar sync. Please use the manual calendar link.',
            'calendar_link' => $calendarLink
        ];
    }
    
    // Remove organizer field if NoCodeAPI doesn't support it (will try without it if this fails)
    // Note: Setting organizer might not be supported by NoCodeAPI, but we'll try
    
    // Log the event data being sent (without sensitive info)
    error_log("Creating calendar event with data: " . json_encode([
        'summary' => $eventData['summary'],
        'start' => $eventData['start'],
        'end' => $eventData['end'],
        'attendees_count' => count($eventData['attendees']),
        'attendees' => array_map(function($a) { return ['email' => $a['email']]; }, $eventData['attendees']), // Log emails for debugging
        'sendNotifications' => $eventData['sendNotifications'] ?? 'not set'
    ]));
    
    // NoCodeAPI uses /event endpoint (singular) for creating events
    // Request body format matches NoCodeAPI's expected structure
    error_log("=== CALENDAR EVENT CREATION REQUEST ===");
    error_log("Endpoint: /event");
    error_log("Method: POST");
    error_log("Patient Email: " . ($patientEmail ?? 'NOT SET'));
    error_log("Attempting to create event with patient as organizer for automatic appearance");
    
    // Try creating event with patient as organizer first (for automatic appearance)
    $result = makeCalendarRequest('/event', 'POST', $eventData);
    
    // If that fails and organizer field might be the issue, try without organizer
    if (!$result['success'] && isset($eventData['organizer'])) {
        error_log("⚠ First attempt with organizer failed, trying without organizer field");
        error_log("   (NoCodeAPI might not support organizer field - will use standard invitation method)");
        $eventDataFallback = $eventData;
        unset($eventDataFallback['organizer']);
        // Set sendUpdates to 'all' as fallback - event will still be created
        $eventDataFallback['sendUpdates'] = 'all';
        $eventDataFallback['sendNotifications'] = true;
        $result = makeCalendarRequest('/event', 'POST', $eventDataFallback);
    }
    
    error_log("=== CALENDAR EVENT CREATION RESPONSE ===");
    error_log("Success: " . ($result['success'] ? 'YES' : 'NO'));
    if (!$result['success']) {
        error_log("Error: " . ($result['error'] ?? 'Unknown'));
        error_log("HTTP Code: " . ($result['http_code'] ?? 'N/A'));
    }
    
    // Log detailed result
    error_log("Calendar Event Creation Result: " . json_encode($result));
    
    // If successful, log the sync and extract event link
    $eventLink = null;
    if ($result['success'] && isset($appointmentData['appointment_id'])) {
        try {
            // NoCodeAPI might return the event in different formats
            $eventData = $result['data'] ?? $result['raw_response'] ?? [];
            $eventId = $eventData['id'] ?? null;
            
            // Try to get the event link from various possible response formats
            $eventLink = $eventData['htmlLink'] ?? $eventData['link'] ?? $eventData['eventLink'] ?? $eventData['url'] ?? null;
            
            // If no direct link, construct it from event ID
            if ($eventId && !$eventLink) {
                $eventLink = "https://calendar.google.com/calendar/event?eid=" . urlencode($eventId);
            }
            
            if ($eventId) {
                $stmt = $pdo->prepare("
                    UPDATE appointments 
                    SET calendar_event_id = ?, last_synced = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$eventId, $appointmentData['appointment_id']]);
                error_log("✓ Updated appointment {$appointmentData['appointment_id']} with calendar_event_id: {$eventId}");
            } else {
                error_log("⚠ Warning: Event created but no event ID found in response");
                error_log("Response structure: " . json_encode(array_keys($eventData)));
            }
            
            if ($eventLink) {
                error_log("✓ Calendar event created successfully. View link: {$eventLink}");
                $result['event_link'] = $eventLink; // Add event link to result
            } else {
                error_log("⚠ Warning: Event created but no htmlLink found. Response keys: " . implode(', ', array_keys($eventData)));
            }
            
            // Log success with patient email confirmation
            if ($patientEmail) {
                error_log("✓ Calendar event created and invitation sent to patient: {$patientEmail}");
            }
        } catch (PDOException $e) {
            error_log("✗ Failed to update calendar_event_id: " . $e->getMessage());
        }
    } else {
        error_log("✗ Calendar event creation failed: " . ($result['error'] ?? 'Unknown error'));
        if (isset($result['http_code'])) {
            error_log("HTTP Code: " . $result['http_code']);
        }
        if (isset($result['response'])) {
            error_log("API Response: " . substr($result['response'], 0, 1000));
        }
        if (isset($result['raw_response'])) {
            error_log("Raw Response: " . json_encode($result['raw_response']));
        }
    }
    
    // Generate Google Calendar link as fallback (always include it)
    $startDateTimeObj = new DateTime($dateTime);
    $calendarLink = generateGoogleCalendarLink($appointmentData, $startDateTimeObj, $endDateTime);
    $result['calendar_link'] = $calendarLink;
    
    // Add more detailed error logging if sync failed
    if (!$result['success']) {
        if (isset($result['http_code'])) {
            error_log("Calendar API HTTP Code: " . $result['http_code']);
        }
        if (isset($result['response'])) {
            error_log("Calendar API Response: " . substr($result['response'], 0, 1000));
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
    
=======
        'attendees' => [],
        'sendNotifications' => true
    ];
    
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    return makeCalendarRequest('/event', 'POST', $eventData);
}

/**
 * Update a calendar event
<<<<<<< HEAD
 * Can accept either appointmentData array or direct eventData array
 */
function updateCalendarEvent($eventId, $data) {
    // Check if data is already in eventData format (has 'start' key) or appointmentData format
    if (isset($data['start']) && isset($data['end'])) {
        // Already in eventData format - use directly
        $eventData = $data;
    } else {
        // Convert from appointmentData format to eventData format
        $appointmentData = $data;
        
        // Convert time if needed
        $timeStr = $appointmentData['time'] ?? '';
        if (strpos($timeStr, 'AM') !== false || strpos($timeStr, 'PM') !== false) {
            $timeStr = convertTo24Hour($timeStr);
        }
        
        // Ensure time format is HH:MM:SS
        $timeParts = explode(':', $timeStr);
        if (count($timeParts) === 2) {
            $timeStr = $timeStr . ':00';
        }
        
        $dateTime = ($appointmentData['date'] ?? '') . 'T' . $timeStr;
        $endDateTime = new DateTime($dateTime);
        $endDateTime->modify('+1 hour');
        
        $eventData = [
            'summary' => sprintf(
                '%s %s - %s',
                ($appointmentData['type'] ?? 'medical') === 'medical' ? '🏥' : '🦷',
                $appointmentData['name'] ?? 'Appointment',
                strtoupper($appointmentData['type'] ?? 'medical')
            ),
            'location' => 'BSU Clinic, Batangas State University',
            'description' => sprintf(
                "Appointment Details\n\nPatient: %s\nPatient ID: %s\nType: %s\nNotes: %s",
                $appointmentData['name'] ?? 'Patient',
                $appointmentData['patient_id'] ?? 'N/A',
                ucfirst($appointmentData['type'] ?? 'medical'),
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
        
        // Include attendees if provided
        if (isset($appointmentData['attendees'])) {
            $eventData['attendees'] = $appointmentData['attendees'];
        }
        
        // Include sendUpdates if provided
        if (isset($appointmentData['sendUpdates'])) {
            $eventData['sendUpdates'] = $appointmentData['sendUpdates'];
        }
    }
    
    // Log the update
    error_log("Updating calendar event: {$eventId}");
    if (isset($eventData['attendees'])) {
        error_log("New attendees: " . json_encode(array_map(function($a) { return $a['email']; }, $eventData['attendees'])));
    }
    
    // NoCodeAPI uses /event endpoint with eventId as query parameter
    return makeCalendarRequest('/event?eventId=' . urlencode($eventId), 'PUT', $eventData);
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
}

/**
 * Delete a calendar event
 */
function deleteCalendarEvent($eventId) {
<<<<<<< HEAD
    // NoCodeAPI uses /event endpoint (singular) for deletion
    return makeCalendarRequest('/event?eventId=' . urlencode($eventId), 'DELETE');
=======
    return makeCalendarRequest('/event?eventId=' . $eventId, 'DELETE');
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
}

/**
 * Get all events from calendar
 */
function getCalendarEvents($timeMin = null, $timeMax = null) {
    $params = [];
    if ($timeMin) $params[] = 'timeMin=' . urlencode($timeMin);
    if ($timeMax) $params[] = 'timeMax=' . urlencode($timeMax);
    
    $queryString = !empty($params) ? '?' . implode('&', $params) : '';
<<<<<<< HEAD
    // NoCodeAPI uses /listEvents endpoint for listing events
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    return makeCalendarRequest('/listEvents' . $queryString, 'GET');
}

/**
 * Get single event
 */
function getCalendarEvent($eventId) {
    return makeCalendarRequest('/event?eventId=' . $eventId, 'GET');
}

<<<<<<< HEAD
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
    // Only output if this is a direct API call, not an include
    if (basename($_SERVER['PHP_SELF']) === 'calendar_api.php') {
        // Clean output buffer before sending JSON
        if (ob_get_level() > 0) {
            ob_end_clean();
        }
        echo json_encode(['success' => false, 'error' => 'Database connection failed']);
        exit;
    }
    // If included, just log the error - don't output anything
    error_log("Calendar API: Database connection failed: " . $e->getMessage());
}

// Handle API requests - only if this is a direct API call
$action = $_GET['action'] ?? $_POST['action'] ?? null;

// Only process API actions if this file is being called directly
if (basename($_SERVER['PHP_SELF']) === 'calendar_api.php') {
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
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
}
?>
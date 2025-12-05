<?php
// File: dental/crud/save_dental_record.php
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, return JSON instead

// Start output buffering to catch any unexpected output
ob_start();

require_once '../../config/database.php';
session_start();

// Clear any output that might have been generated
ob_clean();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    ob_end_flush();
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    ob_end_flush();
    exit();
}

try {
    $pdo = getDB();
    
    // Get form data
    $patient_id = $_POST['patient_id'] ?? null;
    $program = $_POST['program'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    // Validate patient ID
    if (!$patient_id) {
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Patient ID is required']);
        ob_end_flush();
        exit();
    }
    
    // Check if a dental record already exists for this patient and appointment
    // This prevents duplicate records from being created
    $appointment_id = $_POST['appointment_id'] ?? null;
    if ($appointment_id) {
        // First, try to find if there's an appointment record linked to this appointment_id
        // Check if there's already a dental record for this specific appointment
        // We'll check by matching the appointment date/time with the dental record creation
        
        // Get appointment details first
        $appointmentStmt = $pdo->prepare("
            SELECT appointment_date, appointment_time 
            FROM appointments 
            WHERE id = ? AND appointment_type = 'dental'
        ");
        $appointmentStmt->execute([$appointment_id]);
        $appointment = $appointmentStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($appointment) {
            // Check if a dental record already exists for this appointment (check by date/time match)
            // Since dental_records.created_at will match appointment date
            $checkDate = $appointment['appointment_date'];
            $checkStmt = $pdo->prepare("
                SELECT id FROM dental_records 
                WHERE patient_id = ? 
                AND dentist_id = ? 
                AND DATE(created_at) = ?
                AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $checkStmt->execute([$patient_id, $_SESSION['user_id'], $checkDate]);
            $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingRecord) {
                // A record was just created for this appointment, likely a duplicate submission
                ob_clean();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Dental record already saved for this appointment',
                    'record_id' => $existingRecord['id'],
                    'duplicate_prevented' => true
                ]);
                ob_end_flush();
                exit();
            }
        } else {
            // Fallback: check if a record was created very recently (within 1 minute)
            $checkStmt = $pdo->prepare("
                SELECT id FROM dental_records 
                WHERE patient_id = ? 
                AND dentist_id = ? 
                AND created_at >= DATE_SUB(NOW(), INTERVAL 1 MINUTE)
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $checkStmt->execute([$patient_id, $_SESSION['user_id']]);
            $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingRecord) {
                // A record was just created (within the last minute), likely a duplicate submission
                ob_clean();
                echo json_encode([
                    'success' => true, 
                    'message' => 'Dental record already saved',
                    'record_id' => $existingRecord['id'],
                    'duplicate_prevented' => true
                ]);
                ob_end_flush();
                exit();
            }
        }
    }
    
    // Get checkboxes data
    $gingivitis = isset($_POST['gingivitis']) ? 1 : 0;
    $early_periodontitis = isset($_POST['early_periodontitis']) ? 1 : 0;
    $class_molar = isset($_POST['class_molar']) ? 1 : 0;
    $overjet = isset($_POST['overjet']) ? 1 : 0;
    $overbite = isset($_POST['overbite']) ? 1 : 0;
    $orthodontic = isset($_POST['orthodontic']) ? 1 : 0;
    $stayplate = isset($_POST['stayplate']) ? 1 : 0;
    $clenching = isset($_POST['clenching']) ? 1 : 0;
    $clicking = isset($_POST['clicking']) ? 1 : 0;
    
    // Collect tooth status data
    $tooth_status = [];
    foreach ($_POST as $key => $value) {
        // Match pattern: tooth_XX_status where XX is the tooth number
        if (preg_match('/^tooth_(\d+)_status$/', $key, $matches)) {
            $tooth_number = $matches[1]; // Extract tooth number from regex match
            // Save the value if it's not empty (trim to handle whitespace-only values)
            $trimmedValue = trim($value);
            if ($trimmedValue !== '') {
                $tooth_status[$tooth_number] = $trimmedValue;
            }
        }
    }
    
    // Also check for any tooth status values that might be in the raw POST data
    // This is a fallback in case the regex doesn't catch all variations
    if (empty($tooth_status)) {
        // Try alternative pattern matching
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'tooth_') === 0 && strpos($key, '_status') !== false) {
                // Extract tooth number manually
                $parts = explode('_', $key);
                if (count($parts) >= 3 && is_numeric($parts[1])) {
                    $tooth_number = $parts[1];
                    $trimmedValue = trim($value);
                    if ($trimmedValue !== '') {
                        $tooth_status[$tooth_number] = $trimmedValue;
                    }
                }
            }
        }
    }
    
    // Debug logging (remove in production if needed)
    error_log("Collected tooth status data: " . json_encode($tooth_status, JSON_PRETTY_PRINT));
    error_log("Number of teeth with status: " . count($tooth_status));
    if (count($tooth_status) > 0) {
        error_log("Sample tooth status entries: " . json_encode(array_slice($tooth_status, 0, 5, true), JSON_PRETTY_PRINT));
    } else {
        error_log("WARNING: No tooth status data collected! Checking POST data...");
        $toothFields = array_filter(array_keys($_POST), function($key) {
            return preg_match('/^tooth_\d+_status$/', $key);
        });
        error_log("Found tooth status fields in POST: " . json_encode($toothFields));
        foreach ($toothFields as $field) {
            error_log("  $field = " . ($_POST[$field] ?? 'NOT SET'));
        }
    }
    
    // Collect index table data (Temporary and Permanent Teeth tables)
    $index_data = [
        'temporary' => [],
        'permanent' => []
    ];
    
    // Collect temporary teeth index data (6 visits)
    for ($i = 1; $i <= 6; $i++) {
        $decayed = trim($_POST["temp_decayed_{$i}"] ?? '');
        $filled = trim($_POST["temp_filled_{$i}"] ?? '');
        $total = trim($_POST["temp_total_{$i}"] ?? '');
        
        // Only add if at least one field has data
        if ($decayed !== '' || $filled !== '' || $total !== '') {
            $index_data['temporary'][(string)$i] = [
                'decayed' => $decayed,
                'filled' => $filled,
                'total' => $total
            ];
        }
    }
    
    // Collect permanent teeth index data (4 visits)
    for ($i = 1; $i <= 4; $i++) {
        $d = trim($_POST["perm_d_{$i}"] ?? '');
        $m = trim($_POST["perm_m_{$i}"] ?? '');
        $f = trim($_POST["perm_f_{$i}"] ?? '');
        $total = trim($_POST["perm_total_{$i}"] ?? '');
        
        // Only add if at least one field has data
        if ($d !== '' || $m !== '' || $f !== '' || $total !== '') {
            $index_data['permanent'][(string)$i] = [
                'd' => $d,
                'm' => $m,
                'f' => $f,
                'total' => $total
            ];
        }
    }
    
    // Debug logging
    error_log("Collected index table data: " . json_encode($index_data, JSON_PRETTY_PRINT));
    
    // Check if this is an empty form submission (no data at all)
    // This prevents saving blank/unfilled forms
    $hasData = false;
    $hasData = $hasData || !empty($tooth_status);
    $hasData = $hasData || !empty(trim($remarks));
    $hasData = $hasData || $gingivitis || $early_periodontitis || $class_molar || $overjet || $overbite || $orthodontic || $stayplate || $clenching || $clicking;
    
    // Check index data
    foreach ($index_data['temporary'] as $visit) {
        if (!empty($visit['decayed']) || !empty($visit['filled']) || !empty($visit['total'])) {
            $hasData = true;
            break;
        }
    }
    if (!$hasData) {
        foreach ($index_data['permanent'] as $visit) {
            if (!empty($visit['d']) || !empty($visit['m']) || !empty($visit['f']) || !empty($visit['total'])) {
                $hasData = true;
                break;
            }
        }
    }
    
    // Check treatments
    if (!$hasData && !empty($treatments)) {
        foreach ($treatments as $treatment) {
            if (!empty($treatment['date']) || !empty($treatment['tooth']) || !empty($treatment['operation'])) {
                $hasData = true;
                break;
            }
        }
    }
    
    // If no data at all, don't create a record
    if (!$hasData) {
        ob_clean();
        echo json_encode([
            'success' => false, 
            'message' => 'Cannot save empty form. Please fill in at least one field (tooth status, remarks, checkboxes, index data, or treatments) before saving.'
        ]);
        ob_end_flush();
        exit();
    }
    
    // Collect treatment records
    $treatment_dates = $_POST['treatment_date'] ?? [];
    $treatment_teeth = $_POST['treatment_tooth'] ?? [];
    $treatment_operations = $_POST['treatment_operation'] ?? [];
    $treatment_dentists = $_POST['treatment_dentist'] ?? [];
    
    $treatments = [];
    for ($i = 0; $i < count($treatment_dates); $i++) {
        if (!empty($treatment_dates[$i]) || !empty($treatment_teeth[$i]) || !empty($treatment_operations[$i])) {
            $treatments[] = [
                'date' => $treatment_dates[$i] ?? '',
                'tooth' => $treatment_teeth[$i] ?? '',
                'operation' => $treatment_operations[$i] ?? '',
                'dentist' => $treatment_dentists[$i] ?? ''
            ];
        }
    }
    
    // Check and remove 'chief_complaint' column from dental_records if it exists
    // Note: chief_complaint is a medical record field, not a dental record field
    // It doesn't belong in dental_records and is not in the dental form
    $hasChiefComplaint = false;
    $chiefComplaintNullable = true;
    
    $columnCheck = $pdo->query("SHOW COLUMNS FROM `dental_records` LIKE 'chief_complaint'");
    if ($columnCheck->rowCount() > 0) {
        $hasChiefComplaint = true;
        $columnInfo = $columnCheck->fetch(PDO::FETCH_ASSOC);
        $isNotNull = (strtoupper($columnInfo['Null']) === 'NO');
        
        // Try to remove the column first (preferred solution)
        try {
            $pdo->exec("ALTER TABLE `dental_records` DROP COLUMN `chief_complaint`");
            error_log("Removed 'chief_complaint' column from dental_records table (not needed for dental records)");
            $hasChiefComplaint = false; // Column no longer exists
        } catch (PDOException $e) {
            error_log("Error removing 'chief_complaint' column from dental_records: " . $e->getMessage());
            
            // If removal fails, try to make it nullable as fallback
            if ($isNotNull) {
                try {
                    $pdo->exec("ALTER TABLE `dental_records` MODIFY COLUMN `chief_complaint` varchar(255) DEFAULT NULL");
                    error_log("Made 'chief_complaint' column nullable in dental_records as fallback");
                    $chiefComplaintNullable = true;
                } catch (PDOException $e2) {
                    error_log("Error making 'chief_complaint' nullable: " . $e2->getMessage());
                    $chiefComplaintNullable = false; // Column still NOT NULL
                }
            } else {
                $chiefComplaintNullable = true; // Already nullable
            }
        }
    }
    
    // Double-check column state after modification attempts
    if ($hasChiefComplaint) {
        $columnCheck = $pdo->query("SHOW COLUMNS FROM `dental_records` LIKE 'chief_complaint'");
        if ($columnCheck->rowCount() > 0) {
            $columnInfo = $columnCheck->fetch(PDO::FETCH_ASSOC);
            $chiefComplaintNullable = (strtoupper($columnInfo['Null']) === 'YES');
            error_log("chief_complaint column state: exists=" . ($hasChiefComplaint ? 'yes' : 'no') . ", nullable=" . ($chiefComplaintNullable ? 'yes' : 'no'));
        } else {
            $hasChiefComplaint = false; // Column was successfully removed
        }
    }
    
    // Check and ensure medical_records.chief_complaint column is nullable if needed
    // (Do this BEFORE transaction since ALTER TABLE causes implicit commit)
    try {
        $columnCheck = $pdo->query("SHOW COLUMNS FROM `medical_records` LIKE 'chief_complaint'");
        if ($columnCheck->rowCount() > 0) {
            $columnInfo = $columnCheck->fetch(PDO::FETCH_ASSOC);
            // If column is NOT NULL, try to make it nullable
            if (strtoupper($columnInfo['Null']) === 'NO') {
                $pdo->exec("ALTER TABLE `medical_records` MODIFY COLUMN `chief_complaint` varchar(255) DEFAULT NULL");
                error_log("Made 'chief_complaint' column nullable in medical_records table");
            }
        }
    } catch (PDOException $e) {
        error_log("Note: Could not modify medical_records.chief_complaint column: " . $e->getMessage());
        // Continue anyway - we'll ensure $chief_complaint is never empty
    }
    
    // Check if dental_records table exists, if not create it
    // (Do this BEFORE transaction since CREATE/ALTER TABLE causes implicit commit)
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'dental_records'");
    if ($tableCheck->rowCount() == 0) {
        // Create dental_records table
        $pdo->exec("
            CREATE TABLE `dental_records` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `patient_id` int(11) NOT NULL,
              `dentist_id` int(11) NOT NULL,
              `program` varchar(100) DEFAULT NULL,
              `gingivitis` tinyint(1) DEFAULT 0,
              `early_periodontitis` tinyint(1) DEFAULT 0,
              `class_molar` tinyint(1) DEFAULT 0,
              `overjet` tinyint(1) DEFAULT 0,
              `overbite` tinyint(1) DEFAULT 0,
              `orthodontic` tinyint(1) DEFAULT 0,
              `stayplate` tinyint(1) DEFAULT 0,
              `clenching` tinyint(1) DEFAULT 0,
              `clicking` tinyint(1) DEFAULT 0,
              `tooth_status` text DEFAULT NULL,
              `treatments` text DEFAULT NULL,
              `index_data` text DEFAULT NULL,
              `remarks` text DEFAULT NULL,
              `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
              `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
              PRIMARY KEY (`id`),
              KEY `patient_id` (`patient_id`),
              KEY `dentist_id` (`dentist_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci
        ");
    } else {
        // Table exists - check if required columns exist, if not add them
        // Check and add 'program' column if missing
        $columnCheck = $pdo->query("SHOW COLUMNS FROM `dental_records` LIKE 'program'");
        if ($columnCheck->rowCount() == 0) {
            try {
                $pdo->exec("ALTER TABLE `dental_records` ADD COLUMN `program` varchar(100) DEFAULT NULL AFTER `dentist_id`");
                error_log("Added 'program' column to dental_records table");
            } catch (PDOException $e) {
                error_log("Error adding 'program' column to dental_records: " . $e->getMessage());
            }
        }
        
        
        // Check and add 'index_data' column if missing (stores index table data)
        $columnCheck = $pdo->query("SHOW COLUMNS FROM `dental_records` LIKE 'index_data'");
        if ($columnCheck->rowCount() == 0) {
            try {
                $pdo->exec("ALTER TABLE `dental_records` ADD COLUMN `index_data` text DEFAULT NULL AFTER `treatments`");
                error_log("Added 'index_data' column to dental_records table");
            } catch (PDOException $e) {
                error_log("Error adding 'index_data' column to dental_records: " . $e->getMessage());
            }
        }
    }
    
    // Start transaction AFTER all schema modifications
    $pdo->beginTransaction();
    
    // Verify patient record exists
    $patientStmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $patientStmt->execute([$patient_id]);
    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
    
    
    if (!$patient) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Patient record not found. Please try opening the form again.']);
        exit();
    }
    
    $patient_user_id = $patient['user_id'] ?? null;
    
    // Verify user exists - if not, we can still save the record but can't link to appointment
    if ($patient_user_id) {
        $userStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
        $userStmt->execute([$patient_user_id]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            // User doesn't exist - log error and set patient_user_id to null to skip appointment lookup
            error_log("Warning: Patient record has user_id {$patient_user_id} but user doesn't exist in users table");
            $patient_user_id = null;
        }
    }
    
    // Insert into dental_records table
    // Build INSERT statement dynamically to include chief_complaint if column exists
    $insertColumns = [
        'patient_id', 
        'dentist_id',
        'program', 
        'gingivitis', 
        'early_periodontitis', 
        'class_molar', 
        'overjet', 
        'overbite', 
        'orthodontic', 
        'stayplate', 
        'clenching', 
        'clicking', 
        'tooth_status', 
        'treatments', 
        'index_data',
        'remarks', 
        'created_at'
    ];
    
    // Add chief_complaint if column exists (it's not in the dental form, so set to NULL)
    if ($hasChiefComplaint) {
        $insertColumns[] = 'chief_complaint';
    }
    
    $columnsStr = implode(', ', $insertColumns);
    $placeholders = [];
    foreach ($insertColumns as $col) {
        if ($col === 'created_at') {
            $placeholders[] = 'NOW()';
        } else {
            $placeholders[] = ':' . $col;
        }
    }
    $placeholdersStr = implode(', ', $placeholders);
    
    $stmt = $pdo->prepare("
        INSERT INTO dental_records (
            {$columnsStr}
        ) VALUES (
            {$placeholdersStr}
        )
    ");
    
    $params = [
        ':patient_id' => $patient_id,
        ':dentist_id' => $_SESSION['user_id'],
        ':program' => $program,
        ':gingivitis' => $gingivitis,
        ':early_periodontitis' => $early_periodontitis,
        ':class_molar' => $class_molar,
        ':overjet' => $overjet,
        ':overbite' => $overbite,
        ':orthodontic' => $orthodontic,
        ':stayplate' => $stayplate,
        ':clenching' => $clenching,
        ':clicking' => $clicking,
        ':tooth_status' => !empty($tooth_status) ? json_encode($tooth_status, JSON_UNESCAPED_UNICODE) : null,
        ':treatments' => !empty($treatments) ? json_encode($treatments, JSON_UNESCAPED_UNICODE) : null,
        ':index_data' => !empty($index_data) ? json_encode($index_data, JSON_UNESCAPED_UNICODE) : null,
        ':remarks' => $remarks
    ];
    
    // Add chief_complaint parameter if column exists
    // Note: chief_complaint should NOT be in dental_records, but we handle it if it exists
    if ($hasChiefComplaint) {
        // If column is nullable, use NULL; otherwise use a default value
        // (chief_complaint is not in the dental form, so we use a default)
        if ($chiefComplaintNullable) {
            $params[':chief_complaint'] = null;
        } else {
            // Column is NOT NULL and we couldn't modify it, so provide a default value
            // Use a descriptive default since this field doesn't belong in dental_records
            $params[':chief_complaint'] = 'Dental Examination - Routine Check-up';
        }
    }
    
    try {
        $stmt->execute($params);
        
        // Debug logging
        error_log("Saving dental record - Tooth status count: " . count($tooth_status));
        error_log("Saving dental record - Treatments count: " . count($treatments));
        
        $dental_record_id = $pdo->lastInsertId();
        
        if (!$dental_record_id) {
            throw new Exception("Failed to get dental record ID after insert");
        }
    } catch (PDOException $e) {
        error_log("Error executing dental record insert: " . $e->getMessage());
        error_log("SQL: INSERT INTO dental_records with columns: " . $columnsStr);
        error_log("Params: " . json_encode(array_keys($params)));
        throw $e; // Re-throw to be caught by outer catch block
    }
    
    // AUTOMATIC APPOINTMENT COMPLETION: Update appointment status to 'completed'
    // Get appointment details for status update
    $appointment_date = null;
    $appointment_time = null;
    $today = date('Y-m-d');
    
    // Find the appointment if appointment_id is provided
    $appointment = null;
    if ($appointment_id) {
        $findStmt = $pdo->prepare("
            SELECT id, appointment_date, appointment_time 
            FROM appointments 
            WHERE id = ? AND appointment_type = 'dental'
        ");
        $findStmt->execute([$appointment_id]);
        $appointment = $findStmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // If appointment_id not provided or not found, try to find by patient_user_id
    if (!$appointment && $patient_user_id) {
        $findAppointmentSql = "SELECT id, appointment_date, appointment_time FROM appointments 
                               WHERE patient_id = :patient_user_id 
                               AND appointment_type = 'dental'
                               AND appointment_date <= :today
                               AND status NOT IN ('completed', 'cancelled')
                               ORDER BY appointment_date DESC, appointment_time DESC
                               LIMIT 1";
        
        $findStmt = $pdo->prepare($findAppointmentSql);
        $findStmt->execute([
            ':patient_user_id' => $patient_user_id,
            ':today' => $today
        ]);
        $appointment = $findStmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $appointmentsUpdated = 0;
    if ($appointment) {
        $updateStmt = $pdo->prepare("UPDATE appointments 
                                     SET status = 'completed', 
                                         updated_at = NOW() 
                                     WHERE id = :appointment_id");
        $updateStmt->execute([':appointment_id' => $appointment['id']]);
        $appointmentsUpdated = $updateStmt->rowCount();
        
        if ($appointmentsUpdated > 0) {
            error_log("Auto-completed dental appointment ID {$appointment['id']} for patient {$patient_id} on {$today}");
        }
    }
    
    // NOTE: We NO LONGER create a medical_record entry here to prevent duplicates.
    // Dental records should ONLY exist in dental_records table.
    // The medical_records_handler.php will query dental_records separately and merge them,
    // so we don't need to duplicate the data in medical_records.
    
    // Commit transaction
    $pdo->commit();
    
    // Clean any output before sending JSON
    ob_clean();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Dental record saved successfully',
        'record_id' => $dental_record_id
    ]);
    
    // End output buffering and send output
    ob_end_flush();
    exit();
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error saving dental record: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Clean any output before sending JSON
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage(),
        'error_code' => $e->getCode()
    ]);
    ob_end_flush();
    exit();
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error saving dental record: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Clean any output before sending JSON
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
    ob_end_flush();
    exit();
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Fatal error saving dental record: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Clean any output before sending JSON
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Fatal error: ' . $e->getMessage()
    ]);
    ob_end_flush();
    exit();
}
?>
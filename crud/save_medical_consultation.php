<?php
/**
 * Save Medical Consultation Form (Walk-in)
 * Creates a CONSULTATION record (type: "Consultation") for walk-in patients.
 * This is different from scheduled appointments which create MEDICAL records (type: "Medical").
 * 
 * Flow: Walk-in Patient → Patient Records → Walk-in Consultation Tab → This File → medical_consultations table → Labeled as "Consultation"
 */

require_once '../config/database.php';
session_start();

// Ensure clean output for JSON response
ob_start();
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    ob_end_clean();
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_end_clean();
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $pdo = getDB();
    
    // Get form data
    $patient_id = $_POST['patient_id'] ?? null;
    $physician_id = $_POST['physician_id'] ?? $_SESSION['user_id'];
    
    // Ensure physician_name is set correctly - prioritize POST, then session, then lookup from database
    $physician_name = $_POST['physician_name'] ?? '';
    if (empty($physician_name)) {
        // Try to get from session
        $session_fname = $_SESSION['fname'] ?? '';
        $session_lname = $_SESSION['lname'] ?? '';
        if (!empty($session_fname) || !empty($session_lname)) {
            $physician_name = trim($session_fname . ' ' . $session_lname);
        } else {
            // Fallback: lookup from users table
            try {
                $userStmt = $pdo->prepare("SELECT fname, lname FROM users WHERE id = ?");
                $userStmt->execute([$physician_id]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $physician_name = trim(($user['fname'] ?? '') . ' ' . ($user['lname'] ?? ''));
                }
            } catch (PDOException $e) {
                error_log("Error fetching physician name: " . $e->getMessage());
            }
        }
    }
    
    // Get user role to determine validation requirements
    $user_role = $_SESSION['role'] ?? 'doctor';
    
    // Patient Information (readonly, for reference)
    $full_name = $_POST['full_name'] ?? '';
    $sr_code = $_POST['sr_code'] ?? '';
    $address = $_POST['address'] ?? '';
    $program = $_POST['program'] ?? '';
    
    // Nurse's Assessment - optional for doctors
    // If doctor is filling the form and assessment_date is empty, use current date
    $assessment_date = $_POST['assessment_date'] ?? '';
    if (empty($assessment_date)) {
        if ($user_role === 'doctor') {
            // For doctors, use current date if not provided
            $assessment_date = date('Y-m-d');
        } else {
            // For nurses, assessment_date is required - but we'll validate below
            $assessment_date = date('Y-m-d');
        }
    }
    $control_number = $_POST['control_number'] ?? '';
    $age = $_POST['age'] ?? '';
    $sex = $_POST['sex'] ?? '';
    $purpose = $_POST['purpose'] ?? '';
    
    // Vital Signs
    $blood_pressure = $_POST['blood_pressure'] ?? '';
    $pulse_rate = $_POST['pulse_rate'] ?? '';
    $spo2 = $_POST['spo2'] ?? '';
    $respiratory_rate = $_POST['respiratory_rate'] ?? '';
    $temperature = $_POST['temperature'] ?? '';
    
    // Measurements
    $height = $_POST['height'] ?? '';
    $weight = $_POST['weight'] ?? '';
    $bmi = $_POST['bmi'] ?? '';
    $last_menstrual_period = $_POST['last_menstrual_period'] ?? null;
    $vision_right = $_POST['vision_right'] ?? '';
    $vision_left = $_POST['vision_left'] ?? '';
    
    // Notes
    $nurse_notes = $_POST['nurse_notes'] ?? '';
    $doctor_date = $_POST['doctor_date'] ?? null;
    $doctor_notes = $_POST['doctor_notes'] ?? '';
    
    // Treatment Plan
    $treatment_instructions = $_POST['treatment_instructions'] ?? '';
    
    // Validate required fields
    // Purpose is always required, but assessment_date is optional for doctors
    if (empty($patient_id) || empty($purpose)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Patient ID and Purpose are required']);
        exit();
    }
    
    // For nurses, assessment_date should be provided (but we default it above, so this is just a sanity check)
    if ($user_role !== 'doctor' && empty($assessment_date)) {
        ob_end_clean();
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Assessment date is required']);
        exit();
    }
    
    // Check if medical_consultations table exists, create it if not
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'medical_consultations'");
    if ($tableCheck->rowCount() == 0) {
        // Table doesn't exist, create it
        $createTableSql = "CREATE TABLE `medical_consultations` (
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `patient_id` int(11) NOT NULL,
          `physician_id` int(11) NOT NULL,
          `physician_name` varchar(255) DEFAULT NULL,
          `assessment_date` date NOT NULL,
          `control_number` varchar(50) DEFAULT NULL,
          `age` int(11) DEFAULT NULL,
          `sex` enum('Male','Female','Other') DEFAULT NULL,
          `purpose` varchar(255) NOT NULL,
          `blood_pressure` varchar(20) DEFAULT NULL,
          `pulse_rate` varchar(20) DEFAULT NULL,
          `spo2` varchar(20) DEFAULT NULL,
          `respiratory_rate` varchar(20) DEFAULT NULL,
          `temperature` varchar(20) DEFAULT NULL,
          `height` varchar(20) DEFAULT NULL,
          `weight` varchar(20) DEFAULT NULL,
          `bmi` varchar(20) DEFAULT NULL,
          `last_menstrual_period` date DEFAULT NULL,
          `vision_right` varchar(20) DEFAULT NULL,
          `vision_left` varchar(20) DEFAULT NULL,
          `nurse_notes` text DEFAULT NULL,
          `doctor_date` date DEFAULT NULL,
          `doctor_notes` text DEFAULT NULL,
          `treatment_instructions` text DEFAULT NULL,
          `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
          `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
          PRIMARY KEY (`id`),
          KEY `idx_patient_id` (`patient_id`),
          KEY `idx_physician_id` (`physician_id`),
          KEY `idx_assessment_date` (`assessment_date`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci";
        
        try {
            $pdo->exec($createTableSql);
            error_log("Created missing medical_consultations table automatically");
        } catch (PDOException $e) {
            error_log("Failed to create medical_consultations table: " . $e->getMessage());
            echo json_encode([
                'success' => false,
                'message' => 'Database error: Failed to create medical_consultations table. Please contact administrator.'
            ]);
            exit();
        }
    } else {
        // Table exists - check if treatment_instructions column exists, add it if not
        try {
            $columnCheck = $pdo->query("SHOW COLUMNS FROM medical_consultations LIKE 'treatment_instructions'");
            if ($columnCheck->rowCount() == 0) {
                $alterSql = "ALTER TABLE medical_consultations ADD COLUMN treatment_instructions TEXT DEFAULT NULL AFTER doctor_notes";
                $pdo->exec($alterSql);
                error_log("Added treatment_instructions column to medical_consultations table");
            }
        } catch (PDOException $e) {
            error_log("Note: Could not add treatment_instructions column: " . $e->getMessage());
            // Continue anyway - column might already exist
        }
    }
    
    // Check and add consultation_id column to medicine_dispensed BEFORE starting transaction
    // ALTER TABLE automatically commits, so we need to do this outside the transaction
    try {
        $columnCheck = $pdo->query("SHOW COLUMNS FROM medicine_dispensed LIKE 'consultation_id'");
        if ($columnCheck->rowCount() == 0) {
            $alterSql = "ALTER TABLE medicine_dispensed ADD COLUMN consultation_id INT(11) DEFAULT NULL AFTER patient_id";
            $pdo->exec($alterSql);
            error_log("Added consultation_id column to medicine_dispensed table");
        }
    } catch (PDOException $e) {
        error_log("Note: Could not add consultation_id column to medicine_dispensed: " . $e->getMessage());
        // Continue anyway - column might already exist or we'll use fallback
    }
    
    // Start transaction AFTER all ALTER TABLE operations are done
    $pdo->beginTransaction();
    
    // Insert into medical_consultations table
    // Check if treatment_instructions column exists to include it in INSERT
    $columnCheck = $pdo->query("SHOW COLUMNS FROM medical_consultations LIKE 'treatment_instructions'");
    $hasTreatmentColumn = $columnCheck->rowCount() > 0;
    
    if ($hasTreatmentColumn) {
        $sql = "INSERT INTO medical_consultations (
            patient_id,
            physician_id,
            physician_name,
            assessment_date,
            control_number,
            age,
            sex,
            purpose,
            blood_pressure,
            pulse_rate,
            spo2,
            respiratory_rate,
            temperature,
            height,
            weight,
            bmi,
            last_menstrual_period,
            vision_right,
            vision_left,
            nurse_notes,
            doctor_date,
            doctor_notes,
            treatment_instructions,
            created_at
        ) VALUES (
            :patient_id,
            :physician_id,
            :physician_name,
            :assessment_date,
            :control_number,
            :age,
            :sex,
            :purpose,
            :blood_pressure,
            :pulse_rate,
            :spo2,
            :respiratory_rate,
            :temperature,
            :height,
            :weight,
            :bmi,
            :last_menstrual_period,
            :vision_right,
            :vision_left,
            :nurse_notes,
            :doctor_date,
            :doctor_notes,
            :treatment_instructions,
            NOW()
        )";
    } else {
        $sql = "INSERT INTO medical_consultations (
            patient_id,
            physician_id,
            physician_name,
            assessment_date,
            control_number,
            age,
            sex,
            purpose,
            blood_pressure,
            pulse_rate,
            spo2,
            respiratory_rate,
            temperature,
            height,
            weight,
            bmi,
            last_menstrual_period,
            vision_right,
            vision_left,
            nurse_notes,
            doctor_date,
            doctor_notes,
            created_at
        ) VALUES (
            :patient_id,
            :physician_id,
            :physician_name,
            :assessment_date,
            :control_number,
            :age,
            :sex,
            :purpose,
            :blood_pressure,
            :pulse_rate,
            :spo2,
            :respiratory_rate,
            :temperature,
            :height,
            :weight,
            :bmi,
            :last_menstrual_period,
            :vision_right,
            :vision_left,
            :nurse_notes,
            :doctor_date,
            :doctor_notes,
            NOW()
        )";
    }
    
    $stmt = $pdo->prepare($sql);
    $params = [
        ':patient_id' => $patient_id,
        ':physician_id' => $physician_id,
        ':physician_name' => $physician_name,
        ':assessment_date' => $assessment_date,
        ':control_number' => $control_number,
        ':age' => $age,
        ':sex' => $sex,
        ':purpose' => $purpose,
        ':blood_pressure' => $blood_pressure,
        ':pulse_rate' => $pulse_rate,
        ':spo2' => $spo2,
        ':respiratory_rate' => $respiratory_rate,
        ':temperature' => $temperature,
        ':height' => $height,
        ':weight' => $weight,
        ':bmi' => $bmi,
        ':last_menstrual_period' => $last_menstrual_period ?: null,
        ':vision_right' => $vision_right,
        ':vision_left' => $vision_left,
        ':nurse_notes' => $nurse_notes,
        ':doctor_date' => $doctor_date ?: null,
        ':doctor_notes' => $doctor_notes
    ];
    
    if ($hasTreatmentColumn) {
        $params[':treatment_instructions'] = $treatment_instructions;
    }
    
    $stmt->execute($params);
    
    $consultation_id = $pdo->lastInsertId();
    
    // Handle medicine dispensing from Treatment Plan section
    if (isset($_POST['medicines']) && is_array($_POST['medicines'])) {
        
        // Get employee ID for dispensed_by field once (outside the loop for efficiency)
        // Must reference employees.id, not users.id
        $employeeStmt = $pdo->prepare("
            SELECT e.id 
            FROM employees e 
            WHERE e.user_id = ? 
            AND e.status = 'active'
            LIMIT 1
        ");
        $employeeStmt->execute([$physician_id]);
        $employeeData = $employeeStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$employeeData || !isset($employeeData['id'])) {
            // If physician is not in employees table, find first active employee as fallback
            $fallbackStmt = $pdo->query("SELECT id FROM employees WHERE status = 'active' LIMIT 1");
            $fallbackEmployee = $fallbackStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$fallbackEmployee || !isset($fallbackEmployee['id'])) {
                // No employees exist - this is a critical error
                $pdo->rollBack();
                ob_end_clean();
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'message' => 'System error: No active employee found to record medicine dispensing. Please contact administrator.'
                ]);
                exit();
            }
            
            $dispensed_by = $fallbackEmployee['id'];
            error_log("Warning: Physician (user_id: {$physician_id}) not found in employees table. Using employee ID {$dispensed_by} as fallback.");
        } else {
            $dispensed_by = $employeeData['id'];
        }
        
        foreach ($_POST['medicines'] as $medicine) {
            $inventory_id = intval($medicine['inventory_id'] ?? 0);
            $quantity = intval($medicine['quantity'] ?? 0);
            
            // Skip if invalid medicine or quantity
            if ($inventory_id <= 0 || $quantity <= 0) {
                continue;
            }
            
            // Check medicine availability with lock to prevent race conditions
            $checkStmt = $pdo->prepare("
                SELECT id, quantity, dispensed, batch_number, item_code, expiry_date, item_name 
                FROM inventory 
                WHERE id = ? 
                AND status = 'active'
                AND expiry_date >= CURDATE()
                FOR UPDATE
            ");
            $checkStmt->execute([$inventory_id]);
            $medData = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$medData) {
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => "Medicine ID {$inventory_id} not found, expired, or inactive"
                ]);
                exit();
            }
            
            // Calculate available quantity (quantity field now reflects actual available stock)
            $available = $medData['quantity'] ?? 0;
            
            if ($available < $quantity) {
                // Rollback and return error
                $pdo->rollBack();
                echo json_encode([
                    'success' => false,
                    'message' => "Insufficient stock for {$medData['item_name']}. Available: {$available}, Requested: {$quantity}"
                ]);
                exit();
            }
            
            // Check if consultation_id column exists in medicine_dispensed table
            // Note: Column check was done before transaction, so we can safely check here
            $columnCheck = $pdo->query("SHOW COLUMNS FROM medicine_dispensed LIKE 'consultation_id'");
            $hasConsultationColumn = $columnCheck->rowCount() > 0;
            
            // Insert into medicine_dispensed table for comprehensive tracking
            if ($hasConsultationColumn) {
                $dispenseStmt = $pdo->prepare("
                    INSERT INTO medicine_dispensed (
                        patient_id,
                        consultation_id,
                        inventory_id,
                        batch_number,
                        item_code,
                        expiry_date,
                        quantity,
                        dispensed_date,
                        dispensed_time,
                        dispensed_by,
                        purpose
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?)
                ");
                
                $dispenseStmt->execute([
                    $patient_id,
                    $consultation_id,
                    $inventory_id,
                    $medData['batch_number'] ?? null,
                    $medData['item_code'] ?? null,
                    $medData['expiry_date'] ?? null,
                    $quantity,
                    $dispensed_by,
                    $purpose . ($treatment_instructions ? ' - ' . $treatment_instructions : '')
                ]);
            } else {
                // Fallback: Insert without consultation_id if column doesn't exist
                $dispenseStmt = $pdo->prepare("
                    INSERT INTO medicine_dispensed (
                        patient_id,
                        inventory_id,
                        batch_number,
                        item_code,
                        expiry_date,
                        quantity,
                        dispensed_date,
                        dispensed_time,
                        dispensed_by,
                        purpose
                    ) VALUES (?, ?, ?, ?, ?, ?, CURDATE(), CURTIME(), ?, ?)
                ");
                
                $dispenseStmt->execute([
                    $patient_id,
                    $inventory_id,
                    $medData['batch_number'] ?? null,
                    $medData['item_code'] ?? null,
                    $medData['expiry_date'] ?? null,
                    $quantity,
                    $dispensed_by,
                    $purpose . ($treatment_instructions ? ' - ' . $treatment_instructions : '')
                ]);
            }
            
            // Update inventory: decrement quantity AND increment dispensed count
            // This ensures real-time stock accuracy across all modules
            $updateStmt = $pdo->prepare("
                UPDATE inventory 
                SET quantity = quantity - ?,
                    dispensed = dispensed + ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $updateStmt->execute([$quantity, $quantity, $inventory_id]);
            
            // Check if update was successful and quantity is now low
            if ($updateStmt->rowCount() > 0) {
                // Get updated inventory data to check for low stock alerts
                $checkStmt = $pdo->prepare("SELECT quantity FROM inventory WHERE id = ?");
                $checkStmt->execute([$inventory_id]);
                $updatedMed = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                // Trigger alert check if quantity is now <= 10 (but don't fail if alert check errors)
                // Note: Alert check is skipped here to avoid including admin/inventory.php which has
                // session checks that interfere with JSON responses. Alerts will be checked by the
                // scheduled task or when admin accesses the inventory page.
                // The inventory will still be updated correctly, just the alert won't trigger immediately.
                if ($updatedMed && $updatedMed['quantity'] <= 10) {
                    // Log that alert check was skipped
                    error_log("Low stock detected for inventory ID {$inventory_id}, but alert check skipped to avoid output conflicts");
                    // Alerts will be checked by the scheduled task or admin inventory page
                }
            }
            
            error_log("Dispensed {$quantity} of medicine ID {$inventory_id} ({$medData['item_name']}) to patient ID {$patient_id}");
        }
    }
    
    // Also insert into visit_logs for tracking (optional - won't fail if table doesn't exist)
    try {
        // Check if visit_logs table exists
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'visit_logs'");
        if ($tableCheck->rowCount() > 0) {
            $visitLogSql = "INSERT INTO visit_logs (
                patient_id,
                physician_id,
                physician_name,
                visit_date,
                purpose,
                created_at
            ) VALUES (
                :patient_id,
                :physician_id,
                :physician_name,
                :visit_date,
                :purpose,
                NOW()
            )";
            
            $visitStmt = $pdo->prepare($visitLogSql);
            $visitStmt->execute([
                ':patient_id' => $patient_id,
                ':physician_id' => $physician_id,
                ':physician_name' => $physician_name,
                ':visit_date' => $assessment_date,
                ':purpose' => $purpose
            ]);
        }
    } catch (PDOException $e) {
        // Silently ignore if visit_logs table doesn't exist - it's optional for tracking
        error_log("Note: visit_logs table not available: " . $e->getMessage());
    }
    
    // AUTOMATIC APPOINTMENT COMPLETION: Update appointment status to 'completed'
    // NOTE: appointments.patient_id references users.id, but medical_consultations.patient_id references patients.id
    // We need to get the user_id from the patients table first
    try {
        // Get user_id from patients table
        $userStmt = $pdo->prepare("SELECT user_id FROM patients WHERE id = ?");
        $userStmt->execute([$patient_id]);
        $patient = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($patient && isset($patient['user_id'])) {
            $user_id = $patient['user_id'];
            
            // Find the most recent appointment for this patient with 'medical' type that is not completed/cancelled
            // Match by user_id (appointments.patient_id = users.id), appointment_type='medical', and appointment_date
            $findAppointmentSql = "SELECT id FROM appointments 
                                   WHERE patient_id = :user_id 
                                   AND appointment_type = 'medical'
                                   AND appointment_date <= :assessment_date
                                   AND status NOT IN ('completed', 'cancelled')
                                   ORDER BY appointment_date DESC, appointment_time DESC
                                   LIMIT 1";
            
            $findStmt = $pdo->prepare($findAppointmentSql);
            $findStmt->execute([
                ':user_id' => $user_id,
                ':assessment_date' => $assessment_date
            ]);
            $appointment = $findStmt->fetch(PDO::FETCH_ASSOC);
            
            $appointmentsUpdated = 0;
            if ($appointment) {
                // Get appointment details before updating for logging
                $appointmentStmt = $pdo->prepare("
                    SELECT 
                        a.*,
                        u.fname,
                        u.lname
                    FROM appointments a
                    JOIN users u ON a.patient_id = u.id
                    WHERE a.id = :appointment_id
                ");
                $appointmentStmt->execute([':appointment_id' => $appointment['id']]);
                $appointmentData = $appointmentStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($appointmentData) {
                    $oldStatus = $appointmentData['status'] ?? 'scheduled';
                    
                $updateStmt = $pdo->prepare("UPDATE appointments 
                                             SET status = 'completed', 
                                                 updated_at = NOW() 
                                             WHERE id = :appointment_id");
                $updateStmt->execute([':appointment_id' => $appointment['id']]);
                $appointmentsUpdated = $updateStmt->rowCount();
                
                if ($appointmentsUpdated > 0) {
                    error_log("Auto-completed medical appointment ID {$appointment['id']} for patient (user_id: {$user_id}, patients.id: {$patient_id}) on {$assessment_date}");
                        
                        // Log appointment completion
                        try {
                            require_once(__DIR__ . '/../includes/appointment_logger.php');
                            $employeeUserId = $_SESSION['user_id'] ?? null;
                            if ($employeeUserId) {
                                logAppointmentCompleted($pdo, $appointment['id'], $employeeUserId, $appointmentData);
                            }
                        } catch (Exception $e) {
                            error_log("Failed to log appointment completion: " . $e->getMessage());
                            // Continue even if logging fails
                        }
                    }
                }
            }
        }
    } catch (PDOException $e) {
        // Don't fail the consultation save if appointment completion fails
        error_log("Note: Could not complete appointment automatically: " . $e->getMessage());
    }
    
    // Commit transaction
    $pdo->commit();
    
    // Return success with updated inventory flag for frontend refresh
    echo json_encode([
        'success' => true,
        'message' => 'Medical consultation record saved successfully',
        'consultation_id' => $consultation_id,
        'inventory_updated' => true // Flag to indicate inventory was updated
    ]);
    
} catch (PDOException $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Clean any output before sending JSON
    ob_end_clean();
    
    error_log("Error saving consultation: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Clean any output before sending JSON
    ob_end_clean();
    
    error_log("Error saving consultation: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
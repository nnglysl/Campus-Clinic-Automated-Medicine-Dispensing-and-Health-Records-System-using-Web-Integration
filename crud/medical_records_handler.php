<?php
/**
 * Medical Records Handler
 * Unified endpoint for saving, fetching, and viewing medical records
 */

// Suppress display of errors to prevent HTML output
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Ensure no output before headers
if (ob_get_level()) {
    ob_clean();
}

require_once '../config/database.php';
session_start();

// Set JSON header early
header('Content-Type: application/json; charset=utf-8');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$pdo = getDB();
$action = $_POST['action'] ?? $_GET['action'] ?? 'get'; // default to 'get'

try {
    switch ($action) {
        /**
         * ===========================================================
         * SAVE MEDICAL RECORD
         * ===========================================================
         */
        case 'save':
            $patient_id = $_POST['patient_id'] ?? null;
            $visit_date = $_POST['visit_date'] ?? date('Y-m-d');
            $visit_time = $_POST['visit_time'] ?? date('H:i:s');
            $chief_complaint = $_POST['chief_complaint'] ?? '';
            $diagnosis = $_POST['diagnosis'] ?? '';
            $treatment_instructions = $_POST['treatment_instructions'] ?? '';
            $blood_pressure = $_POST['blood_pressure'] ?? null;
            $heart_rate = $_POST['heart_rate'] ?? null;
            $temperature = $_POST['temperature'] ?? null;
            $weight = $_POST['weight'] ?? null;
            $height = $_POST['height'] ?? null;
            $employee_id = $_SESSION['user_id'];

            // Calculate BMI
            $bmi = null;
            if ($height && $weight) {
                $heightInMeters = $height / 100;
                $bmi = round($weight / ($heightInMeters * $heightInMeters), 1);
            }

            // Validate required fields
            if (!$patient_id || !$chief_complaint || !$diagnosis) {
                echo json_encode(['success' => false, 'error' => 'Patient ID, chief complaint, and diagnosis are required']);
                exit;
            }

            // Insert record
            $stmt = $pdo->prepare("
                INSERT INTO medical_records 
                (patient_id, employee_id, visit_date, visit_time, chief_complaint, 
                 diagnosis, treatment_instructions, blood_pressure, heart_rate, 
                 temperature, weight, height, bmi)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $patient_id, $employee_id, $visit_date, $visit_time,
                $chief_complaint, $diagnosis, $treatment_instructions,
                $blood_pressure, $heart_rate, $temperature, $weight, $height, $bmi
            ]);

            $record_id = $pdo->lastInsertId();

            // Note: Prescriptions table removed - medicines are now stored in medicine_dispensed table
            // This code is kept for backward compatibility but no longer inserts to prescriptions

            // Create visit log (optional - won't fail if table doesn't exist)
            try {
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'visit_logs'");
                if ($tableCheck->rowCount() > 0) {
                    $stmt = $pdo->prepare("
                        SELECT 
                            COALESCE(p.full_name, CONCAT(u.fname, ' ', u.lname)) AS full_name
                        FROM patients p
                        LEFT JOIN users u ON u.id = p.user_id
                        WHERE p.id = ?
                    ");
                    $stmt->execute([$patient_id]);
                    $patient = $stmt->fetch(PDO::FETCH_ASSOC);

                    $stmt = $pdo->prepare("SELECT CONCAT(fname, ' ', lname) as name FROM users WHERE id = ?");
                    $stmt->execute([$employee_id]);
                    $physician = $stmt->fetch(PDO::FETCH_ASSOC);

                    $stmt = $pdo->prepare("
                        INSERT INTO visit_logs 
                        (patient_id, physician_id, physician_name, visit_date, purpose)
                        VALUES (?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $patient_id, $employee_id, 'Dr. ' . ($physician['name'] ?? ''), $visit_date, $chief_complaint
                    ]);
                }
            } catch (PDOException $e) {
                // Silently ignore if visit_logs table doesn't exist - it's optional for tracking
                error_log("Note: visit_logs table not available: " . $e->getMessage());
            }

            echo json_encode([
                'success' => true,
                'message' => 'Medical record saved successfully',
                'record_id' => $record_id
            ]);
            break;

        /**
         * ===========================================================
         * GET ALL MEDICAL RECORDS (for one patient)
         * ===========================================================
         */
        case 'get':
            $patient_id = $_GET['patient_id'] ?? null;

            if (!$patient_id) {
                error_log("Medical Records API Error: Patient ID is missing in request");
                echo json_encode([
                    'success' => false, 
                    'error' => 'Patient ID is required',
                    'records' => []
                ]);
                exit;
            }

            // Ensure patient_id is an integer
            $patient_id = (int)$patient_id;
            
            // Validate patient_id is positive
            if ($patient_id <= 0) {
                error_log("Medical Records API Error: Invalid patient_id = " . $patient_id);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Invalid patient ID',
                    'records' => []
                ]);
                exit;
            }

            // Debug logging
            error_log("Medical Records API: Requested patient_id = " . $patient_id . " (type: " . gettype($patient_id) . ")");

            // Get medical records from medical_records table
            // Try to get appointment_type from appointments table if linked
            // Note: appointments.patient_id = users.id, but mr.patient_id should be patients.id
            // However, some old records may have patient_id = users.id, so we check both
            // First, get the user_id for this patient
            $patientStmt = $pdo->prepare("SELECT user_id FROM patients WHERE id = ?");
            $patientStmt->execute([$patient_id]);
            $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
            $user_id = $patient ? $patient['user_id'] : null;
            
            // Check if dental_records table exists to exclude duplicate medical_records
            // We'll filter out medical_records that are duplicates of dental_records
            $excludeDentalMedicalRecords = false;
            $dentalRecordDates = [];
            try {
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'dental_records'");
                if ($tableCheck->rowCount() > 0) {
                    $excludeDentalMedicalRecords = true;
                    // Get all dates when dental records were created for this patient
                    // We'll exclude medical_records created on the same date with "Dental" in chief_complaint
                    $dentalDatesStmt = $pdo->prepare("
                        SELECT DISTINCT DATE(created_at) as dental_date 
                        FROM dental_records 
                        WHERE patient_id = ?
                    ");
                    $dentalDatesStmt->execute([$patient_id]);
                    $dentalDates = $dentalDatesStmt->fetchAll(PDO::FETCH_ASSOC);
                    foreach ($dentalDates as $row) {
                        $dentalRecordDates[] = $row['dental_date'];
                    }
                }
            } catch (PDOException $e) {
                error_log("Note: Error checking dental_records for exclusion: " . $e->getMessage());
            }
            
            // Build WHERE clause to exclude medical_records that duplicate dental_records
            $whereClause = "WHERE mr.patient_id = ?";
            $params = [$patient_id];
            
            if ($excludeDentalMedicalRecords && !empty($dentalRecordDates)) {
                // Exclude medical_records created on the same date as dental_records with "Dental" in chief_complaint
                $placeholders = implode(',', array_fill(0, count($dentalRecordDates), '?'));
                $whereClause .= " AND NOT (
                    mr.visit_date IN ($placeholders)
                    AND (mr.chief_complaint LIKE '%Dental%' OR mr.chief_complaint LIKE '%dental%')
                )";
                $params = array_merge($params, $dentalRecordDates);
            }
            
            $stmt = $pdo->prepare("
                SELECT DISTINCT
                    mr.id,
                    mr.patient_id,
                    mr.employee_id,
                    mr.visit_date,
                    mr.visit_time,
                    mr.chief_complaint,
                    mr.diagnosis,
                    mr.treatment_instructions,
                    mr.blood_pressure,
                    mr.heart_rate,
                    mr.pulse_rate,
                    mr.spo2,
                    mr.respiratory_rate,
                    mr.temperature,
                    mr.height,
                    mr.weight,
                    mr.bmi,
                    mr.created_at,
                    mr.updated_at,
                    CONCAT(u.fname, ' ', u.lname) as physician_name,
                    COALESCE(p.full_name, CONCAT(up.fname, ' ', up.lname)) as patient_name,
                    mr.chief_complaint as purpose,
                    mr.treatment_instructions as treatment,
                    NULL as notes,
                    COALESCE(a.appointment_type, 'medical') as appointment_type
                FROM medical_records mr
                LEFT JOIN users u ON mr.employee_id = u.id
                LEFT JOIN patients p ON mr.patient_id = p.id
                LEFT JOIN users up ON mr.patient_id = up.id AND p.id IS NULL
                LEFT JOIN appointments a ON (
                    (a.patient_id = p.user_id OR a.patient_id = up.id)
                    AND a.appointment_date = mr.visit_date 
                    AND a.appointment_time = mr.visit_time
                    AND a.status = 'completed'
                )
                $whereClause
                ORDER BY mr.visit_date DESC, mr.visit_time DESC
            ");
            $stmt->execute($params);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check which medical_records are actually dental records
            // (created when dental appointments are completed)
            $dentalMedicalRecordIds = [];
            try {
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'dental_records'");
                if ($tableCheck->rowCount() > 0) {
                    // Get all dental_records for this patient
                    $dentalStmt = $pdo->prepare("SELECT id, patient_id, created_at FROM dental_records WHERE patient_id = ?");
                    $dentalStmt->execute([$patient_id]);
                    $allDentalRecords = $dentalStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // For each dental record, find the corresponding medical_record (created on same day)
                    foreach ($allDentalRecords as $dentalRecord) {
                        $dentalDate = date('Y-m-d', strtotime($dentalRecord['created_at']));
                        // Find medical_record with same patient_id and visit_date = dental created_at date
                        // and chief_complaint contains "Dental" (indicating it's from a dental appointment)
                        $findStmt = $pdo->prepare("
                            SELECT id FROM medical_records 
                            WHERE patient_id = ? 
                            AND visit_date = ? 
                            AND (chief_complaint LIKE '%Dental%' OR chief_complaint LIKE '%dental%')
                            LIMIT 1
                        ");
                        $findStmt->execute([$patient_id, $dentalDate]);
                        $medRecord = $findStmt->fetch(PDO::FETCH_ASSOC);
                        if ($medRecord) {
                            $dentalMedicalRecordIds[] = $medRecord['id'];
                        }
                    }
                }
            } catch (PDOException $e) {
                error_log("Note: Error checking dental records: " . $e->getMessage());
            }
            
            // Add record_type for medical_records
            foreach ($records as &$record) {
                $record['is_consultation'] = false;
                
                // Check if this medical_record is actually from a dental appointment
                // Priority 1: Check if linked to dental_record
                if (in_array($record['id'], $dentalMedicalRecordIds)) {
                    $record['record_type'] = 'Dental';
                    $record['appointment_type'] = 'dental';
                    $record['is_dental'] = true;
                } 
                // Priority 2: Check appointment_type from appointments table
                elseif ($record['appointment_type'] === 'dental') {
                    $record['record_type'] = 'Dental';
                    $record['is_dental'] = true;
                } 
                // Priority 3: Check chief_complaint for "Dental" keyword
                elseif (stripos($record['chief_complaint'] ?? '', 'dental') !== false || 
                        stripos($record['chief_complaint'] ?? '', 'Dental Check-up') !== false) {
                    $record['record_type'] = 'Dental';
                    $record['appointment_type'] = 'dental';
                    $record['is_dental'] = true;
                } 
                // Default: Medical (from scheduled appointments)
                else {
                    // Check if this record is linked to an appointment
                    // If appointment_type is 'medical' and there's a linked appointment, it's from an appointment
                    if (!empty($record['appointment_type']) && $record['appointment_type'] === 'medical') {
                        $record['record_type'] = 'Medical';
                        $record['is_consultation'] = false;
                        $record['is_from_appointment'] = true; // Flag to identify appointment records
                    } else {
                        // If not linked to appointment, it might be an old record or from other sources
                        // Default to Medical but mark as not from appointment
                        $record['record_type'] = 'Medical';
                        $record['is_consultation'] = false;
                        $record['is_from_appointment'] = false;
                    }
                    $record['is_dental'] = false;
                }
            }
            unset($record);
            
            error_log("Medical Records API: Found " . count($records) . " medical_records for patient_id: " . $patient_id);
            // Debug: Log first record to see if pulse_rate and spo2 are present
            if (count($records) > 0) {
                $firstRecord = $records[0];
                error_log("Sample medical_record - pulse_rate: " . ($firstRecord['pulse_rate'] ?? 'NOT SET') . ", spo2: " . ($firstRecord['spo2'] ?? 'NOT SET') . ", respiratory_rate: " . ($firstRecord['respiratory_rate'] ?? 'NOT SET'));
            }
            
            // Also get dental records from dental_records table
            try {
                // Check if dental_records table exists
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'dental_records'");
                if ($tableCheck->rowCount() > 0) {
                    $stmt = $pdo->prepare("
                        SELECT 
                            dr.id as dental_record_id,
                            dr.patient_id,
                            dr.dentist_id,
                            dr.program,
                            dr.gingivitis,
                            dr.early_periodontitis,
                            dr.class_molar,
                            dr.overjet,
                            dr.overbite,
                            dr.orthodontic,
                            dr.stayplate,
                            dr.clenching,
                            dr.clicking,
                            dr.tooth_status,
                            dr.treatments,
                            dr.index_data,
                            dr.remarks,
                            dr.created_at,
                            dr.updated_at,
                            dr.created_at as visit_date,
                            NULL as visit_time,
                            CONCAT(u.fname, ' ', u.lname) as physician_name,
                            p.full_name as patient_name,
                            'Dental Examination' as chief_complaint,
                            CASE 
                                WHEN dr.gingivitis = 1 THEN 'Gingivitis'
                                WHEN dr.early_periodontitis = 1 THEN 'Early Periodontitis'
                                WHEN dr.class_molar = 1 THEN 'Class Molar'
                                WHEN dr.overjet = 1 THEN 'Overjet'
                                WHEN dr.overbite = 1 THEN 'Overbite'
                                WHEN dr.orthodontic = 1 THEN 'Orthodontic'
                                WHEN dr.stayplate = 1 THEN 'Stayplate'
                                WHEN dr.clenching = 1 THEN 'Clenching'
                                WHEN dr.clicking = 1 THEN 'Clicking'
                                ELSE 'Dental Examination'
                            END as diagnosis,
                            dr.remarks as treatment_instructions,
                            'dental' as appointment_type
                        FROM dental_records dr
                        LEFT JOIN users u ON dr.dentist_id = u.id
                        LEFT JOIN patients p ON dr.patient_id = p.id
                        WHERE dr.patient_id = ?
                        ORDER BY dr.created_at DESC
                    ");
                    $stmt->execute([$patient_id]);
                    $dentalRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    error_log("Medical Records Query: Found " . count($dentalRecords) . " dental_records for patient_id: " . $patient_id);
                    
                    // Merge dental records into records array
                    foreach ($dentalRecords as $dentalRecord) {
                        // Map fields for consistency
                        $dentalRecord['is_consultation'] = false;
                        $dentalRecord['record_type'] = 'Dental';
                        $dentalRecord['is_dental'] = true;
                        
                        // Ensure dental_record_id is preserved (it's the actual dental_records.id)
                        // This is needed to fetch full details later
                        if (!isset($dentalRecord['dental_record_id']) && isset($dentalRecord['id'])) {
                            $dentalRecord['dental_record_id'] = $dentalRecord['id'];
                        }
                        
                        // Decode JSON fields for use in frontend
                        if (!empty($dentalRecord['tooth_status'])) {
                            $dentalRecord['tooth_status'] = json_decode($dentalRecord['tooth_status'], true) ?? [];
                        } else {
                            $dentalRecord['tooth_status'] = [];
                        }
                        
                        if (!empty($dentalRecord['treatments'])) {
                            $dentalRecord['treatments'] = json_decode($dentalRecord['treatments'], true) ?? [];
                        } else {
                            $dentalRecord['treatments'] = [];
                        }
                        
                        // Decode index_data (index table data)
                        if (!empty($dentalRecord['index_data'])) {
                            $dentalRecord['index_data'] = json_decode($dentalRecord['index_data'], true) ?? ['temporary' => [], 'permanent' => []];
                        } else {
                            $dentalRecord['index_data'] = ['temporary' => [], 'permanent' => []];
                        }
                        
                        // Add dentist_name for consistency
                        if (!isset($dentalRecord['dentist_name'])) {
                            $dentalRecord['dentist_name'] = $dentalRecord['physician_name'] ?? null;
                        }
                        
                        // Format visit_date properly
                        if ($dentalRecord['visit_date']) {
                            $dentalRecord['visit_date'] = date('Y-m-d', strtotime($dentalRecord['visit_date']));
                        }
                        
                        $records[] = $dentalRecord;
                    }
                }
            } catch (PDOException $e) {
                // If dental_records table doesn't exist or query fails, just continue
                error_log("Note: dental_records query error: " . $e->getMessage());
            }
            
            // Also get medical consultations and merge them
            try {
                // Check if medical_consultations table exists
                $tableCheck = $pdo->query("SHOW TABLES LIKE 'medical_consultations'");
                if ($tableCheck->rowCount() > 0) {
                    $stmt = $pdo->prepare("
                        SELECT 
                            mc.id,
                            mc.patient_id,
                            mc.physician_id,
                            mc.assessment_date,
                            mc.control_number,
                            mc.age,
                            mc.sex,
                            mc.purpose,
                            mc.blood_pressure,
                            mc.pulse_rate,
                            mc.spo2,
                            mc.respiratory_rate,
                            mc.temperature,
                            mc.height,
                            mc.weight,
                            mc.bmi,
                            mc.last_menstrual_period,
                            mc.vision_right,
                            mc.vision_left,
                            mc.nurse_notes,
                            mc.doctor_date,
                            mc.doctor_notes,
                            COALESCE(mc.treatment_instructions, '') as treatment_instructions,
                            mc.created_at,
                            mc.updated_at,
                            p.full_name as patient_name,
                            COALESCE(mc.doctor_notes, mc.nurse_notes, '') as treatment,
                            TRIM(CONCAT(COALESCE(mc.nurse_notes, ''), ' ', COALESCE(mc.doctor_notes, ''))) as notes,
                            mc.assessment_date as visit_date,
                            NULL as visit_time,
                            mc.purpose as chief_complaint,
                            COALESCE(mc.doctor_notes, mc.nurse_notes, 'Consultation completed') as diagnosis,
                            COALESCE(mc.physician_name, CONCAT(u.fname, ' ', u.lname), 'Unknown') as physician_name
                        FROM medical_consultations mc
                        LEFT JOIN patients p ON mc.patient_id = p.id
                        LEFT JOIN users u ON mc.physician_id = u.id
                        WHERE mc.patient_id = ?
                        ORDER BY mc.assessment_date DESC, mc.created_at DESC
                    ");
                    $stmt->execute([$patient_id]);
                    $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // Debug logging - also check raw query result
                    error_log("Medical Records Query: Found " . count($consultations) . " consultations for patient_id: " . $patient_id);
                    if (count($consultations) > 0) {
                        error_log("Sample consultation data: " . json_encode($consultations[0]));
                    } else {
                        // Test if any consultations exist at all
                        $testStmt = $pdo->query("SELECT COUNT(*) as count, patient_id FROM medical_consultations GROUP BY patient_id");
                        $testResults = $testStmt->fetchAll(PDO::FETCH_ASSOC);
                        error_log("All consultations by patient_id: " . json_encode($testResults));
                    }
                    
                    // Merge consultations into records
                    foreach ($consultations as $consultation) {
                        // Map fields for display (medical_consultations doesn't have chief_complaint/diagnosis, use purpose/doctor_notes)
                        // IMPORTANT: Use different field names to avoid overwriting medical_records fields
                        $consultation['chief_complaint'] = $consultation['purpose'] ?? 'Consultation';
                        // For consultations, diagnosis comes from doctor_notes or nurse_notes
                        // Store it in diagnosis field but ensure is_consultation flag is set
                        $consultation['diagnosis'] = $consultation['doctor_notes'] ?? $consultation['nurse_notes'] ?? 'Consultation completed';
                        $consultation['treatment'] = $consultation['doctor_notes'] ?? $consultation['nurse_notes'] ?? 'N/A';
                        
                        // Fetch medicines from medicine_dispensed table for this consultation
                        // First try to match by consultation_id (most accurate)
                        // Fallback to patient_id + date if consultation_id column doesn't exist
                        try {
                            // Check if consultation_id column exists
                            $columnCheck = $pdo->query("SHOW COLUMNS FROM medicine_dispensed LIKE 'consultation_id'");
                            $hasConsultationColumn = $columnCheck->rowCount() > 0;
                            
                            if ($hasConsultationColumn) {
                                // Match by consultation_id (most accurate - prevents cross-contamination)
                                $medStmt = $pdo->prepare("
                                    SELECT 
                                        md.id,
                                        md.inventory_id,
                                        md.quantity,
                                        md.dispensed_date,
                                        md.dispensed_time,
                                        md.purpose,
                                        i.item_name,
                                        i.item_code,
                                        i.batch_number
                                    FROM medicine_dispensed md
                                    LEFT JOIN inventory i ON md.inventory_id = i.id
                                    WHERE md.consultation_id = ?
                                    ORDER BY md.dispensed_time DESC
                                ");
                                $medStmt->execute([$consultation['id']]);
                            } else {
                                // Fallback: Match by patient_id and dispensed_date = assessment_date
                                $medStmt = $pdo->prepare("
                                    SELECT 
                                        md.id,
                                        md.inventory_id,
                                        md.quantity,
                                        md.dispensed_date,
                                        md.dispensed_time,
                                        md.purpose,
                                        i.item_name,
                                        i.item_code,
                                        i.batch_number
                                    FROM medicine_dispensed md
                                    LEFT JOIN inventory i ON md.inventory_id = i.id
                                    WHERE md.patient_id = ? 
                                    AND md.dispensed_date = ?
                                    ORDER BY md.dispensed_time DESC
                                ");
                                $medStmt->execute([$consultation['patient_id'], $consultation['assessment_date']]);
                            }
                            
                            $medicines = $medStmt->fetchAll(PDO::FETCH_ASSOC);
                            $consultation['medicines'] = $medicines;
                        } catch (PDOException $e) {
                            error_log("Note: Could not fetch medicines for consultation: " . $e->getMessage());
                            $consultation['medicines'] = [];
                        }
                        
                        // Add a flag to identify it as a consultation (CRITICAL for proper display)
                        $consultation['is_consultation'] = true;
                        $consultation['record_type'] = 'Consultation';
                        // Ensure appointment_type is NOT set for consultations
                        $consultation['appointment_type'] = null;
                        $consultation['is_from_appointment'] = false;
                        $records[] = $consultation;
                    }
                    
                    // Re-sort by date
                    usort($records, function($a, $b) {
                        $dateA = $a['visit_date'] ?? $a['assessment_date'] ?? $a['created_at'] ?? '';
                        $dateB = $b['visit_date'] ?? $b['assessment_date'] ?? $b['created_at'] ?? '';
                        return strcmp($dateB, $dateA);
                    });
                }
            } catch (PDOException $e) {
                // If medical_consultations table doesn't exist or query fails, just continue with medical_records
                error_log("Note: medical_consultations query error: " . $e->getMessage());
            }
            
            // Final sort by date (includes all types: medical, dental, consultations)
            usort($records, function($a, $b) {
                $dateA = $a['visit_date'] ?? $a['assessment_date'] ?? $a['created_at'] ?? '';
                $dateB = $b['visit_date'] ?? $b['assessment_date'] ?? $b['created_at'] ?? '';
                return strcmp($dateB, $dateA);
            });

            foreach ($records as &$record) {
                // Medicines are now stored in medicine_dispensed table, not prescriptions
                // Prescriptions table has been removed - set empty array for backward compatibility
                $record['prescriptions'] = [];
            }

            // Debug logging
            error_log("Medical Records Response: Returning " . count($records) . " total records (medical_records + consultations) for patient_id: " . $patient_id);
            
            // Log first record for debugging
            if (count($records) > 0) {
                error_log("First record sample: " . json_encode($records[0]));
            } else {
                error_log("WARNING: No records found for patient_id: " . $patient_id);
                // Test query to see what's in the database
                $testStmt = $pdo->query("SELECT id, patient_id FROM medical_consultations LIMIT 5");
                $testRecords = $testStmt->fetchAll(PDO::FETCH_ASSOC);
                error_log("Sample medical_consultations records: " . json_encode($testRecords));
            }
            
            echo json_encode([
                'success' => true, 
                'records' => $records,
                'count' => count($records),
                'patient_id' => $patient_id
            ]);
            break;

        /**
         * ===========================================================
         * GET SINGLE MEDICAL RECORD (by record_id)
         * ===========================================================
         */
        case 'get_single':
            $record_id = $_GET['record_id'] ?? null;
            $source = $_GET['source'] ?? 'medical_records';

            if (!$record_id) {
                echo json_encode(['success' => false, 'error' => 'Record ID required']);
                exit;
            }

            $record = null;
            
            // Fetch record based on source table
            if ($source === 'dental_records') {
                // Fetch dental record
                $stmt = $pdo->prepare("
                    SELECT 
                        dr.*,
                        CONCAT(u.fname, ' ', u.lname) as physician_name,
                        p.full_name as patient_name,
                        p.sr_code,
                        p.program,
                        dr.created_at as visit_date
                    FROM dental_records dr
                    LEFT JOIN users u ON dr.dentist_id = u.id
                    LEFT JOIN patients p ON dr.patient_id = p.id
                    WHERE dr.id = ?
                ");
                $stmt->execute([$record_id]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($record) {
                    // Fetch treatments
                    try {
                        $treatmentStmt = $pdo->prepare("
                            SELECT 
                                date, tooth, operation, dentist
                            FROM dental_treatments
                            WHERE dental_record_id = ?
                            ORDER BY date ASC
                        ");
                        $treatmentStmt->execute([$record_id]);
                        $record['treatments'] = $treatmentStmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $record['treatments'] = [];
                    }
                    $record['source_table'] = 'dental_records';
                    $record['is_dental'] = true;
                }
            } elseif ($source === 'medical_consultations') {
                // Fetch consultation record
                $stmt = $pdo->prepare("
                    SELECT 
                        mc.*,
                        CONCAT(u.fname, ' ', u.lname) as physician_name,
                        p.full_name as patient_name,
                        p.sr_code,
                        p.program,
                        mc.assessment_date as visit_date
                    FROM medical_consultations mc
                    LEFT JOIN users u ON mc.physician_id = u.id
                    LEFT JOIN patients p ON mc.patient_id = p.id
                    WHERE mc.id = ?
                ");
                $stmt->execute([$record_id]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($record) {
                    // Fetch medicines dispensed
                    try {
                        $columnCheck = $pdo->query("SHOW COLUMNS FROM medicine_dispensed LIKE 'consultation_id'");
                        $hasConsultationColumn = $columnCheck->rowCount() > 0;
                        
                        if ($hasConsultationColumn) {
                            $medStmt = $pdo->prepare("
                                SELECT 
                                    md.id,
                                    md.inventory_id,
                                    md.quantity,
                                    md.dispensed_date,
                                    md.dispensed_time,
                                    i.item_name,
                                    i.item_code,
                                    i.batch_number
                                FROM medicine_dispensed md
                                LEFT JOIN inventory i ON md.inventory_id = i.id
                                WHERE md.consultation_id = ?
                                ORDER BY md.dispensed_time DESC
                            ");
                            $medStmt->execute([$record_id]);
                        } else {
                            $medStmt = $pdo->prepare("
                                SELECT 
                                    md.id,
                                    md.inventory_id,
                                    md.quantity,
                                    md.dispensed_date,
                                    md.dispensed_time,
                                    i.item_name,
                                    i.item_code,
                                    i.batch_number
                                FROM medicine_dispensed md
                                LEFT JOIN inventory i ON md.inventory_id = i.id
                                WHERE md.patient_id = ? 
                                AND md.dispensed_date = ?
                                ORDER BY md.dispensed_time DESC
                            ");
                            $medStmt->execute([$record['patient_id'], $record['assessment_date']]);
                        }
                        $record['medicines'] = $medStmt->fetchAll(PDO::FETCH_ASSOC);
                    } catch (PDOException $e) {
                        $record['medicines'] = [];
                    }
                    $record['source_table'] = 'medical_consultations';
                    $record['is_consultation'] = true;
                }
            } else {
                // Fetch medical record (default)
                $stmt = $pdo->prepare("
                    SELECT 
                        mr.*,
                        CONCAT(u.fname, ' ', u.lname) as physician_name,
                        p.full_name as patient_name,
                        p.sr_code,
                        p.program
                    FROM medical_records mr
                    LEFT JOIN users u ON mr.employee_id = u.id
                    LEFT JOIN patients p ON mr.patient_id = p.id
                    WHERE mr.id = ?
                ");
                $stmt->execute([$record_id]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($record) {
                    $record['prescriptions'] = [];
                    $record['source_table'] = 'medical_records';
                }
            }

            if ($record) {
                echo json_encode(['success' => true, 'record' => $record]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Record not found']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid or missing action']);
            break;
    }
} catch (PDOException $e) {
    error_log("Medical Records Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>

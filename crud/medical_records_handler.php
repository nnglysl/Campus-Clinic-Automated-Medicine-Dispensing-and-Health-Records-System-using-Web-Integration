<?php
/**
 * Medical Records Handler
 * Unified endpoint for saving, fetching, and viewing medical records
 */

require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

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

            // Handle prescriptions if provided
            if (isset($_POST['medicines']) && is_array($_POST['medicines'])) {
                foreach ($_POST['medicines'] as $medicine) {
                    if (!empty($medicine['inventory_id']) && !empty($medicine['quantity'])) {
                        $stmt = $pdo->prepare("
                            INSERT INTO prescriptions 
                            (medical_record_id, inventory_id, quantity, dosage_instructions)
                            VALUES (?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $record_id,
                            $medicine['inventory_id'],
                            $medicine['quantity'],
                            $medicine['dosage_instructions'] ?? ''
                        ]);
                    }
                }
            }

            // Create visit log
            $stmt = $pdo->prepare("SELECT full_name FROM patients WHERE id = ?");
            $stmt->execute([$patient_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT CONCAT(fname, ' ', lname) as name FROM users WHERE id = ?");
            $stmt->execute([$employee_id]);
            $physician = $stmt->fetch(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("
                INSERT INTO visit_logs 
                (patient_id, medical_record_id, purpose, physician_name, visit_date)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $patient_id, $record_id, $chief_complaint, 'Dr. ' . $physician['name'], $visit_date
            ]);

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
                echo json_encode(['success' => false, 'error' => 'Patient ID is required']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT 
                    mr.*,
                    CONCAT(u.fname, ' ', u.lname) as physician_name,
                    p.full_name as patient_name
                FROM medical_records mr
                LEFT JOIN users u ON mr.employee_id = u.id
                LEFT JOIN patients p ON mr.patient_id = p.id
                WHERE mr.patient_id = ?
                ORDER BY mr.visit_date DESC, mr.visit_time DESC
            ");
            $stmt->execute([$patient_id]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($records as &$record) {
                $stmt = $pdo->prepare("
                    SELECT 
                        pr.*, 
                        i.name as medicine_name, 
                        i.code as medicine_code
                    FROM prescriptions pr
                    LEFT JOIN inventory i ON pr.inventory_id = i.id
                    WHERE pr.medical_record_id = ?
                ");
                $stmt->execute([$record['id']]);
                $record['prescriptions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            echo json_encode(['success' => true, 'records' => $records]);
            break;

        /**
         * ===========================================================
         * GET SINGLE MEDICAL RECORD (by record_id)
         * ===========================================================
         */
        case 'get_single':
            $record_id = $_GET['record_id'] ?? null;

            if (!$record_id) {
                echo json_encode(['success' => false, 'error' => 'Record ID required']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT 
                    mr.*,
                    CONCAT(u.fname, ' ', u.lname) as physician_name,
                    p.full_name as patient_name
                FROM medical_records mr
                LEFT JOIN users u ON mr.employee_id = u.id
                LEFT JOIN patients p ON mr.patient_id = p.id
                WHERE mr.id = ?
            ");
            $stmt->execute([$record_id]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($record) {
                $stmt = $pdo->prepare("
                    SELECT 
                        pr.*, 
                        i.name as medicine_name, 
                        i.code as medicine_code
                    FROM prescriptions pr
                    LEFT JOIN inventory i ON pr.inventory_id = i.id
                    WHERE pr.medical_record_id = ?
                ");
                $stmt->execute([$record['id']]);
                $record['prescriptions'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$pdo = getDB();

$user = [
    'id' => $_SESSION['user_id'],
    'fname' => $_SESSION['fname'] ?? 'Admin',
    'lname' => $_SESSION['lname'] ?? '',
    'role' => $_SESSION['role'] ?? 'admin'
];

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'get_patient':
            getPatient($pdo);
            break;
        
        case 'add_patient':
            addPatient($pdo);
            break;
        
        case 'get_medical_records':
            getMedicalRecords($pdo);
            break;
        
        case 'add_medical_record':
            addMedicalRecord($pdo, $user);
            break;
        
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
    }
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

// Get single patient details
function getPatient($pdo) {
    $patientId = intval($_GET['patient_id'] ?? 0);
    
    if ($patientId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$patientId]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($patient) {
        echo json_encode(['success' => true, 'patient' => $patient]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Patient not found']);
    }
}

// Add new patient
function addPatient($pdo) {
    $fullName = trim($_POST['full_name'] ?? '');
    $srCode = trim($_POST['sr_code'] ?? '');
    $position = trim($_POST['position'] ?? '');
    $dob = trim($_POST['date_of_birth'] ?? '');
    $gender = trim($_POST['gender'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $emergencyName = trim($_POST['emergency_contact_name'] ?? '');
    $emergencyRelation = trim($_POST['emergency_contact_relationship'] ?? '');
    $emergencyPhone = trim($_POST['emergency_contact_phone'] ?? '');
    $bloodType = trim($_POST['blood_type'] ?? '');
    $allergies = trim($_POST['allergies'] ?? '') ?: 'None';
    $conditions = trim($_POST['medical_conditions'] ?? '') ?: 'None';
    
    // Validate required fields
    if (empty($fullName) || empty($srCode) || empty($position) || empty($dob) || empty($gender)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        return;
    }
    
    // Calculate age
    $birthDate = new DateTime($dob);
    $today = new DateTime();
    $age = $today->diff($birthDate)->y;
    
    // Check if SR-Code already exists
    $checkStmt = $pdo->prepare("SELECT id FROM patients WHERE sr_code = ?");
    $checkStmt->execute([$srCode]);
    
    if ($checkStmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'SR-Code already exists']);
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO patients (
            full_name, sr_code, position, date_of_birth, age, gender, 
            email, phone, address, emergency_contact_name, 
            emergency_contact_relationship, emergency_contact_phone, 
            blood_type, allergies, medical_conditions
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $result = $stmt->execute([
        $fullName, $srCode, $position, $dob, $age, $gender,
        $email, $phone, $address, $emergencyName, $emergencyRelation,
        $emergencyPhone, $bloodType, $allergies, $conditions
    ]);
    
    if ($result) {
        $newPatientId = $pdo->lastInsertId();
        echo json_encode([
            'success' => true, 
            'message' => 'Patient added successfully', 
            'patient_id' => $newPatientId
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error adding patient']);
    }
}

// Get patient medical records
function getMedicalRecords($pdo) {
    $patientId = intval($_GET['patient_id'] ?? 0);
    
    if ($patientId <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid patient ID']);
        return;
    }
    
    // FIXED: Join with employees table instead of users table
    // since medical_records.employee_id references employees table
    $stmt = $pdo->prepare("
        SELECT 
            mr.*,
            COALESCE(e.first_name, u.fname) as fname,
            COALESCE(e.last_name, u.lname) as lname
        FROM medical_records mr 
        LEFT JOIN employees e ON mr.employee_id = e.id 
        LEFT JOIN users u ON mr.employee_id = u.id
        WHERE mr.patient_id = ? 
        ORDER BY mr.visit_date DESC, mr.visit_time DESC
    ");
    $stmt->execute([$patientId]);
    $records = [];
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        // Get prescriptions for this record
        $prescStmt = $pdo->prepare("
            SELECT p.*, i.name as medicine_name, i.batchId as batch_id 
            FROM prescriptions p 
            JOIN inventory i ON p.inventory_id = i.id 
            WHERE p.medical_record_id = ?
        ");
        $prescStmt->execute([$row['id']]);
        $prescriptions = $prescStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Build physician name
        $physicianName = 'Dr. ' . ($row['fname'] ?? 'Unknown') . ' ' . ($row['lname'] ?? '');
        $row['physician_name'] = $physicianName;
        $row['prescriptions'] = $prescriptions;
        $records[] = $row;
    }
    
    echo json_encode(['success' => true, 'records' => $records]);
}

// Add new medical record
function addMedicalRecord($pdo, $user) {
    $pdo->beginTransaction();
    
    try {
        $patientId = intval($_POST['patient_id'] ?? 0);
        $employeeId = intval($_POST['physician_id'] ?? $user['id']); // Use session user_id as employee_id
        $visitDate = trim($_POST['visit_date'] ?? '');
        $visitTime = trim($_POST['visit_time'] ?? '');
        $chiefComplaint = trim($_POST['chief_complaint'] ?? '');
        $diagnosis = trim($_POST['diagnosis'] ?? '');
        $clinicalNotes = trim($_POST['clinical_notes'] ?? '');
        $treatmentInstructions = trim($_POST['treatment_instructions'] ?? '');
        $bloodPressure = trim($_POST['blood_pressure'] ?? '') ?: null;
        $heartRate = intval($_POST['heart_rate'] ?? 0) ?: null;
        $temperature = floatval($_POST['temperature'] ?? 0) ?: null;
        $weight = floatval($_POST['weight'] ?? 0) ?: null;
        $height = intval($_POST['height'] ?? 0) ?: null;
        $bmi = floatval($_POST['bmi'] ?? 0) ?: null;
        
        // Validate required fields
        if ($patientId <= 0) {
            throw new Exception("Invalid patient ID");
        }
        
        if (empty($visitDate) || empty($visitTime) || empty($chiefComplaint) || empty($diagnosis)) {
            throw new Exception("Please fill in all required fields");
        }
        
        // FIXED: Insert medical record with employee_id instead of physician_id
        $stmt = $pdo->prepare("
            INSERT INTO medical_records (
                patient_id, employee_id, visit_date, visit_time, 
                chief_complaint, diagnosis, clinical_notes, treatment_instructions, 
                blood_pressure, heart_rate, temperature, weight, height, bmi
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $patientId, $employeeId, $visitDate, $visitTime,
            $chiefComplaint, $diagnosis, $clinicalNotes, $treatmentInstructions,
            $bloodPressure, $heartRate, $temperature, $weight, $height, $bmi
        ]);
        
        $medicalRecordId = $pdo->lastInsertId();
        
        // Process prescriptions
        $medicines = json_decode($_POST['medicines'] ?? '[]', true);
        
        if (!is_array($medicines)) {
            $medicines = [];
        }
        
        foreach ($medicines as $medicine) {
            $inventoryId = intval($medicine['inventory_id'] ?? 0);
            $quantity = intval($medicine['quantity'] ?? 0);
            $dosage = trim($medicine['dosage'] ?? '');
            
            if ($inventoryId <= 0 || $quantity <= 0) {
                continue;
            }
            
            // Check medicine availability
            $checkStmt = $pdo->prepare("SELECT quantity, dispensed FROM inventory WHERE id = ? FOR UPDATE");
            $checkStmt->execute([$inventoryId]);
            $medData = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$medData) {
                throw new Exception("Medicine not found in inventory");
            }
            
            $available = ($medData['quantity'] ?? 0) - ($medData['dispensed'] ?? 0);
            
            if ($available < $quantity) {
                throw new Exception("Insufficient medicine stock. Available: " . $available);
            }
            
            // Insert prescription
            $prescStmt = $pdo->prepare("
                INSERT INTO prescriptions (medical_record_id, inventory_id, quantity, dosage_instructions) 
                VALUES (?, ?, ?, ?)
            ");
            $prescStmt->execute([$medicalRecordId, $inventoryId, $quantity, $dosage]);
            
            // Update medicine inventory - only update dispensed count
            $updateStmt = $pdo->prepare("
                UPDATE inventory 
                SET dispensed = dispensed + ? 
                WHERE id = ?
            ");
            $updateStmt->execute([$quantity, $inventoryId]);
        }
        
        // Add visit log
        $physicianName = trim($_POST['physician_name'] ?? 'Dr. ' . $user['fname'] . ' ' . $user['lname']);
        $logStmt = $pdo->prepare("
            INSERT INTO visit_logs (patient_id, medical_record_id, purpose, physician_name, visit_date) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $logStmt->execute([$patientId, $medicalRecordId, $chiefComplaint, $physicianName, $visitDate]);
        
        $pdo->commit();
        echo json_encode([
            'success' => true, 
            'message' => 'Medical record added successfully', 
            'record_id' => $medicalRecordId
        ]);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        error_log("Error in addMedicalRecord: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}
?>
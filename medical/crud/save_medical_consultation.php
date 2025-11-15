<?php
/**
 * Save Medical Consultation Form
 * Location: crud/save_consultation.php
 */

require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit();
}

try {
    $pdo = getDB();
    
    // Get form data
    $patient_id = $_POST['patient_id'] ?? null;
    $physician_id = $_POST['physician_id'] ?? $_SESSION['user_id'];
    $physician_name = $_POST['physician_name'] ?? '';
    
    // Patient Information (readonly, for reference)
    $full_name = $_POST['full_name'] ?? '';
    $sr_code = $_POST['sr_code'] ?? '';
    $address = $_POST['address'] ?? '';
    $program = $_POST['program'] ?? '';
    
    // Nurse's Assessment
    $assessment_date = $_POST['assessment_date'] ?? date('Y-m-d');
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
    
    // Validate required fields
    if (empty($patient_id) || empty($purpose)) {
        echo json_encode(['success' => false, 'message' => 'Patient ID and Purpose are required']);
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert into medical_consultations table
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
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
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
    ]);
    
    $consultation_id = $pdo->lastInsertId();
    
    // Also insert into visit_logs for tracking
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
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Medical consultation record saved successfully',
        'consultation_id' => $consultation_id
    ]);
    
} catch (PDOException $e) {
    // Rollback on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error saving consultation: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    // Rollback on error
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error saving consultation: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
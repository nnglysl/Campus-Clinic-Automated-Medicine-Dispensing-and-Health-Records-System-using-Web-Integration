<?php
/**
 * Save Appointment Medical Record
 * Creates a MEDICAL record (type: "Medical") when completing a scheduled appointment.
 * This is different from walk-in consultations which create CONSULTATION records (type: "Consultation").
 * 
 * Flow: Scheduled Appointment → Fill Form Button → This File → medical_records table → Labeled as "Medical"
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
    $pdo->beginTransaction();
    
    $appointment_id = $_POST['appointment_id'] ?? null;
    $chief_complaint = ''; // Not used in appointment form, set to empty
    $diagnosis = $_POST['diagnosis'] ?? '';
    $treatment_instructions = null; // Not used in appointment form, set to null
    
    // Vital signs
    $blood_pressure = $_POST['blood_pressure'] ?? null;
    $pulse_rate = $_POST['pulse_rate'] ?? null;
    $spo2 = $_POST['spo2'] ?? null;
    $respiratory_rate = $_POST['respiratory_rate'] ?? null;
    $temperature = $_POST['temperature'] ?? null;
    
    // Debug logging
    error_log("Save Appointment Record - Received data: pulse_rate=" . ($pulse_rate ?? 'NULL') . ", spo2=" . ($spo2 ?? 'NULL') . ", respiratory_rate=" . ($respiratory_rate ?? 'NULL'));
    
    // Measurements
    $height = $_POST['height'] ?? null;
    $weight = $_POST['weight'] ?? null;
    $bmi = $_POST['bmi'] ?? null;
    
    if (!$appointment_id) {
        throw new Exception('Appointment ID is required');
    }
    
    if (empty($diagnosis)) {
        throw new Exception('Diagnosis is required');
    }
    
    // Get appointment details
    $stmt = $pdo->prepare("
        SELECT a.*, u.fname, u.lname 
        FROM appointments a
        LEFT JOIN users u ON a.patient_id = u.id
        WHERE a.id = ?
    ");
    $stmt->execute([$appointment_id]);
    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$appointment) {
        throw new Exception('Appointment not found');
    }
    
    // Convert users.id (appointments.patient_id) to patients.id (medical_records.patient_id)
    $patientUserId = $appointment['patient_id']; // This is users.id
    $patientStmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
    $patientStmt->execute([$patientUserId]);
    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$patient) {
        throw new Exception('Patient record not found');
    }
    
    $patientId = $patient['id']; // This is patients.id, which is what medical_records uses
    
    // Check if medical record already exists for this appointment
    // Use a more specific check to prevent duplicates
    $checkStmt = $pdo->prepare("
        SELECT id FROM medical_records 
        WHERE patient_id = ? 
        AND visit_date = ? 
        AND visit_time = ?
        AND employee_id = ?
        LIMIT 1
    ");
    $checkStmt->execute([
        $patientId,
        $appointment['appointment_date'],
        $appointment['appointment_time'],
        $_SESSION['user_id']
    ]);
    
    $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
    if ($existingRecord) {
        // Record already exists, update it using the record ID for precision
        $updateStmt = $pdo->prepare("
            UPDATE medical_records SET
                chief_complaint = ?,
                diagnosis = ?,
                treatment_instructions = ?,
                blood_pressure = ?,
                heart_rate = ?,
                pulse_rate = ?,
                spo2 = ?,
                respiratory_rate = ?,
                temperature = ?,
                height = ?,
                weight = ?,
                bmi = ?,
                employee_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $updateStmt->execute([
            $chief_complaint,
            $diagnosis,
            $treatment_instructions,
            $blood_pressure,
            $pulse_rate, // heart_rate (same as pulse_rate)
            $pulse_rate, // pulse_rate
            $spo2,
            $respiratory_rate,
            $temperature,
            $height,
            $weight,
            $bmi,
            $_SESSION['user_id'],
            $existingRecord['id']
        ]);
        
        error_log("Save Appointment Record - Updated record with pulse_rate=" . ($pulse_rate ?? 'NULL') . ", spo2=" . ($spo2 ?? 'NULL'));
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Medical record updated successfully'
        ]);
    } else {
        // Create new medical record
        $insertStmt = $pdo->prepare("
            INSERT INTO medical_records 
            (patient_id, employee_id, visit_date, visit_time, chief_complaint, 
             diagnosis, treatment_instructions, blood_pressure, heart_rate, 
             pulse_rate, spo2, respiratory_rate, temperature, height, weight, bmi, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $insertStmt->execute([
            $patientId,
            $_SESSION['user_id'],
            $appointment['appointment_date'],
            $appointment['appointment_time'],
            $chief_complaint,
            $diagnosis,
            $treatment_instructions,
            $blood_pressure,
            $pulse_rate, // heart_rate
            $pulse_rate, // pulse_rate
            $spo2,
            $respiratory_rate,
            $temperature,
            $height,
            $weight,
            $bmi
        ]);
        
        error_log("Save Appointment Record - Inserted record with pulse_rate=" . ($pulse_rate ?? 'NULL') . ", spo2=" . ($spo2 ?? 'NULL'));
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Medical record created successfully',
            'record_id' => $pdo->lastInsertId()
        ]);
    }
    
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error saving appointment record: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    error_log("Error saving appointment record: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>


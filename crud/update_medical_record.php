<?php
/**
 * Update Medical Record API
 * Handles updates to both medical_records and medical_consultations
 */

require_once '../config/database.php';
session_start();

// Set headers early and prevent any output before JSON
header('Content-Type: application/json');

// Clean any existing output
while (ob_get_level()) {
    ob_end_clean();
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

try {
    $pdo = getDB();
    
    // Get JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid JSON data: ' . json_last_error_msg()
        ]);
        exit;
    }
    
    $isConsultation = $data['is_consultation'] ?? false;
    $pdo->beginTransaction();
    
    if ($isConsultation) {
        // Update medical_consultations
        $consultation_id = $data['consultation_id'] ?? null;
        
        if (!$consultation_id) {
            throw new Exception('Consultation ID is required');
        }
        
        // Verify record exists and user has permission
        $checkStmt = $pdo->prepare("SELECT id, physician_id FROM medical_consultations WHERE id = ?");
        $checkStmt->execute([$consultation_id]);
        $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingRecord) {
            throw new Exception('Consultation record not found');
        }
        
        // Check permission (physician who created it, or admin)
        $userRole = $_SESSION['role'] ?? '';
        if ($existingRecord['physician_id'] != $_SESSION['user_id'] && $userRole !== 'admin') {
            throw new Exception('You do not have permission to update this record');
        }
        
        $updateStmt = $pdo->prepare("
            UPDATE medical_consultations SET
                purpose = ?,
                assessment_date = ?,
                control_number = ?,
                age = ?,
                sex = ?,
                blood_pressure = ?,
                pulse_rate = ?,
                spo2 = ?,
                respiratory_rate = ?,
                temperature = ?,
                height = ?,
                weight = ?,
                bmi = ?,
                last_menstrual_period = ?,
                vision_right = ?,
                vision_left = ?,
                nurse_notes = ?,
                doctor_date = ?,
                doctor_notes = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $data['purpose'] ?? '',
            $data['assessment_date'] ?? null,
            $data['control_number'] ?? null,
            $data['age'] ?? null,
            $data['sex'] ?? null,
            $data['blood_pressure'] ?? null,
            $data['pulse_rate'] ?? null,
            $data['spo2'] ?? null,
            $data['respiratory_rate'] ?? null,
            $data['temperature'] ?? null,
            $data['height'] ?? null,
            $data['weight'] ?? null,
            $data['bmi'] ?? null,
            $data['last_menstrual_period'] ?? null,
            $data['vision_right'] ?? null,
            $data['vision_left'] ?? null,
            $data['nurse_notes'] ?? null,
            $data['doctor_date'] ?? null,
            $data['doctor_notes'] ?? null,
            $consultation_id
        ]);
        
    } else {
        // Update medical_records
        $medical_record_id = $data['medical_record_id'] ?? null;
        
        if (!$medical_record_id) {
            throw new Exception('Medical record ID is required');
        }
        
        // Verify record exists and user has permission
        $checkStmt = $pdo->prepare("SELECT id, employee_id FROM medical_records WHERE id = ?");
        $checkStmt->execute([$medical_record_id]);
        $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existingRecord) {
            throw new Exception('Medical record not found');
        }
        
        // Check permission (employee who created it, or admin)
        $userRole = $_SESSION['role'] ?? '';
        if ($existingRecord['employee_id'] != $_SESSION['user_id'] && $userRole !== 'admin') {
            throw new Exception('You do not have permission to update this record');
        }
        
        $updateStmt = $pdo->prepare("
            UPDATE medical_records SET
                visit_date = ?,
                visit_time = ?,
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
                updated_at = NOW()
            WHERE id = ?
        ");
        
        $updateStmt->execute([
            $data['visit_date'] ?? null,
            $data['visit_time'] ?? null,
            $data['chief_complaint'] ?? '',
            $data['diagnosis'] ?? '',
            $data['treatment_instructions'] ?? '',
            $data['blood_pressure'] ?? null,
            $data['pulse_rate'] ?? $data['heart_rate'] ?? null,
            $data['pulse_rate'] ?? $data['heart_rate'] ?? null,
            $data['spo2'] ?? null,
            $data['respiratory_rate'] ?? null,
            $data['temperature'] ?? null,
            $data['height'] ?? null,
            $data['weight'] ?? null,
            $data['bmi'] ?? null,
            $medical_record_id
        ]);
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Medical record updated successfully'
    ]);
    exit; // Important: exit after sending JSON
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error updating medical record: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
    exit;
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error updating medical record: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    exit;
}
?>

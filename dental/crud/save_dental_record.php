<?php
// File: ../crud/save_dental_record.php
require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
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
    $program = $_POST['program'] ?? '';
    $civil_status = $_POST['civil_status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    // Validate patient ID
    if (!$patient_id) {
        echo json_encode(['success' => false, 'message' => 'Patient ID is required']);
        exit();
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
        if (strpos($key, 'tooth_') === 0 && strpos($key, '_status') !== false) {
            $tooth_number = str_replace(['tooth_', '_status'], '', $key);
            if (!empty($value)) {
                $tooth_status[$tooth_number] = $value;
            }
        }
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
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Insert dental record
    $stmt = $pdo->prepare("
        INSERT INTO dental_records (
            patient_id, 
            program, 
            civil_status, 
            gingivitis, 
            early_periodontitis, 
            class_molar, 
            overjet, 
            overbite, 
            orthodontic, 
            stayplate, 
            clenching, 
            clicking, 
            tooth_status, 
            treatments, 
            remarks, 
            dentist_id, 
            created_at
        ) VALUES (
            :patient_id, 
            :program, 
            :civil_status, 
            :gingivitis, 
            :early_periodontitis, 
            :class_molar, 
            :overjet, 
            :overbite, 
            :orthodontic, 
            :stayplate, 
            :clenching, 
            :clicking, 
            :tooth_status, 
            :treatments, 
            :remarks, 
            :dentist_id, 
            NOW()
        )
    ");
    
    $stmt->execute([
        ':patient_id' => $patient_id,
        ':program' => $program,
        ':civil_status' => $civil_status,
        ':gingivitis' => $gingivitis,
        ':early_periodontitis' => $early_periodontitis,
        ':class_molar' => $class_molar,
        ':overjet' => $overjet,
        ':overbite' => $overbite,
        ':orthodontic' => $orthodontic,
        ':stayplate' => $stayplate,
        ':clenching' => $clenching,
        ':clicking' => $clicking,
        ':tooth_status' => json_encode($tooth_status),
        ':treatments' => json_encode($treatments),
        ':remarks' => $remarks,
        ':dentist_id' => $_SESSION['user_id']
    ]);
    
    $dental_record_id = $pdo->lastInsertId();
    
    // Also insert into medical_records table for compatibility
    $stmt = $pdo->prepare("
        INSERT INTO medical_records (
            patient_id,
            visit_date,
            purpose,
            chief_complaint,
            diagnosis,
            treatment,
            notes,
            physician_name,
            created_at
        ) VALUES (
            :patient_id,
            CURDATE(),
            'Dental Examination',
            'Dental Check-up',
            'Dental Record',
            :treatment,
            :notes,
            :physician_name,
            NOW()
        )
    ");
    
    $physician_name = ($_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? '');
    $treatment_summary = count($treatments) . ' dental treatment(s) recorded';
    
    $stmt->execute([
        ':patient_id' => $patient_id,
        ':treatment' => $treatment_summary,
        ':notes' => 'Dental Record ID: ' . $dental_record_id . '. ' . $remarks,
        ':physician_name' => trim($physician_name)
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Dental record saved successfully',
        'record_id' => $dental_record_id
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error saving dental record: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error saving dental record: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
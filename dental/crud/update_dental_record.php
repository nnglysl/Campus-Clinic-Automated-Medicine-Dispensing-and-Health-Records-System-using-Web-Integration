<?php
// File: dental/crud/update_dental_record.php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once '../../config/database.php';
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
    
    // Get JSON data
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data || json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode([
            'success' => false, 
            'message' => 'Invalid JSON data: ' . (json_last_error_msg() ?: 'Unknown error')
        ]);
        exit();
    }
    
    // Log received data for debugging
    error_log("Update dental record - Received data: " . json_encode($data));
    
    $dental_record_id = $data['dental_record_id'] ?? null;
    
    if (!$dental_record_id) {
        echo json_encode(['success' => false, 'message' => 'Dental record ID is required']);
        exit();
    }
    
    // Verify the record exists and belongs to the current user (dentist) or user has permission
    $checkStmt = $pdo->prepare("SELECT id, dentist_id FROM dental_records WHERE id = ?");
    $checkStmt->execute([$dental_record_id]);
    $existingRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$existingRecord) {
        echo json_encode(['success' => false, 'message' => 'Dental record not found']);
        exit();
    }
    
    // Check if user is the dentist who created the record or is an admin
    $userRole = $_SESSION['role'] ?? '';
    if ($existingRecord['dentist_id'] != $_SESSION['user_id'] && $userRole !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to update this record']);
        exit();
    }
    
    // Start transaction
    $pdo->beginTransaction();
    
    // Update dental record
    $updateStmt = $pdo->prepare("
        UPDATE dental_records SET
            gingivitis = ?,
            early_periodontitis = ?,
            class_molar = ?,
            overjet = ?,
            overbite = ?,
            orthodontic = ?,
            stayplate = ?,
            clenching = ?,
            clicking = ?,
            tooth_status = ?,
            index_data = ?,
            treatments = ?,
            remarks = ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    
    // Prepare index_data - validate it's JSON if provided
    $index_data = $data['index_data'] ?? null;
    if ($index_data && !is_string($index_data)) {
        // If it's an array/object, convert to JSON
        $index_data = json_encode($index_data, JSON_UNESCAPED_UNICODE);
    }
    if ($index_data === null || $index_data === '') {
        $index_data = null; // Set to NULL for empty index_data
    }
    
    // Prepare treatments - ensure it's JSON string
    $treatments = $data['treatments'] ?? '[]';
    if ($treatments && !is_string($treatments)) {
        $treatments = json_encode($treatments, JSON_UNESCAPED_UNICODE);
    }
    
    // Prepare tooth_status - preserve empty strings for unfilled teeth
    // tooth_status JSON contains all teeth, with empty string ("") for unfilled/unmarked teeth
    // This ensures both filled and unfilled states are accurately stored
    $tooth_status = $data['tooth_status'] ?? '{}';
    if (!is_string($tooth_status)) {
        // If it's an object/array, convert to JSON (preserves empty strings)
        $tooth_status = json_encode($tooth_status, JSON_UNESCAPED_UNICODE);
    }
    
    // Validate JSON format while preserving empty string values
    $tooth_status_decoded = json_decode($tooth_status, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("Invalid tooth_status JSON: " . json_last_error_msg());
        $tooth_status = '{}'; // Fallback to empty object
    } else {
        // Re-encode to ensure proper format (preserves empty string values)
        $tooth_status = json_encode($tooth_status_decoded, JSON_UNESCAPED_UNICODE);
    }
    
    error_log("Updating tooth_status (preserving empty strings for unfilled teeth): " . substr($tooth_status, 0, 300));
    
    $updateStmt->execute([
        $data['gingivitis'] ?? 0,
        $data['early_periodontitis'] ?? 0,
        $data['class_molar'] ?? 0,
        $data['overjet'] ?? 0,
        $data['overbite'] ?? 0,
        $data['orthodontic'] ?? 0,
        $data['stayplate'] ?? 0,
        $data['clenching'] ?? 0,
        $data['clicking'] ?? 0,
        $tooth_status, // Contains all teeth with empty strings for unfilled ones
        $index_data,
        $treatments,
        $data['remarks'] ?? '',
        $dental_record_id
    ]);
    
    // Also update the corresponding medical_records entry if it exists
    // Find the medical_record linked to this dental_record
    $dentalRecord = $pdo->prepare("SELECT patient_id, created_at FROM dental_records WHERE id = ?");
    $dentalRecord->execute([$dental_record_id]);
    $dentalData = $dentalRecord->fetch(PDO::FETCH_ASSOC);
    
    if ($dentalData) {
        $dentalDate = date('Y-m-d', strtotime($dentalData['created_at']));
        
        // Build diagnosis from updated conditions
        $diagnosis_parts = [];
        if ($data['gingivitis'] ?? 0) $diagnosis_parts[] = 'Gingivitis';
        if ($data['early_periodontitis'] ?? 0) $diagnosis_parts[] = 'Early Periodontitis';
        if ($data['class_molar'] ?? 0) $diagnosis_parts[] = 'Class Molar';
        if ($data['overjet'] ?? 0) $diagnosis_parts[] = 'Overjet';
        if ($data['overbite'] ?? 0) $diagnosis_parts[] = 'Overbite';
        if ($data['orthodontic'] ?? 0) $diagnosis_parts[] = 'Orthodontic';
        if ($data['stayplate'] ?? 0) $diagnosis_parts[] = 'Stayplate';
        if ($data['clenching'] ?? 0) $diagnosis_parts[] = 'Clenching';
        if ($data['clicking'] ?? 0) $diagnosis_parts[] = 'Clicking';
        
        $diagnosis = !empty($diagnosis_parts) ? implode(', ', $diagnosis_parts) : 'Dental Examination';
        
        // Update medical_records
        $updateMedicalStmt = $pdo->prepare("
            UPDATE medical_records SET
                diagnosis = ?,
                treatment_instructions = ?,
                updated_at = NOW()
            WHERE patient_id = ?
            AND visit_date = ?
            AND chief_complaint LIKE '%Dental%'
            LIMIT 1
        ");
        
        $updateMedicalStmt->execute([
            $diagnosis,
            $data['remarks'] ?? '',
            $dentalData['patient_id'],
            $dentalDate
        ]);
    }
    
    $pdo->commit();
    
    error_log("Dental record updated successfully: ID " . $dental_record_id);
    
    echo json_encode([
        'success' => true,
        'message' => 'Dental record updated successfully',
        'dental_record_id' => $dental_record_id
    ]);
    
} catch (PDOException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMessage = $e->getMessage();
    error_log("Error updating dental record: " . $errorMessage);
    error_log("Error trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $errorMessage
    ]);
} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $errorMessage = $e->getMessage();
    error_log("Error updating dental record: " . $errorMessage);
    error_log("Error trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $errorMessage
    ]);
}
?>


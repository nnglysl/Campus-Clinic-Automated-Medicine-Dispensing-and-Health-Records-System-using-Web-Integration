<?php
/**
 * Get full dental record details by ID
 */

require_once '../../config/database.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$dental_record_id = $_GET['id'] ?? null;

if (!$dental_record_id) {
    echo json_encode(['success' => false, 'message' => 'Dental record ID is required']);
    exit();
}

try {
    $pdo = getDB();
    
    // Get full dental record with related data
    // Note: Use dr.program directly from dental_records table (it's stored there)
    // Don't try to get p.program from patients table as it may not exist in all database schemas
    $stmt = $pdo->prepare("
        SELECT 
            dr.*,
            CONCAT(u.fname, ' ', u.lname) as dentist_name,
            p.full_name as patient_name,
            p.sr_code,
            p.address
            -- dr.program is already included in dr.*, so we don't need to select it separately
        FROM dental_records dr
        LEFT JOIN users u ON dr.dentist_id = u.id
        LEFT JOIN patients p ON dr.patient_id = p.id
        WHERE dr.id = ?
    ");
    $stmt->execute([$dental_record_id]);
    $dentalRecord = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$dentalRecord) {
        echo json_encode([
            'success' => false,
            'message' => 'Dental record not found'
        ]);
        exit();
    }
    
    // Add position as alias for program (for compatibility)
    $dentalRecord['position'] = $dentalRecord['program'] ?? 'Student';
    
    // Decode JSON fields
    if ($dentalRecord['tooth_status']) {
        $dentalRecord['tooth_status'] = json_decode($dentalRecord['tooth_status'], true) ?? [];
    } else {
        $dentalRecord['tooth_status'] = [];
    }
    
    if ($dentalRecord['treatments']) {
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
    
    echo json_encode([
        'success' => true,
        'record' => $dentalRecord
    ]);
    
} catch (PDOException $e) {
    error_log("Error getting dental record: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>


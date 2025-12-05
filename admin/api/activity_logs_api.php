<?php
session_start();
require_once '../../config/database.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized', 'data' => []]);
    exit;
}
try {
    $pdo = getDB();
    $action = $_GET['action'] ?? '';
    $dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
    $dateTo = $_GET['date_to'] ?? date('Y-m-d');
    $search = $_GET['search'] ?? '';
    if (empty($action)) throw new Exception('Action required');
    $data = [];
    switch ($action) {
        case 'get_user_logs':
            $sql = "SELECT ual.id, ual.user_id, COALESCE(CONCAT(u.fname, ' ', u.lname), 'Unknown') as user_name, ual.action, ual.details, ual.created_at FROM user_activity_logs ual LEFT JOIN users u ON ual.user_id = u.id WHERE DATE(ual.created_at) BETWEEN ? AND ?";
            if (!empty($search)) $sql .= " AND (COALESCE(CONCAT(u.fname, ' ', u.lname), '') LIKE ? OR ual.action LIKE ? OR ual.details LIKE ?)";
            $sql .= " ORDER BY ual.created_at DESC";
            $params = [$dateFrom, $dateTo];
            if (!empty($search)) { $searchParam = "%{$search}%"; $params[] = $searchParam; $params[] = $searchParam; $params[] = $searchParam; }
            $stmt = $pdo->prepare($sql); $stmt->execute($params); $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        case 'get_medicine_logs':
            $sql = "SELECT md.id, i.item_name as medicine_name, md.quantity, md.dispensed_date, md.dispensed_time, COALESCE(CONCAT(d.fname, ' ', d.lname), 'Unknown') as dispensed_by, COALESCE(CONCAT(p.fname, ' ', p.lname), CONCAT(u_patient.fname, ' ', u_patient.lname), 'Unknown') as patient_name, COALESCE((SELECT quantity FROM inventory WHERE id = md.inventory_id), 0) as remaining_stock FROM medicine_dispensed md LEFT JOIN inventory i ON md.inventory_id = i.id LEFT JOIN users d ON md.dispensed_by = d.id LEFT JOIN patients p ON md.patient_id = p.id LEFT JOIN users u_patient ON p.user_id = u_patient.id WHERE DATE(md.dispensed_date) BETWEEN ? AND ?";
            if (!empty($search)) $sql .= " AND (i.item_name LIKE ? OR COALESCE(CONCAT(p.fname, ' ', p.lname), CONCAT(u_patient.fname, ' ', u_patient.lname), '') LIKE ?)";
            $sql .= " ORDER BY md.dispensed_date DESC";
            $params = [$dateFrom, $dateTo];
            if (!empty($search)) { $searchParam = "%{$search}%"; $params[] = $searchParam; $params[] = $searchParam; }
            $stmt = $pdo->prepare($sql); $stmt->execute($params); $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        case 'get_medical_activity':
            $sql = "SELECT 'Medical Record' as activity_type, mr.id as record_id, 'medical_records' as source_table, COALESCE(CONCAT(p.fname, ' ', p.lname), CONCAT(u_mr.fname, ' ', u_mr.lname), 'Unknown') as patient_name, COALESCE(mr.visit_date, mr.created_at) as created_at, FALSE as is_consultation FROM medical_records mr LEFT JOIN patients p ON mr.patient_id = p.id LEFT JOIN users u_mr ON p.user_id = u_mr.id WHERE DATE(mr.created_at) BETWEEN ? AND ? UNION ALL SELECT 'Medical Consultation' as activity_type, mc.id as record_id, 'medical_consultations' as source_table, COALESCE(CONCAT(p.fname, ' ', p.lname), CONCAT(u_mc.fname, ' ', u_mc.lname), 'Unknown') as patient_name, COALESCE(mc.assessment_date, mc.created_at) as created_at, TRUE as is_consultation FROM medical_consultations mc LEFT JOIN patients p ON mc.patient_id = p.id LEFT JOIN users u_mc ON p.user_id = u_mc.id WHERE DATE(mc.created_at) BETWEEN ? AND ? UNION ALL SELECT 'Dental Record' as activity_type, dr.id as record_id, 'dental_records' as source_table, COALESCE(CONCAT(p.fname, ' ', p.lname), CONCAT(u_dr.fname, ' ', u_dr.lname), 'Unknown') as patient_name, dr.created_at as created_at, FALSE as is_consultation FROM dental_records dr LEFT JOIN patients p ON dr.patient_id = p.id LEFT JOIN users u_dr ON p.user_id = u_dr.id WHERE DATE(dr.created_at) BETWEEN ? AND ?";
            $params = [$dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo];
            $stmt = $pdo->prepare($sql); $stmt->execute($params); $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            // Filter out records with "Unknown" patient names
            $results = array_filter($results, function($row) {
                $patientName = trim($row['patient_name'] ?? '');
                return !empty($patientName) && strtolower($patientName) !== 'unknown';
            });
            $results = array_values($results);
            if (!empty($search)) {
                $searchLower = strtolower($search);
                $results = array_filter($results, function($row) use ($searchLower) {
                    return strpos(strtolower($row['patient_name'] ?? ''), $searchLower) !== false || strpos(strtolower($row['activity_type'] ?? ''), $searchLower) !== false;
                });
                $results = array_values($results);
            }
            usort($results, function($a, $b) {
                $timeA = strtotime($a['created_at'] ?? '1970-01-01');
                $timeB = strtotime($b['created_at'] ?? '1970-01-01');
                return $timeB - $timeA;
            });
            $data = $results;
            break;
        case 'get_appointment_history':
            $sql = "SELECT ah.id, ah.appointment_id, COALESCE(CONCAT(p.fname, ' ', p.lname), CONCAT(u_patient.fname, ' ', u_patient.lname), 'Unknown') as patient_name, ah.action, a.appointment_type, ah.old_date, ah.old_time, ah.new_date, ah.new_time, COALESCE(CONCAT(u.fname, ' ', u.lname), 'System') as changed_by_name, ah.changed_by, ah.notes, ah.created_at FROM appointment_history ah LEFT JOIN appointments a ON ah.appointment_id = a.id LEFT JOIN users u_patient ON a.patient_id = u_patient.id LEFT JOIN patients p ON u_patient.id = p.user_id LEFT JOIN users u ON ah.changed_by = u.id WHERE DATE(ah.created_at) BETWEEN ? AND ?";
            if (!empty($search)) $sql .= " AND (COALESCE(CONCAT(p.fname, ' ', p.lname), CONCAT(u_patient.fname, ' ', u_patient.lname), '') LIKE ? OR ah.action LIKE ?)";
            $sql .= " ORDER BY ah.created_at DESC";
            $params = [$dateFrom, $dateTo];
            if (!empty($search)) { $searchParam = "%{$search}%"; $params[] = $searchParam; $params[] = $searchParam; }
            $stmt = $pdo->prepare($sql); $stmt->execute($params); $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
        default: throw new Exception('Invalid action');
    }
    echo json_encode(['success' => true, 'data' => $data], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'data' => []], JSON_UNESCAPED_UNICODE);
}
?>

<?php
header('Content-Type: application/json');
require_once '../config/database.php';

$pdo = getDB();
$action = $_GET['action'] ?? '';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$search = $_GET['search'] ?? '';

try {
    switch ($action) {
        case 'get_user_logs':
            $sql = "SELECT 
                        ual.id,
                        CONCAT(u.fname, ' ', COALESCE(u.mname, ''), ' ', u.lname) as user_name,
                        ual.action,
                        ual.details,
                        ual.created_at
                    FROM user_activity_logs ual
                    LEFT JOIN users u ON ual.user_id = u.id
                    WHERE DATE(ual.created_at) BETWEEN :date_from AND :date_to";
            
            if (!empty($search)) {
                $sql .= " AND (
                    CONCAT(u.fname, ' ', u.lname) LIKE :search 
                    OR ual.action LIKE :search 
                    OR ual.details LIKE :search
                )";
            }
            
            $sql .= " ORDER BY ual.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':date_from', $dateFrom);
            $stmt->bindValue(':date_to', $dateTo);
            if (!empty($search)) {
                $stmt->bindValue(':search', "%$search%");
            }
            $stmt->execute();
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'get_medicine_logs':
            $sql = "SELECT 
                        md.id,
                        i.item_name as medicine_name,
                        md.quantity,
                        CONCAT(e.first_name, ' ', e.last_name) as dispensed_by,
                        CONCAT(u.fname, ' ', COALESCE(u.mname, ''), ' ', u.lname) as patient_name,
                        i.quantity as remaining_stock,
                        md.dispensed_date,
                        md.dispensed_time
                    FROM medicine_dispensed md
                    LEFT JOIN inventory i ON md.inventory_id = i.id
                    LEFT JOIN employees e ON md.dispensed_by = e.id
                    LEFT JOIN users u ON md.patient_id = u.id
                    WHERE md.dispensed_date BETWEEN :date_from AND :date_to";
            
            if (!empty($search)) {
                $sql .= " AND (
                    i.item_name LIKE :search 
                    OR CONCAT(e.first_name, ' ', e.last_name) LIKE :search
                    OR CONCAT(u.fname, ' ', u.lname) LIKE :search
                )";
            }
            
            $sql .= " ORDER BY md.dispensed_date DESC, md.dispensed_time DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':date_from', $dateFrom);
            $stmt->bindValue(':date_to', $dateTo);
            if (!empty($search)) {
                $stmt->bindValue(':search', "%$search%");
            }
            $stmt->execute();
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        case 'get_alert_logs':
            $sql = "SELECT 
                        al.id,
                        al.alert_type,
                        al.item_name,
                        al.message,
                        al.created_at
                    FROM alert_log al
                    WHERE DATE(al.created_at) BETWEEN :date_from AND :date_to";
            
            if (!empty($search)) {
                $sql .= " AND (
                    al.alert_type LIKE :search 
                    OR al.item_name LIKE :search 
                    OR al.message LIKE :search
                )";
            }
            
            $sql .= " ORDER BY al.created_at DESC";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':date_from', $dateFrom);
            $stmt->bindValue(':date_to', $dateTo);
            if (!empty($search)) {
                $stmt->bindValue(':search', "%$search%");
            }
            $stmt->execute();
            
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'data' => $data]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
            break;
    }
} catch (PDOException $e) {
    error_log("Activity Logs API Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>
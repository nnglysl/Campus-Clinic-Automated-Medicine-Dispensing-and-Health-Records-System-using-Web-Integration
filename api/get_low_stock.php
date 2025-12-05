<?php
session_start();
require_once '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Get low stock items (quantity <= 10) - get individual batches, not aggregated
    $stmt = $pdo->prepare("
        SELECT 
            batch_number,
            item_code,
            item_name,
            quantity as quantity_on_hand,
            dispensed as quantity_dispensed,
            expiry_date as expiration_date
        FROM inventory 
        WHERE status = 'active'
        AND quantity <= 10
        ORDER BY quantity ASC, item_name ASC
        LIMIT 50
    ");
    $stmt->execute();
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'items' => $items
    ]);
} catch (PDOException $e) {
    error_log("Low stock API error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'items' => []
    ]);
}
?>


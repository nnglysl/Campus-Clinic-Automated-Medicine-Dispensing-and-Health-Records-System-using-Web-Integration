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
    // Get items expiring within 30 days (but not expired)
    $stmt = $pdo->prepare("
        SELECT 
            batch_number,
            item_code,
            item_name,
            quantity as quantity_on_hand,
            dispensed as quantity_dispensed,
            expiry_date as expiration_date
        FROM inventory 
        WHERE expiry_date > CURDATE() 
        AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
        AND status = 'active'
        ORDER BY expiry_date ASC
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
    error_log("Expiring items API error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'items' => []
    ]);
}
?>


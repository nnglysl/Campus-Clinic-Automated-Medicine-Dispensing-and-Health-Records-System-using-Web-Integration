<?php
session_start();
require_once __DIR__ . '/../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    // Fetch all medicines from inventory that are available to dispense
    // Quantity field now reflects actual available stock (already decremented when dispensed)
    $stmt = $pdo->query("
        SELECT id, batch_number, item_code, item_name, quantity, dispensed, expiry_date, description, status 
        FROM inventory 
        WHERE status = 'active' 
        AND expiry_date >= CURDATE() 
        AND quantity > 0 
        ORDER BY item_name ASC, expiry_date ASC
    ");
    $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'medicines' => $medicines
    ]);
} catch (PDOException $e) {
    error_log("Get medicines API error: " . $e->getMessage());
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'medicines' => []
    ]);
}
?>


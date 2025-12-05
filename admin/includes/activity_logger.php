<?php
function logActivity($pdo, $userId, $action, $details = null) {
    try {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        
        $sql = "INSERT INTO user_activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
                VALUES (:user_id, :action, :details, :ip_address, :user_agent, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':action' => $action,
            ':details' => $details,
            ':ip_address' => $ipAddress,
            ':user_agent' => $userAgent
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Activity logging error: " . $e->getMessage());
        return false;
    }
}

if ($loginSuccessful) {
    require_once 'includes/activity_logger.php';
    logActivity($pdo, $user['id'], 'Login', 'User logged in successfully');
}


session_start();
require_once '../config/database.php';
require_once '../includes/activity_logger.php';

if (isset($_SESSION['user_id'])) {
    $pdo = getDB();
    logActivity($pdo, $_SESSION['user_id'], 'Logout', 'User logged out');
}

session_destroy();
header('Location: ../login.php');


if ($userCreated) {
    require_once '../includes/activity_logger.php';
    logActivity($pdo, $_SESSION['user_id'], 'Add User', "Created new user: {$newUser['username']}");
    echo json_encode(['success' => true, 'message' => 'User created successfully']);
}



if ($medicineDispensed) {
    require_once '../includes/activity_logger.php';
    $details = "Dispensed {$quantity} units of {$medicineName} to patient {$patientName}";
    logActivity($pdo, $_SESSION['user_id'], 'Dispense Medicine', $details);
    echo json_encode(['success' => true, 'message' => 'Medicine dispensed successfully']);
}



if ($inventoryAdded) {
    require_once '../includes/activity_logger.php';
    $details = "Added {$quantity} units of {$itemName} to inventory (Batch: {$batchNumber})";
    logActivity($pdo, $_SESSION['user_id'], 'Add Inventory', $details);
    echo json_encode(['success' => true, 'message' => 'Inventory added successfully']);
}

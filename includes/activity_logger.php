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


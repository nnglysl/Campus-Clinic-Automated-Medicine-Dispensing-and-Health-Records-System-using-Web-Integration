<?php
session_start();
require_once '../../db.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        switch($action) {
            case 'getNotifications':
                $stmt = $pdo->prepare("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 50");
                $stmt->execute();
                $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM notifications WHERE status = 'unread'");
                $stmt->execute();
                $unreadCount = $stmt->fetch()['count'];
                
                echo json_encode([
                    'success' => true, 
                    'notifications' => $notifications,
                    'unread_count' => $unreadCount
                ]);
                break;
                
            case 'markNotificationRead':
                $id = $_POST['id'] ?? null;
                if (!$id) {
                    echo json_encode(['success' => false, 'error' => 'Notification ID is required']);
                    break;
                }
                $stmt = $pdo->prepare("UPDATE notifications SET status = 'read' WHERE id = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
                break;
                
            case 'markAllNotificationsRead':
                $stmt = $pdo->prepare("UPDATE notifications SET status = 'read' WHERE status = 'unread'");
                $stmt->execute();
                echo json_encode(['success' => true]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
}
?>


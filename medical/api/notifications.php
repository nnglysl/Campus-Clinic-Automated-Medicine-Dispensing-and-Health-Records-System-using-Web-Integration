<?php
session_start();
require_once '../../db.php';

// Check if user is logged in and is medical staff
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['doctor', 'medical', 'nurse'])) {
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
                // Get notifications for medical staff (medical appointment bookings)
                $stmt = $pdo->prepare("
                    SELECT * FROM notifications 
                    WHERE type = 'appointment_booked' 
                    ORDER BY created_at DESC 
                    LIMIT 50
                ");
                $stmt->execute();
                $allNotifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Filter to only medical appointments
                $notifications = array_filter($allNotifications, function($notif) {
                    $data = json_decode($notif['data'] ?? '{}', true);
                    return isset($data['appointment_type']) && $data['appointment_type'] === 'medical';
                });
                $notifications = array_values($notifications); // Re-index array
                
                // Count unread medical appointments
                $stmt = $pdo->prepare("
                    SELECT * FROM notifications 
                    WHERE status = 'unread' 
                    AND type = 'appointment_booked'
                ");
                $stmt->execute();
                $allUnread = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $unreadCount = 0;
                foreach ($allUnread as $notif) {
                    $data = json_decode($notif['data'] ?? '{}', true);
                    if (isset($data['appointment_type']) && $data['appointment_type'] === 'medical') {
                        $unreadCount++;
                    }
                }
                
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
                // Get all unread medical appointment notifications
                $stmt = $pdo->prepare("
                    SELECT id FROM notifications 
                    WHERE status = 'unread' 
                    AND type = 'appointment_booked'
                ");
                $stmt->execute();
                $allUnread = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Filter and update only medical appointments
                $medicalIds = [];
                foreach ($allUnread as $notif) {
                    $data = json_decode($notif['data'] ?? '{}', true);
                    if (isset($data['appointment_type']) && $data['appointment_type'] === 'medical') {
                        $medicalIds[] = $notif['id'];
                    }
                }
                
                if (!empty($medicalIds)) {
                    $placeholders = implode(',', array_fill(0, count($medicalIds), '?'));
                    $stmt = $pdo->prepare("UPDATE notifications SET status = 'read' WHERE id IN ($placeholders)");
                    $stmt->execute($medicalIds);
                }
                
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


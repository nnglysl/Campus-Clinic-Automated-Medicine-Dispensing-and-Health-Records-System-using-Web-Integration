<?php
require_once '../config/database.php';
session_start();

// ---- HTTP headers for real-time streaming ----
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');
header('X-Accel-Buffering: no'); // disable nginx buffering for long-poll

set_time_limit(30);
ignore_user_abort(true);

try {
    $pdo = getDB();

    // ---- Input sanitization ----
    $lastId     = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    $timeout    = isset($_GET['timeout']) ? (int)$_GET['timeout'] : 25;
    $timeout    = ($timeout > 25) ? 25 : max($timeout, 5); // keep between 5–25s
    $department = isset($_GET['department']) ? trim($_GET['department']) : null;
    $userRole   = $_SESSION['role'] ?? 'student';

    $startTime = time();
    $checkInterval = 2; // seconds between DB checks

    // ---- Long-poll loop ----
    while ((time() - $startTime) < $timeout) {

        // Fetch new schedule notifications newer than last_id
        $stmt = $pdo->prepare("
            SELECT 
                id,
                notification_data,
                created_at,
                is_read
            FROM schedule_notifications
            WHERE id > ?
              AND (expires_at IS NULL OR expires_at > NOW())
            ORDER BY id ASC
            LIMIT 50
        ");
        $stmt->execute([$lastId]);
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if ($notifications && count($notifications) > 0) {
            $parsedNotifications = [];

            foreach ($notifications as $notification) {
                $data = json_decode($notification['notification_data'], true);

                // Filter notifications by department (if provided)
                if ($department && isset($data['department']) && $data['department'] !== $department) {
                    continue;
                }

                $parsedNotifications[] = [
                    'id'         => (int)$notification['id'],
                    'type'       => $data['type'] ?? 'unknown',
                    'action'     => $data['action'] ?? 'unknown',
                    'data'       => $data,
                    'created_at' => $notification['created_at'],
                    'is_read'    => (bool)$notification['is_read']
                ];
            }

            // If notifications matched our filter
            if (!empty($parsedNotifications)) {
                $newLastId = (int)$notifications[count($notifications) - 1]['id'];

                // Mark as read to prevent duplicates
                $update = $pdo->prepare("
                    UPDATE schedule_notifications 
                    SET is_read = 1 
                    WHERE id > ? AND id <= ?
                ");
                $update->execute([$lastId, $newLastId]);

                echo json_encode([
                    'success'       => true,
                    'has_changes'   => true,
                    'notifications' => $parsedNotifications,
                    'last_id'       => $newLastId,
                    'timestamp'     => date('Y-m-d H:i:s')
                ], JSON_PRETTY_PRINT);
                exit;
            }
        }

        // Wait a bit before next DB check
        sleep($checkInterval);
    }

    // ---- No changes found within timeout ----
    echo json_encode([
        'success'     => true,
        'has_changes' => false,
        'notifications' => [],
        'last_id'     => $lastId,
        'timestamp'   => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    error_log('Error in poll_schedule_changes.php: ' . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Server error: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ], JSON_PRETTY_PRINT);
}
?>

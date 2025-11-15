<?php
/**
 * Doctor Schedule API
 * Place in: api/doctor_schedule_api.php
 */

require_once __DIR__ . '/../config/database.php';
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Check authentication
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized');
    }

    $pdo = getDB();
    
    // Get doctor available days from schedule
    $stmt = $pdo->query("
        SELECT DISTINCT DAYOFWEEK(schedule_date) as day_of_week
        FROM doctor_schedules
        WHERE is_available = 1
        AND schedule_type = 'available'
        AND schedule_date >= CURDATE()
    ");
    
    $availableDays = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Convert MySQL day of week (1=Sunday, 7=Saturday) to JS (0=Sunday, 6=Saturday)
    $availableDays = array_map(function($day) {
        return ($day - 1) % 7;
    }, $availableDays);
    
    // If no schedules found, default to Monday-Friday
    if (empty($availableDays)) {
        $availableDays = [1, 2, 3, 4, 5]; // Mon-Fri
    }
    
    echo json_encode([
        'success' => true,
        'availableDays' => array_values(array_unique($availableDays))
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
<?php
/**
 * Get Appointments API - Simple Version
 * File: crud/get_appointments.php
 */

require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

try {
    $pdo = getDB();
    
    $action = $_GET['action'] ?? 'get_appointments';
    
    switch ($action) {
        case 'get_calendar':
            $month = $_GET['month'] ?? date('m');
            $year = $_GET['year'] ?? date('Y');
            $appointmentType = $_GET['appointment_type'] ?? null;
            
            // Get all appointments for the month (excluding deleted)
            $sql = "
                SELECT 
                    a.id,
                    a.patient_id,
                    a.appointment_date,
                    a.appointment_time,
                    a.appointment_type,
                    a.status,
                    a.notes,
                    a.created_at,
                    u.fname,
                    u.lname,
                    u.email,
                    u.phone
                FROM appointments a
                LEFT JOIN users u ON a.patient_id = u.id
                WHERE MONTH(a.appointment_date) = ?
                AND YEAR(a.appointment_date) = ?
                AND a.status != 'deleted'";

            $params = [$month, $year];

            if ($appointmentType) {
                $sql .= " AND a.appointment_type = ?";
                $params[] = $appointmentType;
            }

            $sql .= " ORDER BY a.appointment_date, a.appointment_time";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $appointments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode([
                'success' => true,
                'appointments' => $appointments,
                'schedules' => [], // Empty for now
                'unavailable' => [], // Empty for now
                'timestamp' => time()
            ]);
            break;
            
        case 'poll':
            // Simple polling - check for updates
            $lastUpdate = $_GET['last_update'] ?? 0;
            
            $stmt = $pdo->query("
                SELECT MAX(updated_at) as last_update
                FROM appointments
            ");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $currentUpdate = strtotime($result['last_update'] ?? 'now');
            
            echo json_encode([
                'success' => true,
                'has_updates' => $currentUpdate > $lastUpdate,
                'timestamp' => $currentUpdate
            ]);
            break;
            
        case 'update_status':
            // Only allow employees/staff to update
            if (!in_array($_SESSION['role'] ?? '', ['employee', 'admin', 'dentist', 'staff', 'doctor', 'nurse'])) {
                throw new Exception('Access denied');
            }
            
            // Get POST data
            parse_str(file_get_contents('php://input'), $postData);
            
            $id = $postData['id'] ?? null;
            $status = $postData['status'] ?? null;
            
            if (!$id || !$status) {
                throw new Exception('Appointment ID and status are required');
            }
            
            $validStatuses = ['scheduled', 'confirmed', 'completed', 'cancelled'];
            if (!in_array($status, $validStatuses)) {
                throw new Exception('Invalid status');
            }
            
            // Get appointment details before updating for logging
            $stmt = $pdo->prepare("
                SELECT 
                    a.*,
                    u.fname,
                    u.lname
                FROM appointments a
                JOIN users u ON a.patient_id = u.id
                WHERE a.id = ?
            ");
            $stmt->execute([$id]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$appointment) {
                throw new Exception('Appointment not found');
            }
            
            $oldStatus = $appointment['status'] ?? 'scheduled';
            
            $stmt = $pdo->prepare("
                UPDATE appointments
                SET status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$status, $id]);
            
            // Log appointment status change
            try {
                require_once(__DIR__ . '/../includes/appointment_logger.php');
                $userId = $_SESSION['user_id'] ?? null;
                if ($userId) {
                    logAppointmentStatusChange($pdo, $id, $userId, $oldStatus, $status, $appointment);
                }
            } catch (Exception $e) {
                error_log("Failed to log appointment status change: " . $e->getMessage());
                // Continue even if logging fails
            }
            
            // Create notification if appointment is cancelled
            if ($status === 'cancelled') {
                try {
                    $stmt = $pdo->prepare("
                        SELECT a.*, u.fname, u.lname
                        FROM appointments a
                        JOIN users u ON a.patient_id = u.id
                        WHERE a.id = ?
                    ");
                    $stmt->execute([$id]);
                    $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($appointment) {
                        $patientName = trim($appointment['fname'] . ' ' . $appointment['lname']);
                        $appointmentDate = date('F j, Y', strtotime($appointment['appointment_date']));
                        $appointmentTime = date('g:i A', strtotime($appointment['appointment_time']));
                        $appointmentType = $appointment['appointment_type'];
                        
                        $message = "Appointment cancelled: {$appointmentType} appointment on {$appointmentDate} at {$appointmentTime}";
                        
                        $data = json_encode([
                            'appointment_id' => $id,
                            'appointment_type' => $appointmentType,
                            'patient_name' => $patientName,
                            'appointment_date' => $appointment['appointment_date'],
                            'appointment_time' => $appointment['appointment_time'],
                            'timestamp' => date('Y-m-d H:i:s')
                        ]);
                        
                        $stmt = $pdo->prepare("
                            INSERT INTO notifications (type, message, data, status, created_at) 
                            VALUES ('appointment_cancelled', ?, ?, 'unread', NOW())
                        ");
                        $stmt->execute([$message, $data]);
                    }
                } catch (Exception $e) {
                    error_log("Failed to create cancellation notification: " . $e->getMessage());
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'Appointment status updated successfully',
                'timestamp' => time()
            ]);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred',
        'details' => $e->getMessage()
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
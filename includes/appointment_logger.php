<?php
/**
 * Appointment History Logger
 * Logs appointment changes to appointment_history table and user_activity_logs
 */

/**
 * Log appointment history to appointment_history table
 * 
 * @param PDO $pdo Database connection
 * @param int $appointmentId Appointment ID
 * @param string $action Action type: 'created', 'updated', 'cancelled', 'completed', 'rescheduled'
 * @param int|null $changedBy User ID who made the change
 * @param string|null $oldDate Previous appointment date (for rescheduled)
 * @param string|null $oldTime Previous appointment time (for rescheduled)
 * @param string|null $newDate New appointment date (for rescheduled)
 * @param string|null $newTime New appointment time (for rescheduled)
 * @param string|null $notes Additional notes
 * @return bool Success status
 */
function logAppointmentHistory($pdo, $appointmentId, $action, $changedBy = null, $oldDate = null, $oldTime = null, $newDate = null, $newTime = null, $notes = null) {
    try {
        // Validate changed_by if provided - ensure user exists
        if ($changedBy !== null && $changedBy !== '') {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $checkStmt->execute([$changedBy]);
            if (!$checkStmt->fetch()) {
                error_log("Appointment history logging: Invalid user ID $changedBy, setting to NULL");
                $changedBy = null; // Set to NULL if user doesn't exist
            }
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO appointment_history 
            (appointment_id, action, old_date, old_time, new_date, new_time, changed_by, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->execute([
            $appointmentId,
            $action,
            $oldDate,
            $oldTime,
            $newDate,
            $newTime,
            $changedBy, // Will be NULL if invalid or not provided
            $notes
        ]);
        
        return true;
    } catch (PDOException $e) {
        error_log("Appointment history logging error: " . $e->getMessage());
        error_log("Attempted values - appointment_id: $appointmentId, action: $action, changed_by: " . ($changedBy ?? 'NULL'));
        return false;
    }
}

/**
 * Log appointment activity to user_activity_logs
 * 
 * @param PDO $pdo Database connection
 * @param int $userId User ID who performed the action
 * @param string $action Action type
 * @param string $details Action details
 * @return bool Success status
 */
function logAppointmentActivity($pdo, $userId, $action, $details) {
    try {
        require_once(__DIR__ . '/activity_logger.php');
        return logActivity($pdo, $userId, $action, $details);
    } catch (Exception $e) {
        error_log("Appointment activity logging error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log appointment creation with full details
 * 
 * @param PDO $pdo Database connection
 * @param int $appointmentId Appointment ID
 * @param int $userId User ID who created the appointment
 * @param array $appointmentData Appointment data array
 * @return void
 */
function logAppointmentCreated($pdo, $appointmentId, $userId, $appointmentData = []) {
    // Get appointment details if not provided
    if (empty($appointmentData)) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    a.*,
                    u.fname,
                    u.lname
                FROM appointments a
                JOIN users u ON a.patient_id = u.id
                WHERE a.id = ?
            ");
            $stmt->execute([$appointmentId]);
            $appointmentData = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching appointment for logging: " . $e->getMessage());
            return;
        }
    }
    
    if (!$appointmentData) {
        return;
    }
    
    $patientName = trim(($appointmentData['fname'] ?? '') . ' ' . ($appointmentData['lname'] ?? ''));
    $appointmentDate = $appointmentData['appointment_date'] ?? '';
    $appointmentTime = $appointmentData['appointment_time'] ?? '';
    $appointmentType = $appointmentData['appointment_type'] ?? '';
    
    // Log to appointment_history
    logAppointmentHistory(
        $pdo,
        $appointmentId,
        'created',
        $userId,
        null,
        null,
        $appointmentDate,
        $appointmentTime,
        "Appointment created: {$appointmentType} appointment for {$patientName}"
    );
    
    // Log to user_activity_logs
    $details = "Created {$appointmentType} appointment for {$patientName} on " . 
               date('F j, Y', strtotime($appointmentDate)) . " at " . 
               date('g:i A', strtotime($appointmentTime));
    logAppointmentActivity($pdo, $userId, 'Create Appointment', $details);
}

/**
 * Log appointment status change
 * 
 * @param PDO $pdo Database connection
 * @param int $appointmentId Appointment ID
 * @param int $userId User ID who changed the status
 * @param string $oldStatus Previous status
 * @param string $newStatus New status
 * @param array $appointmentData Appointment data array (optional)
 * @return void
 */
function logAppointmentStatusChange($pdo, $appointmentId, $userId, $oldStatus, $newStatus, $appointmentData = []) {
    // Get appointment details if not provided
    if (empty($appointmentData)) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    a.*,
                    u.fname,
                    u.lname
                FROM appointments a
                JOIN users u ON a.patient_id = u.id
                WHERE a.id = ?
            ");
            $stmt->execute([$appointmentId]);
            $appointmentData = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching appointment for logging: " . $e->getMessage());
            return;
        }
    }
    
    if (!$appointmentData) {
        return;
    }
    
    $patientName = trim(($appointmentData['fname'] ?? '') . ' ' . ($appointmentData['lname'] ?? ''));
    $appointmentDate = $appointmentData['appointment_date'] ?? '';
    $appointmentTime = $appointmentData['appointment_time'] ?? '';
    $appointmentType = $appointmentData['appointment_type'] ?? '';
    
    // Determine action type for appointment_history
    $action = 'updated';
    if ($newStatus === 'cancelled') {
        $action = 'cancelled';
    } elseif ($newStatus === 'completed') {
        $action = 'completed';
    }
    
    // Log to appointment_history
    logAppointmentHistory(
        $pdo,
        $appointmentId,
        $action,
        $userId,
        $appointmentDate,
        $appointmentTime,
        $appointmentDate,
        $appointmentTime,
        "Status changed from '{$oldStatus}' to '{$newStatus}'"
    );
    
    // Log to user_activity_logs
    $statusText = ucfirst($newStatus);
    $actionText = "{$statusText} Appointment";
    $details = "Changed appointment status for {$patientName} ({$appointmentType}) on " . 
               date('F j, Y', strtotime($appointmentDate)) . " at " . 
               date('g:i A', strtotime($appointmentTime)) . " from '{$oldStatus}' to '{$newStatus}'";
    logAppointmentActivity($pdo, $userId, $actionText, $details);
}

/**
 * Log appointment cancellation
 * 
 * @param PDO $pdo Database connection
 * @param int $appointmentId Appointment ID
 * @param int $userId User ID who cancelled the appointment
 * @param array $appointmentData Appointment data array (optional)
 * @return void
 */
function logAppointmentCancelled($pdo, $appointmentId, $userId, $appointmentData = []) {
    // Get appointment details if not provided
    if (empty($appointmentData)) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    a.*,
                    u.fname,
                    u.lname
                FROM appointments a
                JOIN users u ON a.patient_id = u.id
                WHERE a.id = ?
            ");
            $stmt->execute([$appointmentId]);
            $appointmentData = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching appointment for logging: " . $e->getMessage());
            return;
        }
    }
    
    if (!$appointmentData) {
        return;
    }
    
    $oldStatus = $appointmentData['status'] ?? 'scheduled';
    logAppointmentStatusChange($pdo, $appointmentId, $userId, $oldStatus, 'cancelled', $appointmentData);
}

/**
 * Log appointment completion
 * 
 * @param PDO $pdo Database connection
 * @param int $appointmentId Appointment ID
 * @param int $userId User ID who completed the appointment
 * @param array $appointmentData Appointment data array (optional)
 * @return void
 */
function logAppointmentCompleted($pdo, $appointmentId, $userId, $appointmentData = []) {
    // Get appointment details if not provided
    if (empty($appointmentData)) {
        try {
            $stmt = $pdo->prepare("
                SELECT 
                    a.*,
                    u.fname,
                    u.lname
                FROM appointments a
                JOIN users u ON a.patient_id = u.id
                WHERE a.id = ?
            ");
            $stmt->execute([$appointmentId]);
            $appointmentData = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching appointment for logging: " . $e->getMessage());
            return;
        }
    }
    
    if (!$appointmentData) {
        return;
    }
    
    $oldStatus = $appointmentData['status'] ?? 'scheduled';
    logAppointmentStatusChange($pdo, $appointmentId, $userId, $oldStatus, 'completed', $appointmentData);
}


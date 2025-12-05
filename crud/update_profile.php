<?php
session_start();
require_once(__DIR__ . '/../db.php');
require_once(__DIR__ . '/../includes/activity_logger.php');

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid data received');
    }

    // SECURITY: Explicitly ignore role data if sent - profile updates should not change role
    if (isset($data['role'])) {
        error_log("WARNING: Role field detected in profile update request for user_id: $userId. Ignoring role data.");
        unset($data['role']); // Remove role from data to prevent accidental updates
    }

    // Validate that user is updating their own profile
    if ($data['userId'] != $userId) {
        throw new Exception('Unauthorized: Cannot update another user\'s profile');
    }

    // Validate required fields
    if (empty($data['firstName']) || empty($data['lastName']) || empty($data['email'])) {
        throw new Exception('First name, last name, and email are required');
    }

    // Validate email format
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Check if email is already taken by another user
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $stmt->execute([$data['email'], $userId]);
    if ($stmt->fetch()) {
        throw new Exception('Email address is already in use');
    }

    // Check if user is an employee (has record in employees table)
    $stmt = $pdo->prepare("SELECT id, email FROM employees WHERE user_id = ?");
    $stmt->execute([$userId]);
    $employee = $stmt->fetch();

    // Start transaction to ensure both tables are updated
    $pdo->beginTransaction();
    
    try {
    // SECURITY: Explicitly exclude role from profile updates
    // Role should only be changed by admin through proper admin interface
    // Update user profile - DO NOT update role, password, or other sensitive fields
    $stmt = $pdo->prepare("
        UPDATE users 
        SET fname = ?, 
            mname = ?, 
            lname = ?, 
            email = ?, 
            phone = ?, 
            address = ?, 
            date_of_birth = ?, 
            gender = ?, 
            blood_type = ?,
            updated_at = NOW()
        WHERE id = ?
    ");

    $result = $stmt->execute([
        $data['firstName'],
        $data['middleName'],
        $data['lastName'],
        $data['email'],
        $data['phone'],
        $data['address'],
        $data['dateOfBirth'],
        $data['gender'],
        $data['bloodType'],
        $userId
    ]);

        if (!$result) {
            throw new Exception('Failed to update user profile');
        }

        // If user is an employee, also update the employees table
        if ($employee) {
            // Check if email is already taken in employees table (excluding current employee)
            if ($data['email'] !== $employee['email']) {
                $checkStmt = $pdo->prepare("SELECT id FROM employees WHERE email = ? AND id != ?");
                $checkStmt->execute([$data['email'], $employee['id']]);
                if ($checkStmt->fetch()) {
                    throw new Exception('Email address is already in use by another employee');
                }
            }

            // Calculate age if date of birth is provided
            $age = null;
            if (!empty($data['dateOfBirth'])) {
                $birthDate = new DateTime($data['dateOfBirth']);
                $today = new DateTime();
                $age = $today->diff($birthDate)->y;
            }

            // Update employees table to sync with users table
            $stmt = $pdo->prepare("
                UPDATE employees 
                SET first_name = ?, 
                    middle_name = ?, 
                    last_name = ?, 
                    email = ?, 
                    phone = ?, 
                    address = ?, 
                    birth_date = ?, 
                    age = ?,
                    gender = ?
                WHERE user_id = ?
            ");

            $result = $stmt->execute([
                $data['firstName'],
                $data['middleName'],
                $data['lastName'],
                $data['email'],
                $data['phone'],
                $data['address'],
                $data['dateOfBirth'],
                $age,
                $data['gender'],
                $userId
            ]);

            if (!$result) {
                throw new Exception('Failed to update employee record');
            }
        }

        // Commit transaction
        $pdo->commit();

        // Log profile update activity
        logActivity($pdo, $userId, 'Update Profile', 'User updated their profile information');

        // Update session variables
        $_SESSION['fname'] = $data['firstName'];
        $_SESSION['lname'] = $data['lastName'];
        $_SESSION['email'] = $data['email'];

        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log('Update Profile Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
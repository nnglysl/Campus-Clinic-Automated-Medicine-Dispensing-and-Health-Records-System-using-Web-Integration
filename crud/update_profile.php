<?php
session_start();
require_once(__DIR__ . '/../db.php');

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

    // Update user profile
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

    if ($result) {
        // Update session variables
        $_SESSION['fname'] = $data['firstName'];
        $_SESSION['lname'] = $data['lastName'];
        $_SESSION['email'] = $data['email'];

        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully'
        ]);
    } else {
        throw new Exception('Failed to update profile');
    }

} catch (Exception $e) {
    error_log('Update Profile Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
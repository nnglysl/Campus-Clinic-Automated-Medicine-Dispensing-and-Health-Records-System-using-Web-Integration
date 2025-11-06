<?php
// Database Configuration - Matches your existing db.php
$host = 'localhost';
$dbname = 'clinic_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Helper function to get database connection
function getDB() {
    global $pdo;
    return $pdo;
}

// Sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Check if user is logged in
function checkAuth() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        header("Location: /login.php");
        exit();
    }
    return $_SESSION['user_id'];
}

// Check if user is admin or doctor
function checkAdminOrDoctor() {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../login.php");
        exit();
    }

    return [
        'id' => $_SESSION['user_id'],
        'fname' => $_SESSION['fname'] ?? '',
        'lname' => $_SESSION['lname'] ?? '',
        'role' => $_SESSION['role'] ?? ''
    ];
}

?>
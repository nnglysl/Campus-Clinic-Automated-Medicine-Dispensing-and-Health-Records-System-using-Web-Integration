<?php
<<<<<<< HEAD
=======
// Database Configuration - Matches your existing db.php
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
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

<<<<<<< HEAD
=======
// Helper function to get database connection
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
function getDB() {
    global $pdo;
    return $pdo;
}

<<<<<<< HEAD
=======
// Sanitize input
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

<<<<<<< HEAD
=======
// Check if user is logged in
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
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

<<<<<<< HEAD
=======
// Check if user is admin or doctor
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
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
<?php
session_start();
require_once '../config/database.php';
require_once '../includes/activity_logger.php';

if (isset($_SESSION['user_id'])) {
    $pdo = getDB();
    logActivity($pdo, $_SESSION['user_id'], 'Logout', 'User logged out');
}

$_SESSION = array();

if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

session_destroy();

header("Location: login.php");
exit();
?>
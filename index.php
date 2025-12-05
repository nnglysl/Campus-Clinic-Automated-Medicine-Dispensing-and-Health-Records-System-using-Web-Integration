<?php 
session_start();

<<<<<<< HEAD
=======
// If user is already logged in, redirect to dashboard
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BSU Clinic System - Login</title>
    <link href="auth/login.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php include 'auth/sysheader.php'; ?>

    <?php
header('Location: auth/login.php');
exit();
?>
</body>
</html>

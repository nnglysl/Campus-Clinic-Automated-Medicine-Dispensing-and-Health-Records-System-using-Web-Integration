<?php
include('../db.php');
session_start();

$error_message = '';
$success_message = '';

// Handle signup form submission
if (isset($_POST['signup'])) {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Basic validation
    if (empty($fname) || empty($lname) || empty($email) || empty($phone) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email address.';
    } elseif (!preg_match('/^(09|\+639)\d{9}$/', $phone)) {
        $error_message = 'Invalid phone number format. Use 09XXXXXXXXX or +639XXXXXXXXX';
    } elseif (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        // Check if email or phone already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? OR phone = ?");
        $stmt->execute([$email, $phone]);
        
        if ($stmt->fetch()) {
            $error_message = 'Email or phone number already registered.';
        } else {
            // Create user account
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (fname, lname, email, phone, password) VALUES (?, ?, ?, ?, ?)");
            
            if ($stmt->execute([$fname, $lname, $email, $phone, $hashed_password])) {
                $success_message = 'Account created successfully! Redirecting to login...';
                header('refresh:2;url=login.php');
            } else {
                $error_message = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>BSU Clinic System - Sign Up</title>
    <?php include('header.php'); ?>
</head>

<body>
    <?php include('sysheader.php'); ?>

    <div class="container">
        <div class="login-card">
            <h2 class="login-title">Sign Up</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <!-- Regular Signup Form -->
            <form method="POST">
                <div class="form-group">
                    <input type="text" name="fname" class="form-input" placeholder="First Name" 
                           value="<?= isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <input type="text" name="lname" class="form-input" placeholder="Last Name" 
                           value="<?= isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <input type="email" name="email" class="form-input" placeholder="Email" 
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <input type="tel" name="phone" class="form-input" placeholder="Phone (09XXXXXXXXX)" 
                           value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" required>
                    <span class="input-icon"><i class="bi bi-phone-fill"></i></span>
                </div>
               
                <div class="form-group">
                    <input type="password" name="password" class="form-input" placeholder="Password" required>
                    <span class="input-icon"><i class="bi bi-lock-fill"></i></span>
                </div>

                <div class="form-group">
                    <input type="password" name="confirm_password" class="form-input" placeholder="Confirm Password" required>
                    <span class="input-icon"><i class="bi bi-lock-fill"></i></span>
                </div>

                <button type="submit" name="signup" class="signup-button">Sign Up</button>

                <div class="login-link">
                    Already have an account? <a href="login.php">Log in</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

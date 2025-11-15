<?php
include('../db.php');
session_start();

// Hardcoded admin email - DO NOT CHANGE
define('ADMIN_EMAIL', 'superadmin@gmail.com');

$error_message = '';
$success_message = '';

// Handle Email/Password Login
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'] ?? '';

    // Check users table first
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        // Password is correct, now determine the role and redirect
        
        // PRIORITY 1: Check if this is the hardcoded admin
        $isHardcodedAdmin = (strtolower($email) === strtolower(ADMIN_EMAIL));
        
        if ($isHardcodedAdmin) {
            // HARDCODED ADMIN - Always redirect to admin dashboard
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['lname'] = $user['lname'] ?? '';
            $_SESSION['role'] = 'admin';
            $_SESSION['username'] = trim($user['fname'] . ' ' . ($user['lname'] ?? ''));
            
            // Update role in database to ensure consistency
            $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            header('Location: ../admin/adminDashboard.php');
            exit();
        }
        
        // PRIORITY 2: Check if user is an employee (has record in employees table)
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE (email = ? OR user_id = ?) AND status = 'active'");
        $stmt->execute([$email, $user['id']]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($employee) {
            // USER IS AN EMPLOYEE - Use role from employees table as source of truth
            
            // Sync the role between users and employees tables
            if ($user['role'] !== $employee['role']) {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$employee['role'], $user['id']]);
            }
            
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['lname'] = $user['lname'] ?? '';
            $_SESSION['role'] = $employee['role']; // Use role from employees table
            $_SESSION['username'] = trim($user['fname'] . ' ' . ($user['lname'] ?? ''));
            
            // Redirect based on employee role
            switch ($employee['role']) {
                case 'admin':
                    // Regular admin (not hardcoded)
                    header('Location: ../admin/adminDashboard.php');
                    break;
                    
                case 'doctor':
                    header('Location: ../medical/medical_dashboard.php');
                    break;
                    
                case 'dentist':
                    header('Location: ../dental/dental_dashboard.php');
                    break;
                    
                case 'nurse':
                    header('Location: ../nurse/nurse_dashboard.php');
                    break;
                    
                case 'staff':
                    header('Location: ../staff/staff_dashboard.php');
                    break;
                    
                default:
                    // Fallback for any other employee roles
                    header('Location: ../employee/dashboard.php');
                    break;
            }
            exit();
        }
        
        // PRIORITY 3: Check if user has an employee-type role in users table (but no employee record)
        // This handles edge cases where someone was assigned a role but employee record wasn't created
        if (in_array($user['role'], ['admin', 'doctor', 'dentist', 'nurse', 'staff', 'employee'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['lname'] = $user['lname'] ?? '';
            $_SESSION['role'] = $user['role'];
            $_SESSION['username'] = trim($user['fname'] . ' ' . ($user['lname'] ?? ''));
            
            // Redirect based on user role
            switch ($user['role']) {
                case 'admin':
                    header('Location: ../admin/adminDashboard.php');
                    break;
                    
                case 'doctor':
                    header('Location: ../medical/medical_dashboard.php');
                    break;
                    
                case 'dentist':
                    header('Location: ../dental/dental_dashboard.php');
                    break;
                    
                case 'nurse':
                    header('Location: ../nurse/nurse_dashboard.php');
                    break;
                    
                case 'staff':
                    header('Location: ../staff/staff_dashboard.php');
                    break;
                    
                default:
                    header('Location: ../employee/dashboard.php');
                    break;
            }
            exit();
        }
        
        // PRIORITY 4: USER IS A PATIENT (default case)
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['fname'] = $user['fname'];
        $_SESSION['lname'] = $user['lname'] ?? '';
        $_SESSION['role'] = 'patient'; // Explicitly set as patient
        $_SESSION['username'] = trim($user['fname'] . ' ' . ($user['lname'] ?? ''));
        
        // Update role in database if not set
        if (empty($user['role']) || $user['role'] === 'patient') {
            $stmt = $pdo->prepare("UPDATE users SET role = 'patient' WHERE id = ?");
            $stmt->execute([$user['id']]);
        }
        
        header('Location: ../student/student_dashboard.php');
        exit();
        
    } else {
        $error_message = 'Invalid email or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>BSU Clinic System - Login</title>
    <?php include('header.php'); ?>
    
    <style>
        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            margin: 25px 0 20px 0;
            color: #666;
            font-size: 14px;
        }
        
        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid #ddd;
        }
        
        .divider span {
            padding: 0 15px;
        }
        
        .btn-phone-login {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            background: none;
            border: none;
            padding: 0;
            margin: 0 auto 20px auto;
            cursor: pointer;
            transition: transform 0.2s ease;
            display: block;
            text-align: center;
        }

        .btn-phone-login img {
            width: 40px;
            height: 40px;
            object-fit: contain;
            filter: invert(40%) sepia(0%) saturate(0%) brightness(80%) contrast(90%);
            transition: filter 0.3s ease, transform 0.2s ease;
        }

        .btn-phone-login:hover {
            transform: scale(1.1);
        }
    </style>
</head>

<body>
    <?php include('sysheader.php'); ?>

    <div class="container">
        <div class="login-card">
            <h2 class="login-title">Login</h2>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?= $success_message ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <input type="email" name="email" class="form-input" placeholder="Email" required>
                    <span class="input-icon"><i class="bi bi-person-fill"></i></span>
                </div>

                <div class="form-group">
                    <input type="password" name="password" class="form-input" placeholder="Password" required>
                    <span class="input-icon"><i class="bi bi-lock-fill"></i></span>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <span>Remember me</span>
                    </label>
                    <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
                </div>

                <button type="submit" name="login" class="login-button">Login</button>
            </form>

            <div class="divider">
                <span>or login with</span>
            </div>

            <a href="./phone_login.php" class="btn-phone-login">
                <img src="../img/phone-circle.png" alt="Phone Icon">
            </a>

            <div class="signup-link">
                Don't have an account? <a href="signup.php">Sign up</a>
            </div>
        </div>
    </div>
</body>
</html>
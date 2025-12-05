<?php
include('../db.php');
require_once('../includes/activity_logger.php');
session_start();

define('ADMIN_EMAIL', 'wanaconnect20@gmail.com');

$error_message = '';
$success_message = '';

if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {

        $isHardcodedAdmin = (strtolower($email) === strtolower(ADMIN_EMAIL));

        if ($isHardcodedAdmin) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['lname'] = $user['lname'] ?? '';
            $_SESSION['role'] = 'admin';
            $_SESSION['username'] = trim($user['fname'] . ' ' . ($user['lname'] ?? ''));

            $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $stmt->execute([$user['id']]);

            logActivity($pdo, $user['id'], 'Login', 'Admin logged in successfully');

            header('Location: ../admin/adminDashboard.php');
            exit();
        }

        if (isset($_POST['remember'])) {
            setcookie("saved_email", $email, time() + (30 * 24 * 60 * 60), "/");
        } else {
            setcookie("saved_email", "", time() - 3600, "/");
        }

        $stmt = $pdo->prepare("SELECT * FROM employees WHERE (email = ? OR user_id = ?) AND status = 'active'");
        $stmt->execute([$email, $user['id']]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);

        $isEmployeeRole = in_array($user['role'] ?? '', ['admin', 'doctor', 'dentist', 'nurse', 'staff', 'employee']);
        
        if ($employee && $isEmployeeRole) {
            if ($user['role'] !== $employee['role']) {
                $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
                $stmt->execute([$employee['role'], $user['id']]);
            }

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['lname'] = $user['lname'] ?? '';
            $_SESSION['role'] = $employee['role'];
            $_SESSION['username'] = trim($user['fname'] . ' ' . ($user['lname'] ?? ''));

            $roleName = ucfirst($employee['role']);
            logActivity($pdo, $user['id'], 'Login', "$roleName logged in successfully");

            switch ($employee['role']) {
                case 'admin': header('Location: ../admin/adminDashboard.php'); break;
                case 'doctor': header('Location: ../medical/medical_dashboard.php'); break;
                case 'dentist': header('Location: ../dental/dental_dashboard.php'); break;
                case 'nurse': header('Location: ../nurse/nurse_dashboard.php'); break;
            }
            exit();
        }
        
        if ($employee && !$isEmployeeRole) {
            $stmt = $pdo->prepare("UPDATE employees SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$employee['id']]);
        }

        $userRole = $user['role'] ?? '';
        if (in_array($userRole, ['admin', 'doctor', 'dentist', 'nurse', 'staff', 'employee'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['lname'] = $user['lname'] ?? '';
            $_SESSION['role'] = $userRole;
            $_SESSION['username'] = trim($user['fname'] . ' ' . ($user['lname'] ?? ''));

            $roleName = ucfirst($userRole);
            logActivity($pdo, $user['id'], 'Login', "$roleName logged in successfully");

            switch ($userRole) {
                case 'admin': header('Location: ../admin/adminDashboard.php'); break;
                case 'doctor': header('Location: ../medical/medical_dashboard.php'); break;
                case 'dentist': header('Location: ../dental/dental_dashboard.php'); break;
                case 'nurse': header('Location: ../nurse/nurse_dashboard.php'); break;
                default: header('Location: ../student/student_dashboard.php'); break;
            }
            exit();
        }

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['email'] = $user['email'];
        $_SESSION['fname'] = $user['fname'];
        $_SESSION['lname'] = $user['lname'] ?? '';
        $_SESSION['role'] = 'patient';
        $_SESSION['username'] = trim($user['fname'] . ' ' . ($user['lname'] ?? ''));

        logActivity($pdo, $user['id'], 'Login', 'Patient logged in successfully');

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
    <link href="responsive.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
<style>
body {
    background: #f6f6f6;
}

.login-card {
    width: 380px;
    margin: 70px auto;
    padding: 35px 30px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 4px 20px rgba(0,0,0,0.08);
}

.login-title {
    text-align: center;
    font-size: 28px;
    margin-bottom: 25px;
    font-weight: 700;
    color: #333;
}

.form-input {
    width: 100%;
    padding: 12px 15px;
    border-radius: 8px;
    border: 1px solid #ccc;
    margin-bottom: 18px;
    font-size: 15px;
}

.form-input:focus {
    border-color: #800000;
    outline: none;
}

.login-options {
    display: flex;
    justify-content: space-between;
    margin: 5px 0 20px 0;
}

.remember-me {
    font-size: 14px;
    display: flex;
    gap: 5px;
}

.forgot-password {
    font-size: 14px;
    color: #007bff;
    text-decoration: none;
}

.forgot-password:hover {
    text-decoration: underline;
}

.login-button {
    width: 100%;
    background: #800000;
    border: none;
    padding: 12px;
    border-radius: 8px;
    color: #fff;
    font-size: 17px;
    font-weight: 600;
    cursor: pointer;
}

.login-button:hover {
    background: #a00000;
}

.signup-link {
    text-align: center;
    margin-top: 5px;
    font-size: 14px;
}

.signup-link a {
    color: #007bff;
    text-decoration: none;
}

.signup-link a:hover {
    text-decoration: underline;
}

.password-wrapper {
    position: relative;
}
.password-toggle-icon {
    position: absolute;
    right: 13px;
    top: 35%;
    transform: translateY(-50%);
    font-size: 20px;
    cursor: pointer;
    color: #666;
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
        
        <?php if (isset($_SESSION['signup_success'])): ?>
            <div class="alert alert-success"><?= htmlspecialchars($_SESSION['signup_success']) ?></div>
            <?php unset($_SESSION['signup_success']); ?>
        <?php endif; ?>

        <form method="POST">
            <input type="email" name="email" class="form-input"
                   placeholder="Email"
                   value="<?= isset($_COOKIE['saved_email']) ? htmlspecialchars($_COOKIE['saved_email']) : '' ?>"
                   required>

            <div class="password-wrapper">
                <input type="password" name="password" id="passwordInput" class="form-input" placeholder="Password" required>
                <i class="bi bi-eye-fill password-toggle-icon" id="togglePassword" onclick="togglePasswordVisibility()"></i>
            </div>

            <div class="login-options">
                <label class="remember-me">
                    <input type="checkbox" name="remember" <?= isset($_COOKIE['saved_email']) ? 'checked' : '' ?>>
                    Remember me
                </label>

                <a href="forgot-password.php" class="forgot-password">Forgot Password?</a>
            </div>

            <button type="submit" name="login" class="login-button">Login</button>
        </form>

        <div class="signup-link">
            Don't have an account? <a href="signup.php">Sign up</a>
        </div>

    </div>
</div>

<script>
function togglePasswordVisibility() {
    const input = document.getElementById("passwordInput");
    const icon = document.getElementById("togglePassword");

    if (input.type === "password") {
        input.type = "text";
        icon.classList.replace("bi-eye-fill", "bi-eye-slash-fill");
    } else {
        input.type = "password";
        icon.classList.replace("bi-eye-slash-fill", "bi-eye-fill");
    }
}
</script>

</body>
</html>

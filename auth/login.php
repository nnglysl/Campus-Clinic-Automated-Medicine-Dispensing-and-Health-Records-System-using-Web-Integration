<?php
include('../db.php');
<<<<<<< HEAD
require_once('../includes/activity_logger.php');
session_start();

define('ADMIN_EMAIL', 'wanaconnect20@gmail.com');

$error_message = '';
$success_message = '';

=======
include('../config/sms.php');
session_start();

// Hardcoded admin email
define('ADMIN_EMAIL', 'superadmin@g.batstate-u.edu.ph');

$error_message = '';
$success_message = '';
$show_otp_form = false;

// Handle OTP verification
if (isset($_POST['verify_otp'])) {
    $input_otp = trim($_POST['otp']);

    if (!isset($_SESSION['otp_user_id'])) {
        $error_message = 'Session expired. Please login again.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM otp_verifications WHERE user_id = ? AND otp_code = ? AND is_verified = 0 ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$_SESSION['otp_user_id'], $input_otp]);
        $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($otp_record) {
            // Check if OTP expired (10 minutes)
            $created_time = strtotime($otp_record['created_at']);
            if (time() - $created_time > 600) {
                $error_message = 'OTP expired. Please request a new one.';
                $show_otp_form = true;
            } else {
                // Mark OTP as verified
                $stmt = $pdo->prepare("UPDATE otp_verifications SET is_verified = 1 WHERE id = ?");
                $stmt->execute([$otp_record['id']]);

                // Get user data
                $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->execute([$_SESSION['otp_user_id']]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                // Check if user is hardcoded admin
                $isAdmin = (strtolower($user['email']) === strtolower(ADMIN_EMAIL));

                // Override role if admin email matches
                if ($isAdmin) {
                    $user['role'] = 'admin';
                    $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
                    $stmt->execute([$user['id']]);
                }

                // Login user - set all session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['fname'] = $user['fname'];
                $_SESSION['lname'] = $user['lname'] ?? '';
                $_SESSION['role'] = $user['role'] ?? 'patient';
                $_SESSION['username'] = trim($user['fname'] . ' ' . ($user['lname'] ?? ''));

                // Clear temporary OTP session
                unset($_SESSION['otp_user_id']);

                // Redirect based on role
                switch ($_SESSION['role']) {
                    case 'admin':
                        header('Location: ../admin/adminDashboard.php');
                        break;
                    case 'doctor':
                        header('Location: ../employee/dashboard.php');
                        break;
                    case 'patient':
                        header('Location: ../student/student_dashboard.php');
                        break;
                    default:
                        header('Location: ../dashboard.php');
                        break;
                }
                exit();
            }
        } else {
            $error_message = 'Invalid OTP. Please try again.';
            $show_otp_form = true;
        }
    }
}

// Handle initial login
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
if (isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'] ?? '';

<<<<<<< HEAD
=======
    // FIRST: Check if email belongs to an active employee
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employee && password_verify($password, $employee['password'])) {
        // EMPLOYEE LOGIN - No OTP required
        
        // Check if employee exists in users table
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$existing_user) {
            // Create user entry for employee
            $stmt = $pdo->prepare("INSERT INTO users (fname, lname, email, phone, password, role, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $employee['first_name'], 
                $employee['last_name'], 
                $employee['email'], 
                $employee['phone'], 
                $employee['password'], 
                $employee['role']
            ]);
            $user_id = $pdo->lastInsertId();
        } else {
            $user_id = $existing_user['id'];
            // Update role in users table to match employee role
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$employee['role'], $user_id]);
        }
        
        // Set session variables
        $_SESSION['user_id'] = $user_id;
        $_SESSION['email'] = $employee['email'];
        $_SESSION['fname'] = $employee['first_name'];
        $_SESSION['lname'] = $employee['last_name'] ?? '';
        $_SESSION['role'] = $employee['role'];
        $_SESSION['username'] = trim($employee['first_name'] . ' ' . ($employee['last_name'] ?? ''));

        // Redirect based on role
        if ($employee['role'] === 'admin') {
            header('Location: ../admin/adminDashboard.php');
        } else {
            header('Location: ../employee/dashboard.php');
        }
        exit();
    }
    
    // SECOND: Check users table (for patients and admin)
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
<<<<<<< HEAD

        $isHardcodedAdmin = (strtolower($email) === strtolower(ADMIN_EMAIL));

        if ($isHardcodedAdmin) {
=======
        $isAdmin = (strtolower($email) === strtolower(ADMIN_EMAIL));

        if ($isAdmin) {
            // ADMIN LOGIN - No OTP required
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['lname'] = $user['lname'] ?? '';
            $_SESSION['role'] = 'admin';
            $_SESSION['username'] = trim($user['fname'] . ' ' . ($user['lname'] ?? ''));

<<<<<<< HEAD
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
=======
            // Update role in database
            $stmt = $pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
            $stmt->execute([$user['id']]);

            header('Location: ../admin/adminDashboard.php');
            exit();
        } else {
            // PATIENT LOGIN - Requires OTP
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

            // Save OTP to database
            $stmt = $pdo->prepare("INSERT INTO otp_verifications (user_id, otp_code) VALUES (?, ?)");
            $stmt->execute([$user['id'], $otp]);

            // Send OTP via SMS
            $message = "Your BSU Clinic System login OTP is: $otp. Valid for 10 minutes.";
            $result = sendSms($user['phone'], $message);

            if ($result['success'] ?? false) {
                $_SESSION['otp_user_id'] = $user['id'];
                $success_message = 'OTP sent to your registered phone number.';
                $show_otp_form = true;
            } else {
                $error_detail = $result['error'] ?? 'Unknown error';
                $error_message = 'Failed to send OTP: ' . $error_detail;
            }
        }
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    } else {
        $error_message = 'Invalid email or password.';
    }
}
<<<<<<< HEAD
=======

// Resend OTP
if (isset($_POST['resend_otp'])) {
    if (isset($_SESSION['otp_user_id'])) {
        $stmt = $pdo->prepare("SELECT phone, email FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['otp_user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            $isAdmin = (strtolower($user['email']) === strtolower(ADMIN_EMAIL));
            $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);

            $stmt = $pdo->prepare("INSERT INTO otp_verifications (user_id, otp_code) VALUES (?, ?)");
            $stmt->execute([$_SESSION['otp_user_id'], $otp]);

            $message = "Your BSU Clinic System login OTP is: $otp. Valid for 10 minutes.";
            $result = sendSms($user['phone'], $message);

            if ($result['success'] ?? false) {
                $success_message = ($isAdmin ? '🔐 Admin: ' : '') . 'New OTP sent to your phone.';
                $show_otp_form = true;
            } else {
                $error_message = 'Failed to resend OTP.';
            }
        }
    }
}
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>BSU Clinic System - Login</title>
    <?php include('header.php'); ?>
<<<<<<< HEAD
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
=======
    <style>
        .admin-badge {
            display: inline-block;
            background: linear-gradient(135deg, #6b0d00 0%, #8b1000 100%);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-left: 8px;
            box-shadow: 0 2px 4px rgba(107, 13, 0, 0.3);
        }
    </style>
</head>

<body>
    <?php include('sysheader.php'); ?>

    <div class="container">
        <div class="login-card">
            <h2 class="login-title">
                <?= $show_otp_form ? 'Verify OTP' : 'Login' ?>
                <?php if ($show_otp_form && isset($_SESSION['otp_user_id'])): ?>
                    <?php
                    $stmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
                    $stmt->execute([$_SESSION['otp_user_id']]);
                    $tempUser = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($tempUser && strtolower($tempUser['email']) === strtolower(ADMIN_EMAIL)):
                    ?>
                        <span class="admin-badge">👑 ADMIN</span>
                    <?php endif; ?>
                <?php endif; ?>
            </h2>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?= $success_message ?></div>
            <?php endif; ?>

            <?php if ($show_otp_form): ?>
                <form method="POST">
                    <div class="form-group">
                        <input type="text" name="otp" class="form-input" placeholder="Enter 6-digit OTP"
                               maxlength="6" pattern="\d{6}" required autofocus>
                        <span class="input-icon"><i class="bi bi-shield-lock-fill"></i></span>
                    </div>

                    <button type="submit" name="verify_otp" class="login-button">Verify OTP</button>

                    <div class="signup-link">
                        Didn't receive OTP?
                        <button type="submit" name="resend_otp" style="background:none;border:none;color:#b30000;cursor:pointer;text-decoration:underline;padding:0;">Resend</button>
                    </div>
                </form>
            <?php else: ?>
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

                    <div class="signup-link">
                        Don't have an account? <a href="signup.php">Sign up</a>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9

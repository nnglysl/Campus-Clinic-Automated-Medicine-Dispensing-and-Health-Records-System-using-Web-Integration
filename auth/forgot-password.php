<?php
// Start output buffering to prevent any output before redirect
ob_start();
include('../db.php');
session_start();

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../admin/phpmailer/src/Exception.php';
require '../admin/phpmailer/src/PHPMailer.php';
require '../admin/phpmailer/src/SMTP.php';
require_once __DIR__ . '/../config/sms.php';

$error_message = '';
$success_message = '';
$selected_method = isset($_POST['verification_method']) ? $_POST['verification_method'] : 'email';
$show_code_form = false;
$phone_for_verification = '';

// Handle Email submission (existing functionality - kept intact)
if (isset($_POST['submit_email'])) {
    $email = trim($_POST['email']);
    $verification_method = isset($_POST['verification_method']) ? $_POST['verification_method'] : 'email';
    
    if ($verification_method === 'email') {
        // EXISTING EMAIL FUNCTIONALITY - UNCHANGED
        if (empty($email)) {
            $error_message = 'Please enter your email address.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = 'Please enter a valid email address.';
        } else {
            $stmt = $pdo->prepare("SELECT id, fname, email FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $token_hash = hash('sha256', $token);
                
                // Use MySQL's DATE_ADD to ensure timezone consistency with NOW() check
                // This ensures the expiry time matches MySQL's timezone
                $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
                $stmt->execute([$token_hash, $user['id']]);
                
                // Generate correct reset link URL
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                $host = $_SERVER['HTTP_HOST'];
                // Get the base path correctly - this file is in /auth/, so dirname gives us the auth directory path
                $script_path = dirname($_SERVER['PHP_SELF']);
                // Normalize the path (remove trailing slash, ensure it starts with /)
                $script_path = '/' . trim($script_path, '/');
                // Build the reset link - reset-password.php is in the same directory as forgot-password.php
                $reset_link = $protocol . "://" . $host . $script_path . "/reset-password.php?token=" . urlencode($token);

                $mail = new PHPMailer(true);
                
                try {
                    $mail->isSMTP();
                    $mail->Host       = $_ENV['MAIL_HOST'];
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $_ENV['MAIL_USERNAME'];
                    $mail->Password   = $_ENV['MAIL_PASSWORD'];
                    $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
                    $mail->Port       = $_ENV['MAIL_PORT'];

                    // Use specific sender name for password reset emails (not Inventory Alert System)
                    $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], 'BSU Clinic System - Password Reset');
                    $mail->addAddress($user['email'], $user['fname']);

                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request - BSU Clinic System';

                    $mail->Body = '
                    <html><body>
                    <h2>Password Reset Request</h2>
                    <p>Hello <strong>' . htmlspecialchars($user['fname']) . '</strong>,</p>
                    <p>Click this link to reset your password:</p>
                    <p><a href="' . $reset_link . '">' . $reset_link . '</a></p>
                    </body></html>';

                    $mail->AltBody = "Reset Password: " . $reset_link;

                    $mail->send();
                    $success_message = 'If an account exists with this email, a reset link has been sent.';
                    
                } catch (Exception $e) {
                    $success_message = 'Email sending failed. Dev link: <a href="' . $reset_link . '">Reset Password</a>';
                }
            } else {
                $success_message = 'If an account exists with this email, a reset link has been sent.';
            }
        }
    } elseif ($verification_method === 'sms') {
        // NEW SMS FUNCTIONALITY
        $phone = trim($_POST['phone'] ?? '');
        
        if (empty($phone)) {
            $error_message = 'Please enter your phone number.';
        } elseif (!preg_match('/^(09|\+639)\d{9,10}$/', $phone)) {
            $error_message = 'Invalid phone number format. Use 09XXXXXXXXX (11 digits) or +639XXXXXXXXX (13 characters)';
        } else {
            // Normalize phone number for database lookup
            $phone_normalized = preg_replace('/[^0-9+]/', '', $phone);
            if (strpos($phone_normalized, '09') === 0) {
                $phone_normalized = '+63' . substr($phone_normalized, 1);
            }
            
            $stmt = $pdo->prepare("SELECT id, fname, phone FROM users WHERE phone = ? OR phone = ?");
            $stmt->execute([$phone, $phone_normalized]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Generate 6-digit verification code
                $verification_code = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
                $code_hash = hash('sha256', $verification_code);
                
                // Use MySQL's DATE_ADD to ensure timezone consistency with NOW() check
                // This ensures the expiry time matches MySQL's timezone
                $expiry_time = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
                $stmt->execute([$code_hash, $user['id']]);
                
                // Record OTP in otp_verifications table
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO otp_verifications (user_id, phone, otp_code, created_at, expires_at, is_verified) 
                        VALUES (?, ?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 1 HOUR), 0)
                    ");
                    $stmt->execute([
                        $user['id'],
                        $user['phone'],
                        $verification_code
                    ]);
                } catch (PDOException $e) {
                    // Log error but don't fail the process
                    error_log("Error recording OTP in otp_verifications: " . $e->getMessage());
                }
                
                // Send SMS with verification code
                $message = "BSU Clinic: Your password reset verification code is {$verification_code}. Valid for 1 hour. Do not share this code.";
                $sms_result = sendSms($user['phone'], $message);
                
                if ($sms_result && isset($sms_result['success']) && $sms_result['success']) {
                    $success_message = 'A verification code has been sent to your phone number.';
                    $show_code_form = true;
                    $phone_for_verification = $phone;
                    // Store phone in session for verification
                    $_SESSION['sms_reset_phone'] = $phone;
                    $_SESSION['sms_reset_user_id'] = $user['id'];
                } else {
                    $error_message = 'Failed to send SMS. Please try again or use email instead.';
                    // Clear the token if SMS failed
                    $stmt = $pdo->prepare("UPDATE users SET reset_token = NULL, reset_token_expiry = NULL WHERE id = ?");
                    $stmt->execute([$user['id']]);
                    // Also mark OTP as invalid if SMS failed (optional cleanup)
                    try {
                        $stmt = $pdo->prepare("UPDATE otp_verifications SET is_verified = -1 WHERE user_id = ? AND otp_code = ? AND is_verified = 0 ORDER BY created_at DESC LIMIT 1");
                        $stmt->execute([$user['id'], $verification_code]);
                    } catch (PDOException $e) {
                        error_log("Error updating OTP status: " . $e->getMessage());
                    }
                }
            } else {
                // Don't reveal if phone exists or not (security)
                $success_message = 'If an account exists with this phone number, a verification code has been sent.';
            }
        }
    }
}

// Handle SMS verification code submission
if (isset($_POST['verify_sms_code'])) {
    // Combine the 6 individual code inputs
    $code1 = trim($_POST['code1'] ?? '');
    $code2 = trim($_POST['code2'] ?? '');
    $code3 = trim($_POST['code3'] ?? '');
    $code4 = trim($_POST['code4'] ?? '');
    $code5 = trim($_POST['code5'] ?? '');
    $code6 = trim($_POST['code6'] ?? '');
    $entered_code = $code1 . $code2 . $code3 . $code4 . $code5 . $code6;
    
    $phone = $_SESSION['sms_reset_phone'] ?? '';
    $user_id = $_SESSION['sms_reset_user_id'] ?? null;
    
    if (empty($entered_code) || strlen($entered_code) < 6) {
        $error_message = 'Please enter the complete 6-digit verification code.';
        $show_code_form = true;
        $phone_for_verification = $phone;
    } elseif (!preg_match('/^\d{6}$/', $entered_code)) {
        $error_message = 'Please enter a valid 6-digit code.';
        $show_code_form = true;
        $phone_for_verification = $phone;
    } elseif (!$user_id) {
        $error_message = 'Session expired. Please request a new verification code.';
        unset($_SESSION['sms_reset_phone']);
        unset($_SESSION['sms_reset_user_id']);
    } else {
        // Verify the code - check both users table (hashed) and otp_verifications table (plain)
        $code_hash = hash('sha256', $entered_code);
        
        // First, verify against users table (hashed token)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE id = ? AND reset_token = ? AND reset_token_expiry IS NOT NULL AND reset_token_expiry >= NOW()");
        $stmt->execute([$user_id, $code_hash]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Also verify against otp_verifications table (plain OTP code)
        $otp_valid = false;
        $otp_record = null;
        try {
            $stmt = $pdo->prepare("
                SELECT id FROM otp_verifications 
                WHERE user_id = ? 
                AND otp_code = ? 
                AND is_verified = 0 
                AND expires_at >= NOW()
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$user_id, $entered_code]);
            $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);
            $otp_valid = ($otp_record !== false);
        } catch (PDOException $e) {
            error_log("Error checking OTP in otp_verifications: " . $e->getMessage());
        }
        
        // Code is valid if either check passes (for backward compatibility)
        if ($user || $otp_valid) {
            // Code is valid - mark OTP as verified in otp_verifications table
            if ($otp_valid && $otp_record && isset($otp_record['id'])) {
                try {
                    $stmt = $pdo->prepare("
                        UPDATE otp_verifications 
                        SET is_verified = 1, verified_at = NOW() 
                        WHERE id = ?
                    ");
                    $stmt->execute([$otp_record['id']]);
                } catch (PDOException $e) {
                    // Log error but don't fail the process
                    error_log("Error updating OTP verification status: " . $e->getMessage());
                }
            } else if ($otp_valid) {
                // Fallback: update by user_id and otp_code if id not available
                try {
                    $stmt = $pdo->prepare("
                        UPDATE otp_verifications 
                        SET is_verified = 1, verified_at = NOW() 
                        WHERE user_id = ? 
                        AND otp_code = ? 
                        AND is_verified = 0 
                        AND expires_at >= NOW()
                        ORDER BY created_at DESC 
                        LIMIT 1
                    ");
                    $stmt->execute([$user_id, $entered_code]);
                } catch (PDOException $e) {
                    error_log("Error updating OTP verification status (fallback): " . $e->getMessage());
                }
            }
            
            // Generate a token for password reset (similar to email flow)
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            
            // Update with new token for password reset
            $stmt = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE id = ?");
            $stmt->execute([$token_hash, $user_id]);
            
            // Clear session variables
            unset($_SESSION['sms_reset_phone']);
            unset($_SESSION['sms_reset_user_id']);
            
            // Clean any output buffer to ensure clean redirect
            if (ob_get_level() > 0) {
                ob_end_clean();
            }
            
            // Build redirect URL to reset password page
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'];
            $script_path = dirname($_SERVER['PHP_SELF']);
            $script_path = '/' . trim($script_path, '/');
            $reset_link = $protocol . "://" . $host . $script_path . "/reset-password.php?token=" . urlencode($token);
            
            // Perform redirect
            header("Location: " . $reset_link);
            exit();
        } else {
            // Code is invalid or expired - record failed verification attempt (optional)
            try {
                // Try to find the OTP record and mark it as failed (if exists)
                $stmt = $pdo->prepare("
                    SELECT id FROM otp_verifications 
                    WHERE user_id = ? 
                    AND otp_code = ? 
                    AND is_verified = 0 
                    AND expires_at >= NOW()
                    ORDER BY created_at DESC 
                    LIMIT 1
                ");
                $stmt->execute([$user_id, $entered_code]);
                $otp_record = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Note: We don't update is_verified here to allow retry attempts
                // The OTP will expire naturally based on expires_at
            } catch (PDOException $e) {
                error_log("Error checking OTP record: " . $e->getMessage());
            }
            
            // Code is invalid or expired
            $error_message = 'Invalid or expired verification code. Please try again or request a new code.';
            $show_code_form = true;
            $phone_for_verification = $phone;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Forgot Password - BSU Clinic</title>
<?php include('header.php'); ?>
<link href="responsive.css" rel="stylesheet">
<link rel="stylesheet"
href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />

<style>
body {
    font-family: Arial, sans-serif;
    background: #f2f2f2;
    margin: 0;
    padding: 0;
}

.forgot-card {
    max-width: 420px;
    margin: 80px auto;
    background: #fff;
    padding: 40px 35px;
    border-radius: 15px;
    box-shadow: 0 4px 18px rgba(0,0,0,0.1);
    text-align: center;
    transition: all 0.3s ease;
}

.icon-wrapper i {
    font-size: 55px;
    color: #a01212;
    margin-bottom: 10px;
}

.form-group {
    position: relative;
}

.form-input {
    width: 100%;
    padding: 12px 40px 12px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 15px;
}

.input-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #777;
}

.submit-button {
    width: 100%;
    padding: 14px;
    border: none;
    background: #a01212;
    color: white;
    font-size: 17px;
    border-radius: 8px;
    cursor: pointer;
    margin-top: 20px;
    transition: 0.3s;
}

.submit-button:hover {
    background: #8b0f0f;
}

.alert {
    padding: 12px 15px;
    border-radius: 8px;
    margin-bottom: 15px;
    text-align: left;
    font-size: 14px;
}

.alert-danger {
    background: #f8d7da;
    color: #842029;
}

.alert-success {
    background: #d1e7dd;
    color: #0f5132;
}

.back-to-login {
    margin-top: 20px;
}

.back-to-login a {
    color: #555;
}

.method-selection {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    justify-content: center;
}

.method-option {
    flex: 1;
}

.method-option input[type="radio"] {
    display: none;
}

.method-option label {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 15px 20px;
    border: 2px solid #ddd;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s ease;
    background: #fff;
    text-align: center;
}

.method-option label i {
    font-size: 24px;
    color: #777;
    margin-bottom: 8px;
    transition: color 0.3s ease;
}

.method-option label span {
    font-size: 14px;
    color: #555;
    font-weight: 500;
    transition: color 0.3s ease;
}

.method-option input[type="radio"]:checked + label {
    border-color: #a01212;
    background: #fff5f5;
}

.method-option input[type="radio"]:checked + label i {
    color: #a01212;
}

.method-option input[type="radio"]:checked + label span {
    color: #a01212;
    font-weight: 600;
}

.method-option label:hover {
    border-color: #a01212;
    background: #fff9f9;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const emailRadio = document.getElementById('method_email');
    const smsRadio = document.getElementById('method_sms');
    const emailGroup = document.getElementById('emailGroup');
    const phoneGroup = document.getElementById('phoneGroup');
    const emailInput = document.getElementById('emailInput');
    const phoneInput = document.getElementById('phoneInput');
    const submitText = document.getElementById('submitText');
    
    function toggleInputs() {
        if (emailRadio.checked) {
            emailGroup.style.display = 'block';
            phoneGroup.style.display = 'none';
            emailInput.required = true;
            phoneInput.required = false;
            submitText.textContent = 'Send Reset Link';
        } else if (smsRadio.checked) {
            emailGroup.style.display = 'none';
            phoneGroup.style.display = 'block';
            emailInput.required = false;
            phoneInput.required = true;
            submitText.textContent = 'Send Verification Code';
        }
    }
    
    emailRadio.addEventListener('change', toggleInputs);
    smsRadio.addEventListener('change', toggleInputs);
    
    // Phone number formatting
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d+]/g, '');
            if (value.startsWith('+639')) {
                // Keep +639 format
            } else if (value.startsWith('639')) {
                value = '+' + value;
            } else if (value.startsWith('09')) {
                // Keep 09 format
            } else if (value.length > 0 && !value.startsWith('09') && !value.startsWith('+')) {
                value = '09' + value.replace(/^09/, '');
            }
            e.target.value = value;
        });
    }
});
</script>
</head>
<?php include('sysheader.php'); ?>
<body>

<div class="forgot-card">

    <div class="icon-wrapper">
        <i class="bi bi-key-fill"></i>
    </div>

    <h2>Forgot Password?</h2>
    <p>Choose how you'd like to receive your reset instructions.</p>

    <?php if ($error_message): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>

    <?php if ($success_message && !$show_code_form): ?>
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill"></i> <?= $success_message ?>
    </div>
    <?php endif; ?>

    <?php if ($show_code_form): ?>
    <!-- SMS Verification Code Form -->
    <div class="alert alert-success">
        <i class="bi bi-check-circle-fill"></i> <?= $success_message ?>
    </div>
    
    <?php if ($error_message): ?>
    <div class="alert alert-danger">
        <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error_message) ?>
    </div>
    <?php endif; ?>
    
    <form method="POST" id="verifyCodeForm">
        <input type="hidden" name="verification_method" value="sms">
        
        <div class="form-group">
            <label style="display: block; margin-bottom: 15px; text-align: center; font-weight: 500; color: #333;">
                Enter 6-digit verification code sent to <?= htmlspecialchars($phone_for_verification) ?>
            </label>
            <div class="code-input-container" style="display: flex; gap: 8px; justify-content: center; align-items: center; margin-bottom: 10px;">
                <input type="text" name="code1" id="code1" class="code-input" 
                       maxlength="1" 
                       pattern="[0-9]"
                       style="width: 55px; height: 60px; text-align: center; font-size: 28px; font-weight: bold; border: 2px solid #ddd; border-radius: 8px; transition: border-color 0.3s;"
                       required autofocus>
                <input type="text" name="code2" id="code2" class="code-input" 
                       maxlength="1" 
                       pattern="[0-9]"
                       style="width: 55px; height: 60px; text-align: center; font-size: 28px; font-weight: bold; border: 2px solid #ddd; border-radius: 8px; transition: border-color 0.3s;"
                       required>
                <input type="text" name="code3" id="code3" class="code-input" 
                       maxlength="1" 
                       pattern="[0-9]"
                       style="width: 55px; height: 60px; text-align: center; font-size: 28px; font-weight: bold; border: 2px solid #ddd; border-radius: 8px; transition: border-color 0.3s;"
                       required>
                <input type="text" name="code4" id="code4" class="code-input" 
                       maxlength="1" 
                       pattern="[0-9]"
                       style="width: 55px; height: 60px; text-align: center; font-size: 28px; font-weight: bold; border: 2px solid #ddd; border-radius: 8px; transition: border-color 0.3s;"
                       required>
                <input type="text" name="code5" id="code5" class="code-input" 
                       maxlength="1" 
                       pattern="[0-9]"
                       style="width: 55px; height: 60px; text-align: center; font-size: 28px; font-weight: bold; border: 2px solid #ddd; border-radius: 8px; transition: border-color 0.3s;"
                       required>
                <input type="text" name="code6" id="code6" class="code-input" 
                       maxlength="1" 
                       pattern="[0-9]"
                       style="width: 55px; height: 60px; text-align: center; font-size: 28px; font-weight: bold; border: 2px solid #ddd; border-radius: 8px; transition: border-color 0.3s;"
                       required>
            </div>
            <input type="hidden" name="verification_code" id="verification_code">
            <style>
            .code-input:focus {
                border-color: #a01212 !important;
                outline: none;
                box-shadow: 0 0 0 3px rgba(160, 18, 18, 0.1);
            }
            .code-input:not(:placeholder-shown) {
                border-color: #28a745;
            }
            </style>
        </div>
        
        <button type="submit" name="verify_sms_code" class="submit-button">
            <i class="bi bi-check-circle-fill"></i> Verify Code
        </button>
        
        <div style="margin-top: 15px; text-align: center;">
            <a href="forgot-password.php" style="color: #555; text-decoration: none; font-size: 14px;">
                <i class="bi bi-arrow-left"></i> Request New Code
            </a>
        </div>
    </form>
    
    <?php elseif (empty($success_message)): ?>
    <form method="POST" id="resetForm">
        <!-- Verification Method Selection -->
        <div class="method-selection">
            <div class="method-option">
                <input type="radio" id="method_email" name="verification_method" value="email" 
                       <?= $selected_method === 'email' ? 'checked' : '' ?> required>
                <label for="method_email">
                    <i class="bi bi-envelope-fill"></i>
                    <span>Email</span>
                </label>
            </div>
            <div class="method-option">
                <input type="radio" id="method_sms" name="verification_method" value="sms"
                       <?= $selected_method === 'sms' ? 'checked' : '' ?>>
                <label for="method_sms">
                    <i class="bi bi-phone-fill"></i>
                    <span>SMS</span>
                </label>
            </div>
        </div>
        
        <!-- Email Input (shown when Email is selected) -->
        <div class="form-group" id="emailGroup" style="display: <?= $selected_method === 'email' ? 'block' : 'none' ?>;">
            <input type="email" name="email" id="emailInput" class="form-input"
                   placeholder="Enter your email" 
                   value="<?= isset($_POST['email']) && $selected_method === 'email' ? htmlspecialchars($_POST['email']) : '' ?>"
                   <?= $selected_method === 'email' ? 'required' : '' ?>>
            <span class="input-icon"><i class="bi bi-envelope-fill"></i></span>
        </div>
        
        <!-- Phone Input (shown when SMS is selected) -->
        <div class="form-group" id="phoneGroup" style="display: <?= $selected_method === 'sms' ? 'block' : 'none' ?>;">
            <input type="tel" name="phone" id="phoneInput" class="form-input"
                   placeholder="Enter your phone (09XXXXXXXXX)" 
                   value="<?= isset($_POST['phone']) && $selected_method === 'sms' ? htmlspecialchars($_POST['phone']) : '' ?>"
                   pattern="^(09|\+639)\d{9,10}$"
                   <?= $selected_method === 'sms' ? 'required' : '' ?>>
            <span class="input-icon"><i class="bi bi-phone-fill"></i></span>
        </div>
        
        <button type="submit" name="submit_email" class="submit-button">
            <i class="bi bi-send-fill"></i> 
            <span id="submitText"><?= $selected_method === 'email' ? 'Send Reset Link' : 'Send Verification Code' ?></span>
        </button>
    </form>
    <?php endif; ?>

    <div class="back-to-login">
        <a href="login.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const emailRadio = document.getElementById('method_email');
    const smsRadio = document.getElementById('method_sms');
    const emailGroup = document.getElementById('emailGroup');
    const phoneGroup = document.getElementById('phoneGroup');
    const emailInput = document.getElementById('emailInput');
    const phoneInput = document.getElementById('phoneInput');
    const submitText = document.getElementById('submitText');
    
    function toggleInputs() {
        if (emailRadio && emailRadio.checked) {
            if (emailGroup) emailGroup.style.display = 'block';
            if (phoneGroup) phoneGroup.style.display = 'none';
            if (emailInput) emailInput.required = true;
            if (phoneInput) phoneInput.required = false;
            if (submitText) submitText.textContent = 'Send Reset Link';
        } else if (smsRadio && smsRadio.checked) {
            if (emailGroup) emailGroup.style.display = 'none';
            if (phoneGroup) phoneGroup.style.display = 'block';
            if (emailInput) emailInput.required = false;
            if (phoneInput) phoneInput.required = true;
            if (submitText) submitText.textContent = 'Send Verification Code';
        }
    }
    
    if (emailRadio) emailRadio.addEventListener('change', toggleInputs);
    if (smsRadio) smsRadio.addEventListener('change', toggleInputs);
    
    // Phone number formatting
    if (phoneInput) {
        phoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/[^\d+]/g, '');
            if (value.startsWith('+639')) {
                // Keep +639 format
            } else if (value.startsWith('639')) {
                value = '+' + value;
            } else if (value.startsWith('09')) {
                // Keep 09 format
            } else if (value.length > 0 && !value.startsWith('09') && !value.startsWith('+')) {
                value = '09' + value.replace(/^09/, '');
            }
            e.target.value = value;
        });
    }
    
    // 6-box verification code input handling (one digit per box)
    const codeInputs = ['code1', 'code2', 'code3', 'code4', 'code5', 'code6'];
    const codeInputElements = codeInputs.map(id => document.getElementById(id)).filter(el => el !== null);
    
    if (codeInputElements.length > 0) {
        // Focus on first input on page load
        codeInputElements[0].focus();
        
        codeInputElements.forEach((input, index) => {
            // Only allow digits
            input.addEventListener('input', function(e) {
                this.value = this.value.replace(/[^\d]/g, '');
                
                // Limit to 1 digit
                if (this.value.length > 1) {
                    this.value = this.value.charAt(0);
                }
                
                // Auto-advance to next box when a digit is entered
                if (this.value.length === 1 && index < codeInputElements.length - 1) {
                    codeInputElements[index + 1].focus();
                }
                
                // Update hidden field with combined code
                updateVerificationCode();
            });
            
            // Handle backspace to go to previous box
            input.addEventListener('keydown', function(e) {
                if (e.key === 'Backspace' && this.value === '' && index > 0) {
                    codeInputElements[index - 1].focus();
                    codeInputElements[index - 1].value = '';
                    updateVerificationCode();
                }
            });
            
            // Handle arrow keys for navigation
            input.addEventListener('keydown', function(e) {
                if (e.key === 'ArrowLeft' && index > 0) {
                    e.preventDefault();
                    codeInputElements[index - 1].focus();
                } else if (e.key === 'ArrowRight' && index < codeInputElements.length - 1) {
                    e.preventDefault();
                    codeInputElements[index + 1].focus();
                }
            });
            
            // Handle paste - distribute pasted code across boxes (only on first input)
            if (index === 0) {
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pastedData = (e.clipboardData || window.clipboardData).getData('text').replace(/[^\d]/g, '').substring(0, 6);
                    
                    if (pastedData.length > 0) {
                        // Clear all inputs first
                        codeInputElements.forEach(box => box.value = '');
                        
                        // Distribute pasted code: 1 digit per box
                        for (let i = 0; i < Math.min(pastedData.length, codeInputElements.length); i++) {
                            codeInputElements[i].value = pastedData[i];
                        }
                        updateVerificationCode();
                        
                        // Focus on the last filled box or last box
                        const lastFilledIndex = Math.min(pastedData.length - 1, codeInputElements.length - 1);
                        codeInputElements[lastFilledIndex].focus();
                    }
                });
            }
        });
        
        // Function to update hidden verification code field
        function updateVerificationCode() {
            const combinedCode = codeInputElements.map(input => input.value).join('');
            const hiddenInput = document.getElementById('verification_code');
            if (hiddenInput) {
                hiddenInput.value = combinedCode;
            }
        }
    }
});
</script>

</body>
</html>

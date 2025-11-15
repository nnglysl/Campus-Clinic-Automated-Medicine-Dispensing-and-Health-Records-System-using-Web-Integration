<?php
include('../db.php');
session_start();

$error_message = '';
$success_message = '';
$step = 'phone'; // 'phone', 'otp', or 'register'

// Handle phone submission
if (isset($_POST['send_otp'])) {
    $phone = trim($_POST['phone']);
    
    // Validate phone format
    if (!preg_match('/^(09|\+639)\d{9}$/', $phone)) {
        $error_message = 'Invalid phone number format. Use 09XXXXXXXXX or +639XXXXXXXXX';
    } else {
        // Normalize phone number
        if (strpos($phone, '09') === 0) {
            $phone = '+63' . substr($phone, 1);
        }
        
        // Check if phone exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
        $stmt->execute([$phone]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Generate OTP
        $otp = sprintf('%06d', mt_rand(0, 999999));
        $_SESSION['otp'] = $otp;
        $_SESSION['otp_phone'] = $phone;
        $_SESSION['otp_expiry'] = time() + 300; // 5 minutes
        $_SESSION['user_exists'] = $user ? true : false;
        
        // Send SMS
        require_once('sms_helper.php');
        $smsResult = sendSms($phone, "Your BSU Clinic verification code is: $otp. Valid for 5 minutes.");
        
        if ($smsResult['success']) {
            $success_message = 'OTP sent to your phone!';
            $step = 'otp';
        } else {
            $error_message = 'Failed to send OTP. Please try again.';
            error_log("SMS Error: " . json_encode($smsResult));
        }
    }
}

// Handle OTP verification
if (isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp']);
    
    if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_phone'])) {
        $error_message = 'Session expired. Please start over.';
        $step = 'phone';
    } elseif (time() > $_SESSION['otp_expiry']) {
        $error_message = 'OTP expired. Please request a new one.';
        unset($_SESSION['otp'], $_SESSION['otp_phone'], $_SESSION['otp_expiry']);
        $step = 'phone';
    } elseif ($entered_otp !== $_SESSION['otp']) {
        $error_message = 'Invalid OTP. Please try again.';
        $step = 'otp';
    } else {
        // OTP verified
        if ($_SESSION['user_exists']) {
            // User exists - log them in
            $stmt = $pdo->prepare("SELECT * FROM users WHERE phone = ?");
            $stmt->execute([$_SESSION['otp_phone']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['lname'] = $user['lname'] ?? '';
            $_SESSION['role'] = $user['role'] ?? 'patient';
            $_SESSION['username'] = trim($user['fname'] . ' ' . ($user['lname'] ?? ''));
            
            // Clean up OTP session
            unset($_SESSION['otp'], $_SESSION['otp_phone'], $_SESSION['otp_expiry'], $_SESSION['user_exists']);
            
            // Redirect based on role
            if ($user['role'] === 'admin') {
                header('Location: ../admin/adminDashboard.php');
            } else {
                header('Location: ../student/student_dashboard.php');
            }
            exit();
        } else {
            // New user - show registration form
            $step = 'register';
        }
    }
}

// Handle registration
if (isset($_POST['complete_registration'])) {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $email = trim($_POST['email']);
    
    if (empty($fname) || empty($email)) {
        $error_message = 'Please fill in all required fields.';
        $step = 'register';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email address.';
        $step = 'register';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        
        if ($stmt->fetch()) {
            $error_message = 'Email already registered. Please use a different email.';
            $step = 'register';
        } else {
            // Create user account
            $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (fname, lname, email, phone, password, role, created_at) VALUES (?, ?, ?, ?, ?, 'patient', NOW())");
            
            if ($stmt->execute([$fname, $lname, $email, $_SESSION['otp_phone'], $random_password])) {
                $user_id = $pdo->lastInsertId();
                
                // Set session and login
                $_SESSION['user_id'] = $user_id;
                $_SESSION['email'] = $email;
                $_SESSION['fname'] = $fname;
                $_SESSION['lname'] = $lname;
                $_SESSION['role'] = 'patient';
                $_SESSION['username'] = trim($fname . ' ' . $lname);
                
                // Clean up OTP session
                unset($_SESSION['otp'], $_SESSION['otp_phone'], $_SESSION['otp_expiry'], $_SESSION['user_exists']);
                
                header('Location: ../student/student_dashboard.php');
                exit();
            } else {
                $error_message = 'Registration failed. Please try again.';
                $step = 'register';
            }
        }
    }
}

// Preserve step state
if (isset($_SESSION['otp']) && !isset($_POST['send_otp'])) {
    $step = 'otp';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <title>BSU Clinic System - Phone Login</title>
    <?php include('header.php'); ?>
    <style>
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin: 20px 0;
        }
        
        .otp-input {
            width: 50px;
            height: 50px;
            text-align: center;
            font-size: 24px;
            border: 2px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .otp-input:focus {
            border-color: #8B0000;
            outline: none;
            box-shadow: 0 0 0 3px rgba(139, 0, 0, 0.1);
        }
        
        .resend-otp {
            text-align: center;
            margin-top: 15px;
            color: #666;
        }
        
        .resend-otp button {
            background: none;
            border: none;
            color: #8B0000;
            cursor: pointer;
            text-decoration: underline;
            font-size: 14px;
            padding: 0;
        }
        
        .resend-otp button:hover {
            color: #660000;
        }
        
        .phone-info {
            text-align: center;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .phone-info i {
            color: #8B0000;
            font-size: 48px;
            margin-bottom: 10px;
        }
    </style>
</head>

<body>
    <?php include('sysheader.php'); ?>

    <div class="container">
        <div class="login-card">
            <h2 class="login-title">
                Phone Login
            </h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
            <?php endif; ?>

            <?php if ($step === 'phone'): ?>
                <!-- Step 1: Enter Phone Number -->
                <div class="phone-info">
                    <i class="bi bi-shield-check"></i>
                    <p style="margin: 0; color: #666;">
                        We'll send a verification code to your phone number
                    </p>
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <input type="tel" name="phone" class="form-input" 
                               placeholder="Phone Number (09XXXXXXXXX)" 
                               pattern="^(09|\+639)\d{9}$"
                               value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" 
                               required>
                        <span class="input-icon"><i class="bi bi-phone-fill"></i></span>
                    </div>
                    
                    <button type="submit" name="send_otp" class="login-button">
                        <i class="bi bi-send-fill"></i> Send Verification Code
                    </button>
                </form>
                
            <?php elseif ($step === 'otp'): ?>
                <!-- Step 2: Verify OTP -->
                <div class="phone-info">
                    <i class="bi bi-chat-dots-fill"></i>
                    <p style="margin: 0; color: #666;">
                        Enter the 6-digit code sent to<br>
                        <strong><?= htmlspecialchars($_SESSION['otp_phone']) ?></strong>
                    </p>
                </div>
                
                <form method="POST" id="otpForm">
                    <div class="otp-inputs">
                        <input type="text" class="otp-input" maxlength="1" pattern="\d" required autofocus>
                        <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
                        <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
                        <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
                        <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
                        <input type="text" class="otp-input" maxlength="1" pattern="\d" required>
                    </div>
                    
                    <input type="hidden" name="otp" id="otpValue">
                    <button type="submit" name="verify_otp" class="login-button">
                        <i class="bi bi-check-circle-fill"></i> Verify Code
                    </button>
                </form>
                
                <div class="resend-otp">
                    Didn't receive the code? 
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="phone" value="<?= htmlspecialchars($_SESSION['otp_phone']) ?>">
                        <button type="submit" name="send_otp">Resend OTP</button>
                    </form>
                </div>
                
            <?php elseif ($step === 'register'): ?>
                <!-- Step 3: Complete Registration -->
                <div class="phone-info">
                    <i class="bi bi-person-plus-fill"></i>
                    <p style="margin: 0; color: #666;">
                        Complete your profile to continue
                    </p>
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <input type="text" name="fname" class="form-input" 
                               placeholder="First Name *" 
                               value="<?= isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : '' ?>" 
                               required>
                        <span class="input-icon"><i class="bi bi-person-fill"></i></span>
                    </div>
                    
                    <div class="form-group">
                        <input type="text" name="lname" class="form-input" 
                               placeholder="Last Name" 
                               value="<?= isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : '' ?>">
                        <span class="input-icon"><i class="bi bi-person-fill"></i></span>
                    </div>
                    
                    <div class="form-group">
                        <input type="email" name="email" class="form-input" 
                               placeholder="Email Address *" 
                               value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" 
                               required>
                        <span class="input-icon"><i class="bi bi-envelope-fill"></i></span>
                    </div>
                    
                    <div class="form-group">
                        <input type="tel" class="form-input" 
                               value="<?= htmlspecialchars($_SESSION['otp_phone']) ?>" 
                               disabled style="background: #f0f0f0;">
                        <span class="input-icon"><i class="bi bi-phone-fill"></i></span>
                    </div>
                    
                    <button type="submit" name="complete_registration" class="login-button">
                        <i class="bi bi-check-circle-fill"></i> Complete Registration
                    </button>
                </form>
            <?php endif; ?>

            <div class="signup-link">
                <a href="login.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        // OTP Input Handler
        document.addEventListener('DOMContentLoaded', function() {
            const otpInputs = document.querySelectorAll('.otp-input');
            const otpForm = document.getElementById('otpForm');
            const otpValue = document.getElementById('otpValue');
            
            if (otpInputs.length > 0) {
                otpInputs.forEach((input, index) => {
                    input.addEventListener('input', function(e) {
                        const value = this.value;
                        
                        // Only allow numbers
                        if (!/^\d$/.test(value)) {
                            this.value = '';
                            return;
                        }
                        
                        // Move to next input
                        if (value && index < otpInputs.length - 1) {
                            otpInputs[index + 1].focus();
                        }
                        
                        // Update hidden input
                        updateOtpValue();
                        
                        // Auto-submit if all filled
                        if (index === otpInputs.length - 1 && value) {
                            const allFilled = Array.from(otpInputs).every(inp => inp.value);
                            if (allFilled) {
                                setTimeout(() => otpForm.submit(), 300);
                            }
                        }
                    });
                    
                    input.addEventListener('keydown', function(e) {
                        // Handle backspace
                        if (e.key === 'Backspace') {
                            if (!this.value && index > 0) {
                                otpInputs[index - 1].focus();
                                otpInputs[index - 1].value = '';
                            }
                            updateOtpValue();
                        }
                        
                        // Handle paste
                        if (e.key === 'v' && (e.ctrlKey || e.metaKey)) {
                            e.preventDefault();
                            navigator.clipboard.readText().then(text => {
                                const numbers = text.replace(/\D/g, '').slice(0, 6);
                                numbers.split('').forEach((num, i) => {
                                    if (otpInputs[i]) {
                                        otpInputs[i].value = num;
                                    }
                                });
                                updateOtpValue();
                                if (numbers.length === 6) {
                                    otpInputs[5].focus();
                                    setTimeout(() => otpForm.submit(), 300);
                                }
                            });
                        }
                    });
                });
                
                function updateOtpValue() {
                    const otp = Array.from(otpInputs).map(input => input.value).join('');
                    if (otpValue) {
                        otpValue.value = otp;
                    }
                }
                
                // Auto-focus first input
                if (otpInputs[0]) {
                    otpInputs[0].focus();
                }
            }
        });
    </script>
</body>
</html>
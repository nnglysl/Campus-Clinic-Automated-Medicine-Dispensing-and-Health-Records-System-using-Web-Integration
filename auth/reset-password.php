<?php
include('../db.php');
session_start();

$error_message = '';
$success_message = '';
$token_valid = false;
$user_id = null;

// Check if token is provided
if (isset($_GET['token']) && !empty($_GET['token'])) {
    $token = $_GET['token'];
    $token_hash = hash('sha256', $token);
    
    // Verify token exists and is not expired
    // Use >= instead of > to account for any microsecond differences and ensure token is valid
    // Also check that expiry is not NULL to handle edge cases
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry IS NOT NULL AND reset_token_expiry >= NOW()");
    $stmt->execute([$token_hash]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $token_valid = true;
        $user_id = $user['id'];
    } else {
        $error_message = 'Invalid or expired reset token. Please request a new password reset link.';
    }
} else {
    $error_message = 'No reset token provided.';
}

// Handle password reset submission
if (isset($_POST['reset_password']) && $token_valid && $user_id) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($new_password) || empty($confirm_password)) {
        $error_message = 'Please fill in all fields.';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        try {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password and clear reset token
            $stmt = $pdo->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $success_message = 'Password reset successfully! You can now login with your new password.';
            $token_valid = false; // Prevent form from showing again
            
        } catch (PDOException $e) {
            error_log("Password reset error: " . $e->getMessage());
            $error_message = 'An error occurred. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reset Password - BSU Clinic</title>
<?php include('header.php'); ?>
<link href="responsive.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />

<style>
body {
    font-family: Arial, sans-serif;
    background: #f2f2f2;
    margin: 0;
    padding: 0;
}

.reset-card {
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
    margin-bottom: 20px;
}

.form-input {
    width: 100%;
    padding: 12px 40px 12px 12px;
    border: 1px solid #ddd;
    border-radius: 8px;
    font-size: 15px;
    box-sizing: border-box;
}

.input-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #777;
    z-index: 10;
}

.toggle-password {
    cursor: pointer;
    transition: color 0.3s ease;
}

.toggle-password:hover {
    color: #a01212;
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

.submit-button:disabled {
    background: #ccc;
    cursor: not-allowed;
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
    text-decoration: none;
}

.back-to-login a:hover {
    text-decoration: underline;
}

.password-strength {
    font-size: 12px;
    margin-top: 5px;
    text-align: left;
}

.password-strength.weak {
    color: #dc3545;
}

.password-strength.medium {
    color: #ffc107;
}

.password-strength.strong {
    color: #28a745;
}
</style>
</head>
<?php include('sysheader.php'); ?>
<body>

<div class="reset-card">

    <div class="icon-wrapper">
        <i class="bi bi-key-fill"></i>
    </div>

    <h2>Reset Password</h2>
    
    <?php if ($success_message): ?>
        <p>Your password has been reset successfully!</p>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success_message) ?>
        </div>
        <div class="back-to-login">
            <a href="login.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
        </div>
    <?php elseif (!$token_valid): ?>
        <p>The reset link is invalid or has expired.</p>
        <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>
        <div class="back-to-login">
            <a href="forgot-password.php"><i class="bi bi-arrow-left"></i> Request New Reset Link</a>
        </div>
    <?php else: ?>
        <p>Enter your new password below.</p>

        <?php if ($error_message): ?>
        <div class="alert alert-danger">
            <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error_message) ?>
        </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <input type="password" name="new_password" id="new_password" class="form-input"
                       placeholder="Enter new password" required minlength="8">
                <span class="input-icon toggle-password" data-target="new_password" style="cursor: pointer;">
                    <i class="bi bi-eye-fill" id="toggleNewPassword"></i>
                </span>
                <div class="password-strength" id="passwordStrength"></div>
            </div>
            
            <div class="form-group">
                <input type="password" name="confirm_password" id="confirm_password" class="form-input"
                       placeholder="Confirm new password" required minlength="8">
                <span class="input-icon toggle-password" data-target="confirm_password" style="cursor: pointer;">
                    <i class="bi bi-eye-fill" id="toggleConfirmPassword"></i>
                </span>
            </div>
            
            <button type="submit" name="reset_password" class="submit-button">
                <i class="bi bi-check-circle-fill"></i> Reset Password
            </button>
        </form>

        <div class="back-to-login">
            <a href="login.php"><i class="bi bi-arrow-left"></i> Back to Login</a>
        </div>
    <?php endif; ?>

</div>

<script>
// Password strength indicator
const passwordInput = document.getElementById('new_password');
const confirmInput = document.getElementById('confirm_password');
const strengthDiv = document.getElementById('passwordStrength');

if (passwordInput && strengthDiv) {
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = '';
        let strengthClass = '';
        
        if (password.length === 0) {
            strengthDiv.textContent = '';
            strengthDiv.className = 'password-strength';
        } else if (password.length < 8) {
            strength = 'Password must be at least 8 characters';
            strengthClass = 'weak';
        } else {
            let score = 0;
            if (password.length >= 8) score++;
            if (password.length >= 12) score++;
            if (/[a-z]/.test(password)) score++;
            if (/[A-Z]/.test(password)) score++;
            if (/[0-9]/.test(password)) score++;
            if (/[^a-zA-Z0-9]/.test(password)) score++;
            
            if (score <= 2) {
                strength = 'Weak password';
                strengthClass = 'weak';
            } else if (score <= 4) {
                strength = 'Medium password';
                strengthClass = 'medium';
            } else {
                strength = 'Strong password';
                strengthClass = 'strong';
            }
        }
        
        strengthDiv.textContent = strength;
        strengthDiv.className = 'password-strength ' + strengthClass;
    });
}

// Confirm password validation
if (confirmInput && passwordInput) {
    confirmInput.addEventListener('input', function() {
        if (this.value !== passwordInput.value && this.value.length > 0) {
            this.setCustomValidity('Passwords do not match');
        } else {
            this.setCustomValidity('');
        }
    });
}

// Password visibility toggle functionality
document.querySelectorAll('.toggle-password').forEach(function(toggle) {
    toggle.addEventListener('click', function() {
        const targetId = this.getAttribute('data-target');
        const passwordInput = document.getElementById(targetId);
        const icon = this.querySelector('i');
        
        if (passwordInput) {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('bi-eye-fill');
                icon.classList.add('bi-eye-slash-fill');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('bi-eye-slash-fill');
                icon.classList.add('bi-eye-fill');
            }
        }
    });
});
</script>

</body>
</html>


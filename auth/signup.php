<?php
include('../db.php');
session_start();

<<<<<<< HEAD
if (!isset($pdo)) {
    die("Database connection failed. Please check your database configuration.");
}

$error_message = '';
$success_message = '';

=======
$error_message = '';
$success_message = '';

// Handle signup form submission
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
if (isset($_POST['signup'])) {
    $fname = trim($_POST['fname']);
    $lname = trim($_POST['lname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
<<<<<<< HEAD
    $phone = preg_replace('/[^\d+]/', '', $phone);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = 'patient';
    
    if (empty($fname) || empty($lname) || empty($email) || empty($phone) || empty($password)) {
        $error_message = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Invalid email address format.';
    } elseif (!preg_match('/@g\.batstate-u\.edu\.ph$/i', $email)) {
        $error_message = 'Students must register using their @g.batstate-u.edu.ph email.';
    }
    elseif (!preg_match('/^(09|\+639)\d{9,10}$/', $phone)) {
        $error_message = 'Invalid phone number format. Use 09XXXXXXXXX (11 digits) or +639XXXXXXXXX (13 characters)';
    } elseif (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) OR TRIM(phone) = TRIM(?)");
        $stmt->execute([$email, $phone]);
        
        $existingUser = $stmt->fetch();
        if ($existingUser) {
            $error_message = 'Email or phone number already registered.';
        } else {
            try {
                $pdo->beginTransaction();
                
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt = $pdo->prepare("INSERT INTO users (fname, lname, email, phone, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$fname, $lname, $email, $phone, $hashed_password, $role]);
                
                $user_id = $pdo->lastInsertId();
                
                $full_name = trim($fname . ' ' . $lname);
                
                $sr_code = 'SR-' . date('Y') . '-' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
                
                $stmt = $pdo->prepare("INSERT INTO patients (user_id, sr_code, fname, lname, full_name, email, contact_number) 
                                       VALUES (?, ?, ?, ?, ?, ?, ?)
                                       ON DUPLICATE KEY UPDATE 
                                       sr_code = VALUES(sr_code),
                                       fname = VALUES(fname),
                                       lname = VALUES(lname),
                                       full_name = VALUES(full_name),
                                       email = VALUES(email),
                                       contact_number = VALUES(contact_number)");
                $stmt->execute([$user_id, $sr_code, $fname, $lname, $full_name, $email, $phone]);
                
                $pdo->commit();
                
                $_SESSION['signup_success'] = 'Account created successfully! You can now log in.';
                
                header('Location: login.php');
                exit();
                
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Signup error: " . $e->getMessage());
                error_log("Signup error trace: " . $e->getTraceAsString());
                $error_message = 'Registration failed: ' . htmlspecialchars($e->getMessage()) . '. Please check the error log for more details.';
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log("Signup error: " . $e->getMessage());
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
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
<<<<<<< HEAD
    <link href="responsive.css" rel="stylesheet">
    
    <style>
        .password-wrapper {
            position: relative;
        }

        .password-toggle-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            font-size: 20px;
            transition: color 0.3s ease;
            z-index: 10;
            padding: 5px;
            user-select: none;
        }

        .password-toggle-icon:hover {
            color: #333;
        }

        .password-toggle-icon:active {
            transform: translateY(-50%) scale(0.95);
        }

        /* Adjust input padding to make room for eye icon */
        .password-wrapper .form-input {
            padding-right: 45px;
        }

        /* Role selector styling */
        .role-selector {
            margin-bottom: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 2px solid #e0e0e0;
        }

        .role-selector label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: #333;
        }

        .role-options {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .role-option {
            flex: 1;
            min-width: 120px;
        }

        .role-option input[type="radio"] {
            display: none;
        }

        .role-option label {
            display: block;
            padding: 12px 20px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .role-option input[type="radio"]:checked + label {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: #667eea;
        }

        .role-option label:hover {
            border-color: #667eea;
        }

        .email-hint {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            padding-left: 5px;
        }

        .email-hint.warning {
            color: #ff6b6b;
            font-weight: 500;
        }
        
        .login-card {
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15) !important;
        }
    </style>
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
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

<<<<<<< HEAD
            <form method="POST" id="signupForm">
                <input type="hidden" name="role" value="patient" id="hiddenRole">

=======
            <!-- Regular Signup Form -->
            <form method="POST">
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                <div class="form-group">
                    <input type="text" name="fname" class="form-input" placeholder="First Name" 
                           value="<?= isset($_POST['fname']) ? htmlspecialchars($_POST['fname']) : '' ?>" required>
                </div>
                
                <div class="form-group">
                    <input type="text" name="lname" class="form-input" placeholder="Last Name" 
                           value="<?= isset($_POST['lname']) ? htmlspecialchars($_POST['lname']) : '' ?>" required>
                </div>
                
                <div class="form-group">
<<<<<<< HEAD
                    <input type="email" name="email" id="emailInput" class="form-input" placeholder="Email (XX-XXXXX@g.batstate-u.edu.ph)" 
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
                    <div class="email-hint" id="emailHint">
                        
                    </div>
=======
                    <input type="email" name="email" class="form-input" placeholder="Email" 
                           value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>" required>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                </div>
                
                <div class="form-group">
                    <input type="tel" name="phone" class="form-input" placeholder="Phone (09XXXXXXXXX)" 
                           value="<?= isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : '' ?>" required>
                    <span class="input-icon"><i class="bi bi-phone-fill"></i></span>
                </div>
               
<<<<<<< HEAD
                <div class="form-group password-wrapper">
                    <input type="password" name="password" id="passwordInput" class="form-input" placeholder="Password (min. 8 characters)" required minlength="8">
                    <i class="bi bi-eye-fill password-toggle-icon" id="togglePassword" onclick="togglePasswordVisibility('passwordInput', 'togglePassword')"></i>
                </div>

                <div class="form-group password-wrapper">
                    <input type="password" name="confirm_password" id="confirmPasswordInput" class="form-input" placeholder="Confirm Password" required minlength="8">
                    <i class="bi bi-eye-fill password-toggle-icon" id="toggleConfirmPassword" onclick="togglePasswordVisibility('confirmPasswordInput', 'toggleConfirmPassword')"></i>
=======
                <div class="form-group">
                    <input type="password" name="password" class="form-input" placeholder="Password" required>
                    <span class="input-icon"><i class="bi bi-lock-fill"></i></span>
                </div>

                <div class="form-group">
                    <input type="password" name="confirm_password" class="form-input" placeholder="Confirm Password" required>
                    <span class="input-icon"><i class="bi bi-lock-fill"></i></span>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                </div>

                <button type="submit" name="signup" class="signup-button">Sign Up</button>

                <div class="login-link">
                    Already have an account? <a href="login.php">Log in</a>
                </div>
            </form>
        </div>
    </div>
<<<<<<< HEAD

    <script>
        function togglePasswordVisibility(inputId, iconId) {
            const passwordInput = document.getElementById(inputId);
            const toggleIcon = document.getElementById(iconId);
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye-fill');
                toggleIcon.classList.add('bi-eye-slash-fill');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash-fill');
                toggleIcon.classList.add('bi-eye-fill');
            }
        }

        const emailInput = document.getElementById('emailInput');
        const emailHint = document.getElementById('emailHint');
        const signupForm = document.getElementById('signupForm');

        emailInput.addEventListener('input', function() {
            const email = this.value.trim();
            const isStudentEmail = /@g\.batstate-u\.edu\.ph$/i.test(email);
            
            if (email.length > 0 && !isStudentEmail) {
                emailHint.textContent = '⚠️ Students must use @g.batstate-u.edu.ph email';
                emailHint.classList.add('warning');
                this.style.borderColor = '#ff6b6b';
            } else if (email.length > 0 && isStudentEmail) {
                emailHint.textContent = '✓ Valid student email';
                emailHint.style.color = '#00cc66';
                this.style.borderColor = '#00cc66';
            } else {
                emailHint.textContent = 'Students must use @g.batstate-u.edu.ph email';
                emailHint.classList.remove('warning');
                emailHint.style.color = '#666';
                this.style.borderColor = '#e0e0e0';
            }
        });

        signupForm.addEventListener('submit', function(e) {
            const email = emailInput.value.trim();
            const isStudentEmail = /@g\.batstate-u\.edu\.ph$/i.test(email);
            
            if (!isStudentEmail) {
                e.preventDefault();
                alert('Students must register using their @g.batstate-u.edu.ph email address.');
                emailInput.focus();
                return false;
            }

            const password = document.getElementById('passwordInput').value;
            const confirmPassword = document.getElementById('confirmPasswordInput').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                document.getElementById('confirmPasswordInput').focus();
                return false;
            }

            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                document.getElementById('passwordInput').focus();
                return false;
            }
            
            const phone = phoneInput ? phoneInput.value.trim() : '';
            const isValidPhone = /^(09|\+639)\d{9,10}$/.test(phone);
            if (phone && !isValidPhone) {
                e.preventDefault();
                alert('Invalid phone number format. Use 09XXXXXXXXX (11 digits) or +639XXXXXXXXX (13 characters)');
                if (phoneInput) phoneInput.focus();
                return false;
            }
        });

        const passwordInput = document.getElementById('passwordInput');
        const confirmPasswordInput = document.getElementById('confirmPasswordInput');

        if (confirmPasswordInput) {
            confirmPasswordInput.addEventListener('input', function() {
                if (this.value !== passwordInput.value && this.value.length > 0) {
                    this.style.borderColor = '#ff4444';
                } else if (this.value === passwordInput.value && this.value.length > 0) {
                    this.style.borderColor = '#00cc66';
                } else {
                    this.style.borderColor = '#e0e0e0';
                }
            });
        }

        if (passwordInput) {
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                
                if (password.length >= 8) strength++;
                if (password.length >= 12) strength++;
                if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^a-zA-Z0-9]/.test(password)) strength++;

                if (password.length > 0 && password.length < 8) {
                    this.style.borderColor = '#ff4444';
                } else if (strength <= 2) {
                    this.style.borderColor = '#ffaa00';
                } else if (strength >= 3) {
                    this.style.borderColor = '#00cc66';
                }
            });
        }

        const phoneInput = document.querySelector('input[name="phone"]');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                let value = this.value.replace(/[^\d+]/g, '');
                
                if (value.includes('+')) {
                    value = '+' + value.replace(/\+/g, '');
                }
                
                if (value.startsWith('+639')) {
                    if (value.length > 13) {
                        value = value.substring(0, 13);
                    }
                } else if (value.startsWith('09')) {
                    if (value.length > 11) {
                        value = value.substring(0, 11);
                    }
                } else if (value.length > 0 && !value.startsWith('09') && !value.startsWith('+639')) {
                    if (value.startsWith('9') && value.length <= 10) {
                        value = '0' + value;
                    } else if (value.startsWith('639') && value.length <= 12) {
                        value = '+' + value;
                    }
                }
                
                this.value = value;
                
                const isValid = /^(09|\+639)\d{9,10}$/.test(value);
                if (value.length > 0) {
                    this.style.borderColor = isValid ? '#00cc66' : '#ff4444';
                } else {
                    this.style.borderColor = '#e0e0e0';
                }
            });
            
            phoneInput.addEventListener('blur', function() {
                const value = this.value.trim();
                const isValid = /^(09|\+639)\d{9,10}$/.test(value);
                if (value.length > 0 && !isValid) {
                    this.style.borderColor = '#ff4444';
                }
            });
        }
    </script>
</body>
</html>
=======
</body>
</html>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9

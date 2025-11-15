<?php
require_once '../config/database.php';
session_start();

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ✅ Define user info safely
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['fname'] ?? 'Admin';
$last_name = $_SESSION['lname'] ?? '';
$user_role = $_SESSION['role'] ?? 'admin';
$fullName = trim($first_name . ' ' . $last_name);

// Log session info for debugging
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("User info: " . print_r($_SESSION, true));

$loggedInPhysician = [
    'name' => $fullName,
    'id' => $user_id
];

$pdo = getDB();

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    header('Content-Type: application/json');
    
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    try {
        // Validate passwords match
        if ($newPassword !== $confirmPassword) {
            echo json_encode(['success' => false, 'message' => 'New passwords do not match.']);
            exit();
        }
        
        // Validate password length
        if (strlen($newPassword) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters long.']);
            exit();
        }
        
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $userData = $stmt->fetch();
        
        if (!$userData || !password_verify($currentPassword, $userData['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit();
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashedPassword, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Password changed successfully!']);
        exit();
        
    } catch (PDOException $e) {
        error_log("Error changing password: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Student Portal</title>
     <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../employee/nav.css">
    <style>
      * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
      }

      body {
        background-color: #f8f9fa;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; 
        background: #f5f5f5;
        color: #333;
        height: 100vh;
        overflow: hidden;
      }

      .content-area {
        flex: 1;
        padding: 2rem;
        height: calc(100vh - var(--header-h));
        overflow-y: auto;
        background: transparent;
      }

      /* Scrollbar styling */
      .content-area::-webkit-scrollbar {
        width: 8px;
      }
      .content-area::-webkit-scrollbar-thumb {
        background: #bbb;
        border-radius: 4px;
      }
      .content-area::-webkit-scrollbar-thumb:hover {
        background: #999;
      }

      h2 {
        font-weight: 600;
        margin-bottom: 2rem;
      }
      .password-section {
        background: #fff;
        padding: 2.5rem;
        border-radius: 10px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        max-width: 700px;
        margin: 0 auto;
      }

      .password-section h3 {
        font-weight: 600;
        color: #333;
        margin-bottom: 1.5rem;
      }

      .password-section .form-label {
        font-weight: 500;
        color: #333;
      }

      .password-section .form-control {
        border-radius: 6px;
        padding: 0.65rem;
        font-size: 1rem;
        height: 45px;
      }

      .password-section .form-control:focus {
        border-color: var(--accent);
        box-shadow: 0 0 0 0.1rem rgba(139, 0, 0, 0.25);
      }

      .password-section .btn-primary {
        background-color: var(--accent);
        border: none;
        padding: 0.65rem;
        font-weight: 500;
        border-radius: 6px;
        transition: background-color 0.3s;
      }

      .password-section .btn-primary:hover {
        background-color: #6b0000;
      }
        button.btn.btn-primary,
        .btn.btn-primary {
        background-color: #6b0000 !important; /* black background */
        color: #ffffff !important; /* white text */
        border: none !important;
        padding: 0.75rem;
        font-weight: 600;
        font-size: 1rem;
        border-radius: 8px;
        width: 100%;
        transition: all 0.3s ease;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        }

        button.btn.btn-primary:hover,
        .btn.btn-primary:hover {
        background-color: #a51515ff !important; /* dark gray hover */
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
        }

        button.btn.btn-primary:active,
        .btn.btn-primary:active {
        background-color: #111111 !important;
        transform: scale(0.98);
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.4);
        }

      .alert {
        border-radius: 6px;
        margin-bottom: 1rem;
        font-size: 0.95rem;
      }

      .alert-success {
        background-color: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
      }

      .alert-error {
        background-color: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
      }

      .hidden {
        display: none !important;
      }
    </style>
</head>
<body>
    <!-- HEADER -->
    <div class="header">
        <div class="logo-section">
            <div class="logo">
                <img src="../img/bsu-logo.png" alt="University Logo" />
            </div>
            <div class="university-name">
                <h1>Batangas State</h1>
                <h1>University</h1>
            </div>
        </div>

    <div class="header-icons">
      <div class="notification-icon"><i class="bi bi-bell-fill"></i></div>
      <div class="logout-icon" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></div>
    </div>
    </div>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <a href="../student/student_dashboard.php" class="menu-item">Dashboard</a>
            <a href="../student/profile.php" class="menu-item">Profile</a>
            <a href="../student/appointment.php" class="menu-item">Appointment</a>
            <a href="../student/records.php" class="menu-item">Health Records</a>
            <a href="../student/settings.php" class="menu-item active">Settings</a>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <h2>Settings</h2>

            <!-- Change Password Section -->
            <div class="password-section">
                <h3>Change Your Password</h3>

                <div id="passwordSuccessMessage" class="alert alert-success hidden">
                    Password changed successfully!
                </div>

                <div id="passwordErrorMessage" class="alert alert-error hidden">
                    Current password is incorrect.
                </div>

                <form id="passwordForm">
                    <div class="mb-3">
                        <label for="currentPassword" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="currentPassword" placeholder="Enter current password" required>
                    </div>

                    <div class="mb-3">
                        <label for="newPassword" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="newPassword" placeholder="Enter new password" required>
                    </div>

                    <div class="mb-4">
                        <label for="confirmPassword" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirmPassword" placeholder="Re-enter new password" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-key me-2"></i> Change Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../js/logout.js"></script>
    <script>
        
        // Password change functionality
        const passwordForm = document.getElementById('passwordForm');
        const passwordSuccessMessage = document.getElementById('passwordSuccessMessage');
        const passwordErrorMessage = document.getElementById('passwordErrorMessage');

        passwordForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;

            passwordErrorMessage.classList.add('hidden');
            passwordSuccessMessage.classList.add('hidden');

            if (newPassword !== confirmPassword) {
                passwordErrorMessage.textContent = 'New passwords do not match.';
                passwordErrorMessage.classList.remove('hidden');
                return;
            }

            if (newPassword.length < 8) {
                passwordErrorMessage.textContent = 'Password must be at least 8 characters long.';
                passwordErrorMessage.classList.remove('hidden');
                return;
            }

            // Send password change request via AJAX
            try {
                const formData = new FormData();
                formData.append('change_password', '1');
                formData.append('current_password', currentPassword);
                formData.append('new_password', newPassword);
                formData.append('confirm_password', confirmPassword);

                const response = await fetch('settings.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    passwordSuccessMessage.textContent = result.message;
                    passwordSuccessMessage.classList.remove('hidden');
                    passwordForm.reset();

                    // Show success alert with SweetAlert2
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: result.message,
                        timer: 2000,
                        showConfirmButton: false
                    });

                    setTimeout(() => {
                        passwordSuccessMessage.classList.add('hidden');
                    }, 3000);
                } else {
                    passwordErrorMessage.textContent = result.message;
                    passwordErrorMessage.classList.remove('hidden');

                    // Show error alert with SweetAlert2
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: result.message
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                passwordErrorMessage.textContent = 'An error occurred. Please try again.';
                passwordErrorMessage.classList.remove('hidden');

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred. Please try again.'
                });
            }
        });
    </script>
</body>
</html>
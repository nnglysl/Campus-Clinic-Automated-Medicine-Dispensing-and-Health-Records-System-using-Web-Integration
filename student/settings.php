<?php
require_once '../config/database.php';
session_start();

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user = [
    'id' => $_SESSION['user_id'],
    'fname' => $_SESSION['fname'] ?? 'Student',
    'lname' => $_SESSION['lname'] ?? '',
    'role' => $_SESSION['role'] ?? 'student'
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
        $stmt->execute([$user['id']]);
        $userData = $stmt->fetch();
        
        if (!$userData || !password_verify($currentPassword, $userData['password'])) {
            echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
            exit();
        }
        
        // Update password
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashedPassword, $user['id']]);
        
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
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../employee/nav.css">
    <link rel="stylesheet" href="../employee/settings.css">
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
            <div class="logout-icon" onclick="window.location.href='../logout.php'"><i class="bi bi-box-arrow-right"></i></div>
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
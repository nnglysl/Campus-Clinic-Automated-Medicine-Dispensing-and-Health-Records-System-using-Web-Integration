<?php
require_once '../config/database.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}
// ✅ Get user data safely
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['fname'] ?? 'Doctor';
$last_name = $_SESSION['lname'] ?? '';
$user_role = $_SESSION['role'] ?? 'doctor';
$user_name = $first_name; // ✅ FIX: Add this line - it was missing!
$fullName = trim($first_name . ' ' . $last_name);

// Check if user is logged in and has a valid role
// Allow students to access this page
if (!isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

// Log session info for debugging
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("User info: " . print_r($_SESSION, true));

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
    <link rel="stylesheet" href="../css/nav.css">
    <link rel="stylesheet" href="../medical/css/medical_settings.css">
    <link rel="stylesheet" href="../medical/css/responsive.css" />
    <link rel="stylesheet" href="../admin/css/notifications.css" />
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
            <!-- Mobile Menu Icon -->
            <button type="button" class="mobile-menu-icon" id="mobileMenuBtn" aria-label="Toggle navigation menu" aria-expanded="false">
              <i class="bi bi-list"></i>
            </button>
            <?php include 'notification_component.php'; ?>
            <div class="logout-icon" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></div>
        </div>
    </div>

    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <a href="../medical/medical_dashboard.php" class="menu-item">Dashboard</a>
            <a href="../medical/medical_profile.php" class="menu-item ">Profile</a>
            <a href="../medical/medical_patients.php" class="menu-item ">Patients</a>
            <a href="../medical/medical_appointments.php" class="menu-item">Appointments</a>
            <a href="../medical/medical_settings.php" class="menu-item active">Settings</a>

            <div class="user-profile">
                <span><?php echo htmlspecialchars($fullName); ?></span>
            </div>
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

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="../js/logout.js"></script>
    <script src="js/notifications.js"></script>
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
                        showConfirmButton: false,
                        confirmButtonColor: '#8b2332'
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
                        text: result.message,
                        confirmButtonColor: '#8b2332'
                    });
                }
            } catch (error) {
                console.error('Error:', error);
                passwordErrorMessage.textContent = 'An error occurred. Please try again.';
                passwordErrorMessage.classList.remove('hidden');

                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'An error occurred. Please try again.',
                    confirmButtonColor: '#8b2332'
                });
            }
        });
        
        // Initialize notification system after DOM is ready
        $(document).ready(function() {
            if (window.MedicalNotificationSystem) {
                MedicalNotificationSystem.init();
            }
        });
    </script>
    <script src="../js/mobile-menu.js"></script>
</body>
</html>
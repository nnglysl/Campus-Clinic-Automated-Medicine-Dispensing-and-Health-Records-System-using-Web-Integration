<?php
require_once '../config/database.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ✅ Define user info safely
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['fname'] ?? 'Student';
$last_name = $_SESSION['lname'] ?? '';
$user_role = $_SESSION['role'] ?? 'student';
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
    
    // SECURITY: Explicitly ignore any role data that might be sent
    // Only accept password-related fields
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    
    // SECURITY: Log if role data is being sent (for debugging)
    if (isset($_POST['role'])) {
        error_log("WARNING: Role field detected in password change request for user_id: $user_id. Ignoring role data.");
    }
    
    try {
        // Get current user role BEFORE update to verify it doesn't change
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $currentRole = $stmt->fetchColumn();
        
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
        
        // SECURITY: Update ONLY password and updated_at - explicitly exclude role
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashedPassword, $user_id]);
        
        // SECURITY: Verify role hasn't changed after update
        $stmt = $pdo->prepare("SELECT role FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $newRole = $stmt->fetchColumn();
        
        if ($currentRole !== $newRole) {
            // CRITICAL: Role was changed - rollback and log error
            error_log("CRITICAL ERROR: User role changed during password update! User ID: $user_id, Old Role: $currentRole, New Role: $newRole");
            // Restore original role
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$currentRole, $user_id]);
            echo json_encode(['success' => false, 'message' => 'Security error detected. Please contact administrator.']);
            exit();
        }
        
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
    <link href="css/nav.css" rel="stylesheet" />
    <link rel="stylesheet" href="../student/css/settings.css">
    <link rel="stylesheet" href="../admin/css/notifications.css" />
    <link href="css/responsive.css" rel="stylesheet" />
</head>
<body>
    <!-- HEADER -->
    <div class="header">
        <div class="logo-section">
            <div class="logo">
                <img src="../img/bsu-logo.png" alt="University Logo" loading="lazy" />
            </div>
            <div class="university-name">
                <h1>Batangas State</h1>
                <h1>University</h1>
            </div>
        </div>
  <div class="header-icons">
    <!-- ADD MOBILE MENU ICON FIRST -->
    <div class="mobile-menu-icon" id="mobileMenuBtn">
      <i class="bi bi-list"></i>
    </div>
    <?php include 'notification_component.php'; ?>
    <div class="logout-icon" id="logoutBtn">
      <i class="bi bi-box-arrow-right"></i>
    </div>
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

            <div class="user-profile">
                <div class="avatar"></div>
                <span><?php echo htmlspecialchars($fullName); ?></span>
            </div>
        </div>

        <!-- Content Area -->
        <div class="content-area">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12 col-md-10 col-lg-8 col-xl-6 mx-auto">
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
            // SECURITY: Only send password-related fields - explicitly exclude role
            try {
                const formData = new FormData();
                formData.append('change_password', '1');
                formData.append('current_password', currentPassword);
                formData.append('new_password', newPassword);
                formData.append('confirm_password', confirmPassword);
                // NOTE: Intentionally NOT sending role field - password update must not modify role

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
        
        // Initialize notification system
        if (window.StudentNotificationSystem) {
          StudentNotificationSystem.init();
        }
        // ==========================================
// MOBILE MENU TOGGLE SCRIPT
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const sidebar = document.querySelector('.sidebar');
  const body = document.body;
  
  if (mobileMenuBtn && sidebar) {
    // Create overlay element for better click handling
    const overlay = document.createElement('div');
    overlay.className = 'menu-overlay';
    overlay.style.cssText = 'display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(2px); z-index: 998; cursor: pointer;';
    document.body.appendChild(overlay);
    
    // Toggle menu on button click
    mobileMenuBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      const isActive = sidebar.classList.toggle('active');
      body.classList.toggle('menu-open');
      overlay.style.display = isActive ? 'block' : 'none';
      
      // Change icon
      const icon = mobileMenuBtn.querySelector('i');
      if (icon) {
      if (isActive) {
        icon.classList.remove('bi-list');
        icon.classList.add('bi-x-lg');
      } else {
        icon.classList.remove('bi-x-lg');
        icon.classList.add('bi-list');
        }
      }
    });
    
    // Close menu when clicking overlay
    overlay.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      closeMobileMenu();
    });
    
    // Close menu when clicking outside (fallback)
    document.addEventListener('click', (e) => {
      if (sidebar.classList.contains('active') && 
          !sidebar.contains(e.target) && 
          !mobileMenuBtn.contains(e.target) &&
          !overlay.contains(e.target)) {
        closeMobileMenu();
      }
    });
    
    // Close menu when clicking menu items
    document.querySelectorAll('.sidebar .menu-item').forEach(item => {
      item.addEventListener('click', () => {
        closeMobileMenu();
      });
    });
    
    // Function to close mobile menu
    function closeMobileMenu() {
      sidebar.classList.remove('active');
      body.classList.remove('menu-open');
      overlay.style.display = 'none';
      const icon = mobileMenuBtn.querySelector('i');
      if (icon) {
      icon.classList.remove('bi-x-lg');
      icon.classList.add('bi-list');
      }
    }
    
    // Prevent clicks inside sidebar from closing it
    sidebar.addEventListener('click', (e) => {
      e.stopPropagation();
    });
    
    // Close menu on escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && sidebar.classList.contains('active')) {
        closeMobileMenu();
      }
    });
  }
});
    </script>
</body>
</html>
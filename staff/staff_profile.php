<?php
session_start();
require_once('../db.php');

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: /auth/login.php");
    exit();
}

// ✅ Define user info safely
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['fname'] ?? 'Staff';
$last_name = $_SESSION['lname'] ?? '';
$user_role = $_SESSION['role'] ?? 'staff';
$fullName = trim($first_name . ' ' . $last_name);

// Log session info for debugging
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("User info: " . print_r($_SESSION, true));

$loggedInPhysician = [
    'name' => $fullName,
    'id' => $user_id
];

// Check if user is an employee
if (!in_array($_SESSION['role'], ['doctor', 'dentist', 'nurse', 'staff', 'admin'])) {
    header('Location: ../auth/login.php');
    exit;
}

// Fetch user profile data
try {
    // Get user data from users table
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("User not found with ID: " . $user_id);
        header('Location: ../auth/login.php');
        exit;
    }

    // Get employee data using user_id foreign key
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $employee = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($employee) {
        // Map employee columns to expected names in the form
        $user['position'] = $employee['role'] ?? ucfirst($user['role'] ?? 'Staff');
        $user['employee_id'] = $employee['id'] ?? 'N/A';
        $user['hire_date'] = $employee['created_at'] ?? null;
        $user['employee_username'] = $employee['username'] ?? $user['email'];
        
        // Use employee data as primary if available
        if (empty($user['phone']) && !empty($employee['phone'])) {
            $user['phone'] = $employee['phone'];
        }
        if (empty($user['address']) && !empty($employee['address'])) {
            $user['address'] = $employee['address'];
        }
    } else {
        // If no employee record found, try to match by email as fallback
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ?");
        $stmt->execute([$user['email']]);
        $employee = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($employee) {
            // Link them for future queries
            $updateStmt = $pdo->prepare("UPDATE employees SET user_id = ? WHERE id = ?");
            $updateStmt->execute([$user_id, $employee['id']]);
            
            $user['position'] = $employee['role'] ?? ucfirst($user['role'] ?? 'Staff');
            $user['employee_id'] = $employee['id'];
            $user['hire_date'] = $employee['created_at'];
            
            error_log("Auto-linked employee ID " . $employee['id'] . " to user ID " . $user_id);
        } else {
            // Set defaults if no employee record exists
            $user['position'] = ucfirst($user['role'] ?? 'Staff');
            $user['employee_id'] = 'N/A';
            $user['hire_date'] = null;
            
            error_log("No employee record found for user ID: " . $user_id);
        }
    }

    // Calculate age from date of birth
    if (!empty($user['date_of_birth'])) {
        $dob = new DateTime($user['date_of_birth']);
        $now = new DateTime();
        $age = $now->diff($dob)->y;
    } else {
        $age = '';
    }

    // Format date of birth for display
    $formattedDob = !empty($user['date_of_birth']) ? date('F d, Y', strtotime($user['date_of_birth'])) : '';

} catch (PDOException $e) {
    error_log("Error fetching user profile: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    die("Error loading profile. Please try again later. Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - BSU Clinic</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../css/nav.css">
    <link rel="stylesheet" href="../staff/css/staff_profile.css">
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
      <a href="../staff/staff_dashboard.php" class="menu-item">Dashboard</a>
      <a href="../staff/staff_profile.php" class="menu-item active">Profile</a>
      <a href="../staff/staff_patients.php" class="menu-item ">Patients</a>
      <a href="../staff/settings.php" class="menu-item">Settings</a>
      
      <div class="user-profile">
        <div class="avatar"></div>
        <span><?php echo htmlspecialchars($fullName); ?></span>
      </div>
    </div>

      <!-- Content Area -->
    <div class="content-area">
      <h2>Profile</h2>

      <!-- Tabs -->
      <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item" role="presentation">
          <a class="nav-link active" id="attendance-tab" data-tab="attendance" href="#">Attendance</a>
        </li>
        <li class="nav-item" role="presentation">
          <a class="nav-link" id="profile-tab" data-tab="profile" href="#">Profile</a>
        </li>
      </ul>

      <!-- Tab Content -->
      <div class="tab-content">
        <!-- TIME IN & OUT TAB -->
        <div class="tab-pane active" id="attendance">
          <div class="card p-4 text-center">
            <h3 class="mb-3">Daily Attendance</h3>

            <div id="currentTime" style="font-size: 2rem; font-weight: 600; margin-bottom: 10px;"></div>
            <div id="currentDate" style="color: gray; margin-bottom: 1rem;"></div>

            <p><strong>Time In:</strong> <span id="displayTimeIn">--:-- --</span></p>
            <p><strong>Time Out:</strong> <span id="displayTimeOut">--:-- --</span></p>

            <div class="btn-time-group" style="display: flex; gap: 1rem; justify-content: center;">
              <button id="timeInBtn" class="btn btn-edit px-4 py-2 rounded">
                <i class="fas fa-sign-in-alt me-2"></i>Time In
              </button>
              <button id="timeOutBtn" class="btn btn-cancel px-4 py-2 rounded">
                <i class="fas fa-sign-out-alt me-2"></i>Time Out
              </button>
            </div>
          </div>

          <div class="card mt-4 p-3">
            <h5><i class="fas fa-history me-2"></i> Attendance History</h5>
            <div id="attendanceList" class="mt-2"></div>
          </div>
        </div>

        <!-- Profile Tab -->
        <div class="tab-pane" id="profile">
          <div class="profile-section">
            <div class="profile-header">
              <h3>Personal Information</h3>
              <div>
                <button class="btn-edit" id="editBtn">
                  <i class="fas fa-edit"></i> Edit Profile
                </button>
                <button class="btn-save hidden" id="saveBtn">
                  <i class="fas fa-save"></i> Save Changes
                </button>
                <button class="btn-cancel hidden" id="cancelBtn">
                  <i class="fas fa-times"></i> Cancel
                </button>
              </div>
            </div>

            <div id="successMessage" class="alert alert-success hidden">
              <i class="fas fa-check-circle me-2"></i>
              Profile updated successfully!
            </div>

            <form id="profileForm">
              <input type="hidden" id="userId" value="<?php echo $user_id; ?>">
              <div class="row">
                <div class="col-md-4 mb-3">
                  <label for="firstName" class="form-label">First Name</label>
                  <input type="text" class="form-control" id="firstName" value="<?php echo htmlspecialchars($user['fname'] ?? ''); ?>" disabled>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="middleName" class="form-label">Middle Name</label>
                  <input type="text" class="form-control" id="middleName" value="<?php echo htmlspecialchars($user['mname'] ?? ''); ?>" disabled>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="lastName" class="form-label">Last Name</label>
                  <input type="text" class="form-control" id="lastName" value="<?php echo htmlspecialchars($user['lname'] ?? ''); ?>" disabled>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="dateofBirth" class="form-label">Date of Birth</label>
                  <input type="date" class="form-control" id="dateofBirth" value="<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>" disabled>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="age" class="form-label">Age</label>
                  <input type="text" class="form-control" id="age" value="<?php echo $age; ?>" disabled readonly>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="gender" class="form-label">Gender</label>
                  <select class="form-control" id="gender" disabled>
                    <option value="Male" <?php echo ($user['gender'] ?? '') === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo ($user['gender'] ?? '') === 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo ($user['gender'] ?? '') === 'Other' ? 'selected' : ''; ?>>Other</option>
                  </select>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="address" class="form-label">Address</label>
                  <input type="text" class="form-control" id="address" value="<?php echo htmlspecialchars($user['address'] ?? ''); ?>" disabled>
                </div>
                <div class="col-md-4 mb-3">
                  <label for="bloodType" class="form-label">Blood Type</label>
                  <select class="form-control" id="bloodType" disabled>
                    <option value="">Select</option>
                    <option value="A+" <?php echo ($user['blood_type'] ?? '') === 'A+' ? 'selected' : ''; ?>>A+</option>
                    <option value="A-" <?php echo ($user['blood_type'] ?? '') === 'A-' ? 'selected' : ''; ?>>A-</option>
                    <option value="B+" <?php echo ($user['blood_type'] ?? '') === 'B+' ? 'selected' : ''; ?>>B+</option>
                    <option value="B-" <?php echo ($user['blood_type'] ?? '') === 'B-' ? 'selected' : ''; ?>>B-</option>
                    <option value="AB+" <?php echo ($user['blood_type'] ?? '') === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                    <option value="AB-" <?php echo ($user['blood_type'] ?? '') === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                    <option value="O+" <?php echo ($user['blood_type'] ?? '') === 'O+' ? 'selected' : ''; ?>>O+</option>
                    <option value="O-" <?php echo ($user['blood_type'] ?? '') === 'O-' ? 'selected' : ''; ?>>O-</option>
                  </select>
                </div>
              </div>

              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="email" class="form-label">Email Address</label>
                  <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" disabled>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="phone" class="form-label">Phone Number</label>
                  <input type="tel" class="form-control" id="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" disabled>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="position" class="form-label">Position</label>
                  <input type="text" class="form-control" id="position" value="<?php echo htmlspecialchars($user['position'] ?? ucfirst($user['role'] ?? 'Staff')); ?>" disabled>
                </div>
                <div class="col-md-6 mb-3">
                  <label for="employeeId" class="form-label">Employee ID</label>
                  <input type="text" class="form-control" id="employeeId" value="<?php echo htmlspecialchars($user['employee_id'] ?? 'N/A'); ?>" disabled>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script> 
  <script>
    // User data from PHP
    const userData = {
      id: <?php echo $user_id; ?>,
      firstName: "<?php echo htmlspecialchars($user['fname'] ?? ''); ?>",
      middleName: "<?php echo htmlspecialchars($user['mname'] ?? ''); ?>",
      lastName: "<?php echo htmlspecialchars($user['lname'] ?? ''); ?>",
      email: "<?php echo htmlspecialchars($user['email'] ?? ''); ?>",
      phone: "<?php echo htmlspecialchars($user['phone'] ?? ''); ?>",
      address: "<?php echo htmlspecialchars($user['address'] ?? ''); ?>",
      dateOfBirth: "<?php echo htmlspecialchars($user['date_of_birth'] ?? ''); ?>",
      gender: "<?php echo htmlspecialchars($user['gender'] ?? ''); ?>",
      bloodType: "<?php echo htmlspecialchars($user['blood_type'] ?? ''); ?>"
    };

    // ===== TAB SWITCHING =====
    document.addEventListener('DOMContentLoaded', function() {
        // Get all nav links and tab panes
        const navLinks = document.querySelectorAll('.nav-link');
        const tabPanes = document.querySelectorAll('.tab-pane');
        
        console.log('Nav links found:', navLinks.length);
        console.log('Tab panes found:', tabPanes.length);
        
        // Add click event to each nav link
        navLinks.forEach(navLink => {
            navLink.addEventListener('click', function(e) {
                e.preventDefault();
                console.log('Tab clicked:', this.id);
                
                // Remove active class from all nav links
                navLinks.forEach(link => link.classList.remove('active'));
                
                // Add active class to clicked nav link
                this.classList.add('active');
                
                // Get the target tab from data-tab attribute
                const targetTab = this.getAttribute('data-tab');
                console.log('Target tab:', targetTab);
                
                // Hide all tab panes
                tabPanes.forEach(pane => {
                    pane.classList.remove('active');
                    console.log('Hiding pane:', pane.id);
                });
                
                // Show the target tab pane
                const targetPane = document.getElementById(targetTab);
                if (targetPane) {
                    targetPane.classList.add('active');
                    console.log('Showing pane:', targetTab);
                } else {
                    console.error('Tab pane not found:', targetTab);
                }
            });
        });
        
        // Set the first tab as active by default
        if (navLinks.length > 0 && tabPanes.length > 0) {
            navLinks[0].classList.add('active');
            tabPanes[0].classList.add('active');
            console.log('Default tab activated');
        }

        // Initialize attendance
        loadAttendanceRecords();
    });

    // ===== PROFILE EDIT FUNCTIONALITY =====
    const editBtn = document.getElementById('editBtn');
    const saveBtn = document.getElementById('saveBtn');
    const cancelBtn = document.getElementById('cancelBtn');
    const formInputs = document.querySelectorAll('#profileForm input, #profileForm select');
    const successMessage = document.getElementById('successMessage');
    let originalValues = {};

    // Auto-calculate age when date of birth changes
    document.getElementById('dateofBirth').addEventListener('change', function() {
      const dob = new Date(this.value);
      const today = new Date();
      let age = today.getFullYear() - dob.getFullYear();
      const monthDiff = today.getMonth() - dob.getMonth();
      
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
        age--;
      }
      
      document.getElementById('age').value = age;
    });

    editBtn.addEventListener('click', function() {
      formInputs.forEach(input => {
        originalValues[input.id] = input.value;
        if (input.id !== 'employeeId' && input.id !== 'position' && input.id !== 'age') {
          input.disabled = false;
        }
      });
      editBtn.classList.add('hidden');
      saveBtn.classList.remove('hidden');
      cancelBtn.classList.remove('hidden');
      successMessage.classList.add('hidden');
    });

    saveBtn.addEventListener('click', async function() {
      const formData = {
        userId: document.getElementById('userId').value,
        firstName: document.getElementById('firstName').value,
        middleName: document.getElementById('middleName').value,
        lastName: document.getElementById('lastName').value,
        dateOfBirth: document.getElementById('dateofBirth').value,
        gender: document.getElementById('gender').value,
        address: document.getElementById('address').value,
        bloodType: document.getElementById('bloodType').value,
        email: document.getElementById('email').value,
        phone: document.getElementById('phone').value
      };

      try {
        const response = await fetch('../crud/update_profile.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(formData)
        });

        const result = await response.json();

        if (result.success) {
          formInputs.forEach(input => input.disabled = true);
          editBtn.classList.remove('hidden');
          saveBtn.classList.add('hidden');
          cancelBtn.classList.add('hidden');
          successMessage.classList.remove('hidden');
          setTimeout(() => successMessage.classList.add('hidden'), 3000);
          
          const fullName = `${formData.firstName} ${formData.middleName} ${formData.lastName}`.trim();
          document.querySelector('.user-profile span').textContent = fullName;
        } else {
          Swal.fire('Error', result.error || 'Failed to update profile', 'error');
        }
      } catch (error) {
        console.error('Error:', error);
        Swal.fire('Error', 'Failed to update profile. Please try again.', 'error');
      }
    });

    cancelBtn.addEventListener('click', function() {
      formInputs.forEach(input => {
        input.value = originalValues[input.id];
        input.disabled = true;
      });
      editBtn.classList.remove('hidden');
      saveBtn.classList.add('hidden');
      cancelBtn.classList.add('hidden');
      successMessage.classList.add('hidden');
    });

    // ===== ATTENDANCE FUNCTIONALITY =====
    let attendanceRecords = [];

    async function loadAttendanceRecords() {
      try {
        const response = await fetch('../crud/attendance_handler.php?action=list');
        const result = await response.json();
        
        if (result.success) {
          attendanceRecords = result.data;
          updateAttendance();
          renderAttendanceHistory();
        }
      } catch (error) {
        console.error('Error loading attendance:', error);
      }
    }

    function updateClock() {
      const now = new Date();
      document.getElementById("currentTime").textContent = now.toLocaleTimeString();
      document.getElementById("currentDate").textContent = now.toLocaleDateString();
    }
    setInterval(updateClock, 1000);
    updateClock();

    function updateAttendance() {
      const today = new Date().toLocaleDateString('en-US');
      
      const record = attendanceRecords.find(r => {
        const recordDate = r.date.replace(/^0/, '').replace(/\/0/g, '/');
        const todayFormatted = today.replace(/^0/, '').replace(/\/0/g, '/');
        return recordDate === todayFormatted;
      });
      
      if (record) {
        document.getElementById("displayTimeIn").textContent = record.timeIn;
        document.getElementById("displayTimeOut").textContent = record.timeOut || "--:-- --";
      } else {
        document.getElementById("displayTimeIn").textContent = "--:-- --";
        document.getElementById("displayTimeOut").textContent = "--:-- --";
      }
    }

    function renderAttendanceHistory() {
      const container = document.getElementById("attendanceList");
      container.innerHTML = "";
      
      if (attendanceRecords.length === 0) {
        container.innerHTML = '<p class="text-muted text-center">No attendance records yet.</p>';
        return;
      }
      
      attendanceRecords.forEach(record => {
        const div = document.createElement("div");
        div.className = "p-2 border-bottom";
        div.innerHTML = `<i class="fas fa-calendar-day me-2"></i>${record.date} — Time In: ${record.timeIn} | Time Out: ${record.timeOut || "----"}`;
        container.appendChild(div);
      });
    }

    document.getElementById("timeInBtn").addEventListener("click", async function() {
      const now = new Date();
      const today = now.toLocaleDateString();

      const existing = attendanceRecords.find(r => r.date === today);
      if (existing && existing.timeIn) {
        Swal.fire("Already Timed In", "You have already timed in today!", "warning");
        return;
      }

      try {
        const response = await fetch('../crud/attendance_handler.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'timeIn' })
        });

        const result = await response.json();

        if (result.success) {
          await loadAttendanceRecords();
          Swal.fire("Success!", "You have timed in successfully.", "success");
        } else {
          Swal.fire("Error", result.error || "Failed to time in", "error");
        }
      } catch (error) {
        console.error('Error:', error);
        Swal.fire("Error", "Failed to time in. Please try again.", "error");
      }
    });

    document.getElementById("timeOutBtn").addEventListener("click", async function() {
      try {
        const response = await fetch('../crud/attendance_handler.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ action: 'timeOut' })
        });

        const result = await response.json();

        if (result.success) {
          await loadAttendanceRecords();
          Swal.fire("Success!", "You have timed out successfully.", "success");
        } else {
          Swal.fire("Error", result.error || "Failed to time out", "error");
        }
      } catch (error) {
        console.error('Error:', error);
        Swal.fire("Error", "Failed to time out. Please try again.", "error");
      }
    });
  </script>
</body>
</html>
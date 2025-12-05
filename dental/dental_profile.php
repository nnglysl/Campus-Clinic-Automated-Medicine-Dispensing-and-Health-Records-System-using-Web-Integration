<?php
session_start();
require_once('../db.php');

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
$first_name = $_SESSION['fname'] ?? 'Dentist';
$last_name = $_SESSION['lname'] ?? '';
$user_role = $_SESSION['role'] ?? 'dentist';
$fullName = trim($first_name . ' ' . $last_name);

// Log session info for debugging
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("User info: " . print_r($_SESSION, true));

$loggedInPhysician = [
    'name' => $fullName,
    'id' => $user_id
];

// Check if user is an employee
if (!in_array($_SESSION['role'], ['employee', 'doctor', 'dentist', 'nurse', 'staff', 'admin'])) {
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
        // Username removed - not used in system
        
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
    <link rel="stylesheet" href="../dental/css/dental_profile.css">
    <link rel="stylesheet" href="../dental/css/responsive.css" />
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
      <a href="../dental/dental_dashboard.php" class="menu-item">Dashboard</a>
      <a href="../dental/dental_profile.php" class="menu-item active">Profile</a>
      <a href="../dental/dental_patients.php" class="menu-item ">Patients</a>
      <a href="../dental/dental_appointments.php" class="menu-item">Appointments</a>
      <a href="../dental/dental_settings.php" class="menu-item">Settings</a>
      
      <div class="user-profile">
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
        <li class="nav-item" role="presentation">
          <a class="nav-link" id="schedule-tab" data-tab="schedule" href="#">Make a schedule</a>
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

<!-- Schedule Tab - NEW CALENDAR -->
<div class="tab-pane" id="schedule">
  <?php
  // Determine department based on role
  $department = ($_SESSION['role'] === 'dentist') ? 'Dental' : 'Medical';
  $departmentClass = ($_SESSION['role'] === 'dentist') ? 'dental' : 'medical';
  $departmentIcon = ($_SESSION['role'] === 'dentist') ? 'bi-heart-pulse-fill' : 'bi-hospital-fill';
  ?>
  
  <!-- Department Indicator -->
  <div class="department-indicator <?php echo $departmentClass; ?>">
    <i class="bi <?php echo $departmentIcon; ?>"></i>
    <div>
      <h5><?php echo $department; ?> Department</h5>
      <small>Manage your availability for <?php echo strtolower($department); ?> appointments</small>
    </div>
  </div>
  
  <div class="calendar-wrapper">
    <!-- Calendar content starts here -->
    <div class="calendar-section" id="calendarSection">
      <div class="calendar-header">
        <h1 class="calendar-title">Doctor Schedule Calendar</h1>
                <div class="nav-buttons">
                  <button class="nav-btn" id="prevBtn">◀</button>
                  <button class="today-btn" id="todayBtn">Today</button>
                  <button class="nav-btn" id="nextBtn">▶</button>
                </div>
              </div>

              <div class="month-year" id="monthYear"></div>

              <div class="legend">
                <div class="legend-item">
                  <div class="legend-dot available"></div>
                  <span>Available</span>
                </div>
                <div class="legend-item">
                  <div class="legend-dot unavailable"></div>
                  <span>Unavailable</span>
                </div>
              </div>

              <div class="info-banner">
                💡 <strong>Tip:</strong> Click on any date to manage your availability for that day. Weekends are automatically disabled.
              </div>

              <div class="calendar-grid">
                <div class="weekdays">
                  <div class="weekday">Mon</div>
                  <div class="weekday">Tue</div>
                  <div class="weekday">Wed</div>
                  <div class="weekday">Thu</div>
                  <div class="weekday">Fri</div>
                  <div class="weekday">Sat</div>
                  <div class="weekday">Sun</div>
                </div>
                <div class="days-grid" id="daysGrid"></div>
              </div>
            </div>

            <!-- Right: Time Section -->
            <div class="time-section">
              <div class="time-header">
                <div class="time-title">Time</div>
                <div class="selected-date" id="selectedDate">Select a date</div>
              </div>
              <div id="timeSlotContainer">
                <div class="no-selection">Select a date to view time slots</div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Side Drawer -->
  <div class="drawer-overlay" id="drawerOverlay"></div>
  <div class="side-drawer" id="sideDrawer">
    <button class="close-drawer" id="closeDrawer">×</button>
    
    <div class="drawer-header">
      <div class="drawer-title">Manage Availability</div>
      <div class="drawer-subtitle" id="drawerDate">November 10</div>
    </div>

    <div class="drawer-content">
      <div class="section">
        <div class="section-title">Availability Options</div>
        
        <div class="option-card" id="optionWholeDay" data-mode="whole-day">
          <div class="option-header">
            <span>✅</span>
            <span>Available for the Whole Day</span>
          </div>
          <div class="option-desc">All time slots for this day are open for appointments.</div>
        </div>

        <div class="option-card" id="optionUnavailable" data-mode="unavailable-day">
          <div class="option-header">
            <span>🚫</span>
            <span>Unavailable for the Whole Day</span>
          </div>
          <div class="option-desc">Mark entire day as unavailable (e.g., vacation, conference).</div>
        </div>

        <div class="option-card" id="optionCustomize" data-mode="customize">
          <div class="option-header">
            <span>🕒</span>
            <span>Customize Availability</span>
          </div>
          <div class="option-desc">Select or drag across time slots to mark them as available or unavailable.</div>
        </div>

        <div id="reasonField" class="form-group" style="display: none; margin-top: 12px;">
          <label style="font-size: 14px; font-weight: 500; margin-bottom: 6px; display: block;">Reason (Optional)</label>
          <input type="text" id="reasonInput" placeholder="e.g., Conference, Personal Leave, Vacation" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 6px; font-size: 14px;">
        </div>
      </div>

      <div class="section">
        <div class="section-title">Schedule Timeline</div>
        <div class="timeline">
          <div class="timeline-header">
            <span>🕗 8:00 AM — 5:00 PM</span>
          </div>
          <div class="timeline-slots" id="timelineSlots"></div>
        </div>
        <div class="tip-box" id="tipBox" style="display: none;">
          💡 <strong>Tip:</strong> Click on any time slot to toggle between Available → Unavailable → Not Set. You can also drag across multiple slots in Customize mode.
        </div>
      </div>
    </div>

    <div class="drawer-actions">
      <button class="btn-cal btn-secondary-cal" id="backBtn">🔙 Back to Calendar</button>
      <button class="btn-cal btn-primary-cal" id="saveChanges">💾 Save Changes</button>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script>
  <script src="js/notifications.js"></script> 
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

    // ===== TAB SWITCHING - FIXED VERSION =====
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
                    
                    // Initialize calendar when schedule tab is clicked
                    if (targetTab === 'schedule') {
                        setTimeout(() => {
                            console.log('Initializing calendar...');
                            if (!document.getElementById('daysGrid').children.length) {
                                initCalendar();
                            }
                        }, 100);
                    }
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

    // ===== NEW CALENDAR FUNCTIONALITY =====
    let currentDate = new Date();
    let selectedDate = null;
    let schedules = {};
    let drawerMode = null;
    let isDragging = false;
    let dragMode = null;

    // Time slots in 30-minute intervals from 8:00 AM to 5:00 PM
    const hours = [];
    for (let hour = 8; hour <= 17; hour++) {
      hours.push(hour + 0.0); // :00
      if (hour < 17) {
        hours.push(hour + 0.5); // :30
      }
    }

    function initCalendar() {
      renderCalendar();
      initCalendarEventListeners();
      loadSchedules();
    }

    function initCalendarEventListeners() {
      document.getElementById('prevBtn').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
      });

      document.getElementById('nextBtn').addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
      });

      document.getElementById('todayBtn').addEventListener('click', () => {
        currentDate = new Date();
        renderCalendar();
        selectDate(currentDate);
      });

      document.getElementById('closeDrawer').addEventListener('click', closeDrawer);
      document.getElementById('backBtn').addEventListener('click', closeDrawer);
      document.getElementById('drawerOverlay').addEventListener('click', closeDrawer);

      document.getElementById('optionWholeDay').addEventListener('click', () => {
        setDrawerMode('whole-day');
      });

      document.getElementById('optionUnavailable').addEventListener('click', () => {
        setDrawerMode('unavailable-day');
      });

      document.getElementById('optionCustomize').addEventListener('click', () => {
        setDrawerMode('customize');
      });

      document.getElementById('saveChanges').addEventListener('click', saveScheduleChanges);

      document.addEventListener('mouseup', () => {
        isDragging = false;
        dragMode = null;
      });
    }

    function renderCalendar() {
      const year = currentDate.getFullYear();
      const month = currentDate.getMonth();

      document.getElementById('monthYear').textContent = 
        currentDate.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });

      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const startDay = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1;

      const daysGrid = document.getElementById('daysGrid');
      daysGrid.innerHTML = '';

      const prevMonthLastDay = new Date(year, month, 0).getDate();
      for (let i = startDay - 1; i >= 0; i--) {
        const day = prevMonthLastDay - i;
        const cell = createDayCell(day, true, false);
        daysGrid.appendChild(cell);
      }

      for (let day = 1; day <= lastDay.getDate(); day++) {
        const date = new Date(year, month, day);
        const isWeekend = date.getDay() === 0 || date.getDay() === 6;
        const isToday = isToday_helper(date);
        const cell = createDayCell(day, false, isWeekend, isToday, date);
        daysGrid.appendChild(cell);
      }

      const remainingCells = 42 - daysGrid.children.length;
      for (let day = 1; day <= remainingCells; day++) {
        const cell = createDayCell(day, true, false);
        daysGrid.appendChild(cell);
      }
    }

    function createDayCell(day, isOtherMonth, isWeekend, isToday = false, date = null) {
      const cell = document.createElement('div');
      cell.className = 'day-cell';
      
      if (isOtherMonth) cell.classList.add('other-month');
      if (isWeekend) cell.classList.add('weekend');
      if (isToday) cell.classList.add('today');

      const dayNumber = document.createElement('div');
      dayNumber.className = 'day-number';
      dayNumber.textContent = day;
      cell.appendChild(dayNumber);

      if (date && !isOtherMonth) {
        const dateKey = formatDate(date);
        const daySchedules = schedules[dateKey] || {};
        
        const schedulesContainer = document.createElement('div');
        schedulesContainer.className = 'day-schedules';
        
        if (daySchedules.wholeDay) {
          const indicator = document.createElement('div');
          indicator.className = 'schedule-indicator available';
          schedulesContainer.appendChild(indicator);
        } else if (daySchedules.unavailableDay) {
          const indicator = document.createElement('div');
          indicator.className = 'schedule-indicator unavailable';
          schedulesContainer.appendChild(indicator);
        } else if (daySchedules.slots) {
          const available = Object.values(daySchedules.slots).filter(s => s === 'available').length;
          const unavailable = Object.values(daySchedules.slots).filter(s => s === 'unavailable').length;
          
          if (available > 0) {
            const indicator = document.createElement('div');
            indicator.className = 'schedule-indicator available';
            schedulesContainer.appendChild(indicator);
          }
          if (unavailable > 0) {
            const indicator = document.createElement('div');
            indicator.className = 'schedule-indicator unavailable';
            schedulesContainer.appendChild(indicator);
          }
        }
        
        cell.appendChild(schedulesContainer);

        cell.addEventListener('click', () => {
          if (!isWeekend) {
            openDrawer(date);
          }
        });
      }

      return cell;
    }

    function selectDate(date) {
      selectedDate = date;
      
      document.querySelectorAll('.day-cell').forEach(cell => {
        cell.classList.remove('selected');
      });

      document.getElementById('selectedDate').textContent = 
        date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
      
      renderTimeSlots(date);
    }

    function renderTimeSlots(date) {
      const container = document.getElementById('timeSlotContainer');
      const dateKey = formatDate(date);
      const daySchedule = schedules[dateKey] || {};
      
      container.innerHTML = '';

      hours.forEach(hour => {
        const timeSlot = document.createElement('div');
        timeSlot.className = 'time-slot';
        
        const timeStr = formatHour(hour);
        timeSlot.textContent = timeStr;

        if (daySchedule.wholeDay) {
          timeSlot.classList.add('has-schedule');
        } else if (daySchedule.unavailableDay) {
          timeSlot.classList.add('unavailable');
        } else if (daySchedule.slots && daySchedule.slots[hour]) {
          timeSlot.classList.add(daySchedule.slots[hour] === 'available' ? 'has-schedule' : 'unavailable');
        }

        timeSlot.addEventListener('click', () => {
          openDrawer(date);
        });

        container.appendChild(timeSlot);
      });
    }

    function openDrawer(date) {
      selectedDate = date;
      selectDate(date);
      
      document.getElementById('drawerDate').textContent = 
        date.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' });
      
      const dateKey = formatDate(date);
      const daySchedule = schedules[dateKey] || {};
      
      if (daySchedule.wholeDay) {
        setDrawerMode('whole-day');
      } else if (daySchedule.unavailableDay) {
        setDrawerMode('unavailable-day');
      } else {
        setDrawerMode('customize');
      }
      
      renderTimelineSlots(date);
      
      document.getElementById('sideDrawer').classList.add('open');
      document.getElementById('drawerOverlay').classList.add('active');
      document.getElementById('calendarSection').classList.add('drawer-open');
    }

    function closeDrawer() {
      document.getElementById('sideDrawer').classList.remove('open');
      document.getElementById('drawerOverlay').classList.remove('active');
      document.getElementById('calendarSection').classList.remove('drawer-open');
    }

    function setDrawerMode(mode) {
      drawerMode = mode;
      
      document.querySelectorAll('.option-card').forEach(card => {
        card.classList.remove('active');
      });
      
      document.querySelector(`[data-mode="${mode}"]`).classList.add('active');
      
      const reasonField = document.getElementById('reasonField');
      const tipBox = document.getElementById('tipBox');
      
      if (mode === 'whole-day') {
        const dateKey = formatDate(selectedDate);
        schedules[dateKey] = { wholeDay: true };
        renderTimelineSlots(selectedDate);
        tipBox.style.display = 'none';
        reasonField.style.display = 'none';
      } else if (mode === 'unavailable-day') {
        const dateKey = formatDate(selectedDate);
        schedules[dateKey] = { unavailableDay: true };
        renderTimelineSlots(selectedDate);
        tipBox.style.display = 'none';
        reasonField.style.display = 'block';
      } else {
        const dateKey = formatDate(selectedDate);
        if (schedules[dateKey]?.wholeDay || schedules[dateKey]?.unavailableDay) {
          schedules[dateKey] = { slots: {} };
        }
        renderTimelineSlots(selectedDate);
        tipBox.style.display = 'block';
        reasonField.style.display = 'none';
      }
    }

    function renderTimelineSlots(date) {
      const container = document.getElementById('timelineSlots');
      const dateKey = formatDate(date);
      const daySchedule = schedules[dateKey] || {};
      
      container.innerHTML = '';

      hours.forEach(hour => {
        const slot = document.createElement('div');
        slot.className = 'timeline-slot';
        slot.dataset.hour = hour;
        
        let status = 'neutral';
        if (daySchedule.wholeDay) {
          status = 'available';
        } else if (daySchedule.unavailableDay) {
          status = 'unavailable';
        } else if (daySchedule.slots && daySchedule.slots[hour]) {
          status = daySchedule.slots[hour];
        }
        
        slot.classList.add(status);
        
        const timeDiv = document.createElement('div');
        timeDiv.className = 'slot-time';
        timeDiv.textContent = formatHour(hour);
        
        const statusDiv = document.createElement('div');
        statusDiv.className = 'slot-status';
        
        const indicator = document.createElement('div');
        indicator.className = `status-indicator ${status}`;
        
        const statusText = document.createElement('span');
        statusText.textContent = status === 'neutral' ? 'Not Set' : status.charAt(0).toUpperCase() + status.slice(1);
        
        statusDiv.appendChild(indicator);
        statusDiv.appendChild(statusText);
        
        slot.appendChild(timeDiv);
        slot.appendChild(statusDiv);
        
        if (status !== 'booked') {
          slot.addEventListener('click', (e) => {
            if (!isDragging) {
              toggleSlotStatusClick(slot);
            }
          });
          
          if (drawerMode === 'customize') {
            slot.addEventListener('mousedown', (e) => {
              e.preventDefault();
              isDragging = true;
              const currentStatus = getSlotStatus(slot);
              dragMode = currentStatus === 'available' ? 'unavailable' : 'available';
              toggleSlotStatus(slot, dragMode);
            });
            
            slot.addEventListener('mouseenter', () => {
              if (isDragging && dragMode) {
                toggleSlotStatus(slot, dragMode);
              }
            });
          }
        }
        
        container.appendChild(slot);
      });
    }

    function getSlotStatus(slot) {
      if (slot.classList.contains('available')) return 'available';
      if (slot.classList.contains('unavailable')) return 'unavailable';
      return 'neutral';
    }

    function toggleSlotStatusClick(slot) {
      const currentStatus = getSlotStatus(slot);
      let newStatus;
      
      if (currentStatus === 'neutral') {
        newStatus = 'available';
      } else if (currentStatus === 'available') {
        newStatus = 'unavailable';
      } else {
        newStatus = 'neutral';
      }
      
      toggleSlotStatus(slot, newStatus);
    }

    function toggleSlotStatus(slot, newStatus) {
      if (slot.classList.contains('booked')) return;
      
      slot.classList.remove('available', 'unavailable', 'neutral');
      slot.classList.add(newStatus);
      
      const indicator = slot.querySelector('.status-indicator');
      const statusText = slot.querySelector('.slot-status span');
      
      indicator.className = `status-indicator ${newStatus}`;
      statusText.textContent = newStatus === 'neutral' ? 'Not Set' : newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
      
      const dateKey = formatDate(selectedDate);
      const hour = parseFloat(slot.dataset.hour);
      
      if (!schedules[dateKey]) schedules[dateKey] = { slots: {} };
      if (!schedules[dateKey].slots) schedules[dateKey].slots = {};
      
      if (newStatus === 'neutral') {
        delete schedules[dateKey].slots[hour];
      } else {
        schedules[dateKey].slots[hour] = newStatus;
      }
    }

    async function saveScheduleChanges() {
  const dateKey = formatDate(selectedDate);
  const daySchedule = schedules[dateKey];
  
  console.log('Saving schedule for date:', dateKey);
  console.log('Selected date object:', selectedDate);
  console.log('Day schedule:', daySchedule);
  
  if (!daySchedule) {
    Swal.fire('Error', 'No changes to save', 'info');
    return;
  }

  try {
    let payload = {
      action: 'create',
      date: dateKey
    };

    if (daySchedule.wholeDay) {
      payload.scheduleType = 'available';
      payload.startTime = '08:00';
      // Store end time as 17:30 to allow slot at 17:00 (5:00 PM) for 30-minute appointments
      payload.endTime = '17:30';
    } else if (daySchedule.unavailableDay) {
      payload.scheduleType = 'unavailable';
      payload.reason = document.getElementById('reasonInput').value;
    } else if (daySchedule.slots) {
      payload.scheduleType = 'custom';
      payload.slots = daySchedule.slots;
    }

    console.log('Sending payload:', payload);

    const response = await fetch('../crud/schedule_handler.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify(payload)
    });

    const result = await response.json();
    console.log('Server response:', result);

    if (result.success) {
      await loadSchedules();
      
      renderCalendar();
      if (selectedDate) {
        selectDate(selectedDate);
      }
      closeDrawer();
      Swal.fire('Success!', 'Schedule saved successfully!', 'success');
    } else {
      throw new Error(result.error || 'Failed to save schedule');
    }
    
  } catch (error) {
    console.error('Error:', error);
    Swal.fire('Error', error.message || 'Failed to save schedule', 'error');
  }
}
    async function loadSchedules() {
  try {
    const response = await fetch('../crud/schedule_handler.php?action=list');
    const result = await response.json();
    
    if (result.success) {
      schedules = {};
      
      // Process schedules from database
      result.data.forEach(schedule => {
        const dateKey = schedule.schedule_date;
        
        if (!schedules[dateKey]) {
          schedules[dateKey] = { slots: {} };
        }
        
        if (schedule.schedule_type === 'unavailable' && !schedule.start_time) {
          // Whole day unavailable
          schedules[dateKey] = { unavailableDay: true };
        } else if (schedule.schedule_type === 'available' && 
                   schedule.start_time === '08:00:00' && 
                   schedule.end_time === '17:00:00') {
          // Whole day available
          schedules[dateKey] = { wholeDay: true };
        } else {
          // Custom time slots with 30-minute intervals
          const startParts = schedule.start_time.split(':');
          const endParts = schedule.end_time.split(':');
          const startHour = parseInt(startParts[0]) + (parseInt(startParts[1]) / 60);
          const endHour = parseInt(endParts[0]) + (parseInt(endParts[1]) / 60);
          
          // Generate 30-minute intervals
          for (let slot = startHour; slot < endHour; slot += 0.5) {
            schedules[dateKey].slots[slot] = schedule.schedule_type;
          }
        }
      });
      
      renderCalendar();
    }
  } catch (error) {
    console.error('Error loading schedules:', error);
  }
}

    function formatDate(date) {
  // Use local date components instead of ISO string to avoid timezone issues
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

    function formatHour(hour) {
      const isHalfHour = hour % 1 === 0.5;
      const hourInt = Math.floor(hour);
      const displayHour = hourInt > 12 ? hourInt - 12 : hourInt === 0 ? 12 : hourInt;
      const ampm = hourInt >= 12 ? 'PM' : 'AM';
      const minutes = isHalfHour ? '30' : '00';
      return `${displayHour}:${minutes} ${ampm}`;
    }

    function isToday_helper(date) {
      const today = new Date();
      return date.getDate() === today.getDate() &&
             date.getMonth() === today.getMonth() &&
             date.getFullYear() === today.getFullYear();
    }

    // Initialize calendar when schedule tab is clicked
    document.querySelector('[data-tab="schedule"]').addEventListener('click', function() {
      setTimeout(() => {
        if (!document.getElementById('daysGrid').children.length) {
          initCalendar();
        }
      }, 100);
    });
    
    // Initialize notification system
    if (window.DentalNotificationSystem) {
      DentalNotificationSystem.init();
    }
  </script>
  <script src="../js/mobile-menu.js"></script>
</body>
</html>
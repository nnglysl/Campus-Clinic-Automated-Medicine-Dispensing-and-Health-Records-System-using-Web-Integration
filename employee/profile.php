<?php
session_start();
require_once('../db.php');

// Check if user is logged in and is an employee
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['employee', 'doctor', 'nurse', 'staff', 'admin'])) {
    header('Location: ../auth/login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch user profile data
try {
    // Get user data from users table
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        error_log("User not found with ID: " . $userId);
        header('Location: ../auth/login.php');
        exit;
    }

    // Get employee data using user_id foreign key
    $stmt = $pdo->prepare("SELECT * FROM employees WHERE user_id = ?");
    $stmt->execute([$userId]);
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
            $updateStmt->execute([$userId, $employee['id']]);
            
            $user['position'] = $employee['role'] ?? ucfirst($user['role'] ?? 'Staff');
            $user['employee_id'] = $employee['id'];
            $user['hire_date'] = $employee['created_at'];
            
            error_log("Auto-linked employee ID " . $employee['id'] . " to user ID " . $userId);
        } else {
            // Set defaults if no employee record exists
            $user['position'] = ucfirst($user['role'] ?? 'Staff');
            $user['employee_id'] = 'N/A';
            $user['hire_date'] = null;
            
            error_log("No employee record found for user ID: " . $userId);
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

// Get user's full name
$fullName = trim(($user['fname'] ?? '') . ' ' . ($user['mname'] ?? '') . ' ' . ($user['lname'] ?? ''));
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

    <!-- FullCalendar CDN -->
    <link href='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.css' rel='stylesheet' />
    <script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.8/index.global.min.js'></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="../employee/nav.css">
    <link rel="stylesheet" href="../employee/profile.css">
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
      <a href="../employee/dashboard.php" class="menu-item">Dashboard</a>
      <a href="../employee/profile.php" class="menu-item active">Profile</a>
      <a href="../employee/patients.php" class="menu-item">Patients</a>
      <a href="../employee/appointments.php" class="menu-item">Appointments</a>
      <a href="../employee/reports.php" class="menu-item">Reports & Analytics</a>
      <a href="../employee/settings.php" class="menu-item">Settings</a>
      
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
          <a class="nav-link active" id="attendance-tab" data-tab="attendance">Attendance</a>
        </li>
        <li class="nav-item" role="presentation">
          <a class="nav-link " id="profile-tab" data-tab="profile">Profile</a>
        </li>
        <li class="nav-item" role="presentation">
          <a class="nav-link" id="schedule-tab" data-tab="schedule">Make a schedule</a>
        </li>
      </ul>

      <!-- Tab Content -->
      <div class="tab-content">
        <!-- TIME IN & OUT TAB -->
        <div class="tab-pane" id="attendance">
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
        <div class="tab-pane active" id="profile">
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
              <input type="hidden" id="userId" value="<?php echo $userId; ?>">
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

        <!-- Schedule Tab -->
        <div class="tab-pane" id="schedule">
          <div class="card">
            <div class="card-header">
              <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Doctor Availability Calendar</h5>
            </div>
            <div class="card-body">
              <div id="calendar"></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    // User data from PHP
    const userData = {
      id: <?php echo $userId; ?>,
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
    document.querySelectorAll('.nav-link').forEach(tab => {
      tab.addEventListener('click', function() {
        document.querySelectorAll('.nav-link').forEach(t => t.classList.remove('active'));
        this.classList.add('active');
        
        document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
        
        const targetTab = this.getAttribute('data-tab');
        document.getElementById(targetTab).classList.add('active');
        
        // Re-render calendar when schedule tab is opened
        if (targetTab === 'schedule' && window.calendar) {
          setTimeout(() => {
            window.calendar.updateSize();
          }, 100);
        }
      });
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
      // Collect form data
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
          
          // Update sidebar name if changed
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

    // Load attendance records from server
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
  const today = new Date().toLocaleDateString('en-US'); // This will give format like "11/6/2025"
  
  // Find record matching today's date
  const record = attendanceRecords.find(r => {
    // Compare dates - handle both "11/6/2025" and "11/06/2025" formats
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
      const now = new Date();
      const today = now.toLocaleDateString();

      const record = attendanceRecords.find(r => r.date === today);
      if (!record || !record.timeIn) {
        Swal.fire("Error", "You haven't timed in yet!", "error");
        return;
      }

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

    // Initialize attendance
    loadAttendanceRecords();

    // ===== CALENDAR FUNCTIONALITY =====
document.addEventListener("DOMContentLoaded", function() {
  let savedEvents = [];
  const calendarEl = document.getElementById("calendar");

  // Load saved schedules from server
  async function loadSchedules() {
    try {
      const response = await fetch('../crud/schedule_handler.php?action=list');
      const result = await response.json();
      
      if (result.success) {
        savedEvents = result.data;
        window.calendar.removeAllEvents();
        savedEvents.forEach(event => window.calendar.addEvent(event));
      }
    } catch (error) {
      console.error('Error loading schedules:', error);
    }
  }

  window.calendar = new FullCalendar.Calendar(calendarEl, {
    initialView: 'dayGridMonth',
    selectable: true,
    height: "auto",
    events: savedEvents,
    headerToolbar: {
      start: 'title',
      center: '',
      end: 'prev,next today'
    },
    dateClick: async function(info) {
      const clickedDate = new Date(info.dateStr);
      const day = clickedDate.getDay();

      if (day === 0 || day === 6) {
        Swal.fire("Not Allowed", "Scheduling on weekends is not allowed.", "warning");
        return;
      }

      // Check if date already has unavailability marked
      const existingUnavailable = savedEvents.find(e => 
        e.start === info.dateStr && e.extendedProps?.type === 'unavailable'
      );

      if (existingUnavailable) {
        Swal.fire("Already Marked", "This date is already marked as unavailable.", "info");
        return;
      }

      const result = await Swal.fire({
        title: "Manage Schedule",
        html: `
          <div style="text-align: left;">
            <div class="form-check mb-3">
              <input type="checkbox" class="form-check-input" id="markUnavailable" style="cursor: pointer;">
              <label class="form-check-label" for="markUnavailable" style="cursor: pointer; font-weight: 500;">
                Mark as Unavailable (Full Day)
              </label>
            </div>
            
            <div id="timeFields">
              <label class="d-block text-start mb-2">Start Time</label>
              <input type="time" id="startTime" class="swal2-input" style="width: 80%;">
              <label class="d-block text-start mb-2 mt-3">End Time</label>
              <input type="time" id="endTime" class="swal2-input" style="width: 80%;">
            </div>
            
            <div id="reasonField" style="display: none;">
              <label class="d-block text-start mb-2 mt-3">Reason (Optional)</label>
              <input type="text" id="unavailableReason" class="swal2-input" 
                placeholder="e.g., Conference, Personal Leave" style="width: 80%;">
            </div>
          </div>
        `,
        confirmButtonText: "Save Schedule",
        showCancelButton: true,
        confirmButtonColor: '#6B0D12',
        didOpen: () => {
          const checkbox = document.getElementById('markUnavailable');
          const timeFields = document.getElementById('timeFields');
          const reasonField = document.getElementById('reasonField');
          const startTime = document.getElementById('startTime');
          const endTime = document.getElementById('endTime');

          checkbox.addEventListener('change', function() {
            if (this.checked) {
              timeFields.style.display = 'none';
              reasonField.style.display = 'block';
              startTime.value = '';
              endTime.value = '';
              startTime.removeAttribute('required');
              endTime.removeAttribute('required');
            } else {
              timeFields.style.display = 'block';
              reasonField.style.display = 'none';
              startTime.setAttribute('required', 'required');
              endTime.setAttribute('required', 'required');
            }
          });
        },
        preConfirm: () => {
          const isUnavailable = document.getElementById('markUnavailable').checked;
          const start = document.getElementById("startTime").value;
          const end = document.getElementById("endTime").value;
          const reason = document.getElementById("unavailableReason").value;

          if (!isUnavailable) {
            if (!start || !end) {
              Swal.showValidationMessage("Both start and end times are required.");
              return false;
            }

            const toMinutes = (timeStr) => {
              const [hour, minute] = timeStr.split(":").map(Number);
              return hour * 60 + minute;
            };

            const startMinutes = toMinutes(start);
            const endMinutes = toMinutes(end);

            if (startMinutes >= endMinutes) {
              Swal.showValidationMessage("End time must be later than start time.");
              return false;
            }

            return { start, end, type: 'available' };
          } else {
            return { type: 'unavailable', reason };
          }
        }
      });

      if (result.isConfirmed) {
        try {
          const payload = {
            action: 'create',
            date: info.dateStr,
            scheduleType: result.value.type
          };

          if (result.value.type === 'available') {
            payload.startTime = result.value.start;
            payload.endTime = result.value.end;
          } else {
            payload.reason = result.value.reason;
          }

          const response = await fetch('../crud/schedule_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
          });

          const apiResult = await response.json();

          if (apiResult.success) {
            await loadSchedules();
            Swal.fire({
              title: "Success!",
              text: apiResult.message,
              icon: "success",
              confirmButtonColor: '#6B0D12'
            });
          } else {
            Swal.fire("Error", apiResult.error || "Failed to save schedule", "error");
          }
        } catch (error) {
          console.error('Error:', error);
          Swal.fire("Error", "Failed to save schedule. Please try again.", "error");
        }
      }
    },
    eventClick: async function(info) {
      const eventType = info.event.extendedProps?.type || 'available';
      const reason = info.event.extendedProps?.reason;
      
      let message = info.event.title;
      if (eventType === 'unavailable' && reason) {
        message += `\n\nReason: ${reason}`;
      }

      const result = await Swal.fire({
        title: "Remove Schedule?",
        text: message,
        showCancelButton: true,
        confirmButtonText: "Delete",
        confirmButtonColor: '#d33',
        icon: eventType === 'unavailable' ? 'warning' : 'question'
      });

      if (result.isConfirmed) {
        try {
          const response = await fetch('../crud/schedule_handler.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'delete',
              id: info.event.id
            })
          });

          const apiResult = await response.json();

          if (apiResult.success) {
            info.event.remove();
            Swal.fire({
              title: "Deleted!",
              text: "Schedule has been removed.",
              icon: "success",
              confirmButtonColor: '#6B0D12'
            });
          } else {
            Swal.fire("Error", apiResult.error || "Failed to delete schedule", "error");
          }
        } catch (error) {
          console.error('Error:', error);
          Swal.fire("Error", "Failed to delete schedule. Please try again.", "error");
        }
      }
    },
    // Add legend/key
    eventDidMount: function(info) {
      if (info.event.extendedProps?.type === 'unavailable') {
        info.el.style.cursor = 'pointer';
        if (info.event.extendedProps?.reason) {
          info.el.title = `Unavailable - ${info.event.extendedProps.reason}`;
        }
      }
    }
  });

  window.calendar.render();
  loadSchedules();

  // Add legend below calendar
  const legendHTML = `
    <div style="display: flex; justify-content: center; gap: 2rem; margin-top: 1rem; padding: 1rem; background: #f8f9fa; border-radius: 6px;">
      <div style="display: flex; align-items: center; gap: 0.5rem;">
        <div style="width: 20px; height: 20px; background: #198754; border-radius: 3px;"></div>
        <span style="font-size: 0.9rem; font-weight: 500;">Available</span>
      </div>
      <div style="display: flex; align-items: center; gap: 0.5rem;">
        <div style="width: 20px; height: 20px; background: #dc3545; border-radius: 3px;"></div>
        <span style="font-size: 0.9rem; font-weight: 500;">Unavailable</span>
      </div>
    </div>
  `;
  
  document.querySelector('#calendar').insertAdjacentHTML('afterend', legendHTML);
});

    // ===== LOGOUT FUNCTIONALITY =====
    document.getElementById('logoutBtn').addEventListener('click', () => {
      Swal.fire({
        title: 'Logout',
        text: 'Are you sure you want to logout?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#800000',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, logout'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = './logout.php';
        }
      });
    });
  </script>
</body>
</html>
<?php
session_start();
require_once '../db.php';
<<<<<<< HEAD
require_once '../includes/activity_logger.php';
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit();
}

$userName = $_SESSION['fname'] . ' ' . ($_SESSION['lname'] ?? '');

<<<<<<< HEAD
// Handle Form Submissions
=======
// Handle Add Employee Form Submission
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_employee') {
        try {
            // Validate input
            $first_name = trim($_POST['first_name']);
            $middle_name = trim($_POST['middle_name'] ?? '');
            $last_name = trim($_POST['last_name']);
            $birth_date = $_POST['birth_date'];
            $gender = $_POST['gender'];
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $address = trim($_POST['address']);
            $role = $_POST['role'];
            $password = $_POST['password'];
            
            // Calculate age
            $birthDate = new DateTime($birth_date);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Email already exists']);
                exit();
            }
            
<<<<<<< HEAD
=======
            // Generate username from email
            $username = explode('@', $email)[0];
            
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Insert into users table
            $stmt = $pdo->prepare("INSERT INTO users (fname, mname, lname, email, phone, password, role, date_of_birth, gender, address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([$first_name, $middle_name, $last_name, $email, $phone, $hashed_password, $role, $birth_date, $gender, $address]);
            $user_id = $pdo->lastInsertId();
            
            // Insert into employees table
            $stmt = $pdo->prepare("
<<<<<<< HEAD
                INSERT INTO employees (user_id, first_name, middle_name, last_name, birth_date, age, gender, email, phone, address, role, password, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([
                $user_id, $first_name, $middle_name, $last_name, $birth_date, $age, 
                $gender, $email, $phone, $address, $role, $hashed_password
=======
                INSERT INTO employees (user_id, first_name, middle_name, last_name, birth_date, age, gender, email, phone, address, role, username, password, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([
                $user_id, $first_name, $middle_name, $last_name, $birth_date, $age, 
                $gender, $email, $phone, $address, $role, $username, $hashed_password
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            ]);
            
            $pdo->commit();
            
<<<<<<< HEAD
            // Log activity
            $employeeName = trim($first_name . ' ' . $last_name);
            $roleName = ucfirst($role);
            logActivity($pdo, $_SESSION['user_id'], 'Add Employee', "Added new $roleName: $employeeName ($email)");
            
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            echo json_encode(['success' => true, 'message' => 'Employee added successfully']);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit();
        }
    }
    
<<<<<<< HEAD
    if ($_POST['action'] === 'edit_employee') {
        try {
            // Validate input
            $employee_id = $_POST['employee_id'];
            $first_name = trim($_POST['first_name']);
            $middle_name = trim($_POST['middle_name'] ?? '');
            $last_name = trim($_POST['last_name']);
            $birth_date = $_POST['birth_date'];
            $gender = $_POST['gender'];
            $email = trim($_POST['email']);
            $phone = trim($_POST['phone']);
            $address = trim($_POST['address']);
            $role = $_POST['role'];
            $password = $_POST['password'] ?? '';
            
            // Calculate age
            $birthDate = new DateTime($birth_date);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
            
            // Get current employee data including user_id
            $stmt = $pdo->prepare("SELECT email, user_id FROM employees WHERE id = ?");
            $stmt->execute([$employee_id]);
            $currentEmployee = $stmt->fetch();
            
            if (!$currentEmployee) {
                echo json_encode(['success' => false, 'message' => 'Employee not found']);
                exit();
            }
            
            $user_id = $currentEmployee['user_id'];
            
            // Check if new email already exists (excluding current user)
            if ($email !== $currentEmployee['email']) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$email, $user_id]);
                if ($stmt->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'Email already exists']);
                    exit();
                }
            }
            
            // Start transaction
            $pdo->beginTransaction();
            
            // Update users table using user_id (more reliable than email)
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET fname = ?, mname = ?, lname = ?, email = ?, phone = ?, password = ?, role = ?, date_of_birth = ?, gender = ?, address = ? WHERE id = ?");
                $stmt->execute([$first_name, $middle_name, $last_name, $email, $phone, $hashed_password, $role, $birth_date, $gender, $address, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE users SET fname = ?, mname = ?, lname = ?, email = ?, phone = ?, role = ?, date_of_birth = ?, gender = ?, address = ? WHERE id = ?");
                $stmt->execute([$first_name, $middle_name, $last_name, $email, $phone, $role, $birth_date, $gender, $address, $user_id]);
            }
            
            // Update employees table
            if (!empty($password)) {
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE employees SET first_name = ?, middle_name = ?, last_name = ?, birth_date = ?, age = ?, gender = ?, email = ?, phone = ?, address = ?, role = ?, password = ? WHERE id = ?");
                $stmt->execute([$first_name, $middle_name, $last_name, $birth_date, $age, $gender, $email, $phone, $address, $role, $hashed_password, $employee_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE employees SET first_name = ?, middle_name = ?, last_name = ?, birth_date = ?, age = ?, gender = ?, email = ?, phone = ?, address = ?, role = ? WHERE id = ?");
                $stmt->execute([$first_name, $middle_name, $last_name, $birth_date, $age, $gender, $email, $phone, $address, $role, $employee_id]);
            }
            
            $pdo->commit();
            
            // Log activity
            $employeeName = trim($first_name . ' ' . $last_name);
            $roleName = ucfirst($role);
            logActivity($pdo, $_SESSION['user_id'], 'Edit Employee', "Updated $roleName: $employeeName ($email)");
            
            echo json_encode(['success' => true, 'message' => 'Employee updated successfully']);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit();
        }
    }
    
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    if ($_POST['action'] === 'update_status') {
        try {
            $employee_id = $_POST['employee_id'];
            $status = $_POST['status'];
            
<<<<<<< HEAD
            // Get employee details for logging
            $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, role FROM employees WHERE id = ?");
            $stmt->execute([$employee_id]);
            $employee = $stmt->fetch();
            
            $stmt = $pdo->prepare("UPDATE employees SET status = ? WHERE id = ?");
            $stmt->execute([$status, $employee_id]);
            
            // Log activity
            if ($employee) {
                $statusName = ucfirst($status);
                logActivity($pdo, $_SESSION['user_id'], 'Update Employee Status', "Changed status of {$employee['name']} to $statusName");
            }
            
=======
            $stmt = $pdo->prepare("UPDATE employees SET status = ? WHERE id = ?");
            $stmt->execute([$status, $employee_id]);
            
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            exit();
            
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit();
        }
    }
    
    if ($_POST['action'] === 'delete_employee') {
        try {
            $employee_id = $_POST['employee_id'];
            
<<<<<<< HEAD
            // Get employee details for logging
            $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, email, role FROM employees WHERE id = ?");
=======
            // Get employee email
            $stmt = $pdo->prepare("SELECT email FROM employees WHERE id = ?");
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            $stmt->execute([$employee_id]);
            $employee = $stmt->fetch();
            
            if ($employee) {
                // Start transaction
                $pdo->beginTransaction();
                
                // Delete from users table
                $stmt = $pdo->prepare("DELETE FROM users WHERE email = ?");
                $stmt->execute([$employee['email']]);
                
                // Delete from employees table
                $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
                $stmt->execute([$employee_id]);
                
                $pdo->commit();
<<<<<<< HEAD
                
                // Log activity
                $roleName = ucfirst($employee['role'] ?? 'Employee');
                logActivity($pdo, $_SESSION['user_id'], 'Delete Employee', "Deleted $roleName: {$employee['name']} ({$employee['email']})");
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            }
            
            echo json_encode(['success' => true, 'message' => 'Employee deleted successfully']);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit();
        }
    }
}

<<<<<<< HEAD
// Fetch all employees - includes all staff roles (doctors, dentists, nurses, staff, employees)
// This is for the Active/Inactive tabs in user management
$stmt = $pdo->query("
    SELECT 
        COALESCE(e.id, u.id) as id,
        u.id as user_id,
        u.fname as first_name,
        u.mname as middle_name,
        u.lname as last_name,
        u.email,
        u.phone,
        COALESCE(e.address, u.address) as address,
        u.role,
        COALESCE(e.status, 'active') as status,
        COALESCE(e.birth_date, u.date_of_birth) as birth_date,
        e.age,
        COALESCE(e.gender, u.gender) as gender,
        COALESCE(e.created_at, u.created_at) as created_at,
        COALESCE(e.updated_at, u.updated_at) as updated_at
    FROM users u
    LEFT JOIN employees e ON u.id = e.user_id
    WHERE u.role IN ('doctor', 'dentist', 'nurse', 'staff', 'employee')
    ORDER BY COALESCE(e.status, 'active') ASC, u.lname ASC, u.fname ASC
");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get selected date from URL parameter or default to today
$selectedDate = isset($_GET['date']) && !empty($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$displayDate = $selectedDate;

// Check if selected date is weekend (Saturday = 6, Sunday = 0)
$dayOfWeek = date('w', strtotime($selectedDate)); // 0 = Sunday, 6 = Saturday
$isWeekend = ($dayOfWeek == 0 || $dayOfWeek == 6);

// Fetch schedules for selected date - Doctors, dentists, and nurses
// Doctors/dentists show schedules, nurses show attendance
=======
// Fetch all employees
$stmt = $pdo->query("SELECT * FROM employees ORDER BY status ASC, last_name ASC");
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch today's schedules
$today = date('Y-m-d');
// Around line 90, update the SQL query to include schedule_type:
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
$stmt = $pdo->prepare("
    SELECT 
        u.id as user_id,
        u.fname as first_name,
        u.mname as middle_name,
        u.lname as last_name,
        u.email,
        u.role,
<<<<<<< HEAD
=======
        u.photo,
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        ds.schedule_date,
        ds.start_time as time_in,
        ds.end_time as time_out,
        ds.is_available,
        ds.schedule_type,
        ds.reason,
        att.status as current_status,
        att.time_in as last_check_in,
<<<<<<< HEAD
        att.date as attendance_date,
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        CASE 
            WHEN att.time_in IS NOT NULL AND att.time_out IS NULL THEN 'in'
            WHEN att.time_out IS NOT NULL THEN 'out'
            ELSE 'out'
<<<<<<< HEAD
        END as availability_status,
        DATE_FORMAT(ds.start_time, '%h:%i %p') as time_in_formatted,
        DATE_FORMAT(ds.end_time, '%h:%i %p') as time_out_formatted,
        CASE 
            WHEN att.time_in IS NOT NULL THEN 
                TIME_FORMAT(att.time_in, '%h:%i %p')
            ELSE NULL
        END as attendance_time_in,
        CASE 
            WHEN att.time_out IS NOT NULL THEN 
                TIME_FORMAT(att.time_out, '%h:%i %p')
            ELSE NULL
        END as attendance_time_out,
        CASE
            WHEN ds.start_time IS NOT NULL AND ds.end_time IS NOT NULL THEN
                CONCAT(
                    TIMESTAMPDIFF(HOUR, ds.start_time, ds.end_time), 'h ',
                    MOD(TIMESTAMPDIFF(MINUTE, ds.start_time, ds.end_time), 60), 'm'
                )
            ELSE NULL
        END as duration
    FROM users u
    LEFT JOIN employees e ON u.id = e.user_id
    LEFT JOIN doctor_schedules ds ON u.id = ds.user_id 
        AND ds.schedule_date = ?
    LEFT JOIN attendance att ON u.id = att.user_id 
        AND att.date = ?
    WHERE u.role IN ('doctor', 'dentist', 'nurse')
    ORDER BY u.lname ASC, u.fname ASC
");
$stmt->execute([$selectedDate, $selectedDate]);
$todaySchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Format display date
$displayDateFormatted = date('l, F j, Y', strtotime($displayDate));
=======
        END as availability_status
    FROM users u
    LEFT JOIN doctor_schedules ds ON u.id = ds.user_id 
        AND ds.schedule_date = ? 
        AND ds.is_available = 1
    LEFT JOIN attendance att ON u.id = att.user_id 
        AND att.date = CURDATE()
    WHERE u.role IN ('doctor', 'nurse', 'staff', 'employee')
    ORDER BY u.lname ASC, u.fname ASC
");
$stmt->execute([$today]);
$todaySchedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Management</title>
  
<<<<<<< HEAD
  <link href="../admin/css/userManagement.css" rel="stylesheet">
  <link href="/finalproject/css/nav.css" rel="stylesheet">
  <link href="../admin/css/responsive.css" rel="stylesheet">
  <link href="../admin/css/notifications.css" rel="stylesheet">
=======
  <link href="../admin/userManagement.css" rel="stylesheet">
  <link href="../admin/nav.css" rel="stylesheet">
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Text:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
</head>

<body>
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
<<<<<<< HEAD
      <!-- Mobile Menu Icon -->
      <button type="button" class="mobile-menu-icon" id="mobileMenuBtn" aria-label="Toggle navigation menu" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>
      <?php include 'notification_component.php'; ?>
      <div class="logout-icon" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></div>
=======
      <div class="notification-icon"><i class="bi bi-bell-fill"></i></div>
      <div class="logout-icon" onclick="logout()"><i class="bi bi-box-arrow-right"></i></div>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    </div>
  </div>

  <div class="main-container">
    <div class="sidebar">
      <div>
        <a href="../admin/adminDashboard.php" class="menu-item">Dashboard</a>
        <a href="../admin/patients.php" class="menu-item">Patient</a>
        <a href="../admin/userManagement.php" class="menu-item active">User Management</a>
        <a href="../admin/inventory.php" class="menu-item">Inventory</a>
<<<<<<< HEAD
        <a href="../admin/activity_logs.php" class="menu-item">Activity Logs</a>
        <a href="../admin/reports.php" class="menu-item">Reports & Analytics</a>
      </div>
      <div class="user-profile">
=======
        <a href="../admin/appointmentManagement.php" class="menu-item">Appointments</a>
        <a href="../admin/reports.php" class="menu-item">Reports & Analytics</a>
      </div>
      <div class="user-profile">
        <div class="avatar"></div>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        <span><?php echo htmlspecialchars($userName); ?></span>
      </div>
    </div>

    <div class="content">
      <div class="page-header">
        <h2><i class="bi bi-people-fill"></i> User Management</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
          <i class="bi bi-person-plus-fill"></i> Add New Employee
        </button>
      </div>

      <ul class="nav nav-tabs user-tabs" id="userTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" type="button" role="tab">
            Schedule & Availability
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="active-users-tab" data-bs-toggle="tab" data-bs-target="#active-users" type="button" role="tab">
            Active
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="inactive-users-tab" data-bs-toggle="tab" data-bs-target="#inactive-users" type="button" role="tab">
            Inactive
          </button>
        </li>
      </ul>

      <div class="tab-content">
        <!-- Schedule & Availability -->
        <div class="tab-pane fade show active" id="schedule" role="tabpanel">
          <div class="table-container">
            <!-- Filter Controls -->
            <div class="schedule-filters mb-4">
<<<<<<< HEAD
              <div class="row align-items-end g-3">
                <div class="col-md">
                  <label class="form-label">View Range</label>
=======
              <div class="row align-items-end">
                <div class="col-md-3">
                  <label class="form-label fw-bold"><i class="bi bi-funnel"></i> View Range</label>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                  <select id="scheduleViewType" class="form-select">
                    <option value="today" selected>Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="year">This Year</option>
                    <option value="all">All Upcoming</option>
                  </select>
                </div>
<<<<<<< HEAD
                <div class="col-md">
                  <label class="form-label">Department</label>
  <select id="scheduleDepartment" class="form-select">
    <option value="all" selected>All Departments</option>
    <option value="medical">Medical</option>
    <option value="dental">Dental</option>
  </select>
</div>
                <div class="col-md">
                  <label class="form-label">Specific Date</label>
                  <input type="date" id="scheduleDatePicker" class="form-control" value="<?php echo htmlspecialchars($selectedDate); ?>" max="2099-12-31">
                </div>
                <div class="col-md">
                  <label class="form-label">Search Employee</label>
                  <input type="text" id="scheduleSearch" class="form-control" placeholder="Search by name, email or role..">
                </div>
                <div class="col-md">
                  <label class="form-label">&nbsp;</label>
                  <button class="btn btn-primary w-100" onclick="searchSchedule()">
                    <i class="bi bi-search"></i> Search
=======
                <div class="col-md-3">
                  <label class="form-label fw-bold"><i class="bi bi-calendar-event"></i> Specific Date</label>
                  <input type="date" id="scheduleDatePicker" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="col-md-4">
                  <label class="form-label fw-bold"><i class="bi bi-search"></i> Search Employee</label>
                  <input type="text" id="scheduleSearch" class="form-control" placeholder="Search by name, email, or role...">
                </div>
                <div class="col-md-2">
                  <button class="btn btn-outline-success w-100" onclick="refreshSchedule()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                  </button>
                </div>
              </div>
            </div>

            <!-- Schedule Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0" id="scheduleDateDisplay">
<<<<<<< HEAD
                <i class="bi bi-calendar-week"></i> Schedule - <?php echo htmlspecialchars($displayDateFormatted); ?>
=======
                <i class="bi bi-calendar-week"></i> Today's Schedule - <?php echo date('l, F j, Y'); ?>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
              </h5>
              <div>
                <span class="badge bg-primary" id="scheduleCount">
                  <?php echo count($todaySchedules); ?> Employee(s)
                </span>
              </div>
            </div>

            <!-- Schedule Container -->
            <div id="scheduleContainer">
              <?php if (empty($todaySchedules)): ?>
                <div class="text-center py-5">
                  <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
                  <p class="text-muted mt-3">No employees scheduled for today</p>
                </div>
              <?php else: ?>
                <?php foreach ($todaySchedules as $emp): ?>
                <div class="employee-schedule-row">
                  <div class="employee-schedule-info">
<<<<<<< HEAD
                      <div class="employee-photo-small bg-secondary d-flex align-items-center justify-content-center">
                        <i class="bi bi-person-fill text-white" style="font-size: 1rem;"></i>
                      </div>
=======
                    <?php if (!empty($emp['photo'])): ?>
                      <img src="<?php echo htmlspecialchars($emp['photo']); ?>" class="employee-photo-small" alt="<?php echo htmlspecialchars($emp['first_name']); ?>">
                    <?php else: ?>
                      <div class="employee-photo-small bg-secondary d-flex align-items-center justify-content-center">
                        <i class="bi bi-person-fill text-white" style="font-size: 1rem;"></i>
                      </div>
                    <?php endif; ?>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                    <div>
                      <div><strong><?php echo htmlspecialchars($emp['first_name'] . ' ' . ($emp['middle_name'] ? $emp['middle_name'] . ' ' : '') . $emp['last_name']); ?></strong></div>
                      <div class="text-muted" style="font-size: 0.85rem;">
                        <span class="role-badge role-<?php echo $emp['role']; ?>">
                          <i class="bi bi-person-badge-fill"></i> <?php echo ucfirst($emp['role']); ?>
                        </span>
                      </div>
                    </div>
                  </div>
                  <div class="employee-schedule-details">
<<<<<<< HEAD
    <?php 
    $role = strtolower($emp['role'] ?? '');
    $isDoctorOrDentist = in_array($role, ['doctor', 'dentist']);
    $isNurse = ($role === 'nurse');
    
    // For nurses: always show attendance time in/out
    if ($isNurse): 
      $hasAttendance = $emp['attendance_time_in'] || ($emp['last_check_in'] !== null);
      if ($hasAttendance): ?>
        <div class="schedule-table d-flex text-center">
          <div class="flex-fill border-end p-2">
            <small class="text-muted d-block">Time In</small>
            <strong><?php echo $emp['attendance_time_in'] ?? ($emp['last_check_in'] ? date('g:i A', strtotime($emp['last_check_in'])) : '--:--'); ?></strong>
          </div>
          <div class="flex-fill border-end p-2">
            <small class="text-muted d-block">Time Out</small>
            <strong><?php echo $emp['attendance_time_out'] ?? '--:--'; ?></strong>
          </div>
          <div class="flex-fill border-end p-2">
            <small class="text-muted d-block">Status</small>
            <?php
            $status = $emp['availability_status'] ?? 'out';
            $badges = [
              'in' => '<span class="availability-badge availability-in"><i class="bi bi-check-circle-fill"></i> Checked In</span>',
              'out' => '<span class="availability-badge availability-out"><i class="bi bi-x-circle-fill"></i> Checked Out</span>',
              'break' => '<span class="availability-badge availability-break"><i class="bi bi-pause-circle-fill"></i> On Break</span>'
            ];
            echo $badges[$status] ?? ($emp['last_check_in'] && !$emp['attendance_time_out'] ? $badges['in'] : $badges['out']);
            ?>
          </div>
          <div class="flex-fill p-2">
            <small class="text-muted d-block">Date</small>
            <strong style="font-size: 0.85rem;">
              <?php echo $emp['attendance_date'] ? date('M j, Y', strtotime($emp['attendance_date'])) : date('M j, Y', strtotime($selectedDate)); ?>
            </strong>
          </div>
        </div>
      <?php else: ?>
        <div class="text-center">
          <span class="availability-badge availability-unavailable">
            <i class="bi bi-clock-history"></i> No Attendance Record
          </span>
        </div>
            <?php endif; ?>
    <?php 
    // For doctors and dentists
    elseif ($isDoctorOrDentist): 
      // Check if it's weekend - show "No Schedule" for weekends
      if ($isWeekend): ?>
        <div class="text-center">
          <span class="availability-badge availability-unavailable">
            <i class="bi bi-calendar-x"></i> No Schedule
          </span>
        </div>
      <?php else: 
        // For weekdays (Monday-Friday): show schedule from doctor_schedules table
        if ($emp['time_in'] && $emp['schedule_type'] !== 'unavailable' && $emp['is_available']): ?>
        <div class="schedule-table d-flex text-center">
          <div class="flex-fill border-end p-2">
            <small class="text-muted d-block">Schedule</small>
              <strong><?php echo $emp['time_in_formatted'] ?? date('g:i A', strtotime($emp['time_in'])); ?> - <?php echo $emp['time_out_formatted'] ?? date('g:i A', strtotime($emp['time_out'])); ?></strong>
          </div>
          <div class="flex-fill border-end p-2">
            <small class="text-muted d-block">Duration</small>
              <strong><?php echo $emp['duration'] ?? 'N/A'; ?></strong>
          </div>
          <div class="flex-fill border-end p-2">
            <small class="text-muted d-block">Attendance Status</small>
            <?php
            $status = $emp['availability_status'] ?? 'out';
            $badges = [
              'in' => '<span class="availability-badge availability-in"><i class="bi bi-check-circle-fill"></i> Checked In</span>',
              'out' => '<span class="availability-badge availability-out"><i class="bi bi-x-circle-fill"></i> Not Checked In</span>',
              'break' => '<span class="availability-badge availability-break"><i class="bi bi-pause-circle-fill"></i> On Break</span>'
            ];
              echo $badges[$status] ?? $badges['out'];
            ?>
          </div>
          <div class="flex-fill p-2">
            <small class="text-muted d-block">Last Check-in</small>
            <strong style="font-size: 0.85rem;">
                <?php echo $emp['attendance_time_in'] ?? ($emp['last_check_in'] ? date('g:i A', strtotime($emp['last_check_in'])) : 'N/A'); ?>
            </strong>
          </div>
        </div>
        <?php elseif ($emp['schedule_type'] === 'unavailable'): ?>
          <div class="text-center">
            <span class="availability-badge availability-unavailable">
              <i class="bi bi-calendar-x"></i> Unavailable
              <?php if (!empty($emp['reason'])): ?>
                <small class="d-block mt-1" style="font-size: 0.75rem; opacity: 0.8;">
                  <?php echo htmlspecialchars($emp['reason']); ?>
                </small>
      <?php endif; ?>
            </span>
          </div>
    <?php else: ?>
      <div class="text-center">
        <span class="availability-badge availability-unavailable">
              <i class="bi bi-calendar-x"></i> No Schedule
        </span>
      </div>
        <?php endif; ?>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
=======
                    <?php if ($emp['time_in'] && $emp['is_available']): ?>
  <?php if ($emp['schedule_type'] === 'unavailable'): ?>
    <div class="text-center">
      <span class="availability-badge availability-unavailable">
        <i class="bi bi-calendar-x"></i> Unavailable
        <?php if ($emp['reason']): ?>
          <small class="d-block mt-1" style="font-size: 0.75rem; opacity: 0.8;">
            <?php echo htmlspecialchars($emp['reason']); ?>
          </small>
        <?php endif; ?>
      </span>
    </div>
  <?php else: ?>
                      <div class="schedule-table d-flex text-center">
                        <div class="flex-fill border-end p-2">
                          <small class="text-muted d-block">Schedule</small>
                          <strong><?php echo date('g:i A', strtotime($emp['time_in'])); ?> - <?php echo date('g:i A', strtotime($emp['time_out'])); ?></strong>
                        </div>
                        <div class="flex-fill border-end p-2">
                          <small class="text-muted d-block">Duration</small>
                          <strong>
                            <?php
                            $start = new DateTime($emp['time_in']);
                            $end = new DateTime($emp['time_out']);
                            $diff = $start->diff($end);
                            echo $diff->h . 'h ' . $diff->i . 'm';
                            ?>
                          </strong>
                        </div>
                        <div class="flex-fill border-end p-2">
                          <small class="text-muted d-block">Attendance Status</small>
                          <?php
                          $status = $emp['availability_status'] ?? 'out';
                          $badges = [
                            'in' => '<span class="availability-badge availability-in"><i class="bi bi-check-circle-fill"></i> Checked In</span>',
                            'out' => '<span class="availability-badge availability-out"><i class="bi bi-x-circle-fill"></i> Not Checked In</span>',
                            'break' => '<span class="availability-badge availability-break"><i class="bi bi-pause-circle-fill"></i> On Break</span>'
                          ];
                          echo $badges[$status];
                          ?>
                        </div>
                        <div class="flex-fill p-2">
                          <small class="text-muted d-block">Last Check-in</small>
                          <strong style="font-size: 0.85rem;">
                            <?php echo $emp['last_check_in'] ? date('g:i A', strtotime($emp['last_check_in'])) : 'N/A'; ?>
                          </strong>
                        </div>
                      </div>
                    <?php else: ?>
  <div class="text-center">
    <span class="availability-badge availability-unavailable">
      <i class="bi bi-calendar-x"></i> Unavailable
    </span>
  </div>
<?php endif; ?>
                  </div>
                </div>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Active Users Tab -->
        <div class="tab-pane fade" id="active-users" role="tabpanel">
          <div class="table-container">
            <table id="activeUsersTable" class="display table table-striped table-hover" style="width:100%">
              <thead>
                <tr>
<<<<<<< HEAD
=======
                  <th>Photo</th>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                  <th>Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Role</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>

        <!-- Inactive Users Tab -->
        <div class="tab-pane fade" id="inactive-users" role="tabpanel">
          <div class="table-container">
            <table id="inactiveUsersTable" class="display table table-striped table-hover" style="width:100%">
              <thead>
                <tr>
<<<<<<< HEAD
=======
                  <th>Photo</th>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                  <th>Name</th>
                  <th>Email</th>
                  <th>Phone</th>
                  <th>Role</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Employee Modal -->
<<<<<<< HEAD
  <!-- Add Employee Modal -->
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
  <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-labelledby="addEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="addEmployeeModalLabel">
            <i class="bi bi-person-plus-fill"></i> Add New Employee
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="addEmployeeForm">
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">First Name *</label>
                <input type="text" class="form-control" name="first_name" required>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Middle Name</label>
                <input type="text" class="form-control" name="middle_name">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Last Name *</label>
                <input type="text" class="form-control" name="last_name" required>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Birth Date *</label>
                <input type="date" class="form-control" name="birth_date" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Gender *</label>
                <select class="form-control" name="gender" required>
                  <option value="">Select Gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Email *</label>
                <input type="email" class="form-control" name="email" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Phone *</label>
                <input type="tel" class="form-control" name="phone" required>
              </div>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Address *</label>
              <textarea class="form-control" name="address" rows="2" required></textarea>
            </div>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Role *</label>
                <select class="form-control" name="role" required>
                  <option value="">Select Role</option>
<<<<<<< HEAD
                  <option value="doctor">Medical Doctor</option>
                  <option value="dentist">Dentist</option>
=======
                  <option value="doctor">Doctor</option>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                  <option value="nurse">Nurse</option>
                  <option value="staff">Staff</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Password *</label>
                <input type="password" class="form-control" name="password" minlength="8" required>
                <small class="text-muted">Minimum 8 characters</small>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="submitAddEmployee()">
            <i class="bi bi-check-circle"></i> Add Employee
          </button>
        </div>
      </div>
    </div>
  </div>

<<<<<<< HEAD
  <!-- Edit Employee Modal -->
  <div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-labelledby="editEmployeeModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editEmployeeModalLabel">
            <i class="bi bi-pencil-square"></i> Edit Employee
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <form id="editEmployeeForm">
            <input type="hidden" id="edit_employee_id" name="employee_id">
            <div class="row">
              <div class="col-md-4 mb-3">
                <label class="form-label">First Name *</label>
                <input type="text" class="form-control" id="edit_first_name" name="first_name" required>
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Middle Name</label>
                <input type="text" class="form-control" id="edit_middle_name" name="middle_name">
              </div>
              <div class="col-md-4 mb-3">
                <label class="form-label">Last Name *</label>
                <input type="text" class="form-control" id="edit_last_name" name="last_name" required>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Birth Date *</label>
                <input type="date" class="form-control" id="edit_birth_date" name="birth_date" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Gender *</label>
                <select class="form-control" id="edit_gender" name="gender" required>
                  <option value="">Select Gender</option>
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                </select>
              </div>
            </div>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Email *</label>
                <input type="email" class="form-control" id="edit_email" name="email" required>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">Phone *</label>
                <input type="tel" class="form-control" id="edit_phone" name="phone" required>
              </div>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Address *</label>
              <textarea class="form-control" id="edit_address" name="address" rows="2" required></textarea>
            </div>
            
            <div class="row">
              <div class="col-md-6 mb-3">
                <label class="form-label">Role *</label>
                <select class="form-control" id="edit_role" name="role" required>
                  <option value="">Select Role</option>
                  <option value="doctor">Medical Doctor</option>
                  <option value="dentist">Dentist</option>
                  <option value="nurse">Nurse</option>
                  <option value="staff">Staff</option>
                </select>
              </div>
              <div class="col-md-6 mb-3">
                <label class="form-label">New Password (Optional)</label>
                <input type="password" class="form-control" id="edit_password" name="password" minlength="8">
                <small class="text-muted">Leave blank to keep current password</small>
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" class="btn btn-primary" onclick="submitEditEmployee()">
            <i class="bi bi-check-circle"></i> Update Employee
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script>
  <script src="../admin/js/notifications.js"></script>
  <script>
    // Pass employees data to JavaScript
    const employees = <?php echo json_encode($employees, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
  </script>
  <script src="../admin/userManagement.js"></script>
  <script src="../js/mobile-menu.js"></script>

=======
  <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  
  <script>
    const employees = <?php echo json_encode($employees); ?>;
    
    // Auto-refresh schedule every 30 seconds
    let scheduleRefreshInterval;
    let searchTimeout;
    
    function startScheduleRefresh() {
      scheduleRefreshInterval = setInterval(() => {
        const scheduleTab = document.getElementById('schedule');
        if (scheduleTab && scheduleTab.classList.contains('active')) {
          refreshSchedule();
        }
      }, 30000);
    }
    
    function stopScheduleRefresh() {
      if (scheduleRefreshInterval) {
        clearInterval(scheduleRefreshInterval);
      }
    }
    
    // Enhanced refresh function with filters
    function refreshSchedule() {
      const refreshBtn = document.querySelector('.btn-outline-success');
      const originalText = refreshBtn.innerHTML;
      refreshBtn.innerHTML = '<i class="bi bi-arrow-clockwise spinner-border spinner-border-sm"></i> Refreshing...';
      refreshBtn.disabled = true;
      
      const viewType = document.getElementById('scheduleViewType').value;
      const date = document.getElementById('scheduleDatePicker').value;
      const search = document.getElementById('scheduleSearch').value;
      
      let url = 'refresh_schedule.php?';
      const params = new URLSearchParams();
      
      if (viewType === 'today' && date) {
        params.append('view', 'today');
        params.append('date', date);
      } else {
        params.append('view', viewType);
      }
      
      if (search) {
        params.append('search', search);
      }
      
      fetch(url + params.toString())
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            updateScheduleDisplay(data.schedules);
            updateScheduleHeader(data);
          } else {
            console.error('Error:', data.error);
          }
          refreshBtn.innerHTML = originalText;
          refreshBtn.disabled = false;
        })
        .catch(error => {
          console.error('Error refreshing schedule:', error);
          refreshBtn.innerHTML = originalText;
          refreshBtn.disabled = false;
        });
    }
    
    // Update schedule header with count and date info
    function updateScheduleHeader(data) {
      document.getElementById('scheduleDateDisplay').innerHTML = 
        `<i class="bi bi-calendar-week"></i> ${data.dateRangeInfo}`;
      document.getElementById('scheduleCount').textContent = 
        `${data.count} Employee(s)`;
    }
    
    // Update schedule display
    function updateScheduleDisplay(schedules) {
      const container = document.getElementById('scheduleContainer');
      if (!container) return;
      
      if (schedules.length === 0) {
        container.innerHTML = `
          <div class="text-center py-5">
            <i class="bi bi-calendar-x" style="font-size: 3rem; color: #ccc;"></i>
            <p class="text-muted mt-3">No schedules found</p>
          </div>
        `;
        return;
      }
      
      // Group schedules by date for multi-day views
      const viewType = document.getElementById('scheduleViewType').value;
      
      if (viewType === 'today') {
        // Single day view
        container.innerHTML = schedules.map(emp => createEmployeeScheduleCard(emp)).join('');
      } else {
        // Multi-day view - group by date
        const groupedByDate = {};
        schedules.forEach(emp => {
          if (emp.schedule_date) {
            if (!groupedByDate[emp.schedule_date]) {
              groupedByDate[emp.schedule_date] = [];
            }
            groupedByDate[emp.schedule_date].push(emp);
          }
        });
        
        const sortedDates = Object.keys(groupedByDate).sort();
        
        let html = '';
        sortedDates.forEach(date => {
          const dateObj = new Date(date + 'T00:00:00');
          const options = { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' };
          const formattedDate = dateObj.toLocaleDateString('en-US', options);
          
          html += `
            <div class="mb-4">
              <div class="date-group-header">
                <h6 class="text-muted mb-3">
                  <i class="bi bi-calendar3"></i> ${formattedDate}
                  <span class="badge bg-secondary ms-2">${groupedByDate[date].length}</span>
                </h6>
              </div>
          `;
          
          groupedByDate[date].forEach(emp => {
            html += createEmployeeScheduleCard(emp);
          });
          
          html += '</div>';
        });
        
        container.innerHTML = html;
      }
    }
    
    // Create employee schedule card HTML
    function createEmployeeScheduleCard(emp) {
      const photoHtml = emp.photo 
        ? `<img src="${emp.photo}" class="employee-photo-small" alt="${emp.first_name}">`
        : `<div class="employee-photo-small bg-secondary d-flex align-items-center justify-content-center">
             <i class="bi bi-person-fill text-white" style="font-size: 1rem;"></i>
           </div>`;
      
      let scheduleContent;
      if (emp.time_in && emp.is_available) {
        const statusBadges = {
          'in': '<span class="availability-badge availability-in"><i class="bi bi-check-circle-fill"></i> Checked In</span>',
          'out': '<span class="availability-badge availability-out"><i class="bi bi-x-circle-fill"></i> Not Checked In</span>',
          'break': '<span class="availability-badge availability-break"><i class="bi bi-pause-circle-fill"></i> On Break</span>'
        };
        
        scheduleContent = `
          <div class="schedule-table d-flex text-center">
            <div class="flex-fill border-end p-2">
              <small class="text-muted d-block">Schedule</small>
              <strong>${emp.time_in_formatted || 'N/A'} - ${emp.time_out_formatted || 'N/A'}</strong>
            </div>
            <div class="flex-fill border-end p-2">
              <small class="text-muted d-block">Duration</small>
              <strong>${emp.duration || 'N/A'}</strong>
            </div>
            <div class="flex-fill border-end p-2">
              <small class="text-muted d-block">Attendance</small>
              ${statusBadges[emp.availability_status] || statusBadges['out']}
            </div>
            <div class="flex-fill p-2">
              <small class="text-muted d-block">Last Check-in</small>
              <strong style="font-size: 0.85rem;">${emp.last_check_in || 'N/A'}</strong>
            </div>
          </div>
        `;
      } else {
        scheduleContent = `
          <div class="text-center">
            <span class="availability-badge availability-unavailable">
              <i class="bi bi-calendar-x"></i> Unavailable
            </span>
          </div>
        `;
      }
      
      return `
        <div class="employee-schedule-row">
          <div class="employee-schedule-info">
            ${photoHtml}
            <div>
              <div><strong>${emp.first_name} ${emp.middle_name ? emp.middle_name + ' ' : ''}${emp.last_name}</strong></div>
              <div class="text-muted" style="font-size: 0.85rem;">
                <span class="role-badge role-${emp.role}">
                  <i class="bi bi-person-badge-fill"></i> ${emp.role. charAt(0).toUpperCase() + emp.role.slice(1)}
                </span>
              </div>
            </div>
          </div>
          <div class="employee-schedule-details">
            ${scheduleContent}
          </div>
        </div>
      `;
    }
    
    // Event Listeners
    document.addEventListener('DOMContentLoaded', function() {
      // View type change
      document.getElementById('scheduleViewType').addEventListener('change', function() {
        const datePicker = document.getElementById('scheduleDatePicker');
        if (this.value === 'today') {
          datePicker.disabled = false;
        } else {
          datePicker.disabled = true;
        }
        refreshSchedule();
      });
      
      // Date picker change
      document.getElementById('scheduleDatePicker').addEventListener('change', function() {
        document.getElementById('scheduleViewType').value = 'today';
        refreshSchedule();
      });
      
      // Search with debounce
      document.getElementById('scheduleSearch').addEventListener('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
          refreshSchedule();
        }, 500);
      });
    });
    
    // Start auto-refresh when page loads
    startScheduleRefresh();
    
    // Stop refresh when navigating away
    window.addEventListener('beforeunload', stopScheduleRefresh);
    
    // Initialize DataTables
    $(document).ready(function() {
      // Active Users Table
      $('#activeUsersTable').DataTable({
        data: employees.filter(e => e.status === 'active'),
        columns: [
          { 
            data: 'photo',
            render: function(data, type, row) {
              if (data) {
                return `<img src="${data}" class="employee-photo-small" alt="${row.first_name}">`;
              }
              return '<div class="employee-photo-small bg-secondary d-flex align-items-center justify-content-center"><i class="bi bi-person-fill text-white"></i></div>';
            }
          },
          { 
            data: null,
            render: function(data, type, row) {
              return `${row.first_name} ${row.middle_name || ''} ${row.last_name}`.trim();
            }
          },
          { data: 'email' },
          { data: 'phone' },
          { 
            data: 'role',
            render: function(data) {
              return `<span class="role-badge role-${data}">${data.charAt(0).toUpperCase() + data.slice(1)}</span>`;
            }
          },
          { 
            data: 'id',
            render: function(data, type, row) {
              return `
                <button class="btn btn-sm btn-warning" onclick="updateStatus(${data}, 'inactive')">
                  <i class="bi bi-pause-circle"></i> Deactivate
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteEmployee(${data})">
                  <i class="bi bi-trash"></i> Delete
                </button>
              `;
            }
          }
        ]
      });
      
      // Inactive Users Table
      $('#inactiveUsersTable').DataTable({
        data: employees.filter(e => e.status === 'inactive'),
        columns: [
          { 
            data: 'photo',
            render: function(data, type, row) {
              if (data) {
                return `<img src="${data}" class="employee-photo-small" alt="${row.first_name}">`;
              }
              return '<div class="employee-photo-small bg-secondary d-flex align-items-center justify-content-center"><i class="bi bi-person-fill text-white"></i></div>';
            }
          },
          { 
            data: null,
            render: function(data, type, row) {
              return `${row.first_name} ${row.middle_name || ''} ${row.last_name}`.trim();
            }
          },
          { data: 'email' },
          { data: 'phone' },
          { 
            data: 'role',
            render: function(data) {
              return `<span class="role-badge role-${data}">${data.charAt(0).toUpperCase() + data.slice(1)}</span>`;
            }
          },
          { 
            data: 'id',
            render: function(data) {
              return `
                <button class="btn btn-sm btn-success" onclick="updateStatus(${data}, 'active')">
                  <i class="bi bi-check-circle"></i> Activate
                </button>
                <button class="btn btn-sm btn-danger" onclick="deleteEmployee(${data})">
                  <i class="bi bi-trash"></i> Delete
                </button>
              `;
            }
          }
        ]
      });
    });
    
    // Submit Add Employee Form
    function submitAddEmployee() {
      const form = document.getElementById('addEmployeeForm');
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }
      
      const formData = new FormData(form);
      formData.append('action', 'add_employee');
      
      fetch('userManagement.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert(data.message);
          location.reload();
        } else {
          alert(data.message);
        }
      })
      .catch(error => {
        alert('Error adding employee: ' + error);
      });
    }
    
    // Update Employee Status
    function updateStatus(employeeId, status) {
      if (!confirm(`Are you sure you want to ${status === 'active' ? 'activate' : 'deactivate'} this employee?`)) {
        return;
      }
      
      const formData = new FormData();
      formData.append('action', 'update_status');
      formData.append('employee_id', employeeId);
      formData.append('status', status);
      
      fetch('userManagement.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert(data.message);
          location.reload();
        } else {
          alert(data.message);
        }
      })
      .catch(error => {
        alert('Error updating status: ' + error);
      });
    }
    
    // Delete Employee
    function deleteEmployee(employeeId) {
      if (!confirm('Are you sure you want to delete this employee? This action cannot be undone.')) {
        return;
      }
      
      const formData = new FormData();
      formData.append('action', 'delete_employee');
      formData.append('employee_id', employeeId);
      
      fetch('userManagement.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          alert(data.message);
          location.reload();
        } else {
          alert(data.message);
        }
      })
      .catch(error => {
        alert('Error deleting employee: ' + error);
      });
    }
    
    // Logout Function
    function logout() {
      if (confirm('Are you sure you want to logout?')) {
        window.location.href = '/auth/logout.php';
      }
    }
  </script>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
</body>
</html>
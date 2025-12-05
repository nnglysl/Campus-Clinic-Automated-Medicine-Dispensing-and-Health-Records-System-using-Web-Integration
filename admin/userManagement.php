<?php
session_start();
require_once '../db.php';
require_once '../includes/activity_logger.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /auth/login.php');
    exit();
}

$userName = $_SESSION['fname'] . ' ' . ($_SESSION['lname'] ?? '');

// Handle Form Submissions
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
                INSERT INTO employees (user_id, first_name, middle_name, last_name, birth_date, age, gender, email, phone, address, role, password, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([
                $user_id, $first_name, $middle_name, $last_name, $birth_date, $age, 
                $gender, $email, $phone, $address, $role, $hashed_password
            ]);
            
            $pdo->commit();
            
            // Log activity
            $employeeName = trim($first_name . ' ' . $last_name);
            $roleName = ucfirst($role);
            logActivity($pdo, $_SESSION['user_id'], 'Add Employee', "Added new $roleName: $employeeName ($email)");
            
            echo json_encode(['success' => true, 'message' => 'Employee added successfully']);
            exit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit();
        }
    }
    
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
    
    if ($_POST['action'] === 'update_status') {
        try {
            $employee_id = $_POST['employee_id'];
            $status = $_POST['status'];
            
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
            
            // Get employee details for logging
            $stmt = $pdo->prepare("SELECT CONCAT(first_name, ' ', last_name) as name, email, role FROM employees WHERE id = ?");
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
                
                // Log activity
                $roleName = ucfirst($employee['role'] ?? 'Employee');
                logActivity($pdo, $_SESSION['user_id'], 'Delete Employee', "Deleted $roleName: {$employee['name']} ({$employee['email']})");
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
$stmt = $pdo->prepare("
    SELECT 
        u.id as user_id,
        u.fname as first_name,
        u.mname as middle_name,
        u.lname as last_name,
        u.email,
        u.role,
        ds.schedule_date,
        ds.start_time as time_in,
        ds.end_time as time_out,
        ds.is_available,
        ds.schedule_type,
        ds.reason,
        att.status as current_status,
        att.time_in as last_check_in,
        att.date as attendance_date,
        CASE 
            WHEN att.time_in IS NOT NULL AND att.time_out IS NULL THEN 'in'
            WHEN att.time_out IS NOT NULL THEN 'out'
            ELSE 'out'
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Management</title>
  
  <link href="../admin/css/userManagement.css" rel="stylesheet">
  <link href="/finalproject/css/nav.css" rel="stylesheet">
  <link href="../admin/css/responsive.css" rel="stylesheet">
  <link href="../admin/css/notifications.css" rel="stylesheet">
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
      <!-- Mobile Menu Icon -->
      <button type="button" class="mobile-menu-icon" id="mobileMenuBtn" aria-label="Toggle navigation menu" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>
      <?php include 'notification_component.php'; ?>
      <div class="logout-icon" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></div>
    </div>
  </div>

  <div class="main-container">
    <div class="sidebar">
      <div>
        <a href="../admin/adminDashboard.php" class="menu-item">Dashboard</a>
        <a href="../admin/patients.php" class="menu-item">Patient</a>
        <a href="../admin/userManagement.php" class="menu-item active">User Management</a>
        <a href="../admin/inventory.php" class="menu-item">Inventory</a>
        <a href="../admin/activity_logs.php" class="menu-item">Activity Logs</a>
        <a href="../admin/reports.php" class="menu-item">Reports & Analytics</a>
      </div>
      <div class="user-profile">
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
              <div class="row align-items-end g-3">
                <div class="col-md">
                  <label class="form-label">View Range</label>
                  <select id="scheduleViewType" class="form-select">
                    <option value="today" selected>Today</option>
                    <option value="week">This Week</option>
                    <option value="month">This Month</option>
                    <option value="year">This Year</option>
                    <option value="all">All Upcoming</option>
                  </select>
                </div>
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
                  </button>
                </div>
              </div>
            </div>

            <!-- Schedule Header -->
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 class="mb-0" id="scheduleDateDisplay">
                <i class="bi bi-calendar-week"></i> Schedule - <?php echo htmlspecialchars($displayDateFormatted); ?>
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
                      <div class="employee-photo-small bg-secondary d-flex align-items-center justify-content-center">
                        <i class="bi bi-person-fill text-white" style="font-size: 1rem;"></i>
                      </div>
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
  <!-- Add Employee Modal -->
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
                  <option value="doctor">Medical Doctor</option>
                  <option value="dentist">Dentist</option>
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

</body>
</html>
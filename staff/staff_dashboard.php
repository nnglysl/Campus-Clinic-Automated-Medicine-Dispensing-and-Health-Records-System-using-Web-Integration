<?php
include('../db.php');
session_start();

// ✅ Check if user is logged in
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

// Check if user is an employee
if (!in_array($_SESSION['role'], ['doctor', 'dentist', 'nurse', 'staff', 'admin'])) {
    header('Location: ../auth/login.php');
    exit;
}

// ✅ Fetch statistics safely
try {
    // Today's appointments count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE appointment_date = CURDATE()");
    $stmt->execute();
    $today_appointments = $stmt->fetch()['count'] ?? 0;

    // Pending appointments count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE status IN ('scheduled','confirmed')");
    $stmt->execute();
    $pending_appointments = $stmt->fetch()['count'] ?? 0;

    // Total patients count
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM patients");
    $stmt->execute();
    $total_patients = $stmt->fetch()['count'] ?? 0;

    // Today's appointments details
    $stmt = $pdo->prepare("
        SELECT a.*, p.full_name, p.sr_code
        FROM appointments a
        LEFT JOIN patients p ON a.patient_id = p.id
        WHERE a.appointment_date = CURDATE()
        ORDER BY a.appointment_time ASC
    ");
    $stmt->execute();
    $today_appointments_list = $stmt->fetchAll();

    // Recent activities
    $stmt = $pdo->prepare("
        (SELECT 'patient' AS type, full_name AS name, created_at AS activity_time 
         FROM patients 
         ORDER BY created_at DESC LIMIT 5)
        UNION ALL
        (SELECT 'appointment' AS type, 
         CONCAT(p.full_name, ' - ', a.appointment_type, ' Appointment') AS name, 
         a.created_at AS activity_time
         FROM appointments a
         LEFT JOIN patients p ON a.patient_id = p.id
         ORDER BY a.created_at DESC LIMIT 5)
        ORDER BY activity_time DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $today_appointments = 0;
    $pending_appointments = 0;
    $total_patients = 0;
    $today_appointments_list = [];
    $recent_activities = [];
}

// ✅ Helper functions
function formatTime($time) {
    $timestamp = strtotime($time);
    return date('g:i A', $timestamp);
}

function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $diff = time() - $timestamp;
    
    if ($diff < 60) return 'Just now';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 7200) return '1 hour ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 172800) return '1 day ago';
    return floor($diff / 86400) . ' days ago';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - Batangas State University Clinic</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <link rel="stylesheet" href="../css/nav.css" />
  <link rel="stylesheet" href="../staff/css/staff_dashboard.css" />
</head>

<body>
  <!-- Header -->
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
      <a href="../staff/staff_dashboard.php" class="menu-item active">Dashboard</a>
      <a href="../staff/staff_profile.php" class="menu-item">Profile</a>
      <a href="../staff/staff_patients.php" class="menu-item">Patients</a>
      <a href="/finalproject/settings.php" class="menu-item">Settings</a>

      <div class="user-profile">
        <div class="avatar"></div>
        <span><?php echo htmlspecialchars($fullName); ?></span>
      </div>
    </div>

    <!-- Content Area -->
    <main class="content-demo">
      <h2 class="fw-bold">Hello, <span id="userName"><?php echo htmlspecialchars($user_name); ?></span>!</h2>

      <div class="dashboard-layout">
        <!-- LEFT COLUMN -->
        <div class="stats-column">
          <!-- Stats Row -->
          <div class="stats-row">
            <div class="stat-card">
              <div class="stat-icon">👥</div>
              <div>
                <div class="stat-title">Pending Appointment</div>
                <div class="stat-value"><?php echo $pending_appointments; ?></div>
              </div>
            </div>
            <div class="stat-card">
              <div class="stat-icon">🧑</div>
              <div>
                <div class="stat-title">Total Patient</div>
                <div class="stat-value"><?php echo $total_patients; ?></div>
              </div>
            </div>
            <div class="stat-card">
              <div class="stat-icon">📅</div>
              <div>
                <div class="stat-title">Appointment Today</div>
                <div class="stat-value"><?php echo $today_appointments; ?></div>
              </div>
            </div>
          </div>

          <!-- Today's Appointments -->
          <div class="appointments-list mt-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="m-0">
                <i class="bi bi-calendar-check me-2"></i>Today's Appointments
              </h5>
              <a href="/employee/appointments.php" class="see-more-btn">See More</a>
            </div>

            <div id="appointmentsList">
              <?php if (empty($today_appointments_list)): ?>
                <p class="text-muted text-center py-3">No appointments for today</p>
              <?php else: ?>
                <?php foreach ($today_appointments_list as $appointment): ?>
                  <div class="appointment-item">
                    <div class="appointment-info">
                      <div class="appointment-name"><?php echo htmlspecialchars($appointment['full_name'] ?? 'N/A'); ?></div>
                      <div class="appointment-details">
                        <span><i class="bi bi-calendar-event"></i> <?php echo date('M d, Y', strtotime($appointment['appointment_date'])); ?></span>
                        <span><i class="bi bi-clock"></i> <?php echo formatTime($appointment['appointment_time']); ?></span>
                      </div>
                    </div>
                    <span class="appointment-type type-<?php echo strtolower($appointment['appointment_type']); ?>">
                      <i class="bi <?php echo $appointment['appointment_type'] == 'medical' ? 'bi-heart-pulse' : 'bi-tooth'; ?>"></i> 
                      <?php echo ucfirst($appointment['appointment_type']); ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="calendar-column">
          <!-- Calendar -->
          <div class="calendar-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 id="currentMonth" class="m-0">November 2025</h5>
              <div>
                <button class="btn btn-outline-secondary btn-sm me-2" id="prevMonth">
                  <i class="fas fa-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="nextMonth">
                  <i class="fas fa-chevron-right"></i>
                </button>
              </div>
            </div>
            <div class="calendar-grid" id="calendar"></div>
          </div>

          <!-- Recent Activity -->
          <div class="activity-card mt-4">
            <div class="activity-header">
              <h4>Recent Activity</h4>
              <a href="#" class="view-all">View All</a>
            </div>

            <div class="activity-list">
              <?php if (empty($recent_activities)): ?>
                <p class="text-muted text-center py-3">No recent activities</p>
              <?php else: ?>
                <?php foreach ($recent_activities as $activity): ?>
                  <div class="activity-item">
                    <div class="activity-icon">
                      <i class="fas <?php echo $activity['type'] == 'patient' ? 'fa-user-plus' : 'fa-calendar'; ?>"></i>
                    </div>
                    <div class="activity-content">
                      <h6><?php echo $activity['type'] == 'patient' ? 'New Patient Registered' : 'Appointment Scheduled'; ?></h6>
                      <p><?php echo htmlspecialchars($activity['name']); ?></p>
                      <span class="activity-time"><?php echo timeAgo($activity['activity_time']); ?></span>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div> 
    </main>
  </div>


<script>
async function updateSchedule(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    formData.append('action', 'update_schedule');
    
    const response = await fetch('../api/appointment_sync.php', {
        method: 'POST',
        body: formData
    });
    
    const data = await response.json();
    if (data.success) {
        alert('Schedule updated!');
        window.appointmentSync.loadCalendar();
    }
}
</script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script> 
  <script>
    // Calendar
    let currentDate = new Date();
    const monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];

    function generateCalendar(date) {
      const cal = document.getElementById('calendar');
      const monthLabel = document.getElementById('currentMonth');
      
      if (!cal) return;
      
      cal.innerHTML = '';
      monthLabel.textContent = `${monthNames[date.getMonth()]} ${date.getFullYear()}`;

      // Day headers
      ['SUN','MON','TUE','WED','THU','FRI','SAT'].forEach(d => {
        const header = document.createElement('div');
        header.className = 'calendar-day-header';
        header.textContent = d;
        cal.appendChild(header);
      });

      const firstDay = new Date(date.getFullYear(), date.getMonth(), 1);
      const lastDay = new Date(date.getFullYear(), date.getMonth() + 1, 0);

      // Empty cells before month starts
      for (let i = 0; i < firstDay.getDay(); i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day empty';
        cal.appendChild(emptyDay);
      }

      // Calendar days
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      
      for (let d = 1; d <= lastDay.getDate(); d++) {
        const dayDate = new Date(date.getFullYear(), date.getMonth(), d);
        dayDate.setHours(0, 0, 0, 0);
        
        const dayEl = document.createElement('div');
        dayEl.textContent = d;
        
        let cls = 'calendar-day ';
        if (dayDate.getTime() === today.getTime()) {
          cls += 'today';
        } else if (dayDate < today) {
          cls += 'past';
        } else {
          cls += 'future';
        }
        
        dayEl.className = cls;
        cal.appendChild(dayEl);
      }
    }

    // Initialize
    generateCalendar(currentDate);

    // Month navigation
    document.getElementById('prevMonth').addEventListener('click', () => {
      currentDate.setMonth(currentDate.getMonth() - 1);
      generateCalendar(currentDate);
    });

    document.getElementById('nextMonth').addEventListener('click', () => {
      currentDate.setMonth(currentDate.getMonth() + 1);
      generateCalendar(currentDate);
    });
  </script>
</body>
</html>
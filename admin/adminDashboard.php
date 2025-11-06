<?php
session_start();
require_once('../db.php');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /finalproject/auth/login.php');
    exit;
}

// Get user info
$userName = $_SESSION['username'] ?? $_SESSION['fname'] ?? 'Admin';
// Fetch dashboard statistics
try {
    // Get pending appointments count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE status = 'pending'");
    $pendingAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get total patients count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'patient'");
    $totalPatients = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get today's appointments count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM appointments WHERE DATE(appointment_date) = CURDATE() AND status != 'cancelled'");
    $todayAppointments = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get total doctors count
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'doctor'");
    $totalDoctors = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get today's appointments list
    $stmt = $pdo->query("
        SELECT a.*, u.fname, u.lname, d.fname as doctor_fname, d.lname as doctor_lname
        FROM appointments a 
        JOIN users u ON a.patient_id = u.id 
        LEFT JOIN users d ON a.doctor_id = d.id
        WHERE DATE(a.appointment_date) = CURDATE() 
        AND a.status != 'cancelled'
        ORDER BY a.appointment_time ASC
        LIMIT 5
    ");
    $todayAppointmentsList = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get inventory statistics
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM inventory");
    $totalMedicines = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM inventory WHERE quantity <= reorder_level");
    $lowStock = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM inventory WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE()");
    $expiring = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get recent activities
    $stmt = $pdo->query("
        SELECT 
            'patient_registered' as type,
            CONCAT(u.fname, ' ', u.lname) as name,
            u.created_at as activity_time
        FROM users u 
        WHERE u.role = 'patient' 
        UNION ALL
        SELECT 
            'appointment_scheduled' as type,
            CONCAT(u.fname, ' ', u.lname, ' - ', a.appointment_type, ' Appointment') as name,
            a.created_at as activity_time
        FROM appointments a
        JOIN users u ON a.patient_id = u.id
        ORDER BY activity_time DESC
        LIMIT 10
    ");
    $recentActivities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $pendingAppointments = 0;
    $totalPatients = 0;
    $todayAppointments = 0;
    $totalDoctors = 0;
    $todayAppointmentsList = [];
    $totalMedicines = 0;
    $lowStock = 0;
    $expiring = 0;
    $recentActivities = [];
}

/**
 * Format time relative to now
 */
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->d > 0) {
        return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    } elseif ($diff->h > 0) {
        return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    } elseif ($diff->i > 0) {
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    } else {
        return 'Just now';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - Batangas State University</title>
  
  <!-- Stylesheets -->
  <link href="../admin/adminDashboard.css" rel="stylesheet">
  <link href="../admin/nav.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Text:ital@0;1&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <!-- DataTables CDN -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
  <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
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

    <!-- Right-side icons -->
    <div class="header-icons">
      <div class="notification-icon"><i class="bi bi-bell-fill"></i></div>
      <div class="logout-icon" onclick="window.location.href='../logout.php'">
        <i class="bi bi-box-arrow-right"></i>
      </div>
    </div>
  </div>

  <!-- Sidebar + Content -->
  <div class="main-container">
    <div class="sidebar">
      <div>
        <a href="../admin/adminDashboard.php" class="menu-item active">Dashboard</a>
        <a href="../admin/patients.php" class="menu-item">Patient</a>
        <a href="../admin/userManagement.php" class="menu-item">User Management</a>
        <a href="../admin/inventory.php" class="menu-item">Inventory</a>
        <a href="../admin/appointmentManagement.php" class="menu-item">Appointments</a>
        <a href="../admin/reports.php" class="menu-item">Reports & Analytics</a>
      </div>
      <div class="user-profile">
        <div class="avatar"></div>
        <span><?php echo htmlspecialchars($userName); ?></span>
      </div>
    </div>

    <main class="dashboard-content">
      <h2 class="welcome-text">Welcome, <?php echo htmlspecialchars($userName); ?></h2>

      <div class="dashboard-layout">
        <!-- LEFT COLUMN -->
        <div class="left-column">
          <!-- 4 Stats Row -->
          <div class="stats-row">
            <div class="stat-card">
              <div class="stat-icon">👥</div>
              <div>
                <div class="stat-title">Pending Appointment</div>
                <div class="stat-value"><?php echo $pendingAppointments; ?></div>
              </div>
            </div>
            <div class="stat-card">
              <div class="stat-icon">🧑</div>
              <div>
                <div class="stat-title">Total Patient</div>
                <div class="stat-value"><?php echo $totalPatients; ?></div>
              </div>
            </div>
            <div class="stat-card">
              <div class="stat-icon">📅</div>
              <div>
                <div class="stat-title">Appointment Today</div>
                <div class="stat-value"><?php echo $todayAppointments; ?></div>
              </div>
            </div>
            <div class="stat-card">
              <div class="stat-icon">👥</div>
              <div>
                <div class="stat-title">Total Doctor</div>
                <div class="stat-value"><?php echo $totalDoctors; ?></div>
              </div>
            </div>
          </div>

          <!-- Today's Appointments -->
          <div class="appointments-list">
            <div class="d-flex justify-content-between align-items-center mb-2">
              <h5 class="m-0">
                <i class="bi bi-calendar-check me-2"></i>Today's Appointments
              </h5>
              <a href="../admin/appointmentManagement.php" class="see-more-btn">See More</a>
            </div>

            <div id="appointmentsList">
              <?php if (empty($todayAppointmentsList)): ?>
                <div class="text-center text-muted py-4">
                  <i class="bi bi-calendar-x" style="font-size: 2rem;"></i>
                  <p class="mt-2">No appointments scheduled for today</p>
                </div>
              <?php else: ?>
                <?php foreach ($todayAppointmentsList as $apt): ?>
                  <div class="appointment-item">
                    <div class="appointment-info">
                      <p class="appointment-name">
                        <?php echo htmlspecialchars($apt['fname'] . ' ' . $apt['lname']); ?>
                      </p>
                      <div class="appointment-details">
                        <span>
                          <i class="bi bi-clock"></i> 
                          <?php echo date('g:i A', strtotime($apt['appointment_time'])); ?>
                        </span>
                        <?php if (!empty($apt['doctor_fname'])): ?>
                          <span>
                            <i class="bi bi-person"></i> 
                            Dr. <?php echo htmlspecialchars($apt['doctor_lname']); ?>
                          </span>
                        <?php endif; ?>
                      </div>
                    </div>
                    <span class="appointment-type type-<?php echo $apt['appointment_type']; ?>">
                      <?php echo ucfirst($apt['appointment_type']); ?>
                    </span>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>

          <!-- 3 Inventory Stats Row -->
          <div class="stats-row">
            <div class="stat-card">
              <i class="bi bi-box-seam stat-icon text-primary"></i>
              <div>
                <div class="stat-title">Total Items</div>
                <div class="stat-value"><?php echo $totalMedicines; ?></div>
              </div>
            </div>
            <div class="stat-card">
              <i class="bi bi-exclamation-triangle stat-icon text-warning"></i>
              <div>
                <div class="stat-title">Low Stock</div>
                <div class="stat-value"><?php echo $lowStock; ?></div>
              </div>
            </div>
            <div class="stat-card">
              <i class="bi bi-x-circle stat-icon text-danger"></i>
              <div>
                <div class="stat-title">Expiring</div>
                <div class="stat-value"><?php echo $expiring; ?></div>
              </div>
            </div>
          </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="right-column">
          <!-- Calendar -->
          <div class="calendar-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 id="currentMonth" class="m-0">November 2025</h5>
              <div>
                <button class="btn btn-outline-secondary btn-sm me-2" id="prevMonth">
                  <i class="bi bi-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="nextMonth">
                  <i class="bi bi-chevron-right"></i>
                </button>
              </div>
            </div>
            <div class="calendar-grid" id="calendar"></div>
          </div>

          <!-- Recent Activity -->
          <div class="activity-card">
            <div class="activity-header">
              <h4>Recent Activity</h4>
              <a href="#" class="view-all">View All</a>
            </div>

            <div class="activity-list">
              <?php if (empty($recentActivities)): ?>
                <div class="text-center text-muted py-4">
                  <i class="bi bi-activity" style="font-size: 2rem;"></i>
                  <p class="mt-2">No recent activities</p>
                </div>
              <?php else: ?>
                <?php foreach (array_slice($recentActivities, 0, 3) as $activity): ?>
                  <div class="activity-item">
                    <div class="activity-icon">
                      <?php if ($activity['type'] === 'patient_registered'): ?>
                        <i class="bi bi-person-plus"></i>
                      <?php else: ?>
                        <i class="bi bi-calendar"></i>
                      <?php endif; ?>
                    </div>
                    <div class="activity-content">
                      <h6>
                        <?php 
                          echo $activity['type'] === 'patient_registered' 
                            ? 'New Patient Registered' 
                            : 'Appointment Scheduled'; 
                        ?>
                      </h6>
                      <p><?php echo htmlspecialchars($activity['name']); ?></p>
                      <span class="activity-time">
                        <?php echo timeAgo($activity['activity_time']); ?>
                      </span>
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

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Calendar functionality
    const calendar = document.getElementById('calendar');
    const currentMonthEl = document.getElementById('currentMonth');
    const prevMonthBtn = document.getElementById('prevMonth');
    const nextMonthBtn = document.getElementById('nextMonth');

    let currentDate = new Date();
    const today = new Date();

    function renderCalendar() {
      const year = currentDate.getFullYear();
      const month = currentDate.getMonth();
      
      currentMonthEl.textContent = new Date(year, month).toLocaleDateString('en-US', { 
        month: 'long', 
        year: 'numeric' 
      });

      const firstDay = new Date(year, month, 1).getDay();
      const daysInMonth = new Date(year, month + 1, 0).getDate();

      calendar.innerHTML = '';

      // Day headers
      ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
        const header = document.createElement('div');
        header.className = 'calendar-day-header';
        header.textContent = day;
        calendar.appendChild(header);
      });

      // Empty cells
      for (let i = 0; i < firstDay; i++) {
        const empty = document.createElement('div');
        empty.className = 'calendar-day empty';
        calendar.appendChild(empty);
      }

      // Days
      for (let day = 1; day <= daysInMonth; day++) {
        const dayEl = document.createElement('div');
        const dayDate = new Date(year, month, day);
        
        dayEl.className = 'calendar-day';
        dayEl.textContent = day;

        if (dayDate.toDateString() === today.toDateString()) {
          dayEl.classList.add('today');
        } else if (dayDate < today) {
          dayEl.classList.add('past');
        } else {
          dayEl.classList.add('future');
        }

        calendar.appendChild(dayEl);
      }
    }

    prevMonthBtn.addEventListener('click', () => {
      currentDate.setMonth(currentDate.getMonth() - 1);
      renderCalendar();
    });

    nextMonthBtn.addEventListener('click', () => {
      currentDate.setMonth(currentDate.getMonth() + 1);
      renderCalendar();
    });

    renderCalendar();

    // Auto-refresh appointments every 5 minutes
    setInterval(() => {
      location.reload();
    }, 300000);
  </script>

  <style>
    .appointment-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 15px;
      background: #f8f9fa;
      border-radius: 8px;
      margin-bottom: 10px;
      border-left: 4px solid #6b0d00;
    }

    .appointment-info {
      flex: 1;
    }

    .appointment-name {
      font-weight: 600;
      margin: 0 0 8px 0;
      color: #333;
    }

    .appointment-details {
      display: flex;
      gap: 15px;
      font-size: 0.9rem;
      color: #666;
    }

    .appointment-details span {
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .appointment-type {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 0.8rem;
      font-weight: 600;
      text-transform: uppercase;
      white-space: nowrap;
    }

    .type-medical {
      background: #ffebee;
      color: #c62828;
    }

    .type-dental {
      background: #e3f2fd;
      color: #1565c0;
    }

    .see-more-btn {
      color: #6b0d00;
      text-decoration: none;
      font-weight: 500;
      font-size: 0.9rem;
    }

    .see-more-btn:hover {
      text-decoration: underline;
    }

    .activity-item {
      display: flex;
      gap: 15px;
      padding: 15px 0;
      border-bottom: 1px solid #e0e0e0;
    }

    .activity-item:last-child {
      border-bottom: none;
    }

    .activity-icon {
      width: 40px;
      height: 40px;
      background: #f0f0f0;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      color: #6b0d00;
      font-size: 1.2rem;
      flex-shrink: 0;
    }

    .activity-content h6 {
      margin: 0 0 5px 0;
      font-size: 0.95rem;
      font-weight: 600;
      color: #333;
    }

    .activity-content p {
      margin: 0 0 5px 0;
      font-size: 0.9rem;
      color: #666;
    }

    .activity-time {
      font-size: 0.8rem;
      color: #999;
    }
  </style>
</body>
</html>
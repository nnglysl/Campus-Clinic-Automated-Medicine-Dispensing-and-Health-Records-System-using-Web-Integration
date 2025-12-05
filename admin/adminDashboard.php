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
    
<<<<<<< HEAD
    // Get inventory statistics - count distinct medicines/items (all active items)
    $stmt = $pdo->query("SELECT COUNT(DISTINCT item_name) as count FROM inventory WHERE status = 'active' OR status IS NULL");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalMedicines = $result ? (int)$result['count'] : 0;
    
    // If still 0, try without status filter to see if there's any data
    if ($totalMedicines == 0) {
        $stmt = $pdo->query("SELECT COUNT(DISTINCT item_name) as count FROM inventory");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalMedicines = $result ? (int)$result['count'] : 0;
    }
    
    // Get low stock count (quantity <= 10) - count individual batches
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM inventory WHERE quantity <= 10 AND status = 'active'");
    $lowStock = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // Get expiring items count (within 30 days, not expired)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM inventory WHERE expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND expiry_date >= CURDATE() AND status = 'active'");
    $expiring = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
=======
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
    
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
} catch (PDOException $e) {
    error_log("Dashboard Error: " . $e->getMessage());
    $pendingAppointments = 0;
    $totalPatients = 0;
    $todayAppointments = 0;
    $totalDoctors = 0;
<<<<<<< HEAD
    $totalMedicines = 0;
    $lowStock = 0;
    $expiring = 0;
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard - Batangas State University</title>
  
  <!-- Stylesheets -->
<<<<<<< HEAD
  <link href="../admin/css/adminDashboard.css" rel="stylesheet">
  <link href="/finalproject/css/nav.css" rel="stylesheet">
  <link href="../admin/css/notifications.css" rel="stylesheet">
  <link href="../admin/css/responsive.css" rel="stylesheet">
=======
  <link href="../admin/adminDashboard.css" rel="stylesheet">
  <link href="../admin/nav.css" rel="stylesheet">
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
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
<<<<<<< HEAD
        <img src="../img/bsu-logo.png" alt="University Logo" loading="lazy" />
=======
        <img src="../img/bsu-logo.png" alt="University Logo" />
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
      </div>
      <div class="university-name">
        <h1>Batangas State</h1>
        <h1>University</h1>
      </div>
    </div>

<<<<<<< HEAD
    <div class="header-icons">
      <!-- Mobile Menu Icon -->
      <button type="button" class="mobile-menu-icon" id="mobileMenuBtn" aria-label="Toggle navigation menu" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>
      <?php include 'notification_component.php'; ?>
      <div class="logout-icon" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></div>
=======
    <!-- Right-side icons -->
    <div class="header-icons">
      <div class="notification-icon"><i class="bi bi-bell-fill"></i></div>
      <div class="logout-icon" onclick="window.location.href='../logout.php'">
        <i class="bi bi-box-arrow-right"></i>
      </div>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
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
<<<<<<< HEAD
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            <div class="stat-card">
              <i class="bi bi-box-seam stat-icon text-primary"></i>
              <div>
                <div class="stat-title">Total Items</div>
                <div class="stat-value"><?php echo $totalMedicines; ?></div>
              </div>
            </div>
<<<<<<< HEAD
          </div>

          <!-- Alert Sections Row -->
          <div class="row mt-4">
            <div class="col-md-6">
              <div class="alert-section">
                <div class="alert-header">
                  <i class="bi bi-exclamation-triangle-fill"></i>
                  <h5>Low Stock Alert</h5>
                </div>
                <div id="lowStockCards" class="alert-cards-container"></div>
              </div>
            </div>
            <div class="col-md-6">
              <div class="alert-section expired-section">
                <div class="alert-header">
                  <i class="bi bi-calendar-x-fill"></i>
                  <h5>Nearing Expiration Alert</h5>
                </div>
                <div id="expiredCards" class="alert-cards-container"></div>
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
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
<<<<<<< HEAD
                <button class="btn btn-outline-secondary btn-sm me-2" id="prevMonth" aria-label="Previous month">
                  <i class="bi bi-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="nextMonth" aria-label="Next month">
=======
                <button class="btn btn-outline-secondary btn-sm me-2" id="prevMonth">
                  <i class="bi bi-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="nextMonth">
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                  <i class="bi bi-chevron-right"></i>
                </button>
              </div>
            </div>
            <div class="calendar-grid" id="calendar"></div>
          </div>

<<<<<<< HEAD
          <!-- Appointments List -->
          <div class="appointments-card">
            <div class="appointments-header">
              <div>
                <h5>Appointments</h5>
                <p class="selected-date-label" id="selectedDateLabel">Select a date</p>
              </div>
              <span class="appointment-count badge text-bg-primary" id="appointmentsCount">0</span>
            </div>
            <div class="appointments-list-content" id="appointmentsList">
              <div class="no-selection-message">
                <p>Select a date on the calendar to view appointments.</p>
              </div>
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<<<<<<< HEAD
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script>
  <script src="../admin/js/notifications.js"></script>
  <script>
    (() => {
      // Calendar functionality
      const calendar = document.getElementById('calendar');
      const currentMonthEl = document.getElementById('currentMonth');
      const prevMonthBtn = document.getElementById('prevMonth');
      const nextMonthBtn = document.getElementById('nextMonth');
      const appointmentsListEl = document.getElementById('appointmentsList');
      const selectedDateLabel = document.getElementById('selectedDateLabel');
      const appointmentsCountEl = document.getElementById('appointmentsCount');

      if (!calendar || !currentMonthEl || !prevMonthBtn || !nextMonthBtn) {
        console.warn('Dashboard calendar elements are missing.');
        return;
      }

      const today = new Date();
      let currentDate = new Date(today.getFullYear(), today.getMonth(), 1);
      let selectedDate = formatDate(today);
      let appointmentsByDate = {};
      let isLoadingCalendar = false;

    function formatDate(dateObj) {
      const year = dateObj.getFullYear();
      const month = String(dateObj.getMonth() + 1).padStart(2, '0');
      const day = String(dateObj.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    }

    function formatDisplayDate(dateString) {
      if (!dateString) {
        return 'Select a date';
      }
      const [year, month, day] = dateString.split('-').map(Number);
      const dateObj = new Date(year, month - 1, day);
      return dateObj.toLocaleDateString('en-US', {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
        year: 'numeric'
      });
    }

    function formatTime(timeString) {
      if (!timeString) return '';
      const [hour, minute] = timeString.split(':');
      const date = new Date();
      date.setHours(parseInt(hour, 10), parseInt(minute, 10));
      return date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit'
      });
    }

    function groupAppointmentsByDate(appointments = []) {
      return appointments.reduce((acc, appointment) => {
        const date = appointment.appointment_date;
        if (!acc[date]) {
          acc[date] = [];
        }
        acc[date].push(appointment);
        return acc;
      }, {});
    }

    function escapeHtml(value = '') {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function capitalize(value = '') {
      if (!value) return '';
      return value.charAt(0).toUpperCase() + value.slice(1);
    }

    function formatStatus(status = '') {
      return capitalize(status.replace(/_/g, ' '));
    }

    function renderCalendar() {
      const year = currentDate.getFullYear();
      const monthIndex = currentDate.getMonth();

      currentMonthEl.textContent = new Date(year, monthIndex).toLocaleDateString('en-US', {
        month: 'long',
        year: 'numeric'
      });

      const firstDay = new Date(year, monthIndex, 1).getDay();
      const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();

      calendar.innerHTML = '';

=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
      ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
        const header = document.createElement('div');
        header.className = 'calendar-day-header';
        header.textContent = day;
        calendar.appendChild(header);
      });

<<<<<<< HEAD
=======
      // Empty cells
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
      for (let i = 0; i < firstDay; i++) {
        const empty = document.createElement('div');
        empty.className = 'calendar-day empty';
        calendar.appendChild(empty);
      }

<<<<<<< HEAD
      for (let day = 1; day <= daysInMonth; day++) {
        const dateObj = new Date(year, monthIndex, day);
        const dateIso = formatDate(dateObj);
        const dayEl = document.createElement('div');

        dayEl.className = 'calendar-day';
        dayEl.textContent = day;

        if (dateObj.toDateString() === today.toDateString()) {
          dayEl.classList.add('today');
        }

        if (dateIso === selectedDate) {
          dayEl.classList.add('selected');
        }

        if (appointmentsByDate[dateIso]?.length) {
          dayEl.classList.add('has-appointments');
          dayEl.setAttribute('data-count', appointmentsByDate[dateIso].length);
        }

        dayEl.addEventListener('click', () => {
          selectedDate = dateIso;
          renderCalendar();
          showAppointmentsForDate(dateIso);
        });

        calendar.appendChild(dayEl);
      }

      const totalCells = firstDay + daysInMonth;
      const trailingEmpty = (7 - (totalCells % 7)) % 7;
      for (let i = 0; i < trailingEmpty; i++) {
        const empty = document.createElement('div');
        empty.className = 'calendar-day empty';
        calendar.appendChild(empty);
      }
    }

    function showAppointmentsForDate(dateIso) {
      if (!dateIso) {
        appointmentsListEl.innerHTML = `
          <div class="no-selection-message">
            <p>Select a date on the calendar to view appointments.</p>
          </div>
        `;
        appointmentsCountEl.textContent = '0';
        selectedDateLabel.textContent = 'Select a date';
        return;
      }

      const appointments = appointmentsByDate[dateIso] || [];
      selectedDateLabel.textContent = formatDisplayDate(dateIso);
      appointmentsCountEl.textContent = appointments.length;

      if (!appointments.length) {
        appointmentsListEl.innerHTML = `
          <div class="no-selection-message">
            <p>No appointments for this day.</p>
          </div>
        `;
        return;
      }

      appointmentsListEl.innerHTML = '';

      appointments
        .sort((a, b) => a.appointment_time.localeCompare(b.appointment_time))
        .forEach(appointment => {
          const item = document.createElement('div');
          item.className = 'appointment-item';
          const statusValue = (appointment.status || 'pending').toLowerCase();
          const statusClass = statusValue.replace(/[^a-z-]/g, '') || 'pending';

          item.innerHTML = `
            <div class="appointment-info">
              <div class="appointment-name">${escapeHtml(`${appointment.fname ?? ''} ${appointment.lname ?? ''}`.trim() || 'Patient')}</div>
              <div class="appointment-details">
                <span><i class="bi bi-clock-fill"></i> ${formatTime(appointment.appointment_time)}</span>
                <span><i class="bi bi-clipboard-heart"></i> ${escapeHtml(capitalize(appointment.appointment_type ?? 'N/A'))}</span>
              </div>
            </div>
            <div class="appointment-meta">
              <span class="appointment-status status-${statusClass}">
                ${escapeHtml(formatStatus(statusValue))}
              </span>
            </div>
          `;

          appointmentsListEl.appendChild(item);
        });
    }

    async function loadCalendarData() {
      if (isLoadingCalendar) return;
      isLoadingCalendar = true;
      calendar.classList.add('is-loading');

      const monthParam = String(currentDate.getMonth() + 1).padStart(2, '0');
      const yearParam = currentDate.getFullYear();

      try {
        const response = await fetch(`../crud/appointment_sync.php?action=get_calendar&month=${monthParam}&year=${yearParam}`);
        const data = await response.json();

        if (!data.success) {
          throw new Error(data.error || 'Failed to fetch calendar data.');
        }

        appointmentsByDate = groupAppointmentsByDate(data.appointments);
        renderCalendar();

        if (
          !selectedDate ||
          (new Date(selectedDate).getMonth() !== currentDate.getMonth() ||
          new Date(selectedDate).getFullYear() !== currentDate.getFullYear())
        ) {
          selectedDate = formatDate(new Date(yearParam, currentDate.getMonth(), 1));
        }

        showAppointmentsForDate(selectedDate);
      } catch (error) {
        console.error('Calendar load error:', error);
        appointmentsListEl.innerHTML = `
          <div class="no-selection-message">
            <p>${error.message}</p>
          </div>
        `;
      } finally {
        calendar.classList.remove('is-loading');
        isLoadingCalendar = false;
      }
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    }

    prevMonthBtn.addEventListener('click', () => {
      currentDate.setMonth(currentDate.getMonth() - 1);
<<<<<<< HEAD
      loadCalendarData();
=======
      renderCalendar();
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    });

    nextMonthBtn.addEventListener('click', () => {
      currentDate.setMonth(currentDate.getMonth() + 1);
<<<<<<< HEAD
      loadCalendarData();
    });

      loadCalendarData();

      setInterval(() => {
        loadCalendarData();
      }, 300000);
    })();

    // Fetch and display low stock items
    async function fetchLowStockItems() {
      try {
        const response = await fetch('../api/get_low_stock.php');
        const data = await response.json();
        
        const container = document.getElementById('lowStockCards');
        
        if (data.success && data.items.length > 0) {
          container.innerHTML = data.items.map(item => `
            <div class="alert-card">
              <div class="alert-card-header">
                <h6 class="alert-card-title">${escapeHtmlForAlerts(item.item_name)}</h6>
                <span class="alert-badge">${item.quantity_on_hand} left</span>
              </div>
              <div class="alert-card-body">
                <p><i class="bi bi-upc-scan"></i> ${escapeHtmlForAlerts(item.batch_number)}</p>
                <p><i class="bi bi-bag"></i> Dispensed: ${item.quantity_dispensed || 0}</p>
                <p><i class="bi bi-calendar-event"></i> Exp: ${new Date(item.expiration_date).toLocaleDateString()}</p>
              </div>
            </div>
          `).join('');
        } else {
          container.innerHTML = '<p class="text-muted text-center py-3">No low stock items</p>';
        }
      } catch (error) {
        console.error('Error fetching low stock:', error);
        const container = document.getElementById('lowStockCards');
        if (container) {
          container.innerHTML = '<p class="text-danger text-center py-3">Error loading data</p>';
        }
      }
    }

    // Fetch and display expiring items
    async function fetchExpiringItems() {
      try {
        const response = await fetch('../api/get_expiring_items.php');
        const data = await response.json();
        
        const container = document.getElementById('expiredCards');
        
        if (data.success && data.items.length > 0) {
          container.innerHTML = data.items.map(item => {
            const daysLeft = Math.ceil((new Date(item.expiration_date) - new Date()) / (1000 * 60 * 60 * 24));
            return `
              <div class="alert-card">
                <div class="alert-card-header">
                  <h6 class="alert-card-title">${escapeHtmlForAlerts(item.item_name)}</h6>
                  <span class="alert-badge">${daysLeft} days left</span>
                </div>
                <div class="alert-card-body">
                  <p><i class="bi bi-upc-scan"></i> ${escapeHtmlForAlerts(item.batch_number)}</p>
                  <p><i class="bi bi-box-seam"></i> Qty: ${item.quantity_on_hand}</p>
                  <p><i class="bi bi-calendar-x"></i> ${new Date(item.expiration_date).toLocaleDateString()}</p>
                </div>
              </div>
            `;
          }).join('');
        } else {
          container.innerHTML = '<p class="text-muted text-center py-3">No items nearing expiration</p>';
        }
      } catch (error) {
        console.error('Error fetching expiring items:', error);
        const container = document.getElementById('expiredCards');
        if (container) {
          container.innerHTML = '<p class="text-danger text-center py-3">Error loading data</p>';
        }
      }
    }

    // Helper function to escape HTML (for use outside IIFE)
    function escapeHtmlForAlerts(value = '') {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    // Load inventory alerts when page loads
    document.addEventListener('DOMContentLoaded', function() {
      fetchLowStockItems();
      fetchExpiringItems();
      
      // Refresh every 30 seconds for real-time updates
      setInterval(() => {
        fetchLowStockItems();
        fetchExpiringItems();
      }, 30000);
    });
    
    // Also refresh when page becomes visible (user switches back to tab)
    document.addEventListener('visibilitychange', function() {
      if (!document.hidden) {
        fetchLowStockItems();
        fetchExpiringItems();
      }
    });

// Mobile Menu Toggle
document.addEventListener('DOMContentLoaded', () => {
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const sidebar = document.querySelector('.sidebar');
  const body = document.body;
  
  if (mobileMenuBtn && sidebar) {
    mobileMenuBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      const isActive = sidebar.classList.toggle('active');
      body.classList.toggle('menu-open');
      mobileMenuBtn.setAttribute('aria-expanded', isActive ? 'true' : 'false');
      
      const icon = mobileMenuBtn.querySelector('i');
      if (isActive) {
        icon.classList.remove('bi-list');
        icon.classList.add('bi-x-lg');
      } else {
        icon.classList.remove('bi-x-lg');
        icon.classList.add('bi-list');
      }
    });
    
    document.addEventListener('click', (e) => {
      if (sidebar.classList.contains('active') && 
          !sidebar.contains(e.target) && 
          !mobileMenuBtn.contains(e.target)) {
        closeMobileMenu();
      }
    });
    
    document.querySelectorAll('.sidebar .menu-item').forEach(item => {
      item.addEventListener('click', () => {
        closeMobileMenu();
      });
    });
    
    function closeMobileMenu() {
      sidebar.classList.remove('active');
      body.classList.remove('menu-open');
      mobileMenuBtn.setAttribute('aria-expanded', 'false');
      const icon = mobileMenuBtn.querySelector('i');
      icon.classList.remove('bi-x-lg');
      icon.classList.add('bi-list');
    }
    
    sidebar.addEventListener('click', (e) => {
      e.stopPropagation();
    });
    
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && sidebar.classList.contains('active')) {
        closeMobileMenu();
      }
    });
  }
});

  </script>

  
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
</body>
</html>
<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ✅ Define user info safely
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['fname'] ?? 'Dentist';
$last_name = $_SESSION['lname'] ?? '';
$user_role = $_SESSION['role'] ?? 'dentist';
$user_name = $first_name;
$fullName = trim($first_name . ' ' . $last_name);

// Check if user is logged in and has a valid role
if (!isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
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

} catch (PDOException $e) {
    error_log("Dashboard query error: " . $e->getMessage());
    $today_appointments = 0;
    $pending_appointments = 0;
    $total_patients = 0;
}

// ✅ Helper functions
function formatTime($time) {
    $timestamp = strtotime($time);
    return date('g:i A', $timestamp);
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
  <link rel="stylesheet" href="../dental/css/dental_dashboard.css" />
  <link rel="stylesheet" href="../admin/css/notifications.css" />
  <link rel="stylesheet" href="../dental/css/responsive.css" />
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
      <a href="../dental/dental_dashboard.php" class="menu-item active">Dashboard</a>
      <a href="../dental/dental_profile.php" class="menu-item">Profile</a>
      <a href="../dental/dental_patients.php" class="menu-item">Patients</a>
      <a href="../dental/dental_appointments.php" class="menu-item">Appointments</a>
      <a href="../dental/dental_settings.php" class="menu-item">Settings</a>

      <div class="user-profile">
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
        </div>

        <!-- RIGHT COLUMN -->
        <div class="calendar-column">
          <!-- Calendar -->
          <div class="calendar-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
              <h5 id="currentMonth" class="m-0">November 2025</h5>
              <div>
                <button class="btn btn-outline-secondary btn-sm me-2" id="prevMonth" aria-label="Previous month">
                  <i class="bi bi-chevron-left"></i>
                </button>
                <button class="btn btn-outline-secondary btn-sm" id="nextMonth" aria-label="Next month">
                  <i class="bi bi-chevron-right"></i>
                </button>
              </div>
            </div>
            <div class="calendar-grid" id="calendar"></div>
          </div>

          <!-- Appointments List -->
          <div class="appointments-card">
            <div class="appointments-header">
              <div>
                <h5>Appointments</h5>
                <p class="selected-date-label" id="selectedDateLabel">All Dental Appointments</p>
              </div>
              <span class="appointment-count badge text-bg-primary" id="appointmentsCount">0</span>
            </div>
            <div class="appointments-list-content" id="appointmentsList">
              <div class="no-selection-message">
                <p>Loading appointments...</p>
              </div>
            </div>
          </div>
        </div>
      </div> 
    </main>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script>
  <script src="js/notifications.js"></script> 
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
      let selectedDate = null; // null means show all appointments
      let appointmentsByDate = {};
      let allAppointments = []; // Store all appointments
      let isLoadingCalendar = false;
      const appointmentType = 'dental'; // Filter for dental appointments only

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

      ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
        const header = document.createElement('div');
        header.className = 'calendar-day-header';
        header.textContent = day;
        calendar.appendChild(header);
      });

      for (let i = 0; i < firstDay; i++) {
        const empty = document.createElement('div');
        empty.className = 'calendar-day empty';
        calendar.appendChild(empty);
      }

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
          // Toggle: if same date clicked, show all; otherwise filter to that date
          if (selectedDate === dateIso) {
            selectedDate = null;
          } else {
            selectedDate = dateIso;
          }
          renderCalendar();
          showAppointmentsForDate(selectedDate);
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
      let appointments = [];
      
      if (dateIso) {
        // Show appointments for selected date
        appointments = appointmentsByDate[dateIso] || [];
        selectedDateLabel.textContent = formatDisplayDate(dateIso);
      } else {
        // Show all appointments
        appointments = allAppointments;
        selectedDateLabel.textContent = 'All Dental Appointments';
      }
      
      appointmentsCountEl.textContent = appointments.length;

      if (!appointments.length) {
        appointmentsListEl.innerHTML = `
          <div class="no-selection-message">
            <p>${dateIso ? 'No appointments for this day.' : 'No appointments found.'}</p>
          </div>
        `;
        return;
      }

      appointmentsListEl.innerHTML = '';

      // Sort by date and time
      appointments
        .sort((a, b) => {
          const dateCompare = a.appointment_date.localeCompare(b.appointment_date);
          if (dateCompare !== 0) return dateCompare;
          return a.appointment_time.localeCompare(b.appointment_time);
        })
        .forEach(appointment => {
          const item = document.createElement('div');
          item.className = 'appointment-item';
          const statusValue = (appointment.status || 'pending').toLowerCase();
          const statusClass = statusValue.replace(/[^a-z-]/g, '') || 'pending';

          item.innerHTML = `
            <div class="appointment-info">
              <div class="appointment-name">${escapeHtml(`${appointment.fname ?? ''} ${appointment.lname ?? ''}`.trim() || 'Patient')}</div>
              <div class="appointment-details">
                <span><i class="bi bi-calendar-event"></i> ${formatDateDisplay(appointment.appointment_date)}</span>
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

    function formatDateDisplay(dateString) {
      if (!dateString) return '';
      const [year, month, day] = dateString.split('-').map(Number);
      const dateObj = new Date(year, month - 1, day);
      return dateObj.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      });
    }

    async function loadCalendarData() {
      if (isLoadingCalendar) return;
      isLoadingCalendar = true;
      calendar.classList.add('is-loading');

      const monthParam = String(currentDate.getMonth() + 1).padStart(2, '0');
      const yearParam = currentDate.getFullYear();

      try {
        const response = await fetch(`../crud/appointment_sync.php?action=get_calendar&month=${monthParam}&year=${yearParam}&appointment_type=${appointmentType}`);
        const data = await response.json();

        if (!data.success) {
          throw new Error(data.error || 'Failed to fetch calendar data.');
        }

        appointmentsByDate = groupAppointmentsByDate(data.appointments);
        
        // Update all appointments list (merge with existing)
        data.appointments.forEach(apt => {
          if (!allAppointments.find(a => a.id === apt.id)) {
            allAppointments.push(apt);
          }
        });
        
        renderCalendar();
        
        // Show all appointments by default, or selected date if one is selected
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
    }

    prevMonthBtn.addEventListener('click', () => {
      currentDate.setMonth(currentDate.getMonth() - 1);
      loadCalendarData();
    });

    nextMonthBtn.addEventListener('click', () => {
      currentDate.setMonth(currentDate.getMonth() + 1);
      loadCalendarData();
    });

      // Function to load all appointments (not just current month)
      async function loadAllAppointments() {
        try {
          // Get appointments for current month and next 2 months to show upcoming appointments
          const today = new Date();
          const monthsToLoad = [];
          for (let i = 0; i < 3; i++) {
            const date = new Date(today.getFullYear(), today.getMonth() + i, 1);
            monthsToLoad.push({
              month: String(date.getMonth() + 1).padStart(2, '0'),
              year: date.getFullYear()
            });
          }
          
          // Load appointments for multiple months
          const promises = monthsToLoad.map(({month, year}) => 
            fetch(`../crud/appointment_sync.php?action=get_calendar&month=${month}&year=${year}&appointment_type=${appointmentType}`)
              .then(res => res.json())
          );
          
          const results = await Promise.all(promises);
          
          // Combine all appointments
          allAppointments = [];
          results.forEach(result => {
            if (result.success && result.appointments) {
              result.appointments.forEach(apt => {
                if (!allAppointments.find(a => a.id === apt.id)) {
                  allAppointments.push(apt);
                }
              });
            }
          });
          
          // Update appointments by date for calendar indicators
          appointmentsByDate = groupAppointmentsByDate(allAppointments);
          
          // Refresh display
          showAppointmentsForDate(selectedDate);
          renderCalendar();
        } catch (error) {
          console.error('Error loading all appointments:', error);
        }
      }

      // Load all appointments on page load
      loadAllAppointments();
      loadCalendarData();

      setInterval(() => {
        loadAllAppointments();
        loadCalendarData();
      }, 300000);
    })();
    async function fetchLowStockItems() {
  try {
    const response = await fetch('../api/get_low_stock.php');
    const data = await response.json();
    
    const container = document.getElementById('lowStockCards');
    
    if (data.success && data.items.length > 0) {
      container.innerHTML = data.items.map(item => `
        <div class="alert-card">
          <div class="alert-card-header">
            <h6 class="alert-card-title">${item.item_name}</h6>
            <span class="alert-badge">${item.quantity_on_hand} left</span>
          </div>
          <div class="alert-card-body">
            <p><i class="bi bi-upc-scan"></i> ${item.batch_number}</p>
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
    document.getElementById('lowStockCards').innerHTML = '<p class="text-danger text-center py-3">Error loading data</p>';
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
              <h6 class="alert-card-title">${item.item_name}</h6>
              <span class="alert-badge">${daysLeft} days left</span>
            </div>
            <div class="alert-card-body">
              <p><i class="bi bi-upc-scan"></i> ${item.batch_number}</p>
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
    document.getElementById('expiredCards').innerHTML = '<p class="text-danger text-center py-3">Error loading data</p>';
  }
}

// Load data when page loads
document.addEventListener('DOMContentLoaded', function() {
  fetchLowStockItems();
  fetchExpiringItems();
  
  // Refresh every 5 minutes
  setInterval(() => {
    fetchLowStockItems();
    fetchExpiringItems();
  }, 300000);
});

// Initialize notification system after jQuery is ready
$(document).ready(function() {
  if (window.DentalNotificationSystem) {
    DentalNotificationSystem.init();
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
</body>
</html>
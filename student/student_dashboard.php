<?php
session_start();
require_once('../config/database.php');

<<<<<<< HEAD
=======
// Check if user is logged in
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
<<<<<<< HEAD

// Define all user variables properly
$userId = $_SESSION['user_id'];
$userName = $_SESSION['fname'] ?? 'Student';
$userEmail = $_SESSION['email'] ?? '';
$firstName = $_SESSION['fname'] ?? 'Student';
$lastName = $_SESSION['lname'] ?? '';
$fullName = trim($firstName . ' ' . $lastName);
$userRole = $_SESSION['role'] ?? 'student';
=======
$userId = $_SESSION['user_id'];
$userName = $_SESSION['fname'] ?? 'Student';
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
<<<<<<< HEAD
  <title>Dashboard - BSU Clinic System</title>

  <link href="css/nav.css" rel="stylesheet" />
  <link href="css/dashboard.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="../admin/css/notifications.css" rel="stylesheet" />
  <link href="css/responsive.css" rel="stylesheet" />
  
  <style>
    .calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 5px;
      width: 100%;
      max-width: 100%;
    }
    
    .calendar-day {
      padding: 10px;
      text-align: center;
      border-radius: 5px;
      background: white;
      font-size: 0.9rem;
      border: 1px solid #e0e0e0;
      transition: all 0.2s;
      position: relative;
      cursor: default;
      min-height: 40px;
      display: flex;
      align-items: center;
      justify-content: center;
      aspect-ratio: 1;
      word-break: break-word;
      overflow: hidden;
    }
    
    .calendar-day-header {
      font-weight: 600;
      color: #666;
      padding: 5px;
      font-size: 0.85rem;
      text-align: center;
      min-height: 30px;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    
    .calendar-day.empty {
      background: transparent;
      border: none;
    }
    
    .calendar-day.has-appointment {
      background: #7e1414 !important;
      color: white !important;
      font-weight: bold;
      cursor: pointer;
    }
    
    .calendar-day.has-appointment:hover {
      background: #9a1a1a !important;
      transform: scale(1.05);
      z-index: 10;
    }
    
    .appointment-tooltip {
      position: absolute;
      bottom: 100%;
      left: 50%;
      transform: translateX(-50%);
      margin-bottom: 5px;
      padding: 8px 12px;
      background: #7e1414;
      color: white;
      border-radius: 5px;
      font-size: 0.75rem;
      white-space: nowrap;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.2s;
      z-index: 1000;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    .appointment-tooltip::after {
      content: '';
      position: absolute;
      top: 100%;
      left: 50%;
      transform: translateX(-50%);
      border: 5px solid transparent;
      border-top-color: #7e1414;
    }
    
    .calendar-day.has-appointment:hover .appointment-tooltip {
      opacity: 1;
    }
    
    .calendar-day.available {
      background: #f0f0f0;
    }
    
    .calendar-day.available.medical {
      background: #90EE90;
      color: #1b5e20;
      font-weight: 600;
    }
    
    .calendar-day.available.dental {
      background: #87CEEB;
      color: #0d47a1;
      font-weight: 600;
    }
    
    .calendar-day.available.both {
      background: linear-gradient(135deg, #90EE90 50%, #87CEEB 50%);
      color: #1b5e20;
      font-weight: 600;
      position: relative;
    }
    
    .calendar-day.unavailable {
      background: #e8e8e8;
      color: #999;
    }
    
    .calendar-legend {
      display: flex;
      flex-wrap: wrap;
      gap: 15px;
      margin-top: 15px;
      padding-top: 15px;
      border-top: 1px solid #e0e0e0;
      justify-content: center;
      width: 100%;
    }
    
    .legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.85rem;
      color: #666;
    }
    
    .legend-color {
      width: 20px;
      height: 20px;
      border-radius: 4px;
      border: 1px solid rgba(0,0,0,0.1);
    }
    
    .legend-color.appointment {
      background: #7e1414;
    }
    
    .legend-color.medical {
      background: #90EE90;
    }
    
    .legend-color.dental {
      background: #87CEEB;
    }
    
    .legend-color.both {
      background: linear-gradient(135deg, #90EE90 50%, #87CEEB 50%);
    }
    
    .legend-color.unavailable {
      background: #e8e8e8;
    }
    
    .month-title {
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: 15px;
      text-align: center;
    }
    
    /* Responsive Calendar Styles */
    @media (max-width: 991px) {
      .calendar-container {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
      
      .calendar-grid {
        min-width: 100%;
        gap: 3px;
      }
      
      .calendar-day {
        padding: 6px 4px;
        font-size: 0.75rem;
        min-height: 35px;
      }
      
      .calendar-day-header {
        padding: 4px 2px;
        font-size: 0.7rem;
        min-height: 25px;
      }
      
      .month-title {
        font-size: 1rem;
        margin-bottom: 12px;
      }
      
      .calendar-legend {
        flex-direction: column;
        gap: 8px;
        margin-top: 12px;
        padding-top: 12px;
      }
      
      .legend-item {
        font-size: 0.75rem;
        gap: 6px;
      }
      
      .legend-color {
        width: 16px;
        height: 16px;
      }
    }
    
    @media (max-width: 768px) {
      .calendar-container {
        padding: 12px !important;
      }
      
      .calendar-grid {
        gap: 2px;
      }
      
      .calendar-day {
        padding: 4px 2px;
        font-size: 0.7rem;
        min-height: 32px;
        border-radius: 3px;
      }
      
      .calendar-day-header {
        padding: 3px 1px;
        font-size: 0.65rem;
        min-height: 22px;
      }
      
      .month-title {
        font-size: 0.9rem;
        margin-bottom: 10px;
      }
      
      .appointment-tooltip {
        font-size: 0.7rem;
        padding: 6px 10px;
        white-space: normal;
        max-width: 120px;
        word-wrap: break-word;
      }
    }
    
    @media (max-width: 600px) {
      .calendar-day {
        padding: 3px 1px;
        font-size: 0.65rem;
        min-height: 30px;
      }
      
      .calendar-day-header {
        padding: 2px 1px;
        font-size: 0.6rem;
        min-height: 20px;
      }
      
      .month-title {
        font-size: 0.85rem;
      }
    }

    .stat-card {
      background: white !important;
      border: 2px solid #7e1414;
      transition: transform 0.2s;
    }

    .stat-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 5px 15px rgba(0,0,0,0.2) !important;
    }

    .stat-card h4 {
      color: #7e1414;
      font-size: 2rem;
      font-weight: bold;
      margin: 0;
    }

    .stat-card p {
      color: #666;
      margin: 0;
      font-size: 0.9rem;
    }

    .appointment-card {
      background: white;
      border-left: 4px solid #7e1414;
      transition: all 0.3s;
    }

    .appointment-card:hover {
      transform: translateX(5px);
      box-shadow: 0 3px 10px rgba(0,0,0,0.15) !important;
    }

    .appointment-type-badge {
      display: inline-block;
      padding: 4px 12px;
      border-radius: 20px;
      font-size: 0.85rem;
      font-weight: 600;
      margin-bottom: 8px;
    }

    .appointment-type-badge.dental {
      background: #e3f2fd;
      color: #1976d2;
    }

    .appointment-type-badge.medical {
      background: #f3e5f5;
      color: #7b1fa2;
    }

    .cancel-btn {
      border-color: #7e1414 !important;
      color: #7e1414 !important;
    }

    .cancel-btn:hover {
      background: #7e1414 !important;
      color: white !important;
    }

    .empty-state {
      text-align: center;
      padding: 30px;
      color: #999;
    }

    .empty-state i {
      font-size: 3rem;
      color: #ddd;
      margin-bottom: 15px;
    }
  </style>
</head>

<body class="dashboard-page">

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
  <div class="main-container d-flex">

    <div class="sidebar">
      <a href="../student/student_dashboard.php" class="menu-item active">Dashboard</a>
      <a href="../student/profile.php" class="menu-item">Profile</a>
      <a href="../student/appointment.php" class="menu-item">Appointment</a>
      <a href="../student/records.php" class="menu-item">Health Records</a>
      <a href="../student/settings.php" class="menu-item ">Settings</a>
    
    <div class="user-profile">
      <div class="avatar"></div>
        <span><?php echo htmlspecialchars($fullName); ?></span>
      </div>
    </div>

    <main class="content-demo flex-grow-1 p-4">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12">
            <h2 class="fw-bold mb-4">Hello, <?= htmlspecialchars($userName) ?>!</h2>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-12 col-lg-8 col-xl-8 col-xxl-8">
            <div class="stats-section mb-4">
              <div class="row g-3">
                <div class="col-6 col-sm-4 col-md-4 col-lg-4 col-xl-4 col-xxl-4">
                  <div class="stat-card p-3 text-center rounded shadow-sm">
                    <h4 id="completedCount">0</h4>
                    <p>Completed</p>
                  </div>
                </div>
                <div class="col-6 col-sm-4 col-md-4 col-lg-4 col-xl-4 col-xxl-4">
                  <div class="stat-card p-3 text-center rounded shadow-sm">
                    <h4 id="pendingCount">0</h4>
                    <p>Pending</p>
                  </div>
                </div>
                <div class="col-12 col-sm-4 col-md-4 col-lg-4 col-xl-4 col-xxl-4">
                  <div class="stat-card p-3 text-center rounded shadow-sm">
                    <h4 id="upcomingCount">0</h4>
                    <p>Upcoming</p>
                  </div>
                </div>
              </div>
            </div>

            <div class="appointments-list">
              <h5 class="mb-3">
                <i class="bi bi-calendar-check me-2" style="color: #7e1414;"></i>
                My Appointments
              </h5>
              <div id="appointmentsList">
                <div class="text-center py-4">
                  <div class="spinner-border text-danger" role="status">
                    <span class="visually-hidden">Loading...</span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-12 col-lg-4 col-xl-4 col-xxl-4">
            <div class="calendar-container p-3 bg-light rounded shadow-sm">
              <h5>
                <i class="bi bi-calendar3 me-2" style="color: #7e1414;"></i>
                Calendar
              </h5>
              <div id="dashboardCalendar" class="mt-3"></div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script src="../js/logout.js"></script>
  <script src="js/notifications.js"></script>
  <script>
const APPOINTMENTS_API = '../crud/appointment_sync.php';
const AVAILABILITY_API = '../crud/get_available_slots.php';

let activeCategory = 'medical';
let isDashboardLoading = false;
let calendarReferenceDate = new Date();
let lastUpdateTimestamp = 0;
let pollingInterval = null;

document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.category-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const category = btn.dataset.category;
      if (category && category !== activeCategory) {
        activeCategory = category;
        setActiveCategoryButton(category);
        renderDashboard(true);
      }
    });
  });
  setActiveCategoryButton(activeCategory);
  renderDashboard(true);
  
  // Start real-time polling every 5 seconds
  startRealTimePolling();
  
  // Also listen for custom events from other pages
  window.addEventListener('appointmentUpdated', () => {
    console.log('Appointment update event received, refreshing...');
    renderDashboard(true);
  });
  
  // Handle page visibility - pause polling when hidden
  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      stopRealTimePolling();
    } else {
      startRealTimePolling();
      renderDashboard(true); // Refresh when coming back
    }
  });
});

function setActiveCategoryButton(category) {
  document.querySelectorAll('.category-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.category === category);
  });
}

async function renderDashboard(forceRefresh) {
  if (isDashboardLoading && !forceRefresh) {
    return;
  }
  isDashboardLoading = true;
  showAppointmentsLoading();
  try {
    // Fetch all appointments (including completed) for stats
    const allAppointmentsParams = new URLSearchParams({
      action: 'get_appointments',
      filter: 'all'
    });
    const allAppointmentsResponse = await fetch(`${APPOINTMENTS_API}?${allAppointmentsParams.toString()}`);
    const allAppointmentsResult = await allAppointmentsResponse.json();
    const allAppointments = allAppointmentsResult.appointments || [];
    
    // Fetch active appointments (excluding completed/cancelled) for display
    const [activeAppointments, medicalAvailability, dentalAvailability] = await Promise.all([
      fetchAppointments(activeCategory),
      fetchAvailability('medical'),
      fetchAvailability('dental')
    ]);
    
    // Merge availability data with department info
    const availability = {};
    
    // Process medical availability (green)
    Object.keys(medicalAvailability).forEach(date => {
      const medInfo = medicalAvailability[date];
      // Only add if status is available or partially-available
      if (medInfo.status === 'available' || medInfo.status === 'partially-available') {
        availability[date] = {
          ...medInfo,
          department: 'medical'
        };
      }
    });
    
    // Process dental availability (blue) - merge with medical if same date
    Object.keys(dentalAvailability).forEach(date => {
      const dentInfo = dentalAvailability[date];
      // Only add if status is available or partially-available
      if (dentInfo.status === 'available' || dentInfo.status === 'partially-available') {
        if (availability[date]) {
          // Date has both medical and dental availability
          availability[date] = {
            ...availability[date],
            hasMedical: true,
            hasDental: true,
            department: 'both',
            // Combine slot counts
            total_slots: (availability[date].total_slots || 0) + (dentInfo.total_slots || 0),
            available_slots: (availability[date].available_slots || 0) + (dentInfo.available_slots || 0),
            booked_slots: (availability[date].booked_slots || 0) + (dentInfo.booked_slots || 0)
          };
        } else {
          // Only dental availability
          availability[date] = {
            ...dentInfo,
            department: 'dental'
          };
        }
      }
    });
    
    // Update stats with all appointments
    updateStats(allAppointments);
    // Render only active appointments
    renderAppointmentsList(activeAppointments);
    
    // Filter scheduled appointments (both medical and dental) for calendar
    // Only show scheduled or confirmed appointments (not completed/cancelled)
    const scheduledAppointments = allAppointments.filter(app => 
      (app.status === 'scheduled' || app.status === 'confirmed') &&
      (app.appointment_type === 'medical' || app.appointment_type === 'dental')
    );
    
    renderCalendar(document.getElementById("dashboardCalendar"), scheduledAppointments, availability);
    
    // Update last update timestamp
    if (allAppointmentsResult.timestamp) {
      lastUpdateTimestamp = allAppointmentsResult.timestamp;
    }
  } catch (error) {
    console.error('Dashboard load error:', error);
    showAppointmentsError(error.message || 'Failed to load appointments');
  } finally {
    isDashboardLoading = false;
  }
}

async function fetchAppointments(category) {
  const params = new URLSearchParams({
    action: 'get_appointments',
    filter: 'all' // This will return only active appointments (scheduled/confirmed) for students
    // Removed type filter to fetch both dental and medical
  });
  const response = await fetch(`${APPOINTMENTS_API}?${params.toString()}`);
  const result = await response.json();
  if (!result.success) {
    throw new Error(result.error || 'Unable to load appointments');
  }
  
  // Update timestamp for polling
  if (result.timestamp) {
    lastUpdateTimestamp = result.timestamp;
  }
  
  // Filter out completed and cancelled appointments from active view
  const activeAppointments = (result.appointments || []).filter(apt => 
    apt.status !== 'completed' && apt.status !== 'cancelled'
  );
  
  return activeAppointments;
}

async function fetchAvailability(category) {
  const { start, end } = getMonthRange(calendarReferenceDate);
  const response = await fetch(`${AVAILABILITY_API}?start_date=${start}&end_date=${end}&department=${category}`);
  const result = await response.json();
  if (!result.success) {
    throw new Error(result.error || 'Unable to load schedule availability');
  }
  return result.data?.calendar_dates || {};
}

function getMonthRange(referenceDate) {
  const start = new Date(referenceDate.getFullYear(), referenceDate.getMonth(), 1);
  const end = new Date(referenceDate.getFullYear(), referenceDate.getMonth() + 1, 0);
  return {
    start: formatDateInput(start),
    end: formatDateInput(end)
  };
}

function formatDateInput(dateObj) {
  return `${dateObj.getFullYear()}-${String(dateObj.getMonth() + 1).padStart(2, '0')}-${String(dateObj.getDate()).padStart(2, '0')}`;
}

function showAppointmentsLoading() {
  const list = document.getElementById("appointmentsList");
  if (list) {
    list.innerHTML = `
      <div class="text-center py-4">
        <div class="spinner-border text-danger" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
      </div>`;
  }
}

function showAppointmentsError(message) {
  const list = document.getElementById("appointmentsList");
  if (list) {
    list.innerHTML = `
      <div class="empty-state">
        <i class="bi bi-exclamation-triangle"></i>
        <p>${message}</p>
      </div>`;
  }
}

function updateStats(allAppointments) {
  // Count all appointments regardless of status for accurate stats
  const completed = allAppointments.filter(a => a.status === 'completed');
  const pending = allAppointments.filter(a => ['scheduled', 'confirmed'].includes(a.status));
  const upcoming = allAppointments.filter(a => {
    const appDate = buildAppointmentDate(a);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return appDate >= today && ['scheduled', 'confirmed'].includes(a.status);
  });
  
  const completedCountEl = document.getElementById("completedCount");
  const pendingCountEl = document.getElementById("pendingCount");
  const upcomingCountEl = document.getElementById("upcomingCount");
  
  if (completedCountEl) completedCountEl.textContent = completed.length;
  if (pendingCountEl) pendingCountEl.textContent = pending.length;
  if (upcomingCountEl) upcomingCountEl.textContent = upcoming.length;
}

function renderAppointmentsList(allAppointments) {
  const appointmentsListEl = document.getElementById("appointmentsList");
  if (!appointmentsListEl) return;
  
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  
  const upcoming = allAppointments
    .filter(app => {
      const appDate = buildAppointmentDate(app);
      return appDate >= today && (app.status === 'scheduled' || app.status === 'confirmed');
    })
    .sort((a, b) => buildAppointmentDate(a) - buildAppointmentDate(b));
  
  if (upcoming.length === 0) {
    appointmentsListEl.innerHTML = `
      <div class="empty-state">
        <i class="bi bi-calendar-x d-block"></i>
        <p>No upcoming ${capitalize(activeCategory)} appointments</p>
        <a href="appointment.php" class="btn btn-sm" style="background: #7e1414; color: white;">
          <i class="bi bi-plus-circle me-1"></i>Book Appointment
        </a>
      </div>`;
    return;
  }
  
  let listHTML = '';
  upcoming.forEach((app, index) => {
    const formattedDate = buildAppointmentDate(app).toLocaleDateString('en-US', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric'
    });
    const formattedTime = convertTo12Hour(app.appointment_time);
    const isNext = index === 0;
    const calendarIcon = app.calendar_event_id 
      ? '<i class="bi bi-calendar-check text-success ms-2" title="Synced to Google Calendar"></i>'
      : '<i class="bi bi-calendar-x text-muted ms-2" title="Not synced"></i>';
    
    listHTML += `
      <div class="appointment-card p-3 mb-3 rounded shadow-sm ${isNext ? 'border-2' : ''}">
        ${isNext ? '<div class="badge bg-success mb-2">Next Appointment</div>' : ''}
        <div class="appointment-type-badge ${app.appointment_type}">${app.appointment_type.toUpperCase()}</div>
        <div class="d-flex justify-content-between align-items-start">
          <div>
            <p class="fw-bold mb-1" style="color: #7e1414;">
              <i class="bi bi-calendar-event me-1"></i>${formattedDate}
              ${calendarIcon}
            </p>
            <p class="mb-2" style="color: #666;">
              <i class="bi bi-clock me-1"></i>${formattedTime}
            </p>
            <span class="badge bg-warning text-dark">${app.status.toUpperCase()}</span>
          </div>
          <div class="d-flex flex-column gap-2">
            <button class="btn btn-sm cancel-btn" data-id="${app.id}">
              <i class="bi bi-x-circle me-1"></i>Cancel
            </button>
          </div>
        </div>
      </div>`;
  });
  
  appointmentsListEl.innerHTML = listHTML;
  
  appointmentsListEl.querySelectorAll(".cancel-btn").forEach(btn => {
    btn.addEventListener("click", async (e) => {
      const id = e.currentTarget.dataset.id;
      if (!id) return;
      const result = await Swal.fire({
        title: 'Cancel Appointment?',
        text: "This will also remove it from your Google Calendar.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#7e1414',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, cancel it',
        cancelButtonText: 'No, keep it'
      });
      if (!result.isConfirmed) return;
      try {
        const formData = new FormData();
        formData.append('id', id);
        const response = await fetch(`${APPOINTMENTS_API}?action=cancel`, {
          method: 'POST',
          body: formData
        });
        const cancelResult = await response.json();
        if (!cancelResult.success) {
          throw new Error(cancelResult.error || 'Failed to cancel appointment');
        }
        await Swal.fire({
          icon: 'success',
          title: 'Cancelled!',
          text: 'Your appointment has been cancelled.',
          confirmButtonColor: '#7e1414',
          timer: 2000
        });
        renderDashboard(true);
      } catch (error) {
        console.error('Cancel error:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: error.message || 'Failed to cancel appointment',
          confirmButtonColor: '#7e1414'
        });
      }
    });
  });
  
  appointmentsListEl.querySelectorAll(".sync-btn").forEach(btn => {
    btn.addEventListener("click", async (e) => {
      const id = e.currentTarget.dataset.id;
      if (!id) return;
      const button = e.currentTarget;
      button.disabled = true;
      button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing...';
      try {
        const formData = new FormData();
        formData.append('id', id);
        const response = await fetch(`${APPOINTMENTS_API}?action=sync`, {
          method: 'POST',
          body: formData
        });
        const syncResult = await response.json();
        if (!syncResult.success) {
          throw new Error(syncResult.error || 'Failed to sync appointment');
        }
        await Swal.fire({
          icon: 'success',
          title: 'Synced!',
          text: 'Appointment synced to Google Calendar',
          confirmButtonColor: '#7e1414',
          timer: 2000,
          showConfirmButton: false
        });
        renderDashboard(true);
      } catch (error) {
        console.error('Sync error:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: error.message || 'Failed to sync appointment',
          confirmButtonColor: '#7e1414'
        });
        button.disabled = false;
        button.innerHTML = '<i class="bi bi-calendar-plus me-1"></i>Sync';
      }
    });
  });
}

function buildAppointmentDate(app) {
  const [year, month, day] = app.appointment_date.split('-').map(Number);
  const [hours, minutes] = (app.appointment_time || '00:00').split(':').map(Number);
  return new Date(year, month - 1, day, hours, minutes);
}

function renderCalendar(calendarEl, appointments, availability) {
  if (!calendarEl) return;
  const displayDate = new Date(calendarReferenceDate);
  const monthNames = ["January","February","March","April","May","June","July","August","September","October","November","December"];
  const daysInMonth = new Date(displayDate.getFullYear(), displayDate.getMonth() + 1, 0).getDate();
  const firstDayOfMonth = new Date(displayDate.getFullYear(), displayDate.getMonth(), 1).getDay();
  
  const appointmentMap = appointments.reduce((acc, app) => {
    const key = app.appointment_date;
    if (!acc[key]) acc[key] = [];
    acc[key].push(app);
    return acc;
  }, {});
  
  let html = `<div class="month-title">${monthNames[displayDate.getMonth()]} ${displayDate.getFullYear()}</div>`;
  html += `<div class="calendar-grid">`;
  
  const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  weekdays.forEach(day => {
    html += `<div class="calendar-day-header">${day}</div>`;
  });
  
  for (let i = 0; i < firstDayOfMonth; i++) {
    html += `<div class="calendar-day empty"></div>`;
  }
  
  for (let d = 1; d <= daysInMonth; d++) {
    const dateISO = `${displayDate.getFullYear()}-${String(displayDate.getMonth() + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    const info = availability[dateISO];
    const dayAppointments = appointmentMap[dateISO] || [];
    const hasAppointments = dayAppointments.length > 0;
    const classes = ['calendar-day'];
    let tooltip = '';
    
    // Check if date has scheduled appointments (both medical and dental)
    if (hasAppointments) {
      classes.push('has-appointment');
      // Sort appointments by time
      const sortedApps = dayAppointments.sort((a, b) => {
        const timeA = a.appointment_time || '00:00:00';
        const timeB = b.appointment_time || '00:00:00';
        return timeA.localeCompare(timeB);
      });
      
      // Build tooltip with all appointments for this day
      const entries = sortedApps
        .map(app => `${convertTo12Hour(app.appointment_time)} • ${capitalize(app.appointment_type)} (${app.status})`)
        .join('<br>');
      tooltip = entries;
    } else {
      // Only show availability info if no appointments
      if (!info) {
        classes.push('unavailable');
      } else {
        const status = info.status || 'available';
        classes.push(status);
        
        // Add department-specific class for available dates
        if (status === 'available' || status === 'partially-available') {
          if (info.department === 'medical') {
            classes.push('medical');
          } else if (info.department === 'dental') {
            classes.push('dental');
          } else if (info.department === 'both') {
            classes.push('both');
          }
        }
        
        if (info.status === 'unavailable' && info.reason) {
          tooltip = info.reason;
        } else if (status === 'available' || status === 'partially-available') {
          // Add availability info to tooltip
          const deptInfo = [];
          if (info.department === 'medical' || info.department === 'both') {
            deptInfo.push('Medical available');
          }
          if (info.department === 'dental' || info.department === 'both') {
            deptInfo.push('Dental available');
          }
          if (deptInfo.length > 0) {
            tooltip = deptInfo.join(' • ');
          }
        }
      }
    }
    
    html += `<div class="${classes.join(' ')}" ${tooltip && hasAppointments ? `title="${tooltip.replace(/<br>/g, ', ')}"` : ''}>${d}${tooltip && hasAppointments ? `<div class="appointment-tooltip">${tooltip}</div>` : ''}</div>`;
  }
  
    html += "</div>";
    
    // Add legend
    html += `
      <div class="calendar-legend">
        <div class="legend-item">
          <div class="legend-color appointment"></div>
          <span>Scheduled Appointment</span>
        </div>
        <div class="legend-item">
          <div class="legend-color medical"></div>
          <span>Medical Available</span>
        </div>
        <div class="legend-item">
          <div class="legend-color dental"></div>
          <span>Dental Available</span>
        </div>
        <div class="legend-item">
          <div class="legend-color both"></div>
          <span>Both Available</span>
        </div>
        <div class="legend-item">
          <div class="legend-color unavailable"></div>
          <span>Unavailable</span>
        </div>
      </div>
    `;
    
    calendarEl.innerHTML = html;
}

function capitalize(str = '') {
  return str.charAt(0).toUpperCase() + str.slice(1);
}

function convertTo12Hour(time24) {
  if (!time24) return '';
  const [hourStr, minuteStr] = time24.split(':');
  let hour = parseInt(hourStr, 10);
  const minutes = minuteStr ?? '00';
  const ampm = hour >= 12 ? 'PM' : 'AM';
  hour = hour % 12 || 12;
  return `${hour}:${minutes.padStart(2, '0')} ${ampm}`;
}

// ==========================================
// REAL-TIME POLLING FUNCTIONS
// ==========================================

function startRealTimePolling() {
  if (pollingInterval) {
    clearInterval(pollingInterval);
  }
  
  // Poll every 5 seconds for real-time updates
  pollingInterval = setInterval(async () => {
    try {
      const response = await fetch(`${APPOINTMENTS_API}?action=poll&last_update=${lastUpdateTimestamp}`);
      const result = await response.json();
      
      if (result.success && result.has_updates) {
        console.log('Real-time update detected, refreshing dashboard...');
        // Update timestamp
        lastUpdateTimestamp = result.timestamp || Date.now();
        // Silent refresh - don't show loading spinner
        await renderDashboard(false);
      } else if (result.success && result.timestamp) {
        // Update timestamp even if no updates
        lastUpdateTimestamp = result.timestamp;
      }
    } catch (error) {
      console.error('Polling error:', error);
      // Don't show error to user, just log it
    }
  }, 5000); // Poll every 5 seconds
}

function stopRealTimePolling() {
  if (pollingInterval) {
    clearInterval(pollingInterval);
    pollingInterval = null;
  }
}

// Initialize notification system
if (window.StudentNotificationSystem) {
  StudentNotificationSystem.init();
}
// Add to your existing JavaScript or create mobile-menu.js
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

=======
  <title>Appointment Reservation</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- External CSS -->
  <link href="../student/css/nav.css" rel="stylesheet" />
  <link rel="stylesheet" href="css/appointment.css" />
</head>
<body>
  <div class="layout">
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
        <div class="logout-icon" onclick="window.location.href='../logout.php'">
          <i class="bi bi-box-arrow-right"></i>
        </div>
      </div>
    </div>

    <!-- Sidebar + Content -->
    <div class="main-container d-flex">
      <!-- Sidebar -->
      <div class="sidebar">
        <a href="../student/student_dashboard.php" class="menu-item">Dashboard</a>
        <a href="../student/profile.php" class="menu-item">Profile</a>
        <a href="../student/appointment.php" class="menu-item active">Appointment</a>
        <a href="../student/records.php" class="menu-item">Health Records</a>
        <a href="../student/settings.php" class="menu-item">Settings</a>
      </div>

      <!-- Main Content -->
      <div class="content p-4 flex-grow-1">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <h2 class="fw-bold">Make a Reservation</h2>
        </div>

        <!-- Calendar -->
        <div class="calendar-container">
          <div class="calendar-header d-flex justify-content-between align-items-center mb-3">
            <button id="prevMonth" class="btn btn-sm btn-outline-danger"><i class="bi bi-chevron-left"></i></button>
            <h4 id="monthYear" class="fw-bold text-center mb-0"></h4>
            <button id="nextMonth" class="btn btn-sm btn-outline-danger"><i class="bi bi-chevron-right"></i></button>
          </div>
          <div class="weekdays fw-semibold text-center mb-2">
            <span>Sun</span><span>Mon</span><span>Tue</span><span>Wed</span>
            <span>Thu</span><span>Fri</span><span>Sat</span>
          </div>
          <div class="days" id="calendarDays"></div>
        </div>

        <!-- My Appointments -->
        <div class="mt-4">
          <h4 class="fw-bold mb-3">My Appointments</h4>
          <div id="appointmentsList" class="appointments-list"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal -->
  <div class="modal fade" id="bookingModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content border-0 shadow-lg">
        <div class="modal-header bg-danger text-white">
          <h5 class="modal-title fw-semibold">
            <i class="bi bi-calendar-check me-2"></i>Reserve Appointment
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <div class="modal-body p-4">
          <h6 class="text-center text-maroon fw-bold mb-3">Select Appointment Details</h6>

          <div class="mb-3">
            <label class="form-label fw-semibold">Appointment Type</label>
            <select id="appointmentType" class="form-select">
              <option value="">-- Select Type --</option>
              <option value="medical">Medical</option>
              <option value="dental">Dental</option>
            </select>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Available Time</label>
            <div id="timeButtons" class="time-buttons row g-2"></div>
          </div>

          <div class="mb-3">
            <label class="form-label fw-semibold">Notes (Optional)</label>
            <textarea id="appointmentNotes" class="form-control" rows="2" placeholder="Any special requests or concerns..."></textarea>
          </div>

          <div class="alert alert-warning small mb-0 text-center">
            Note: Appointments are only available Monday–Wednesday, 8:00 AM – 5:00 PM (excluding 12 PM lunch break).
          </div>
        </div>

        <div class="modal-footer border-0 p-3">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button class="btn btn-danger" id="confirmBooking">Confirm Booking</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Calendar & Booking Logic -->
  <script>
    const userId = <?php echo $userId; ?>;
    const daysContainer = document.getElementById("calendarDays");
    const monthYear = document.getElementById("monthYear");
    const prevMonthBtn = document.getElementById("prevMonth");
    const nextMonthBtn = document.getElementById("nextMonth");

    let currentDate = new Date();
    let appointments = [];
    let selectedDate = null;

    // Available days (1=Monday, 2=Tuesday, 3=Wednesday)
    const availableDays = [1, 2, 3];

    // Load appointments from database
    async function loadAppointments() {
      try {
        const response = await fetch('appointment_handler.php?action=list');
        const result = await response.json();
        
        if (result.success) {
          appointments = result.data;
          renderCalendar();
          renderMyAppointments();
        }
      } catch (error) {
        console.error('Error loading appointments:', error);
      }
    }

    function renderCalendar() {
      daysContainer.innerHTML = "";
      const year = currentDate.getFullYear();
      const month = currentDate.getMonth();
      const monthStart = new Date(year, month, 1);
      const monthEnd = new Date(year, month + 1, 0);
      const daysInMonth = monthEnd.getDate();
      const startDay = monthStart.getDay();
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      monthYear.textContent = `${currentDate.toLocaleString("default", { month: "long" })} ${year}`;

      // Empty cells for days before month starts
      for (let i = 0; i < startDay; i++) {
        const emptyCell = document.createElement("button");
        emptyCell.classList.add("invisible");
        daysContainer.appendChild(emptyCell);
      }

      // Render days
      for (let i = 1; i <= daysInMonth; i++) {
        const dateObj = new Date(year, month, i);
        const dateISO = dateObj.toISOString().split("T")[0];
        const btn = document.createElement("button");
        btn.textContent = i;

        const isPast = dateObj < today;
        const isWeekend = dateObj.getDay() === 0 || dateObj.getDay() === 6;
        const isAvailableDay = availableDays.includes(dateObj.getDay());
        const dayAppointments = appointments.filter(a => a.date === dateISO && a.status !== 'cancelled');

        if (isPast || isWeekend || !isAvailableDay) {
          btn.classList.add("booked");
          btn.disabled = true;
        } else {
          btn.classList.add("available");
          if (dayAppointments.length > 0) {
            btn.classList.add("has-bookings");
          }
          btn.addEventListener("click", () => openBookingModal(dateISO));
        }

        daysContainer.appendChild(btn);
      }
    }

    async function openBookingModal(dateISO) {
      selectedDate = dateISO;
      const modal = new bootstrap.Modal(document.getElementById("bookingModal"));
      document.getElementById("appointmentType").value = "";
      document.getElementById("appointmentNotes").value = "";

      // Load booked times for this date
      const bookedTimes = appointments
        .filter(a => a.date === dateISO && a.status !== 'cancelled')
        .map(a => a.time);

      // Generate time buttons
      const timeContainer = document.getElementById("timeButtons");
      timeContainer.innerHTML = "";
      
      for (let hour = 8; hour <= 17; hour++) {
        if (hour === 12) continue; // Skip lunch break

        const time = `${hour.toString().padStart(2, "0")}:00:00`;
        const timeDisplay = `${hour > 12 ? hour - 12 : hour}:00 ${hour >= 12 ? 'PM' : 'AM'}`;
        const isBooked = bookedTimes.includes(time);

        const btn = document.createElement("button");
        btn.className = "btn btn-outline-danger rounded-pill px-3 py-1 col-3";
        btn.textContent = timeDisplay;
        btn.disabled = isBooked;
        
        if (isBooked) {
          btn.classList.add("disabled");
          btn.title = "Time slot already booked";
        } else {
          btn.onclick = () => {
            document.querySelectorAll("#timeButtons button").forEach(b => {
              b.classList.remove("btn-danger");
              b.classList.add("btn-outline-danger");
            });
            btn.classList.remove("btn-outline-danger");
            btn.classList.add("btn-danger");
            btn.dataset.selected = true;
            document.getElementById("confirmBooking").dataset.time = time;
          };
        }
        
        timeContainer.appendChild(btn);
      }

      modal.show();
    }

    // Confirm booking
    document.getElementById("confirmBooking").onclick = async function() {
      const type = document.getElementById("appointmentType").value;
      const time = this.dataset.time;
      const notes = document.getElementById("appointmentNotes").value;

      if (!type) {
        Swal.fire({
          icon: "warning",
          title: "Select Type",
          text: "Please select an appointment type.",
          confirmButtonColor: "#d32f2f"
        });
        return;
      }

      if (!time) {
        Swal.fire({
          icon: "warning",
          title: "Select Time",
          text: "Please select a time slot.",
          confirmButtonColor: "#d32f2f"
        });
        return;
      }

      try {
        const response = await fetch('appointment_handler.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'create',
            date: selectedDate,
            time: time,
            type: type,
            notes: notes
          })
        });

        const result = await response.json();

        if (result.success) {
          bootstrap.Modal.getInstance(document.getElementById("bookingModal")).hide();
          await loadAppointments();
          
          Swal.fire({
            icon: "success",
            title: "Appointment Confirmed!",
            html: `Your ${type} appointment on<br><strong>${new Date(selectedDate).toLocaleDateString()}</strong> at <strong>${convertTo12Hour(time)}</strong><br>has been confirmed and synced to the clinic calendar.`,
            confirmButtonColor: "#d32f2f"
          });
        } else {
          throw new Error(result.error || 'Booking failed');
        }
      } catch (error) {
        Swal.fire({
          icon: "error",
          title: "Booking Failed",
          text: error.message,
          confirmButtonColor: "#d32f2f"
        });
      }
    };

    // Render my appointments
    function renderMyAppointments() {
      const container = document.getElementById("appointmentsList");
      const myAppointments = appointments.filter(a => a.status !== 'cancelled');

      if (myAppointments.length === 0) {
        container.innerHTML = '<p class="text-muted">No appointments scheduled.</p>';
        return;
      }

      container.innerHTML = myAppointments.map(apt => `
        <div class="card mb-2">
          <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h6 class="mb-1"><i class="bi bi-calendar-event"></i> ${new Date(apt.date).toLocaleDateString()}</h6>
                <p class="mb-0 text-muted small">
                  ${convertTo12Hour(apt.time)} - ${apt.type.toUpperCase()}
                  ${apt.calendar_event_id ? '<i class="bi bi-check-circle-fill text-success ms-2" title="Synced to calendar"></i>' : ''}
                </p>
              </div>
              <button class="btn btn-sm btn-outline-danger" onclick="cancelAppointment(${apt.id})">
                <i class="bi bi-x-circle"></i> Cancel
              </button>
            </div>
          </div>
        </div>
      `).join('');
    }

    // Cancel appointment
    async function cancelAppointment(appointmentId) {
      const result = await Swal.fire({
        title: 'Cancel Appointment?',
        text: "This action cannot be undone.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d32f2f',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, cancel it'
      });

      if (result.isConfirmed) {
        try {
          const response = await fetch(`appointment_handler.php?action=cancel&id=${appointmentId}`, {
            method: 'POST'
          });
          const result = await response.json();

          if (result.success) {
            await loadAppointments();
            Swal.fire('Cancelled!', 'Your appointment has been cancelled.', 'success');
          }
        } catch (error) {
          Swal.fire('Error', 'Failed to cancel appointment', 'error');
        }
      }
    }

    function convertTo12Hour(time24) {
      const [hours, minutes] = time24.split(':');
      const hour = parseInt(hours);
      const ampm = hour >= 12 ? 'PM' : 'AM';
      const hour12 = hour % 12 || 12;
      return `${hour12}:${minutes} ${ampm}`;
    }

    prevMonthBtn.addEventListener("click", () => {
      currentDate.setMonth(currentDate.getMonth() - 1);
      renderCalendar();
    });

    nextMonthBtn.addEventListener("click", () => {
      currentDate.setMonth(currentDate.getMonth() + 1);
      renderCalendar();
    });

    // Initial load
    loadAppointments();
  </script>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
</body>
</html>
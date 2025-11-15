<?php
session_start();
require_once('../config/database.php');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
$userId = $_SESSION['user_id'];
$userName = $_SESSION['fname'] ?? 'Student';
$userEmail = $_SESSION['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Dashboard - BSU Clinic System</title>

  <link href="css/nav.css" rel="stylesheet" />
  <link href="css/dashboard.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" rel="stylesheet">
  
  <style>
    .calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 5px;
    }
    
    .calendar-grid .day {
      padding: 10px;
      text-align: center;
      border-radius: 5px;
      background: white;
      font-size: 0.9rem;
      border: 1px solid #e0e0e0;
      transition: all 0.2s;
    }
    
    .calendar-grid .day.marked {
      background: #7e1414;
      color: white;
      font-weight: bold;
      position: relative;
    }
    
    .calendar-grid .day.marked::after {
      content: '●';
      position: absolute;
      bottom: 2px;
      left: 50%;
      transform: translateX(-50%);
      font-size: 0.5rem;
    }
    
    .calendar-grid .weekday {
      font-weight: 600;
      color: #666;
      padding: 5px;
      font-size: 0.85rem;
    }
    
    .month-title {
      font-size: 1.1rem;
      font-weight: 600;
      margin-bottom: 15px;
      text-align: center;
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

  <div class="main-container d-flex">

    <div class="sidebar">
      <a href="student_dashboard.php" class="menu-item active">Dashboard</a>
      <a href="profile.php" class="menu-item">Profile</a>
      <a href="appointment.php" class="menu-item">Appointment</a>
      <a href="records.php" class="menu-item">Health Records</a>
      <a href="settings.php" class="menu-item">Settings</a>
    </div>

    <main class="content-demo flex-grow-1 p-4">
      <h2 class="fw-bold mb-4">Hello, <?= htmlspecialchars($userName) ?>!</h2>

      <div class="appointments-layout d-flex gap-4">

        <div class="appointments-left flex-grow-1">
          <div class="stats-section mb-4 d-flex gap-3">
            <div class="stat-card p-3 text-center flex-fill rounded shadow-sm">
              <h4 id="completedCount">0</h4>
              <p>Completed</p>
            </div>
            <div class="stat-card p-3 text-center flex-fill rounded shadow-sm">
              <h4 id="pendingCount">0</h4>
              <p>Pending</p>
            </div>
            <div class="stat-card p-3 text-center flex-fill rounded shadow-sm">
              <h4 id="upcomingCount">0</h4>
              <p>Upcoming</p>
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

        <div class="appointments-right">
          <div class="calendar-container p-3 bg-light rounded shadow-sm">
            <h5>
              <i class="bi bi-calendar3 me-2" style="color: #7e1414;"></i>
              Calendar
            </h5>
            <div id="dashboardCalendar" class="mt-3"></div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script>
  <script>
const userId = <?= $userId ?>;

async function loadAppointments() {
  try {
    const response = await fetch('appointment_handler.php?action=list');
    const result = await response.json();
    
    console.log('API Response:', result);
    
    if (result.success) {
      return result.data || [];
    }
    return [];
  } catch (error) {
    console.error('Error loading appointments:', error);
    return [];
  }
}

async function renderDashboard() {
  const calendarEl = document.getElementById("dashboardCalendar");
  const completedCountEl = document.getElementById("completedCount");
  const pendingCountEl = document.getElementById("pendingCount");
  const upcomingCountEl = document.getElementById("upcomingCount");
  const appointmentsListEl = document.getElementById("appointmentsList");

  let appointments = await loadAppointments();
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  console.log('All appointments:', appointments);

  const activeAppointments = appointments.filter(a => 
    a.status !== 'cancelled' && a.status !== 'deleted'
  );

  const completed = activeAppointments.filter(a => {
    const [year, month, day] = a.date.split('-').map(Number);
    const [hours, minutes] = a.time.split(':').map(Number);
    const appDate = new Date(year, month - 1, day, hours, minutes);
    return appDate < today && a.status === 'completed';
  });

  const upcoming = activeAppointments.filter(a => {
    const [year, month, day] = a.date.split('-').map(Number);
    const [hours, minutes] = a.time.split(':').map(Number);
    const appDate = new Date(year, month - 1, day, hours, minutes);
    return appDate >= today && (a.status === 'scheduled' || a.status === 'confirmed');
  }).sort((a, b) => {
    const [yearA, monthA, dayA] = a.date.split('-').map(Number);
    const [hoursA, minutesA] = a.time.split(':').map(Number);
    const dateA = new Date(yearA, monthA - 1, dayA, hoursA, minutesA);
    
    const [yearB, monthB, dayB] = b.date.split('-').map(Number);
    const [hoursB, minutesB] = b.time.split(':').map(Number);
    const dateB = new Date(yearB, monthB - 1, dayB, hoursB, minutesB);
    
    return dateA - dateB;
  });

  completedCountEl.textContent = completed.length;
  pendingCountEl.textContent = upcoming.length;
  upcomingCountEl.textContent = upcoming.length;

  if (upcoming.length === 0) {
    appointmentsListEl.innerHTML = `
      <div class="empty-state">
        <i class="bi bi-calendar-x d-block"></i>
        <p>No upcoming appointments</p>
        <a href="appointment.php" class="btn btn-sm" style="background: #7e1414; color: white;">
          <i class="bi bi-plus-circle me-1"></i>Book Appointment
        </a>
      </div>`;
  } else {
    let listHTML = '';
    
    upcoming.forEach((app, index) => {
      const [year, month, day] = app.date.split('-').map(Number);
      const [hours, minutes] = app.time.split(':').map(Number);
      const appDate = new Date(year, month - 1, day, hours, minutes);
      
      const formattedDate = appDate.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric'
      });
      const formattedTime = convertTo12Hour(app.time);
      const isNext = index === 0;
      
      // Calendar sync indicator
      const calendarIcon = app.calendar_event_id 
        ? '<i class="bi bi-calendar-check text-success ms-2" title="Synced to Google Calendar"></i>' 
        : '<i class="bi bi-calendar-x text-muted ms-2" title="Not synced"></i>';

      listHTML += `
        <div class="appointment-card p-3 mb-3 rounded shadow-sm ${isNext ? 'border-2' : ''}">
          ${isNext ? '<div class="badge bg-success mb-2">Next Appointment</div>' : ''}
          <div class="appointment-type-badge ${app.type}">${app.type.toUpperCase()}</div>
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
              ${!app.calendar_event_id ? `
                <button class="btn btn-sm sync-btn" data-id="${app.id}" title="Sync to Google Calendar">
                  <i class="bi bi-calendar-plus me-1"></i>Sync
                </button>
              ` : ''}
              <button class="btn btn-sm cancel-btn" data-id="${app.id}">
                <i class="bi bi-x-circle me-1"></i>Cancel
              </button>
            </div>
          </div>
        </div>`;
    });

    appointmentsListEl.innerHTML = listHTML;

    // Handle Cancel Button
    document.querySelectorAll(".cancel-btn").forEach(btn => {
      btn.addEventListener("click", async (e) => {
        const appointmentId = e.target.closest('.cancel-btn').dataset.id;

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

        if (result.isConfirmed) {
          try {
            const response = await fetch(`appointment_handler.php?action=cancel&id=${appointmentId}`, {
              method: 'POST'
            });
            const cancelResult = await response.json();

            if (cancelResult.success) {
              await Swal.fire({
                icon: 'success',
                title: 'Cancelled!',
                text: 'Your appointment has been cancelled.',
                confirmButtonColor: '#7e1414',
                timer: 2000
              });
              renderDashboard();
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Error',
                text: cancelResult.error || 'Failed to cancel appointment',
                confirmButtonColor: '#7e1414'
              });
            }
          } catch (error) {
            console.error('Cancel error:', error);
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'Failed to cancel appointment',
              confirmButtonColor: '#7e1414'
            });
          }
        }
      });
    });

    // Handle Sync Button
    document.querySelectorAll(".sync-btn").forEach(btn => {
      btn.addEventListener("click", async (e) => {
        const appointmentId = e.target.closest('.sync-btn').dataset.id;
        const button = e.target.closest('.sync-btn');
        
        button.disabled = true;
        button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Syncing...';

        try {
          const response = await fetch(`appointment_handler.php?action=sync&id=${appointmentId}`, {
            method: 'POST'
          });
          const syncResult = await response.json();

          if (syncResult.success) {
            await Swal.fire({
              icon: 'success',
              title: 'Synced!',
              text: 'Appointment synced to Google Calendar',
              confirmButtonColor: '#7e1414',
              timer: 2000,
              showConfirmButton: false
            });
            renderDashboard();
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Sync Failed',
              text: syncResult.error || 'Failed to sync to calendar',
              confirmButtonColor: '#7e1414'
            });
            button.disabled = false;
            button.innerHTML = '<i class="bi bi-calendar-plus me-1"></i>Sync';
          }
        } catch (error) {
          console.error('Sync error:', error);
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to sync appointment',
            confirmButtonColor: '#7e1414'
          });
          button.disabled = false;
          button.innerHTML = '<i class="bi bi-calendar-plus me-1"></i>Sync';
        }
      });
    });
  }

  // Render Calendar with marked dates
  renderCalendar(calendarEl, upcoming);
}

function renderCalendar(calendarEl, appointments) {
  const date = new Date();
  const monthNames = ["January","February","March","April","May","June",
                      "July","August","September","October","November","December"];
  const daysInMonth = new Date(date.getFullYear(), date.getMonth()+1, 0).getDate();
  const firstDayOfMonth = new Date(date.getFullYear(), date.getMonth(), 1).getDay();

  let html = `<div class="month-title">${monthNames[date.getMonth()]} ${date.getFullYear()}</div>`;
  html += `<div class="calendar-grid">`;

  const weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
  weekdays.forEach(day => {
    html += `<div class="weekday">${day}</div>`;
  });

  // Get marked days from upcoming appointments in current month
  const markedDays = appointments
    .filter(a => {
      const [year, month, day] = a.date.split('-').map(Number);
      const appDate = new Date(year, month - 1, day);
      return appDate.getMonth() === date.getMonth() && 
             appDate.getFullYear() === date.getFullYear();
    })
    .map(a => {
      const [year, month, day] = a.date.split('-').map(Number);
      return day;
    });

  console.log('Marked days for calendar:', markedDays);

  for (let i = 0; i < firstDayOfMonth; i++) {
    html += `<div class="day"></div>`;
  }

  for (let d = 1; d <= daysInMonth; d++) {
    const isMarked = markedDays.includes(d);
    html += `<div class="day ${isMarked ? 'marked' : ''}">${d}</div>`;
  }

  html += "</div>";
  calendarEl.innerHTML = html;
}

function convertTo12Hour(time24) {
  const [hours, minutes] = time24.split(':');
  const hour = parseInt(hours);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  const hour12 = hour % 12 || 12;
  return `${hour12}:${minutes} ${ampm}`;
}

// Initial load
renderDashboard();

// Auto-refresh every 30 seconds
setInterval(renderDashboard, 30000);
  </script>

</body>
</html>
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Admin Appointment Management - BSU Clinic</title>

  <link href="../employee/appointments.css" rel="stylesheet" />
  <link href="/finalproject/css/nav.css" rel="stylesheet" />

  <!-- Bootstrap + Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
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
      <div class="notification-icon"><i class="bi bi-bell-fill"></i></div>
      <div class="logout-icon" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></div>
    </div>
  </div>

  <div class="main-container">
    <div class="sidebar">
      <div>
        <a href="../admin/adminDashboard.php" class="menu-item">Dashboard</a>
        <a href="../admin/patients.php" class="menu-item">Patient</a>
        <a href="../admin/userManagement.php" class="menu-item">User Management</a>
        <a href="../admin/inventory.php" class="menu-item">Inventory</a>
        <a href="../admin/appointmentManagement.php" class="menu-item active">Appointments</a>
        <a href="../admin/reports.php" class="menu-item">Reports & Analytics</a>
      </div>
      <div class="user-profile">
        <div class="avatar"></div>
        <span><?php echo htmlspecialchars($userName); ?></span>
      </div>
    </div>

     <div class="main-content">
      <div class="top-bar">
        <h2>Patient Appointments</h2>
        <div class="search-bar">
          <i class="bi bi-search"></i>
          <input type="text" placeholder="Search student..." id="searchInput">
        </div>
      </div>

      <div class="content-area">
        <!-- Calendar Section -->
        <div class="calendar-section">
          <div class="calendar-container">
            <div class="calendar-header">
              <button class="nav-btn" id="prevMonth">
                <i class="bi bi-chevron-left"></i>
              </button>
              <h3 id="monthYear">November 2025</h3>
              <button class="nav-btn" id="nextMonth">
                <i class="bi bi-chevron-right"></i>
              </button>
            </div>

            <div class="legend">
              <div class="legend-item">
                <div class="legend-box" style="background: #fff8e6; border-color: #ffa726;"></div>
                <span>Has Appointments</span>
              </div>
              <div class="legend-item">
                <div class="legend-box" style="background: #6b0d00; border-color: #6b0d00;"></div>
                <span>Selected Date</span>
              </div>
            </div>

            <div class="calendar" id="calendar"></div>
          </div>
        </div>

        <!-- Appointments Panel -->
        <div class="appointments-panel">
          <div class="panel-header">
            <h3>
              <i class="bi bi-calendar-check"></i>
              Appointments
            </h3>
            <div class="selected-date-display" id="selectedDateDisplay">
              Select a date to view appointments
            </div>
          </div>

          <div class="appointments-list" id="appointmentsList">
            <div class="empty-state">
              <i class="bi bi-calendar-week"></i>
              <p>Select a date to view student appointments</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script>
  <script>
    const API_BASE = '../crud/get_appointments.php';
    let currentDate = new Date();
    let selectedDate = null;
    let appointmentsCache = {};
    let lastUpdate = 0;
    
    document.addEventListener('DOMContentLoaded', () => {
      console.log('Initializing employee calendar...');
      initializeCalendar();
      initializeEventListeners();
      startPolling();
    });
    
    function initializeEventListeners() {
      document.getElementById('prevMonth')?.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        loadMonthAppointments();
      });
      
      document.getElementById('nextMonth')?.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        loadMonthAppointments();
      });
      
      document.getElementById('searchInput')?.addEventListener('input', (e) => {
        filterAppointments(e.target.value);
      });
    }
    
    async function initializeCalendar() {
      await loadMonthAppointments();
    }
    
    // Poll for updates every 5 seconds
    function startPolling() {
      setInterval(async () => {
        try {
          const response = await fetch(`${API_BASE}?action=poll&last_update=${lastUpdate}`);
          const result = await response.json();
          
          if (result.success && result.has_updates) {
            console.log('Updates detected, refreshing...');
            await loadMonthAppointments();
            if (selectedDate) {
              displayAppointments(selectedDate);
            }
          }
        } catch (error) {
          console.error('Polling error:', error);
        }
      }, 5000);
    }
    
    async function loadMonthAppointments() {
      try {
        const month = String(currentDate.getMonth() + 1).padStart(2, '0');
        const year = currentDate.getFullYear();
        
        console.log(`Loading appointments for ${month}/${year}...`);
        
        const response = await fetch(
          `${API_BASE}?action=get_calendar&month=${month}&year=${year}`
        );
        
        if (!response.ok) {
          const text = await response.text();
          console.error('Response not OK:', response.status, text);
          throw new Error(`Failed to load appointments: ${response.status}`);
        }
        
        const result = await response.json();
        console.log('API Response:', result);
        
        if (result.success) {
          lastUpdate = result.timestamp;
          
          // Cache appointments by date
          appointmentsCache = {};
          result.appointments.forEach(apt => {
            if (!appointmentsCache[apt.appointment_date]) {
              appointmentsCache[apt.appointment_date] = [];
            }
            appointmentsCache[apt.appointment_date].push(apt);
          });
          
          console.log('Appointments cached:', appointmentsCache);
          renderCalendar();
        } else {
          throw new Error(result.error || 'Unknown error');
        }
      } catch (error) {
        console.error('Error loading appointments:', error);
        Swal.fire('Error', 'Failed to load appointments: ' + error.message, 'error');
      }
    }
    
    function renderCalendar() {
      const calendar = document.getElementById('calendar');
      if (!calendar) return;
      
      const year = currentDate.getFullYear();
      const month = currentDate.getMonth();
      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const daysInMonth = lastDay.getDate();
      const startDay = firstDay.getDay();
      
      document.getElementById('monthYear').textContent = 
        firstDay.toLocaleString('default', { month: 'long', year: 'numeric' });
      
      calendar.innerHTML = '';
      
      // Weekday headers
      ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].forEach(day => {
        const el = document.createElement('div');
        el.className = 'weekday';
        el.textContent = day;
        calendar.appendChild(el);
      });
      
      // Previous month days
      const prevMonthLastDay = new Date(year, month, 0).getDate();
      for (let i = startDay - 1; i >= 0; i--) {
        const el = document.createElement('div');
        el.className = 'day-cell other-month';
        el.innerHTML = `<div class="day-number">${prevMonthLastDay - i}</div>`;
        calendar.appendChild(el);
      }
      
      // Current month days
      for (let day = 1; day <= daysInMonth; day++) {
        const date = new Date(year, month, day);
        const dateISO = formatDate(date);
        
        const el = document.createElement('div');
        el.className = 'day-cell';
        
        const appointments = appointmentsCache[dateISO] || [];
        
        if (appointments.length > 0) {
          el.classList.add('has-bookings');
        }
        
        if (selectedDate === dateISO) {
          el.classList.add('selected');
        }
        
        el.innerHTML = `
          <div class="day-number">${day}</div>
          ${appointments.length > 0 ? `<div class="day-badge badge-bookings">${appointments.length} apt${appointments.length > 1 ? 's' : ''}</div>` : ''}
        `;
        
        el.addEventListener('click', () => {
          selectedDate = dateISO;
          renderCalendar();
          displayAppointments(dateISO);
        });
        
        calendar.appendChild(el);
      }
      
      // Next month days
      const totalCells = calendar.children.length - 7;
      const remaining = (Math.ceil(totalCells / 7) * 7) - totalCells;
      for (let i = 1; i <= remaining; i++) {
        const el = document.createElement('div');
        el.className = 'day-cell other-month';
        el.innerHTML = `<div class="day-number">${i}</div>`;
        calendar.appendChild(el);
      }
    }
    
    function displayAppointments(dateISO) {
      const container = document.getElementById('appointmentsList');
      const dateDisplay = document.getElementById('selectedDateDisplay');
      
      if (!container) return;
      
      const appointments = appointmentsCache[dateISO] || [];
      
      // Update date display
      const date = new Date(dateISO + 'T00:00:00');
      dateDisplay.textContent = date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
      });
      
      if (appointments.length === 0) {
        container.innerHTML = `
          <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <p>No appointments scheduled for this date</p>
          </div>
        `;
        return;
      }
      
      container.innerHTML = '';
      
      // Sort by time
      appointments.sort((a, b) => a.appointment_time.localeCompare(b.appointment_time));
      
      appointments.forEach(apt => {
        const card = document.createElement('div');
        card.className = `appointment-card ${apt.appointment_type}`;
        
        card.innerHTML = `
          <div class="appointment-header">
            <div>
              <div class="student-name">
                <i class="bi bi-person-circle"></i>
                ${apt.fname || 'Unknown'} ${apt.lname || 'Student'}
              </div>
              <span class="appointment-type-badge type-${apt.appointment_type}">
                ${apt.appointment_type === 'dental' ? '🦷' : '🩺'} ${apt.appointment_type}
              </span>
            </div>
          </div>
          
          <div class="appointment-details">
            <div class="detail-item">
              <i class="bi bi-clock"></i>
              <span>${formatTime(apt.appointment_time)}</span>
            </div>
            <div class="detail-item">
              <i class="bi bi-calendar"></i>
              <span>${formatDate2(apt.appointment_date)}</span>
            </div>
            ${apt.notes ? `
            <div class="detail-item">
              <i class="bi bi-sticky"></i>
              <span>${apt.notes}</span>
            </div>
            ` : ''}
            ${apt.email ? `
            <div class="detail-item">
              <i class="bi bi-envelope"></i>
              <span>${apt.email}</span>
            </div>
            ` : ''}
          </div>
          
          <div>
            <span class="appointment-status status-${apt.status}">${apt.status}</span>
          </div>
          
          <div class="appointment-actions">
            ${apt.status === 'scheduled' || apt.status === 'confirmed' ? `
              <button class="btn-sm btn-confirm" onclick="updateStatus(${apt.id}, 'completed')">
                <i class="bi bi-check-circle-fill"></i> Complete
              </button>
            ` : ''}
            ${apt.status !== 'cancelled' && apt.status !== 'completed' ? `
              <button class="btn-sm btn-cancel" onclick="updateStatus(${apt.id}, 'cancelled')">
                <i class="bi bi-x-circle"></i> Cancel Appointment
              </button>
            ` : ''}
          </div>
        `;
        
        container.appendChild(card);
      });
    }
    
    function filterAppointments(searchTerm) {
      if (!selectedDate) return;
      
      const appointments = appointmentsCache[selectedDate] || [];
      const filtered = appointments.filter(apt => {
        const studentName = `${apt.fname || ''} ${apt.lname || ''}`.toLowerCase();
        const type = (apt.appointment_type || '').toLowerCase();
        const search = searchTerm.toLowerCase();
        
        return studentName.includes(search) || type.includes(search);
      });
      
      // Temporarily update cache for display
      const tempCache = {...appointmentsCache};
      tempCache[selectedDate] = filtered;
      
      const originalCache = appointmentsCache;
      appointmentsCache = tempCache;
      displayAppointments(selectedDate);
      appointmentsCache = originalCache;
    }
    
    async function updateStatus(id, status) {
      const statusText = status.charAt(0).toUpperCase() + status.slice(1);
      
      const result = await Swal.fire({
        title: `${statusText} Appointment?`,
        text: `This will mark the appointment as ${status}`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: status === 'cancelled' ? '#f44336' : '#4caf50',
        cancelButtonColor: '#999',
        confirmButtonText: `Yes, ${status}`
      });
      
      if (result.isConfirmed) {
        try {
          const response = await fetch(API_BASE, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=update_status&id=${id}&status=${status}`
          });
          
          const result = await response.json();
          
          if (result.success) {
            await Swal.fire('Success!', result.message, 'success');
            await loadMonthAppointments();
            if (selectedDate) displayAppointments(selectedDate);
          } else {
            throw new Error(result.error);
          }
        } catch (error) {
          Swal.fire('Error', error.message || 'Failed to update appointment', 'error');
        }
      }
    }
    
    function formatDate(date) {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    }
    
    function formatDate2(dateStr) {
      const date = new Date(dateStr + 'T00:00:00');
      return date.toLocaleDateString('en-US', { 
        month: 'short', 
        day: 'numeric', 
        year: 'numeric' 
      });
    }
    
    function formatTime(time24) {
      const [hours, minutes] = time24.split(':');
      const hour = parseInt(hours);
      const ampm = hour >= 12 ? 'PM' : 'AM';
      const hour12 = hour % 12 || 12;
      return `${hour12}:${minutes} ${ampm}`;
    }
  </script>
</body>
</html>
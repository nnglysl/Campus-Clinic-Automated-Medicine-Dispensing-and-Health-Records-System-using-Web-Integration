<?php
/**
 * Employee Appointment Calendar - View Student Reservations
 * File: employee/appointment.php
 */

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
$fullName = trim($first_name . ' ' . $last_name);

// Check if user is logged in and has a valid role
// Allow students to access this page
if (!isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Employee Appointment Management - BSU Clinic</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="../css/nav.css" rel="stylesheet" />
  <link href="../dental/css/dental_appointments.css" rel="stylesheet" />
  <link rel="stylesheet" href="../dental/css/responsive.css" />
  <link href="../admin/css/notifications.css" rel="stylesheet" />
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

  <div class="main-container d-flex">
    <div class="sidebar">
      <a href="../dental/dental_dashboard.php" class="menu-item ">Dashboard</a>
      <a href="../dental/dental_profile.php" class="menu-item">Profile</a>
      <a href="../dental/dental_patients.php" class="menu-item">Patients</a>
      <a href="../dental/dental_appointments.php" class="menu-item active">Appointments</a>
      <a href="../dental/dental_settings.php" class="menu-item">Settings</a>

      <div class="user-profile">
        <span><?php echo htmlspecialchars($fullName); ?></span>
      </div>
    </div>

    <div class="main-content">
      <div class="top-bar">
        <h2>Patient Appointments</h2>
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

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="js/notifications.js"></script>
  <script src="../js/logout.js"></script>
  <script>
    const API_BASE = '../crud/appointment_sync.php';
    const APPOINTMENT_TYPE = 'dental';
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
          `${API_BASE}?action=get_calendar&month=${month}&year=${year}&appointment_type=${APPOINTMENT_TYPE}`
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
            ${apt.status !== 'cancelled' && apt.status !== 'completed' ? `
              <button class="btn-sm btn-fill-form" data-patient-id="${apt.patient_id}" data-appointment-id="${apt.id}" data-fname="${(apt.fname || '').replace(/"/g, '&quot;')}" data-lname="${(apt.lname || '').replace(/"/g, '&quot;')}" data-date="${apt.appointment_date}" data-time="${apt.appointment_time}">
                <i class="bi bi-file-earmark-medical-fill"></i> Fill Form
              </button>
              <button class="btn-sm btn-cancel" onclick="event.stopPropagation(); updateStatus(${apt.id}, 'cancelled')">
                <i class="bi bi-x-circle"></i> Cancel
              </button>
            ` : ''}
            ${apt.status === 'completed' ? `
              <span class="text-success"><i class="bi bi-check-circle-fill"></i> Completed</span>
            ` : ''}
            ${apt.status === 'cancelled' ? `
              <span class="text-danger"><i class="bi bi-x-circle"></i> Cancelled</span>
            ` : ''}
          </div>
        `;
        
        // Add click event listener for Fill Form button
        if (apt.status !== 'cancelled' && apt.status !== 'completed') {
          const fillFormBtn = card.querySelector('.btn-fill-form');
          if (fillFormBtn) {
            fillFormBtn.addEventListener('click', function(e) {
              e.stopPropagation();
              const patientId = this.getAttribute('data-patient-id');
              const appointmentId = this.getAttribute('data-appointment-id');
              const fname = this.getAttribute('data-fname') || '';
              const lname = this.getAttribute('data-lname') || '';
              const date = this.getAttribute('data-date');
              const time = this.getAttribute('data-time');
              
              openDentalAppointmentForm(patientId, appointmentId, fname, lname, date, time);
            });
          }
        }
        
        container.appendChild(card);
      });
    }
    
    async function updateStatus(id, status) {
      const statusText = status.charAt(0).toUpperCase() + status.slice(1);
      
      // Custom messages based on status
      let confirmMessage = `This will mark the appointment as ${status}`;
      if (status === 'cancelled') {
        confirmMessage = 'This will cancel the appointment and free up the time slot for other students.';
      } else if (status === 'completed') {
        confirmMessage = 'This will mark the appointment as completed and automatically create a medical record.';
      }
      
      const result = await Swal.fire({
        title: `${statusText} Appointment?`,
        text: confirmMessage,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: status === 'cancelled' ? '#f44336' : '#4caf50',
        cancelButtonColor: '#999',
        confirmButtonText: `Yes, ${status}`,
        showLoaderOnConfirm: true,
        preConfirm: async () => {
          try {
            const response = await fetch(API_BASE, {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: `action=update_status&id=${id}&status=${status}`
            });
            
            const result = await response.json();
            
            if (!result.success) {
              throw new Error(result.error || 'Failed to update appointment');
            }
            
            return result;
          } catch (error) {
            Swal.showValidationMessage(error.message || 'Failed to update appointment');
            return false;
          }
        },
        allowOutsideClick: () => !Swal.isLoading()
      });
      
      if (result.isConfirmed && result.value) {
        await Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: result.value.message || `Appointment ${status} successfully`,
          timer: 2000,
          showConfirmButton: false
        });
        
        // Force immediate refresh to show real-time updates
        lastUpdate = 0; // Reset to force refresh
        await loadMonthAppointments();
        if (selectedDate) {
          displayAppointments(selectedDate);
        }
        
        // Trigger a custom event for other components to listen
        window.dispatchEvent(new CustomEvent('appointmentUpdated', {
          detail: { appointmentId: id, status: status }
        }));
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
    
    // Open dental appointment form
    async function openDentalAppointmentForm(patientUserId, appointmentId, patientFname, patientLname, appointmentDate, appointmentTime) {
      try {
        // Show modal first so elements exist in DOM
        const modalElement = document.getElementById('appointmentDentalFormModal');
        if (!modalElement) {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Form modal not found. Please refresh the page.'
          });
          return;
        }
        
        const modal = new bootstrap.Modal(modalElement);
        modal.show();
        
        // Wait for modal to be fully rendered
        await new Promise(resolve => {
          modalElement.addEventListener('shown.bs.modal', resolve, { once: true });
          // Fallback timeout
          setTimeout(resolve, 300);
        });
        
        // Initialize dental chart
        initializeDentalChartForModal();
        
        // Clear form fields first
        clearDentalAppointmentForm();
        
        // Fetch patient details
        const response = await fetch(`../crud/get_patient_by_user_id.php?user_id=${patientUserId}&fname=${encodeURIComponent(patientFname || '')}&lname=${encodeURIComponent(patientLname || '')}`);
        const patientData = await response.json();
        
        if (!patientData.success || !patientData.patient) {
          console.error('Failed to fetch patient data:', patientData);
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: patientData.message || 'Failed to load patient data. Please try again.'
          });
          modal.hide();
          return;
        }
        
        const patient = patientData.patient;
        
        if (!patient.id) {
          console.error('Patient ID is missing in patient data:', patient);
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Patient ID not found. Please check patient record.'
          });
          modal.hide();
          return;
        }
        
        // Now set all form values after modal is shown and form is cleared
        const appointmentFormId = document.getElementById('dentalAppointmentFormId');
        const patientIdEl = document.getElementById('dentalAppointmentFormPatientId');
        const fullNameEl = document.getElementById('dentalAppointmentFormFullName');
        const srCodeEl = document.getElementById('dentalAppointmentFormSRCode');
        const addressEl = document.getElementById('dentalAppointmentFormAddress');
        const programEl = document.getElementById('dentalAppointmentFormProgram');
        const dateInput = document.getElementById('dentalAppointmentFormDateInput');
        const timeInput = document.getElementById('dentalAppointmentFormTimeInput');
        
        // Set appointment ID
        if (appointmentFormId) {
          appointmentFormId.value = appointmentId;
        } else {
          console.error('Appointment form ID element not found!');
        }
        
        // Set patient ID (CRITICAL - must be set)
        if (!patientIdEl) {
          console.error('Patient ID element not found!');
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Form element not found. Please refresh the page.'
          });
          modal.hide();
          return;
        }
        
        patientIdEl.value = patient.id;
        console.log('Patient ID set to:', patient.id, 'Element value:', patientIdEl.value);
        
        // Set patient information
        if (fullNameEl) fullNameEl.value = patient.full_name || '';
        if (srCodeEl) srCodeEl.value = patient.sr_code || '';
        if (addressEl) addressEl.value = patient.address || '';
        if (programEl) programEl.value = patient.program || patient.position || '';
        
        // Set appointment date and time
        if (dateInput) dateInput.value = appointmentDate;
        if (timeInput) timeInput.value = appointmentTime;
        
        // Verify patient_id is set
        const verifyPatientId = document.getElementById('dentalAppointmentFormPatientId');
        if (!verifyPatientId || !verifyPatientId.value) {
          console.error('Patient ID verification failed after setting!');
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'Failed to set patient ID. Please try again.'
          });
          modal.hide();
        } else {
          console.log('Patient ID verified successfully:', verifyPatientId.value);
        }
      } catch (error) {
        console.error('Error opening dental appointment form:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Failed to open dental form: ' + error.message
        });
      }
    }
    
    // Initialize dental chart for modal
    function initializeDentalChartForModal() {
      const dentalOptions = [
        { value: '', text: '-' },
        { value: 'X', text: 'X' },
        { value: 'C', text: 'C' },
        { value: 'RF', text: 'RF' },
        { value: 'M', text: 'M' },
        { value: 'Im', text: 'Im' },
        { value: 'Un', text: 'Un' },
        { value: '√', text: '√' },
        { value: 'Cm', text: 'Cm' },
        { value: 'Sp', text: 'Sp' },
        { value: 'JC', text: 'JC' },
        { value: 'Am', text: 'Am' },
        { value: 'Comp', text: 'Comp' },
        { value: 'TF', text: 'TF' },
        { value: 'S', text: 'S' },
        { value: 'In', text: 'In' },
        { value: 'AB', text: 'AB' },
        { value: 'P', text: 'P' },
        { value: 'RPD', text: 'RPD' },
        { value: 'CD', text: 'CD' },
        { value: 'FB', text: 'FB' }
      ];
      
      function createTooth(number, showNumberTop = true) {
        const container = document.createElement('div');
        container.className = 'tooth-container';
        
        if (showNumberTop) {
          const numDiv = document.createElement('div');
          numDiv.className = 'tooth-number';
          numDiv.textContent = number;
          container.appendChild(numDiv);
        }
        
        const diagram = document.createElement('div');
        diagram.className = 'tooth-diagram';
        diagram.onclick = function() { toggleTooth(this); };
        container.appendChild(diagram);
        
        const select = document.createElement('select');
        select.className = 'tooth-select';
        select.name = `tooth_${number}_status`;
        select.id = `tooth_${number}_status`; // Add ID for easier access
        // Ensure the select is part of the form by setting the form attribute
        const form = document.getElementById('dentalAppointmentForm');
        if (form) {
          select.setAttribute('form', form.id);
        }
        
        // Add change handler to sync visual marking with select value
        select.addEventListener('change', function() {
          if (this.value && this.value !== '') {
            diagram.classList.add('marked');
          } else {
            diagram.classList.remove('marked');
          }
        });
        
        dentalOptions.forEach(option => {
          const opt = document.createElement('option');
          opt.value = option.value;
          opt.textContent = option.text;
          select.appendChild(opt);
        });
        
        container.appendChild(select);
        
        if (!showNumberTop) {
          const numDiv = document.createElement('div');
          numDiv.className = 'tooth-number';
          numDiv.textContent = number;
          container.appendChild(numDiv);
        }
        
        return container;
      }
      
      function toggleTooth(element) {
        const container = element.closest('.tooth-container');
        if (container) {
          const select = container.querySelector('select');
          if (select) {
            // If marking the tooth, set a default status if none is selected
            if (!element.classList.contains('marked') && !select.value) {
              element.classList.add('marked');
              select.value = 'X'; // Default to 'X' for extraction when marked
            } else if (element.classList.contains('marked')) {
              // If already marked, toggle it off and clear the select
              element.classList.remove('marked');
              select.value = '';
            } else {
              // If not marked and has value, just toggle the visual
              element.classList.toggle('marked');
            }
          } else {
            // Fallback if select not found
            element.classList.toggle('marked');
          }
        } else {
          // Fallback if container not found
          element.classList.toggle('marked');
        }
      }
      
      // Initialize teeth charts
      const upperTemp = document.getElementById('modalUpperTempTeeth');
      if (upperTemp) {
        upperTemp.innerHTML = '';
        [55, 54, 53, 52, 51, 61, 62, 63, 64, 65].forEach(num => {
          upperTemp.appendChild(createTooth(num, true));
        });
      }
      
      const upperPerm = document.getElementById('modalUpperPermTeeth');
      if (upperPerm) {
        upperPerm.innerHTML = '';
        [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28].forEach(num => {
          upperPerm.appendChild(createTooth(num, true));
        });
      }
      
      const lowerPerm = document.getElementById('modalLowerPermTeeth');
      if (lowerPerm) {
        lowerPerm.innerHTML = '';
        [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38].forEach(num => {
          lowerPerm.appendChild(createTooth(num, false));
        });
      }
      
      const lowerTemp = document.getElementById('modalLowerTempTeeth');
      if (lowerTemp) {
        lowerTemp.innerHTML = '';
        [85, 84, 83, 82, 81, 71, 72, 73, 74, 75].forEach(num => {
          lowerTemp.appendChild(createTooth(num, false));
        });
      }
    }
    
    // Clear dental appointment form
    function clearDentalAppointmentForm() {
      const form = document.getElementById('dentalAppointmentForm');
      if (!form) return;
      
      // Clear all inputs except readonly fields and hidden fields (preserve patient_id, appointment_id, physician_id, physician_name)
      const inputs = form.querySelectorAll('input:not([readonly]):not([type="hidden"]), select, textarea');
      inputs.forEach(input => {
        if (input.type === 'checkbox') {
          input.checked = false;
        } else {
          input.value = '';
        }
      });
      
      // Clear marked teeth
      document.querySelectorAll('#appointmentDentalFormModal .tooth-diagram.marked').forEach(tooth => {
        tooth.classList.remove('marked');
      });
    }
    
    // Submit dental appointment form
    let isSubmitting = false; // Prevent duplicate submissions
    
    async function submitDentalAppointmentForm(event) {
      event.preventDefault();
      event.stopPropagation(); // Prevent event bubbling
      
      // Prevent duplicate submissions
      if (isSubmitting) {
        console.warn('Form submission already in progress, ignoring duplicate submit');
        return false;
      }
      
      const form = event.target;
      
      // Check patient_id BEFORE creating FormData
      const patientIdEl = document.getElementById('dentalAppointmentFormPatientId');
      const patientId = patientIdEl?.value;
      
      if (!patientId) {
        console.error('Patient ID is missing!', {
          patientIdEl: patientIdEl,
          patientIdValue: patientIdEl?.value,
          form: form
        });
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Patient ID is missing. Please try opening the form again.'
        });
        return;
      }
      
      const formData = new FormData(form);
      const appointmentId = formData.get('appointment_id');
      
      // Verify patient_id is in FormData
      const patientIdFromForm = formData.get('patient_id');
      if (!patientIdFromForm) {
        console.error('Patient ID not found in FormData!', {
          patientIdEl: patientIdEl?.value,
          formData: Array.from(formData.entries())
        });
        // Manually add it to FormData
        formData.set('patient_id', patientId);
      }
      
      // Explicitly collect all tooth status values from select elements
      // This ensures dynamically created elements are included even if FormData doesn't capture them
      // Try multiple selectors to find all tooth select elements
      const allToothSelects = Array.from(form.querySelectorAll('select[name^="tooth_"][name$="_status"]'));
      // Also check in the modal in case elements are outside the form
      const modalElement = document.getElementById('appointmentDentalFormModal');
      const modalToothSelects = modalElement ? Array.from(modalElement.querySelectorAll('select[name^="tooth_"][name$="_status"]')) : [];
      
      // Combine and deduplicate by name (in case same element appears in both)
      const selectMap = new Map();
      [...allToothSelects, ...modalToothSelects].forEach(select => {
        if (select.name && !selectMap.has(select.name)) {
          selectMap.set(select.name, select);
        }
      });
      
      console.log('Found tooth select elements:', selectMap.size);
      
      let toothStatusCount = 0;
      selectMap.forEach((select, toothName) => {
        const toothValue = select.value;
        
        // Only add non-empty values to FormData
        if (toothValue && toothValue.trim() !== '') {
          formData.set(toothName, toothValue);
          toothStatusCount++;
          console.log(`  Added ${toothName}: ${toothValue}`);
        } else {
          console.log(`  Skipped ${toothName} (empty value)`);
        }
      });
      
      console.log(`Total tooth status values collected: ${toothStatusCount}`);
      
      if (toothStatusCount === 0) {
        console.warn('WARNING: No tooth status values were collected! This might indicate an issue with the form.');
      }
      
      // Debug: Log all tooth status fields being submitted (after manual collection)
      const toothStatusFields = Array.from(formData.entries()).filter(([key]) => key.startsWith('tooth_') && key.endsWith('_status'));
      console.log('Tooth status fields in FormData:', toothStatusFields.length);
      toothStatusFields.forEach(([key, value]) => {
        if (value) {
          console.log(`  ${key}: ${value}`);
        }
      });
      
      console.log('Submitting dental form with patient_id:', patientId, 'appointment_id:', appointmentId);
      
      // Show loading
      const submitBtn = form.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';
      
      // Set submitting flag
      isSubmitting = true;
      
      try {
        // Save the dental record
        const saveResponse = await fetch('crud/save_dental_record.php', {
          method: 'POST',
          body: formData
        });
        
        // Check response status first
        if (!saveResponse.ok) {
          const errorText = await saveResponse.text();
          console.error('HTTP Error:', saveResponse.status, errorText);
          throw new Error(`Server error (${saveResponse.status}): ${errorText.substring(0, 200)}`);
        }
        
        // Check if response is valid JSON
        let saveResult;
        const responseText = await saveResponse.text();
        
        if (!responseText || responseText.trim() === '') {
          throw new Error('Empty response from server. Please check the server logs.');
        }
        
        try {
          saveResult = JSON.parse(responseText);
        } catch (parseError) {
          console.error('Failed to parse response as JSON:', parseError);
          console.error('Response text:', responseText.substring(0, 500));
          throw new Error('Invalid JSON response from server. The server may have encountered an error. Please check the server logs.');
        }
        
        if (!saveResult.success) {
          const errorMessage = saveResult.message || 'Failed to save dental record';
          console.error('Save failed:', errorMessage);
          if (saveResult.error_code) {
            console.error('Error code:', saveResult.error_code);
          }
          throw new Error(errorMessage);
        }
        
        // Check if this was a duplicate prevention (record already exists)
        if (saveResult.duplicate_prevented) {
          // Don't show error, but inform user that record already exists
          Swal.fire({
            icon: 'info',
            title: 'Record Already Exists',
            text: 'A dental record for this appointment has already been saved.',
            timer: 2000,
            showConfirmButton: false
          }).then(() => {
            // Close modal and reload appointments
            const modal = bootstrap.Modal.getInstance(document.getElementById('appointmentDentalFormModal'));
            modal.hide();
            lastUpdate = 0;
            loadMonthAppointments();
            if (selectedDate) {
              displayAppointments(selectedDate);
            }
          });
          return;
        }
        
        // Update appointment status to completed
        const updateResponse = await fetch(API_BASE, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `action=update_status&id=${appointmentId}&status=completed`
        });
        
        // Check update response status
        if (!updateResponse.ok) {
          const errorText = await updateResponse.text();
          console.error('Update HTTP Error:', updateResponse.status, errorText);
          // Don't throw here - the record was saved successfully, just log the update error
          console.warn('Dental record saved but failed to update appointment status');
        } else {
          // Parse update response
          const updateText = await updateResponse.text();
          let updateResult;
          
          try {
            updateResult = JSON.parse(updateText);
          } catch (parseError) {
            console.error('Failed to parse update response:', parseError);
            console.warn('Dental record saved but failed to parse appointment status update response');
            updateResult = { success: false };
          }
          
          if (!updateResult.success) {
            console.warn('Dental record saved but appointment status update failed:', updateResult.error || 'Unknown error');
            // Don't throw - the main save was successful
          }
        }
        
        // Success
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: 'Dental record saved and appointment completed.',
          timer: 2000,
          showConfirmButton: false
        }).then(() => {
          // Close modal
          const modal = bootstrap.Modal.getInstance(document.getElementById('appointmentDentalFormModal'));
          modal.hide();
          
          // Reload appointments
          lastUpdate = 0;
          loadMonthAppointments();
          if (selectedDate) {
            displayAppointments(selectedDate);
          }
        });
        
      } catch (error) {
        console.error('Error:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: error.message || 'An error occurred while saving the record'
        });
      } finally {
        // Reset submitting flag and button state
        isSubmitting = false;
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
      }
    }
    
    // Print dental record
    function printDentalRecord() {
      const printContent = document.getElementById('dentalRecordPrintContent').cloneNode(true);
      const printWindow = window.open('', '_blank');
      
      printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>Dental Examination Record</title>
          <style>
            @media print {
              body { margin: 0; padding: 20px; }
              .consultation-form-container { box-shadow: none; }
            }
            body { font-family: Arial, sans-serif; }
            .consultation-form-header { background: #8B0000; color: white; padding: 20px; text-align: center; }
            .consultation-section { margin-bottom: 30px; }
            .consultation-section-title { color: #8B0000; font-weight: bold; border-bottom: 2px solid #8B0000; padding-bottom: 5px; margin-bottom: 15px; }
            .form-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 15px; }
            .form-group label { font-weight: bold; display: block; margin-bottom: 5px; }
            .readonly-field { background: #f5f5f5; padding: 8px; border: 1px solid #ddd; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            table th, table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            table th { background: #f0f0f0; }
          </style>
        </head>
        <body>
          ${printContent.innerHTML}
        </body>
        </html>
      `);
      
      printWindow.document.close();
      printWindow.focus();
      
      setTimeout(() => {
        printWindow.print();
        printWindow.close();
      }, 250);
    }
    
    // Export dental record to PDF
    function exportDentalToPDF() {
      const printContent = document.getElementById('dentalRecordPrintContent');
      const patientName = document.getElementById('dentalAppointmentFormFullName').value || 'Patient';
      const date = document.getElementById('dentalAppointmentFormDateInput').value || new Date().toISOString().split('T')[0];
      
      if (typeof html2canvas !== 'undefined' && typeof window.jsPDF !== 'undefined') {
        html2canvas(printContent, {
          scale: 2,
          useCORS: true,
          logging: false
        }).then(canvas => {
          const imgData = canvas.toDataURL('image/png');
          const { jsPDF } = window.jsPDF;
          const pdf = new jsPDF('p', 'mm', 'a4');
          
          const imgWidth = 210;
          const pageHeight = 297;
          const imgHeight = (canvas.height * imgWidth) / canvas.width;
          let heightLeft = imgHeight;
          
          let position = 0;
          
          pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
          heightLeft -= pageHeight;
          
          while (heightLeft >= 0) {
            position = heightLeft - imgHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
          }
          
          pdf.save(`dental-record-${patientName.replace(/\s+/g, '-')}-${date}.pdf`);
        }).catch(error => {
          console.error('Error generating PDF:', error);
          printDentalRecord();
        });
      } else {
        printDentalRecord();
      }
    }
    
    // Add dental treatment row
    function addDentalTreatmentRowModal() {
      const tbody = document.getElementById('modalTreatmentBody');
      if (!tbody) return;
      
      const newRow = document.createElement('tr');
      const dentistName = '<?php echo htmlspecialchars($fullName ?? ''); ?>';
      newRow.innerHTML = `
        <td><input type="date" name="treatment_date[]"></td>
        <td><input type="text" name="treatment_tooth[]"></td>
        <td><input type="text" name="treatment_operation[]"></td>
        <td><input type="text" name="treatment_dentist[]" value="${dentistName}"></td>
      `;
      tbody.appendChild(newRow);
    }
  </script>
  
  <style>
    .btn-fill-form {
      background: #8B0000;
      color: white;
      border: none;
      padding: 6px 12px;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.875rem;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      transition: background 0.3s;
    }
    .btn-fill-form:hover {
      background: #A52A2A;
    }
    
    /* Consultation Form Styling - Override for modal */
    #appointmentDentalFormModal .consultation-form-container {
      background: white;
    }
    
    #appointmentDentalFormModal .document-header {
      background: white;
      border: 2px solid #8b2332;
      border-top: none;
      border-radius: 0;
      padding: 20px;
      margin: 0;
      border-bottom: 3px solid #8b2332;
    }
    
    #appointmentDentalFormModal .modal-header {
      background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%);
      border: none;
      border-radius: 8px 8px 0 0;
    }
    
    #appointmentDentalFormModal .modal-header .btn-light {
      background: white;
      color: #8B0000;
      border: none;
    }
    
    #appointmentDentalFormModal .modal-header .btn-light:hover {
      background: #f8f9fa;
      color: #8B0000;
    }
    
    #appointmentDentalFormModal .document-header .header-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 20px;
    }
    
    #appointmentDentalFormModal .document-header .header-logo {
      flex-shrink: 0;
      width: 100px;
      height: 100px;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid #ddd;
      border-radius: 4px;
      padding: 5px;
      background: #f8f9fa;
    }
    
    #appointmentDentalFormModal .document-header .header-info-boxes {
      flex: 1;
      display: flex;
      flex-direction: row;
      gap: 15px;
      justify-content: flex-start;
      align-items: center;
    }
    
    #appointmentDentalFormModal .document-header .info-box {
      display: flex;
      align-items: center;
      gap: 10px;
      flex: 1;
      min-width: 0;
      border: 1px solid #8b2332;
      border-radius: 4px;
      padding: 8px 12px;
      background: white;
    }
    
    #appointmentDentalFormModal .document-header .info-box label {
      font-size: 11px;
      font-weight: 600;
      color: #8b2332;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      white-space: nowrap;
    }
    
    #appointmentDentalFormModal .document-header .info-box span {
      font-size: 13px;
      color: #333;
      font-weight: 500;
    }
    
    #appointmentDentalFormModal .document-header .header-title {
      width: 100%;
      text-align: center;
      padding-top: 10px;
      border-top: 2px solid #8b2332;
      margin-top: 5px;
    }
    
    #appointmentDentalFormModal .document-header .header-title h2 {
      font-size: 20px;
      font-weight: 700;
      color: #8b2332;
      margin: 0;
      text-transform: uppercase;
      letter-spacing: 1px;
      line-height: 1.2;
    }
    
    #appointmentDentalFormModal .dental-form {
      padding: 20px;
    }
    
    #appointmentDentalFormModal .consultation-section {
      margin-bottom: 30px;
      padding: 25px;
      background: #fafafa;
      border-radius: 8px;
      border: 1px solid #e0e0e0;
    }
    
    #appointmentDentalFormModal .consultation-section-title {
      color: #8b2332;
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid #8b2332;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    #appointmentDentalFormModal .form-row {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin-bottom: 15px;
    }
    
    #appointmentDentalFormModal .form-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    
    #appointmentDentalFormModal .form-group label {
      font-weight: 600;
      font-size: 13px;
      color: #555;
    }
    
    #appointmentDentalFormModal .form-group .required {
      color: #dc3545;
    }
    
    #appointmentDentalFormModal .form-group input[type="text"],
    #appointmentDentalFormModal .form-group input[type="date"],
    #appointmentDentalFormModal .form-group input[type="time"] {
      width: 100%;
      border: 1px solid #ddd;
      padding: 10px 12px;
      border-radius: 6px;
      font-size: 14px;
      background: white;
      transition: border-color 0.2s;
    }
    
    #appointmentDentalFormModal .readonly-field {
      background: #f8f9fa !important;
      color: #666 !important;
      cursor: not-allowed !important;
      border: 1px solid #ddd !important;
    }
    
    /* Dental Chart Styles for Modal */
    #appointmentDentalFormModal .dental-chart-wrapper {
      padding: 20px;
      background: white;
      border-radius: 8px;
      border: 2px solid #8b2332;
      margin-bottom: 25px;
    }
    
    #appointmentDentalFormModal .teeth-section-standalone {
      display: flex;
      flex-direction: column;
      gap: 20px;
      max-width: 100%;
      margin: 0 auto;
    }
    
    #appointmentDentalFormModal .teeth-row {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
    }
    
    #appointmentDentalFormModal .teeth-label {
      text-align: center;
      font-size: 13px;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
    }
    
    #appointmentDentalFormModal .tooth-container {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 5px;
    }
    
    #appointmentDentalFormModal .tooth-number {
      font-size: 11px;
      font-weight: 600;
      color: #333;
    }
    
    #appointmentDentalFormModal .tooth-diagram {
      width: 32px;
      height: 32px;
      border: 2px solid #333;
      border-radius: 50%;
      position: relative;
      background: white;
      cursor: pointer;
      transition: all 0.2s;
    }
    
    #appointmentDentalFormModal .tooth-diagram:hover {
      border-color: #8b2332;
      transform: scale(1.1);
    }
    
    #appointmentDentalFormModal .tooth-diagram.marked {
      background: #ffebee;
      border-color: #8b2332;
    }
    
    #appointmentDentalFormModal .tooth-select {
      width: 50px;
      padding: 4px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 11px;
      text-align: center;
      background: white;
    }
    
    #appointmentDentalFormModal .tooth-select:focus {
      outline: none;
      border-color: #8b2332;
    }
    
    /* Index Tables Styles for Modal */
    #appointmentDentalFormModal .index-tables-container {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin-top: 25px;
    }
    
    #appointmentDentalFormModal .index-table-wrapper {
      background: white;
      border: 2px solid #333;
      border-radius: 4px;
      overflow: hidden;
    }
    
    #appointmentDentalFormModal .index-table-title {
      background: #f8f9fa;
      padding: 10px 15px;
      margin: 0;
      font-size: 14px;
      font-weight: 700;
      border-bottom: 2px solid #333;
    }
    
    #appointmentDentalFormModal .index-table {
      width: 100%;
      border-collapse: collapse;
      background: white;
    }
    
    #appointmentDentalFormModal .index-table thead th {
      background: #f8f9fa;
      border: 1px solid #333;
      padding: 8px 6px;
      font-size: 11px;
      font-weight: 700;
      text-align: center;
    }
    
    #appointmentDentalFormModal .index-table tbody td {
      border: 1px solid #333;
      padding: 0;
      text-align: center;
      height: 35px;
    }
    
    #appointmentDentalFormModal .index-table tbody td.label-cell {
      background: #f8f9fa;
      font-weight: 600;
      font-size: 11px;
      padding: 8px;
      text-align: left;
    }
    
    #appointmentDentalFormModal .index-table tbody td input {
      width: 100%;
      height: 100%;
      border: none;
      padding: 8px 4px;
      text-align: center;
      font-size: 12px;
      background: white;
    }
    
    #appointmentDentalFormModal .index-table tbody td input[readonly] {
      background: #f0f0f0;
      color: #666;
      font-weight: 600;
    }
    
    #appointmentDentalFormModal .index-legend {
      padding: 10px 15px;
      background: #f8f9fa;
      border-top: 1px solid #333;
      font-size: 11px;
    }
    
    /* Treatment Table Styles for Modal */
    #appointmentDentalFormModal .treatment-record-wrapper {
      margin-top: 20px;
    }
    
    #appointmentDentalFormModal .treatment-table-standalone {
      width: 100%;
      border-collapse: collapse;
      background: white;
      table-layout: fixed;
      border: 2px solid #000;
      margin-bottom: 15px;
    }
    
    #appointmentDentalFormModal .treatment-table-standalone thead {
      background: #f8f9fa;
    }
    
    #appointmentDentalFormModal .treatment-table-standalone th {
      border-bottom: 2px solid #000;
      border-right: 1px solid #000;
      padding: 12px 8px;
      font-size: 13px;
      background: #f8f9fa;
      font-weight: 700;
      text-align: center;
    }
    
    #appointmentDentalFormModal .treatment-table-standalone tbody tr td {
      border-right: 1px solid #000;
      border-bottom: 1px solid #000;
      padding: 0;
      background: white;
      height: 45px;
    }
    
    #appointmentDentalFormModal .treatment-table-standalone input {
      width: 100%;
      height: 100%;
      border: none;
      padding: 10px 8px;
      font-size: 13px;
      background: white;
    }
    
    #appointmentDentalFormModal .add-row-btn-standalone {
      padding: 8px 16px;
      background: #8b2332;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
      font-size: 14px;
      margin-top: 10px;
    }
    
    /* Legend Section Styles for Modal */
    #appointmentDentalFormModal .legend-section {
      margin: 40px 0 30px 0;
      padding: 25px;
      background: white;
      border-radius: 8px;
      border: 1px solid #e0e0e0;
    }
    
    #appointmentDentalFormModal .legend-title {
      font-size: 16px;
      font-weight: 700;
      color: #8b2332;
      margin-bottom: 15px;
    }
    
    #appointmentDentalFormModal .legend-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 20px;
    }
    
    #appointmentDentalFormModal .legend-column {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    
    #appointmentDentalFormModal .legend-item {
      font-size: 13px;
      color: #333;
    }
    
    #appointmentDentalFormModal .legend-item strong {
      color: #8b2332;
      font-weight: 700;
    }
    
    /* Remarks Section Styles for Modal */
    #appointmentDentalFormModal .remarks-section {
      margin-top: 30px;
    }
    
    #appointmentDentalFormModal .remarks-section label {
      font-weight: 600;
      font-size: 14px;
      color: #333;
      display: block;
      margin-bottom: 8px;
    }
    
    #appointmentDentalFormModal .remarks-section textarea {
      width: 100%;
      min-height: 80px;
      padding: 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 14px;
      font-family: inherit;
    }
  </style>
  
  <!-- Dental Appointment Form Modal -->
  <div class="modal fade" id="appointmentDentalFormModal" tabindex="-1" aria-labelledby="appointmentDentalFormModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content" style="border: none;">
        <div class="modal-body" style="padding: 0;">
          <div class="consultation-form-container" style="border: 2px solid #8b2332; border-radius: 8px; overflow: hidden;">
            <!-- Red Header Bar with Close button -->
            <div class="modal-header" style="background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%); color: white; border: none; border-radius: 8px 8px 0 0; padding: 15px 20px; display: flex; justify-content: flex-end; align-items: center; gap: 10px;">
              <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="opacity: 1;"></button>
            </div>
            
            <div class="document-header" style="border-top: none; border-radius: 0;">
              <div class="header-top">
                <div class="header-logo">
                  <img src="../img/bsu-logo.png" alt="BSU Logo" style="width: 80px; height: 80px; object-fit: contain;">
                </div>
                <div class="header-info-boxes">
                  <div class="info-box">
                    <label>Reference No.:</label>
                    <span>BatStateU-FO-HSD-11</span>
                  </div>
                  <div class="info-box">
                    <label>Effectivity Date:</label>
                    <span>May 18, 2022</span>
                  </div>
                  <div class="info-box">
                    <label>Revision No.:</label>
                    <span>01</span>
                  </div>
                </div>
              </div>
              <div class="header-title">
                <h2>DENTAL EXAMINATION RECORD</h2>
              </div>
            </div>

            <form id="dentalAppointmentForm" class="dental-form" onsubmit="return submitDentalAppointmentForm(event)" style="padding: 20px;">
              <input type="hidden" id="dentalAppointmentFormId" name="appointment_id">
              <input type="hidden" id="dentalAppointmentFormPatientId" name="patient_id">
              <input type="hidden" name="physician_id" value="<?php echo $user_id; ?>">
              <input type="hidden" name="physician_name" value="<?php echo htmlspecialchars($fullName); ?>">

              <!-- Patient Information Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Patient Information</h4>
                <div class="form-row">
                  <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" id="dentalAppointmentFormFullName" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>SR Code <span class="required">*</span></label>
                    <input type="text" id="dentalAppointmentFormSRCode" readonly class="readonly-field">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Address</label>
                    <input type="text" id="dentalAppointmentFormAddress" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Program</label>
                    <input type="text" id="dentalAppointmentFormProgram" readonly class="readonly-field">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Appointment Date</label>
                    <input type="date" id="dentalAppointmentFormDateInput" name="appointment_date" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Appointment Time</label>
                    <input type="time" id="dentalAppointmentFormTimeInput" name="appointment_time" readonly class="readonly-field">
                  </div>
                </div>
              </div>

              <!-- Dentition Status Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Dentition Status and Treatment Needs</h4>
                
                <div class="dental-chart-wrapper">
                  <div class="teeth-section-standalone">
                    <!-- Upper Temporary Teeth -->
                    <div>
                      <div class="teeth-label">Temporary Teeth - Right <span style="float: right;">Left</span></div>
                      <div class="teeth-row" id="modalUpperTempTeeth"></div>
                    </div>

                    <!-- Upper Permanent Teeth -->
                    <div style="margin-top: 20px;">
                      <div class="teeth-row" id="modalUpperPermTeeth"></div>
                    </div>

                    <!-- Lower Permanent Teeth -->
                    <div style="margin-top: 30px;">
                      <div class="teeth-row" id="modalLowerPermTeeth"></div>
                    </div>

                    <!-- Lower Temporary Teeth -->
                    <div style="margin-top: 20px;">
                      <div class="teeth-row" id="modalLowerTempTeeth"></div>
                      <div class="teeth-label">Temporary Teeth</div>
                    </div>
                  </div>
                </div>
                
                <!-- Index Tables -->
                <div class="index-tables-container">
                  <!-- Temporary Teeth Index -->
                  <div class="index-table-wrapper">
                    <h5 class="index-table-title">Temporary Teeth</h5>
                    <table class="index-table">
                      <thead>
                        <tr>
                          <th rowspan="2">Index d.f.t</th>
                          <th colspan="6">Date of Visits</th>
                        </tr>
                        <tr>
                          <th>1st</th>
                          <th>2nd</th>
                          <th>3rd</th>
                          <th>4th</th>
                          <th>5th</th>
                          <th>6th</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td class="label-cell">No. T/Decayed</td>
                          <td><input type="text" name="temp_decayed_1"></td>
                          <td><input type="text" name="temp_decayed_2"></td>
                          <td><input type="text" name="temp_decayed_3"></td>
                          <td><input type="text" name="temp_decayed_4"></td>
                          <td><input type="text" name="temp_decayed_5"></td>
                          <td><input type="text" name="temp_decayed_6"></td>
                        </tr>
                        <tr>
                          <td class="label-cell">No. T/Filled</td>
                          <td><input type="text" name="temp_filled_1"></td>
                          <td><input type="text" name="temp_filled_2"></td>
                          <td><input type="text" name="temp_filled_3"></td>
                          <td><input type="text" name="temp_filled_4"></td>
                          <td><input type="text" name="temp_filled_5"></td>
                          <td><input type="text" name="temp_filled_6"></td>
                        </tr>
                        <tr>
                          <td class="label-cell">Total d.f.t.</td>
                          <td><input type="text" name="temp_total_1" readonly></td>
                          <td><input type="text" name="temp_total_2" readonly></td>
                          <td><input type="text" name="temp_total_3" readonly></td>
                          <td><input type="text" name="temp_total_4" readonly></td>
                          <td><input type="text" name="temp_total_5" readonly></td>
                          <td><input type="text" name="temp_total_6" readonly></td>
                        </tr>
                      </tbody>
                    </table>
                  </div>

                  <!-- Permanent Teeth Index -->
                  <div class="index-table-wrapper">
                    <h5 class="index-table-title">Permanent Teeth</h5>
                    <table class="index-table">
                      <thead>
                        <tr>
                          <th rowspan="2">Index d.f.t</th>
                          <th colspan="4">Date if Visits</th>
                        </tr>
                        <tr>
                          <th>1st</th>
                          <th>2nd</th>
                          <th>3rd</th>
                          <th>4th</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td class="label-cell">D</td>
                          <td><input type="text" name="perm_d_1"></td>
                          <td><input type="text" name="perm_d_2"></td>
                          <td><input type="text" name="perm_d_3"></td>
                          <td><input type="text" name="perm_d_4"></td>
                        </tr>
                        <tr>
                          <td class="label-cell">M</td>
                          <td><input type="text" name="perm_m_1"></td>
                          <td><input type="text" name="perm_m_2"></td>
                          <td><input type="text" name="perm_m_3"></td>
                          <td><input type="text" name="perm_m_4"></td>
                        </tr>
                        <tr>
                          <td class="label-cell">F</td>
                          <td><input type="text" name="perm_f_1"></td>
                          <td><input type="text" name="perm_f_2"></td>
                          <td><input type="text" name="perm_f_3"></td>
                          <td><input type="text" name="perm_f_4"></td>
                        </tr>
                        <tr>
                          <td class="label-cell">Total DMF</td>
                          <td><input type="text" name="perm_total_1" readonly></td>
                          <td><input type="text" name="perm_total_2" readonly></td>
                          <td><input type="text" name="perm_total_3" readonly></td>
                          <td><input type="text" name="perm_total_4" readonly></td>
                        </tr>
                        <tr>
                          <td class="label-cell">Total no of Teeth</td>
                          <td><input type="text" name="perm_teeth_1"></td>
                          <td><input type="text" name="perm_teeth_2"></td>
                          <td><input type="text" name="perm_teeth_3"></td>
                          <td><input type="text" name="perm_teeth_4"></td>
                        </tr>
                      </tbody>
                    </table>
                    
                    <div class="index-legend">
                      <strong>Legend:</strong>
                      <span class="legend-abbr">X = Carious tooth indicated for extraction</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Treatment Record Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Treatment Record</h4>
                
                <div class="treatment-record-wrapper">
                  <table class="treatment-table-standalone">
                    <thead>
                      <tr>
                        <th>Date</th>
                        <th>Tooth No.</th>
                        <th>Operation</th>
                        <th>Dentist</th>
                      </tr>
                    </thead>
                    <tbody id="modalTreatmentBody">
                      <tr>
                        <td><input type="date" name="treatment_date[]"></td>
                        <td><input type="text" name="treatment_tooth[]"></td>
                        <td><input type="text" name="treatment_operation[]"></td>
                        <td><input type="text" name="treatment_dentist[]" value="<?php echo htmlspecialchars($fullName ?? ''); ?>"></td>
                      </tr>
                    </tbody>
                  </table>
                  <button type="button" class="add-row-btn-standalone" onclick="addDentalTreatmentRowModal()">
                    + Add Treatment Row
                  </button>
                </div>
              </div>

              <!-- Legend Section -->
              <div class="legend-section">
                <div class="legend-title">Legend:</div>
                <div class="legend-grid">
                  <div class="legend-column">
                    <div class="legend-item"><strong>X</strong> - For extraction</div>
                    <div class="legend-item"><strong>C</strong> - For filling</div>
                    <div class="legend-item"><strong>RF</strong> - Root Fragment</div>
                    <div class="legend-item"><strong>M</strong> - Missing</div>
                    <div class="legend-item"><strong>Im</strong> - Impacted</div>
                    <div class="legend-item"><strong>Un</strong> - Unerupted</div>
                    <div class="legend-item"><strong>√</strong> - Present</div>
                    <div class="legend-item"><strong>Cm</strong> - Cong. Missing</div>
                    <div class="legend-item"><strong>Sp</strong> - Supernumerary</div>
                    
                    <h5 style="margin-top: 15px;">Periodontal Screening:</h5>
                    <div class="legend-item">
                      <input type="checkbox" name="gingivitis"> Gingivitis
                    </div>
                    <div class="legend-item">
                      <input type="checkbox" name="early_periodontitis"> Early Periodontitis
                    </div>
                  </div>

                  <div class="legend-column">
                    <div class="legend-item"><strong>JC</strong> - Jacket Crown</div>
                    <div class="legend-item"><strong>Am</strong> - Amalgam</div>
                    <div class="legend-item"><strong>Comp</strong> - Composite</div>
                    <div class="legend-item"><strong>TF</strong> - Temporary Filling</div>
                    <div class="legend-item"><strong>S</strong> - Sealant</div>
                    <div class="legend-item"><strong>In</strong> - Inlay</div>

                    <h5 style="margin-top: 15px;">Occlusion:</h5>
                    <div class="legend-item">
                      <input type="checkbox" name="class_molar"> Class (Molar)
                    </div>
                    <div class="legend-item">
                      <input type="checkbox" name="overjet"> Overjet
                    </div>
                    <div class="legend-item">
                      <input type="checkbox" name="overbite"> Overbite
                    </div>
                  </div>

                  <div class="legend-column">
                    <div class="legend-item"><strong>AB</strong> - Abutment</div>
                    <div class="legend-item"><strong>P</strong> - Pontic</div>
                    <div class="legend-item"><strong>RPD</strong> - Partial Denture</div>
                    <div class="legend-item"><strong>CD</strong> - Complete Denture</div>
                    <div class="legend-item"><strong>FB</strong> - Fixed Bridge</div>

                    <h5 style="margin-top: 15px;">Appliances:</h5>
                    <div class="legend-item">
                      <input type="checkbox" name="orthodontic"> Orthodontic
                    </div>
                    <div class="legend-item">
                      <input type="checkbox" name="stayplate"> Stayplate
                    </div>

                    <h5 style="margin-top: 15px;">TMD:</h5>
                    <div class="legend-item">
                      <input type="checkbox" name="clenching"> Clenching
                    </div>
                    <div class="legend-item">
                      <input type="checkbox" name="clicking"> Clicking
                    </div>
                  </div>
                </div>
              </div>

              <!-- Remarks Section -->
              <div class="remarks-section">
                <label for="dentalAppointmentRemarks">Remarks:</label>
                <textarea id="dentalAppointmentRemarks" name="remarks" placeholder="Enter any additional notes or observations..."></textarea>
              </div>

              <!-- Action Buttons -->
              <div class="form-actions" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                  <i class="bi bi-x-circle me-1"></i>Cancel
                </button>
                <button type="submit" class="btn btn-primary" style="background: #8B0000; border: none;">
                  <i class="bi bi-check-circle me-1"></i>Save Dental Record
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  <script>
    // Initialize notification system after DOM is ready
    $(document).ready(function() {
      if (window.DentalNotificationSystem) {
        DentalNotificationSystem.init();
      }
    });
  </script>
  <script src="../js/mobile-menu.js"></script>
</body>
</html>
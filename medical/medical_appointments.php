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
// ✅ Get user data safely
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['fname'] ?? 'Doctor';
$last_name = $_SESSION['lname'] ?? '';
$user_role = $_SESSION['role'] ?? 'doctor';
$user_name = $first_name; // ✅ FIX: Add this line - it was missing!
$fullName = trim($first_name . ' ' . $last_name);

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
  <link href="../medical/css/medical_appointments.css" rel="stylesheet" />
  <link rel="stylesheet" href="../medical/css/responsive.css" />
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
      <a href="../medical/medical_dashboard.php" class="menu-item ">Dashboard</a>
      <a href="../medical/medical_profile.php" class="menu-item">Profile</a>
      <a href="../medical/medical_patients.php" class="menu-item">Patients</a>
      <a href="../medical/medical_appointments.php" class="menu-item active">Appointments</a>
      <a href="../medical/medical_settings.php" class="menu-item">Settings</a>

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
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
  <script src="js/notifications.js"></script>
  <script src="../js/logout.js"></script>
  <script>
    const API_BASE = '../crud/appointment_sync.php';
    const APPOINTMENT_TYPE = 'medical';
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
              <button class="btn-sm btn-fill-form" data-patient-id="${apt.patient_id}" data-appointment-id="${apt.id}" data-fname="${(apt.fname || '').replace(/"/g, '&quot;')}" data-lname="${(apt.lname || '').replace(/"/g, '&quot;')}" data-date="${apt.appointment_date}" data-time="${apt.appointment_time}" title="Open Medical Examination Form (for scheduled appointments)">
                <i class="bi bi-file-earmark-medical-fill"></i> Fill Medical Form
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
              openAppointmentForm(patientId, appointmentId, fname, lname, date, time);
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
    
    // Open appointment medical form modal
    async function openAppointmentForm(patientUserId, appointmentId, patientFname, patientLname, appointmentDate, appointmentTime) {
      try {
        // Store appointment data
        const appointmentFormId = document.getElementById('appointmentFormId');
        if (appointmentFormId) {
          appointmentFormId.value = appointmentId;
        }
        
        // Fetch patient details
        const response = await fetch(`../crud/get_patient_by_user_id.php?user_id=${patientUserId}`);
        const patientData = await response.json();
        
        if (patientData.success && patientData.patient) {
          const patient = patientData.patient;
          const fullNameEl = document.getElementById('appointmentFormFullName');
          const srCodeEl = document.getElementById('appointmentFormSRCode');
          const addressEl = document.getElementById('appointmentFormAddress');
          const programEl = document.getElementById('appointmentFormProgram');
          
          if (fullNameEl) fullNameEl.value = patient.full_name || '';
          if (srCodeEl) srCodeEl.value = patient.sr_code || '';
          if (addressEl) addressEl.value = patient.address || '';
          if (programEl) programEl.value = patient.program || patient.position || '';
        } else {
          // Fallback to using the passed parameters
          const fullNameEl = document.getElementById('appointmentFormFullName');
          if (fullNameEl) fullNameEl.value = `${patientFname} ${patientLname}`.trim();
        }
        
        // Set appointment date and time
        const dateInput = document.getElementById('appointmentFormDateInput');
        const timeInput = document.getElementById('appointmentFormTimeInput');
        if (dateInput) dateInput.value = appointmentDate;
        if (timeInput) timeInput.value = appointmentTime;
        
        // Clear form fields
        const clearFields = [
          'appointmentFormBloodPressure',
          'appointmentFormPulseRate',
          'appointmentFormSpo2',
          'appointmentFormRespiratoryRate',
          'appointmentFormTemperature',
          'appointmentFormHeight',
          'appointmentFormWeight',
          'appointmentFormBMI',
          'appointmentFormDiagnosis'
        ];
        
        clearFields.forEach(fieldId => {
          const field = document.getElementById(fieldId);
          if (field) field.value = '';
        });
        
        // Show modal
        const modalElement = document.getElementById('appointmentMedicalFormModal');
        if (modalElement) {
          const modal = new bootstrap.Modal(modalElement);
          modal.show();
        }
      } catch (error) {
        console.error('Error opening appointment form:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Failed to open appointment form. Please try again.'
        });
      }
    }
    
    // Submit appointment medical form
    let isSubmitting = false;
    
    async function submitAppointmentMedicalForm(event) {
      event.preventDefault();
      
      // Prevent double submission
      if (isSubmitting) {
        return;
      }
      
      const form = event.target;
      const formData = new FormData(form);
      const appointmentId = formData.get('appointment_id');
      const diagnosis = formData.get('diagnosis');
      
      if (!diagnosis) {
        Swal.fire({
          icon: 'error',
          title: 'Validation Error',
          text: 'Please fill in the Diagnosis field.'
        });
        return;
      }
      
      // Show loading
      const submitBtn = form.querySelector('button[type="submit"]');
      const originalText = submitBtn.innerHTML;
      submitBtn.disabled = true;
      submitBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Saving...';
      isSubmitting = true;
      
      try {
        // First, save the medical record
        const saveResponse = await fetch('../crud/save_appointment_record.php', {
          method: 'POST',
          body: formData
        });
        
        const saveResult = await saveResponse.json();
        
        if (!saveResult.success) {
          throw new Error(saveResult.message || 'Failed to save medical record');
        }
        
        // Then, update appointment status to completed
        const updateResponse = await fetch(API_BASE, {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: `action=update_status&id=${appointmentId}&status=completed`
        });
        
        const updateResult = await updateResponse.json();
        
        if (!updateResult.success) {
          throw new Error(updateResult.error || 'Failed to update appointment status');
        }
        
        // Success
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: 'Medical record saved and appointment completed.',
          timer: 2000,
          showConfirmButton: false
        }).then(() => {
          // Close modal
          const modal = bootstrap.Modal.getInstance(document.getElementById('appointmentMedicalFormModal'));
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
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalText;
        isSubmitting = false;
      }
    }
    
    // Calculate BMI for appointment form
    function calculateAppointmentBMI() {
      const height = parseFloat(document.getElementById('appointmentFormHeight').value);
      const weight = parseFloat(document.getElementById('appointmentFormWeight').value);
      const bmiInput = document.getElementById('appointmentFormBMI');
      
      if (height > 0 && weight > 0) {
        const heightInMeters = height / 100;
        const bmi = (weight / (heightInMeters * heightInMeters)).toFixed(2);
        bmiInput.value = bmi;
      } else {
        bmiInput.value = '';
      }
    }
    
    // Print medical appointment record
    function printMedicalAppointmentRecord() {
      const printContent = document.getElementById('appointmentMedicalFormModal').querySelector('.consultation-form-container');
      if (!printContent) {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Form content not found'
        });
        return;
      }
      
      const printWindow = window.open('', '_blank');
      const patientName = document.getElementById('appointmentFormFullName')?.value || 'Patient';
      const date = document.getElementById('appointmentFormDateInput')?.value || new Date().toISOString().split('T')[0];
      
      printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
          <title>Medical Examination Record - ${patientName}</title>
          <meta charset="UTF-8">
          <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
          <style>
            body { font-family: Arial, sans-serif; padding: 20px; }
            .consultation-form-container { background: white; }
            .document-header { margin-bottom: 30px; }
            .consultation-section { margin-bottom: 30px; page-break-inside: avoid; }
            .consultation-section-title { color: #8b2332; font-size: 18px; font-weight: 700; margin-bottom: 15px; padding-bottom: 8px; border-bottom: 2px solid #8b2332; }
            .form-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 15px; }
            .form-group { display: flex; flex-direction: column; gap: 5px; }
            .form-group label { font-weight: 600; font-size: 14px; color: #333; }
            .readonly-field { background: #f9f9f9; border: 1px solid #ddd; padding: 8px 12px; border-radius: 4px; }
            .vitals-grid, .measurements-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; }
            textarea { width: 100%; padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; min-height: 60px; }
            @media print {
              @page { margin: 1cm; }
              body { padding: 0; }
            }
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
        setTimeout(() => printWindow.close(), 500);
      }, 250);
    }
    
    // Export medical appointment record to PDF
    function exportMedicalAppointmentToPDF() {
      const printContent = document.getElementById('appointmentMedicalFormModal').querySelector('.consultation-form-container');
      const patientName = document.getElementById('appointmentFormFullName')?.value || 'Patient';
      const date = document.getElementById('appointmentFormDateInput')?.value || new Date().toISOString().split('T')[0];
      
      if (typeof html2canvas !== 'undefined' && typeof window.jsPDF !== 'undefined') {
        Swal.fire({
          title: 'Generating PDF...',
          text: 'Please wait while we prepare your document',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });
        
        html2canvas(printContent, {
          scale: 2,
          useCORS: true,
          logging: false,
          backgroundColor: '#ffffff'
        }).then(canvas => {
          const imgData = canvas.toDataURL('image/png', 1.0);
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
          
          pdf.save('medical-examination-' + patientName.replace(/\s+/g, '-') + '-' + date + '.pdf');
          Swal.close();
        }).catch(error => {
          console.error('PDF generation error:', error);
          Swal.fire({
            icon: 'error',
            title: 'Export Failed',
            text: 'Failed to generate PDF. Please try printing instead.',
            confirmButtonText: 'Print Instead'
          }).then(() => {
            printMedicalAppointmentRecord();
          });
        });
      } else {
        printMedicalAppointmentRecord();
      }
    }
  </script>
  
  <!-- Appointment Medical Form Modal -->
  <div class="modal fade" id="appointmentMedicalFormModal" tabindex="-1" aria-labelledby="appointmentMedicalFormModalLabel" aria-hidden="true">
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
                <h2>MEDICAL EXAMINATION RECORD</h2>
              </div>
            </div>

            <form id="appointmentMedicalForm" class="medical-form" onsubmit="submitAppointmentMedicalForm(event)" style="padding: 20px;">
              <input type="hidden" id="appointmentFormId" name="appointment_id">
              <input type="hidden" name="physician_id" value="<?php echo $user_id; ?>">
              <input type="hidden" name="physician_name" value="<?php echo htmlspecialchars($fullName); ?>">
              
              <!-- Patient Information Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Patient Information</h4>
                <div class="form-row">
                  <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" id="appointmentFormFullName" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>SR Code <span class="required">*</span></label>
                    <input type="text" id="appointmentFormSRCode" readonly class="readonly-field">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Address</label>
                    <input type="text" id="appointmentFormAddress" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Program</label>
                    <input type="text" id="appointmentFormProgram" readonly class="readonly-field">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Appointment Date</label>
                    <input type="date" id="appointmentFormDateInput" name="appointment_date" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Appointment Time</label>
                    <input type="time" id="appointmentFormTimeInput" name="appointment_time" readonly class="readonly-field">
                  </div>
                </div>
              </div>

              <!-- Vital Signs Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Vital Signs</h4>
                <div class="vitals-grid">
                  <div class="form-group">
                    <label>Blood Pressure (BP)</label>
                    <input type="text" id="appointmentFormBloodPressure" name="blood_pressure" placeholder="e.g., 120/80">
                  </div>
                  <div class="form-group">
                    <label>Pulse Rate (PR)</label>
                    <input type="text" id="appointmentFormPulseRate" name="pulse_rate" placeholder="e.g., 72 bpm">
                  </div>
                  <div class="form-group">
                    <label>SpO2</label>
                    <input type="text" id="appointmentFormSpo2" name="spo2" placeholder="e.g., 98%">
                  </div>
                  <div class="form-group">
                    <label>Respiratory Rate (RR)</label>
                    <input type="text" id="appointmentFormRespiratoryRate" name="respiratory_rate" placeholder="e.g., 16">
                  </div>
                  <div class="form-group">
                    <label>Temperature (T)</label>
                    <input type="text" id="appointmentFormTemperature" name="temperature" placeholder="e.g., 36.5°C">
                  </div>
                </div>
              </div>

              <!-- Measurements Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Measurements</h4>
                <div class="measurements-grid">
                  <div class="form-group">
                    <label>Height (HT)</label>
                    <input type="text" id="appointmentFormHeight" name="height" placeholder="e.g., 165 cm" oninput="calculateAppointmentBMI()">
                  </div>
                  <div class="form-group">
                    <label>Weight (WT)</label>
                    <input type="text" id="appointmentFormWeight" name="weight" placeholder="e.g., 60 kg" oninput="calculateAppointmentBMI()">
                  </div>
                  <div class="form-group">
                    <label>BMI</label>
                    <input type="text" id="appointmentFormBMI" name="bmi" readonly class="readonly-field" placeholder="Auto-calculated">
                  </div>
                </div>
              </div>

              <!-- Doctor's Notes Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Doctor's Notes</h4>
                <div class="form-group">
                  <label>Diagnosis <span class="required">*</span></label>
                  <textarea id="appointmentFormDiagnosis" name="diagnosis" rows="2" placeholder="Enter diagnosis..." required></textarea>
                </div>
              </div>

              <div class="form-actions" style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; display: flex; justify-content: flex-end; gap: 10px;">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                  <i class="bi bi-x-circle me-1"></i>Cancel
                </button>
                <button type="submit" class="btn btn-primary" style="background: #8B0000; border: none;">
                  <i class="bi bi-check-circle me-1"></i>Save Medical Record
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
  
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
    
    /* Consultation Form Styling */
    .consultation-form-container {
      background: white;
    }
    
    #appointmentMedicalFormModal .modal-header {
      background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%);
      border: none;
      border-radius: 8px 8px 0 0;
    }
    
    #appointmentMedicalFormModal .modal-header .btn-light {
      background: white;
      color: #8B0000;
      border: none;
    }
    
    #appointmentMedicalFormModal .modal-header .btn-light:hover {
      background: #f8f9fa;
      color: #8B0000;
    }
    
    /* Document Header Style (matching official form format) */
    #appointmentMedicalFormModal .document-header {
      background: white;
      border: 2px solid #8b2332;
      border-top: none;
      border-radius: 0;
      padding: 20px;
      margin: 0;
      display: flex;
      flex-wrap: wrap;
      align-items: flex-start;
      gap: 20px;
      border-bottom: 3px solid #8b2332;
    }
    
    #appointmentMedicalFormModal .document-header .header-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 20px;
      width: 100%;
    }
    
    #appointmentMedicalFormModal .document-header .header-info-boxes {
      flex: 1;
      display: flex;
      flex-direction: row;
      gap: 15px;
      justify-content: flex-start;
      align-items: center;
    }
    
    #appointmentMedicalFormModal .document-header .info-box {
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
    
    #appointmentMedicalFormModal .document-header .info-box label {
      font-size: 11px;
      font-weight: 600;
      color: #8b2332;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      white-space: nowrap;
    }
    
    #appointmentMedicalFormModal .document-header .info-box span {
      font-size: 13px;
      color: #333;
      font-weight: 500;
    }

    .header-logo {
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

    .header-logo img {
      max-width: 100%;
      max-height: 100%;
      object-fit: contain;
    }

    .header-info-boxes {
      flex: 1;
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
      min-width: 300px;
    }

    .info-box {
      flex: 1;
      min-width: 150px;
      border: 1px solid #8B0000;
      border-radius: 4px;
      padding: 8px 12px;
      background: white;
    }

    .info-box label {
      display: block;
      font-size: 11px;
      font-weight: 600;
      color: #8B0000;
      margin-bottom: 4px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .info-box span {
      display: block;
      font-size: 13px;
      color: #333;
      font-weight: 500;
    }

    .header-title {
      width: 100%;
      text-align: center;
      padding-top: 10px;
      border-top: 2px solid #8B0000;
      margin-top: 5px;
    }

    .header-title h2 {
      font-size: 20px;
      font-weight: 700;
      color: #8B0000;
      margin: 0;
      text-transform: uppercase;
      letter-spacing: 1px;
      line-height: 1.2;
    }
    
    /* Legacy header style (for backward compatibility) */
    .consultation-form-header {
      background: #8B0000;
      color: white;
      padding: 20px;
      text-align: center;
      margin-bottom: 20px;
      position: relative;
    }
    
    .consultation-form-header h3 {
      margin: 0;
      font-size: 24px;
      font-weight: bold;
      text-transform: uppercase;
      letter-spacing: 1px;
    }
    
    .consultation-section {
      margin-bottom: 25px;
    }
    
    .consultation-section-title {
      color: #8B0000;
      font-size: 18px;
      font-weight: bold;
      margin-bottom: 15px;
      border-bottom: 2px solid #8B0000;
      padding-bottom: 5px;
    }
    
    .form-row {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 15px;
      margin-bottom: 15px;
    }
    
    .form-group {
      margin-bottom: 15px;
    }
    
    .form-group label {
      display: block;
      font-weight: bold;
      margin-bottom: 5px;
      color: #333;
      font-size: 0.95rem;
    }
    
    .form-group .required {
      color: #dc3545;
    }
    
    .form-control, 
    textarea, 
    input[type="text"], 
    input[type="date"], 
    input[type="time"], 
    input[type="number"] {
      width: 100%;
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 0.95rem;
      font-family: inherit;
    }
    
    .form-control:focus, 
    textarea:focus, 
    input:focus {
      border-color: #8B0000;
      outline: none;
      box-shadow: 0 0 0 2px rgba(139, 0, 0, 0.1);
    }
    
    .readonly-field {
      background: #f9f9f9;
      cursor: not-allowed;
    }
    
    #appointmentMedicalFormModal .consultation-section {
      margin-bottom: 30px;
      padding: 25px;
      background: #fafafa;
      border-radius: 8px;
      border: 1px solid #e0e0e0;
    }
    
    #appointmentMedicalFormModal .consultation-section-title {
      color: #8b2332;
      font-size: 16px;
      font-weight: 600;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid #8b2332;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
    
    #appointmentMedicalFormModal .form-row {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      margin-bottom: 15px;
    }
    
    #appointmentMedicalFormModal .form-group {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    
    #appointmentMedicalFormModal .form-group label {
      font-weight: 600;
      font-size: 13px;
      color: #555;
    }
    
    #appointmentMedicalFormModal .form-group .required {
      color: #dc3545;
    }
    
    #appointmentMedicalFormModal .form-group input[type="text"],
    #appointmentMedicalFormModal .form-group input[type="date"],
    #appointmentMedicalFormModal .form-group input[type="time"] {
      width: 100%;
      border: 1px solid #ddd;
      padding: 10px 12px;
      border-radius: 6px;
      font-size: 14px;
      background: white;
      transition: border-color 0.2s;
    }
    
    #appointmentMedicalFormModal .readonly-field {
      background: #f8f9fa !important;
      color: #666 !important;
      cursor: not-allowed !important;
      border: 1px solid #ddd !important;
    }
    
    .vitals-grid, 
    .measurements-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
      gap: 15px;
    }
    
    textarea {
      resize: vertical;
      min-height: 60px;
    }
    
    .form-actions {
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid #e0e0e0;
    }
  </style>
  <script src="../js/mobile-menu.js"></script>
</body>
</html>
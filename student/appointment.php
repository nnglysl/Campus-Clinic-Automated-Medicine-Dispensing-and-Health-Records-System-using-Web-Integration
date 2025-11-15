<?php
require_once '../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

$user = [
    'id' => $_SESSION['user_id'],
    'fname' => $_SESSION['fname'] ?? 'Student',
    'lname' => $_SESSION['lname'] ?? '',
    'role' => $_SESSION['role'] ?? 'student',
    'full_name' => ($_SESSION['fname'] ?? 'Student') . ' ' . ($_SESSION['lname'] ?? '')
];

$pdo = getDB();

// Add after $pdo = getDB();
function getDoctorAvailableDates($pdo, $startDate, $endDate) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT schedule_date
            FROM doctor_schedules
            WHERE schedule_date BETWEEN ? AND ?
            AND is_available = 1
            AND schedule_type = 'available'
            ORDER BY schedule_date
        ");
        $stmt->execute([$startDate, $endDate]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        error_log("Error fetching doctor schedules: " . $e->getMessage());
        return [];
    }
}

$startDate = date('Y-m-01');
$endDate = date('Y-m-t', strtotime('+2 months'));
$availableDates = getDoctorAvailableDates($pdo, $startDate, $endDate);
// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    header('Content-Type: application/json');
    
    try {
        $appointmentType = $_POST['appointment_type'] ?? '';
        $appointmentDate = $_POST['appointment_date'] ?? '';
        $appointmentTime = $_POST['appointment_time'] ?? '';
        $department = $_POST['department'] ?? '';
        
        if (empty($appointmentType) || empty($appointmentDate) || empty($appointmentTime) || empty($department)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit();
        }
        
        if (strtotime($appointmentDate) < strtotime(date('Y-m-d'))) {
            echo json_encode(['success' => false, 'message' => 'Cannot book appointments in the past.']);
            exit();
        }
        
        $dayOfWeek = date('w', strtotime($appointmentDate));
        if ($dayOfWeek == 0 || $dayOfWeek == 6) {
            echo json_encode(['success' => false, 'message' => 'Appointments not available on weekends.']);
            exit();
        }
        
        // Check for conflicts (1 patient per slot per department)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM appointments 
            WHERE appointment_date = ? 
            AND appointment_time = ? 
            AND appointment_type = ?
            AND status != 'cancelled'
        ");
        $stmt->execute([$appointmentDate, $appointmentTime, $appointmentType]);
        $conflict = $stmt->fetch();
        
        if ($conflict['count'] >= 1) {
            echo json_encode(['success' => false, 'message' => 'Time slot already booked.']);
            exit();
        }
        
        // Insert appointment with department
        $stmt = $pdo->prepare("
            INSERT INTO appointments 
            (patient_id, appointment_date, appointment_time, appointment_type, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, 'scheduled', NOW(), NOW())
        ");
        $stmt->execute([
            $user['id'],
            $appointmentDate,
            $appointmentTime,
            $appointmentType
        ]);
        
        $appointmentId = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Appointment confirmed!',
            'appointment' => [
                'id' => $appointmentId,
                'type' => $appointmentType,
                'department' => $department,
                'date' => $appointmentDate,
                'time' => $appointmentTime
            ]
        ]);
        exit();
        
    } catch (PDOException $e) {
        error_log("Error booking appointment: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Appointment Reservation - BSU Clinic</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <link href="css/nav.css" rel="stylesheet" />
  <link href="css/appointment.css" rel="stylesheet" />
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

    <div class="main-container d-flex">
      <div class="sidebar">
        <a href="student_dashboard.php" class="menu-item">Dashboard</a>
        <a href="profile.php" class="menu-item">Profile</a>
        <a href="appointment.php" class="menu-item active">Appointment</a>
        <a href="records.php" class="menu-item">Health Records</a>
        <a href="settings.php" class="menu-item">Settings</a>
      </div>

      <div class="content-wrapper" style="flex: 1; display: flex; flex-direction: column;">
        <!-- Department Selection View -->
        <div class="department-selection" id="departmentSelection">
          <h2>Book an Appointment</h2>
          <p>Please select the department you'd like to visit</p>
          
          <div class="department-buttons">
            <div class="department-btn dental" onclick="selectDepartment('dental')">
              <i class="bi bi-heart-pulse-fill"></i>
              <h3>Dental</h3>
              <p>Dental care & treatment</p>
            </div>
            
            <div class="department-btn medical" onclick="selectDepartment('medical')">
              <i class="bi bi-hospital-fill"></i>
              <h3>Medical</h3>
              <p>General medical services</p>
            </div>
          </div>
        </div>

        <!-- Dental Calendar -->
        <div class="appointment-container dental" id="dentalCalendar">
          <div class="date-section">
            <a href="#" class="back-to-selection" onclick="backToSelection(); return false;">
              <i class="bi bi-arrow-left"></i> Back to Selection
            </a>
            
            <span class="department-badge">🦷 Dental Department</span>
            <h3>Select Date</h3>
            <p class="subtitle">Real-time sync with dental schedules
              <span class="last-updated" id="lastUpdatedDental"></span>
            </p>

            <div class="calendar-controls">
              <button id="prevMonthDental"><i class="bi bi-chevron-left"></i></button>
              <h4 id="monthYearDental">November 2025</h4>
              <button id="nextMonthDental"><i class="bi bi-chevron-right"></i></button>
            </div>

            <div class="calendar-grid" id="calendarGridDental"></div>
            
          
          </div>

          <div class="time-section">
            <h3>Select Time</h3>

            <div class="time-slots-list" id="timeSlotsListDental">
              <div class="empty-state">
                <i class="bi bi-calendar-week"></i>
                <p>Select a date to view available time slots</p>
              </div>
            </div>

            <div class="action-buttons">
              <button class="btn-back" onclick="backToSelection()">BACK</button>
              <button class="btn-next" id="btnNextDental" disabled>NEXT</button>
            </div>
          </div>
        </div>

        <!-- Medical Calendar -->
        <div class="appointment-container medical" id="medicalCalendar">
          <div class="date-section">
            <a href="#" class="back-to-selection" onclick="backToSelection(); return false;">
              <i class="bi bi-arrow-left"></i> Back to Selection
            </a>
            
            <span class="department-badge">🩺 Medical Department</span>
            <h3>Select Date</h3>
            <p class="subtitle">Real-time sync with medical schedules
              <span class="last-updated" id="lastUpdatedMedical"></span>
            </p>

            <div class="calendar-controls">
              <button id="prevMonthMedical"><i class="bi bi-chevron-left"></i></button>
              <h4 id="monthYearMedical">November 2025</h4>
              <button id="nextMonthMedical"><i class="bi bi-chevron-right"></i></button>
            </div>

            <div class="calendar-grid" id="calendarGridMedical"></div>
          
          </div>

          <div class="time-section">
            <h3>Select Time</h3>

            <div class="time-slots-list" id="timeSlotsListMedical">
              <div class="empty-state">
                <i class="bi bi-calendar-week"></i>
                <p>Select a date to view available time slots</p>
              </div>
            </div>

            <div class="action-buttons">
              <button class="btn-back" onclick="backToSelection()">BACK</button>
              <button class="btn-next" id="btnNextMedical" disabled>NEXT</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Booking Modal -->
  <div class="modal fade" id="bookingModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">
            <i class="bi bi-calendar-check me-2"></i>Confirm Appointment
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>

        <form id="appointmentForm">
          <div class="modal-body">
            <div class="mb-4">
              <h6 class="fw-semibold text-primary">
                <span id="departmentDisplay"></span> - <span id="selectedDateDisplay"></span>
              </h6>
            </div>

            <input type="hidden" id="appointmentDate" name="appointment_date">
            <input type="hidden" id="appointmentTime" name="appointment_time">
            <input type="hidden" id="appointmentType" name="appointment_type">
            <input type="hidden" id="appointmentDepartment" name="department">
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Confirm Booking</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script>
  <script>
    // Configuration
    const API_BASE = '../crud';
    const DOCTOR_AVAILABLE_DATES = <?php echo json_encode($availableDates); ?>;
    // State
    let currentDepartment = null;
    let currentDate = { dental: new Date(), medical: new Date() };
    let selectedDate = { dental: null, medical: null };
    let selectedTime = { dental: null, medical: null };
    let availabilityCache = { dental: {}, medical: {} };
    let timeSlotsCache = { dental: {}, medical: {} };
    let lastNotificationId = 0;
    let lastSyncTime = { dental: null, medical: null };
    let pollAbortController = null;
    
    // Initialize
    document.addEventListener('DOMContentLoaded', () => {
      initializeEventListeners();
      // Don't render calendar yet - wait for department selection
    });
    
    function initializeEventListeners() {
      // Dental calendar
      document.getElementById('prevMonthDental')?.addEventListener('click', async () => {
        currentDate.dental.setMonth(currentDate.dental.getMonth() - 1);
        await syncAvailability('dental');
        renderCalendar('dental');
      });
      
      document.getElementById('nextMonthDental')?.addEventListener('click', async () => {
        currentDate.dental.setMonth(currentDate.dental.getMonth() + 1);
        await syncAvailability('dental');
        renderCalendar('dental');
      });
      
      document.getElementById('btnNextDental')?.addEventListener('click', () => handleNextClick('dental'));
      
      // Medical calendar
      document.getElementById('prevMonthMedical')?.addEventListener('click', async () => {
        currentDate.medical.setMonth(currentDate.medical.getMonth() - 1);
        await syncAvailability('medical');
        renderCalendar('medical');
      });
      
      document.getElementById('nextMonthMedical')?.addEventListener('click', async () => {
        currentDate.medical.setMonth(currentDate.medical.getMonth() + 1);
        await syncAvailability('medical');
        renderCalendar('medical');
      });
      
      document.getElementById('btnNextMedical')?.addEventListener('click', () => handleNextClick('medical'));
      
      document.getElementById('appointmentForm')?.addEventListener('submit', handleFormSubmit);
    }
    
    async function selectDepartment(dept) {
      currentDepartment = dept;
      
      // Hide selection, show calendar
      document.getElementById('departmentSelection').style.display = 'none';
      document.getElementById(dept + 'Calendar').classList.add('active');
      
      // Initialize calendar and sync - sync FIRST before rendering
      await syncAvailability(dept);
      renderCalendar(dept);
      startRealTimeSync(dept);
    }
    
    function backToSelection() {
      // Stop polling
      if (pollAbortController) {
        pollAbortController.abort();
      }
      
      // Hide calendars, show selection
      document.getElementById('dentalCalendar').classList.remove('active');
      document.getElementById('medicalCalendar').classList.remove('active');
      document.getElementById('departmentSelection').style.display = 'flex';
      
      // Reset state
      currentDepartment = null;
      selectedDate = { dental: null, medical: null };
      selectedTime = { dental: null, medical: null };
    }
    
    // Real-time sync system
    function startRealTimeSync(dept) {
      if (pollAbortController) {
        pollAbortController.abort();
      }
      startLongPolling(dept);
    }
    
    async function startLongPolling(dept) {
      while (currentDepartment === dept) {
        try {
          pollAbortController = new AbortController();
          
          const response = await fetch(
            `${API_BASE}/poll_schedule_changes.php?last_id=${lastNotificationId}&timeout=25`,
            { signal: pollAbortController.signal }
          );
          
          if (!response.ok) throw new Error('Poll failed');
          
          const result = await response.json();
          
          if (result.success && result.has_changes) {
            console.log('Changes detected:', result.notifications);
            lastNotificationId = result.last_id;
            
            // Check if changes affect current view
            const affectsCurrentView = result.notifications.some(notif => {
              const data = notif.data;
              if (data.schedule_date || data.appointment_date) {
                const date = new Date(data.schedule_date || data.appointment_date);
                return date.getMonth() === currentDate[dept].getMonth() && 
                       date.getFullYear() === currentDate[dept].getFullYear();
              }
              return false;
            });
            
            if (affectsCurrentView) {
              showSyncIndicator('syncing', 'New changes detected...');
              await syncAvailability(dept);
              
              // Refresh time slots if date is selected
              if (selectedDate[dept]) {
                renderTimeSlots(selectedDate[dept], dept);
              }
            }
          }
          
          await new Promise(resolve => setTimeout(resolve, 1000));
          
        } catch (error) {
          if (error.name === 'AbortError') break;
          console.error('Polling error:', error);
          await new Promise(resolve => setTimeout(resolve, 5000));
        }
      }
    }
    
    async function syncAvailability(dept) {
      try {
        const startDate = new Date(currentDate[dept].getFullYear(), currentDate[dept].getMonth(), 1);
        const endDate = new Date(currentDate[dept].getFullYear(), currentDate[dept].getMonth() + 1, 0);
        
        const response = await fetch(
          `${API_BASE}/get_available_slots.php?start_date=${formatDate(startDate)}&end_date=${formatDate(endDate)}&department=${dept}`
        );
        
        if (!response.ok) throw new Error('Sync failed');
        
        const result = await response.json();
        
        if (result.success) {
          availabilityCache[dept] = result.data.calendar_dates;
          lastSyncTime[dept] = new Date(result.data.last_updated);
          
          // Cache all time slots for the month
          const timeSlots = result.data.time_slots || [];
          timeSlots.forEach(slot => {
            if (!timeSlotsCache[dept][slot.date]) {
              timeSlotsCache[dept][slot.date] = [];
            }
            timeSlotsCache[dept][slot.date].push(slot);
          });
          
          renderCalendar(dept);
          updateLastSyncedTime(dept);
          showSyncIndicator('success', 'Synced successfully');
        } else {
          throw new Error(result.error || 'Sync failed');
        }
      } catch (error) {
        console.error('Sync error:', error);
        showSyncIndicator('error', 'Sync failed');
      }
    }
    
    function showSyncIndicator(type, message) {
      const indicator = document.getElementById('syncIndicator');
      const messageEl = document.getElementById('syncMessage');
      
      indicator.className = 'sync-indicator ' + type;
      messageEl.textContent = message;
      
      if (type === 'success') {
        setTimeout(() => {
          indicator.style.display = 'none';
        }, 2000);
      }
    }
    
    function updateLastSyncedTime(dept) {
      const el = document.getElementById('lastUpdated' + dept.charAt(0).toUpperCase() + dept.slice(1));
      if (el && lastSyncTime[dept]) {
        el.textContent = `Last synced: ${lastSyncTime[dept].toLocaleTimeString()}`;
      }
    }
    
    function renderCalendar(dept) {
  const grid = document.getElementById('calendarGrid' + dept.charAt(0).toUpperCase() + dept.slice(1));
  if (!grid) return;
  
  const year = currentDate[dept].getFullYear();
  const month = currentDate[dept].getMonth();
  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  const daysInMonth = lastDay.getDate();
  const startDay = firstDay.getDay();
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  
  document.getElementById('monthYear' + dept.charAt(0).toUpperCase() + dept.slice(1)).textContent = 
    firstDay.toLocaleString('default', { month: 'long', year: 'numeric' });
  
  grid.innerHTML = '';
  
  // Headers
  ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].forEach(day => {
    const el = document.createElement('div');
    el.className = 'calendar-header';
    el.textContent = day;
    grid.appendChild(el);
  });
  
  // Previous month days
  const prevMonthLastDay = new Date(year, month, 0).getDate();
  for (let i = startDay - 1; i >= 0; i--) {
    const el = document.createElement('div');
    el.className = 'calendar-day other-month';
    el.textContent = prevMonthLastDay - i;
    grid.appendChild(el);
  }
  
  // Current month days
  for (let day = 1; day <= daysInMonth; day++) {
    const date = new Date(year, month, day);
    date.setHours(0, 0, 0, 0);
    
    const dateISO = formatDate(date);
    const dayOfWeek = date.getDay();
    
    const el = document.createElement('div');
    el.className = 'calendar-day';
    el.textContent = day;
    
    const isPast = date < today;
    const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
    
    // ✅ CHECK IF DATE HAS DOCTOR SCHEDULE INSTEAD OF HARDCODED DAYS
    const isDoctorAvailable = DOCTOR_AVAILABLE_DATES.includes(dateISO);
    
    if (date.getTime() === today.getTime()) el.classList.add('today');
    if (selectedDate[dept] && dateISO === selectedDate[dept]) el.classList.add('selected');
    
    if (isPast || isWeekend || !isDoctorAvailable) {
      el.classList.add('disabled');
    } else {
      const availability = availabilityCache[dept][dateISO];
      
      if (availability) {
        el.classList.add(availability.status);
        el.title = `${availability.available_slots} of ${availability.total_slots} slots available`;
        
        if (availability.status === 'fully-booked') {
          el.style.cursor = 'not-allowed';
        } else {
          el.addEventListener('click', () => {
            selectedDate[dept] = dateISO;
            renderCalendar(dept);
            renderTimeSlots(dateISO, dept);
          });
        }
      } else if (!el.classList.contains('unavailable')) {
        el.addEventListener('click', () => {
          selectedDate[dept] = dateISO;
          renderCalendar(dept);
          renderTimeSlots(dateISO, dept);
        });
      }
    }
    
    grid.appendChild(el);
  }
  
  // Next month days
  const totalCells = grid.children.length - 7;
  const remaining = (Math.ceil(totalCells / 7) * 7) - totalCells;
  for (let i = 1; i <= remaining; i++) {
    const el = document.createElement('div');
    el.className = 'calendar-day other-month';
    el.textContent = i;
    grid.appendChild(el);
  }
}
    
    async function renderTimeSlots(dateISO, dept) {
      const container = document.getElementById('timeSlotsList' + dept.charAt(0).toUpperCase() + dept.slice(1));
      if (!container) return;
      
      // Check cache first for instant loading
      if (timeSlotsCache[dept][dateISO]) {
        displayTimeSlots(timeSlotsCache[dept][dateISO], container, dept);
        return;
      }
      
      container.innerHTML = '<div class="loading-spinner"><i class="bi bi-arrow-repeat"></i><p>Loading time slots...</p></div>';
      
      try {
        const response = await fetch(`${API_BASE}/get_available_slots.php?start_date=${dateISO}&end_date=${dateISO}&department=${dept}`);
        const result = await response.json();
        
        if (!result.success) throw new Error(result.error);
        
        const slots = result.data.time_slots.filter(slot => slot.date === dateISO);
        
        // Cache the slots
        timeSlotsCache[dept][dateISO] = slots;
        
        displayTimeSlots(slots, container, dept);
      } catch (error) {
        console.error('Error loading time slots:', error);
        container.innerHTML = '<div class="empty-state" style="color: #f44336;"><i class="bi bi-exclamation-triangle"></i><p>Error loading slots</p></div>';
      }
    }
    
    function displayTimeSlots(slots, container, dept) {
      if (slots.length === 0) {
        container.innerHTML = '<div class="empty-state"><i class="bi bi-calendar-x"></i><p>No time slots available</p></div>';
        return;
      }
      
      container.innerHTML = '';
      
      slots.forEach(slot => {
        const item = document.createElement('div');
        item.className = 'time-slot-item';
        if (!slot.available) item.classList.add('disabled');
        
        const statusClass = slot.available ? 'status-available' : 'status-booked';
        const statusText = slot.available ? 'Available' : 'Booked';
        
        item.innerHTML = `
          <input type="radio" name="timeSlot${dept}" value="${slot.time}" ${slot.available ? '' : 'disabled'}>
          <div class="time-slot-info">
            <span class="time-slot-time">${slot.formatted_time}</span>
            <span class="time-slot-status ${statusClass}">${statusText}</span>
          </div>
        `;
        
        if (slot.available) {
          item.querySelector('input').addEventListener('change', (e) => {
            if (e.target.checked) {
              selectedTime[dept] = slot.time;
              document.getElementById('btnNext' + dept.charAt(0).toUpperCase() + dept.slice(1)).disabled = false;
            }
          });
        }
        
        container.appendChild(item);
      });
    }
    
    function formatDate(date) {
      const year = date.getFullYear();
      const month = String(date.getMonth() + 1).padStart(2, '0');
      const day = String(date.getDate()).padStart(2, '0');
      return `${year}-${month}-${day}`;
    }
    
    function formatTime(time24) {
      const parts = time24.split(':');
      const hour = parseInt(parts[0]);
      const minutes = parts[1] || '00';
      const ampm = hour >= 12 ? 'PM' : 'AM';
      const hour12 = hour % 12 || 12;
      return `${String(hour12).padStart(2, '0')}:${minutes} ${ampm}`;
    }
    
    function handleNextClick(dept) {
      if (!selectedDate[dept] || !selectedTime[dept]) return;
      
      const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
      document.getElementById('appointmentForm')?.reset();
      
      document.getElementById('appointmentDate').value = selectedDate[dept];
      document.getElementById('appointmentTime').value = selectedTime[dept];
      document.getElementById('appointmentType').value = dept;
      document.getElementById('appointmentDepartment').value = dept;
      
      const [year, month, day] = selectedDate[dept].split('-').map(Number);
      const dateObj = new Date(year, month - 1, day);
      
      const formatted = dateObj.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
      });
      
      const deptName = dept.charAt(0).toUpperCase() + dept.slice(1);
      const deptIcon = dept === 'dental' ? '🦷' : '🩺';
      
      document.getElementById('departmentDisplay').textContent = `${deptIcon} ${deptName}`;
      document.getElementById('selectedDateDisplay').textContent = 
        `${formatted} at ${formatTime(selectedTime[dept])}`;
      
      modal.show();
    }
    
    async function handleFormSubmit(e) {
      e.preventDefault();
      const formData = new FormData(e.target);
      formData.append('book_appointment', '1');
      
      try {
        const response = await fetch('appointment.php', { 
          method: 'POST', 
          body: formData 
        });
        
        const text = await response.text();
        let result;
        
        try {
          result = JSON.parse(text);
        } catch (parseError) {
          console.error('JSON parse error:', parseError);
          throw new Error('Invalid response from server');
        }
        
        bootstrap.Modal.getInstance(document.getElementById('bookingModal'))?.hide();
        
        if (result.success) {
          const deptName = result.appointment.department.charAt(0).toUpperCase() + result.appointment.department.slice(1);
          await Swal.fire({
            icon: 'success',
            title: 'Appointment Confirmed!',
            html: `<p>Your ${deptName} appointment has been booked for ${result.appointment.date}.</p>`,
            confirmButtonColor: currentDepartment === 'dental' ? '#1976d2' : '#388e3c'
          });
          window.location.href = 'student_dashboard.php';
        } else {
          Swal.fire({ 
            icon: 'error', 
            title: 'Booking Failed', 
            text: result.message || 'Unknown error occurred'
          });
        }
      } catch (error) {
        console.error('Submission error:', error);
        Swal.fire({ 
          icon: 'error', 
          title: 'Error', 
          text: error.message || 'An error occurred while booking'
        });
      }
    }
    
    // Clean up on page unload
    window.addEventListener('beforeunload', () => {
      if (pollAbortController) {
        pollAbortController.abort();
      }
    });
  </script>
</body>
</html>
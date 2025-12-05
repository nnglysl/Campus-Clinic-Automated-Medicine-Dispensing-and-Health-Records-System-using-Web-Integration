<?php
$start_time = microtime(true);

require_once '../config/database.php';
session_start();

// Enable output buffering and compression
ob_start();
if (extension_loaded('zlib')) {
    ob_start('ob_gzhandler');
}

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// ✅ Define user info safely
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['fname'] ?? 'Student';
$last_name = $_SESSION['lname'] ?? '';
$user_role = $_SESSION['role'] ?? 'student';
$fullName = trim($first_name . ' ' . $last_name);

// Check if user is logged in and has a valid role
if (!isset($_SESSION['role'])) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();

// Preload doctor availability window
$startDate = date('Y-m-01');
$endDate = date('Y-m-t', strtotime('+2 months'));

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    header('Content-Type: application/json');
    
    try {
        $appointmentType = $_POST['appointment_type'] ?? '';
        $appointmentDate = $_POST['appointment_date'] ?? '';
        $appointmentTime = $_POST['appointment_time'] ?? '';
        $department = $_POST['department'] ?? $appointmentType; // Use appointment_type as fallback for department
        
        if (empty($appointmentType) || empty($appointmentDate) || empty($appointmentTime)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit();
        }
        
        // Ensure department is set
        if (empty($department)) {
            $department = $appointmentType;
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
        
        // Optimized conflict check - use EXISTS for better performance
        $stmt = $pdo->prepare("
            SELECT 1 
            FROM appointments 
            WHERE appointment_date = ? 
            AND appointment_time = ? 
            AND appointment_type = ?
            AND status != 'cancelled'
            LIMIT 1
        ");
        $stmt->execute([$appointmentDate, $appointmentTime, $appointmentType]);
        $conflict = $stmt->fetch();
        
        if ($conflict) {
            echo json_encode(['success' => false, 'message' => 'Time slot already booked.']);
            exit();
        }
        
        // Insert appointment
        $stmt = $pdo->prepare("
            INSERT INTO appointments 
            (patient_id, appointment_date, appointment_time, appointment_type, status, created_at, updated_at) 
            VALUES (?, ?, ?, ?, 'scheduled', NOW(), NOW())
        ");
        $stmt->execute([
            $user_id,
            $appointmentDate,
            $appointmentTime,
            $appointmentType
        ]);
        
        $appointmentId = $pdo->lastInsertId();
        
        // Prepare data for background processing
        $patientName = trim($first_name . ' ' . $last_name);
        $appointmentDateFormatted = date('F j, Y', strtotime($appointmentDate));
        $appointmentTimeFormatted = date('g:i A', strtotime($appointmentTime));
        
        // Return response immediately (< 1 second)
        $responseData = [
            'success' => true, 
            'message' => 'Appointment confirmed! Processing email and calendar sync...',
            'appointment' => [
                'id' => $appointmentId,
                'type' => $appointmentType,
                'department' => $department,
                'date' => $appointmentDate,
                'time' => $appointmentTime
            ]
        ];
        
        // Send response immediately
        echo json_encode($responseData);
        
        // Close connection and continue processing in background
        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } else {
            // Fallback: close connection manually
            if (ob_get_level() > 0) {
                ob_end_flush();
            }
            ignore_user_abort(true);
            header('Connection: close');
            header('Content-Length: ' . strlen(json_encode($responseData)));
            flush();
            if (function_exists('session_write_close')) {
                session_write_close();
            }
        }
        
        // Process email, notifications, and calendar sync in background
        try {
            // Send confirmation email
            require_once(__DIR__ . '/includes/email_helper.php');
            $emailResult = sendAppointmentConfirmationEmail($pdo, $user_id, [
                'id' => $appointmentId,
                'type' => $appointmentType,
                'date' => $appointmentDate,
                'time' => $appointmentTime
            ]);
            
            if ($emailResult['success']) {
                error_log("✓ Background: Email sent for appointment {$appointmentId}");
            } else {
                error_log("✗ Background: Email failed - " . $emailResult['message']);
            }
        } catch (Exception $e) {
            error_log("✗ Background: Email error - " . $e->getMessage());
        }
        
        // Create notifications
        try {
            // Staff notification
            $message = "New {$appointmentType} appointment booked by {$patientName} on {$appointmentDateFormatted} at {$appointmentTimeFormatted}";
            $data = json_encode([
                'appointment_id' => $appointmentId,
                'appointment_type' => $appointmentType,
                'patient_name' => $patientName,
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $stmt = $pdo->prepare("
                INSERT INTO notifications (type, message, data, status, created_at) 
                VALUES ('appointment_booked', ?, ?, 'unread', NOW())
            ");
            $stmt->execute([$message, $data]);
            
            // Student notification
            $deptName = ($appointmentType === 'dental') ? 'Dental Department' : 'Medical Department';
            $deptIcon = ($appointmentType === 'dental') ? '🦷' : '🩺';
            $dayOfWeek = date('l', strtotime($appointmentDate));
            $studentMessage = "{$deptIcon} Your appointment has been confirmed: {$deptName} on {$appointmentDateFormatted} ({$dayOfWeek}) at {$appointmentTimeFormatted}";
            $studentData = json_encode([
                'appointment_id' => $appointmentId,
                'appointment_type' => $appointmentType,
                'patient_name' => $patientName,
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
                'department' => $deptName,
                'formatted_date' => $appointmentDateFormatted,
                'formatted_time' => $appointmentTimeFormatted,
                'day_of_week' => $dayOfWeek,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            
            $stmt = $pdo->prepare("
                INSERT INTO notifications (type, message, data, status, created_at) 
                VALUES ('student_appointment_booked', ?, ?, 'unread', NOW())
            ");
            $stmt->execute([$studentMessage, $studentData]);
            
            error_log("✓ Background: Notifications created for appointment {$appointmentId}");
        } catch (Exception $e) {
            error_log("✗ Background: Notification error - " . $e->getMessage());
        }
        
        // Sync to Google Calendar
        try {
            require_once(__DIR__ . '/../api/calendar_api.php');
            $appointmentData = [
                'appointment_id' => $appointmentId,
                'date' => $appointmentDate,
                'time' => $appointmentTime,
                'type' => $appointmentType,
                'name' => $patientName,
                'patient_id' => $user_id,
                'notes' => ''
            ];
            
            $syncResult = createCalendarEvent($appointmentData);
            
            if ($syncResult && isset($syncResult['success']) && $syncResult['success']) {
                $eventData = $syncResult['data'] ?? $syncResult['raw_response'] ?? [];
                $eventId = $eventData['id'] ?? 'N/A';
                error_log("✓ Background: Calendar synced for appointment {$appointmentId} - Event ID: {$eventId}");
            } else {
                error_log("✗ Background: Calendar sync failed for appointment {$appointmentId}");
            }
        } catch (Exception $e) {
            error_log("✗ Background: Calendar error - " . $e->getMessage());
        }
        
        exit();
        
    } catch (PDOException $e) {
        error_log("Error booking appointment: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        exit();
    }
}

error_log("PHP execution time: " . (microtime(true) - $start_time) . " seconds");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Appointment Reservation - BSU Clinic</title>

  <!-- Preconnect to CDN and API endpoints for faster loading -->
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
  <link rel="prefetch" href="../crud/get_available_slots.php">
  
  <!-- Load CSS normally -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet" />
  <link href="css/nav.css" rel="stylesheet" />
  <link href="css/appointment.css" rel="stylesheet" />
  <link href="../admin/css/notifications.css" rel="stylesheet" />
  <link href="css/responsive.css" rel="stylesheet">
</head>
<body>
   
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
        <a href="../student/student_dashboard.php" class="menu-item">Dashboard</a>
            <a href="../student/profile.php" class="menu-item">Profile</a>
            <a href="../student/appointment.php" class="menu-item active">Appointment</a>
            <a href="../student/records.php" class="menu-item">Health Records</a>
            <a href="../student/settings.php" class="menu-item ">Settings</a>

        <div class="user-profile">
                <div class="avatar"></div>
                <span><?php echo htmlspecialchars($fullName); ?></span>
            </div>
      </div>

      <div class="content-wrapper" style="flex: 1; display: flex; flex-direction: column;">
        <!-- Department Selection View -->
        <div class="department-selection" id="departmentSelection">
          <div class="container-fluid">
            <div class="row">
              <div class="col-12">
          <h2>Book an Appointment</h2>
          <p>Please select the department you'd like to visit</p>
              </div>
            </div>
          
            <div class="row g-4 justify-content-center">
              <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                <button type="button" class="department-btn dental w-100" data-dept="dental" onclick="selectDepartment('dental')">
              <i class="bi bi-heart-pulse-fill"></i>
              <h3>Dental</h3>
              <p>Dental care & treatment</p>
            </button>
              </div>
            
              <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                <button type="button" class="department-btn medical w-100" data-dept="medical" onclick="selectDepartment('medical')">
              <i class="bi bi-hospital-fill"></i>
              <h3>Medical</h3>
              <p>General medical services</p>
            </button>
              </div>
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

  <!-- Booking Modal -->
  <div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
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

  <!-- Scripts at bottom - load in correct order for logout -->
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script>
  <script src="js/notifications.js"></script>
  
  <script>
const API_BASE = '../crud';
let currentDepartment = null;
let currentDate = { dental: new Date(), medical: new Date() };
let selectedDate = { dental: null, medical: null };
let selectedTime = { dental: null, medical: null };
let availabilityCache = { dental: {}, medical: {} };
let timeSlotsCache = { dental: {}, medical: {} };
let lastNotificationId = 0;
let lastSyncTime = { dental: null, medical: null };
let pollAbortController = null;
let isNavigating = false;
let isFetching = { dental: false, medical: false };
let monthCache = { dental: {}, medical: {} };
let renderThrottle = null;
let lastRenderTime = { dental: 0, medical: 0 };
const RENDER_THROTTLE_MS = 100; // Throttle renders to max once per 100ms

document.addEventListener('DOMContentLoaded', () => {
  initializeEventListeners();
});

function initializeEventListeners() {
  // Debounce month navigation for better performance
  let monthNavTimeout = null;
  const handleMonthNav = async (dept, direction, btn) => {
    if (isNavigating) return;
    isNavigating = true;
    btn.disabled = true;
    
    try {
      currentDate[dept].setMonth(currentDate[dept].getMonth() + direction);
      renderCalendar(dept); // Show immediately from cache if available
      await syncAvailability(dept); // Fetch if not cached
      renderCalendar(dept); // Update with fresh data
    } finally {
      btn.disabled = false;
      isNavigating = false;
    }
  };
  
  document.getElementById('prevMonthDental')?.addEventListener('click', async (e) => {
    e.preventDefault();
    if (monthNavTimeout) clearTimeout(monthNavTimeout);
    monthNavTimeout = setTimeout(() => handleMonthNav('dental', -1, e.currentTarget), 150);
  });
  
  document.getElementById('nextMonthDental')?.addEventListener('click', async (e) => {
    e.preventDefault();
    if (monthNavTimeout) clearTimeout(monthNavTimeout);
    monthNavTimeout = setTimeout(() => handleMonthNav('dental', 1, e.currentTarget), 150);
  });
  
  document.getElementById('btnNextDental')?.addEventListener('click', () => handleNextClick('dental'));
  
  document.getElementById('prevMonthMedical')?.addEventListener('click', async (e) => {
    e.preventDefault();
    if (monthNavTimeout) clearTimeout(monthNavTimeout);
    monthNavTimeout = setTimeout(() => handleMonthNav('medical', -1, e.currentTarget), 150);
  });
  
  document.getElementById('nextMonthMedical')?.addEventListener('click', async (e) => {
    e.preventDefault();
    if (monthNavTimeout) clearTimeout(monthNavTimeout);
    monthNavTimeout = setTimeout(() => handleMonthNav('medical', 1, e.currentTarget), 150);
  });
  
  document.getElementById('btnNextMedical')?.addEventListener('click', () => handleNextClick('medical'));
  document.getElementById('appointmentForm')?.addEventListener('submit', handleFormSubmit);
}

function setActiveDepartmentButton(dept) {
  document.querySelectorAll('.department-btn').forEach(btn => {
    const buttonDept = btn.getAttribute('data-dept');
    btn.classList.toggle('active', dept && buttonDept === dept);
  });
}

async function selectDepartment(dept) {
  currentDepartment = dept;
  setActiveDepartmentButton(dept);
  
  document.getElementById('departmentSelection').style.display = 'none';
  document.getElementById('dentalCalendar').classList.remove('active');
  document.getElementById('medicalCalendar').classList.remove('active');
  document.getElementById(dept + 'Calendar').classList.add('active');
  
  // Show calendar immediately (will show as loading/unavailable)
  renderCalendar(dept);
  
  // Load current month first for instant display
  await syncAvailability(dept);
  renderCalendar(dept);
  
  // Then prefetch adjacent months in background (don't wait)
  prefetchMultipleMonths(dept, 2).then(() => {
    // Only re-render if still on same month and department
    if (currentDepartment === dept) {
      renderCalendar(dept);
    }
  });
  
  // Start real-time sync after initial load
  startRealTimeSync(dept);
}

async function prefetchMultipleMonths(dept, monthsToFetch = 2) {
  const promises = [];
  const currentMonth = new Date(currentDate[dept]);
  
  // Prefetch previous and next months (skip current as it's already loaded)
  for (let i = -1; i <= monthsToFetch; i++) {
    if (i === 0) continue; // Skip current month
    
    const targetMonth = new Date(currentMonth);
    targetMonth.setMonth(currentMonth.getMonth() + i);
    
    const monthKey = `${targetMonth.getFullYear()}-${String(targetMonth.getMonth() + 1).padStart(2, '0')}`;
    
    if (!monthCache[dept][monthKey]) {
      promises.push(fetchMonthData(dept, targetMonth, monthKey));
    }
  }
  
  // Run in parallel but don't block
  await Promise.all(promises);
}

async function fetchMonthData(dept, date, monthKey) {
  try {
    const startDate = new Date(date.getFullYear(), date.getMonth(), 1);
    const endDate = new Date(date.getFullYear(), date.getMonth() + 1, 0);
    
    const response = await fetch(
      `${API_BASE}/get_available_slots.php?start_date=${formatDate(startDate)}&end_date=${formatDate(endDate)}&department=${dept}`
    );
    
    if (!response.ok) throw new Error('Fetch failed');
    const result = await response.json();
    
    if (result.success) {
      monthCache[dept][monthKey] = {
        calendar_dates: result.data.calendar_dates,
        time_slots: result.data.time_slots || [],
        last_updated: new Date(result.data.last_updated)
      };
      
      return true;
    }
  } catch (error) {
    console.error('Error fetching month:', error);
    return false;
  }
}

async function syncAvailability(dept) {
  const monthKey = `${currentDate[dept].getFullYear()}-${String(currentDate[dept].getMonth() + 1).padStart(2, '0')}`;
  
  // Check cache first
  if (monthCache[dept][monthKey]) {
    const cached = monthCache[dept][monthKey];
    availabilityCache[dept] = cached.calendar_dates;
    lastSyncTime[dept] = cached.last_updated;
    
    cached.time_slots.forEach(slot => {
      if (!timeSlotsCache[dept][slot.date]) {
        timeSlotsCache[dept][slot.date] = [];
      }
      timeSlotsCache[dept][slot.date].push(slot);
    });
    
    updateLastSyncedTime(dept);
    return;
  }
  
  if (isFetching[dept]) return;
  isFetching[dept] = true;
  
  try {
    await fetchMonthData(dept, currentDate[dept], monthKey);
    
    if (monthCache[dept][monthKey]) {
      const cached = monthCache[dept][monthKey];
      availabilityCache[dept] = cached.calendar_dates;
      lastSyncTime[dept] = cached.last_updated;
      
      cached.time_slots.forEach(slot => {
        if (!timeSlotsCache[dept][slot.date]) {
          timeSlotsCache[dept][slot.date] = [];
        }
        timeSlotsCache[dept][slot.date].push(slot);
      });
      
      updateLastSyncedTime(dept);
    }
    
    prefetchAdjacentMonths(dept);
  } finally {
    isFetching[dept] = false;
  }
}

async function prefetchAdjacentMonths(dept) {
  const current = new Date(currentDate[dept]);
  
  const prevMonth = new Date(current);
  prevMonth.setMonth(current.getMonth() - 1);
  const prevKey = `${prevMonth.getFullYear()}-${String(prevMonth.getMonth() + 1).padStart(2, '0')}`;
  
  const nextMonth = new Date(current);
  nextMonth.setMonth(current.getMonth() + 1);
  const nextKey = `${nextMonth.getFullYear()}-${String(nextMonth.getMonth() + 1).padStart(2, '0')}`;
  
  if (!monthCache[dept][prevKey]) {
    fetchMonthData(dept, prevMonth, prevKey);
  }
  if (!monthCache[dept][nextKey]) {
    fetchMonthData(dept, nextMonth, nextKey);
  }
}

function backToSelection() {
  if (pollAbortController) {
    pollAbortController.abort();
  }
  document.getElementById('dentalCalendar').classList.remove('active');
  document.getElementById('medicalCalendar').classList.remove('active');
  document.getElementById('departmentSelection').style.display = 'flex';
  setActiveDepartmentButton(null);
  currentDepartment = null;
  selectedDate = { dental: null, medical: null };
  selectedTime = { dental: null, medical: null };
}

function startRealTimeSync(dept) {
  if (pollAbortController) {
    pollAbortController.abort();
  }
  startLongPolling(dept);
}

async function startLongPolling(dept) {
  // Start with a longer delay to reduce initial load
  await new Promise(resolve => setTimeout(resolve, 2000));
  
  while (currentDepartment === dept && !document.hidden) {
    try {
      pollAbortController = new AbortController();
      const response = await fetch(
        `${API_BASE}/poll_schedule_changes.php?last_id=${lastNotificationId}&timeout=25&department=${dept}`,
        { 
          signal: pollAbortController.signal,
          cache: 'no-cache'
        }
      );
      
      if (!response.ok) throw new Error('Poll failed');
      const result = await response.json();
      
      if (result.success && result.has_changes) {
        lastNotificationId = result.last_id;
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
          const monthKey = `${currentDate[dept].getFullYear()}-${String(currentDate[dept].getMonth() + 1).padStart(2, '0')}`;
          delete monthCache[dept][monthKey];
          
          // Use requestAnimationFrame for smooth updates
          requestAnimationFrame(async () => {
          await syncAvailability(dept);
          renderCalendar(dept);
          
          if (selectedDate[dept]) {
            renderTimeSlots(selectedDate[dept], dept);
          }
          });
        }
      }
      // Increase polling interval to reduce server load (5 seconds)
      await new Promise(resolve => setTimeout(resolve, 5000));
    } catch (error) {
      if (error.name === 'AbortError') break;
      // Exponential backoff on error
      await new Promise(resolve => setTimeout(resolve, 15000));
    }
  }
}

// Pause polling when tab is hidden to save resources
document.addEventListener('visibilitychange', () => {
  if (document.hidden && pollAbortController) {
    pollAbortController.abort();
  } else if (!document.hidden && currentDepartment) {
    startRealTimeSync(currentDepartment);
  }
});

function updateLastSyncedTime(dept) {
  const el = document.getElementById('lastUpdated' + dept.charAt(0).toUpperCase() + dept.slice(1));
  if (el && lastSyncTime[dept]) {
    el.textContent = `Last synced: ${lastSyncTime[dept].toLocaleTimeString()}`;
  }
}

function renderCalendar(dept) {
  // Throttle renders for better performance
  const now = performance.now();
  if (now - lastRenderTime[dept] < RENDER_THROTTLE_MS) {
    if (renderThrottle) clearTimeout(renderThrottle);
    renderThrottle = setTimeout(() => renderCalendar(dept), RENDER_THROTTLE_MS - (now - lastRenderTime[dept]));
    return;
  }
  lastRenderTime[dept] = now;
  
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
  
  // Use DocumentFragment for better performance (batch DOM updates)
  const fragment = document.createDocumentFragment();
  
  ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'].forEach(day => {
    const el = document.createElement('div');
    el.className = 'calendar-header';
    el.textContent = day;
    fragment.appendChild(el);
  });
  
  const prevMonthLastDay = new Date(year, month, 0).getDate();
  for (let i = startDay - 1; i >= 0; i--) {
    const el = document.createElement('div');
    el.className = 'calendar-day other-month';
    el.textContent = prevMonthLastDay - i;
    fragment.appendChild(el);
  }
  
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
    
    if (date.getTime() === today.getTime()) el.classList.add('today');
    if (selectedDate[dept] && dateISO === selectedDate[dept]) el.classList.add('selected');
    
    const availability = availabilityCache[dept][dateISO];
    
    if (isPast || isWeekend || !availability || availability.status === 'unavailable') {
      el.classList.add('disabled');
    } else {
      el.classList.add(availability.status);
      if (typeof availability.available_slots !== 'undefined') {
        el.title = `${availability.available_slots} of ${availability.total_slots} slots available`;
      } else if (availability.reason) {
        el.title = availability.reason;
      }
      
      if (availability.status === 'fully-booked') {
        el.style.cursor = 'not-allowed';
      } else {
        el.addEventListener('click', () => {
          selectedDate[dept] = dateISO;
          renderCalendar(dept);
          renderTimeSlots(dateISO, dept);
        });
      }
    }
    fragment.appendChild(el);
  }
  
  const totalCells = fragment.children.length - 7;
  const remaining = (Math.ceil(totalCells / 7) * 7) - totalCells;
  for (let i = 1; i <= remaining; i++) {
    const el = document.createElement('div');
    el.className = 'calendar-day other-month';
    el.textContent = i;
    fragment.appendChild(el);
  }
  
  // Replace all at once for better performance
  grid.innerHTML = '';
  grid.appendChild(fragment);
}

async function renderTimeSlots(dateISO, dept) {
  const container = document.getElementById('timeSlotsList' + dept.charAt(0).toUpperCase() + dept.slice(1));
  if (!container) return;
  
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
    timeSlotsCache[dept][dateISO] = slots;
    displayTimeSlots(slots, container, dept);
  } catch (error) {
    container.innerHTML = '<div class="empty-state" style="color: #f44336;"><i class="bi bi-exclamation-triangle"></i><p>Error loading slots</p></div>';
  }
}

function displayTimeSlots(slots, container, dept) {
  if (slots.length === 0) {
    container.innerHTML = '<div class="empty-state"><i class="bi bi-calendar-x"></i><p>No time slots available</p></div>';
    return;
  }
  
  // Use DocumentFragment for better performance
  const fragment = document.createDocumentFragment();
  const btnNextId = 'btnNext' + dept.charAt(0).toUpperCase() + dept.slice(1);
  const btnNext = document.getElementById(btnNextId);
  
  // Sort slots by time for consistent display
  const sortedSlots = [...slots].sort((a, b) => {
    const timeA = a.time.split(':').map(Number);
    const timeB = b.time.split(':').map(Number);
    return (timeA[0] * 60 + timeA[1]) - (timeB[0] * 60 + timeB[1]);
  });
  
  sortedSlots.forEach(slot => {
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
      const radioInput = item.querySelector('input');
      radioInput.addEventListener('change', (e) => {
        if (e.target.checked) {
          selectedTime[dept] = slot.time;
          if (btnNext) btnNext.disabled = false;
        }
      });
    }
    fragment.appendChild(item);
  });
  
  // Replace all at once
  container.innerHTML = '';
  container.appendChild(fragment);
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
  
  const modalElement = document.getElementById('bookingModal');
  const modalDialog = modalElement.querySelector('.modal-dialog');
  
  // Ensure modal dialog has centered class for proper positioning
  if (modalDialog && !modalDialog.classList.contains('modal-dialog-centered')) {
    modalDialog.classList.add('modal-dialog-centered');
  }
  
  const modal = new bootstrap.Modal(modalElement);
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
  
  // Hide menu overlay when modal is shown (non-blocking)
  requestAnimationFrame(() => {
    const menuOverlay = document.querySelector('.menu-overlay');
    if (menuOverlay) {
      menuOverlay.style.display = 'none';
      menuOverlay.style.pointerEvents = 'none';
    }
    
    // Close mobile menu if open
    const sidebar = document.querySelector('.sidebar');
    if (sidebar && sidebar.classList.contains('active')) {
      sidebar.classList.remove('active');
      document.body.classList.remove('menu-open');
      const mobileMenuBtn = document.getElementById('mobileMenuBtn');
      if (mobileMenuBtn) {
        mobileMenuBtn.setAttribute('aria-expanded', 'false');
        const icon = mobileMenuBtn.querySelector('i');
        if (icon) {
          icon.classList.remove('bi-x-lg');
          icon.classList.add('bi-list');
        }
      }
    }
  });
  
  // Show modal with smooth transition
  modal.show();
  
  // Force re-center after modal is shown (in case CSS didn't apply)
  setTimeout(() => {
    if (modalDialog) {
      modalDialog.style.position = 'fixed';
      modalDialog.style.top = '50%';
      modalDialog.style.left = '50%';
      modalDialog.style.transform = 'translate(-50%, -50%)';
      modalDialog.style.margin = '0';
    }
  }, 10);
}

async function handleFormSubmit(e) {
  e.preventDefault();
  
  // Disable form submission button to prevent double submission
  const submitButton = e.target.querySelector('button[type="submit"]');
  let originalButtonText = '';
  if (submitButton) {
    originalButtonText = submitButton.textContent;
    submitButton.disabled = true;
    submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
  }
  
  const formData = new FormData(e.target);
  formData.append('book_appointment', '1');
  
  // Get appointment details for optimistic update
  const appointmentDate = formData.get('appointment_date');
  const appointmentTime = formData.get('appointment_time');
  const appointmentType = formData.get('appointment_type');
  
  // Hide modal immediately (non-blocking) using requestAnimationFrame for smooth transition
  const modalElement = document.getElementById('bookingModal');
  const modalInstance = bootstrap.Modal.getInstance(modalElement);
  
  // Use requestAnimationFrame to hide modal smoothly without blocking
  requestAnimationFrame(() => {
    if (modalInstance) {
      modalInstance.hide();
    }
    // Remove backdrop immediately to prevent darkening
    setTimeout(() => {
      const backdrop = document.querySelector('.modal-backdrop');
      if (backdrop) {
        backdrop.remove();
      }
      document.body.classList.remove('modal-open');
      document.body.style.overflow = '';
      document.body.style.paddingRight = '';
    }, 150); // Small delay to allow modal close animation
  });
  
  // OPTIMISTIC UI UPDATE: Immediately update cache and UI
  const bookingPromise = (async () => {
    try {
      const response = await fetch('appointment.php', { 
        method: 'POST', 
        body: formData,
        headers: {
          'Cache-Control': 'no-cache'
        }
      });
      
      const text = await response.text();
      let result;
      
      try {
        result = JSON.parse(text);
      } catch (parseError) {
        console.error('Failed to parse JSON response:', text);
        console.error('Parse error:', parseError);
        throw new Error('Invalid response from server. Please check the console for details.');
      }
      
      return result;
    } catch (error) {
      throw error;
    }
  })();
      
  // Update cache optimistically - mark slot as booked (non-blocking)
  requestAnimationFrame(() => {
    if (timeSlotsCache[currentDepartment] && timeSlotsCache[currentDepartment][appointmentDate]) {
      timeSlotsCache[currentDepartment][appointmentDate] = timeSlotsCache[currentDepartment][appointmentDate].map(slot => {
        if (slot.time === appointmentTime) {
          return { ...slot, available: false };
        }
        return slot;
      });
    }
    
    // Invalidate month cache to force refresh on next view
    const monthKey = `${new Date(appointmentDate).getFullYear()}-${String(new Date(appointmentDate).getMonth() + 1).padStart(2, '0')}`;
    delete monthCache[currentDepartment][monthKey];
    
    // Update availability cache
    if (availabilityCache[currentDepartment][appointmentDate]) {
      const dateInfo = availabilityCache[currentDepartment][appointmentDate];
      if (dateInfo.available_slots > 0) {
        dateInfo.available_slots--;
        dateInfo.booked_slots++;
        if (dateInfo.available_slots === 0) {
          dateInfo.status = 'fully-booked';
        } else {
          dateInfo.status = 'partially-available';
        }
      }
    }
    
    // Re-render calendar with updated data (non-blocking)
    renderCalendar(currentDepartment);
    if (selectedDate[currentDepartment]) {
      renderTimeSlots(selectedDate[currentDepartment], currentDepartment);
    }
  });
  
  // Stop polling to save resources
  if (pollAbortController) {
    pollAbortController.abort();
    pollAbortController = null;
  }
  
  // Preload dashboard page in background for instant redirect
  const dashboardLink = document.createElement('link');
  dashboardLink.rel = 'prefetch';
  dashboardLink.href = 'student_dashboard.php';
  document.head.appendChild(dashboardLink);
  
  // Prepare success message (will be shown after confirmation)
  const deptName = appointmentType.charAt(0).toUpperCase() + appointmentType.slice(1);
  const [year, month, day] = appointmentDate.split('-').map(Number);
  const dateObj = new Date(year, month - 1, day);
  const formattedDate = dateObj.toLocaleDateString('en-US', { 
    weekday: 'long', 
    year: 'numeric', 
    month: 'long', 
    day: 'numeric' 
  });
  const formattedTime = formatTime(appointmentTime);
  
  // Process booking in background without blocking UI
  try {
    const result = await bookingPromise;
    
    // Only show confirmation if appointment was successfully saved
    if (result.success) {
      const message = `
        <div style="text-align: left; padding: 10px 0;">
          <div style="background-color: #f8f9fa; border-radius: 8px; padding: 20px; margin-bottom: 15px; border-left: 4px solid #28a745;">
            <h6 style="margin: 0 0 15px 0; color: #212529; font-weight: 600; font-size: 1.1em;">
              <i class="bi bi-calendar-check-fill" style="color: #28a745; margin-right: 8px;"></i>
              Appointment Successfully Booked!
            </h6>
            <div style="color: #495057; line-height: 1.8;">
              <p style="margin: 8px 0; font-size: 0.95em;">
                <strong>Department:</strong> ${deptName}
              </p>
              <p style="margin: 8px 0; font-size: 0.95em;">
                <strong>Date:</strong> ${formattedDate}
              </p>
              <p style="margin: 8px 0; font-size: 0.95em;">
                <strong>Time:</strong> ${formattedTime}
              </p>
              <p style="margin: 8px 0; font-size: 0.95em;">
                <strong>Appointment ID:</strong> #${result.appointment.id}
              </p>
            </div>
          </div>
        </div>
      `;
      
      // Show confirmation popup with optimized timing (non-blocking)
      // Use requestIdleCallback if available for better performance
      const showSuccess = () => {
        Swal.fire({
          icon: 'success',
          title: 'Appointment Confirmed!',
          html: message,
          confirmButtonText: 'Go to Dashboard',
          confirmButtonColor: currentDepartment === 'dental' ? '#1976d2' : '#388e3c',
          allowOutsideClick: false,
          allowEscapeKey: false,
          position: 'center',
          backdrop: true,
          customClass: {
            popup: 'swal2-popup-centered',
            container: 'swal2-container-centered'
          },
          showClass: {
            popup: 'animate__animated animate__fadeInDown'
          },
          hideClass: {
            popup: 'animate__animated animate__fadeOutUp'
          },
          timer: 3000,
          timerProgressBar: true
        }).then(() => {
          // Use replace instead of href for faster navigation (no history entry)
          window.location.replace('student_dashboard.php');
        });
      };
      
      // Use requestIdleCallback for non-blocking display
      if (window.requestIdleCallback) {
        requestIdleCallback(showSuccess, { timeout: 500 });
      } else {
        setTimeout(showSuccess, 100);
      }
    } else {
      // Revert optimistic update on failure
      if (timeSlotsCache[currentDepartment] && timeSlotsCache[currentDepartment][appointmentDate]) {
        timeSlotsCache[currentDepartment][appointmentDate] = timeSlotsCache[currentDepartment][appointmentDate].map(slot => {
          if (slot.time === appointmentTime) {
            return { ...slot, available: true };
          }
          return slot;
        });
      }
      
      // Re-enable button and show error
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = originalButtonText;
      }
      
      Swal.fire({ 
        icon: 'error', 
        title: 'Booking Failed', 
        text: result.message || 'Unknown error occurred',
        confirmButtonColor: '#dc3545'
      });
    }
  } catch (error) {
    // Revert optimistic update on error
    if (timeSlotsCache[currentDepartment] && timeSlotsCache[currentDepartment][appointmentDate]) {
      timeSlotsCache[currentDepartment][appointmentDate] = timeSlotsCache[currentDepartment][appointmentDate].map(slot => {
        if (slot.time === appointmentTime) {
          return { ...slot, available: true };
        }
        return slot;
      });
    }
    
    // Re-enable button on error
    if (submitButton) {
      submitButton.disabled = false;
      submitButton.textContent = originalButtonText;
    }
    
    Swal.fire({ 
      icon: 'error', 
      title: 'Error', 
      text: error.message || 'An error occurred while booking',
      confirmButtonColor: '#dc3545'
    });
  }
}

window.addEventListener('beforeunload', () => {
  if (pollAbortController) {
    pollAbortController.abort();
  }
});

// Initialize notification system
if (window.StudentNotificationSystem) {
  StudentNotificationSystem.init();
}

// Mobile Menu Toggle
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
      if (isActive) {
        overlay.style.display = 'block';
        overlay.style.pointerEvents = 'auto';
      } else {
        overlay.style.display = 'none';
        overlay.style.pointerEvents = 'none';
      }
      
      // Update aria-expanded for accessibility
      mobileMenuBtn.setAttribute('aria-expanded', isActive ? 'true' : 'false');
      
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
      overlay.style.pointerEvents = 'none';
      mobileMenuBtn.setAttribute('aria-expanded', 'false');
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
    
    // Hide overlay when modal is shown
    const bookingModal = document.getElementById('bookingModal');
    if (bookingModal) {
      bookingModal.addEventListener('show.bs.modal', () => {
        overlay.style.display = 'none';
        overlay.style.pointerEvents = 'none';
        // Close mobile menu if open
        if (sidebar.classList.contains('active')) {
          sidebar.classList.remove('active');
          body.classList.remove('menu-open');
          mobileMenuBtn.setAttribute('aria-expanded', 'false');
          const icon = mobileMenuBtn.querySelector('i');
          if (icon) {
            icon.classList.remove('bi-x-lg');
            icon.classList.add('bi-list');
          }
        }
      });
      
      // Re-enable overlay when modal is hidden (if menu was open)
      bookingModal.addEventListener('hidden.bs.modal', () => {
        // Remove backdrop immediately to prevent darkening (non-blocking)
        requestAnimationFrame(() => {
          const backdrop = document.querySelector('.modal-backdrop');
          if (backdrop) {
            backdrop.remove();
          }
          document.body.classList.remove('modal-open');
          document.body.style.overflow = '';
          document.body.style.paddingRight = '';
        });
        
        // Only show overlay if menu is still active
        if (sidebar.classList.contains('active')) {
          overlay.style.display = 'block';
          overlay.style.pointerEvents = 'auto';
        }
      });
    }
  }
});

  </script>
</body>
</html>
<?php
// Flush output buffer at the end
ob_end_flush();
?>
?>
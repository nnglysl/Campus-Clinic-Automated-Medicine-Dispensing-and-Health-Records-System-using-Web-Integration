<?php
require_once '../config/database.php';
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Handle appointment booking
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    header('Content-Type: application/json');
    
    try {
        $appointmentType = $_POST['appointment_type'] ?? '';
        $appointmentDate = $_POST['appointment_date'] ?? '';
        $appointmentTime = $_POST['appointment_time'] ?? '';
        
        // Validation
        if (empty($appointmentType) || empty($appointmentDate) || empty($appointmentTime)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit();
        }
        
        if (strtotime($appointmentDate) < strtotime(date('Y-m-d'))) {
            echo json_encode(['success' => false, 'message' => 'Cannot book appointments in the past.']);
            exit();
        }
        
        $dayOfWeek = date('w', strtotime($appointmentDate));
        if ($dayOfWeek == 0 || $dayOfWeek == 6) {
            echo json_encode(['success' => false, 'message' => 'Appointments are not available on weekends.']);
            exit();
        }
        
        // Check for conflicts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM appointments 
            WHERE appointment_date = ? 
            AND appointment_time = ? 
            AND status != 'cancelled'
        ");
        $stmt->execute([$appointmentDate, $appointmentTime]);
        $conflict = $stmt->fetch();
        
        if ($conflict['count'] >= 20) {
            echo json_encode(['success' => false, 'message' => 'That time slot is fully booked.']);
            exit();
        }
        
        // Insert appointment
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
        
        // Log appointment creation
        try {
            require_once(__DIR__ . '/../includes/appointment_logger.php');
            $appointmentData = [
                'appointment_date' => $appointmentDate,
                'appointment_time' => $appointmentTime,
                'appointment_type' => $appointmentType,
                'fname' => $user['fname'],
                'lname' => $user['lname']
            ];
            logAppointmentCreated($pdo, $appointmentId, $user['id'], $appointmentData);
        } catch (Exception $e) {
            error_log("Failed to log appointment creation: " . $e->getMessage());
            // Continue even if logging fails
        }
        
        // Create notification for medical/dental staff
        try {
            $patientName = trim($user['fname'] . ' ' . $user['lname']);
            $appointmentDateFormatted = date('F j, Y', strtotime($appointmentDate));
            $appointmentTimeFormatted = date('g:i A', strtotime($appointmentTime));
            
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
        } catch (Exception $e) {
            error_log("Failed to create notification: " . $e->getMessage());
        }
        
        // Try to sync to Google Calendar (optional, won't fail if it doesn't work)
        $calendarSynced = false;
        try {
            require_once(__DIR__ . '/../api/calendar_api.php');
            
            // Get appointment details for calendar sync
            $stmt = $pdo->prepare("
                SELECT 
                    a.*,
                    u.fname,
                    u.lname
                FROM appointments a
                JOIN users u ON a.patient_id = u.id
                WHERE a.id = ?
            ");
            $stmt->execute([$appointmentId]);
            $appointment = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($appointment) {
                $appointmentData = [
                    'appointment_id' => $appointmentId,
                    'date' => $appointment['appointment_date'],
                    'time' => $appointment['appointment_time'],
                    'type' => $appointment['appointment_type'],
                    'name' => trim(($appointment['fname'] ?? '') . ' ' . ($appointment['lname'] ?? '')),
                    'patient_id' => $appointment['patient_id'],
                    'notes' => $appointment['notes'] ?? ''
                ];
                
                $syncResult = createCalendarEvent($appointmentData);
                if ($syncResult['success']) {
                    $calendarSynced = true;
                    error_log("Appointment {$appointmentId} synced to Google Calendar successfully");
                } else {
                    error_log("Failed to sync appointment {$appointmentId} to Google Calendar: " . ($syncResult['error'] ?? 'Unknown error'));
                }
            }
        } catch (Exception $e) {
            error_log("Calendar sync failed for appointment {$appointmentId}: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Appointment confirmed!' . ($calendarSynced ? ' & synced to Google Calendar' : ''),
            'calendar_synced' => $calendarSynced,
            'appointment' => [
                'id' => $appointmentId,
                'type' => $appointmentType,
                'date' => $appointmentDate,
                'time' => $appointmentTime
            ]
        ]);
        exit();
        
    } catch (PDOException $e) {
        error_log("Error booking appointment: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        exit();
    } catch (Exception $e) {
        error_log("General error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        exit();
    }
}

// Fetch booked slots per date for calendar display
$bookedSlots = [];
try {
    $stmt = $pdo->query("
        SELECT appointment_date, appointment_time, COUNT(*) as count 
        FROM appointments 
        WHERE status != 'cancelled' AND appointment_date >= CURDATE()
        GROUP BY appointment_date, appointment_time
    ");
    $result = $stmt->fetchAll();
    foreach ($result as $row) {
        $date = $row['appointment_date'];
        if (!isset($bookedSlots[$date])) {
            $bookedSlots[$date] = [];
        }
        $bookedSlots[$date][$row['appointment_time']] = $row['count'];
    }
} catch (PDOException $e) {
    error_log("Error fetching booked slots: " . $e->getMessage());
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
  
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background-color: #f5f5f5;
    }

    .content-wrapper {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .appointment-container {
      flex: 1;
      display: flex;
      background: white;
      margin: 20px;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
      overflow: hidden;
    }

    .date-section {
      width: 45%;
      padding: 30px;
      border-right: 1px solid #e0e0e0;
      display: flex;
      flex-direction: column;
    }

    .date-section h3 {
      font-size: 1.5rem;
      font-weight: 600;
      color: #333;
      margin-bottom: 10px;
    }

    .date-section .subtitle {
      color: #8b0000;
      font-size: 0.9rem;
      margin-bottom: 30px;
    }

    .calendar-controls {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .calendar-controls button {
      background: transparent;
      border: none;
      font-size: 1.2rem;
      cursor: pointer;
      color: #666;
      padding: 5px 10px;
    }

    .calendar-controls button:hover {
      color: #333;
    }

    .calendar-controls h4 {
      margin: 0;
      font-size: 1.1rem;
      font-weight: 600;
      color: #333;
    }

    .calendar-grid {
      display: grid;
      grid-template-columns: repeat(7, 1fr);
      gap: 8px;
      margin-bottom: 30px;
    }

    .calendar-header {
      text-align: center;
      font-weight: 600;
      font-size: 0.85rem;
      color: #666;
      padding: 10px 0;
    }

    .calendar-day {
      aspect-ratio: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      border-radius: 4px;
      cursor: pointer;
      font-size: 0.9rem;
      color: #333;
      background: white;
      border: 1px solid #e0e0e0;
      transition: all 0.2s;
    }

    .calendar-day:hover:not(.disabled):not(.other-month) {
      border-color: #6b7bd6;
      background: #f0f2ff;
    }

    .calendar-day.selected {
      background: #6b7bd6;
      color: white;
      border-color: #6b7bd6;
      font-weight: 600;
    }

    .calendar-day.today {
      background: #e8f0fe;
      border-color: #1a73e8;
    }

    .calendar-day.other-month {
      color: #ccc;
      cursor: default;
    }

    .calendar-day.disabled {
      color: #ccc;
      background: #f9f9f9;
      cursor: not-allowed;
    }

    .calendar-day.available {
      background: #e8f5e9;
      border-color: #4caf50;
    }

    .calendar-day.fully-booked {
      background: #ffebee;
      border-color: #f44336;
    }

    .legend {
      display: flex;
      gap: 20px;
      margin-top: 20px;
    }

    .legend-item {
      display: flex;
      align-items: center;
      gap: 8px;
      font-size: 0.85rem;
    }

    .legend-box {
      width: 20px;
      height: 20px;
      border-radius: 4px;
      border: 1px solid;
    }

    .legend-available {
      background: #e8f5e9;
      border-color: #4caf50;
    }

    .legend-fully-booked {
      background: #ffebee;
      border-color: #f44336;
    }

    .time-section {
      width: 55%;
      padding: 30px;
      display: flex;
      flex-direction: column;
    }

    .time-section h3 {
      font-size: 1.5rem;
      font-weight: 600;
      color: #333;
      margin-bottom: 30px;
    }

    .time-slots-list {
      flex: 1;
      overflow-y: auto;
      padding-right: 10px;
    }

    .time-slot-item {
      display: flex;
      align-items: center;
      padding: 15px;
      margin-bottom: 12px;
      border: 1px solid #e0e0e0;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s;
      background: white;
    }

    .time-slot-item:hover:not(.disabled) {
      border-color: #6b7bd6;
      background: #f8f9ff;
      transform: translateX(5px);
    }

    .time-slot-item.disabled {
      opacity: 0.5;
      cursor: not-allowed;
      background: #f5f5f5;
    }

    .time-slot-item input[type="radio"] {
      margin-right: 15px;
      width: 18px;
      height: 18px;
      cursor: pointer;
    }

    .time-slot-item.disabled input[type="radio"] {
      cursor: not-allowed;
    }

    .time-slot-info {
      flex: 1;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .time-slot-time {
      font-weight: 600;
      color: #333;
      font-size: 1rem;
    }

    .time-slot-status {
      font-size: 0.85rem;
      padding: 4px 12px;
      border-radius: 12px;
      font-weight: 500;
    }

    .status-available {
      background: #e8f5e9;
      color: #2e7d32;
    }

    .status-limited {
      background: #fff3e0;
      color: #e65100;
    }

    .status-fully-booked {
      background: #ffebee;
      color: #c62828;
    }

    .action-buttons {
      display: flex;
      gap: 15px;
      margin-top: 30px;
      padding-top: 20px;
      border-top: 1px solid #e0e0e0;
    }

    .btn-back, .btn-next {
      flex: 1;
      padding: 12px 24px;
      border: none;
      border-radius: 6px;
      font-size: 1rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-back {
      background: #e0e0e0;
      color: #333;
    }

    .btn-back:hover {
      background: #d0d0d0;
    }

    .btn-next {
      background: #6b7bd6;
      color: white;
    }

    .btn-next:hover {
      background: #5a6bc5;
    }

    .btn-next:disabled {
      background: #ccc;
      cursor: not-allowed;
    }

    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: #999;
    }

    .empty-state i {
      font-size: 3rem;
      margin-bottom: 20px;
      color: #ddd;
    }

    @media (max-width: 968px) {
      .appointment-container {
        flex-direction: column;
      }

      .date-section, .time-section {
        width: 100%;
        border-right: none;
      }

      .date-section {
        border-bottom: 1px solid #e0e0e0;
      }
    }
  </style>
</head>
<body>
  <div class="layout">
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

    <div class="main-container d-flex">
      <div class="sidebar">
        <a href="student_dashboard.php" class="menu-item">Dashboard</a>
        <a href="profile.php" class="menu-item">Profile</a>
        <a href="appointment.php" class="menu-item active">Appointment</a>
        <a href="records.php" class="menu-item">Health Records</a>
        <a href="settings.php" class="menu-item">Settings</a>
      </div>

      <div class="content-wrapper">
        <div class="appointment-container">
          <div class="date-section">
            <h3>Date</h3>
            <p class="subtitle">To the extent possible, additional slots are made regularly.</p>

            <div class="calendar-controls">
              <button id="prevMonth"><i class="bi bi-chevron-left"></i></button>
              <h4 id="monthYear">November 2025</h4>
              <button id="nextMonth"><i class="bi bi-chevron-right"></i></button>
            </div>

            <div class="calendar-grid" id="calendarGrid"></div>

            <div class="legend">
              <div class="legend-item">
                <div class="legend-box legend-available"></div>
                <span>Available</span>
              </div>
              <div class="legend-item">
                <div class="legend-box legend-fully-booked"></div>
                <span>Fully Booked</span>
              </div>
            </div>
          </div>

          <div class="time-section">
            <h3>Time</h3>

            <div class="time-slots-list" id="timeSlotsList">
              <div class="empty-state">
                <i class="bi bi-calendar-week"></i>
                <p>Select a date to view available time slots</p>
              </div>
            </div>

            <div class="action-buttons">
              <button class="btn-back" onclick="window.location.href='student_dashboard.php'">BACK</button>
              <button class="btn-next" id="btnNext" disabled>NEXT</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

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
              <h6 class="fw-semibold text-primary"><span id="selectedDateDisplay"></span></h6>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Appointment Type</label>
              <select id="appointmentType" name="appointment_type" class="form-select" required>
                <option value="">-- Select Type --</option>
                <option value="dental">Dental</option>
                <option value="medical">Medical</option>
              </select>
            </div>

            <input type="hidden" id="appointmentDate" name="appointment_date">
            <input type="hidden" id="appointmentTime" name="appointment_time">
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary">Confirm</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
    window.bookedSlots = <?php echo json_encode($bookedSlots); ?>;
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const DOCTOR_AVAILABLE_DAYS = [1, 2, 3]; // Monday, Tuesday, Wednesday
    const MAX_SLOTS_PER_TIME = 20;
    const TIME_SLOTS = [
      '07:00:00', '07:30:00',
      '08:00:00', '08:30:00',
      '09:00:00', '09:30:00',
      '10:00:00', '10:30:00',
      '11:00:00', '11:30:00',
      '12:00:00', '12:30:00',
      '13:00:00', '13:30:00',
      '14:00:00', '14:30:00',
      '15:00:00', '15:30:00',
      '16:00:00', '16:30:00',
      '17:00:00', '17:30:00',
      '18:00:00'
    ];

    let currentDate = new Date();
    let selectedDate = null;
    let selectedTime = null;
    let bookedSlots = window.bookedSlots || {};

    console.log('Initial booked slots:', bookedSlots);

    document.addEventListener('DOMContentLoaded', () => {
      initializeEventListeners();
      renderCalendar();
    });

    function initializeEventListeners() {
      document.getElementById('prevMonth')?.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() - 1);
        renderCalendar();
      });

      document.getElementById('nextMonth')?.addEventListener('click', () => {
        currentDate.setMonth(currentDate.getMonth() + 1);
        renderCalendar();
      });

      document.getElementById('btnNext')?.addEventListener('click', handleNextClick);
      document.getElementById('appointmentForm')?.addEventListener('submit', handleFormSubmit);
    }

    function renderCalendar() {
      const grid = document.getElementById('calendarGrid');
      if (!grid) return;

      const year = currentDate.getFullYear();
      const month = currentDate.getMonth();
      const firstDay = new Date(year, month, 1);
      const lastDay = new Date(year, month + 1, 0);
      const daysInMonth = lastDay.getDate();
      const startDay = firstDay.getDay();
      const today = new Date();
      today.setHours(0, 0, 0, 0);

      document.getElementById('monthYear').textContent = 
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
        
        const dateISO = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        const dayOfWeek = date.getDay();

        const el = document.createElement('div');
        el.className = 'calendar-day';
        el.textContent = day;

        const isPast = date < today;
        const isWeekend = dayOfWeek === 0 || dayOfWeek === 6;
        const isAvailable = DOCTOR_AVAILABLE_DAYS.includes(dayOfWeek);

        if (date.getTime() === today.getTime()) el.classList.add('today');
        if (selectedDate && dateISO === selectedDate) el.classList.add('selected');

        if (isPast || isWeekend || !isAvailable) {
          el.classList.add('disabled');
        } else {
          const slotsBooked = getBookedCountForDate(dateISO);
          const totalSlots = TIME_SLOTS.length * MAX_SLOTS_PER_TIME;
          
          if (slotsBooked >= totalSlots) {
            el.classList.add('fully-booked');
          } else if (slotsBooked > 0) {
            el.classList.add('available');
          }

          el.addEventListener('click', () => {
            selectedDate = dateISO;
            console.log('Selected date:', selectedDate);
            renderCalendar();
            renderTimeSlots(dateISO);
          });
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

    function getBookedCountForDate(dateISO) {
      if (!bookedSlots[dateISO]) return 0;
      return Object.values(bookedSlots[dateISO]).reduce((sum, count) => sum + count, 0);
    }

    function renderTimeSlots(dateISO) {
      const container = document.getElementById('timeSlotsList');
      if (!container) return;

      container.innerHTML = '';

      TIME_SLOTS.forEach(time => {
        const bookedCount = (bookedSlots[dateISO] && bookedSlots[dateISO][time]) || 0;
        const availableSlots = MAX_SLOTS_PER_TIME - bookedCount;
        const isFullyBooked = availableSlots <= 0;

        const item = document.createElement('div');
        item.className = 'time-slot-item';
        if (isFullyBooked) item.classList.add('disabled');

        const displayTime = formatTime(time);
        let statusClass = 'status-available';
        let statusText = `${availableSlots} available`;

        if (isFullyBooked) {
          statusClass = 'status-fully-booked';
          statusText = 'Fully Booked';
        } else if (availableSlots <= 5) {
          statusClass = 'status-limited';
          statusText = `Only ${availableSlots} left`;
        }

        item.innerHTML = `
          <input type="radio" name="timeSlot" value="${time}" ${isFullyBooked ? 'disabled' : ''}>
          <div class="time-slot-info">
            <span class="time-slot-time">${displayTime}</span>
            <span class="time-slot-status ${statusClass}">${statusText}</span>
          </div>
        `;

        if (!isFullyBooked) {
          item.querySelector('input').addEventListener('change', (e) => {
            if (e.target.checked) {
              selectedTime = time;
              updateNextButton();
            }
          });
        }

        container.appendChild(item);
      });
    }

    function formatTime(time24) {
      const parts = time24.split(':');
      const hour = parseInt(parts[0]);
      const minutes = parts[1] || '00';
      const ampm = hour >= 12 ? 'PM' : 'AM';
      const hour12 = hour % 12 || 12;
      return `${String(hour12).padStart(2, '0')}:${minutes} ${ampm}`;
    }

    function updateNextButton() {
      const btn = document.getElementById('btnNext');
      if (btn) {
        btn.disabled = !selectedDate || !selectedTime;
      }
    }

    function handleNextClick() {
      if (!selectedDate || !selectedTime) return;

      const modal = new bootstrap.Modal(document.getElementById('bookingModal'));
      document.getElementById('appointmentForm')?.reset();

      document.getElementById('appointmentDate').value = selectedDate;
      document.getElementById('appointmentTime').value = selectedTime;

      const [year, month, day] = selectedDate.split('-').map(Number);
      const dateObj = new Date(year, month - 1, day);
      
      const formatted = dateObj.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
      });
      
      document.getElementById('selectedDateDisplay').textContent = 
        `${formatted} at ${formatTime(selectedTime)}`;

      modal.show();
    }

    async function handleFormSubmit(e) {
      e.preventDefault();
      const formData = new FormData(e.target);
      formData.append('book_appointment', '1');

      if (!formData.get('appointment_type')) {
        Swal.fire({ 
          icon: 'warning', 
          title: 'Select Type', 
          text: 'Please select appointment type' 
        });
        return;
      }

      console.log('Submitting form with data:', {
        date: formData.get('appointment_date'),
        time: formData.get('appointment_time'),
        type: formData.get('appointment_type')
      });

      try {
        const response = await fetch('appointment.php', { 
          method: 'POST', 
          body: formData 
        });
        
        const text = await response.text();
        console.log('Raw response:', text);
        
        let result;
        try {
          result = JSON.parse(text);
        } catch (parseError) {
          console.error('JSON parse error:', parseError);
          console.error('Response text:', text);
          throw new Error('Invalid response from server');
        }

        bootstrap.Modal.getInstance(document.getElementById('bookingModal'))?.hide();

        if (result.success) {
          await Swal.fire({
            icon: 'success',
            title: 'Appointment Confirmed!',
            html: `<p>Your ${result.appointment.type} appointment has been booked for ${result.appointment.date}.</p>`,
            confirmButtonColor: '#6b7bd6'
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
  </script>
</body>
</html>
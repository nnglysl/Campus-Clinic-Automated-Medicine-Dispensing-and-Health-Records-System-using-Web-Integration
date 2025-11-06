<?php
require_once '../config/database.php';
session_start();

// Enable error reporting for debugging (remove in production)
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

// Handle appointment booking - NOW USES CENTRALIZED HANDLER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['book_appointment'])) {
    header('Content-Type: application/json');
    
    try {
        $appointmentType = $_POST['appointment_type'] ?? '';
        $appointmentDate = $_POST['appointment_date'] ?? '';
        $appointmentTime = $_POST['appointment_time'] ?? '';
        
        // Validate inputs
        if (empty($appointmentType) || empty($appointmentDate) || empty($appointmentTime)) {
            echo json_encode(['success' => false, 'message' => 'All fields are required.']);
            exit();
        }
        
        // Check if date is not in the past
        if (strtotime($appointmentDate) < strtotime(date('Y-m-d'))) {
            echo json_encode(['success' => false, 'message' => 'Cannot book appointments in the past.']);
            exit();
        }
        
        // Check if date is weekend
        $dayOfWeek = date('w', strtotime($appointmentDate));
        if ($dayOfWeek == 0 || $dayOfWeek == 6) {
            echo json_encode(['success' => false, 'message' => 'Appointments are not available on weekends.']);
            exit();
        }
        
        // Check for time conflicts
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count 
            FROM appointments 
            WHERE appointment_date = ? 
            AND appointment_time = ? 
            AND status != 'cancelled'
        ");
        $stmt->execute([$appointmentDate, $appointmentTime]);
        $conflict = $stmt->fetch();
        
        if ($conflict['count'] > 0) {
            echo json_encode(['success' => false, 'message' => 'That time slot has already been reserved.']);
            exit();
        }
        
        // Insert appointment with scheduled status
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
        
        // Now sync to Google Calendar using the centralized handler
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => '../crud/appointment_handler.php?action=sync&id=' . $appointmentId,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_COOKIE => session_name() . '=' . session_id(),
            CURLOPT_TIMEOUT => 30
        ]);
        
        $calendarResponse = curl_exec($ch);
        $calendarResult = json_decode($calendarResponse, true);
        curl_close($ch);
        
        // Log calendar sync result (optional)
        if ($calendarResult && $calendarResult['success']) {
            error_log("Appointment {$appointmentId} synced to calendar: {$calendarResult['event_id']}");
        } else {
            error_log("Calendar sync failed for appointment {$appointmentId}: " . 
                     ($calendarResult['error'] ?? 'Unknown error'));
        }
        
        echo json_encode([
            'success' => true, 
            'message' => 'Appointment confirmed and synced to calendar!',
            'appointment' => [
                'id' => $appointmentId,
                'type' => $appointmentType,
                'date' => $appointmentDate,
                'time' => $appointmentTime
            ],
            'calendar_synced' => $calendarResult['success'] ?? false
        ]);
        exit();
        
    } catch (PDOException $e) {
        error_log("Error booking appointment: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
        exit();
    }
}

// Fetch all booked appointments for calendar display
$bookedAppointments = [];
try {
    $stmt = $pdo->query("
        SELECT appointment_date, appointment_time, appointment_type 
        FROM appointments 
        WHERE status != 'cancelled'
        ORDER BY appointment_date, appointment_time
    ");
    $bookedAppointments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
}

// Fetch user's appointments
$myAppointments = [];
try {
    $stmt = $pdo->prepare("
        SELECT * FROM appointments 
        WHERE patient_id = ?
        ORDER BY appointment_date DESC, appointment_time DESC
        LIMIT 10
    ");
    $stmt->execute([$user['id']]);
    $myAppointments = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching user appointments: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Appointment Reservation</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet" />

  <!-- SweetAlert2 -->
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <!-- External CSS -->
  <link href="../student/css/nav.css" rel="stylesheet" />
  <link rel="stylesheet" href="../student/css/appointment.css" />
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
      <!-- Right-side icons -->
      <div class="header-icons">
        <div class="notification-icon"><i class="bi bi-bell-fill"></i></div>
        <div class="logout-icon" onclick="window.location.href='../logout.php'"><i class="bi bi-box-arrow-right"></i></div>
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
        <?php if (!empty($myAppointments)): ?>
        <div class="mt-4">
          <h5 class="fw-bold mb-3">My Appointments</h5>
          <div class="table-responsive">
            <table class="table table-bordered">
              <thead class="table-light">
                <tr>
                  <th>Date</th>
                  <th>Time</th>
                  <th>Type</th>
                  <th>Status</th>
                  <th>Calendar</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($myAppointments as $apt): ?>
                <tr>
                  <td><?php echo date('F j, Y', strtotime($apt['appointment_date'])); ?></td>
                  <td><?php echo date('g:i A', strtotime($apt['appointment_time'])); ?></td>
                  <td><?php echo htmlspecialchars($apt['appointment_type']); ?></td>
                  <td>
                    <span class="badge bg-<?php 
                      echo $apt['status'] === 'confirmed' ? 'success' : 
                           ($apt['status'] === 'scheduled' ? 'warning' : 
                           ($apt['status'] === 'cancelled' ? 'danger' : 'secondary')); 
                    ?>">
                      <?php echo htmlspecialchars(ucfirst($apt['status'])); ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($apt['calendar_event_id']): ?>
                      <span class="badge bg-success">
                        <i class="bi bi-check-circle"></i> Synced
                      </span>
                    <?php else: ?>
                      <span class="badge bg-warning">
                        <i class="bi bi-exclamation-triangle"></i> Not Synced
                      </span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php endif; ?>
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

        <form id="appointmentForm">
          <div class="modal-body p-4">
            <h6 class="text-center text-maroon fw-bold mb-3">Select Appointment Details</h6>

            <div class="mb-3">
              <label class="form-label fw-semibold">Appointment Type</label>
              <select id="appointmentType" name="appointment_type" class="form-select" required>
                <option value="">-- Select Type --</option>
                <option value="dental">Dental</option>
                <option value="medical">Medical</option>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label fw-semibold">Available Time</label>
              <div id="timeButtons" class="time-buttons">
                <!-- JS will insert time buttons -->
              </div>
            </div>

            <input type="hidden" id="appointmentDate" name="appointment_date">
            <input type="hidden" id="appointmentTime" name="appointment_time">

            <div class="alert alert-warning small mb-0 text-center">
              Note: Appointments are only available Monday–Friday, 8:00 AM – 5:00 PM.
            </div>
          </div>

          <div class="modal-footer border-0 p-3">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" id="confirmBooking">Confirm</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Calendar & Booking Logic -->
  <script>
    // Booked appointments from PHP
    const bookedDates = <?php echo json_encode($bookedAppointments, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    const daysContainer = document.getElementById("calendarDays");
    const monthYear = document.getElementById("monthYear");
    const prevMonthBtn = document.getElementById("prevMonth");
    const nextMonthBtn = document.getElementById("nextMonth");

    let currentDate = new Date();
    let selectedDate = null;

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

      // Days of the month
      for (let i = 1; i <= daysInMonth; i++) {
        const dateObj = new Date(year, month, i);
        dateObj.setHours(0, 0, 0, 0);
        const dateISO = dateObj.toISOString().split("T")[0];
        const btn = document.createElement("button");
        btn.textContent = i;

        // Disable past dates
        if (dateObj < today) {
          btn.classList.add("booked");
          btn.disabled = true;
        }
        // Disable weekends
        else if (dateObj.getDay() === 0 || dateObj.getDay() === 6) {
          btn.classList.add("booked");
          btn.disabled = true;
        }
        // Available dates
        else {
          btn.classList.add("available");
          btn.addEventListener("click", () => openBookingModal(dateISO));
        }

        daysContainer.appendChild(btn);
      }
    }

    function openBookingModal(dateISO) {
      selectedDate = dateISO;
      const modal = new bootstrap.Modal(document.getElementById("bookingModal"));
      document.getElementById("appointmentType").value = "";
      document.getElementById("appointmentDate").value = dateISO;
      document.getElementById("appointmentTime").value = "";

      // Generate time buttons (8 AM to 5 PM, skip 12 PM for lunch break)
      const timeContainer = document.getElementById("timeButtons");
      timeContainer.innerHTML = "";
      
      for (let hour = 8; hour <= 17; hour++) {
        if (hour === 12) continue; // skip lunch break

        const time = `${hour.toString().padStart(2, "0")}:00:00`;
        const displayTime = `${hour.toString().padStart(2, "0")}:00`;
        
        // Check if time is already booked
        const isBooked = bookedDates.some(apt => 
          apt.appointment_date === dateISO && apt.appointment_time === time
        );

        const btn = document.createElement("button");
        btn.type = "button";
        btn.textContent = displayTime;
        btn.classList.add("btn", "btn-outline-danger", "rounded-pill", "px-3", "py-1", "m-1");
        
        if (isBooked) {
          btn.classList.add("disabled");
          btn.disabled = true;
          btn.title = "Already booked";
        } else {
          btn.onclick = () => {
            document.querySelectorAll("#timeButtons button").forEach(b => {
              b.classList.remove("selected", "btn-danger");
              b.classList.add("btn-outline-danger");
            });
            btn.classList.remove("btn-outline-danger");
            btn.classList.add("selected", "btn-danger");
            document.getElementById("appointmentTime").value = time;
          };
        }
        
        timeContainer.appendChild(btn);
      }

      modal.show();
    }

    // Handle form submission
    document.getElementById("appointmentForm").addEventListener("submit", async function(e) {
      e.preventDefault();

      const formData = new FormData(this);
      formData.append("book_appointment", "1");

      const type = formData.get("appointment_type");
      const time = formData.get("appointment_time");

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
        const response = await fetch("appointment.php", {
          method: "POST",
          body: formData
        });

        const result = await response.json();

        if (result.success) {
          const modal = bootstrap.Modal.getInstance(document.getElementById("bookingModal"));
          modal.hide();

          Swal.fire({
            icon: "success",
            title: "Appointment Confirmed!",
            html: `
              <p>${result.appointment.type} appointment on ${result.appointment.date} at ${result.appointment.time}</p>
              ${result.calendar_synced ? 
                '<p class="text-success"><i class="bi bi-check-circle"></i> Synced to Google Calendar</p>' : 
                '<p class="text-warning"><i class="bi bi-exclamation-triangle"></i> Not synced to calendar</p>'
              }
            `,
            confirmButtonColor: "#d32f2f"
          }).then(() => {
            location.reload(); // Reload to show updated appointments
          });
        } else {
          Swal.fire({
            icon: "error",
            title: "Booking Failed",
            text: result.message,
            confirmButtonColor: "#d32f2f"
          });
        }
      } catch (error) {
        console.error("Error:", error);
        Swal.fire({
          icon: "error",
          title: "Error",
          text: "An error occurred. Please try again.",
          confirmButtonColor: "#d32f2f"
        });
      }
    });

    prevMonthBtn.addEventListener("click", () => {
      currentDate.setMonth(currentDate.getMonth() - 1);
      renderCalendar();
    });

    nextMonthBtn.addEventListener("click", () => {
      currentDate.setMonth(currentDate.getMonth() + 1);
      renderCalendar();
    });

    renderCalendar();
  </script>
</body>
</html>
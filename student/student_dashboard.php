<?php
session_start();
require_once('../config/database.php');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$pdo = getDB();
$userId = $_SESSION['user_id'];
$userName = $_SESSION['fname'] ?? 'Student';
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
</body>
</html>
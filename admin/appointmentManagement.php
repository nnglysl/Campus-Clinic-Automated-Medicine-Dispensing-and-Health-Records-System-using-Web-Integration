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
  <title>Appointment Management - BSU Clinic</title>

  <link href="../admin/appointmentManagement.css" rel="stylesheet" />
  <link href="../admin/nav.css" rel="stylesheet" />

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
      <div class="logout-icon" onclick="window.location.href='../auth/logout.php'">
        <i class="bi bi-box-arrow-right"></i>
      </div>
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

    <!-- Main Content -->
    <div class="main-content">
      <div class="top-bar">
        <h2>Appointments</h2>
        <div class="search-bar">
          <i class="bi bi-search"></i>
          <input type="text" placeholder="Search" id="searchInput">
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
                <div class="legend-box" style="background: #f0f9f0; border-color: #81c784;"></div>
                <span>Available (No bookings)</span>
              </div>
              <div class="legend-item">
                <div class="legend-box" style="background: #fff8e6; border-color: #ffa726;"></div>
                <span>Has Bookings</span>
              </div>
              <div class="legend-item">
                <div class="legend-box" style="background: #ffe5e5; border-color: #ff6b6b;"></div>
                <span>Unavailable</span>
              </div>
              <div class="legend-item">
                <div class="legend-box" style="background: #f9f9f9;"></div>
                <span>Other Month</span>
              </div>
            </div>

            <div class="calendar" id="calendar"></div>
          </div>
        </div>

        <!-- Appointments Panel -->
        <div class="appointments-panel">
          <div class="tabs">
            <button class="tab active" data-tab="today">Today</button>
            <button class="tab" data-tab="upcoming">Upcoming</button>
            <button class="tab" data-tab="cancelled">Cancelled</button>
          </div>

          <div class="appointments-list" id="appointmentsList">
            <div id="appointmentsContent"></div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Sync Status Notification -->
  <div id="syncStatus" class="sync-status">
    <span id="syncMessage"></span>
  </div>
<script src="../js/appointment.js"></script>
</body>
</html>
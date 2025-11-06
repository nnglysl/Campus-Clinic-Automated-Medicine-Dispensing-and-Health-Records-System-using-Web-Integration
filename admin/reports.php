<?php
session_start();
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

// Fetch patient statistics
$stmt = $pdo->query("SELECT COUNT(*) as total FROM patients");
$totalPatients = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COUNT(*) as new_patients FROM patients WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$newPatients = $stmt->fetch(PDO::FETCH_ASSOC)['new_patients'];

$stmt = $pdo->query("SELECT COUNT(*) as total_visits FROM visit_logs WHERE visit_date >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$totalVisits = $stmt->fetch(PDO::FETCH_ASSOC)['total_visits'];

// Fetch patient details with visit logs
$stmt = $pdo->query("
    SELECT p.sr_code, p.full_name, p.gender, 
           v.purpose, v.visit_date as last_visit
    FROM patients p
    LEFT JOIN visit_logs v ON p.id = v.patient_id
    WHERE v.id IN (
        SELECT MAX(id) FROM visit_logs GROUP BY patient_id
    )
    ORDER BY v.visit_date DESC
    LIMIT 10
");
$patientDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch inventory data
$stmt = $pdo->query("SELECT * FROM inventory WHERE status = 'active' ORDER BY name ASC");
$inventoryItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch dispensed history
$stmt = $pdo->query("
    SELECT md.dispensed_date, 
           p.full_name as patient_name,
           i.name as medicine,
           CONCAT(md.quantity, ' ', 
                  CASE 
                    WHEN i.name LIKE '%tablet%' THEN 'tablets'
                    WHEN i.name LIKE '%capsule%' THEN 'capsules'
                    WHEN i.name LIKE '%vial%' THEN 'vials'
                    WHEN i.name LIKE '%dose%' THEN 'doses'
                    ELSE 'units'
                  END
           ) as quantity,
           CONCAT(e.first_name, ' ', e.last_name) as dispensed_by,
           md.purpose
    FROM medicine_dispensed md
    JOIN patients p ON md.patient_id = p.id
    JOIN inventory i ON md.inventory_id = i.id
    JOIN employees e ON md.dispensed_by = e.id
    ORDER BY md.dispensed_date DESC, md.dispensed_time DESC
    LIMIT 10
");
$dispensedHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch stock entries
$stmt = $pdo->query("
    SELECT se.delivery_date, se.dr_number, se.supplier,
           i.name as medicine, 
           CONCAT(se.quantity, ' ', 
                  CASE 
                    WHEN i.name LIKE '%tablet%' THEN 'tablets'
                    WHEN i.name LIKE '%capsule%' THEN 'capsules'
                    WHEN i.name LIKE '%vial%' THEN 'vials'
                    WHEN i.name LIKE '%dose%' THEN 'doses'
                    ELSE 'units'
                  END
           ) as quantity,
           se.unit_price, se.total_amount,
           CONCAT(e.first_name, ' ', e.last_name) as received_by
    FROM stock_entries se
    JOIN inventory i ON se.inventory_id = i.id
    JOIN employees e ON se.received_by = e.id
    ORDER BY se.delivery_date DESC
    LIMIT 10
");
$stockEntries = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate dispensed totals
$stmt = $pdo->query("SELECT COALESCE(SUM(quantity), 0) as total FROM medicine_dispensed");
$totalDispensed = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

$stmt = $pdo->query("SELECT COALESCE(SUM(quantity), 0) as today FROM medicine_dispensed WHERE dispensed_date = CURDATE()");
$todayDispensed = $stmt->fetch(PDO::FETCH_ASSOC)['today'];

// Stock entry statistics
$stmt = $pdo->query("SELECT COUNT(*) as count FROM stock_entries");
$totalDeliveries = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

$stmt = $pdo->query("SELECT COALESCE(SUM(quantity), 0) as items FROM stock_entries");
$itemsReceived = $stmt->fetch(PDO::FETCH_ASSOC)['items'];

$stmt = $pdo->query("SELECT COALESCE(SUM(total_amount), 0) as total FROM stock_entries");
$totalValue = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reports & Analytics</title>

  <!-- Stylesheets -->
  <link href="../admin/reports.css" rel="stylesheet" />
  <link href="../admin/nav.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Text:ital@0;1&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  
  <!-- DataTables CDN -->
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />
  <script src="https://code.jquery.com/jquery-3.7.0.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
</head>

<body>
  <!-- HEADER -->
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
      <div class="logout-icon"><i class="bi bi-box-arrow-right"></i></div>
    </div>
  </div>

  <!-- SIDEBAR + MAIN CONTENT -->
  <div class="main-container">
    <div class="sidebar">
      <div>
        <a href="../admin/adminDashboard.php" class="menu-item">Dashboard</a>
        <a href="../admin/patients.php" class="menu-item">Patient</a>
        <a href="../admin/userManagement.php" class="menu-item">User Management</a>
        <a href="../admin/inventory.php" class="menu-item">Inventory</a>
        <a href="../admin/appointmentManagement.php" class="menu-item">Appointments</a>
        <a href="../admin/reports.php" class="menu-item active">Reports & Analytics</a>
      </div>
      <div class="user-profile">
        <div class="avatar"></div>
        <span><?php echo htmlspecialchars($userName); ?></span>
      </div>
    </div>

      <!-- MAIN DASHBOARD -->
    <main class="dashboard-content">
      <h2 class="welcome-text">Reports</h2>

      <div class="mb-3">
        <button class="btn btn-light btn-sm" onclick="window.print()"><i class="bi bi-printer"></i> Print</button>
        <button class="btn btn-secondary btn-sm"><i class="bi bi-file-pdf"></i> Export PDF</button>
      </div>

      <ul class="nav nav-tabs report-tabs" id="reportTabs">
        <li class="nav-item"><a class="nav-link active" data-target="patientsTab">Patients</a></li>
        <li class="nav-item"><a class="nav-link" data-target="inventoryTab">Inventory</a></li>
        <li class="nav-item"><a class="nav-link" data-target="dispensedTab">Dispensed</a></li>
        <li class="nav-item"><a class="nav-link" data-target="stockEntryTab">Stock Entry</a></li>
      </ul>

      <!-- PATIENTS TAB -->
      <div id="patientsTab" class="tab-content active">
        <div class="time-filters">
          <button class="btn btn-outline-secondary active" data-period="today">Today</button>
          <button class="btn btn-outline-secondary" data-period="week">This week</button>
          <button class="btn btn-outline-secondary" data-period="month">This month</button>
          <button class="btn btn-outline-secondary" data-period="year">This year</button>
        </div>

        <h5 class="mt-4">Patients Report</h5>

        <div class="stats-row">
          <div class="stat-card">
            <h2 id="totalPatients"><?php echo number_format($totalPatients); ?></h2>
            <p>Total Patients</p>
          </div>
          <div class="stat-card">
            <h2 id="newPatients"><?php echo number_format($newPatients); ?></h2>
            <p>New Patients</p>
          </div>
          <div class="stat-card">
            <h2 id="totalVisits"><?php echo number_format($totalVisits); ?></h2>
            <p>Total Visits</p>
          </div>
        </div>

        <div class="row mt-4">
          <div class="col-md-4">
            <div class="chart-box">
              <h6>Patient Visits</h6>
              <canvas id="patientVisitsChart"></canvas>
            </div>
          </div>

          <div class="col-md-4">
            <div class="chart-box">
              <h6>Health Trends</h6>
              <canvas id="healthTrendsChart"></canvas>
            </div>
          </div>

          <div class="col-md-4">
            <div class="chart-box">
              <h6>Monthly Appointments Trend</h6>
              <select class="form-select form-select-sm chart-filter mb-2" id="yearFilter">
                <option value="2025">2025</option>
                <option value="2024">2024</option>
                <option value="2023">2023</option>
              </select>
              <canvas id="lineChart"></canvas>
            </div>
          </div>
        </div>

        <div class="table-container">
          <h6 class="mb-3">Patient Details</h6>
          <table class="table table-bordered" id="patientsTable">
            <thead class="table-light">
              <tr>
                <th>Patient ID</th>
                <th>Name</th>
                <th>Gender</th>
                <th>Purpose</th>
                <th>Last Visit</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($patientDetails as $patient): ?>
              <tr>
                <td><?php echo htmlspecialchars($patient['sr_code']); ?></td>
                <td><?php echo htmlspecialchars($patient['full_name']); ?></td>
                <td><?php echo htmlspecialchars($patient['gender']); ?></td>
                <td><?php echo htmlspecialchars($patient['purpose'] ?? 'N/A'); ?></td>
                <td><?php echo $patient['last_visit'] ? date('Y-m-d', strtotime($patient['last_visit'])) : 'N/A'; ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- INVENTORY TAB -->
      <div id="inventoryTab" class="tab-content">
        <div class="time-filters">
          <button class="btn btn-outline-secondary active">Today</button>
          <button class="btn btn-outline-secondary">This week</button>
          <button class="btn btn-outline-secondary">This month</button>
          <button class="btn btn-outline-secondary">This year</button>
        </div>

        <h5 class="mt-4">Inventory Report</h5>

        <div class="row mt-4">
          <div class="col-md-6">
            <div class="chart-box" style="height: 350px;">
              <h6>Medicine Usage Trend</h6>
              <canvas id="medicineUsageChart"></canvas>
            </div>
          </div>
          <div class="col-md-6">
            <div class="chart-box" style="height: 350px;">
              <h6>Stock Distribution</h6>
              <canvas id="stockDistributionChart"></canvas>
            </div>
          </div>
        </div>

        <div class="table-container">
          <h6 class="mb-3">Inventory Details</h6>
          <table class="table table-bordered" id="inventoryTable">
            <thead class="table-light">
              <tr>
                <th>Item Code</th>
                <th>Item Name</th>
                <th>Quantity</th>
                <th>Dispensed</th>
                <th>Expiry Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($inventoryItems as $item): ?>
              <tr>
                <td><?php echo htmlspecialchars($item['code']); ?></td>
                <td><?php echo htmlspecialchars($item['name']); ?></td>
                <td><?php echo number_format($item['quantity']); ?></td>
                <td><?php echo number_format($item['dispensed']); ?></td>
                <td><?php echo date('Y-m-d', strtotime($item['expiry'])); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- DISPENSED TAB -->
      <div id="dispensedTab" class="tab-content">
        <div class="time-filters">
          <button class="btn btn-outline-secondary active">Today</button>
          <button class="btn btn-outline-secondary">This week</button>
          <button class="btn btn-outline-secondary">This month</button>
          <button class="btn btn-outline-secondary">This year</button>
        </div>

        <h5 class="mt-4">Dispensed Medicine Report</h5>

        <div class="stats-row">
          <div class="stat-card">
            <h2><?php echo number_format($totalDispensed); ?></h2>
            <p>Total Dispensed</p>
          </div>
          <div class="stat-card">
            <h2><?php echo number_format($todayDispensed); ?></h2>
            <p>Today's Dispensed</p>
          </div>
        </div>

        <div class="table-container">
          <h6 class="mb-3">Dispensed History</h6>
          <table class="table table-bordered" id="dispensedTable">
            <thead class="table-light">
              <tr>
                <th>Date</th>
                <th>Patient Name</th>
                <th>Medicine</th>
                <th>Quantity</th>
                <th>Dispensed By</th>
                <th>Purpose</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($dispensedHistory as $record): ?>
              <tr>
                <td><?php echo date('Y-m-d', strtotime($record['dispensed_date'])); ?></td>
                <td><?php echo htmlspecialchars($record['patient_name']); ?></td>
                <td><?php echo htmlspecialchars($record['medicine']); ?></td>
                <td><?php echo htmlspecialchars($record['quantity']); ?></td>
                <td><?php echo htmlspecialchars($record['dispensed_by']); ?></td>
                <td><?php echo htmlspecialchars($record['purpose'] ?? 'N/A'); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- STOCK ENTRY TAB -->
      <div id="stockEntryTab" class="tab-content">
        <div class="time-filters">
          <button class="btn btn-outline-secondary active">Today</button>
          <button class="btn btn-outline-secondary">This week</button>
          <button class="btn btn-outline-secondary">This month</button>
          <button class="btn btn-outline-secondary">This year</button>
        </div>

        <h5 class="mt-4">Stock Entry Report</h5>

        <div class="stats-row">
          <div class="stat-card">
            <h2><?php echo number_format($totalDeliveries); ?></h2>
            <p>Total Deliveries</p>
          </div>
          <div class="stat-card">
            <h2><?php echo number_format($itemsReceived); ?></h2>
            <p>Items Received</p>
          </div>
          <div class="stat-card">
            <h2>₱<?php echo number_format($totalValue, 2); ?></h2>
            <p>Total Value</p>
          </div>
        </div>

        <div class="table-container">
          <h6 class="mb-3">Stock Entry History</h6>
          <table class="table table-bordered" id="stockEntryTable">
            <thead class="table-light">
              <tr>
                <th>Delivery Date</th>
                <th>DR Number</th>
                <th>Supplier</th>
                <th>Medicine</th>
                <th>Quantity</th>
                <th>Unit Price</th>
                <th>Total Amount</th>
                <th>Received By</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($stockEntries as $entry): ?>
              <tr>
                <td><?php echo date('Y-m-d', strtotime($entry['delivery_date'])); ?></td>
                <td><?php echo htmlspecialchars($entry['dr_number']); ?></td>
                <td><?php echo htmlspecialchars($entry['supplier']); ?></td>
                <td><?php echo htmlspecialchars($entry['medicine']); ?></td>
                <td><?php echo htmlspecialchars($entry['quantity']); ?></td>
                <td>₱<?php echo number_format($entry['unit_price'], 2); ?></td>
                <td>₱<?php echo number_format($entry['total_amount'], 2); ?></td>
                <td><?php echo htmlspecialchars($entry['received_by']); ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
  <script>
    // Tab switching
    document.querySelectorAll('#reportTabs .nav-link').forEach(tab => {
      tab.addEventListener('click', function (e) {
        e.preventDefault();
        document.querySelectorAll('#reportTabs .nav-link').forEach(link => link.classList.remove('active'));
        this.classList.add('active');
        document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
        document.getElementById(this.getAttribute('data-target')).classList.add('active');
      });
    });

    // Time filter buttons
    document.querySelectorAll('.time-filters .btn').forEach(btn => {
      btn.addEventListener('click', function() {
        this.parentElement.querySelectorAll('.btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
      });
    });

    // Initialize DataTables
    $(document).ready(function() {
      $('#patientsTable').DataTable({ pageLength: 5 });
      $('#inventoryTable').DataTable({ pageLength: 5 });
      $('#dispensedTable').DataTable({ pageLength: 5 });
      $('#stockEntryTable').DataTable({ pageLength: 5 });
    });

    // Patient Visits Chart (Bar)
    const patientVisitsCtx = document.getElementById('patientVisitsChart').getContext('2d');
    new Chart(patientVisitsCtx, {
      type: 'bar',
      data: {
        labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
        datasets: [{
          label: 'Visits',
          data: [12, 19, 15, 25, 22, 18, 20],
          backgroundColor: '#6b0000',
          borderRadius: 5
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { stepSize: 5 } }
        }
      }
    });

    // Health Trends Chart (Line)
    const healthTrendsCtx = document.getElementById('healthTrendsChart').getContext('2d');
    new Chart(healthTrendsCtx, {
      type: 'line',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        datasets: [{
          label: 'Health Issues',
          data: [30, 45, 35, 50, 40, 60],
          borderColor: '#6b0000',
          backgroundColor: 'rgba(107, 0, 0, 0.1)',
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true }
        }
      }
    });

    // Monthly Appointments Trend (Line Chart)
    const lineChartCtx = document.getElementById('lineChart').getContext('2d');
    new Chart(lineChartCtx, {
      type: 'line',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
        datasets: [{
          label: 'Appointments',
          data: [65, 59, 80, 81, 56, 75, 70, 85, 78, 90, 95, 88],
          borderColor: '#6b0000',
          backgroundColor: 'rgba(107, 0, 0, 0.1)',
          tension: 0.4,
          fill: true,
          pointBackgroundColor: '#6b0000',
          pointBorderColor: '#fff',
          pointBorderWidth: 2,
          pointRadius: 4
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { 
          legend: { 
            display: true,
            position: 'top'
          } 
        },
        scales: {
          y: { 
            beginAtZero: true,
            ticks: {
              stepSize: 20
            }
          }
        }
      }
    });

    // Medicine Usage Chart (Line)
    const medicineUsageCtx = document.getElementById('medicineUsageChart').getContext('2d');
    new Chart(medicineUsageCtx, {
      type: 'line',
      data: {
        labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct'],
        datasets: [{
          label: 'Medicines Dispensed',
          data: [120, 150, 180, 160, 190, 210, 200, 230, 220, 250],
          borderColor: '#6b0000',
          backgroundColor: 'rgba(107, 0, 0, 0.1)',
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: true, position: 'top' } },
        scales: {
          y: { beginAtZero: true }
        }
      }
    });

    // Stock Distribution Chart (Pie)
    const stockDistributionCtx = document.getElementById('stockDistributionChart').getContext('2d');
    new Chart(stockDistributionCtx, {
      type: 'pie',
      data: {
        labels: ['PVRV', 'Tetanus Toxoid', 'Paracetamol', 'Amoxicillin', 'Others'],
        datasets: [{
          data: [70, 96, 200, 150, 180],
          backgroundColor: ['#6b0000', '#dc3545', '#ffc107', '#28a745', '#17a2b8']
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'bottom' }
        }
      }
    });
  </script>
</body>
</html>
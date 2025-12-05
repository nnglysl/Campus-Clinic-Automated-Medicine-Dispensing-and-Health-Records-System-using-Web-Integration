<?php
// Start session if needed
session_start();


$totalPatients = 248;
$newPatients = 42;
$totalVisits = 156;

$patients = [
    ['id' => 'P001', 'name' => 'Juan Dela Cruz', 'gender' => 'Male', 'purpose' => 'Headache', 'last_visit' => '2025-10-20'],
    ['id' => 'P002', 'name' => 'Maria Santos', 'gender' => 'Female', 'purpose' => 'Dental', 'last_visit' => '2025-10-19'],
    ['id' => 'P003', 'name' => 'Pedro Reyes', 'gender' => 'Male', 'purpose' => 'Medical', 'last_visit' => '2025-10-18'],
    ['id' => 'P004', 'name' => 'Ana Garcia', 'gender' => 'Female', 'purpose' => 'Headache', 'last_visit' => '2025-10-17'],
    ['id' => 'P005', 'name' => 'Jose Mendoza', 'gender' => 'Male', 'purpose' => 'Headache', 'last_visit' => '2025-10-16'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batangas State University - Clinic Reports & Analytics</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../employee/nav.css">
    <link rel="stylesheet" href="../employee/reports.css">
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

    <!-- Main Container -->
    <div class="main-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <a href="../employee/dashboard.php" class="menu-item">Dashboard</a>
            <a href="../employee/profile.php" class="menu-item">Profile</a>
            <a href="../employee/patients.php" class="menu-item">Patients</a>
            <a href="../employee/appointments.php" class="menu-item">Appointments</a>
            <a href="../employee/reports.php" class="menu-item active">Reports & Analytics</a>
            <a href="../employee/settings.php" class="menu-item">Settings</a>
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
                        <h2 id="totalPatients"><?php echo $totalPatients; ?></h2>
                        <p>Total Patients</p>
                    </div>
                    <div class="stat-card">
                        <h2 id="newPatients"><?php echo $newPatients; ?></h2>
                        <p>New Patients</p>
                    </div>
                    <div class="stat-card">
                        <h2 id="totalVisits"><?php echo $totalVisits; ?></h2>
                        <p>Total Visits</p>
                    </div>
                </div>

                <div class="row mt-4">
                    <!-- Patient Visits -->
                    <div class="col-md-4">
                        <div class="chart-box">
                            <h6>Patient Visits</h6>
                            <canvas id="patientVisitsChart"></canvas>
                        </div>
                    </div>

                    <!-- Health Trends -->
                    <div class="col-md-4">
                        <div class="chart-box">
                            <h6>Health Trends</h6>
                            <canvas id="healthTrendsChart"></canvas>
                        </div>
                    </div>

                    <!-- Monthly Appointments Trend -->
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
                            <?php foreach ($patients as $patient): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($patient['id']); ?></td>
                                <td><?php echo htmlspecialchars($patient['name']); ?></td>
                                <td><?php echo htmlspecialchars($patient['gender']); ?></td>
                                <td><?php echo htmlspecialchars($patient['purpose']); ?></td>
                                <td><?php echo htmlspecialchars($patient['last_visit']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
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
    </script>
</body>
</html>
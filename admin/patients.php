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
    'fname' => $_SESSION['fname'] ?? 'Admin',
    'lname' => $_SESSION['lname'] ?? '',
    'role' => $_SESSION['role'] ?? 'admin'
];

error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("User array: " . print_r($user, true));

$loggedInPhysician = [
    'name' => $user['fname'] . ' ' . $user['lname'],
    'id' => $user['id']
];

$pdo = getDB();

// Fetch all patients with error handling
$patients = [];
try {
    $stmt = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC");
    $patients = $stmt->fetchAll();
    error_log("Fetched " . count($patients) . " patients"); // Debug log
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
    $patients = []; // Ensure it's an array even on error
}

// Fetch all medicines from inventory
$medicines = [];
try {
    $stmt = $pdo->query("SELECT id, batchId, code, name, quantity, dispensed, expiry, description, status FROM inventory WHERE quantity > 0 AND status = 'active' ORDER BY name");
    $medicines = $stmt->fetchAll();
    error_log("Fetched " . count($medicines) . " medicines"); // Debug log
} catch (PDOException $e) {
    error_log("Error fetching medicines: " . $e->getMessage());
    $medicines = []; // Ensure it's an array even on error
}


// Fetch visit logs
$visitLogs = [];
try {
    $stmt = $pdo->query("
        SELECT vl.*, p.full_name as patient_name 
        FROM visit_logs vl 
        JOIN patients p ON vl.patient_id = p.id 
        ORDER BY vl.visit_date DESC 
        LIMIT 50
    ");
    $visitLogs = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching visit logs: " . $e->getMessage());
}

// Function to get patient initials
function getInitials($name) {
    $words = explode(' ', $name);
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[count($words) - 1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Patients - Batangas State University</title>

  <!-- Stylesheets -->
  <link href="../admin/css/patients.css" rel="stylesheet">
  <link href="/finalproject/css/nav.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" />
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

    <div class="header-icons">
      <div class="notification-icon"><i class="bi bi-bell-fill"></i></div>
      <div class="logout-icon" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></div>
    </div>
  </div>

  <!-- SIDEBAR + MAIN -->
  <div class="main-container">
    <div class="sidebar">
      <div>
        <a href="../admin/adminDashboard.php" class="menu-item">Dashboard</a>
        <a href="../admin/patients.php" class="menu-item active">Patients</a>
        <a href="../admin/userManagement.php" class="menu-item">User Management</a>
        <a href="../admin/inventory.php" class="menu-item">Inventory</a>
        <a href="../admin/appointmentManagement.php" class="menu-item">Appointments</a>
        <a href="../admin/reports.php" class="menu-item">Reports & Analytics</a>
      </div>
      <div class="user-profile">
        <div class="avatar"></div>
        <span><?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?></span>
      </div>
    </div>

    <div class="main-content">
      <h2 class="page-title">Patients</h2>
      
      <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="searchPatients" placeholder="Search patients...">
      </div>
      
      <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="patients-tab" data-bs-toggle="tab" data-bs-target="#patients" type="button" role="tab">Patients</button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#visitLogs" type="button" role="tab">Visit Logs</button>
        </li>
      </ul>

      <div class="tab-content mt-3" id="dashboardTabsContent">
        <div class="tab-pane fade show active" id="patients" role="tabpanel">
          <div class="content-wrapper">
            <div class="patients-section" id="patientsSection">
              <div class="patients-header">
                <h3>All Patients</h3>
                <button class="btn-add-patient" data-bs-toggle="modal" data-bs-target="#addPatientModal">
                  <i class="bi bi-plus-lg"></i> Add New Patient
                </button>
              </div>

              <div class="patients-list" id="patientsList">
                <?php if (empty($patients)): ?>
                  <p style="text-align: center; color: #999; padding: 40px;">No patients found. Add a new patient to get started.</p>
                <?php else: ?>
                  <?php foreach ($patients as $patient): ?>
                  <div class="patient-card" data-patient-id="<?php echo $patient['id']; ?>">
                    <div class="patient-avatar-wrapper">
                      <div class="patient-initials"><?php echo getInitials($patient['full_name']); ?></div>
                    </div>
                    <div class="patient-info-compact">
                      <div class="patient-name"><?php echo htmlspecialchars($patient['full_name']); ?></div>
                      <div class="patient-meta">SR-Code: <?php echo htmlspecialchars($patient['sr_code']); ?></div>
                      <div class="patient-meta"><?php echo htmlspecialchars($patient['position']); ?></div>
                    </div>
                    <i class="bi bi-chevron-right view-patient-btn"></i>
                  </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>
            </div>

            <div class="patient-detail-section" id="patientDetailSection">
              <div class="detail-card">
                <div class="detail-header">
                  <div class="detail-patient-info">
                    <div class="detail-avatar"><div class="patient-initials" id="detailInitials">--</div></div>
                    <div class="detail-name-section">
                      <h2 id="detailName">Patient Name</h2>
                      <p id="detailSRCode">SR-Code: --</p>
                      <p id="detailPosition">Position</p>
                    </div>
                  </div>
                  <button class="btn-back" onclick="closePatientDetail()"><i class="bi bi-arrow-left"></i> Back</button>
                </div>

                <div class="tabs-container">
                  <button class="tab-item active" onclick="switchTab('info')">Personal Information</button>
                  <button class="tab-item" onclick="switchTab('medical')">Medical Records</button>
                  <button class="tab-item" onclick="switchTab('new-record')">New Medical Record</button>
                </div>

                <div class="tab-content-inner active" id="infoTab">
                  <!-- Personal Information will be loaded dynamically -->
                </div>

                <div class="tab-content-inner" id="medicalTab">
                  <div class="form-section">
                    <h4 class="section-title">Medical History</h4>
                    <div id="medicalRecordsContainer">
                      <p style="text-align: center; color: #999; padding: 40px;">No medical records found.</p>
                    </div>
                  </div>
                </div>

                <div class="tab-content-inner" id="newRecordTab">
                  <?php include 'includes/newRecord.php'; ?>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="tab-pane fade" id="visitLogs" role="tabpanel">
          <div class="visit-logs-container">
            <div class="visit-logs-header">
              <h5>Visit Logs</h5>
            </div>
            <div class="table-responsive">
              <table class="visit-logs-table">
                <thead>
                  <tr>
                    <th>#</th>
                    <th>Patient Name</th>
                    <th>Purpose</th>
                    <th>Doctor</th>
                    <th>Date</th>
                  </tr>
                </thead>
                <tbody id="visitLogsTableBody">
                  <?php if (empty($visitLogs)): ?>
                    <tr>
                      <td colspan="5" style="text-align: center; color: #999; padding: 20px;">No visit logs found.</td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($visitLogs as $index => $log): ?>
                    <tr>
                      <td><?php echo $index + 1; ?></td>
                      <td><?php echo htmlspecialchars($log['patient_name']); ?></td>
                      <td><?php echo htmlspecialchars($log['purpose']); ?></td>
                      <td><?php echo htmlspecialchars($log['physician_name']); ?></td>
                      <td><?php echo date('m/d/Y', strtotime($log['visit_date'])); ?></td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Add Patient Modal -->
  <?php include './includes/addPatient.php'; ?>

  
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/logout.js"></script>
<script>
    // Initialize variables with fallbacks to prevent undefined errors
    let patientsData = [];
    let loggedInPhysician = {};
    let medicineInventory = [];

    try {
        // Safely parse the PHP data
        <?php 
        // Ensure $patients is an array
        $patientsJson = is_array($patients) ? json_encode($patients, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '[]';
        $physicianJson = json_encode($loggedInPhysician, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $medicinesJson = is_array($medicines) ? json_encode($medicines, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '[]';
        
        // Validate JSON encoding
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON encoding error: " . json_last_error_msg());
        }
        ?>
        
        patientsData = <?php echo $patientsJson; ?>;
        loggedInPhysician = <?php echo $physicianJson; ?>;
        medicineInventory = <?php echo $medicinesJson; ?>;
        
        // Validation checks
        if (!Array.isArray(patientsData)) {
            console.error('patientsData is not an array, resetting to empty array');
            patientsData = [];
        }
        
        if (!loggedInPhysician || typeof loggedInPhysician !== 'object') {
            console.error('loggedInPhysician is invalid');
            loggedInPhysician = { id: 0, name: 'Unknown' };
        }
        
        if (!Array.isArray(medicineInventory)) {
            console.error('medicineInventory is not an array, resetting to empty array');
            medicineInventory = [];
        }
        
        // Log successful loading
        console.log('Data loaded successfully');
        console.log('Patients count:', patientsData.length);
        console.log('Physician:', loggedInPhysician);
        console.log('Medicines count:', medicineInventory.length);
        
    } catch (error) {
        console.error('CRITICAL ERROR loading page data:', error);
        console.error('Error details:', error.message);
        console.error('Stack trace:', error.stack);
        
        // Set safe defaults
        patientsData = patientsData || [];
        loggedInPhysician = loggedInPhysician || { id: 0, name: 'Unknown' };
        medicineInventory = medicineInventory || [];
        
        // Alert user
        alert('Warning: Some data failed to load. The page may not function correctly. Error: ' + error.message);
    }
</script>
<script src="../js/patients.js"></script>
</body>
</html>
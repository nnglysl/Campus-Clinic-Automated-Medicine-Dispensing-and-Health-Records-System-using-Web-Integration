<?php
require_once '../config/database.php';
session_start();

// Enable error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Define user info safely
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['fname'] ?? 'Admin';
$last_name = $_SESSION['lname'] ?? '';
$user_role = $_SESSION['role'] ?? 'admin';
$fullName = trim($first_name . ' ' . $last_name);

// Log session info for debugging
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("User info: " . print_r($_SESSION, true));

$loggedInPhysician = [
    'name' => $fullName,
    'id' => $user_id
];

$pdo = getDB();

// Fetch all patients with error handling
$patients = [];
try {
    $stmt = $pdo->query("SELECT * FROM patients ORDER BY created_at DESC");
    $patients = $stmt->fetchAll();
    error_log("Fetched " . count($patients) . " patients");
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
    $patients = [];
}

// Fetch all medicines from inventory
$medicines = [];
try {
    $stmt = $pdo->query("SELECT id, batchId, code, name, quantity, dispensed, expiry, description, status FROM inventory WHERE quantity > 0 AND status = 'active' ORDER BY name");
    $medicines = $stmt->fetchAll();
    error_log("Fetched " . count($medicines) . " medicines");
} catch (PDOException $e) {
    error_log("Error fetching medicines: " . $e->getMessage());
    $medicines = [];
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Batangas State University - Clinic Patients</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/nav.css" />
  <link rel="stylesheet" href="../dental/css/dental_patients.css" />
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

  <!-- Main Container -->
  <div class="main-container">
    <!-- Sidebar -->
    <div class="sidebar">
      <a href="../dental/dental_dashboard.php" class="menu-item">Dashboard</a>
      <a href="../dental/dental_profile.php" class="menu-item">Profile</a>
      <a href="../dental/dental_patients.php" class="menu-item active">Patients</a>
      <a href="../dental/dental_appointments.php" class="menu-item">Appointments</a>
      <a href="../dental/settings.php" class="menu-item">Settings</a>

      <div class="user-profile">
        <div class="avatar"></div>
        <span><?php echo htmlspecialchars($fullName); ?></span>
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
                  <button class="tab-item active" data-tab="info">Personal Information</button>
                  <button class="tab-item" data-tab="medical">Medical Records</button>
                  <button class="tab-item" data-tab="new-record">New Dental Record</button>
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
                  <!-- DENTAL RECORD FORM -->
                  <div class="consultation-form-header">
                    <h3>DENTAL EXAMINATION FORM</h3>
                  </div>

                  <form id="dentalConsultationForm" class="dental-form">
                    <input type="hidden" name="patient_id" id="consultationPatientId">
                    <input type="hidden" name="physician_id" value="<?php echo $user_id; ?>">
                    <input type="hidden" name="physician_name" value="<?php echo htmlspecialchars($fullName); ?>">

                    <!-- Patient Information Section -->
                    <div class="consultation-section">
                      <h4 class="consultation-section-title">Patient Information</h4>
                      <div class="form-row">
                        <div class="form-group">
                          <label>Full Name <span class="required">*</span></label>
                          <input type="text" name="full_name" id="consultFullName" readonly class="readonly-field">
                        </div>
                        <div class="form-group">
                          <label>SR Code <span class="required">*</span></label>
                          <input type="text" name="sr_code" id="consultSRCode" readonly class="readonly-field">
                        </div>
                      </div>
                      <div class="form-row">
                        <div class="form-group">
                          <label>Address</label>
                          <input type="text" name="address" id="consultAddress" readonly class="readonly-field">
                        </div>
                        <div class="form-group">
                          <label>Program</label>
                          <input type="text" name="program" id="consultProgram" readonly class="readonly-field">
                        </div>
                      </div>
                    </div>

                    <!-- Dentition Status Section -->
                    <div class="consultation-section">
                      <h4 class="consultation-section-title">Dentition Status and Treatment Needs</h4>
                      
                      <div class="dental-chart-wrapper">
                        <div class="teeth-section-standalone">
                          <!-- Upper Temporary Teeth -->
                          <div>
                            <div class="teeth-label">Temporary Teeth - Right <span style="float: right;">Left</span></div>
                            <div class="teeth-row" id="upperTempTeeth"></div>
                          </div>

                          <!-- Upper Permanent Teeth -->
                          <div style="margin-top: 20px;">
                            <div class="teeth-row" id="upperPermTeeth"></div>
                          </div>

                          <!-- Lower Permanent Teeth -->
                          <div style="margin-top: 30px;">
                            <div class="teeth-row" id="lowerPermTeeth"></div>
                          </div>

                          <!-- Lower Temporary Teeth -->
                          <div style="margin-top: 20px;">
                            <div class="teeth-row" id="lowerTempTeeth"></div>
                            <div class="teeth-label">Temporary Teeth</div>
                          </div>
                        </div>
                      </div>
                      
                      <!-- Index Tables -->
                      <div class="index-tables-container">
                        <!-- Temporary Teeth Index -->
                        <div class="index-table-wrapper">
                          <h5 class="index-table-title">Temporary Teeth</h5>
                          <table class="index-table">
                            <thead>
                              <tr>
                                <th rowspan="2">Index d.f.t</th>
                                <th colspan="6">Date of Visits</th>
                              </tr>
                              <tr>
                                <th>1st</th>
                                <th>2nd</th>
                                <th>3rd</th>
                                <th>4th</th>
                                <th>5th</th>
                                <th>6th</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td class="label-cell">No. T/Decayed</td>
                                <td><input type="text" name="temp_decayed_1"></td>
                                <td><input type="text" name="temp_decayed_2"></td>
                                <td><input type="text" name="temp_decayed_3"></td>
                                <td><input type="text" name="temp_decayed_4"></td>
                                <td><input type="text" name="temp_decayed_5"></td>
                                <td><input type="text" name="temp_decayed_6"></td>
                              </tr>
                              <tr>
                                <td class="label-cell">No. T/Filled</td>
                                <td><input type="text" name="temp_filled_1"></td>
                                <td><input type="text" name="temp_filled_2"></td>
                                <td><input type="text" name="temp_filled_3"></td>
                                <td><input type="text" name="temp_filled_4"></td>
                                <td><input type="text" name="temp_filled_5"></td>
                                <td><input type="text" name="temp_filled_6"></td>
                              </tr>
                              <tr>
                                <td class="label-cell">Total d.f.t.</td>
                                <td><input type="text" name="temp_total_1" readonly></td>
                                <td><input type="text" name="temp_total_2" readonly></td>
                                <td><input type="text" name="temp_total_3" readonly></td>
                                <td><input type="text" name="temp_total_4" readonly></td>
                                <td><input type="text" name="temp_total_5" readonly></td>
                                <td><input type="text" name="temp_total_6" readonly></td>
                              </tr>
                            </tbody>
                          </table>
                        </div>

                        <!-- Permanent Teeth Index -->
                        <div class="index-table-wrapper">
                          <h5 class="index-table-title">Permanent Teeth</h5>
                          <table class="index-table">
                            <thead>
                              <tr>
                                <th rowspan="2">Index d.f.t</th>
                                <th colspan="4">Date if Visits</th>
                              </tr>
                              <tr>
                                <th>1st</th>
                                <th>2nd</th>
                                <th>3rd</th>
                                <th>4th</th>
                              </tr>
                            </thead>
                            <tbody>
                              <tr>
                                <td class="label-cell">D</td>
                                <td><input type="text" name="perm_d_1"></td>
                                <td><input type="text" name="perm_d_2"></td>
                                <td><input type="text" name="perm_d_3"></td>
                                <td><input type="text" name="perm_d_4"></td>
                              </tr>
                              <tr>
                                <td class="label-cell">M</td>
                                <td><input type="text" name="perm_m_1"></td>
                                <td><input type="text" name="perm_m_2"></td>
                                <td><input type="text" name="perm_m_3"></td>
                                <td><input type="text" name="perm_m_4"></td>
                              </tr>
                              <tr>
                                <td class="label-cell">F</td>
                                <td><input type="text" name="perm_f_1"></td>
                                <td><input type="text" name="perm_f_2"></td>
                                <td><input type="text" name="perm_f_3"></td>
                                <td><input type="text" name="perm_f_4"></td>
                              </tr>
                              <tr>
                                <td class="label-cell">Total DMF</td>
                                <td><input type="text" name="perm_total_1" readonly></td>
                                <td><input type="text" name="perm_total_2" readonly></td>
                                <td><input type="text" name="perm_total_3" readonly></td>
                                <td><input type="text" name="perm_total_4" readonly></td>
                              </tr>
                              <tr>
                                <td class="label-cell">Total no of Teeth</td>
                                <td><input type="text" name="perm_teeth_1"></td>
                                <td><input type="text" name="perm_teeth_2"></td>
                                <td><input type="text" name="perm_teeth_3"></td>
                                <td><input type="text" name="perm_teeth_4"></td>
                              </tr>
                            </tbody>
                          </table>
                          
                          <div class="index-legend">
                            <strong>Legend:</strong>
                            <span class="legend-abbr">X = Carious tooth indicated for extraction</span>
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Treatment Record Section -->
                    <div class="consultation-section">
                      <h4 class="consultation-section-title">Treatment Record</h4>
                      
                      <div class="treatment-record-wrapper">
                        <table class="treatment-table-standalone">
                          <thead>
                            <tr>
                              <th>Date</th>
                              <th>Tooth No.</th>
                              <th>Operation</th>
                              <th>Dentist</th>
                            </tr>
                          </thead>
                          <tbody id="treatmentBody">
                            <tr>
                              <td><input type="date" name="treatment_date[]"></td>
                              <td><input type="text" name="treatment_tooth[]"></td>
                              <td><input type="text" name="treatment_operation[]"></td>
                              <td><input type="text" name="treatment_dentist[]" value="<?php echo htmlspecialchars($fullName ?? ''); ?>"></td>
                            </tr>
                          </tbody>
                        </table>
                        <button type="button" class="add-row-btn-standalone" onclick="addDentalTreatmentRow()">
                          + Add Treatment Row
                        </button>
                      </div>
                    </div>

                    <!-- Legend Section -->
                    <div class="legend-section">
                      <div class="legend-title">Legend:</div>
                      <div class="legend-grid">
                        <div class="legend-column">
                          <div class="legend-item"><strong>X</strong> - For extraction</div>
                          <div class="legend-item"><strong>C</strong> - For filling</div>
                          <div class="legend-item"><strong>RF</strong> - Root Fragment</div>
                          <div class="legend-item"><strong>M</strong> - Missing</div>
                          <div class="legend-item"><strong>Im</strong> - Impacted</div>
                          <div class="legend-item"><strong>Un</strong> - Unerupted</div>
                          <div class="legend-item"><strong>√</strong> - Present</div>
                          <div class="legend-item"><strong>Cm</strong> - Cong. Missing</div>
                          <div class="legend-item"><strong>Sp</strong> - Supernumerary</div>
                          
                          <h5 style="margin-top: 15px;">Periodontal Screening:</h5>
                          <div class="legend-item">
                            <input type="checkbox" name="gingivitis"> Gingivitis
                          </div>
                          <div class="legend-item">
                            <input type="checkbox" name="early_periodontitis"> Early Periodontitis
                          </div>
                        </div>

                        <div class="legend-column">
                          <div class="legend-item"><strong>JC</strong> - Jacket Crown</div>
                          <div class="legend-item"><strong>Am</strong> - Amalgam</div>
                          <div class="legend-item"><strong>Comp</strong> - Composite</div>
                          <div class="legend-item"><strong>TF</strong> - Temporary Filling</div>
                          <div class="legend-item"><strong>S</strong> - Sealant</div>
                          <div class="legend-item"><strong>In</strong> - Inlay</div>

                          <h5 style="margin-top: 15px;">Occlusion:</h5>
                          <div class="legend-item">
                            <input type="checkbox" name="class_molar"> Class (Molar)
                          </div>
                          <div class="legend-item">
                            <input type="checkbox" name="overjet"> Overjet
                          </div>
                          <div class="legend-item">
                            <input type="checkbox" name="overbite"> Overbite
                          </div>
                        </div>

                        <div class="legend-column">
                          <div class="legend-item"><strong>AB</strong> - Abutment</div>
                          <div class="legend-item"><strong>P</strong> - Pontic</div>
                          <div class="legend-item"><strong>RPD</strong> - Partial Denture</div>
                          <div class="legend-item"><strong>CD</strong> - Complete Denture</div>
                          <div class="legend-item"><strong>FB</strong> - Fixed Bridge</div>

                          <h5 style="margin-top: 15px;">Appliances:</h5>
                          <div class="legend-item">
                            <input type="checkbox" name="orthodontic"> Orthodontic
                          </div>
                          <div class="legend-item">
                            <input type="checkbox" name="stayplate"> Stayplate
                          </div>

                          <h5 style="margin-top: 15px;">TMD:</h5>
                          <div class="legend-item">
                            <input type="checkbox" name="clenching"> Clenching
                          </div>
                          <div class="legend-item">
                            <input type="checkbox" name="clicking"> Clicking
                          </div>
                        </div>
                      </div>
                    </div>

                    <!-- Remarks Section -->
                    <div class="remarks-section">
                      <label for="dentalRemarks">Remarks:</label>
                      <textarea id="dentalRemarks" name="remarks" placeholder="Enter any additional notes or observations..."></textarea>
                    </div>

                    <!-- Action Buttons -->
                    <div class="action-buttons">
                      <button type="button" class="dental-btn dental-btn-secondary" onclick="clearDentalForm()">
                        Clear Form
                      </button>
                      <button type="submit" class="dental-btn dental-btn-primary">
                        Submit Dental Record
                      </button>
                    </div>
                  </form>
                  <!-- END DENTAL RECORD FORM -->
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
  <?php include '../admin/includes/addPatient.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script>

  <script>
    // Initialize variables with fallbacks to prevent undefined errors
    let patientsData = [];
    let loggedInPhysician = {};
    let medicineInventory = [];

    try {
        <?php 
        $patientsJson = is_array($patients) ? json_encode($patients, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '[]';
        $physicianJson = json_encode($loggedInPhysician, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $medicinesJson = is_array($medicines) ? json_encode($medicines, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '[]';
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("JSON encoding error: " . json_last_error_msg());
        }
        ?>
        
        patientsData = <?php echo $patientsJson; ?>;
        loggedInPhysician = <?php echo $physicianJson; ?>;
        medicineInventory = <?php echo $medicinesJson; ?>;
        
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
        
        console.log('Data loaded successfully');
        console.log('Patients count:', patientsData.length);
        console.log('Physician:', loggedInPhysician);
        console.log('Medicines count:', medicineInventory.length);
        
    } catch (error) {
        console.error('CRITICAL ERROR loading page data:', error);
        console.error('Error details:', error.message);
        console.error('Stack trace:', error.stack);
        
        patientsData = patientsData || [];
        loggedInPhysician = loggedInPhysician || { id: 0, name: 'Unknown' };
        medicineInventory = medicineInventory || [];
        
        alert('Warning: Some data failed to load. The page may not function correctly. Error: ' + error.message);
    }

    // DENTAL FORM FUNCTIONS
    const dentalOptions = [
        { value: '', text: '-' },
        { value: 'X', text: 'X' },
        { value: 'C', text: 'C' },
        { value: 'RF', text: 'RF' },
        { value: 'M', text: 'M' },
        { value: 'Im', text: 'Im' },
        { value: 'Un', text: 'Un' },
        { value: '√', text: '√' },
        { value: 'Cm', text: 'Cm' },
        { value: 'Sp', text: 'Sp' },
        { value: 'JC', text: 'JC' },
        { value: 'Am', text: 'Am' },
        { value: 'Comp', text: 'Comp' },
        { value: 'TF', text: 'TF' },
        { value: 'S', text: 'S' },
        { value: 'In', text: 'In' },
        { value: 'AB', text: 'AB' },
        { value: 'P', text: 'P' },
        { value: 'RPD', text: 'RPD' },
        { value: 'CD', text: 'CD' },
        { value: 'FB', text: 'FB' }
    ];

    function createTooth(number, showNumberTop = true) {
        const container = document.createElement('div');
        container.className = 'tooth-container';
        
        if (showNumberTop) {
            const numDiv = document.createElement('div');
            numDiv.className = 'tooth-number';
            numDiv.textContent = number;
            container.appendChild(numDiv);
        }
        
        const diagram = document.createElement('div');
        diagram.className = 'tooth-diagram';
        diagram.onclick = function() { toggleTooth(this); };
        container.appendChild(diagram);
        
        const select = document.createElement('select');
        select.className = 'tooth-select';
        select.name = `tooth_${number}_status`;
        
        dentalOptions.forEach(option => {
            const opt = document.createElement('option');
            opt.value = option.value;
            opt.textContent = option.text;
            select.appendChild(opt);
        });
        
        container.appendChild(select);
        
        if (!showNumberTop) {
            const numDiv = document.createElement('div');
            numDiv.className = 'tooth-number';
            numDiv.textContent = number;
            container.appendChild(numDiv);
        }
        
        return container;
    }

    function toggleTooth(element) {
        element.classList.toggle('marked');
    }

    function addDentalTreatmentRow() {
        const tbody = document.getElementById('treatmentBody');
        const newRow = document.createElement('tr');
        const dentistName = '<?php echo htmlspecialchars($fullName ?? ''); ?>';
        newRow.innerHTML = `
            <td><input type="date" name="treatment_date[]"></td>
            <td><input type="text" name="treatment_tooth[]"></td>
            <td><input type="text" name="treatment_operation[]"></td>
            <td><input type="text" name="treatment_dentist[]" value="${dentistName}"></td>
        `;
        tbody.appendChild(newRow);
    }

    function clearDentalForm() {
        if (confirm('Clear all form data except patient information?')) {
            const form = document.getElementById('dentalConsultationForm');
            const readonlyFields = form.querySelectorAll('input[readonly]');
            const readonlyValues = {};
            
            readonlyFields.forEach(field => {
                readonlyValues[field.name] = field.value;
            });
            
            form.reset();
            
            readonlyFields.forEach(field => {
                field.value = readonlyValues[field.name];
            });
            
            document.querySelectorAll('.tooth-diagram.marked').forEach(tooth => {
                tooth.classList.remove('marked');
            });
        }
    }

    function initializeDentalChart() {
        console.log('Initializing dental chart...');
        
        const upperTemp = document.getElementById('upperTempTeeth');
        if (upperTemp) {
            upperTemp.innerHTML = '';
            [55, 54, 53, 52, 51, 61, 62, 63, 64, 65].forEach(num => {
                upperTemp.appendChild(createTooth(num, true));
            });
        }
        
        const upperPerm = document.getElementById('upperPermTeeth');
        if (upperPerm) {
            upperPerm.innerHTML = '';
            [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28].forEach(num => {
                upperPerm.appendChild(createTooth(num, true));
            });
        }
        
        const lowerPerm = document.getElementById('lowerPermTeeth');
        if (lowerPerm) {
            lowerPerm.innerHTML = '';
            [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38].forEach(num => {
                lowerPerm.appendChild(createTooth(num, false));
            });
        }
        
        const lowerTemp = document.getElementById('lowerTempTeeth');
        if (lowerTemp) {
            lowerTemp.innerHTML = '';
            [85, 84, 83, 82, 81, 71, 72, 73, 74, 75].forEach(num => {
                lowerTemp.appendChild(createTooth(num, false));
            });
        }
        
        console.log('Dental chart initialized successfully');
    }

    function loadPatientDataIntoDentalForm(patient) {
        console.log('Loading patient data into dental form:', patient);
        
        const patientIdField = document.getElementById('consultationPatientId');
        if (patientIdField) {
            patientIdField.value = patient.id;
        }
        
        const nameField = document.getElementById('consultFullName');
        if (nameField) {
            nameField.value = patient.full_name || '';
        }
        
        const srCodeField = document.getElementById('consultSRCode');
        if (srCodeField) {
            srCodeField.value = patient.sr_code || '';
        }
        
        const addressField = document.getElementById('consultAddress');
        if (addressField) {
            addressField.value = patient.address || '';
        }
        
        const programField = document.getElementById('consultProgram');
        if (programField) {
            programField.value = patient.position || '';
        }
        
        console.log('Patient data loaded into dental form');
    }

    // Make patient cards clickable
    document.addEventListener('DOMContentLoaded', function() {
        console.log('DOM Content Loaded - Setting up patient cards');
        
        // Initialize dental chart
        initializeDentalChart();
        
        // Setup index table auto-calculation
        setupIndexTableCalculations();
        
        const patientCards = document.querySelectorAll('.patient-card');
        console.log('Found', patientCards.length, 'patient cards');
        
        patientCards.forEach((card, index) => {
            card.addEventListener('click', function(e) {
                e.preventDefault();
                const patientId = this.getAttribute('data-patient-id');
                console.log('Patient card clicked:', patientId);
                loadPatientDetails(patientId);
            });
        });
        
        // Search functionality
        const searchInput = document.getElementById('searchPatients');
        if (searchInput) {
            searchInput.addEventListener('input', function(e) {
                const searchTerm = e.target.value.toLowerCase();
                
                patientCards.forEach(card => {
                    const patientName = card.querySelector('.patient-name').textContent.toLowerCase();
                    const srCode = card.querySelector('.patient-meta').textContent.toLowerCase();
                    
                    if (patientName.includes(searchTerm) || srCode.includes(searchTerm)) {
                        card.style.display = 'flex';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        }
        
        // Tab switching for detail view
        const tabButtons = document.querySelectorAll('.tab-item');
        tabButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const tabName = this.getAttribute('data-tab');
                console.log('Tab clicked:', tabName);
                switchTab(tabName);
            });
        });

        // Handle dental form submission
        const dentalForm = document.getElementById('dentalConsultationForm');
        if (dentalForm) {
            dentalForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                
                Swal.fire({
                    title: 'Saving Dental Record',
                    text: 'Please wait...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                
                fetch('../crud/save_dental_record.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success',
                            text: 'Dental record saved successfully!',
                            timer: 2000
                        }).then(() => {
                            const patientId = document.getElementById('consultationPatientId').value;
                            if (patientId) {
                                loadMedicalRecords(patientId);
                                switchTab('medical');
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to save dental record'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error saving dental record:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred while saving the dental record'
                    });
                });
            });
        }
    });

    // Load patient details
    function loadPatientDetails(patientId) {
        console.log('Loading patient details for ID:', patientId);
        console.log('Available patients:', patientsData);
        
        const patient = patientsData.find(p => p.id == patientId);
        
        if (!patient) {
            console.error('Patient not found:', patientId);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Patient not found!'
            });
            return;
        }
        
        console.log('Found patient:', patient);
        
        const patientsSection = document.getElementById('patientsSection');
        const detailSection = document.getElementById('patientDetailSection');
        
        console.log('Hiding patients section, showing detail section');
        patientsSection.classList.add('hidden');
        detailSection.classList.add('active');
        
        document.getElementById('detailInitials').textContent = getInitials(patient.full_name);
        document.getElementById('detailName').textContent = patient.full_name;
        document.getElementById('detailSRCode').textContent = 'SR-Code: ' + patient.sr_code;
        document.getElementById('detailPosition').textContent = patient.position;
        
        loadPersonalInfo(patient);
        loadMedicalRecords(patientId);
        loadPatientDataIntoDentalForm(patient);
        
        switchTab('info');
    }

    function closePatientDetail() {
        console.log('Closing patient detail view');
        const patientsSection = document.getElementById('patientsSection');
        const detailSection = document.getElementById('patientDetailSection');
        
        patientsSection.classList.remove('hidden');
        detailSection.classList.remove('active');
    }

    function loadPersonalInfo(patient) {
        console.log('Loading personal info for:', patient.full_name);
        const infoTab = document.getElementById('infoTab');
        
        infoTab.innerHTML = `
            <div class="form-section">
                <h4 class="section-title">Personal Information</h4>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Full Name</label>
                        <p>${patient.full_name || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>SR-Code</label>
                        <p>${patient.sr_code || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>Position</label>
                        <p>${patient.position || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>Date of Birth</label>
                        <p>${patient.date_of_birth ? formatDate(patient.date_of_birth) : 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>Age</label>
                        <p>${patient.age || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>Sex</label>
                        <p>${patient.sex || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>Contact Number</label>
                        <p>${patient.contact_number || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>Emergency Contact</label>
                        <p>${patient.emergency_contact || 'N/A'}</p>
                    </div>
                    <div class="info-item full-width">
                        <label>Address</label>
                        <p>${patient.address || 'N/A'}</p>
                    </div>
                </div>
            </div>
        `;
    }

    function loadMedicalRecords(patientId) {
        console.log('Loading medical records for patient ID:', patientId);
        const container = document.getElementById('medicalRecordsContainer');
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">Loading medical records...</p>';
        
        fetch(`../crud/medical_records.php?patient_id=${patientId}`)
            .then(response => {
                console.log('Fetch response status:', response.status);
                return response.json();
            })
            .then(data => {
                console.log('Medical records data:', data);
                if (data.success && data.records && data.records.length > 0) {
                    container.innerHTML = data.records.map(record => `
                        <div class="medical-record-card">
                            <div class="record-header">
                                <h5>${formatDate(record.visit_date)}</h5>
                                <span class="badge bg-primary">${record.purpose || 'General Checkup'}</span>
                            </div>
                            <div class="record-body">
                                <p><strong>Chief Complaint:</strong> ${record.chief_complaint || 'N/A'}</p>
                                <p><strong>Diagnosis:</strong> ${record.diagnosis || 'N/A'}</p>
                                <p><strong>Treatment:</strong> ${record.treatment || 'N/A'}</p>
                                <p><strong>Physician:</strong> ${record.physician_name || 'N/A'}</p>
                                ${record.notes ? `<p><strong>Notes:</strong> ${record.notes}</p>` : ''}
                            </div>
                        </div>
                    `).join('');
                } else {
                    container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No medical records found.</p>';
                }
            })
            .catch(error => {
                console.error('Error loading medical records:', error);
                container.innerHTML = '<p style="text-align: center; color: #dc3545; padding: 40px;">Error loading medical records. Please try again.</p>';
            });
    }

    function switchTab(tabName) {
        console.log('Switching to tab:', tabName);
        
        document.querySelectorAll('.tab-item').forEach(tab => {
            tab.classList.remove('active');
        });
        
        document.querySelectorAll('.tab-content-inner').forEach(content => {
            content.classList.remove('active');
        });
        
        const tabs = document.querySelectorAll('.tab-item');
        tabs.forEach(tab => {
            const tabData = tab.getAttribute('data-tab');
            if (tabData === tabName) {
                tab.classList.add('active');
            }
        });
        
        if (tabName === 'info') {
            document.getElementById('infoTab').classList.add('active');
        } else if (tabName === 'medical') {
            document.getElementById('medicalTab').classList.add('active');
        } else if (tabName === 'new-record') {
            document.getElementById('newRecordTab').classList.add('active');
        }
    }

    function getInitials(name) {
        const words = name.trim().split(' ');
        if (words.length >= 2) {
            return (words[0].charAt(0) + words[words.length - 1].charAt(0)).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
    }

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const date = new Date(dateString);
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
    }

    // Setup auto-calculation for index tables
    function setupIndexTableCalculations() {
        // Temporary teeth calculations
        for (let i = 1; i <= 6; i++) {
            const decayedInput = document.querySelector(`input[name="temp_decayed_${i}"]`);
            const filledInput = document.querySelector(`input[name="temp_filled_${i}"]`);
            const totalInput = document.querySelector(`input[name="temp_total_${i}"]`);
            
            if (decayedInput && filledInput && totalInput) {
                const calculateTempTotal = () => {
                    const decayed = parseInt(decayedInput.value) || 0;
                    const filled = parseInt(filledInput.value) || 0;
                    totalInput.value = decayed + filled;
                };
                
                decayedInput.addEventListener('input', calculateTempTotal);
                filledInput.addEventListener('input', calculateTempTotal);
            }
        }
        
        // Permanent teeth calculations
        for (let i = 1; i <= 4; i++) {
            const dInput = document.querySelector(`input[name="perm_d_${i}"]`);
            const mInput = document.querySelector(`input[name="perm_m_${i}"]`);
            const fInput = document.querySelector(`input[name="perm_f_${i}"]`);
            const totalInput = document.querySelector(`input[name="perm_total_${i}"]`);
            
            if (dInput && mInput && fInput && totalInput) {
                const calculatePermTotal = () => {
                    const d = parseInt(dInput.value) || 0;
                    const m = parseInt(mInput.value) || 0;
                    const f = parseInt(fInput.value) || 0;
                    totalInput.value = d + m + f;
                };
                
                dInput.addEventListener('input', calculatePermTotal);
                mInput.addEventListener('input', calculatePermTotal);
                fInput.addEventListener('input', calculatePermTotal);
            }
        }
    }
  </script>
</body>
</html>
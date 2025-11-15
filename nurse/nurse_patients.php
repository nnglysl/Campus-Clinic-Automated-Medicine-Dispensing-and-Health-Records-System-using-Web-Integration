<?php
require_once '../config/database.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

// Define user info safely
$user_id = $_SESSION['user_id'];
$first_name = $_SESSION['fname'] ?? 'Staff';
$last_name = $_SESSION['lname'] ?? '';
$user_role = $_SESSION['role'] ?? 'staff';
$fullName = trim($first_name . ' ' . $last_name);

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
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
    $patients = [];
}

// Fetch all medicines from inventory
$medicines = [];
try {
    $stmt = $pdo->query("SELECT id, batchId, code, name, quantity, dispensed, expiry, description, status FROM inventory WHERE quantity > 0 AND status = 'active' ORDER BY name");
    $medicines = $stmt->fetchAll();
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
  <link rel="stylesheet" href="../nurse/css/nurse_patients.css" />
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
      <a href="../nurse/nurse_dashboard.php" class="menu-item">Dashboard</a>
      <a href="../nurse/nurse_profile.php" class="menu-item">Profile</a>
      <a href="../nurse/nurse_patients.php" class="menu-item active">Patients</a>
      <a href="../nurse/settings.php" class="menu-item">Settings</a>

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
                  <button class="tab-item" data-tab="new-record">New Medical Consultation</button>
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
                  <!-- Medical Consultation Form -->
                  <div class="consultation-form-container">
                    <div class="consultation-form-header">
                      <h3>MEDICAL CONSULTATION FORM</h3>
                    </div>

                    <form id="medicalConsultationForm" class="medical-form">
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

                      <!-- Nurse's Assessment Section -->
                      <div class="consultation-section">
                        <h4 class="consultation-section-title">Nurse's Assessment</h4>
                        <div class="form-row">
                          <div class="form-group">
                            <label>Date <span class="required">*</span></label>
                            <input type="date" name="assessment_date" required>
                          </div>
                          <div class="form-group">
                            <label>Control Number</label>
                            <input type="text" name="control_number" placeholder="Enter control number">
                          </div>
                        </div>
                        <div class="form-row">
                          <div class="form-group">
                            <label>Age</label>
                            <input type="number" name="age" id="consultAge" readonly class="readonly-field">
                          </div>
                          <div class="form-group">
                            <label>Sex</label>
                            <input type="text" name="sex" id="consultSex" readonly class="readonly-field">
                          </div>
                        </div>
                        <div class="form-row full">
                          <div class="form-group">
                            <label>Purpose of Consultation <span class="required">*</span></label>
                            <input type="text" name="purpose" placeholder="Enter purpose of visit" required>
                          </div>
                        </div>
                      </div>

                      <!-- Vital Signs Section -->
                      <div class="consultation-section">
                        <h4 class="consultation-section-title">Vital Signs</h4>
                        <div class="vitals-grid">
                          <div class="form-group">
                            <label>Blood Pressure (BP)</label>
                            <input type="text" name="blood_pressure" placeholder="e.g., 120/80">
                          </div>
                          <div class="form-group">
                            <label>Pulse Rate (PR)</label>
                            <input type="text" name="pulse_rate" placeholder="e.g., 72 bpm">
                          </div>
                          <div class="form-group">
                            <label>SpO2</label>
                            <input type="text" name="spo2" placeholder="e.g., 98%">
                          </div>
                          <div class="form-group">
                            <label>Respiratory Rate (RR)</label>
                            <input type="text" name="respiratory_rate" placeholder="e.g., 16">
                          </div>
                          <div class="form-group">
                            <label>Temperature (T)</label>
                            <input type="text" name="temperature" placeholder="e.g., 36.5°C">
                          </div>
                        </div>
                      </div>

                      <!-- Measurements Section -->
                      <div class="consultation-section">
                        <h4 class="consultation-section-title">Measurements</h4>
                        <div class="measurements-grid">
                          <div class="form-group">
                            <label>Height (HT)</label>
                            <input type="text" name="height" id="consultHeight" placeholder="e.g., 165 cm">
                          </div>
                          <div class="form-group">
                            <label>Weight (WT)</label>
                            <input type="text" name="weight" id="consultWeight" placeholder="e.g., 60 kg">
                          </div>
                          <div class="form-group">
                            <label>BMI</label>
                            <input type="text" name="bmi" id="consultBMI" readonly placeholder="Auto-calculated" class="readonly-field">
                          </div>
                        </div>
                        <div class="form-row">
                          <div class="form-group">
                            <label>Last Menstrual Period</label>
                            <input type="date" name="last_menstrual_period">
                          </div>
                          <div class="form-group"></div>
                        </div>
                        <div class="form-row">
                          <div class="form-group">
                            <label>Vision - Right (R)</label>
                            <input type="text" name="vision_right" placeholder="e.g., 20/20">
                          </div>
                          <div class="form-group">
                            <label>Vision - Left (L)</label>
                            <input type="text" name="vision_left" placeholder="e.g., 20/20">
                          </div>
                        </div>
                      </div>

                      <div class="section-divider"></div>

                      <!-- Notes Section -->
                      <div class="consultation-section">
                        <h4 class="consultation-section-title">Clinical Notes</h4>
                        <div class="notes-container">
                          <div class="notes-section">
                            <h5>Nurse's Notes</h5>
                            <div class="form-group">
                              <textarea name="nurse_notes" rows="6" placeholder="Enter nurse's observations and notes here..."></textarea>
                            </div>
                          </div>
                          <div class="notes-section">
                            <h5>Doctor's Notes</h5>
                            <div class="form-group">
                              <label>Date</label>
                              <input type="date" name="doctor_date">
                            </div>
                            <div class="form-group">
                              <textarea name="doctor_notes" rows="6" placeholder="Enter doctor's diagnosis, treatment, and recommendations..."></textarea>
                            </div>
                          </div>
                        </div>
                      </div>

                      <!-- Submit Section -->
                      <div class="action-buttons">
                        <button type="button" class="btn-secondary" onclick="resetConsultationForm()">Reset Form</button>
                        <button type="submit" class="btn-primary">Submit Consultation Form</button>
                      </div>
                    </form>
                  </div>
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
    // Initialize variables
    let patientsData = [];
    let loggedInPhysician = {};
    let currentPatientData = null;

    try {
        <?php 
        $patientsJson = is_array($patients) ? json_encode($patients, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '[]';
        $physicianJson = json_encode($loggedInPhysician, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        ?>
        
        patientsData = <?php echo $patientsJson; ?>;
        loggedInPhysician = <?php echo $physicianJson; ?>;
        
        if (!Array.isArray(patientsData)) patientsData = [];
        if (!loggedInPhysician || typeof loggedInPhysician !== 'object') {
            loggedInPhysician = { id: 0, name: 'Unknown' };
        }
        
    } catch (error) {
        console.error('Error loading page data:', error);
        patientsData = [];
        loggedInPhysician = { id: 0, name: 'Unknown' };
    }

    // DOM Content Loaded
    document.addEventListener('DOMContentLoaded', function() {
        const patientCards = document.querySelectorAll('.patient-card');
        
        patientCards.forEach(card => {
            card.addEventListener('click', function(e) {
                e.preventDefault();
                const patientId = this.getAttribute('data-patient-id');
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
        
        // Tab switching
        const tabButtons = document.querySelectorAll('.tab-item');
        tabButtons.forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const tabName = this.getAttribute('data-tab');
                switchTab(tabName);
            });
        });

        // Form submission
        const consultationForm = document.getElementById('medicalConsultationForm');
        if (consultationForm) {
            consultationForm.addEventListener('submit', handleConsultationSubmit);
        }

        // BMI calculation
        const heightInput = document.getElementById('consultHeight');
        const weightInput = document.getElementById('consultWeight');
        if (heightInput && weightInput) {
            heightInput.addEventListener('input', calculateBMI);
            weightInput.addEventListener('input', calculateBMI);
        }
    });

    // Load patient details
    function loadPatientDetails(patientId) {
        const patient = patientsData.find(p => p.id == patientId);
        
        if (!patient) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Patient not found!'
            });
            return;
        }
        
        currentPatientData = patient;
        
        const patientsSection = document.getElementById('patientsSection');
        const detailSection = document.getElementById('patientDetailSection');
        
        patientsSection.classList.add('hidden');
        detailSection.classList.add('active');
        
        document.getElementById('detailInitials').textContent = getInitials(patient.full_name);
        document.getElementById('detailName').textContent = patient.full_name;
        document.getElementById('detailSRCode').textContent = 'SR-Code: ' + patient.sr_code;
        document.getElementById('detailPosition').textContent = patient.position;
        
        loadPersonalInfo(patient);
        loadMedicalRecords(patientId);
        populateConsultationForm(patient);
        
        switchTab('info');
    }

    // Close patient detail
    function closePatientDetail() {
        const patientsSection = document.getElementById('patientsSection');
        const detailSection = document.getElementById('patientDetailSection');
        
        patientsSection.classList.remove('hidden');
        detailSection.classList.remove('active');
        currentPatientData = null;
    }

    // Load personal information
    function loadPersonalInfo(patient) {
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

    // Load medical records
    function loadMedicalRecords(patientId) {
        const container = document.getElementById('medicalRecordsContainer');
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">Loading medical records...</p>';
        
        fetch(`../crud/medical_records.php?patient_id=${patientId}`)
            .then(response => response.json())
            .then(data => {
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
                container.innerHTML = '<p style="text-align: center; color: #dc3545; padding: 40px;">Error loading medical records.</p>';
            });
    }

    // Populate consultation form
    function populateConsultationForm(patient) {
        document.getElementById('consultationPatientId').value = patient.id;
        document.getElementById('consultFullName').value = patient.full_name || '';
        document.getElementById('consultSRCode').value = patient.sr_code || '';
        document.getElementById('consultAddress').value = patient.address || '';
        document.getElementById('consultProgram').value = patient.position || '';
        document.getElementById('consultAge').value = patient.age || '';
        document.getElementById('consultSex').value = patient.sex || '';
    }

    // Calculate BMI
    function calculateBMI() {
        const heightInput = document.getElementById('consultHeight');
        const weightInput = document.getElementById('consultWeight');
        const bmiInput = document.getElementById('consultBMI');
        
        const height = parseFloat(heightInput.value);
        const weight = parseFloat(weightInput.value);
        
        if (height > 0 && weight > 0) {
            const heightInMeters = height / 100;
            const bmi = (weight / (heightInMeters * heightInMeters)).toFixed(2);
            bmiInput.value = bmi;
        } else {
            bmiInput.value = '';
        }
    }

    // Handle consultation form submission
    function handleConsultationSubmit(e) {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        
        Swal.fire({
            title: 'Submitting...',
            text: 'Please wait while we save the consultation record.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });
        
        fetch('../crud/save_consultation.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Medical consultation record saved successfully.',
                    confirmButtonColor: '#8b2332'
                }).then(() => {
                    resetConsultationForm();
                    if (currentPatientData) {
                        loadMedicalRecords(currentPatientData.id);
                    }
                    switchTab('medical');
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to save consultation record.',
                    confirmButtonColor: '#8b2332'
                });
            }
        })
        .catch(error => {
            console.error('Error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'An error occurred while saving the consultation record.',
                confirmButtonColor: '#8b2332'
            });
        });
    }

    // Reset consultation form
    function resetConsultationForm() {
        const form = document.getElementById('medicalConsultationForm');
        if (form) {
            form.reset();
            if (currentPatientData) {
                populateConsultationForm(currentPatientData);
            }
        }
    }

    // Switch tabs
    function switchTab(tabName) {
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

    // Helper functions
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
  </script>
</body>
</html>
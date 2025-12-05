<?php
require_once '../config/database.php';
require_once '../includes/patient_sync.php';
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
syncPatientRecords($pdo);

// Fetch all patients with error handling
$patients = [];
try {
    $stmt = $pdo->query("SELECT * FROM patients ORDER BY full_name ASC");
    $patients = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
    $patients = [];
}

foreach ($patients as &$patient) {
    $patient['position'] = $patient['program'] ?? 'Student';
    $patient['phone'] = $patient['contact_number'] ?? null;
    // Remove civil_status as it's not used in patient records
    unset($patient['civil_status']);
}
unset($patient);

// Fetch all medicines from inventory that are available to dispense (not expired, including near expiration)
$medicines = [];
try {
    $stmt = $pdo->query("
        SELECT id, batch_number, item_code, item_name, quantity, dispensed, expiry_date, description, status 
        FROM inventory 
        WHERE status = 'active' 
        AND expiry_date >= CURDATE() 
        AND quantity > 0 
        ORDER BY item_name ASC, expiry_date ASC
    ");
    $medicines = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching medicines: " . $e->getMessage());
    $medicines = [];
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
  <link rel="stylesheet" href="../nurse/css/responsive.css" />
  <link rel="stylesheet" href="../admin/css/notifications.css" />
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
      <!-- Mobile Menu Icon -->
      <button type="button" class="mobile-menu-icon" id="mobileMenuBtn" aria-label="Toggle navigation menu" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>
      <?php include 'notification_component.php'; ?>
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
      <a href="../nurse/nurse_settings.php" class="menu-item">Settings</a>

      <div class="user-profile">
        <span><?php echo htmlspecialchars($fullName); ?></span>
      </div>
    </div>

    <div class="main-content">
      <h2 class="page-title">Patients</h2>
      
      <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="searchPatients" placeholder="Search patients...">
      </div>

      <div class="content-wrapper">
        <div class="patients-section" id="patientsSection">
          <div class="patients-header">
            <h3>All Patients</h3>
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
              <button class="tab-item" data-tab="medical">Patient Records</button>
              <button class="tab-item" data-tab="new-record">Patient Consultation</button>
            </div>

            <div class="tab-content-inner active" id="infoTab">
              <!-- Personal Information will be loaded dynamically -->
            </div>

            <div class="tab-content-inner" id="medicalTab">
              <div class="form-section">
                <h4 class="section-title-medical-history">Medical History</h4>
                <div id="medicalRecordsContainer">
                  <p style="text-align: center; color: #999; padding: 40px;">No medical records found.</p>
                </div>
              </div>
            </div>

            <div class="tab-content-inner" id="newRecordTab">
              <!-- Medical Consultation Form -->
              <div class="consultation-form-container">
                <div class="document-header">
                  <div class="header-top">
                    <div class="header-logo">
                      <img src="../img/bsu-logo.png" alt="BSU Logo" style="width: 80px; height: 80px; object-fit: contain;">
                    </div>
                    <div class="header-info-boxes">
                      <div class="info-box">
                        <label>Reference No.:</label>
                        <span>BatStateU-FO-HSD-12</span>
                      </div>
                      <div class="info-box">
                        <label>Effectivity Date:</label>
                        <span>May 18, 2022</span>
                      </div>
                      <div class="info-box">
                        <label>Revision No.:</label>
                        <span>01</span>
                      </div>
                    </div>
                  </div>
                  <div class="header-title">
                    <h2>MEDICAL CONSULTATION FORM</h2>
                  </div>
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
                    <div class="form-row">
                      <div class="form-group">
                        <label>Age</label>
                        <input type="number" name="age" id="consultAge" readonly class="readonly-field">
                      </div>
                      <div class="form-group">
                        <label>Gender</label>
                        <input type="text" name="sex" id="consultSex" readonly class="readonly-field">
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

                  <!-- Treatment Plan Section -->
                  <div class="consultation-section">
                    <h4 class="consultation-section-title">Treatment Plan</h4>
                    
                    <div id="medicinesContainer">
                      <div class="medicine-row">
                        <div class="form-group" style="flex: 2; position: relative;">
                          <label>Medicine</label>
                          <input type="text" 
                                 class="medicine-search-input" 
                                 data-index="0"
                                 placeholder="Type to search medicine..."
                                 autocomplete="off">
                          <input type="hidden" name="medicines[0][inventory_id]" class="medicine-hidden-id">
                          <div class="medicine-suggestions" id="medicineSuggestions0" style="display: none;"></div>
                        </div>
                        <div class="form-group">
                          <label>Quantity</label>
                          <input type="number" name="medicines[0][quantity]" min="1" placeholder="Enter quantity">
                        </div>
                        <div class="form-group" style="flex: 0 0 auto; padding-top: 28px;">
                          <button type="button" class="btn-icon btn-remove-medicine" onclick="removeMedicineRow(this)" style="display: none;">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </div>

                      <div class="form-row full" style="margin-top: 20px;">
                        <div class="form-group">
                          <label>Treatment Instructions</label>
                          <textarea name="treatment_instructions" rows="4" placeholder="e.g., Rest for 24 hours, drink plenty of fluids..."></textarea>
                        </div>
                      </div>
                      <button type="button" class="btn-add-medicine" onclick="addMedicineRow()">
                        <i class="bi bi-plus-circle"></i> Add Another Medicine
                      </button>
                    </div>
                  </div>

                  <!-- Submit Section -->
                  <div class="action-buttons">
                    <button type="submit" class="btn btn-primary" style="background: #8B0000; border: none;">
                      <i class="bi bi-check-circle me-1"></i>Save Record
                    </button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script>
  <script src="js/notifications.js"></script>

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
        
        // Store medicines data for autocomplete
        <?php 
        $medicinesJson = is_array($medicines) ? json_encode($medicines, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) : '[]';
        ?>
        window.medicinesData = <?php echo $medicinesJson; ?>;
        if (!Array.isArray(window.medicinesData)) window.medicinesData = [];
        
    } catch (error) {
        console.error('Error loading page data:', error);
        patientsData = [];
        loggedInPhysician = { id: 0, name: 'Unknown' };
        window.medicinesData = [];
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
    const firstName = patient.fname || '';
    const middleName = patient.mname || '';
    const lastName = patient.lname || '';
    const gender = patient.sex || patient.gender || 'N/A';
    const age = patient.age || calculateAge(patient.date_of_birth) || 'N/A';
    const guardianName = patient.guardian_name || patient.emergency_contact_name || 'N/A';
    const guardianRelationship = patient.guardian_relationship || patient.emergency_contact_relationship || 'N/A';
    const guardianPhone = patient.guardian_contact || patient.emergency_contact_phone || 'N/A';
        
        infoTab.innerHTML = `
            <div class="form-section">
                <h4 class="section-title">Personal Information</h4>
                <div class="info-grid">
                    <div class="info-item">
                    <label>First Name</label>
                    <p>${firstName || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                    <label>Middle Name</label>
                    <p>${middleName || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                    <label>Last Name</label>
                    <p>${lastName || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>Date of Birth</label>
                        <p>${patient.date_of_birth ? formatDate(patient.date_of_birth) : 'N/A'}</p>
                    </div>
                    <div class="info-item">
                        <label>Age</label>
                    <p>${age}</p>
                    </div>
                    <div class="info-item">
                    <label>Gender</label>
                    <p>${gender}</p>
                    </div>
                    <div class="info-item">
                        <label>Contact Number</label>
                        <p>${patient.contact_number || 'N/A'}</p>
                    </div>
                    <div class="info-item">
                    <label>SR-Code</label>
                    <p>${patient.sr_code || 'N/A'}</p>
                </div>
                <div class="info-item">
                    <label>Blood Type</label>
                    <p>${patient.blood_type || 'N/A'}</p>
                </div>
                    </div>
            
            <div class="info-grid-2" style="margin-top: 15px;">
                <div class="info-item">
                        <label>Address</label>
                        <p>${patient.address || 'N/A'}</p>
                    </div>
                <div class="info-item">
                    <label>Email Address</label>
                    <p>${patient.email || 'N/A'}</p>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <h4 class="section-title">Emergency Contact Information</h4>
            <div class="info-grid">
                <div class="info-item">
                    <label>Guardian Name</label>
                    <p>${guardianName}</p>
                </div>
                <div class="info-item">
                    <label>Relationship</label>
                    <p>${guardianRelationship}</p>
                </div>
                <div class="info-item">
                    <label>Contact Number</label>
                    <p>${guardianPhone}</p>
                </div>
            </div>
        </div>
        
        <div class="form-section">
            <h4 class="section-title">Medical Information</h4>
            <div class="info-grid-2">
                <div class="info-item">
                    <label>Allergies</label>
                    <p>${patient.allergies || 'None'}</p>
                </div>
                <div class="info-item">
                    <label>Medical Conditions</label>
                    <p>${patient.medical_condition || 'None'}</p>
                </div>
                </div>
            </div>
        `;
}
    
// Improved calculateAge function
function calculateAge(dateOfBirth) {
    if (!dateOfBirth) return null;
    
    try {
        const today = new Date();
        const birthDate = new Date(dateOfBirth);
        
        // Check if date is valid
        if (isNaN(birthDate.getTime())) {
            return null;
        }
        
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        
        return age;
    } catch (error) {
        console.error('Error calculating age:', error);
        return null;
    }
}

    // Load medical records
    function loadMedicalRecords(patientId) {
        const container = document.getElementById('medicalRecordsContainer');
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">Loading medical records...</p>';
        
        console.log('Loading medical records for patient_id:', patientId);
        
        fetch(`../crud/medical_records.php?patient_id=${patientId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Medical records API response:', data);
                console.log('Records count:', data.records ? data.records.length : 0);
                if (data.success && data.records && data.records.length > 0) {
                    // For nurses: only show consultation records (exclude dental and medical appointment records)
                    const medicalRecords = data.records.filter(record => {
                        // Exclude dental records
                        if (record.record_type === 'Dental' || record.is_dental === true || record.appointment_type === 'dental') {
                            return false;
                        }
                        // Only show consultation records - exclude appointment-based medical records
                        // Consultation records have is_consultation === true
                        // Medical appointment records have is_from_appointment === true or appointment_type === 'medical' without is_consultation
                        return record.is_consultation === true;
                    });
                    
                    if (medicalRecords.length > 0) {
                        container.innerHTML = medicalRecords.map((record, index) => {
                            const recordDate = formatDate(record.visit_date || record.assessment_date);
                            const recordType = record.record_type || (record.is_consultation ? 'Consultation' : 'Medical');
                            // Match the design: Medical = medium purple, Consultation = light blue
                            const typeBadgeClass = recordType === 'Consultation' ? 'badge-consultation' : 'badge-medical';
                            
                            return `
                                <div class="medical-record-list-item" style="cursor: pointer;" onclick="showMedicalRecordForm(${index})" data-record-index="${index}">
                                    <div class="record-item-date">
                                        <strong>${recordDate}</strong>
                                    </div>
                                    <div class="record-item-type">
                                        <span class="badge ${typeBadgeClass}">${recordType}</span>
                                    </div>
                                    <div class="record-item-arrow">
                                        <i class="bi bi-chevron-right"></i>
                                    </div>
                                </div>
                            `;
                        }).join('');
                        
                        // Store only medical/consultation records globally for modal access
                        window.medicalRecordsData = medicalRecords;
                    } else {
                        container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No consultation records found.</p>';
                        window.medicalRecordsData = [];
                    }
                } else {
                    container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No consultation records found.</p>';
                    window.medicalRecordsData = [];
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
    
    // FIX: Properly populate age field
    let age = '';
    if (patient.age && patient.age !== '' && patient.age !== null) {
        age = patient.age;
    } else if (patient.date_of_birth) {
        age = calculateAge(patient.date_of_birth) || '';
    }
    document.getElementById('consultAge').value = age;
    
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
        
        const form = e.target;
        const formData = new FormData(form);
        
        // Collect medicines data from autocomplete inputs and validate
        const medicines = [];
        const medicineRows = document.querySelectorAll('.medicine-row');
        
        // First, remove all existing medicine entries from FormData
        for (let i = 0; i < 100; i++) { // Remove up to 100 medicine entries
            formData.delete(`medicines[${i}][inventory_id]`);
            formData.delete(`medicines[${i}][quantity]`);
        }
        
        // Collect valid medicines
        medicineRows.forEach((row, index) => {
            const hiddenInput = row.querySelector('.medicine-hidden-id');
            const quantityInput = row.querySelector('input[name*="[quantity]"]');
            const searchInput = row.querySelector('.medicine-search-input');
            
            if (hiddenInput && quantityInput && searchInput) {
                const inventoryId = hiddenInput.value.trim();
                const quantity = quantityInput.value.trim();
                const medicineName = searchInput.value.trim();
                
                // Only include if medicine is selected and quantity is provided
                if (inventoryId && quantity && parseInt(quantity) > 0 && medicineName) {
                    // Add medicine data with sequential index
                    formData.append(`medicines[${medicines.length}][inventory_id]`, inventoryId);
                    formData.append(`medicines[${medicines.length}][quantity]`, quantity);
                    
                    medicines.push({
                        inventory_id: inventoryId,
                        quantity: quantity,
                        name: medicineName
                    });
                }
            }
        });
        
        // Validate that if medicine rows exist, at least one is filled
        if (medicineRows.length > 0 && medicines.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Validation Error',
                text: 'Please select a medicine and enter a quantity, or remove empty medicine rows.',
                confirmButtonColor: '#8b2332'
            });
            return;
        }
        
        // Log form data for debugging
        console.log('Submitting consultation form with medicines:', medicines);
        console.log('FormData entries:', Array.from(formData.entries()));
        
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
        .then(response => {
            // Check if response is OK
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            // Try to parse as JSON
            return response.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    console.error('Response is not JSON:', text);
                    throw new Error('Server returned invalid response. Please check server logs.');
                }
            });
        })
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
                text: error.message || 'An error occurred while saving the consultation record. Please check the console for details.',
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
            // Reset medicines to single row
            const container = document.getElementById('medicinesContainer');
            if (container) {
                container.innerHTML = `
                    <div class="medicine-row">
                        <div class="form-group" style="flex: 2; position: relative;">
                            <label>Medicine</label>
                            <input type="text" 
                                   class="medicine-search-input" 
                                   data-index="0"
                                   placeholder="Type to search medicine..."
                                   autocomplete="off">
                            <input type="hidden" name="medicines[0][inventory_id]" class="medicine-hidden-id">
                            <div class="medicine-suggestions" id="medicineSuggestions0" style="display: none;"></div>
                        </div>
                        <div class="form-group">
                            <label>Quantity</label>
                            <input type="number" name="medicines[0][quantity]" min="1" placeholder="Enter quantity">
                        </div>
                        <div class="form-group" style="flex: 0 0 auto; padding-top: 28px;">
                            <button type="button" class="btn-icon btn-remove-medicine" onclick="removeMedicineRow(this)" style="display: none;">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-row full" style="margin-top: 20px;">
                        <div class="form-group">
                            <label>Treatment Instructions</label>
                            <textarea name="treatment_instructions" rows="4" placeholder="e.g., Rest for 24 hours, drink plenty of fluids..."></textarea>
                        </div>
                    </div>
                    <button type="button" class="btn-add-medicine" onclick="addMedicineRow()">
                        <i class="bi bi-plus-circle"></i> Add Another Medicine
                    </button>
                `;
                medicineRowCount = 1;
                updateRemoveButtons();
                // Initialize autocomplete for the reset row
                setTimeout(() => {
                    initializeAllMedicineAutocompletes();
                }, 100);
            }
        }
    }

    // Medicine row management
    let medicineRowCount = 1;

    function addMedicineRow() {
        const container = document.getElementById('medicinesContainer');
        // Find the last medicine-row before the treatment instructions section
        const existingRows = container.querySelectorAll('.medicine-row');
        const treatmentSection = container.querySelector('.form-row.full');
        const addButton = container.querySelector('.btn-add-medicine');
        
        const newRow = document.createElement('div');
        newRow.className = 'medicine-row';
        newRow.innerHTML = `
            <div class="form-group" style="flex: 2; position: relative;">
                <label>Medicine</label>
                <input type="text" 
                       class="medicine-search-input" 
                       data-index="${medicineRowCount}"
                       placeholder="Type to search medicine..."
                       autocomplete="off">
                <input type="hidden" name="medicines[${medicineRowCount}][inventory_id]" class="medicine-hidden-id">
                <div class="medicine-suggestions" id="medicineSuggestions${medicineRowCount}" style="display: none;"></div>
            </div>
            <div class="form-group">
                <label>Quantity</label>
                <input type="number" name="medicines[${medicineRowCount}][quantity]" min="1" placeholder="Enter quantity">
            </div>
            <div class="form-group" style="flex: 0 0 auto; padding-top: 28px;">
                <button type="button" class="btn-icon btn-remove-medicine" onclick="removeMedicineRow(this)">
                    <i class="bi bi-trash"></i>
                </button>
            </div>
        `;
        
        // Insert before the treatment instructions section
        if (treatmentSection) {
            container.insertBefore(newRow, treatmentSection);
        } else {
            container.appendChild(newRow);
        }
        
        // Initialize autocomplete for the new row
        const newInput = newRow.querySelector('.medicine-search-input');
        initializeMedicineAutocomplete(newInput);
        
        medicineRowCount++;
        updateRemoveButtons();
    }

    function removeMedicineRow(button) {
        const row = button.closest('.medicine-row');
        row.remove();
        updateRemoveButtons();
    }

    function updateRemoveButtons() {
        const rows = document.querySelectorAll('.medicine-row');
        rows.forEach((row, index) => {
            const removeBtn = row.querySelector('.btn-remove-medicine');
            if (rows.length === 1) {
                removeBtn.style.display = 'none';
            } else {
                removeBtn.style.display = 'block';
            }
        });
    }

    // Medicine search autocomplete functionality
    function initializeMedicineAutocomplete(inputElement) {
        const index = inputElement.getAttribute('data-index');
        const suggestionsId = `medicineSuggestions${index}`;
        const hiddenInput = inputElement.parentElement.querySelector('.medicine-hidden-id');
        const suggestionsContainer = document.getElementById(suggestionsId);
        
        let searchTimeout;
        let highlightedIndex = -1;
        
        inputElement.addEventListener('input', function(e) {
            const searchTerm = e.target.value.trim().toLowerCase();
            
            clearTimeout(searchTimeout);
            
            if (searchTerm.length < 1) {
                suggestionsContainer.style.display = 'none';
                hiddenInput.value = '';
                return;
            }
            
            // Search immediately for better responsiveness
            searchTimeout = setTimeout(() => {
                filterMedicines(searchTerm, suggestionsContainer, inputElement, hiddenInput);
            }, 50); // Reduced delay for faster search
        });
        
        inputElement.addEventListener('keydown', function(e) {
            const items = suggestionsContainer.querySelectorAll('.medicine-suggestion-item');
            
            if (items.length === 0) return;
            
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightedIndex = (highlightedIndex + 1) % items.length;
                updateHighlight(items, highlightedIndex);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightedIndex = highlightedIndex <= 0 ? items.length - 1 : highlightedIndex - 1;
                updateHighlight(items, highlightedIndex);
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (highlightedIndex >= 0 && items[highlightedIndex]) {
                    const item = items[highlightedIndex];
                    const medicineId = item.getAttribute('data-id');
                    const medicineName = item.getAttribute('data-name');
                    
                    if (medicineId && medicineName) {
                        inputElement.value = medicineName;
                        hiddenInput.value = medicineId;
                        hiddenInput.setAttribute('value', medicineId);
                        suggestionsContainer.style.display = 'none';
                        highlightedIndex = -1;
                        hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                    }
                }
            } else if (e.key === 'Escape') {
                suggestionsContainer.style.display = 'none';
                highlightedIndex = -1;
            }
        });
        
        // Handle suggestion selection
        suggestionsContainer.addEventListener('click', function(e) {
            const item = e.target.closest('.medicine-suggestion-item');
            if (item) {
                const medicineId = item.getAttribute('data-id');
                const medicineName = item.getAttribute('data-name');
                
                if (medicineId && medicineName) {
                    inputElement.value = medicineName;
                    hiddenInput.value = medicineId;
                    hiddenInput.setAttribute('value', medicineId); // Ensure value is set
                    suggestionsContainer.style.display = 'none';
                    highlightedIndex = -1;
                    
                    // Trigger change event to ensure form recognizes the value
                    hiddenInput.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        });
        
        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!inputElement.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                suggestionsContainer.style.display = 'none';
                highlightedIndex = -1;
            }
        });
    }
    
    function filterMedicines(searchTerm, suggestionsContainer, inputElement, hiddenInput) {
        if (!window.medicinesData || window.medicinesData.length === 0) {
            suggestionsContainer.innerHTML = '<div class="medicine-suggestion-empty">No medicines available</div>';
            suggestionsContainer.style.display = 'block';
            return;
        }
        
        const filtered = window.medicinesData.filter(medicine => {
            const name = (medicine.item_name || '').toLowerCase();
            const code = (medicine.item_code || '').toLowerCase();
            // quantity already represents available stock (decremented when dispensed)
            const available = medicine.quantity || 0;
            
            return (name.includes(searchTerm) || code.includes(searchTerm)) && available > 0;
        });
        
        if (filtered.length === 0) {
            suggestionsContainer.innerHTML = '<div class="medicine-suggestion-empty">No medicines found matching your search</div>';
            suggestionsContainer.style.display = 'block';
            return;
        }
        
        let html = '';
        filtered.forEach(medicine => {
            // quantity already represents available stock (decremented when dispensed)
            const available = medicine.quantity || 0;
            const expiryDate = medicine.expiry_date || '';
            const expiryDisplay = expiryDate ? new Date(expiryDate).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' }) : '';
            const daysUntilExpiry = expiryDate ? Math.floor((new Date(expiryDate) - new Date()) / (1000 * 60 * 60 * 24)) : null;
            const isNearingExpiry = daysUntilExpiry !== null && daysUntilExpiry <= 30 && daysUntilExpiry > 0;
            const isExpired = daysUntilExpiry !== null && daysUntilExpiry <= 0;
            const expiryClass = isNearingExpiry ? 'medicine-suggestion-expiry' : (isExpired ? 'medicine-suggestion-expired' : '');
            
            // Build expiry warning text
            let expiryWarning = '';
            if (expiryDate) {
                if (isExpired) {
                    expiryWarning = `<div class="${expiryClass}"><i class="bi bi-exclamation-triangle-fill"></i> <strong>EXPIRED</strong> - Expired on ${expiryDisplay}</div>`;
                } else if (isNearingExpiry) {
                    const daysText = daysUntilExpiry === 1 ? '1 day' : `${daysUntilExpiry} days`;
                    expiryWarning = `<div class="${expiryClass}"><i class="bi bi-exclamation-triangle-fill"></i> <strong>Nearing Expiry</strong> - Expires in ${daysText} (${expiryDisplay})</div>`;
                } else {
                    expiryWarning = `<div class="medicine-suggestion-expiry-normal">Expires: ${expiryDisplay}</div>`;
                }
            }
            
            html += `
                <div class="medicine-suggestion-item ${isNearingExpiry ? 'nearing-expiry-item' : ''} ${isExpired ? 'expired-item' : ''}" 
                     data-id="${medicine.id}" 
                     data-name="${medicine.item_name}">
                    <div class="medicine-suggestion-name">${medicine.item_name}</div>
                    <div class="medicine-suggestion-code">Code: ${medicine.item_code}</div>
                    <div class="medicine-suggestion-stock">Available: ${available}</div>
                    ${expiryWarning}
                </div>
            `;
        });
        
        suggestionsContainer.innerHTML = html;
        suggestionsContainer.style.display = 'block';
    }
    
    function updateHighlight(items, index) {
        items.forEach((item, i) => {
            if (i === index) {
                item.classList.add('highlighted');
                item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
            } else {
                item.classList.remove('highlighted');
            }
        });
    }
    
    // Initialize autocomplete for all medicine search inputs
    function initializeAllMedicineAutocompletes() {
        document.querySelectorAll('.medicine-search-input').forEach(input => {
            if (!input.hasAttribute('data-autocomplete-initialized')) {
                initializeMedicineAutocomplete(input);
                input.setAttribute('data-autocomplete-initialized', 'true');
            }
        });
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
            // Initialize medicine autocompletes when new record tab is shown
            setTimeout(() => {
                initializeAllMedicineAutocompletes();
            }, 100);
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

    // Show medical record form in modal (read-only view)
    function showMedicalRecordForm(index) {
        if (!window.medicalRecordsData || !window.medicalRecordsData[index]) {
            console.error('Medical record not found at index:', index);
            return;
        }

        const record = window.medicalRecordsData[index];
        const modalElement = document.getElementById('medicalRecordModal');
        if (!modalElement) {
            console.error('Modal element not found');
            return;
        }
        
        // Initialize or get existing modal instance
        let modal;
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        } else {
            console.error('Bootstrap is not loaded');
            return;
        }
        
        // Store current record index
        document.getElementById('medicalRecordModal').dataset.recordIndex = index;
        
        // Get patient data
        const patient = currentPatientData;
        
        // Populate patient information
        document.getElementById('viewFullName').value = patient?.full_name || record.patient_name || 'N/A';
        document.getElementById('viewSRCode').value = patient?.sr_code || 'N/A';
        document.getElementById('viewAddress').value = patient?.address || 'N/A';
        document.getElementById('viewProgram').value = patient?.program || patient?.position || 'N/A';
        // Age and Gender should come from patient data or record data, in Patient Information section
        document.getElementById('viewAge').value = patient?.age || record.age || '';
        document.getElementById('viewSex').value = patient?.sex || record.sex || '';
        
        // Determine record type - check if it's a consultation or appointment record
        const isConsultation = record.is_consultation === true;
        const isFromAppointment = record.is_from_appointment === true || 
                                  (record.appointment_type === 'medical' && !record.is_consultation);
        
        // Populate consultation form fields (read-only)
        if (isConsultation) {
            // It's a consultation - show consultation form
            const modalTitle = document.getElementById('modalRecordTitle');
            const modalRefNo = document.getElementById('modalReferenceNo');
            const modalHeaderTitle = document.getElementById('modalHeaderTitle');
            if (modalTitle) modalTitle.textContent = 'MEDICAL CONSULTATION RECORD';
            if (modalRefNo) modalRefNo.textContent = 'BatStateU-FO-HSD-12';
            if (modalHeaderTitle) modalHeaderTitle.textContent = 'Medical Consultation Record';
            
            document.getElementById('viewPurpose').value = record.purpose || '';
            document.getElementById('viewAssessmentDate').value = record.assessment_date || record.visit_date || '';
            document.getElementById('viewControlNumber').value = record.control_number || '';
            // Age and Gender are already populated in Patient Information section above
            // Update them here if record has different values, but they stay in Patient Information section
            if (record.age) document.getElementById('viewAge').value = record.age;
            if (record.sex) document.getElementById('viewSex').value = record.sex;
            document.getElementById('viewBloodPressure').value = record.blood_pressure || '';
            document.getElementById('viewPulseRate').value = record.pulse_rate || '';
            document.getElementById('viewSpo2').value = record.spo2 || '';
            document.getElementById('viewRespiratoryRate').value = record.respiratory_rate || '';
            document.getElementById('viewTemperature').value = record.temperature || '';
            document.getElementById('viewHeight').value = record.height || '';
            document.getElementById('viewWeight').value = record.weight || '';
            document.getElementById('viewBmi').value = record.bmi || '';
            document.getElementById('viewLastMenstrualPeriod').value = record.last_menstrual_period || '';
            document.getElementById('viewVisionRight').value = record.vision_right || '';
            document.getElementById('viewVisionLeft').value = record.vision_left || '';
            document.getElementById('viewNurseNotes').value = record.nurse_notes || '';
            document.getElementById('viewDoctorDate').value = record.doctor_date || '';
            document.getElementById('viewDoctorNotes').value = record.doctor_notes || '';
            
            // Treatment Plan - Always show section
            const treatmentInstructions = record.treatment_instructions || '';
            document.getElementById('viewTreatmentInstructions').value = treatmentInstructions;
            
            // Display medicines - show even if empty, matching doctor form structure
            const medicinesContainer = document.getElementById('viewMedicinesContainer');
            if (record.medicines && Array.isArray(record.medicines) && record.medicines.length > 0) {
                let medicinesHTML = '<div style="margin-bottom: 15px;"><strong>Medicines Dispensed:</strong></div>';
                medicinesHTML += '<div class="medicines-list" style="margin-bottom: 20px;">';
                record.medicines.forEach((medicine, index) => {
                    medicinesHTML += `
                        <div class="medicine-item" style="display: flex; gap: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; background: #f9f9f9;">
                            <div style="flex: 2;">
                                <strong>${medicine.item_name || 'Unknown Medicine'}</strong>
                                ${medicine.item_code ? `<div style="font-size: 0.9em; color: #666;">Code: ${medicine.item_code}</div>` : ''}
                                ${medicine.batch_number ? `<div style="font-size: 0.9em; color: #666;">Batch: ${medicine.batch_number}</div>` : ''}
                            </div>
                            <div style="flex: 1; text-align: right;">
                                <div><strong>Quantity:</strong> ${medicine.quantity || 0}</div>
                                ${medicine.dispensed_date ? `<div style="font-size: 0.9em; color: #666;">Date: ${new Date(medicine.dispensed_date).toLocaleDateString()}</div>` : ''}
                            </div>
                        </div>
                    `;
                });
                medicinesHTML += '</div>';
                medicinesContainer.innerHTML = medicinesHTML;
            } else {
                medicinesContainer.innerHTML = '<div style="color: #999; font-style: italic; margin-bottom: 20px;">No medicines dispensed</div>';
            }
            // Always show Treatment Plan section to match doctor form structure
            document.getElementById('viewTreatmentPlanSection').style.display = 'block';
            
            // Show consultation form, hide appointment form
            document.getElementById('consultationFormFields').style.display = 'block';
            document.getElementById('appointmentFormFields').style.display = 'none';
        } else if (isFromAppointment) {
            // It's a medical record from appointment - show appointment form
            const modalTitle = document.getElementById('modalRecordTitle');
            const modalRefNo = document.getElementById('modalReferenceNo');
            const modalHeaderTitle = document.getElementById('modalHeaderTitle');
            if (modalTitle) modalTitle.textContent = 'MEDICAL APPOINTMENT RECORD';
            if (modalRefNo) modalRefNo.textContent = 'BatStateU-FO-HSD-11';
            if (modalHeaderTitle) modalHeaderTitle.textContent = 'Medical Appointment Record';
            
            document.getElementById('viewAppointmentDate').value = record.visit_date || '';
            document.getElementById('viewAppointmentTime').value = record.visit_time || '';
            document.getElementById('viewAppointmentBP').value = record.blood_pressure || '';
            document.getElementById('viewAppointmentPR').value = record.pulse_rate || record.heart_rate || '';
            document.getElementById('viewAppointmentSpO2').value = record.spo2 || '';
            document.getElementById('viewAppointmentRR').value = record.respiratory_rate || '';
            document.getElementById('viewAppointmentTemp').value = record.temperature || '';
            document.getElementById('viewAppointmentHeight').value = record.height || '';
            document.getElementById('viewAppointmentWeight').value = record.weight || '';
            document.getElementById('viewAppointmentBMI').value = record.bmi || '';
            document.getElementById('viewDiagnosis').value = record.diagnosis || '';
            
            // Show appointment form, hide consultation form
            document.getElementById('consultationFormFields').style.display = 'none';
            document.getElementById('appointmentFormFields').style.display = 'block';
        } else {
            // Fallback: show consultation form as default
            const modalTitle = document.getElementById('modalRecordTitle');
            const modalRefNo = document.getElementById('modalReferenceNo');
            const modalHeaderTitle = document.getElementById('modalHeaderTitle');
            if (modalTitle) modalTitle.textContent = 'MEDICAL CONSULTATION RECORD';
            if (modalRefNo) modalRefNo.textContent = 'BatStateU-FO-HSD-12';
            if (modalHeaderTitle) modalHeaderTitle.textContent = 'Medical Consultation Record';
            
            document.getElementById('consultationFormFields').style.display = 'block';
            document.getElementById('appointmentFormFields').style.display = 'none';
        }
        
        // Show modal
        modal.show();
    }
    
    // Initialize notification system
    if (window.NurseNotificationSystem) {
      NurseNotificationSystem.init();
    }
  </script>

<script>
// Mobile Menu Toggle
document.addEventListener('DOMContentLoaded', () => {
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const sidebar = document.querySelector('.sidebar');
  const body = document.body;
  
  if (mobileMenuBtn && sidebar) {
    mobileMenuBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      const isActive = sidebar.classList.toggle('active');
      body.classList.toggle('menu-open');
      mobileMenuBtn.setAttribute('aria-expanded', isActive ? 'true' : 'false');
      
      const icon = mobileMenuBtn.querySelector('i');
      if (isActive) {
        icon.classList.remove('bi-list');
        icon.classList.add('bi-x-lg');
      } else {
        icon.classList.remove('bi-x-lg');
        icon.classList.add('bi-list');
      }
    });
    
    document.addEventListener('click', (e) => {
      if (sidebar.classList.contains('active') && 
          !sidebar.contains(e.target) && 
          !mobileMenuBtn.contains(e.target)) {
        closeMobileMenu();
      }
    });
    
    document.querySelectorAll('.sidebar .menu-item').forEach(item => {
      item.addEventListener('click', () => {
        closeMobileMenu();
      });
    });
    
    function closeMobileMenu() {
      sidebar.classList.remove('active');
      body.classList.remove('menu-open');
      mobileMenuBtn.setAttribute('aria-expanded', 'false');
      const icon = mobileMenuBtn.querySelector('i');
      icon.classList.remove('bi-x-lg');
      icon.classList.add('bi-list');
    }
    
    sidebar.addEventListener('click', (e) => {
      e.stopPropagation();
    });
    
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && sidebar.classList.contains('active')) {
        closeMobileMenu();
      }
    });
  }
});
</script>

  <!-- Medical Record View Modal -->
  <div class="modal fade" id="medicalRecordModal" tabindex="-1" aria-labelledby="medicalRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header" style="background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%); color: white;">
          <h5 class="modal-title" id="medicalRecordModalLabel">
            <i class="bi bi-file-medical me-2"></i><span id="modalHeaderTitle">Medical Consultation Record</span>
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="medicalRecordPrintContent">
          <div class="consultation-form-container">
            <div class="document-header">
              <div class="header-top">
                <div class="header-logo">
                  <img src="../img/bsu-logo.png" alt="BSU Logo" style="width: 80px; height: 80px; object-fit: contain;">
                </div>
                <div class="header-info-boxes">
                  <div class="info-box">
                    <label>Reference No.:</label>
                    <span id="modalReferenceNo">BatStateU-FO-HSD-12</span>
                  </div>
                  <div class="info-box">
                    <label>Effectivity Date:</label>
                    <span>May 18, 2022</span>
                  </div>
                  <div class="info-box">
                    <label>Revision No.:</label>
                    <span>01</span>
                  </div>
                </div>
              </div>
              <div class="header-title">
                <h2 id="modalRecordTitle">MEDICAL CONSULTATION RECORD</h2>
              </div>
            </div>

            <div class="medical-form">
              <!-- Patient Information Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Patient Information</h4>
                <div class="form-row">
                  <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" id="viewFullName" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>SR Code <span class="required">*</span></label>
                    <input type="text" id="viewSRCode" readonly class="readonly-field">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Address</label>
                    <input type="text" id="viewAddress" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Program</label>
                    <input type="text" id="viewProgram" readonly class="readonly-field">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Age</label>
                    <input type="number" id="viewAge" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Gender</label>
                    <input type="text" id="viewSex" readonly class="readonly-field">
                  </div>
                </div>
              </div>

              <!-- Consultation Form Fields -->
              <div id="consultationFormFields" style="display: none;">
                <div class="consultation-section">
                  <h4 class="consultation-section-title">Nurse's Assessment</h4>
                  <div class="form-row">
                    <div class="form-group">
                      <label>Date <span class="required">*</span></label>
                      <input type="date" id="viewAssessmentDate" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>Control Number</label>
                      <input type="text" id="viewControlNumber" readonly class="readonly-field">
                    </div>
                  </div>
                  <div class="form-row full">
                    <div class="form-group">
                      <label>Purpose of Consultation <span class="required">*</span></label>
                      <input type="text" id="viewPurpose" readonly class="readonly-field">
                    </div>
                  </div>
                </div>

                <div class="consultation-section">
                  <h4 class="consultation-section-title">Vital Signs</h4>
                  <div class="vitals-grid">
                    <div class="form-group">
                      <label>Blood Pressure (BP)</label>
                      <input type="text" id="viewBloodPressure" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>Pulse Rate (PR)</label>
                      <input type="text" id="viewPulseRate" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>SpO2</label>
                      <input type="text" id="viewSpo2" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>Respiratory Rate (RR)</label>
                      <input type="text" id="viewRespiratoryRate" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>Temperature (T)</label>
                      <input type="text" id="viewTemperature" readonly class="readonly-field">
                    </div>
                  </div>
                </div>

                <div class="consultation-section">
                  <h4 class="consultation-section-title">Measurements</h4>
                  <div class="measurements-grid">
                    <div class="form-group">
                      <label>Height (HT)</label>
                      <input type="text" id="viewHeight" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>Weight (WT)</label>
                      <input type="text" id="viewWeight" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>BMI</label>
                      <input type="text" id="viewBmi" readonly class="readonly-field">
                    </div>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label>Last Menstrual Period</label>
                      <input type="date" id="viewLastMenstrualPeriod" readonly class="readonly-field">
                    </div>
                    <div class="form-group"></div>
                  </div>
                  <div class="form-row">
                    <div class="form-group">
                      <label>Vision - Right (R)</label>
                      <input type="text" id="viewVisionRight" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>Vision - Left (L)</label>
                      <input type="text" id="viewVisionLeft" readonly class="readonly-field">
                    </div>
                  </div>
                </div>

                <div class="section-divider"></div>

                <!-- Treatment Plan Section -->
                <div class="consultation-section" id="viewTreatmentPlanSection">
                  <h4 class="consultation-section-title">Treatment Plan</h4>
                  <div id="viewMedicinesContainer">
                    <!-- Medicines will be populated here -->
                  </div>
                  <div class="form-row full" style="margin-top: 20px;">
                    <div class="form-group">
                      <label>Treatment Instructions</label>
                      <textarea id="viewTreatmentInstructions" rows="4" readonly class="readonly-field"></textarea>
                    </div>
                  </div>
                </div>

                <div class="section-divider"></div>

                <div class="consultation-section">
                  <h4 class="consultation-section-title">Clinical Notes</h4>
                  <div class="notes-container">
                    <div class="notes-section">
                      <h5>Nurse's Notes</h5>
                      <div class="form-group">
                        <textarea id="viewNurseNotes" rows="6" readonly class="readonly-field"></textarea>
                      </div>
                    </div>
                    <div class="notes-section">
                      <h5>Doctor's Notes</h5>
                      <div class="form-group">
                        <label>Date</label>
                        <input type="date" id="viewDoctorDate" readonly class="readonly-field">
                      </div>
                      <div class="form-group">
                        <textarea id="viewDoctorNotes" rows="6" readonly class="readonly-field"></textarea>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
              
              <!-- Medical Record Form Fields (for appointment-based records) -->
              <div id="appointmentFormFields" style="display: none;">
                <div class="consultation-section">
                  <h4 class="consultation-section-title">Appointment Information</h4>
                  <div class="form-row">
                    <div class="form-group">
                      <label>Appointment Date <span class="required">*</span></label>
                      <input type="date" id="viewAppointmentDate" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>Appointment Time</label>
                      <input type="time" id="viewAppointmentTime" readonly class="readonly-field">
                    </div>
                  </div>
                </div>

                <div class="consultation-section">
                  <h4 class="consultation-section-title">Vital Signs</h4>
                  <div class="vitals-grid">
                    <div class="form-group">
                      <label>Blood Pressure (BP)</label>
                      <input type="text" id="viewAppointmentBP" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>Pulse Rate (PR)</label>
                      <input type="text" id="viewAppointmentPR" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>SpO2</label>
                      <input type="text" id="viewAppointmentSpO2" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>Respiratory Rate (RR)</label>
                      <input type="text" id="viewAppointmentRR" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>Temperature (T)</label>
                      <input type="text" id="viewAppointmentTemp" readonly class="readonly-field">
                    </div>
                  </div>
                </div>

                <div class="consultation-section">
                  <h4 class="consultation-section-title">Measurements</h4>
                  <div class="measurements-grid">
                    <div class="form-group">
                      <label>Height (HT)</label>
                      <input type="text" id="viewAppointmentHeight" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>Weight (WT)</label>
                      <input type="text" id="viewAppointmentWeight" readonly class="readonly-field">
                    </div>
                    <div class="form-group">
                      <label>BMI</label>
                      <input type="text" id="viewAppointmentBMI" readonly class="readonly-field">
                    </div>
                  </div>
                </div>

                <div class="consultation-section">
                  <h4 class="consultation-section-title">Doctor's Notes</h4>
                  <div class="form-group">
                    <label>Diagnosis <span class="required">*</span></label>
                    <textarea id="viewDiagnosis" rows="2" readonly class="readonly-field" placeholder="Enter diagnosis..."></textarea>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle me-1"></i>Close
          </button>
        </div>
      </div>
    </div>
  </div>
  
  <style>
    .medical-record-list-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 16px 20px;
      margin-bottom: 12px;
      background: white;
      border-radius: 10px;
      box-shadow: 0 2px 6px rgba(0,0,0,0.08);
      transition: all 0.2s ease;
      border: 1px solid transparent;
      cursor: pointer;
    }
    .medical-record-list-item:hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(0,0,0,0.12);
      border-color: #e0e0e0;
    }
    .record-item-date {
      font-size: 15px;
      color: #333;
      font-weight: 600;
      min-width: 220px;
    }
    .record-item-date strong {
      color: #333;
      font-weight: 600;
    }
    .record-item-type {
      display: flex;
      align-items: center;
      flex: 1;
      justify-content: flex-start;
      margin-left: 20px;
    }
    .record-item-arrow {
      color: #8B0000;
      font-size: 18px;
      margin-left: auto;
    }
    .badge {
      padding: 6px 14px;
      border-radius: 20px;
      font-size: 13px;
      font-weight: 500;
      white-space: nowrap;
    }
    .badge-medical {
      background-color: #198754;
      color: white;
    }
    .badge-consultation {
      background-color: #ffc107;
      color: white;
    }
    .badge-dental {
      background-color: #0d6efd;
      color: white;
    }
    .section-title-medical-history {
      font-size: 20px;
      font-weight: 600;
      color: #8B0000;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 2px solid #8B0000;
    }
  </style>
</body>
</html>
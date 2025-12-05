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
$first_name = $_SESSION['fname'] ?? 'Doctor';
$last_name = $_SESSION['lname'] ?? '';
$user_role = $_SESSION['role'] ?? 'doctor';
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
  <link rel="stylesheet" href="../medical/css/medical_patients.css" />
  <link rel="stylesheet" href="../medical/css/responsive.css" />
  <link rel="stylesheet" href="../admin/css/notifications.css" />
</head>

<body>
  <!-- HEADER -->
  <div class="header">
    <div class="logo-section">
      <div class="logo">
        <img src="../img/bsu-logo.png" alt="University Logo" loading="lazy" />
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
      <a href="../medical/medical_dashboard.php" class="menu-item">Dashboard</a>
      <a href="../medical/medical_profile.php" class="menu-item">Profile</a>
      <a href="../medical/medical_patients.php" class="menu-item active">Patients</a>
      <a href="../medical/medical_appointments.php" class="menu-item">Appointments</a>
      <a href="../medical/medical_settings.php" class="menu-item">Settings</a>

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

      <div class="mt-3">
        <div class="content-wrapper">
          <div class="patients-section" id="patientsSection">
            <div class="patients-header">
              <h3>All Patients</h3>
            </div>

            <div class="patients-list" id="patientsList">
              <?php if (empty($patients)): ?>
                <p style="text-align: center; color: #999; padding: 40px;">No patients found.</p>
              <?php else: ?>
                <?php foreach ($patients as $patient): ?>
                <div class="patient-card" data-patient-id="<?php echo $patient['id']; ?>">
                  <div class="patient-avatar-wrapper">
                    <div class="patient-initials"><?php echo getInitials($patient['full_name']); ?></div>
                  </div>
                  <div class="patient-info-compact">
                    <div class="patient-name"><?php echo htmlspecialchars($patient['full_name'] ?? ''); ?></div>
                    <div class="patient-meta">SR-Code: <?php echo htmlspecialchars($patient['sr_code'] ?? 'N/A'); ?></div>
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
                <!-- Medical Consultation Form (Walk-in Emergency) -->
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
                <h2 id="consultationFormTitle">MEDICAL CONSULTATION FORM</h2>
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
                    <div class="consultation-section" id="nurseAssessmentSection">
                      <h4 class="consultation-section-title">Nurse's Assessment</h4>
                      <div class="form-row">
                        <div class="form-group">
                          <label>Date <span class="required" id="assessmentDateRequired">*</span></label>
                          <input type="date" name="assessment_date" id="assessmentDateInput">
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
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="js/notifications.js"></script>
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
        
        // Initialize medicine autocompletes after a short delay to ensure DOM is ready
        setTimeout(() => {
            initializeAllMedicineAutocompletes();
        }, 100);
        
        // Make nurse assessment optional for doctors
        const userRole = '<?php echo htmlspecialchars($user_role ?? 'doctor', ENT_QUOTES); ?>';
        if (userRole === 'doctor') {
            const assessmentDateInput = document.getElementById('assessmentDateInput');
            const assessmentDateRequired = document.getElementById('assessmentDateRequired');
            
            if (assessmentDateInput) {
                assessmentDateInput.removeAttribute('required');
            }
            
            if (assessmentDateRequired) {
                assessmentDateRequired.style.display = 'none';
            }
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
        
        // Lazy load: Only load data for the active tab initially
        loadPersonalInfo(patient);
        // Don't load medical records or consultation form until tab is clicked (lazy loading)
        
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

    // Load personal information - FIXED VERSION
    function loadPersonalInfo(patient) {
        const infoTab = document.getElementById('infoTab');
    const firstName = patient.fname || '';
    const middleName = patient.mname || '';
    const lastName = patient.lname || '';
    const gender = patient.sex || patient.gender || 'N/A';
    
    // FIX: Properly calculate and display age
    let age = 'N/A';
    if (patient.age && patient.age !== '' && patient.age !== null) {
        // If age is directly stored, use it
        age = patient.age;
    } else if (patient.date_of_birth) {
        // Otherwise calculate from date of birth
        age = calculateAge(patient.date_of_birth);
    }
    
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
                    // Filter to exclude dental records - only show medical and consultation records
                    const medicalRecords = data.records.filter(record => {
                        return !(record.record_type === 'Dental' || record.is_dental === true || record.appointment_type === 'dental');
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
                        container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No medical records found.</p>';
                        window.medicalRecordsData = [];
                    }
                } else {
                    container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No medical records found.</p>';
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
        
        // Check if doctor is filling the form (nurse assessment optional)
        const userRole = '<?php echo htmlspecialchars($user_role ?? 'doctor', ENT_QUOTES); ?>';
        const form = e.target;
        
        // Remove required attribute from assessment_date if doctor is submitting
        if (userRole === 'doctor') {
            const assessmentDateInput = form.querySelector('[name="assessment_date"]');
            if (assessmentDateInput) {
                assessmentDateInput.removeAttribute('required');
            }
        }
        
        // Validate form
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        
        const formData = new FormData(form);
        
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
            // Reset medicines to single row - preserve treatment instructions and add button
            const container = document.getElementById('medicinesContainer');
            const treatmentSection = container.querySelector('.form-row.full');
            const addButton = container.querySelector('.btn-add-medicine');
            
            // Remove all existing medicine rows
            const existingRows = container.querySelectorAll('.medicine-row');
            existingRows.forEach(row => row.remove());
            
            // Create new single medicine row
            const newRow = document.createElement('div');
            newRow.className = 'medicine-row';
            newRow.innerHTML = `
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
            `;
            
            // Insert before treatment section
            if (treatmentSection) {
                container.insertBefore(newRow, treatmentSection);
            } else {
                container.appendChild(newRow);
            }
            
            // Initialize autocomplete for the reset row
            const newInput = newRow.querySelector('.medicine-search-input');
            initializeMedicineAutocomplete(newInput);
            
            medicineRowCount = 1;
            updateRemoveButtons();
        }
    }

    // Switch tabs
    // Track which tabs have been loaded (lazy loading)
    let tabsLoaded = {
      info: false,
      medical: false,
      newRecord: false
    };
    
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
            tabsLoaded.info = true;
        } else if (tabName === 'medical') {
            document.getElementById('medicalTab').classList.add('active');
            // Lazy load medical records only when tab is clicked
            if (!tabsLoaded.medical && currentPatientData) {
                tabsLoaded.medical = true;
                loadMedicalRecords(currentPatientData.id);
            }
        } else if (tabName === 'new-record') {
            document.getElementById('newRecordTab').classList.add('active');
            // Lazy load consultation form only when tab is clicked
            if (!tabsLoaded.newRecord && currentPatientData) {
                tabsLoaded.newRecord = true;
                populateConsultationForm(currentPatientData);
                // Initialize autocompletes when consultation tab is opened
                setTimeout(() => {
                    initializeAllMedicineAutocompletes();
                }, 100);
            } else if (tabsLoaded.newRecord) {
                // Re-initialize autocompletes if already loaded
                setTimeout(() => {
                    initializeAllMedicineAutocompletes();
                }, 100);
            }
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
            
            searchTimeout = setTimeout(() => {
                filterMedicines(searchTerm, suggestionsContainer, inputElement, hiddenInput);
            }, 100);
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
                    items[highlightedIndex].click();
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
                
                inputElement.value = medicineName;
                hiddenInput.value = medicineId;
                suggestionsContainer.style.display = 'none';
                highlightedIndex = -1;
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
        
        // Reset edit mode (make sure fields are readonly initially)
        // Check if function exists to avoid errors on first load
        setTimeout(() => {
            if (typeof cancelMedicalRecordEdit === 'function') {
                cancelMedicalRecordEdit();
            }
        }, 100);
        
        // Get patient data
        const patient = currentPatientData;
        
        // Populate patient information
        document.getElementById('viewFullName').value = patient?.full_name || record.patient_name || 'N/A';
        document.getElementById('viewSRCode').value = patient?.sr_code || 'N/A';
        document.getElementById('viewAddress').value = patient?.address || 'N/A';
        document.getElementById('viewProgram').value = patient?.program || patient?.position || 'N/A';
        
        // Calculate age - check patient age first, then record age, then calculate from date_of_birth
        let age = '';
        if (patient?.age && patient.age !== '' && patient.age !== null && !isNaN(patient.age)) {
            age = patient.age;
        } else if (record?.age && record.age !== '' && record.age !== null && !isNaN(record.age)) {
            age = record.age;
        } else if (patient?.date_of_birth || patient?.dob) {
            const dob = patient.date_of_birth || patient.dob;
            const calculatedAge = calculateAge(dob);
            if (calculatedAge !== null && !isNaN(calculatedAge)) {
                age = calculatedAge;
            }
        } else if (record?.date_of_birth || record?.dob) {
            const dob = record.date_of_birth || record.dob;
            const calculatedAge = calculateAge(dob);
            if (calculatedAge !== null && !isNaN(calculatedAge)) {
                age = calculatedAge;
            }
        }
        document.getElementById('viewAge').value = age || '';
        
        document.getElementById('viewSex').value = patient?.sex || patient?.gender || record?.sex || record?.gender || '';
        
        // Determine record type - check if it's a consultation or appointment record
        // Consultation records come from medical_consultations table (is_consultation = true)
        // Appointment records come from medical_records table linked to appointments (is_consultation = false, is_from_appointment = true or appointment_type = 'medical')
        const isConsultation = record.is_consultation === true;
        const isFromAppointment = record.is_from_appointment === true || 
                                  (record.appointment_type === 'medical' && !record.is_consultation);
        
        // Populate consultation form fields (read-only)
        if (isConsultation) {
            // It's a consultation - show consultation form
            // Update header for consultation record
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
            
            // Display medicines - show even if empty, matching nurse form structure
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
            // Always show Treatment Plan section to match nurse form structure
            document.getElementById('viewTreatmentPlanSection').style.display = 'block';
            
            // Show consultation form, hide appointment form
            document.getElementById('consultationFormFields').style.display = 'block';
            document.getElementById('appointmentFormFields').style.display = 'none';
        } else if (isFromAppointment) {
            // It's a medical record from appointment - show appointment form layout matching the form
            // Update header for appointment record
            const modalTitle = document.getElementById('modalRecordTitle');
            const modalRefNo = document.getElementById('modalReferenceNo');
            const modalHeaderTitle = document.getElementById('modalHeaderTitle');
            if (modalTitle) modalTitle.textContent = 'MEDICAL EXAMINATION RECORD';
            if (modalRefNo) modalRefNo.textContent = 'BatStateU-FO-HSD-11';
            if (modalHeaderTitle) modalHeaderTitle.textContent = 'Medical Examination Record';
            
            // Populate appointment-specific fields
            document.getElementById('viewAppointmentDate').value = record.visit_date || '';
            document.getElementById('viewAppointmentTime').value = record.visit_time || '';
            
            // Vital Signs
            console.log('Appointment Record Data:', {
                pulse_rate: record.pulse_rate,
                heart_rate: record.heart_rate,
                spo2: record.spo2,
                respiratory_rate: record.respiratory_rate,
                fullRecord: record
            });
            document.getElementById('viewAppointmentBP').value = record.blood_pressure || '';
            document.getElementById('viewAppointmentPR').value = record.pulse_rate || record.heart_rate || '';
            document.getElementById('viewAppointmentSpO2').value = record.spo2 || '';
            document.getElementById('viewAppointmentRR').value = record.respiratory_rate || '';
            document.getElementById('viewAppointmentTemp').value = record.temperature || '';
            
            // Measurements
            document.getElementById('viewAppointmentHeight').value = record.height || '';
            document.getElementById('viewAppointmentWeight').value = record.weight || '';
            document.getElementById('viewAppointmentBMI').value = record.bmi || '';
            
            // Doctor's Notes - Use diagnosis from medical_records table
            // Ensure we're using the correct diagnosis field (not overwritten by consultation mapping)
            const diagnosisValue = record.diagnosis || '';
            console.log('Appointment Record - Diagnosis:', diagnosisValue, 'Full record:', record);
            document.getElementById('viewDiagnosis').value = diagnosisValue;
            
            // Show appointment form, hide consultation form
            document.getElementById('consultationFormFields').style.display = 'none';
            document.getElementById('appointmentFormFields').style.display = 'block';
        } else {
            // Fallback: If it's neither consultation nor appointment, show consultation form as default
            // This handles edge cases where record type is unclear
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
    
    // Print medical record
    function printMedicalRecord() {
    // Get the original content
    const printContent = document.getElementById('medicalRecordPrintContent');
    
    // Create a clone to avoid modifying the original
    const clonedContent = printContent.cloneNode(true);
    
    // Get all input and textarea elements in the clone
    const inputs = clonedContent.querySelectorAll('input, textarea, select');
    
    // Set the value attribute for all inputs so they show in print
    inputs.forEach(input => {
        if (input.type === 'checkbox' || input.type === 'radio') {
            if (input.checked) {
                input.setAttribute('checked', 'checked');
            } else {
                input.removeAttribute('checked');
            }
        } else if (input.tagName === 'TEXTAREA') {
            // For textarea, set the text content
            input.textContent = input.value;
        } else if (input.tagName === 'SELECT') {
            // For select, mark the selected option
            const selectedOption = input.options[input.selectedIndex];
            if (selectedOption) {
                Array.from(input.options).forEach(option => {
                    option.removeAttribute('selected');
                });
                selectedOption.setAttribute('selected', 'selected');
            }
        } else {
            // For regular inputs, set the value attribute
            input.setAttribute('value', input.value);
        }
    });
    
    const printWindow = window.open('', '_blank');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Medical Consultation Record</title>
            <style>
                @media print {
                    @page { 
                        margin: 1cm;
                        size: portrait;
                    }
                    body { 
                        margin: 0;
                        -webkit-print-color-adjust: exact;
                        print-color-adjust: exact;
                    }
                    .no-print {
                        display: none !important;
                    }
                }
                
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12pt;
                    padding: 20px;
                    color: #000;
                }
                
                /* Document Header Styles */
                .document-header {
                    display: flex;
                    flex-direction: column;
                    gap: 15px;
                    margin-bottom: 20px;
                    padding-bottom: 15px;
                    border-bottom: 2px solid #8B0000;
                }
                
                .document-header .header-top {
                    display: flex;
                    align-items: flex-start;
                    justify-content: space-between;
                    gap: 20px;
                }
                
                .header-logo {
                    flex: 0 0 auto;
                }
                
                .header-logo img {
                    width: 80px;
                    height: 80px;
                }
                
                .header-info-boxes {
                    flex: 1;
                    display: flex;
                    flex-direction: row;
                    gap: 15px;
                    justify-content: flex-start;
                    align-items: center;
                }
                
                .info-box {
                    display: flex;
                    align-items: center;
                    gap: 10px;
                    font-size: 10pt;
                    flex: 1;
                    min-width: 0;
                }
                
                .info-box label {
                    font-weight: bold;
                    white-space: nowrap;
                }
                
                .header-title {
                    width: 100%;
                    text-align: center;
                    padding-top: 10px;
                    border-top: 2px solid #8B0000;
                    margin-top: 5px;
                }
                
                .header-title h2 {
                    margin: 0;
                    font-size: 18pt;
                    color: #8B0000;
                    font-weight: bold;
                }
                
                /* Consultation Form Styles */
                .consultation-form-header {
                    background: #8B0000;
                    color: white;
                    padding: 15px;
                    text-align: center;
                    margin-bottom: 20px;
                }
                
                .consultation-form-header h3 {
                    margin: 0;
                    font-size: 20pt;
                }
                
                .consultation-section {
                    margin-bottom: 20px;
                    page-break-inside: avoid;
                }
                
                .consultation-section-title {
                    color: #8B0000;
                    font-size: 14pt;
                    font-weight: bold;
                    margin-bottom: 12px;
                    border-bottom: 2px solid #8B0000;
                    padding-bottom: 5px;
                }
                
                .form-row {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 15px;
                    margin-bottom: 12px;
                }
                
                .form-row.full {
                    grid-template-columns: 1fr;
                }
                
                .form-group {
                    margin-bottom: 12px;
                }
                
                .form-group label {
                    display: block;
                    font-weight: bold;
                    margin-bottom: 5px;
                    color: #333;
                    font-size: 10pt;
                }
                
                .form-group .required {
                    color: #dc3545;
                }
                
                input[type="text"],
                input[type="date"],
                input[type="time"],
                input[type="number"],
                textarea,
                select,
                .readonly-field {
                    width: 100%;
                    padding: 6px 10px;
                    border: 1px solid #333;
                    background: #fff;
                    font-size: 11pt;
                    box-sizing: border-box;
                    font-family: Arial, sans-serif;
                }
                
                textarea {
                    min-height: 80px;
                    resize: none;
                }
                
                .vitals-grid,
                .measurements-grid {
                    display: grid;
                    grid-template-columns: repeat(3, 1fr);
                    gap: 15px;
                    margin-bottom: 12px;
                }
                
                .notes-container {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 20px;
                }
                
                .notes-section h5 {
                    color: #8B0000;
                    font-size: 12pt;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                
                .section-divider {
                    height: 2px;
                    background: #ddd;
                    margin: 20px 0;
                }
                
                /* Hide buttons and non-printable elements */
                button,
                .modal-header,
                .modal-footer,
                .btn,
                .no-print {
                    display: none !important;
                }
            </style>
        </head>
        <body>
            ${clonedContent.innerHTML}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    // Wait for content to load before printing
    setTimeout(() => {
        printWindow.print();
        // Don't close the window immediately - let user close it
        // printWindow.close();
    }, 500);
}

// Enhanced Export to PDF function
function exportToPDF() {
    const printContent = document.getElementById('medicalRecordPrintContent');
    const patientName = document.getElementById('viewFullName').value || 'Patient';
    const date = document.getElementById('viewAssessmentDate').value || document.getElementById('viewVisitDate').value || new Date().toISOString().split('T')[0];
    
    // Create a clone and set all values
    const clonedContent = printContent.cloneNode(true);
    const inputs = clonedContent.querySelectorAll('input, textarea, select');
    
    inputs.forEach(input => {
        if (input.type === 'checkbox' || input.type === 'radio') {
            if (input.checked) {
                input.setAttribute('checked', 'checked');
            }
        } else if (input.tagName === 'TEXTAREA') {
            input.textContent = input.value;
        } else if (input.tagName === 'SELECT') {
            const selectedOption = input.options[input.selectedIndex];
            if (selectedOption) {
                Array.from(input.options).forEach(option => {
                    option.removeAttribute('selected');
                });
                selectedOption.setAttribute('selected', 'selected');
            }
        } else {
            input.setAttribute('value', input.value);
        }
    });
    
    // Create temporary container for rendering
    const tempContainer = document.createElement('div');
    tempContainer.style.position = 'absolute';
    tempContainer.style.left = '-9999px';
    tempContainer.style.width = '210mm'; // A4 width
    tempContainer.style.padding = '20px';
    tempContainer.style.background = 'white';
    tempContainer.innerHTML = clonedContent.innerHTML;
    document.body.appendChild(tempContainer);
    
    // Use html2canvas to capture the content
    if (typeof html2canvas !== 'undefined' && typeof window.jsPDF !== 'undefined') {
        html2canvas(tempContainer, {
            scale: 2,
            useCORS: true,
            logging: false,
            backgroundColor: '#ffffff'
        }).then(canvas => {
            // Remove temporary container
            document.body.removeChild(tempContainer);
            
            const imgData = canvas.toDataURL('image/png');
            const { jsPDF } = window.jsPDF;
            const pdf = new jsPDF('p', 'mm', 'a4');
            
            const imgWidth = 210; // A4 width in mm
            const pageHeight = 297; // A4 height in mm
            const imgHeight = (canvas.height * imgWidth) / canvas.width;
            let heightLeft = imgHeight;
            
            let position = 0;
            
            pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
            
            while (heightLeft >= 0) {
                position = heightLeft - imgHeight;
                pdf.addPage();
                pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
                heightLeft -= pageHeight;
            }
            
            pdf.save(`medical-record-${patientName.replace(/\s+/g, '-')}-${date}.pdf`);
        }).catch(error => {
            console.error('Error generating PDF:', error);
            document.body.removeChild(tempContainer);
            // Fallback to print
            Swal.fire({
                icon: 'warning',
                title: 'PDF Generation Failed',
                text: 'Falling back to print dialog...',
                confirmButtonColor: '#8b2332'
            }).then(() => {
                printMedicalRecord();
            });
        });
    } else {
        // Remove temporary container
        document.body.removeChild(tempContainer);
        
        // Libraries not loaded - fallback to print
        Swal.fire({
            icon: 'info',
            title: 'PDF Export',
            text: 'PDF libraries not available. Using print dialog instead. You can print to PDF from there.',
            confirmButtonColor: '#8b2332'
        }).then(() => {
            printMedicalRecord();
        });
    }
}
    
    // Store original record data for cancel functionality
    let originalMedicalRecordData = null;
    let currentMedicalRecordIndex = null;
    let isMedicalRecordConsultation = false;
    
    // Enable edit mode for medical record
    function enableMedicalRecordEdit() {
        const modal = document.getElementById('medicalRecordModal');
        const recordIndex = modal.dataset.recordIndex;
        
        if (recordIndex !== undefined && window.medicalRecordsData && window.medicalRecordsData[recordIndex]) {
            originalMedicalRecordData = JSON.parse(JSON.stringify(window.medicalRecordsData[recordIndex]));
            currentMedicalRecordIndex = recordIndex;
            isMedicalRecordConsultation = window.medicalRecordsData[recordIndex].is_consultation || false;
        }
        
        // Make fields editable based on record type
        if (isMedicalRecordConsultation) {
            // Consultation fields
            const consultationFields = [
                'viewPurpose', 'viewAssessmentDate', 'viewControlNumber',
                'viewAge', 'viewSex', 'viewBloodPressure', 'viewPulseRate',
                'viewSpo2', 'viewRespiratoryRate', 'viewTemperature',
                'viewHeight', 'viewWeight', 'viewBmi', 'viewLastMenstrualPeriod',
                'viewVisionRight', 'viewVisionLeft', 'viewNurseNotes',
                'viewDoctorDate', 'viewDoctorNotes', 'viewTreatmentInstructions'
            ];
            
            consultationFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.readOnly = false;
                    field.classList.remove('readonly-field');
                }
            });
            
            // Enable medicine editing if Treatment Plan section exists
            const treatmentPlanSection = document.getElementById('viewTreatmentPlanSection');
            if (treatmentPlanSection) {
                treatmentPlanSection.style.display = 'block';
            }
        } else {
            // Medical record fields
            const medicalFields = [
                'viewVisitDate', 'viewVisitTime', 'viewChiefComplaint',
                'viewDiagnosis', 'viewTreatment'
            ];
            
            medicalFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.readOnly = false;
                    field.classList.remove('readonly-field');
                }
            });
        }
        
        // Show/hide buttons
        document.getElementById('editMedicalRecordBtn').style.display = 'none';
        document.getElementById('updateMedicalRecordBtn').style.display = 'inline-block';
        document.getElementById('cancelMedicalEditBtn').style.display = 'inline-block';
    }
    
    // Cancel edit mode for medical record
    function cancelMedicalRecordEdit() {
        if (!originalMedicalRecordData) return;
        
        const record = originalMedicalRecordData;
        
        if (isMedicalRecordConsultation) {
            // Restore consultation fields
            document.getElementById('viewPurpose').value = record.purpose || '';
            document.getElementById('viewAssessmentDate').value = record.assessment_date || record.visit_date || '';
            document.getElementById('viewControlNumber').value = record.control_number || '';
            document.getElementById('viewAge').value = record.age || '';
            document.getElementById('viewSex').value = record.sex || '';
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
            document.getElementById('viewTreatmentInstructions').value = record.treatment_instructions || '';
        } else {
            // Restore medical record fields
            document.getElementById('viewVisitDate').value = record.visit_date || '';
            document.getElementById('viewVisitTime').value = record.visit_time || '';
            document.getElementById('viewChiefComplaint').value = record.chief_complaint || '';
            document.getElementById('viewDiagnosis').value = record.diagnosis || '';
            document.getElementById('viewTreatment').value = record.treatment_instructions || record.treatment || '';
        }
        
        // Make fields readonly again
        const allFields = document.querySelectorAll('#medicalRecordModal input, #medicalRecordModal textarea');
        allFields.forEach(field => {
            if (field.id && field.id.startsWith('view')) {
                field.readOnly = true;
                field.classList.add('readonly-field');
            }
        });
        
        // Show/hide buttons
        document.getElementById('editMedicalRecordBtn').style.display = 'inline-block';
        document.getElementById('updateMedicalRecordBtn').style.display = 'none';
        document.getElementById('cancelMedicalEditBtn').style.display = 'none';
        
        originalMedicalRecordData = null;
        currentMedicalRecordIndex = null;
        isMedicalRecordConsultation = false;
    }
    
    // Update medical record
    async function updateMedicalRecord() {
        if (currentMedicalRecordIndex === null || !window.medicalRecordsData || !window.medicalRecordsData[currentMedicalRecordIndex]) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Record data not found. Please refresh and try again.',
                confirmButtonColor: '#8b2332'
            });
            return;
        }
        
        const record = window.medicalRecordsData[currentMedicalRecordIndex];
        const recordId = record.id;
        const isConsultation = record.is_consultation || false;
        
        if (!recordId) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Record ID not found.',
                confirmButtonColor: '#8b2332'
            });
            return;
        }
        
        let updatedData = {
            record_id: recordId,
            is_consultation: isConsultation
        };
        
        if (isConsultation) {
            // Update consultation record
            updatedData = {
                ...updatedData,
                consultation_id: recordId,
                purpose: document.getElementById('viewPurpose').value || '',
                assessment_date: document.getElementById('viewAssessmentDate').value || '',
                control_number: document.getElementById('viewControlNumber').value || '',
                age: document.getElementById('viewAge').value || '',
                sex: document.getElementById('viewSex').value || '',
                blood_pressure: document.getElementById('viewBloodPressure').value || '',
                pulse_rate: document.getElementById('viewPulseRate').value || '',
                spo2: document.getElementById('viewSpo2').value || '',
                respiratory_rate: document.getElementById('viewRespiratoryRate').value || '',
                temperature: document.getElementById('viewTemperature').value || '',
                height: document.getElementById('viewHeight').value || '',
                weight: document.getElementById('viewWeight').value || '',
                bmi: document.getElementById('viewBmi').value || '',
                last_menstrual_period: document.getElementById('viewLastMenstrualPeriod').value || '',
                vision_right: document.getElementById('viewVisionRight').value || '',
                vision_left: document.getElementById('viewVisionLeft').value || '',
                nurse_notes: document.getElementById('viewNurseNotes').value || '',
                doctor_date: document.getElementById('viewDoctorDate').value || '',
                doctor_notes: document.getElementById('viewDoctorNotes').value || '',
                treatment_instructions: document.getElementById('viewTreatmentInstructions').value || ''
            };
        } else {
            // Update medical record
            updatedData = {
                ...updatedData,
                medical_record_id: recordId,
                visit_date: document.getElementById('viewVisitDate').value || '',
                visit_time: document.getElementById('viewVisitTime').value || '',
                chief_complaint: document.getElementById('viewChiefComplaint').value || '',
                diagnosis: document.getElementById('viewDiagnosis').value || '',
                treatment_instructions: document.getElementById('viewTreatment').value || ''
            };
        }
        
        try {
            const response = await fetch('../crud/update_medical_record.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(updatedData)
            });
            
            const result = await response.json();
            
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Medical record updated successfully.',
                    confirmButtonColor: '#8b2332',
                    timer: 2000
                }).then(() => {
                    // Update the record in memory
                    window.medicalRecordsData[currentMedicalRecordIndex] = {
                        ...window.medicalRecordsData[currentMedicalRecordIndex],
                        ...updatedData
                    };
                    
                    // Exit edit mode
                    cancelMedicalRecordEdit();
                    
                    // Reload records to get fresh data
                    if (currentPatientData && currentPatientData.id) {
                        loadMedicalRecords(currentPatientData.id);
                    }
                });
            } else {
                throw new Error(result.message || 'Failed to update medical record');
            }
        } catch (error) {
            console.error('Error updating medical record:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: error.message || 'Failed to update medical record. Please try again.',
                confirmButtonColor: '#8b2332'
            });
        }
    }
  </script>
  
  <!-- Medical Record View Modal -->
  <div class="modal fade" id="medicalRecordModal" tabindex="-1" aria-labelledby="medicalRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header" style="background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%); color: white;">
          <h5 class="modal-title" id="medicalRecordModalLabel">
            <i class="bi bi-file-medical me-2"></i><span id="modalHeaderTitle">Medical Consultation Record</span>
          </h5>
          <div class="d-flex gap-2">
            <button type="button" id="editMedicalRecordBtn" class="btn btn-sm" onclick="enableMedicalRecordEdit()" style="display: inline-block; background: linear-gradient(135deg, #6b0d00 0%, #8b1a00 100%); border: none; color: white; transition: all 0.2s;">
              <i class="bi bi-pencil me-1"></i>Edit
            </button>
            <button type="button" id="updateMedicalRecordBtn" class="btn btn-success btn-sm" onclick="updateMedicalRecord()" style="display: none;">
              <i class="bi bi-check-circle me-1"></i>Update
            </button>
            <button type="button" id="cancelMedicalEditBtn" class="btn btn-secondary btn-sm" onclick="cancelMedicalRecordEdit()" style="display: none;">
              <i class="bi bi-x-circle me-1"></i>Cancel
            </button>
            <button type="button" class="btn btn-light btn-sm" onclick="printMedicalRecord()">
              <i class="bi bi-printer me-1"></i>Print
            </button>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
        </div>
        <div class="modal-body" id="medicalRecordPrintContent">
          <!-- Consultation Form View (Read-only) -->
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
                <!-- Nurse's Assessment Section -->
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

                <!-- Vital Signs Section -->
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

                <!-- Measurements Section -->
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

                <!-- Notes Section -->
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
                <!-- Patient Information Section (already shown above, but add Appointment Date/Time) -->
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

                <!-- Vital Signs Section -->
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

                <!-- Measurements Section -->
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

                <!-- Doctor's Notes Section -->
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
    .form-section {
      margin-bottom: 25px;
      padding-bottom: 20px;
      border-bottom: 1px solid #e0e0e0;
    }
    .form-section:last-child {
      border-bottom: none;
    }
    .form-section-title {
      color: #8B0000;
      font-weight: 600;
      margin-bottom: 15px;
      font-size: 1.1rem;
    }
    .form-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 15px;
      margin-bottom: 15px;
    }
    .form-group {
      display: flex;
      flex-direction: column;
    }
    .form-group label {
      font-weight: 500;
      color: #666;
      font-size: 0.9rem;
      margin-bottom: 5px;
    }
    .form-group .required {
      color: #dc3545;
    }
    .form-control {
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 0.95rem;
    }
    .form-control:focus {
      border-color: #8B0000;
      outline: none;
      box-shadow: 0 0 0 2px rgba(139, 0, 0, 0.1);
    }
    
    /* Medicine Autocomplete Styles */
    .medicine-search-input {
      width: 100%;
      padding: 8px 12px;
      border: 1px solid #ddd;
      border-radius: 4px;
      font-size: 0.95rem;
      box-sizing: border-box;
    }
    
    .medicine-search-input:focus {
      border-color: #8B0000;
      outline: none;
      box-shadow: 0 0 0 2px rgba(139, 0, 0, 0.1);
    }
    
    .medicine-suggestions {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #ddd;
      border-radius: 4px;
      box-shadow: 0 4px 6px rgba(0,0,0,0.1);
      max-height: 300px;
      overflow-y: auto;
      z-index: 1000;
      margin-top: 2px;
    }
    
    .medicine-suggestion-item {
      padding: 10px 15px;
      cursor: pointer;
      border-bottom: 1px solid #f0f0f0;
      transition: background-color 0.2s;
    }
    
    .medicine-suggestion-item:hover,
    .medicine-suggestion-item.highlighted {
      background-color: #e7f3ff;
    }
    
    .medicine-suggestion-item:last-child {
      border-bottom: none;
    }
    
    .medicine-suggestion-name {
      font-weight: 600;
      color: #333;
      margin-bottom: 2px;
    }
    
    .medicine-suggestion-code {
      font-size: 12px;
      color: #666;
    }
    
    .medicine-suggestion-stock {
      font-size: 11px;
      color: #28a745;
      margin-top: 2px;
    }
    
    .medicine-suggestion-expiry {
      font-size: 12px;
      color: #ff6b00;
      font-weight: 600;
      margin-top: 4px;
      line-height: 1.4;
      background-color: #fff3e0;
      padding: 4px 8px;
      border-radius: 4px;
      border-left: 3px solid #ff9800;
    }
    
    .medicine-suggestion-expired {
      font-size: 12px;
      color: #dc3545;
      font-weight: 600;
      margin-top: 4px;
      line-height: 1.4;
      background-color: #ffebee;
      padding: 4px 8px;
      border-radius: 4px;
      border-left: 3px solid #dc3545;
    }
    
    .medicine-suggestion-expiry-normal {
      font-size: 11px;
      color: #6c757d;
      font-weight: 400;
      margin-top: 2px;
      line-height: 1.4;
    }
    
    .medicine-suggestion-item.nearing-expiry-item {
      border-left: 3px solid #ff9800;
      background-color: #fffbf0;
    }
    
    .medicine-suggestion-item.expired-item {
      border-left: 3px solid #dc3545;
      background-color: #fff5f5;
      opacity: 0.7;
    }
    
    .medicine-suggestion-empty {
      padding: 15px;
      text-align: center;
      color: #999;
      font-style: italic;
    }
    
    /* Edit Button - Maroon Theme */
    #editMedicalRecordBtn {
      background: linear-gradient(135deg, #6b0d00 0%, #8b1a00 100%) !important;
      border: none !important;
      color: white !important;
      transition: all 0.2s;
    }
    
    #editMedicalRecordBtn:hover {
      background: linear-gradient(135deg, #8b1a00 0%, #a52a00 100%) !important;
      transform: translateY(-1px);
      box-shadow: 0 4px 8px rgba(107, 13, 0, 0.3);
      color: white !important;
    }
    
    #editMedicalRecordBtn:active {
      transform: translateY(0);
      color: white !important;
    }
    
    #editMedicalRecordBtn:focus {
      background: linear-gradient(135deg, #6b0d00 0%, #8b1a00 100%) !important;
      border: none !important;
      color: white !important;
      box-shadow: 0 0 0 0.25rem rgba(107, 13, 0, 0.25);
    }
  </style>
  <script>
    // Initialize notification system
    if (window.MedicalNotificationSystem) {
      MedicalNotificationSystem.init();
    }
  </script>
  <script src="../js/mobile-menu.js"></script>
</body>
</html>
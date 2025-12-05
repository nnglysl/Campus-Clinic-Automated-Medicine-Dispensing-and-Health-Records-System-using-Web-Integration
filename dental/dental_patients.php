<?php
require_once '../config/database.php';
require_once '../includes/patient_sync.php';
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
$first_name = $_SESSION['fname'] ?? 'Dentist';
$last_name = $_SESSION['lname'] ?? '';
$user_role = $_SESSION['role'] ?? 'dentist';
$fullName = trim($first_name . ' ' . $last_name);

// Log session info for debugging
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("User info: " . print_r($_SESSION, true));

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
    error_log("Fetched " . count($patients) . " patients");
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
    $patients = [];
}

foreach ($patients as &$patient) {
    $patient['position'] = $patient['program'] ?? 'Student';
    $patient['phone'] = $patient['contact_number'] ?? null;
}
unset($patient);

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
  <link rel="stylesheet" href="../dental/css/responsive.css" />
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
      <a href="../dental/dental_dashboard.php" class="menu-item">Dashboard</a>
      <a href="../dental/dental_profile.php" class="menu-item">Profile</a>
      <a href="../dental/dental_patients.php" class="menu-item active">Patients</a>
      <a href="../dental/dental_appointments.php" class="menu-item">Appointments</a>
      <a href="../dental/dental_settings.php" class="menu-item">Settings</a>

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
            </div>

            <div class="tab-content-inner active" id="infoTab">
              <!-- Personal Information will be loaded dynamically -->
            </div>

            <div class="tab-content-inner" id="medicalTab">
              <div class="form-section">
                <h4 class="section-title-medical-history">Medical History</h4>
                <div id="medicalRecordsContainer">
                  
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
  <script src="js/notifications.js"></script>
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
        const container = element.closest('.tooth-container');
        if (container) {
            const select = container.querySelector('select');
            if (select) {
                // If marking the tooth, ensure it has a status
                if (!element.classList.contains('marked') && !select.value) {
                    element.classList.add('marked');
                    select.value = 'X'; // Default to 'X' when marking
                } else if (element.classList.contains('marked')) {
                    // If unmarking, clear the status (set to unfilled)
                    element.classList.remove('marked');
                    select.value = '';
                } else {
                    // Toggle the visual state
        element.classList.toggle('marked');
                    if (!element.classList.contains('marked')) {
                        select.value = ''; // Clear status when unmarked
                    }
                }
            } else {
                element.classList.toggle('marked');
            }
        } else {
            element.classList.toggle('marked');
        }
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
    
    // Initialize dental chart for view modal (read-only)
    function initializeDentalChartForView(toothStatus = {}) {
        const dentalOptions = [
            { value: '', text: '' },
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
        
        function createToothView(number, showNumberTop = true) {
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
            // Mark tooth if it has ANY status (not just 'X')
            if (toothStatus[number] && toothStatus[number] !== '') {
                diagram.classList.add('marked');
            }
            container.appendChild(diagram);
            
            const select = document.createElement('select');
            select.className = 'tooth-select';
            select.id = `viewTooth_${number}_status`;
            select.name = `tooth_${number}_status`;
            select.disabled = true;
            
            const statusValue = toothStatus[number] || '';
            dentalOptions.forEach(option => {
                const opt = document.createElement('option');
                opt.value = option.value;
                opt.textContent = option.text;
                opt.selected = opt.value === statusValue;
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
        
        // Clear and populate upper temporary teeth
        const viewUpperTemp = document.getElementById('viewUpperTempTeeth');
        if (viewUpperTemp) {
            viewUpperTemp.innerHTML = '';
            [55, 54, 53, 52, 51, 61, 62, 63, 64, 65].forEach(num => {
                viewUpperTemp.appendChild(createToothView(num, true));
            });
        }
        
        // Clear and populate upper permanent teeth
        const viewUpperPerm = document.getElementById('viewUpperPermTeeth');
        if (viewUpperPerm) {
            viewUpperPerm.innerHTML = '';
            [18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28].forEach(num => {
                viewUpperPerm.appendChild(createToothView(num, true));
            });
        }
        
        // Clear and populate lower permanent teeth
        const viewLowerPerm = document.getElementById('viewLowerPermTeeth');
        if (viewLowerPerm) {
            viewLowerPerm.innerHTML = '';
            [48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38].forEach(num => {
                viewLowerPerm.appendChild(createToothView(num, false));
            });
        }
        
        // Clear and populate lower temporary teeth
        const viewLowerTemp = document.getElementById('viewLowerTempTeeth');
        if (viewLowerTemp) {
            viewLowerTemp.innerHTML = '';
            [85, 84, 83, 82, 81, 71, 72, 73, 74, 75].forEach(num => {
                viewLowerTemp.appendChild(createToothView(num, false));
            });
        }
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
    // Use event delegation for record clicks (works for dynamically added records)
    // Event delegation for medical record list items (works for dynamically loaded content)
    document.addEventListener('click', function(e) {
        const recordItem = e.target.closest('.medical-record-list-item');
        if (recordItem) {
            e.preventDefault();
            e.stopPropagation();
            const index = parseInt(recordItem.getAttribute('data-record-index'));
            console.log('Record clicked via event delegation, index:', index);
            console.log('window.medicalRecordsData available:', !!window.medicalRecordsData);
            console.log('window.medicalRecordsData length:', window.medicalRecordsData ? window.medicalRecordsData.length : 0);
            console.log('window.showMedicalRecordForm available:', !!window.showMedicalRecordForm);
            
            if (!isNaN(index) && index >= 0) {
                if (window.showMedicalRecordForm) {
                    window.showMedicalRecordForm(index);
                } else {
                    console.error('showMedicalRecordForm function not found on window object');
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Unable to open record. Please refresh the page.',
                        confirmButtonColor: '#8b2332'
                    });
                }
            } else {
                console.error('Invalid record index:', index);
            }
        }
    });
    
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
        
        // Store current patient data globally for use in record modals
        window.currentPatientData = patient;
        
        loadPersonalInfo(patient);
        loadMedicalRecords(patientId);
        
        switchTab('info');
    }

    function closePatientDetail() {
        console.log('Closing patient detail view');
        const patientsSection = document.getElementById('patientsSection');
        const detailSection = document.getElementById('patientDetailSection');
        
        patientsSection.classList.remove('hidden');
        detailSection.classList.remove('active');
    }

    function calculateAge(dateOfBirth) {
        if (!dateOfBirth) return null;
        const today = new Date();
        const birthDate = new Date(dateOfBirth);
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
            age--;
        }
        return age;
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

    function loadMedicalRecords(patientId) {
        console.log('Loading dental records for patient ID:', patientId);
        const container = document.getElementById('medicalRecordsContainer');
        container.innerHTML = '<p style="text-align: center; color: #999; padding: 20px;">Loading dental records...</p>';
        
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
                    // Filter to only show dental records
                    const dentalRecords = data.records.filter(record => {
                        return record.record_type === 'Dental' || record.is_dental === true || record.appointment_type === 'dental';
                    });
                    
                    if (dentalRecords.length > 0) {
                        container.innerHTML = dentalRecords.map((record, index) => {
                            const recordDate = formatDate(record.visit_date || record.assessment_date);
                            const recordType = 'Dental'; // Always Dental since we filtered
                            const typeBadgeClass = 'badge-dental';
                            
                            return `
                                <div class="medical-record-list-item" style="cursor: pointer;" data-record-index="${index}">
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
                        
                        // Store only dental records globally for modal access
                        window.medicalRecordsData = dentalRecords;
                        
                        console.log('Dental records loaded:', dentalRecords.length);
                        console.log('window.medicalRecordsData set with', window.medicalRecordsData.length, 'records');
                    } else {
                        container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No dental records found.</p>';
                        window.medicalRecordsData = [];
                    }
                } else {
                    container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No dental records found.</p>';
                    window.medicalRecordsData = [];
                }
            })
                .catch(error => {
                    console.error('Error loading dental records:', error);
                    console.error('Error details:', {
                        message: error.message,
                        stack: error.stack,
                        patientId: patientId
                    });
                    container.innerHTML = `
                        <p style="text-align: center; color: #dc3545; padding: 40px;">
                            Error loading dental records.<br>
                            <small style="color: #999;">Patient ID: ${patientId}</small><br>
                            <small style="color: #999;">Error: ${error.message}</small>
                        </p>
                    `;
                    window.medicalRecordsData = [];
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

    // Show medical/dental record form
    function showMedicalRecordForm(index) {
        console.log('showMedicalRecordForm called with index:', index);
        console.log('window.medicalRecordsData:', window.medicalRecordsData);
        console.log('Records count:', window.medicalRecordsData ? window.medicalRecordsData.length : 0);
        
        if (!window.medicalRecordsData || !window.medicalRecordsData[index]) {
            console.error('Medical record not found at index:', index);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Record not found. Index: ' + index + ', Total records: ' + (window.medicalRecordsData ? window.medicalRecordsData.length : 0),
                confirmButtonColor: '#8b2332'
            });
            return;
        }
        
        const record = window.medicalRecordsData[index];
        console.log('Clicked record:', record);
        console.log('Record type:', record.record_type, 'is_dental:', record.is_dental, 'appointment_type:', record.appointment_type);
        
        // Check if it's a dental record
        const isDental = record.record_type === 'Dental' || record.is_dental === true || record.appointment_type === 'dental';
        console.log('Is dental record:', isDental);
        
        if (isDental) {
            // It's a dental record - show full dental form directly from record data
            console.log('Opening dental record modal...');
            showDentalRecordModalDirect(record, index);
        } else {
            // It's a medical/consultation record - show medical modal
            console.log('Opening medical record modal...');
            showMedicalRecordModal(record, index);
        }
    }
    
    // Make function globally accessible
    window.showMedicalRecordForm = showMedicalRecordForm;
    
    // Show dental record modal with full form - directly from record data (like medical records)
    async function showDentalRecordModalDirect(record, index) {
        const modalElement = document.getElementById('dentalRecordModal');
        if (!modalElement) {
            console.error('Dental record modal not found');
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Dental record modal not found.',
                confirmButtonColor: '#8b2332'
            });
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
        
        // If record doesn't have index_data, try to fetch full record details
        const dentalRecordId = record.dental_record_id || record.id;
        if (!record.index_data && dentalRecordId) {
            try {
                console.log('Fetching full dental record details for ID:', dentalRecordId);
                const response = await fetch(`crud/get_dental_record.php?id=${dentalRecordId}`);
                const result = await response.json();
                if (result.success && result.record) {
                    console.log('Fetched full record:', result.record);
                    // Merge the full record data with the existing record
                    record = { ...record, ...result.record };
                    // Update the record in the global array
                    if (window.medicalRecordsData && window.medicalRecordsData[index]) {
                        window.medicalRecordsData[index] = record;
                    }
                }
            } catch (error) {
                console.error('Error fetching full dental record:', error);
            }
        }
        
        // Get patient data - try to get from global variable or find from patientsData
        let patient = null;
        if (typeof window.currentPatientData !== 'undefined' && window.currentPatientData) {
            patient = window.currentPatientData;
        } else if (typeof patientsData !== 'undefined' && patientsData && record.patient_id) {
            // Try to find patient from patientsData using patient_id from record
            patient = patientsData.find(p => p.id == record.patient_id);
        }
        
        console.log('Patient data for modal:', patient);
        
        // Populate patient information - use record data as fallback
        document.getElementById('viewDentalFullName').value = patient?.full_name || record.patient_name || 'N/A';
        document.getElementById('viewDentalSRCode').value = patient?.sr_code || record.sr_code || 'N/A';
        document.getElementById('viewDentalAddress').value = patient?.address || record.address || 'N/A';
        document.getElementById('viewDentalProgram').value = patient?.program || patient?.position || record.program || 'N/A';
        
        // Set appointment date and dentist
        const visitDate = record.visit_date || record.created_at || '';
        document.getElementById('viewDentalDate').value = visitDate.split(' ')[0]; // Get date part only
        
        // Set dentist name if field exists
        const dentistField = document.getElementById('viewDentalDentist');
        if (dentistField) {
            dentistField.value = record.dentist_name || record.physician_name || 'N/A';
        }
        
        // Populate dental conditions checkboxes
        document.getElementById('viewDentalGingivitis').checked = record.gingivitis == 1;
        document.getElementById('viewDentalEarlyPeriodontitis').checked = record.early_periodontitis == 1;
        document.getElementById('viewDentalClassMolar').checked = record.class_molar == 1;
        document.getElementById('viewDentalOverjet').checked = record.overjet == 1;
        document.getElementById('viewDentalOverbite').checked = record.overbite == 1;
        document.getElementById('viewDentalOrthodontic').checked = record.orthodontic == 1;
        document.getElementById('viewDentalStayplate').checked = record.stayplate == 1;
        document.getElementById('viewDentalClenching').checked = record.clenching == 1;
        document.getElementById('viewDentalClicking').checked = record.clicking == 1;
        
        // Initialize and populate tooth chart
        // tooth_status should already be decoded as an object from PHP
        console.log('Raw tooth_status from record:', record.tooth_status);
        console.log('Type of tooth_status:', typeof record.tooth_status);
        
        // Define all tooth numbers that should be tracked
        const allToothNumbers = [
            // Upper temporary
            55, 54, 53, 52, 51, 61, 62, 63, 64, 65,
            // Upper permanent
            18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28,
            // Lower permanent
            48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38,
            // Lower temporary
            85, 84, 83, 82, 81, 71, 72, 73, 74, 75
        ];
        
        let toothStatus = {};
        
        // Initialize all teeth with empty string (unfilled state) first
        allToothNumbers.forEach(num => {
            toothStatus[String(num)] = ''; // Empty string = unfilled/unmarked
        });
        
        // Then override with saved values from record if they exist
        if (record.tooth_status) {
            let savedStatus = {};
            if (typeof record.tooth_status === 'string') {
                try {
                    savedStatus = JSON.parse(record.tooth_status || '{}');
                } catch (e) {
                    console.error('Error parsing tooth_status JSON:', e);
                    savedStatus = {};
                }
            } else if (typeof record.tooth_status === 'object' && record.tooth_status !== null) {
                savedStatus = record.tooth_status;
            }
            
            // Merge saved status into initialized status (preserving empty strings for unfilled)
            Object.keys(savedStatus).forEach(key => {
                const value = savedStatus[key];
                // Preserve empty strings as empty strings (unfilled state)
                toothStatus[String(key)] = (value !== undefined && value !== null) ? String(value) : '';
            });
        }
        
        const filledTeeth = Object.entries(toothStatus).filter(([k, v]) => v && v !== '').length;
        const unfilledTeeth = Object.entries(toothStatus).filter(([k, v]) => !v || v === '').length;
        console.log('Initialized toothStatus with all teeth:', {
            total: Object.keys(toothStatus).length,
            filled: filledTeeth,
            unfilled: unfilledTeeth
        });
        
        // Initialize chart with tooth status
        initializeDentalChartForView(toothStatus);
        
        // Populate tooth status after chart is initialized (process ALL teeth, including unfilled ones)
        setTimeout(() => {
            if (toothStatus && typeof toothStatus === 'object') {
                let populatedCount = 0;
                let unfilledCount = 0;
                let notFoundCount = 0;
                
                // Process ALL teeth, including empty ones (unfilled/unmarked state)
                Object.keys(toothStatus).forEach(toothNum => {
                    const statusValue = toothStatus[toothNum] || ''; // Default to empty string for unfilled
                    
                    // Try with the key as-is (might be string or number)
                    let selectEl = document.getElementById(`viewTooth_${toothNum}_status`);
                    
                    // If not found, try with numeric conversion
                    if (!selectEl && !isNaN(toothNum)) {
                        selectEl = document.getElementById(`viewTooth_${parseInt(toothNum)}_status`);
                    }
                    
                    // If still not found, try with string conversion
                    if (!selectEl) {
                        selectEl = document.getElementById(`viewTooth_${String(toothNum)}_status`);
                    }
                    
                    if (selectEl) {
                        // Set the value (empty string for unfilled, or actual status value)
                        selectEl.value = statusValue;
                        
                        // Update visual marking: only mark if there's a non-empty status
                        const container = selectEl.closest('.tooth-container');
                        if (container) {
                            const diagram = container.querySelector('.tooth-diagram');
                            if (diagram) {
                                if (statusValue && statusValue !== '') {
                                diagram.classList.add('marked');
                                } else {
                                    diagram.classList.remove('marked');
                            }
                        }
                        }
                        
                        populatedCount++;
                        if (statusValue) {
                            console.log(`✓ Set tooth ${toothNum} to status: "${statusValue}"`);
                        } else {
                            unfilledCount++;
                            console.log(`✓ Set tooth ${toothNum} to unfilled (empty)`);
                        }
                    } else {
                        notFoundCount++;
                        console.warn(`✗ Select element not found for tooth ${toothNum} (tried: viewTooth_${toothNum}_status)`);
                    }
                });
                
                console.log(`Populated ${populatedCount} teeth (${filledTeeth} filled, ${unfilledCount} unfilled)`);
                if (notFoundCount > 0) {
                    console.warn(`${notFoundCount} teeth could not be found in the chart`);
                }
            } else {
                console.warn('toothStatus is not a valid object:', toothStatus);
            }
        }, 100);
        
        // Populate treatment records
        const treatmentBody = document.getElementById('viewDentalTreatmentBody');
        if (treatmentBody) {
            treatmentBody.innerHTML = '';
            
            // treatments should already be decoded as an array from PHP
            let treatments = [];
            if (record.treatments) {
                if (Array.isArray(record.treatments)) {
                    treatments = record.treatments;
                } else if (typeof record.treatments === 'string') {
                    try {
                        treatments = JSON.parse(record.treatments || '[]');
                    } catch (e) {
                        console.error('Error parsing treatments:', e);
                        treatments = [];
                    }
                }
            }
            
            if (treatments.length === 0) {
                treatmentBody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #999;">No treatments recorded</td></tr>';
            } else {
                treatments.forEach(treatment => {
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>${treatment.date || 'N/A'}</td>
                        <td>${treatment.tooth || 'N/A'}</td>
                        <td>${treatment.operation || 'N/A'}</td>
                        <td>${treatment.dentist || record.physician_name || 'N/A'}</td>
                    `;
                    treatmentBody.appendChild(row);
                });
            }
        }
        
        // Populate remarks
        document.getElementById('viewDentalRemarks').value = record.remarks || '';
        
        // Store record index in modal for edit functionality
        modalElement.dataset.recordIndex = index;
        
        // Reset edit mode (make sure fields are readonly initially)
        if (typeof cancelDentalRecordEdit === 'function') {
            cancelDentalRecordEdit();
        }
        
        // Function to populate index tables (called after modal is shown)
        const populateIndexTables = () => {
            console.log('Populating index tables...');
            console.log('Record index_data:', record.index_data);
            console.log('Record full data:', record);
            
            // Handle index_data - it might be a string (JSON) or already an object
            let indexData = { temporary: {}, permanent: {} };
            if (record.index_data) {
                if (typeof record.index_data === 'string') {
                    try {
                        indexData = JSON.parse(record.index_data);
                    } catch (e) {
                        console.error('Error parsing index_data JSON:', e);
                    }
                } else if (typeof record.index_data === 'object' && record.index_data !== null) {
                    indexData = record.index_data;
                }
            }
            
            console.log('Parsed indexData:', indexData);
            console.log('Temporary data:', indexData.temporary);
            console.log('Permanent data:', indexData.permanent);
            
            // Populate temporary teeth index table (6 visits)
            for (let i = 1; i <= 6; i++) {
                // Handle both numeric and string keys
                const visitData = (indexData.temporary && (indexData.temporary[i] || indexData.temporary[String(i)])) 
                    ? (indexData.temporary[i] || indexData.temporary[String(i)]) 
                    : {};
                
                const decayedInput = document.getElementById(`viewTempDecayed${i}`);
                const filledInput = document.getElementById(`viewTempFilled${i}`);
                const totalInput = document.getElementById(`viewTempTotal${i}`);
                
                if (decayedInput) {
                    decayedInput.value = visitData.decayed || '';
                    console.log(`Set viewTempDecayed${i} to:`, visitData.decayed || '');
                } else {
                    console.warn(`Input viewTempDecayed${i} not found`);
                }
                if (filledInput) {
                    filledInput.value = visitData.filled || '';
                    console.log(`Set viewTempFilled${i} to:`, visitData.filled || '');
                }
                if (totalInput) {
                    totalInput.value = visitData.total || '';
                    console.log(`Set viewTempTotal${i} to:`, visitData.total || '');
                }
            }
            
            // Populate permanent teeth index table (4 visits)
            for (let i = 1; i <= 4; i++) {
                // Handle both numeric and string keys
                const visitData = (indexData.permanent && (indexData.permanent[i] || indexData.permanent[String(i)])) 
                    ? (indexData.permanent[i] || indexData.permanent[String(i)]) 
                    : {};
                
                const dInput = document.getElementById(`viewPermD${i}`);
                const mInput = document.getElementById(`viewPermM${i}`);
                const fInput = document.getElementById(`viewPermF${i}`);
                const totalInput = document.getElementById(`viewPermTotal${i}`);
                
                if (dInput) {
                    dInput.value = visitData.d || '';
                    console.log(`Set viewPermD${i} to:`, visitData.d || '');
                } else {
                    console.warn(`Input viewPermD${i} not found`);
                }
                if (mInput) {
                    mInput.value = visitData.m || '';
                    console.log(`Set viewPermM${i} to:`, visitData.m || '');
                }
                if (fInput) {
                    fInput.value = visitData.f || '';
                    console.log(`Set viewPermF${i} to:`, visitData.f || '');
                }
                if (totalInput) {
                    totalInput.value = visitData.total || '';
                    console.log(`Set viewPermTotal${i} to:`, visitData.total || '');
                }
            }
        };
        
        // Show modal first
        modal.show();
        
        // Populate index tables after modal is shown (use setTimeout to ensure DOM is ready)
        setTimeout(() => {
            populateIndexTables();
        }, 100);
    }
    
    // Show medical/consultation record modal (simple summary view)
    function showMedicalRecordModal(record, index) {
        const modalElement = document.getElementById('medicalRecordModal');
        if (!modalElement) {
            // Fallback to SweetAlert if modal doesn't exist
            Swal.fire({
                icon: 'info',
                title: record.record_type === 'Consultation' ? 'Consultation Record' : 'Medical Record',
                html: `
                    <div style="text-align: left;">
                        <p><strong>Date:</strong> ${formatDate(record.visit_date || record.assessment_date)}</p>
                        <p><strong>Type:</strong> ${record.record_type || 'Medical'}</p>
                        <p><strong>Chief Complaint:</strong> ${record.chief_complaint || 'N/A'}</p>
                        <p><strong>Diagnosis:</strong> ${record.diagnosis || 'N/A'}</p>
                        <p><strong>Treatment:</strong> ${record.treatment_instructions || record.treatment || 'N/A'}</p>
                        <p><strong>Physician:</strong> ${record.physician_name || 'N/A'}</p>
                    </div>
                `,
                confirmButtonColor: '#8b2332',
                width: '600px'
            });
            return;
        }
        
        // Use existing medical record modal if available
        // Similar to medical_patients.php implementation
        Swal.fire({
            icon: 'info',
            title: record.record_type === 'Consultation' ? 'Consultation Record' : 'Medical Record',
            html: `
                <div style="text-align: left;">
                    <p><strong>Date:</strong> ${formatDate(record.visit_date || record.assessment_date)}</p>
                    <p><strong>Type:</strong> ${record.record_type || 'Medical'}</p>
                    <p><strong>Chief Complaint:</strong> ${record.chief_complaint || 'N/A'}</p>
                    <p><strong>Diagnosis:</strong> ${record.diagnosis || 'N/A'}</p>
                    <p><strong>Treatment:</strong> ${record.treatment_instructions || record.treatment || 'N/A'}</p>
                    <p><strong>Physician:</strong> ${record.physician_name || 'N/A'}</p>
                </div>
            `,
            confirmButtonColor: '#8b2332',
            width: '600px'
        });
    }
    
    // Store original record data for cancel functionality
    let originalDentalRecordData = null;
    let currentDentalRecordIndex = null;
    
    // Enable edit mode for dental record
    function enableDentalRecordEdit() {
        // Store original data
        const modal = document.getElementById('dentalRecordModal');
        const recordIndex = modal.dataset.recordIndex;
        if (recordIndex !== undefined && window.medicalRecordsData && window.medicalRecordsData[recordIndex]) {
            originalDentalRecordData = JSON.parse(JSON.stringify(window.medicalRecordsData[recordIndex]));
            currentDentalRecordIndex = recordIndex;
        }
        
        // Make all editable fields non-readonly
        const editableFields = [
            'viewDentalRemarks',
            'viewDentalGingivitis', 'viewDentalEarlyPeriodontitis',
            'viewDentalClassMolar', 'viewDentalOverjet', 'viewDentalOverbite',
            'viewDentalOrthodontic', 'viewDentalStayplate',
            'viewDentalClenching', 'viewDentalClicking'
        ];
        
        editableFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                if (field.type === 'checkbox') {
                    field.disabled = false;
                } else {
                    field.readOnly = false;
                    field.removeAttribute('readonly');
                    field.classList.remove('readonly-field');
                }
            }
        });
        
        // Make tooth chart editable - enable all select dropdowns
        const toothSelects = document.querySelectorAll('#viewDentalChartWrapper select, .dental-chart-wrapper select');
        toothSelects.forEach(select => {
            select.disabled = false;
            select.removeAttribute('disabled');
            select.style.pointerEvents = 'auto';
            select.style.cursor = 'pointer';
            
            // Add change listener to sync visual marking with select value
            const updateVisualMarking = () => {
                const container = select.closest('.tooth-container');
                if (container) {
                    const diagram = container.querySelector('.tooth-diagram');
                    if (diagram) {
                        const value = select.value ? select.value.trim() : '';
                        if (value && value !== '') {
                            diagram.classList.add('marked');
                        } else {
                            // Unfilled/unmarked state - remove marking
                            diagram.classList.remove('marked');
                        }
                    }
                }
            };
            
            // Update visual marking when select changes
            select.addEventListener('change', updateVisualMarking);
            
            // Set initial visual state
            updateVisualMarking();
        });
        
        // Make tooth circles clickable
        const toothDiagrams = document.querySelectorAll('#viewDentalChartWrapper .tooth-diagram, .dental-chart-wrapper .tooth-diagram');
        toothDiagrams.forEach(diagram => {
            diagram.style.cursor = 'pointer';
            diagram.style.pointerEvents = 'auto';
            // Add click handler if not already present
            if (!diagram.hasAttribute('data-edit-handler')) {
                diagram.setAttribute('data-edit-handler', 'true');
                diagram.onclick = function() {
                    toggleTooth(this);
                    // Also focus the corresponding select dropdown
                    const container = this.closest('.tooth-container');
                    if (container) {
                        const select = container.querySelector('select');
                        if (select) {
                            select.focus();
                        }
                    }
                };
            }
        });
        
        // Make index table inputs editable
        const indexInputs = document.querySelectorAll('#viewDentalChartWrapper input[readonly], .index-table input[readonly]');
        indexInputs.forEach(input => {
            input.readOnly = false;
            input.removeAttribute('readonly');
            input.classList.remove('readonly-field');
        });
        
        // Make treatment table inputs editable (if they exist)
        const treatmentInputs = document.querySelectorAll('.treatment-table-standalone input[readonly]');
        treatmentInputs.forEach(input => {
            input.readOnly = false;
            input.removeAttribute('readonly');
            input.classList.remove('readonly-field');
        });
        
        // Show/hide buttons
        document.getElementById('editDentalRecordBtn').style.display = 'none';
        document.getElementById('updateDentalRecordBtn').style.display = 'inline-block';
        document.getElementById('cancelDentalEditBtn').style.display = 'inline-block';
    }
    
    // Cancel edit mode for dental record
    function cancelDentalRecordEdit() {
        if (!originalDentalRecordData) return;
        
        // Restore original data
        const record = originalDentalRecordData;
        
        // Restore checkboxes
        document.getElementById('viewDentalGingivitis').checked = record.gingivitis == 1;
        document.getElementById('viewDentalEarlyPeriodontitis').checked = record.early_periodontitis == 1;
        document.getElementById('viewDentalClassMolar').checked = record.class_molar == 1;
        document.getElementById('viewDentalOverjet').checked = record.overjet == 1;
        document.getElementById('viewDentalOverbite').checked = record.overbite == 1;
        document.getElementById('viewDentalOrthodontic').checked = record.orthodontic == 1;
        document.getElementById('viewDentalStayplate').checked = record.stayplate == 1;
        document.getElementById('viewDentalClenching').checked = record.clenching == 1;
        document.getElementById('viewDentalClicking').checked = record.clicking == 1;
        document.getElementById('viewDentalRemarks').value = record.remarks || '';
        
        // Restore tooth chart - ensure all teeth are restored, including unfilled ones
        const toothStatus = (record.tooth_status && typeof record.tooth_status === 'object') 
            ? record.tooth_status 
            : (typeof record.tooth_status === 'string' ? JSON.parse(record.tooth_status || '{}') : {});
        
        // Define all tooth numbers to ensure all teeth are processed
        const allToothNumbers = [
            55, 54, 53, 52, 51, 61, 62, 63, 64, 65, // Upper temp
            18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28, // Upper perm
            48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38, // Lower perm
            85, 84, 83, 82, 81, 71, 72, 73, 74, 75 // Lower temp
        ];
        
        // Process all teeth, including unfilled ones (empty string)
        allToothNumbers.forEach(toothNum => {
            const toothKey = String(toothNum);
            const statusValue = (toothStatus[toothKey] !== undefined) ? (toothStatus[toothKey] || '') : '';
            const selectEl = document.getElementById(`viewTooth_${toothNum}_status`);
            
            if (selectEl) {
                selectEl.value = statusValue;
                
                // Update visual marking
                const container = selectEl.closest('.tooth-container');
                if (container) {
                    const diagram = container.querySelector('.tooth-diagram');
                    if (diagram) {
                        if (statusValue && statusValue !== '') {
                            diagram.classList.add('marked');
                        } else {
                            diagram.classList.remove('marked');
                        }
                    }
                }
            }
        });
        
        // Make fields readonly again
        const editableFields = [
            'viewDentalRemarks',
            'viewDentalGingivitis', 'viewDentalEarlyPeriodontitis',
            'viewDentalClassMolar', 'viewDentalOverjet', 'viewDentalOverbite',
            'viewDentalOrthodontic', 'viewDentalStayplate',
            'viewDentalClenching', 'viewDentalClicking'
        ];
        
        editableFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                if (field.type === 'checkbox') {
                    field.disabled = true;
                } else {
                    field.readOnly = true;
                    field.setAttribute('readonly', 'readonly');
                    field.classList.add('readonly-field');
                }
            }
        });
        
        // Make tooth chart readonly - disable all select dropdowns
        const toothSelects = document.querySelectorAll('#viewDentalChartWrapper select, .dental-chart-wrapper select');
        toothSelects.forEach(select => {
            select.disabled = true;
            select.setAttribute('disabled', 'disabled');
            select.style.pointerEvents = 'none';
            select.style.cursor = 'not-allowed';
        });
        
        // Remove click handlers from tooth diagrams
        const toothDiagrams = document.querySelectorAll('#viewDentalChartWrapper .tooth-diagram, .dental-chart-wrapper .tooth-diagram');
        toothDiagrams.forEach(diagram => {
            diagram.style.cursor = 'default';
            diagram.style.pointerEvents = 'none';
            diagram.onclick = null;
            diagram.removeAttribute('data-edit-handler');
        });
        
        // Make index table inputs readonly again
        const indexInputs = document.querySelectorAll('#viewDentalChartWrapper input, .index-table input');
        indexInputs.forEach(input => {
            if (input.id && (input.id.includes('viewTemp') || input.id.includes('viewPerm'))) {
                input.readOnly = true;
                input.setAttribute('readonly', 'readonly');
                input.classList.add('readonly-field');
            }
        });
        
        // Make treatment table inputs readonly again
        const treatmentInputs = document.querySelectorAll('.treatment-table-standalone input');
        treatmentInputs.forEach(input => {
            input.readOnly = true;
            input.setAttribute('readonly', 'readonly');
            input.classList.add('readonly-field');
        });
        
        // Show/hide buttons
        document.getElementById('editDentalRecordBtn').style.display = 'inline-block';
        document.getElementById('updateDentalRecordBtn').style.display = 'none';
        document.getElementById('cancelDentalEditBtn').style.display = 'none';
        
        originalDentalRecordData = null;
        currentDentalRecordIndex = null;
    }
    
    // Update dental record
    async function updateDentalRecord() {
        if (currentDentalRecordIndex === null || !window.medicalRecordsData || !window.medicalRecordsData[currentDentalRecordIndex]) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Record data not found. Please refresh and try again.',
                confirmButtonColor: '#8b2332'
            });
            return;
        }
        
        const record = window.medicalRecordsData[currentDentalRecordIndex];
        const dentalRecordId = record.dental_record_id || record.id;
        
        if (!dentalRecordId) {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Dental record ID not found.',
                confirmButtonColor: '#8b2332'
            });
            return;
        }
        
        // Collect updated data
        const updatedData = {
            dental_record_id: dentalRecordId,
            gingivitis: document.getElementById('viewDentalGingivitis').checked ? 1 : 0,
            early_periodontitis: document.getElementById('viewDentalEarlyPeriodontitis').checked ? 1 : 0,
            class_molar: document.getElementById('viewDentalClassMolar').checked ? 1 : 0,
            overjet: document.getElementById('viewDentalOverjet').checked ? 1 : 0,
            overbite: document.getElementById('viewDentalOverbite').checked ? 1 : 0,
            orthodontic: document.getElementById('viewDentalOrthodontic').checked ? 1 : 0,
            stayplate: document.getElementById('viewDentalStayplate').checked ? 1 : 0,
            clenching: document.getElementById('viewDentalClenching').checked ? 1 : 0,
            clicking: document.getElementById('viewDentalClicking').checked ? 1 : 0,
            remarks: document.getElementById('viewDentalRemarks').value || ''
        };
        
        // Collect tooth status from all tooth select dropdowns
        // Define all tooth numbers that should be tracked
        const allToothNumbers = [
            // Upper temporary
            55, 54, 53, 52, 51, 61, 62, 63, 64, 65,
            // Upper permanent
            18, 17, 16, 15, 14, 13, 12, 11, 21, 22, 23, 24, 25, 26, 27, 28,
            // Lower permanent
            48, 47, 46, 45, 44, 43, 42, 41, 31, 32, 33, 34, 35, 36, 37, 38,
            // Lower temporary
            85, 84, 83, 82, 81, 71, 72, 73, 74, 75
        ];
        
        // Initialize all teeth with empty string (unfilled state)
        const toothStatus = {};
        allToothNumbers.forEach(num => {
            toothStatus[String(num)] = ''; // Empty string = unfilled/unmarked
        });
        
        // Collect actual values from select dropdowns (including empty ones for unfilled teeth)
        const toothSelects = document.querySelectorAll('#viewDentalChartWrapper select, .dental-chart-wrapper select');
        toothSelects.forEach(select => {
            // Extract tooth number from ID (format: viewTooth_XX_status)
            let toothNum = select.id.replace('viewTooth_', '').replace('_status', '');
            // Also check name attribute as fallback (format: tooth_XX_status)
            if (!toothNum && select.name) {
                const nameMatch = select.name.match(/tooth_(\d+)_status/);
                if (nameMatch) {
                    toothNum = nameMatch[1];
                }
            }
            
            if (toothNum) {
                // Save the value (even if empty string - this represents unfilled/unmarked state)
                const value = select.value ? select.value.trim() : '';
                toothStatus[String(toothNum)] = value;
            }
        });
        
        // Stringify with proper handling to preserve empty strings
        updatedData.tooth_status = JSON.stringify(toothStatus);
        
        const filledCount = Object.values(toothStatus).filter(v => v && v !== '').length;
        const unfilledCount = Object.values(toothStatus).filter(v => !v || v === '').length;
        console.log('Collected tooth status for update:', {
            total: Object.keys(toothStatus).length,
            filled: filledCount,
            unfilled: unfilledCount,
            data: toothStatus
        });
        
        // Collect index_data from index table inputs
        const indexData = {
            temporary: {},
            permanent: {}
        };
        
        // Collect temporary teeth index data (6 visits)
        for (let i = 1; i <= 6; i++) {
            const decayedInput = document.getElementById(`viewTempDecayed${i}`);
            const filledInput = document.getElementById(`viewTempFilled${i}`);
            const totalInput = document.getElementById(`viewTempTotal${i}`);
            
            if (decayedInput || filledInput || totalInput) {
                indexData.temporary[String(i)] = {
                    decayed: decayedInput ? (decayedInput.value || '') : '',
                    filled: filledInput ? (filledInput.value || '') : '',
                    total: totalInput ? (totalInput.value || '') : ''
                };
            }
        }
        
        // Collect permanent teeth index data (4 visits)
        for (let i = 1; i <= 4; i++) {
            const dInput = document.getElementById(`viewPermD${i}`);
            const mInput = document.getElementById(`viewPermM${i}`);
            const fInput = document.getElementById(`viewPermF${i}`);
            const totalInput = document.getElementById(`viewPermTotal${i}`);
            const teethInput = document.getElementById(`viewPermTeeth${i}`);
            
            if (dInput || mInput || fInput || totalInput || teethInput) {
                indexData.permanent[String(i)] = {
                    d: dInput ? (dInput.value || '') : '',
                    m: mInput ? (mInput.value || '') : '',
                    f: fInput ? (fInput.value || '') : '',
                    total: totalInput ? (totalInput.value || '') : '',
                    teeth: teethInput ? (teethInput.value || '') : ''
                };
            }
        }
        
        updatedData.index_data = JSON.stringify(indexData);
        console.log('Collected index_data for update:', indexData);
        
        // Collect treatments (if editable in future, for now keep existing)
        updatedData.treatments = record.treatments ? (typeof record.treatments === 'string' ? record.treatments : JSON.stringify(record.treatments)) : '[]';
        
        console.log('Sending update request with data:', updatedData);
        
        try {
            const response = await fetch('crud/update_dental_record.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(updatedData)
            });
            
            // Check if response is OK before parsing JSON
            if (!response.ok) {
                const errorText = await response.text();
                console.error('HTTP error:', response.status, errorText);
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            
            const result = await response.json();
            console.log('Update response:', result);
            
            if (result.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Dental record updated successfully.',
                    confirmButtonColor: '#8b2332',
                    timer: 2000
                }).then(() => {
                    // Update the record in memory
                    window.medicalRecordsData[currentDentalRecordIndex] = {
                        ...window.medicalRecordsData[currentDentalRecordIndex],
                        ...updatedData,
                        tooth_status: toothStatus,
                        index_data: indexData,
                        remarks: updatedData.remarks
                    };
                    
                    // Exit edit mode
                    cancelDentalRecordEdit();
                    
                    // Reload records to get fresh data
                    if (window.currentPatientData && window.currentPatientData.id) {
                        loadMedicalRecords(window.currentPatientData.id);
                    }
                });
            } else {
                throw new Error(result.message || 'Failed to update dental record');
            }
        } catch (error) {
            console.error('Error updating dental record:', error);
            console.error('Error details:', {
                name: error.name,
                message: error.message,
                stack: error.stack
            });
            
            let errorMessage = 'Failed to update dental record. Please try again.';
            if (error.message) {
                errorMessage = error.message;
            } else if (error instanceof TypeError && error.message.includes('JSON')) {
                errorMessage = 'Invalid response from server. Please refresh and try again.';
            } else if (error instanceof SyntaxError) {
                errorMessage = 'Server response error. Please check your connection and try again.';
            }
            
            Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: errorMessage,
                confirmButtonColor: '#8b2332',
                footer: 'If this problem persists, please contact support.'
            });
        }
    }
  </script>
  
  <style>
    .medical-record-list-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      pointer-events: auto;
      position: relative;
      z-index: 1;
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
  
  <!-- Dental Record View Modal -->
  <div class="modal fade" id="dentalRecordModal" tabindex="-1" aria-labelledby="dentalRecordModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content" style="border: none;">
        <div class="modal-header" style="background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%); color: white;">
          <h5 class="modal-title" id="dentalRecordModalLabel">
            <i class="bi bi-tooth me-2"></i>Dental Record
          </h5>
          <div class="d-flex gap-2">
            <button type="button" id="editDentalRecordBtn" class="btn btn-warning btn-sm" onclick="enableDentalRecordEdit()" style="display: inline-block;">
              <i class="bi bi-pencil me-1"></i>Edit
            </button>
            <button type="button" id="updateDentalRecordBtn" class="btn btn-success btn-sm" onclick="updateDentalRecord()" style="display: none;">
              <i class="bi bi-check-circle me-1"></i>Update
            </button>
            <button type="button" id="cancelDentalEditBtn" class="btn btn-secondary btn-sm" onclick="cancelDentalRecordEdit()" style="display: none;">
              <i class="bi bi-x-circle me-1"></i>Cancel
            </button>
            <button type="button" class="btn btn-light btn-sm" onclick="printDentalRecordView()">
              <i class="bi bi-printer me-1"></i>Print
            </button>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
        </div>
        <div class="modal-body" id="dentalRecordPrintContent" style="padding: 0;">
          <div class="consultation-form-container">
            <div class="document-header">
              <div class="header-top">
                <div class="header-logo">
                  <img src="../img/bsu-logo.png" alt="BSU Logo" style="width: 80px; height: 80px; object-fit: contain;">
                </div>
                <div class="header-info-boxes">
                  <div class="info-box">
                    <label>Reference No.:</label>
                    <span>BatStateU-FO-HSD-11</span>
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
                <h2>DENTAL EXAMINATION RECORD</h2>
              </div>
            </div>

            <div class="dental-form" style="padding: 20px;">
              <!-- Patient Information Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Patient Information</h4>
                <div class="form-row">
                  <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" id="viewDentalFullName" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>SR Code <span class="required">*</span></label>
                    <input type="text" id="viewDentalSRCode" readonly class="readonly-field">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Address</label>
                    <input type="text" id="viewDentalAddress" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Program</label>
                    <input type="text" id="viewDentalProgram" readonly class="readonly-field">
                  </div>
                </div>
                <div class="form-row">
                  <div class="form-group">
                    <label>Date</label>
                    <input type="date" id="viewDentalDate" readonly class="readonly-field">
                  </div>
                  <div class="form-group">
                    <label>Dentist</label>
                    <input type="text" id="viewDentalDentist" readonly class="readonly-field">
                  </div>
                </div>
              </div>

              <!-- Dentition Status Section -->
              <div class="consultation-section">
                <h4 class="consultation-section-title">Dentition Status and Treatment Needs</h4>
                <div class="dental-chart-wrapper" id="viewDentalChartWrapper">
                  <div class="teeth-section-standalone">
                    <div>
                      <div class="teeth-label">Temporary Teeth - Right <span style="float: right;">Left</span></div>
                      <div class="teeth-row" id="viewUpperTempTeeth"></div>
                    </div>
                    <div style="margin-top: 20px;">
                      <div class="teeth-row" id="viewUpperPermTeeth"></div>
                    </div>
                    <div style="margin-top: 30px;">
                      <div class="teeth-row" id="viewLowerPermTeeth"></div>
                    </div>
                    <div style="margin-top: 20px;">
                      <div class="teeth-row" id="viewLowerTempTeeth"></div>
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
                          <td><input type="text" id="viewTempDecayed1" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempDecayed2" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempDecayed3" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempDecayed4" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempDecayed5" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempDecayed6" readonly class="readonly-field"></td>
                        </tr>
                        <tr>
                          <td class="label-cell">No. T/Filled</td>
                          <td><input type="text" id="viewTempFilled1" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempFilled2" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempFilled3" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempFilled4" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempFilled5" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempFilled6" readonly class="readonly-field"></td>
                        </tr>
                        <tr>
                          <td class="label-cell">Total d.f.t.</td>
                          <td><input type="text" id="viewTempTotal1" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempTotal2" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempTotal3" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempTotal4" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempTotal5" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewTempTotal6" readonly class="readonly-field"></td>
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
                          <td><input type="text" id="viewPermD1" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermD2" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermD3" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermD4" readonly class="readonly-field"></td>
                        </tr>
                        <tr>
                          <td class="label-cell">M</td>
                          <td><input type="text" id="viewPermM1" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermM2" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermM3" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermM4" readonly class="readonly-field"></td>
                        </tr>
                        <tr>
                          <td class="label-cell">F</td>
                          <td><input type="text" id="viewPermF1" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermF2" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermF3" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermF4" readonly class="readonly-field"></td>
                        </tr>
                        <tr>
                          <td class="label-cell">Total DMF</td>
                          <td><input type="text" id="viewPermTotal1" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermTotal2" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermTotal3" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermTotal4" readonly class="readonly-field"></td>
                        </tr>
                        <tr>
                          <td class="label-cell">Total no of Teeth</td>
                          <td><input type="text" id="viewPermTeeth1" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermTeeth2" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermTeeth3" readonly class="readonly-field"></td>
                          <td><input type="text" id="viewPermTeeth4" readonly class="readonly-field"></td>
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
                    <tbody id="viewDentalTreatmentBody">
                      <tr><td colspan="4" style="text-align: center; color: #999;">No treatments recorded</td></tr>
                    </tbody>
                  </table>
                </div>
              </div>

              <!-- Legend Section with Checkboxes -->
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
                      <input type="checkbox" id="viewDentalGingivitis" disabled> Gingivitis
                    </div>
                    <div class="legend-item">
                      <input type="checkbox" id="viewDentalEarlyPeriodontitis" disabled> Early Periodontitis
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
                      <input type="checkbox" id="viewDentalClassMolar" disabled> Class (Molar)
                    </div>
                    <div class="legend-item">
                      <input type="checkbox" id="viewDentalOverjet" disabled> Overjet
                    </div>
                    <div class="legend-item">
                      <input type="checkbox" id="viewDentalOverbite" disabled> Overbite
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
                      <input type="checkbox" id="viewDentalOrthodontic" disabled> Orthodontic
                    </div>
                    <div class="legend-item">
                      <input type="checkbox" id="viewDentalStayplate" disabled> Stayplate
                    </div>

                    <h5 style="margin-top: 15px;">TMD:</h5>
                    <div class="legend-item">
                      <input type="checkbox" id="viewDentalClenching" disabled> Clenching
                    </div>
                    <div class="legend-item">
                      <input type="checkbox" id="viewDentalClicking" disabled> Clicking
                    </div>
                  </div>
                </div>
              </div>

              <!-- Remarks Section -->
              <div class="remarks-section">
                <label for="viewDentalRemarks">Remarks:</label>
                <textarea id="viewDentalRemarks" readonly class="readonly-field" style="min-height: 80px;"></textarea>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>
  
  <script>
    // Helper function to preserve all form values when cloning
    function preserveFormValues(originalContent, clonedContent) {
        // Preserve all input values, checkboxes, and select values in the cloned content
        const originalInputs = originalContent.querySelectorAll('input, select, textarea');
        const clonedInputs = clonedContent.querySelectorAll('input, select, textarea');
        
        // Create a map of IDs to preserve values by ID (more reliable)
        const valueMap = new Map();
        originalInputs.forEach(input => {
            if (input.id) {
                valueMap.set(input.id, {
                    value: input.value,
                    checked: input.checked,
                    type: input.type,
                    tagName: input.tagName
                });
            }
        });
        
        // Apply values to cloned inputs by ID
        clonedInputs.forEach(clonedInput => {
            if (clonedInput.id && valueMap.has(clonedInput.id)) {
                const data = valueMap.get(clonedInput.id);
                
                if (data.type === 'checkbox' || data.type === 'radio') {
                    clonedInput.checked = data.checked;
                } else if (clonedInput.tagName === 'SELECT') {
                    clonedInput.value = data.value;
                    // Also set the selected option
                    const clonedOptions = clonedInput.querySelectorAll('option');
                    clonedOptions.forEach(opt => {
                        opt.selected = (opt.value === data.value);
                    });
                } else {
                    clonedInput.value = data.value || '';
                }
            } else {
                // Fallback: match by index if no ID
                const index = Array.from(clonedInputs).indexOf(clonedInput);
                if (index < originalInputs.length) {
                    const originalInput = originalInputs[index];
                    if (originalInput.type === 'checkbox' || originalInput.type === 'radio') {
                        clonedInput.checked = originalInput.checked;
                    } else if (clonedInput.tagName === 'SELECT') {
                        clonedInput.value = originalInput.value;
                        const clonedOptions = clonedInput.querySelectorAll('option');
                        clonedOptions.forEach(opt => {
                            opt.selected = (opt.value === originalInput.value);
                        });
                    } else {
                        clonedInput.value = originalInput.value || '';
                    }
                }
            }
            
            // Ensure readonly fields display their values
            if (clonedInput.hasAttribute('readonly') || clonedInput.readOnly) {
                clonedInput.style.backgroundColor = '#f9f9f9';
                clonedInput.style.border = '1px solid #ddd';
            }
        });
        
        return clonedContent;
    }
    
    // Print function for dental record view
    function printDentalRecordView() {
        const originalContent = document.getElementById('dentalRecordPrintContent');
        const printContent = originalContent.cloneNode(true);
        
        // Preserve all form values
        preserveFormValues(originalContent, printContent);
        
        // Get all computed styles for the content
        const styles = getComputedStylesForPrint();
        
        const printWindow = window.open('', '_blank');
        const patientName = document.getElementById('viewDentalFullName')?.value || 'Patient';
        const date = document.getElementById('viewDentalDate')?.value || new Date().toISOString().split('T')[0];
        
        printWindow.document.write(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Dental Record - ${patientName}</title>
                <meta charset="UTF-8">
                <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
                <style>
                    ${styles}
                </style>
            </head>
            <body>
                ${printContent.innerHTML}
            </body>
            </html>
        `);
        printWindow.document.close();
        
        // Wait for content to load, then ensure all values are set
        setTimeout(() => {
            // Double-check all values are set in the print window using the helper function
            const printDoc = printWindow.document;
            const printBody = printDoc.body;
            if (printBody) {
                preserveFormValues(originalContent, printBody);
            }
            
            printWindow.focus();
            setTimeout(() => { 
                printWindow.print(); 
                setTimeout(() => printWindow.close(), 500);
            }, 100);
        }, 300);
    }
    
    function getComputedStylesForPrint() {
        return `
            * {
                box-sizing: border-box;
            }
            
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                padding: 20px;
                background: white;
                color: #333;
            }
            
            .consultation-form-container {
                background: white;
                max-width: 100%;
            }
            
            .document-header {
                margin-bottom: 30px;
            }
            
            .header-top {
                display: flex;
                align-items: center;
                gap: 20px;
                margin-bottom: 20px;
            }
            
            .header-logo img {
                width: 80px;
                height: 80px;
                object-fit: contain;
            }
            
            .header-info-boxes {
                display: flex;
                gap: 15px;
                flex-wrap: wrap;
            }
            
            .info-box {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .info-box label {
                font-size: 11px;
                font-weight: 600;
                color: #666;
            }
            
            .info-box span {
                font-size: 12px;
                color: #333;
            }
            
            .header-title h2 {
                text-align: center;
                font-size: 24px;
                font-weight: 700;
                color: #8b2332;
                margin: 0;
                padding: 15px 0;
                border-top: 3px solid #8b2332;
                border-bottom: 3px solid #8b2332;
            }
            
            .dental-form {
                padding: 20px;
            }
            
            .consultation-section {
                margin-bottom: 30px;
            }
            
            .consultation-section-title {
                color: #8b2332;
                font-size: 18px;
                font-weight: 700;
                margin-bottom: 15px;
                padding-bottom: 8px;
                border-bottom: 2px solid #8b2332;
            }
            
            .form-row {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 15px;
                margin-bottom: 15px;
            }
            
            .form-group {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }
            
            .form-group label {
                font-weight: 600;
                font-size: 14px;
                color: #333;
            }
            
            .form-group label .required {
                color: #dc3545;
            }
            
            .readonly-field {
                background: #f9f9f9 !important;
                border: 1px solid #ddd !important;
                padding: 8px 12px !important;
                border-radius: 4px;
                font-size: 14px;
                color: #333 !important;
                -webkit-text-fill-color: #333 !important;
            }
            
            input[readonly],
            textarea[readonly],
            select[disabled] {
                background: #f9f9f9 !important;
                color: #333 !important;
                -webkit-text-fill-color: #333 !important;
            }
            
            input[type="text"],
            input[type="date"],
            textarea {
                color: #333 !important;
                -webkit-text-fill-color: #333 !important;
            }
            
            .dental-chart-wrapper {
                padding: 20px;
                background: white;
                border-radius: 8px;
                border: 2px solid #8b2332;
                margin-bottom: 25px;
            }
            
            .teeth-section-standalone {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }
            
            .teeth-label {
                font-weight: 600;
                font-size: 13px;
                color: #333;
                margin-bottom: 8px;
            }
            
            .teeth-row {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .tooth-container {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 5px;
            }
            
            .tooth-number {
                font-size: 11px;
                font-weight: 600;
                color: #333;
            }
            
            .tooth-diagram {
                width: 32px;
                height: 32px;
                border: 2px solid #333;
                border-radius: 50%;
                position: relative;
                background: white;
            }
            
            .tooth-diagram.marked {
                background: #ffebee;
                border-color: #8b2332;
            }
            
            .tooth-diagram.marked::before,
            .tooth-diagram.marked::after {
                content: '';
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                background: #8b2332;
                width: 70%;
                height: 2px;
            }
            
            .tooth-diagram.marked::before {
                transform: translate(-50%, -50%) rotate(45deg);
            }
            
            .tooth-diagram.marked::after {
                transform: translate(-50%, -50%) rotate(-45deg);
            }
            
            .tooth-select {
                width: 50px;
                padding: 4px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 11px;
                text-align: center;
            }
            
            .index-tables-container {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 20px;
                margin-top: 25px;
            }
            
            .index-table-wrapper {
                background: white;
                border: 2px solid #333;
                border-radius: 4px;
                overflow: hidden;
            }
            
            .index-table-title {
                background: #f8f9fa;
                padding: 10px 15px;
                margin: 0;
                font-size: 14px;
                font-weight: 700;
                border-bottom: 2px solid #333;
            }
            
            .index-table {
                width: 100%;
                border-collapse: collapse;
                background: white;
            }
            
            .index-table thead th {
                background: #f8f9fa;
                border: 1px solid #333;
                padding: 8px 6px;
                font-size: 11px;
                font-weight: 700;
                text-align: center;
            }
            
            .index-table tbody td {
                border: 1px solid #333;
                padding: 0;
                text-align: center;
                height: 35px;
            }
            
            .index-table tbody td.label-cell {
                background: #f8f9fa;
                font-weight: 600;
                font-size: 11px;
                padding: 8px;
                text-align: left;
            }
            
            .index-table tbody td input {
                width: 100%;
                height: 100%;
                border: none;
                padding: 8px 4px;
                text-align: center;
                font-size: 12px;
                background: white;
            }
            
            .index-table tbody td input[readonly] {
                background: #f0f0f0;
                color: #666;
                font-weight: 600;
            }
            
            .index-legend {
                padding: 10px 15px;
                background: #f8f9fa;
                border-top: 1px solid #333;
                font-size: 11px;
            }
            
            .treatment-record-wrapper {
                margin-top: 20px;
            }
            
            .treatment-table-standalone {
                width: 100%;
                border-collapse: collapse;
                background: white;
                table-layout: fixed;
                border: 2px solid #000;
                margin-bottom: 15px;
            }
            
            .treatment-table-standalone thead {
                background: #f8f9fa;
            }
            
            .treatment-table-standalone th {
                border-bottom: 2px solid #000;
                border-right: 1px solid #000;
                padding: 12px 8px;
                font-size: 13px;
                background: #f8f9fa;
                font-weight: 700;
                text-align: center;
            }
            
            .treatment-table-standalone th:last-child {
                border-right: none;
            }
            
            .treatment-table-standalone tbody tr td {
                border-right: 1px solid #000;
                border-bottom: 1px solid #000;
                padding: 0;
                background: white;
                height: 45px;
            }
            
            .treatment-table-standalone tbody tr td:last-child {
                border-right: none;
            }
            
            .treatment-table-standalone tbody tr:last-child td {
                border-bottom: none;
            }
            
            .treatment-table-standalone input {
                width: 100%;
                height: 100%;
                border: none;
                padding: 10px 8px;
                font-size: 13px;
                background: white;
            }
            
            .treatment-table-standalone input[readonly] {
                background: #f0f0f0;
            }
            
            .legend-section {
                margin: 40px 0 30px 0;
                padding: 25px;
                background: white;
                border-radius: 8px;
                border: 1px solid #e0e0e0;
            }
            
            .legend-title {
                font-size: 16px;
                font-weight: 700;
                color: #8b2332;
                margin-bottom: 15px;
            }
            
            .legend-grid {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 20px;
            }
            
            .legend-column {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }
            
            .legend-item {
                font-size: 13px;
                color: #333;
            }
            
            .legend-item strong {
                color: #8b2332;
                font-weight: 700;
            }
            
            .legend-item input[type="checkbox"] {
                margin-right: 8px;
            }
            
            .legend-item input[type="checkbox"]:checked {
                accent-color: #8b2332;
            }
            
            .remarks-section {
                margin-top: 30px;
            }
            
            .remarks-section label {
                font-weight: 600;
                font-size: 14px;
                color: #333;
                display: block;
                margin-bottom: 8px;
            }
            
            .remarks-section textarea {
                width: 100%;
                min-height: 80px;
                padding: 12px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-size: 14px;
                font-family: inherit;
                background: #f9f9f9;
            }
            
            @media print {
                body {
                    padding: 0;
                    margin: 0;
                }
                
                .consultation-form-container {
                    box-shadow: none;
                }
                
                /* Ensure all input values are visible when printing */
                input[readonly],
                textarea[readonly],
                select[disabled] {
                    background: #f9f9f9 !important;
                    color: #333 !important;
                    -webkit-text-fill-color: #333 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                input[type="text"],
                input[type="date"],
                textarea {
                    color: #333 !important;
                    -webkit-text-fill-color: #333 !important;
                }
                
                /* Ensure checkboxes are visible */
                input[type="checkbox"]:checked {
                    accent-color: #8b2332;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                .dental-chart-wrapper {
                    border: 2px solid #8b2332;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                .tooth-diagram.marked {
                    background: #ffebee !important;
                    border-color: #8b2332 !important;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                .index-table-wrapper {
                    border: 2px solid #333;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                .treatment-table-standalone {
                    border: 2px solid #000;
                    -webkit-print-color-adjust: exact;
                    print-color-adjust: exact;
                }
                
                @page {
                    margin: 1cm;
                }
            }
        `;
    }
    
    
    // Initialize notification system
    if (window.DentalNotificationSystem) {
      DentalNotificationSystem.init();
    }
  </script>
  <script src="../js/mobile-menu.js"></script>
</body>
</html>
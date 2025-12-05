<?php
require_once '../config/database.php';
require_once '../includes/patient_sync.php';
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
syncPatientRecords($pdo);

// Fetch all patients with error handling
$patients = [];
try {
    $stmt = $pdo->query("SELECT * FROM patients ORDER BY full_name ASC");
    $patients = $stmt->fetchAll();
    error_log("Fetched " . count($patients) . " patients"); // Debug log
} catch (PDOException $e) {
    error_log("Error fetching patients: " . $e->getMessage());
    $patients = []; // Ensure it's an array even on error
}

foreach ($patients as &$patient) {
    $patient['position'] = $patient['program'] ?? 'Student';
    $patient['phone'] = $patient['contact_number'] ?? null;
    // Remove civil_status as it's not used in patient records
    unset($patient['civil_status']);
}
unset($patient);

// Fetch all medicines from inventory
$medicines = [];
try {
    $stmt = $pdo->query("SELECT id, batch_number, item_code, item_name, quantity, dispensed, expiry_date, description, status FROM inventory WHERE quantity > 0 AND status = 'active' ORDER BY item_name");
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
  <link href="../admin/css/responsive.css" rel="stylesheet">
  <link href="../admin/css/notifications.css" rel="stylesheet">
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
      <!-- Mobile Menu Icon -->
      <button type="button" class="mobile-menu-icon" id="mobileMenuBtn" aria-label="Toggle navigation menu" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>
      <?php include 'notification_component.php'; ?>
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
        <a href="../admin/activity_logs.php" class="menu-item">Activity Logs</a>
        <a href="../admin/reports.php" class="menu-item">Reports & Analytics</a>
      </div>
      <div class="user-profile">
        <span><?php echo htmlspecialchars($user['fname'] . ' ' . $user['lname']); ?></span>
      </div>
    </div>

    <div class="main-content">
      <h2 class="page-title">Patients</h2>
      
      <div class="search-box">
        <i class="bi bi-search"></i>
        <input type="text" class="form-control" id="searchPatients" placeholder="Search patients...">
      </div>
      
      <div class="tab-content mt-3" id="dashboardTabsContent">
        <div class="tab-pane fade show active" id="patients" role="tabpanel">
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
                  <button class="tab-item active" onclick="switchTab('info')">Personal Information</button>
                  <button class="tab-item" onclick="switchTab('medical')">Medical Records</button>
                  <button class="tab-item" onclick="switchTab('dental')">Dental Records</button>
                </div>

                <div class="tab-content-inner active" id="infoTab">
                  <!-- Personal Information will be loaded dynamically -->
                </div>

                <div class="tab-content-inner" id="medicalTab">
                  <div class="form-section">
                    <div id="medicalRecordsContainer">
                      <p style="text-align: center; color: #999; padding: 40px;">No medical records found.</p>
                    </div>
                  </div>
                </div>

                <div class="tab-content-inner" id="dentalTab">
                  <div class="form-section">
                    <div id="dentalRecordsContainer">
                      <p style="text-align: center; color: #999; padding: 40px;">No dental records found.</p>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="../js/logout.js"></script>
<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="../admin/js/notifications.js"></script>
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

    // Patient card click handler
    document.addEventListener('DOMContentLoaded', function() {
        const patientCards = document.querySelectorAll('.patient-card');
        
        patientCards.forEach(card => {
            card.addEventListener('click', function() {
                const patientId = this.getAttribute('data-patient-id');
                loadPatientDetail(patientId);
            });
        });

        // Search functionality
        const searchInput = document.getElementById('searchPatients');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                filterPatients(this.value);
            });
        }
    });

    // Load patient details
    function loadPatientDetail(patientId) {
        const patient = patientsData.find(p => p.id == patientId);
        
        if (!patient) {
            alert('Patient not found');
            return;
        }

        const patientsSection = document.getElementById('patientsSection');
        const patientDetailSection = document.getElementById('patientDetailSection');
        
        patientsSection.style.display = 'none';
        patientDetailSection.style.display = 'block';
        patientDetailSection.classList.add('active');

        // Update detail header
        document.getElementById('detailInitials').textContent = getInitials(patient.full_name);
        document.getElementById('detailName').textContent = patient.full_name;
        document.getElementById('detailSRCode').textContent = 'SR-Code: ' + (patient.sr_code || 'N/A');
        document.getElementById('detailPosition').textContent = patient.position || patient.program || 'Student';

        // Store current patient globally
        window.currentPatientData = patient;

        // Load personal information
        loadPersonalInfo(patient);

        // Load medical and dental records
        loadMedicalRecords(patientId);
        loadDentalRecords(patientId);

        // Switch to info tab by default
        switchTab('info');
    }
    
    // View full record modal
    function viewFullRecord(recordIndex, sourceTable) {
        const records = window.medicalRecordsData || [];
        const record = records[recordIndex];
        const patient = window.currentPatientData;
        
        if (!record) {
            console.error('Record not found');
            return;
        }
        
        // Determine record type - check multiple indicators for consistency
        const isDental = record.source_table === 'dental_records' || 
                        record.is_dental === true || 
                        record.record_type === 'Dental' ||
                        record.appointment_type === 'dental';
        
        // Check if it's a consultation (medicine dispensing)
        // Consultation records: explicitly marked or from medical_consultations table
        // Medical records: from medical_records table with appointments (appointment_type = 'medical')
        const isConsultation = record.is_consultation === true || 
                              record.source_table === 'medical_consultations' ||
                              (record.record_type === 'Consultation' || record.record_type === 'Medical Consultation');
        
        // Log for debugging
        console.log('Record type detection:', {
            source_table: record.source_table,
            is_consultation: record.is_consultation,
            record_type: record.record_type,
            appointment_type: record.appointment_type,
            isConsultation: isConsultation,
            recordId: record.id
        });
        
        if (isDental) {
            showDentalRecordModal(record, patient);
        } else {
            // Pass isConsultation flag - false means show Medical Examination form
            showMedicalRecordModal(record, patient, isConsultation);
        }
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
    // Store records globally for modal viewing
    window.medicalRecordsData = [];
    window.dentalRecordsData = [];
    window.currentPatientData = null;
    
    // Load medical records
    function loadMedicalRecords(patientId) {
        fetch(`../crud/medical_records.php?patient_id=${patientId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                const container = document.getElementById('medicalRecordsContainer');
                
                // Store records globally
                window.medicalRecordsData = data.success && data.records ? data.records : [];
                
                if (data.success && data.records && data.records.length > 0) {
                    // Filter only medical records (exclude dental)
                    const medicalRecords = data.records.filter(record => {
                        const isDental = record.source_table === 'dental_records' || 
                                        record.is_dental === true || 
                                        record.record_type === 'Dental' ||
                                        record.appointment_type === 'dental';
                        return !isDental;
                    });
                    
                    if (medicalRecords.length > 0) {
                        container.innerHTML = medicalRecords.map((record, originalIndex) => {
                            // Find original index in full records array for modal viewing
                            const index = data.records.findIndex(r => r.id === record.id);
                            
                            const isConsultation = record.is_consultation === true || 
                                                  record.source_table === 'medical_consultations' ||
                                                  (record.record_type === 'Consultation' || record.record_type === 'Medical Consultation');
                            
                            let recordType, recordTypeIcon, badgeColor, badgeTextColor;
                            if (isConsultation) {
                                recordType = record.record_type || 'Medical Consultation';
                                recordTypeIcon = 'bi-clipboard-pulse';
                                badgeColor = '#fff3cd';
                                badgeTextColor = '#856404';
                            } else {
                                recordType = record.record_type || 'Medical Record';
                                recordTypeIcon = 'bi-file-medical';
                                badgeColor = '#d4edda';
                                badgeTextColor = '#155724';
                        }
                        
                        return `
                            <div class="medical-record-card">
                                <div class="record-header">
                                    <div>
                                            <strong>${formatDate(record.visit_date || record.assessment_date || record.created_at)}</strong>
                                            ${record.visit_time ? `<span class="record-time">${record.visit_time}</span>` : ''}
                                            <span class="record-type-badge" style="display: inline-block; margin-left: 10px; padding: 4px 8px; background: ${badgeColor}; color: ${badgeTextColor}; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                                <i class="bi ${recordTypeIcon}"></i> ${recordType}
                                            </span>
                                    </div>
                                    <div class="record-physician">
                                            <i class="bi bi-person-badge"></i> ${record.physician_name || record.dentist_name || 'Unknown'}
                                    </div>
                                </div>
                                    <div class="record-footer" style="margin-top: 15px; padding-top: 15px; text-align: right;">
                                        <button class="btn btn-sm btn-primary" onclick="viewFullRecord(${index}, '${record.source_table || 'medical_records'}')" style="background: #8B0000; border: none;">
                                            <i class="bi bi-file-text me-1"></i>View Record
                                        </button>
                                    </div>
                                    </div>
                            `;
                        }).join('');
                    } else {
                        container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No medical records found.</p>';
                    }
                } else {
                    container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No medical records found.</p>';
                }
            })
            .catch(error => {
                console.error('Error loading medical records:', error);
                const container = document.getElementById('medicalRecordsContainer');
                if (container) {
                    container.innerHTML = `<p style="text-align: center; color: #dc3545; padding: 40px;">Error loading medical records: ${error.message}</p>`;
                }
            });
    }

    // Load dental records (separate from medical records)
    function loadDentalRecords(patientId) {
        fetch(`../crud/medical_records.php?patient_id=${patientId}`)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                const container = document.getElementById('dentalRecordsContainer');
                
                if (data.success && data.records && data.records.length > 0) {
                    // Filter only dental records and remove blank/incomplete records
                    const dentalRecordsFiltered = data.records.filter(record => {
                        const isDental = record.source_table === 'dental_records' || 
                                        record.is_dental === true || 
                                        record.record_type === 'Dental' ||
                                        record.appointment_type === 'dental';
                        
                        // Filter out blank/incomplete records - must have at least visit_date or created_at
                        const hasDate = record.visit_date || record.created_at;
                        // Must have either dental_record_id or id to be valid
                        const hasId = record.dental_record_id || record.id;
                        
                        return isDental && hasDate && hasId;
                    });
                    
                    // Deduplicate records based on dental_record_id or unique combination
                    const uniqueRecordsMap = new Map();
                    dentalRecordsFiltered.forEach((record, idx) => {
                        // Use dental_record_id as primary key if available
                        const uniqueKey = record.dental_record_id 
                            ? `dental_${record.dental_record_id}`
                            : `dental_${record.id || idx}_${record.visit_date || record.created_at || ''}_${record.dentist_id || record.physician_id || ''}`;
                        
                        // Only add if we haven't seen this record before
                        if (!uniqueRecordsMap.has(uniqueKey)) {
                            uniqueRecordsMap.set(uniqueKey, record);
                        }
                    });
                    
                    const uniqueDentalRecords = Array.from(uniqueRecordsMap.values());
                    
                    if (uniqueDentalRecords.length > 0) {
                        // Store dental records globally for modal access
                        window.dentalRecordsData = uniqueDentalRecords;
                        
                        container.innerHTML = uniqueDentalRecords.map((record, displayIndex) => {
                            // Find original index in full records array for modal viewing
                            const originalIndex = data.records.findIndex(r => {
                                // Match by dental_record_id first, then by id
                                if (record.dental_record_id) {
                                    return (r.dental_record_id === record.dental_record_id) || 
                                           (r.id === record.dental_record_id);
                                }
                                return r.id === record.id;
                            });
                            
                            // Use original index if found, otherwise use the record itself for modal
                            const modalIndex = originalIndex >= 0 ? originalIndex : displayIndex;
                            
                            return `
                                <div class="medical-record-card">
                                    <div class="record-header">
                                        <div>
                                            <strong>${formatDate(record.visit_date || record.created_at)}</strong>
                                            ${record.visit_time ? `<span class="record-time">${record.visit_time}</span>` : ''}
                                            <span class="record-type-badge" style="display: inline-block; margin-left: 10px; padding: 4px 8px; background: #e3f2fd; color: #1976d2; border-radius: 4px; font-size: 12px; font-weight: 600;">
                                                <i class="bi bi-tooth"></i> Dental Record
                                            </span>
                                    </div>
                                        <div class="record-physician">
                                            <i class="bi bi-person-badge"></i> ${record.dentist_name || record.physician_name || 'Unknown'}
                                    </div>
                                    </div>
                                    <div class="record-footer" style="margin-top: 15px; padding-top: 15px; text-align: right;">
                                        <button class="btn btn-sm btn-primary" onclick="viewDentalRecord(${displayIndex})" style="background: #8B0000; border: none;">
                                            <i class="bi bi-file-text me-1"></i>View Record
                                        </button>
                                </div>
                            </div>
                        `;
                    }).join('');
                } else {
                        container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No dental records found.</p>';
                        window.dentalRecordsData = [];
                    }
                } else {
                    container.innerHTML = '<p style="text-align: center; color: #999; padding: 40px;">No dental records found.</p>';
                    window.dentalRecordsData = [];
                }
            })
            .catch(error => {
                console.error('Error loading dental records:', error);
                const container = document.getElementById('dentalRecordsContainer');
                if (container) {
                    container.innerHTML = `<p style="text-align: center; color: #dc3545; padding: 40px;">Error loading dental records: ${error.message}</p>`;
                }
                window.dentalRecordsData = [];
            });
    }
    
    // View dental record directly from dental records array
    function viewDentalRecord(displayIndex) {
        const dentalRecords = window.dentalRecordsData || [];
        const record = dentalRecords[displayIndex];
        const patient = window.currentPatientData;
        
        if (!record) {
            console.error('Dental record not found at index:', displayIndex);
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'Dental record not found.',
                confirmButtonColor: '#8b2332'
            });
            return;
        }
        
        showDentalRecordModal(record, patient);
    }

    // Close patient detail
    function closePatientDetail() {
        const patientsSection = document.getElementById('patientsSection');
        const patientDetailSection = document.getElementById('patientDetailSection');
        
        if (patientsSection) patientsSection.style.display = 'block';
        if (patientDetailSection) {
            patientDetailSection.style.display = 'none';
            patientDetailSection.classList.remove('active');
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
        if (tabName === 'info') {
            if (tabs[0]) tabs[0].classList.add('active');
            const infoTab = document.getElementById('infoTab');
            if (infoTab) infoTab.classList.add('active');
        } else if (tabName === 'medical') {
            if (tabs[1]) tabs[1].classList.add('active');
            const medicalTab = document.getElementById('medicalTab');
            if (medicalTab) medicalTab.classList.add('active');
        } else if (tabName === 'dental') {
            if (tabs[2]) tabs[2].classList.add('active');
            const dentalTab = document.getElementById('dentalTab');
            if (dentalTab) dentalTab.classList.add('active');
        }
    }

    // Filter patients
    function filterPatients(searchTerm) {
        const cards = document.querySelectorAll('.patient-card');
        const term = searchTerm.toLowerCase();

        cards.forEach(card => {
            const name = card.querySelector('.patient-name');
            const srCode = card.querySelector('.patient-meta');
            
            if (name && srCode) {
                const nameText = name.textContent.toLowerCase();
                const srCodeText = srCode.textContent.toLowerCase();
                
                if (nameText.includes(term) || srCodeText.includes(term)) {
                    card.style.display = 'flex';
                } else {
                    card.style.display = 'none';
                }
            }
        });
    }

    // Helper functions
    function getInitials(name) {
        if (!name) return '??';
        const words = name.split(' ');
        if (words.length >= 2) {
            return (words[0][0] + words[words.length - 1][0]).toUpperCase();
        }
        return name.substring(0, 2).toUpperCase();
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

    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        
        try {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
        } catch (error) {
            console.error('Error formatting date:', error);
            return dateString;
        }
    }
    
    // Show medical/consultation record modal
    function showMedicalRecordModal(record, patient, isConsultation) {
        const modalElement = document.getElementById('medicalRecordModal');
        if (!modalElement) {
            console.error('Medical record modal not found');
            return;
        }

        let modal;
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        } else {
            console.error('Bootstrap is not loaded');
            return;
        }

        // Populate patient information (including Age and Gender in Patient Information section)
        document.getElementById('viewFullName').value = patient?.full_name || record.patient_name || 'N/A';
        document.getElementById('viewSRCode').value = patient?.sr_code || 'N/A';
        document.getElementById('viewAddress').value = patient?.address || 'N/A';
        document.getElementById('viewProgram').value = patient?.program || patient?.position || 'N/A';
        
        // Age and Gender are in Patient Information section
        const viewAgeEl = document.getElementById('viewAge');
        const viewSexEl = document.getElementById('viewSex');
        if (viewAgeEl) {
            viewAgeEl.value = record.age || patient?.age || calculateAge(patient?.date_of_birth) || '';
        }
        if (viewSexEl) {
            viewSexEl.value = record.sex || patient?.sex || patient?.gender || '';
        }

        // Explicitly check: Consultation records come from medical_consultations table OR have is_consultation flag
        // Medical records come from medical_records table with appointments (appointment_type = 'medical')
        // IMPORTANT: Default to Medical Examination form unless explicitly a consultation
        const isExplicitConsultation = (record.is_consultation === true) || 
                                      (record.source_table === 'medical_consultations') ||
                                      (record.record_type === 'Consultation' || record.record_type === 'Medical Consultation');
        
        console.log('showMedicalRecordModal - Record type:', {
            isExplicitConsultation: isExplicitConsultation,
            source_table: record.source_table,
            is_consultation: record.is_consultation,
            record_type: record.record_type,
            appointment_type: record.appointment_type
        });
        
        // Determine record type - default to Medical Examination (appointment-based) if not consultation
        if (isExplicitConsultation) {
            // Consultation record
            console.log('Showing Medical Consultation Record form');
            document.getElementById('modalHeaderTitle').textContent = 'Medical Consultation Record';
            document.getElementById('modalRecordTitle').textContent = 'MEDICAL CONSULTATION RECORD';
            document.getElementById('modalReferenceNo').textContent = 'BatStateU-FO-HSD-12';
            
            // Explicitly show consultation form and hide appointment form
            const consultationForm = document.getElementById('consultationFormFields');
            const appointmentForm = document.getElementById('appointmentFormFields');
            if (consultationForm) consultationForm.style.display = 'block';
            if (appointmentForm) appointmentForm.style.display = 'none';
            
            // Populate consultation fields (Age and Gender are in Patient Information section, already populated above)
            document.getElementById('viewAssessmentDate').value = record.assessment_date || record.visit_date || '';
            document.getElementById('viewControlNumber').value = record.control_number || '';
            document.getElementById('viewPurpose').value = record.purpose || record.chief_complaint || '';
            document.getElementById('viewBloodPressure').value = record.blood_pressure || '';
            document.getElementById('viewPulseRate').value = record.pulse_rate || record.heart_rate || '';
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
            
            // Treatment Plan - Display medicines if they exist
            const treatmentInstructions = record.treatment_instructions || '';
            const viewTreatmentInstructions = document.getElementById('viewTreatmentInstructions');
            if (viewTreatmentInstructions) {
                viewTreatmentInstructions.value = treatmentInstructions;
            }
            
            // Display medicines from medicine_dispensed
            const medicinesContainer = document.getElementById('viewMedicinesContainer');
            if (medicinesContainer) {
                if (record.medicines && Array.isArray(record.medicines) && record.medicines.length > 0) {
                    let medicinesHTML = '<div style="margin-bottom: 15px;"><strong>Medicines Dispensed:</strong></div>';
                    medicinesHTML += '<div class="medicines-list" style="margin-bottom: 20px;">';
                    record.medicines.forEach((medicine) => {
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
                
                // Show Treatment Plan section
                const treatmentPlanSection = document.getElementById('viewTreatmentPlanSection');
                if (treatmentPlanSection) {
                    treatmentPlanSection.style.display = 'block';
                }
            }
        } else {
            // Medical examination record (appointment-based, no medicine dispensing)
            console.log('Showing Medical Examination Record form');
            document.getElementById('modalHeaderTitle').textContent = 'Medical Examination Record';
            document.getElementById('modalRecordTitle').textContent = 'MEDICAL EXAMINATION RECORD';
            document.getElementById('modalReferenceNo').textContent = 'BatStateU-FO-HSD-11';
            
            // Explicitly hide consultation form and show appointment form
            const consultationForm = document.getElementById('consultationFormFields');
            const appointmentForm = document.getElementById('appointmentFormFields');
            if (consultationForm) consultationForm.style.display = 'none';
            if (appointmentForm) appointmentForm.style.display = 'block';
            
            // Populate appointment fields
            document.getElementById('viewAppointmentDate').value = record.visit_date || '';
            document.getElementById('viewAppointmentTime').value = record.visit_time || '';
            document.getElementById('viewAppointmentBP').value = record.blood_pressure || '';
            document.getElementById('viewAppointmentPR').value = record.heart_rate || record.pulse_rate || '';
            document.getElementById('viewAppointmentSpO2').value = record.spo2 || '';
            document.getElementById('viewAppointmentRR').value = record.respiratory_rate || '';
            document.getElementById('viewAppointmentTemp').value = record.temperature || '';
            document.getElementById('viewAppointmentHeight').value = record.height || '';
            document.getElementById('viewAppointmentWeight').value = record.weight || '';
            document.getElementById('viewAppointmentBMI').value = record.bmi || '';
            document.getElementById('viewDiagnosis').value = record.diagnosis || '';
        }
        
        modal.show();
    }
    
    // Show dental record modal - Fetch full dental record to ensure complete data
    async function showDentalRecordModal(record, patient) {
        const modalElement = document.getElementById('dentalRecordModal');
        if (!modalElement) {
            console.error('Dental record modal not found');
            return;
        }

        let modal;
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            modal = bootstrap.Modal.getOrCreateInstance(modalElement);
        } else {
            console.error('Bootstrap is not loaded');
            return;
        }

        // Get dental record ID - use dental_record_id if available, otherwise use id
        const dentalRecordId = record.dental_record_id || record.id;
        
        // Fetch full dental record from dental_records table to ensure complete data
        if (dentalRecordId) {
            try {
                console.log('Fetching full dental record details for ID:', dentalRecordId);
                const response = await fetch(`../dental/crud/get_dental_record.php?id=${dentalRecordId}`);
                const result = await response.json();
                
                if (result.success && result.record) {
                    console.log('Fetched full dental record:', result.record);
                    // Use the complete dental record data
                    record = result.record;
                } else {
                    console.warn('Failed to fetch full dental record, using summary data');
                }
            } catch (error) {
                console.error('Error fetching full dental record:', error);
                // Continue with existing record data as fallback
            }
        }

        // Populate patient information - use record data first, then patient data as fallback (matching dental_patients.php)
        document.getElementById('viewDentalFullName').value = record.patient_name || patient?.full_name || 'N/A';
        document.getElementById('viewDentalSRCode').value = record.sr_code || patient?.sr_code || 'N/A';
        document.getElementById('viewDentalAddress').value = record.address || patient?.address || 'N/A';
        document.getElementById('viewDentalProgram').value = record.program || record.position || patient?.program || patient?.position || 'N/A';
        
        const visitDate = record.visit_date || record.created_at || '';
        document.getElementById('viewDentalDate').value = visitDate.split(' ')[0]; // Get date part only
        
        const dentistField = document.getElementById('viewDentalDentist');
        if (dentistField) {
            dentistField.value = record.dentist_name || record.physician_name || 'N/A';
        }
        
        // Populate dental conditions - use == 1 comparison (matching dental_patients.php)
        document.getElementById('viewDentalGingivitis').checked = record.gingivitis == 1;
        document.getElementById('viewDentalEarlyPeriodontitis').checked = record.early_periodontitis == 1;
        document.getElementById('viewDentalClassMolar').checked = record.class_molar == 1;
        document.getElementById('viewDentalOverjet').checked = record.overjet == 1;
        document.getElementById('viewDentalOverbite').checked = record.overbite == 1;
        document.getElementById('viewDentalOrthodontic').checked = record.orthodontic == 1;
        document.getElementById('viewDentalStayplate').checked = record.stayplate == 1;
        document.getElementById('viewDentalClenching').checked = record.clenching == 1;
        document.getElementById('viewDentalClicking').checked = record.clicking == 1;
        
        // Initialize and populate tooth chart - properly decode tooth_status (matching dental_patients.php)
        let toothStatus = {};
        if (record.tooth_status) {
            if (typeof record.tooth_status === 'string') {
                try {
                    toothStatus = JSON.parse(record.tooth_status || '{}');
                } catch (e) {
                    console.error('Error parsing tooth_status JSON:', e);
                    toothStatus = {};
                }
            } else if (typeof record.tooth_status === 'object' && record.tooth_status !== null) {
                toothStatus = record.tooth_status;
            }
        }
        
        if (typeof initializeDentalChartForView === 'function') {
            initializeDentalChartForView(toothStatus);
        }
        
        // Populate tooth status after chart is initialized (double-check to ensure values are set) - matching dental_patients.php
        setTimeout(() => {
            if (toothStatus && typeof toothStatus === 'object') {
                let populatedCount = 0;
                let notFoundCount = 0;
                
                // Try both string and numeric keys
                Object.keys(toothStatus).forEach(toothNum => {
                    const statusValue = toothStatus[toothNum];
                    if (!statusValue) return; // Skip empty values
                    
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
                        selectEl.value = statusValue;
                        // Also mark the tooth diagram visually
                        const container = selectEl.closest('.tooth-container');
                        if (container) {
                            const diagram = container.querySelector('.tooth-diagram');
                            if (diagram && statusValue && statusValue !== '') {
                                diagram.classList.add('marked');
                                // Add 'status-x' class specifically for 'X' status to show X mark
                                if (statusValue === 'X') {
                                    diagram.classList.add('status-x');
                                } else {
                                    diagram.classList.remove('status-x');
                                }
                            }
                        }
                        populatedCount++;
                        console.log(`✓ Set tooth ${toothNum} to status: ${statusValue}`);
                    } else {
                        notFoundCount++;
                        console.warn(`✗ Select element not found for tooth ${toothNum} (tried: viewTooth_${toothNum}_status)`);
                    }
                });
                
                console.log(`Populated ${populatedCount} teeth with status values`);
                if (notFoundCount > 0) {
                    console.warn(`${notFoundCount} teeth could not be found in the chart`);
                }
            } else {
                console.warn('toothStatus is not a valid object:', toothStatus);
            }
        }, 100);
        
        // Function to populate index tables (called after modal is shown) - matching dental_patients.php
        const populateIndexTables = () => {
            console.log('Populating index tables...');
            console.log('Record index_data:', record.index_data);
            
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
            
            // Populate temporary teeth index table (6 visits) - using object keys like dental_patients.php
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
                }
                if (filledInput) {
                    filledInput.value = visitData.filled || '';
                }
                if (totalInput) {
                    totalInput.value = visitData.total || '';
                }
            }
            
            // Populate permanent teeth index table (4 visits) - using object keys like dental_patients.php
            for (let i = 1; i <= 4; i++) {
                // Handle both numeric and string keys
                const visitData = (indexData.permanent && (indexData.permanent[i] || indexData.permanent[String(i)])) 
                    ? (indexData.permanent[i] || indexData.permanent[String(i)]) 
                    : {};
                
                const dInput = document.getElementById(`viewPermD${i}`);
                const mInput = document.getElementById(`viewPermM${i}`);
                const fInput = document.getElementById(`viewPermF${i}`);
                const totalInput = document.getElementById(`viewPermTotal${i}`);
                const teethInput = document.getElementById(`viewPermTeeth${i}`);
                
                if (dInput) {
                    dInput.value = visitData.d || '';
                }
                if (mInput) {
                    mInput.value = visitData.m || '';
                }
                if (fInput) {
                    fInput.value = visitData.f || '';
                }
                if (totalInput) {
                    totalInput.value = visitData.total || '';
                }
                if (teethInput) {
                    teethInput.value = visitData.teeth || '';
                }
            }
        };
        
        // Show modal first
        modal.show();
        
        // Populate index tables after modal is shown (use setTimeout to ensure DOM is ready)
        setTimeout(() => {
            populateIndexTables();
        }, 100);
        
        // Populate treatments
        const treatmentBody = document.getElementById('viewDentalTreatmentBody');
        if (treatmentBody) {
            treatmentBody.innerHTML = '';
            
            let treatments = [];
            if (record.treatments) {
                if (Array.isArray(record.treatments)) {
                    treatments = record.treatments;
                } else if (typeof record.treatments === 'string') {
                    try {
                        treatments = JSON.parse(record.treatments || '[]');
                    } catch (e) {
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
                        <td>${treatment.dentist || record.dentist_name || record.physician_name || 'N/A'}</td>
                    `;
                    treatmentBody.appendChild(row);
                });
            }
        }
        
        // Populate remarks
        if (document.getElementById('viewDentalRemarks')) {
            document.getElementById('viewDentalRemarks').value = record.remarks || '';
        }
        
        // Note: Index tables population is handled in populateIndexTables function called after modal.show()
    }
</script>
<?php include __DIR__ . '/../student/includes/record_modals.php'; ?>
<link href="../student/css/records.css" rel="stylesheet">
<style>
    .consultation-form-container {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    /* Dental Record Modal Styling - Match dental appointments form */
    #dentalRecordModal .consultation-form-container {
        background: white;
    }
    
    #dentalRecordModal .document-header {
        background: white;
        border: 2px solid #8b2332;
        border-top: none;
        border-radius: 0;
        padding: 20px;
        margin: 0;
        border-bottom: 3px solid #8b2332;
    }
    
    #dentalRecordModal .modal-header {
        background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%);
        border: none;
        border-radius: 8px 8px 0 0;
    }
    
    /* Close Button Styling - Proper Bootstrap close button */
    #dentalRecordModal .modal-header .btn-close,
    #dentalRecordModal .modal-header .btn-close-white,
    #medicalRecordModal .modal-header .btn-close,
    #medicalRecordModal .modal-header .btn-close-white {
        opacity: 1 !important;
        filter: brightness(0) invert(1);
        background: transparent !important;
        border: none !important;
        width: 1em !important;
        height: 1em !important;
        padding: 0.5rem !important;
        margin: 0 !important;
        border-radius: 0.25rem;
    }
    
    #dentalRecordModal .modal-header .btn-close:hover,
    #dentalRecordModal .modal-header .btn-close-white:hover,
    #medicalRecordModal .modal-header .btn-close:hover,
    #medicalRecordModal .modal-header .btn-close-white:hover {
        opacity: 0.75 !important;
        background: rgba(255, 255, 255, 0.1) !important;
    }
    
    #dentalRecordModal .document-header .header-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        margin-bottom: 10px;
    }
    
    #dentalRecordModal .document-header .header-logo {
        flex-shrink: 0;
        width: 100px;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px;
        background: #f8f9fa;
    }
    
    #dentalRecordModal .document-header .header-logo img {
        width: 80px;
        height: 80px;
        object-fit: contain;
    }
    
    #dentalRecordModal .document-header .header-info-boxes {
        flex: 1;
        display: flex;
        flex-direction: row;
        gap: 15px;
        justify-content: flex-start;
        align-items: center;
    }
    
    #dentalRecordModal .document-header .info-box {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 0;
        border: 1px solid #8b2332;
        border-radius: 4px;
        padding: 8px 12px;
        background: white;
    }
    
    #dentalRecordModal .document-header .info-box label {
        font-size: 11px;
        font-weight: 600;
        color: #8b2332;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
        margin: 0;
    }
    
    #dentalRecordModal .document-header .info-box span {
        font-size: 13px;
        color: #333;
        font-weight: 500;
    }
    
    #dentalRecordModal .document-header .header-title {
        width: 100%;
        text-align: center;
        padding-top: 10px;
        border-top: 2px solid #8b2332;
        margin-top: 5px;
    }
    
    #dentalRecordModal .document-header .header-title h2 {
        font-size: 20px;
        font-weight: 700;
        color: #8b2332;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 1px;
        line-height: 1.2;
    }
    
    #dentalRecordModal .dental-form {
        padding: 20px;
    }
    
    #dentalRecordModal .consultation-section {
        margin-bottom: 30px;
        padding: 25px;
        background: #fafafa;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }
    
    #dentalRecordModal .consultation-section-title {
        color: #8b2332;
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #8b2332;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    #dentalRecordModal .form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 15px;
    }
    
    #dentalRecordModal .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    #dentalRecordModal .form-group label {
        font-weight: 600;
        font-size: 13px;
        color: #555;
    }
    
    #dentalRecordModal .form-group .required {
        color: #dc3545;
    }
    
    #dentalRecordModal .form-group input[type="text"],
    #dentalRecordModal .form-group input[type="date"],
    #dentalRecordModal .form-group input[type="time"] {
        width: 100%;
        border: 1px solid #ddd;
        padding: 10px 12px;
        border-radius: 6px;
        font-size: 14px;
        background: white;
        transition: border-color 0.2s;
    }
    
    #dentalRecordModal .readonly-field {
        background: #f8f9fa !important;
        color: #666 !important;
        cursor: not-allowed !important;
        border: 1px solid #ddd !important;
    }
    
    /* Dental Chart Styles */
    #dentalRecordModal .dental-chart-wrapper {
        padding: 20px;
        background: white;
        border-radius: 8px;
        border: 2px solid #8b2332;
        margin-bottom: 25px;
    }
    
    #dentalRecordModal .teeth-section-standalone {
        display: flex;
        flex-direction: column;
        gap: 20px;
        max-width: 100%;
        margin: 0 auto;
    }
    
    #dentalRecordModal .teeth-row {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    #dentalRecordModal .teeth-label {
        text-align: center;
        font-size: 13px;
        font-weight: 600;
        color: #333;
        margin-bottom: 8px;
    }
    
    #dentalRecordModal .tooth-container {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 5px;
    }
    
    #dentalRecordModal .tooth-number {
        font-size: 11px;
        font-weight: 600;
        color: #333;
    }
    
    #dentalRecordModal .tooth-diagram {
        width: 32px;
        height: 32px;
        border: 2px solid #333;
        border-radius: 50%;
        position: relative;
        background: white;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    #dentalRecordModal .tooth-diagram:hover {
        border-color: #8b2332;
        transform: scale(1.1);
    }
    
    #dentalRecordModal .tooth-diagram.marked {
        background: #ffebee;
        border-color: #8b2332;
        position: relative;
    }
    
    /* X mark visual for teeth with 'X' status */
    #dentalRecordModal .tooth-diagram.status-x::before,
    #dentalRecordModal .tooth-diagram.status-x::after {
        content: '';
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        background: #8b2332;
        width: 70%;
        height: 2px;
    }
    
    #dentalRecordModal .tooth-diagram.status-x::before {
        transform: translate(-50%, -50%) rotate(45deg);
    }
    
    #dentalRecordModal .tooth-diagram.status-x::after {
        transform: translate(-50%, -50%) rotate(-45deg);
    }
    
    #dentalRecordModal .tooth-select {
        width: 50px;
        padding: 4px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 11px;
        text-align: center;
        background: white;
    }
    
    #dentalRecordModal .tooth-select:focus {
        outline: none;
        border-color: #8b2332;
    }
    
    /* Index Tables Styles */
    #dentalRecordModal .index-tables-container {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-top: 25px;
    }
    
    #dentalRecordModal .index-table-wrapper {
        background: white;
        border: 2px solid #333;
        border-radius: 4px;
        overflow: hidden;
    }
    
    #dentalRecordModal .index-table-title {
        background: #f8f9fa;
        padding: 10px 15px;
        margin: 0;
        font-size: 14px;
        font-weight: 700;
        border-bottom: 2px solid #333;
    }
    
    #dentalRecordModal .index-table {
        width: 100%;
        border-collapse: collapse;
        background: white;
    }
    
    #dentalRecordModal .index-table thead th {
        background: #f8f9fa;
        border: 1px solid #333;
        padding: 8px 6px;
        font-size: 11px;
        font-weight: 700;
        text-align: center;
    }
    
    #dentalRecordModal .index-table tbody td {
        border: 1px solid #333;
        padding: 0;
        text-align: center;
        height: 35px;
    }
    
    #dentalRecordModal .index-table tbody td.label-cell {
        background: #f8f9fa;
        font-weight: 600;
        font-size: 11px;
        padding: 8px;
        text-align: left;
    }
    
    #dentalRecordModal .index-table tbody td input {
        width: 100%;
        height: 100%;
        border: none;
        padding: 8px 4px;
        text-align: center;
        font-size: 12px;
        background: white;
    }
    
    #dentalRecordModal .index-table tbody td input[readonly] {
        background: #f0f0f0;
        color: #666;
        font-weight: 600;
    }
    
    #dentalRecordModal .index-legend {
        padding: 10px 15px;
        background: #f8f9fa;
        border-top: 1px solid #333;
        font-size: 11px;
    }
    
    /* Treatment Table Styles */
    #dentalRecordModal .treatment-record-wrapper {
        margin-top: 20px;
    }
    
    #dentalRecordModal .treatment-table-standalone {
        width: 100%;
        border-collapse: collapse;
        background: white;
        table-layout: fixed;
        border: 2px solid #000;
        margin-bottom: 15px;
    }
    
    #dentalRecordModal .treatment-table-standalone thead {
        background: #f8f9fa;
    }
    
    #dentalRecordModal .treatment-table-standalone th {
        border-bottom: 2px solid #000;
        border-right: 1px solid #000;
        padding: 12px 8px;
        font-size: 13px;
        background: #f8f9fa;
        font-weight: 700;
        text-align: center;
    }
    
    #dentalRecordModal .treatment-table-standalone tbody tr td {
        border-right: 1px solid #000;
        border-bottom: 1px solid #000;
        padding: 0;
        background: white;
        height: 45px;
    }
    
    #dentalRecordModal .treatment-table-standalone input {
        width: 100%;
        height: 100%;
        border: none;
        padding: 10px 8px;
        font-size: 13px;
        background: white;
    }
    
    /* Legend Section Styles */
    #dentalRecordModal .legend-section {
        margin-top: 25px;
        padding: 20px;
        background: #fafafa;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }
    
    #dentalRecordModal .legend-title {
        font-size: 14px;
        font-weight: 700;
        color: #333;
        margin-bottom: 15px;
    }
    
    #dentalRecordModal .legend-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 20px;
    }
    
    #dentalRecordModal .legend-column {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    #dentalRecordModal .legend-column h5 {
        margin-top: 15px;
        margin-bottom: 8px;
        font-size: 13px;
        font-weight: 600;
        color: #555;
    }
    
    #dentalRecordModal .legend-item {
        font-size: 12px;
        color: #666;
    }
    
    #dentalRecordModal .legend-item input[type="checkbox"] {
        margin-right: 8px;
    }
    
    /* Remarks Section */
    #dentalRecordModal .remarks-section {
        margin-top: 25px;
        padding: 20px;
        background: #fafafa;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }
    
    #dentalRecordModal .remarks-section label {
        display: block;
        font-weight: 600;
        font-size: 14px;
        color: #333;
        margin-bottom: 10px;
    }
    
    #dentalRecordModal .remarks-section textarea {
        width: 100%;
        min-height: 80px;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 14px;
        font-family: inherit;
    }
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }
    .form-row.full {
        grid-template-columns: 1fr;
    }
    .form-group {
        display: flex;
        flex-direction: column;
    }
    .form-group label {
        font-weight: 500;
        color: #666;
        font-size: 13px;
        margin-bottom: 5px;
    }
    .readonly-field {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
        background: #f8f9fa;
        color: #333;
    }
    .vitals-grid, .measurements-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
    }
    .notes-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
    }
    .notes-section h5 {
        color: #8B0000;
        font-size: 14px;
        margin-bottom: 10px;
    }
    .section-divider {
        height: 2px;
        background: #e0e0e0;
        margin: 25px 0;
    }
    
    /* Medical Record Modal Styling - Match medical appointments form */
    #medicalRecordModal .consultation-form-container {
        background: white;
    }
    
    #medicalRecordModal .modal-header {
        background: linear-gradient(135deg, #8B0000 0%, #A52A2A 100%);
        border: none;
        border-radius: 8px 8px 0 0;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    #medicalRecordModal .document-header {
        background: white;
        border: 2px solid #8b2332;
        border-top: none;
        border-radius: 0;
        padding: 20px;
        margin: 0;
        border-bottom: 3px solid #8b2332;
    }
    
    #medicalRecordModal .document-header .header-top {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 20px;
        width: 100%;
        margin-bottom: 10px;
    }
    
    #medicalRecordModal .document-header .header-logo {
        flex-shrink: 0;
        width: 100px;
        height: 100px;
        display: flex;
        align-items: center;
        justify-content: center;
        border: 1px solid #ddd;
        border-radius: 4px;
        padding: 5px;
        background: #f8f9fa;
    }
    
    #medicalRecordModal .document-header .header-logo img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    
    #medicalRecordModal .document-header .header-info-boxes {
        flex: 1;
        display: flex;
        flex-direction: row;
        gap: 15px;
        justify-content: flex-start;
        align-items: center;
    }
    
    #medicalRecordModal .document-header .info-box {
        display: flex;
        align-items: center;
        gap: 10px;
        flex: 1;
        min-width: 0;
        border: 1px solid #8b2332;
        border-radius: 4px;
        padding: 8px 12px;
        background: white;
    }
    
    #medicalRecordModal .document-header .info-box label {
        font-size: 11px;
        font-weight: 600;
        color: #8b2332;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        white-space: nowrap;
        margin: 0;
    }
    
    #medicalRecordModal .document-header .info-box span {
        font-size: 13px;
        color: #333;
        font-weight: 500;
    }
    
    #medicalRecordModal .document-header .header-title {
        width: 100%;
        text-align: center;
        padding-top: 10px;
        border-top: 2px solid #8b2332;
        margin-top: 5px;
    }
    
    #medicalRecordModal .document-header .header-title h2 {
        font-size: 20px;
        font-weight: 700;
        color: #8b2332;
        margin: 0;
        text-transform: uppercase;
        letter-spacing: 1px;
        line-height: 1.2;
    }
    
    #medicalRecordModal .modal-body {
        padding: 0;
    }
    
    #medicalRecordModal .medical-form {
        padding: 20px;
    }
    
    #medicalRecordModal .consultation-section {
        margin-bottom: 30px;
        padding: 25px;
        background: #fafafa;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }
    
    #medicalRecordModal .consultation-section-title {
        color: #8b2332;
        font-size: 16px;
        font-weight: 600;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #8b2332;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    
    #medicalRecordModal .form-row {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 20px;
        margin-bottom: 15px;
    }
    
    #medicalRecordModal .form-row.full {
        grid-template-columns: 1fr;
    }
    
    #medicalRecordModal .form-group {
        display: flex;
        flex-direction: column;
        gap: 8px;
    }
    
    #medicalRecordModal .form-group label {
        font-weight: 600;
        font-size: 13px;
        color: #555;
    }
    
    #medicalRecordModal .form-group .required {
        color: #dc3545;
    }
    
    #medicalRecordModal .form-group input,
    #medicalRecordModal .form-group textarea {
        width: 100%;
        border: 1px solid #ddd;
        padding: 10px 12px;
        border-radius: 6px;
        font-size: 14px;
        background: white;
        transition: border-color 0.2s;
    }
    
    #medicalRecordModal .readonly-field {
        background: #f8f9fa !important;
        color: #666 !important;
        cursor: not-allowed !important;
        border: 1px solid #ddd !important;
    }
    
    #medicalRecordModal .vitals-grid,
    #medicalRecordModal .measurements-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    #medicalRecordModal .notes-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 30px;
        margin-top: 20px;
    }
    
    #medicalRecordModal .notes-section {
        background-color: #f9f9f9;
        padding: 25px;
        border-radius: 10px;
        border: 2px solid #e0e0e0;
    }
    
    #medicalRecordModal .notes-section h5 {
        color: #8b2332;
        margin-bottom: 15px;
        font-size: 16px;
        font-weight: 600;
    }
    
    #medicalRecordModal .notes-section textarea {
        min-height: 150px;
        width: 100%;
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    #medicalRecordModal .section-divider {
        height: 2px;
        background: linear-gradient(to right, #8b2332, transparent);
        margin: 40px 0;
    }
    
    #medicalRecordModal .treatment-plan-section {
        margin-top: 30px;
    }
    
    #medicalRecordModal .medicine-item {
        padding: 12px;
        border: 1px solid #ddd;
        border-radius: 4px;
        margin-bottom: 10px;
        background: #f9f9f9;
    }
    
    #medicalRecordModal #viewMedicinesContainer {
        margin-bottom: 20px;
    }
    
    #medicalRecordModal .medicines-list {
        margin-bottom: 20px;
    }
    
    @media (max-width: 768px) {
        .header-info-boxes {
            flex-direction: column;
            gap: 10px;
        }
        .notes-container {
            grid-template-columns: 1fr;
        }
        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>
<script src="../js/mobile-menu.js"></script>
</body>
</html>
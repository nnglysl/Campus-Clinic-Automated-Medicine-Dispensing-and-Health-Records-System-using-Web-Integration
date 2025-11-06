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
    'fname' => $_SESSION['fname'] ?? 'Student',
    'lname' => $_SESSION['lname'] ?? '',
    'role' => $_SESSION['role'] ?? 'student',
    'full_name' => ($_SESSION['fname'] ?? 'Student') . ' ' . ($_SESSION['lname'] ?? ''),
    'sr_code' => $_SESSION['sr_code'] ?? 'N/A',
    'dob' => $_SESSION['dob'] ?? 'N/A'
];

$pdo = getDB();

// Fetch patient information
$patientInfo = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE sr_code = ? OR id = ?");
    $stmt->execute([$user['sr_code'], $user['id']]);
    $patientInfo = $stmt->fetch();
    
    if (!$patientInfo) {
        // If no patient record found, use session data
        $patientInfo = [
            'full_name' => $user['full_name'],
            'sr_code' => $user['sr_code'],
            'dob' => $user['dob']
        ];
    }
} catch (PDOException $e) {
    error_log("Error fetching patient info: " . $e->getMessage());
    $patientInfo = [
        'full_name' => $user['full_name'],
        'sr_code' => $user['sr_code'],
        'dob' => $user['dob']
    ];
}

// Fetch medical records from database
$medicalRecords = [];
try {
    $stmt = $pdo->prepare("
        SELECT 
            mr.*,
            vl.visit_date,
            vl.purpose,
            vl.physician_name,
            vl.blood_pressure,
            vl.heart_rate,
            vl.temperature,
            vl.height,
            vl.weight,
            vl.bmi,
            vl.eye_test
        FROM medical_records mr
        LEFT JOIN visit_logs vl ON mr.visit_log_id = vl.id
        WHERE mr.patient_id = (SELECT id FROM patients WHERE sr_code = ? OR id = ?)
        ORDER BY vl.visit_date DESC
    ");
    $stmt->execute([$user['sr_code'], $user['id']]);
    $medicalRecords = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error fetching medical records: " . $e->getMessage());
    $medicalRecords = [];
}

// Format date for display
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

// Format time for display
function formatTime($time) {
    return date('g:i A', strtotime($time));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Health Records</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">

  <!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Text:ital@0;1&display=swap" rel="stylesheet">

  <!-- External CSS -->
  <link href="../student/css/nav.css" rel="stylesheet" />
  <link href="../student/css/records.css" rel="stylesheet">
</head>

<body>
  <div class="layout">
    <!-- Header -->
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
        <div class="logout-icon" id="logoutBtn" onclick="window.location.href='../logout.php'"><i class="bi bi-box-arrow-right"></i></div>
      </div>
    </div>

    <!-- Sidebar + Content -->
    <div class="main-container">
      <!-- Sidebar -->
      <div class="sidebar">
        <a href="../student/student_dashboard.php" class="menu-item">Dashboard</a>
        <a href="../student/profile.php" class="menu-item">Profile</a>
        <a href="../student/appointment.php" class="menu-item">Appointment</a>
        <a href="../student/records.php" class="menu-item active">Health Records</a>
        <a href="../student/settings.php" class="menu-item">Settings</a>
      </div>

      <!-- Main Content -->
      <div class="content-demo p-4">
        <!-- Records List View -->
        <div id="recordsListView">
          <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
              <h2 class="fw-bold">My Health Records</h2>
            </div>

            <h5 class="text-secondary mb-3">Medical History</h5>
            <div id="medicalHistoryContainer">
              <?php if (empty($medicalRecords)): ?>
                <div class="alert alert-info">
                  <i class="bi bi-info-circle me-2"></i>
                  No medical records found. Visit the clinic to start your health record.
                </div>
              <?php else: ?>
                <?php foreach ($medicalRecords as $index => $record): ?>
                  <div class="card mb-3 shadow-sm border-0" style="cursor: pointer;">
                    <div class="card-body d-flex justify-content-between align-items-center">
                      <div>
                        <h6 class="fw-bold mb-2 text-danger">
                          <i class="bi bi-<?php echo ($record['record_type'] ?? 'visit') === 'dental' ? 'tooth' : 'heart-pulse'; ?> me-2"></i>
                          <?php echo ucfirst($record['record_type'] ?? 'Medical'); ?> Checkup
                        </h6>
                        <p class="mb-1">
                          <i class="bi bi-person-badge text-secondary me-1"></i>
                          <?php echo htmlspecialchars($record['physician_name'] ?? 'N/A'); ?>
                        </p>
                        <p class="mb-1 text-muted">
                          <i class="bi bi-calendar-event me-1"></i>
                          <?php echo formatDate($record['visit_date']); ?> at 
                          <?php echo isset($record['visit_time']) ? formatTime($record['visit_time']) : 'N/A'; ?>
                        </p>
                      </div>
                      <button class="btn btn-danger" onclick="viewFullRecord(<?php echo $index; ?>)">
                        <i class="bi bi-file-text"></i> View Full Record
                      </button>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Full Record View (Hidden by default) -->
        <div id="fullRecordView" class="record-view-wrapper" style="display: none;">
          
          <!-- Back Button (fixed at top of content area) -->
          <div class="back-button-container">
            <button class="btn btn-outline-danger back-button" onclick="backToList()">
              <i class="bi bi-arrow-left"></i> Back to Records
            </button>
          </div>

          <!-- Scrollable Record Content -->
          <div class="record-scroll-container">
            <div class="medical-record-page shadow-sm bg-white rounded-3">
              
              <!-- Hospital Header -->
              <div class="record-header text-center">
                <img src="../img/bsu-logo.png" alt="BSU Logo" width="80">
                <h2>BATANGAS STATE UNIVERSITY</h2>
                <p>The National Engineering University</p>
                <p>University Health Services</p>
                <p>Rizal Avenue, Batangas City, Philippines 4200</p>
                <p>Tel: (043) 425-0139 | Email: clinic@batstate-u.edu.ph</p>
              </div>

              <!-- Patient Information (Always shown) -->
              <div class="patient-info-section">
                <h5>PATIENT INFORMATION</h5>
                <div class="row">
                  <div class="col-md-6">
                    <div class="info-row">
                      <span class="info-label-medical">Patient Name:</span>
                      <span class="info-value-medical" id="patientName"><?php echo strtoupper(htmlspecialchars($patientInfo['full_name'])); ?></span>
                    </div>
                    <div class="info-row">
                      <span class="info-label-medical">Student ID:</span>
                      <span class="info-value-medical" id="studentId"><?php echo htmlspecialchars($patientInfo['sr_code']); ?></span>
                    </div>
                    <div class="info-row">
                      <span class="info-label-medical">Date of Birth:</span>
                      <span class="info-value-medical" id="patientDob"><?php echo isset($patientInfo['dob']) ? formatDate($patientInfo['dob']) : 'N/A'; ?></span>
                    </div>
                  </div>
                  <div class="col-md-6">
                    <div class="info-row">
                      <span class="info-label-medical">Date of Visit:</span>
                      <span class="info-value-medical" id="visitDate">—</span>
                    </div>
                    <div class="info-row">
                      <span class="info-label-medical">Time:</span>
                      <span class="info-value-medical" id="visitTime">—</span>
                    </div>
                    <div class="info-row">
                      <span class="info-label-medical">Record Number:</span>
                      <span class="info-value-medical" id="recordNumber">—</span>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Vital Signs (medical & visit only) -->
              <div id="vitalSignsSection">
                <h4 class="section-title">VITAL SIGNS</h4>
                <div class="vital-signs-grid">
                  <div class="vital-item">
                    <div class="vital-label">Blood Pressure</div>
                    <div class="vital-value" id="vitalBP">—</div>
                  </div>
                  <div class="vital-item">
                    <div class="vital-label">BMI</div>
                    <div class="vital-value" id="vitalBMI">—</div>
                  </div>
                  <div class="vital-item">
                    <div class="vital-label">Heart Rate</div>
                    <div class="vital-value" id="vitalHR">—</div>
                  </div>
                  <div class="vital-item">
                    <div class="vital-label">Height</div>
                    <div class="vital-value" id="vitalHeight">—</div>
                  </div>
                  <div class="vital-item">
                    <div class="vital-label">Eye Examination</div>
                    <div class="vital-value" id="vitalEye">—</div>
                  </div>
                  <div class="vital-item">
                    <div class="vital-label">Temperature</div>
                    <div class="vital-value" id="vitalTemp">—</div>
                  </div>
                </div>
              </div>

              <!-- Chief Complaint / Reason for Visit (visit only) -->
              <div id="complaintSection">
                <h4 class="section-title">CHIEF COMPLAINT / REASON FOR VISIT</h4>
                <div class="diagnosis-box" id="chiefComplaint">General checkup</div>
              </div>

              <!-- Diagnosis (Always shown) -->
              <div id="diagnosisSection">
                <h4 class="section-title">DIAGNOSIS</h4>
                <div class="diagnosis-box" id="diagnosisText">—</div>
              </div>

              <!-- Treatment / Medication (visit only) -->
              <div id="medicationSection">
                <h4 class="section-title">TREATMENT / MEDICATION PRESCRIBED</h4>
                <div class="medication-box" id="medicationText">—</div>
              </div>

              <!-- Clinical Notes (Always shown) -->
              <div id="clinicalNotesSection">
                <h4 class="section-title">CLINICAL NOTES</h4>
                <div class="notes-box" id="clinicalNotes">—</div>
              </div>

              <!-- Physician Name -->
              <div class="mt-4 text-end">
                <p class="fw-bold mb-0">Attending Physician:</p>
                <p id="physicianName" class="text-danger fw-semibold">—</p>
              </div>

            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ===== JS ===== -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Medical records data from PHP
    const medicalRecords = <?php echo json_encode($medicalRecords, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

    function viewFullRecord(index) {
      const record = medicalRecords[index];
      
      if (!record) {
        console.error('Record not found');
        return;
      }

      // Format date and time
      const visitDate = new Date(record.visit_date);
      const formattedDate = visitDate.toLocaleDateString("en-US", { year: "numeric", month: "long", day: "numeric" });
      const formattedTime = record.visit_time ? new Date("2000-01-01 " + record.visit_time).toLocaleTimeString("en-US", { hour: "numeric", minute: "2-digit", hour12: true }) : 'N/A';

      // Fill in common fields
      document.getElementById('visitDate').textContent = formattedDate;
      document.getElementById('visitTime').textContent = formattedTime;
      document.getElementById('recordNumber').textContent = `REC-${new Date(record.visit_date).getFullYear()}-${String(record.id).padStart(4, '0')}`;
      document.getElementById('diagnosisText').textContent = record.diagnosis || '—';
      document.getElementById('clinicalNotes').textContent = record.clinical_notes || record.notes || '—';
      document.getElementById('physicianName').textContent = record.physician_name || '—';

      const recordType = record.record_type || 'visit';

      // Conditional display based on record type
      if (recordType === "medical") {
        // Medical: Show vital signs and clinical notes only
        document.getElementById('vitalSignsSection').style.display = 'block';
        document.getElementById('vitalBP').textContent = record.blood_pressure || '—';
        document.getElementById('vitalBMI').textContent = record.bmi || '—';
        document.getElementById('vitalHR').textContent = record.heart_rate ? record.heart_rate + ' bpm' : '—';
        document.getElementById('vitalHeight').textContent = record.height ? record.height + ' cm' : '—';
        document.getElementById('vitalEye').textContent = record.eye_test || '—';
        document.getElementById('vitalTemp').textContent = record.temperature ? record.temperature + '°C' : '—';
        
        document.getElementById('complaintSection').style.display = 'none';
        document.getElementById('medicationSection').style.display = 'none';
        document.getElementById('diagnosisSection').style.display = 'block';
      } 
      else if (recordType === "dental") {
        // Dental: Show diagnosis and clinical notes only
        document.getElementById('vitalSignsSection').style.display = 'none';
        document.getElementById('complaintSection').style.display = 'none';
        document.getElementById('medicationSection').style.display = 'none';
        document.getElementById('diagnosisSection').style.display = 'block';
      } 
      else {
        // Visit: Show everything
        document.getElementById('vitalSignsSection').style.display = 'block';
        document.getElementById('vitalBP').textContent = record.blood_pressure || '—';
        document.getElementById('vitalBMI').textContent = record.bmi || '—';
        document.getElementById('vitalHR').textContent = record.heart_rate ? record.heart_rate + ' bpm' : '—';
        document.getElementById('vitalHeight').textContent = record.height ? record.height + ' cm' : '—';
        document.getElementById('vitalEye').textContent = record.eye_test || '—';
        document.getElementById('vitalTemp').textContent = record.temperature ? record.temperature + '°C' : '—';
        
        document.getElementById('complaintSection').style.display = 'block';
        document.getElementById('chiefComplaint').textContent = record.purpose || record.chief_complaint || 'General checkup';
        
        document.getElementById('diagnosisSection').style.display = 'block';
        
        document.getElementById('medicationSection').style.display = 'block';
        const medication = record.medication || record.treatment || '—';
        document.getElementById('medicationText').innerHTML = medication.replace(/\n/g, '<br>');
      }

      document.getElementById('recordsListView').style.display = 'none';
      document.getElementById('fullRecordView').style.display = 'flex';
    }

    function backToList() {
      document.getElementById('recordsListView').style.display = 'block';
      document.getElementById('fullRecordView').style.display = 'none';
    }
  </script>
</body>
</html>
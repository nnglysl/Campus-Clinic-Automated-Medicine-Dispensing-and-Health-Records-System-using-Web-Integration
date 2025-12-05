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

<<<<<<< HEAD
$fullName = trim($user['fname'] . ' ' . $user['lname']);

=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
$pdo = getDB();

// Fetch patient information
$patientInfo = [];
try {
<<<<<<< HEAD
    // First try to find by user_id (most reliable)
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $patientInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If not found by user_id, try by sr_code
    if (!$patientInfo && $user['sr_code'] && $user['sr_code'] !== 'N/A') {
        $stmt = $pdo->prepare("SELECT * FROM patients WHERE sr_code = ?");
        $stmt->execute([$user['sr_code']]);
        $patientInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    // If still not found, use session data as fallback
    if (!$patientInfo) {
        $patientInfo = [
            'full_name' => $user['full_name'],
            'sr_code' => $user['sr_code'],
            'dob' => $user['dob'],
            'address' => '',
            'program' => '',
            'sex' => '',
            'date_of_birth' => $user['dob']
        ];
    } else {
        // Ensure all required fields are present
        $patientInfo['full_name'] = $patientInfo['full_name'] ?? $user['full_name'];
        $patientInfo['sr_code'] = $patientInfo['sr_code'] ?? $user['sr_code'];
        $patientInfo['dob'] = $patientInfo['date_of_birth'] ?? $patientInfo['dob'] ?? $user['dob'];
        $patientInfo['date_of_birth'] = $patientInfo['date_of_birth'] ?? $patientInfo['dob'] ?? $user['dob'];
        $patientInfo['address'] = $patientInfo['address'] ?? '';
        $patientInfo['program'] = $patientInfo['program'] ?? '';
        $patientInfo['sex'] = $patientInfo['sex'] ?? '';
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    }
} catch (PDOException $e) {
    error_log("Error fetching patient info: " . $e->getMessage());
    $patientInfo = [
        'full_name' => $user['full_name'],
        'sr_code' => $user['sr_code'],
<<<<<<< HEAD
        'dob' => $user['dob'],
        'address' => '',
        'program' => '',
        'sex' => '',
        'date_of_birth' => $user['dob']
    ];
}

// Fetch medical records from database - using same logic as medical_records_handler.php for consistency
$medicalRecords = [];
try {
    // First, get the patient record to get patients.id
    $patientStmt = $pdo->prepare("SELECT id, user_id FROM patients WHERE user_id = ? OR sr_code = ?");
    $patientStmt->execute([$user['id'], $user['sr_code']]);
    $patient = $patientStmt->fetch(PDO::FETCH_ASSOC);
    
    if ($patient) {
        $patient_id = $patient['id'];
        $user_id = $patient['user_id'] ?? $user['id'];
        
        // Fetch medical records - using same query as medical_records_handler.php
        $stmt = $pdo->prepare("
            SELECT DISTINCT
                mr.id,
                mr.patient_id,
                mr.employee_id,
                mr.visit_date,
                mr.visit_time,
                mr.chief_complaint,
                mr.diagnosis,
                mr.treatment_instructions,
                mr.blood_pressure,
                mr.heart_rate,
                mr.pulse_rate,
                mr.spo2,
                mr.respiratory_rate,
                mr.temperature,
                mr.height,
                mr.weight,
                mr.bmi,
                mr.created_at,
                mr.updated_at,
                CONCAT(u.fname, ' ', u.lname) as physician_name,
                COALESCE(p.full_name, CONCAT(up.fname, ' ', up.lname)) as patient_name,
                mr.chief_complaint as purpose,
                mr.treatment_instructions as treatment,
                NULL as notes,
                COALESCE(a.appointment_type, 'medical') as appointment_type
            FROM medical_records mr
            LEFT JOIN users u ON mr.employee_id = u.id
            LEFT JOIN patients p ON mr.patient_id = p.id
            LEFT JOIN users up ON mr.patient_id = up.id AND p.id IS NULL
            LEFT JOIN appointments a ON (
                (a.patient_id = p.user_id OR a.patient_id = up.id)
                AND a.appointment_date = mr.visit_date 
                AND a.appointment_time = mr.visit_time
                AND a.status = 'completed'
            )
            WHERE mr.patient_id = ?
            ORDER BY mr.visit_date DESC, mr.visit_time DESC
        ");
        $stmt->execute([$patient_id]);
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check which medical_records are actually dental records (same logic as medical_records_handler.php)
        $dentalMedicalRecordIds = [];
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'dental_records'");
            if ($tableCheck->rowCount() > 0) {
                $dentalStmt = $pdo->prepare("SELECT id, patient_id, created_at FROM dental_records WHERE patient_id = ?");
                $dentalStmt->execute([$patient_id]);
                $allDentalRecords = $dentalStmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($allDentalRecords as $dentalRecord) {
                    $dentalDate = date('Y-m-d', strtotime($dentalRecord['created_at']));
                    $findStmt = $pdo->prepare("
                        SELECT id FROM medical_records 
                        WHERE patient_id = ? 
                        AND visit_date = ? 
                        AND (chief_complaint LIKE '%Dental%' OR chief_complaint LIKE '%dental%')
                        LIMIT 1
                    ");
                    $findStmt->execute([$patient_id, $dentalDate]);
                    $medRecord = $findStmt->fetch(PDO::FETCH_ASSOC);
                    if ($medRecord) {
                        $dentalMedicalRecordIds[] = $medRecord['id'];
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("Note: Error checking dental records: " . $e->getMessage());
        }
        
        // Add record_type for medical_records (same logic as medical_records_handler.php)
        foreach ($records as &$record) {
            $record['is_consultation'] = false;
            
            // Check if this medical_record is actually from a dental appointment
            if (in_array($record['id'], $dentalMedicalRecordIds)) {
                $record['record_type'] = 'Dental';
                $record['appointment_type'] = 'dental';
                $record['is_dental'] = true;
            } elseif ($record['appointment_type'] === 'dental') {
                $record['record_type'] = 'Dental';
                $record['is_dental'] = true;
            } elseif (stripos($record['chief_complaint'] ?? '', 'dental') !== false || 
                     stripos($record['chief_complaint'] ?? '', 'Dental Check-up') !== false) {
                $record['record_type'] = 'Dental';
                $record['appointment_type'] = 'dental';
                $record['is_dental'] = true;
            } else {
                // Default: Medical (from scheduled appointments)
                if (!empty($record['appointment_type']) && $record['appointment_type'] === 'medical') {
                    $record['record_type'] = 'Medical';
                    $record['is_from_appointment'] = true;
                } else {
                    $record['record_type'] = 'Medical';
                    $record['is_from_appointment'] = false;
                }
                $record['is_dental'] = false;
            }
        }
        unset($record);
        
        // Also get dental records from dental_records table (same logic as medical_records_handler.php)
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'dental_records'");
            if ($tableCheck->rowCount() > 0) {
                $stmt = $pdo->prepare("
                    SELECT 
                        dr.id as dental_record_id,
                        dr.patient_id,
                        dr.dentist_id,
                        dr.program,
                        dr.gingivitis,
                        dr.early_periodontitis,
                        dr.class_molar,
                        dr.overjet,
                        dr.overbite,
                        dr.orthodontic,
                        dr.stayplate,
                        dr.clenching,
                        dr.clicking,
                        dr.tooth_status,
                        dr.treatments,
                        dr.remarks,
                        dr.created_at,
                        dr.updated_at,
                        dr.created_at as visit_date,
                        NULL as visit_time,
                        CONCAT(u.fname, ' ', u.lname) as physician_name,
                        p.full_name as patient_name,
                        'Dental Examination' as chief_complaint,
                        CASE 
                            WHEN dr.gingivitis = 1 THEN 'Gingivitis'
                            WHEN dr.early_periodontitis = 1 THEN 'Early Periodontitis'
                            WHEN dr.class_molar = 1 THEN 'Class Molar'
                            WHEN dr.overjet = 1 THEN 'Overjet'
                            WHEN dr.overbite = 1 THEN 'Overbite'
                            WHEN dr.orthodontic = 1 THEN 'Orthodontic'
                            WHEN dr.stayplate = 1 THEN 'Stayplate'
                            WHEN dr.clenching = 1 THEN 'Clenching'
                            WHEN dr.clicking = 1 THEN 'Clicking'
                            ELSE 'Dental Examination'
                        END as diagnosis,
                        dr.remarks as treatment_instructions,
                        'dental' as appointment_type
                    FROM dental_records dr
                    LEFT JOIN users u ON dr.dentist_id = u.id
                    LEFT JOIN patients p ON dr.patient_id = p.id
                    WHERE dr.patient_id = ?
                    ORDER BY dr.created_at DESC
                ");
                $stmt->execute([$patient_id]);
                $dentalRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Merge dental records into records array
                foreach ($dentalRecords as $dentalRecord) {
                    $dentalRecord['is_consultation'] = false;
                    $dentalRecord['record_type'] = 'Dental';
                    $dentalRecord['is_dental'] = true;
                    
                    if (!isset($dentalRecord['dental_record_id']) && isset($dentalRecord['id'])) {
                        $dentalRecord['dental_record_id'] = $dentalRecord['id'];
                    }
                    
                    if (!empty($dentalRecord['tooth_status'])) {
                        $dentalRecord['tooth_status'] = json_decode($dentalRecord['tooth_status'], true) ?? [];
                    } else {
                        $dentalRecord['tooth_status'] = [];
                    }
                    
                    if (!empty($dentalRecord['treatments'])) {
                        $dentalRecord['treatments'] = json_decode($dentalRecord['treatments'], true) ?? [];
                    } else {
                        $dentalRecord['treatments'] = [];
                    }
                    
                    if (!isset($dentalRecord['dentist_name'])) {
                        $dentalRecord['dentist_name'] = $dentalRecord['physician_name'] ?? null;
                    }
                    
                    if ($dentalRecord['visit_date']) {
                        $dentalRecord['visit_date'] = date('Y-m-d', strtotime($dentalRecord['visit_date']));
                    }
                    
                    $records[] = $dentalRecord;
                }
            }
        } catch (PDOException $e) {
            error_log("Note: dental_records query error: " . $e->getMessage());
        }
        
        // Also get medical consultations and merge them (same logic as medical_records_handler.php)
        try {
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'medical_consultations'");
            if ($tableCheck->rowCount() > 0) {
                $stmt = $pdo->prepare("
            SELECT 
                mc.id,
                mc.patient_id,
                mc.physician_id,
                mc.assessment_date,
                mc.control_number,
                mc.age,
                mc.sex,
                mc.purpose,
                mc.blood_pressure,
                mc.pulse_rate,
                mc.spo2,
                mc.respiratory_rate,
                mc.temperature,
                mc.height,
                mc.weight,
                mc.bmi,
                mc.last_menstrual_period,
                mc.vision_right,
                mc.vision_left,
                mc.nurse_notes,
                mc.doctor_date,
                mc.doctor_notes,
                mc.created_at,
                mc.updated_at,
                        p.full_name as patient_name,
                        COALESCE(mc.doctor_notes, mc.nurse_notes, '') as treatment,
                        TRIM(CONCAT(COALESCE(mc.nurse_notes, ''), ' ', COALESCE(mc.doctor_notes, ''))) as notes,
                mc.assessment_date as visit_date,
                NULL as visit_time,
                        mc.purpose as chief_complaint,
                        COALESCE(mc.doctor_notes, mc.nurse_notes, 'Consultation completed') as diagnosis,
                        COALESCE(mc.physician_name, CONCAT(u.fname, ' ', u.lname), 'Unknown') as physician_name
            FROM medical_consultations mc
                    LEFT JOIN patients p ON mc.patient_id = p.id
                    LEFT JOIN users u ON mc.physician_id = u.id
            WHERE mc.patient_id = ?
            ORDER BY mc.assessment_date DESC, mc.created_at DESC
        ");
                $stmt->execute([$patient_id]);
                $consultations = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Merge consultations into records
                foreach ($consultations as $consultation) {
                    $consultation['chief_complaint'] = $consultation['purpose'] ?? 'Consultation';
                    $consultation['diagnosis'] = $consultation['doctor_notes'] ?? $consultation['nurse_notes'] ?? 'Consultation completed';
                    $consultation['treatment'] = $consultation['doctor_notes'] ?? $consultation['nurse_notes'] ?? 'N/A';
                    $consultation['treatment_instructions'] = $consultation['treatment'];
                    
                    // Add a flag to identify it as a consultation (CRITICAL for proper display)
                    $consultation['is_consultation'] = true;
                    $consultation['record_type'] = 'Consultation';
                    $consultation['appointment_type'] = null;
                    $consultation['is_from_appointment'] = false;
                    $records[] = $consultation;
                }
            }
        } catch (PDOException $e) {
            error_log("Note: medical_consultations query error: " . $e->getMessage());
        }
        
        // Final sort by date (includes all types: medical, dental, consultations)
        usort($records, function($a, $b) {
            $dateA = $a['visit_date'] ?? $a['assessment_date'] ?? $a['created_at'] ?? '';
            $dateB = $b['visit_date'] ?? $b['assessment_date'] ?? $b['created_at'] ?? '';
            return strcmp($dateB, $dateA);
        });
        
        $medicalRecords = $records;
    }
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
} catch (PDOException $e) {
    error_log("Error fetching medical records: " . $e->getMessage());
    $medicalRecords = [];
}

// Format date for display
function formatDate($date) {
<<<<<<< HEAD
    if (!$date) return 'N/A';
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    return date('F j, Y', strtotime($date));
}

// Format time for display
function formatTime($time) {
<<<<<<< HEAD
    if (!$time) return 'N/A';
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    return date('g:i A', strtotime($time));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Health Records</title>

<<<<<<< HEAD
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Text:ital@0;1&display=swap" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link href="css/nav.css" rel="stylesheet" />
  <link href="css/records.css" rel="stylesheet">
  <link href="../medical/css/medical_patients.css" rel="stylesheet">
  <link href="../dental/css/dental_patients.css" rel="stylesheet">
  <link href="../admin/css/notifications.css" rel="stylesheet" />
  <link href="css/responsive.css" rel="stylesheet" />
</head>

<body>
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    <!-- Header -->
    <div class="header">
      <div class="logo-section">
        <div class="logo">
<<<<<<< HEAD
          <img src="../img/bsu-logo.png" alt="University Logo" loading="lazy" />
=======
          <img src="../img/bsu-logo.png" alt="University Logo" />
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        </div>
        <div class="university-name">
          <h1>Batangas State</h1>
          <h1>University</h1>
        </div>
      </div>
      <div class="header-icons">
<<<<<<< HEAD
         <!-- ADD MOBILE MENU ICON FIRST -->
    <div class="mobile-menu-icon" id="mobileMenuBtn">
      <i class="bi bi-list"></i>
    </div>
        <?php include 'notification_component.php'; ?>
        <div class="logout-icon" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></div>
=======
        <div class="notification-icon"><i class="bi bi-bell-fill"></i></div>
        <div class="logout-icon" id="logoutBtn" onclick="window.location.href='../logout.php'"><i class="bi bi-box-arrow-right"></i></div>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
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
<<<<<<< HEAD

        <div class="user-profile">
          <div class="avatar"></div>
          <span><?php echo htmlspecialchars($fullName); ?></span>
        </div>
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
      </div>

      <!-- Main Content -->
      <div class="content-demo p-4">
        <!-- Records List View -->
        <div id="recordsListView">
          <div class="container-fluid">
<<<<<<< HEAD
            <div class="row">
              <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                  <h2>My Health Records</h2>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-12">
                <h5 class="text-secondary mb-3">Medical History</h5>
              </div>
            </div>
            <div class="row">
              <div class="col-12">
                <div id="medicalHistoryContainer">
=======
            <div class="d-flex justify-content-between align-items-center mb-4">
              <h2 class="fw-bold">My Health Records</h2>
            </div>

            <h5 class="text-secondary mb-3">Medical History</h5>
            <div id="medicalHistoryContainer">
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
              <?php if (empty($medicalRecords)): ?>
                <div class="alert alert-info">
                  <i class="bi bi-info-circle me-2"></i>
                  No medical records found. Visit the clinic to start your health record.
                </div>
              <?php else: ?>
<<<<<<< HEAD
                <?php foreach ($medicalRecords as $index => $record): 
                  // Determine record type - ensure proper labeling
                  $recordType = 'Medical'; // Default
                  if (isset($record['is_consultation']) && $record['is_consultation']) {
                      $recordType = 'Consultation';
                  } elseif (isset($record['is_dental']) && $record['is_dental']) {
                      $recordType = 'Dental';
                  } elseif (isset($record['record_type'])) {
                      $recordType = $record['record_type'];
                  } elseif (isset($record['appointment_type']) && $record['appointment_type'] === 'dental') {
                      $recordType = 'Dental';
                  }
                  
                  $visitDate = $record['visit_date'] ?? $record['assessment_date'] ?? '';
                  $visitTime = $record['visit_time'] ?? '';
                  $physicianName = $record['physician_name'] ?? 'N/A';
                ?>
                  <div class="card mb-3 shadow-sm border-0" style="cursor: pointer;">
                    <div class="card-body">
                      <div class="row align-items-center">
                        <div class="col-12 col-sm-12 col-md-8 col-lg-8 col-xl-8 col-xxl-8">
                          <h6 class="fw-bold mb-2 text-danger">
                            <i class="bi bi-<?php 
                              $icon = 'heart-pulse'; // Default
                              if (strtolower($recordType) === 'dental') {
                                  $icon = 'tooth';
                              } elseif (strtolower($recordType) === 'consultation') {
                                  $icon = 'file-medical';
                              }
                              echo $icon;
                            ?> me-2"></i>
                            <?php echo htmlspecialchars($recordType); ?> Record
                          </h6>
                          <p class="mb-1">
                            <i class="bi bi-person-badge text-secondary me-1"></i>
                            <?php echo htmlspecialchars($physicianName); ?>
                          </p>
                          <p class="mb-1 text-muted">
                            <i class="bi bi-calendar-event me-1"></i>
                            <?php echo $visitDate ? formatDate($visitDate) : 'N/A'; ?>
                            <?php if ($visitTime): ?>
                              at <?php echo formatTime($visitTime); ?>
                            <?php endif; ?>
                          </p>
                        </div>
                        <div class="col-12 col-sm-12 col-md-4 col-lg-4 col-xl-4 col-xxl-4 text-end mt-2 mt-md-0">
                          <button class="btn btn-danger w-100 w-md-auto" onclick="viewFullRecord(<?php echo $index; ?>)">
                            <i class="bi bi-file-text"></i> View Full Record
                          </button>
                        </div>
                      </div>
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endif; ?>
<<<<<<< HEAD
                </div>
              </div>
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            </div>
          </div>
        </div>

<<<<<<< HEAD
        <?php include __DIR__ . '/includes/record_modals.php'; ?>
      </div>
    </div>

  <!-- ===== JS ===== -->
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/mobile-menu.js"></script>
  <script src="js/notifications.js"></script>
  <script src="../js/logout.js"></script>
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
  <script>
    // Medical records data from PHP
    const medicalRecords = <?php echo json_encode($medicalRecords, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

<<<<<<< HEAD
    // Patient data for modals - ensure all fields are properly escaped and available
    const patientData = {
      full_name: <?php echo json_encode($patientInfo['full_name'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      sr_code: <?php echo json_encode($patientInfo['sr_code'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      address: <?php echo json_encode($patientInfo['address'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      program: <?php echo json_encode($patientInfo['program'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      dob: <?php echo json_encode($patientInfo['date_of_birth'] ?? $patientInfo['dob'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      date_of_birth: <?php echo json_encode($patientInfo['date_of_birth'] ?? $patientInfo['dob'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      sex: <?php echo json_encode($patientInfo['sex'] ?? $patientInfo['gender'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
      age: <?php echo json_encode($patientInfo['age'] ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
    };
    
    // Debug: Log patient data to console (remove in production)
    console.log('Patient Data:', patientData);

    // Reusable function to calculate age from date of birth
    function calculateAge(dateOfBirth) {
      if (!dateOfBirth || dateOfBirth === '' || dateOfBirth === 'N/A') {
        return null;
      }
      
      try {
        const today = new Date();
        const birthDate = new Date(dateOfBirth);
        
        // Check if date is valid
        if (isNaN(birthDate.getTime())) {
          return null;
        }
        
        let age = today.getFullYear() - birthDate.getFullYear();
        const monthDiff = today.getMonth() - birthDate.getMonth();
        
        // Adjust age if birthday hasn't occurred this year
        if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
          age--;
        }
        
        return age;
      } catch (error) {
        console.error('Error calculating age:', error);
        return null;
      }
    }

    // Function to get and display age for patient
    function getPatientAge() {
      // First check if age is directly available
      if (patientData.age && patientData.age !== '' && patientData.age !== null && !isNaN(patientData.age)) {
        return patientData.age;
      }
      
      // Then try to calculate from date of birth
      const dob = patientData.date_of_birth || patientData.dob;
      if (dob && dob !== '' && dob !== 'N/A') {
        const calculatedAge = calculateAge(dob);
        if (calculatedAge !== null && !isNaN(calculatedAge)) {
          return calculatedAge;
        }
      }
      
      return '';
    }

=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    function viewFullRecord(index) {
      const record = medicalRecords[index];
      
      if (!record) {
        console.error('Record not found');
<<<<<<< HEAD
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'Record not found',
          confirmButtonColor: '#800000'
        });
        return;
      }

      // Determine record type - ensure proper identification
      const isConsultation = record.is_consultation === true;
      const isDental = record.is_dental === true || record.record_type === 'Dental' || record.appointment_type === 'dental';
      const isFromAppointment = record.is_from_appointment === true || 
                                (record.appointment_type === 'medical' && !record.is_consultation);

      if (isDental) {
        // Show dental record modal
        showDentalRecordModal(record);
      } else {
        // Show medical/consultation record modal
        showMedicalRecordModal(record, isConsultation, isFromAppointment);
      }
    }

    // Show medical/consultation record modal (same logic as medical_patients.php)
    function showMedicalRecordModal(record, isConsultation, isFromAppointment) {
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

      // Populate patient information - ensure fields exist before setting values
      // This applies to both consultation and appointment records since Patient Information is shared
      const fullNameField = document.getElementById('viewFullName');
      const srCodeField = document.getElementById('viewSRCode');
      const addressField = document.getElementById('viewAddress');
      const programField = document.getElementById('viewProgram');
      const ageField = document.getElementById('viewAge');
      const sexField = document.getElementById('viewSex');
      
      if (fullNameField) {
        fullNameField.value = patientData.full_name || record.patient_name || record.full_name || 'N/A';
      }
      if (srCodeField) {
        srCodeField.value = patientData.sr_code || record.sr_code || 'N/A';
      }
      if (addressField) {
        addressField.value = patientData.address || record.address || 'N/A';
      }
      if (programField) {
        programField.value = patientData.program || record.program || 'N/A';
      }
      
      // Calculate and display age - check record age first, then calculate from patient DOB
      let age = '';
      if (record?.age && record.age !== '' && record.age !== null && !isNaN(record.age)) {
        age = record.age;
      } else {
        age = getPatientAge();
      }
      if (ageField) {
        ageField.value = age || '';
      }
      
      // Set gender - check multiple sources
      if (sexField) {
        sexField.value = patientData.sex || record.sex || record.gender || '';
      }
      
      // Debug: Log populated values (remove in production)
      console.log('Populated Patient Info:', {
        full_name: fullNameField?.value,
        sr_code: srCodeField?.value,
        address: addressField?.value,
        program: programField?.value,
        age: ageField?.value,
        sex: sexField?.value,
        patientData: patientData,
        record: record,
        calculatedAge: age
      });

      // Populate consultation form fields (read-only)
      if (isConsultation) {
        // It's a consultation - show consultation form
        const modalTitle = document.getElementById('modalRecordTitle');
        const modalRefNo = document.getElementById('modalReferenceNo');
        const modalHeaderTitle = document.getElementById('modalHeaderTitle');
        if (modalTitle) modalTitle.textContent = 'MEDICAL CONSULTATION RECORD';
        if (modalRefNo) modalRefNo.textContent = 'BatStateU-FO-HSD-12';
        if (modalHeaderTitle) modalHeaderTitle.textContent = 'Medical Consultation Record';
        
        document.getElementById('viewPurpose').value = record.purpose || record.chief_complaint || '';
        document.getElementById('viewAssessmentDate').value = record.assessment_date || record.visit_date || '';
        document.getElementById('viewControlNumber').value = record.control_number || '';
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
        
        // Show consultation form, hide appointment form
        document.getElementById('consultationFormFields').style.display = 'block';
        document.getElementById('appointmentFormFields').style.display = 'none';
      } else if (isFromAppointment) {
        // It's a medical record from appointment - show appointment form layout
        const modalTitle = document.getElementById('modalRecordTitle');
        const modalRefNo = document.getElementById('modalReferenceNo');
        const modalHeaderTitle = document.getElementById('modalHeaderTitle');
        if (modalTitle) modalTitle.textContent = 'MEDICAL APPOINTMENT RECORD';
        if (modalRefNo) modalRefNo.textContent = 'BatStateU-FO-HSD-11';
        if (modalHeaderTitle) modalHeaderTitle.textContent = 'Medical Appointment Record';
        
        // Ensure Patient Information section is visible (it should be, but double-check)
        // The Patient Information section is shared and should always be visible
        const fullNameField = document.getElementById('viewFullName');
        if (fullNameField && fullNameField.closest('.consultation-section')) {
          fullNameField.closest('.consultation-section').style.display = 'block';
        }
        
        // Re-populate age and gender to ensure they're set for appointment records
        const ageField = document.getElementById('viewAge');
        const sexField = document.getElementById('viewSex');
        
        // Always set age and gender for appointment records to ensure they're displayed
        if (ageField) {
          let age = '';
          if (record?.age && record.age !== '' && record.age !== null && !isNaN(record.age)) {
            age = record.age;
          } else {
            age = getPatientAge();
          }
          ageField.value = age || '';
        }
        if (sexField) {
          sexField.value = patientData.sex || record.sex || record.gender || '';
        }
        
        // Populate appointment-specific fields
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

    // Show dental record modal (same logic as dental_patients.php)
    function showDentalRecordModal(record) {
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

      // Populate patient information
      document.getElementById('viewDentalFullName').value = patientData.full_name || record.patient_name || 'N/A';
      document.getElementById('viewDentalSRCode').value = patientData.sr_code || 'N/A';
      document.getElementById('viewDentalAddress').value = patientData.address || 'N/A';
      document.getElementById('viewDentalProgram').value = patientData.program || 'N/A';
      
      // Set appointment date and dentist
      const visitDate = record.visit_date || record.created_at || '';
      document.getElementById('viewDentalDate').value = visitDate.split(' ')[0];
      
      const dentistField = document.getElementById('viewDentalDentist');
      if (dentistField) {
        dentistField.value = record.dentist_name || record.physician_name || 'N/A';
      }
      
      // Populate dental conditions checkboxes
      if (document.getElementById('viewDentalGingivitis')) {
        document.getElementById('viewDentalGingivitis').checked = record.gingivitis == 1;
        document.getElementById('viewDentalEarlyPeriodontitis').checked = record.early_periodontitis == 1;
        document.getElementById('viewDentalClassMolar').checked = record.class_molar == 1;
        document.getElementById('viewDentalOverjet').checked = record.overjet == 1;
        document.getElementById('viewDentalOverbite').checked = record.overbite == 1;
        document.getElementById('viewDentalOrthodontic').checked = record.orthodontic == 1;
        document.getElementById('viewDentalStayplate').checked = record.stayplate == 1;
        document.getElementById('viewDentalClenching').checked = record.clenching == 1;
        document.getElementById('viewDentalClicking').checked = record.clicking == 1;
      }
      
      // Initialize and populate tooth chart
      const toothStatus = (record.tooth_status && typeof record.tooth_status === 'object') 
          ? record.tooth_status 
          : (typeof record.tooth_status === 'string' ? JSON.parse(record.tooth_status || '{}') : {});
      
      if (typeof initializeDentalChartForView === 'function') {
        initializeDentalChartForView(toothStatus);
      }
      
      // Populate tooth status after chart is initialized
      if (toothStatus && typeof toothStatus === 'object') {
        Object.keys(toothStatus).forEach(toothNum => {
          const selectEl = document.getElementById(`viewTooth_${toothNum}_status`);
          if (selectEl) {
            selectEl.value = toothStatus[toothNum];
          }
        });
      }
      
      // Populate treatment records
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
      if (document.getElementById('viewDentalRemarks')) {
        document.getElementById('viewDentalRemarks').value = record.remarks || '';
      }
      
      // Show modal
      modal.show();
    }
    
    // Initialize notification system
    if (window.StudentNotificationSystem) {
      StudentNotificationSystem.init();
    }
  </script>

  <!-- Medical Record View Modal (Same as medical/dental staff) -->
  <?php include __DIR__ . '/includes/record_modals.php'; ?>
=======
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
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
</body>
</html>
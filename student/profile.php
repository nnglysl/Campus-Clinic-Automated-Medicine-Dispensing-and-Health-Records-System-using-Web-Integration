<?php
require_once '../config/database.php';
require_once '../includes/patient_sync.php';
session_start();

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: ../auth/login.php");
    exit();
}

$user = [
    'id' => $_SESSION['user_id'],
    'role' => $_SESSION['role'] ?? 'patient'
];

$pdo = getDB();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    header('Content-Type: application/json');
    
    try {
        // Personal Information
        $firstName = $_POST['first_name'] ?? '';
        $middleName = $_POST['middle_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $contact = $_POST['contact'] ?? '';
        $srCode = trim($_POST['sr_code'] ?? '');
        $dob = $_POST['dob'] ?? '';
        $bloodType = $_POST['blood_type'] ?? '';
        $address = $_POST['address'] ?? '';
        $gender = $_POST['gender'] ?? '';
        
        // Academic Information
        $course = $_POST['course'] ?? '';
        $yearLevel = $_POST['year_level'] ?? '';
        
        // Emergency Contact
        $guardianName = $_POST['guardian_name'] ?? '';
        $relationship = $_POST['relationship'] ?? '';
        $guardianContact = $_POST['guardian_contact'] ?? '';
        
        // Medical Information
        $allergies = $_POST['allergies'] ?? '';
        $medicalConditions = $_POST['medical_conditions'] ?? '';
        
        // Get existing profile photo path before upload (for deletion)
        $existingPhotoPath = null;
        try {
            $stmt = $pdo->prepare("SELECT profile_photo FROM user_profiles WHERE user_id = ?");
            $stmt->execute([$user['id']]);
            $existingProfile = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($existingProfile && !empty($existingProfile['profile_photo'])) {
                $existingPhotoPath = $existingProfile['profile_photo'];
            }
        } catch (PDOException $e) {
            error_log("Error fetching existing profile photo: " . $e->getMessage());
        }
        
        // Handle photo upload with enhanced validation
        $photoPath = null;
        $uploadError = null;
        
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/profiles/';
            
            // Create upload directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                if (!mkdir($uploadDir, 0755, true)) {
                    $uploadError = 'Failed to create upload directory.';
                }
            }
            
            if (!$uploadError) {
                $file = $_FILES['profile_photo'];
                $fileSize = $file['size'];
                $tmpName = $file['tmp_name'];
                $originalName = $file['name'];
                
                // File size validation (max 5MB)
                $maxFileSize = 5 * 1024 * 1024; // 5MB in bytes
                if ($fileSize > $maxFileSize) {
                    $uploadError = 'File size exceeds maximum limit of 5MB.';
                }
                
                // Get file extension
                $fileExtension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                // Validate file extension
                if (!in_array($fileExtension, $allowedExtensions)) {
                    $uploadError = 'Invalid file type. Only JPG, PNG, GIF, and WEBP images are allowed.';
                }
                
                // Validate MIME type (more secure)
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                
                $allowedMimeTypes = [
                    'image/jpeg',
                    'image/jpg',
                    'image/png',
                    'image/gif',
                    'image/webp'
                ];
                
                if (!in_array($mimeType, $allowedMimeTypes)) {
                    $uploadError = 'Invalid file type. Please upload a valid image file.';
                }
                
                // Validate that it's actually an image by checking dimensions
                if (!$uploadError) {
                    $imageInfo = @getimagesize($tmpName);
                    if ($imageInfo === false) {
                        $uploadError = 'File is not a valid image.';
                    } else {
                        // Optional: Validate image dimensions (max 2000x2000)
                        $maxDimension = 2000;
                        if ($imageInfo[0] > $maxDimension || $imageInfo[1] > $maxDimension) {
                            $uploadError = "Image dimensions exceed maximum of {$maxDimension}x{$maxDimension} pixels.";
                        }
                    }
                }
                
                // If all validations pass, proceed with upload
                if (!$uploadError) {
                    // Delete old profile photo if exists
                    if (!empty($existingPhotoPath)) {
                        $oldPhotoPath = $existingPhotoPath;
                        // Check if it's a relative path
                        if (strpos($oldPhotoPath, '../') === 0) {
                            $oldPhotoFullPath = $oldPhotoPath;
                        } else {
                            $oldPhotoFullPath = '../uploads/profiles/' . basename($oldPhotoPath);
                        }
                        
                        // Delete old file if it exists
                        if (file_exists($oldPhotoFullPath) && is_file($oldPhotoFullPath)) {
                            @unlink($oldPhotoFullPath);
                        }
                    }
                    
                    // Generate unique filename
                    $fileName = 'profile_' . $user['id'] . '_' . time() . '_' . uniqid() . '.' . $fileExtension;
                    $fullPath = $uploadDir . $fileName;
                    
                    // Move uploaded file
                    if (move_uploaded_file($tmpName, $fullPath)) {
                        // Set proper permissions
                        chmod($fullPath, 0644);
                        $photoPath = '../uploads/profiles/' . $fileName;
                    } else {
                        $uploadError = 'Failed to save uploaded file. Please try again.';
                    }
                }
            }
            
            // If there was an upload error, log it but don't fail the entire update
            if ($uploadError) {
                error_log("Profile photo upload error for user {$user['id']}: " . $uploadError);
                // Don't set photoPath, but continue with profile update
            }
        } elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
            // Handle other upload errors
            $uploadErrorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive in php.ini.',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive in HTML form.',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded.',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
            ];
            
            $errorCode = $_FILES['profile_photo']['error'];
            $uploadError = $uploadErrorMessages[$errorCode] ?? 'Unknown upload error.';
            error_log("Profile photo upload error for user {$user['id']}: " . $uploadError);
        }
        
        // Update users table (basic info + date_of_birth, gender, address for patient role)
        $sql = "UPDATE users SET 
                fname = ?,
                mname = ?,
                lname = ?,
                email = ?,
                phone = ?,
                date_of_birth = ?,
                gender = ?,
                address = ?,
                blood_type = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$firstName, $middleName, $lastName, $email, $contact, $dob ?: null, $gender ?: null, $address ?: null, $bloodType ?: null, $user['id']]);
        
        // Update or insert into user_profiles table (extended info)
        if ($photoPath) {
            // If photo was uploaded, include it in the query
            $profileSql = "INSERT INTO user_profiles 
                          (user_id, dob, blood_type, address, course, year_level, 
                           guardian_name, guardian_relationship, guardian_contact, 
                           allergies, medical_conditions, profile_photo, updated_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                          ON DUPLICATE KEY UPDATE
                          dob = VALUES(dob),
                          blood_type = VALUES(blood_type),
                          address = VALUES(address),
                          course = VALUES(course),
                          year_level = VALUES(year_level),
                          guardian_name = VALUES(guardian_name),
                          guardian_relationship = VALUES(guardian_relationship),
                          guardian_contact = VALUES(guardian_contact),
                          allergies = VALUES(allergies),
                          medical_conditions = VALUES(medical_conditions),
                          profile_photo = VALUES(profile_photo)";
            
            $profileParams = [
                $user['id'],
                $dob,
                $bloodType,
                $address,
                $course,
                $yearLevel,
                $guardianName,
                $relationship,
                $guardianContact,
                $allergies,
                $medicalConditions,
                $photoPath
            ];
        } else {
            // If no photo uploaded, don't update photo field
            $profileSql = "INSERT INTO user_profiles 
                          (user_id, dob, blood_type, address, course, year_level, 
                           guardian_name, guardian_relationship, guardian_contact,
                           allergies, medical_conditions, updated_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                          ON DUPLICATE KEY UPDATE
                          dob = VALUES(dob),
                          blood_type = VALUES(blood_type),
                          address = VALUES(address),
                          course = VALUES(course),
                          year_level = VALUES(year_level),
                          guardian_name = VALUES(guardian_name),
                          guardian_relationship = VALUES(guardian_relationship),
                          guardian_contact = VALUES(guardian_contact),
                          allergies = VALUES(allergies),
                          medical_conditions = VALUES(medical_conditions)";
            
            $profileParams = [
                $user['id'],
                $dob,
                $bloodType,
                $address,
                $course,
                $yearLevel,
                $guardianName,
                $relationship,
                $guardianContact,
                $allergies,
                $medicalConditions
            ];
        }
        
        $stmt = $pdo->prepare($profileSql);
        $stmt->execute($profileParams);

        // Calculate age from date of birth
        $age = null;
        if (!empty($dob)) {
            try {
                $birthDate = new DateTime($dob);
                $today = new DateTime();
                $age = $today->diff($birthDate)->y;
            } catch (Exception $e) {
                error_log("Error calculating age: " . $e->getMessage());
            }
        }
        
        // Update SR code, gender, date_of_birth, and age in patients table
        $stmt = $pdo->prepare("UPDATE patients SET sr_code = ?, sex = ?, date_of_birth = ?, age = ? WHERE user_id = ?");
        $stmt->execute([$srCode, $gender, $dob ?: null, $age, $user['id']]);
        
        // If patient record doesn't exist, create it
        if ($stmt->rowCount() === 0) {
            // Check if patient exists
            $checkStmt = $pdo->prepare("SELECT id FROM patients WHERE user_id = ?");
            $checkStmt->execute([$user['id']]);
            if ($checkStmt->rowCount() === 0) {
                // Create patient record
                $createStmt = $pdo->prepare("INSERT INTO patients (user_id, sr_code, sex, full_name, fname, mname, lname, email, contact_number, date_of_birth, age, blood_type, address, program, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                $fullName = trim($firstName . ' ' . $middleName . ' ' . $lastName);
                $createStmt->execute([$user['id'], $srCode, $gender, $fullName, $firstName, $middleName, $lastName, $email, $contact, $dob ?: null, $age, $bloodType, $address, $course]);
            } else {
                // Update existing record with age
                $updateStmt = $pdo->prepare("UPDATE patients SET sr_code = ?, sex = ?, date_of_birth = ?, age = ? WHERE user_id = ?");
                $updateStmt->execute([$srCode, $gender, $dob ?: null, $age, $user['id']]);
            }
        }

        // Note: syncPatientRecords() is not called here to avoid trigger conflicts
        // The triggers on user_profiles (after_profile_insert_patient, after_profile_update_patient)
        // already handle syncing to patients table automatically
        // If triggers are removed, uncomment the line below:
        // syncPatientRecords($pdo);
        
        // Update session variables
        $_SESSION['fname'] = $firstName;
        $_SESSION['lname'] = $lastName;
        $_SESSION['email'] = $email;
        $_SESSION['username'] = trim($firstName . ' ' . $lastName);
        
        // Prepare response message
        $message = 'Profile updated successfully!';
        if (isset($uploadError) && $uploadError) {
            $message .= ' However, there was an issue with the photo upload: ' . $uploadError;
        } elseif ($photoPath) {
            $message .= ' Profile photo updated successfully.';
        }
        
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'photo_uploaded' => !empty($photoPath),
            'upload_error' => $uploadError ?? null
        ]);
        exit();
        
    } catch (PDOException $e) {
        error_log("Error updating profile: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
        exit();
    }
}

// Fetch student profile data
$studentProfile = [];
try {
    // Get basic user info
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user['id']]);
    $userInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get extended profile info
    $stmt = $pdo->prepare("SELECT * FROM user_profiles WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $profileInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get patient info (for SR code and gender)
    $stmt = $pdo->prepare("SELECT sr_code, sex FROM patients WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    $patientInfo = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Combine data
    $studentProfile = [
        'first_name' => $userInfo['fname'] ?? '',
        'middle_name' => $userInfo['mname'] ?? '',
        'last_name' => $userInfo['lname'] ?? '',
        'full_name' => trim(($userInfo['fname'] ?? '') . ' ' . ($userInfo['mname'] ?? '') . ' ' . ($userInfo['lname'] ?? '')),
        'email' => $userInfo['email'] ?? '',
        'contact' => $userInfo['phone'] ?? '',
        'sr_code' => $patientInfo['sr_code'] ?? '',
        'dob' => $profileInfo['dob'] ?? '',
        'blood_type' => $profileInfo['blood_type'] ?? '',
        'address' => $profileInfo['address'] ?? '',
        'course' => $profileInfo['course'] ?? '',
        'year_level' => $profileInfo['year_level'] ?? '',
        'guardian_name' => $profileInfo['guardian_name'] ?? '',
        'guardian_relationship' => $profileInfo['guardian_relationship'] ?? '',
        'guardian_contact' => $profileInfo['guardian_contact'] ?? '',
        'allergies' => $profileInfo['allergies'] ?? '',
        'medical_conditions' => $profileInfo['medical_conditions'] ?? '',
        'profile_photo' => $profileInfo['profile_photo'] ?? null,
        'gender' => $patientInfo['sex'] ?? $userInfo['sex'] ?? ''
    ];
    
} catch (PDOException $e) {
    error_log("Error fetching profile: " . $e->getMessage());
    // Set default values if error
    $studentProfile = [
        'first_name' => $_SESSION['fname'] ?? 'Student',
        'middle_name' => $_SESSION['mname'] ?? '',
        'last_name' => $_SESSION['lname'] ?? '',
        'full_name' => $_SESSION['username'] ?? 'Student',
        'email' => $_SESSION['email'] ?? '',
        'contact' => '',
        'sr_code' => '',
        'dob' => '',
        'blood_type' => '',
        'address' => '',
        'course' => '',
        'year_level' => '',
        'guardian_name' => '',
        'guardian_relationship' => '',
        'guardian_contact' => '',
        'allergies' => '',
        'medical_conditions' => '',
        'profile_photo' => null,
        'gender' => $_SESSION['sex'] ?? ''
    ];
}

// Define fullName for sidebar
$fullName = trim(($studentProfile['first_name'] ?? '') . ' ' . ($studentProfile['last_name'] ?? ''));

// Fetch statistics
$stats = [
    'totalVisits' => 0
];

try {
    // Count appointments as visits
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch();
    $stats['totalVisits'] = $result['count'] ?? 0;
} catch (PDOException $e) {
    error_log("Error fetching stats: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Profile Dashboard</title>

  <!-- CSS LINKS -->
  <link href="css/nav.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Text:ital@0;1&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link href="../admin/css/notifications.css" rel="stylesheet" />
  <link href="css/responsive.css" rel="stylesheet" />
  <link href="css/profile.css" rel="stylesheet" />
</head>

<body>
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
    <!-- ADD MOBILE MENU ICON FIRST -->
    <div class="mobile-menu-icon" id="mobileMenuBtn">
      <i class="bi bi-list"></i>
    </div>
    <?php include 'notification_component.php'; ?>
    <div class="logout-icon" id="logoutBtn">
      <i class="bi bi-box-arrow-right"></i>
    </div>
  </div>
</div>

    <!-- ===== MAIN CONTAINER (SIDEBAR + CONTENT) ===== -->
    <div class="main-container">

      <!-- ===== SIDEBAR ===== -->
      <div class="sidebar">
        <a href="../student/student_dashboard.php" class="menu-item">Dashboard</a>
            <a href="../student/profile.php" class="menu-item active">Profile</a>
            <a href="../student/appointment.php" class="menu-item">Appointment</a>
            <a href="../student/records.php" class="menu-item">Health Records</a>
            <a href="../student/settings.php" class="menu-item ">Settings</a>

        <div class="user-profile">
          <div class="avatar"></div>
          <span><?php echo htmlspecialchars($fullName); ?></span>
        </div>
      </div>

      <!-- ===== CONTENT AREA ===== -->
      <div class="content-demo">
        <!-- Profile Header -->
        <div class="profile-header">
          <div class="header-top">
            <button id="editProfileBtn" class="btn btn-danger">
              <i class="bi bi-pencil"></i> Edit Profile
            </button>
          </div>
        </div>

        <!-- Profile Photo Section -->
        <div class="profile-form-card">
          <div class="profile-photo-section">
            <img id="profilePhoto" src="<?php echo $studentProfile['profile_photo'] ? htmlspecialchars($studentProfile['profile_photo']) : 'https://via.placeholder.com/120'; ?>" alt="Profile Photo" />
            <h3 id="fullName"><?php echo strtoupper(htmlspecialchars($studentProfile['full_name'])); ?></h3>
          </div>
        </div>

        <!-- Personal Information Form -->
        <div class="profile-form-card">
          <h5 class="form-section-title">Personal Information</h5>
          <div class="row g-3">
            <div class="col-12 col-sm-6 col-md-4 col-lg-4 col-xl-4">
              <div class="info-item">
                <label>First Name</label>
                <p><?php echo htmlspecialchars($studentProfile['first_name'] ?: 'N/A'); ?></p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-4 col-xl-4">
              <div class="info-item">
                <label>Middle Name</label>
                <p><?php echo htmlspecialchars($studentProfile['middle_name'] ?: 'N/A'); ?></p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-4 col-xl-4">
              <div class="info-item">
                <label>Last Name</label>
                <p><?php echo htmlspecialchars($studentProfile['last_name'] ?: 'N/A'); ?></p>
              </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4 col-lg-4 col-xl-4">
              <div class="info-item">
                <label>Date of Birth</label>
                <p><?php 
                  if (!empty($studentProfile['dob'])) {
                    $date = new DateTime($studentProfile['dob']);
                    echo $date->format('F d, Y');
                  } else {
                    echo 'N/A';
                  }
                ?></p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-4 col-xl-4">
              <div class="info-item">
                <label>Age</label>
                <p><?php 
                  if (!empty($studentProfile['dob'])) {
                    $birthDate = new DateTime($studentProfile['dob']);
                    $today = new DateTime();
                    $age = $today->diff($birthDate)->y;
                    echo $age;
                  } else {
                    echo 'N/A';
                  }
                ?></p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-4 col-xl-4">
              <div class="info-item">
                <label>Gender</label>
                <p><?php echo htmlspecialchars($studentProfile['gender'] ?: 'N/A'); ?></p>
              </div>
            </div>
            
            <div class="col-12 col-sm-6 col-md-4 col-lg-4 col-xl-4">
              <div class="info-item">
                <label>Contact Number</label>
                <p><?php echo htmlspecialchars($studentProfile['contact'] ?: 'N/A'); ?></p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-4 col-xl-4">
              <div class="info-item">
                <label>SR-Code</label>
                <p><?php echo htmlspecialchars($studentProfile['sr_code'] ?: 'N/A'); ?></p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-4 col-xl-4">
              <div class="info-item">
                <label>Blood Type</label>
                <p><?php echo htmlspecialchars($studentProfile['blood_type'] ?: 'N/A'); ?></p>
              </div>
            </div>
            
            <div class="col-12 col-md-8 col-lg-8 col-xl-8">
              <div class="info-item">
                <label>Address</label>
                <p><?php echo htmlspecialchars($studentProfile['address'] ?: 'N/A'); ?></p>
              </div>
            </div>
            <div class="col-12 col-md-4 col-lg-4 col-xl-4">
              <div class="info-item">
                <label>Email Address</label>
                <p><?php echo htmlspecialchars($studentProfile['email'] ?: 'N/A'); ?></p>
              </div>
            </div>
          </div>
        </div>

        <!-- Academic Information Form -->
        <div class="profile-form-card">
          <h5 class="form-section-title">Academic Information</h5>
          <div class="row g-3">
            <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6">
              <div class="info-item">
                <label>Course</label>
                <p><?php echo htmlspecialchars($studentProfile['course'] ?: 'N/A'); ?></p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6">
              <div class="info-item">
                <label>Year Level</label>
                <p><?php echo htmlspecialchars($studentProfile['year_level'] ?: 'N/A'); ?></p>
              </div>
            </div>
          </div>
        </div>

        <!-- Medical Information Form -->
        <div class="profile-form-card">
          <h5 class="form-section-title">Medical Information</h5>
          <div class="row g-3">
            <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6">
              <div class="info-item">
                <label>Allergies</label>
                <p><?php echo htmlspecialchars($studentProfile['allergies'] ?: 'None'); ?></p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6">
              <div class="info-item">
                <label>Medical Conditions</label>
                <p><?php echo htmlspecialchars($studentProfile['medical_conditions'] ?: 'None'); ?></p>
              </div>
            </div>
          </div>
        </div>

        <!-- Emergency Contact Form -->
        <div class="profile-form-card">
          <h5 class="form-section-title">Emergency Contact Information</h5>
          <div class="row g-3">
            <div class="col-12 col-sm-6 col-md-4 col-lg-4 col-xl-4">
              <div class="info-item">
                <label>Guardian Name</label>
                <p><?php echo htmlspecialchars($studentProfile['guardian_name'] ?: 'N/A'); ?></p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-4 col-xl-4">
              <div class="info-item">
                <label>Relationship</label>
                <p><?php echo htmlspecialchars($studentProfile['guardian_relationship'] ?: 'N/A'); ?></p>
              </div>
            </div>
            <div class="col-12 col-sm-6 col-md-4 col-lg-4 col-xl-4">
              <div class="info-item">
                <label>Contact Number</label>
                <p><?php echo htmlspecialchars($studentProfile['guardian_contact'] ?: 'N/A'); ?></p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

  <!-- ======= Edit Profile Modal ======= -->
  <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="editProfileLabel">Edit Profile</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>

        <form id="profileForm" enctype="multipart/form-data">
          <div class="modal-body">
            <!-- Photo Upload -->
            <div class="text-center mb-4">
              <div class="photo-upload-area">
                <img id="previewPhoto" src="<?php echo $studentProfile['profile_photo'] ? htmlspecialchars($studentProfile['profile_photo']) : 'https://via.placeholder.com/120'; ?>" class="rounded-circle border border-danger mb-2" width="120" height="120" alt="Preview" />
                <input type="file" id="photoInput" name="profile_photo" class="form-control mt-2" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" />
                <div class="photo-upload-info">
                  <p class="mb-1">Max file size: <span class="file-size">5MB</span></p>
                  <p class="mb-0">Allowed formats: JPG, PNG, GIF, WEBP</p>
                </div>
                <div class="upload-progress" id="uploadProgress">
                  <div class="upload-progress-bar" id="uploadProgressBar"></div>
                </div>
                <div class="file-error-message" id="fileErrorMessage"></div>
                <div class="file-success-message" id="fileSuccessMessage"></div>
              </div>
            </div>

            <!-- Personal Information -->
            <div class="info-section">
              <h6 class="section-title">Personal Information</h6>
              <div class="row g-3">
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                  <input type="text" id="firstName" name="first_name" class="form-control" placeholder="First Name" value="<?php echo htmlspecialchars($studentProfile['first_name']); ?>" required />
                </div>
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="middleName" class="form-label">Middle Name</label>
                  <input type="text" id="middleName" name="middle_name" class="form-control" placeholder="Middle Name" value="<?php echo htmlspecialchars($studentProfile['middle_name']); ?>" />
                </div>
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="lastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                  <input type="text" id="lastName" name="last_name" class="form-control" placeholder="Last Name" value="<?php echo htmlspecialchars($studentProfile['last_name']); ?>" required />
                </div>
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                  <input type="date" id="dob" name="dob" class="form-control" value="<?php echo htmlspecialchars($studentProfile['dob']); ?>" required />
                </div>
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="gender" class="form-label">Gender <span class="text-danger">*</span></label>
                  <select id="gender" name="gender" class="form-select" required>
                    <option value="">Select Gender</option>
                    <option value="Male" <?php echo $studentProfile['gender'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                    <option value="Female" <?php echo $studentProfile['gender'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                    <option value="Other" <?php echo $studentProfile['gender'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                  </select>
                </div>
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="contact" class="form-label">Contact Number <span class="text-danger">*</span></label>
                  <input type="text" id="contact" name="contact" class="form-control" placeholder="Contact Number" value="<?php echo htmlspecialchars($studentProfile['contact']); ?>" required />
                </div>
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="srCode" class="form-label">SR-Code</label>
                  <input type="text" id="srCode" name="sr_code" class="form-control" placeholder="SR-Code" value="<?php echo htmlspecialchars($studentProfile['sr_code']); ?>" />
                </div>
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="bloodType" class="form-label">Blood Type</label>
                  <select id="bloodType" name="blood_type" class="form-select">
                    <option value="">Select Blood Type</option>
                    <option value="O+" <?php echo $studentProfile['blood_type'] === 'O+' ? 'selected' : ''; ?>>O+</option>
                    <option value="O-" <?php echo $studentProfile['blood_type'] === 'O-' ? 'selected' : ''; ?>>O-</option>
                    <option value="A+" <?php echo $studentProfile['blood_type'] === 'A+' ? 'selected' : ''; ?>>A+</option>
                    <option value="A-" <?php echo $studentProfile['blood_type'] === 'A-' ? 'selected' : ''; ?>>A-</option>
                    <option value="B+" <?php echo $studentProfile['blood_type'] === 'B+' ? 'selected' : ''; ?>>B+</option>
                    <option value="B-" <?php echo $studentProfile['blood_type'] === 'B-' ? 'selected' : ''; ?>>B-</option>
                    <option value="AB+" <?php echo $studentProfile['blood_type'] === 'AB+' ? 'selected' : ''; ?>>AB+</option>
                    <option value="AB-" <?php echo $studentProfile['blood_type'] === 'AB-' ? 'selected' : ''; ?>>AB-</option>
                  </select>
                </div>
                <div class="col-12">
                  <label for="address" class="form-label">Address <span class="text-danger">*</span></label>
                  <input type="text" id="address" name="address" class="form-control" placeholder="Enter address" value="<?php echo htmlspecialchars($studentProfile['address']); ?>" required />
                </div>
                <div class="col-12">
                  <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                  <input type="email" id="email" name="email" class="form-control" placeholder="Email" value="<?php echo htmlspecialchars($studentProfile['email']); ?>" required />
                </div>
              </div>
            </div>

            <!-- Academic Information -->
            <div class="info-section mt-4">
              <h6 class="section-title">Academic Information</h6>
              <div class="row g-3">
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="course" class="form-label">Course</label>
                  <input type="text" id="course" name="course" class="form-control" placeholder="Course" value="<?php echo htmlspecialchars($studentProfile['course']); ?>" />
                </div>
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="yearLevel" class="form-label">Year Level</label>
                  <select id="yearLevel" name="year_level" class="form-select">
                    <option value="">Select Year Level</option>
                    <option value="1st Year" <?php echo $studentProfile['year_level'] === '1st Year' ? 'selected' : ''; ?>>1st Year</option>
                    <option value="2nd Year" <?php echo $studentProfile['year_level'] === '2nd Year' ? 'selected' : ''; ?>>2nd Year</option>
                    <option value="3rd Year" <?php echo $studentProfile['year_level'] === '3rd Year' ? 'selected' : ''; ?>>3rd Year</option>
                    <option value="4th Year" <?php echo $studentProfile['year_level'] === '4th Year' ? 'selected' : ''; ?>>4th Year</option>
                  </select>
                </div>
              </div>
            </div>

            <!-- Medical Information -->
            <div class="info-section mt-4">
              <h6 class="section-title">Medical Information</h6>
              <div class="row g-3">
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="allergies" class="form-label">Allergies</label>
                  <input type="text" id="allergies" name="allergies" class="form-control" placeholder="Enter allergies (e.g., food, medication)" value="<?php echo htmlspecialchars($studentProfile['allergies']); ?>" />
                </div>
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="medicalConditions" class="form-label">Medical Conditions</label>
                  <input type="text" id="medicalConditions" name="medical_conditions" class="form-control" placeholder="Enter medical conditions" value="<?php echo htmlspecialchars($studentProfile['medical_conditions']); ?>" />
                </div>
              </div>
            </div>

            <!-- Emergency Contact -->
            <div class="info-section mt-4">
              <h6 class="section-title">Emergency Contact Information</h6>
              <div class="row g-3">
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="guardianName" class="form-label">Guardian Name</label>
                  <input type="text" id="guardianName" name="guardian_name" class="form-control" placeholder="Guardian's full name" value="<?php echo htmlspecialchars($studentProfile['guardian_name']); ?>" />
                </div>
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="relationship" class="form-label">Relationship</label>
                  <select id="relationship" name="relationship" class="form-select">
                    <option value="">Select Relationship</option>
                    <option value="Mother" <?php echo $studentProfile['guardian_relationship'] === 'Mother' ? 'selected' : ''; ?>>Mother</option>
                    <option value="Father" <?php echo $studentProfile['guardian_relationship'] === 'Father' ? 'selected' : ''; ?>>Father</option>
                    <option value="Guardian" <?php echo $studentProfile['guardian_relationship'] === 'Guardian' ? 'selected' : ''; ?>>Guardian</option>
                    <option value="Sibling" <?php echo $studentProfile['guardian_relationship'] === 'Sibling' ? 'selected' : ''; ?>>Sibling</option>
                  </select>
                </div>
                <div class="col-12 col-sm-6 col-md-6 col-lg-6 col-xl-6 col-xxl-6">
                  <label for="guardianContact" class="form-label">Contact Number</label>
                  <input type="text" id="guardianContact" name="guardian_contact" class="form-control" placeholder="Guardian's contact" value="<?php echo htmlspecialchars($studentProfile['guardian_contact']); ?>" />
                </div>
              </div>
            </div>
          </div>

          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger" id="saveChanges">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ===== JS ===== -->
  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="js/notifications.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script>
  <script>
    // ===== OPEN EDIT MODAL =====
    document.getElementById('editProfileBtn').addEventListener('click', () => {
      const modal = new bootstrap.Modal(document.getElementById('editProfileModal'));
      modal.show();
    });

    // ===== PHOTO UPLOAD PREVIEW WITH VALIDATION =====
    document.getElementById('photoInput').addEventListener('change', function() {
      const file = this.files[0];
      const errorMessage = document.getElementById('fileErrorMessage');
      const successMessage = document.getElementById('fileSuccessMessage');
      const previewPhoto = document.getElementById('previewPhoto');
      
      // Hide previous messages
      errorMessage.classList.remove('show');
      successMessage.classList.remove('show');
      
      if (!file) {
        return;
      }
      
      // Validate file size (5MB max)
      const maxSize = 5 * 1024 * 1024; // 5MB in bytes
      if (file.size > maxSize) {
        errorMessage.textContent = 'File size exceeds 5MB limit. Please choose a smaller image.';
        errorMessage.classList.add('show');
        this.value = ''; // Clear the input
        return;
      }
      
      // Validate file type
      const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
      if (!allowedTypes.includes(file.type)) {
        errorMessage.textContent = 'Invalid file type. Please upload a JPG, PNG, GIF, or WEBP image.';
        errorMessage.classList.add('show');
        this.value = ''; // Clear the input
        return;
      }
      
      // Validate image dimensions (optional - max 2000x2000)
      const img = new Image();
      const reader = new FileReader();
      
      reader.onload = function(e) {
        img.onload = function() {
          const maxDimension = 2000;
          if (img.width > maxDimension || img.height > maxDimension) {
            errorMessage.textContent = `Image dimensions (${img.width}x${img.height}) exceed maximum of ${maxDimension}x${maxDimension} pixels.`;
            errorMessage.classList.add('show');
            document.getElementById('photoInput').value = '';
            return;
          }
          
          // Show preview
          previewPhoto.src = e.target.result;
          successMessage.textContent = `Image selected: ${file.name} (${(file.size / 1024).toFixed(2)} KB)`;
          successMessage.classList.add('show');
        };
        img.src = e.target.result;
      };
      
      reader.onerror = function() {
        errorMessage.textContent = 'Error reading file. Please try again.';
        errorMessage.classList.add('show');
      };
      
      reader.readAsDataURL(file);
    });

    // ===== SAVE CHANGES VIA AJAX =====
    document.getElementById('profileForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const formData = new FormData(this);
      formData.append('update_profile', '1');
      
      // Show upload progress if file is being uploaded
      const photoInput = document.getElementById('photoInput');
      const uploadProgress = document.getElementById('uploadProgress');
      const uploadProgressBar = document.getElementById('uploadProgressBar');
      
      if (photoInput.files.length > 0) {
        uploadProgress.classList.add('active');
        uploadProgressBar.style.width = '0%';
        
        // Simulate progress (since we can't track actual upload progress with fetch)
        let progress = 0;
        const progressInterval = setInterval(() => {
          progress += 10;
          if (progress <= 90) {
            uploadProgressBar.style.width = progress + '%';
          }
        }, 100);
      }
      
      try {
        const response = await fetch('profile.php', {
          method: 'POST',
          body: formData
        });
        
        // Complete progress bar
        if (uploadProgress.classList.contains('active')) {
          uploadProgressBar.style.width = '100%';
        }
        
        const result = await response.json();
        
        // Hide progress bar
        setTimeout(() => {
          uploadProgress.classList.remove('active');
          uploadProgressBar.style.width = '0%';
        }, 500);
        
        if (result.success) {
          const modal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
          modal.hide();
          
          // Show appropriate message based on photo upload status
          let message = result.message;
          if (result.upload_error) {
            message = result.message + '\n\nNote: ' + result.upload_error;
          }
          
          Swal.fire({
            icon: 'success',
            title: 'Profile Updated!',
            text: message,
            confirmButtonColor: '#800000'
          }).then(() => {
            location.reload(); // Reload to show updated data
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: result.message
          });
        }
      } catch (error) {
        console.error('Error:', error);
        
        // Hide progress bar on error
        uploadProgress.classList.remove('active');
        uploadProgressBar.style.width = '0%';
        
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'An error occurred. Please try again.'
        });
      }
    });
    
    // Initialize notification system
    if (window.StudentNotificationSystem) {
      StudentNotificationSystem.init();
    }
       
        // Initialize notification system
        if (window.StudentNotificationSystem) {
          StudentNotificationSystem.init();
        }
        // ==========================================
// MOBILE MENU TOGGLE SCRIPT
// ==========================================
document.addEventListener('DOMContentLoaded', () => {
  const mobileMenuBtn = document.getElementById('mobileMenuBtn');
  const sidebar = document.querySelector('.sidebar');
  const body = document.body;
  
  if (mobileMenuBtn && sidebar) {
    // Create overlay element for better click handling
    const overlay = document.createElement('div');
    overlay.className = 'menu-overlay';
    overlay.style.cssText = 'display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.55); backdrop-filter: blur(2px); z-index: 998; cursor: pointer;';
    document.body.appendChild(overlay);
    
    // Toggle menu on button click
    mobileMenuBtn.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      const isActive = sidebar.classList.toggle('active');
      body.classList.toggle('menu-open');
      overlay.style.display = isActive ? 'block' : 'none';
      
      // Change icon
      const icon = mobileMenuBtn.querySelector('i');
      if (icon) {
        if (isActive) {
          icon.classList.remove('bi-list');
          icon.classList.add('bi-x-lg');
        } else {
          icon.classList.remove('bi-x-lg');
          icon.classList.add('bi-list');
        }
      }
    });
    
    // Close menu when clicking overlay
    overlay.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      closeMobileMenu();
    });
    
    // Close menu when clicking outside (fallback)
    document.addEventListener('click', (e) => {
      if (sidebar.classList.contains('active') && 
          !sidebar.contains(e.target) && 
          !mobileMenuBtn.contains(e.target) &&
          !overlay.contains(e.target)) {
        closeMobileMenu();
      }
    });
    
    // Close menu when clicking menu items
    document.querySelectorAll('.sidebar .menu-item').forEach(item => {
      item.addEventListener('click', () => {
        closeMobileMenu();
      });
    });
    
    // Function to close mobile menu
    function closeMobileMenu() {
      sidebar.classList.remove('active');
      body.classList.remove('menu-open');
      overlay.style.display = 'none';
      const icon = mobileMenuBtn.querySelector('i');
      if (icon) {
        icon.classList.remove('bi-x-lg');
        icon.classList.add('bi-list');
      }
    }
    
    // Prevent clicks inside sidebar from closing it
    sidebar.addEventListener('click', (e) => {
      e.stopPropagation();
    });
    
    // Close menu on escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && sidebar.classList.contains('active')) {
        closeMobileMenu();
      }
    });
  }
});
  </script>
</body>
</html>
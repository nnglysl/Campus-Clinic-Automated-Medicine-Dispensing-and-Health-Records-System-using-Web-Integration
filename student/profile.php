<?php
require_once '../config/database.php';
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
        $dob = $_POST['dob'] ?? '';
        $bloodType = $_POST['blood_type'] ?? '';
        $address = $_POST['address'] ?? '';
        
        // Academic Information
        $course = $_POST['course'] ?? '';
        $yearLevel = $_POST['year_level'] ?? '';
        
        // Emergency Contact
        $guardianName = $_POST['guardian_name'] ?? '';
        $relationship = $_POST['relationship'] ?? '';
        $guardianContact = $_POST['guardian_contact'] ?? '';
        
        // Handle photo upload
        $photoPath = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/profiles/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (in_array(strtolower($fileExtension), $allowedTypes)) {
                $fileName = 'profile_' . $user['id'] . '_' . time() . '.' . $fileExtension;
                $fullPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $fullPath)) {
                    // Store relative path from the student folder
                    $photoPath = '../uploads/profiles/' . $fileName;
                } else {
                    $photoPath = null;
                }
            }
        }
        
        // Update users table (basic info)
        $sql = "UPDATE users SET 
                fname = ?,
                mname = ?,
                lname = ?,
                email = ?,
                phone = ?
                WHERE id = ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$firstName, $middleName, $lastName, $email, $contact, $user['id']]);
        
        // Update or insert into user_profiles table (extended info)
        if ($photoPath) {
            // If photo was uploaded, include it in the query
            $profileSql = "INSERT INTO user_profiles 
                          (user_id, dob, blood_type, address, course, year_level, 
                           guardian_name, guardian_relationship, guardian_contact, profile_photo, updated_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                          ON DUPLICATE KEY UPDATE
                          dob = VALUES(dob),
                          blood_type = VALUES(blood_type),
                          address = VALUES(address),
                          course = VALUES(course),
                          year_level = VALUES(year_level),
                          guardian_name = VALUES(guardian_name),
                          guardian_relationship = VALUES(guardian_relationship),
                          guardian_contact = VALUES(guardian_contact),
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
                $photoPath
            ];
        } else {
            // If no photo uploaded, don't update photo field
            $profileSql = "INSERT INTO user_profiles 
                          (user_id, dob, blood_type, address, course, year_level, 
                           guardian_name, guardian_relationship, guardian_contact, updated_at)
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                          ON DUPLICATE KEY UPDATE
                          dob = VALUES(dob),
                          blood_type = VALUES(blood_type),
                          address = VALUES(address),
                          course = VALUES(course),
                          year_level = VALUES(year_level),
                          guardian_name = VALUES(guardian_name),
                          guardian_relationship = VALUES(guardian_relationship),
                          guardian_contact = VALUES(guardian_contact)";
            
            $profileParams = [
                $user['id'],
                $dob,
                $bloodType,
                $address,
                $course,
                $yearLevel,
                $guardianName,
                $relationship,
                $guardianContact
            ];
        }
        
        $stmt = $pdo->prepare($profileSql);
        $stmt->execute($profileParams);
        
        // Update session variables
        $_SESSION['fname'] = $firstName;
        $_SESSION['lname'] = $lastName;
        $_SESSION['email'] = $email;
        $_SESSION['username'] = trim($firstName . ' ' . $lastName);
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
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
    
    // Combine data
    $studentProfile = [
        'first_name' => $userInfo['fname'] ?? '',
        'middle_name' => $userInfo['mname'] ?? '',
        'last_name' => $userInfo['lname'] ?? '',
        'full_name' => trim(($userInfo['fname'] ?? '') . ' ' . ($userInfo['mname'] ?? '') . ' ' . ($userInfo['lname'] ?? '')),
        'email' => $userInfo['email'] ?? '',
        'contact' => $userInfo['phone'] ?? '',
        'sr_code' => $userInfo['student_id'] ?? 'N/A',
        'dob' => $profileInfo['dob'] ?? '',
        'blood_type' => $profileInfo['blood_type'] ?? '',
        'address' => $profileInfo['address'] ?? '',
        'course' => $profileInfo['course'] ?? '',
        'year_level' => $profileInfo['year_level'] ?? '',
        'guardian_name' => $profileInfo['guardian_name'] ?? '',
        'guardian_relationship' => $profileInfo['guardian_relationship'] ?? '',
        'guardian_contact' => $profileInfo['guardian_contact'] ?? '',
        'profile_photo' => $profileInfo['profile_photo'] ?? null
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
        'sr_code' => 'N/A',
        'dob' => '',
        'blood_type' => '',
        'address' => '',
        'course' => '',
        'year_level' => '',
        'guardian_name' => '',
        'guardian_relationship' => '',
        'guardian_contact' => '',
        'profile_photo' => null
    ];
}

// Fetch statistics
$stats = [
    'totalVisits' => 0,
    'prescriptions' => 0
];

try {
    // Count appointments as visits
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM appointments WHERE user_id = ? AND status = 'completed'");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch();
    $stats['totalVisits'] = $result['count'] ?? 0;
    
    // Count prescriptions if medical_records table exists
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM medical_records WHERE patient_id = ? AND medication IS NOT NULL");
    $stmt->execute([$user['id']]);
    $result = $stmt->fetch();
    $stats['prescriptions'] = $result['count'] ?? 0;
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
  <link href="css/profile.css" rel="stylesheet" />
  <link href="css/nav.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Text:ital@0;1&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
</head>

<body>
    <!-- ===== HEADER ===== -->
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

    <!-- ===== MAIN CONTAINER (SIDEBAR + CONTENT) ===== -->
    <div class="main-container">

      <!-- ===== SIDEBAR ===== -->
      <div class="sidebar">
        <a href="student_dashboard.php" class="menu-item">Dashboard</a>
        <a href="profile.php" class="menu-item active">Profile</a>
        <a href="appointment.php" class="menu-item">Appointment</a>
        <a href="records.php" class="menu-item">Health Records</a>
        <a href="settings.php" class="menu-item">Settings</a>
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
          <form>
            <div class="row g-3">
              <div class="col-md-4">
                <label class="form-label">First Name</label>
                <input type="text" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['first_name']); ?>" readonly>
              </div>
              <div class="col-md-4">
                <label class="form-label">Middle Name</label>
                <input type="text" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['middle_name']); ?>" readonly>
              </div>
              <div class="col-md-4">
                <label class="form-label">Last Name</label>
                <input type="text" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['last_name']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email Address</label>
                <input type="email" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['email']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Contact Number</label>
                <input type="text" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['contact']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Date of Birth</label>
                <input type="text" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['dob']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Blood Type</label>
                <input type="text" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['blood_type']); ?>" readonly>
              </div>
              <div class="col-12">
                <label class="form-label">Address</label>
                <textarea class="form-control readonly-form" rows="2" readonly><?php echo htmlspecialchars($studentProfile['address']); ?></textarea>
              </div>
            </div>
          </form>
        </div>

        <!-- Academic Information Form -->
        <div class="profile-form-card">
          <h5 class="form-section-title">Academic Information</h5>
          <form>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Course</label>
                <input type="text" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['course']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Year Level</label>
                <input type="text" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['year_level']); ?>" readonly>
              </div>
            </div>
          </form>
        </div>

        <!-- Emergency Contact Form -->
        <div class="profile-form-card">
          <h5 class="form-section-title">Emergency Contact Information</h5>
          <form>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Guardian Name</label>
                <input type="text" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['guardian_name']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Relationship</label>
                <input type="text" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['guardian_relationship']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Contact Number</label>
                <input type="text" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['guardian_contact']); ?>" readonly>
              </div>
            </div>
          </form>
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
              <img id="previewPhoto" src="<?php echo $studentProfile['profile_photo'] ? htmlspecialchars($studentProfile['profile_photo']) : 'https://via.placeholder.com/120'; ?>" class="rounded-circle border border-danger mb-2" width="120" height="120" alt="Preview" />
              <input type="file" id="photoInput" name="profile_photo" class="form-control mt-2" accept="image/*" />
            </div>

            <!-- Personal Information -->
            <div class="info-section">
              <h6 class="section-title">Personal Information</h6>
              <div class="row g-3">
                <div class="col-md-4">
                  <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                  <input type="text" id="firstName" name="first_name" class="form-control" placeholder="First Name" value="<?php echo htmlspecialchars($studentProfile['first_name']); ?>" required />
                </div>
                <div class="col-md-4">
                  <label for="middleName" class="form-label">Middle Name</label>
                  <input type="text" id="middleName" name="middle_name" class="form-control" placeholder="Middle Name" value="<?php echo htmlspecialchars($studentProfile['middle_name']); ?>" />
                </div>
                <div class="col-md-4">
                  <label for="lastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                  <input type="text" id="lastName" name="last_name" class="form-control" placeholder="Last Name" value="<?php echo htmlspecialchars($studentProfile['last_name']); ?>" required />
                </div>
                <div class="col-md-6">
                  <label for="email" class="form-label">Email Address <span class="text-danger">*</span></label>
                  <input type="email" id="email" name="email" class="form-control" placeholder="Email" value="<?php echo htmlspecialchars($studentProfile['email']); ?>" required />
                </div>
                <div class="col-md-6">
                  <label for="contact" class="form-label">Contact Number <span class="text-danger">*</span></label>
                  <input type="text" id="contact" name="contact" class="form-control" placeholder="Contact Number" value="<?php echo htmlspecialchars($studentProfile['contact']); ?>" required />
                </div>
                <div class="col-md-6">
                  <label for="dob" class="form-label">Date of Birth <span class="text-danger">*</span></label>
                  <input type="date" id="dob" name="dob" class="form-control" value="<?php echo htmlspecialchars($studentProfile['dob']); ?>" required />
                </div>
                <div class="col-md-6">
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
                  <textarea id="address" name="address" class="form-control" rows="2" placeholder="Enter address" required><?php echo htmlspecialchars($studentProfile['address']); ?></textarea>
                </div>
              </div>
            </div>

            <!-- Academic Information -->
            <div class="info-section mt-4">
              <h6 class="section-title">Academic Information</h6>
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="course" class="form-label">Course</label>
                  <input type="text" id="course" name="course" class="form-control" placeholder="Course" value="<?php echo htmlspecialchars($studentProfile['course']); ?>" />
                </div>
                <div class="col-md-6">
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

            <!-- Emergency Contact -->
            <div class="info-section mt-4">
              <h6 class="section-title">Emergency Contact Information</h6>
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="guardianName" class="form-label">Guardian Name</label>
                  <input type="text" id="guardianName" name="guardian_name" class="form-control" placeholder="Guardian's full name" value="<?php echo htmlspecialchars($studentProfile['guardian_name']); ?>" />
                </div>
                <div class="col-md-6">
                  <label for="relationship" class="form-label">Relationship</label>
                  <select id="relationship" name="relationship" class="form-select">
                    <option value="">Select Relationship</option>
                    <option value="Mother" <?php echo $studentProfile['guardian_relationship'] === 'Mother' ? 'selected' : ''; ?>>Mother</option>
                    <option value="Father" <?php echo $studentProfile['guardian_relationship'] === 'Father' ? 'selected' : ''; ?>>Father</option>
                    <option value="Guardian" <?php echo $studentProfile['guardian_relationship'] === 'Guardian' ? 'selected' : ''; ?>>Guardian</option>
                    <option value="Sibling" <?php echo $studentProfile['guardian_relationship'] === 'Sibling' ? 'selected' : ''; ?>>Sibling</option>
                  </select>
                </div>
                <div class="col-md-6">
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
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script>
  <script>
    // ===== OPEN EDIT MODAL =====
    document.getElementById('editProfileBtn').addEventListener('click', () => {
      const modal = new bootstrap.Modal(document.getElementById('editProfileModal'));
      modal.show();
    });

    // ===== PHOTO UPLOAD PREVIEW =====
    document.getElementById('photoInput').addEventListener('change', function() {
      const file = this.files[0];
      if (!file) return;
      
      const reader = new FileReader();
      reader.onload = function(e) {
        document.getElementById('previewPhoto').src = e.target.result;
      };
      reader.readAsDataURL(file);
    });

    // ===== SAVE CHANGES VIA AJAX =====
    document.getElementById('profileForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      
      const formData = new FormData(this);
      formData.append('update_profile', '1');
      
      try {
        const response = await fetch('profile.php', {
          method: 'POST',
          body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
          const modal = bootstrap.Modal.getInstance(document.getElementById('editProfileModal'));
          modal.hide();
          
          Swal.fire({
            icon: 'success',
            title: 'Profile Updated!',
            text: result.message,
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
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: 'An error occurred. Please try again.'
        });
      }
    });
  </script>
</body>
</html>
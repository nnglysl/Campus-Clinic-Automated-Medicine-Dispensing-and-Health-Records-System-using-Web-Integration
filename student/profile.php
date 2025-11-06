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
    'role' => $_SESSION['role'] ?? 'student'
];

$pdo = getDB();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    header('Content-Type: application/json');
    
    try {
        // Personal Information
        $firstName = $_POST['first_name'] ?? '';
        $lastName = $_POST['last_name'] ?? '';
        $email = $_POST['email'] ?? '';
        $contact = $_POST['contact'] ?? '';
        $dob = $_POST['dob'] ?? '';
        $bloodType = $_POST['blood_type'] ?? '';
        $address = $_POST['address'] ?? '';
        
        // Academic Information
        $campus = $_POST['campus'] ?? '';
        $college = $_POST['college'] ?? '';
        $course = $_POST['course'] ?? '';
        $yearLevel = $_POST['year_level'] ?? '';
        
        // Emergency Contact
        $guardianName = $_POST['guardian_name'] ?? '';
        $relationship = $_POST['relationship'] ?? '';
        $guardianContact = $_POST['guardian_contact'] ?? '';
        $guardianEmail = $_POST['guardian_email'] ?? '';
        
        // Handle photo upload
        $photoPath = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = '../uploads/profiles/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            
            $fileExtension = pathinfo($_FILES['profile_photo']['name'], PATHINFO_EXTENSION);
            $fileName = 'profile_' . $user['id'] . '_' . time() . '.' . $fileExtension;
            $photoPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $photoPath)) {
                $photoPath = '/uploads/profiles/' . $fileName;
            } else {
                $photoPath = null;
            }
        }
        
        // Update patients table
        $sql = "UPDATE patients SET 
                full_name = ?,
                email = ?,
                contact = ?,
                dob = ?,
                blood_type = ?,
                address = ?,
                campus = ?,
                college = ?,
                course = ?,
                year_level = ?,
                guardian_name = ?,
                guardian_relationship = ?,
                guardian_contact = ?,
                guardian_email = ?";
        
        $params = [
            $firstName . ' ' . $lastName,
            $email,
            $contact,
            $dob,
            $bloodType,
            $address,
            $campus,
            $college,
            $course,
            $yearLevel,
            $guardianName,
            $relationship,
            $guardianContact,
            $guardianEmail
        ];
        
        if ($photoPath) {
            $sql .= ", profile_photo = ?";
            $params[] = $photoPath;
        }
        
        $sql .= ", updated_at = NOW() WHERE id = ?";
        $params[] = $user['id'];
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        
        // Update session variables
        $_SESSION['fname'] = $firstName;
        $_SESSION['lname'] = $lastName;
        
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
        exit();
        
    } catch (PDOException $e) {
        error_log("Error updating profile: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred. Please try again.']);
        exit();
    }
}

// Fetch student profile data
$studentProfile = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE id = ?");
    $stmt->execute([$user['id']]);
    $studentProfile = $stmt->fetch();
    
    if (!$studentProfile) {
        // Create default profile if not exists
        $studentProfile = [
            'full_name' => 'Student',
            'sr_code' => 'N/A',
            'email' => '',
            'contact' => '',
            'dob' => '',
            'blood_type' => '',
            'address' => '',
            'campus' => '',
            'college' => '',
            'course' => '',
            'year_level' => '',
            'guardian_name' => '',
            'guardian_relationship' => '',
            'guardian_contact' => '',
            'guardian_email' => '',
            'profile_photo' => null
        ];
    }
    
    // Split full name into first and last name
    $nameParts = explode(' ', $studentProfile['full_name']);
    $studentProfile['first_name'] = $nameParts[0] ?? '';
    $studentProfile['last_name'] = isset($nameParts[1]) ? implode(' ', array_slice($nameParts, 1)) : '';
    
} catch (PDOException $e) {
    error_log("Error fetching profile: " . $e->getMessage());
}

// Fetch statistics
$stats = [
    'totalVisits' => 0,
    'prescriptions' => 0
];

try {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM visit_logs WHERE patient_id = ?");
    $stmt->execute([$user['id']]);
    $stats['totalVisits'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM medical_records WHERE patient_id = ? AND medication IS NOT NULL");
    $stmt->execute([$user['id']]);
    $stats['prescriptions'] = $stmt->fetch()['count'];
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
  <link href="../student/css/profile.css" rel="stylesheet" />
  <link href="../student/css/nav.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/remixicon@4.3.0/fonts/remixicon.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Text:ital@0;1&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
</head>

<body>
  <div class="layout">
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
        <div class="logout-icon" id="logoutBtn" onclick="window.location.href='../logout.php'"><i class="bi bi-box-arrow-right"></i></div>
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
        <a href="../student/settings.php" class="menu-item">Settings</a>
      </div>

      <!-- ===== CONTENT AREA ===== -->
      <div class="content-demo">
        <!-- Profile Header -->
        <div class="profile-header">
          <div class="header-top">
            <h2 class="title">Profile Dashboard</h2>
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
            <p class="text-secondary" id="studentId">Student ID: <?php echo htmlspecialchars($studentProfile['sr_code']); ?></p>
          </div>
        </div>

        <!-- Personal Information Form -->
        <div class="profile-form-card">
          <h5 class="form-section-title">Personal Information</h5>
          <form>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">First Name</label>
                <input type="text" id="displayFirstName" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['first_name']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Last Name</label>
                <input type="text" id="displayLastName" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['last_name']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email Address</label>
                <input type="email" id="displayEmail" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['email']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Contact Number</label>
                <input type="text" id="displayContact" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['contact']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Date of Birth</label>
                <input type="text" id="displayDob" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['dob']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Blood Type</label>
                <input type="text" id="displayBloodType" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['blood_type']); ?>" readonly>
              </div>
              <div class="col-12">
                <label class="form-label">Address</label>
                <textarea id="displayAddress" class="form-control readonly-form" rows="2" readonly><?php echo htmlspecialchars($studentProfile['address']); ?></textarea>
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
                <label class="form-label">Campus</label>
                <input type="text" id="displayCampus" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['campus']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">College</label>
                <input type="text" id="displayCollege" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['college']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Course</label>
                <input type="text" id="displayCourse" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['course']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Year Level</label>
                <input type="text" id="displayYearLevel" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['year_level']); ?>" readonly>
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
                <input type="text" id="displayGuardianName" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['guardian_name']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Relationship</label>
                <input type="text" id="displayRelationship" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['guardian_relationship']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Contact Number</label>
                <input type="text" id="displayGuardianContact" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['guardian_contact']); ?>" readonly>
              </div>
              <div class="col-md-6">
                <label class="form-label">Email Address</label>
                <input type="email" id="displayGuardianEmail" class="form-control readonly-form" value="<?php echo htmlspecialchars($studentProfile['guardian_email']); ?>" readonly>
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
                <div class="col-md-6">
                  <label for="firstName" class="form-label">First Name</label>
                  <input type="text" id="firstName" name="first_name" class="form-control" placeholder="First Name" value="<?php echo htmlspecialchars($studentProfile['first_name']); ?>" />
                </div>
                <div class="col-md-6">
                  <label for="lastName" class="form-label">Last Name</label>
                  <input type="text" id="lastName" name="last_name" class="form-control" placeholder="Last Name" value="<?php echo htmlspecialchars($studentProfile['last_name']); ?>" />
                </div>
                <div class="col-md-6">
                  <label for="email" class="form-label">Email Address</label>
                  <input type="email" id="email" name="email" class="form-control" placeholder="Email" value="<?php echo htmlspecialchars($studentProfile['email']); ?>" />
                </div>
                <div class="col-md-6">
                  <label for="contact" class="form-label">Contact Number</label>
                  <input type="text" id="contact" name="contact" class="form-control" placeholder="Contact Number" value="<?php echo htmlspecialchars($studentProfile['contact']); ?>" />
                </div>
                <div class="col-md-6">
                  <label for="dob" class="form-label">Date of Birth</label>
                  <input type="date" id="dob" name="dob" class="form-control" value="<?php echo htmlspecialchars($studentProfile['dob']); ?>" />
                </div>
                <div class="col-md-6">
                  <label for="bloodType" class="form-label">Blood Type</label>
                  <select id="bloodType" name="blood_type" class="form-select">
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
                  <label for="address" class="form-label">Address</label>
                  <textarea id="address" name="address" class="form-control" rows="2" placeholder="Enter address"><?php echo htmlspecialchars($studentProfile['address']); ?></textarea>
                </div>
              </div>
            </div>

            <!-- Academic Information -->
            <div class="info-section mt-4">
              <h6 class="section-title">Academic Information</h6>
              <div class="row g-3">
                <div class="col-md-6">
                  <label for="campus" class="form-label">Campus</label>
                  <input type="text" id="campus" name="campus" class="form-control" placeholder="Campus" value="<?php echo htmlspecialchars($studentProfile['campus']); ?>" />
                </div>
                <div class="col-md-6">
                  <label for="college" class="form-label">College</label>
                  <input type="text" id="college" name="college" class="form-control" placeholder="College" value="<?php echo htmlspecialchars($studentProfile['college']); ?>" />
                </div>
                <div class="col-md-6">
                  <label for="course" class="form-label">Course</label>
                  <input type="text" id="course" name="course" class="form-control" placeholder="Course" value="<?php echo htmlspecialchars($studentProfile['course']); ?>" />
                </div>
                <div class="col-md-6">
                  <label for="yearLevel" class="form-label">Year Level</label>
                  <select id="yearLevel" name="year_level" class="form-select">
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
                <div class="col-md-6">
                  <label for="guardianEmail" class="form-label">Email Address</label>
                  <input type="email" id="guardianEmail" name="guardian_email" class="form-control" placeholder="Guardian's email" value="<?php echo htmlspecialchars($studentProfile['guardian_email']); ?>" />
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
  <script>
    // Profile data from PHP
    const profileData = <?php echo json_encode($studentProfile, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

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
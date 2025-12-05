<?php
/**
 * Get patient by user_id
 * Used to fetch patient details from appointments page
 */

require_once '../config/database.php';
session_start();

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$user_id = $_GET['user_id'] ?? null;
$fname = $_GET['fname'] ?? null; // Optional: from appointment data
$lname = $_GET['lname'] ?? null; // Optional: from appointment data

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit();
}

try {
    $pdo = getDB();
    
    // Get patient by user_id (patients.user_id = users.id)
    $stmt = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($patient) {
        echo json_encode([
            'success' => true,
            'patient' => $patient
        ]);
    } else {
        // Patient record doesn't exist - try to get user data and create patient record
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Create patient record automatically
            $fname = $user['fname'] ?? 'Patient';
            $lname = $user['lname'] ?? 'User' . $user_id;
            $mname = $user['mname'] ?? null;
            $full_name = trim($fname . ' ' . ($mname ? $mname . ' ' : '') . $lname);
            $email = $user['email'] ?? 'patient' . $user_id . '@appointment.temp';
            $sr_code = 'SR-' . date('Y') . '-' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
            
            // Check if SR code already exists
            $checkStmt = $pdo->prepare("SELECT id FROM patients WHERE sr_code = ?");
            $checkStmt->execute([$sr_code]);
            if ($checkStmt->fetch()) {
                // SR code exists, append timestamp
                $sr_code = 'SR-' . date('Y') . '-' . str_pad($user_id, 5, '0', STR_PAD_LEFT) . '-' . time();
            }
            
            try {
                // Calculate age from date_of_birth if available
                $age = null;
                if (!empty($user['date_of_birth']) && $user['date_of_birth'] != '0000-00-00') {
                    try {
                        $birthDate = new DateTime($user['date_of_birth']);
                        $today = new DateTime();
                        $age = $today->diff($birthDate)->y;
                    } catch (Exception $e) {
                        error_log("Error calculating age: " . $e->getMessage());
                    }
                }
                
                $insertStmt = $pdo->prepare("
                    INSERT INTO patients (user_id, sr_code, fname, mname, lname, full_name, email, contact_number, date_of_birth, age, address, program) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insertStmt->execute([
                    $user_id,
                    $sr_code,
                    $fname,
                    $mname,
                    $lname,
                    $full_name,
                    $email,
                    $user['phone'] ?? null,
                    $user['date_of_birth'] ?? null,
                    $age,
                    $user['address'] ?? null,
                    $user['position'] ?? 'Student'
                ]);
            } catch (PDOException $e) {
                error_log("Error creating patient record for user {$user_id}: " . $e->getMessage());
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create patient record: ' . $e->getMessage()
                ]);
                exit();
            }
            
            // Get the newly created patient record
            $stmt = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $patient = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($patient) {
                echo json_encode([
                    'success' => true,
                    'patient' => $patient,
                    'message' => 'Patient record created automatically'
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'message' => 'Failed to create patient record'
                ]);
            }
        } else {
            // User doesn't exist - this can happen if user was deleted but appointment remains
            // Try to create a patient record using appointment data (fname, lname) if available
            error_log("Warning: User ID {$user_id} not found in users table. Attempting to create patient record with appointment data.");
            
            // Use appointment data if available, otherwise use default
            $fname_final = !empty($fname) ? trim($fname) : 'Patient';
            $lname_final = !empty($lname) ? trim($lname) : 'User' . $user_id;
            $full_name = trim($fname_final . ' ' . $lname_final);
            $sr_code = 'SR-' . date('Y') . '-' . str_pad($user_id, 5, '0', STR_PAD_LEFT);
            
            // Check if SR code already exists
            $checkStmt = $pdo->prepare("SELECT id FROM patients WHERE sr_code = ?");
            $checkStmt->execute([$sr_code]);
            if ($checkStmt->fetch()) {
                // SR code exists, append timestamp
                $sr_code = 'SR-' . date('Y') . '-' . str_pad($user_id, 5, '0', STR_PAD_LEFT) . '-' . time();
            }
            
            // Email is required - create a placeholder email from appointment data
            $email = !empty($fname) && !empty($lname) 
                ? strtolower(str_replace(' ', '.', $fname_final . '.' . $lname_final)) . '@appointment.temp' 
                : 'patient' . $user_id . '@appointment.temp';
            
            // Check if patient already exists with this user_id (even if user doesn't exist)
            $checkPatientStmt = $pdo->prepare("SELECT * FROM patients WHERE user_id = ?");
            $checkPatientStmt->execute([$user_id]);
            $existing_patient = $checkPatientStmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing_patient) {
                // Patient record already exists - return it
                echo json_encode([
                    'success' => true,
                    'patient' => $existing_patient,
                    'message' => 'Patient record found (user record missing)'
                ]);
            } else {
                // Try to create new patient record
                try {
                    // Age will be NULL since we don't have date_of_birth in this case
                    $insertStmt = $pdo->prepare("
                        INSERT INTO patients (user_id, sr_code, fname, lname, full_name, email, contact_number, address, program, age) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NULL)
                    ");
                    $insertStmt->execute([
                        $user_id, // Set user_id even if user doesn't exist - maintains link for future sync
                        $sr_code,
                        $fname_final,
                        $lname_final,
                        $full_name,
                        $email,
                        null,
                        null,
                        'Student'
                    ]);
                    
                    // Get the newly created patient record
                    $stmt = $pdo->prepare("SELECT * FROM patients WHERE sr_code = ?");
                    $stmt->execute([$sr_code]);
                    $patient = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($patient) {
                        echo json_encode([
                            'success' => true,
                            'patient' => $patient,
                            'message' => 'Patient record created from appointment data'
                        ]);
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Failed to create patient record - record not found after insert'
                        ]);
                    }
                } catch (PDOException $e) {
                    error_log("Error creating patient record for missing user: " . $e->getMessage());
                    error_log("SQL Error Code: " . $e->getCode());
                    
                    // Check if error is due to foreign key constraint or duplicate entry
                    if ($e->getCode() == '23000') { // Integrity constraint violation
                        // Might be duplicate key or foreign key issue
                        // Try to find existing patient record one more time
                        $stmt = $pdo->prepare("SELECT * FROM patients WHERE user_id = ? OR sr_code = ?");
                        $stmt->execute([$user_id, $sr_code]);
                        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        if ($existing) {
                            echo json_encode([
                                'success' => true,
                                'patient' => $existing,
                                'message' => 'Patient record found (may have been created by another process)'
                            ]);
                        } else {
                            echo json_encode([
                                'success' => false,
                                'message' => 'User not found. Please verify the user exists in the system or contact an administrator. Error: ' . $e->getMessage()
                            ]);
                        }
                    } else {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Database error: ' . $e->getMessage()
                        ]);
                    }
                }
            }
        }
    }
} catch (PDOException $e) {
    error_log("Error getting patient by user_id: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>


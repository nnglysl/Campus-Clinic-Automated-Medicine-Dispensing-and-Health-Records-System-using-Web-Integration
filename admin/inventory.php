<?php
session_start();
require_once '../db.php';
<<<<<<< HEAD
require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Enable error logging
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/error_log.txt');

// Set error handler for AJAX requests
if (isset($_POST['action'])) {
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        error_log("PHP Error: [$errno] $errstr in $errfile on line $errline");
        return false;
    });
    
    register_shutdown_function(function() {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Server error: ' . $error['message'],
                'file' => basename($error['file']),
                'line' => $error['line']
            ]);
            exit;
        }
    });
}

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: /finalproject/auth/login.php');
    exit;
}
$userName = $_SESSION['username'] ?? $_SESSION['fname'] ?? 'Admin';

// PHPMailer for email notifications
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'phpmailer/src/Exception.php';
require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';

// Function to send inventory alert emails
function sendInventoryAlert($alertType, $medicines) {
    $mail = new PHPMailer(true);
    
    try {
        //Server settings
        $mail->isSMTP();
        $mail->Host       = $_ENV['MAIL_HOST'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $_ENV['MAIL_USERNAME'];
        $mail->Password   = $_ENV['MAIL_PASSWORD'];
        $mail->SMTPSecure = $_ENV['MAIL_ENCRYPTION'];
        $mail->Port       = $_ENV['MAIL_PORT'];

        //Recipients
        $mail->setFrom($_ENV['MAIL_FROM_ADDRESS'], $_ENV['MAIL_FROM_NAME']);
        $mail->addAddress($_ENV['MAIL_USERNAME']);

        //Content
        $mail->isHTML(true);
        
        if ($alertType === 'low_stock') {
            $mail->Subject = 'Low Stock Alert - Immediate Action Required';
            $body = '<h2 style="color: #dc3545;">Low Stock Alert</h2>';
            $body .= '<p>The following medicines are running low on stock:</p>';
            $body .= '<table border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
            $body .= '<tr style="background-color: #f8d7da;"><th>Batch Number</th><th>Item Code</th><th>Medicine Name</th><th>Current Quantity</th><th>Dispensed</th><th>Expiry Date</th></tr>';
            
            foreach ($medicines as $med) {
                $body .= "<tr>";
                $body .= "<td>{$med['batch_number']}</td>";
                $body .= "<td>{$med['item_code']}</td>";
                $body .= "<td>{$med['item_name']}</td>";
                $body .= "<td style='color: red; font-weight: bold;'>{$med['quantity']}</td>";
                $body .= "<td>{$med['dispensed']}</td>";
                $body .= "<td>{$med['expiry_date']}</td>";
                $body .= "</tr>";
            }
            $body .= '</table>';
            $body .= '<p style="margin-top: 20px;"><strong>Action Required:</strong> Please reorder these medicines immediately.</p>';
        } 
        elseif ($alertType === 'nearing_expiry') {
            $mail->Subject = 'Medicines Nearing Expiration Date';
            $body = '<h2 style="color: #ffc107;">Expiration Alert</h2>';
            $body .= '<p>The following medicines are nearing their expiration date (within 30 days):</p>';
            $body .= '<table border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
            $body .= '<tr style="background-color: #fff3cd;"><th>Batch Number</th><th>Item Code</th><th>Medicine Name</th><th>Quantity</th><th>Expiry Date</th><th>Days Until Expiry</th></tr>';
            
            foreach ($medicines as $med) {
                $daysLeft = floor((strtotime($med['expiry_date']) - time()) / (60 * 60 * 24));
                $body .= "<tr>";
                $body .= "<td>{$med['batch_number']}</td>";
                $body .= "<td>{$med['item_code']}</td>";
                $body .= "<td>{$med['item_name']}</td>";
                $body .= "<td>{$med['quantity']}</td>";
                $body .= "<td style='color: orange; font-weight: bold;'>{$med['expiry_date']}</td>";
                $body .= "<td style='color: orange; font-weight: bold;'>{$daysLeft} days</td>";
                $body .= "</tr>";
            }
            $body .= '</table>';
            $body .= '<p style="margin-top: 20px;"><strong>Action Required:</strong> Please plan for disposal or usage of these medicines.</p>';
        }
        elseif ($alertType === 'expired') {
            $mail->Subject = 'URGENT: Expired Medicines Detected';
            $body = '<h2 style="color: #dc3545;">EXPIRED MEDICINES ALERT</h2>';
            $body .= '<p style="color: red; font-weight: bold;">The following medicines have EXPIRED and should be removed immediately:</p>';
            $body .= '<table border="1" cellpadding="10" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
            $body .= '<tr style="background-color: #f8d7da;"><th>Batch Number</th><th>Item Code</th><th>Medicine Name</th><th>Quantity</th><th>Expiry Date</th><th>Days Expired</th></tr>';
            
            foreach ($medicines as $med) {
                $daysExpired = abs(floor((strtotime($med['expiry_date']) - time()) / (60 * 60 * 24)));
                $body .= "<tr>";
                $body .= "<td>{$med['batch_number']}</td>";
                $body .= "<td>{$med['item_code']}</td>";
                $body .= "<td>{$med['item_name']}</td>";
                $body .= "<td>{$med['quantity']}</td>";
                $body .= "<td style='color: red; font-weight: bold;'>{$med['expiry_date']}</td>";
                $body .= "<td style='color: red; font-weight: bold;'>{$daysExpired} days ago</td>";
                $body .= "</tr>";
            }
            $body .= '</table>';
            $body .= '<p style="margin-top: 20px;"><strong>URGENT ACTION REQUIRED:</strong> Remove and dispose of these medicines immediately.</p>';
        }
        
        $mail->Body = $body;
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        return false;
    }
}

// Function to create system notification (update existing or create new)
function createNotification($pdo, $type, $message, $medicines, $forceUpdate = false) {
    try {
        // Check if a notification with the same type was created today (once per day rule)
        $stmt = $pdo->prepare("
            SELECT id FROM notifications 
            WHERE type = ? 
            AND DATE(created_at) = CURDATE()
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$type]);
        $existing = $stmt->fetch();
        
        $data = json_encode([
            'medicines' => $medicines,
            'timestamp' => date('Y-m-d H:i:s'),
            'count' => is_array($medicines) ? count($medicines) : 0
        ]);
        
        // Only update if forceUpdate is true AND notification exists today
        // Otherwise, respect once-per-day rule
        if ($existing) {
            if ($forceUpdate) {
                // Update existing notification with new data and mark as unread
                $stmt = $pdo->prepare("
                    UPDATE notifications 
                    SET message = ?, data = ?, status = 'unread', created_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$message, $data, $existing['id']]);
                return true;
            }
            // Notification already exists today and forceUpdate is false - don't create duplicate
            return false;
        } else {
            // No notification today - create new one
            $stmt = $pdo->prepare("INSERT INTO notifications (type, message, data, status, created_at) VALUES (?, ?, ?, 'unread', NOW())");
            $stmt->execute([$type, $message, $data]);
            return true;
        }
    } catch (Exception $e) {
        error_log("Failed to create notification: " . $e->getMessage());
        return false;
    }
}

// Function to check and send automatic alerts
function checkAndSendAutomaticAlerts($pdo, $forceCheck = false) {
    // Check for low stock (quantity <= 10) - get individual batches for accurate reporting
    $stmt = $pdo->prepare("
        SELECT id, batch_number, item_code, item_name, quantity, dispensed, expiry_date
        FROM inventory 
        WHERE status = 'active' AND quantity <= 10
        ORDER BY quantity ASC, item_name ASC
    ");
    $stmt->execute();
    $lowStockMeds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check for nearing expiry (within 30 days but not expired)
    $stmt = $pdo->prepare("
        SELECT * FROM inventory 
        WHERE expiry_date > CURDATE() 
        AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
        AND status = 'active'
        ORDER BY expiry_date ASC
    ");
    $stmt->execute();
    $nearingExpiryMeds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check for expired medicines
    $stmt = $pdo->prepare("
        SELECT * FROM inventory 
        WHERE expiry_date < CURDATE() 
        AND status = 'active'
    ");
    $stmt->execute();
    $expiredMeds = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $alertsSent = false;
    
    // Send low stock alerts - check if we need to send (once per day per item)
    if (count($lowStockMeds) > 0) {
        // Filter items that haven't received an alert today
        $itemsToAlert = [];
        $itemCodes = array_unique(array_column($lowStockMeds, 'item_code'));
        
        if (count($itemCodes) > 0) {
            $placeholders = str_repeat('?,', count($itemCodes) - 1) . '?';
            
            // Get item codes that already received an alert today
            $stmt = $pdo->prepare("
                SELECT DISTINCT item_code 
                FROM alert_log 
                WHERE alert_type = 'low_stock' 
                AND item_code IS NOT NULL
                AND DATE(sent_at) = CURDATE()
                AND item_code IN ($placeholders)
            ");
            $stmt->execute($itemCodes);
            $alreadyAlerted = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'item_code');
            
            // Filter medicines that haven't been alerted today
            foreach ($lowStockMeds as $med) {
                // If forceCheck is true, include all items
                // Otherwise, only include items not alerted today
                if ($forceCheck || !in_array($med['item_code'], $alreadyAlerted)) {
                    $itemsToAlert[] = $med;
                }
            }
        } else {
            // If no item codes, check by batch_number
            $batchNumbers = array_unique(array_column($lowStockMeds, 'batch_number'));
            if (count($batchNumbers) > 0) {
                $placeholders = str_repeat('?,', count($batchNumbers) - 1) . '?';
                
                $stmt = $pdo->prepare("
                    SELECT DISTINCT batch_number 
                    FROM alert_log 
                    WHERE alert_type = 'low_stock' 
                    AND batch_number IS NOT NULL
                    AND DATE(sent_at) = CURDATE()
                    AND batch_number IN ($placeholders)
                ");
                $stmt->execute($batchNumbers);
                $alreadyAlertedBatches = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'batch_number');
                
                foreach ($lowStockMeds as $med) {
                    // Only include items not alerted today (respect once-per-day rule)
                    if (!in_array($med['batch_number'], $alreadyAlertedBatches)) {
                        $itemsToAlert[] = $med;
                    }
                }
            } else {
                $itemsToAlert = $lowStockMeds;
            }
        }
        
        // Only send alerts for items that haven't been alerted today
        if (count($itemsToAlert) > 0) {
            // Prepare data for email - aggregate by item_code for email, but keep individual batches
            $emailData = [];
            $aggregated = [];
            
            foreach ($itemsToAlert as $med) {
                $itemCode = $med['item_code'];
                if (!isset($aggregated[$itemCode])) {
                    $aggregated[$itemCode] = [
                        'item_code' => $itemCode,
                        'item_name' => $med['item_name'],
                        'total_quantity' => 0,
                        'batches' => []
                    ];
                }
                $aggregated[$itemCode]['total_quantity'] += $med['quantity'];
                $aggregated[$itemCode]['batches'][] = $med['batch_number'];
            }
            
            foreach ($aggregated as $med) {
                $emailData[] = [
                    'batch_number' => implode(', ', $med['batches']),
                    'item_code' => $med['item_code'],
                    'item_name' => $med['item_name'],
                    'quantity' => $med['total_quantity'],
                    'dispensed' => 0,
                    'expiry_date' => 'Multiple dates'
                ];
            }
            
            if (sendInventoryAlert('low_stock', $emailData)) {
                $message = count($itemsToAlert) . " medicine batch(es) are running low on stock (≤10 units)";
                // Only create notification if items were actually alerted (respects once-per-day rule)
                createNotification($pdo, 'low_stock', $message, $itemsToAlert, false);
                
                // Log each item that received an alert today
                foreach ($itemsToAlert as $med) {
                    $stmt = $pdo->prepare("
                        INSERT INTO alert_log (alert_type, item_count, item_code, batch_number, item_name, message, sent_at) 
                        VALUES ('low_stock', 1, ?, ?, ?, ?, NOW())
                    ");
                    $msg = "Low stock alert: {$med['item_name']} ({$med['item_code']}) has only {$med['quantity']} units remaining";
                    $stmt->execute([
                        $med['item_code'],
                        $med['batch_number'],
                        $med['item_name'],
                        $msg
                    ]);
                }
                $alertsSent = true;
            }
        }
    }
    
    // Send nearing expiry alerts - check if we need to send (once per day per item)
    if (count($nearingExpiryMeds) > 0) {
        // Filter items that haven't received an alert today
        $itemsToAlert = [];
        $itemCodes = array_unique(array_column($nearingExpiryMeds, 'item_code'));
        
        if (count($itemCodes) > 0) {
            $placeholders = str_repeat('?,', count($itemCodes) - 1) . '?';
            
            // Get item codes that already received an alert today
            $stmt = $pdo->prepare("
                SELECT DISTINCT item_code 
                FROM alert_log 
                WHERE alert_type = 'nearing_expiry' 
                AND item_code IS NOT NULL
                AND DATE(sent_at) = CURDATE()
                AND item_code IN ($placeholders)
            ");
            $stmt->execute($itemCodes);
            $alreadyAlerted = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'item_code');
            
            // Filter medicines that haven't been alerted today
            // Only bypass once-per-day check if forceCheck is explicitly true (for manual triggers)
            foreach ($nearingExpiryMeds as $med) {
                // Only include items not alerted today (respect once-per-day rule)
                if (!in_array($med['item_code'], $alreadyAlerted)) {
                    $itemsToAlert[] = $med;
                }
            }
        } else {
            // If no item codes, check by batch_number
            $batchNumbers = array_unique(array_column($nearingExpiryMeds, 'batch_number'));
            if (count($batchNumbers) > 0) {
                $placeholders = str_repeat('?,', count($batchNumbers) - 1) . '?';
                
                $stmt = $pdo->prepare("
                    SELECT DISTINCT batch_number 
                    FROM alert_log 
                    WHERE alert_type = 'nearing_expiry' 
                    AND batch_number IS NOT NULL
                    AND DATE(sent_at) = CURDATE()
                    AND batch_number IN ($placeholders)
                ");
                $stmt->execute($batchNumbers);
                $alreadyAlertedBatches = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'batch_number');
                
                foreach ($nearingExpiryMeds as $med) {
                    // Only include items not alerted today (respect once-per-day rule)
                    if (!in_array($med['batch_number'], $alreadyAlertedBatches)) {
                        $itemsToAlert[] = $med;
                    }
                }
            } else {
                $itemsToAlert = $nearingExpiryMeds;
            }
        }
        
        // Only send alerts for items that haven't been alerted today
        if (count($itemsToAlert) > 0) {
            if (sendInventoryAlert('nearing_expiry', $itemsToAlert)) {
                $message = count($itemsToAlert) . " medicine(s) are nearing expiration (within 30 days)";
                // Only create notification if items were actually alerted (respects once-per-day rule)
                createNotification($pdo, 'nearing_expiry', $message, $itemsToAlert, false);
                
                // Log each item that received an alert today
                foreach ($itemsToAlert as $med) {
                    $stmt = $pdo->prepare("
                        INSERT INTO alert_log (alert_type, item_count, item_code, batch_number, item_name, message, sent_at) 
                        VALUES ('nearing_expiry', 1, ?, ?, ?, ?, NOW())
                    ");
                    $daysLeft = (new DateTime($med['expiry_date']))->diff(new DateTime())->days;
                    $msg = "Expiring soon: {$med['item_name']} ({$med['item_code']}) expires in {$daysLeft} days on {$med['expiry_date']}";
                    $stmt->execute([
                        $med['item_code'],
                        $med['batch_number'],
                        $med['item_name'],
                        $msg
                    ]);
                }
                $alertsSent = true;
            }
        }
    }
    
    // Send expired alerts - check if we need to send (once per day per item, but critical so allow force)
    if (count($expiredMeds) > 0) {
        // Filter items that haven't received an alert today
        $itemsToAlert = [];
        $itemCodes = array_unique(array_column($expiredMeds, 'item_code'));
        
        if (count($itemCodes) > 0) {
            $placeholders = str_repeat('?,', count($itemCodes) - 1) . '?';
            
            // Get item codes that already received an alert today
            $stmt = $pdo->prepare("
                SELECT DISTINCT item_code 
                FROM alert_log 
                WHERE alert_type = 'expired' 
                AND item_code IS NOT NULL
                AND DATE(sent_at) = CURDATE()
                AND item_code IN ($placeholders)
            ");
            $stmt->execute($itemCodes);
            $alreadyAlerted = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'item_code');
            
            // Filter medicines that haven't been alerted today
            // Only bypass once-per-day check if forceCheck is explicitly true (for manual triggers)
            foreach ($expiredMeds as $med) {
                // Only include items not alerted today (respect once-per-day rule)
                if (!in_array($med['item_code'], $alreadyAlerted)) {
                    $itemsToAlert[] = $med;
                }
            }
        } else {
            // If no item codes, check by batch_number
            $batchNumbers = array_unique(array_column($expiredMeds, 'batch_number'));
            if (count($batchNumbers) > 0) {
                $placeholders = str_repeat('?,', count($batchNumbers) - 1) . '?';
                
                $stmt = $pdo->prepare("
                    SELECT DISTINCT batch_number 
                    FROM alert_log 
                    WHERE alert_type = 'expired' 
                    AND batch_number IS NOT NULL
                    AND DATE(sent_at) = CURDATE()
                    AND batch_number IN ($placeholders)
                ");
                $stmt->execute($batchNumbers);
                $alreadyAlertedBatches = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'batch_number');
                
                foreach ($expiredMeds as $med) {
                    // Only include items not alerted today (respect once-per-day rule)
                    if (!in_array($med['batch_number'], $alreadyAlertedBatches)) {
                        $itemsToAlert[] = $med;
                    }
                }
            } else {
                $itemsToAlert = $expiredMeds;
            }
        }
        
        // Only send alerts for items that haven't been alerted today
        if (count($itemsToAlert) > 0) {
            if (sendInventoryAlert('expired', $itemsToAlert)) {
                $message = count($itemsToAlert) . " medicine(s) have EXPIRED and need immediate removal";
                createNotification($pdo, 'expired', $message, $itemsToAlert, true);
                
                // Log each item that received an alert today
                foreach ($itemsToAlert as $med) {
                    $stmt = $pdo->prepare("
                        INSERT INTO alert_log (alert_type, item_count, item_code, batch_number, item_name, message, sent_at) 
                        VALUES ('expired', 1, ?, ?, ?, ?, NOW())
                    ");
                    $msg = "EXPIRED: {$med['item_name']} ({$med['item_code']}) expired on {$med['expiry_date']}";
                    $stmt->execute([
                        $med['item_code'],
                        $med['batch_number'],
                        $med['item_name'],
                        $msg
                    ]);
                }
                $alertsSent = true;
            }
        }
    }
    
    return $alertsSent;
}

// Function to generate item code based on medicine name
function generateItemCode($pdo, $item_name) {
    // Check if item already exists in item_master
    $stmt = $pdo->prepare("SELECT item_code FROM item_master WHERE item_name = ? LIMIT 1");
    $stmt->execute([$item_name]);
    $result = $stmt->fetch();
    
    if ($result) {
        return $result['item_code'];
    }
    
    // Generate new item code
    $stmt = $pdo->query("SELECT item_code FROM item_master ORDER BY id DESC LIMIT 1");
    $result = $stmt->fetch();
    
    if ($result) {
        // Extract number from last item code (e.g., MED001 -> 1)
        $lastNumber = intval(substr($result['item_code'], 3));
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    $newCode = 'MED' . str_pad($newNumber, 3, '0', STR_PAD_LEFT);
    
    // Insert into item_master
    $stmt = $pdo->prepare("INSERT INTO item_master (item_code, item_name, created_at) VALUES (?, ?, NOW())");
    $stmt->execute([$newCode, $item_name]);
    
    return $newCode;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Set JSON header immediately to ensure proper response
    header('Content-Type: application/json');
    // Disable output buffering for faster response
    if (ob_get_level()) {
        ob_end_clean();
    }
=======

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    
    $action = $_POST['action'];
    
    try {
        switch($action) {
            case 'getMedicines':
<<<<<<< HEAD
                // Automatically archive any expired items that are still marked as active
                // This ensures expired items are moved to archive even if they weren't updated
                $archiveStmt = $pdo->prepare("
                    UPDATE inventory 
                    SET status = 'archive' 
                    WHERE expiry_date < CURDATE() 
                    AND status = 'active'
                ");
                $archiveStmt->execute();
                $archivedCount = $archiveStmt->rowCount();
                if ($archivedCount > 0) {
                    error_log("Automatically archived {$archivedCount} expired item(s) during getMedicines");
                }
                
                // Optimized query - fetch all necessary fields
                // Quantity field reflects actual available stock (decremented when dispensed)
                // This ensures real-time accuracy across all modules
                $stmt = $pdo->query("
                    SELECT i.id, i.batch_number, i.item_code, i.item_name, i.quantity, 
                           i.dispensed, i.expiry_date, i.description, i.supplier, 
                           i.delivery_date, i.status,
                           bd.dr_number, bd.supplier as batch_supplier 
                    FROM inventory i
                    LEFT JOIN batch_deliveries bd ON i.batch_number = bd.batch_number
                    WHERE i.status = 'active' OR i.status = 'archive'
                    ORDER BY i.batch_number ASC, i.item_code ASC, i.expiry_date ASC
                ");
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
                break;
                
            case 'addMedicine':
                try {
                    // Validate required fields
                    if (empty($_POST['batch_number'])) {
                        echo json_encode(['success' => false, 'error' => 'Batch number is required']);
                        break;
                    }
                    
                    if (empty($_POST['item_name'])) {
                        echo json_encode(['success' => false, 'error' => 'Medicine/Item name is required']);
                        break;
                    }
                    
                    if (!isset($_POST['quantity'])) {
                        echo json_encode(['success' => false, 'error' => 'Quantity is required']);
                        break;
                    }
                    
                    if (empty($_POST['expiry_date'])) {
                        echo json_encode(['success' => false, 'error' => 'Expiry date is required']);
                        break;
                    }
                    
                    if (empty($_POST['description'])) {
                        echo json_encode(['success' => false, 'error' => 'Description is required']);
                        break;
                    }
                    
                    // Allow empty supplier but provide default
                    $batch_number = trim($_POST['batch_number']);
                    $item_name = trim($_POST['item_name']);
                    $quantity = intval($_POST['quantity']);
                    $expiry_date = $_POST['expiry_date'];
                    $description = trim($_POST['description']);
                    $supplier = !empty($_POST['supplier']) ? trim($_POST['supplier']) : 'Unknown Supplier';
                    $delivery_date = !empty($_POST['delivery_date']) ? $_POST['delivery_date'] : date('Y-m-d');
                    
                    // Additional validation
                    if ($quantity <= 0) {
                        echo json_encode(['success' => false, 'error' => 'Quantity must be greater than 0']);
                        break;
                    }
                    
                    // Validate expiry date format
                    $dateCheck = DateTime::createFromFormat('Y-m-d', $expiry_date);
                    if (!$dateCheck || $dateCheck->format('Y-m-d') !== $expiry_date) {
                        echo json_encode(['success' => false, 'error' => 'Invalid expiry date format']);
                        break;
                    }
                    
                    // Generate or get item code
                    $item_code = generateItemCode($pdo, $item_name);
                    
                    if (!$item_code) {
                        echo json_encode(['success' => false, 'error' => 'Failed to generate item code']);
                        break;
                    }
                    
                    // Start transaction
                    $pdo->beginTransaction();
                    
                    try {
                        // Get a valid employee ID for received_by
                        $received_by_id = null;
                        
                        // First try to get from session
                        if (isset($_SESSION['user_id'])) {
                            $stmt = $pdo->prepare("SELECT id FROM employees WHERE user_id = ? LIMIT 1");
                            $stmt->execute([$_SESSION['user_id']]);
                            $result = $stmt->fetch();
                            if ($result) {
                                $received_by_id = $result['id'];
                            }
                        }
                        
                        // If not found, get the first active employee
                        if (!$received_by_id) {
                            $stmt = $pdo->query("SELECT id FROM employees WHERE status = 'active' ORDER BY id ASC LIMIT 1");
                            $result = $stmt->fetch();
                            if ($result) {
                                $received_by_id = $result['id'];
                            } else {
                                throw new Exception('No active employees found in the system');
                            }
                        }
                        
                        // Check if batch_delivery exists, if not create it
                        $stmt = $pdo->prepare("SELECT id FROM batch_deliveries WHERE batch_number = ?");
                        $stmt->execute([$batch_number]);
                        
                        if (!$stmt->fetch()) {
                            $stmt = $pdo->prepare("
                                INSERT INTO batch_deliveries 
                                (batch_number, delivery_date, supplier, received_by, total_items) 
                                VALUES (?, ?, ?, ?, 0)
                            ");
                            $stmt->execute([$batch_number, $delivery_date, $supplier, $received_by_id]);
                        }
                        
                        // Insert or update inventory using composite key
                        $stmt = $pdo->prepare("
                            INSERT INTO inventory 
                            (batch_number, item_code, item_name, expiry_date, quantity, dispensed, 
                            description, supplier, delivery_date, status) 
                            VALUES (?, ?, ?, ?, ?, 0, ?, ?, ?, 'active')
                            ON DUPLICATE KEY UPDATE 
                            quantity = quantity + VALUES(quantity),
                            description = VALUES(description),
                            supplier = VALUES(supplier),
                            delivery_date = VALUES(delivery_date)
                        ");
                        $stmt->execute([
                            $batch_number, 
                            $item_code, 
                            $item_name, 
                            $expiry_date, 
                            $quantity, 
                            $description, 
                            $supplier, 
                            $delivery_date
                        ]);
                        
                        // Commit transaction
                        $pdo->commit();
                        
                        // Check and send automatic alerts immediately after stock update (force check)
                        try {
                            checkAndSendAutomaticAlerts($pdo, true);
                        } catch (Exception $alertError) {
                            error_log("Alert check failed: " . $alertError->getMessage());
                        }
                        
                        echo json_encode([
                            'success' => true, 
                            'item_code' => $item_code,
                            'message' => 'Stock added successfully'
                        ]);
                        
                    } catch (Exception $e) {
                        // Rollback on error
                        $pdo->rollBack();
                        throw $e;
                    }
                    
                } catch (Exception $e) {
                    // Log the error
                    error_log("Error adding medicine: " . $e->getMessage());
                    error_log("Stack trace: " . $e->getTraceAsString());
                    error_log("POST data: " . print_r($_POST, true));
                    
                    // Ensure proper JSON response
                    header('Content-Type: application/json');
                    http_response_code(500);
                    echo json_encode([
                        'success' => false, 
                        'error' => $e->getMessage() ?: 'An unexpected error occurred while saving the stock.'
                    ]);
                    exit;
                }
                break;
                
            case 'updateMedicine':
                // Validate required fields
                if (empty($_POST['id']) || empty($_POST['item_name']) || 
                    !isset($_POST['quantity']) || empty($_POST['expiry_date']) || 
                    empty($_POST['description'])) {
                    echo json_encode(['success' => false, 'error' => 'All fields are required']);
                    break;
                }
                
                $id = $_POST['id'];
                $item_name = trim($_POST['item_name']);
                $quantity = intval($_POST['quantity']);
                $expiry_date = $_POST['expiry_date'];
                $description = trim($_POST['description']);
                $supplier = trim($_POST['supplier'] ?? '');
                
                // Check if the expiry date is in the past
                $expiryDateObj = new DateTime($expiry_date);
                $today = new DateTime();
                $today->setTime(0, 0, 0); // Reset time to midnight for accurate date comparison
                $expiryDateObj->setTime(0, 0, 0);
                $isExpired = $expiryDateObj < $today;
                
                // Determine status: if expiry date is in the past, set to 'archive', otherwise keep current status or set to 'active'
                // First, get current status to preserve it if not expired
                $currentStmt = $pdo->prepare("SELECT status FROM inventory WHERE id = ?");
                $currentStmt->execute([$id]);
                $currentStatus = $currentStmt->fetchColumn();
                
                // If expired, set to archive; otherwise, if currently active, keep active; if archived but now valid, set to active
                if ($isExpired) {
                    $newStatus = 'archive';
                } else {
                    // If expiry date is now in the future, set to active (in case it was previously archived)
                    $newStatus = 'active';
                }
                
                $stmt = $pdo->prepare("
                    UPDATE inventory 
                    SET item_name = ?, quantity = ?, expiry_date = ?, description = ?, supplier = ?, status = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$item_name, $quantity, $expiry_date, $description, $supplier, $newStatus, $id]);
                
                // Check and send automatic alerts immediately after update (force check)
                try {
                    checkAndSendAutomaticAlerts($pdo, true);
                } catch (Exception $alertError) {
                    error_log("Alert check failed: " . $alertError->getMessage());
                }
                
                // Return success with information about whether item was archived
                echo json_encode([
                    'success' => true,
                    'archived' => $isExpired,
                    'status' => $newStatus,
                    'message' => $isExpired ? 'Medicine updated and automatically moved to archive due to expired date.' : 'Medicine updated successfully.'
                ]);
                break;
                
            case 'getNextBatchNumber':
                // Generate date-based batch number: BATCH + YYMMDD
                $today = date('ymd'); // Gets current date in YYMMDD format (e.g., 251115)
                $todayBatch = 'BATCH' . $today;
                
                // Check if a batch for today already exists
                $stmt = $pdo->prepare("
                    SELECT batch_number, delivery_date 
                    FROM batch_deliveries 
                    WHERE batch_number = ?
                ");
                $stmt->execute([$todayBatch]);
                $existingBatch = $stmt->fetch();
                
                if ($existingBatch) {
                    // Batch for today already exists, return it
                    echo json_encode([
                        'success' => true, 
                        'batch_number' => $todayBatch,
                        'is_existing' => true,
                        'delivery_date' => $existingBatch['delivery_date']
                    ]);
                } else {
                    // New batch for today
                    echo json_encode([
                        'success' => true, 
                        'batch_number' => $todayBatch,
                        'is_existing' => false,
                        'delivery_date' => date('Y-m-d')
                    ]);
                }
                break;

            case 'getBatchForDate':
              $date = $_POST['date'] ?? date('Y-m-d');
              $dateObj = new DateTime($date);
              $dateBatch = 'BATCH' . $dateObj->format('ymd');
              
              $stmt = $pdo->prepare("
                  SELECT batch_number, COUNT(DISTINCT item_code) as item_count
                  FROM batch_deliveries bd
                  LEFT JOIN inventory i ON bd.batch_number = i.batch_number
                  WHERE bd.batch_number = ?
                  GROUP BY bd.batch_number
              ");
              $stmt->execute([$dateBatch]);
              $result = $stmt->fetch();
              
              echo json_encode([
                  'success' => true,
                  'batch_number' => $dateBatch,
                  'exists' => $result ? true : false,
                  'item_count' => $result ? $result['item_count'] : 0
              ]);
              break;
                
            case 'searchMedicines':
                $searchTerm = trim($_POST['search'] ?? '');
                
                if (empty($searchTerm)) {
                    echo json_encode(['success' => true, 'medicines' => []]);
                    break;
                }
                
                // Search for distinct medicine names from item_master with inventory data
                // Optimized query: Calculate total quantity from all active inventory entries
                $stmt = $pdo->prepare("
                    SELECT 
                        im.item_name,
                        im.item_code,
                        COALESCE(MAX(i.description), '') as description,
                        COALESCE(SUM(CASE WHEN i.status = 'active' THEN i.quantity ELSE 0 END), 0) as total_quantity
                    FROM item_master im
                    LEFT JOIN inventory i ON im.item_name = i.item_name
                    WHERE im.item_name LIKE ?
                    GROUP BY im.item_name, im.item_code
                    HAVING total_quantity >= 0
                    ORDER BY im.item_name ASC
                    LIMIT 10
                ");
                $stmt->execute(["%{$searchTerm}%"]);
                $medicines = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'medicines' => $medicines]);
                break;
                
            case 'getMedicineDetails':
                $item_name = trim($_POST['item_name']);
                
                if (empty($item_name)) {
                    echo json_encode(['success' => false, 'error' => 'Item name is required']);
                    break;
                }
                
                // Get item code from item_master
                $stmt = $pdo->prepare("SELECT item_code FROM item_master WHERE item_name = ? LIMIT 1");
                $stmt->execute([$item_name]);
                $itemMaster = $stmt->fetch();
                
                // Get total current stock (sum of all active inventory entries for this item)
                // Also get the most recent supplier for this medicine
                // This correctly sums all quantities across all batches for the same medicine
                $stmt = $pdo->prepare("
                    SELECT 
                        COALESCE(SUM(quantity), 0) as total_quantity,
                        MAX(description) as description,
                        (SELECT supplier FROM inventory 
                         WHERE item_name = ? AND status = 'active' AND supplier IS NOT NULL AND supplier != ''
                         ORDER BY id DESC LIMIT 1) as supplier
                    FROM inventory 
                    WHERE item_name = ? AND status = 'active'
                ");
                $stmt->execute([$item_name, $item_name]);
                $inventoryData = $stmt->fetch();
                
                $totalQuantity = (int)($inventoryData['total_quantity'] ?? 0);
                $description = $inventoryData['description'] ?? '';
                $supplier = $inventoryData['supplier'] ?? '';
                
                if ($itemMaster) {
                    echo json_encode([
                        'success' => true, 
                        'item_code' => $itemMaster['item_code'], 
                        'exists' => true,
                        'current_stock' => (int)$totalQuantity,
                        'description' => $description,
                        'supplier' => $supplier
                    ]);
                } else {
                    // Generate preview of new item code
                    $stmt = $pdo->query("SELECT item_code FROM item_master ORDER BY id DESC LIMIT 1");
                    $result = $stmt->fetch();
                    
                    if ($result) {
                        $num = intval(substr($result['item_code'], 3)) + 1;
                    } else {
                        $num = 1;
                    }
                    
                    $newCode = 'MED' . str_pad($num, 3, '0', STR_PAD_LEFT);
                    echo json_encode([
                        'success' => true, 
                        'item_code' => $newCode, 
                        'exists' => false,
                        'current_stock' => 0,
                        'description' => ''
                    ]);
                }
                break;
                
            case 'searchSuppliers':
                $searchTerm = trim($_POST['search'] ?? '');
                
                if (empty($searchTerm)) {
                    echo json_encode(['success' => true, 'suppliers' => []]);
                    break;
                }
                
                // Search for distinct supplier names from inventory
                $stmt = $pdo->prepare("
                    SELECT DISTINCT supplier
                    FROM inventory 
                    WHERE supplier IS NOT NULL 
                    AND supplier != '' 
                    AND supplier LIKE ?
                    ORDER BY supplier ASC
                    LIMIT 10
                ");
                $stmt->execute(["%{$searchTerm}%"]);
                $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                echo json_encode(['success' => true, 'suppliers' => array_column($suppliers, 'supplier')]);
                break;
                
            case 'getItemCode':
                $item_name = trim($_POST['item_name']);
                
                if (empty($item_name)) {
                    echo json_encode(['success' => false, 'error' => 'Item name is required']);
                    break;
                }
                
                // Check if item exists in item_master
                $stmt = $pdo->prepare("SELECT item_code FROM item_master WHERE item_name = ? LIMIT 1");
                $stmt->execute([$item_name]);
                $result = $stmt->fetch();
                
                if ($result) {
                    echo json_encode(['success' => true, 'item_code' => $result['item_code'], 'exists' => true]);
                } else {
                    // Generate preview of new item code
                    $stmt = $pdo->query("SELECT item_code FROM item_master ORDER BY id DESC LIMIT 1");
                    $result = $stmt->fetch();
                    
                    if ($result) {
                        $num = intval(substr($result['item_code'], 3)) + 1;
                    } else {
                        $num = 1;
                    }
                    
                    $newCode = 'MED' . str_pad($num, 3, '0', STR_PAD_LEFT);
                    echo json_encode(['success' => true, 'item_code' => $newCode, 'exists' => false]);
=======
                $stmt = $pdo->query("SELECT * FROM inventory ORDER BY batchId ASC");
                echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
                break;
                
            case 'addMedicine':
                $batchId = $_POST['batchId'];
                $code = $_POST['code'];
                $name = $_POST['name'];
                $quantity = $_POST['quantity'];
                $expiry = $_POST['expiry'];
                $description = $_POST['description'];
                
                $stmt = $pdo->prepare("INSERT INTO inventory (batchId, code, name, quantity, dispensed, expiry, description, status) VALUES (?, ?, ?, ?, 0, ?, ?, 'active')");
                $stmt->execute([$batchId, $code, $name, $quantity, $expiry, $description]);
                
                echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
                break;
                
            case 'updateMedicine':
                $id = $_POST['id'];
                $name = $_POST['name'];
                $quantity = $_POST['quantity'];
                $expiry = $_POST['expiry'];
                $description = $_POST['description'];
                
                $stmt = $pdo->prepare("UPDATE inventory SET name = ?, quantity = ?, expiry = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $quantity, $expiry, $description, $id]);
                
                echo json_encode(['success' => true]);
                break;
                
            case 'getNextBatchId':
                $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(batchId, 6) AS UNSIGNED)) as maxBatch FROM inventory");
                $result = $stmt->fetch();
                $nextNum = ($result['maxBatch'] ?? 0) + 1;
                echo json_encode(['success' => true, 'batchId' => 'BATCH' . str_pad($nextNum, 3, '0', STR_PAD_LEFT)]);
                break;
                
            case 'getItemCode':
                $name = $_POST['name'];
                $stmt = $pdo->prepare("SELECT code FROM inventory WHERE name = ? LIMIT 1");
                $stmt->execute([$name]);
                $result = $stmt->fetch();
                
                if ($result) {
                    echo json_encode(['success' => true, 'code' => $result['code']]);
                } else {
                    $stmt = $pdo->query("SELECT MAX(CAST(SUBSTRING(code, 4) AS UNSIGNED)) as maxCode FROM inventory");
                    $result = $stmt->fetch();
                    $nextNum = ($result['maxCode'] ?? 0) + 1;
                    echo json_encode(['success' => true, 'code' => 'MED' . str_pad($nextNum, 3, '0', STR_PAD_LEFT)]);
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                }
                break;

            case 'archiveExpired':
<<<<<<< HEAD
                $stmt = $pdo->prepare("
                    UPDATE inventory 
                    SET status = 'archive' 
                    WHERE expiry_date < CURDATE() 
                    AND status = 'active'
                ");
=======
                $stmt = $pdo->prepare("UPDATE inventory SET status = 'archive' WHERE expiry < CURDATE() AND status != 'archive'");
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                $stmt->execute();
                echo json_encode(['success' => true, 'affected' => $stmt->rowCount()]);
                break;
                
<<<<<<< HEAD
            case 'checkAlerts':
                // Check alerts without forcing (only send if not sent today per item)
                // This allows dashboard updates without triggering duplicate alerts
                $alertsSent = checkAndSendAutomaticAlerts($pdo, false);
                echo json_encode(['success' => true, 'alerts_sent' => $alertsSent]);
                break;
                
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
<<<<<<< HEAD

// Don't run automatic alert check on page load - it blocks rendering
// Instead, run it asynchronously via AJAX after page loads
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Inventory</title>

  <!-- Stylesheets -->
<<<<<<< HEAD
  <link href="../admin/css/inventory.css" rel="stylesheet" />
  <link href="../admin/css/responsive.css" rel="stylesheet">
  <link href="/finalproject/css/nav.css" rel="stylesheet" />
  <link href="../admin/css/notifications.css" rel="stylesheet" />
=======
  <link href="../admin/inventory.css" rel="stylesheet" />
  <link href="../admin/nav.css" rel="stylesheet" />
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://fonts.googleapis.com/css2?family=DM+Serif+Text:ital@0;1&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css" />
<<<<<<< HEAD
  
  <style>
    /* Autocomplete Dropdown Styles */
    .autocomplete-wrapper {
      position: relative;
    }
    
    .autocomplete-dropdown {
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
    
    .autocomplete-item {
      padding: 10px 15px;
      cursor: pointer;
      border-bottom: 1px solid #f0f0f0;
      transition: background-color 0.2s;
    }
    
    .autocomplete-item:hover,
    .autocomplete-item.highlighted {
      background-color: #e7f3ff;
    }
    
    .autocomplete-item.exact-match {
      background-color: #d4edda;
      border-left: 3px solid #28a745;
    }
    
    .autocomplete-item.exact-match:hover,
    .autocomplete-item.exact-match.highlighted {
      background-color: #c3e6cb;
    }
    
    .autocomplete-item:last-child {
      border-bottom: none;
    }
    
    .autocomplete-item-name {
      font-weight: 600;
      color: #333;
      margin-bottom: 2px;
    }
    
    .autocomplete-item-code {
      font-size: 12px;
      color: #666;
    }
    
    .autocomplete-item-stock {
      font-size: 11px;
      color: #28a745;
      margin-top: 2px;
    }
    
    #stockDescription[readonly] {
      background-color: #f8f9fa;
      cursor: not-allowed;
    }
    
    /* Calendar button styling */
    #stockExpiryCalendarBtn {
      border-left: none;
      border-color: #ced4da;
    }
    
    #stockExpiryCalendarBtn:hover {
      background-color: #e9ecef;
      border-color: #ced4da;
    }
    
    /* Date input styling */
    #stockExpiry {
      cursor: pointer;
    }
    
    #stockExpiry::-webkit-calendar-picker-indicator {
      cursor: pointer;
      opacity: 0.6;
    }
    
    #stockExpiry::-webkit-calendar-picker-indicator:hover {
      opacity: 1;
    }
    
    /* Fix navbar consistency */
    .dropdown-menu-container.active > a {
      background: rgba(255, 255, 255, 0.15);
      color: white;
      font-weight: 600;
    }
    
    .submenu-item.active {
      background: rgba(255, 255, 255, 0.2);
      color: white;
      font-weight: 600;
    }
    
    /* Ensure submenu is visible when it has show class */
    .submenu.show {
      display: block !important;
    }
  </style>
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
</head>

<body>
  <!-- HEADER -->
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
      <!-- Mobile Menu Icon -->
      <button type="button" class="mobile-menu-icon" id="mobileMenuBtn" aria-label="Toggle navigation menu" aria-expanded="false">
        <i class="bi bi-list"></i>
      </button>
      <?php include 'notification_component.php'; ?>
      <div class="logout-icon" id="logoutBtn"><i class="bi bi-box-arrow-right"></i></div>
=======
      <div class="notification-icon"><i class="bi bi-bell-fill"></i></div>
      <div class="logout-icon"><i class="bi bi-box-arrow-right"></i></div>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    </div>
  </div>

  <!-- SIDEBAR + CONTENT -->
  <div class="main-container">
    <div class="sidebar">
      <div>
        <a href="../admin/adminDashboard.php" class="menu-item">Dashboard</a>
        <a href="../admin/patients.php" class="menu-item">Patient</a>
        <a href="../admin/userManagement.php" class="menu-item">User Management</a>

        <!-- INVENTORY DROPDOWN -->
        <div class="dropdown-menu-container">
          <a href="../admin/inventory.php" id="inventoryMain">Inventory</a>
          <div class="submenu" id="inventorySubmenu">
            <a href="#" class="submenu-item" id="medicineTab">Medicines</a>
            <a href="#" class="submenu-item" id="stockTab">Stocks</a>
          </div>
        </div>
<<<<<<< HEAD
        <a href="../admin/activity_logs.php" class="menu-item">Activity Logs</a>
        <a href="../admin/reports.php" class="menu-item">Reports & Analytics</a>
      </div>
     <div class="user-profile">
=======

        <a href="../admin/appointmentManagement.php" class="menu-item">Appointments</a>
        <a href="../admin/reports.html" class="menu-item">Reports & Analytics</a>
      </div>
      <div class="user-profile">
        <div class="avatar"></div>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        <span><?php echo htmlspecialchars($userName); ?></span>
      </div>
    </div>

<<<<<<< HEAD
=======

>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    <div class="main-content">
      <!-- Inventory Overview -->
      <div id="inventoryOverviewSection">
        <h2>Inventory Dashboard</h2>

        <div class="row mt-4">
          <div class="col-md-6">
            <div class="alert-section">
              <div class="alert-header">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <h5>Low Stock Alert</h5>
              </div>
              <div id="lowStockCards" class="alert-cards-container"></div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="alert-section expired-section">
              <div class="alert-header">
                <i class="bi bi-calendar-x-fill"></i>
                <h5>Nearing Expiration Alert</h5>
              </div>
              <div id="expiredCards" class="alert-cards-container"></div>
            </div>
          </div>
        </div>

<<<<<<< HEAD
        <div id="detailedTablesSection" class="mt-4">
=======
        <div class="mt-4">
          <button class="btn btn-outline-secondary" id="toggleDetailedView">
            <i class="bi bi-table"></i> View Detailed Tables
          </button>
        </div>

        <div id="detailedTablesSection" style="display:none;" class="mt-4">
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
          <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
              <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#lowstockDashboard">Low Stock Items</button>
            </li>
            <li class="nav-item">
              <button class="nav-link" data-bs-toggle="tab" data-bs-target="#expiredDashboard">Nearing Expiration</button>
            </li>
          </ul>

          <div class="tab-content">
            <div class="tab-pane fade show active" id="lowstockDashboard">
              <div class="table-container">
                <table id="lowStockDashboardTable" class="display table table-bordered table-striped">
                  <thead>
                    <tr>
<<<<<<< HEAD
                      <th>Batch Number</th>
=======
                      <th>Batch ID</th>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                      <th>Item Code</th>
                      <th>Item Name</th>
                      <th>Quantity</th>
                      <th>Dispensed</th>
                      <th>Expiry Date</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>

            <div class="tab-pane fade" id="expiredDashboard">
              <div class="table-container">
                <table id="expiredDashboardTable" class="display table table-bordered table-striped">
                  <thead>
                    <tr>
<<<<<<< HEAD
                      <th>Batch Number</th>
=======
                      <th>Batch ID</th>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                      <th>Item Code</th>
                      <th>Item Name</th>
                      <th>Quantity</th>
                      <th>Dispensed</th>
                      <th>Expiry Date</th>
                      <th>Days Until Expiry</th>
                      <th>Status</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Medicines Section -->
      <div id="medicinesSection" style="display:none;">
        <h2 class="mb-4">Medicines</h2>
        <div class="mb-3">
          <label for="sortBy" class="form-label me-2">Sort by:</label>
          <select id="sortBy" class="form-select d-inline-block" style="width: auto;">
<<<<<<< HEAD
            <option value="batch">Batch Number</option>
=======
            <option value="batch">Batch ID</option>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            <option value="code">Item Code</option>
            <option value="name">Item Name</option>
            <option value="quantity">Quantity</option>
            <option value="expiry">Expiry Date</option>
          </select>
        </div>
        <ul class="nav nav-tabs mb-3">
          <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#active">Active</button>
          </li>
          <li class="nav-item">
<<<<<<< HEAD
=======
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#bod">BOD</button>
          </li>
          <li class="nav-item">
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#archive">Archive/Disposal</button>
          </li>
        </ul>
        <div class="tab-content table-container">
          <div class="tab-pane fade show active" id="active">
            <table id="activeTable" class="display table table-bordered table-striped">
              <thead>
                <tr>
<<<<<<< HEAD
                  <th>Batch Number</th>
=======
                  <th>Batch ID</th>
                  <th>Item Code</th>
                  <th>Item Name</th>
                  <th>Quantity</th>
                  <th>Dispensed</th>
                  <th>Expiry Date</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <div class="tab-pane fade" id="bod">
            <table id="bodTable" class="display table table-bordered table-striped">
              <thead>
                <tr>
                  <th>Batch ID</th>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                  <th>Item Code</th>
                  <th>Item Name</th>
                  <th>Quantity</th>
                  <th>Dispensed</th>
                  <th>Expiry Date</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
          <div class="tab-pane fade" id="archive">
            <table id="archiveTable" class="display table table-bordered table-striped">
              <thead>
                <tr>
<<<<<<< HEAD
                  <th>Batch Number</th>
=======
                  <th>Batch ID</th>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                  <th>Item Code</th>
                  <th>Item Name</th>
                  <th>Quantity</th>
                  <th>Dispensed</th>
                  <th>Expiry Date</th>
                  <th>Status</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody></tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Stocks Section -->
      <div id="stocksSection" style="display:none;">
        <h2 class="mb-4">Stocks</h2>
        <button class="btn btn-primary mb-3" id="addStockBtn">
          <i class="bi bi-plus-circle"></i> Add New Stock Batch
        </button>
        <div class="table-container">
          <table id="stocksTable" class="display table table-bordered table-striped">
            <thead>
              <tr>
<<<<<<< HEAD
                <th>Batch Number</th>
                <th>Item Code</th>
                <th>Item Name</th>
                <th>Available Quantity</th>
                <th>Original Quantity</th>
                <th>Expiry Date</th>
                <th>Status</th>
                <th>Dispensed</th>
=======
                <th>Batch ID</th>
                <th>Item Code</th>
                <th>Item Name</th>
                <th>Quantity</th>
                <th>Expiry Date</th>
                <th>Status</th>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                <th>Action</th>
              </tr>
            </thead>
            <tbody></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- Add/Edit Stock Modal -->
  <div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
<<<<<<< HEAD
        <div class="modal-header" style="background: linear-gradient(135deg, #6b0d00 0%, #8b1a00 100%); color: white;">
=======
        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
          <h5 class="modal-title" id="addStockModalLabel">
            <i class="bi bi-plus-circle-fill me-2"></i>Add New Stock Batch
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <form id="addStockForm">
            <input type="hidden" id="stockId">
<<<<<<< HEAD
            <input type="hidden" id="stockBatchNumber">
=======
            <input type="hidden" id="stockBatchId">
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            <input type="hidden" id="stockItemCode">
            
            <div class="auto-generated-info">
              <strong><i class="bi bi-info-circle-fill"></i> Auto-Generated IDs</strong>
              <div class="info-details">
<<<<<<< HEAD
                <div><strong>Batch Number:</strong> <span id="displayBatchNumber"></span></div>
=======
                <div><strong>Batch ID:</strong> <span id="displayBatchId"></span></div>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                <div><strong>Item Code:</strong> <span id="displayItemCode"></span></div>
              </div>
            </div>

            <div class="form-section">
              <div class="form-section-title">
                <i class="bi bi-box-seam-fill"></i> Item Information
              </div>
              <div class="mb-3">
                <label for="stockName" class="form-label">
                  Medicine/Item Name <span class="required">*</span>
                </label>
<<<<<<< HEAD
                <div class="autocomplete-wrapper" style="position: relative;">
                  <input type="text" class="form-control" id="stockName" placeholder="Type to search medicines..." autocomplete="off" required>
                  <div id="medicineSuggestions" class="autocomplete-dropdown" style="display: none;"></div>
                </div>
              </div>
              <div class="mb-3">
                <label for="stockSupplier" class="form-label">
                  Supplier Name <span class="required">*</span>
                </label>
                <div class="autocomplete-wrapper" style="position: relative;">
                  <input type="text" class="form-control" id="stockSupplier" placeholder="Type to search suppliers..." autocomplete="off" required>
                  <div id="supplierSuggestions" class="autocomplete-dropdown" style="display: none;"></div>
                </div>
                <div class="invalid-feedback">
                  Supplier name is required. Please enter a valid supplier.
                </div>
=======
                <input type="text" class="form-control" id="stockName" placeholder="e.g., Paracetamol, Amoxicillin" required>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
              </div>
              <div class="mb-3">
                <label for="stockDescription" class="form-label">
                  Description <span class="required">*</span>
                </label>
                <textarea class="form-control" id="stockDescription" rows="3" placeholder="Enter item description, usage, or notes..." required></textarea>
<<<<<<< HEAD
                <small class="text-muted" id="descriptionHint" style="display: none;">Description is auto-filled for existing medicines. Edit only for new medicines.</small>
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
              </div>
            </div>

            <div class="form-section">
              <div class="form-section-title">
                <i class="bi bi-calculator-fill"></i> Quantity Management
              </div>
<<<<<<< HEAD
              <!-- Quantity section for Add mode (three boxes) -->
              <div class="quantity-section" id="addQuantitySection">
=======
              <div class="quantity-section">
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                <div class="quantity-inputs">
                  <div class="quantity-input-group">
                    <label>
                      <i class="bi bi-box"></i> Current Stock
                    </label>
                    <input type="number" class="form-control" id="stockCurrentQty" min="0" value="0" readonly>
                  </div>
<<<<<<< HEAD
                    <div class="quantity-input-group">
                    <label>
                      <i class="bi bi-plus-circle"></i> New Arrivals
                    </label>
                    <input type="number" class="form-control" id="stockNewQty" min="0" placeholder="Enter quantity">
=======
                  <div class="quantity-input-group">
                    <label>
                      <i class="bi bi-plus-circle"></i> New Arrivals
                    </label>
                    <input type="number" class="form-control" id="stockNewQty" min="0" value="0" placeholder="0">
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                  </div>
                  <div class="quantity-input-group">
                    <label>
                      <i class="bi bi-check-circle"></i> Total Quantity
                    </label>
                    <input type="number" class="form-control quantity-total-input" id="stockTotalQty" min="0" readonly>
                  </div>
                </div>
              </div>
<<<<<<< HEAD
              <!-- Quantity section for Edit mode (single input) -->
              <div class="quantity-section" id="editQuantitySection" style="display: none;">
                <div class="mb-3">
                  <label for="stockEditQty" class="form-label">
                    Total Quantity <span class="required">*</span>
                  </label>
                  <input type="number" class="form-control" id="stockEditQty" min="0" placeholder="Enter total quantity for this batch">
                  <small class="text-muted">Edit the total stock quantity for this specific batch</small>
                </div>
              </div>
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            </div>

            <div class="form-section">
              <div class="form-section-title">
                <i class="bi bi-calendar-event-fill"></i> Expiry Information
              </div>
              <div class="mb-3">
                <label for="stockExpiry" class="form-label">
                  Expiry Date <span class="required">*</span>
                </label>
<<<<<<< HEAD
                <div class="input-group">
                  <input type="text" class="form-control" id="stockExpiry" placeholder="YYYY-MM-DD" maxlength="10" required>
                  <button class="btn btn-outline-secondary" type="button" id="stockExpiryCalendarBtn" title="Open Calendar">
                    <i class="bi bi-calendar3"></i>
                  </button>
                </div>
                <div class="invalid-feedback" id="expiryDateError">
                  Please enter a valid date in YYYY-MM-DD format.
                </div>
=======
                <input type="date" class="form-control" id="stockExpiry" required>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
              </div>
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="bi bi-x-circle"></i> Cancel
          </button>
<<<<<<< HEAD
          <button type="button" class="btn btn-primary" id="saveStockBtn" style="background: linear-gradient(135deg, #6b0d00 0%, #8b1a00 100%); border: none;">
=======
          <button type="button" class="btn btn-primary" id="saveStockBtn" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border: none;">
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            <i class="bi bi-check-circle"></i> Save Stock
          </button>
        </div>
      </div>
    </div>
  </div>

  <!-- View Modal -->
  <div class="modal fade" id="viewModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Item Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body" id="viewModalContent"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<<<<<<< HEAD
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script src="../js/logout.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="../admin/js/notifications.js"></script>
  <script>
    let medicines = [];
    let activeTable, archiveTable, stocksTable, lowStockDashTable, expiredDashTable;
=======
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script>
    let medicines = [];
    let activeTable, bodTable, archiveTable, stocksTable, lowStockDashTable, expiredDashTable;
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9

    function ajaxRequest(action, data = {}) {
      return $.ajax({
        url: '',
        method: 'POST',
        data: { action, ...data },
<<<<<<< HEAD
        dataType: 'json',
        timeout: 30000, // 30 second timeout
        error: function(xhr, status, error) {
          console.error('AJAX Error:', {action, status, error, responseText: xhr.responseText});
        }
      });
    }

    // Lazy loading state tracking
    let sectionsLoaded = {
      overview: false,
      medicines: false,
      stocks: false
    };
    
    let tabsLoaded = {
      lowstockDashboard: false,
      expiredDashboard: false,
      active: false,
      archive: false
    };

    function loadMedicines() {
      // Always load data - check visibility when rendering (lazy loading optimization)
      const isMedicinesVisible = $("#medicinesSection").is(':visible');
      const isStocksVisible = $("#stocksSection").is(':visible');
      const isOverviewVisible = $("#inventoryOverviewSection").is(':visible');
      
      // Show loading indicator only for visible sections
      if (isMedicinesVisible && activeTable) {
        activeTable.processing(true);
      }
      if (isStocksVisible && stocksTable) {
        stocksTable.processing(true);
      }
      
      ajaxRequest('getMedicines').done(function(response) {
        if (response.success) {
          medicines = response.data;
          
          // Check visibility again after data loads (in case user switched tabs)
          const currentMedicinesVisible = $("#medicinesSection").is(':visible');
          const currentStocksVisible = $("#stocksSection").is(':visible');
          const currentOverviewVisible = $("#inventoryOverviewSection").is(':visible');
          
          // Only render what's currently visible (lazy loading)
          if (currentOverviewVisible) {
            updateDashboardCards();
            // Only update tables if their tabs are active
            // Mark tabs as loaded if they're active (for initial page load)
            if ($('#lowstockDashboard').hasClass('active')) {
              if (!tabsLoaded.lowstockDashboard) {
                tabsLoaded.lowstockDashboard = true;
              }
              updateDashboardTables();
            }
            if ($('#expiredDashboard').hasClass('active')) {
              if (!tabsLoaded.expiredDashboard) {
                tabsLoaded.expiredDashboard = true;
              }
              updateDashboardTables();
            }
          }
          
          // Render medicines if section is visible and loaded
          if (currentMedicinesVisible && sectionsLoaded.medicines) {
            renderMedicines();
          }
          
          // Render stocks if section is visible and loaded
          if (currentStocksVisible && sectionsLoaded.stocks) {
            renderStocks();
          }
        }
      }).always(function() {
        // Hide loading indicators
        if (activeTable) activeTable.processing(false);
        if (stocksTable) stocksTable.processing(false);
      });
    }


=======
        dataType: 'json'
      });
    }

    function loadMedicines() {
      ajaxRequest('getMedicines').done(function(response) {
        if (response.success) {
          medicines = response.data;
          renderMedicines();
          renderStocks();
          updateOverview();
          updateDashboardTables();
          updateDashboardCards();
        }
      });
    }

>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    function getDaysUntilExpiry(expiryDate) {
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      const expiry = new Date(expiryDate);
      expiry.setHours(0, 0, 0, 0);
      return Math.floor((expiry - today) / (1000 * 60 * 60 * 24));
    }

    function isExpiredOrNearing(expiryDate) {
      const daysLeft = getDaysUntilExpiry(expiryDate);
      return daysLeft > 0 && daysLeft <= 30;
    }

    function checkAndArchiveExpired() {
      ajaxRequest('archiveExpired').done(function(response) {
        if (response.success && response.affected > 0) {
          console.log(`${response.affected} expired item(s) automatically moved to archive`);
          loadMedicines();
        }
      });
    }

    function updateDashboardCards() {
      const lowStockContainer = $('#lowStockCards');
      const expiredContainer = $('#expiredCards');
      
      lowStockContainer.empty();
      expiredContainer.empty();

      const lowStockItems = medicines.filter(m => m.quantity <= 10 && m.status === 'active');
      if (lowStockItems.length === 0) {
        lowStockContainer.html(`
          <div class="empty-state">
            <i class="bi bi-check-circle"></i>
            <p>All items are well stocked!</p>
          </div>
        `);
      } else {
        lowStockItems.forEach(medicine => {
          const cardClass = medicine.quantity === 0 ? 'critical' : '';
          lowStockContainer.append(`
            <div class="alert-item-card ${cardClass}">
              <div class="alert-item-info">
                <div>
<<<<<<< HEAD
                  <div class="alert-item-name">${medicine.item_name}</div>
                  <div class="alert-item-code">${medicine.batch_number} - ${medicine.item_code}</div>
=======
                  <div class="alert-item-name">${medicine.name}</div>
                  <div class="alert-item-code">${medicine.batchId} - ${medicine.code}</div>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                </div>
                <span class="alert-item-badge ${medicine.quantity === 0 ? 'danger' : 'warning'}">
                  ${medicine.quantity} left
                </span>
              </div>
              <div class="alert-item-details">
                <span><i class="bi bi-box"></i> Dispensed: ${medicine.dispensed}</span>
<<<<<<< HEAD
                <span><i class="bi bi-calendar"></i> Exp: ${medicine.expiry_date}</span>
=======
                <span><i class="bi bi-calendar"></i> Exp: ${medicine.expiry}</span>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
              </div>
            </div>
          `);
        });
      }

      const nearingExpirationItems = medicines.filter(m => {
<<<<<<< HEAD
        const daysLeft = getDaysUntilExpiry(m.expiry_date);
=======
        const daysLeft = getDaysUntilExpiry(m.expiry);
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        return daysLeft > 0 && daysLeft <= 30 && m.status !== 'archive';
      });
      
      if (nearingExpirationItems.length === 0) {
        expiredContainer.html(`
          <div class="empty-state">
            <i class="bi bi-check-circle"></i>
            <p>No items expiring soon!</p>
          </div>
        `);
      } else {
<<<<<<< HEAD
        nearingExpirationItems.sort((a, b) => getDaysUntilExpiry(a.expiry_date) - getDaysUntilExpiry(b.expiry_date));
        nearingExpirationItems.forEach(medicine => {
          const daysLeft = getDaysUntilExpiry(medicine.expiry_date);
          const daysText = `${daysLeft} days left`;
          
          expiredContainer.append(`
            <div class="alert-item-card expired">
              <div class="alert-item-info">
                <div>
                  <div class="alert-item-name">${medicine.item_name}</div>
                  <div class="alert-item-code">${medicine.batch_number} - ${medicine.item_code}</div>
                </div>
                <span class="alert-item-badge danger">
=======
        nearingExpirationItems.sort((a, b) => getDaysUntilExpiry(a.expiry) - getDaysUntilExpiry(b.expiry));
        nearingExpirationItems.forEach(medicine => {
          const daysLeft = getDaysUntilExpiry(medicine.expiry);
          const daysText = `${daysLeft} days left`;
          
          expiredContainer.append(`
            <div class="alert-item-card">
              <div class="alert-item-info">
                <div>
                  <div class="alert-item-name">${medicine.name}</div>
                  <div class="alert-item-code">${medicine.batchId} - ${medicine.code}</div>
                </div>
                <span class="alert-item-badge warning">
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
                  ${daysText}
                </span>
              </div>
              <div class="alert-item-details">
                <span><i class="bi bi-box"></i> Qty: ${medicine.quantity}</span>
<<<<<<< HEAD
                <span><i class="bi bi-calendar"></i> ${medicine.expiry_date}</span>
=======
                <span><i class="bi bi-calendar"></i> ${medicine.expiry}</span>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
              </div>
            </div>
          `);
        });
      }
    }

    function updateDashboardTables() {
<<<<<<< HEAD
      // Ensure tables are initialized before updating
      if (!lowStockDashTable && $('#lowStockDashboardTable').length) {
        lowStockDashTable = initializeDataTableIfNeeded('lowStockDashboardTable', {
          pageLength: 5,
          order: [[0, 'asc']],
          deferRender: true
        });
      }
      if (!expiredDashTable && $('#expiredDashboardTable').length) {
        expiredDashTable = initializeDataTableIfNeeded('expiredDashboardTable', {
          pageLength: 5,
          order: [[5, 'asc']],
          deferRender: true
        });
      }
      
      if (lowStockDashTable) lowStockDashTable.clear();
      if (expiredDashTable) expiredDashTable.clear();
=======
      lowStockDashTable.clear();
      expiredDashTable.clear();
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9

      medicines.filter(m => m.quantity <= 10 && m.status === 'active').forEach(medicine => {
        lowStockDashTable.row.add($(`
          <tr>
<<<<<<< HEAD
            <td>${medicine.batch_number}</td>
            <td>${medicine.item_code}</td>
            <td>${medicine.item_name}</td>
            <td>${medicine.quantity}</td>
            <td>${medicine.dispensed}</td>
            <td>${medicine.expiry_date}</td>
=======
            <td>${medicine.batchId}</td>
            <td>${medicine.code}</td>
            <td>${medicine.name}</td>
            <td>${medicine.quantity}</td>
            <td>${medicine.dispensed}</td>
            <td>${medicine.expiry}</td>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            <td><span class="status-badge status-low">Low Stock</span></td>
          </tr>
        `)[0]);
      });

      medicines.filter(m => {
<<<<<<< HEAD
        const daysLeft = getDaysUntilExpiry(m.expiry_date);
        return daysLeft > 0 && daysLeft <= 30 && m.status !== 'archive';
      }).forEach(medicine => {
        const daysLeft = getDaysUntilExpiry(medicine.expiry_date);
=======
        const daysLeft = getDaysUntilExpiry(m.expiry);
        return daysLeft > 0 && daysLeft <= 30 && m.status !== 'archive';
      }).forEach(medicine => {
        const daysLeft = getDaysUntilExpiry(medicine.expiry);
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        const statusClass = 'status-low';
        const statusText = 'Nearing Expiration';
        const daysText = `${daysLeft} days`;

        expiredDashTable.row.add($(`
          <tr>
<<<<<<< HEAD
            <td>${medicine.batch_number}</td>
            <td>${medicine.item_code}</td>
            <td>${medicine.item_name}</td>
            <td>${medicine.quantity}</td>
            <td>${medicine.dispensed}</td>
            <td>${medicine.expiry_date}</td>
=======
            <td>${medicine.batchId}</td>
            <td>${medicine.code}</td>
            <td>${medicine.name}</td>
            <td>${medicine.quantity}</td>
            <td>${medicine.dispensed}</td>
            <td>${medicine.expiry}</td>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            <td>${daysText}</td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
          </tr>
        `)[0]);
      });

<<<<<<< HEAD
      if (lowStockDashTable) lowStockDashTable.draw();
      if (expiredDashTable) expiredDashTable.draw();
    }

    // Lazy load function to initialize DataTable only when needed
    function initializeDataTableIfNeeded(tableId, options) {
      if ($.fn.DataTable.isDataTable('#' + tableId)) {
        return $('#' + tableId).DataTable();
      }
      return $('#' + tableId).DataTable(options);
    }

    $(document).ready(function() {
      // Initialize navbar state first (before DataTables to prevent layout shifts)
      $("#inventorySubmenu").addClass("show");
      $("#inventoryMain").parent().addClass("active");
      
      // Initialize only overview DataTables immediately (lazy load others)
      const dataTableOptions = {
        pageLength: 5,
        order: [[0, 'asc']],
        deferRender: true,
        processing: true,
        language: {
          processing: '<i class="bi bi-hourglass-split"></i> Loading...'
        }
      };
      
      // Only initialize dashboard tables if overview is visible
      if ($("#inventoryOverviewSection").is(':visible')) {
        lowStockDashTable = initializeDataTableIfNeeded('lowStockDashboardTable', {
          ...dataTableOptions,
          order: [[0, 'asc']]
        });
        expiredDashTable = initializeDataTableIfNeeded('expiredDashboardTable', {
          ...dataTableOptions,
          order: [[5, 'asc']]
        });
        sectionsLoaded.overview = true;
      }

      // Show overview section by default
      $("#inventoryOverviewSection").show();
      $("#medicinesSection").hide();
      $("#stocksSection").hide();

      // Load only overview data initially (lazy load others)
      function loadOverviewData() {
        ajaxRequest('getMedicines').done(function(response) {
          if (response.success) {
            medicines = response.data;
            updateDashboardCards();
            sectionsLoaded.overview = true;
            
            // Check if the "Low Stock Items" tab is active by default and load its data
            if ($('#lowstockDashboard').hasClass('active')) {
              tabsLoaded.lowstockDashboard = true;
              updateDashboardTables();
            }
            // Check if the "Nearing Expiration" tab is active by default and load its data
            if ($('#expiredDashboard').hasClass('active')) {
              tabsLoaded.expiredDashboard = true;
              updateDashboardTables();
            }
          }
        });
      }
      
      // Load overview data with requestIdleCallback for better performance
      if (window.requestIdleCallback) {
        requestIdleCallback(loadOverviewData, { timeout: 2000 });
      } else {
        setTimeout(loadOverviewData, 100);
      }
      
      // Defer archive check to avoid blocking initial render
      setTimeout(function() {
      checkAndArchiveExpired();
      }, 500);

      // Run alert check asynchronously after page load (non-blocking)
      setTimeout(function() {
        ajaxRequest('checkAlerts');
      }, 1000);

      // Auto-check alerts and refresh inventory every 5 minutes (optimized for once-per-day alerts)
      // Only refresh if page is visible and relevant sections are loaded
      // Alerts are only sent once per day per item, so frequent checks aren't needed
      setInterval(function() {
        if (!document.hidden) {
          ajaxRequest('checkAlerts').done(function() {
            // Only reload if relevant sections are visible and loaded
            if ($("#inventoryOverviewSection").is(':visible') && sectionsLoaded.overview) {
              loadMedicines();
            } else if ($("#medicinesSection").is(':visible') && sectionsLoaded.medicines) {
              loadMedicines();
            } else if ($("#stocksSection").is(':visible') && sectionsLoaded.stocks) {
              loadMedicines();
            }
          });
        }
      }, 300000); // 5 minutes - alerts are only sent once per day per item, so frequent checks aren't needed
      
      // Also refresh when page becomes visible (user switches back to tab) - lazy loading
      document.addEventListener('visibilitychange', function() {
        if (!document.hidden) {
          // Only refresh visible sections (lazy loading optimization)
          if ($("#inventoryOverviewSection").is(':visible')) {
            if (!sectionsLoaded.overview) {
              loadOverviewData();
            } else {
              loadMedicines();
            }
          } else if ($("#medicinesSection").is(':visible')) {
            if (!sectionsLoaded.medicines) {
              sectionsLoaded.medicines = true;
              if (!activeTable) {
                activeTable = initializeDataTableIfNeeded('activeTable', dataTableOptions);
              }
              if (!archiveTable) {
                archiveTable = initializeDataTableIfNeeded('archiveTable', dataTableOptions);
              }
              loadMedicines();
            } else {
              loadMedicines();
            }
          } else if ($("#stocksSection").is(':visible')) {
            if (!sectionsLoaded.stocks) {
              sectionsLoaded.stocks = true;
              if (!stocksTable) {
                stocksTable = initializeDataTableIfNeeded('stocksTable', dataTableOptions);
              }
              loadMedicines();
            } else {
              loadMedicines();
            }
          }
          ajaxRequest('checkAlerts');
        }
      });
      
      // Refresh on window focus (user clicks back to browser window) - lazy loading
      $(window).on('focus', function() {
        // Only refresh if sections are visible and loaded
        if ($("#inventoryOverviewSection").is(':visible') && sectionsLoaded.overview) {
          loadMedicines();
        } else if ($("#medicinesSection").is(':visible') && sectionsLoaded.medicines) {
          loadMedicines();
        } else if ($("#stocksSection").is(':visible') && sectionsLoaded.stocks) {
          loadMedicines();
        }
        ajaxRequest('checkAlerts');
      });

      // Supplier autocomplete functionality
      let supplierSearchTimeout;
      
      $('#stockSupplier').on('input', function() {
        const searchTerm = $(this).val().trim();
        const suggestions = $('#supplierSuggestions');
        
        // Clear validation
        if (searchTerm) {
          $(this).removeClass('is-invalid');
        }
        
        // Clear previous timeout
        clearTimeout(supplierSearchTimeout);
        
        if (searchTerm.length < 1) {
          suggestions.hide().empty();
          return;
        }
        
        // Instant search with minimal debounce
        supplierSearchTimeout = setTimeout(function() {
          ajaxRequest('searchSuppliers', { search: searchTerm }).done(function(response) {
            if (response.success && response.suppliers.length > 0) {
              let html = '';
              response.suppliers.forEach(function(supplier) {
                html += `
                  <div class="autocomplete-item" data-supplier="${supplier}">
                    <div class="autocomplete-item-name">${supplier}</div>
                  </div>
                `;
              });
              suggestions.html(html).show();
            } else {
              suggestions.hide().empty();
            }
          });
        }, 100);
      });
      
      // Handle supplier selection
      $(document).on('click', '#supplierSuggestions .autocomplete-item', function() {
        const supplier = $(this).data('supplier');
        $('#stockSupplier').val(supplier);
        $('#supplierSuggestions').hide();
      });
      
      // Hide supplier suggestions when clicking outside
      $(document).on('click', function(e) {
        if (!$(e.target).closest('#stockSupplier').length && 
            !$(e.target).closest('#supplierSuggestions').length) {
          $('#supplierSuggestions').hide();
        }
      });
      
      // Handle supplier keyboard navigation
      $('#stockSupplier').on('keydown', function(e) {
        const suggestions = $('#supplierSuggestions');
        const items = suggestions.find('.autocomplete-item');
        
        if (items.length === 0) return;
        
        const current = items.filter('.highlighted');
        let next;
        
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          if (current.length === 0) {
            next = items.first();
          } else {
            next = current.next();
            if (next.length === 0) next = items.first();
          }
          items.removeClass('highlighted');
          next.addClass('highlighted');
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          if (current.length === 0) {
            next = items.last();
          } else {
            next = current.prev();
            if (next.length === 0) next = items.last();
          }
          items.removeClass('highlighted');
          next.addClass('highlighted');
        } else if (e.key === 'Enter') {
          e.preventDefault();
          if (current.length > 0) {
            current.click();
          }
        } else if (e.key === 'Escape') {
          suggestions.hide();
        }
      });

      // Medicine autocomplete functionality with real-time updates
      let medicineSearchTimeout;
      let medicineDetailsTimeout;
      let selectedMedicine = null;
      let lastSearchedTerm = '';
      
      // Real-time medicine details loading function
      function loadMedicineDetailsRealTime(medicineName) {
        // Clear previous timeout
        clearTimeout(medicineDetailsTimeout);
        
        // Only load if name is not empty and different from last search
        if (!medicineName || medicineName.trim().length < 1) {
          return;
        }
        
        const trimmedName = medicineName.trim();
        if (trimmedName === lastSearchedTerm) {
          return; // Already loaded for this term
        }
        
        // Debounce the API call slightly to avoid too many requests
        medicineDetailsTimeout = setTimeout(function() {
          const currentName = $('#stockName').val().trim();
          if (currentName !== trimmedName) {
            return; // User continued typing, ignore this call
          }
          
          lastSearchedTerm = trimmedName;
          
          // Load medicine details in real-time
          ajaxRequest('getMedicineDetails', { item_name: trimmedName }).done(function(response) {
            // Double-check medicine name still matches (prevent race conditions)
            const currentMedicineName = $('#stockName').val().trim();
            if (currentMedicineName !== trimmedName) {
              return; // Medicine changed during request, ignore response
            }
            
            if (response.success) {
              // Update item code display instantly
              $('#stockItemCode').val(response.item_code);
              $('#displayItemCode').text(response.item_code + (response.exists ? ' (Existing)' : ' (New)'));
              
              // Update current stock instantly
              const currentStock = parseInt(response.current_stock) || 0;
              $('#stockCurrentQty').val(currentStock);
              
              // Auto-populate supplier if exists and field is empty or matches previous value
              if (response.exists && response.supplier) {
                const currentSupplier = $('#stockSupplier').val().trim();
                // Only auto-populate if field is empty or if it matches the medicine's supplier
                if (!currentSupplier || currentSupplier === selectedMedicine?.supplier) {
                  $('#stockSupplier').val(response.supplier);
                }
              }
              
              // Update description - for existing medicines, auto-populate if empty
              const isEditMode = $('#stockId').val() !== '';
              if (response.exists) {
                const currentDescription = $('#stockDescription').val().trim();
                // Auto-populate description if empty or if it matches previous medicine description
                if (!currentDescription || currentDescription === selectedMedicine?.description) {
                  $('#stockDescription').val(response.description || '').prop('readonly', !isEditMode);
                  if (!isEditMode) $('#descriptionHint').show();
                } else if (response.description) {
                  // Keep current description but show hint
                  if (!isEditMode) $('#descriptionHint').show();
                }
              } else {
                // New medicine - allow editing, clear if was from previous medicine
                if (selectedMedicine && selectedMedicine.exists) {
                  $('#stockDescription').val('').prop('readonly', false);
                }
                $('#descriptionHint').hide();
              }
              
              // Update total quantity if new quantity is set
              updateTotalQuantity();
              
              // Update selected medicine object
              selectedMedicine = {
                name: trimmedName,
                code: response.item_code,
                description: response.description || '',
                currentStock: response.current_stock || 0,
                supplier: response.supplier || '',
                exists: response.exists
              };
            }
          }).fail(function() {
            // Silently fail - don't interrupt user experience
            console.log('Real-time medicine details update failed');
          });
        }, 300); // Slightly longer debounce for details to avoid too many API calls
      }
      
      $('#stockName').on('input', function() {
        const searchTerm = $(this).val().trim();
        const suggestions = $('#medicineSuggestions');
        
        // Clear previous timeout
        clearTimeout(medicineSearchTimeout);
        
        if (searchTerm.length < 1) {
          suggestions.hide().empty();
          selectedMedicine = null;
          lastSearchedTerm = '';
          resetMedicineFields();
          return;
        }
        
        // Instant search with minimal debounce for better UX
        medicineSearchTimeout = setTimeout(function() {
          ajaxRequest('searchMedicines', { search: searchTerm }).done(function(response) {
            if (response.success && response.medicines.length > 0) {
              let html = '';
              let exactMatch = null;
              
              response.medicines.forEach(function(med) {
                const isExactMatch = med.item_name.toLowerCase() === searchTerm.toLowerCase();
                if (isExactMatch) {
                  exactMatch = med;
                }
                
                html += `
                  <div class="autocomplete-item ${isExactMatch ? 'exact-match' : ''}" data-name="${med.item_name}" data-code="${med.item_code}" data-description="${med.description || ''}" data-stock="${med.total_quantity || 0}">
                    <div class="autocomplete-item-name">${med.item_name}</div>
                    <div class="autocomplete-item-code">Code: ${med.item_code}</div>
                    <div class="autocomplete-item-stock">Current Stock: ${med.total_quantity || 0}</div>
                  </div>
                `;
              });
              
              suggestions.html(html).show();
              
              // If there's an exact match, auto-populate fields immediately
              if (exactMatch) {
                const medicineName = exactMatch.item_name;
                const itemCode = exactMatch.item_code;
                const description = exactMatch.description || '';
                const currentStock = parseInt(exactMatch.total_quantity) || 0;
                
                // Update fields instantly
                $('#stockItemCode').val(itemCode);
                $('#displayItemCode').text(itemCode + ' (Existing)');
                
                const isEditMode = $('#stockId').val() !== '';
                if (description) {
                  const currentDesc = $('#stockDescription').val().trim();
                  if (!currentDesc || currentDesc === selectedMedicine?.description) {
                    $('#stockDescription').val(description).prop('readonly', !isEditMode);
                    if (!isEditMode) $('#descriptionHint').show();
                  }
                }
                
                $('#stockCurrentQty').val(currentStock);
                $('#stockNewQty').val('');
                updateTotalQuantity();
                
                selectedMedicine = {
                  name: medicineName,
                  code: itemCode,
                  description: description,
                  currentStock: currentStock,
                  exists: true
                };
                
                // Also trigger full details load for complete data (supplier, etc.)
                loadMedicineDetailsRealTime(medicineName);
              } else {
                // No exact match, but still try to load details for partial matches
                // This helps with real-time updates as user types
                if (searchTerm.length >= 3) {
                  loadMedicineDetailsRealTime(searchTerm);
                }
              }
            } else {
              suggestions.hide().empty();
              // Try to load details even if no suggestions (might be new medicine)
              if (searchTerm.length >= 3) {
                loadMedicineDetailsRealTime(searchTerm);
              } else {
                // Reset fields if search term is too short
                lastSearchedTerm = '';
                if (!selectedMedicine || selectedMedicine.name !== searchTerm) {
                  $('#displayItemCode').text('Will be auto-generated on save');
                  $('#stockCurrentQty').val(0);
                  $('#stockDescription').val('').prop('readonly', false);
                  $('#descriptionHint').hide();
                  updateTotalQuantity();
                }
              }
            }
          });
        }, 100);
      });
      
      // Handle selection from medicine autocomplete
      $(document).on('click', '#medicineSuggestions .autocomplete-item', function() {
        const medicineName = $(this).data('name');
        const itemCode = $(this).data('code');
        const description = $(this).data('description') || '';
        const currentStock = parseInt($(this).data('stock')) || 0;
        
        // Update the input field
        $('#stockName').val(medicineName);
        $('#medicineSuggestions').hide();
        
        // Immediately populate fields from clicked item (instant feedback)
        selectedMedicine = {
          name: medicineName,
          code: itemCode,
          description: description,
          currentStock: currentStock,
          exists: true
        };
        
        $('#stockItemCode').val(itemCode);
        $('#displayItemCode').text(itemCode + ' (Existing)');
        
        // Immediately populate description and stock from clicked item
        const isEditMode = $('#stockId').val() !== '';
        if (description) {
          $('#stockDescription').val(description).prop('readonly', !isEditMode);
          if (!isEditMode) $('#descriptionHint').show();
        } else {
          $('#stockDescription').val('').prop('readonly', !isEditMode);
          if (!isEditMode) $('#descriptionHint').show();
        }
        
        $('#stockCurrentQty').val(currentStock);
        $('#stockNewQty').val('');
        updateTotalQuantity();
        
        // Update last searched term to prevent duplicate API call
        lastSearchedTerm = medicineName;
        
        // Load full medicine details from database immediately (for supplier, etc.)
        // This ensures we have the most up-to-date data
        loadMedicineDetailsRealTime(medicineName);
      });
      
      // Hide medicine suggestions when clicking outside
      $(document).on('click', function(e) {
        if (!$(e.target).closest('#stockName').length && 
            !$(e.target).closest('#medicineSuggestions').length) {
          $('#medicineSuggestions').hide();
        }
      });
      
      // Handle keyboard navigation
      $('#stockName').on('keydown', function(e) {
        const suggestions = $('#medicineSuggestions');
        const items = suggestions.find('.autocomplete-item');
        
        if (items.length === 0) return;
        
        const current = items.filter('.highlighted');
        let next;
        
        if (e.key === 'ArrowDown') {
          e.preventDefault();
          if (current.length === 0) {
            next = items.first();
          } else {
            next = current.next();
            if (next.length === 0) next = items.first();
          }
          items.removeClass('highlighted');
          next.addClass('highlighted');
        } else if (e.key === 'ArrowUp') {
          e.preventDefault();
          if (current.length === 0) {
            next = items.last();
          } else {
            next = current.prev();
            if (next.length === 0) next = items.last();
          }
          items.removeClass('highlighted');
          next.addClass('highlighted');
        } else if (e.key === 'Enter') {
          e.preventDefault();
          if (current.length > 0) {
            current.click();
          }
        } else if (e.key === 'Escape') {
          suggestions.hide();
        }
      });
      
      // Function to load medicine details (optimized for speed)
      // Legacy function - kept for backward compatibility, now uses real-time function
      function loadMedicineDetails(medicineName) {
        loadMedicineDetailsRealTime(medicineName);
      }
      
      // Function to reset medicine fields
      function resetMedicineFields() {
        $('#stockItemCode').val('');
        $('#displayItemCode').text('Will be auto-generated on save');
        $('#stockCurrentQty').val(0);
        $('#stockDescription').val('').prop('readonly', false);
        $('#descriptionHint').hide();
        $('#stockNewQty').val(''); // Empty by default
        $('#stockTotalQty').val('');
        selectedMedicine = null;
      }
      
      // Update total quantity in real-time when new quantity changes
      $('#stockNewQty').on('input', function() {
        updateTotalQuantity();
      });
      
      // Also update on keyup for better real-time feedback
      $('#stockNewQty').on('keyup', function() {
        updateTotalQuantity();
      });
      
      // Function to update total quantity (real-time calculation)
      function updateTotalQuantity() {
        const current = parseInt($('#stockCurrentQty').val()) || 0;
        const newQtyValue = $('#stockNewQty').val().trim();
        const newQty = newQtyValue === '' ? 0 : parseInt(newQtyValue) || 0;
        const total = current + newQty;
        $('#stockTotalQty').val(total > 0 ? total : '');
      }
      
      // Clear medicine selection when name is cleared
      $('#stockName').on('input', function() {
        if ($(this).val().trim() === '') {
          resetMedicineFields();
=======
      lowStockDashTable.draw();
      expiredDashTable.draw();
    }

    $(document).ready(function() {
      activeTable = $('#activeTable').DataTable({ pageLength: 5, order: [[0, 'asc']] });
      bodTable = $('#bodTable').DataTable({ pageLength: 5, order: [[0, 'asc']] });
      archiveTable = $('#archiveTable').DataTable({ pageLength: 5, order: [[0, 'asc']] });
      stocksTable = $('#stocksTable').DataTable({ pageLength: 5, order: [[0, 'asc']] });
      lowStockDashTable = $('#lowStockDashboardTable').DataTable({ pageLength: 5, order: [[0, 'asc']] });
      expiredDashTable = $('#expiredDashboardTable').DataTable({ pageLength: 5, order: [[5, 'asc']] });

      loadMedicines();
      checkAndArchiveExpired();

      $('#stockNewQty').on('input', function() {
        const current = parseInt($('#stockCurrentQty').val()) || 0;
        const newQty = parseInt($(this).val()) || 0;
        $('#stockTotalQty').val(current + newQty);
      });

      $('#stockName').on('blur', function() {
        const medicineName = $(this).val().trim();
        if (medicineName && !$('#stockId').val()) {
          ajaxRequest('getItemCode', { name: medicineName }).done(function(response) {
            if (response.success) {
              $('#stockItemCode').val(response.code);
              $('#displayItemCode').text(response.code);
            }
          });
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        }
      });

      $("#inventoryMain").on("click", function(e) {
        e.preventDefault();
<<<<<<< HEAD
        // On inventory page, clicking main link shows overview and keeps submenu open
        $("#inventorySubmenu").addClass("show");
        $("#inventoryMain").parent().addClass("active");
        $("#inventoryOverviewSection").show();
        $("#medicinesSection").hide();
        $("#stocksSection").hide();
        // Don't block with archive check on every click - run it asynchronously
        setTimeout(function() {
        checkAndArchiveExpired();
        }, 300);
=======
        $("#inventorySubmenu").toggleClass("show");
        $("#inventoryOverviewSection").show();
        $("#medicinesSection").hide();
        $("#stocksSection").hide();
        checkAndArchiveExpired();
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
      });

      $("#medicineTab").on("click", function(e) {
        e.preventDefault();
        $("#inventoryOverviewSection").hide();
        $("#medicinesSection").show();
        $("#stocksSection").hide();
<<<<<<< HEAD
        // Mark active tab
        $("#medicineTab").addClass("active");
        $("#stockTab").removeClass("active");
        
        // Lazy load medicines section data and tables
        if (!sectionsLoaded.medicines) {
          sectionsLoaded.medicines = true;
          // Initialize DataTables for medicines section
          if (!activeTable) {
            activeTable = initializeDataTableIfNeeded('activeTable', dataTableOptions);
          }
          if (!archiveTable) {
            archiveTable = initializeDataTableIfNeeded('archiveTable', dataTableOptions);
          }
        }
        
        // Always load/refresh data when tab is clicked
        if (medicines.length === 0) {
          // No data yet, load it
          loadMedicines();
        } else {
          // Data exists, render immediately and refresh in background
          renderMedicines();
          // Refresh data in background to ensure it's up to date
          loadMedicines();
        }
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
      });

      $("#stockTab").on("click", function(e) {
        e.preventDefault();
        $("#inventoryOverviewSection").hide();
        $("#medicinesSection").hide();
        $("#stocksSection").show();
<<<<<<< HEAD
        // Mark active tab
        $("#stockTab").addClass("active");
        $("#medicineTab").removeClass("active");
        
        // Lazy load stocks section data and tables
        if (!sectionsLoaded.stocks) {
          sectionsLoaded.stocks = true;
          // Initialize DataTable for stocks section
          if (!stocksTable) {
            stocksTable = initializeDataTableIfNeeded('stocksTable', dataTableOptions);
          }
        }
        
        // Always load/refresh data when tab is clicked
        if (medicines.length === 0) {
          // No data yet, load it
          loadMedicines();
        } else {
          // Data exists, render immediately and refresh in background
          renderStocks();
          // Refresh data in background to ensure it's up to date
          loadMedicines();
        }
      });
      
      // Lazy load dashboard tables when tabs are clicked
      $('button[data-bs-toggle="tab"][data-bs-target="#lowstockDashboard"]').on('shown.bs.tab', function() {
        if (!tabsLoaded.lowstockDashboard) {
          tabsLoaded.lowstockDashboard = true;
        }
        // Always update tables when tab is shown, in case data was refreshed
        if (medicines.length > 0) {
          updateDashboardTables();
        } else {
          // If no data, load it first
          loadMedicines();
        }
      });
      
      $('button[data-bs-toggle="tab"][data-bs-target="#expiredDashboard"]').on('shown.bs.tab', function() {
        if (!tabsLoaded.expiredDashboard) {
          tabsLoaded.expiredDashboard = true;
        }
        // Always update tables when tab is shown, in case data was refreshed
        if (medicines.length > 0) {
          updateDashboardTables();
        } else {
          // If no data, load it first
          loadMedicines();
        }
      });
      
      // Lazy load medicines table tabs - ensure data is loaded when tabs are shown
      $('button[data-bs-toggle="tab"][data-bs-target="#active"]').on('shown.bs.tab', function() {
        if (!tabsLoaded.active) {
          tabsLoaded.active = true;
          if (medicines.length > 0 && sectionsLoaded.medicines) {
            renderMedicines();
          } else if (sectionsLoaded.medicines) {
            // Data not loaded yet, load it
            loadMedicines();
          }
        } else if (medicines.length > 0) {
          // Tab already loaded, just re-render
          renderMedicines();
        }
      });
      
      $('button[data-bs-toggle="tab"][data-bs-target="#archive"]').on('shown.bs.tab', function() {
        // Ensure archive table is initialized when tab is shown
        if (!archiveTable && $('#archiveTable').length) {
          archiveTable = initializeDataTableIfNeeded('archiveTable', {
            pageLength: 5,
            order: [[0, 'asc']],
            deferRender: true,
            processing: true
          });
        }
        
        if (!tabsLoaded.archive) {
          tabsLoaded.archive = true;
          if (medicines.length > 0 && sectionsLoaded.medicines) {
            renderMedicines();
          } else if (sectionsLoaded.medicines) {
            // Data not loaded yet, load it
            loadMedicines();
          }
        } else if (medicines.length > 0) {
          // Tab already loaded, just re-render to ensure archive items are shown
          renderMedicines();
        } else {
          // No data, load it
          loadMedicines();
        }
=======
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
      });

      $('#addStockBtn').on('click', function(e) {
        e.preventDefault();
        $('#addStockModalLabel').html('<i class="bi bi-plus-circle-fill me-2"></i>Add New Stock Batch');
        $('#addStockForm')[0].reset();
        $('#stockId').val('');
<<<<<<< HEAD
        resetMedicineFields();
        $('#stockSupplier').removeClass('is-invalid');
        $('#stockExpiry').removeClass('is-invalid');
        
        // Show add quantity section, hide edit quantity section
        $('#addQuantitySection').show();
        $('#editQuantitySection').hide();
        // Make sure we're using the add mode quantity input
        $('#stockTotalQty').attr('id', 'stockTotalQty');
        
        ajaxRequest('getNextBatchNumber').done(function(response) {
          if (response.success) {
            $('#stockBatchNumber').val(response.batch_number);
            $('#displayBatchNumber').text(response.batch_number);
=======
        $('#stockCurrentQty').val(0);
        $('#stockNewQty').val(0);
        $('#stockTotalQty').val(0);
        
        ajaxRequest('getNextBatchId').done(function(response) {
          if (response.success) {
            $('#stockBatchId').val(response.batchId);
            $('#displayBatchId').text(response.batchId);
            $('#displayItemCode').text('Will be assigned based on medicine name');
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
          }
        });
        
        new bootstrap.Modal(document.getElementById('addStockModal')).show();
      });

<<<<<<< HEAD
      // Function to validate expiry date format strictly
      function validateExpiryDate(dateString) {
        if (!dateString) {
          return { valid: false, error: 'Expiry date is required' };
        }

        // Strict format validation: YYYY-MM-DD
        const datePattern = /^(\d{4})-(\d{2})-(\d{2})$/;
        
        if (!datePattern.test(dateString)) {
          return { 
            valid: false, 
            error: 'Date must be in YYYY-MM-DD format (4-digit year, 2-digit month, 2-digit day)' 
          };
        }

        // Extract components
        const dateParts = dateString.match(datePattern);
        const year = dateParts[1];
        const month = dateParts[2];
        const day = dateParts[3];

        // Validate year: exactly 4 digits (prevent 6+ digits)
        if (year.length !== 4) {
          return { 
            valid: false, 
            error: 'Year must be exactly 4 digits (e.g., 2024)' 
          };
        }

        const yearNum = parseInt(year);
        if (isNaN(yearNum) || yearNum < 1900 || yearNum > 2100) {
          return { 
            valid: false, 
            error: 'Year must be between 1900 and 2100 (4 digits)' 
          };
        }

        // Validate month: exactly 2 digits (01-12)
        if (month.length !== 2) {
          return { 
            valid: false, 
            error: 'Month must be exactly 2 digits (e.g., 01, 02, ..., 12)' 
          };
        }

        const monthNum = parseInt(month);
        if (isNaN(monthNum) || monthNum < 1 || monthNum > 12) {
          return { 
            valid: false, 
            error: 'Month must be between 01 and 12 (2 digits)' 
          };
        }

        // Validate day: exactly 2 digits (01-31)
        if (day.length !== 2) {
          return { 
            valid: false, 
            error: 'Day must be exactly 2 digits (e.g., 01, 02, ..., 31)' 
          };
        }

        const dayNum = parseInt(day);
        if (isNaN(dayNum) || dayNum < 1 || dayNum > 31) {
          return { 
            valid: false, 
            error: 'Day must be between 01 and 31 (2 digits)' 
          };
        }

        // Validate day is valid for the month
        const daysInMonth = new Date(yearNum, monthNum, 0).getDate();
        if (dayNum > daysInMonth) {
          return { 
            valid: false, 
            error: `Day must be between 01 and ${daysInMonth.toString().padStart(2, '0')} for the selected month` 
          };
        }

        // Validate the date is actually valid (e.g., not Feb 30)
        const dateObj = new Date(yearNum, monthNum - 1, dayNum);
        if (dateObj.getFullYear() !== yearNum || 
            dateObj.getMonth() !== (monthNum - 1) || 
            dateObj.getDate() !== dayNum) {
          return { 
            valid: false, 
            error: 'Invalid date. Please check the day, month, and year combination.' 
          };
        }

        return { valid: true };
      }

      // Calendar button click handler - creates a temporary date input to use native picker
      $('#stockExpiryCalendarBtn').on('click', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        // Save current scroll position
        const savedScrollTop = window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop;
        const savedScrollLeft = window.pageXOffset || document.documentElement.scrollLeft || document.body.scrollLeft;
        
        // Get button position relative to viewport
        const button = this;
        const buttonRect = button.getBoundingClientRect();
        
        // Create a temporary date input positioned at the button location
        const tempDateInput = document.createElement('input');
        tempDateInput.type = 'date';
        tempDateInput.id = 'tempDatePicker';
        tempDateInput.style.cssText = `
          position: fixed;
          top: ${buttonRect.bottom + 2}px;
          left: ${buttonRect.left}px;
          width: ${buttonRect.width}px;
          height: 1px;
          opacity: 0.01;
          z-index: 1050;
          pointer-events: auto;
        `;
        document.body.appendChild(tempDateInput);
        
        // Set current date if available
        const currentDate = $('#stockExpiry').val();
        if (currentDate && /^\d{4}-\d{2}-\d{2}$/.test(currentDate)) {
          tempDateInput.value = currentDate;
        }
        
        // Function to restore scroll position
        const restoreScroll = function() {
          window.scrollTo(savedScrollLeft, savedScrollTop);
          if (document.documentElement) {
            document.documentElement.scrollTop = savedScrollTop;
            document.documentElement.scrollLeft = savedScrollLeft;
          }
          if (document.body) {
            document.body.scrollTop = savedScrollTop;
            document.body.scrollLeft = savedScrollLeft;
          }
        };
        
        // Prevent scrolling by restoring position immediately
        const preventScroll = function() {
          restoreScroll();
        };
        
        // Add scroll prevention listener
        const scrollHandler = function(e) {
          e.preventDefault();
          restoreScroll();
          return false;
        };
        
        // Temporarily disable scroll
        const originalOverflow = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        
        // Trigger the date picker
        setTimeout(function() {
          restoreScroll();
          
          // Try to use showPicker API (modern browsers)
          if (tempDateInput.showPicker) {
            try {
              tempDateInput.showPicker();
            } catch (err) {
              // Fallback to focus/click
              tempDateInput.focus();
              tempDateInput.click();
            }
          } else {
            // Fallback for older browsers
            tempDateInput.focus();
            tempDateInput.click();
          }
          
          // Restore scroll after a short delay
          setTimeout(function() {
            restoreScroll();
            document.body.style.overflow = originalOverflow;
          }, 50);
        }, 10);
        
        // Handle date selection
        const handleDateChange = function() {
          const selectedDate = tempDateInput.value;
          if (selectedDate) {
            $('#stockExpiry').val(selectedDate);
            $('#stockExpiry').trigger('blur');
            // Validation will be triggered by blur event
          }
          cleanup();
        };
        
        tempDateInput.addEventListener('change', handleDateChange);
        
        // Cleanup function
        const cleanup = function() {
          document.body.style.overflow = originalOverflow;
          restoreScroll();
          if (tempDateInput && tempDateInput.parentNode) {
            tempDateInput.removeEventListener('change', handleDateChange);
            tempDateInput.parentNode.removeChild(tempDateInput);
          }
        };
        
        // Cleanup on outside click
        const outsideClickHandler = function(e) {
          if (!tempDateInput.contains(e.target) && !button.contains(e.target)) {
            cleanup();
            document.removeEventListener('click', outsideClickHandler);
          }
        };
        
        setTimeout(function() {
          document.addEventListener('click', outsideClickHandler);
        }, 100);
        
        // Cleanup after timeout
        setTimeout(cleanup, 10000);
      });

      // Restrict expiry date input to enforce format
      $('#stockExpiry').on('input', function(e) {
        let value = $(this).val();
        const cursorPos = this.selectionStart;
        
        // Remove any non-digit and non-hyphen characters
        value = value.replace(/[^\d-]/g, '');
        
        // Auto-format as user types with strict digit limits
        let formatted = '';
        let digitCount = 0;
        
        for (let i = 0; i < value.length; i++) {
          const char = value[i];
          
          if (/\d/.test(char)) {
            // Year: exactly 4 digits (positions 0-3) - prevent 6+ digits
            if (digitCount < 4) {
              formatted += char;
              digitCount++;
            }
            // Month: exactly 2 digits (positions 5-6)
            else if (digitCount >= 4 && digitCount < 6) {
              if (formatted.length === 4) formatted += '-';
              formatted += char;
              digitCount++;
            }
            // Day: exactly 2 digits (positions 8-9)
            else if (digitCount >= 6 && digitCount < 8) {
              if (formatted.length === 7) formatted += '-';
              formatted += char;
              digitCount++;
            }
            // Stop after 8 digits (YYYY-MM-DD = 4+2+2) - prevents 6+ digit years
            else {
              break;
            }
          } else if (char === '-' && formatted.length === 4 && digitCount === 4) {
            // Add hyphen after year
            formatted += '-';
          } else if (char === '-' && formatted.length === 7 && digitCount === 6) {
            // Add hyphen after month
            formatted += '-';
          }
        }
        
        // Limit to exactly 10 characters (YYYY-MM-DD)
        if (formatted.length > 10) {
          formatted = formatted.substring(0, 10);
        }
        
        $(this).val(formatted);
        
        // Restore cursor position
        setTimeout(() => {
          const newPos = Math.min(cursorPos, formatted.length);
          this.setSelectionRange(newPos, newPos);
        }, 0);
        
        // Validate format in real-time
        if (formatted.length === 10) {
          const validation = validateExpiryDate(formatted);
          if (!validation.valid) {
            $(this).addClass('is-invalid');
            $('#expiryDateError').text(validation.error);
          } else {
            $(this).removeClass('is-invalid');
          }
        } else if (formatted.length > 0) {
          $(this).removeClass('is-invalid');
        }
      });

      // Prevent typing more than allowed digits
      $('#stockExpiry').on('keydown', function(e) {
        const value = $(this).val();
        const cursorPos = this.selectionStart;
        
        // Allow navigation and deletion keys
        if ([8, 9, 13, 27, 37, 38, 39, 40, 46].indexOf(e.keyCode) !== -1 ||
            (e.keyCode === 65 && e.ctrlKey === true) || // Ctrl+A
            (e.keyCode >= 35 && e.keyCode <= 40)) { // Home, End, Arrow keys
          return;
        }
        
        // Count digits in current value
        const digitCount = (value.match(/\d/g) || []).length;
        
        // Prevent typing if we've reached the limit (8 digits = 4 year + 2 month + 2 day)
        // This prevents 6+ digit years
        if (digitCount >= 8 && /\d/.test(String.fromCharCode(e.keyCode))) {
          e.preventDefault();
          return false;
        }
      });

      // Validate on blur
      $('#stockExpiry').on('blur', function() {
        const expiry_date = $(this).val();
        
        if (!expiry_date) {
          $(this).removeClass('is-invalid');
          return;
        }

        const validation = validateExpiryDate(expiry_date);
        
        if (!validation.valid) {
          $(this).addClass('is-invalid');
          $('#expiryDateError').text(validation.error);
        } else {
          $(this).removeClass('is-invalid');
        }
      });

      // Prevent paste of invalid formats
      $('#stockExpiry').on('paste', function(e) {
        e.preventDefault();
        const pastedText = (e.originalEvent || e).clipboardData.getData('text');
        
        // Try to extract date from pasted text
        const dateMatch = pastedText.match(/(\d{4})[-\/](\d{2})[-\/](\d{2})/);
        if (dateMatch) {
          const formatted = `${dateMatch[1]}-${dateMatch[2]}-${dateMatch[3]}`;
          $(this).val(formatted);
          $(this).trigger('blur');
        }
      });

      $('#saveStockBtn').on('click', function() {
        const id = $('#stockId').val();
        const batch_number = $('#stockBatchNumber').val();
        const item_code = $('#stockItemCode').val();
        const item_name = $('#stockName').val().trim();
        const supplier = $('#stockSupplier').val().trim();
        // Use appropriate quantity field based on mode (edit vs add)
        const quantity = id ? 
          (parseInt($('#stockEditQty').val()) || 0) : 
          (parseInt($('#stockTotalQty').val()) || 0);
        const expiry_date = $('#stockExpiry').val();
        const description = $('#stockDescription').val().trim();

        // Validation
        if (!item_name) {
          Swal.fire({
            icon: 'error',
            title: 'Missing Information',
            text: 'Medicine/Item name is required'
          });
          return;
        }

        if (!supplier) {
          $('#stockSupplier').addClass('is-invalid');
          Swal.fire({
            icon: 'error',
            title: 'Missing Information',
            text: 'Supplier name is required'
          });
          return;
        }

        if (!description) {
          Swal.fire({
            icon: 'error',
            title: 'Missing Information',
            text: 'Description is required'
          });
          return;
        }

        if (quantity <= 0) {
          Swal.fire({
            icon: 'error',
            title: 'Invalid Quantity',
            text: 'Please enter a valid quantity greater than 0'
          });
          return;
        }

        if (!expiry_date) {
          $('#stockExpiry').addClass('is-invalid');
          Swal.fire({
            icon: 'error',
            title: 'Missing Information',
            text: 'Expiry date is required'
          });
          return;
        }

        // Validate expiry date format strictly
        const dateValidation = validateExpiryDate(expiry_date);
        if (!dateValidation.valid) {
          $('#stockExpiry').addClass('is-invalid');
          $('#expiryDateError').text(dateValidation.error);
          Swal.fire({
            icon: 'error',
            title: 'Invalid Date Format',
            text: dateValidation.error
          });
          return;
        }

        // Clear validation if all checks pass
        $('#stockExpiry').removeClass('is-invalid');

        // Validate batch_number for new entries (item_code will be auto-generated on server)
        if (!id && !batch_number) {
          Swal.fire({
            icon: 'error',
            title: 'Missing Information',
            text: 'Batch number is required. Please try again.'
          });
          return;
        }

        // Show loading
        Swal.fire({
          title: 'Saving...',
          text: 'Please wait while we save the stock information',
          allowOutsideClick: false,
          didOpen: () => {
            Swal.showLoading();
          }
        });

        const action = id ? 'updateMedicine' : 'addMedicine';
        // For new entries, don't send item_code - server will auto-generate it
        // Batch number is already auto-generated and should be present
        const data = id ? 
          { id, item_name, quantity, expiry_date, description, supplier } : 
          { batch_number, item_name, quantity, expiry_date, description, supplier };
        
        // Log data being sent for debugging
        console.log('Sending data:', data);
        
        ajaxRequest(action, data).done(function(response) {
          console.log('Server response:', response);
          
          // Close loading modal first
          Swal.close();
          
          // Validate response
          if (!response) {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: 'No response from server. Please try again.'
            });
            return;
          }
          
          if (response.success) {
            // Close modal immediately for better UX
            const modal = bootstrap.Modal.getInstance(document.getElementById('addStockModal'));
            if (modal) {
              modal.hide();
            }
            $('#addStockForm')[0].reset();
            
            // Show success message with archive notification if applicable
            const successMessage = id 
              ? (response.archived 
                  ? 'Stock updated and automatically moved to archive due to expired date.' 
                  : 'Stock updated successfully')
              : `Stock added successfully<br><small>Item Code: ${response.item_code || 'N/A'}</small>`;
            
            Swal.fire({
              icon: 'success',
              title: 'Success!',
              html: successMessage,
              timer: response.archived ? 3000 : 2000,
              showConfirmButton: false
            });
            
            // Immediately reload data to reflect status changes (especially if archived)
            // Use shorter timeout for updates to ensure real-time reflection
            setTimeout(function() {
              // Force reload medicines data to get updated status
              loadMedicines();
            }, 50);
            
            // If item was archived, immediately update visible tables
            if (response.archived) {
              // Force immediate re-render of visible sections after data reloads
              // Wait for loadMedicines to complete first
              setTimeout(function() {
                const isMedicinesVisible = $("#medicinesSection").is(':visible');
                const isStocksVisible = $("#stocksSection").is(':visible');
                
                // Mark sections as loaded to ensure rendering happens
                if (isMedicinesVisible) {
                  sectionsLoaded.medicines = true;
                }
                if (isStocksVisible) {
                  sectionsLoaded.stocks = true;
                }
                
                // Update medicines table immediately (will move item to archive tab)
                if (isMedicinesVisible && medicines.length > 0) {
                  renderMedicines();
                }
                
                // Update stocks table immediately (will filter out archived/expired item)
                if (isStocksVisible && medicines.length > 0) {
                  renderStocks();
                }
              }, 150);
            }
            
            // Load notifications and check alerts in background (non-blocking)
            setTimeout(function() {
              loadNotifications();
              ajaxRequest('checkAlerts').fail(function() {
                console.log('Alert check failed, but continuing...');
              });
            }, 200);
            
            // Archive any other expired items in background (non-blocking, lowest priority)
            // Only if this item wasn't already archived
            if (!response.archived) {
              setTimeout(function() {
                checkAndArchiveExpired();
              }, 500);
            }
            
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error',
              text: response.error || 'Failed to save stock. Please check your input and try again.'
            });
          }
        }).fail(function(xhr, status, error) {
          console.error('Ajax error:', {xhr, status, error});
          console.error('Response text:', xhr.responseText);
          
          // Close loading modal
          Swal.close();
          
          let errorMessage = 'An error occurred while saving. Please try again.';
          
          // Handle timeout
          if (status === 'timeout') {
            errorMessage = 'Request timed out. The server may be busy. Please try again.';
          } else if (status === 'parsererror') {
            errorMessage = 'Invalid response from server. Please check server logs.';
          } else {
            // Try to parse error response
            try {
              if (xhr.responseText) {
                const errorResponse = JSON.parse(xhr.responseText);
                if (errorResponse.error) {
                  errorMessage = errorResponse.error;
                } else if (errorResponse.message) {
                  errorMessage = errorResponse.message;
                }
              }
            } catch(e) {
              // If response is not JSON, check if it's a short error message
              if (xhr.responseText && xhr.responseText.length < 500 && !xhr.responseText.includes('<html')) {
                errorMessage = xhr.responseText.substring(0, 200);
              } else if (xhr.status === 500) {
                errorMessage = 'Internal server error. Please contact administrator.';
              } else if (xhr.status === 0) {
                errorMessage = 'Network error. Please check your connection.';
              }
            }
          }
          
          Swal.fire({
            icon: 'error',
            title: 'Error',
            text: errorMessage,
            confirmButtonText: 'OK'
          });
=======
      $('#saveStockBtn').on('click', function() {
        const id = $('#stockId').val();
        const batchId = $('#stockBatchId').val();
        const code = $('#stockItemCode').val();
        const name = $('#stockName').val().trim();
        const quantity = parseInt($('#stockTotalQty').val()) || 0;
        const expiry = $('#stockExpiry').val();
        const description = $('#stockDescription').val();

        if (!name || quantity < 0 || !expiry || !description) {
          alert('Please fill all required fields');
          return;
        }

        const action = id ? 'updateMedicine' : 'addMedicine';
        const data = id ? { id, name, quantity, expiry, description } : { batchId, code, name, quantity, expiry, description };
        
        ajaxRequest(action, data).done(function(response) {
          if (response.success) {
            $('#addStockForm')[0].reset();
            bootstrap.Modal.getInstance(document.getElementById('addStockModal')).hide();
            loadMedicines();
            checkAndArchiveExpired();
          } else {
            alert('Error: ' + response.error);
          }
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        });
      });

      $('#sortBy').on('change', function() { sortMedicines(); });

      $(document).on('click', '.view-btn', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const medicine = medicines.find(m => m.id == id);
        if (medicine) {
          const statusClass = getStatusClass(medicine);
          const statusText = getStatusText(medicine);
          $('#viewModalContent').html(`
            <div class="row">
<<<<<<< HEAD
              <div class="col-md-6 mb-3"><strong>Batch Number:</strong><br>${medicine.batch_number}</div>
              <div class="col-md-6 mb-3"><strong>Item Code:</strong><br>${medicine.item_code}</div>
              <div class="col-md-6 mb-3"><strong>Item Name:</strong><br>${medicine.item_name}</div>
              <div class="col-md-6 mb-3"><strong>Available Quantity:</strong><br><span style="font-weight: bold; color: ${medicine.quantity <= 10 ? '#dc3545' : '#28a745'}">${medicine.quantity}</span></div>
              <div class="col-md-6 mb-3"><strong>Total Dispensed:</strong><br>${medicine.dispensed || 0}</div>
              <div class="col-md-6 mb-3"><strong>Original Stock:</strong><br>${(medicine.quantity || 0) + (medicine.dispensed || 0)}</div>
              <div class="col-md-6 mb-3"><strong>Expiry Date:</strong><br>${medicine.expiry_date}</div>
              <div class="col-md-6 mb-3"><strong>Supplier:</strong><br>${medicine.supplier || 'N/A'}</div>
              <div class="col-md-6 mb-3"><strong>Status:</strong><br>${getStatusBadges(medicine)}</div>
=======
              <div class="col-md-6 mb-3"><strong>Batch ID:</strong><br>${medicine.batchId}</div>
              <div class="col-md-6 mb-3"><strong>Item Code:</strong><br>${medicine.code}</div>
              <div class="col-md-6 mb-3"><strong>Item Name:</strong><br>${medicine.name}</div>
              <div class="col-md-6 mb-3"><strong>Quantity:</strong><br>${medicine.quantity}</div>
              <div class="col-md-6 mb-3"><strong>Dispensed:</strong><br>${medicine.dispensed}</div>
              <div class="col-md-6 mb-3"><strong>Expiry Date:</strong><br>${medicine.expiry}</div>
              <div class="col-md-6 mb-3"><strong>Status:</strong><br><span class="status-badge ${statusClass}">${statusText}</span></div>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
              <div class="col-12 mb-3"><strong>Description:</strong><br>${medicine.description}</div>
            </div>
          `);
          new bootstrap.Modal(document.getElementById('viewModal')).show();
        }
      });

      $(document).on('click', '.edit-stock-btn', function(e) {
        e.preventDefault();
        const id = $(this).data('id');
        const medicine = medicines.find(m => m.id == id);
        if (medicine) {
          $('#addStockModalLabel').html('<i class="bi bi-pencil-square me-2"></i>Edit Stock');
          $('#stockId').val(medicine.id);
<<<<<<< HEAD
          $('#stockBatchNumber').val(medicine.batch_number);
          $('#stockItemCode').val(medicine.item_code);
          $('#stockName').val(medicine.item_name);
          $('#stockSupplier').val(medicine.supplier || '');
          $('#stockExpiry').val(medicine.expiry_date);
          $('#stockDescription').val(medicine.description);
          
          // Make description editable for edit mode
          $('#stockDescription').prop('readonly', false);
          $('#descriptionHint').hide();
          
          // Show edit quantity section, hide add quantity section
          $('#addQuantitySection').hide();
          $('#editQuantitySection').show();
          $('#stockEditQty').val(medicine.quantity);
          
          $('#stockSupplier').removeClass('is-invalid');
          
          $('#displayBatchNumber').text(medicine.batch_number);
          $('#displayItemCode').text(medicine.item_code);
=======
          $('#stockBatchId').val(medicine.batchId);
          $('#stockItemCode').val(medicine.code);
          $('#stockName').val(medicine.name);
          $('#stockCurrentQty').val(medicine.quantity);
          $('#stockNewQty').val(0);
          $('#stockTotalQty').val(medicine.quantity);
          $('#stockExpiry').val(medicine.expiry);
          $('#stockDescription').val(medicine.description);
          
          $('#displayBatchId').text(medicine.batchId);
          $('#displayItemCode').text(medicine.code);
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
          
          new bootstrap.Modal(document.getElementById('addStockModal')).show();
        }
      });

<<<<<<< HEAD
    });

    function getStatusClass(medicine) {
      if (medicine.status === 'archive') {
        // Check if it's expired (past expiry date)
        const expiryDate = new Date(medicine.expiry_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        expiryDate.setHours(0, 0, 0, 0);
        if (expiryDate < today) {
          return 'status-expired';
        }
        return 'status-inactive';
      }
      // Check if active item is expired (shouldn't happen, but as fallback)
      const expiryDate = new Date(medicine.expiry_date);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      expiryDate.setHours(0, 0, 0, 0);
      
      if (expiryDate < today) {
        return 'status-expired';
      }
      
      // Check if nearing expiry (within 30 days)
      const daysUntilExpiry = Math.floor((expiryDate - today) / (1000 * 60 * 60 * 24));
      const isNearingExpiry = daysUntilExpiry <= 30 && daysUntilExpiry > 0;
      const isLowStock = medicine.quantity <= 10;
      
      // If both conditions apply, use combined status
      if (isNearingExpiry && isLowStock) {
        return 'status-near-low';
      }
      
      if (isNearingExpiry) {
        return 'status-near';
      }
      
      if (isLowStock) {
        return 'status-low';
      }
      
=======
      $('#toggleDetailedView').on('click', function() {
        const detailedSection = $('#detailedTablesSection');
        const isVisible = detailedSection.is(':visible');
        detailedSection.slideToggle();
        $(this).html(isVisible 
          ? '<i class="bi bi-table"></i> View Detailed Tables' 
          : '<i class="bi bi-x-lg"></i> Hide Detailed Tables'
        );
      });

      $("#inventoryMain").click();
    });

    function getStatusClass(medicine) {
      if (medicine.status === 'archive') return 'status-inactive';
      if (medicine.status === 'bod') return 'status-bod';
      if (medicine.quantity <= 10) return 'status-low';
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
      return 'status-active';
    }

    function getStatusText(medicine) {
<<<<<<< HEAD
      if (medicine.status === 'archive') {
        // Check if it's expired (past expiry date)
        const expiryDate = new Date(medicine.expiry_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        expiryDate.setHours(0, 0, 0, 0);
        if (expiryDate < today) {
          return 'Expired';
        }
        return 'Archived';
      }
      // Check if active item is expired (shouldn't happen, but as fallback)
      const expiryDate = new Date(medicine.expiry_date);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      expiryDate.setHours(0, 0, 0, 0);
      
      if (expiryDate < today) {
        return 'Expired';
      }
      
      // Check if nearing expiry (within 30 days)
      const daysUntilExpiry = Math.floor((expiryDate - today) / (1000 * 60 * 60 * 24));
      const isNearingExpiry = daysUntilExpiry <= 30 && daysUntilExpiry > 0;
      const isLowStock = medicine.quantity <= 10;
      
      // If both conditions apply, show combined status
      if (isNearingExpiry && isLowStock) {
        return 'Low Stock & Nearing Expiry';
      }
      
      if (isNearingExpiry) {
        return 'Nearing Expiry';
      }
      
      if (isLowStock) {
        return 'Low Stock';
      }
      
      return 'In Stock';
    }

    // Helper function to generate status badges HTML (supports multiple badges)
    function getStatusBadges(medicine) {
      if (medicine.status === 'archive') {
        const expiryDate = new Date(medicine.expiry_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        expiryDate.setHours(0, 0, 0, 0);
        if (expiryDate < today) {
          return '<span class="status-badge status-expired">Expired</span>';
        }
        return '<span class="status-badge status-inactive">Archived</span>';
      }
      
      const expiryDate = new Date(medicine.expiry_date);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      expiryDate.setHours(0, 0, 0, 0);
      
      if (expiryDate < today) {
        return '<span class="status-badge status-expired">Expired</span>';
      }
      
      const daysUntilExpiry = Math.floor((expiryDate - today) / (1000 * 60 * 60 * 24));
      const isNearingExpiry = daysUntilExpiry <= 30 && daysUntilExpiry > 0;
      const isLowStock = medicine.quantity <= 10;
      
      let badges = [];
      
      // Add badges separately if both conditions apply
      if (isLowStock) {
        badges.push('<span class="status-badge status-low">Low Stock</span>');
      }
      
      if (isNearingExpiry) {
        badges.push('<span class="status-badge status-near">Nearing Expiry</span>');
      }
      
      // If no badges, show In Stock
      if (badges.length === 0) {
        badges.push('<span class="status-badge status-active">In Stock</span>');
      }
      
      return badges.join(' ');
    }

    function renderMedicines() {
      // Ensure tables are initialized before rendering
      if (!activeTable && $('#activeTable').length) {
        activeTable = initializeDataTableIfNeeded('activeTable', {
          pageLength: 5,
          order: [[0, 'asc']],
          deferRender: true,
          processing: true
        });
      }
      // Always initialize archive table if it exists in DOM, even if tab is not visible
      // This ensures expired items can be added to it
      if (!archiveTable && $('#archiveTable').length) {
        archiveTable = initializeDataTableIfNeeded('archiveTable', {
          pageLength: 5,
          order: [[0, 'asc']],
          deferRender: true,
          processing: true,
          autoWidth: false
        });
      }
      
      if (activeTable) activeTable.clear();
      if (archiveTable) archiveTable.clear();

      let activeCount = 0;
      let archiveCount = 0;

      medicines.forEach(medicine => {
        const statusBadges = getStatusBadges(medicine);
        
        // Check expiry date to ensure expired items are treated as archived
        const expiryDate = new Date(medicine.expiry_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        expiryDate.setHours(0, 0, 0, 0);
        const isExpired = expiryDate < today;
        
        // If expired but still marked as active, treat as archived
        const effectiveStatus = (isExpired || medicine.status === 'archive') ? 'archive' : 'active';
        
        const row = `
          <tr>
            <td>${medicine.batch_number}</td>
            <td>${medicine.item_code}</td>
            <td>${medicine.item_name}</td>
            <td>${medicine.quantity}</td>
            <td>${medicine.dispensed}</td>
            <td>${medicine.expiry_date}</td>
            <td>${statusBadges}</td>
=======
      if (medicine.status === 'archive') return 'Archived';
      if (medicine.status === 'bod') return 'BOD';
      if (medicine.quantity <= 10) return 'Low Stock';
      return 'In Stock';
    }

    function renderMedicines() {
      activeTable.clear();
      bodTable.clear();
      archiveTable.clear();

      medicines.forEach(medicine => {
        const statusClass = getStatusClass(medicine);
        const statusText = getStatusText(medicine);
        const row = `
          <tr>
            <td>${medicine.batchId}</td>
            <td>${medicine.code}</td>
            <td>${medicine.name}</td>
            <td>${medicine.quantity}</td>
            <td>${medicine.dispensed}</td>
            <td>${medicine.expiry}</td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            <td>
              <button class="btn btn-secondary btn-sm view-btn" data-id="${medicine.id}">
                <i class="bi bi-eye"></i> View
              </button>
            </td>
          </tr>
        `;
        
<<<<<<< HEAD
        if (effectiveStatus === 'active' && activeTable) {
          activeTable.row.add($(row)[0]);
          activeCount++;
        } else if (effectiveStatus === 'archive') {
          // Always add to archive table if it exists, regardless of tab visibility
          if (archiveTable) {
            archiveTable.row.add($(row)[0]);
            archiveCount++;
          } else {
            // If archive table doesn't exist yet, log for debugging
            console.warn('Archive table not initialized, but item should be archived:', {
              id: medicine.id,
              name: medicine.item_name,
              expiry: medicine.expiry_date,
              status: medicine.status,
              isExpired: isExpired
            });
          }
        }
      });

      // Draw tables after adding all rows
      if (activeTable) {
        activeTable.draw();
      }
      if (archiveTable) {
        archiveTable.draw();
        // Force redraw to ensure archived items are visible even if tab is not active
        archiveTable.columns.adjust().draw();
      }
      
      // Debug logging
      console.log(`Rendered medicines: ${activeCount} active, ${archiveCount} archived`);
      if (archiveCount > 0 && archiveTable) {
        console.log('Archive table has', archiveTable.rows().count(), 'total rows after render');
      }
      
      // Debug logging
      console.log(`Rendered medicines: ${activeCount} active, ${archiveCount} archived`);
    }

    function renderStocks() {
      // Ensure table is initialized before rendering
      if (!stocksTable && $('#stocksTable').length) {
        stocksTable = initializeDataTableIfNeeded('stocksTable', dataTableOptions);
      }
      
      if (stocksTable) stocksTable.clear();

      medicines.forEach(medicine => {
        const statusBadges = getStatusBadges(medicine);
        // Calculate original quantity (available + dispensed)
        const originalQuantity = (medicine.quantity || 0) + (medicine.dispensed || 0);
        
        // Check if expired for filtering
        const expiryDate = new Date(medicine.expiry_date);
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        expiryDate.setHours(0, 0, 0, 0);
        const isExpired = expiryDate < today;
        
        const row = `
          <tr>
            <td>${medicine.batch_number}</td>
            <td>${medicine.item_code}</td>
            <td>${medicine.item_name}</td>
            <td><strong style="color: ${medicine.quantity <= 10 ? '#dc3545' : '#28a745'}">${medicine.quantity}</strong></td>
            <td><strong style="color: #6c757d;">${originalQuantity}</strong></td>
            <td>${medicine.expiry_date}</td>
            <td>${statusBadges}</td>
            <td>${medicine.dispensed || 0}</td>
=======
        if (medicine.status === 'active') {
          activeTable.row.add($(row)[0]);
        } else if (medicine.status === 'bod') {
          bodTable.row.add($(row)[0]);
        } else if (medicine.status === 'archive') {
          archiveTable.row.add($(row)[0]);
        }
      });

      activeTable.draw();
      bodTable.draw();
      archiveTable.draw();
    }

    function renderStocks() {
      stocksTable.clear();

      medicines.forEach(medicine => {
        const statusClass = getStatusClass(medicine);
        const statusText = getStatusText(medicine);
        const row = `
          <tr>
            <td>${medicine.batchId}</td>
            <td>${medicine.code}</td>
            <td>${medicine.name}</td>
            <td>${medicine.quantity}</td>
            <td>${medicine.expiry}</td>
            <td><span class="status-badge ${statusClass}">${statusText}</span></td>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
            <td>
              <button class="btn btn-secondary btn-sm view-btn" data-id="${medicine.id}">
                <i class="bi bi-eye"></i> View
              </button>
<<<<<<< HEAD
              <button class="btn btn-primary btn-sm edit-stock-btn" data-id="${medicine.id}" style="margin-left: 5px;">
                <i class="bi bi-pencil-square"></i> Edit
              </button>
            </td>
          </tr>
        `;
        // Only show if status is active AND not expired
        if (medicine.status === 'active' && !isExpired && stocksTable) {
          stocksTable.row.add($(row)[0]);
        }
      });

      if (stocksTable) stocksTable.draw();
=======
            </td>
          </tr>
        `;
        stocksTable.row.add($(row)[0]);
      });

      stocksTable.draw();
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
    }

    function sortMedicines() {
      const sortBy = $('#sortBy').val();
      medicines.sort((a, b) => {
        let compareA, compareB;
        switch(sortBy) {
<<<<<<< HEAD
          case 'batch':
            compareA = a.batch_number;
            compareB = b.batch_number;
            break;
          case 'code':
            compareA = a.item_code;
            compareB = b.item_code;
            break;
          case 'name':
            compareA = a.item_name;
            compareB = b.item_name;
            break;
          case 'quantity':
            compareA = a.quantity;
            compareB = b.quantity;
            break;
          case 'expiry':
            compareA = new Date(a.expiry_date);
            compareB = new Date(b.expiry_date);
            break;
          default:
            compareA = a.batch_number;
            compareB = b.batch_number;
=======
          case 'batch': compareA = a.batchId; compareB = b.batchId; break;
          case 'code': compareA = a.code; compareB = b.code; break;
          case 'name': compareA = a.name; compareB = b.name; break;
          case 'quantity': compareA = a.quantity; compareB = b.quantity; break;
          case 'expiry': compareA = new Date(a.expiry); compareB = new Date(b.expiry); break;
          default: compareA = a.batchId; compareB = b.batchId;
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
        }
        if (compareA < compareB) return -1;
        if (compareA > compareB) return 1;
        return 0;
      });
      renderMedicines();
    }
<<<<<<< HEAD
  </script>
  <script src="../js/mobile-menu.js"></script>
</body>
</html>
=======

    function updateOverview() {
      const total = medicines.length;
      const lowStock = medicines.filter(m => m.quantity <= 10 && m.status === 'active').length;
      const nearingExpiration = medicines.filter(m => isExpiredOrNearing(m.expiry) && m.status !== 'archive').length;
      $("#totalMedicinesCount").text(total);
      $("#lowStockCount").text(lowStock);
      $("#expiredCount").text(nearingExpiration);
    }
  </script>
</body>
>>>>>>> e3e4af906e18ab75d8fadcab962d35be6fcb7fd9
</html>
<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

try {
    switch ($action) {
        case 'getAll':
            $stmt = $pdo->query("SELECT * FROM employees ORDER BY status ASC, last_name ASC");
            $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'employees' => $employees]);
            break;

        case 'getById':
            $id = $_GET['id'] ?? 0;
            $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
            $stmt->execute([$id]);
            $employee = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($employee) {
                echo json_encode(['success' => true, 'employee' => $employee]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Employee not found']);
            }
            break;

        case 'create':
            $data = [
                'first_name' => $_POST['firstName'],
                'middle_name' => $_POST['middleName'] ?? null,
                'last_name' => $_POST['lastName'],
                'birth_date' => $_POST['birthDate'],
                'age' => $_POST['age'],
                'gender' => $_POST['gender'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'],
                'address' => $_POST['address'],
                'role' => $_POST['role'],
                'password' => password_hash($_POST['password'], PASSWORD_DEFAULT),
                'status' => 'active'
            ];

            // Check if email exists
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Email already exists']);
                break;
            }

            $sql = "INSERT INTO employees (first_name, middle_name, last_name, birth_date, age, gender, email, phone, address, role, password, status) 
                    VALUES (:first_name, :middle_name, :last_name, :birth_date, :age, :gender, :email, :phone, :address, :role, :password, :status)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            break;

        case 'update':
            $id = $_POST['id'];
            $data = [
                'id' => $id,
                'first_name' => $_POST['firstName'],
                'middle_name' => $_POST['middleName'] ?? null,
                'last_name' => $_POST['lastName'],
                'birth_date' => $_POST['birthDate'],
                'age' => $_POST['age'],
                'gender' => $_POST['gender'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone'],
                'address' => $_POST['address'],
                'role' => $_POST['role']
            ];

            // Check if email exists for other employees
            $stmt = $pdo->prepare("SELECT id FROM employees WHERE email = ? AND id != ?");
            $stmt->execute([$data['email'], $id]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'error' => 'Email already exists']);
                break;
            }

            $sql = "UPDATE employees SET 
                    first_name = :first_name,
                    middle_name = :middle_name,
                    last_name = :last_name,
                    birth_date = :birth_date,
                    age = :age,
                    gender = :gender,
                    email = :email,
                    phone = :phone,
                    address = :address,
                    role = :role
                    WHERE id = :id";

            // Update password if provided
            if (!empty($_POST['password'])) {
                $data['password'] = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $sql = str_replace("role = :role", "role = :role, password = :password", $sql);
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($data);
            
            echo json_encode(['success' => true]);
            break;

        case 'deactivate':
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE employees SET status = 'inactive' WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'activate':
            $id = $_POST['id'];
            $stmt = $pdo->prepare("UPDATE employees SET status = 'active' WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        case 'delete':
            $id = $_POST['id'];
            $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true]);
            break;

        default:
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
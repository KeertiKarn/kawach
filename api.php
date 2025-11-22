<?php
// Set headers for CORS and JSON response
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// --- 1. Database Configuration (VERIFY THESE) ---
$db_host = 'localhost';
$db_user = 'root'; // Default XAMPP/MAMP user
$db_password = ''; // CHANGE THIS if you set a password for MySQL
$db_name = 'kawach_db'; 

// --- 2. Database Connection ---
try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database connection failed: " . $e->getMessage()]);
    exit();
}

// --- 3. Handle Request ---
$action = $_GET['action'] ?? '';
$data = [];

// Read JSON input for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid JSON input."]);
        exit();
    }
}

switch ($action) {
    case 'register_pilgrim':
        handle_register_pilgrim($pdo, $data);
        break;
    case 'login':
        handle_login($pdo, $data);
        break;
    case 'file_complaint':
        handle_file_complaint($pdo, $data);
        break;
    case 'get_pilgrim_status':
        handle_get_pilgrim_status($pdo, $_GET);
        break;
    case 'get_official_dashboard':
        handle_get_official_dashboard($pdo);
        break;
    case 'assign_task':
        handle_assign_task($pdo, $data);
        break;
    case 'get_worker_tasks':
        handle_get_worker_tasks($pdo, $_GET);
        break;
    case 'complete_task':
        handle_complete_task($pdo, $data);
        break;
    default:
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Invalid API action."]);
        break;
}

// --- 4. API Functions ---

function handle_register_pilgrim($pdo, $data) {
    $name = $data['name'] ?? '';
    $phone = $data['phone'] ?? '';
    $password = $data['password'] ?? '';
    
    if (empty($name) || empty($phone) || empty($password)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "All fields are required for registration."]);
        return;
    }

    // Check if phone number already exists
    $stmt = $pdo->prepare("SELECT user_id FROM users WHERE phone_number = ?");
    $stmt->execute([$phone]);
    if ($stmt->fetch()) {
        http_response_code(409); // Conflict
        echo json_encode(["status" => "error", "message" => "Phone number already registered."]);
        return;
    }

    // Generate a simple user ID (P-X)
    $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'pilgrim'");
    $count = $stmt->fetchColumn() + 101;
    $user_id = "P-" . $count;

    // Password is stored as plain text 'pass' for mock purposes
    $hashed_password = $password; 

    try {
        $stmt = $pdo->prepare("INSERT INTO users (user_id, name, phone_number, password_hash, role) VALUES (?, ?, ?, ?, 'pilgrim')");
        $stmt->execute([$user_id, $name, $phone, $hashed_password]);

        echo json_encode(["status" => "success", "message" => "Registration successful.", "phone" => $phone]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Database insert failed: " . $e->getMessage()]);
    }
}


function handle_login($pdo, $data) {
    $username = $data['username'] ?? ''; // Phone for pilgrim, ID for others
    $password = $data['password'] ?? '';
    $role = $data['role'] ?? '';

    $user = null;

    if ($role === 'pilgrim') {
        $stmt = $pdo->prepare("SELECT user_id, name, phone_number, password_hash FROM users WHERE phone_number = ? AND role = 'pilgrim'");
    } else {
        // Worker and Official login uses user_id
        $stmt = $pdo->prepare("SELECT user_id, name, password_hash FROM users WHERE user_id = ? AND role = ?");
    }

    $stmt->execute($role === 'pilgrim' ? [$username] : [$username, $role]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    // Mock Password Check (Compares against plain text 'pass')
    if ($user && $password === $user['password_hash']) {
        echo json_encode(["status" => "success", "role" => $role, "id" => $user['user_id'], "phone" => $user['phone_number'] ?? null]);
    } else {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid credentials for $role."]);
    }
}

function handle_file_complaint($pdo, $data) {
    $qr_code = $data['qr_code'] ?? '';
    $description = $data['description'] ?? '';
    $contact = $data['contact'] ?? null;
    $type = $data['type'] ?? 'pilgrim'; 

    if (empty($qr_code) || empty($description)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Location and description are required."]);
        return;
    }
    
    // Generate a unique complaint ID (CXXX)
    $stmt = $pdo->query("SELECT COUNT(*) FROM complaints");
    $count = $stmt->fetchColumn() + 1;
    $complaint_id = "C" . str_pad($count, 3, '0', STR_PAD_LEFT);

    try {
        $stmt = $pdo->prepare("INSERT INTO complaints (complaint_id, location_qr, description, contact, status) VALUES (?, ?, ?, ?, 'Pending')");
        $stmt->execute([$complaint_id, $qr_code, $description, $contact]);

        echo json_encode(["status" => "success", "message" => "Complaint filed successfully.", "complaint_id" => $complaint_id]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Complaint filing failed: " . $e->getMessage()]);
    }
}

function handle_get_pilgrim_status($pdo, $params) {
    $contact = $params['contact'] ?? '';

    if (empty($contact)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Contact number is required to view status."]);
        return;
    }

    try {
        // Select only pilgrim-submitted complaints based on contact/phone
        $stmt = $pdo->prepare("SELECT complaint_id AS id, location_qr AS location, description, status, assigned_worker_id AS assignedWorker, DATE(created_at) AS date FROM complaints WHERE contact = ? ORDER BY created_at DESC");
        $stmt->execute([$contact]);
        $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["status" => "success", "complaints" => $complaints]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to retrieve status: " . $e->getMessage()]);
    }
}

function handle_get_official_dashboard($pdo) {
    try {
        // Fetch pending and assigned complaints
        $stmt = $pdo->query("SELECT complaint_id AS id, location_qr AS location, description, status, contact, DATE(created_at) AS date, assigned_worker_id FROM complaints WHERE status IN ('Pending', 'Assigned') ORDER BY created_at ASC");
        $complaints = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Fetch workers list
        $stmt = $pdo->query("SELECT user_id AS id, name FROM users WHERE role = 'worker'");
        $workers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["status" => "success", "complaints" => $complaints, "workers" => $workers]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to retrieve dashboard data: " . $e->getMessage()]);
    }
}

function handle_assign_task($pdo, $data) {
    $complaint_id = $data['complaint_id'] ?? '';
    $worker_id = $data['worker_id'] ?? '';
    $official_id = $data['official_id'] ?? 'O-01'; // Mocking official identity

    if (empty($complaint_id) || empty($worker_id)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Complaint ID and Worker ID are required."]);
        return;
    }

    try {
        $stmt = $pdo->prepare("UPDATE complaints SET status = 'Assigned', assigned_worker_id = ? WHERE complaint_id = ? AND status = 'Pending'");
        $stmt->execute([$worker_id, $complaint_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["status" => "success", "message" => "Task assigned successfully to $worker_id."]);
        } else {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Complaint not found or already assigned."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Assignment failed: " . $e->getMessage()]);
    }
}

function handle_get_worker_tasks($pdo, $params) {
    $worker_id = $params['worker_id'] ?? '';

    if (empty($worker_id)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Worker ID is required to fetch tasks."]);
        return;
    }

    try {
        $stmt = $pdo->prepare("SELECT complaint_id AS id, location_qr AS location, description, status, DATE(created_at) AS date FROM complaints WHERE assigned_worker_id = ? ORDER BY created_at DESC");
        $stmt->execute([$worker_id]);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(["status" => "success", "tasks" => $tasks]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Failed to retrieve worker tasks: " . $e->getMessage()]);
    }
}

function handle_complete_task($pdo, $data) {
    $complaint_id = $data['complaint_id'] ?? '';
    $notes = $data['notes'] ?? '';
    // This is a mock path, as actual file upload/saving is complex for a simple API
    $photo_url = "uploads/" . ($data['photo_filename'] ?? 'default_proof.jpg'); 

    if (empty($complaint_id) || empty($notes)) {
        http_response_code(400);
        echo json_encode(["status" => "error", "message" => "Complaint ID and completion notes are required."]);
        return;
    }

    try {
        // Update the status, save the photo URL, and append the worker notes to the description
        $stmt = $pdo->prepare("UPDATE complaints SET status = 'Completed', completion_photo_url = ?, description = CONCAT(description, '\n\nWorker Notes: ', ?) WHERE complaint_id = ? AND status = 'Assigned'");
        $stmt->execute([$photo_url, $notes, $complaint_id]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(["status" => "success", "message" => "Task $complaint_id marked completed."]);
        } else {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Complaint not found or not assigned."]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(["status" => "error", "message" => "Completion failed: " . $e->getMessage()]);
    }
}

?>
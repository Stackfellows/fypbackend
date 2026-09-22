<?php
/**
 * FYP Complaint Portal - Authentication API
 */
require_once __DIR__ . '/../config/cors.php';
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
$pdo = getDBConnection();

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // ─── LOGIN ───────────────────────────────────────────────────────────
    if ($action === 'login' || $action === 'student-login') {
        $email = trim($data['email'] ?? '');
        $rollNo = trim($data['rollNo'] ?? ($data['roll_no'] ?? ''));
        $password = $data['password'] ?? '';

        if ((empty($email) && empty($rollNo)) || empty($password)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Email/Roll No and password are required.']);
            exit;
        }

        if (!empty($email)) {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?)");
            $stmt->execute([$email]);
        } else {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(roll_no) = LOWER(?)");
            $stmt->execute([$rollNo]);
        }
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials. Please verify your email/password.']);
            exit;
        }

        unset($user['password']);
        $user['_id'] = $user['id'];
        $user['rollNo'] = $user['roll_no'];
        $_SESSION['user'] = $user;

        $token = base64_encode(json_encode([
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role'],
            'created' => time()
        ]));

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'user' => $user,
            'token' => $token
        ]);
        exit;
    }

    // ─── REGISTER ────────────────────────────────────────────────────────
    if ($action === 'register') {
        $name = trim($data['name'] ?? ($data['fullName'] ?? ''));
        $rollNo = trim($data['rollNo'] ?? ($data['roll_no'] ?? ''));
        $email = trim($data['email'] ?? '');
        $password = $data['password'] ?? '';
        $department = trim($data['department'] ?? 'Computer Science');

        if (empty($name) || empty($email) || empty($password)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Name, Email, and Password are required.']);
            exit;
        }

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            http_response_code(409);
            echo json_encode(['status' => 'error', 'message' => 'An account with this email already exists.']);
            exit;
        }

        if (!empty($rollNo)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(roll_no) = LOWER(?)");
            $stmt->execute([$rollNo]);
            if ($stmt->fetch()) {
                http_response_code(409);
                echo json_encode(['status' => 'error', 'message' => 'This Roll Number is already registered.']);
                exit;
            }
        }

        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $role = 'student';

        $insert = $pdo->prepare("INSERT INTO users (name, roll_no, email, password, role, department) VALUES (?, ?, ?, ?, ?, ?)");
        $insert->execute([$name, $rollNo ?: null, $email, $hashedPassword, $role, $department]);
        $userId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("SELECT id, name, roll_no, email, role, department, created_at FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $newUser = $stmt->fetch();
        $newUser['_id'] = $newUser['id'];
        $newUser['rollNo'] = $newUser['roll_no'];

        $_SESSION['user'] = $newUser;

        $token = base64_encode(json_encode([
            'id' => $newUser['id'],
            'email' => $newUser['email'],
            'role' => $newUser['role'],
            'created' => time()
        ]));

        echo json_encode([
            'status' => 'success',
            'message' => 'Registration successful! Welcome to FYP Complaint Portal.',
            'user' => $newUser,
            'token' => $token
        ]);
        exit;
    }

    // ─── LOGOUT ───────────────────────────────────────────────────────────
    if ($action === 'logout') {
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        session_destroy();
        echo json_encode(['status' => 'success', 'message' => 'Logged out successfully.']);
        exit;
    }
}

// ─── GET ME & LISTINGS ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if ($action === 'me') {
        if (!empty($_SESSION['user'])) {
            echo json_encode(['status' => 'success', 'user' => $_SESSION['user']]);
        } else {
            http_response_code(401);
            echo json_encode(['status' => 'unauthenticated', 'user' => null]);
        }
        exit;
    }

    if ($action === 'students') {
        $stmt = $pdo->query("SELECT id as _id, id, name, roll_no, email, department, created_at FROM users WHERE role = 'student' ORDER BY id DESC");
        $students = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'students' => $students]);
        exit;
    }

    if ($action === 'staff') {
        $stmt = $pdo->query("SELECT id as _id, id, name, email, role, department, created_at FROM users WHERE role != 'student' ORDER BY id DESC");
        $staff = $stmt->fetchAll();
        echo json_encode(['status' => 'success', 'staff' => $staff]);
        exit;
    }
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Invalid action or request method.']);

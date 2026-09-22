<?php
/**
 * FYP Complaint Portal - Complaints API
 */
require_once __DIR__ . '/../config/cors.php';
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
$pdo = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? ($_POST['action'] ?? '');
$currentUser = $_SESSION['user'] ?? null;

// Allow React client to supply user identity via Header, Bearer token, or parameter
if (!$currentUser) {
    $uid = $_SERVER['HTTP_X_USER_ID'] ?? ($_GET['user_id'] ?? ($_POST['user_id'] ?? null));
    if (!$uid && !empty($_SERVER['HTTP_AUTHORIZATION'])) {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
            $decoded = json_decode(base64_decode($matches[1]), true);
            if (!empty($decoded['id'])) {
                $uid = $decoded['id'];
            }
        }
    }
    if ($uid) {
        $uStmt = $pdo->prepare("SELECT id, name, roll_no, email, role, department FROM users WHERE id = ?");
        $uStmt->execute([intval($uid)]);
        $currentUser = $uStmt->fetch() ?: null;
    }
}

// Normalizer helper for React frontend compatibility
function normalizeComplaint($c, $comments = []) {
    $formattedComments = array_map(function($comm) {
        return [
            '_id' => $comm['id'],
            'id' => $comm['id'],
            'content' => $comm['message'],
            'message' => $comm['message'],
            'sender' => [
                'name' => $comm['author_name'] ?? 'Admin',
                'role' => ($comm['is_admin'] ? 'admin' : ($comm['author_role'] ?? 'user'))
            ],
            'createdAt' => $comm['created_at'],
            'created_at' => $comm['created_at']
        ];
    }, $comments);

    return array_merge($c, [
        '_id' => (string)$c['id'],
        'id' => $c['id'],
        'complaintId' => $c['ticket_no'],
        'ticket_no' => $c['ticket_no'],
        'subject' => $c['title'],
        'title' => $c['title'],
        'department' => $c['department'],
        'subDepartment' => $c['category'],
        'category' => $c['category'],
        'status' => $c['status'],
        'priority' => $c['priority'],
        'description' => $c['description'],
        'attachments' => !empty($c['attachment']) ? [$c['attachment']] : [],
        'attachment' => $c['attachment'],
        'createdAt' => $c['created_at'],
        'created_at' => $c['created_at'],
        'updatedAt' => $c['updated_at'],
        'updated_at' => $c['updated_at'],
        'student' => [
            'name' => $c['student_name'] ?? 'Student',
            'rollNo' => $c['roll_no'] ?? '',
            'email' => $c['student_email'] ?? '',
            'department' => $c['student_dept'] ?? $c['department'] ?? 'CS'
        ],
        'messages' => $formattedComments,
        'comments' => $formattedComments
    ]);
}

// ─── 0. PUBLIC DEPARTMENTS ───────────────────────────────────────────────────
if ($action === 'departments') {
    $departments = [
        [
            '_id' => '1',
            'name' => 'Computer Science & FYP Cell',
            'subDepartments' => ['Supervisor Allocation', 'FYP Lab & GPU Access', 'Evaluation & Viva Dispute', 'Turnitin Similarity Clearance']
        ],
        [
            '_id' => '2',
            'name' => 'Software Engineering',
            'subDepartments' => ['Sprint Evaluation', 'Industry Mentor Coordination', 'Hardware / IoT Shortage']
        ],
        [
            '_id' => '3',
            'name' => 'Electrical Engineering',
            'subDepartments' => ['Embedded Systems Lab', 'Component Procurement', 'Laboratory Bench Allocation']
        ],
        [
            '_id' => '4',
            'name' => 'General Academic Support',
            'subDepartments' => ['Challan / Examination Slip', 'Library Clearance', 'General Query']
        ]
    ];
    echo json_encode(['status' => 'success', 'departments' => $departments]);
    exit;
}

// ─── 1. GET COMPLAINTS ───────────────────────────────────────────────────────
if ($method === 'GET') {
    // A. Single Ticket Public Lookup by Ticket Number (e.g. FYP-2026-1001)
    if (isset($_GET['ticket_no'])) {
        $ticketNo = trim($_GET['ticket_no']);
        $stmt = $pdo->prepare("
            SELECT c.*, u.name as student_name, u.roll_no, u.email as student_email, u.department as student_dept
            FROM complaints c
            JOIN users u ON c.user_id = u.id
            WHERE c.ticket_no = ?
        ");
        $stmt->execute([$ticketNo]);
        $complaint = $stmt->fetch();

        if (!$complaint) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Complaint ticket not found.']);
            exit;
        }

        $commStmt = $pdo->prepare("
            SELECT cc.*, u.name as author_name, u.role as author_role
            FROM complaint_comments cc
            JOIN users u ON cc.user_id = u.id
            WHERE cc.complaint_id = ?
            ORDER BY cc.created_at ASC
        ");
        $commStmt->execute([$complaint['id']]);
        $comments = $commStmt->fetchAll();

        echo json_encode(['status' => 'success', 'complaint' => normalizeComplaint($complaint, $comments)]);
        exit;
    }

    // B. Single Complaint by ID
    if (isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $stmt = $pdo->prepare("
            SELECT c.*, u.name as student_name, u.roll_no, u.email as student_email, u.department as student_dept
            FROM complaints c
            JOIN users u ON c.user_id = u.id
            WHERE c.id = ?
        ");
        $stmt->execute([$id]);
        $complaint = $stmt->fetch();

        if (!$complaint) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Complaint not found.']);
            exit;
        }

        $commStmt = $pdo->prepare("
            SELECT cc.*, u.name as author_name, u.role as author_role
            FROM complaint_comments cc
            JOIN users u ON cc.user_id = u.id
            WHERE cc.complaint_id = ?
            ORDER BY cc.created_at ASC
        ");
        $commStmt->execute([$id]);
        $comments = $commStmt->fetchAll();

        echo json_encode(['status' => 'success', 'complaint' => normalizeComplaint($complaint, $comments)]);
        exit;
    }

    // C. List Complaints
    $where = [];
    $params = [];

    // If student, only view their own
    if ($currentUser && $currentUser['role'] === 'student') {
        $where[] = "c.user_id = ?";
        $params[] = $currentUser['id'];
    }

    if (!empty($_GET['status']) && $_GET['status'] !== 'all') {
        $where[] = "c.status = ?";
        $params[] = $_GET['status'];
    }

    if (!empty($_GET['priority']) && $_GET['priority'] !== 'all') {
        $where[] = "c.priority = ?";
        $params[] = $_GET['priority'];
    }

    if (!empty($_GET['search'])) {
        $term = '%' . trim($_GET['search']) . '%';
        $where[] = "(c.ticket_no LIKE ? OR c.title LIKE ? OR c.description LIKE ? OR u.name LIKE ? OR u.roll_no LIKE ?)";
        $params = array_merge($params, [$term, $term, $term, $term, $term]);
    }

    $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

    $sql = "
        SELECT c.*, u.name as student_name, u.roll_no, u.email as student_email, u.department as student_dept
        FROM complaints c
        JOIN users u ON c.user_id = u.id
        $whereSql
        ORDER BY c.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $complaints = $stmt->fetchAll();

    $normalizedList = array_map(function($item) {
        return normalizeComplaint($item);
    }, $complaints);

    echo json_encode(['status' => 'success', 'complaints' => $normalizedList]);
    exit;
}

// ─── 2. POST / CREATE / UPDATE COMPLAINTS ─────────────────────────────────────
if ($method === 'POST') {
    // Allow any student or guest to lodge a complaint without blocking
    if (!$currentUser) {
        $guestName = trim($_POST['student_name'] ?? ($_POST['name'] ?? 'Student Guest'));
        $guestRoll = trim($_POST['roll_no'] ?? ($_POST['rollNo'] ?? ''));
        $guestEmail = trim($_POST['email'] ?? ($_POST['student_email'] ?? ''));
        $department = trim($_POST['department'] ?? 'Computer Science');

        if (!empty($guestEmail)) {
            $uCheck = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
            $uCheck->execute([$guestEmail]);
            $existing = $uCheck->fetch();
            if ($existing) {
                $userId = $existing['id'];
            } else {
                $pass = password_hash('student123', PASSWORD_BCRYPT);
                $ins = $pdo->prepare("INSERT INTO users (name, roll_no, email, password, role, department) VALUES (?, ?, ?, ?, 'student', ?)");
                $ins->execute([$guestName, $guestRoll ?: null, $guestEmail, $pass, $department]);
                $userId = $pdo->lastInsertId();
            }
        } else {
            $userId = 2; // Fallback to demo student
        }
    } else {
        $userId = $currentUser['id'];
    }

    // A. Update Status / Priority (Admin / Faculty)
    if ($action === 'update_status' || $action === 'status') {
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $complaintId = intval($_GET['id'] ?? ($data['complaint_id'] ?? ($data['id'] ?? 0)));
        $newStatus = trim($data['status'] ?? '');
        $newPriority = trim($data['priority'] ?? '');
        $remark = trim($data['remark'] ?? ($data['note'] ?? ''));

        if (!$complaintId || empty($newStatus)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Complaint ID and status are required.']);
            exit;
        }

        $updateSql = "UPDATE complaints SET status = ?, updated_at = CURRENT_TIMESTAMP";
        $updateParams = [$newStatus];

        if (!empty($newPriority)) {
            $updateSql .= ", priority = ?";
            $updateParams[] = $newPriority;
        }

        $updateSql .= " WHERE id = ?";
        $updateParams[] = $complaintId;

        $stmt = $pdo->prepare($updateSql);
        $stmt->execute($updateParams);

        if (!empty($remark)) {
            $commStmt = $pdo->prepare("INSERT INTO complaint_comments (complaint_id, user_id, message, is_admin) VALUES (?, ?, ?, 1)");
            $commStmt->execute([$complaintId, $userId, "Status updated to [{$newStatus}]: " . $remark]);
        }

        echo json_encode(['status' => 'success', 'message' => "Complaint status updated to {$newStatus}."]);
        exit;
    }

    // B. Create New Complaint
    $title = trim($_POST['title'] ?? ($_POST['subject'] ?? ''));
    $department = trim($_POST['department'] ?? 'Computer Science');
    $category = trim($_POST['category'] ?? ($_POST['subDepartment'] ?? 'General FYP Issue'));
    $priority = trim($_POST['priority'] ?? 'Medium');
    $description = trim($_POST['description'] ?? '');

    if (empty($title) || empty($description)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Complaint title and description are required.']);
        exit;
    }

    // Handle File Attachment Upload if provided
    $attachmentPath = null;
    $fileField = isset($_FILES['attachment']) ? 'attachment' : (isset($_FILES['attachments']) ? 'attachments' : null);
    if ($fileField && $_FILES[$fileField]['error'] === UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../uploads';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = basename($_FILES[$fileField]['name']);
        $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'zip', 'txt'];

        if (in_array($fileExt, $allowedExts)) {
            $safeName = 'fyp_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
            $destPath = $uploadDir . '/' . $safeName;
            if (move_uploaded_file($_FILES[$fileField]['tmp_name'], $destPath)) {
                $attachmentPath = 'uploads/' . $safeName;
            }
        }
    }

    $ticketNo = 'FYP-' . date('Y') . '-' . rand(1000, 9999);
    $checkStmt = $pdo->prepare("SELECT id FROM complaints WHERE ticket_no = ?");
    $checkStmt->execute([$ticketNo]);
    while ($checkStmt->fetch()) {
        $ticketNo = 'FYP-' . date('Y') . '-' . rand(1000, 9999);
        $checkStmt->execute([$ticketNo]);
    }

    $stmt = $pdo->prepare("
        INSERT INTO complaints (ticket_no, user_id, title, department, category, priority, status, description, attachment)
        VALUES (?, ?, ?, ?, ?, ?, 'Pending', ?, ?)
    ");
    $stmt->execute([
        $ticketNo,
        $userId,
        $title,
        $department,
        $category,
        $priority,
        $description,
        $attachmentPath
    ]);
    $complaintId = $pdo->lastInsertId();

    $newComp = [
        'id' => $complaintId,
        'ticket_no' => $ticketNo,
        'title' => $title,
        'department' => $department,
        'category' => $category,
        'priority' => $priority,
        'status' => 'Pending',
        'description' => $description,
        'attachment' => $attachmentPath,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ];

    echo json_encode([
        'status' => 'success',
        'message' => 'Complaint lodged successfully! Keep your ticket number safe.',
        'ticket_no' => $ticketNo,
        'complaint_id' => $complaintId,
        'complaint' => normalizeComplaint($newComp)
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);

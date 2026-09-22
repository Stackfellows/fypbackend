<?php
/**
 * FYP Complaint Portal - Complaint Comments / Resolution API
 */
require_once __DIR__ . '/../config/cors.php';
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
$pdo = getDBConnection();

$currentUser = $_SESSION['user'] ?? null;
if (!$currentUser) {
    $uid = $_SERVER['HTTP_X_USER_ID'] ?? ($_GET['user_id'] ?? ($_POST['user_id'] ?? null));
    if ($uid) {
        $uStmt = $pdo->prepare("SELECT id, name, roll_no, email, role, department FROM users WHERE id = ?");
        $uStmt->execute([intval($uid)]);
        $currentUser = $uStmt->fetch() ?: null;
    }
}

if (!$currentUser) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Please login to post comments.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $complaintId = intval($data['complaint_id'] ?? 0);
    $message = trim($data['message'] ?? '');

    if (!$complaintId || empty($message)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Complaint ID and message are required.']);
        exit;
    }

    // Verify complaint exists
    $compStmt = $pdo->prepare("SELECT user_id FROM complaints WHERE id = ?");
    $compStmt->execute([$complaintId]);
    $complaint = $compStmt->fetch();

    if (!$complaint) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Complaint not found.']);
        exit;
    }

    // Only complaint owner or admin can comment
    if ($currentUser['role'] === 'student' && $complaint['user_id'] != $currentUser['id']) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'You cannot comment on another student\'s complaint.']);
        exit;
    }

    $isAdmin = ($currentUser['role'] === 'admin' || $currentUser['role'] === 'faculty') ? 1 : 0;

    $stmt = $pdo->prepare("
        INSERT INTO complaint_comments (complaint_id, user_id, message, is_admin)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$complaintId, $currentUser['id'], $message, $isAdmin]);

    // Update timestamp on parent complaint
    $pdo->prepare("UPDATE complaints SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$complaintId]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Reply posted successfully.',
        'comment' => [
            'id' => $pdo->lastInsertId(),
            'message' => $message,
            'author_name' => $currentUser['name'],
            'author_role' => $currentUser['role'],
            'is_admin' => $isAdmin,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);

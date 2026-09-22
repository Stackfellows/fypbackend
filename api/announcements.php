<?php
/**
 * FYP Complaint Portal - Announcements API
 */
require_once __DIR__ . '/../config/cors.php';
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
$pdo = getDBConnection();

$method = $_SERVER['REQUEST_METHOD'];
$currentUser = $_SESSION['user'] ?? null;
if (!$currentUser) {
    $uid = $_SERVER['HTTP_X_USER_ID'] ?? ($_GET['user_id'] ?? ($_POST['user_id'] ?? null));
    if ($uid) {
        $uStmt = $pdo->prepare("SELECT id, name, roll_no, email, role, department FROM users WHERE id = ?");
        $uStmt->execute([intval($uid)]);
        $currentUser = $uStmt->fetch() ?: null;
    }
}

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 10");
    $announcements = $stmt->fetchAll();
    echo json_encode(['status' => 'success', 'announcements' => $announcements]);
    exit;
}

if ($method === 'POST') {
    if (!$currentUser || ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'faculty')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Only FYP Committee / Admin can post announcements.']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
    $title = trim($data['title'] ?? '');
    $content = trim($data['content'] ?? '');

    if (empty($title) || empty($content)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Title and content are required.']);
        exit;
    }

    $author = $currentUser['name'] ?? 'FYP Admin';
    $stmt = $pdo->prepare("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)");
    $stmt->execute([$title, $content, $author]);

    echo json_encode(['status' => 'success', 'message' => 'Announcement posted successfully.']);
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Method not allowed.']);

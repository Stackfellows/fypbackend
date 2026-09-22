<?php
/**
 * FYP Complaint Portal - Dashboard Statistics API
 */
require_once __DIR__ . '/../config/cors.php';
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';
$pdo = getDBConnection();

$currentUser = $_SESSION['user'] ?? null;
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

$userFilter = "";
$params = [];

if ($currentUser && $currentUser['role'] === 'student') {
    $userFilter = "WHERE user_id = ?";
    $params[] = $currentUser['id'];
}

// 1. Complaint Status Counters
$sql = "
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'Pending' OR status = 'Open' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
        SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved,
        SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
    FROM complaints
    $userFilter
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$counts = $stmt->fetch();

$total = (int)($counts['total'] ?? 0);
$open = (int)($counts['pending'] ?? 0);
$inProgress = (int)($counts['in_progress'] ?? 0);
$resolved = (int)($counts['resolved'] ?? 0);
$rejected = (int)($counts['rejected'] ?? 0);

// 2. Department Breakdown
$deptSql = "
    SELECT department, COUNT(*) as count 
    FROM complaints 
    $userFilter 
    GROUP BY department 
    ORDER BY count DESC
";
$deptStmt = $pdo->prepare($deptSql);
$deptStmt->execute($params);
$departments = $deptStmt->fetchAll();

// 3. Category Breakdown
$catSql = "
    SELECT category, COUNT(*) as count 
    FROM complaints 
    $userFilter 
    GROUP BY category 
    ORDER BY count DESC
";
$catStmt = $pdo->prepare($catSql);
$catStmt->execute($params);
$categories = $catStmt->fetchAll();

echo json_encode([
    'status' => 'success',
    'stats' => [
        'total' => $total,
        'pending' => $open,
        'in_progress' => $inProgress,
        'resolved' => $resolved,
        'rejected' => $rejected
    ],
    'complaints' => [
        'total' => $total,
        'open' => $open,
        'inProgress' => $inProgress,
        'resolved' => $resolved,
        'rejected' => $rejected
    ],
    'departments' => $departments,
    'categories' => $categories
]);

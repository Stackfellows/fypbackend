<?php
$pageTitle = "Admin & Faculty Management Panel";
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUser = $_SESSION['user'] ?? null;
if (!$currentUser || ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'faculty')) {
    header("Location: login.php");
    exit;
}

$msg = '';
$msgType = '';

// Handle Announcement Posting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
    $annTitle = trim($_POST['ann_title'] ?? '');
    $annContent = trim($_POST['ann_content'] ?? '');
    if (!empty($annTitle) && !empty($annContent)) {
        $stmt = $pdo->prepare("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)");
        $stmt->execute([$annTitle, $annContent, $currentUser['name']]);
        $msg = "New circular announcement published successfully!";
        $msgType = 'success';
    }
}

// Fetch Global Statistics
$statsStmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN status = 'Rejected' THEN 1 ELSE 0 END) as rejected
FROM complaints");
$stats = $statsStmt->fetch();

// Fetch All Complaints with Student Details
$complaintsStmt = $pdo->query("
    SELECT c.*, u.name as student_name, u.roll_no, u.email as student_email
    FROM complaints c
    JOIN users u ON c.user_id = u.id
    ORDER BY c.created_at DESC
");
$complaints = $complaintsStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Admin Header Banner -->
<div class="card" style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: #ffffff; border: none;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span style="background: #ef4444; color: #ffffff; padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.8rem; font-weight: 800;">
                ADMIN CONSOLE
            </span>
            <h1 style="font-size: 1.75rem; font-weight: 800; margin-top: 0.5rem;">
                FYP Grievance & Complaints Management
            </h1>
            <p style="color: #cbd5e1; font-size: 0.95rem;">
                Welcome, <strong><?php echo htmlspecialchars($currentUser['name']); ?></strong> (Academic Administrator)
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="document.getElementById('announcementModal').scrollIntoView({behavior:'smooth'})">
                📢 Post FYP Announcement
            </button>
        </div>
    </div>
</div>

<?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">📊</div>
        <div class="stat-info">
            <h3><?php echo (int)($stats['total'] ?? 0); ?></h3>
            <p>Total Complaints</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #fef3c7; color: #d97706;">⏳</div>
        <div class="stat-info">
            <h3><?php echo (int)($stats['pending'] ?? 0); ?></h3>
            <p>Pending Review</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #e0e7ff; color: #4338ca;">⚙️</div>
        <div class="stat-info">
            <h3><?php echo (int)($stats['in_progress'] ?? 0); ?></h3>
            <p>In Progress</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #d1fae5; color: #059669;">✅</div>
        <div class="stat-info">
            <h3><?php echo (int)($stats['resolved'] ?? 0); ?></h3>
            <p>Resolved Cases</p>
        </div>
    </div>
</div>

<!-- Complaints Data Table -->
<div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 1rem;">
        <h2 class="card-title">All Student Complaints</h2>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <input type="text" id="tableSearch" class="form-control" placeholder="Search by ticket, name, roll no..." style="width: 250px; padding: 0.4rem 0.75rem;">
            <select id="statusFilter" class="form-select" style="width: 140px; padding: 0.4rem 0.75rem;">
                <option value="all">All Statuses</option>
                <option value="pending">Pending</option>
                <option value="in progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
    </div>

    <div class="table-responsive">
        <table class="table" id="complaintsTable">
            <thead>
                <tr>
                    <th>Ticket ID</th>
                    <th>Student Info</th>
                    <th>Subject & Category</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Filed On</th>
                    <th>Manage</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($complaints)): ?>
                    <tr>
                        <td colspan="7" style="text-align: center; color: var(--text-muted); padding: 2rem;">No complaints registered yet.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($complaints as $c): ?>
                        <?php
                        $badgeClass = 'badge-pending';
                        if ($c['status'] === 'In Progress') $badgeClass = 'badge-progress';
                        elseif ($c['status'] === 'Resolved') $badgeClass = 'badge-resolved';
                        elseif ($c['status'] === 'Rejected') $badgeClass = 'badge-rejected';

                        $prioClass = 'badge-medium';
                        if ($c['priority'] === 'Urgent') $prioClass = 'badge-urgent';
                        elseif ($c['priority'] === 'High') $prioClass = 'badge-high';
                        elseif ($c['priority'] === 'Low') $prioClass = 'badge-low';
                        ?>
                        <tr>
                            <td>
                                <strong style="color: var(--primary); font-family: monospace; font-size: 0.95rem;">
                                    <?php echo htmlspecialchars($c['ticket_no']); ?>
                                </strong>
                            </td>
                            <td>
                                <strong style="color: var(--text-main);"><?php echo htmlspecialchars($c['student_name']); ?></strong>
                                <div style="font-size: 0.8rem; color: var(--text-muted); font-family: monospace;">
                                    <?php echo htmlspecialchars($c['roll_no'] ?? 'N/A'); ?>
                                </div>
                            </td>
                            <td>
                                <a href="complaint-detail.php?id=<?php echo $c['id']; ?>" style="font-weight: 600; color: var(--text-main);">
                                    <?php echo htmlspecialchars($c['title']); ?>
                                </a>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">
                                    <?php echo htmlspecialchars($c['category']); ?> &bull; <?php echo htmlspecialchars($c['department']); ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo $prioClass; ?>"><?php echo htmlspecialchars($c['priority']); ?></span>
                            </td>
                            <td>
                                <span class="badge <?php echo $badgeClass; ?>"><?php echo htmlspecialchars($c['status']); ?></span>
                            </td>
                            <td style="font-size: 0.85rem; color: var(--text-muted);">
                                <?php echo date('M d, Y', strtotime($c['created_at'])); ?>
                            </td>
                            <td>
                                <a href="complaint-detail.php?id=<?php echo $c['id']; ?>" class="btn btn-secondary btn-sm">
                                    Manage &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Post Announcement Card -->
<div class="card" id="announcementModal" style="margin-top: 2rem;">
    <div class="card-header">
        <h3 class="card-title">📢 Post New FYP Notice / Announcement</h3>
    </div>
    <form method="POST" action="admin.php">
        <div class="form-group">
            <label class="form-label" for="ann_title">Notice Title *</label>
            <input type="text" id="ann_title" name="ann_title" class="form-control" placeholder="e.g. FYP Mid-Term Defense Schedule Released" required>
        </div>
        <div class="form-group">
            <label class="form-label" for="ann_content">Notice Body / Message *</label>
            <textarea id="ann_content" name="ann_content" class="form-control" placeholder="Type circular announcement text for student portal..." required></textarea>
        </div>
        <button type="submit" name="post_announcement" class="btn btn-primary">Publish Announcement &rarr;</button>
    </form>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

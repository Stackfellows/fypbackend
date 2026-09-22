<?php
$pageTitle = "Student Dashboard";
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentUser = $_SESSION['user'] ?? null;
if (!$currentUser) {
    header("Location: login.php");
    exit;
}

// If admin visits student dashboard, redirect to admin
if ($currentUser['role'] === 'admin') {
    header("Location: admin.php");
    exit;
}

// Fetch stats for current student
$stmt = $pdo->prepare("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved
FROM complaints
WHERE user_id = ?");
$stmt->execute([$currentUser['id']]);
$stats = $stmt->fetch();

// Fetch complaints list
$compStmt = $pdo->prepare("SELECT * FROM complaints WHERE user_id = ? ORDER BY created_at DESC");
$compStmt->execute([$currentUser['id']]);
$complaints = $compStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Welcome Banner -->
<div class="card" style="background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); color: #ffffff; border: none;">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
        <div>
            <span style="background: rgba(255,255,255,0.2); padding: 0.25rem 0.6rem; border-radius: 4px; font-size: 0.8rem; font-weight: 700;">
                STUDENT CONSOLE
            </span>
            <h1 style="font-size: 1.75rem; font-weight: 800; margin-top: 0.5rem;">
                Welcome, <?php echo htmlspecialchars($currentUser['name']); ?>!
            </h1>
            <p style="color: #e0e7ff; font-size: 0.95rem;">
                Roll No: <strong><?php echo htmlspecialchars($currentUser['roll_no'] ?? 'N/A'); ?></strong> | Department: <strong><?php echo htmlspecialchars($currentUser['department'] ?? 'CS'); ?></strong>
            </p>
        </div>
        <div>
            <a href="new-complaint.php" class="btn btn-primary" style="background: #ffffff; color: var(--primary)!important;">
                + Lodge New Complaint
            </a>
        </div>
    </div>
</div>

<!-- Student Stats Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">📑</div>
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
            <p>Under Investigation</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #d1fae5; color: #059669;">✅</div>
        <div class="stat-info">
            <h3><?php echo (int)($stats['resolved'] ?? 0); ?></h3>
            <p>Resolved</p>
        </div>
    </div>
</div>

<!-- Complaints Table -->
<div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 1rem;">
        <h2 class="card-title">My Registered Complaints</h2>
        <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <input type="text" id="tableSearch" class="form-control" placeholder="Search my complaints..." style="width: 220px; padding: 0.4rem 0.75rem;">
            <select id="statusFilter" class="form-select" style="width: 140px; padding: 0.4rem 0.75rem;">
                <option value="all">All Status</option>
                <option value="pending">Pending</option>
                <option value="in progress">In Progress</option>
                <option value="resolved">Resolved</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
    </div>

    <?php if (empty($complaints)): ?>
        <div style="text-align: center; padding: 3rem 1rem;">
            <span style="font-size: 3rem; display: block; margin-bottom: 1rem;">📂</span>
            <h3 style="font-size: 1.2rem; color: var(--text-main);">No complaints filed yet</h3>
            <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1.5rem;">Have any issue regarding your FYP? Lodge a complaint to get assistance from faculty or admin.</p>
            <a href="new-complaint.php" class="btn btn-primary">+ Lodge Your First Complaint</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table" id="complaintsTable">
                <thead>
                    <tr>
                        <th>Ticket No</th>
                        <th>Title & Category</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Filed On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
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
                                    View Details &rarr;
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

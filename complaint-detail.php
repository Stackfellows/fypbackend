<?php
$pageTitle = "Complaint Details";
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

$id = intval($_GET['id'] ?? 0);
if (!$id) {
    header("Location: dashboard.php");
    exit;
}

// Fetch complaint with student info
$stmt = $pdo->prepare("
    SELECT c.*, u.name as student_name, u.roll_no, u.email as student_email, u.department as student_dept
    FROM complaints c
    JOIN users u ON c.user_id = u.id
    WHERE c.id = ?
");
$stmt->execute([$id]);
$complaint = $stmt->fetch();

if (!$complaint) {
    die("Complaint not found.");
}

// Authorization: only student owner or admin
if ($currentUser['role'] === 'student' && $complaint['user_id'] != $currentUser['id']) {
    die("Unauthorized access.");
}

$msg = '';
$msgType = '';

// Handle Status Update (Admin Only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'faculty') {
        $newStatus = trim($_POST['status'] ?? '');
        $newPriority = trim($_POST['priority'] ?? '');
        $adminRemark = trim($_POST['remark'] ?? '');

        if (!empty($newStatus)) {
            $upSql = "UPDATE complaints SET status = ?, updated_at = CURRENT_TIMESTAMP";
            $upParams = [$newStatus];

            if (!empty($newPriority)) {
                $upSql .= ", priority = ?";
                $upParams[] = $newPriority;
            }

            $upSql .= " WHERE id = ?";
            $upParams[] = $id;

            $pdo->prepare($upSql)->execute($upParams);

            if (!empty($adminRemark)) {
                $pdo->prepare("INSERT INTO complaint_comments (complaint_id, user_id, message, is_admin) VALUES (?, ?, ?, 1)")
                    ->execute([$id, $currentUser['id'], "Status changed to [{$newStatus}]: " . $adminRemark]);
            }

            $msg = "Complaint updated successfully.";
            $msgType = 'success';

            // Refresh complaint
            $stmt->execute([$id]);
            $complaint = $stmt->fetch();
        }
    }
}

// Handle New Reply / Comment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_comment'])) {
    $commentMsg = trim($_POST['comment_message'] ?? '');
    if (!empty($commentMsg)) {
        $isAdmin = ($currentUser['role'] === 'admin' || $currentUser['role'] === 'faculty') ? 1 : 0;
        $ins = $pdo->prepare("INSERT INTO complaint_comments (complaint_id, user_id, message, is_admin) VALUES (?, ?, ?, ?)");
        $ins->execute([$id, $currentUser['id'], $commentMsg, $isAdmin]);

        $pdo->prepare("UPDATE complaints SET updated_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$id]);

        $msg = "Your reply has been posted.";
        $msgType = 'success';
    }
}

// Fetch all comments
$commStmt = $pdo->prepare("
    SELECT cc.*, u.name as author_name, u.role as author_role
    FROM complaint_comments cc
    JOIN users u ON cc.user_id = u.id
    WHERE cc.complaint_id = ?
    ORDER BY cc.created_at ASC
");
$commStmt->execute([$id]);
$comments = $commStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';

$badgeClass = 'badge-pending';
if ($complaint['status'] === 'In Progress') $badgeClass = 'badge-progress';
elseif ($complaint['status'] === 'Resolved') $badgeClass = 'badge-resolved';
elseif ($complaint['status'] === 'Rejected') $badgeClass = 'badge-rejected';
?>

<div style="margin-bottom: 1rem;">
    <a href="<?php echo $currentUser['role'] === 'admin' ? 'admin.php' : 'dashboard.php'; ?>" style="font-size: 0.9rem; color: var(--text-muted);">
        &larr; Back to <?php echo $currentUser['role'] === 'admin' ? 'Admin Panel' : 'Dashboard'; ?>
    </a>
</div>

<?php if (!empty($msg)): ?>
    <div class="alert alert-<?php echo $msgType; ?>"><?php echo htmlspecialchars($msg); ?></div>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; align-items: start;">
    <!-- Main Left Column -->
    <div>
        <div class="card">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
                <div>
                    <span style="font-family: monospace; font-weight: 800; font-size: 1.15rem; color: var(--primary);">
                        <?php echo htmlspecialchars($complaint['ticket_no']); ?>
                    </span>
                    <h1 style="font-size: 1.4rem; font-weight: 800; color: var(--text-main); margin-top: 0.25rem;">
                        <?php echo htmlspecialchars($complaint['title']); ?>
                    </h1>
                </div>
                <div style="display: flex; gap: 0.5rem; align-items: center;">
                    <span class="badge badge-<?php echo strtolower($complaint['priority']); ?>">
                        <?php echo htmlspecialchars($complaint['priority']); ?> Priority
                    </span>
                    <span class="badge <?php echo $badgeClass; ?>">
                        <?php echo htmlspecialchars($complaint['status']); ?>
                    </span>
                </div>
            </div>

            <!-- Details metadata banner -->
            <div style="background: #f8fafc; border-radius: var(--radius-md); padding: 0.75rem 1rem; margin-bottom: 1.25rem; font-size: 0.85rem; color: var(--text-muted); display: flex; flex-wrap: wrap; gap: 1.5rem;">
                <div>Department: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($complaint['department']); ?></strong></div>
                <div>Category: <strong style="color: var(--text-main);"><?php echo htmlspecialchars($complaint['category']); ?></strong></div>
                <div>Logged Date: <strong style="color: var(--text-main);"><?php echo date('M d, Y h:i A', strtotime($complaint['created_at'])); ?></strong></div>
            </div>

            <div style="margin-bottom: 1.5rem;">
                <h4 style="font-size: 0.95rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem;">
                    Problem Description
                </h4>
                <div style="background: #ffffff; border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 1.25rem; font-size: 0.95rem; line-height: 1.6; white-space: pre-wrap; color: var(--text-main);">
                    <?php echo htmlspecialchars($complaint['description']); ?>
                </div>
            </div>

            <?php if (!empty($complaint['attachment'])): ?>
                <div style="margin-bottom: 1.5rem; padding: 0.75rem 1rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: space-between;">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        <span style="font-size: 1.25rem;">📎</span>
                        <span style="font-size: 0.9rem; font-weight: 600; color: var(--primary);">Attached File Available</span>
                    </div>
                    <a href="<?php echo htmlspecialchars($complaint['attachment']); ?>" target="_blank" download class="btn btn-secondary btn-sm">
                        Download Attachment
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Remarks & Discussion Thread -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title">💬 Resolution Timeline & Remarks</h3>
            </div>

            <div class="timeline">
                <!-- Initial Submission Node -->
                <div class="timeline-item">
                    <div class="timeline-card">
                        <div class="timeline-header">
                            <span class="timeline-author"><?php echo htmlspecialchars($complaint['student_name']); ?> (Student)</span>
                            <span class="timeline-date"><?php echo date('M d, Y h:i A', strtotime($complaint['created_at'])); ?></span>
                        </div>
                        <p class="timeline-message">Ticket created with status <strong>Pending</strong>.</p>
                    </div>
                </div>

                <?php foreach ($comments as $comm): ?>
                    <div class="timeline-item <?php echo $comm['is_admin'] ? 'timeline-admin' : ''; ?>">
                        <div class="timeline-card" style="<?php echo $comm['is_admin'] ? 'border-left: 3px solid var(--success); background:#f0fdf4;' : ''; ?>">
                            <div class="timeline-header">
                                <span class="timeline-author" style="<?php echo $comm['is_admin'] ? 'color: #065f46;' : ''; ?>">
                                    <?php echo $comm['is_admin'] ? '🛡️ ' : '👤 '; ?>
                                    <?php echo htmlspecialchars($comm['author_name']); ?> (<?php echo ucfirst($comm['author_role']); ?>)
                                </span>
                                <span class="timeline-date"><?php echo date('M d, Y h:i A', strtotime($comm['created_at'])); ?></span>
                            </div>
                            <p class="timeline-message"><?php echo htmlspecialchars($comm['message']); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Post Reply Box -->
            <form method="POST" action="complaint-detail.php?id=<?php echo $id; ?>" style="margin-top: 1.5rem; border-top: 1px solid var(--border-color); padding-top: 1.25rem;">
                <div class="form-group">
                    <label class="form-label" for="comment_message">Add Remark / Reply Message</label>
                    <textarea id="comment_message" name="comment_message" class="form-control" style="min-height: 80px;" placeholder="Type your follow-up reply or question here..." required></textarea>
                </div>
                <button type="submit" name="post_comment" class="btn btn-primary btn-sm">Post Reply &rarr;</button>
            </form>
        </div>
    </div>

    <!-- Right Column Sidebar -->
    <div>
        <!-- Student Info Card -->
        <div class="card">
            <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--primary); margin-bottom: 0.75rem;">
                👤 Student Information
            </h3>
            <div style="font-size: 0.9rem; display: flex; flex-direction: column; gap: 0.5rem;">
                <div><span style="color: var(--text-muted);">Name:</span> <strong><?php echo htmlspecialchars($complaint['student_name']); ?></strong></div>
                <div><span style="color: var(--text-muted);">Roll No:</span> <strong><?php echo htmlspecialchars($complaint['roll_no'] ?? 'N/A'); ?></strong></div>
                <div><span style="color: var(--text-muted);">Email:</span> <strong><?php echo htmlspecialchars($complaint['student_email']); ?></strong></div>
                <div><span style="color: var(--text-muted);">Department:</span> <strong><?php echo htmlspecialchars($complaint['student_dept']); ?></strong></div>
            </div>
        </div>

        <!-- Admin Action Card -->
        <?php if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'faculty'): ?>
            <div class="card" style="border: 2px solid var(--primary-light);">
                <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--primary); margin-bottom: 0.75rem;">
                    ⚙️ Administrative Action
                </h3>
                <form method="POST" action="complaint-detail.php?id=<?php echo $id; ?>">
                    <div class="form-group">
                        <label class="form-label" for="status">Update Ticket Status</label>
                        <select id="status" name="status" class="form-select">
                            <option value="Pending" <?php echo $complaint['status'] === 'Pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="In Progress" <?php echo $complaint['status'] === 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                            <option value="Resolved" <?php echo $complaint['status'] === 'Resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="Rejected" <?php echo $complaint['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="priority">Adjust Priority</label>
                        <select id="priority" name="priority" class="form-select">
                            <option value="Low" <?php echo $complaint['priority'] === 'Low' ? 'selected' : ''; ?>>Low</option>
                            <option value="Medium" <?php echo $complaint['priority'] === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="High" <?php echo $complaint['priority'] === 'High' ? 'selected' : ''; ?>>High</option>
                            <option value="Urgent" <?php echo $complaint['priority'] === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="remark">Resolution Note / Action Taken</label>
                        <textarea id="remark" name="remark" class="form-control" style="min-height: 80px;" placeholder="e.g. Assigned to lab manager; meeting arranged..."></textarea>
                    </div>

                    <button type="submit" name="update_status" class="btn btn-primary btn-block">Save Updates</button>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

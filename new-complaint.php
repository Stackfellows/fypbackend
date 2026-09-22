<?php
$pageTitle = "Lodge New Complaint";
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

$error = '';
$success = '';
$ticketNo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $department = trim($_POST['department'] ?? 'Computer Science');
    $category = trim($_POST['category'] ?? 'General FYP Issue');
    $priority = trim($_POST['priority'] ?? 'Medium');
    $description = trim($_POST['description'] ?? '');

    if (empty($title) || empty($description)) {
        $error = 'Complaint title and detailed description are required.';
    } else {
        // Handle optional file attachment
        $attachmentPath = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = __DIR__ . '/uploads';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $fileName = basename($_FILES['attachment']['name']);
            $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExts = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'zip', 'txt'];

            if (in_array($fileExt, $allowedExts)) {
                $safeName = 'fyp_' . time() . '_' . rand(1000, 9999) . '.' . $fileExt;
                $destPath = $uploadDir . '/' . $safeName;
                if (move_uploaded_file($_FILES['attachment']['tmp_name'], $destPath)) {
                    $attachmentPath = 'uploads/' . $safeName;
                }
            } else {
                $error = 'Invalid attachment type. Allowed: JPG, PNG, PDF, DOCX, ZIP.';
            }
        }

        if (empty($error)) {
            // Generate unique ticket number
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
                $currentUser['id'],
                $title,
                $department,
                $category,
                $priority,
                $description,
                $attachmentPath
            ]);
            $complaintId = $pdo->lastInsertId();

            $success = "Complaint registered successfully with Ticket Number: <strong>{$ticketNo}</strong>";
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 760px; margin: 1.5rem auto;">
    <div style="margin-bottom: 1rem;">
        <a href="dashboard.php" style="font-size: 0.9rem; color: var(--text-muted);">&larr; Back to Dashboard</a>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h1 class="card-title">📝 Lodge a New FYP Complaint</h1>
                <p style="font-size: 0.88rem; color: var(--text-muted); margin-top: 0.25rem;">
                    Please provide detailed information so the committee can investigate and resolve your issue quickly.
                </p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!empty($success)): ?>
            <div class="alert alert-success">
                <?php echo $success; ?><br>
                <div style="margin-top: 0.75rem;">
                    <a href="complaint-detail.php?id=<?php echo $complaintId; ?>" class="btn btn-primary btn-sm">View Ticket Details &rarr;</a>
                    <a href="dashboard.php" class="btn btn-secondary btn-sm" style="margin-left: 0.5rem;">Return to Dashboard</a>
                </div>
            </div>
        <?php else: ?>

        <form method="POST" action="new-complaint.php" enctype="multipart/form-data">
            <div class="form-group">
                <label class="form-label" for="title">Complaint Subject / Title *</label>
                <input type="text" id="title" name="title" class="form-control" placeholder="Brief summary of the issue (e.g. GPU Lab Access Issue)" required value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
            </div>

            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="department">Academic Department *</label>
                    <select id="department" name="department" class="form-select">
                        <option value="Computer Science">Computer Science</option>
                        <option value="Software Engineering">Software Engineering</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Electrical Engineering">Electrical Engineering</option>
                        <option value="Artificial Intelligence">Artificial Intelligence</option>
                        <option value="Data Science">Data Science</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="category">Grievance Category *</label>
                    <select id="category" name="category" class="form-select">
                        <option value="Supervisor Allocation & Response">Supervisor Allocation & Response</option>
                        <option value="FYP Lab & Equipment Access">FYP Lab & Equipment Access</option>
                        <option value="Mid-term / Final Evaluation Dispute">Mid-term / Final Evaluation Dispute</option>
                        <option value="Turnitin & Plagiarism Clearance">Turnitin & Plagiarism Clearance</option>
                        <option value="Hardware / IoT Component Shortage">Hardware / IoT Component Shortage</option>
                        <option value="Group Member Dispute">Group Member Dispute</option>
                        <option value="General Academic Issue">General Academic Issue</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label" for="priority">Priority Level *</label>
                    <select id="priority" name="priority" class="form-select">
                        <option value="Low">Low (General Query)</option>
                        <option value="Medium" selected>Medium (Standard Processing)</option>
                        <option value="High">High (Impacting Project Timeline)</option>
                        <option value="Urgent">Urgent (Immediate Viva / Deadline Risk)</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Detailed Description *</label>
                <textarea id="description" name="description" class="form-control" placeholder="Provide complete facts, dates, names, or steps taken so far..." required><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label" for="attachment">Attachment (Optional)</label>
                <input type="file" id="attachment" name="attachment" class="form-control">
                <small style="color: var(--text-muted); font-size: 0.8rem; display: block; margin-top: 0.25rem;">
                    Attach screenshots, lab approvals, emails, or error logs (Allowed: JPG, PNG, PDF, DOCX, ZIP - Max 10MB)
                </small>
            </div>

            <div style="display: flex; gap: 1rem; align-items: center; margin-top: 1.5rem;">
                <button type="submit" class="btn btn-primary">Submit FYP Complaint &rarr;</button>
                <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
            </div>
        </form>

        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

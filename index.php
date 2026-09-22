<?php
$pageTitle = "Home";
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

// Fetch summary metrics
$stmt = $pdo->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Resolved' THEN 1 ELSE 0 END) as resolved,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress
FROM complaints");
$stats = $stmt->fetch();

// Fetch recent announcements
$annStmt = $pdo->query("SELECT * FROM announcements ORDER BY created_at DESC LIMIT 3");
$announcements = $annStmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<section class="hero-section">
    <span class="hero-badge">🎓 University Final Year Project</span>
    <h1 class="hero-title">FYP Student Complaint & Grievance Portal</h1>
    <p class="hero-subtitle">
        A centralized, transparent platform for final year students to report academic issues, FYP supervisor concerns, lab hardware shortages, and examination queries.
    </p>
    <div class="hero-actions">
        <?php if ($currentUser): ?>
            <?php if ($currentUser['role'] === 'admin'): ?>
                <a href="admin.php" class="btn btn-primary" style="background:#ffffff; color:var(--primary)!important;">Go to Admin Console &rarr;</a>
            <?php else: ?>
                <a href="new-complaint.php" class="btn btn-primary" style="background:#ffffff; color:var(--primary)!important;">+ Lodge FYP Complaint</a>
                <a href="dashboard.php" class="btn btn-secondary">View My Complaints</a>
            <?php endif; ?>
        <?php else: ?>
            <a href="register.php" class="btn btn-primary" style="background:#ffffff; color:var(--primary)!important;">Student Registration &rarr;</a>
            <a href="login.php" class="btn btn-secondary">Sign In</a>
        <?php endif; ?>
    </div>
</section>

<!-- Quick Ticket Tracker Box -->
<div class="tracker-box">
    <h3 style="margin-bottom: 0.5rem; font-size: 1.15rem; color: var(--primary);">🔍 Quick Ticket Status Lookup</h3>
    <p style="color: var(--text-muted); font-size: 0.9rem; margin-bottom: 1rem;">
        Have a ticket reference number? Track its live progress instantly without logging in (e.g. <code>FYP-2026-1001</code>).
    </p>
    <form id="publicTrackerForm" class="tracker-form">
        <input type="text" id="trackerInput" class="form-control tracker-input" placeholder="Enter Ticket Number (e.g. FYP-2026-1001)" required>
        <button type="submit" class="btn btn-primary">Track Ticket</button>
    </form>
    <div id="trackerResult" style="display: none;"></div>
</div>

<!-- Stats Counter Grid -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon" style="background: #eff6ff; color: #2563eb;">📋</div>
        <div class="stat-info">
            <h3><?php echo (int)($stats['total'] ?? 0); ?></h3>
            <p>Total Complaints Logged</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #fef3c7; color: #d97706;">⏳</div>
        <div class="stat-info">
            <h3><?php echo (int)($stats['in_progress'] ?? 0); ?></h3>
            <p>Under Active Investigation</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon" style="background: #d1fae5; color: #059669;">✅</div>
        <div class="stat-info">
            <h3><?php echo (int)($stats['resolved'] ?? 0); ?></h3>
            <p>Successfully Resolved</p>
        </div>
    </div>
</div>

<!-- Two Column Info & Noticeboard -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-top: 1.5rem;">
    <!-- Key FYP Categories -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📌 Common FYP Grievance Areas</h2>
        </div>
        <div style="display: flex; flex-direction: column; gap: 1rem;">
            <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                <span style="font-size: 1.5rem;">👨‍🏫</span>
                <div>
                    <strong style="color: var(--primary);">Supervisor & Evaluation Allocation</strong>
                    <p style="font-size: 0.88rem; color: var(--text-muted);">Disputes regarding advisor responsiveness, viva scheduling, or evaluation criteria.</p>
                </div>
            </div>
            <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                <span style="font-size: 1.5rem;">💻</span>
                <div>
                    <strong style="color: var(--primary);">Hardware Lab & GPU Access</strong>
                    <p style="font-size: 0.88rem; color: var(--text-muted);">Access to computing clusters, IoT kits, microcontrollers, and specialized equipment.</p>
                </div>
            </div>
            <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
                <span style="font-size: 1.5rem;">📄</span>
                <div>
                    <strong style="color: var(--primary);">Documentation & Plagiarism Checks</strong>
                    <p style="font-size: 0.88rem; color: var(--text-muted);">Turnitin similarity reports, mid-term/final report sign-offs, and library clearance.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Official Noticeboard -->
    <div class="card">
        <div class="card-header">
            <h2 class="card-title">📢 FYP Committee Announcements</h2>
        </div>
        <?php if (!empty($announcements)): ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($announcements as $ann): ?>
                    <div style="padding: 0.75rem; background: #f8fafc; border-radius: var(--radius-md); border-left: 3px solid var(--primary-light);">
                        <strong style="font-size: 0.95rem; color: var(--text-main); display: block; margin-bottom: 0.25rem;">
                            <?php echo htmlspecialchars($ann['title']); ?>
                        </strong>
                        <p style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 0.4rem;">
                            <?php echo htmlspecialchars($ann['content']); ?>
                        </p>
                        <span style="font-size: 0.75rem; color: #94a3b8;">
                            By <?php echo htmlspecialchars($ann['created_by']); ?> • <?php echo date('M d, Y', strtotime($ann['created_at'])); ?>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <p style="color: var(--text-muted); font-size: 0.9rem;">No notices posted at the moment.</p>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

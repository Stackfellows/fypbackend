<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$currentUser = $_SESSION['user'] ?? null;
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? htmlspecialchars($pageTitle) . " | FYP Complaint Portal" : "FYP Student Complaint Portal"; ?></title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <div class="navbar-container">
        <a href="index.php" class="brand-logo">
            <span class="brand-badge">FYP</span>
            <span class="brand-title">Complaint Portal</span>
        </a>
        <ul class="nav-links">
            <li><a href="index.php" class="<?php echo $currentPage === 'index.php' ? 'active' : ''; ?>">Home</a></li>
            <?php if ($currentUser): ?>
                <?php if ($currentUser['role'] === 'admin' || $currentUser['role'] === 'faculty'): ?>
                    <li><a href="admin.php" class="<?php echo $currentPage === 'admin.php' ? 'active' : ''; ?>">Admin Panel</a></li>
                <?php else: ?>
                    <li><a href="dashboard.php" class="<?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">My Dashboard</a></li>
                    <li><a href="new-complaint.php" class="<?php echo $currentPage === 'new-complaint.php' ? 'active' : ''; ?>">New Complaint</a></li>
                <?php endif; ?>
                <li>
                    <span style="font-size: 0.85rem; color: var(--text-muted); padding: 0.35rem 0.5rem; background: #f1f5f9; border-radius: 6px;">
                        👤 <?php echo htmlspecialchars($currentUser['name']); ?> (<?php echo ucfirst($currentUser['role']); ?>)
                    </span>
                </li>
                <li><a href="logout.php" class="btn btn-secondary btn-sm">Logout</a></li>
            <?php else: ?>
                <li><a href="login.php" class="<?php echo $currentPage === 'login.php' ? 'active' : ''; ?>">Login</a></li>
                <li><a href="register.php" class="btn btn-primary btn-sm <?php echo $currentPage === 'register.php' ? 'active' : ''; ?>">Student Register</a></li>
            <?php endif; ?>
        </ul>
    </div>
</nav>

<div class="main-wrapper">

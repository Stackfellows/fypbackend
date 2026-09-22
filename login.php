<?php
$pageTitle = "Login";
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (!empty($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header("Location: admin.php");
    } else {
        header("Location: dashboard.php");
    }
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            unset($user['password']);
            $_SESSION['user'] = $user;

            if ($user['role'] === 'admin') {
                header("Location: admin.php");
            } else {
                header("Location: dashboard.php");
            }
            exit;
        } else {
            $error = 'Invalid email or password. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 480px; margin: 2rem auto;">
    <div class="card">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <span class="brand-badge" style="font-size: 1.1rem; padding: 0.35rem 0.8rem;">FYP</span>
            <h2 style="font-size: 1.5rem; font-weight: 800; margin-top: 0.5rem; color: var(--primary);">Sign In to Portal</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Access your university student or administrative account</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['registered'])): ?>
            <div class="alert alert-success">Registration successful! Please login with your credentials.</div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="form-group">
                <label class="form-label" for="email">University Email</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="e.g. student@fyp.edu.pk" required>
            </div>

            <div class="form-group">
                <label class="form-label" for="password">Password</label>
                <input type="password" id="password" name="password" class="form-control" placeholder="••••••••" required>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">Sign In &rarr;</button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--text-muted);">
            Don't have an account? <a href="register.php" style="font-weight: 600;">Student Register</a>
        </div>

        <!-- 1-Click Demo Credentials for University Evaluation -->
        <div style="margin-top: 1.75rem; padding-top: 1.25rem; border-top: 1px dashed var(--border-color); text-align: center;">
            <p style="font-size: 0.82rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; margin-bottom: 0.75rem;">
                🚀 Quick Demo Login Autofill
            </p>
            <div style="display: flex; gap: 0.5rem; justify-content: center;">
                <button type="button" class="btn btn-secondary btn-sm" onclick="fillCreds('student@fyp.edu.pk', 'student123')">
                    Student Account
                </button>
                <button type="button" class="btn btn-secondary btn-sm" onclick="fillCreds('admin@fyp.edu.pk', 'admin123')">
                    Admin Account
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function fillCreds(email, pass) {
    document.getElementById('email').value = email;
    document.getElementById('password').value = pass;
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

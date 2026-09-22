<?php
$pageTitle = "Student Registration";
require_once __DIR__ . '/config/db.php';
$pdo = getDBConnection();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!empty($_SESSION['user'])) {
    header("Location: dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $rollNo = trim($_POST['roll_no'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? 'Computer Science');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill out all required fields.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } else {
        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(?)");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email address already exists.';
        } else {
            // Check roll number if provided
            if (!empty($rollNo)) {
                $chk = $pdo->prepare("SELECT id FROM users WHERE LOWER(roll_no) = LOWER(?)");
                $chk->execute([$rollNo]);
                if ($chk->fetch()) {
                    $error = 'An account with this Roll Number already exists.';
                }
            }

            if (empty($error)) {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins = $pdo->prepare("INSERT INTO users (name, roll_no, email, password, role, department) VALUES (?, ?, ?, ?, 'student', ?)");
                $ins->execute([$name, $rollNo ?: null, $email, $hash, $department]);

                // Auto login or redirect to login
                $userId = $pdo->lastInsertId();
                $getUser = $pdo->prepare("SELECT id, name, roll_no, email, role, department, created_at FROM users WHERE id = ?");
                $getUser->execute([$userId]);
                $_SESSION['user'] = $getUser->fetch();

                header("Location: dashboard.php");
                exit;
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div style="max-width: 520px; margin: 2rem auto;">
    <div class="card">
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <span class="brand-badge" style="font-size: 1.1rem; padding: 0.35rem 0.8rem;">FYP</span>
            <h2 style="font-size: 1.5rem; font-weight: 800; margin-top: 0.5rem; color: var(--primary);">Student Registration</h2>
            <p style="color: var(--text-muted); font-size: 0.9rem;">Register to log and track your final year project complaints</p>
        </div>

        <?php if (!empty($error)): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="register.php">
            <div class="form-group">
                <label class="form-label" for="name">Full Name *</label>
                <input type="text" id="name" name="name" class="form-control" placeholder="e.g. Usama Khan" required value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="roll_no">Student Roll No *</label>
                    <input type="text" id="roll_no" name="roll_no" class="form-control" placeholder="e.g. FYP-BSCS-042" required value="<?php echo htmlspecialchars($_POST['roll_no'] ?? ''); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="department">Department</label>
                    <select id="department" name="department" class="form-select">
                        <option value="Computer Science">Computer Science</option>
                        <option value="Software Engineering">Software Engineering</option>
                        <option value="Information Technology">Information Technology</option>
                        <option value="Electrical Engineering">Electrical Engineering</option>
                        <option value="Artificial Intelligence">Artificial Intelligence</option>
                        <option value="Data Science">Data Science</option>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">University Email *</label>
                <input type="email" id="email" name="email" class="form-control" placeholder="e.g. student@fyp.edu.pk" required value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                <div class="form-group">
                    <label class="form-label" for="password">Password *</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="Min 6 characters" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" placeholder="Re-type password" required>
                </div>
            </div>

            <button type="submit" class="btn btn-primary btn-block" style="margin-top: 1rem;">Complete Registration &rarr;</button>
        </form>

        <div style="margin-top: 1.5rem; text-align: center; font-size: 0.9rem; color: var(--text-muted);">
            Already have an account? <a href="login.php" style="font-weight: 600;">Sign In</a>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

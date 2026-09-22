<?php
/**
 * FYP Student Complaint Management System
 * Database Configuration & Auto-Migration (PDO SQLite, MySQL & PostgreSQL/Supabase Compatible)
 */

// ─── 1. LOAD ENVIRONMENT VARIABLES (.env) ──────────────────────────────────
if (!function_exists('loadEnvFile')) {
    function loadEnvFile($envPath) {
        if (!file_exists($envPath)) {
            return;
        }
        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || strpos($line, '#') === 0) {
                continue;
            }
            if (strpos($line, '=') !== false) {
                list($k, $v) = explode('=', $line, 2);
                $k = trim($k);
                $v = trim($v);
                if (preg_match('/^(["\'])(.*)\1$/', $v, $m)) {
                    $v = $m[2];
                }
                if (!array_key_exists($k, $_SERVER) && !array_key_exists($k, $_ENV)) {
                    putenv("$k=$v");
                    $_ENV[$k] = $v;
                    $_SERVER[$k] = $v;
                }
            }
        }
    }
}
loadEnvFile(__DIR__ . '/../.env');

// ─── 2. PARSE DATABASE_URL IF PROVIDED ─────────────────────────────────────
$dbUrl = getenv('DATABASE_URL');
if ($dbUrl) {
    $parsedUrl = parse_url($dbUrl);
    if ($parsedUrl) {
        $scheme = $parsedUrl['scheme'] ?? '';
        $inferredType = ($scheme === 'postgres' || $scheme === 'postgresql' || $scheme === 'pgsql') ? 'pgsql' : $scheme;
        if (!defined('DB_TYPE') && !empty($inferredType)) define('DB_TYPE', $inferredType);
        if (!defined('DB_HOST') && isset($parsedUrl['host'])) define('DB_HOST', $parsedUrl['host']);
        if (!defined('DB_PORT') && isset($parsedUrl['port'])) define('DB_PORT', $parsedUrl['port']);
        if (!defined('DB_USER') && isset($parsedUrl['user'])) define('DB_USER', urldecode($parsedUrl['user']));
        if (!defined('DB_PASS') && isset($parsedUrl['pass'])) define('DB_PASS', urldecode($parsedUrl['pass']));
        if (!defined('DB_NAME') && isset($parsedUrl['path'])) define('DB_NAME', ltrim($parsedUrl['path'], '/'));
    }
}

// ─── 3. DEFAULT CONFIGURATION CONSTANTS ────────────────────────────────────
if (!defined('DB_TYPE')) define('DB_TYPE', getenv('DB_TYPE') ?: 'sqlite'); // 'sqlite', 'mysql', or 'pgsql'
if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
if (!defined('DB_PORT')) define('DB_PORT', getenv('DB_PORT') ?: (DB_TYPE === 'pgsql' ? 5432 : 3306));
if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: 'fyp_complaints_db');
if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: 'root');
if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') !== false ? getenv('DB_PASS') : '');
if (!defined('DB_SSLMODE')) define('DB_SSLMODE', getenv('DB_SSLMODE') ?: 'require');

// ─── 4. CUSTOM PDO WRAPPER (POSTGRESQL LASTVAL SUPPORT) ────────────────────
class AppPDO extends PDO {
    public function lastInsertId(?string $name = null): string|false {
        $id = parent::lastInsertId($name);
        if ($id !== false && $id !== '' && $id !== '0' && $id !== null) {
            return $id;
        }
        if ($this->getAttribute(PDO::ATTR_DRIVER_NAME) === 'pgsql') {
            try {
                $val = $this->query("SELECT LASTVAL()")->fetchColumn();
                if ($val !== false) {
                    return (string)$val;
                }
            } catch (Throwable $e) {}
        }
        return $id;
    }
}

// ─── 5. GET DATABASE CONNECTION ────────────────────────────────────────────
function getDBConnection() {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $driver = strtolower(DB_TYPE);

    try {
        if ($driver === 'sqlite') {
            $pdo = connectSqlite();
        } elseif ($driver === 'pgsql' || $driver === 'postgres' || $driver === 'postgresql') {
            $port = DB_PORT ? ";port=" . DB_PORT : "";
            $ssl = DB_SSLMODE ? ";sslmode=" . DB_SSLMODE : "";
            $dsn = "pgsql:host=" . DB_HOST . $port . ";dbname=" . DB_NAME . $ssl . ";connect_timeout=3";

            $pdo = new AppPDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_TIMEOUT => 4
            ]);

            initDatabase($pdo, 'pgsql');
        } else {
            // MySQL
            $port = DB_PORT ? ";port=" . DB_PORT : "";
            $dsn = "mysql:host=" . DB_HOST . $port . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new AppPDO($dsn, DB_USER, DB_PASS);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            initDatabase($pdo, 'mysql');
        }
    } catch (PDOException $e) {
        $msg = $e->getMessage();

        // Optional fallback to SQLite if enabled
        if (getenv('DB_FALLBACK_TO_SQLITE') === 'true') {
            error_log("Database connection failed ($msg). Falling back to SQLite.");
            $pdo = connectSqlite();
            return $pdo;
        }

        $isApi = (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)
              || (!empty($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/api/') !== false);

        $hint = "";
        if ($driver === 'pgsql' || $driver === 'postgres' || $driver === 'postgresql') {
            $hint = "PostgreSQL authentication or network error. If using Supabase, please verify or reset your Database Password in Supabase: Dashboard -> Project Settings -> Database -> Database Password. You can also use the Supabase Connection Pooler string for IPv4 compatibility.";
        }

        if ($isApi) {
            http_response_code(500);
            header('Content-Type: application/json');
            die(json_encode([
                'status' => 'error',
                'message' => 'Database connection failed: ' . $msg,
                'hint' => $hint
            ]));
        } else {
            die("
                <div style='font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;max-width:680px;margin:50px auto;padding:28px;border:1px solid #fca5a5;background:#fff1f2;border-radius:14px;color:#991b1b;box-shadow:0 10px 25px -5px rgba(0,0,0,0.1);'>
                    <h2 style='margin-top:0;display:flex;align-items:center;gap:8px;'>⚠️ Database Connection Failed</h2>
                    <p style='margin:10px 0;'><strong>Error details:</strong> <code>" . htmlspecialchars($msg) . "</code></p>
                    " . ($hint ? "<div style='background:#fee2e2;padding:14px;border-radius:8px;margin-top:14px;line-height:1.5;'><strong>💡 How to fix:</strong> " . htmlspecialchars($hint) . "</div>" : "") . "
                    <div style='margin-top:16px;padding-top:12px;border-top:1px solid #fecaca;font-size:13px;color:#6b7280;display:flex;justify-content:space-between;'>
                        <span>Configured in <code>.env</code></span>
                        <span>Host: <code>" . htmlspecialchars(DB_HOST) . "</code></span>
                    </div>
                </div>
            ");
        }
    }

    return $pdo;
}

function connectSqlite() {
    $dbDir = __DIR__ . '/../database';
    if (!is_dir($dbDir)) {
        mkdir($dbDir, 0777, true);
    }
    $dbPath = $dbDir . '/fyp_complaints.sqlite';
    $isNew = !file_exists($dbPath);

    $pdo = new AppPDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec("PRAGMA foreign_keys = ON;");

    if ($isNew || filesize($dbPath) === 0) {
        initDatabase($pdo, 'sqlite');
    }
    return $pdo;
}

// ─── 6. AUTO-MIGRATION & SEEDING ───────────────────────────────────────────
function initDatabase($pdo, $driver = 'sqlite') {
    if ($driver === 'sqlite') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                roll_no TEXT UNIQUE,
                email TEXT NOT NULL UNIQUE,
                password TEXT NOT NULL,
                role TEXT NOT NULL DEFAULT 'student',
                department TEXT DEFAULT 'Computer Science',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS complaints (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_no TEXT NOT NULL UNIQUE,
                user_id INTEGER NOT NULL,
                title TEXT NOT NULL,
                department TEXT NOT NULL,
                category TEXT NOT NULL,
                priority TEXT NOT NULL DEFAULT 'Medium',
                status TEXT NOT NULL DEFAULT 'Pending',
                description TEXT NOT NULL,
                attachment TEXT DEFAULT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS complaint_comments (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                complaint_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                message TEXT NOT NULL,
                is_admin INTEGER DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            );

            CREATE TABLE IF NOT EXISTS announcements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                created_by TEXT NOT NULL DEFAULT 'Admin Desk',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            );
        ");
    } elseif ($driver === 'pgsql') {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id SERIAL PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                roll_no VARCHAR(100) UNIQUE,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                role VARCHAR(50) NOT NULL DEFAULT 'student',
                department VARCHAR(100) DEFAULT 'Computer Science',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS complaints (
                id SERIAL PRIMARY KEY,
                ticket_no VARCHAR(50) NOT NULL UNIQUE,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                title VARCHAR(255) NOT NULL,
                department VARCHAR(100) NOT NULL,
                category VARCHAR(100) NOT NULL,
                priority VARCHAR(50) NOT NULL DEFAULT 'Medium',
                status VARCHAR(50) NOT NULL DEFAULT 'Pending',
                description TEXT NOT NULL,
                attachment TEXT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS complaint_comments (
                id SERIAL PRIMARY KEY,
                complaint_id INTEGER NOT NULL REFERENCES complaints(id) ON DELETE CASCADE,
                user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                message TEXT NOT NULL,
                is_admin INTEGER DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );

            CREATE TABLE IF NOT EXISTS announcements (
                id SERIAL PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                content TEXT NOT NULL,
                created_by VARCHAR(100) NOT NULL DEFAULT 'Admin Desk',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            );
        ");
    }

    // Seed initial default accounts if empty
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
        $row = $stmt ? $stmt->fetch() : null;
        $userCount = $row ? intval($row['count']) : 0;

        if ($userCount === 0) {
            $adminPass = password_hash('admin123', PASSWORD_BCRYPT);
            $studentPass = password_hash('student123', PASSWORD_BCRYPT);

            // Insert Admin
            $stmt = $pdo->prepare("INSERT INTO users (name, roll_no, email, password, role, department) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute(['FYP Portal Admin', 'ADMIN-01', 'admin@fyp.edu.pk', $adminPass, 'admin', 'Administration']);

            // Insert Demo Student
            $stmt->execute(['Ali Raza', 'FYP-BSCS-001', 'student@fyp.edu.pk', $studentPass, 'student', 'Computer Science']);
            $studentId = $pdo->lastInsertId();

            // Insert Sample Announcements
            $annStmt = $pdo->prepare("INSERT INTO announcements (title, content, created_by) VALUES (?, ?, ?)");
            $annStmt->execute([
                'FYP Progress Presentation Schedule',
                'All final year students are requested to verify their project supervisor assignment. Please log any project disputes or supervisor allocation issues here.',
                'FYP Committee'
            ]);
            $annStmt->execute([
                'Lab & Hardware Component Requests',
                'Students working on hardware/IoT FYP modules can file requests or report hardware lab malfunctions through this portal.',
                'FYP Lab Administrator'
            ]);

            // Insert Sample Complaints for demonstration
            $compStmt = $pdo->prepare("INSERT INTO complaints (ticket_no, user_id, title, department, category, priority, status, description) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $compStmt->execute([
                'FYP-2026-1001',
                $studentId ?: 2,
                'Hardware Lab GPU Access Issue for Deep Learning FYP',
                'Computer Science',
                'FYP Lab Access',
                'High',
                'In Progress',
                'Our FYP group is developing a computer vision model and requires access to the high-performance GPU workstation in Lab 4. The current credentials are not accepting login.'
            ]);
            $comp1Id = $pdo->lastInsertId();

            $compStmt->execute([
                'FYP-2026-1002',
                $studentId ?: 2,
                'Delay in FYP Proposal Sign-off from Committee',
                'Computer Science',
                'Supervisor & Evaluation',
                'Medium',
                'Pending',
                'We submitted our mid-term FYP documentation two weeks ago. Seeking an update on supervisor endorsement so we can proceed with sprint 2 implementation.'
            ]);

            // Insert Sample Comment
            $commStmt = $pdo->prepare("INSERT INTO complaint_comments (complaint_id, user_id, message, is_admin) VALUES (?, ?, ?, ?)");
            $commStmt->execute([
                $comp1Id ?: 1,
                1, // Admin ID
                'Ticket assigned to Systems Administrator. The GPU lab credentials will be reset and shared with your group leader today.',
                1
            ]);
        }
    } catch (Throwable $e) {
        // Table or query initialization handled gracefully
    }
}

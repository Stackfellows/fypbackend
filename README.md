# FYP Student Complaint & Grievance Management System

A complete, modern, and lightweight **University Final Year Project (FYP)** Complaint Management Portal built with **pure, clean PHP** and a responsive integrated frontend.

---

## 🌟 Key Features

1. **Academic Student Portal**:
   - Register with University Email, Student Roll Number, and Academic Department.
   - Dashboard showing live metrics (Total Filed, Under Investigation, Resolved).
   - Lodge new complaints with categories (Supervisor Allocation, Lab/Hardware Resources, Turnitin Plagiarism, Evaluation, Viva disputes).
   - File attachment support (screenshots, lab slips, logs).
   - Real-time discussion thread with faculty & administrators.

2. **Public Ticket Status Tracker**:
   - Quick lookup on the homepage using Ticket Reference Number (e.g. `FYP-2026-1001`) without needing to log in.

3. **Faculty & Admin Management Panel**:
   - Manage all student complaints in one centralized dashboard.
   - Update complaint statuses (`Pending` &rarr; `In Progress` &rarr; `Resolved` / `Rejected`).
   - Post official resolution notes & remarks on student tickets.
   - Publish university circular announcements and notices.

4. **Zero-Configuration Database**:
   - Powered by **PDO SQLite** by default: The database (`database/fyp_complaints.sqlite`) is created and seeded **automatically** on first visit. No manual setup required!
   - Also includes `database/schema_mysql.sql` for students or university evaluators who want to run on MySQL / phpMyAdmin.

---

## 🚀 How to Run the Project

### Option A: Using PHP Built-in Server (Recommended & Fastest)

Open your terminal or command prompt inside this project folder:

```bash
# Using standard PHP
php -S localhost:8000

# Or using XAMPP PHP directly on Windows:
"C:\xampp\php\php.exe" -S localhost:8000
```

Now open your web browser and visit:
👉 **[http://localhost:8000](http://localhost:8000)**

---

### Option B: Using XAMPP (Apache)

1. Copy or move this folder (`Complaint-Portal-Backend`) into your XAMPP web directory:
   `C:\xampp\htdocs\Complaint-Portal`
2. Start **Apache** from the XAMPP Control Panel.
3. Open your browser and navigate to:
   👉 **`http://localhost/Complaint-Portal/`**

*(Optional)* If you wish to use MySQL instead of SQLite:
1. Open phpMyAdmin (`http://localhost/phpmyadmin`).
2. Import `database/schema_mysql.sql`.
3. In `config/db.php`, change `define('DB_TYPE', 'sqlite');` to `define('DB_TYPE', 'mysql');`.

---

## 🔑 Default Test Credentials

For quick demonstration and evaluation, the portal includes pre-seeded demo accounts with 1-click autofill buttons on the login page:

| Role | Email | Password | Roll No |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@fyp.edu.pk` | `admin123` | `ADMIN-01` |
| **Student** | `student@fyp.edu.pk` | `student123` | `FYP-BSCS-001` |

---

## 📁 Project Directory Structure

```text
├── config/
│   └── db.php                  # PDO connection & auto-table creation
├── database/
│   ├── fyp_complaints.sqlite   # SQLite database (auto-generated)
│   └── schema_mysql.sql        # Standalone MySQL schema for phpMyAdmin
├── api/
│   ├── auth.php                # Authentication API (Login, Register, Logout)
│   ├── complaints.php          # Complaint CRUD & status management
│   ├── comments.php            # Ticket discussion & timeline remarks
│   ├── stats.php               # Dashboard analytics counters
│   └── announcements.php       # Committee circular notices
├── includes/
│   ├── header.php              # Navigation & branding
│   └── footer.php              # Footer notice
├── assets/
│   ├── css/style.css           # Modern, responsive UI theme
│   └── js/app.js               # Interactive frontend logic & ticket lookup
├── uploads/                    # Storage for ticket file attachments
├── index.php                   # Homepage & quick ticket tracker
├── login.php                   # Sign-in page with 1-click demo buttons
├── register.php                # Student registration
├── dashboard.php               # Student dashboard
├── new-complaint.php           # Complaint submission form
├── complaint-detail.php        # Ticket details, progress bar & timeline
├── admin.php                   # Admin moderation console
└── logout.php                  # Session logout
```

---

## 🎓 Academic FYP Note
This project was developed as a University Final Year Project (FYP) demonstration for a student complaint and grievance resolution portal.

-- ==========================================================
-- FYP University Student Complaint Management System
-- MySQL Schema & Initial Data
-- Compatible with XAMPP MySQL / phpMyAdmin
-- ==========================================================

CREATE DATABASE IF NOT EXISTS `fyp_complaints_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `fyp_complaints_db`;

-- 1. Users Table
CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(150) NOT NULL,
  `roll_no` VARCHAR(50) UNIQUE NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('student', 'admin', 'faculty') DEFAULT 'student',
  `department` VARCHAR(100) DEFAULT 'Computer Science',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Complaints Table
CREATE TABLE IF NOT EXISTS `complaints` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_no` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `department` VARCHAR(100) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `priority` ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
  `status` ENUM('Pending', 'In Progress', 'Resolved', 'Rejected') DEFAULT 'Pending',
  `description` TEXT NOT NULL,
  `attachment` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Complaint Comments / Resolution Log
CREATE TABLE IF NOT EXISTS `complaint_comments` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `complaint_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `is_admin` TINYINT(1) DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`complaint_id`) REFERENCES `complaints`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Announcements Table
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NOT NULL,
  `created_by` VARCHAR(100) DEFAULT 'FYP Committee',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed Default Accounts
-- Default Admin: admin@fyp.edu.pk / admin123
-- Default Student: student@fyp.edu.pk / student123
INSERT INTO `users` (`name`, `roll_no`, `email`, `password`, `role`, `department`) VALUES
('FYP Portal Administrator', 'ADMIN-01', 'admin@fyp.edu.pk', '$2y$10$tZf4Q6eG2Jc4N41RskKxeeD8l1O3BfXN93a9wK0B7520e2xN78qC2', 'admin', 'Administration'),
('Ali Raza (Student)', 'FYP-BSCS-001', 'student@fyp.edu.pk', '$2y$10$wIuCsh2mY7H1R4g2706tseVf0jO8yW.gR67XvP27rKq07v.Uj9ZkK', 'student', 'Computer Science')
ON DUPLICATE KEY UPDATE `email`=`email`;

-- Seed Sample Announcements
INSERT INTO `announcements` (`title`, `content`, `created_by`) VALUES
('FYP Progress Presentation Schedule', 'All final year students must verify their supervisor allocation and report any disputes.', 'FYP Committee'),
('Hardware Lab Access & Resource Allocation', 'Students requiring specialized hardware components or GPU workstations should submit their request tickets.', 'FYP Lab Admin');

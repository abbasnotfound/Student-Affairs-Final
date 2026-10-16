-- =====================================================================
-- Student Affairs - Complaint Management Portal
-- Database Schema for MySQL / MariaDB (XAMPP)
-- =====================================================================

CREATE DATABASE IF NOT EXISTS `student_affairs`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `student_affairs`;

-- ---------------------------------------------------------------------
-- 1. Administrators and Staff Members
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(80) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'staff') NOT NULL DEFAULT 'staff',
  `full_name` VARCHAR(120) NOT NULL DEFAULT '',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Complaints Table
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `complaints` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `tracking_code` VARCHAR(32) NOT NULL UNIQUE,
  `student_name` VARCHAR(120) NOT NULL,
  `student_class` VARCHAR(60) NOT NULL,
  `section_name` VARCHAR(60) NOT NULL DEFAULT '',
  `email` VARCHAR(254) NOT NULL,
  `category` VARCHAR(100) NOT NULL,
  `subject` VARCHAR(180) NOT NULL,
  `description` TEXT NOT NULL,
  `status` ENUM('Pending', 'In Progress', 'Resolved', 'Rejected') NOT NULL DEFAULT 'Pending',
  `admin_note` TEXT DEFAULT NULL,
  `assigned_to` INT UNSIGNED DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_status` (`status`),
  INDEX `idx_created_at` (`created_at`),
  INDEX `idx_category` (`category`),
  CONSTRAINT `fk_complaints_assigned`
    FOREIGN KEY (`assigned_to`) REFERENCES `admins` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Complaint Status Logs / History
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `complaint_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `complaint_id` INT UNSIGNED NOT NULL,
  `changed_by` INT UNSIGNED DEFAULT NULL,
  `old_status` VARCHAR(50) NOT NULL,
  `new_status` VARCHAR(50) NOT NULL,
  `note` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_logs_complaint`
    FOREIGN KEY (`complaint_id`) REFERENCES `complaints` (`id`)
    ON DELETE CASCADE
    ON UPDATE CASCADE,
  CONSTRAINT `fk_logs_admin`
    FOREIGN KEY (`changed_by`) REFERENCES `admins` (`id`)
    ON DELETE SET NULL
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Email Notification Logs
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `email_logs` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `complaint_id` INT UNSIGNED DEFAULT NULL,
  `recipient_email` VARCHAR(254) NOT NULL,
  `tracking_code` VARCHAR(32) NOT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `body_preview` TEXT NOT NULL,
  `delivery_status` ENUM('Sent', 'Logged', 'Failed') NOT NULL DEFAULT 'Sent',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_email_code` (`tracking_code`),
  INDEX `idx_recipient` (`recipient_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


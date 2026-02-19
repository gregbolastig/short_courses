-- ============================================================================
-- STUDENT REGISTRATION SYSTEM - DATABASE SCHEMA
-- ============================================================================
-- Compatible with MySQL 5.5+ and MariaDB 10.0+
-- Table Prefix: shortcourse_
-- ============================================================================

CREATE DATABASE IF NOT EXISTS student_registration_db;
USE student_registration_db;

-- ============================================================================
-- USERS & AUTHENTICATION
-- ============================================================================

CREATE TABLE IF NOT EXISTS shortcourse_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username (username),
    INDEX idx_email (email)
);

-- ============================================================================
-- STUDENTS
-- ============================================================================

CREATE TABLE IF NOT EXISTS shortcourse_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    
    -- Personal Information
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    extension_name VARCHAR(20),
    birthday DATE NOT NULL,
    age INT NOT NULL,
    sex ENUM('Male','Female','Other') NOT NULL,
    civil_status VARCHAR(50) NOT NULL,
    
    -- Contact Information
    contact_number VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    
    -- Address Information
    province VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    street_address VARCHAR(200),
    place_of_birth VARCHAR(200) NOT NULL,
    
    -- Guardian Information
    guardian_last_name VARCHAR(100) NOT NULL,
    guardian_first_name VARCHAR(100) NOT NULL,
    guardian_middle_name VARCHAR(100),
    guardian_extension VARCHAR(20),
    parent_contact VARCHAR(20) NOT NULL,
    
    -- System Information
    profile_picture VARCHAR(255),
    uli VARCHAR(50) NOT NULL UNIQUE,
    last_school VARCHAR(200) NOT NULL,
    school_province VARCHAR(100) NOT NULL,
    school_city VARCHAR(100) NOT NULL,
    verification_code VARCHAR(4) NOT NULL DEFAULT '0000',
    is_verified BOOLEAN DEFAULT FALSE,
    
    -- Status
    status ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Legacy fields (for backward compatibility)
    course VARCHAR(200) NULL,
    nc_level VARCHAR(10) NULL,
    adviser VARCHAR(200) NULL,
    training_start DATE NULL,
    training_end DATE NULL,
    
    -- Soft Delete
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    
    FOREIGN KEY (approved_by) REFERENCES shortcourse_users(id) ON DELETE SET NULL,
    FOREIGN KEY (deleted_by) REFERENCES shortcourse_users(id) ON DELETE SET NULL,
    
    INDEX idx_student_id (student_id),
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_uli (uli),
    INDEX idx_name (last_name, first_name),
    INDEX idx_created_at (created_at)
);

-- ============================================================================
-- COURSES & ADVISERS
-- ============================================================================

CREATE TABLE IF NOT EXISTS shortcourse_courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(200) NOT NULL UNIQUE,
    course_code VARCHAR(20) UNIQUE,
    description TEXT,
    duration_hours INT,
    nc_levels VARCHAR(100) DEFAULT 'NC I,NC II',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    
    FOREIGN KEY (deleted_by) REFERENCES shortcourse_users(id) ON DELETE SET NULL,
    
    INDEX idx_course_name (course_name),
    INDEX idx_course_code (course_code),
    INDEX idx_active (is_active)
);

CREATE TABLE IF NOT EXISTS shortcourse_advisers (
    adviser_id INT AUTO_INCREMENT PRIMARY KEY,
    adviser_name VARCHAR(200) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    
    FOREIGN KEY (deleted_by) REFERENCES shortcourse_users(id) ON DELETE SET NULL,
    
    INDEX idx_adviser_name (adviser_name),
    INDEX idx_active (is_active)
);

-- ============================================================================
-- COURSE APPLICATIONS
-- ============================================================================

CREATE TABLE IF NOT EXISTS shortcourse_course_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    nc_level VARCHAR(10) NULL,
    
    -- Training details
    adviser VARCHAR(255) NULL,
    training_start DATE NULL,
    training_end DATE NULL,
    
    -- Status
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    notes TEXT NULL,
    
    -- Enrollment tracking
    enrollment_created BOOLEAN DEFAULT FALSE,
    enrollment_id INT NULL,
    
    -- Soft Delete
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    
    FOREIGN KEY (student_id) REFERENCES shortcourse_students(id) ON DELETE RESTRICT,
    FOREIGN KEY (course_id) REFERENCES shortcourse_courses(course_id) ON DELETE RESTRICT,
    FOREIGN KEY (reviewed_by) REFERENCES shortcourse_users(id) ON DELETE SET NULL,
    FOREIGN KEY (deleted_by) REFERENCES shortcourse_users(id) ON DELETE SET NULL,
    
    INDEX idx_student_id (student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_status (status),
    INDEX idx_applied_at (applied_at)
);

-- ============================================================================
-- SYSTEM ACTIVITIES
-- ============================================================================

CREATE TABLE IF NOT EXISTS shortcourse_system_activities (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_type ENUM('admin', 'student', 'system') NOT NULL DEFAULT 'system',
    activity_type VARCHAR(50) NOT NULL,
    activity_description TEXT NOT NULL,
    entity_type VARCHAR(50) NULL,
    entity_id INT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    session_id VARCHAR(128) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES shortcourse_users(id) ON DELETE SET NULL,
    
    INDEX idx_activity_type (activity_type),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    INDEX idx_entity (entity_type, entity_id)
);

-- ============================================================================
-- CHECKLIST & BOOKKEEPING
-- ============================================================================

CREATE TABLE IF NOT EXISTS shortcourse_checklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shortcourse_bookkeeping_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    enrollment_id INT NOT NULL,
    receipt_number VARCHAR(9) NOT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    UNIQUE KEY unique_enrollment_receipt (enrollment_id),
    
    INDEX idx_student_id (student_id),
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_created_by (created_by),
    
    FOREIGN KEY (student_id) REFERENCES shortcourse_students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

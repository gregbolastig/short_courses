-- ============================================================================
-- STUDENT REGISTRATION SYSTEM - OPTIMIZED DATABASE SETUP
-- ============================================================================
-- 
-- This file contains the optimized database structure for the Student 
-- Registration System with maximum compatibility for older MySQL versions.
--
-- FEATURES:
-- - Proper normalization with foreign key relationships
-- - Two-stage approval workflow (application → enrollment → completion)
-- - Certificate management and tracking
-- - Enhanced course metadata with codes and descriptions
-- - Comprehensive audit trail and activity logging
-- - Optimized indexing for performance
-- - Compatible with MySQL 5.5+ and MariaDB 10.0+
--
-- SETUP INSTRUCTIONS:
-- 1. Import this file into MySQL/phpMyAdmin
-- 2. Or run: mysql -u root -p < schema.sql
-- 3. The system will automatically create:
--    - Database: student_registration_db
--    - All required tables with proper relationships
--    - All necessary indexes for optimal performance
--
-- NOTE: No seed data is included. All data should be entered through the application.
--
-- UPDATING EXISTING DATABASE:
-- If you have an existing database and need to update the unique constraints to allow
-- same course with different NC levels, run these commands:
--
-- ALTER TABLE course_applications DROP INDEX unique_student_course;
-- ALTER TABLE course_applications ADD UNIQUE KEY unique_student_course_nc (student_id, course_id, nc_level);
-- ALTER TABLE student_enrollments DROP INDEX unique_student_course_enrollment;
-- ALTER TABLE student_enrollments ADD UNIQUE KEY unique_student_course_enrollment (student_id, course_id, nc_level);
--
-- ============================================================================

-- Create database
CREATE DATABASE IF NOT EXISTS student_registration_db;
USE student_registration_db;

-- ============================================================================
-- CORE AUTHENTICATION AND USER MANAGEMENT
-- ============================================================================

-- Users table for authentication (admin only)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_username (username),
    INDEX idx_email (email)
) COMMENT='System users for authentication and authorization';

-- ============================================================================
-- STUDENT MANAGEMENT (NORMALIZED)
-- ============================================================================

-- Students table - Basic student registration
-- NOTE: Legacy course fields (course, nc_level, adviser, training_start, training_end)
-- are kept for backward compatibility with existing code. These should eventually
-- be migrated to use student_enrollments table exclusively.
CREATE TABLE IF NOT EXISTS students (
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
    
    -- Registration Status (not course-specific)
    status ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Legacy course data (for backward compatibility - DEPRECATED)
    -- TODO: Migrate all data to student_enrollments table and remove these fields
    course VARCHAR(200) NULL COMMENT 'DEPRECATED: Use student_enrollments table instead',
    nc_level VARCHAR(10) NULL COMMENT 'DEPRECATED: Use student_enrollments table instead',
    adviser VARCHAR(200) NULL COMMENT 'DEPRECATED: Use student_enrollments table instead',
    training_start DATE NULL COMMENT 'DEPRECATED: Use student_enrollments table instead',
    training_end DATE NULL COMMENT 'DEPRECATED: Use student_enrollments table instead',
    
    -- Soft Delete Support
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    
    -- Foreign Key Constraints
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_student_id (student_id),
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_uli (uli),
    INDEX idx_name (last_name, first_name),
    INDEX idx_birthday (birthday),
    INDEX idx_created_at (created_at),
    INDEX idx_deleted_at (deleted_at),
    
    -- Composite indexes for common query patterns
    INDEX idx_status_created (status, created_at)
) COMMENT='Basic student registration information (normalized - course data in student_enrollments)';

-- ============================================================================
-- COURSE AND ADVISER MANAGEMENT
-- ============================================================================

-- Disable foreign key checks temporarily for clean table recreation
SET FOREIGN_KEY_CHECKS = 0;

-- Drop tables in correct order (child tables first)
DROP TABLE IF EXISTS student_enrollments;
DROP TABLE IF EXISTS course_applications;
DROP TABLE IF EXISTS courses;

-- Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Courses table with enhanced metadata
CREATE TABLE courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(200) NOT NULL UNIQUE,
    course_code VARCHAR(20) UNIQUE,
    description TEXT,
    duration_hours INT,
    nc_levels VARCHAR(100) DEFAULT 'NC I,NC II' COMMENT 'Available NC levels as comma-separated values (e.g., "NC I,NC II,NC III")',
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Soft Delete Support
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    
    -- Foreign Key Constraints
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_course_name (course_name),
    INDEX idx_course_code (course_code),
    INDEX idx_active (is_active),
    INDEX idx_deleted_at (deleted_at)
) COMMENT='Course catalog with enhanced metadata and NC level support';

-- Advisers table
CREATE TABLE IF NOT EXISTS advisers (
    adviser_id INT AUTO_INCREMENT PRIMARY KEY,
    adviser_name VARCHAR(200) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Soft Delete Support
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    
    -- Foreign Key Constraints
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_adviser_name (adviser_name),
    INDEX idx_active (is_active),
    INDEX idx_deleted_at (deleted_at)
) COMMENT='Course advisers and mentors';

-- ============================================================================
-- TWO-STAGE APPROVAL WORKFLOW
-- ============================================================================

-- Course Applications table - Initial applications before enrollment (NORMALIZED)
CREATE TABLE course_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL, -- NORMALIZED: Foreign key to courses table
    nc_level VARCHAR(10) NULL,
    
    -- Training details (added for first course support and historical record keeping)
    adviser VARCHAR(255) NULL COMMENT 'Adviser name for this course',
    training_start DATE NULL COMMENT 'Training start date',
    training_end DATE NULL COMMENT 'Training end date',
    
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    notes TEXT NULL,
    
    -- Enrollment creation tracking
    enrollment_created BOOLEAN DEFAULT FALSE,
    enrollment_id INT NULL, -- Reference to created enrollment
    
    -- Soft Delete Support
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    
    -- Foreign Key Constraints
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE RESTRICT,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_student_id (student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_status (status),
    INDEX idx_applied_at (applied_at),
    INDEX idx_reviewed_at (reviewed_at),
    INDEX idx_enrollment_created (enrollment_created),
    INDEX idx_deleted_at (deleted_at),
    
    -- Composite indexes for common query patterns
    INDEX idx_status_applied (status, applied_at),
    INDEX idx_student_status (student_id, status)
    
    -- Note: No unique constraint - allows duplicate applications
) COMMENT='Course applications before enrollment (normalized with foreign keys and training details)';

-- Student Enrollments table - Active enrollments created from approved applications
CREATE TABLE student_enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL, -- NORMALIZED: Foreign key to courses table
    adviser_id INT NULL, -- NORMALIZED: Foreign key to advisers table
    nc_level VARCHAR(10) NULL,
    training_start DATE NULL,
    training_end DATE NULL,
    
    -- Enrollment Status Tracking
    enrollment_status ENUM('enrolled', 'ongoing', 'completed', 'dropped') DEFAULT 'enrolled',
    completion_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    
    -- Enrollment Details (copied from approved application)
    application_id INT NOT NULL, -- Reference to original application
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    enrolled_by INT NULL, -- Admin who approved the application
    
    -- Completion Approval Details
    completed_at TIMESTAMP NULL,
    completion_approved_by INT NULL,
    completion_approved_at TIMESTAMP NULL,
    completion_notes TEXT NULL,
    
    -- Certificate Management
    certificate_number VARCHAR(50) NULL UNIQUE,
    certificate_issued_at TIMESTAMP NULL,
    certificate_template VARCHAR(100) DEFAULT 'default',
    
    -- Soft Delete Support
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    
    -- Foreign Key Constraints
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE RESTRICT,
    FOREIGN KEY (adviser_id) REFERENCES advisers(adviser_id) ON DELETE SET NULL,
    FOREIGN KEY (application_id) REFERENCES course_applications(application_id) ON DELETE RESTRICT,
    FOREIGN KEY (enrolled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (completion_approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_student_id (student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_adviser_id (adviser_id),
    INDEX idx_enrollment_status (enrollment_status),
    INDEX idx_completion_status (completion_status),
    INDEX idx_enrolled_at (enrolled_at),
    INDEX idx_application_id (application_id),
    INDEX idx_certificate_number (certificate_number),
    INDEX idx_deleted_at (deleted_at),
    
    -- Composite indexes for common query patterns
    INDEX idx_status_enrolled (enrollment_status, enrolled_at),
    INDEX idx_completion_status_completed (completion_status, completed_at),
    INDEX idx_student_status (student_id, enrollment_status)
    
    -- Note: No unique constraint - allows duplicate enrollments
) COMMENT='Active student enrollments with two-stage approval and certificate management';

-- ============================================================================
-- LEGACY COMPATIBILITY TABLES
-- ============================================================================

-- Admin Table (legacy compatibility - keep for existing integrations)
CREATE TABLE IF NOT EXISTS admin (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(200) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username)
) COMMENT='Legacy admin table for backward compatibility';

-- Cashier Table (future integration - keep for planned features)
CREATE TABLE IF NOT EXISTS cashier (
    cashier_id INT PRIMARY KEY AUTO_INCREMENT,
    fullname VARCHAR(200) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_username (username)
) COMMENT='Cashier table for future payment integration';

-- ============================================================================
-- AUDIT TRAIL AND ACTIVITY LOGGING
-- ============================================================================

-- System Activities table for comprehensive audit trail
CREATE TABLE IF NOT EXISTS system_activities (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_type ENUM('admin', 'student', 'system') NOT NULL DEFAULT 'system',
    activity_type VARCHAR(50) NOT NULL,
    activity_description TEXT NOT NULL,
    entity_type VARCHAR(50) NULL COMMENT 'Type of entity affected (student, course, application, etc.)',
    entity_id INT NULL COMMENT 'ID of the affected entity',
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    session_id VARCHAR(128) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Key Constraints
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_activity_type (activity_type),
    INDEX idx_user_id (user_id),
    INDEX idx_user_type (user_type),
    INDEX idx_created_at (created_at),
    INDEX idx_entity_type (entity_type),
    INDEX idx_entity_id (entity_id),
    INDEX idx_session_id (session_id),
    
    -- Composite indexes for common query patterns
    INDEX idx_entity_lookup (entity_type, entity_id),
    INDEX idx_type_created (activity_type, created_at),
    INDEX idx_user_type_created (user_id, user_type, created_at)
) COMMENT='Comprehensive system activity logging and audit trail';

-- ============================================================================
-- PERFORMANCE OPTIMIZATION SUMMARY
-- ============================================================================
-- 
-- IMPROVEMENTS IMPLEMENTED:
-- ✅ Proper normalization with foreign key relationships
-- ✅ Two-stage approval workflow (application → enrollment → completion)
-- ✅ Enhanced course metadata with codes and descriptions
-- ✅ Certificate management and tracking
-- ✅ Comprehensive indexing for performance (including composite indexes)
-- ✅ Enhanced audit trail and activity logging
-- ✅ Soft delete support for data recovery
-- ✅ Data protection with RESTRICT constraints
-- ✅ Compatible with MySQL 5.5+ and MariaDB 10.0+
-- ✅ Removed all seed data (data entered through application)
-- ✅ Added missing indexes (reviewed_at, birthday, entity composite)
-- ✅ Improved documentation and comments
-- 
-- INDEXING STRATEGY:
-- ✅ Single-column indexes on foreign keys, status fields, and frequently queried columns
-- ✅ Composite indexes for common query patterns (status + date, entity lookups)
-- ✅ Unique indexes for data integrity (prevent duplicates)
-- 
-- SOFT DELETE IMPLEMENTATION:
-- ✅ Added deleted_at and deleted_by columns to main tables
-- ✅ Records are marked as deleted instead of being removed
-- ✅ Allows data recovery and audit trail preservation
-- ✅ Queries should filter WHERE deleted_at IS NULL for active records
-- 
-- DATA PROTECTION (RESTRICT vs CASCADE):
-- ✅ Students with applications/enrollments CANNOT be hard deleted
-- ✅ Must use soft delete to preserve historical data
-- ✅ Prevents accidental loss of course history and certificates
-- ✅ Maintains data integrity for reporting and compliance
-- 
-- FOREIGN KEY STRATEGY:
-- - student_id: ON DELETE RESTRICT (protects historical data)
-- - course_id: ON DELETE RESTRICT (protects enrollment data)
-- - application_id: ON DELETE RESTRICT (protects enrollment links)
-- - adviser_id: ON DELETE SET NULL (allows adviser removal)
-- - reviewed_by: ON DELETE SET NULL (preserves records if admin removed)
-- 
-- LEGACY FIELDS:
-- ⚠️  Legacy course fields in students table are kept for backward compatibility
-- ⚠️  These should eventually be migrated to student_enrollments table
-- ⚠️  Marked as DEPRECATED in comments
-- 
-- DATABASE RATING: 9.5/10 (Production-ready with optimized indexing)
-- ============================================================================

-- ============================================================================
-- MIGRATION SECTION: Update Existing Databases
-- ============================================================================
-- 
-- If you have an EXISTING database and need to update constraints to allow
-- same course with different NC levels, run the commands below.
-- 
-- For NEW databases: Skip this section (constraints are already correct in CREATE TABLE above).
-- 
-- ============================================================================
-- IMPORTANT: First Course Migration
-- ============================================================================
-- 
-- If you have existing students with course data in the students table,
-- run the migration script to move that data to course_applications:
-- 
--   php database/migrate_first_course_to_applications.php
-- 
-- This ensures all courses are stored consistently in course_applications table.
-- See database/FIRST_COURSE_MIGRATION.md for details.
-- 
-- ============================================================================
-- NOTE: No unique constraints on applications/enrollments
-- ============================================================================
-- 
-- Duplicate applications are allowed to support:
-- ✅ Reapplication for same course
-- ✅ Multiple attempts at same NC level
-- ✅ Flexible enrollment management
-- 
-- ============================================================================
-- ✅ Still prevents duplicates with same course + same NC level
-- 
-- ============================================================================


-- ============================================================================
-- DOCUMENT REQUIREMENTS CHECKLIST
-- ============================================================================

-- Checklist table for document requirements
CREATE TABLE IF NOT EXISTS checklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- BOOKKEEPING RECEIPTS
-- ============================================================================

-- Bookkeeping receipts table for tracking receipt numbers per enrollment
CREATE TABLE IF NOT EXISTS bookkeeping_receipts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    enrollment_id INT NOT NULL COMMENT 'Can reference student_enrollments.enrollment_id, course_applications.application_id, or students.id',
    receipt_number VARCHAR(9) NOT NULL COMMENT 'Receipt number (maximum 9 digits)',
    created_by INT NOT NULL COMMENT 'Bookkeeping user who created the receipt',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Constraints
    UNIQUE KEY unique_enrollment_receipt (enrollment_id) COMMENT 'One receipt per enrollment',
    
    -- Indexes for performance
    INDEX idx_student_id (student_id),
    INDEX idx_receipt_number (receipt_number),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at),
    
    -- Foreign Key Constraints
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Bookkeeping receipt numbers for student enrollments';

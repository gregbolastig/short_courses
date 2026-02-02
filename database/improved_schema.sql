-- ============================================================================
-- IMPROVED STUDENT COURSE APPLICATION SYSTEM - DATABASE SCHEMA
-- ============================================================================
-- 
-- This schema provides proper foreign key relationships and normalization
-- for the Student Course Application System
--
-- ============================================================================

-- Create database
CREATE DATABASE IF NOT EXISTS student_registration_db;
USE student_registration_db;

-- Users table for authentication (admin only)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin') NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Students table - Basic student registration (no course info here)
CREATE TABLE IF NOT EXISTS students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    extension_name VARCHAR(20),
    birthday DATE NOT NULL,
    age INT NOT NULL,
    sex ENUM('Male','Female','Other') NOT NULL,
    civil_status VARCHAR(50) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    province VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    street_address VARCHAR(200),
    place_of_birth VARCHAR(200) NOT NULL,
    guardian_last_name VARCHAR(100) NOT NULL,
    guardian_first_name VARCHAR(100) NOT NULL,
    guardian_middle_name VARCHAR(100),
    guardian_extension VARCHAR(20),
    parent_contact VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    profile_picture VARCHAR(255),
    uli VARCHAR(50) NOT NULL UNIQUE,
    last_school VARCHAR(200) NOT NULL,
    school_province VARCHAR(100) NOT NULL,
    school_city VARCHAR(100) NOT NULL,
    verification_code VARCHAR(4) NOT NULL DEFAULT '0000',
    is_verified BOOLEAN DEFAULT FALSE,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student_id (student_id),
    INDEX idx_status (status),
    INDEX idx_email (email),
    INDEX idx_uli (uli)
);

-- Student Enrollments table - Stores approved course applications (replaces course info in students table)
CREATE TABLE IF NOT EXISTS student_enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    nc_level VARCHAR(10) NULL,
    adviser_id INT NULL,
    training_start DATE NULL,
    training_end DATE NULL,
    enrollment_status ENUM('enrolled', 'ongoing', 'completed', 'dropped') DEFAULT 'enrolled',
    completion_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    
    -- Enrollment details (copied from approved application)
    application_id INT NOT NULL, -- Reference to original application
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    enrolled_by INT NULL, -- Admin who approved the application
    
    -- Completion approval details
    completed_at TIMESTAMP NULL,
    completion_approved_by INT NULL,
    completion_approved_at TIMESTAMP NULL,
    completion_notes TEXT NULL,
    
    -- Certificate details
    certificate_number VARCHAR(50) NULL,
    certificate_issued_at TIMESTAMP NULL,
    
    -- Foreign Key Constraints
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (adviser_id) REFERENCES advisers(adviser_id) ON DELETE SET NULL,
    FOREIGN KEY (application_id) REFERENCES course_applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (enrolled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (completion_approved_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_student_id (student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_enrollment_status (enrollment_status),
    INDEX idx_completion_status (completion_status),
    INDEX idx_enrolled_at (enrolled_at),
    INDEX idx_application_id (application_id),
    
    -- Unique constraint to prevent duplicate enrollments
    UNIQUE KEY unique_student_course_enrollment (student_id, course_id)
);

-- Courses table with proper structure
CREATE TABLE IF NOT EXISTS courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(200) NOT NULL UNIQUE,
    course_code VARCHAR(20) UNIQUE,
    description TEXT,
    duration_hours INT,
    nc_levels JSON, -- Store available NC levels as JSON array
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_course_name (course_name),
    INDEX idx_course_code (course_code),
    INDEX idx_active (is_active)
);

-- Advisers table
CREATE TABLE IF NOT EXISTS advisers (
    adviser_id INT AUTO_INCREMENT PRIMARY KEY,
    adviser_name VARCHAR(200) NOT NULL,
    email VARCHAR(150) UNIQUE,
    phone VARCHAR(20),
    department VARCHAR(100),
    specialization VARCHAR(200),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_adviser_name (adviser_name),
    INDEX idx_active (is_active)
);

-- Course Applications table - Initial applications before enrollment
CREATE TABLE IF NOT EXISTS course_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL, -- Foreign key to courses table
    nc_level VARCHAR(10) NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    notes TEXT NULL,
    
    -- Enrollment creation flag
    enrollment_created BOOLEAN DEFAULT FALSE,
    enrollment_id INT NULL, -- Reference to created enrollment
    
    -- Foreign Key Constraints
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    
    -- Indexes for performance
    INDEX idx_student_id (student_id),
    INDEX idx_course_id (course_id),
    INDEX idx_status (status),
    INDEX idx_applied_at (applied_at),
    INDEX idx_enrollment_created (enrollment_created),
    
    -- Unique constraint to prevent duplicate applications
    UNIQUE KEY unique_student_course (student_id, course_id)
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_students_lastname ON students(last_name, first_name);
CREATE INDEX IF NOT EXISTS idx_students_created ON students(created_at);
CREATE INDEX IF NOT EXISTS idx_applications_dates ON course_applications(training_start, training_end);

-- Insert default admin user if not exists
INSERT IGNORE INTO users (username, email, password, role) VALUES 
('admin', 'admin@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Sample courses data
INSERT IGNORE INTO courses (course_name, course_code, description, duration_hours, nc_levels) VALUES 
('Automotive Servicing', 'AUTO-001', 'Complete automotive maintenance and repair training', 480, '["NC I", "NC II"]'),
('Computer Systems Servicing', 'CSS-001', 'Computer hardware and software troubleshooting', 320, '["NC II"]'),
('Electrical Installation and Maintenance', 'EIM-001', 'Electrical systems installation and maintenance', 400, '["NC I", "NC II"]'),
('Welding', 'WELD-001', 'Arc and gas welding techniques', 360, '["NC I", "NC II"]'),
('Cookery', 'COOK-001', 'Professional cooking and food preparation', 280, '["NC II"]');

-- Sample advisers data
INSERT IGNORE INTO advisers (adviser_name, email, phone, department, specialization) VALUES 
('John Doe', 'john.doe@tesda.gov.ph', '09123456789', 'Automotive', 'Automotive Technology'),
('Jane Smith', 'jane.smith@tesda.gov.ph', '09123456790', 'ICT', 'Computer Systems'),
('Mike Johnson', 'mike.johnson@tesda.gov.ph', '09123456791', 'Electrical', 'Electrical Systems'),
('Sarah Wilson', 'sarah.wilson@tesda.gov.ph', '09123456792', 'Welding', 'Metal Fabrication'),
('Lisa Brown', 'lisa.brown@tesda.gov.ph', '09123456793', 'Food Service', 'Culinary Arts');
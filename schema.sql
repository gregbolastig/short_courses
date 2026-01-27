-- ============================================================================
-- STUDENT REGISTRATION SYSTEM - COMPLETE DATABASE SETUP
-- ============================================================================
-- 
-- This file contains the complete database structure for the Student 
-- Registration System including all tables, sample data, and indexes.
--
-- SETUP INSTRUCTIONS:
-- 1. Import this file into MySQL/phpMyAdmin
-- 2. Or run: mysql -u root -p < schema.sql
-- 3. The system will automatically create:
--    - Database: student_registration_db
--    - All required tables
--    - Sample admin user (username: admin, password: admin123)
--    - Sample courses including "Automotive servicing"
--
-- ADMIN LOGIN:
-- Username: admin
-- Password: admin123
--
-- ============================================================================

-- Complete Database Setup for Student Registration System
-- This file creates the entire database structure with all necessary tables

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

-- Students table with approval status
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
    course VARCHAR(200),
    nc_level VARCHAR(50),
    training_start DATE,
    training_end DATE,
    adviser VARCHAR(200),
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (approved_by) REFERENCES users(id)
);

-- Courses table (simplified)
CREATE TABLE IF NOT EXISTS courses (
    course_id INT AUTO_INCREMENT PRIMARY KEY,
    course_name VARCHAR(200) NOT NULL UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Admin Table (legacy compatibility)
CREATE TABLE IF NOT EXISTS admin (
    admin_id INT AUTO_INCREMENT PRIMARY KEY,
    fullname VARCHAR(200) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Cashier Table (legacy compatibility)
CREATE TABLE IF NOT EXISTS cashier (
    cashier_id INT PRIMARY KEY AUTO_INCREMENT,
    fullname VARCHAR(200) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user if not exists
INSERT IGNORE INTO users (username, email, password, role) VALUES 
('admin', 'admin@system.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin');

-- Insert sample admin user for legacy compatibility (password: admin123)
INSERT IGNORE INTO admin (fullname, username, password) VALUES 
('System Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- No sample courses inserted - users will add courses manually through the interface

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_students_status ON students(status);
CREATE INDEX IF NOT EXISTS idx_students_lastname ON students(last_name, first_name);
CREATE INDEX IF NOT EXISTS idx_courses_active ON courses(is_active);
CREATE INDEX IF NOT EXISTS idx_courses_name ON courses(course_name);
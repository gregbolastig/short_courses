-- Create database
CREATE DATABASE IF NOT EXISTS short_course_db;
USE short_course_db;

-- Student Registration Table (Pending/Approved/Rejected)
CREATE TABLE student_registration (
    student_id INT PRIMARY KEY AUTO_INCREMENT,
    lastname VARCHAR(100) NOT NULL,
    middlename VARCHAR(100),
    firstname VARCHAR(100) NOT NULL,
    uli_no VARCHAR(50) UNIQUE,
    birthday DATE NOT NULL,
    birth_city VARCHAR(100) NOT NULL,
    birth_province VARCHAR(100) NOT NULL,
    sex ENUM('Male', 'Female') NOT NULL,
    civil_status ENUM('Single', 'Married', 'Widowed', 'Separated') NOT NULL,
    contact_no VARCHAR(20) NOT NULL,
    street VARCHAR(200),
    barangay VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    guardian_lastname VARCHAR(100),
    guardian_middlename VARCHAR(100),
    guardian_firstname VARCHAR(100),
    guardian_contact_no VARCHAR(20),
    last_school_attended VARCHAR(200),
    profile_pic VARCHAR(255),
    registration_status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_date TIMESTAMP NULL,
    processed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (processed_by) REFERENCES admin(admin_id) ON DELETE SET NULL
);

-- Student Table (Only approved students)
CREATE TABLE student (
    student_id INT PRIMARY KEY,
    lastname VARCHAR(100) NOT NULL,
    middlename VARCHAR(100),
    firstname VARCHAR(100) NOT NULL,
    uli_no VARCHAR(50) UNIQUE,
    birthday DATE NOT NULL,
    birth_city VARCHAR(100) NOT NULL,
    birth_province VARCHAR(100) NOT NULL,
    sex ENUM('Male', 'Female') NOT NULL,
    civil_status ENUM('Single', 'Married', 'Widowed', 'Separated') NOT NULL,
    contact_no VARCHAR(20) NOT NULL,
    street VARCHAR(200),
    barangay VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    province VARCHAR(100) NOT NULL,
    guardian_lastname VARCHAR(100),
    guardian_middlename VARCHAR(100),
    guardian_firstname VARCHAR(100),
    guardian_contact_no VARCHAR(20),
    last_school_attended VARCHAR(200),
    profile_pic VARCHAR(255),
    approved_date TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student_registration(student_id) ON DELETE CASCADE
);

-- Student Course Enrollment Table
CREATE TABLE student_enrollment (
    enrollment_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    course VARCHAR(200) NOT NULL,
    nc_level VARCHAR(50) NOT NULL,
    training_start_date DATE NOT NULL,
    training_end_date DATE NOT NULL,
    adviser VARCHAR(200),
    enrollment_status ENUM('Active', 'Completed', 'Dropped') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES student(student_id) ON DELETE CASCADE
);

-- Admin Table
CREATE TABLE admin (
    admin_id INT PRIMARY KEY AUTO_INCREMENT,
    fullname VARCHAR(200) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Cashier Table
CREATE TABLE cashier (
    cashier_id INT PRIMARY KEY AUTO_INCREMENT,
    fullname VARCHAR(200) NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Indexes for better performance
CREATE INDEX idx_registration_status ON student_registration(registration_status);
CREATE INDEX idx_student_lastname ON student(lastname, firstname);
CREATE INDEX idx_enrollment_student ON student_enrollment(student_id);
CREATE INDEX idx_enrollment_status ON student_enrollment(enrollment_status);
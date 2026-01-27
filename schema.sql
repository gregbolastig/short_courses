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

-- Courses Table
CREATE TABLE courses (
    course_id INT PRIMARY KEY AUTO_INCREMENT,
    course_name VARCHAR(200) NOT NULL UNIQUE,
    nc_level VARCHAR(50) NOT NULL,
    description TEXT,
    start_date DATE,
    end_date DATE,
    duration_months INT DEFAULT 6,
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

-- Insert sample courses
INSERT INTO courses (course_name, nc_level, description, start_date, end_date, duration_months, is_active) VALUES
('Computer Programming', 'NC II', 'Learn programming fundamentals, web development, and software engineering principles.', '2025-02-01', '2025-08-01', 6, 1),
('Computer Programming', 'NC III', 'Advanced programming concepts, database management, and system development.', '2025-03-01', '2025-11-01', 8, 1),
('Automotive Servicing', 'NC I', 'Basic automotive maintenance, engine diagnostics, and repair fundamentals.', '2025-02-15', '2025-06-15', 4, 1),
('Automotive Servicing', 'NC II', 'Advanced automotive repair, electrical systems, and diagnostic procedures.', '2025-04-01', '2025-10-01', 6, 1),
('Welding', 'NC I', 'Basic welding techniques, safety procedures, and metal fabrication.', '2025-03-01', '2025-06-01', 3, 1),
('Welding', 'NC II', 'Advanced welding processes, blueprint reading, and quality control.', '2025-05-01', '2025-10-01', 5, 1),
('Electrical Installation', 'NC II', 'Electrical wiring, circuit installation, and safety compliance.', '2025-02-01', '2025-08-01', 6, 1),
('Plumbing', 'NC I', 'Basic plumbing installation, pipe fitting, and maintenance procedures.', '2025-03-15', '2025-07-15', 4, 1),
('Carpentry', 'NC II', 'Wood construction, furniture making, and building techniques.', '2025-04-01', '2025-09-01', 5, 1),
('Masonry', 'NC I', 'Concrete work, brick laying, and construction fundamentals.', '2025-05-01', '2025-09-01', 4, 1),
('Electronics', 'NC II', 'Electronic circuits, component testing, and device repair.', '2025-06-01', '2025-12-01', 6, 1),
('Food Processing', 'NC II', 'Food safety, preservation techniques, and quality control.', '2025-07-01', '2025-12-01', 5, 1);

-- Insert sample admin user (password: admin123)
INSERT INTO admin (fullname, username, password) VALUES 
('System Administrator', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- Create indexes for better performance
CREATE INDEX idx_courses_active ON courses(is_active);
CREATE INDEX idx_courses_name ON courses(course_name);
CREATE INDEX idx_courses_nc_level ON courses(nc_level);
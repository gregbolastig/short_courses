    -- Create the database
    CREATE DATABASE grading_system;

    -- Use the database
    USE grading_system;

    -- Create Students Table
    CREATE TABLE students (
        student_id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        gender ENUM('Male', 'Female') NOT NULL,
        address VARCHAR(255),
        date_of_birth DATE,
        contact_number VARCHAR(20),
        student_number VARCHAR(20) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        enrollment_date DATE,
        role ENUM('Student') DEFAULT 'Student',
        status ENUM('Active', 'Inactive', 'Graduated') DEFAULT 'Active'
    );
    ALTER TABLE students
ADD COLUMN batch VARCHAR(20) NULL AFTER enrollment_date;

    -- Create Faculty Table
    CREATE TABLE faculty (
        faculty_id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        gender ENUM('Male', 'Female') NOT NULL,
        address VARCHAR(255),
        date_of_birth DATE,
        contact_number VARCHAR(20),
        employee_number VARCHAR(20) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        department VARCHAR(100),
        hire_date DATE,
        role ENUM('Faculty') DEFAULT 'Faculty',
        status ENUM('Active', 'Inactive', 'Retired') DEFAULT 'Active'
    );

    -- Create Admins Table
    CREATE TABLE admins (
        admin_id INT PRIMARY KEY AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        date_of_birth DATE,
        contact_number VARCHAR(20),
        employee_number VARCHAR(20) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('Admin', 'Registrar') DEFAULT 'Admin',
        status ENUM('Active', 'Inactive') DEFAULT 'Active'
    );


    CREATE TABLE coursesubject (
        subject_id INT PRIMARY KEY AUTO_INCREMENT,
        subject_code VARCHAR(50) NOT NULL,
        subject_name VARCHAR(255) NOT NULL,
        description TEXT NULL,
        units TINYINT NOT NULL,
        lecture_hours TINYINT NULL,
        lab_hours TINYINT NULL,
        semester ENUM('1', '2', 'Summer') NULL,
        year_level ENUM('1', '2', '3', '4') NULL,
        prerequisite VARCHAR(50) NULL,
        status ENUM('Active', 'Inactive') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        category ENUM('Core', 'Minor') NOT NULL
    );

    CREATE TABLE faculty_subject_assignment (
        assignment_id INT PRIMARY KEY AUTO_INCREMENT,
        faculty_id INT NOT NULL,
        subject_id INT NOT NULL,
        academic_year VARCHAR(9) NOT NULL, -- e.g., "2024-2025"
        semester ENUM('1', '2', 'Summer') NOT NULL,
        year_level ENUM('1', '2', '3', '4') NOT NULL, -- Added year_level
        section VARCHAR(50) NULL, -- Optional: If sections exist
        schedule VARCHAR(255) NULL, -- Optional: For storing class schedules
        status ENUM('Active', 'Inactive') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES coursesubject(subject_id) ON DELETE CASCADE
    );

    CREATE TABLE faculty_assignment_schedules (
        schedule_id INT PRIMARY KEY AUTO_INCREMENT,
        assignment_id INT NOT NULL,
        day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday') NOT NULL,
        start_time TIME NOT NULL,
        end_time TIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (assignment_id) REFERENCES faculty_subject_assignment(assignment_id) ON DELETE CASCADE
    );

    CREATE TABLE student_enrollment (
        enrollment_id INT PRIMARY KEY AUTO_INCREMENT,
        student_id INT NOT NULL,
        subject_id INT NOT NULL,
        academic_year VARCHAR(9) NOT NULL,
        semester ENUM('1', '2', 'Summer') NOT NULL,
        status ENUM('Enrolled', 'Dropped', 'Completed') DEFAULT 'Enrolled',
        enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        faculty_assignment_id INT NULL,
        grade VARCHAR(10) NULL,
        FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
        FOREIGN KEY (subject_id) REFERENCES coursesubject(subject_id) ON DELETE CASCADE,
        FOREIGN KEY (faculty_assignment_id) REFERENCES faculty_subject_assignment(assignment_id)
    );



    CREATE TABLE grading_scales (
        scale_id INT PRIMARY KEY AUTO_INCREMENT,
        grade VARCHAR(10) NOT NULL,  -- Example: 1.00, 1.25, INC, D
        min_percentage INT NOT NULL, -- Example: 97, 94, 65
        max_percentage INT NOT NULL, -- Example: 100, 96, 74
        status ENUM('Active', 'Inactive') DEFAULT 'Active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    );

    INSERT INTO grading_scales (grade, min_percentage, max_percentage, status) VALUES
    ('1.00', 97, 100, 'Active'),
    ('1.25', 94, 96, 'Active'),
    ('1.50', 91, 93, 'Active'),
    ('1.75', 88, 90, 'Active'),
    ('2.00', 85, 87, 'Active'),
    ('2.25', 82, 84, 'Active'),
    ('2.50', 79, 81, 'Active'),
    ('2.75', 76, 78, 'Active'),
    ('3.00', 75, 75, 'Active'),
    ('5.00', 65, 74, 'Active'),
    ('INC', 0, 0, 'Active'),
    ('D', 0, 0, 'Active');


CREATE TABLE announcements (
    announcement_id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    target_audience ENUM('All', 'Faculty', 'Students', 'Admin') NOT NULL DEFAULT 'All',
    publish_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date TIMESTAMP NULL,
    created_by INT NOT NULL,
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    FOREIGN KEY (created_by) REFERENCES admins(admin_id) ON DELETE CASCADE
);

CREATE TABLE programs (
    program_id INT PRIMARY KEY AUTO_INCREMENT,
    program_code VARCHAR(20) UNIQUE NOT NULL, -- BSCPE, DMET, etc.
    program_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    status ENUM('Active','Inactive') DEFAULT 'Active'
);

ALTER TABLE students
ADD COLUMN program_id INT NOT NULL,
ADD FOREIGN KEY (program_id) REFERENCES programs(program_id);

ALTER TABLE faculty_subject_assignment
ADD COLUMN program_id INT NULL AFTER year_level,
ADD FOREIGN KEY (program_id) REFERENCES programs(program_id) ON DELETE SET NULL;

-- Add profile_picture column to faculty table
ALTER TABLE faculty
ADD COLUMN profile_picture VARCHAR(255) NULL AFTER password;

-- Add profile_picture column to students table (optional, for future use)
ALTER TABLE students
ADD COLUMN profile_picture VARCHAR(255) NULL AFTER password;

-- Add profile_picture column to admins table (optional, for future use)
ALTER TABLE admins
ADD COLUMN profile_picture VARCHAR(255) NULL AFTER password;


CREATE TABLE cor_verification (
    verification_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    academic_year VARCHAR(9) NOT NULL,
    semester ENUM('1', '2', 'Summer') NOT NULL,
    token VARCHAR(255) UNIQUE NOT NULL,
    generated_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    UNIQUE KEY unique_cor (student_id, academic_year, semester)
);

ALTER TABLE student_enrollment 
MODIFY COLUMN status ENUM('Enrolled', 'Dropped', 'Completed', 'Failed', 'INC') DEFAULT 'Enrolled';

ALTER TABLE students
ADD COLUMN batch VARCHAR(20) NULL AFTER enrollment_date;

-- Add start_date and end_date to faculty_subject_assignment table
ALTER TABLE faculty_subject_assignment
ADD COLUMN start_date DATE NULL AFTER schedule,
ADD COLUMN end_date DATE NULL AFTER start_date;

-- Add competency column to student_enrollment table
ALTER TABLE student_enrollment
ADD COLUMN competency ENUM('Competent', 'Not Competent', 'N/A') DEFAULT 'N/A' AFTER grade;

-- Create assessment_schedules table for core subjects
CREATE TABLE assessment_schedules (
    schedule_id INT PRIMARY KEY AUTO_INCREMENT,
    assignment_id INT NOT NULL,
    assessment_date DATE NOT NULL,
    description TEXT NULL,
    status ENUM('Scheduled', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES faculty_subject_assignment(assignment_id) ON DELETE CASCADE
);

-- _________________________________ADDITIONAL TABLES FOR ROOM SCHEDULING SYSTEM________________________________

-- Add to grading_system database

-- Rooms table (keep as is from RSMS)
CREATE TABLE rooms (
    room_id INT PRIMARY KEY AUTO_INCREMENT,
    room_name VARCHAR(100) NOT NULL,
    room_number VARCHAR(50) UNIQUE NOT NULL,
    capacity INT,
    building VARCHAR(100),
    floor INT,
    amenities TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Subjects table already exists as 'coursesubject' - we'll map to it

-- Recurring schedules table
CREATE TABLE recurring_schedules (
    recurring_id INT PRIMARY KEY AUTO_INCREMENT,
    room_id INT NOT NULL,
    faculty_id INT NOT NULL, -- Changed from user_id to faculty_id
    subject_id INT NOT NULL, -- Will reference coursesubject
    assigned_by INT NOT NULL, -- References admins.admin_id
    
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    days_of_week VARCHAR(20) NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    
    purpose TEXT,
    notes TEXT,
    status ENUM('active', 'cancelled') DEFAULT 'active',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES coursesubject(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES admins(admin_id) ON DELETE CASCADE,
    
    CONSTRAINT check_end_after_start_recurring CHECK (end_time > start_time),
    CONSTRAINT check_end_date_after_start CHECK (end_date >= start_date)
);

-- Schedules table
CREATE TABLE schedules (
    schedule_id INT PRIMARY KEY AUTO_INCREMENT,
    recurring_id INT DEFAULT NULL,
    room_id INT NOT NULL,
    faculty_id INT NOT NULL, -- Changed from user_id
    subject_id INT NOT NULL, -- References coursesubject
    assigned_by INT NOT NULL, -- References admins.admin_id
    schedule_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    purpose TEXT,
    status ENUM('scheduled', 'completed', 'cancelled') DEFAULT 'scheduled',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (recurring_id) REFERENCES recurring_schedules(recurring_id) ON DELETE CASCADE,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id) ON DELETE CASCADE,
    FOREIGN KEY (faculty_id) REFERENCES faculty(faculty_id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES coursesubject(subject_id) ON DELETE CASCADE,
    FOREIGN KEY (assigned_by) REFERENCES admins(admin_id) ON DELETE CASCADE,
    
    CONSTRAINT check_end_after_start CHECK (end_time > start_time),
    UNIQUE KEY unique_schedule (room_id, schedule_date, start_time, end_time)
);

-- Indexes
CREATE INDEX idx_schedules_date ON schedules(schedule_date);
CREATE INDEX idx_schedules_room ON schedules(room_id, schedule_date);
CREATE INDEX idx_recurring_dates ON recurring_schedules(start_date, end_date);
CREATE INDEX idx_recurring_room ON recurring_schedules(room_id);

-- Add this to your database
CREATE TABLE pending_profile_updates (
    update_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT NOT NULL,
    field_name VARCHAR(50) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    status ENUM('Pending', 'Approved', 'Rejected') DEFAULT 'Pending',
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_by INT NULL,
    reviewed_at TIMESTAMP NULL,
    rejection_reason TEXT NULL,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES admins(admin_id) ON DELETE SET NULL
);

-- Drop the old assessment_schedules table
DROP TABLE IF EXISTS assessment_schedules;

-- Create new assessment_schedules table (supports multiple assessments per assignment)
CREATE TABLE assessment_schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    assignment_id INT NOT NULL,
    assessment_date DATE NOT NULL,
    assessment_type VARCHAR(100) DEFAULT NULL, -- e.g., 'Midterm Exam', 'Final Exam', 'Quiz 1'
    description TEXT,
    status ENUM('Scheduled', 'Completed', 'Cancelled') DEFAULT 'Scheduled',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assignment_id) REFERENCES faculty_subject_assignment(assignment_id) ON DELETE CASCADE,
    INDEX idx_assignment (assignment_id),
    INDEX idx_date (assessment_date)
);

-- Create new table for student-specific assessment assignments
CREATE TABLE student_assessment_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    schedule_id INT NOT NULL,
    enrollment_id INT NOT NULL,
    student_id INT NOT NULL,
    assessment_date DATE NOT NULL,
    status ENUM('Scheduled', 'Taken', 'Missed', 'Excused') DEFAULT 'Scheduled',
    score DECIMAL(5,2) DEFAULT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (schedule_id) REFERENCES assessment_schedules(schedule_id) ON DELETE CASCADE,
    FOREIGN KEY (enrollment_id) REFERENCES student_enrollment(enrollment_id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_schedule (schedule_id, enrollment_id),
    INDEX idx_schedule (schedule_id),
    INDEX idx_student (student_id),
    INDEX idx_date (assessment_date)
);



INSERT INTO `faculty` (`faculty_id`, `name`, `email`, `gender`, `address`, `date_of_birth`, `contact_number`, `employee_number`, `username`, `password`, `profile_picture`, `is_active`, `department`, `hire_date`, `role`, `status`) VALUES
(22, 'Mark Miller Abrera Polinar', 'mmapolinar@tesda.gov.ph', 'Male', 'Larch St Mabuhay Mamatid Cabuyao Laguna', '1997-08-13', '09761707354', '2022-0585', NULL, '$2y$10$qDvI7tlQs6wypHlRWqE9kehBOsrsP60dchQRyEXxvQv/ejf4mXqf2', NULL, 1, 'II', '2021-12-01', 'Faculty', 'Active'),
(43, 'Mark Anthony Rapuson Guerrero', 'marguerrero@tesda.gov.ph', 'Male', '1268, Bkl 20, Lot ext. Brgy. Pulido, Gen. Mariano Alvarez, Cavite', '1993-07-25', '+639951027174', '1998-8528', NULL, '$2y$10$n8yfFxOmm2YD24KyZrLioOVP78Ho9LKf6zGzj/zJqYfn5OTnY8mbO', NULL, 1, 'II', '2016-05-20', 'Faculty', 'Active');



------------------------SHORT COURSES------------------------




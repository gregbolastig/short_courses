
CREATE TABLE short_courses (
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
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE advisers (
    adviser_id INT AUTO_INCREMENT PRIMARY KEY,
    adviser_name VARCHAR(200) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE TABLE shortcourse_students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    uli VARCHAR(50) NOT NULL UNIQUE,

    -- Personal Information
    first_name VARCHAR(100) NOT NULL,
    middle_name VARCHAR(100),
    last_name VARCHAR(100) NOT NULL,
    extension_name VARCHAR(20),
    birthday DATE NOT NULL,
    age INT NOT NULL,
    sex ENUM('Male','Female','Other') NOT NULL,
    civil_status VARCHAR(50) NOT NULL,

    -- Contact & Address
    contact_number VARCHAR(20) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    province VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    barangay VARCHAR(100) NOT NULL,
    street_address VARCHAR(200),
    place_of_birth VARCHAR(200) NOT NULL,

    -- Guardian
    guardian_last_name VARCHAR(100) NOT NULL,
    guardian_first_name VARCHAR(100) NOT NULL,
    guardian_middle_name VARCHAR(100),
    guardian_extension VARCHAR(20),
    parent_contact VARCHAR(20) NOT NULL,

    -- Education
    last_school VARCHAR(200) NOT NULL,
    school_province VARCHAR(100) NOT NULL,
    school_city VARCHAR(100) NOT NULL,

    -- Account
    profile_picture VARCHAR(255),
    verification_code VARCHAR(4) NOT NULL DEFAULT '0000',
    is_verified BOOLEAN DEFAULT FALSE,
    status ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending',

    -- Approval & Soft Delete
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,

    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status_created (status, created_at),
    INDEX idx_name (last_name, first_name),
    INDEX idx_deleted_at (deleted_at)
);

-- ============================================================================
-- ENROLLMENT WORKFLOW
-- ============================================================================

CREATE TABLE short_course_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    adviser_id INT NULL,
    nc_level VARCHAR(10),
    training_start DATE,
    training_end DATE,
    status ENUM('pending', 'approved', 'rejected', 'completed') DEFAULT 'pending',
    notes TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,

    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE RESTRICT,
    FOREIGN KEY (adviser_id) REFERENCES advisers(adviser_id) ON DELETE SET NULL,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student_status (student_id, status),
    INDEX idx_status_applied (status, applied_at)
);

CREATE TABLE shortcourse_student_enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    application_id INT NOT NULL UNIQUE,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    adviser_id INT NULL,
    nc_level VARCHAR(10),
    training_start DATE,
    training_end DATE,

    -- Status
    enrollment_status ENUM('enrolled', 'ongoing', 'completed', 'dropped') DEFAULT 'enrolled',
    completion_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',

    -- Certificate
    certificate_number VARCHAR(50) UNIQUE,
    certificate_issued_at TIMESTAMP NULL,
    certificate_template VARCHAR(100) DEFAULT 'default',

    -- Timestamps & Approval
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    enrolled_by INT NULL,
    completed_at TIMESTAMP NULL,
    completion_approved_by INT NULL,
    completion_approved_at TIMESTAMP NULL,
    completion_notes TEXT,
    deleted_at TIMESTAMP NULL,
    deleted_by INT NULL,

    FOREIGN KEY (application_id) REFERENCES course_applications(application_id) ON DELETE RESTRICT,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE RESTRICT,
    FOREIGN KEY (adviser_id) REFERENCES advisers(adviser_id) ON DELETE SET NULL,
    FOREIGN KEY (enrolled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (completion_approved_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (deleted_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_student_status (student_id, enrollment_status),
    INDEX idx_status_enrolled (enrollment_status, enrolled_at)
);

-- ============================================================================
-- AUDIT & SUPPORTING TABLES
-- ============================================================================

CREATE TABLE system_activities (
    activity_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_type ENUM('admin', 'student', 'system') NOT NULL DEFAULT 'system',
    activity_type VARCHAR(50) NOT NULL,
    activity_description TEXT NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    ip_address VARCHAR(45),
    session_id VARCHAR(128),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_entity_lookup (entity_type, entity_id),
    INDEX idx_type_created (activity_type, created_at)
);

CREATE TABLE checklist (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
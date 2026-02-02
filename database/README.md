# Student Course Application System - Two-Stage Approval Database

## Overview

This document explains the **Two-Stage Approval System** for the Student Course Application System. The system separates application approval from course completion approval, providing better tracking and control over the student journey.

## Two-Stage Approval Workflow

### Stage 1: Application Approval
1. **Student applies** for a course → Record created in `course_applications`
2. **Admin reviews application** → Application status updated to 'approved' or 'rejected'
3. **If approved** → Enrollment record automatically created in `student_enrollments`

### Stage 2: Course Completion Approval
1. **Student completes course** → Enrollment status updated to 'completed'
2. **Admin reviews completion** → Completion status updated to 'approved' or 'rejected'
3. **If approved** → Certificate issued with certificate number

## Database Schema

### Table Relationships

```
users (1) ──────────┐
                    │
                    ▼
students (1) ────► course_applications (many) ────► student_enrollments (1)
                    ▲                                        ▲
                    │                                        │
courses (1) ────────┘                                        │
                                                             │
advisers (1) ────────────────────────────────────────────────┘
```

### Key Tables

#### 1. students
Basic student registration information (no course-specific data).

```sql
CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id VARCHAR(20) NOT NULL UNIQUE,
    first_name VARCHAR(100) NOT NULL,
    -- ... other personal information
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending'
);
```

#### 2. course_applications
Initial course applications before enrollment.

```sql
CREATE TABLE course_applications (
    application_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    nc_level VARCHAR(10) NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT NULL,
    notes TEXT NULL,
    
    -- Enrollment tracking
    enrollment_created BOOLEAN DEFAULT FALSE,
    enrollment_id INT NULL,
    
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    UNIQUE KEY unique_student_course (student_id, course_id)
);
```

#### 3. student_enrollments
Active enrollments created from approved applications.

```sql
CREATE TABLE student_enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    nc_level VARCHAR(10) NULL,
    adviser_id INT NULL,
    training_start DATE NULL,
    training_end DATE NULL,
    
    -- Enrollment status
    enrollment_status ENUM('enrolled', 'ongoing', 'completed', 'dropped') DEFAULT 'enrolled',
    completion_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    
    -- Tracking information
    application_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    enrolled_by INT NULL,
    
    -- Completion details
    completed_at TIMESTAMP NULL,
    completion_approved_by INT NULL,
    completion_approved_at TIMESTAMP NULL,
    completion_notes TEXT NULL,
    
    -- Certificate information
    certificate_number VARCHAR(50) NULL,
    certificate_issued_at TIMESTAMP NULL,
    
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES course_applications(application_id) ON DELETE CASCADE
);
```

## Status Flow

### Application Status Flow
```
pending → approved → (creates enrollment)
       → rejected
```

### Enrollment Status Flow
```
enrolled → completed → (ready for completion approval)
        → dropped
```

### Completion Status Flow
```
pending → approved → (certificate issued)
       → rejected
```

## Common Queries

### Stage 1: Application Management

#### Get Pending Applications
```sql
SELECT 
    ca.application_id,
    s.student_id,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    c.course_name,
    ca.applied_at
FROM course_applications ca
INNER JOIN students s ON ca.student_id = s.id
INNER JOIN courses c ON ca.course_id = c.course_id
WHERE ca.status = 'pending'
ORDER BY ca.applied_at ASC;
```

### Stage 2: Enrollment Management

#### Get Active Enrollments
```sql
SELECT 
    se.enrollment_id,
    s.student_id,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    c.course_name,
    se.enrollment_status,
    se.completion_status,
    se.enrolled_at
FROM student_enrollments se
INNER JOIN students s ON se.student_id = s.id
INNER JOIN courses c ON se.course_id = c.course_id
WHERE se.enrollment_status = 'enrolled'
ORDER BY se.enrolled_at DESC;
```

#### Get Pending Completion Approvals
```sql
SELECT 
    se.enrollment_id,
    s.student_id,
    CONCAT(s.first_name, ' ', s.last_name) as student_name,
    c.course_name,
    se.completed_at,
    a.adviser_name
FROM student_enrollments se
INNER JOIN students s ON se.student_id = s.id
INNER JOIN courses c ON se.course_id = c.course_id
LEFT JOIN advisers a ON se.adviser_id = a.adviser_id
WHERE se.enrollment_status = 'completed' 
  AND se.completion_status = 'pending'
ORDER BY se.completed_at ASC;
```

## PHP Usage Examples

### Stage 1: Application Management

```php
$approvalManager = new TwoStageApprovalManager();

// Create application
$result = $approvalManager->createCourseApplication(1, 1, 'NC II');

// Approve application and create enrollment
$result = $approvalManager->approveApplicationAndCreateEnrollment(
    1,              // application_id
    1,              // admin_id
    1,              // adviser_id
    '2024-03-01',   // training_start
    '2024-08-31',   // training_end
    'Approved for enrollment'
);
```

### Stage 2: Enrollment Management

```php
// Mark course as completed
$result = $approvalManager->markCourseCompleted(1);

// Approve completion and issue certificate
$result = $approvalManager->approveCompletionAndIssueCertificate(
    1,              // enrollment_id
    1,              // admin_id
    'CERT-2024-000001', // certificate_number (optional)
    'Successfully completed all requirements'
);
```

## Statistics and Reporting

### Course Statistics with Two-Stage Data
```sql
SELECT 
    c.course_name,
    COUNT(DISTINCT ca.application_id) as total_applications,
    SUM(CASE WHEN ca.status = 'pending' THEN 1 ELSE 0 END) as pending_applications,
    COUNT(DISTINCT se.enrollment_id) as total_enrollments,
    SUM(CASE WHEN se.enrollment_status = 'enrolled' THEN 1 ELSE 0 END) as active_enrollments,
    SUM(CASE WHEN se.completion_status = 'approved' THEN 1 ELSE 0 END) as certified_students
FROM courses c
LEFT JOIN course_applications ca ON c.course_id = ca.course_id
LEFT JOIN student_enrollments se ON c.course_id = se.course_id
GROUP BY c.course_id, c.course_name;
```

## Migration from Single-Stage System

### Automatic Migration
Run the migration script to upgrade from the old single-stage system:

```bash
php database/migrate_to_two_stage_approval.php
```

### What the Migration Does
1. Creates `student_enrollments` table
2. Adds tracking columns to `course_applications`
3. Migrates existing approved applications to enrollments
4. Removes course-related fields from `students` table
5. Creates necessary indexes and constraints

## Admin Interface Updates

### New Admin Pages Needed
1. **Pending Applications** - Review and approve course applications
2. **Student Enrollments** - Manage active enrollments
3. **Completion Approvals** - Approve course completions and issue certificates
4. **Certificate Management** - View and manage issued certificates

### Dashboard Metrics
- Pending applications count
- Active enrollments count
- Pending completion approvals count
- Total certificates issued

## Benefits of Two-Stage Approval

1. **Better Tracking** - Clear separation between application and completion
2. **Improved Control** - Separate approval processes for different stages
3. **Certificate Management** - Proper certificate issuance and tracking
4. **Audit Trail** - Complete history of student journey
5. **Flexibility** - Can handle complex approval workflows
6. **Data Integrity** - Prevents data duplication and maintains consistency

## Files in this Directory

- `improved_schema.sql` - Complete two-stage approval database schema
- `example_queries.sql` - SQL queries for two-stage approval system
- `two_stage_approval_examples.php` - PHP class with methods for both stages
- `migrate_to_two_stage_approval.php` - Migration script from single-stage system
- `README.md` - This documentation file

## Support

For questions about the two-stage approval system, refer to the example files or contact the development team.
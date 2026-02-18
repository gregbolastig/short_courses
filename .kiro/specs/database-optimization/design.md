# Database Optimization Design Document

## Overview

This design document outlines the comprehensive optimization of the Student Registration System database. The optimization focuses on proper normalization, implementing a two-stage approval workflow, performance improvements through strategic indexing, and maintaining data integrity through proper constraints and relationships.

The design transforms the current mixed schema into a clean, normalized structure that separates concerns appropriately and provides better scalability and maintainability.

## Architecture

### Current Architecture Issues
- Course and adviser information stored as strings in students table
- Mixed single-stage and two-stage approval patterns
- Inconsistent foreign key relationships
- Legacy tables alongside modern structure
- Limited audit trail capabilities

### Target Architecture
- Fully normalized schema with proper foreign key relationships
- Clear separation between student registration, course applications, and enrollments
- Two-stage approval workflow: application approval → enrollment creation → completion approval
- Comprehensive audit trail and activity logging
- Optimized indexing strategy for performance

## Components and Interfaces

### Core Tables

#### 1. students (Normalized)
- Contains only basic student registration information
- Removes course-specific fields (moved to enrollments)
- Maintains proper foreign key to approval user

#### 2. courses (Enhanced)
- Structured course information with codes and descriptions
- JSON field for available NC levels
- Duration and metadata support

#### 3. advisers (Simplified)
- Basic adviser management with name only
- Active status management
- Minimal structure based on actual usage patterns

#### 4. course_applications (Application Stage)
- Initial course applications before enrollment
- Links to students and courses via foreign keys
- Tracks application status and review information
- Prevents duplicate applications via unique constraints

#### 5. student_enrollments (Enrollment Stage)
- Created from approved applications
- Tracks enrollment status and completion status separately
- Certificate management and issuance tracking
- Complete audit trail of enrollment lifecycle

#### 6. system_activities (Enhanced Audit)
- Comprehensive activity logging
- Links to users and entities
- IP address and user agent tracking
- Indexed for efficient querying

### Relationships and Constraints

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

## Data Models

### Student Model (Normalized)
```sql
CREATE TABLE students (
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
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    approved_by INT NULL,
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    -- Constraints
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);
```

### Course Application Model
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
    -- Constraints
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_student_course (student_id, course_id)
);
```

### Student Enrollment Model
```sql
CREATE TABLE student_enrollments (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    course_id INT NOT NULL,
    nc_level VARCHAR(10) NULL,
    adviser_id INT NULL,
    training_start DATE NULL,
    training_end DATE NULL,
    -- Status tracking
    enrollment_status ENUM('enrolled', 'ongoing', 'completed', 'dropped') DEFAULT 'enrolled',
    completion_status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    -- Audit trail
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
    -- Constraints
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
    FOREIGN KEY (course_id) REFERENCES courses(course_id) ON DELETE CASCADE,
    FOREIGN KEY (adviser_id) REFERENCES advisers(adviser_id) ON DELETE SET NULL,
    FOREIGN KEY (application_id) REFERENCES course_applications(application_id) ON DELETE CASCADE,
    FOREIGN KEY (enrolled_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (completion_approved_by) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY unique_student_course_enrollment (student_id, course_id)
);
```

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system-essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property Reflection

After reviewing all properties identified in the prework, several can be consolidated to eliminate redundancy:

**Consolidations:**
- Properties 1.1, 1.2, and 1.5 can be combined into a comprehensive "Foreign Key Integrity" property
- Properties 3.1, 3.3, 3.4, and 3.5 can be combined into a comprehensive "Index Optimization" property  
- Properties 4.1, 4.2, 4.3, 4.4, and 4.5 can be combined into a comprehensive "Data Constraint Consistency" property
- Properties 5.1, 5.2, 5.3, and 5.4 can be combined into a comprehensive "Audit Trail Completeness" property
- Properties 6.1, 6.2, 6.3, and 6.4 can be combined into a comprehensive "Migration Data Integrity" property
- Properties 7.1, 7.2, 7.3, 7.4, and 7.5 can be combined into a comprehensive "Schema Documentation Completeness" property

This reduces 30 individual properties to 15 comprehensive properties that provide unique validation value without redundancy.

### Correctness Properties

Property 1: Foreign Key Integrity
*For any* database table with relationships, all foreign key references should point to valid records in the referenced tables, and cascade rules should be properly enforced
**Validates: Requirements 1.1, 1.2, 1.5**

Property 2: Enrollment Data Separation
*For any* student record, enrollment-specific information should be stored in separate enrollment tables rather than in the basic student information table
**Validates: Requirements 1.3**

Property 3: Data Normalization
*For any* piece of information in the database, it should be stored in exactly one authoritative location to eliminate redundancy
**Validates: Requirements 1.4**

Property 4: Application to Enrollment Workflow
*For any* course application, when approved, it should automatically create exactly one corresponding enrollment record
**Validates: Requirements 2.1, 2.2**

Property 5: Two-Stage Approval Process
*For any* student enrollment, course completion approval should be a separate process from initial enrollment approval
**Validates: Requirements 2.3**

Property 6: Certificate Issuance Uniqueness
*For any* approved course completion, a unique certificate number should be issued and no two certificates should have the same number
**Validates: Requirements 2.4**

Property 7: Status Transition Consistency
*For any* student record, status transitions should follow a logical progression from application through completion
**Validates: Requirements 2.5**

Property 8: Index Optimization
*For any* frequently queried column or foreign key, appropriate database indexes should exist to optimize query performance
**Validates: Requirements 3.1, 3.3, 3.4, 3.5**

Property 9: Data Constraint Consistency
*For any* database table, data types, constraints, and validation rules should be consistently applied across related fields and tables
**Validates: Requirements 4.1, 4.2, 4.3, 4.4, 4.5**

Property 10: Audit Trail Completeness
*For any* significant database operation, a complete audit trail should be maintained with timestamps, user information, and relevant details
**Validates: Requirements 5.1, 5.2, 5.3, 5.4**

Property 11: Audit Query Performance
*For any* audit trail query, the system should provide efficient access through proper indexing and query optimization
**Validates: Requirements 5.5**

Property 12: Migration Data Integrity
*For any* existing data during migration, all information should be preserved and correctly mapped to the new normalized schema structure
**Validates: Requirements 6.1, 6.2, 6.3, 6.4**

Property 13: Migration Rollback Capability
*For any* database migration, a reliable rollback mechanism should exist to revert to the previous schema state if needed
**Validates: Requirements 6.5**

Property 14: Schema Documentation Completeness
*For any* database object (table, column, index, relationship), comprehensive documentation should exist explaining its purpose and constraints
**Validates: Requirements 7.1, 7.2, 7.3, 7.4, 7.5**

## Error Handling

### Migration Error Handling
- **Backup Strategy**: Complete database backup before migration begins
- **Rollback Triggers**: Automatic rollback on foreign key constraint violations
- **Data Validation**: Pre-migration data validation to identify potential issues
- **Transaction Management**: All migration operations wrapped in transactions

### Constraint Violation Handling
- **Foreign Key Violations**: Clear error messages identifying the violated relationship
- **Unique Constraint Violations**: Specific error messages for duplicate data attempts
- **Data Type Violations**: Validation at application layer before database operations
- **NULL Constraint Violations**: Required field validation with user-friendly messages

### Performance Degradation Handling
- **Query Timeout Management**: Configurable timeouts for long-running queries
- **Index Monitoring**: Automated detection of missing or inefficient indexes
- **Connection Pool Management**: Proper database connection lifecycle management
- **Resource Monitoring**: Database resource usage monitoring and alerting

## Testing Strategy

### Dual Testing Approach

The database optimization will use both unit testing and property-based testing to ensure comprehensive coverage:

**Unit Testing:**
- Specific migration scenarios with known data sets
- Individual constraint validation tests
- Foreign key relationship verification tests
- Index existence and performance tests
- Audit trail functionality tests

**Property-Based Testing:**
- Uses **PHPUnit with Faker** for property-based testing in PHP
- Each property-based test configured to run minimum 100 iterations
- Tests universal properties across randomly generated data sets
- Validates correctness properties hold for all valid inputs

**Property-Based Testing Requirements:**
- Each correctness property implemented by a SINGLE property-based test
- Each test tagged with format: '**Feature: database-optimization, Property {number}: {property_text}**'
- Tests focus on universal behaviors rather than specific examples
- Random data generation covers edge cases and boundary conditions

**Integration Testing:**
- End-to-end workflow testing from application to certificate issuance
- Cross-table relationship validation
- Performance testing with realistic data volumes
- Migration testing with production-like data sets

### Testing Framework Configuration
- **Primary Framework**: PHPUnit for PHP-based testing
- **Property Testing Library**: Faker for data generation and property-based testing
- **Database Testing**: PHPUnit Database extension for database-specific tests
- **Performance Testing**: Custom benchmarking tools for query performance validation
- **Migration Testing**: Dedicated test database environments for migration validation
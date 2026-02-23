# Jacobo Z. Gonzales Memorial School of Arts and Trades - Student Management System

A comprehensive web-based student management system built with **PHP**, **MySQL**, and **Tailwind CSS**. Features role-based access control with separate Student, Admin, and Bookkeeping portals, complete with course application workflows, approval systems, and activity logging.

## � Tavble of Contents

- [System Overview](#system-overview)
- [Key Features](#key-features)
- [System Architecture](#system-architecture)
- [Installation & Setup](#installation--setup)
- [Database Structure](#database-structure)
- [Module Documentation](#module-documentation)
- [API Integration](#api-integration)
- [Security Features](#security-features)
- [Troubleshooting](#troubleshooting)

## 🎯 System Overview

This system manages the complete student lifecycle from registration to course completion:

1. **Student Registration** - Students register with personal and educational information
2. **Admin Approval** - Admins review and approve/reject registrations
3. **Course Application** - Approved students can apply for courses
4. **Course Approval** - Admins approve course applications with training details
5. **Course Completion** - Admins mark courses as completed
6. **Bookkeeping** - Track payments and receipts for enrolled students
7. **Activity Logging** - All system activities are logged for audit trails

### User Roles

- **Students**: Register, apply for courses, view profile and course history
- **Admins**: Manage students, courses, approvals, and system settings
- **Bookkeeping**: Manage student payments and receipts

## ✨ Key Features

### � lStudent Portal
- **Registration** - Comprehensive registration form with address API integration
- **Profile Management** - View and update personal information
- **Course Applications** - Apply for multiple courses
- **Course History** - View all applied, enrolled, and completed courses
- **Document Tracking** - Track required documents via checklist

### 👨‍💼 Admin Portal
- **Dashboard** - Overview of pending approvals, recent activities, statistics
- **Student Management** - Search, view, edit, approve/reject students
- **Course Management** - Create, edit, delete courses with NC levels
- **Course Applications** - Review and approve course applications
- **Checklist Management** - Manage document requirements
- **System Activity** - View all system activities with filtering
- **Bookkeeping Access** - Quick access to bookkeeping portal

### 💰 Bookkeeping Portal
- **Student Search** - Find students by name or ULI
- **Course Selection** - View student's enrolled courses
- **Receipt Management** - Upload and manage payment receipts
- **File Upload** - Support for images and PDFs


## 🏗️ System Architecture

### Technology Stack

- **Backend**: PHP 7.4+ with PDO
- **Database**: MySQL 5.7+ / MariaDB
- **Frontend**: HTML5, Tailwind CSS 3.x, JavaScript (ES6+)
- **Web Server**: Apache/Nginx (XAMPP/WAMP compatible)
- **APIs**: Philippine PSGC API, REST Countries API

### Design Patterns

- **MVC-inspired**: Separation of concerns with includes, components, and modules
- **Component-based**: Reusable header, footer, sidebar, and alert components
- **API-first**: External APIs for address and country data
- **Activity Logging**: Centralized logging system for all user actions

### Security Architecture

- **Authentication**: Session-based with role validation
- **Authorization**: Middleware-based access control per role
- **SQL Injection**: Prepared statements with PDO
- **XSS Protection**: HTML escaping on all outputs
- **File Upload**: Type and size validation with secure storage
- **Password**: Hashed with PHP password_hash()

## 🚀 Installation & Setup

### Prerequisites

```bash
# Required
- PHP 7.4 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx web server
- PHP Extensions: PDO, pdo_mysql, mbstring, fileinfo

# Recommended
- XAMPP 8.0+ (includes all requirements)
- phpMyAdmin (for database management)
```

### Step-by-Step Installation

#### 1. Clone/Download Project

```bash
# For XAMPP (Windows)
C:\xampp\htdocs\JZGMSAT\

# For XAMPP (Linux/Mac)
/opt/lampp/htdocs/JZGMSAT/

# For WAMP (Windows)
C:\wamp64\www\JZGMSAT\
```

#### 2. Database Setup

**Option A: Using phpMyAdmin**
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Create database: `grading_system`
3. Click "Import" tab
4. Select `schema.sql` from project root
5. Click "Go" to import

**Option B: Using MySQL Command Line**
```bash
mysql -u root -p
CREATE DATABASE grading_system;
USE grading_system;
SOURCE /path/to/schema.sql;
```

#### 3. Configure Database Connection

Edit `config/database.php`:
```php
private $host = 'localhost';
private $db_name = 'grading_system';
private $username = 'root';
private $password = '';  // Your MySQL password
```

#### 4. Set Directory Permissions

```bash
# Linux/Mac
chmod 755 uploads/
chmod 755 uploads/profiles/
chmod 755 uploads/receipts/

# Windows
# Right-click folders → Properties → Security → Edit
# Give "Users" group Full Control
```

#### 5. Access the System

- **Landing Page**: `http://localhost/JZGMSAT/`
- **Student Registration**: `http://localhost/JZGMSAT/student/register.php`
- **Admin Login**: `http://localhost/JZGMSAT/auth/admin_login.php`
- **Bookkeeping Login**: `http://localhost/JZGMSAT/auth/bookkeeping_login.php`

### Default Credentials

```
Admin Account:
Email: admin@admin.com
Password: admin123

Note: Admin credentials are now stored in the database.
Change the default password immediately after first login!

To add new admin users:
1. Use the password hash generator: database/generate_password_hash.php
2. Insert into database via phpMyAdmin or SQL query
```


## 🗄️ Database Structure

### Database Name: `grading_system`

### Tables Overview

| Table Name | Prefix | Description |
|------------|--------|-------------|
| shortcourse_users | ✓ | Admin and bookkeeping user accounts |
| shortcourse_students | ✓ | Student registration and profile data |
| shortcourse_courses | ✓ | Course catalog with NC levels |
| faculty | ✗ | Faculty members (instructors/advisers) |
| shortcourse_course_applications | ✓ | Student course applications and approvals |
| shortcourse_system_activities | ✓ | System activity audit log |
| shortcourse_checklist | ✓ | Document requirements checklist |
| shortcourse_bookkeeping_receipts | ✓ | Payment receipts and tracking |

### Key Tables Schema

#### shortcourse_students
```sql
CREATE TABLE shortcourse_students (
    id INT PRIMARY KEY AUTO_INCREMENT,
    uli VARCHAR(50) UNIQUE,
    first_name VARCHAR(100),
    middle_name VARCHAR(100),
    last_name VARCHAR(100),
    birthday DATE,
    age INT,
    sex ENUM('Male', 'Female', 'Other'),
    civil_status VARCHAR(50),
    contact_number VARCHAR(20),
    province VARCHAR(100),
    city VARCHAR(100),
    barangay VARCHAR(100),
    street_address VARCHAR(200),
    email VARCHAR(150) UNIQUE,
    profile_picture VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected', 'completed'),
    course VARCHAR(200),
    nc_level VARCHAR(10),
    adviser VARCHAR(200),
    training_start DATE,
    training_end DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

#### shortcourse_course_applications
```sql
CREATE TABLE shortcourse_course_applications (
    application_id INT PRIMARY KEY AUTO_INCREMENT,
    student_id INT,
    course_id INT,
    nc_level VARCHAR(10),
    training_start DATE,
    training_end DATE,
    adviser VARCHAR(255),
    status ENUM('pending', 'approved', 'rejected', 'completed'),
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP NULL,
    reviewed_by INT,
    notes TEXT,
    FOREIGN KEY (student_id) REFERENCES shortcourse_students(id),
    FOREIGN KEY (course_id) REFERENCES shortcourse_courses(course_id)
);
```

#### shortcourse_system_activities
```sql
CREATE TABLE shortcourse_system_activities (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    user_type ENUM('admin', 'student', 'system'),
    activity_type VARCHAR(100),
    activity_description TEXT,
    ip_address VARCHAR(45),
    entity_type VARCHAR(50),
    entity_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

### Database Relationships

```
shortcourse_students (1) ←→ (N) shortcourse_course_applications
shortcourse_courses (1) ←→ (N) shortcourse_course_applications
shortcourse_students (1) ←→ (N) shortcourse_bookkeeping_receipts
faculty (1) ←→ (N) shortcourse_course_applications (via adviser)
shortcourse_users (1) ←→ (N) shortcourse_system_activities
```


## 📚 Module Documentation

### 📁 Project Structure

```
JZGMSAT/
├── admin/                          # Admin Portal
│   ├── components/                 # Admin UI components
│   │   ├── header.php             # Admin header with navigation
│   │   ├── sidebar.php            # Admin sidebar menu
│   │   ├── admin-scripts.php      # Shared JavaScript
│   │   └── admin-styles.php       # Shared CSS
│   ├── api/                       # Admin API endpoints
│   │   └── get_checklist.php     # Checklist API
│   ├── course_application/        # Course application module
│   │   └── edit.php              # Edit course application
│   ├── system_activity/           # System activity module
│   │   └── index.php             # Activity log viewer
│   ├── dashboard.php              # Main admin dashboard
│   ├── students.php               # Student management
│   ├── courses.php                # Course management
│   ├── checklist.php              # Checklist management
│   ├── pending_approvals.php      # Student approval queue
│   ├── course_application.php     # Course application review
│   ├── review_course_application.php  # Course approval form
│   ├── approve_student.php        # Student approval handler
│   ├── approve_course_completion.php  # Course completion handler
│   └── system_activity.php        # System activity log
│
├── student/                       # Student Portal
│   ├── components/                # Student UI components
│   │   ├── header.php            # Student header
│   │   ├── footer.php            # Student footer
│   │   ├── navigation.php        # Navigation menu
│   │   ├── alerts.php            # Alert messages
│   │   ├── register-header.php   # Registration header
│   │   ├── api-utils.js          # API utility functions
│   │   └── api-info.md           # API documentation
│   ├── profile/                   # Student profile module
│   │   ├── profile.php           # View profile & course history
│   │   └── new_course.php        # Apply for new course
│   └── register.php               # Student registration form
│
├── bookkeeping/                   # Bookkeeping Portal
│   ├── index.php                  # Bookkeeping dashboard
│   ├── dashboard.php              # Main interface
│   ├── get_students.php           # Student search API
│   ├── get_student_courses.php    # Course list API
│   ├── save_receipt.php           # Receipt save handler
│   ├── upload_file.php            # File upload handler
│   └── check_database.php         # Database check utility
│
├── auth/                          # Authentication
│   ├── login.php                  # Student login (if enabled)
│   ├── admin_login.php            # Admin login
│   ├── bookkeeping_login.php      # Bookkeeping login
│   └── logout.php                 # Logout handler
│
├── includes/                      # Shared Includes
│   ├── auth_middleware.php        # Authentication middleware
│   └── system_activity_logger.php # Activity logging utility
│
├── config/                        # Configuration
│   └── database.php               # Database connection class
│
├── database/                      # Database files
│   └── (migration scripts)
│
├── uploads/                       # File uploads
│   ├── profiles/                  # Student profile pictures
│   └── receipts/                  # Payment receipts
│
├── assets/                        # Static assets
│   ├── css/style.css             # Custom CSS
│   ├── js/main.js                # Custom JavaScript
│   └── images/                    # Images
│
├── index.php                      # Landing page
├── schema.sql                     # Database schema
└── README.md                      # This file
```

### 🎓 Student Module

#### Registration Flow
1. Student fills registration form (`student/register.php`)
2. System validates input and uploads profile picture
3. Student record created with status: `pending`
4. Admin receives notification of new registration
5. Admin reviews and approves/rejects

#### Profile & Course History
- **File**: `student/profile/profile.php`
- **Features**:
  - View personal information
  - View all course applications
  - Track application status (pending/approved/rejected/completed)
  - View training dates and adviser for each course
  - Display completion certificates

#### Course Application
- **File**: `student/profile/new_course.php`
- **Features**:
  - Apply for new courses
  - Select from available courses
  - Automatic ULI pre-fill
  - Validation to prevent duplicate applications


### 👨‍💼 Admin Module

#### Admin User Management

**Authentication System**
- Admin login is now fully dynamic with database-based authentication
- Passwords are securely hashed using PHP's `password_hash()` function
- No hardcoded credentials in the code
- Failed login attempts are logged for security auditing

**Adding New Admin Users**

There are two methods to add admin users:

**Method 1: Using Password Hash Generator (Recommended)**
1. Navigate to: `http://localhost/JZGMSAT/database/generate_password_hash.php`
2. Enter the desired password
3. Click "Generate Hash"
4. Copy the generated SQL INSERT statement
5. Run it in phpMyAdmin SQL tab

**Method 2: Manual Database Insert**
1. Generate password hash using the tool above
2. Open phpMyAdmin
3. Select `grading_system` database
4. Click on `shortcourse_users` table
5. Click "Insert" tab
6. Fill in the form:
   - `username`: Admin's username
   - `email`: Admin's email (must be unique)
   - `password`: Paste the generated hash
   - `role`: Select 'admin'
7. Click "Go"

**Example SQL:**
```sql
INSERT INTO shortcourse_users (username, email, password, role) 
VALUES (
    'john_admin',
    'john@jzgmsat.edu.ph',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'admin'
);
```

**Security Notes:**
- Never store plain text passwords
- Each admin must have a unique email
- Delete `generate_password_hash.php` in production
- Change default admin password immediately
- Failed login attempts are logged in system_activities

#### Dashboard (`admin/admin-dashboard.php`)
- **Statistics Cards**:
  - Total students
  - Pending approvals
  - Approved students
  - Completed courses
- **Recent Activities**: Last 10 system activities
- **Pending Approvals**: Students awaiting approval
- **Approved Applications**: Courses ready for completion

#### Student Management (`admin/students.php`)
- **Search & Filter**: By name, ULI, status, course
- **Actions**:
  - View detailed profile
  - Edit student information
  - Approve/reject registration
  - View course history
- **Bulk Operations**: Export, print

#### Course Management (`admin/courses.php`)
- **CRUD Operations**: Create, Read, Update, Delete courses
- **Course Details**:
  - Course name
  - NC Level (I, II, III, IV)
  - Description
  - Duration
- **Activity Logging**: All changes logged

#### Course Application Review (`admin/review_course_application.php`)
- **Approval Form**:
  - Select course
  - Set NC level
  - Assign training dates
  - Assign adviser
  - Add notes
- **Actions**: Approve or Reject
- **Data Storage**: Saves to both `students` and `course_applications` tables

#### Checklist Management (`admin/checklist.php`)
- **Document Requirements**:
  - Add/edit/delete checklist items
  - Set item names and descriptions
  - Reorder items
- **Live Search**: Filter checklist items
- **Activity Logging**: Track all changes

#### System Activity Log (`admin/system_activity.php`)
- **Activity Tracking**:
  - User logins/logouts
  - Student registrations
  - Course applications
  - Approvals/rejections
  - Data modifications
- **Filtering**:
  - By activity type
  - By user type (admin/student)
  - By date range
  - Search by description
- **Pagination**: 20 activities per page
- **Statistics**: Today, this week, this month counts

### 💰 Bookkeeping Module

#### Dashboard (`bookkeeping/dashboard.php`)
- **Student Search**:
  - Search by name or ULI
  - Real-time search results
  - Student profile display
- **Course Selection**:
  - View enrolled courses
  - Select course for payment
- **Receipt Management**:
  - Upload receipt files (images/PDFs)
  - View uploaded receipts
  - Track payment history

#### File Upload (`bookkeeping/upload_file.php`)
- **Supported Formats**: JPG, PNG, PDF
- **Max Size**: 5MB
- **Storage**: `uploads/receipts/`
- **Naming**: `receipt_{student_id}_{course_id}_{timestamp}.{ext}`


## 🔌 API Integration

### Philippine Standard Geographic Code (PSGC) API

**Base URL**: `https://psgc.gitlab.io/api`

#### Endpoints Used

```javascript
// Get all provinces
GET /provinces/

// Get cities/municipalities by province
GET /provinces/{province_code}/cities-municipalities/

// Get barangays by city
GET /cities-municipalities/{city_code}/barangays/
```

#### Implementation (`student/components/api-utils.js`)

```javascript
// Load provinces
APIUtils.loadProvinces('province-select');

// Setup cascading dropdowns
APIUtils.setupAddressCascade('province', 'city', 'barangay');

// Features:
// - Response caching
// - Loading states
// - Error handling
// - Form value persistence
```

### REST Countries API

**Base URL**: `https://restcountries.com/v3.1`

#### Endpoint Used

```javascript
// Get all countries with phone codes
GET /all?fields=name,idd
```

#### Usage

```javascript
// Load country codes for phone numbers
APIUtils.loadCountryCodes('country-code-select');
```

### API Utilities Functions

| Function | Description | Parameters |
|----------|-------------|------------|
| `loadProvinces()` | Load Philippine provinces | selectId, selectedValue |
| `loadCities()` | Load cities by province | provinceCode, selectId, selectedValue |
| `loadBarangays()` | Load barangays by city | cityCode, selectId, selectedValue |
| `loadCountryCodes()` | Load country phone codes | selectId, selectedValue |
| `setupAddressCascade()` | Setup cascading address dropdowns | provinceId, cityId, barangayId |

## 🎨 Design System

### Tailwind CSS Configuration

```javascript
tailwind.config = {
    theme: {
        extend: {
            colors: {
                primary: {
                    50: '#eff6ff',
                    500: '#3b82f6',
                    600: '#2563eb',
                    700: '#1d4ed8',
                    900: '#1e3a8a'
                }
            }
        }
    }
}
```

### Component Patterns

#### Cards
```html
<div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6">
    <!-- Card content -->
</div>
```

#### Buttons
```html
<!-- Primary Button -->
<button class="bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg">
    Button Text
</button>

<!-- Secondary Button -->
<button class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-semibold py-2 px-4 rounded-lg">
    Button Text
</button>
```

#### Form Inputs
```html
<input type="text" 
       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
```

#### Alerts
```html
<!-- Success Alert -->
<div class="bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
    <i class="fas fa-check-circle mr-2"></i>
    Success message
</div>

<!-- Error Alert -->
<div class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
    <i class="fas fa-exclamation-circle mr-2"></i>
    Error message
</div>
```

### Responsive Breakpoints

| Breakpoint | Min Width | Usage |
|------------|-----------|-------|
| `sm:` | 640px | Small tablets |
| `md:` | 768px | Tablets |
| `lg:` | 1024px | Laptops |
| `xl:` | 1280px | Desktops |
| `2xl:` | 1536px | Large screens |


## 🔒 Security Features

### Authentication & Authorization

#### Session Management
```php
// Start session
session_start();

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /auth/login.php');
    exit();
}

// Role-based access
requireAdmin();  // Only admins
requireBookkeeping();  // Only bookkeeping
```

#### Password Security
```php
// Hash password
$hashed = password_hash($password, PASSWORD_DEFAULT);

// Verify password
if (password_verify($input, $hashed)) {
    // Login successful
}
```

### SQL Injection Prevention

```php
// Always use prepared statements
$stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
$stmt->bindParam(':id', $student_id, PDO::PARAM_INT);
$stmt->execute();
```

### XSS Protection

```php
// Escape output
echo htmlspecialchars($user_input, ENT_QUOTES, 'UTF-8');

// For attributes
echo '<input value="' . htmlspecialchars($value) . '">';
```

### File Upload Security

```php
// Validate file type
$allowed = ['image/jpeg', 'image/png', 'application/pdf'];
if (!in_array($_FILES['file']['type'], $allowed)) {
    die('Invalid file type');
}

// Validate file size (5MB max)
if ($_FILES['file']['size'] > 5 * 1024 * 1024) {
    die('File too large');
}

// Generate unique filename
$filename = uniqid() . '_' . basename($_FILES['file']['name']);
```

### Directory Protection

`.htaccess` in uploads directory:
```apache
# Prevent direct access to PHP files
<FilesMatch "\.php$">
    Order Deny,Allow
    Deny from all
</FilesMatch>

# Allow only specific file types
<FilesMatch "\.(jpg|jpeg|png|pdf)$">
    Order Allow,Deny
    Allow from all
</FilesMatch>
```

## 📊 Activity Logging

### System Activity Logger

**File**: `includes/system_activity_logger.php`

#### Usage

```php
// Include logger
require_once '../includes/system_activity_logger.php';

// Log activity
logActivity(
    $user_id,           // User ID (or null for system)
    $user_type,         // 'admin', 'student', 'system'
    $activity_type,     // 'login', 'student_registration', etc.
    $description,       // Human-readable description
    $entity_type,       // 'student', 'course', etc. (optional)
    $entity_id          // Related entity ID (optional)
);
```

#### Activity Types

| Type | Description |
|------|-------------|
| `login` | User login |
| `logout` | User logout |
| `student_registration` | New student registration |
| `student_approval` | Student approved |
| `student_rejection` | Student rejected |
| `course_created` | New course created |
| `course_updated` | Course updated |
| `course_deleted` | Course deleted |
| `course_application` | Course application submitted |
| `course_approval` | Course application approved |
| `course_completion` | Course marked as completed |
| `checklist_created` | Checklist item created |
| `checklist_updated` | Checklist item updated |
| `checklist_deleted` | Checklist item deleted |

#### Example

```php
// Log student approval
logActivity(
    $_SESSION['user_id'],
    'admin',
    'student_approval',
    "Approved student registration for {$student_name}",
    'student',
    $student_id
);
```


## 🔧 Configuration

### Database Configuration

**File**: `config/database.php`

```php
class Database {
    private $host = 'localhost';
    private $db_name = 'grading_system';
    private $username = 'root';
    private $password = '';
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch(PDOException $e) {
            echo "Connection Error: " . $e->getMessage();
        }
        return $this->conn;
    }
}
```

### PHP Configuration

**Recommended `php.ini` settings:**

```ini
# File uploads
upload_max_filesize = 5M
post_max_size = 8M
max_file_uploads = 20

# Execution
max_execution_time = 30
max_input_time = 60
memory_limit = 128M

# Sessions
session.gc_maxlifetime = 1440
session.cookie_httponly = 1

# Error reporting (development)
display_errors = On
error_reporting = E_ALL

# Error reporting (production)
display_errors = Off
error_reporting = E_ALL
log_errors = On
error_log = /path/to/php-error.log
```

### Apache Configuration

**`.htaccess` in root directory:**

```apache
# Enable rewrite engine
RewriteEngine On

# Redirect to HTTPS (production)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Prevent directory listing
Options -Indexes

# Protect sensitive files
<FilesMatch "(\.env|\.git|\.htaccess|composer\.json|composer\.lock)">
    Order Allow,Deny
    Deny from all
</FilesMatch>
```

## 🚨 Troubleshooting

### Common Issues

#### 1. Database Connection Error

**Error**: `Connection Error: SQLSTATE[HY000] [1045] Access denied`

**Solutions**:
- Verify MySQL service is running
- Check database credentials in `config/database.php`
- Ensure database `grading_system` exists
- Grant proper permissions to MySQL user

```sql
GRANT ALL PRIVILEGES ON grading_system.* TO 'root'@'localhost';
FLUSH PRIVILEGES;
```

#### 2. File Upload Fails

**Error**: `Failed to upload file` or `File too large`

**Solutions**:
- Check `uploads/` directory exists and is writable
- Verify PHP `upload_max_filesize` setting
- Check disk space availability
- Ensure file type is allowed

```bash
# Linux/Mac
chmod 755 uploads/
chmod 755 uploads/profiles/
chmod 755 uploads/receipts/

# Check permissions
ls -la uploads/
```

#### 3. Session Issues

**Error**: `Session not starting` or `User logged out unexpectedly`

**Solutions**:
- Check PHP session directory is writable
- Verify `session.save_path` in php.ini
- Clear browser cookies
- Check session timeout settings

```php
// Debug session
var_dump($_SESSION);
echo session_save_path();
```

#### 4. API Not Loading

**Error**: `Failed to load provinces/cities`

**Solutions**:
- Check internet connection
- Verify API endpoints are accessible
- Check browser console for errors
- Clear browser cache

```javascript
// Test API manually
fetch('https://psgc.gitlab.io/api/provinces/')
    .then(r => r.json())
    .then(data => console.log(data));
```

#### 5. Blank Page / White Screen

**Error**: Blank page with no error message

**Solutions**:
- Enable error display in php.ini
- Check PHP error logs
- Verify all required files exist
- Check file permissions

```php
// Add to top of file for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);
```


### Debugging Tips

#### Enable Error Reporting

```php
// Add to top of PHP files during development
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
```

#### Check Database Queries

```php
// Debug PDO queries
try {
    $stmt = $conn->prepare($sql);
    $stmt->execute();
} catch (PDOException $e) {
    echo "SQL Error: " . $e->getMessage();
    echo "SQL Query: " . $sql;
}
```

#### Check Session Data

```php
// View session contents
echo '<pre>';
print_r($_SESSION);
echo '</pre>';
```

#### Check File Upload

```php
// Debug file upload
echo '<pre>';
print_r($_FILES);
echo '</pre>';
```

## 📈 Performance Optimization

### Database Optimization

```sql
-- Add indexes for frequently queried columns
CREATE INDEX idx_student_uli ON shortcourse_students(uli);
CREATE INDEX idx_student_status ON shortcourse_students(status);
CREATE INDEX idx_application_status ON shortcourse_course_applications(status);
CREATE INDEX idx_activity_created ON shortcourse_system_activities(created_at);

-- Optimize tables
OPTIMIZE TABLE shortcourse_students;
OPTIMIZE TABLE shortcourse_course_applications;
OPTIMIZE TABLE shortcourse_system_activities;
```

### Caching Strategies

```php
// Cache API responses in session
if (!isset($_SESSION['provinces_cache'])) {
    $_SESSION['provinces_cache'] = fetchProvinces();
}
$provinces = $_SESSION['provinces_cache'];
```

### Image Optimization

```php
// Resize uploaded images
function resizeImage($source, $destination, $maxWidth = 800) {
    list($width, $height) = getimagesize($source);
    $ratio = $width / $height;
    
    if ($width > $maxWidth) {
        $newWidth = $maxWidth;
        $newHeight = $maxWidth / $ratio;
        
        $image = imagecreatefromjpeg($source);
        $newImage = imagecreatetruecolor($newWidth, $newHeight);
        imagecopyresampled($newImage, $image, 0, 0, 0, 0, 
                          $newWidth, $newHeight, $width, $height);
        imagejpeg($newImage, $destination, 85);
    }
}
```

## 🔄 Backup & Restore

### Database Backup

```bash
# Backup database
mysqldump -u root -p grading_system > backup_$(date +%Y%m%d).sql

# Backup with compression
mysqldump -u root -p grading_system | gzip > backup_$(date +%Y%m%d).sql.gz

# Automated daily backup (Linux cron)
0 2 * * * mysqldump -u root -pYOURPASSWORD grading_system > /backups/db_$(date +\%Y\%m\%d).sql
```

### Database Restore

```bash
# Restore from backup
mysql -u root -p grading_system < backup_20260220.sql

# Restore from compressed backup
gunzip < backup_20260220.sql.gz | mysql -u root -p grading_system
```

### File Backup

```bash
# Backup uploads directory
tar -czf uploads_backup_$(date +%Y%m%d).tar.gz uploads/

# Restore uploads
tar -xzf uploads_backup_20260220.tar.gz
```

## 🚀 Deployment

### Production Checklist

- [ ] Change default admin password
- [ ] Change default bookkeeping password
- [ ] Update database credentials
- [ ] Disable error display (`display_errors = Off`)
- [ ] Enable error logging
- [ ] Set up HTTPS/SSL certificate
- [ ] Configure firewall rules
- [ ] Set up automated backups
- [ ] Test all functionality
- [ ] Set proper file permissions
- [ ] Remove development files
- [ ] Update API endpoints if needed
- [ ] Configure email settings (if applicable)

### Security Hardening

```php
// config/database.php - Use environment variables
private $host = getenv('DB_HOST') ?: 'localhost';
private $db_name = getenv('DB_NAME') ?: 'grading_system';
private $username = getenv('DB_USER') ?: 'root';
private $password = getenv('DB_PASS') ?: '';
```

```apache
# .htaccess - Additional security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```


## 📝 Development Guidelines

### Code Style

#### PHP
```php
// Use PSR-12 coding standards
// Class names: PascalCase
class StudentManager {}

// Method names: camelCase
public function getStudentById($id) {}

// Constants: UPPER_SNAKE_CASE
define('MAX_FILE_SIZE', 5242880);

// Variables: snake_case
$student_name = 'John Doe';
```

#### JavaScript
```javascript
// Use ES6+ features
// Variables: camelCase
const studentName = 'John Doe';

// Functions: camelCase
function loadStudentData() {}

// Constants: UPPER_SNAKE_CASE
const MAX_RETRIES = 3;
```

#### SQL
```sql
-- Table names: lowercase with prefix
CREATE TABLE shortcourse_students;

-- Column names: snake_case
first_name VARCHAR(100);

-- Always use prepared statements
SELECT * FROM students WHERE id = :id;
```

### Git Workflow

```bash
# Create feature branch
git checkout -b feature/student-profile-enhancement

# Make changes and commit
git add .
git commit -m "Add: Student profile photo upload feature"

# Push to remote
git push origin feature/student-profile-enhancement

# Create pull request for review
```

### Commit Message Convention

```
Type: Brief description

Detailed description (optional)

Types:
- Add: New feature
- Fix: Bug fix
- Update: Modify existing feature
- Remove: Delete feature/file
- Refactor: Code restructuring
- Docs: Documentation changes
- Style: Code formatting
- Test: Add/update tests
```

## 🧪 Testing

### Manual Testing Checklist

#### Student Module
- [ ] Register new student with all fields
- [ ] Upload profile picture
- [ ] Submit registration
- [ ] View profile after approval
- [ ] Apply for course
- [ ] View course history

#### Admin Module
- [ ] Login as admin
- [ ] View dashboard statistics
- [ ] Approve student registration
- [ ] Reject student registration
- [ ] Create new course
- [ ] Edit existing course
- [ ] Delete course
- [ ] Approve course application
- [ ] Mark course as completed
- [ ] View system activity log
- [ ] Search and filter activities

#### Bookkeeping Module
- [ ] Login as bookkeeping
- [ ] Search for student
- [ ] View student courses
- [ ] Upload receipt
- [ ] View uploaded receipts

### Database Testing

```sql
-- Test student creation
INSERT INTO shortcourse_students (first_name, last_name, email, uli) 
VALUES ('Test', 'Student', 'test@example.com', 'TEST-2026-001');

-- Test course application
INSERT INTO shortcourse_course_applications (student_id, course_id, status)
VALUES (1, 1, 'pending');

-- Test activity logging
INSERT INTO shortcourse_system_activities (user_id, user_type, activity_type, activity_description)
VALUES (1, 'admin', 'test', 'Test activity');

-- Cleanup test data
DELETE FROM shortcourse_students WHERE email = 'test@example.com';
```

## 📚 Additional Resources

### External Documentation

- [PHP Manual](https://www.php.net/manual/en/)
- [MySQL Documentation](https://dev.mysql.com/doc/)
- [Tailwind CSS Docs](https://tailwindcss.com/docs)
- [PDO Tutorial](https://www.php.net/manual/en/book.pdo.php)
- [PSGC API](https://psgc.gitlab.io/)

### Useful Tools

- **phpMyAdmin**: Database management
- **VS Code**: Code editor with PHP extensions
- **Postman**: API testing
- **Chrome DevTools**: Frontend debugging
- **Git**: Version control

### Learning Resources

- [PHP The Right Way](https://phptherightway.com/)
- [MDN Web Docs](https://developer.mozilla.org/)
- [W3Schools PHP](https://www.w3schools.com/php/)
- [Tailwind CSS Tutorial](https://tailwindcss.com/docs/installation)

## 🤝 Contributing

### How to Contribute

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

### Code Review Process

1. Code must follow style guidelines
2. All tests must pass
3. Documentation must be updated
4. Security review for sensitive changes
5. At least one approval required

## 📄 License

This project is developed for Jacobo Z. Gonzales Memorial School of Arts and Trades.

## 👥 Support & Contact

For technical support or questions:

- **Email**: support@jzgmsat.edu.ph
- **Phone**: (123) 456-7890
- **Address**: Jacobo Z. Gonzales Memorial School of Arts and Trades

## 📅 Version History

### Version 1.0.0 (Current)
- Initial release
- Student registration system
- Admin portal with approval workflow
- Course application management
- Bookkeeping module
- System activity logging
- Checklist management

---

**Last Updated**: February 20, 2026  
**Maintained By**: JZGMSAT IT Department


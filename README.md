# Student Registration System with Tailwind CSS

A modern, responsive web-based student registration system built with **Tailwind CSS** and **pure PHP**. Features role-based access control with separate Student and Admin portals, complete with approval workflows and secure authentication.

## 🚀 Live Access

- **Landing Page**: `http://localhost/your-project/index.html`
- **Student Registration**: `http://localhost/your-project/student/register.php` (Direct Access)
- **Admin Login**: `http://localhost/your-project/auth/login.php`
- **Admin Dashboard**: Login as admin to access dashboard

## ✨ Key Features

### 🎓 Student Registration (No Authentication Required)
- **Direct Access** - Students can register without creating accounts
- **Registration Form** - Comprehensive student information collection
- **Mobile-First Design** - Built with Tailwind CSS for perfect mobile experience
- **Form Validation** - Client-side and server-side validation
- **File Upload** - Profile picture upload with preview and validation
- **Unique Student ID** - Auto-generated unique student identifiers
- **Approval Status** - Registration submitted for admin approval

### 👨‍💼 Admin Portal (Authentication Required)
- **Secure Login** - Admin authentication required
- **Dashboard** - Statistics and recent registrations overview
- **Approval System** - Review and approve/reject student registrations
- **Student Management** - View, search, and manage all student records
- **Role-Based Access** - Only admins can access admin functions

### 🔒 Access Control
- **Students**: Direct access to registration form (no login required)
- **Admins**: Must login to access admin functions
- **Security**: Admin portal protected with authentication
- **Session Management**: Secure session handling for admin users

### 🎨 Modern Design
- **Tailwind CSS** - Modern, utility-first CSS framework
- **Responsive Design** - Works perfectly on all devices
- **Professional UI** - Clean, modern interface design
- **Consistent Styling** - Unified design system throughout
- **Accessibility** - WCAG compliant design patterns

## 🛠️ Installation & Setup

### Prerequisites
- **PHP 7.4+** with PDO MySQL extension
- **MySQL 5.7+** or MariaDB
- **Web Server** (Apache/Nginx/XAMPP/WAMP)
- **Internet Connection** (for Tailwind CSS CDN)

### Quick Setup

1. **Clone/Download** the project to your web server directory
   ```bash
   # For XAMPP
   C:\xampp\htdocs\student-registration\
   
   # For WAMP  
   C:\wamp64\www\student-registration\
   
   # For Linux/Mac
   /var/www/html/student-registration/
   ```

2. **Database Configuration**
   - Open `config/database.php`
   - Update database credentials if needed:
     ```php
     private $host = 'localhost';
     private $db_name = 'student_registration_db';
     private $username = 'root';
     private $password = '';
     ```

3. **Initialize Database**
   - Visit: `http://localhost/student-registration/setup_database.php`
   - The system will automatically create:
     - Database: `student_registration_db`
     - Tables: `users`, `students`
     - Default admin account: `admin` / `admin123`

4. **Set Permissions**
   - Ensure `uploads/` directory is writable
   - Linux/Mac: `chmod 755 uploads/`
   - Windows: Right-click → Properties → Security → Full Control

5. **Access the System**
   - **Landing Page**: `http://localhost/student-registration/`
   - **Student Registration**: `http://localhost/student-registration/student/register.php` (Direct Access)
   - **Admin Login**: `http://localhost/student-registration/auth/login.php`

### Default Credentials
```
Admin Login (Only):
Username: admin
Password: admin123

Student Registration:
No accounts - direct access to registration form
```

## 🔐 Access Control

### Student Access (No Authentication)
- ✅ **Direct Access**: Can access registration form immediately
- ✅ **Registration**: Fill out and submit registration form
- ✅ **File Upload**: Upload profile pictures
- ❌ **No Login Required**: Students don't need accounts
- ❌ **No Admin Access**: Cannot access admin functions

### Admin Access (Authentication Required)  
- ✅ **Admin Login Only**: Only admin accounts exist in the system
- ✅ **Dashboard Access**: View statistics and recent registrations
- ✅ **Approval Management**: Approve/reject student registrations
- ✅ **Student Management**: View and manage all student records
- ❌ **Admin Only**: No student accounts in the authentication system

### Security Features
- **Admin-Only Authentication**: Only admin accounts exist in the system
- **Session Management**: Secure session handling for admin users
- **Role Validation**: All authenticated users are admins
- **Direct Student Access**: No barriers for student registration

## 📁 Project Structure

```
project-root/
│
├─ auth/                     # Authentication System (Admin Only)
│   ├─ login.php             # Admin login page
│   └─ logout.php            # Logout functionality
│
├─ admin/                    # Admin Portal (Admin Access Only)
│   ├─ dashboard.php         # Admin dashboard with statistics
│   ├─ students/             # Student Management Module
│   │   ├─ index.php         # Student management interface
│   │   ├─ view.php          # Detailed student profile view
│   │   └─ edit.php          # Edit student information
│   └─ pending_approvals.php # Review and approve registrations
│
├─ student/                  # Student Portal (Student Access Only)
│   └─ register.php          # Student registration form
│
├─ includes/                 # Shared Components
│   ├─ auth_middleware.php   # Role-based access control
│   ├─ header.php            # Common header template
│   └─ footer.php            # Common footer template
│
├─ config/                   # Configuration
│   └─ database.php          # Database connection and setup
│
├─ uploads/                  # File Uploads
│   ├─ profiles/             # Profile pictures
│   └─ .htaccess             # Security rules
│
├─ assets/                   # Static Assets (Legacy)
│   ├─ css/style.css         # Custom CSS (supplementary)
│   └─ js/main.js            # JavaScript functionality
│
├─ index.html                # Landing page with Tailwind CSS
└─ README.md                 # Documentation
```

## 🎨 Design System (Tailwind CSS)

### Color Palette
```css
Primary (Maroon):
- 50: #fef2f2
- 500: #800000 (Main)
- 600: #660000 (Hover)
- 700: #5c0000 (Active)

Secondary (Navy Blue):
- 500: #000080 (Main)
- 600: #000066 (Hover)
```

### Component Classes
- **Cards**: `bg-white rounded-xl shadow-xl overflow-hidden`
- **Buttons**: `bg-primary-500 hover:bg-primary-600 text-white font-medium py-3 px-4 rounded-lg`
- **Forms**: `w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-primary-500`
- **Navigation**: `border-b-2 border-primary-500 text-primary-600`

### Responsive Breakpoints
- **Mobile**: Default (< 640px)
- **Tablet**: `md:` (≥ 768px)  
- **Desktop**: `lg:` (≥ 1024px)
- **Large**: `xl:` (≥ 1280px)

## Database Schema

### Students Table
| Column | Type | Description |
|--------|------|-------------|
| id | INT (PK, AI) | Primary key |
| first_name | VARCHAR(100) | Student's first name |
| middle_name | VARCHAR(100) | Student's middle name (optional) |
| last_name | VARCHAR(100) | Student's last name |
| birthday | DATE | Date of birth |
| age | INT | Auto-calculated age |
| sex | ENUM | Male, Female, Other |
| civil_status | VARCHAR(50) | Civil status |
| contact_number | VARCHAR(20) | Phone number |
| province | VARCHAR(100) | Province (from AAPI) |
| city | VARCHAR(100) | City/Municipality (from AAPI) |
| barangay | VARCHAR(100) | Barangay (from AAPI) |
| street_address | VARCHAR(200) | Street/Subdivision (optional) |
| place_of_birth | VARCHAR(200) | Place of birth |
| parent_name | VARCHAR(200) | Parent/Guardian name |
| parent_contact | VARCHAR(20) | Parent/Guardian contact |
| email | VARCHAR(150) | Email address (unique) |
| profile_picture | VARCHAR(255) | Profile picture path |
| uli | VARCHAR(50) | Unique Learner Identifier (unique) |
| last_school | VARCHAR(200) | Last school attended |
| school_province | VARCHAR(100) | School province |
| school_city | VARCHAR(100) | School city |
| created_at | TIMESTAMP | Registration timestamp |

## API Integration

### Philippine Address API (AAPI)
- **Base URL**: `https://psgc.gitlab.io/api`
- **Provinces**: `/provinces/`
- **Cities**: `/provinces/{province_code}/cities-municipalities/`
- **Barangays**: `/cities-municipalities/{city_code}/barangays/`

The system automatically loads provinces on page load and dynamically updates cities and barangays based on user selection.

## Security Features

- **SQL Injection Protection** - Prepared statements
- **File Upload Security** - Type and size validation
- **XSS Protection** - HTML escaping
- **Captcha Verification** - Math-based captcha
- **Input Validation** - Both client and server-side
- **Directory Protection** - .htaccess rules

## Responsive Design

### Breakpoints
- **Mobile**: < 576px
- **Tablet**: 576px - 768px
- **Desktop**: > 768px

### Color Scheme
- **Primary**: Maroon (#800000)
- **Secondary**: Navy Blue (#000080)
- **Background**: White (#ffffff)

## Future Expansion

The modular structure allows easy addition of new features:

### Suggested Modules
- **Authentication System** (`auth/`)
- **Reports Module** (`reports/`)
- **Settings Module** (`settings/`)
- **API Module** (`api/`)
- **Notifications** (`notifications/`)

### Adding New Modules
1. Create module directory (e.g., `reports/`)
2. Add navigation links in admin interface
3. Follow existing file structure patterns
4. Use existing CSS classes and JavaScript functions

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check MySQL service is running
   - Verify database credentials in `config/database.php`
   - Ensure database user has proper permissions

2. **File Upload Issues**
   - Check `uploads/` directory permissions
   - Verify PHP `upload_max_filesize` and `post_max_size` settings
   - Ensure web server has write permissions

3. **AAPI Not Loading**
   - Check internet connection
   - Verify AAPI endpoints are accessible
   - Check browser console for JavaScript errors

4. **Responsive Issues**
   - Clear browser cache
   - Check CSS file is loading properly
   - Verify viewport meta tag is present

### PHP Configuration
Recommended PHP settings:
```ini
upload_max_filesize = 2M
post_max_size = 8M
max_execution_time = 30
memory_limit = 128M
```

## Support

For issues or questions:
1. Check the troubleshooting section
2. Verify all setup steps were completed
3. Check browser console for JavaScript errors
4. Review PHP error logs

## License

This project is open source and available under the MIT License.
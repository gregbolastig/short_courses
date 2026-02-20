<?php
session_start();

require_once '../config/database.php';
require_once '../includes/auth_middleware.php';
require_once '../includes/system_activity_logger.php';

requireAdmin();

$database = new Database();
$conn = $database->getConnection();
$logger = new SystemActivityLogger($conn);

// Routing: which logical page to show
$page = isset($_GET['page']) ? trim((string)$_GET['page']) : 'index';
$allowed_pages = ['index', 'view', 'edit'];
if (!in_array($page, $allowed_pages, true)) {
    $page = 'index';
}

// Shared state
$page_title = 'Manage Students';
$error_message = '';
$success_message = '';

// `p` is used for pagination to avoid clashing with routing `page`
$p = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;

// ========================================================================
// Helper functions (from original index.php, slightly extended)
// ========================================================================

function checkSoftDeleteColumn(PDO $conn): bool {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM students LIKE 'deleted_at'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function handleStudentDelete(PDO $conn, SystemActivityLogger $logger, int $student_id, int $user_id): array {
    try {
        // Get student info before delete
        $stmt = $conn->prepare("SELECT id, first_name, last_name, profile_picture FROM students WHERE id = :id");
        $stmt->bindParam(':id', $student_id, PDO::PARAM_INT);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            return ['error' => 'Student not found.'];
        }

        // Soft delete record
        $stmt = $conn->prepare("UPDATE students SET deleted_at = NOW() WHERE id = :id");
        $stmt->bindParam(':id', $student_id, PDO::PARAM_INT);
        $stmt->execute();

        // Best-effort delete of profile picture (matches old view.php behaviour)
        if (!empty($student['profile_picture'])) {
            $stored_path = $student['profile_picture'];
            if (strpos($stored_path, '../') === 0) {
                $file_path = $stored_path;
            } else {
                $file_path = '../' . $stored_path;
            }
            if (file_exists($file_path)) {
                @unlink($file_path);
            }
        }

        // Log deletion
        $logger->log(
            'student_deleted',
            "Admin soft-deleted student '{$student['first_name']} {$student['last_name']}' (ID: {$student_id})",
            'admin',
            $user_id,
            'student',
            $student_id
        );

        return ['success' => 'Student removed from view.'];
    } catch (PDOException $e) {
        return ['error' => 'Database error: ' . $e->getMessage()];
    }
}

function buildWhereClause(string $search, string $filter_course, string $filter_status, string $start_date, string $end_date, bool $has_soft_delete): array {
    $where_conditions = [];
    $params = [];

    if ($has_soft_delete) {
        $where_conditions[] = "deleted_at IS NULL";
    }

    if ($search !== '') {
        $where_conditions[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR uli LIKE :search OR student_id LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    if ($filter_course !== '') {
        $where_conditions[] = "course = :course";
        $params[':course'] = $filter_course;
    }

    if ($filter_status !== '') {
        $where_conditions[] = "status = :status";
        $params[':status'] = $filter_status;
    }

    if ($start_date !== '') {
        $where_conditions[] = "DATE(created_at) >= :start_date";
        $params[':start_date'] = $start_date;
    }

    if ($end_date !== '') {
        $where_conditions[] = "DATE(created_at) <= :end_date";
        $params[':end_date'] = $end_date;
    }

    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    return [$where_clause, $params];
}

function getTotalStudents(PDO $conn, string $where_clause, array $params): int {
    $sql = "SELECT COUNT(*) as total FROM students {$where_clause}";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    return (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
}

function getStudents(PDO $conn, string $where_clause, array $params, int $limit, int $offset): array {
    $sql = "SELECT id, student_id, first_name, middle_name, last_name, email, uli, sex,
                   province, city, contact_number, status, created_at
            FROM students {$where_clause}
            ORDER BY created_at DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getActiveCourses(PDO $conn): array {
    try {
        $stmt = $conn->query("SELECT course_name FROM courses WHERE is_active = 1 ORDER BY course_name");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function getStudentStatistics(PDO $conn, bool $has_soft_delete): array {
    $soft_delete_filter = $has_soft_delete ? " AND deleted_at IS NULL" : "";
    $stats = [
        'total' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'completed' => 0,
    ];

    try {
        $queries = [
            'total' => "SELECT COUNT(*) as count FROM students WHERE 1=1{$soft_delete_filter}",
            'pending' => "SELECT COUNT(*) as count FROM students WHERE status = 'pending'{$soft_delete_filter}",
            'approved' => "SELECT COUNT(*) as count FROM students WHERE status = 'approved'{$soft_delete_filter}",
            'rejected' => "SELECT COUNT(*) as count FROM students WHERE status = 'rejected'{$soft_delete_filter}",
            'completed' => "SELECT COUNT(*) as count FROM students WHERE status = 'completed'{$soft_delete_filter}",
        ];

        foreach ($queries as $key => $query) {
            $stmt = $conn->query($query);
            $stats[$key] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
        }
    } catch (PDOException $e) {
        // ignore, keep defaults
    }

    return $stats;
}

function getStatusBadgeClass(string $status): string {
    switch ($status) {
        case 'completed':
        case 'approved':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'rejected':
            return 'bg-red-100 text-red-800 border-red-200';
        case 'pending':
        default:
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
    }
}

function getStudentInitials(string $first_name, string $last_name): string {
    return strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
}

function getStudentFullName(array $student): string {
    return htmlspecialchars(trim(($student['first_name'] ?? '') . ' ' . ($student['middle_name'] ?? '') . ' ' . ($student['last_name'] ?? '')));
}

// ========================================================================
// Global delete handler (works for all views)
// ========================================================================

if (isset($_POST['action'], $_POST['id'], $_POST['admin_password']) && $_POST['action'] === 'delete' && is_numeric($_POST['id'])) {
    $student_id = (int)$_POST['id'];
    $admin_password = $_POST['admin_password'];
    
    // Verify admin password
    try {
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$admin || !password_verify($admin_password, $admin['password'])) {
            $_SESSION['toast_message'] = 'Invalid password. Deletion cancelled.';
            $_SESSION['toast_type'] = 'error';
            header('Location: students.php?page=index');
            exit;
        }
        
        // Password verified, proceed with deletion
        $result = handleStudentDelete($conn, $logger, $student_id, (int)$_SESSION['user_id']);
        if (!empty($result['error'])) {
            $_SESSION['toast_message'] = $result['error'];
            $_SESSION['toast_type'] = 'error';
        } else {
            $_SESSION['toast_message'] = $result['success'];
            $_SESSION['toast_type'] = 'success';
        }
    } catch (PDOException $e) {
        $_SESSION['toast_message'] = 'Database error: ' . $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }
    
    header('Location: students.php?page=index');
    exit;
}

// ========================================================================
// Route-specific data loading
// ========================================================================

$pending_approvals = 0;

if ($page === 'index') {
    // Filters
    $search = isset($_GET['search']) ? (string)$_GET['search'] : '';
    $filter_course = isset($_GET['filter_course']) ? (string)$_GET['filter_course'] : '';
    $filter_status = isset($_GET['filter_status']) ? (string)$_GET['filter_status'] : '';
    $start_date = isset($_GET['start_date']) ? (string)$_GET['start_date'] : '';
    $end_date = isset($_GET['end_date']) ? (string)$_GET['end_date'] : '';

    $limit = 10;
    $offset = ($p - 1) * $limit;

    $has_soft_delete = checkSoftDeleteColumn($conn);

    $students = [];
    $total_students = 0;
    $total_pages = 1;
    $courses = [];
    $statistics = [
        'total' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'completed' => 0,
    ];

    try {
        [$where_clause, $params] = buildWhereClause($search, $filter_course, $filter_status, $start_date, $end_date, $has_soft_delete);
        $total_students = getTotalStudents($conn, $where_clause, $params);
        $total_pages = max(1, (int)ceil($total_students / $limit));
        $students = getStudents($conn, $where_clause, $params, $limit, $offset);
        $courses = getActiveCourses($conn);
        $statistics = getStudentStatistics($conn, $has_soft_delete);
    } catch (PDOException $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }

    $pending_approvals = (int)$statistics['pending'];
}

// View + Edit share some sidebar data
if ($page === 'view' || $page === 'edit') {
    try {
        $stmt = $conn->query("SELECT COUNT(*) as pending FROM students WHERE status = 'pending'");
        $pending_approvals = (int)($stmt->fetch(PDO::FETCH_ASSOC)['pending'] ?? 0);
    } catch (PDOException $e) {
        $pending_approvals = 0;
    }
}

// View-specific data
$student = null;
$course_history = [];

if ($page === 'view') {
    $page_title = 'View Student';
    $breadcrumb_items = [
        ['title' => 'Students', 'icon' => 'fas fa-users', 'url' => 'students.php?page=index'],
        ['title' => 'View Student', 'icon' => 'fas fa-eye']
    ];

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $error_message = 'Invalid student ID.';
    } else {
        $student_id = (int)$_GET['id'];
        try {
            $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
            $stmt->bindParam(':id', $student_id, PDO::PARAM_INT);
            $stmt->execute();
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$student) {
                $error_message = 'Student not found.';
            } else {
                $stmt = $conn->prepare("
                    SELECT ca.*, c.course_name, ca.reviewed_at, ca.applied_at
                    FROM course_applications ca
                    LEFT JOIN courses c ON ca.course_id = c.course_id
                    WHERE ca.student_id = :student_id
                    ORDER BY ca.applied_at DESC
                ");
                $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);
                $stmt->execute();
                $course_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Edit-specific data
$edit_errors = [];

if ($page === 'edit') {
    $page_title = 'Edit Student';
    $breadcrumb_items = [
        ['title' => 'Students', 'icon' => 'fas fa-users', 'url' => 'students.php?page=index'],
        ['title' => 'Edit Student', 'icon' => 'fas fa-edit']
    ];

    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        header('Location: students.php?page=index');
        exit;
    }

    $student_id = (int)$_GET['id'];

    // Load current student
    try {
        $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
        $stmt->bindParam(':id', $student_id, PDO::PARAM_INT);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            header('Location: students.php?page=index');
            exit;
        }
    } catch (PDOException $e) {
        $edit_errors[] = 'Database error: ' . $e->getMessage();
    }

    // Handle POST update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $errors = [];
        $required_fields = [
            'first_name', 'last_name', 'birthday', 'sex', 'civil_status',
            'country_code', 'contact_number', 'province', 'city', 'barangay', 'birth_province', 'birth_city',
            'guardian_first_name', 'guardian_last_name', 'parent_country_code', 'parent_contact',
            'email', 'uli', 'last_school', 'school_province', 'school_city',
        ];

        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
            }
        }

        if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }

        if (!empty($_POST['contact_number'])) {
            $phone_number = str_replace(' ', '', $_POST['contact_number']);
            if (!preg_match('/^\d{10}$/', $phone_number)) {
                $errors[] = 'Contact number must be exactly 10 digits';
            }
        }

        if (!empty($_POST['parent_contact'])) {
            $parent_phone = str_replace(' ', '', $_POST['parent_contact']);
            if (!preg_match('/^\d{10}$/', $parent_phone)) {
                $errors[] = 'Parent contact number must be exactly 10 digits';
            }
        }

        if (empty($_POST['country_code'])) {
            $errors[] = 'Country code is required for contact number';
        }
        if (empty($_POST['parent_country_code'])) {
            $errors[] = 'Country code is required for parent contact number';
        }

        $age = 0;
        if (!empty($_POST['birthday'])) {
            $birthday = new DateTime($_POST['birthday']);
            $today = new DateTime();
            $age = $today->diff($birthday)->y;
        }

        $profile_picture_path = $_POST['existing_profile_picture'] ?? ($student['profile_picture'] ?? '');

        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
            $file_type = $_FILES['profile_picture']['type'];
            $file_size = $_FILES['profile_picture']['size'];

            if (!in_array($file_type, $allowed_types, true)) {
                $errors[] = 'Profile picture must be JPG, JPEG, or PNG';
            } elseif ($file_size > 10 * 1024 * 1024) {
                $errors[] = 'Profile picture must be less than 10MB';
            } else {
                $upload_dir = '../uploads/profiles/';
                if (!is_dir($upload_dir)) {
                    @mkdir($upload_dir, 0755, true);
                }

                $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
                $filename = uniqid('', true) . '.' . $file_extension;
                $full_upload_path = $upload_dir . $filename;

                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $full_upload_path)) {
                    if (!empty($profile_picture_path)) {
                        $old_path = strpos($profile_picture_path, '../') === 0
                            ? $profile_picture_path
                            : '../' . $profile_picture_path;
                        if (file_exists($old_path)) {
                            @unlink($old_path);
                        }
                    }
                    $profile_picture_path = 'uploads/profiles/' . $filename;
                } else {
                    $errors[] = 'Failed to upload profile picture';
                }
            }
        }

        if (empty($errors)) {
            try {
                $full_contact_number = $_POST['country_code'] . $_POST['contact_number'];
                $full_parent_contact = $_POST['parent_country_code'] . $_POST['parent_contact'];

                $sql = "UPDATE students SET
                    first_name = :first_name, middle_name = :middle_name, last_name = :last_name, extension_name = :extension_name,
                    birthday = :birthday, age = :age, sex = :sex, civil_status = :civil_status,
                    contact_number = :contact_number, province = :province, city = :city,
                    barangay = :barangay, street_address = :street_address, birth_province = :birth_province, birth_city = :birth_city,
                    guardian_first_name = :guardian_first_name, guardian_middle_name = :guardian_middle_name,
                    guardian_last_name = :guardian_last_name, guardian_extension = :guardian_extension,
                    parent_contact = :parent_contact, email = :email, profile_picture = :profile_picture,
                    uli = :uli, last_school = :last_school, school_province = :school_province, school_city = :school_city
                    WHERE id = :id";

                $stmt = $conn->prepare($sql);
                $stmt->bindParam(':first_name', $_POST['first_name']);
                $stmt->bindParam(':middle_name', $_POST['middle_name']);
                $stmt->bindParam(':last_name', $_POST['last_name']);
                $stmt->bindParam(':extension_name', $_POST['extension_name']);
                $stmt->bindParam(':birthday', $_POST['birthday']);
                $stmt->bindParam(':age', $age, PDO::PARAM_INT);
                $stmt->bindParam(':sex', $_POST['sex']);
                $stmt->bindParam(':civil_status', $_POST['civil_status']);
                $stmt->bindParam(':contact_number', $full_contact_number);
                $stmt->bindParam(':province', $_POST['province']);
                $stmt->bindParam(':city', $_POST['city']);
                $stmt->bindParam(':barangay', $_POST['barangay']);
                $stmt->bindParam(':street_address', $_POST['street_address']);
                $stmt->bindParam(':birth_province', $_POST['birth_province']);
                $stmt->bindParam(':birth_city', $_POST['birth_city']);
                $stmt->bindParam(':guardian_first_name', $_POST['guardian_first_name']);
                $stmt->bindParam(':guardian_middle_name', $_POST['guardian_middle_name']);
                $stmt->bindParam(':guardian_last_name', $_POST['guardian_last_name']);
                $stmt->bindParam(':guardian_extension', $_POST['guardian_extension']);
                $stmt->bindParam(':parent_contact', $full_parent_contact);
                $stmt->bindParam(':email', $_POST['email']);
                $stmt->bindParam(':profile_picture', $profile_picture_path);
                $stmt->bindParam(':uli', $_POST['uli']);
                $stmt->bindParam(':last_school', $_POST['last_school']);
                $stmt->bindParam(':school_province', $_POST['school_province']);
                $stmt->bindParam(':school_city', $_POST['school_city']);
                $stmt->bindParam(':id', $student_id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    $logger->log(
                        'student_updated',
                        "Admin updated student information for '{$_POST['first_name']} {$_POST['last_name']}' (ID: {$student_id})",
                        'admin',
                        (int)$_SESSION['user_id'],
                        'student',
                        $student_id
                    );

                    $_SESSION['toast_message'] = 'Student information updated successfully!';
                    $_SESSION['toast_type'] = 'success';
                    header("Location: students.php?page=view&id=" . $student_id);
                    exit;
                } else {
                    $errors[] = 'Update failed. Please try again.';
                }
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $errors[] = 'Email or ULI already exists. Please use different values.';
                } else {
                    $errors[] = 'Database error: ' . $e->getMessage();
                }
            }
        }

        $edit_errors = $errors;
    }

    // Recompute parsed phone numbers for form
    $contact_country_code = '';
    $contact_phone_number = '';
    if (!empty($student['contact_number'])) {
        if (preg_match('/^(\+\d{1,4})(\d{10})$/', $student['contact_number'], $matches)) {
            $contact_country_code = $matches[1];
            $contact_phone_number = $matches[2];
        } else {
            $contact_phone_number = $student['contact_number'];
        }
    }

    $parent_country_code = '';
    $parent_phone_number = '';
    if (!empty($student['parent_contact'])) {
        if (preg_match('/^(\+\d{1,4})(\d{10})$/', $student['parent_contact'], $matches)) {
            $parent_country_code = $matches[1];
            $parent_phone_number = $matches[2];
        } else {
            $parent_phone_number = $student['parent_contact'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Jacobo Z. Gonzales Memorial School of Arts and Trades</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
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
    </script>
    <?php if ($page === 'edit'): ?>
        <?php include 'components/admin-styles.php'; ?>
        <style>
            /* Profile preview and upload placeholder (adapted to Tailwind-ready CSS) */
            .profile-preview {
                width: 8rem;
                height: 8rem;
                object-fit: cover;
                border-radius: 0.5rem;
                border-width: 2px;
                border-color: #e5e7eb;
                margin-bottom: 0.75rem;
            }
            .upload-placeholder {
                width: 8rem;
                height: 8rem;
                border-radius: 0.5rem;
                border-width: 2px;
                border-style: dashed;
                border-color: #d1d5db;
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: #f9fafb;
                margin-bottom: 0.75rem;
                color: #6b7280;
                font-size: 0.875rem;
            }
            /* Enhance form inputs */
            input:focus, select:focus, textarea:focus {
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            }
            /* Loading state for form submission */
            .form-loading {
                opacity: 0.6;
                pointer-events: none;
            }
            /* Success animation */
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            .animate-fade-in-up {
                animation: fadeInUp 0.3s ease-out;
            }
            /* Modal styles (from original edit design) */
            .modal-overlay {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                opacity: 0;
                transition: opacity 0.3s ease;
            }
            .modal-overlay.show {
                opacity: 1;
            }
            .modal-content {
                background: white;
                border-radius: 1rem;
                padding: 2rem;
                max-width: 500px;
                width: 90%;
                transform: scale(0.9);
                transition: transform 0.3s ease;
                box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1),
                            0 10px 10px -5px rgba(0, 0, 0, 0.04);
            }
            .modal-overlay.show .modal-content {
                transform: scale(1);
            }
            /* System Toast notification styles */
            #successNotification.show,
            #errorNotification.show {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        </style>
        <script src="../student/components/api-utils.js"></script>
    <?php endif; ?>
</head>
<body class="bg-gray-50">
    <?php include 'components/sidebar.php'; ?>

    <div id="main-content" class="min-h-screen transition-all duration-300 ease-in-out ml-0 md:ml-64">
        <?php include 'components/header.php'; ?>

        <main class="overflow-y-auto focus:outline-none">
            <?php if ($page === 'index'): ?>
                <div class="py-4 md:py-6">
                    <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                        <div class="mb-8 mt-6">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                                <div>
                                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Student Management</h1>
                                    <p class="text-lg text-gray-600 mt-2">View and manage student registrations and applications</p>
                                </div>
                            </div>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-circle mr-2"></i>
                                    <?php echo htmlspecialchars($error_message); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    <?php echo htmlspecialchars($success_message); ?>
                                </div>
                            </div>
                        <?php endif; ?>

                        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 md:p-8 mb-8">
                            <form method="GET" class="space-y-4">
                                <input type="hidden" name="page" value="index">
                                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                    <div>
                                        <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-search text-gray-400"></i>
                                            </div>
                                            <input type="text" id="search" name="search" placeholder="Student name, email, ID..."
                                                   value="<?php echo htmlspecialchars($search ?? ''); ?>"
                                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="filter_status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                        <select id="filter_status" name="filter_status"
                                                class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                            <option value="">All Status</option>
                                            <option value="pending" <?php echo (($filter_status ?? '') === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo (($filter_status ?? '') === 'approved') ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo (($filter_status ?? '') === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="filter_course" class="block text-sm font-medium text-gray-700 mb-2">Course</label>
                                        <select id="filter_course" name="filter_course"
                                                class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                            <option value="">All Courses</option>
                                            <?php foreach ($courses as $course): ?>
                                                <option value="<?php echo htmlspecialchars($course); ?>" <?php echo (($filter_course ?? '') == $course) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($course); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="start_date" class="block text-sm font-medium text-gray-700 mb-2">Start Date</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-calendar text-gray-400"></i>
                                            </div>
                                            <input type="date" id="start_date" name="start_date"
                                                   value="<?php echo htmlspecialchars($start_date ?? ''); ?>"
                                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="end_date" class="block text-sm font-medium text-gray-700 mb-2">End Date</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-calendar text-gray-400"></i>
                                            </div>
                                            <input type="date" id="end_date" name="end_date"
                                                   value="<?php echo htmlspecialchars($end_date ?? ''); ?>"
                                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                        </div>
                                    </div>
                                    <div class="flex items-end">
                                        <button type="submit"
                                                class="w-full inline-flex items-center justify-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                                            <i class="fas fa-filter mr-2"></i>Apply Filters
                                        </button>
                                    </div>
                                </div>
                                <div class="flex justify-start pt-4 border-t border-gray-200">
                                    <a href="students.php?page=index"
                                       class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
                                        <i class="fas fa-times mr-2"></i>Clear Filters
                                    </a>
                                </div>
                            </form>
                        </div>

                        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                            <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-4 sm:px-6 md:px-8 py-5 md:py-6 border-b border-gray-200">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-xl flex items-center justify-center mr-3 flex-shrink-0">
                                        <i class="fas fa-table text-white"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-base md:text-lg font-semibold text-gray-900">Student Records</h2>
                                        <p class="text-xs md:text-sm text-gray-600">
                                            Showing <?php echo count($students); ?> of <?php echo number_format($total_students ?? 0); ?> students
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <?php if (empty($students)): ?>
                                <div class="text-center py-16 px-4">
                                    <div class="w-20 h-20 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                                        <i class="fas fa-users text-gray-400 text-3xl"></i>
                                    </div>
                                    <h3 class="text-lg md:text-xl font-medium text-gray-900 mb-2">No students found</h3>
                                    <p class="text-sm md:text-base text-gray-600 mb-6 max-w-md mx-auto">No students match your current search criteria. Try adjusting your filters.</p>
                                    <a href="students.php?page=index" class="inline-flex items-center px-5 py-2.5 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-colors duration-200 shadow-sm">
                                        <i class="fas fa-refresh mr-2"></i>Clear Filters
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="hidden lg:block overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact Info</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Location</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registration</th>
                                                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($students as $row):
                                                $status_class = getStatusBadgeClass($row['status']);
                                                $initials = getStudentInitials($row['first_name'], $row['last_name']);
                                                $full_name = getStudentFullName($row);
                                            ?>
                                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="flex items-center">
                                                            <div class="ml-4">
                                                                <div class="text-sm font-medium text-gray-900"><?php echo $full_name; ?></div>
                                                                <div class="text-sm text-gray-500">ULI: <?php echo htmlspecialchars($row['uli']); ?></div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($row['email']); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['contact_number']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900"><?php echo htmlspecialchars($row['city']); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($row['province']); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                                            <?php echo ucfirst($row['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                        <div class="flex items-center justify-end space-x-2">
                                                            <a href="students.php?page=view&id=<?php echo (int)$row['id']; ?>"
                                                               class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-xs font-semibold rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                                                <i class="fas fa-eye mr-1"></i>
                                                            </a>
                                                            <a href="students.php?page=edit&id=<?php echo (int)$row['id']; ?>"
                                                               class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-semibold rounded-md text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
                                                                <i class="fas fa-edit mr-1"></i>
                                                            </a>
                                                            <button onclick="confirmStudentDelete(<?php echo (int)$row['id']; ?>, '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>')"
                                                                    class="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-semibold rounded-md text-red-700 bg-red-50 hover:bg-red-100 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200">
                                                                <i class="fas fa-trash mr-1"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="lg:hidden">
                                    <?php foreach ($students as $row):
                                        $status_class = getStatusBadgeClass($row['status']);
                                        $initials = getStudentInitials($row['first_name'], $row['last_name']);
                                        $full_name = getStudentFullName($row);
                                    ?>
                                        <div class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors duration-200">
                                            <div class="flex items-start justify-between">
                                                <div class="flex items-center flex-1 min-w-0">
                                                    <div class="flex-1 min-w-0">
                                                        <div class="text-sm font-medium text-gray-900 truncate"><?php echo $full_name; ?></div>
                                                        <div class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($row['email']); ?></div>
                                                        <div class="text-xs text-gray-400 mt-1">ULI: <?php echo htmlspecialchars($row['uli']); ?></div>
                                                    </div>
                                                </div>
                                                <div class="flex-shrink-0 ml-2">
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($row['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mt-3 grid grid-cols-2 gap-3 text-sm text-gray-600">
                                                <div>
                                                    <span class="font-medium">Location:</span>
                                                    <div><?php echo htmlspecialchars($row['city'] . ', ' . $row['province']); ?></div>
                                                </div>
                                                <div>
                                                    <span class="font-medium">Contact:</span>
                                                    <div><?php echo htmlspecialchars($row['contact_number']); ?></div>
                                                </div>
                                            </div>
                                            <div class="mt-3 text-xs text-gray-500">
                                                Registered: <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
                                            </div>
                                            <div class="flex items-center space-x-2 mt-3 pt-3 border-t border-gray-100">
                                                <a href="students.php?page=view&id=<?php echo (int)$row['id']; ?>"
                                                   class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-xs font-semibold rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors duration-200">
                                                    <i class="fas fa-eye mr-1"></i>View
                                                </a>
                                                <a href="students.php?page=edit&id=<?php echo (int)$row['id']; ?>"
                                                   class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-semibold rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
                                                    <i class="fas fa-edit mr-1"></i>Edit
                                                </a>
                                                <button onclick="confirmStudentDelete(<?php echo (int)$row['id']; ?>, '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>')"
                                                        class="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-semibold rounded-md text-red-700 bg-red-50 hover:bg-red-100 transition-colors duration-200">
                                                    <i class="fas fa-trash mr-1"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (($total_pages ?? 1) > 1): ?>
                                <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                                    <div class="flex items-center justify-between">
                                        <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                            <div>
                                                <p class="text-sm text-gray-700">
                                                    Showing <span class="font-medium"><?php echo (($p - 1) * ($limit ?? 10)) + 1; ?></span> to
                                                    <span class="font-medium"><?php echo min($p * ($limit ?? 10), $total_students); ?></span> of
                                                    <span class="font-medium"><?php echo $total_students; ?></span> results
                                                </p>
                                            </div>
                                            <div>
                                                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                                    <?php if ($p > 1): ?>
                                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['p' => $p - 1])); ?>"
                                                           class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                            <i class="fas fa-chevron-left"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php for ($i = max(1, $p - 2); $i <= min($total_pages, $p + 2); $i++): ?>
                                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['p' => $i])); ?>"
                                                           class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo ($i === $p) ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                                            <?php echo $i; ?>
                                                        </a>
                                                    <?php endfor; ?>
                                                    <?php if ($p < $total_pages): ?>
                                                        <a href="?<?php echo http_build_query(array_merge($_GET, ['p' => $p + 1])); ?>"
                                                           class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                            <i class="fas fa-chevron-right"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                </nav>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        const searchInput = document.getElementById('search');
                        if (!searchInput) return;
                        let searchTimeout;
                        searchInput.addEventListener('input', function() {
                            clearTimeout(searchTimeout);
                            const form = searchInput.closest('form');
                            searchTimeout = setTimeout(function() {
                                form.submit();
                            }, 500);
                        });
                    });

                    function confirmStudentDelete(studentId, studentName) {
                        const modal = document.createElement('div');
                        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
                        modal.innerHTML = `
                            <div class="bg-white rounded-2xl shadow-2xl p-6 sm:p-8 max-w-md mx-4 transform transition-all border border-gray-100">
                                <div class="flex items-center justify-center w-16 h-16 mx-auto bg-gradient-to-br from-red-100 to-red-200 rounded-full shadow-lg mb-6">
                                    <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 rounded-full flex items-center justify-center shadow-inner">
                                        <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                                    </div>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900 text-center mb-3">Delete Student</h3>
                                <div class="w-12 h-1 bg-gradient-to-r from-red-500 to-red-600 rounded-full mx-auto mb-6"></div>
                                <p class="text-base text-gray-600 text-center mb-4 leading-relaxed">
                                    Are you sure you want to delete <strong class="text-gray-900">${studentName}</strong>?
                                </p>
                                <p class="text-sm text-red-600 text-center mb-6 font-medium">
                                    Enter your admin password to confirm this action.
                                </p>
                                <form id="deleteForm" method="POST" action="students.php">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="${studentId}">
                                    <div class="mb-6">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Password</label>
                                        <input type="password" name="admin_password" required
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                               placeholder="Enter your password">
                                    </div>
                                    <div class="flex flex-col-reverse sm:flex-row gap-3">
                                        <button type="button" onclick="this.closest('.fixed').remove()"
                                                class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                            <i class="fas fa-times mr-2"></i>Cancel
                                        </button>
                                        <button type="submit"
                                                class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-semibold rounded-xl shadow-lg text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transform transition-all duration-200 hover:scale-105">
                                            <i class="fas fa-trash mr-2"></i>Delete
                                        </button>
                                    </div>
                                </form>
                            </div>
                        `;
                        document.body.appendChild(modal);
                        modal.addEventListener('click', function(e) {
                            if (e.target === modal) modal.remove();
                        });
                    }
                </script>
            <?php elseif ($page === 'view'): ?>
                <div class="py-4 md:py-6">
                    <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                        <?php if ($error_message): ?>
                            <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg animate-fade-in">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700"><?php echo htmlspecialchars($error_message); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="mb-6 md:mb-8">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                                    <div>
                                        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Student Profile</h1>
                                        <p class="text-lg text-gray-600 mt-2">Complete information for <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <a href="students.php?page=edit&id=<?php echo (int)$student['id']; ?>" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-semibold rounded-lg shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                            <i class="fas fa-edit mr-2"></i>Edit Student
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden mb-6 md:mb-8">
                                <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-6 md:px-8 py-8 md:py-12">
                                    <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                                        <div class="flex-shrink-0">
                                            <?php
                                            $profile_picture_url = '';
                                            $file_exists = false;
                                            if (!empty($student['profile_picture'])) {
                                                $stored_path = $student['profile_picture'];
                                                if (strpos($stored_path, '../') === 0) {
                                                    $profile_picture_url = $stored_path;
                                                } else {
                                                    $profile_picture_url = '../' . $stored_path;
                                                }
                                                $file_exists = file_exists($profile_picture_url);
                                            }
                                            ?>
                                            <?php if (!empty($student['profile_picture']) && $file_exists): ?>
                                                <div class="relative group">
                                                    <img src="<?php echo htmlspecialchars($profile_picture_url); ?>"
                                                         alt="Profile Picture"
                                                         class="w-24 h-24 md:w-32 md:h-32 rounded-full object-cover border-4 border-white shadow-lg"
                                                         onerror="this.parentElement.style.display='none'; this.parentElement.nextElementSibling.style.display='block';">
                                                    <div class="absolute -bottom-1 -right-1 bg-green-500 text-white p-1.5 rounded-full shadow-lg">
                                                        <i class="fas fa-check text-xs"></i>
                                                    </div>
                                                </div>
                                                <div class="relative group" style="display: none;">
                                                    <div class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-white bg-opacity-20 border-4 border-white shadow-lg flex items-center justify-center">
                                                        <div class="text-center">
                                                            <span class="text-2xl md:text-3xl font-bold text-white">
                                                                <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                    <div class="absolute -bottom-1 -right-1 bg-red-500 text-white p-1.5 rounded-full shadow-lg">
                                                        <i class="fas fa-exclamation-triangle text-xs"></i>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="relative group">
                                                    <div class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-white bg-opacity-20 border-4 border-white shadow-lg flex items-center justify-center">
                                                        <span class="text-2xl md:text-3xl font-bold text-white">
                                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                    <div class="absolute -bottom-1 -right-1 bg-gray-400 text-white p-1.5 rounded-full shadow-lg">
                                                        <i class="fas fa-camera text-xs"></i>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <div class="text-center md:text-left text-white flex-1">
                                            <h2 class="text-2xl md:text-3xl font-bold mb-2">
                                                <?php echo htmlspecialchars(trim($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name'])); ?>
                                                <?php if ($student['extension_name']): ?>
                                                    <?php echo htmlspecialchars($student['extension_name']); ?>
                                                <?php endif; ?>
                                            </h2>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-blue-100">
                                                <div class="flex items-center justify-center md:justify-start">
                                                    <i class="fas fa-id-card mr-2"></i>
                                                    <span>ULI: <?php echo htmlspecialchars($student['uli']); ?></span>
                                                </div>
                                                <div class="flex items-center justify-center md:justify-start">
                                                    <i class="fas fa-envelope mr-2"></i>
                                                    <span><?php echo htmlspecialchars($student['email']); ?></span>
                                                </div>
                                                <div class="flex items-center justify-center md:justify-start">
                                                    <i class="fas fa-phone mr-2"></i>
                                                    <span><?php echo htmlspecialchars($student['contact_number']); ?></span>
                                                </div>
                                                <div class="flex items-center justify-center md:justify-start">
                                                    <i class="fas fa-calendar mr-2"></i>
                                                    <span>Registered: <?php echo date('M j, Y', strtotime($student['created_at'])); ?></span>
                                                </div>
                                            </div>
                                            <div class="mt-4">
                                                <?php
                                                $status_class = '';
                                                $status_icon = '';
                                                switch ($student['status']) {
                                                    case 'completed':
                                                        $status_class = 'bg-green-100 text-green-800 border-green-200';
                                                        $status_icon = 'fas fa-graduation-cap';
                                                        break;
                                                    case 'rejected':
                                                        $status_class = 'bg-red-100 text-red-800 border-red-200';
                                                        $status_icon = 'fas fa-times-circle';
                                                        break;
                                                    default:
                                                        $status_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                                        $status_icon = 'fas fa-clock';
                                                }
                                                ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border <?php echo $status_class; ?>">
                                                    <i class="<?php echo $status_icon; ?> mr-2"></i>
                                                    <?php echo ucfirst($student['status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8">
                                    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-8">
                                        <div class="flex items-center mb-6">
                                            <div class="bg-gradient-to-br from-blue-900 to-blue-800 rounded-xl w-12 h-12 flex items-center justify-center mr-4 shadow-lg">
                                                <i class="fas fa-user text-white text-xl"></i>
                                            </div>
                                            <h3 class="text-xl font-bold text-gray-900">Personal Information</h3>
                                        </div>
                                        <div class="space-y-4">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1">First Name</label>
                                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['first_name']); ?></p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1">Middle Name</label>
                                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['middle_name'] ?: 'N/A'); ?></p>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1">Last Name</label>
                                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['last_name']); ?></p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1">Extension</label>
                                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['extension_name'] ?: 'N/A'); ?></p>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1">Birthday</label>
                                                    <p class="text-sm text-gray-900"><?php echo date('F j, Y', strtotime($student['birthday'])); ?></p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1">Age</label>
                                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['age']); ?> years old</p>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1">Gender</label>
                                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['sex']); ?></p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1">Civil Status</label>
                                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['civil_status']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-8">
                                        <div class="flex items-center mb-6">
                                            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl w-12 h-12 flex items-center justify-center mr-4 shadow-lg">
                                                <i class="fas fa-map-marker-alt text-green-600 text-xl"></i>
                                            </div>
                                            <h3 class="text-xl font-bold text-gray-900">Address Information</h3>
                                        </div>
                                        <div class="space-y-4">
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1"><i class="fas fa-map text-green-600 mr-1"></i>Province</label>
                                                    <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($student['province']); ?></p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1"><i class="fas fa-city text-green-600 mr-1"></i>City/Municipality</label>
                                                    <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($student['city']); ?></p>
                                                </div>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1"><i class="fas fa-home text-green-600 mr-1"></i>Barangay</label>
                                                    <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($student['barangay']); ?></p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1"><i class="fas fa-road text-gray-400 mr-1"></i>Street / Subdivision</label>
                                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['street_address'] ?: 'N/A'); ?></p>
                                                </div>
                                            </div>
                                            <div class="pt-4 border-t border-gray-200">
                                                <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                                    <i class="fas fa-baby text-green-600 mr-2"></i>Place of Birth
                                                </h4>
                                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-500 mb-1"><i class="fas fa-map text-green-600 mr-1"></i>Province</label>
                                                        <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($student['birth_province'] ?: 'N/A'); ?></p>
                                                    </div>
                                                    <div>
                                                        <label class="block text-xs font-medium text-gray-500 mb-1"><i class="fas fa-city text-green-600 mr-1"></i>City/Municipality</label>
                                                        <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($student['birth_city'] ?: 'N/A'); ?></p>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-8">
                                        <div class="flex items-center mb-6">
                                            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl w-12 h-12 flex items-center justify-center mr-4 shadow-lg">
                                                <i class="fas fa-users text-purple-600 text-xl"></i>
                                            </div>
                                            <h3 class="text-xl font-bold text-gray-900">Guardian Information</h3>
                                        </div>
                                        <div class="space-y-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Guardian Name</label>
                                                <p class="text-sm text-gray-900">
                                                    <?php
                                                    $guardian_name = trim(($student['guardian_first_name'] ?? '') . ' ' . ($student['guardian_middle_name'] ?? '') . ' ' . ($student['guardian_last_name'] ?? ''));
                                                    echo htmlspecialchars($guardian_name ?: 'N/A');
                                                    ?>
                                                    <?php if ($student['guardian_extension'] ?? ''): ?>
                                                        <?php echo htmlspecialchars($student['guardian_extension']); ?>
                                                    <?php endif; ?>
                                                </p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Contact Number</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['parent_contact']); ?></p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-8">
                                        <div class="flex items-center mb-6">
                                            <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl w-12 h-12 flex items-center justify-center mr-4 shadow-lg">
                                                <i class="fas fa-graduation-cap text-orange-600 text-xl"></i>
                                            </div>
                                            <h3 class="text-xl font-bold text-gray-900">Education Information</h3>
                                        </div>
                                        <div class="space-y-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Last School Attended</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['last_school']); ?></p>
                                            </div>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1"><i class="fas fa-map text-orange-600 mr-1"></i>School Province</label>
                                                    <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($student['school_province']); ?></p>
                                                </div>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1"><i class="fas fa-city text-orange-600 mr-1"></i>School City/Municipality</label>
                                                    <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($student['school_city']); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <?php if ($student['status'] === 'completed' && ($student['course'] || $student['nc_level'] || $student['adviser'])): ?>
                                    <div class="mt-6 md:mt-8 bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-8">
                                        <div class="flex items-center mb-6">
                                            <div class="bg-gradient-to-br from-blue-900 to-blue-800 rounded-xl w-12 h-12 flex items-center justify-center mr-4 shadow-lg">
                                                <i class="fas fa-book text-white text-xl"></i>
                                            </div>
                                            <h3 class="text-xl font-bold text-gray-900">Course Information</h3>
                                        </div>
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                                            <?php if ($student['course']): ?>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1">Course</label>
                                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['course']); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($student['nc_level']): ?>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1">NC Level</label>
                                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['nc_level']); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($student['training_start']): ?>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1">Training Start</label>
                                                    <p class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($student['training_start'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($student['training_end']): ?>
                                                <div>
                                                    <label class="block text-sm font-medium text-gray-500 mb-1">Training End</label>
                                                    <p class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($student['training_end'])); ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($student['adviser']): ?>
                                            <div class="mt-4 pt-4 border-t border-gray-200">
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Assigned Adviser</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['adviser']); ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (!empty($course_history)): ?>
                                    <div class="mt-6 md:mt-8 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                                        <div class="bg-gradient-to-r from-blue-900 to-blue-800 px-6 md:px-8 py-5 border-b border-blue-700">
                                            <div class="flex items-center justify-between">
                                                <div class="flex items-center">
                                                    <div class="bg-white bg-opacity-20 rounded-xl w-12 h-12 flex items-center justify-center mr-4 backdrop-blur-sm shadow-lg">
                                                        <i class="fas fa-history text-white text-xl"></i>
                                                    </div>
                                                    <div>
                                                        <h3 class="text-xl font-bold text-white">Course History</h3>
                                                        <p class="text-sm text-blue-100 mt-1">Complete record of all course applications</p>
                                                    </div>
                                                </div>
                                                <span class="bg-white text-blue-900 text-sm font-bold px-4 py-2 rounded-full shadow-lg">
                                                    <?php echo count($course_history); ?> Course<?php echo count($course_history) > 1 ? 's' : ''; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="p-6 md:p-8">
                                            <div class="hidden md:block overflow-x-auto">
                                                <table class="min-w-full divide-y divide-gray-200">
                                                    <thead class="bg-gray-50">
                                                        <tr>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">NC Level</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adviser</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Training Period</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applied Date</th>
                                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="bg-white divide-y divide-gray-200">
                                                        <?php foreach ($course_history as $course): ?>
                                                            <tr class="hover:bg-gray-50 transition-colors duration-200">
                                                                <td class="px-6 py-4">
                                                                    <div class="flex items-center">
                                                                        <div class="w-8 h-8 bg-blue-900 bg-opacity-10 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                                                            <i class="fas fa-book text-blue-900 text-sm"></i>
                                                                        </div>
                                                                        <div class="text-sm font-medium text-gray-900">
                                                                            <?php echo htmlspecialchars($course['course_name'] ?? 'N/A'); ?>
                                                                        </div>
                                                                    </div>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap">
                                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                        <?php echo htmlspecialchars($course['nc_level'] ?? 'N/A'); ?>
                                                                    </span>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    <?php echo htmlspecialchars($course['adviser'] ?? 'Not Assigned'); ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                                                    <?php if ($course['training_start'] && $course['training_end']): ?>
                                                                        <div class="flex flex-col">
                                                                            <span class="text-xs text-gray-500">Start: <?php echo date('M j, Y', strtotime($course['training_start'])); ?></span>
                                                                            <span class="text-xs text-gray-500">End: <?php echo date('M j, Y', strtotime($course['training_end'])); ?></span>
                                                                        </div>
                                                                    <?php else: ?>
                                                                        <span class="text-gray-400">Not Set</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                                    <?php echo date('M j, Y', strtotime($course['applied_at'])); ?>
                                                                </td>
                                                                <td class="px-6 py-4 whitespace-nowrap">
                                                                    <?php
                                                                    $status_class = '';
                                                                    $status_icon = '';
                                                                    switch ($course['status']) {
                                                                        case 'completed':
                                                                            $status_class = 'bg-green-100 text-green-800';
                                                                            $status_icon = 'fas fa-check-circle';
                                                                            break;
                                                                        case 'approved':
                                                                            $status_class = 'bg-blue-100 text-blue-800';
                                                                            $status_icon = 'fas fa-thumbs-up';
                                                                            break;
                                                                        case 'rejected':
                                                                            $status_class = 'bg-red-100 text-red-800';
                                                                            $status_icon = 'fas fa-times-circle';
                                                                            break;
                                                                        default:
                                                                            $status_class = 'bg-yellow-100 text-yellow-800';
                                                                            $status_icon = 'fas fa-clock';
                                                                    }
                                                                    ?>
                                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                                                                        <i class="<?php echo $status_icon; ?> mr-1"></i>
                                                                        <?php echo ucfirst($course['status']); ?>
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                            <div class="md:hidden space-y-4">
                                                <?php foreach ($course_history as $course): ?>
                                                    <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                                                        <div class="flex items-start justify-between mb-3">
                                                            <div class="flex items-center flex-1">
                                                                <div class="w-10 h-10 bg-blue-900 bg-opacity-10 rounded-lg flex items-center justify-center mr-3 flex-shrink-0">
                                                                    <i class="fas fa-book text-blue-900"></i>
                                                                </div>
                                                                <div class="flex-1 min-w-0">
                                                                    <h4 class="text-sm font-semibold text-gray-900 truncate">
                                                                        <?php echo htmlspecialchars($course['course_name'] ?? 'N/A'); ?>
                                                                    </h4>
                                                                    <p class="text-xs text-gray-500">
                                                                        Applied: <?php echo date('M j, Y', strtotime($course['applied_at'])); ?>
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <?php
                                                            $status_class = '';
                                                            $status_icon = '';
                                                            switch ($course['status']) {
                                                                case 'completed':
                                                                    $status_class = 'bg-green-100 text-green-800';
                                                                    $status_icon = 'fas fa-check-circle';
                                                                    break;
                                                                case 'approved':
                                                                    $status_class = 'bg-blue-100 text-blue-800';
                                                                    $status_icon = 'fas fa-thumbs-up';
                                                                    break;
                                                                case 'rejected':
                                                                    $status_class = 'bg-red-100 text-red-800';
                                                                    $status_icon = 'fas fa-times-circle';
                                                                    break;
                                                                default:
                                                                    $status_class = 'bg-yellow-100 text-yellow-800';
                                                                    $status_icon = 'fas fa-clock';
                                                            }
                                                            ?>
                                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $status_class; ?> flex-shrink-0 ml-2">
                                                                <i class="<?php echo $status_icon; ?> mr-1"></i>
                                                                <?php echo ucfirst($course['status']); ?>
                                                            </span>
                                                        </div>
                                                        <div class="space-y-2 text-sm">
                                                            <div class="flex items-center">
                                                                <span class="text-gray-500 w-24">NC Level:</span>
                                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                                    <?php echo htmlspecialchars($course['nc_level'] ?? 'N/A'); ?>
                                                                </span>
                                                            </div>
                                                            <div class="flex items-center">
                                                                <span class="text-gray-500 w-24">Adviser:</span>
                                                                <span class="text-gray-900"><?php echo htmlspecialchars($course['adviser'] ?? 'Not Assigned'); ?></span>
                                                            </div>
                                                            <?php if ($course['training_start'] && $course['training_end']): ?>
                                                                <div class="flex items-center">
                                                                    <span class="text-gray-500 w-24">Training:</span>
                                                                    <span class="text-gray-900 text-xs">
                                                                        <?php echo date('M j, Y', strtotime($course['training_start'])); ?> -
                                                                        <?php echo date('M j, Y', strtotime($course['training_end'])); ?>
                                                                    </span>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="mt-6 md:mt-8 bg-white rounded-2xl shadow-xl border border-gray-100 p-12">
                                        <div class="text-center">
                                            <div class="bg-gradient-to-br from-gray-50 to-gray-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6 shadow-lg">
                                                <i class="fas fa-history text-gray-400 text-3xl"></i>
                                            </div>
                                            <h3 class="text-2xl font-bold text-gray-900 mb-3">No Course History</h3>
                                            <p class="text-lg text-gray-600 px-4 max-w-md mx-auto">This student hasn't applied for any courses yet.</p>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <div class="mt-8 md:mt-12 flex justify-center pb-8">
                                    <button onclick="confirmViewDelete(<?php echo (int)$student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')"
                                            class="inline-flex items-center justify-center px-8 py-4 border border-transparent text-base font-semibold rounded-xl shadow-lg text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transform transition-all duration-200 hover:scale-105">
                                        <i class="fas fa-trash mr-2"></i>Delete Student
                                    </button>
                                </div>
                            <?php endif; ?>
                    </div>
                </div>

                <script>
                    function confirmViewDelete(studentId, studentName) {
                        const modal = document.createElement('div');
                        modal.className = 'fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 transition-all duration-300';
                        modal.innerHTML = `
                            <div class="bg-white rounded-2xl shadow-2xl p-6 sm:p-8 max-w-md mx-4 transform transition-all border border-gray-100">
                                <div class="flex items-center justify-center w-16 h-16 mx-auto bg-gradient-to-br from-red-100 to-red-200 rounded-full shadow-lg mb-6">
                                    <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 rounded-full flex items-center justify-center shadow-inner">
                                        <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                                    </div>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900 text-center mb-3">Delete Student</h3>
                                <div class="w-12 h-1 bg-gradient-to-r from-red-500 to-red-600 rounded-full mx-auto mb-6"></div>
                                <p class="text-base text-gray-600 text-center mb-4 leading-relaxed">
                                    Are you sure you want to delete <strong class="text-gray-900">${studentName}</strong>?
                                </p>
                                <p class="text-sm text-red-600 text-center mb-6 font-medium">
                                    Enter your admin password to confirm this action.
                                </p>
                                <form id="deleteForm" method="POST" action="students.php">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="${studentId}">
                                    <div class="mb-6">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Password</label>
                                        <input type="password" name="admin_password" required
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                               placeholder="Enter your password" autofocus>
                                    </div>
                                    <div class="flex flex-col-reverse sm:flex-row gap-3">
                                        <button type="button" onclick="this.closest('.fixed').remove()"
                                                class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                            <i class="fas fa-times mr-2"></i>Cancel
                                        </button>
                                        <button type="submit"
                                                class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-semibold rounded-xl shadow-lg text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transform transition-all duration-200 hover:scale-105">
                                            <i class="fas fa-trash mr-2"></i>Delete
                                        </button>
                                    </div>
                                </form>
                            </div>
                        `;
                        document.body.appendChild(modal);
                        modal.addEventListener('click', function(e) {
                            if (e.target === modal) modal.remove();
                        });
                    }
                </script>
            <?php elseif ($page === 'edit'): ?>
                <!-- Success Notification Toast (from original design) -->
                <div id="successNotification" class="hidden fixed top-4 left-1/2 transform -translate-x-1/2 z-50 transition-all duration-300 opacity-0 -translate-y-5">
                    <div class="bg-gradient-to-r from-green-600 to-green-700 text-white px-6 py-4 rounded-lg shadow-2xl border border-green-500 max-w-md">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <i class="fas fa-check-circle text-white text-lg"></i>
                                </div>
                            </div>
                            <div class="flex-1">
                                <p class="font-semibold text-sm mb-1">Success!</p>
                                <p class="text-xs text-green-100" id="successMessage">
                                    Student information updated successfully!
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Error Notification Toast (from original design) -->
                <div id="errorNotification" class="hidden fixed top-4 left-1/2 transform -translate-x-1/2 z-50 transition-all duration-300 opacity-0 -translate-y-5">
                    <div class="bg-gradient-to-r from-red-600 to-red-700 text-white px-6 py-4 rounded-lg shadow-2xl border border-red-500 max-w-md">
                        <div class="flex items-center space-x-3">
                            <div class="flex-shrink-0">
                                <div class="w-10 h-10 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                                    <i class="fas fa-exclamation-circle text-white text-lg"></i>
                                </div>
                            </div>
                            <div class="flex-1">
                                <p class="font-medium text-sm" id="errorMessage">
                                    An error occurred
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Confirmation Modal (from original design) -->
                <div id="confirmModal" class="modal-overlay" style="display: none;">
                    <div class="modal-content">
                        <div class="text-center mb-6">
                            <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-blue-100 mb-4">
                                <i class="fas fa-question-circle text-blue-600 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2" id="modalTitle">Confirm Action</h3>
                            <p class="text-sm text-gray-600" id="modalMessage">Are you sure you want to proceed?</p>
                        </div>
                        <div class="flex gap-3 justify-center">
                            <button id="modalCancel" class="px-6 py-2.5 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 font-medium transition-colors">
                                Cancel
                            </button>
                            <button id="modalConfirm" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium transition-colors">
                                Confirm
                            </button>
                        </div>
                    </div>
                </div>

                <div class="py-4 md:py-6">
                    <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                        <!-- Page Header (gradient card from original design) -->
                        <div class="mb-8 mt-6">
                            <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-2xl p-6 md:p-8 text-white shadow-xl">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                    <div class="mb-4 md:mb-0">
                                        <div class="flex items-center mb-3">
                                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mr-4">
                                                <i class="fas fa-edit text-2xl text-white"></i>
                                            </div>
                                            <div>
                                                <h1 class="text-3xl md:text-4xl font-bold mb-1">Edit Student</h1>
                                                <p class="text-blue-100 text-lg">Update student information and details</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alerts -->
                        <?php if (!empty($edit_errors)): ?>
                            <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg animate-fade-in-up">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h4 class="text-sm font-medium text-red-800 mb-2">Please fix the following errors:</h4>
                                        <ul class="list-disc list-inside space-y-1 text-sm text-red-700">
                                            <?php foreach ($edit_errors as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($student): ?>
                            <form id="edit-form"
                                  method="POST"
                                  action="students.php?page=edit&id=<?php echo (int)$student['id']; ?>"
                                  enctype="multipart/form-data"
                                  class="space-y-8">
                                <input type="hidden" name="existing_profile_picture" value="<?php echo htmlspecialchars($student['profile_picture'] ?? ''); ?>">

                                <!-- Personal Information (from original design) -->
                                <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                    <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-blue-100 rounded-xl p-2">
                                                <i class="fas fa-user text-blue-600"></i>
                                            </div>
                                            <h3 class="text-xl font-bold text-gray-900">Personal Information</h3>
                                        </div>
                                    </div>
                                    <div class="p-6 md:p-8">
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                            <div>
                                                <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                                                <input type="text" id="first_name" name="first_name" required
                                                       value="<?php echo htmlspecialchars($student['first_name']); ?>"
                                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                            </div>
                                            <div>
                                                <label for="middle_name" class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                                                <input type="text" id="middle_name" name="middle_name"
                                                       value="<?php echo htmlspecialchars($student['middle_name']); ?>"
                                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                            </div>
                                            <div>
                                                <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                                                <input type="text" id="last_name" name="last_name" required
                                                       value="<?php echo htmlspecialchars($student['last_name']); ?>"
                                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
                                            <div>
                                                <label for="extension_name" class="block text-sm font-medium text-gray-700 mb-2">Extension Name</label>
                                                <input type="text" id="extension_name" name="extension_name"
                                                       placeholder="Jr., Sr., III, etc."
                                                       value="<?php echo htmlspecialchars($student['extension_name']); ?>"
                                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                            </div>
                                            <div>
                                                <label for="birthday" class="block text-sm font-medium text-gray-700 mb-2">Birthday *</label>
                                                <input type="date" id="birthday" name="birthday" required
                                                       value="<?php echo htmlspecialchars($student['birthday']); ?>"
                                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                            </div>
                                            <div>
                                                <label for="age" class="block text-sm font-medium text-gray-700 mb-2">Age</label>
                                                <input type="number" id="age" name="age" readonly value="<?php echo htmlspecialchars($student['age']); ?>"
                                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-500 text-sm shadow-sm">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
                                            <div>
                                                <label for="sex" class="block text-sm font-medium text-gray-700 mb-2">Sex *</label>
                                                <select id="sex" name="sex" required
                                                        class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                                    <option value="">Select Sex</option>
                                                    <option value="Male" <?php echo ($student['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                                    <option value="Female" <?php echo ($student['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                                    <option value="Other" <?php echo ($student['sex'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label for="civil_status" class="block text-sm font-medium text-gray-700 mb-2">Civil Status *</label>
                                                <select id="civil_status" name="civil_status" required
                                                        class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                                    <option value="">Select Civil Status</option>
                                                    <option value="Single" <?php echo ($student['civil_status'] === 'Single') ? 'selected' : ''; ?>>Single</option>
                                                    <option value="Married" <?php echo ($student['civil_status'] === 'Married') ? 'selected' : ''; ?>>Married</option>
                                                    <option value="Divorced" <?php echo ($student['civil_status'] === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                                    <option value="Widowed" <?php echo ($student['civil_status'] === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label for="contact_number" class="block text-sm font-medium text-gray-700 mb-2">
                                                    <i class="fas fa-phone text-blue-500 mr-2"></i>Contact Number *
                                                </label>
                                                <div class="flex flex-col sm:flex-row gap-2">
                                                    <select id="country_code" name="country_code"
                                                            class="w-full sm:w-24 px-2 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 hover:border-gray-300 bg-gray-50 text-sm shadow-sm"
                                                            data-selected="<?php echo htmlspecialchars($contact_country_code ?? ''); ?>">
                                                        <option value="">Code</option>
                                                    </select>
                                                    <input type="tel" id="contact_number" name="contact_number" required
                                                           class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 hover:border-gray-300 text-sm shadow-sm"
                                                           placeholder="9123456789"
                                                           pattern="[0-9]{10}"
                                                           maxlength="10"
                                                           inputmode="numeric"
                                                           value="<?php echo htmlspecialchars($contact_phone_number ?? ''); ?>">
                                                </div>
                                                <p class="text-xs text-gray-500 mt-2 flex items-center">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Select country code and enter 10-digit phone number (e.g., 9123456789)
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Address Information (from original design, with dynamic selects) -->
                                <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                    <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-green-100 rounded-xl p-2">
                                                <i class="fas fa-map-marker-alt text-green-600"></i>
                                            </div>
                                            <h3 class="text-xl font-bold text-gray-900">Address Information</h3>
                                        </div>
                                    </div>
                                    <div class="p-6 md:p-8">
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                            <div>
                                                <label for="province" class="block text-sm font-medium text-gray-700 mb-2">
                                                    <i class="fas fa-map text-green-600 mr-1"></i>Province *
                                                </label>
                                                <select id="province" name="province" required
                                                        class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm"
                                                        data-selected="<?php echo htmlspecialchars($student['province']); ?>">
                                                    <option value="">Loading provinces...</option>
                                                </select>
                                                <div id="province-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                                    <i class="fas fa-spinner fa-spin mr-2"></i>Loading provinces...
                                                </div>
                                            </div>
                                            <div>
                                                <label for="city" class="block text-sm font-medium text-gray-700 mb-2">
                                                    <i class="fas fa-city text-green-600 mr-1"></i>City/Municipality *
                                                </label>
                                                <select id="city" name="city" required
                                                        class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm"
                                                        data-selected="<?php echo htmlspecialchars($student['city']); ?>">
                                                    <option value="">Select city/municipality</option>
                                                </select>
                                                <div id="city-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                                    <i class="fas fa-spinner fa-spin mr-2"></i>Loading cities...
                                                </div>
                                            </div>
                                            <div>
                                                <label for="barangay" class="block text-sm font-medium text-gray-700 mb-2">
                                                    <i class="fas fa-home text-green-600 mr-1"></i>Barangay *
                                                </label>
                                                <select id="barangay" name="barangay" required
                                                        class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm"
                                                        data-selected="<?php echo htmlspecialchars($student['barangay']); ?>">
                                                    <option value="">Select barangay</option>
                                                </select>
                                                <div id="barangay-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                                    <i class="fas fa-spinner fa-spin mr-2"></i>Loading barangays...
                                                </div>
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                            <div>
                                                <label for="street_address" class="block text-sm font-medium text-gray-700 mb-2">
                                                    <i class="fas fa-road text-gray-400 mr-1"></i>Street / Subdivision
                                                </label>
                                                <input type="text" id="street_address" name="street_address"
                                                       value="<?php echo htmlspecialchars($student['street_address']); ?>"
                                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                            </div>
                                        </div>

                                        <div class="mt-6">
                                            <h4 class="text-sm font-semibold text-gray-700 mb-4 flex items-center">
                                                <i class="fas fa-baby text-green-600 mr-2"></i>Place of Birth
                                            </h4>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                                <div>
                                                    <label for="birth_province" class="block text-sm font-medium text-gray-700 mb-2">
                                                        <i class="fas fa-map text-green-600 mr-1"></i>Province *
                                                    </label>
                                                    <select id="birth_province" name="birth_province" required
                                                            class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm"
                                                            data-selected="<?php echo htmlspecialchars($student['birth_province'] ?? ''); ?>">
                                                        <option value="">Loading provinces...</option>
                                                    </select>
                                                    <div id="birth_province-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                                        <i class="fas fa-spinner fa-spin mr-2"></i>Loading provinces...
                                                    </div>
                                                </div>
                                                <div>
                                                    <label for="birth_city" class="block text-sm font-medium text-gray-700 mb-2">
                                                        <i class="fas fa-city text-green-600 mr-1"></i>City/Municipality *
                                                    </label>
                                                    <select id="birth_city" name="birth_city" required
                                                            class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm"
                                                            data-selected="<?php echo htmlspecialchars($student['birth_city'] ?? ''); ?>">
                                                        <option value="">Select city/municipality</option>
                                                    </select>
                                                    <div id="birth_city-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                                        <i class="fas fa-spinner fa-spin mr-2"></i>Loading cities...
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Parent/Guardian Information (from original design) -->
                                <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                    <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-purple-100 rounded-xl p-2">
                                                <i class="fas fa-users text-purple-600"></i>
                                            </div>
                                            <h3 class="text-xl font-bold text-gray-900">Parent/Guardian Information</h3>
                                        </div>
                                    </div>
                                    <div class="p-6 md:p-8">
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                            <div>
                                                <label for="guardian_first_name" class="block text-sm font-medium text-gray-700 mb-2">Guardian First Name *</label>
                                                <input type="text" id="guardian_first_name" name="guardian_first_name" required
                                                       value="<?php echo htmlspecialchars($student['guardian_first_name']); ?>"
                                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                            </div>
                                            <div>
                                                <label for="guardian_middle_name" class="block text-sm font-medium text-gray-700 mb-2">Guardian Middle Name</label>
                                                <input type="text" id="guardian_middle_name" name="guardian_middle_name"
                                                       value="<?php echo htmlspecialchars($student['guardian_middle_name']); ?>"
                                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                            </div>
                                            <div>
                                                <label for="guardian_last_name" class="block text-sm font-medium text-gray-700 mb-2">Guardian Last Name *</label>
                                                <input type="text" id="guardian_last_name" name="guardian_last_name" required
                                                       value="<?php echo htmlspecialchars($student['guardian_last_name']); ?>"
                                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                            </div>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                                            <div>
                                                <label for="guardian_extension" class="block text-sm font-medium text-gray-700 mb-2">Guardian Extension</label>
                                                <input type="text" id="guardian_extension" name="guardian_extension"
                                                       placeholder="Jr., Sr., III, etc."
                                                       value="<?php echo htmlspecialchars($student['guardian_extension']); ?>"
                                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                            </div>
                                            <div>
                                                <label for="parent_contact" class="block text-sm font-medium text-gray-700 mb-2">
                                                    <i class="fas fa-phone text-blue-500 mr-2"></i>Guardian Contact Number *
                                                </label>
                                                <div class="flex flex-col sm:flex-row gap-2">
                                                    <select id="parent_country_code" name="parent_country_code"
                                                            class="w-full sm:w-24 px-2 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 hover:border-gray-300 bg-gray-50 text-sm shadow-sm"
                                                            data-selected="<?php echo htmlspecialchars($parent_country_code ?? ''); ?>">
                                                        <option value="">Code</option>
                                                    </select>
                                                    <input type="tel" id="parent_contact" name="parent_contact" required
                                                           class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 hover:border-gray-300 text-sm shadow-sm"
                                                           placeholder="9123456789"
                                                           pattern="[0-9]{10}"
                                                           maxlength="10"
                                                           inputmode="numeric"
                                                           value="<?php echo htmlspecialchars($parent_phone_number ?? ''); ?>">
                                                </div>
                                                <p class="text-xs text-gray-500 mt-2 flex items-center">
                                                    <i class="fas fa-info-circle mr-1"></i>
                                                    Select country code and enter 10-digit phone number (e.g., 9123456789)
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Education & Other Information (from original design) -->
                                <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                    <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-indigo-100 rounded-xl p-2">
                                                <i class="fas fa-graduation-cap text-indigo-600"></i>
                                            </div>
                                            <h3 class="text-xl font-bold text-gray-900">Education & Other Information</h3>
                                        </div>
                                    </div>
                                    <div class="p-6 md:p-8">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                            <div>
                                                <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                                                <input type="email" id="email" name="email" required
                                                       value="<?php echo htmlspecialchars($student['email']); ?>"
                                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                            </div>
                                            <div>
                                                <label for="uli" class="block text-sm font-medium text-gray-700 mb-2">ULI (Unique Learner Identifier) *</label>
                                                <input type="text" id="uli" name="uli" required
                                                       value="<?php echo htmlspecialchars($student['uli']); ?>"
                                                       class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                            </div>
                                        </div>

                                        <div class="mb-6">
                                            <label for="last_school" class="block text-sm font-medium text-gray-700 mb-2">Last School Attended (Full Name, no abbreviations) *</label>
                                            <input type="text" id="last_school" name="last_school" required
                                                   placeholder="e.g., University of the Philippines Diliman"
                                                   value="<?php echo htmlspecialchars($student['last_school']); ?>"
                                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label for="school_province" class="block text-sm font-medium text-gray-700 mb-2">
                                                    <i class="fas fa-map text-indigo-600 mr-1"></i>School Province *
                                                </label>
                                                <select id="school_province" name="school_province" required
                                                        class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm"
                                                        data-selected="<?php echo htmlspecialchars($student['school_province']); ?>">
                                                    <option value="">Loading provinces...</option>
                                                </select>
                                                <div id="school_province-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                                    <i class="fas fa-spinner fa-spin mr-2"></i>Loading provinces...
                                                </div>
                                            </div>
                                            <div>
                                                <label for="school_city" class="block text-sm font-medium text-gray-700 mb-2">
                                                    <i class="fas fa-city text-indigo-600 mr-1"></i>School City/Municipality *
                                                </label>
                                                <select id="school_city" name="school_city" required
                                                        class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm"
                                                        data-selected="<?php echo htmlspecialchars($student['school_city']); ?>">
                                                    <option value="">Select school city/municipality</option>
                                                </select>
                                                <div id="school_city-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                                    <i class="fas fa-spinner fa-spin mr-2"></i>Loading cities...
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Profile picture (from original design, adapted path) -->
                                        <div class="mt-8">
                                            <label for="profile_picture" class="block text-sm font-medium text-gray-700 mb-2">Profile Picture</label>
                                            <?php
                                            $current_profile_url = '';
                                            $file_exists = false;
                                            if (!empty($student['profile_picture'])) {
                                                $stored_path = $student['profile_picture'];
                                                if (strpos($stored_path, '../') === 0) {
                                                    $current_profile_url = $stored_path;
                                                } else {
                                                    $current_profile_url = '../' . $stored_path;
                                                }
                                                $file_exists = file_exists($current_profile_url);
                                            }
                                            ?>
                                            <div class="flex items-start space-x-4">
                                                <div class="flex-shrink-0">
                                                    <?php if (!empty($student['profile_picture']) && $file_exists): ?>
                                                        <img id="current-profile" src="<?php echo htmlspecialchars($current_profile_url); ?>"
                                                             class="profile-preview" alt="Current Profile Picture">
                                                    <?php else: ?>
                                                        <div id="upload-placeholder" class="upload-placeholder">
                                                            <i class="fas fa-camera text-2xl"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                    <img id="profile-preview" class="profile-preview" style="display: none;">
                                                </div>
                                                <div class="flex-1">
                                                    <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/jpg,image/png"
                                                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all duration-200">
                                                    <p class="mt-2 text-sm text-gray-500">
                                                        Maximum file size: 10MB. Accepted formats: JPG, JPEG, PNG. Leave empty to keep current photo.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Action Buttons (from original design, routing adapted) -->
                                <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                    <div class="p-6 md:p-8">
                                        <div class="flex flex-col sm:flex-row gap-4 justify-center">
                                            <button type="submit" id="submit-btn"
                                                    class="inline-flex items-center justify-center px-8 py-4 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                                                <i class="fas fa-save mr-2"></i>Update Student Information
                                            </button>
                                            <a href="students.php?page=view&id=<?php echo (int)$student['id']; ?>" id="cancel-btn"
                                               class="inline-flex items-center justify-center px-8 py-4 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200 shadow-sm hover:shadow-md">
                                                <i class="fas fa-times mr-2"></i>Cancel Changes
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>

                <script>
                    // Modal and Toast Functions (from original design)
                    function showModal(title, message, onConfirm) {
                        const modal = document.getElementById('confirmModal');
                        const modalTitle = document.getElementById('modalTitle');
                        const modalMessage = document.getElementById('modalMessage');
                        const modalConfirm = document.getElementById('modalConfirm');
                        const modalCancel = document.getElementById('modalCancel');

                        modalTitle.textContent = title;
                        modalMessage.textContent = message;

                        modal.style.display = 'flex';
                        setTimeout(() => modal.classList.add('show'), 10);

                        const closeModal = () => {
                            modal.classList.remove('show');
                            setTimeout(() => modal.style.display = 'none', 300);
                        };

                        modalConfirm.onclick = () => {
                            closeModal();
                            onConfirm();
                        };

                        modalCancel.onclick = closeModal;
                        modal.onclick = (e) => {
                            if (e.target === modal) closeModal();
                        };
                    }

                    function showSuccessNotification(message) {
                        const notification = document.getElementById('successNotification');
                        const messageElement = document.getElementById('successMessage');
                        if (message) {
                            messageElement.textContent = message;
                        }
                        notification.classList.remove('hidden');
                        setTimeout(() => {
                            notification.classList.add('show');
                        }, 10);
                        setTimeout(() => {
                            closeSuccessNotification();
                        }, 3000);
                    }

                    function closeSuccessNotification() {
                        const notification = document.getElementById('successNotification');
                        notification.classList.remove('show');
                        setTimeout(() => {
                            notification.classList.add('hidden');
                        }, 300);
                    }

                    function showErrorNotification(message) {
                        const notification = document.getElementById('errorNotification');
                        const messageElement = document.getElementById('errorMessage');
                        if (message) {
                            messageElement.textContent = message;
                        }
                        notification.classList.remove('hidden');
                        setTimeout(() => {
                            notification.classList.add('show');
                        }, 10);
                        setTimeout(() => {
                            closeErrorNotification();
                        }, 3000);
                    }

                    function closeErrorNotification() {
                        const notification = document.getElementById('errorNotification');
                        notification.classList.remove('show');
                        setTimeout(() => {
                            notification.classList.add('hidden');
                        }, 300);
                    }

                    // Session-based toast from PHP
                    <?php if (isset($_SESSION['toast_message'])): ?>
                        <?php if (($_SESSION['toast_type'] ?? 'success') === 'success'): ?>
                            showSuccessNotification('<?php echo addslashes($_SESSION['toast_message']); ?>');
                        <?php else: ?>
                            showErrorNotification('<?php echo addslashes($_SESSION['toast_message']); ?>');
                        <?php endif; ?>
                        <?php
                        unset($_SESSION['toast_message']);
                        unset($_SESSION['toast_type']);
                        ?>
                    <?php endif; ?>

                    // Initialize page with API data (from original design)
                    document.addEventListener('DOMContentLoaded', async function() {
                        const countryCodeSelect = document.getElementById('country_code');
                        const parentCountryCodeSelect = document.getElementById('parent_country_code');
                        const selectedCountryCode = countryCodeSelect.dataset.selected;
                        const selectedParentCountryCode = parentCountryCodeSelect.dataset.selected;

                        await loadCountryCodes('country_code', selectedCountryCode || '+63');
                        await loadCountryCodes('parent_country_code', selectedParentCountryCode || '+63');

                        await loadProvinces('province');
                        await loadProvinces('birth_province');
                        await loadProvinces('school_province');

                        const provinceSelect = document.getElementById('province');
                        const citySelect = document.getElementById('city');
                        const barangaySelect = document.getElementById('barangay');
                        const birthProvinceSelect = document.getElementById('birth_province');
                        const birthCitySelect = document.getElementById('birth_city');
                        const schoolProvinceSelect = document.getElementById('school_province');
                        const schoolCitySelect = document.getElementById('school_city');

                        const selectedProvince = provinceSelect.dataset.selected;
                        const selectedCity = citySelect.dataset.selected;
                        const selectedBarangay = barangaySelect.dataset.selected;
                        const selectedBirthProvince = birthProvinceSelect.dataset.selected;
                        const selectedBirthCity = birthCitySelect.dataset.selected;
                        const selectedSchoolProvince = schoolProvinceSelect.dataset.selected;
                        const selectedSchoolCity = schoolCitySelect.dataset.selected;

                        if (selectedProvince) {
                            provinceSelect.value = selectedProvince;
                            const selectedOption = provinceSelect.options[provinceSelect.selectedIndex];
                            const provinceCode = selectedOption.dataset.code;
                            if (provinceCode) {
                                await loadCities(provinceCode, 'city', selectedCity);
                                if (selectedCity) {
                                    const cityOption = Array.from(citySelect.options).find(opt => opt.value === selectedCity);
                                    if (cityOption) {
                                        const cityCode = cityOption.dataset.code;
                                        if (cityCode) {
                                            await loadBarangays(cityCode, 'barangay', selectedBarangay);
                                        }
                                    }
                                }
                            }
                        }

                        if (selectedBirthProvince) {
                            birthProvinceSelect.value = selectedBirthProvince;
                            const selectedOption = birthProvinceSelect.options[birthProvinceSelect.selectedIndex];
                            const provinceCode = selectedOption.dataset.code;
                            if (provinceCode) {
                                await loadCities(provinceCode, 'birth_city', selectedBirthCity);
                            }
                        }

                        if (selectedSchoolProvince) {
                            schoolProvinceSelect.value = selectedSchoolProvince;
                            const selectedOption = schoolProvinceSelect.options[schoolProvinceSelect.selectedIndex];
                            const provinceCode = selectedOption.dataset.code;
                            if (provinceCode) {
                                await loadCities(provinceCode, 'school_city', selectedSchoolCity);
                            }
                        }

                        const errorDiv = document.querySelector('.bg-red-50.border-l-4');
                        if (errorDiv) {
                            errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    });

                    // Province / city / barangay change handlers
                    document.addEventListener('DOMContentLoaded', function() {
                        const provinceSelect = document.getElementById('province');
                        const citySelect = document.getElementById('city');
                        const barangaySelect = document.getElementById('barangay');
                        const birthProvinceSelect = document.getElementById('birth_province');
                        const birthCitySelect = document.getElementById('birth_city');
                        const schoolProvinceSelect = document.getElementById('school_province');
                        const schoolCitySelect = document.getElementById('school_city');

                        if (provinceSelect) {
                            provinceSelect.addEventListener('change', function() {
                                const selectedOption = this.options[this.selectedIndex];
                                const provinceCode = selectedOption.dataset.code;
                                citySelect.innerHTML = '<option value="">Select city/municipality</option>';
                                barangaySelect.innerHTML = '<option value="">Select barangay</option>';
                                if (provinceCode) {
                                    loadCities(provinceCode, 'city');
                                }
                            });
                        }

                        if (citySelect) {
                            citySelect.addEventListener('change', function() {
                                const selectedOption = this.options[this.selectedIndex];
                                const cityCode = selectedOption.dataset.code;
                                barangaySelect.innerHTML = '<option value="">Select barangay</option>';
                                if (cityCode) {
                                    loadBarangays(cityCode, 'barangay');
                                }
                            });
                        }

                        if (birthProvinceSelect) {
                            birthProvinceSelect.addEventListener('change', function() {
                                const selectedOption = this.options[this.selectedIndex];
                                const provinceCode = selectedOption.dataset.code;
                                birthCitySelect.innerHTML = '<option value="">Select city/municipality</option>';
                                if (provinceCode) {
                                    loadCities(provinceCode, 'birth_city');
                                }
                            });
                        }

                        if (schoolProvinceSelect) {
                            schoolProvinceSelect.addEventListener('change', function() {
                                const selectedOption = this.options[this.selectedIndex];
                                const provinceCode = selectedOption.dataset.code;
                                schoolCitySelect.innerHTML = '<option value="">Select school city/municipality</option>';
                                if (provinceCode) {
                                    loadCities(provinceCode, 'school_city');
                                }
                            });
                        }
                    });

                    // Age calculation on birthday change
                    document.addEventListener('DOMContentLoaded', function() {
                        const birthdayInput = document.getElementById('birthday');
                        const ageInput = document.getElementById('age');
                        if (birthdayInput && ageInput) {
                            birthdayInput.addEventListener('change', function() {
                                const birthday = new Date(this.value);
                                const today = new Date();
                                let age = today.getFullYear() - birthday.getFullYear();
                                const monthDiff = today.getMonth() - birthday.getMonth();
                                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
                                    age--;
                                }
                                ageInput.value = age;
                            });
                        }
                    });

                    // Profile picture preview
                    document.addEventListener('DOMContentLoaded', function() {
                        const input = document.getElementById('profile_picture');
                        const preview = document.getElementById('profile-preview');
                        const current = document.getElementById('current-profile');
                        const placeholder = document.getElementById('upload-placeholder');
                        if (!input) return;
                        input.addEventListener('change', function() {
                            const file = this.files[0];
                            if (!file) return;
                            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                            if (!allowedTypes.includes(file.type)) {
                                alert('Please select a valid image file (JPG, JPEG, or PNG)');
                                this.value = '';
                                return;
                            }
                            if (file.size > 10 * 1024 * 1024) {
                                alert('File size must be less than 10MB');
                                this.value = '';
                                return;
                            }
                            const reader = new FileReader();
                            reader.onload = function(e) {
                                preview.src = e.target.result;
                                preview.style.display = 'block';
                                if (current) current.style.display = 'none';
                                if (placeholder) placeholder.style.display = 'none';
                            };
                            reader.readAsDataURL(file);
                        });
                    });

                    // Form submission with confirmation and loading state
                    document.addEventListener('DOMContentLoaded', function() {
                        const form = document.getElementById('edit-form');
                        if (!form) return;
                        const submitBtn = form.querySelector('button[type="submit"]');
                        const originalText = submitBtn ? submitBtn.innerHTML : '';

                        form.addEventListener('submit', function(e) {
                            e.preventDefault();
                            if (!submitBtn) {
                                form.submit();
                                return;
                            }
                            showModal(
                                'Confirm Update',
                                'Are you sure you want to update this student\'s information?',
                                function() {
                                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
                                    submitBtn.disabled = true;
                                    form.classList.add('form-loading');
                                    form.submit();
                                }
                            );
                        });

                        const cancelBtn = document.getElementById('cancel-btn');
                        if (cancelBtn) {
                            cancelBtn.addEventListener('click', function(e) {
                                e.preventDefault();
                                const href = this.href;
                                showModal(
                                    'Cancel Changes',
                                    'Are you sure you want to cancel? Any unsaved changes will be lost.',
                                    function() {
                                        window.location.href = href;
                                    }
                                );
                            });
                        }

                        const inputs = document.querySelectorAll('input[required], select[required]');
                        inputs.forEach(input => {
                            input.addEventListener('blur', function() {
                                if (this.value.trim() === '') {
                                    this.classList.add('border-red-300', 'bg-red-50');
                                    this.classList.remove('border-gray-300');
                                } else {
                                    this.classList.remove('border-red-300', 'bg-red-50');
                                    this.classList.add('border-gray-300');
                                }
                            });
                            input.addEventListener('input', function() {
                                if (this.value.trim() !== '') {
                                    this.classList.remove('border-red-300', 'bg-red-50');
                                    this.classList.add('border-gray-300');
                                }
                            });
                        });
                    });
                </script>
            <?php endif; ?>
        </main>
    </div>

    <?php include 'components/admin-scripts.php'; ?>
</body>
</html>


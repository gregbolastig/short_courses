<?php
session_start();

require_once '../config/database.php';
require_once '../includes/auth_middleware.php';
require_once '../includes/system_activity_logger.php';

requireAdmin();

$database = new Database();
$conn = $database->getConnection();
$logger = new SystemActivityLogger($conn);

// Logical page routing: index | view | edit
$page = isset($_GET['page']) ? trim((string)$_GET['page']) : 'index';
$allowed_pages = ['index', 'view', 'edit'];
if (!in_array($page, $allowed_pages, true)) {
    $page = 'index';
}

$page_title = 'Course Applications';
$error_message = '';
$success_message = '';

// Separate pagination parameter to avoid clashing with logical $page
$p = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;

// Handle success messages from redirects
if (isset($_GET['success']) && $page === 'index') {
    switch ($_GET['success']) {
        case 'updated':
            $success_message = 'Course application updated successfully!';
            break;
    }
}

// -------------------------------------------------------------------------
// Global delete handler (from original index.php)
// -------------------------------------------------------------------------
if ($page === 'index' && isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        // Get application info before deleting
        $stmt = $conn->prepare("
            SELECT ca.*, s.first_name, s.last_name
            FROM shortcourse_course_applications ca 
            JOIN shortcourse_students s ON ca.student_id = s.id 
            WHERE ca.application_id = ?
        ");
        $stmt->execute([$_GET['delete']]);
        $app = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($app) {
            $stmt = $conn->prepare("DELETE FROM shortcourse_course_applications WHERE application_id = ?");
            $stmt->execute([$_GET['delete']]);

            $success_message = "Application for {$app['first_name']} {$app['last_name']} deleted successfully!";
        }
    } catch (PDOException $e) {
        $error_message = 'Cannot delete application: ' . $e->getMessage();
    }
}

// -------------------------------------------------------------------------
// Global approve / reject handler (from original index.php)
// -------------------------------------------------------------------------
if ($page === 'index' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $application_id = $_POST['application_id'] ?? '';

    if ($action === 'approve' && !empty($application_id)) {
        try {
            // Validate required fields for approval
            $required_fields = ['course_name', 'nc_level', 'adviser', 'training_start', 'training_end'];
            $missing_fields = [];

            foreach ($required_fields as $field) {
                if (empty($_POST[$field])) {
                    $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
                }
            }

            if (!empty($missing_fields)) {
                $error_message = 'Please fill in all required fields: ' . implode(', ', $missing_fields);
            } else {
                // Start transaction to ensure both updates succeed
                $conn->beginTransaction();

                // Get student_id from shortcourse_course_applications
                $stmt = $conn->prepare("SELECT student_id FROM shortcourse_course_applications WHERE application_id = :app_id");
                $stmt->bindParam(':app_id', $application_id, PDO::PARAM_INT);
                $stmt->execute();
                $app_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $student_id = $app_data['student_id'] ?? null;

                if (!$student_id) {
                    throw new Exception('Application not found or missing student.');
                }

                // Get course name for students table
                $stmt = $conn->prepare("SELECT course_name FROM shortcourse_courses WHERE course_id = :course_id");
                $stmt->bindParam(':course_id', $_POST['course_name'], PDO::PARAM_INT);
                $stmt->execute();
                $course_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $course_name = $course_data['course_name'] ?? $_POST['course_name'];

                // Update course application with approval and details
                $stmt = $conn->prepare("
                    UPDATE shortcourse_course_applications SET 
                        status = 'approved',
                        course_id = :course_id,
                        nc_level = :nc_level,
                        reviewed_by = :admin_id,
                        reviewed_at = NOW(),
                        notes = :notes
                    WHERE application_id = :id
                ");

                $course_id = $_POST['course_name']; // Form field is named course_name but contains course_id
                $nc_level = $_POST['nc_level'];
                $notes = $_POST['notes'] ?? '';
                $admin_id = (int)$_SESSION['user_id'];

                $stmt->bindParam(':course_id', $course_id, PDO::PARAM_INT);
                $stmt->bindParam(':nc_level', $nc_level, PDO::PARAM_STR);
                $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
                $stmt->bindParam(':notes', $notes, PDO::PARAM_STR);
                $stmt->bindParam(':id', $application_id, PDO::PARAM_INT);

                if (!$stmt->execute()) {
                    throw new Exception('Failed to update course application');
                }

                // Update students table with course details (training dates, adviser, course name)
                $stmt = $conn->prepare("
                    UPDATE students SET 
                        course = :course_name,
                        nc_level = :nc_level,
                        adviser = :adviser,
                        training_start = :training_start,
                        training_end = :training_end,
                        status = 'approved',
                        approved_by = :admin_id,
                        approved_at = NOW()
                    WHERE id = :student_id
                ");

                $adviser = $_POST['adviser'];
                $training_start = $_POST['training_start'];
                $training_end = $_POST['training_end'];

                $stmt->bindParam(':course_name', $course_name, PDO::PARAM_STR);
                $stmt->bindParam(':nc_level', $nc_level, PDO::PARAM_STR);
                $stmt->bindParam(':adviser', $adviser, PDO::PARAM_STR);
                $stmt->bindParam(':training_start', $training_start, PDO::PARAM_STR);
                $stmt->bindParam(':training_end', $training_end, PDO::PARAM_STR);
                $stmt->bindParam(':admin_id', $admin_id, PDO::PARAM_INT);
                $stmt->bindParam(':student_id', $student_id, PDO::PARAM_INT);

                if (!$stmt->execute()) {
                    throw new Exception('Failed to update student record');
                }

                // Commit the transaction
                $conn->commit();

                // Log the activity
                $logger->log(
                    'course_application_approved',
                    'Approved course application ID: ' . $application_id,
                    'admin',
                    $admin_id,
                    'course_application',
                    (int)$application_id
                );

                $success_message = 'Course application approved successfully!';
            }
        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollback();
            }
            $error_message = 'Database error: ' . $e->getMessage();
        }
    } elseif ($action === 'reject' && !empty($application_id)) {
        try {
            $stmt = $conn->prepare("
                UPDATE shortcourse_course_applications SET 
                    status = 'rejected',
                    reviewed_by = :admin_id,
                    reviewed_at = NOW(),
                    notes = :notes
                WHERE application_id = :id
            ");

            $stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':notes', $_POST['notes'] ?? '', PDO::PARAM_STR);
            $stmt->bindParam(':id', $application_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $success_message = 'Course application rejected.';
            } else {
                $error_message = 'Failed to reject course application.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// ========================================================================
// DELETE ACTION HANDLER
// ========================================================================

if (isset($_POST['action'], $_POST['id'], $_POST['admin_password']) && $_POST['action'] === 'delete' && is_numeric($_POST['id'])) {
    $application_id = (int)$_POST['id'];
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
            header('Location: admin-course-application.php?page=index');
            exit;
        }
        
        // Password verified, proceed with deletion
        // Get application details for logging
        $stmt = $conn->prepare("SELECT student_id, course_id FROM shortcourse_course_applications WHERE application_id = :id");
        $stmt->bindParam(':id', $application_id, PDO::PARAM_INT);
        $stmt->execute();
        $app_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($app_data) {
            // Delete the application
            $stmt = $conn->prepare("DELETE FROM shortcourse_course_applications WHERE application_id = :id");
            $stmt->bindParam(':id', $application_id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Log the action
            if (isset($logger)) {
                $logger->log(
                    'course_application_deleted',
                    'Course Application',
                    $application_id,
                    "Deleted course application ID: {$application_id}",
                    $_SESSION['user_id']
                );
            }
            
            $_SESSION['toast_message'] = 'Course application deleted successfully.';
            $_SESSION['toast_type'] = 'success';
        } else {
            $_SESSION['toast_message'] = 'Application not found.';
            $_SESSION['toast_type'] = 'error';
        }
    } catch (PDOException $e) {
        $_SESSION['toast_message'] = 'Error deleting application: ' . $e->getMessage();
        $_SESSION['toast_type'] = 'error';
    }
    
    header('Location: admin-course-application.php?page=index');
    exit;
}

// -------------------------------------------------------------------------
// Route-specific data
// -------------------------------------------------------------------------

// Shared stats for index
$applications = [];
$total_applications = 0;
$total_pages = 0;
$courses = [];
$advisers = [];

if ($page === 'index') {
    try {
        // Filtering
        $status_filter = $_GET['status'] ?? '';
        $course_filter = $_GET['course'] ?? '';
        $search = $_GET['search'] ?? '';
        $start_date = $_GET['start_date'] ?? '';
        $end_date = $_GET['end_date'] ?? '';

        $per_page = 10;
        $offset = ($p - 1) * $per_page;

        // Build WHERE clause
        $where_conditions = [];
        $params = [];

        // Only show completed applications in this browser (from original comment)
        $where_conditions[] = "ca.status = 'completed'";

        if (!empty($status_filter)) {
            $where_conditions[] = "ca.status = :status";
            $params[':status'] = $status_filter;
        }

        if (!empty($course_filter)) {
            $where_conditions[] = "c.course_name = :course_name";
            $params[':course_name'] = $course_filter;
        }

        if (!empty($search)) {
            $where_conditions[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR s.email LIKE :search OR s.uli LIKE :search OR c.course_name LIKE :search)";
            $params[':search'] = '%' . $search . '%';
        }

        if (!empty($start_date)) {
            $where_conditions[] = "DATE(ca.applied_at) >= :start_date";
            $params[':start_date'] = $start_date;
        }

        if (!empty($end_date)) {
            $where_conditions[] = "DATE(ca.applied_at) <= :end_date";
            $params[':end_date'] = $end_date;
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Get total count
        $count_sql = "
            SELECT COUNT(*) as total
            FROM shortcourse_course_applications ca 
            INNER JOIN shortcourse_students s ON ca.student_id = s.id 
            LEFT JOIN shortcourse_courses c ON ca.course_id = c.course_id 
            {$where_clause}
        ";
        $stmt = $conn->prepare($count_sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $total_applications = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        $total_pages = (int)ceil($total_applications / $per_page);

        // Get applications
        $sql = "
            SELECT ca.*, s.first_name, s.last_name, s.middle_name, s.extension_name, s.uli, s.contact_number,
                   s.email, s.age, s.sex, s.civil_status, s.province, s.city, s.barangay,
                   c.course_name, ca.nc_level,
                   u.username as reviewed_by_name
            FROM shortcourse_course_applications ca
            INNER JOIN shortcourse_students s ON ca.student_id = s.id
            LEFT JOIN shortcourse_courses c ON ca.course_id = c.course_id
            LEFT JOIN users u ON ca.reviewed_by = u.id
            {$where_clause}
            ORDER BY ca.applied_at DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Courses for filter and approval modal
        $stmt = $conn->query("SELECT course_id, course_name FROM shortcourse_courses WHERE is_active = 1 ORDER BY course_name");
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Advisers from faculty table for modal
        $stmt = $conn->query("SELECT faculty_id, name as adviser_name, status FROM faculty WHERE status = 'Active' ORDER BY name");
        $advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = 'Database error: ' . $e->getMessage();
        $applications = [];
        $total_applications = 0;
        $total_pages = 0;
        $courses = [];
        $advisers = [];
    }
}

// View-specific data
$application = null;
if ($page === 'view') {
    $page_title = 'View Course Application';
    $breadcrumb_items = [
        ['title' => 'Course Applications', 'icon' => 'fas fa-file-alt', 'url' => 'admin-course-application.php?page=index'],
        ['title' => 'View Application', 'icon' => 'fas fa-eye']
    ];
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        $error_message = 'Invalid application ID.';
    } else {
        try {
            $stmt = $conn->prepare("
                SELECT ca.*, s.first_name, s.last_name, s.middle_name, s.extension_name, 
                       s.email, s.uli, s.contact_number, s.birthday, s.age, s.sex, s.civil_status,
                       s.province, s.city, s.barangay, s.street_address, s.birth_province, s.birth_city,
                       s.guardian_first_name, s.guardian_middle_name, s.guardian_last_name, 
                       s.guardian_extension, s.parent_contact, s.last_school, s.school_province, 
                       s.school_city, s.profile_picture,
                       s.adviser, s.training_start, s.training_end,
                       COALESCE(c.course_name, ca.course_id) as course_name,
                       u.username as reviewed_by_name
                FROM shortcourse_course_applications ca
                INNER JOIN shortcourse_students s ON ca.student_id = s.id
                LEFT JOIN shortcourse_courses c ON ca.course_id = c.course_id
                LEFT JOIN users u ON ca.reviewed_by = u.id
                WHERE ca.application_id = :id
            ");
            $stmt->bindParam(':id', $_GET['id'], PDO::PARAM_INT);
            $stmt->execute();
            $application = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$application) {
                $error_message = 'Application not found.';
            } else {
                // Fetch course history for this student
                $stmt = $conn->prepare("
                    SELECT ca.*, c.course_name, ca.reviewed_at, ca.applied_at
                    FROM shortcourse_course_applications ca
                    LEFT JOIN shortcourse_courses c ON ca.course_id = c.course_id
                    WHERE ca.student_id = :student_id
                    ORDER BY ca.applied_at DESC
                ");
                $stmt->bindParam(':student_id', $application['student_id'], PDO::PARAM_INT);
                $stmt->execute();
                $course_history = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Edit-specific data
if ($page === 'edit') {
    $page_title = 'Edit Course Application';
    $breadcrumb_items = [
        ['title' => 'Course Applications', 'icon' => 'fas fa-file-alt', 'url' => 'admin-course-application.php?page=index'],
        ['title' => 'Edit Application', 'icon' => 'fas fa-edit']
    ];
    $application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $courses_for_edit = [];
    $advisers_for_edit = [];

    if (!$application_id) {
        header('Location: admin-course-application.php?page=index');
        exit;
    }

    try {
        // Get application with student info
        $stmt = $conn->prepare("
            SELECT ca.*, s.first_name, s.last_name, s.student_id, c.course_name
            FROM shortcourse_course_applications ca
            JOIN shortcourse_students s ON ca.student_id = s.id
            LEFT JOIN shortcourse_courses c ON ca.course_id = c.course_id
            WHERE ca.application_id = ?
        ");
        $stmt->execute([$application_id]);
        $application = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$application) {
            header('Location: admin-course-application.php?page=index');
            exit;
        }

        // Get all courses
        $stmt = $conn->query("SELECT course_id, course_name, nc_levels FROM shortcourse_courses WHERE is_active = 1 ORDER BY course_name");
        $courses_for_edit = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get all advisers from faculty table
        $stmt = $conn->query("SELECT faculty_id, name as adviser_name, status FROM faculty WHERE status = 'Active' ORDER BY name");
        $advisers_for_edit = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }

    // Handle edit form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $course_id = $_POST['course_id'];
            $nc_level = $_POST['nc_level'];
            $training_start = $_POST['training_start'];
            $training_end = $_POST['training_end'];
            $adviser = !empty($_POST['adviser']) ? $_POST['adviser'] : null;

            $stmt = $conn->prepare("
                UPDATE shortcourse_course_applications 
                SET course_id = ?, nc_level = ?, training_start = ?, training_end = ?, adviser = ?
                WHERE application_id = ?
            ");

            $stmt->execute([
                $course_id,
                $nc_level,
                $training_start,
                $training_end,
                $adviser,
                $application_id
            ]);

            $logger->log(
                'application_updated',
                "Admin updated course application for {$application['first_name']} {$application['last_name']} (ID: {$application_id})",
                'admin',
                (int)$_SESSION['user_id'],
                'course_application',
                $application_id
            );

            header("Location: admin-course-application.php?page=index&success=updated");
            exit;
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
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
    <?php include 'components/admin-styles.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen">
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
                                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Course Applications Browser</h1>
                                    <p class="text-lg text-gray-600 mt-2">Browse and view detailed student course applications</p>
                                </div>
                                <div class="flex flex-col sm:flex-row gap-3">
                                    <div class="bg-blue-50 border border-blue-200 rounded-lg px-4 py-2">
                                        <div class="flex items-center text-blue-700">
                                            <i class="fas fa-info-circle mr-2"></i>
                                            <span class="text-sm font-medium">View Mode - Browse Applications</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg animate-fade-in-up">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700"><?php echo htmlspecialchars($error_message); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($success_message): ?>
                            <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-r-lg animate-fade-in-up">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle text-green-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-green-700"><?php echo htmlspecialchars($success_message); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Filters -->
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
                                            <input type="text" id="search" name="search"
                                                   placeholder="Student name, email, ID..."
                                                   value="<?php echo htmlspecialchars($search ?? ''); ?>"
                                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                        </div>
                                    </div>
                                    <div>
                                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                        <select id="status" name="status"
                                                class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                            <option value="">All Status</option>
                                            <option value="pending" <?php echo (($status_filter ?? '') === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo (($status_filter ?? '') === 'approved') ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo (($status_filter ?? '') === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label for="course" class="block text-sm font-medium text-gray-700 mb-2">Course</label>
                                        <select id="course" name="course"
                                                class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                            <option value="">All Courses</option>
                                            <?php foreach ($courses as $course): ?>
                                                <option value="<?php echo htmlspecialchars($course['course_name']); ?>"
                                                    <?php echo (($course_filter ?? '') === $course['course_name']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($course['course_name']); ?>
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
                                    <a href="admin-course-application.php?page=index"
                                       class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
                                        <i class="fas fa-times mr-2"></i>Clear Filters
                                    </a>
                                </div>
                            </form>
                        </div>

                        <!-- Applications Table -->
                        <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                            <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div class="flex items-center space-x-3">
                                        <div class="bg-indigo-100 rounded-xl p-2">
                                            <i class="fas fa-eye text-indigo-600"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-900">Application Browser</h3>
                                    </div>
                                    <?php if ($total_pages > 1): ?>
                                        <div class="flex items-center">
                                            <div class="bg-white rounded-lg px-3 py-2 border border-gray-200 shadow-sm">
                                                <span class="text-sm font-medium text-gray-600">
                                                    Page <?php echo $p; ?> of <?php echo $total_pages; ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if (empty($applications)): ?>
                                <div class="text-center py-16">
                                    <div class="bg-gradient-to-br from-indigo-50 to-indigo-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6 shadow-lg">
                                        <i class="fas fa-search text-indigo-600 text-3xl"></i>
                                    </div>
                                    <h3 class="text-2xl font-bold text-gray-900 mb-3">No applications found</h3>
                                    <p class="text-lg text-gray-600 mb-8 px-4 max-w-md mx-auto">
                                        <?php echo !empty($search) || !empty($status_filter) || !empty($course_filter)
                                            ? 'No applications match your search criteria. Try adjusting your filters to view different applications.'
                                            : 'No course applications have been submitted yet. Check back later for new applications to view.'; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <!-- Desktop Table View -->
                                <div class="hidden lg:block overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                            <tr>
                                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Student Information</th>
                                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Course Details</th>
                                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Application Info</th>
                                                <th class="px-6 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Status</th>
                                                <th class="px-6 py-4 text-center text-sm font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($applications as $app): ?>
                                                <tr class="hover:bg-blue-50 transition-all duration-200">
                                                    <td class="px-6 py-4">
                                                        <div class="flex items-center">
                                                            <div class="flex-shrink-0 h-10 w-10">
                                                                <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                                    <span class="text-sm font-medium text-blue-600">
                                                                        <?php echo strtoupper(substr($app['first_name'], 0, 1) . substr($app['last_name'], 0, 1)); ?>
                                                                    </span>
                                                                </div>
                                                            </div>
                                                            <div class="ml-4">
                                                                <div class="text-sm font-medium text-gray-900">
                                                                    <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($app['course_name']); ?></div>
                                                        <?php if ($app['nc_level']): ?>
                                                            <div class="text-sm text-gray-500">NC Level: <?php echo htmlspecialchars($app['nc_level']); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm text-gray-900">Applied: <?php echo date('M j, Y', strtotime($app['applied_at'])); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($app['applied_at'])); ?></div>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <?php
                                                        $status_class = '';
                                                        switch ($app['status']) {
                                                            case 'completed':
                                                            case 'approved':
                                                                $status_class = 'bg-green-100 text-green-800 border-green-200';
                                                                break;
                                                            case 'rejected':
                                                                $status_class = 'bg-red-100 text-red-800 border-red-200';
                                                                break;
                                                            case 'pending':
                                                            default:
                                                                $status_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                                        }
                                                        ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                                            <?php echo ucfirst($app['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 text-center">
                                                        <div class="flex items-center justify-center space-x-2">
                                                            <a href="admin-course-application.php?page=view&id=<?php echo (int)$app['application_id']; ?>"
                                                               class="inline-flex items-center px-3 py-1.5 border border-indigo-300 text-xs font-semibold rounded-md text-indigo-700 bg-indigo-50 hover:bg-indigo-100 hover:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200"
                                                               title="View Details">
                                                                <i class="fas fa-eye mr-1"></i>
                                                            </a>
                                                            <a href="admin-course-application.php?page=edit&id=<?php echo (int)$app['application_id']; ?>"
                                                               class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-xs font-semibold rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200"
                                                               title="Edit Application">
                                                                <i class="fas fa-edit mr-1"></i>
                                                            </a>
                                                            <button type="button"
                                                                    onclick="confirmDelete(<?php echo (int)$app['application_id']; ?>, '<?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>')"
                                                                    class="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-semibold rounded-md text-red-700 bg-red-50 hover:bg-red-100 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200"
                                                                    title="Delete Application">
                                                                <i class="fas fa-trash mr-1"></i>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Card View -->
                                <div class="lg:hidden">
                                    <?php foreach ($applications as $app): ?>
                                        <div class="border-b border-gray-200 p-6 hover:bg-blue-50 transition-all duration-200">
                                            <div class="flex items-start justify-between mb-4">
                                                <div class="flex items-center space-x-3 flex-1">
                                                    <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                        <span class="text-sm font-medium text-blue-600">
                                                            <?php echo strtoupper(substr($app['first_name'], 0, 1) . substr($app['last_name'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                    <div class="flex-1">
                                                        <h4 class="text-lg font-semibold text-gray-900">
                                                            <?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>
                                                        </h4>
                                                        <p class="text-sm font-medium text-gray-700 mt-1">
                                                            <?php echo htmlspecialchars($app['course_name']); ?>
                                                            <?php if ($app['nc_level']): ?>
                                                                - <?php echo htmlspecialchars($app['nc_level']); ?>
                                                            <?php endif; ?>
                                                        </p>
                                                        <?php if ($app['adviser']): ?>
                                                            <p class="text-sm text-blue-600 mt-1">
                                                                Adviser: <?php echo htmlspecialchars($app['adviser']); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                        <p class="text-xs text-gray-500 mt-1">
                                                            Applied: <?php echo date('M j, Y g:i A', strtotime($app['applied_at'])); ?>
                                                        </p>
                                                        <?php if ($app['training_start'] && $app['training_end']): ?>
                                                            <p class="text-xs text-green-600 mt-1">
                                                                Training: <?php echo date('M j', strtotime($app['training_start'])); ?> - <?php echo date('M j, Y', strtotime($app['training_end'])); ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <?php
                                                    $status_class = '';
                                                    switch ($app['status']) {
                                                        case 'completed':
                                                        case 'approved':
                                                            $status_class = 'bg-green-100 text-green-800 border-green-200';
                                                            break;
                                                        case 'rejected':
                                                            $status_class = 'bg-red-100 text-red-800 border-red-200';
                                                            break;
                                                        case 'pending':
                                                        default:
                                                            $status_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                                    }
                                                    ?>
                                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($app['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="flex items-center justify-center space-x-2 mt-4 flex-wrap gap-2">
                                                <a href="admin-course-application.php?page=view&id=<?php echo (int)$app['application_id']; ?>"
                                                   class="inline-flex items-center justify-center px-4 py-2 border border-indigo-300 text-sm font-semibold rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 hover:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                                                    <i class="fas fa-eye mr-2"></i>View
                                                </a>
                                                <a href="admin-course-application.php?page=edit&id=<?php echo (int)$app['application_id']; ?>"
                                                   class="inline-flex items-center justify-center px-4 py-2 border border-blue-300 text-sm font-semibold rounded-lg text-blue-700 bg-blue-50 hover:bg-blue-100 hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                                    <i class="fas fa-edit mr-2"></i>Edit
                                                </a>
                                                <button type="button"
                                                        onclick="confirmDelete(<?php echo (int)$app['application_id']; ?>, '<?php echo htmlspecialchars($app['first_name'] . ' ' . $app['last_name']); ?>')"
                                                        class="inline-flex items-center justify-center px-4 py-2 border border-red-300 text-sm font-semibold rounded-lg text-red-700 bg-red-50 hover:bg-red-100 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200">
                                                    <i class="fas fa-trash mr-2"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="px-6 py-5 border-t border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                        <div class="text-sm font-medium text-gray-700">
                                            Viewing <span class="font-bold text-gray-900"><?php echo $offset + 1; ?></span>
                                            to <span class="font-bold text-gray-900"><?php echo min($offset + $per_page, $total_applications); ?></span>
                                            of <span class="font-bold text-gray-900"><?php echo $total_applications; ?></span> applications
                                        </div>
                                        <div class="flex items-center space-x-2">
                                            <?php
                                            $queryBase = [
                                                'page' => 'index',
                                                'search' => $search ?? '',
                                                'status' => $status_filter ?? '',
                                                'course' => $course_filter ?? '',
                                                'start_date' => $start_date ?? '',
                                                'end_date' => $end_date ?? '',
                                            ];
                                            ?>
                                            <?php if ($p > 1): ?>
                                                <a href="admin-course-application.php?<?php echo http_build_query(array_merge($queryBase, ['p' => $p - 1])); ?>"
                                                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm">
                                                    <i class="fas fa-chevron-left mr-2"></i>Previous
                                                </a>
                                            <?php endif; ?>
                                            <div class="hidden sm:flex items-center space-x-1">
                                                <?php for ($i = max(1, $p - 2); $i <= min($total_pages, $p + 2); $i++): ?>
                                                    <?php if ($i === $p): ?>
                                                        <span class="inline-flex items-center justify-center w-10 h-10 border-2 border-indigo-500 rounded-lg text-sm font-bold text-white bg-indigo-600 shadow-md"><?php echo $i; ?></span>
                                                    <?php else: ?>
                                                        <a href="admin-course-application.php?<?php echo http_build_query(array_merge($queryBase, ['p' => $i])); ?>"
                                                           class="inline-flex items-center justify-center w-10 h-10 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 shadow-sm">
                                                            <?php echo $i; ?>
                                                        </a>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                            <?php if ($p < $total_pages): ?>
                                                <a href="admin-course-application.php?<?php echo http_build_query(array_merge($queryBase, ['p' => $p + 1])); ?>"
                                                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm">
                                                    Next<i class="fas fa-chevron-right ml-2"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Delete Confirmation Modal (adapted from original index.php) -->
                <div id="deleteModal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-all duration-300" aria-hidden="true" onclick="closeDeleteModal()"></div>
                        <div class="inline-block align-bottom bg-white rounded-2xl px-6 pt-6 pb-6 text-left overflow-hidden shadow-2xl transform transition-all duration-300 sm:my-8 sm:align-middle sm:max-w-md sm:w-full border border-gray-100">
                            <div class="text-center mb-6">
                                <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-gradient-to-br from-red-100 to-red-200 mb-4 shadow-lg">
                                    <div class="h-12 w-12 rounded-full bg-gradient-to-br from-red-500 to-red-600 flex items-center justify-center shadow-inner">
                                        <i class="fas fa-exclamation-triangle text-white text-lg"></i>
                                    </div>
                                </div>
                                <h3 class="text-xl font-bold text-gray-900 mb-2">
                                    Delete Application
                                </h3>
                                <div class="w-12 h-1 bg-gradient-to-r from-red-500 to-red-600 rounded-full mx-auto"></div>
                            </div>
                            <div class="text-center mb-8">
                                <div class="bg-gray-50 rounded-xl p-4 mb-4 border border-gray-200">
                                    <div class="flex items-center justify-center space-x-3 mb-2">
                                        <div class="bg-blue-100 rounded-lg p-2">
                                            <i class="fas fa-user text-blue-600"></i>
                                        </div>
                                        <span class="font-semibold text-gray-900 text-lg" id="deleteStudentName"></span>
                                    </div>
                                </div>
                                <p class="text-gray-600 leading-relaxed">
                                    This action will permanently remove the course application from your system. All associated data will be lost and cannot be recovered.
                                </p>
                                <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded-lg">
                                    <div class="flex items-center justify-center space-x-2 text-red-700">
                                        <i class="fas fa-info-circle text-sm"></i>
                                        <span class="text-sm font-medium">This action cannot be undone</span>
                                    </div>
                                </div>
                            </div>
                            <div class="flex flex-col sm:flex-row gap-3">
                                <button type="button" onclick="executeDelete()" class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-semibold rounded-xl shadow-lg text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transform transition-all duration-200 hover:scale-105">
                                    <i class="fas fa-trash mr-2"></i>
                                    Delete Application
                                </button>
                                <button type="button" onclick="closeDeleteModal()" class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                    <i class="fas fa-times mr-2"></i>
                                    Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <script>
                    function confirmDelete(id, studentName) {
                        document.getElementById('deleteStudentName').textContent = studentName;
                        window._deleteApplicationId = id;
                        document.getElementById('deleteModal').classList.remove('hidden');
                        document.body.classList.add('overflow-hidden');
                    }
                    function closeDeleteModal() {
                        document.getElementById('deleteModal').classList.add('hidden');
                        document.body.classList.remove('overflow-hidden');
                        window._deleteApplicationId = null;
                    }
                    function executeDelete() {
                        if (!window._deleteApplicationId) return;
                        const params = new URLSearchParams(window.location.search);
                        params.set('page', 'index');
                        params.set('delete', window._deleteApplicationId);
                        window.location.href = 'admin-course-application.php?' + params.toString();
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
                                        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Course Application Details</h1>
                                        <p class="text-lg text-gray-600 mt-2">Complete information for <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></p>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <a href="admin-course-application.php?page=edit&id=<?php echo (int)$application['application_id']; ?>" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-semibold rounded-lg shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                            <i class="fas fa-edit mr-2"></i>Edit Application
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
                                            if (!empty($application['profile_picture'])) {
                                                $stored_path = $application['profile_picture'];
                                                if (strpos($stored_path, '../') === 0) {
                                                    $profile_picture_url = $stored_path;
                                                } else {
                                                    $profile_picture_url = '../' . $stored_path;
                                                }
                                                $file_exists = file_exists($profile_picture_url);
                                            }
                                            ?>
                                            <?php if (!empty($application['profile_picture']) && $file_exists): ?>
                                                <div class="relative group">
                                                    <img src="<?php echo htmlspecialchars($profile_picture_url); ?>"
                                                         alt="Profile Picture"
                                                         class="w-24 h-24 md:w-32 md:h-32 rounded-full object-cover border-4 border-white shadow-lg">
                                                    <div class="absolute -bottom-1 -right-1 bg-green-500 text-white p-1.5 rounded-full shadow-lg">
                                                        <i class="fas fa-check text-xs"></i>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="relative group">
                                                    <div class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-white bg-opacity-20 border-4 border-white shadow-lg flex items-center justify-center">
                                                        <span class="text-2xl md:text-3xl font-bold text-white">
                                                            <?php echo strtoupper(substr($application['first_name'], 0, 1) . substr($application['last_name'], 0, 1)); ?>
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
                                                <?php echo htmlspecialchars(trim($application['first_name'] . ' ' . ($application['middle_name'] ? $application['middle_name'] . ' ' : '') . $application['last_name'])); ?>
                                                <?php if ($application['extension_name']): ?>
                                                    <?php echo htmlspecialchars($application['extension_name']); ?>
                                                <?php endif; ?>
                                            </h2>
                                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-blue-100">
                                                <div class="flex items-center justify-center md:justify-start">
                                                    <i class="fas fa-id-card mr-2"></i>
                                                    <span>ULI: <?php echo htmlspecialchars($application['uli']); ?></span>
                                                </div>
                                                <div class="flex items-center justify-center md:justify-start">
                                                    <i class="fas fa-envelope mr-2"></i>
                                                    <span><?php echo htmlspecialchars($application['email']); ?></span>
                                                </div>
                                                <div class="flex items-center justify-center md:justify-start">
                                                    <i class="fas fa-phone mr-2"></i>
                                                    <span><?php echo htmlspecialchars($application['contact_number']); ?></span>
                                                </div>
                                                <div class="flex items-center justify-center md:justify-start">
                                                    <i class="fas fa-calendar mr-2"></i>
                                                    <span>Applied: <?php echo date('M j, Y', strtotime($application['applied_at'])); ?></span>
                                                </div>
                                            </div>
                                        </div>
                                </div>
                            </div>

                            <!-- Details sections -->
                            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8">
                                <!-- Personal Information -->
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
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['first_name']); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Middle Name</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['middle_name'] ?: 'N/A'); ?></p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Last Name</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['last_name']); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Extension</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['extension_name'] ?: 'N/A'); ?></p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Birthday</label>
                                                <p class="text-sm text-gray-900"><?php echo $application['birthday'] ? date('F j, Y', strtotime($application['birthday'])) : 'N/A'; ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Age</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['age'] ?? 'N/A'); ?> years old</p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Gender</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['sex'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Civil Status</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['civil_status'] ?? 'N/A'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Contact Information -->
                                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-8">
                                    <div class="flex items-center mb-6">
                                        <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl w-12 h-12 flex items-center justify-center mr-4 shadow-lg">
                                            <i class="fas fa-phone text-green-600 text-xl"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-900">Contact Information</h3>
                                    </div>
                                    <div class="space-y-4">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Email Address</label>
                                                <p class="text-sm text-gray-900 break-all"><?php echo htmlspecialchars($application['email'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Contact Number</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['contact_number'] ?? 'N/A'); ?></p>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-1">ULI Number</label>
                                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['uli']); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Address Information -->
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
                                                <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($application['province'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1"><i class="fas fa-city text-green-600 mr-1"></i>City/Municipality</label>
                                                <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($application['city'] ?? 'N/A'); ?></p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1"><i class="fas fa-home text-green-600 mr-1"></i>Barangay</label>
                                                <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($application['barangay'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1"><i class="fas fa-road text-gray-400 mr-1"></i>Street / Subdivision</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['street_address'] ?: 'N/A'); ?></p>
                                            </div>
                                        </div>
                                        <div class="pt-4 border-t border-gray-200">
                                            <h4 class="text-sm font-semibold text-gray-700 mb-3 flex items-center">
                                                <i class="fas fa-baby text-green-600 mr-2"></i>Place of Birth
                                            </h4>
                                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-500 mb-1"><i class="fas fa-map text-green-600 mr-1"></i>Province</label>
                                                    <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($application['birth_province'] ?? 'N/A'); ?></p>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-medium text-gray-500 mb-1"><i class="fas fa-city text-green-600 mr-1"></i>City/Municipality</label>
                                                    <p class="text-sm text-gray-900 font-medium"><?php echo htmlspecialchars($application['birth_city'] ?? 'N/A'); ?></p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Course Application -->
                                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-8">
                                    <div class="flex items-center mb-6">
                                        <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl w-12 h-12 flex items-center justify-center mr-4 shadow-lg">
                                            <i class="fas fa-book text-purple-600 text-xl"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-900">Course Application</h3>
                                    </div>
                                    <div class="space-y-4">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Course</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['course_name'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">NC Level</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['nc_level'] ?? 'N/A'); ?></p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Training Start</label>
                                                <p class="text-sm text-gray-900">
                                                    <?php echo $application['training_start'] ? date('F j, Y', strtotime($application['training_start'])) : 'Not set'; ?>
                                                </p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Training End</label>
                                                <p class="text-sm text-gray-900">
                                                    <?php echo $application['training_end'] ? date('F j, Y', strtotime($application['training_end'])) : 'Not set'; ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Adviser</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['adviser'] ?? 'Not assigned'); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Applied Date</label>
                                                <p class="text-sm text-gray-900"><?php echo date('F j, Y', strtotime($application['applied_at'])); ?></p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Status</label>
                                                <?php
                                                $status_class = '';
                                                switch ($application['status']) {
                                                    case 'completed':
                                                    case 'approved':
                                                        $status_class = 'bg-green-100 text-green-800 border-green-200';
                                                        break;
                                                    case 'rejected':
                                                        $status_class = 'bg-red-100 text-red-800 border-red-200';
                                                        break;
                                                    case 'pending':
                                                    default:
                                                        $status_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                                }
                                                ?>
                                                <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                                    <?php echo ucfirst($application['status']); ?>
                                                </span>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Reviewed By</label>
                                                <p class="text-sm text-gray-900">
                                                    <?php echo htmlspecialchars($application['reviewed_by_name'] ?? 'Not reviewed'); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <?php if ($application['notes']): ?>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-1">Notes</label>
                                            <p class="text-sm text-gray-900 whitespace-pre-line">
                                                <?php echo htmlspecialchars($application['notes']); ?>
                                            </p>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Parent / Guardian Information -->
                                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-8">
                                    <div class="flex items-center mb-6">
                                        <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl w-12 h-12 flex items-center justify-center mr-4 shadow-lg">
                                            <i class="fas fa-users text-orange-600 text-xl"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-900">Parent / Guardian Information</h3>
                                    </div>
                                    <div class="space-y-4">
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">First Name</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['guardian_first_name'] ?: 'N/A'); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Middle Name</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['guardian_middle_name'] ?: 'N/A'); ?></p>
                                            </div>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Last Name</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['guardian_last_name'] ?: 'N/A'); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">Extension</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['guardian_extension'] ?: 'N/A'); ?></p>
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-1">Contact Number</label>
                                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['parent_contact'] ?? 'N/A'); ?></p>
                                        </div>
                                    </div>
                                </div>

                                <!-- Education Information -->
                                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 md:p-8">
                                    <div class="flex items-center mb-6">
                                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl w-12 h-12 flex items-center justify-center mr-4 shadow-lg">
                                            <i class="fas fa-school text-blue-600 text-xl"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-900">Education Information</h3>
                                    </div>
                                    <div class="space-y-4">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-500 mb-1">Last School Attended</label>
                                            <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['last_school'] ?? 'N/A'); ?></p>
                                        </div>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">School Province</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['school_province'] ?? 'N/A'); ?></p>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-500 mb-1">School City / Municipality</label>
                                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['school_city'] ?? 'N/A'); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Course History Section -->
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
                                        <h4 class="text-lg font-semibold text-gray-900 mb-2">No Course History</h4>
                                        <p class="text-gray-500">This student has no other course applications on record.</p>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <div class="mt-8 md:mt-12 flex justify-center pb-8">
                                <button onclick="confirmViewDelete(<?php echo (int)$application['application_id']; ?>, '<?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?>')"
                                        class="inline-flex items-center justify-center px-8 py-4 border border-transparent text-base font-semibold rounded-xl shadow-lg text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transform transition-all duration-200 hover:scale-105">
                                    <i class="fas fa-trash mr-2"></i>Delete Application
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <script>
                    function confirmViewDelete(applicationId, studentName) {
                        const modal = document.createElement('div');
                        modal.className = 'fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm flex items-center justify-center z-50 transition-all duration-300';
                        modal.innerHTML = `
                            <div class="bg-white rounded-2xl shadow-2xl p-6 sm:p-8 max-w-md mx-4 transform transition-all border border-gray-100">
                                <div class="flex items-center justify-center w-16 h-16 mx-auto bg-gradient-to-br from-red-100 to-red-200 rounded-full shadow-lg mb-6">
                                    <div class="w-12 h-12 bg-gradient-to-br from-red-500 to-red-600 rounded-full flex items-center justify-center shadow-inner">
                                        <i class="fas fa-exclamation-triangle text-white text-xl"></i>
                                    </div>
                                </div>
                                <h3 class="text-2xl font-bold text-gray-900 text-center mb-3">Delete Application</h3>
                                <div class="w-12 h-1 bg-gradient-to-r from-red-500 to-red-600 rounded-full mx-auto mb-6"></div>
                                <p class="text-base text-gray-600 text-center mb-4 leading-relaxed">
                                    Are you sure you want to delete the application for <strong class="text-gray-900">${studentName}</strong>?
                                </p>
                                <p class="text-sm text-red-600 text-center mb-6 font-medium">
                                    Enter your admin password to confirm this action.
                                </p>
                                <form id="deleteForm" method="POST" action="admin-course-application.php">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="${applicationId}">
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
                <div class="py-4 md:py-6">
                    <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                        <div class="mb-8 mt-6">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                                <div>
                                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Edit Course Application</h1>
                                    <?php if ($application): ?>
                                        <p class="text-lg text-gray-600 mt-2">
                                            Student: <span class="font-semibold text-blue-600"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></span>
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($error_message): ?>
                            <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg animate-fade-in-up">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-red-700"><?php echo htmlspecialchars($error_message); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if ($application): ?>
                            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
                                    <div class="flex items-center space-x-3">
                                        <div class="bg-blue-100 rounded-xl p-2">
                                            <i class="fas fa-edit text-blue-600"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-900">Application Details</h3>
                                    </div>
                                </div>
                                <div class="p-8">
                                    <form method="POST" class="space-y-6">
                                        <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                                            <h4 class="text-sm font-semibold text-gray-700 mb-2">Student Information</h4>
                                            <p class="text-gray-900"><span class="font-medium">Name:</span> <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></p>
                                            <p class="text-gray-900"><span class="font-medium">Student ID:</span> <?php echo htmlspecialchars($application['student_id']); ?></p>
                                        </div>

                                        <div>
                                            <label for="course_id" class="block text-lg font-semibold text-gray-700 mb-3">
                                                Course <span class="text-red-500">*</span>
                                            </label>
                                            <select name="course_id" id="course_id" required
                                                    class="block w-full px-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200">
                                                <option value="">Select Course</option>
                                                <?php foreach ($courses_for_edit as $course): ?>
                                                    <option value="<?php echo (int)$course['course_id']; ?>"
                                                        <?php echo ($application['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div>
                                            <label for="nc_level" class="block text-lg font-semibold text-gray-700 mb-3">
                                                NC Level <span class="text-red-500">*</span>
                                            </label>
                                            <select name="nc_level" id="nc_level" required
                                                    class="block w-full px-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200">
                                                <option value="">Select NC Level</option>
                                                <?php
                                                $levels = ['NC I', 'NC II', 'NC III', 'NC IV', 'NC V'];
                                                foreach ($levels as $level): ?>
                                                    <option value="<?php echo $level; ?>" <?php echo ($application['nc_level'] == $level) ? 'selected' : ''; ?>>
                                                        <?php echo $level; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <label for="training_start" class="block text-lg font-semibold text-gray-700 mb-3">
                                                    Training Start Date <span class="text-red-500">*</span>
                                                </label>
                                                <input type="date" name="training_start" id="training_start" required
                                                       value="<?php echo htmlspecialchars($application['training_start'] ?? ''); ?>"
                                                       class="block w-full px-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200">
                                            </div>
                                            <div>
                                                <label for="training_end" class="block text-lg font-semibold text-gray-700 mb-3">
                                                    Training End Date <span class="text-red-500">*</span>
                                                </label>
                                                <input type="date" name="training_end" id="training_end" required
                                                       value="<?php echo htmlspecialchars($application['training_end'] ?? ''); ?>"
                                                       class="block w-full px-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200">
                                            </div>
                                        </div>

                                        <div>
                                            <label for="adviser" class="block text-lg font-semibold text-gray-700 mb-3">
                                                Assigned Adviser (Optional)
                                            </label>
                                            <select name="adviser" id="adviser"
                                                    class="block w-full px-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200">
                                                <option value="">Select Adviser (Optional)</option>
                                                <?php foreach ($advisers_for_edit as $adv): ?>
                                                    <option value="<?php echo htmlspecialchars($adv['adviser_name']); ?>"
                                                        <?php echo ($application['adviser'] == $adv['adviser_name']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($adv['adviser_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="flex flex-col sm:flex-row gap-4 pt-6">
                                            <button type="submit" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-transparent text-lg font-bold rounded-xl shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                                <i class="fas fa-save mr-3"></i>
                                                Update Application
                                            </button>
                                            <a href="admin-course-application.php?page=index" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-gray-300 text-lg font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                                <i class="fas fa-times mr-3"></i>
                                                Cancel
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <?php include 'components/admin-scripts.php'; ?>
</body>
</html>


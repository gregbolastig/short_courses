<?php
session_start();

require_once '../config/database.php';
require_once '../includes/auth_middleware.php';
require_once '../includes/system_activity_logger.php';

// Require admin authentication
requireAdmin();

$database = new Database();
$conn = $database->getConnection();
$logger = new SystemActivityLogger($conn);

// Used by sidebar notification badge (optional but nice to keep consistent)
try {
    $stmt = $conn->query("SELECT COUNT(*) as pending FROM shortcourse_students WHERE status = 'pending'");
    $pending_approvals = (int)($stmt->fetch(PDO::FETCH_ASSOC)['pending'] ?? 0);
} catch (PDOException $e) {
    $pending_approvals = 0;
}

// Routing: reserve `page` for which screen to show
$page = isset($_GET['page']) ? trim((string)$_GET['page']) : 'index';
$allowed_pages = ['index', 'add', 'edit', 'students', 'view'];
if (!in_array($page, $allowed_pages, true)) {
    $page = 'index';
}

// Prevent param collision with routing: use `p` for pagination
$p = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;

// Shared success toast state (used on index page)
$show_success_toast = false;
$success_toast_message = '';
$success_toast_course_name = '';

if (isset($_GET['success'])) {
    $success_type = (string)$_GET['success'];
    $show_success_toast = true;

    switch ($success_type) {
        case 'created':
            $success_toast_message = 'Course created successfully!';
            $success_toast_course_name = isset($_GET['course_name']) ? (string)$_GET['course_name'] : '';
            break;
        case 'updated':
            $success_toast_message = 'Course updated successfully!';
            $success_toast_course_name = isset($_GET['course_name']) ? (string)$_GET['course_name'] : '';
            break;
        case 'deleted':
            $success_toast_message = 'Course deleted successfully!';
            $success_toast_course_name = isset($_GET['course_name']) ? (string)$_GET['course_name'] : '';
            break;
        default:
            $show_success_toast = false;
    }
}

// -----------------------
// Page: index (courses list)
// -----------------------
$total_courses_count = 0;
$courses = [];
$total_pages = 0;
$total_courses = 0;
$search = '';

if ($page === 'index') {
    $page_title = 'Manage Courses';
    $breadcrumb_items = [
        ['title' => 'Manage Courses', 'icon' => 'fas fa-graduation-cap']
    ];

    // Handle delete operation with password verification
    if (isset($_POST['action'], $_POST['id'], $_POST['admin_password']) && $_POST['action'] === 'delete' && is_numeric($_POST['id'])) {
        try {
            $course_id_to_delete = (int)$_POST['id'];
            $admin_password = $_POST['admin_password'];
            
            // Verify admin password
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = :user_id");
            $stmt->bindParam(':user_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->execute();
            $admin = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$admin || !password_verify($admin_password, $admin['password'])) {
                $error_message = 'Invalid password. Deletion cancelled.';
            } else {
                // Password verified, proceed with deletion
                // Get course name before deleting
                $stmt = $conn->prepare("SELECT course_name FROM shortcourse_courses WHERE course_id = ?");
                $stmt->execute([$course_id_to_delete]);
                $course = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($course) {
                    $logger->log(
                        'course_deleted',
                        "Admin deleted course '{$course['course_name']}' (ID: {$course_id_to_delete})",
                        'admin',
                        $_SESSION['user_id'],
                        'course',
                        $course_id_to_delete
                    );

                    $stmt = $conn->prepare("DELETE FROM shortcourse_courses WHERE course_id = ?");
                    $stmt->execute([$course_id_to_delete]);

                    header("Location: " . basename(__FILE__) . "?page=index&success=deleted&course_name=" . urlencode($course['course_name']));
                    exit;
                } else {
                    $error_message = 'Course not found.';
                }
            }
        } catch (PDOException $e) {
            $error_message = 'Cannot delete course: ' . $e->getMessage();
        }
    }

    // Get courses with pagination
    try {
        $per_page = 10;
        $offset = ($p - 1) * $per_page;

        $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
        $search_condition = '';
        $params = [];

        if ($search !== '') {
            $search_condition = "WHERE course_name LIKE :search";
            $params[':search'] = "%{$search}%";
        }

        // Get total count
        $count_sql = "SELECT COUNT(*) as total FROM shortcourse_courses {$search_condition}";
        $stmt = $conn->prepare($count_sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $total_courses = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        $total_pages = (int)ceil($total_courses / $per_page);

        // Get courses
        $sql = "SELECT * FROM shortcourse_courses {$search_condition} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Stats
        $stmt = $conn->query("SELECT COUNT(*) as total FROM shortcourse_courses");
        $total_courses_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
        $total_courses_count = 0;
        $courses = [];
        $total_pages = 0;
        $total_courses = 0;
    }
}

// -----------------------
// Page: add (create course)
// -----------------------
if ($page === 'add') {
    $page_title = 'Add Course';
    $breadcrumb_items = [
        ['title' => 'Manage Courses', 'icon' => 'fas fa-graduation-cap', 'url' => 'admin-manage-courses.php?page=index'],
        ['title' => 'Add Course', 'icon' => 'fas fa-plus']
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $course_name = isset($_POST['course_name']) ? trim((string)$_POST['course_name']) : '';
            if ($course_name === '') {
                throw new RuntimeException('Course name is required.');
            }

            $stmt = $conn->prepare("INSERT INTO shortcourse_courses (course_name) VALUES (?)");
            $stmt->execute([$course_name]);

            $course_id = (int)$conn->lastInsertId();

            $logger->log(
                'course_created',
                "Admin created new course '{$course_name}'",
                'admin',
                $_SESSION['user_id'],
                'course',
                $course_id
            );

            header("Location: " . basename(__FILE__) . "?page=index&success=created&course_name=" . urlencode($course_name));
            exit;
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        } catch (Throwable $e) {
            $error_message = $e->getMessage();
        }
    }
}

// -----------------------
// Page: edit (update course)
// -----------------------
$course_id = 0;
$course = null;
if ($page === 'edit') {
    $page_title = 'Edit Course';

    $course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($course_id <= 0) {
        header('Location: ' . basename(__FILE__) . '?page=index');
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM shortcourse_courses WHERE course_id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$course) {
            header('Location: ' . basename(__FILE__) . '?page=index');
            exit;
        }

        $breadcrumb_items = [
            ['title' => 'Manage Courses', 'icon' => 'fas fa-graduation-cap', 'url' => 'admin-manage-courses.php?page=index'],
            ['title' => 'Edit: ' . $course['course_name'], 'icon' => 'fas fa-edit']
        ];
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            $course_name = isset($_POST['course_name']) ? trim((string)$_POST['course_name']) : '';
            if ($course_name === '') {
                throw new RuntimeException('Course name is required.');
            }

            $stmt = $conn->prepare("UPDATE shortcourse_courses SET course_name = ? WHERE course_id = ?");
            $stmt->execute([$course_name, $course_id]);

            $logger->log(
                'course_updated',
                "Admin updated course '{$course_name}' (ID: {$course_id})",
                'admin',
                $_SESSION['user_id'],
                'course',
                $course_id
            );

            header("Location: " . basename(__FILE__) . "?page=index&success=updated&course_name=" . urlencode($course_name));
            exit;
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        } catch (Throwable $e) {
            $error_message = $e->getMessage();
        }
    }
}

// -----------------------
// Page: students (student enrollments / approvals)
// -----------------------
$total_students = 0;
$approved_students = 0;
$students = [];
$active_courses = [];
$advisers = [];
$total_students_count = 0;

if ($page === 'students') {
    $page_title = 'Student Enrollments';

    // Handle approval action
    if (isset($_POST['action'], $_POST['student_id']) && $_POST['action'] === 'approve') {
        try {
            $student_id = (int)$_POST['student_id'];
            $course_id_selected = (int)($_POST['course_id'] ?? 0);
            $training_start = (string)($_POST['training_start'] ?? '');
            $training_end = (string)($_POST['training_end'] ?? '');
            $adviser_name = (string)($_POST['adviser'] ?? '');

            $stmt = $conn->prepare("SELECT course_name, nc_level FROM shortcourse_courses WHERE course_id = :course_id");
            $stmt->bindParam(':course_id', $course_id_selected, PDO::PARAM_INT);
            $stmt->execute();
            $course_row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$course_row) {
                throw new RuntimeException('Selected course not found.');
            }

            $stmt = $conn->prepare("UPDATE shortcourse_students SET
                status = 'approved',
                approved_by = :admin_id,
                approved_at = NOW(),
                course = :course,
                nc_level = :nc_level,
                training_start = :training_start,
                training_end = :training_end,
                adviser = :adviser
                WHERE id = :id");

            $stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':course', $course_row['course_name']);
            $stmt->bindParam(':nc_level', $course_row['nc_level']);
            $stmt->bindParam(':training_start', $training_start);
            $stmt->bindParam(':training_end', $training_end);
            $stmt->bindParam(':adviser', $adviser_name);
            $stmt->bindParam(':id', $student_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $success_message = 'Student registration approved successfully with course details.';
            } else {
                $error_message = 'Failed to approve student registration.';
            }
        } catch (Throwable $e) {
            $error_message = $e instanceof PDOException ? ('Database error: ' . $e->getMessage()) : $e->getMessage();
        }
    }

    // Handle rejection action (GET)
    if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'reject') {
        try {
            $student_id = (int)$_GET['id'];
            $stmt = $conn->prepare("UPDATE shortcourse_students SET status = 'rejected', approved_by = :admin_id, approved_at = NOW() WHERE id = :id");
            $stmt->bindParam(':admin_id', $_SESSION['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':id', $student_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $success_message = 'Student registration rejected successfully.';
            } else {
                $error_message = 'Failed to reject student registration.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }

    // Data + pagination
    try {
        $stmt = $conn->query("SELECT COUNT(*) as total FROM shortcourse_students");
        $total_students = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);

        $stmt = $conn->query("SELECT COUNT(*) as pending FROM shortcourse_students WHERE status = 'pending'");
        $pending_approvals = (int)($stmt->fetch(PDO::FETCH_ASSOC)['pending'] ?? 0);

        $stmt = $conn->query("SELECT COUNT(*) as approved FROM shortcourse_students WHERE status = 'approved'");
        $approved_students = (int)($stmt->fetch(PDO::FETCH_ASSOC)['approved'] ?? 0);

        $per_page = 10;
        $offset = ($p - 1) * $per_page;

        $search = isset($_GET['search']) ? trim((string)$_GET['search']) : '';
        $search_condition = '';
        $params = [];

        if ($search !== '') {
            $search_condition = "WHERE first_name LIKE :search OR last_name LIKE :search2 OR email LIKE :search3 OR uli LIKE :search4";
            $search_param = "%{$search}%";
            $params[':search'] = $search_param;
            $params[':search2'] = $search_param;
            $params[':search3'] = $search_param;
            $params[':search4'] = $search_param;
        }

        $count_sql = "SELECT COUNT(*) as total FROM shortcourse_students {$search_condition}";
        $stmt = $conn->prepare($count_sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        $total_students_count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
        $total_pages = (int)ceil($total_students_count / $per_page);

        $sql = "SELECT id, uli, first_name, last_name, email, status, course, nc_level, adviser, created_at
                FROM shortcourse_students {$search_condition}
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $conn->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->query("SELECT course_id, course_name, nc_level FROM shortcourse_courses WHERE is_active = 1 ORDER BY course_name");
        $active_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $conn->query("SELECT adviser_id, adviser_name FROM advisers ORDER BY adviser_name");
        $advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}

// -----------------------
// Page: view (view student details)
// -----------------------
$student_id = 0;
$student = null;
if ($page === 'view') {
    $page_title = 'View Student';
    $student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if ($student_id <= 0) {
        header('Location: ' . basename(__FILE__) . '?page=students');
        exit;
    }

    try {
        $stmt = $conn->prepare("SELECT * FROM shortcourse_students WHERE id = :id");
        $stmt->bindParam(':id', $student_id, PDO::PARAM_INT);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            header('Location: ' . basename(__FILE__) . '?page=students');
            exit;
        }

        $stmt = $conn->query("SELECT COUNT(*) as pending FROM shortcourse_students WHERE status = 'pending'");
        $pending_approvals = (int)($stmt->fetch(PDO::FETCH_ASSOC)['pending'] ?? 0);

        $stmt = $conn->query("SELECT adviser_id, adviser_name FROM advisers ORDER BY adviser_name");
        $advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title ?? 'Courses'); ?> - Student Registration System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#1e3a8a',
                            600: '#1e40af',
                            700: '#1d4ed8',
                            800: '#1e3a8a',
                            900: '#1e293b'
                        }
                    }
                }
            }
        }
    </script>
    <?php include 'components/admin-styles.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen bg-gray-50">
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
                                        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Course Management</h1>
                                        <p class="text-lg text-gray-600 mt-2">Organize and manage your educational course offerings</p>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <a href="admin-manage-courses.php?page=add" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-semibold rounded-lg shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                            <i class="fas fa-plus mr-2"></i>
                                            Add New Course
                                        </a>
                                        <a href="admin-manage-courses.php?page=students" class="inline-flex items-center px-6 py-3 border border-blue-300 text-base font-semibold rounded-lg shadow-sm text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                            <i class="fas fa-users mr-2"></i>
                                            Student Enrollments
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <?php if (isset($error_message)): ?>
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
                            <?php endif; ?>

                            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-blue-100 rounded-xl p-2">
                                                <i class="fas fa-list text-blue-600"></i>
                                            </div>
                                            <h3 class="text-xl font-bold text-gray-900">Course Directory</h3>
                                        </div>
                                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-3 sm:space-y-0 sm:space-x-4">
                                            <form method="GET" action="admin-manage-courses.php" class="relative">
                                                <input type="hidden" name="page" value="index">
                                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                    <i class="fas fa-search text-gray-400"></i>
                                                </div>
                                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                                       placeholder="Search courses..."
                                                       class="block w-full sm:w-80 pl-12 pr-4 py-3 border border-gray-300 rounded-xl leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm transition-all duration-200">
                                                <?php if ($search !== ''): ?>
                                                    <a href="admin-manage-courses.php?page=index" class="absolute inset-y-0 right-0 pr-4 flex items-center">
                                                        <i class="fas fa-times text-gray-400 hover:text-gray-600 transition-colors duration-200"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <?php if (empty($courses)): ?>
                                    <div class="text-center py-16">
                                        <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-full w-20 h-20 flex items-center justify-center mx-auto mb-6 shadow-lg">
                                            <i class="fas fa-graduation-cap text-blue-600 text-3xl"></i>
                                        </div>
                                        <h3 class="text-2xl font-bold text-gray-900 mb-3">No courses found</h3>
                                        <p class="text-lg text-gray-600 mb-8 px-4 max-w-md mx-auto">
                                            <?php echo $search !== '' ? 'No courses match your search criteria. Try adjusting your search terms.' : 'Ready to get started? Create your first course and begin building your educational offerings.'; ?>
                                        </p>
                                        <?php if ($search === ''): ?>
                                            <a href="admin-manage-courses.php?page=add" class="inline-flex items-center px-8 py-4 border border-transparent text-lg font-semibold rounded-xl shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                                <i class="fas fa-plus mr-3"></i>
                                                Create Your First Course
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                    <div class="hidden md:block overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gradient-to-r from-gray-50 to-gray-100">
                                                <tr>
                                                    <th class="px-8 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Course Name</th>
                                                    <th class="px-8 py-4 text-left text-sm font-bold text-gray-700 uppercase tracking-wider">Created</th>
                                                    <th class="px-8 py-4 text-center text-sm font-bold text-gray-700 uppercase tracking-wider">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($courses as $row): ?>
                                                    <tr class="hover:bg-blue-50 transition-all duration-200 border-b border-gray-100">
                                                        <td class="px-8 py-6">
                                                            <div class="flex items-center space-x-3">
                                                                <div class="bg-blue-100 rounded-lg p-2">
                                                                    <i class="fas fa-book text-blue-600"></i>
                                                                </div>
                                                                <div>
                                                                    <div class="text-lg font-semibold text-gray-900">
                                                                        <?php echo htmlspecialchars($row['course_name']); ?>
                                                                    </div>
                                                                    <div class="text-sm text-gray-500">Course ID: #<?php echo (int)$row['course_id']; ?></div>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td class="px-8 py-6">
                                                            <div class="flex items-center space-x-2">
                                                                <i class="fas fa-calendar-alt text-gray-400"></i>
                                                                <span class="text-sm font-medium text-gray-900">
                                                                    <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
                                                                </span>
                                                            </div>
                                                            <div class="text-xs text-gray-500 mt-1">
                                                                <?php echo date('g:i A', strtotime($row['created_at'])); ?>
                                                            </div>
                                                        </td>
                                                        <td class="px-8 py-6">
                                                            <div class="flex items-center justify-center space-x-3">
                                                                <a href="admin-manage-courses.php?page=edit&id=<?php echo (int)$row['course_id']; ?>"
                                                                   class="inline-flex items-center px-4 py-2 border border-blue-300 text-sm font-semibold rounded-lg text-blue-700 bg-blue-50 hover:bg-blue-100 hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 transform hover:scale-105 shadow-sm">
                                                                    <i class="fas fa-edit mr-2"></i>Edit
                                                                </a>

                                                                <button onclick="confirmDelete('<?php echo htmlspecialchars($row['course_name']); ?>', <?php echo (int)$row['course_id']; ?>)"
                                                                   class="inline-flex items-center px-4 py-2 border border-red-300 text-sm font-semibold rounded-lg text-red-700 bg-red-50 hover:bg-red-100 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200 transform hover:scale-105 shadow-sm">
                                                                    <i class="fas fa-trash mr-2"></i>Delete
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="md:hidden">
                                        <?php foreach ($courses as $row): ?>
                                            <div class="border-b border-gray-200 p-6 hover:bg-blue-50 transition-all duration-200">
                                                <div class="flex items-start justify-between mb-4">
                                                    <div class="flex items-center space-x-3 flex-1">
                                                        <div class="bg-blue-100 rounded-lg p-2">
                                                            <i class="fas fa-book text-blue-600"></i>
                                                        </div>
                                                        <div class="flex-1">
                                                            <h4 class="text-lg font-semibold text-gray-900">
                                                                <?php echo htmlspecialchars($row['course_name']); ?>
                                                            </h4>
                                                            <p class="text-sm text-gray-500">
                                                                Course ID: #<?php echo (int)$row['course_id']; ?>
                                                            </p>
                                                            <div class="flex items-center space-x-2 mt-2">
                                                                <i class="fas fa-calendar-alt text-gray-400 text-xs"></i>
                                                                <span class="text-sm text-gray-600">
                                                                    Created: <?php echo date('M j, Y', strtotime($row['created_at'])); ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center space-x-3">
                                                    <a href="admin-manage-courses.php?page=edit&id=<?php echo (int)$row['course_id']; ?>"
                                                       class="flex-1 inline-flex items-center justify-center px-4 py-3 border border-blue-300 text-sm font-semibold rounded-lg text-blue-700 bg-blue-50 hover:bg-blue-100 hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 transform hover:scale-105">
                                                        <i class="fas fa-edit mr-2"></i>Edit Course
                                                    </a>
                                                    <button onclick="confirmDelete('<?php echo htmlspecialchars($row['course_name']); ?>', <?php echo (int)$row['course_id']; ?>)"
                                                       class="flex-1 inline-flex items-center justify-center px-4 py-3 border border-red-300 text-sm font-semibold rounded-lg text-red-700 bg-red-50 hover:bg-red-100 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200 transform hover:scale-105">
                                                        <i class="fas fa-trash mr-2"></i>Delete
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if ($total_pages > 1): ?>
                                    <?php $offset = ($p - 1) * 10; ?>
                                    <div class="px-6 py-5 border-t border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                            <div class="text-sm font-medium text-gray-700">
                                                Showing <span class="font-bold text-gray-900"><?php echo $offset + 1; ?></span>
                                                to <span class="font-bold text-gray-900"><?php echo min($offset + 10, $total_courses); ?></span>
                                                of <span class="font-bold text-gray-900"><?php echo $total_courses; ?></span> courses
                                            </div>

                                            <div class="flex items-center space-x-2">
                                                <?php if ($p > 1): ?>
                                                    <a href="admin-manage-courses.php?page=index&p=<?php echo $p - 1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
                                                       class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm">
                                                        <i class="fas fa-chevron-left mr-2"></i>Previous
                                                    </a>
                                                <?php endif; ?>

                                                <div class="hidden sm:flex items-center space-x-1">
                                                    <?php for ($i = max(1, $p - 2); $i <= min($total_pages, $p + 2); $i++): ?>
                                                        <?php if ($i === $p): ?>
                                                            <span class="inline-flex items-center justify-center w-10 h-10 border-2 border-blue-500 rounded-lg text-sm font-bold text-white bg-blue-600 shadow-md"><?php echo $i; ?></span>
                                                        <?php else: ?>
                                                            <a href="admin-manage-courses.php?page=index&p=<?php echo $i; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
                                                               class="inline-flex items-center justify-center w-10 h-10 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 shadow-sm"><?php echo $i; ?></a>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </div>

                                                <?php if ($p < $total_pages): ?>
                                                    <a href="admin-manage-courses.php?page=index&p=<?php echo $p + 1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
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

                    <!-- Toast Container -->
                    <div id="toast-container" class="fixed top-4 right-4 z-[9999] flex flex-col gap-3 pointer-events-none"></div>

                    <!-- Delete Confirmation Modal -->
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
                                    <h3 class="text-xl font-bold text-gray-900 mb-2" id="modal-title">Delete Course</h3>
                                    <div class="w-12 h-1 bg-gradient-to-r from-red-500 to-red-600 rounded-full mx-auto"></div>
                                </div>

                                <div class="text-center mb-6">
                                    <div class="bg-gray-50 rounded-xl p-4 mb-4 border border-gray-200">
                                        <div class="flex items-center justify-center space-x-3 mb-2">
                                            <div class="bg-blue-100 rounded-lg p-2">
                                                <i class="fas fa-graduation-cap text-blue-600"></i>
                                            </div>
                                            <span class="font-semibold text-gray-900 text-lg" id="courseNameToDelete"></span>
                                        </div>
                                    </div>
                                    <p class="text-gray-600 leading-relaxed mb-4">
                                        This action will permanently remove the course from your system.
                                    </p>
                                    <p class="text-sm text-red-600 font-medium">
                                        Enter your admin password to confirm this action.
                                    </p>
                                </div>

                                <form id="deleteForm" method="POST" action="admin-manage-courses.php?page=index">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" id="deleteCourseId" value="">
                                    <div class="mb-6">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">Admin Password</label>
                                        <input type="password" name="admin_password" id="adminPassword" required
                                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                                               placeholder="Enter your password">
                                    </div>
                                    <div class="flex flex-col sm:flex-row-reverse gap-3">
                                        <button type="submit" class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-semibold rounded-xl shadow-lg text-white bg-gradient-to-r from-red-600 to-red-700 hover:from-red-700 hover:to-red-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transform transition-all duration-200 hover:scale-105">
                                            <i class="fas fa-trash mr-2"></i>
                                            Delete Course
                                        </button>
                                        <button type="button" onclick="closeDeleteModal()" class="flex-1 inline-flex items-center justify-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                            <i class="fas fa-times mr-2"></i>
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <script>
                        let courseToDelete = null;

                        function confirmDelete(courseName, courseId) {
                            courseToDelete = courseId;
                            document.getElementById('courseNameToDelete').textContent = courseName;
                            document.getElementById('deleteCourseId').value = courseId;
                            document.getElementById('adminPassword').value = '';
                            document.getElementById('deleteModal').classList.remove('hidden');
                            document.body.classList.add('overflow-hidden');
                            setTimeout(() => {
                                document.getElementById('adminPassword').focus();
                            }, 100);
                        }

                        function closeDeleteModal() {
                            courseToDelete = null;
                            document.getElementById('deleteModal').classList.add('hidden');
                            document.body.classList.remove('overflow-hidden');
                            document.getElementById('adminPassword').value = '';
                        }

                        // Toast notification function (consistent with admin-scripts.php)
                        function showToast(message, type = 'success') {
                            const container = document.getElementById('toast-container');
                            if (!container) return;
                            
                            const toast = document.createElement('div');
                            toast.className = 'transform transition-all duration-300 ease-in-out translate-x-full opacity-0 pointer-events-auto';
                            
                            const config = {
                                success: {
                                    bg: 'bg-gradient-to-r from-green-600 to-green-700',
                                    border: 'border-green-500',
                                    icon: 'fa-check-circle'
                                },
                                error: {
                                    bg: 'bg-gradient-to-r from-red-600 to-red-700',
                                    border: 'border-red-500',
                                    icon: 'fa-exclamation-circle'
                                },
                                warning: {
                                    bg: 'bg-gradient-to-r from-yellow-600 to-yellow-700',
                                    border: 'border-yellow-500',
                                    icon: 'fa-exclamation-triangle'
                                },
                                info: {
                                    bg: 'bg-gradient-to-r from-blue-600 to-blue-700',
                                    border: 'border-blue-500',
                                    icon: 'fa-info-circle'
                                }
                            };
                            
                            const style = config[type] || config.info;
                            
                            toast.innerHTML = `
                                <div class="${style.bg} text-white px-6 py-4 rounded-lg shadow-2xl border ${style.border} flex items-center space-x-3 min-w-[320px] max-w-md">
                                    <i class="fas ${style.icon} text-xl flex-shrink-0"></i>
                                    <span class="flex-1 font-medium text-sm">${escapeHtml(message)}</span>
                                    <button onclick="removeToast(this)" class="text-white hover:text-gray-200 transition flex-shrink-0 ml-2 focus:outline-none">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            `;
                            
                            container.appendChild(toast);
                            
                            // Trigger slide-in animation from right
                            setTimeout(() => {
                                toast.classList.remove('translate-x-full', 'opacity-0');
                            }, 10);
                            
                            // Auto remove after 5 seconds
                            const autoRemoveTimeout = setTimeout(() => {
                                removeToastElement(toast);
                            }, 5000);
                            
                            // Store timeout ID for manual removal
                            toast.dataset.timeoutId = autoRemoveTimeout;
                        }

                        function removeToast(button) {
                            const toast = button.closest('.transform');
                            if (toast) {
                                // Clear auto-remove timeout
                                if (toast.dataset.timeoutId) {
                                    clearTimeout(parseInt(toast.dataset.timeoutId));
                                }
                                removeToastElement(toast);
                            }
                        }

                        function removeToastElement(toast) {
                            toast.classList.add('translate-x-full', 'opacity-0');
                            setTimeout(() => {
                                if (toast.parentNode) {
                                    toast.remove();
                                }
                            }, 300);
                        }

                        function escapeHtml(text) {
                            const div = document.createElement('div');
                            div.textContent = text;
                            return div.innerHTML;
                        }

                        document.addEventListener('keydown', function(event) {
                            if (event.key === 'Escape') {
                                if (!document.getElementById('deleteModal').classList.contains('hidden')) {
                                    closeDeleteModal();
                                }
                            }
                        });

                        // Show success toast if needed
                        <?php if ($show_success_toast): ?>
                        document.addEventListener('DOMContentLoaded', function() {
                            const courseName = <?php echo json_encode($success_toast_course_name); ?>;
                            const message = <?php echo json_encode($success_toast_message); ?>;
                            const fullMessage = courseName ? `${message} Course: "${courseName}"` : message;
                            showToast(fullMessage, 'success');
                            
                            // Clean up URL parameters
                            const url = new URL(window.location);
                            url.searchParams.delete('success');
                            url.searchParams.delete('course_name');
                            window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : ''));
                        });
                        <?php endif; ?>
                    </script>
                <?php endif; ?>

                <?php if ($page === 'add'): ?>
                    <div class="py-4 md:py-6">
                        <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                            <div class="mb-8 mt-6">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                                    <div>
                                        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Add New Course</h1>
                                        <p class="text-lg text-gray-600 mt-2">Create a new course offering for your educational program</p>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <a href="admin-manage-courses.php?page=index" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                            <i class="fas fa-arrow-left mr-2"></i>
                                            Back to Courses
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <?php if (isset($error_message)): ?>
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
                            <?php endif; ?>

                            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
                                    <div class="flex items-center space-x-3">
                                        <div class="bg-blue-100 rounded-xl p-2">
                                            <i class="fas fa-plus text-blue-600"></i>
                                        </div>
                                        <h3 class="text-xl font-bold text-gray-900">Course Information</h3>
                                    </div>
                                </div>

                                <div class="p-8">
                                    <form method="POST" action="admin-manage-courses.php?page=add" class="space-y-8">
                                        <div>
                                            <label for="course_name" class="block text-lg font-semibold text-gray-700 mb-3">
                                                Course Name <span class="text-red-500">*</span>
                                            </label>
                                            <div class="relative">
                                                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                    <i class="fas fa-book text-gray-400"></i>
                                                </div>
                                                <input type="text" name="course_name" id="course_name" required
                                                       value="<?php echo isset($_POST['course_name']) ? htmlspecialchars((string)$_POST['course_name']) : ''; ?>"
                                                       class="block w-full pl-12 pr-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200"
                                                       placeholder="Automotive servicing">
                                            </div>
                                            <p class="mt-2 text-sm text-gray-500">Enter the name of the course you want to offer</p>
                                        </div>

                                        <div class="flex flex-col sm:flex-row gap-4 pt-6">
                                            <button type="submit" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-transparent text-lg font-bold rounded-xl shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                                <i class="fas fa-save mr-3"></i>
                                                Create Course
                                            </button>

                                            <a href="admin-manage-courses.php?page=index" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-gray-300 text-lg font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                                <i class="fas fa-times mr-3"></i>
                                                Cancel
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($page === 'edit'): ?>
                    <div class="py-4 md:py-6">
                        <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                            <div class="mb-8 mt-6">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                                    <div>
                                        <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Edit Course</h1>
                                        <p class="text-lg text-gray-600 mt-2">
                                            Editing: <span class="font-semibold text-blue-600"><?php echo htmlspecialchars($course['course_name'] ?? ''); ?></span>
                                        </p>
                                    </div>
                                    <div class="flex items-center space-x-4">
                                        <a href="admin-manage-courses.php?page=index" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                            <i class="fas fa-arrow-left mr-2"></i>
                                            Back to Courses
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <?php if (isset($error_message)): ?>
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
                            <?php endif; ?>

                            <?php if ($course): ?>
                                <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                                    <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
                                        <div class="flex items-center space-x-3">
                                            <div class="bg-blue-100 rounded-xl p-2">
                                                <i class="fas fa-edit text-blue-600"></i>
                                            </div>
                                            <h3 class="text-xl font-bold text-gray-900">Course Information</h3>
                                        </div>
                                    </div>

                                    <div class="p-8">
                                        <form method="POST" action="admin-manage-courses.php?page=edit&id=<?php echo (int)$course_id; ?>" class="space-y-8">
                                            <div>
                                                <label for="course_name" class="block text-lg font-semibold text-gray-700 mb-3">
                                                    Course Name <span class="text-red-500">*</span>
                                                </label>
                                                <div class="relative">
                                                    <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                        <i class="fas fa-book text-gray-400"></i>
                                                    </div>
                                                    <input type="text" name="course_name" id="course_name" required
                                                           value="<?php echo htmlspecialchars((string)$course['course_name']); ?>"
                                                           class="block w-full pl-12 pr-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200"
                                                           placeholder="Automotive servicing">
                                                </div>
                                                <p class="mt-2 text-sm text-gray-500">Update the name of this course</p>
                                            </div>

                                            <div class="flex flex-col sm:flex-row gap-4 pt-6">
                                                <button type="submit" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-transparent text-lg font-bold rounded-xl shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                                    <i class="fas fa-save mr-3"></i>
                                                    Update Course
                                                </button>

                                                <a href="admin-manage-courses.php?page=index" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-gray-300 text-lg font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
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

                <?php if ($page === 'students'): ?>
                    <div class="py-4 md:py-6">
                        <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                            <nav class="flex mb-6" aria-label="Breadcrumb">
                                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                                    <li class="inline-flex items-center">
                                        <a href="admin-dashboard.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600 transition-colors duration-200">
                                            <i class="fas fa-home mr-2"></i>
                                            Dashboard
                                        </a>
                                    </li>
                                    <li>
                                        <div class="flex items-center">
                                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                            <a href="admin-manage-courses.php?page=index" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2 transition-colors duration-200">Manage Courses</a>
                                        </div>
                                    </li>
                                    <li>
                                        <div class="flex items-center">
                                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                            <span class="ml-1 text-sm font-medium text-blue-600 md:ml-2">Student Enrollments</span>
                                        </div>
                                    </li>
                                </ol>
                            </nav>

                            <div class="mb-6">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                    <div>
                                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Student Enrollments</h1>
                                        <p class="text-gray-600 mt-2">Manage student registrations, approvals, and course assignments</p>
                                    </div>
                                    <div class="flex items-center space-x-3">
                                        <a href="admin-manage-courses.php?page=index" class="inline-flex items-center px-4 py-2 border border-blue-300 text-sm font-medium rounded-md shadow-sm text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                            <i class="fas fa-graduation-cap mr-2"></i>
                                            Manage Courses
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <?php if (isset($error_message)): ?>
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
                            <?php endif; ?>

                            <?php if (isset($success_message)): ?>
                                <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-r-lg animate-fade-in">
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

                            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 md:gap-6 mb-6 md:mb-8">
                                <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl">
                                    <div class="p-4 md:p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <div class="bg-blue-100 rounded-xl p-3 md:p-4 shadow-inner">
                                                    <i class="fas fa-users text-blue-600 text-xl md:text-2xl"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4 md:ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-sm font-medium text-gray-500 truncate">Total Students</dt>
                                                    <dd class="text-2xl md:text-3xl font-bold text-gray-900"><?php echo (int)$total_students; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl">
                                    <div class="p-4 md:p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <div class="bg-yellow-100 rounded-xl p-3 md:p-4 shadow-inner">
                                                    <i class="fas fa-clock text-yellow-600 text-xl md:text-2xl"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4 md:ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-sm font-medium text-gray-500 truncate">Pending Approvals</dt>
                                                    <dd class="text-2xl md:text-3xl font-bold text-yellow-600"><?php echo (int)$pending_approvals; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl">
                                    <div class="p-4 md:p-6">
                                        <div class="flex items-center">
                                            <div class="flex-shrink-0">
                                                <div class="bg-green-100 rounded-xl p-3 md:p-4 shadow-inner">
                                                    <i class="fas fa-check-circle text-green-600 text-xl md:text-2xl"></i>
                                                </div>
                                            </div>
                                            <div class="ml-4 md:ml-5 w-0 flex-1">
                                                <dl>
                                                    <dt class="text-sm font-medium text-gray-500 truncate">Approved Students</dt>
                                                    <dd class="text-2xl md:text-3xl font-bold text-green-600"><?php echo (int)$approved_students; ?></dd>
                                                </dl>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100">
                                <div class="px-4 md:px-6 py-4 border-b border-gray-200 bg-gray-50">
                                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 md:gap-4">
                                        <h3 class="text-base md:text-lg font-semibold text-gray-900">
                                            <i class="fas fa-graduation-cap text-gray-500 mr-2"></i>
                                            Student Course Management
                                        </h3>
                                        <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
                                            <form method="GET" action="admin-manage-courses.php" class="relative">
                                                <input type="hidden" name="page" value="students">
                                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                    <i class="fas fa-search text-gray-400"></i>
                                                </div>
                                                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                                       placeholder="Search students..."
                                                       class="block w-full sm:w-64 pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 text-sm">
                                                <?php if ($search !== ''): ?>
                                                    <a href="admin-manage-courses.php?page=students" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                                        <i class="fas fa-times text-gray-400 hover:text-gray-600"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </form>
                                        </div>
                                    </div>
                                </div>

                                <?php if (empty($students)): ?>
                                    <div class="text-center py-8 md:py-12">
                                        <div class="bg-gray-100 rounded-full w-12 h-12 md:w-16 md:h-16 flex items-center justify-center mx-auto mb-4">
                                            <i class="fas fa-users text-gray-400 text-xl md:text-2xl"></i>
                                        </div>
                                        <h3 class="text-base md:text-lg font-medium text-gray-900 mb-2">No students found</h3>
                                        <p class="text-sm md:text-base text-gray-500 mb-4 px-4">
                                            <?php echo $search !== '' ? 'No students match your search criteria.' : 'Students will appear here once they register.'; ?>
                                        </p>
                                    </div>
                                <?php else: ?>
                                    <div class="hidden md:block overflow-x-auto">
                                        <table class="min-w-full divide-y divide-gray-200">
                                            <thead class="bg-gray-50">
                                                <tr>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ULI</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Adviser</th>
                                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white divide-y divide-gray-200">
                                                <?php foreach ($students as $row): ?>
                                                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm font-medium text-gray-900">
                                                                <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                            </div>
                                                            <div class="text-sm text-gray-500">
                                                                <?php echo htmlspecialchars($row['email']); ?>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900 font-mono bg-gray-100 px-2 py-1 rounded">
                                                                <?php echo htmlspecialchars($row['uli']); ?>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900">
                                                                <?php echo $row['course'] ? htmlspecialchars($row['course']) : '-'; ?>
                                                            </div>
                                                            <?php if (!empty($row['nc_level'])): ?>
                                                                <div class="text-xs text-gray-500">
                                                                    <?php echo htmlspecialchars($row['nc_level']); ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <?php
                                                            $status_classes = [
                                                                'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                                'approved' => 'bg-green-100 text-green-800 border-green-200',
                                                                'rejected' => 'bg-red-100 text-red-800 border-red-200'
                                                            ];
                                                            $status_class = $status_classes[$row['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                                            ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                                                <?php echo ucfirst($row['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap">
                                                            <div class="text-sm text-gray-900">
                                                                <?php echo $row['adviser'] ? htmlspecialchars($row['adviser']) : '-'; ?>
                                                            </div>
                                                        </td>
                                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                            <div class="flex items-center space-x-3">
                                                                <a href="admin-manage-courses.php?page=view&id=<?php echo (int)$row['id']; ?>"
                                                                   class="text-primary-600 hover:text-primary-900 flex items-center">
                                                                    <i class="fas fa-eye mr-1"></i>View
                                                                </a>

                                                                <?php if ($row['status'] === 'pending'): ?>
                                                                    <button onclick="openApprovalModal(<?php echo (int)$row['id']; ?>, '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>')"
                                                                       class="text-green-600 hover:text-green-900 flex items-center">
                                                                        <i class="fas fa-check mr-1"></i>Approve
                                                                    </button>
                                                                    <a href="admin-manage-courses.php?page=students&action=reject&id=<?php echo (int)$row['id']; ?>"
                                                                       class="text-red-600 hover:text-red-900 flex items-center"
                                                                       onclick="return confirm('Are you sure you want to reject this student?')">
                                                                        <i class="fas fa-times mr-1"></i>Reject
                                                                    </a>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="md:hidden">
                                        <?php foreach ($students as $row): ?>
                                            <div class="border-b border-gray-200 p-4">
                                                <div class="flex items-start justify-between">
                                                    <div class="flex-1">
                                                        <h4 class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>
                                                        </h4>
                                                        <p class="text-sm text-gray-500 mt-1"><?php echo htmlspecialchars($row['email']); ?></p>
                                                        <p class="text-xs text-gray-500 mt-1">ULI: <?php echo htmlspecialchars($row['uli']); ?></p>
                                                        <?php if (!empty($row['course'])): ?>
                                                            <p class="text-xs text-gray-500 mt-1">
                                                                Course: <?php echo htmlspecialchars($row['course']); ?>
                                                                <?php if (!empty($row['nc_level'])): ?>
                                                                    (<?php echo htmlspecialchars($row['nc_level']); ?>)
                                                                <?php endif; ?>
                                                            </p>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="ml-4">
                                                        <?php
                                                        $status_classes = [
                                                            'pending' => 'bg-yellow-100 text-yellow-800',
                                                            'approved' => 'bg-green-100 text-green-800',
                                                            'rejected' => 'bg-red-100 text-red-800'
                                                        ];
                                                        $status_class = $status_classes[$row['status']] ?? 'bg-gray-100 text-gray-800';
                                                        ?>
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                                                            <?php echo ucfirst($row['status']); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="mt-3 flex items-center space-x-4 text-sm">
                                                    <a href="admin-manage-courses.php?page=view&id=<?php echo (int)$row['id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                        <i class="fas fa-eye mr-1"></i>View
                                                    </a>
                                                    <?php if ($row['status'] === 'pending'): ?>
                                                        <button onclick="openApprovalModal(<?php echo (int)$row['id']; ?>, '<?php echo htmlspecialchars($row['first_name'] . ' ' . $row['last_name']); ?>')"
                                                           class="text-green-600 hover:text-green-900">
                                                            <i class="fas fa-check mr-1"></i>Approve
                                                        </button>
                                                        <a href="admin-manage-courses.php?page=students&action=reject&id=<?php echo (int)$row['id']; ?>"
                                                           class="text-red-600 hover:text-red-900"
                                                           onclick="return confirm('Are you sure you want to reject this student?')">
                                                            <i class="fas fa-times mr-1"></i>Reject
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <?php if (($total_pages ?? 0) > 1): ?>
                                    <?php $offset = ($p - 1) * 10; ?>
                                    <div class="px-4 md:px-6 py-4 border-t border-gray-200 bg-gray-50">
                                        <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                            <div class="text-sm text-gray-700">
                                                Showing <?php echo $offset + 1; ?> to <?php echo min($offset + 10, $total_students_count); ?> of <?php echo $total_students_count; ?> students
                                            </div>

                                            <div class="flex items-center space-x-2">
                                                <?php if ($p > 1): ?>
                                                    <a href="admin-manage-courses.php?page=students&p=<?php echo $p - 1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
                                                       class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
                                                        <i class="fas fa-chevron-left mr-1"></i>Previous
                                                    </a>
                                                <?php endif; ?>

                                                <div class="hidden sm:flex items-center space-x-1">
                                                    <?php for ($i = max(1, $p - 2); $i <= min($total_pages, $p + 2); $i++): ?>
                                                        <?php if ($i === $p): ?>
                                                            <span class="inline-flex items-center justify-center w-8 h-8 border border-blue-500 rounded text-sm font-medium text-white bg-blue-600 shadow-md"><?php echo $i; ?></span>
                                                        <?php else: ?>
                                                            <a href="admin-manage-courses.php?page=students&p=<?php echo $i; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
                                                               class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200"><?php echo $i; ?></a>
                                                        <?php endif; ?>
                                                    <?php endfor; ?>
                                                </div>

                                                <?php if ($p < $total_pages): ?>
                                                    <a href="admin-manage-courses.php?page=students&p=<?php echo $p + 1; ?><?php echo $search !== '' ? '&search=' . urlencode($search) : ''; ?>"
                                                       class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
                                                        Next<i class="fas fa-chevron-right ml-1"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div id="approvalModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
                        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeApprovalModal()"></div>

                            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                <form id="approvalForm" method="POST" action="admin-manage-courses.php?page=students">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="student_id" id="modalStudentId">

                                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                        <div class="sm:flex sm:items-start">
                                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                                                <i class="fas fa-check text-green-600"></i>
                                            </div>
                                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Approve Student Registration</h3>
                                                <p class="text-sm text-gray-500 mb-4">
                                                    Approving: <span id="modalStudentName" class="font-semibold"></span>
                                                </p>

                                                <div class="space-y-4">
                                                    <div>
                                                        <label for="course_id" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                                                        <select name="course_id" id="course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                            <option value="">Select Course</option>
                                                            <?php foreach ($active_courses as $c): ?>
                                                                <option value="<?php echo (int)$c['course_id']; ?>">
                                                                    <?php echo htmlspecialchars($c['course_name'] . ' - ' . $c['nc_level']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="grid grid-cols-2 gap-4">
                                                        <div>
                                                            <label for="training_start" class="block text-sm font-medium text-gray-700 mb-1">Training Start</label>
                                                            <input type="date" name="training_start" id="training_start" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                        </div>
                                                        <div>
                                                            <label for="training_end" class="block text-sm font-medium text-gray-700 mb-1">Training End</label>
                                                            <input type="date" name="training_end" id="training_end" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label for="adviser" class="block text-sm font-medium text-gray-700 mb-1">Adviser</label>
                                                        <select name="adviser" id="adviser" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                            <option value="">Select Adviser</option>
                                                            <?php foreach ($advisers as $a): ?>
                                                                <option value="<?php echo htmlspecialchars($a['adviser_name']); ?>">
                                                                    <?php echo htmlspecialchars($a['adviser_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors duration-200">
                                            <i class="fas fa-check mr-2"></i>Approve Student
                                        </button>
                                        <button type="button" onclick="closeApprovalModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors duration-200">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <script>
                        function openApprovalModal(studentId, studentName) {
                            document.getElementById('modalStudentId').value = studentId;
                            document.getElementById('modalStudentName').textContent = studentName;
                            document.getElementById('approvalModal').classList.remove('hidden');
                        }

                        function closeApprovalModal() {
                            document.getElementById('approvalModal').classList.add('hidden');
                            document.getElementById('approvalForm').reset();
                        }

                        document.getElementById('approvalModal').addEventListener('click', function(e) {
                            if (e.target === this) {
                                closeApprovalModal();
                            }
                        });
                    </script>
                <?php endif; ?>

                <?php if ($page === 'view'): ?>
                    <div class="py-4 md:py-6">
                        <div class="max-w-4xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                            <div class="mb-6">
                                <a href="admin-manage-courses.php?page=students" class="inline-flex items-center text-blue-600 hover:text-blue-800 transition-colors duration-200">
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    Back to Student Enrollments
                                </a>
                            </div>

                            <?php if (isset($error_message)): ?>
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
                            <?php endif; ?>

                            <?php if ($student): ?>
                                <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100">
                                    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                                        <h2 class="text-xl font-semibold text-gray-900">Student Details</h2>
                                    </div>

                                    <div class="p-6">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                            <div>
                                                <h3 class="text-lg font-medium text-gray-900 mb-4">Personal Information</h3>
                                                <dl class="space-y-3">
                                                    <div>
                                                        <dt class="text-sm font-medium text-gray-500">Full Name</dt>
                                                        <dd class="text-sm text-gray-900"><?php echo htmlspecialchars($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']); ?></dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-sm font-medium text-gray-500">ULI</dt>
                                                        <dd class="text-sm text-gray-900 font-mono bg-gray-100 px-2 py-1 rounded inline-block"><?php echo htmlspecialchars($student['uli']); ?></dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-sm font-medium text-gray-500">Email</dt>
                                                        <dd class="text-sm text-gray-900"><?php echo htmlspecialchars($student['email']); ?></dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-sm font-medium text-gray-500">Contact Number</dt>
                                                        <dd class="text-sm text-gray-900"><?php echo htmlspecialchars($student['contact_number']); ?></dd>
                                                    </div>
                                                </dl>
                                            </div>

                                            <div>
                                                <h3 class="text-lg font-medium text-gray-900 mb-4">Course Information</h3>
                                                <dl class="space-y-3">
                                                    <div>
                                                        <dt class="text-sm font-medium text-gray-500">Status</dt>
                                                        <dd class="text-sm">
                                                            <?php
                                                            $status_classes = [
                                                                'pending' => 'bg-yellow-100 text-yellow-800 border-yellow-200',
                                                                'approved' => 'bg-green-100 text-green-800 border-green-200',
                                                                'rejected' => 'bg-red-100 text-red-800 border-red-200'
                                                            ];
                                                            $status_class = $status_classes[$student['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                                            ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                                                <?php echo ucfirst($student['status']); ?>
                                                            </span>
                                                        </dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-sm font-medium text-gray-500">Course</dt>
                                                        <dd class="text-sm text-gray-900"><?php echo $student['course'] ? htmlspecialchars($student['course']) : 'Not assigned'; ?></dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-sm font-medium text-gray-500">NC Level</dt>
                                                        <dd class="text-sm text-gray-900"><?php echo $student['nc_level'] ? htmlspecialchars($student['nc_level']) : 'Not assigned'; ?></dd>
                                                    </div>
                                                    <div>
                                                        <dt class="text-sm font-medium text-gray-500">Adviser</dt>
                                                        <dd class="text-sm text-gray-900"><?php echo $student['adviser'] ? htmlspecialchars($student['adviser']) : 'Not assigned'; ?></dd>
                                                    </div>
                                                </dl>
                                            </div>
                                        </div>

                                        <?php if ($student['status'] === 'pending'): ?>
                                            <div class="mt-6 pt-6 border-t border-gray-200">
                                                <div class="flex space-x-3">
                                                    <button onclick="openApprovalModal(<?php echo (int)$student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')"
                                                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors duration-200">
                                                        <i class="fas fa-check mr-2"></i>
                                                        Approve Student
                                                    </button>
                                                    <a href="admin-manage-courses.php?page=students&action=reject&id=<?php echo (int)$student['id']; ?>"
                                                       class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200"
                                                       onclick="return confirm('Are you sure you want to reject this student?')">
                                                        <i class="fas fa-times mr-2"></i>
                                                        Reject Student
                                                    </a>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div id="approvalModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
                        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeApprovalModal()"></div>

                            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                                <form id="approvalForm" method="POST" action="admin-manage-courses.php?page=students">
                                    <input type="hidden" name="action" value="approve">
                                    <input type="hidden" name="student_id" id="modalStudentId">

                                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                        <div class="sm:flex sm:items-start">
                                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                                                <i class="fas fa-check text-green-600"></i>
                                            </div>
                                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">Approve Student Registration</h3>
                                                <p class="text-sm text-gray-500 mb-4">
                                                    Approving: <span id="modalStudentName" class="font-semibold"></span>
                                                </p>

                                                <div class="space-y-4">
                                                    <div>
                                                        <label for="course_id" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                                                        <select name="course_id" id="course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                            <option value="">Select Course</option>
                                                            <?php
                                                            try {
                                                                $stmt = $conn->query("SELECT course_id, course_name, nc_level FROM shortcourse_courses WHERE is_active = 1 ORDER BY course_name");
                                                                $active_courses_view = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                                            } catch (PDOException $e) {
                                                                $active_courses_view = [];
                                                            }
                                                            ?>
                                                            <?php foreach ($active_courses_view as $c): ?>
                                                                <option value="<?php echo (int)$c['course_id']; ?>">
                                                                    <?php echo htmlspecialchars($c['course_name'] . ' - ' . $c['nc_level']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>

                                                    <div class="grid grid-cols-2 gap-4">
                                                        <div>
                                                            <label for="training_start" class="block text-sm font-medium text-gray-700 mb-1">Training Start</label>
                                                            <input type="date" name="training_start" id="training_start" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                        </div>
                                                        <div>
                                                            <label for="training_end" class="block text-sm font-medium text-gray-700 mb-1">Training End</label>
                                                            <input type="date" name="training_end" id="training_end" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                        </div>
                                                    </div>

                                                    <div>
                                                        <label for="adviser" class="block text-sm font-medium text-gray-700 mb-1">Adviser</label>
                                                        <select name="adviser" id="adviser" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                                            <option value="">Select Adviser</option>
                                                            <?php foreach (($advisers ?? []) as $a): ?>
                                                                <option value="<?php echo htmlspecialchars($a['adviser_name']); ?>">
                                                                    <?php echo htmlspecialchars($a['adviser_name']); ?>
                                                                </option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                        <button type="submit" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-green-600 text-base font-medium text-white hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 sm:ml-3 sm:w-auto sm:text-sm transition-colors duration-200">
                                            <i class="fas fa-check mr-2"></i>Approve Student
                                        </button>
                                        <button type="button" onclick="closeApprovalModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors duration-200">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>

                    <script>
                        function openApprovalModal(studentId, studentName) {
                            document.getElementById('modalStudentId').value = studentId;
                            document.getElementById('modalStudentName').textContent = studentName;
                            document.getElementById('approvalModal').classList.remove('hidden');
                        }

                        function closeApprovalModal() {
                            document.getElementById('approvalModal').classList.add('hidden');
                            document.getElementById('approvalForm').reset();
                        }
                    </script>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <?php include 'components/admin-scripts.php'; ?>
</body>
</html>

                        </button>
                        <button type="button" onclick="closeApprovalModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm transition-colors duration-200">
                            <i class="fas fa-times mr-2"></i>Cancel
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php include 'components/admin-scripts.php'; ?>
    
    <script>
        // Add event listeners when page loads
        document.addEventListener('DOMContentLoaded', function() {
            // Show success toast if needed
            <?php if ($show_success_toast): ?>
                const courseName = <?php echo json_encode($success_toast_course_name); ?>;
                const message = <?php echo json_encode($success_toast_message); ?>;
                const fullMessage = courseName ? `${message} Course: "${courseName}"` : message;
                showToast(fullMessage, 'success');
                
                // Clean up URL parameters
                const url = new URL(window.location);
                url.searchParams.delete('success');
                url.searchParams.delete('course_name');
                window.history.replaceState({}, document.title, url.pathname + (url.search ? url.search : ''));
            <?php endif; ?>
        });
        
        function showDeleteModal(courseId, courseName) {
            document.getElementById('deleteModal').classList.remove('hidden');
            document.getElementById('deleteCourseId').value = courseId;
            document.getElementById('deleteCourseName').textContent = courseName;
            document.body.style.overflow = 'hidden';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.add('hidden');
            document.getElementById('adminPassword').value = '';
            document.body.style.overflow = 'auto';
        }
        
        function showApprovalModal(studentId, studentName) {
            document.getElementById('approvalModal').classList.remove('hidden');
            document.getElementById('approvalStudentId').value = studentId;
            document.getElementById('approvalStudentName').textContent = studentName;
            document.body.style.overflow = 'hidden';
        }
        
        function closeApprovalModal() {
            document.getElementById('approvalModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
    </script>
</body>
</html>
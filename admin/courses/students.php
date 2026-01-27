<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

// Set page title
$page_title = 'Student Enrollments';

// Handle approval/rejection actions
if (isset($_POST['action']) && isset($_POST['student_id'])) {
    $action = $_POST['action'];
    $student_id = $_POST['student_id'];
    
    if ($action === 'approve') {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            // Get form data
            $course_id = $_POST['course_id'];
            $training_start = $_POST['training_start'];
            $training_end = $_POST['training_end'];
            $adviser = $_POST['adviser'];
            
            // Get course details
            $stmt = $conn->prepare("SELECT course_name, nc_level FROM courses WHERE course_id = :course_id");
            $stmt->bindParam(':course_id', $course_id);
            $stmt->execute();
            $course = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Update student with approval details
            $stmt = $conn->prepare("UPDATE students SET 
                status = 'approved', 
                approved_by = :admin_id, 
                approved_at = NOW(),
                course = :course,
                nc_level = :nc_level,
                training_start = :training_start,
                training_end = :training_end,
                adviser = :adviser
                WHERE id = :id");
            
            $stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $stmt->bindParam(':course', $course['course_name']);
            $stmt->bindParam(':nc_level', $course['nc_level']);
            $stmt->bindParam(':training_start', $training_start);
            $stmt->bindParam(':training_end', $training_end);
            $stmt->bindParam(':adviser', $adviser);
            $stmt->bindParam(':id', $student_id);
            
            if ($stmt->execute()) {
                $success_message = 'Student registration approved successfully with course details.';
            } else {
                $error_message = 'Failed to approve student registration.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle simple rejection actions (from GET request)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $student_id = $_GET['id'];
    
    if ($action === 'reject') {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            $stmt = $conn->prepare("UPDATE students SET status = 'rejected', approved_by = :admin_id, approved_at = NOW() WHERE id = :id");
            $stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $stmt->bindParam(':id', $student_id);
            
            if ($stmt->execute()) {
                $success_message = 'Student registration rejected successfully.';
            } else {
                $error_message = 'Failed to reject student registration.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get statistics and data
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Total students
    $stmt = $conn->query("SELECT COUNT(*) as total FROM students");
    $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Pending approvals
    $stmt = $conn->query("SELECT COUNT(*) as pending FROM students WHERE status = 'pending'");
    $pending_approvals = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    
    // Approved students
    $stmt = $conn->query("SELECT COUNT(*) as approved FROM students WHERE status = 'approved'");
    $approved_students = $stmt->fetch(PDO::FETCH_ASSOC)['approved'];
    
    // Pagination for students
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    // Search functionality
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $search_condition = '';
    $params = [];
    
    if (!empty($search)) {
        $search_condition = "WHERE first_name LIKE :search OR last_name LIKE :search2 OR email LIKE :search3 OR uli LIKE :search4";
        $search_param = "%$search%";
        $params[':search'] = $search_param;
        $params[':search2'] = $search_param;
        $params[':search3'] = $search_param;
        $params[':search4'] = $search_param;
    }
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM students $search_condition";
    $stmt = $conn->prepare($count_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_students_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_students_count / $per_page);
    
    // Get students with pagination
    $sql = "SELECT id, uli, first_name, last_name, email, status, course, nc_level, adviser, created_at FROM students $search_condition ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get active courses for dropdown
    $stmt = $conn->query("SELECT course_id, course_name, nc_level FROM courses WHERE is_active = 1 ORDER BY course_name");
    $active_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Enrollments - Student Registration System</title>
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
    <?php include '../components/admin-styles.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen bg-gray-50">
        <?php include '../components/sidebar.php'; ?>
        
        <!-- Main content wrapper -->
        <div id="main-content" class="min-h-screen transition-all duration-300 ease-in-out ml-0 md:ml-64">
            <?php include '../components/header.php'; ?>
            
            <!-- Main content area -->
            <main class="overflow-y-auto focus:outline-none">
                <div class="py-4 md:py-6">
                    <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                        
                        <!-- Breadcrumb -->
                        <nav class="flex mb-6" aria-label="Breadcrumb">
                            <ol class="inline-flex items-center space-x-1 md:space-x-3">
                                <li class="inline-flex items-center">
                                    <a href="../dashboard.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600 transition-colors duration-200">
                                        <i class="fas fa-home mr-2"></i>
                                        Dashboard
                                    </a>
                                </li>
                                <li>
                                    <div class="flex items-center">
                                        <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                                        <a href="index.php" class="ml-1 text-sm font-medium text-gray-700 hover:text-blue-600 md:ml-2 transition-colors duration-200">Manage Courses</a>
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

                        <!-- Page Header -->
                        <div class="mb-6">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Student Enrollments</h1>
                                    <p class="text-gray-600 mt-2">Manage student registrations, approvals, and course assignments</p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <a href="index.php" class="inline-flex items-center px-4 py-2 border border-blue-300 text-sm font-medium rounded-md shadow-sm text-blue-700 bg-blue-50 hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                        <i class="fas fa-graduation-cap mr-2"></i>
                                        Manage Courses
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Alerts -->
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

                        <!-- Statistics Cards -->
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
                                                <dd class="text-2xl md:text-3xl font-bold text-gray-900"><?php echo $total_students; ?></dd>
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
                                                <dd class="text-2xl md:text-3xl font-bold text-yellow-600"><?php echo $pending_approvals; ?></dd>
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
                                                <dd class="text-2xl md:text-3xl font-bold text-green-600"><?php echo $approved_students; ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div> 
                       <!-- Students Table -->
                        <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100">
                            <div class="px-4 md:px-6 py-4 border-b border-gray-200 bg-gray-50">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 md:gap-4">
                                    <h3 class="text-base md:text-lg font-semibold text-gray-900">
                                        <i class="fas fa-graduation-cap text-gray-500 mr-2"></i>
                                        Student Course Management
                                    </h3>
                                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
                                        <!-- Search Bar -->
                                        <form method="GET" action="" class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-search text-gray-400"></i>
                                            </div>
                                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                                   placeholder="Search students..." 
                                                   class="block w-full sm:w-64 pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 text-sm">
                                            <?php if (!empty($search)): ?>
                                                <a href="students.php" class="absolute inset-y-0 right-0 pr-3 flex items-center">
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
                                        <?php echo !empty($search) ? 'No students match your search criteria.' : 'Students will appear here once they register.'; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <!-- Desktop Table View -->
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
                                            <?php foreach ($students as $student): ?>
                                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                        </div>
                                                        <div class="text-sm text-gray-500">
                                                            <?php echo htmlspecialchars($student['email']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900 font-mono bg-gray-100 px-2 py-1 rounded">
                                                            <?php echo htmlspecialchars($student['uli']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900">
                                                            <?php echo $student['course'] ? htmlspecialchars($student['course']) : '-'; ?>
                                                        </div>
                                                        <?php if ($student['nc_level']): ?>
                                                            <div class="text-xs text-gray-500">
                                                                <?php echo htmlspecialchars($student['nc_level']); ?>
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
                                                        $status_class = $status_classes[$student['status']] ?? 'bg-gray-100 text-gray-800 border-gray-200';
                                                        ?>
                                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                                            <?php echo ucfirst($student['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900">
                                                            <?php echo $student['adviser'] ? htmlspecialchars($student['adviser']) : '-'; ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div class="flex items-center space-x-3">
                                                            <a href="view.php?id=<?php echo $student['id']; ?>" 
                                                               class="text-primary-600 hover:text-primary-900 flex items-center">
                                                                <i class="fas fa-eye mr-1"></i>View
                                                            </a>
                                                            
                                                            <?php if ($student['status'] === 'pending'): ?>
                                                                <button onclick="openApprovalModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" 
                                                                   class="text-green-600 hover:text-green-900 flex items-center">
                                                                    <i class="fas fa-check mr-1"></i>Approve
                                                                </button>
                                                                <a href="?action=reject&id=<?php echo $student['id']; ?>" 
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

                                <!-- Mobile Card View -->
                                <div class="md:hidden">
                                    <?php foreach ($students as $student): ?>
                                        <div class="border-b border-gray-200 p-4">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <h4 class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                    </h4>
                                                    <p class="text-sm text-gray-500 mt-1">
                                                        <?php echo htmlspecialchars($student['email']); ?>
                                                    </p>
                                                    <p class="text-xs text-gray-500 mt-1">
                                                        ULI: <?php echo htmlspecialchars($student['uli']); ?>
                                                    </p>
                                                    <?php if ($student['course']): ?>
                                                        <p class="text-xs text-gray-500 mt-1">
                                                            Course: <?php echo htmlspecialchars($student['course']); ?>
                                                            <?php if ($student['nc_level']): ?>
                                                                (<?php echo htmlspecialchars($student['nc_level']); ?>)
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
                                                    $status_class = $status_classes[$student['status']] ?? 'bg-gray-100 text-gray-800';
                                                    ?>
                                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                                                        <?php echo ucfirst($student['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="mt-3 flex items-center space-x-4 text-sm">
                                                <a href="view.php?id=<?php echo $student['id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                    <i class="fas fa-eye mr-1"></i>View
                                                </a>
                                                <?php if ($student['status'] === 'pending'): ?>
                                                    <button onclick="openApprovalModal(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" 
                                                       class="text-green-600 hover:text-green-900">
                                                        <i class="fas fa-check mr-1"></i>Approve
                                                    </button>
                                                    <a href="?action=reject&id=<?php echo $student['id']; ?>" 
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

                            <!-- Pagination -->
                            <?php if ($total_pages > 1): ?>
                                <div class="px-4 md:px-6 py-4 border-t border-gray-200 bg-gray-50">
                                    <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                                        <div class="text-sm text-gray-700">
                                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_students_count); ?> of <?php echo $total_students_count; ?> students
                                        </div>
                                        
                                        <div class="flex items-center space-x-2">
                                            <!-- Previous Button -->
                                            <?php if ($page > 1): ?>
                                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                                   class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200">
                                                    <i class="fas fa-chevron-left mr-1"></i>Previous
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Page Numbers -->
                                            <div class="hidden sm:flex items-center space-x-1">
                                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                    <?php if ($i == $page): ?>
                                                        <span class="inline-flex items-center justify-center w-8 h-8 border border-blue-500 rounded text-sm font-medium text-white bg-blue-600 shadow-md"><?php echo $i; ?></span>
                                                    <?php else: ?>
                                                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                                                           class="inline-flex items-center justify-center w-8 h-8 border border-gray-300 rounded text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-all duration-200"><?php echo $i; ?></a>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                            
                                            <!-- Next Button -->
                                            <?php if ($page < $total_pages): ?>
                                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
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
            </main>
        </div>
    </div>    
 
   <!-- Approval Modal -->
    <div id="approvalModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <!-- Background overlay -->
            <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" onclick="closeApprovalModal()"></div>
            
            <!-- Modal panel -->
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <form id="approvalForm" method="POST" action="">
                    <input type="hidden" name="action" value="approve">
                    <input type="hidden" name="student_id" id="modalStudentId">
                    
                    <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                        <div class="sm:flex sm:items-start">
                            <div class="mx-auto flex-shrink-0 flex items-center justify-center h-12 w-12 rounded-full bg-green-100 sm:mx-0 sm:h-10 sm:w-10">
                                <i class="fas fa-check text-green-600"></i>
                            </div>
                            <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                <h3 class="text-lg leading-6 font-medium text-gray-900 mb-4">
                                    Approve Student Registration
                                </h3>
                                <p class="text-sm text-gray-500 mb-4">
                                    Approving: <span id="modalStudentName" class="font-semibold"></span>
                                </p>
                                
                                <div class="space-y-4">
                                    <!-- Course Dropdown -->
                                    <div>
                                        <label for="course_id" class="block text-sm font-medium text-gray-700 mb-1">Course</label>
                                        <select name="course_id" id="course_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Course</option>
                                            <?php foreach ($active_courses as $course): ?>
                                                <option value="<?php echo $course['course_id']; ?>">
                                                    <?php echo htmlspecialchars($course['course_name'] . ' - ' . $course['nc_level']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Training Duration -->
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
                                    
                                    <!-- Adviser Dropdown -->
                                    <div>
                                        <label for="adviser" class="block text-sm font-medium text-gray-700 mb-1">Adviser</label>
                                        <select name="adviser" id="adviser" required class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                                            <option value="">Select Adviser</option>
                                            <option value="Juan dela Cruz">Juan dela Cruz</option>
                                            <option value="Jane Smith">Jane Smith</option>
                                            <option value="Mike Johnson">Mike Johnson</option>
                                            <option value="Sarah Wilson">Sarah Wilson</option>
                                            <option value="David Brown">David Brown</option>
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
        
        // Close modal when clicking outside
        document.getElementById('approvalModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeApprovalModal();
            }
        });
    </script>
    
    <?php include '../components/admin-scripts.php'; ?>
</body>
</html>
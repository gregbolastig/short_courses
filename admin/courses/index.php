<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

// Set page title
$page_title = 'Manage Courses';

// Set breadcrumb
$breadcrumb_items = [
    ['title' => 'Manage Courses', 'icon' => 'fas fa-graduation-cap']
];

// Initialize variables to prevent undefined warnings
$total_courses_count = 0;
$active_courses = 0;
$inactive_courses = 0;
$courses = [];
$total_pages = 0;
$total_courses = 0;
$search = '';

// Handle delete operation
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("DELETE FROM courses WHERE course_id = ?");
        $stmt->execute([$_GET['delete']]);
        $success_message = 'Course deleted successfully!';
    } catch (PDOException $e) {
        $error_message = 'Cannot delete course: ' . $e->getMessage();
    }
}

// Handle toggle status operation
if (isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("UPDATE courses SET is_active = NOT is_active WHERE course_id = ?");
        $stmt->execute([$_POST['course_id']]);
        $success_message = 'Course status updated successfully!';
    } catch (PDOException $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

// Get courses with pagination
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    // Search functionality
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $search_condition = '';
    $params = [];
    
    if (!empty($search)) {
        $search_condition = "WHERE course_name LIKE ?";
        $search_param = "%$search%";
        $params = [$search_param];
    }
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM courses $search_condition";
    $stmt = $conn->prepare($count_sql);
    
    if (!empty($search)) {
        $stmt->bindValue(1, $search_param, PDO::PARAM_STR);
    }
    
    $stmt->execute();
    $total_courses = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_courses / $per_page);
    
    // Get courses
    $sql = "SELECT * FROM courses $search_condition ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    $stmt = $conn->prepare($sql);
    
    // Bind search parameters if they exist
    if (!empty($search)) {
        $stmt->bindValue(1, $search_param, PDO::PARAM_STR);
    }
    
    // Bind pagination parameters
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    
    $stmt->execute();
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $conn->query("SELECT COUNT(*) as total FROM courses");
    $total_courses_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as active FROM courses WHERE is_active = 1");
    $active_courses = $stmt->fetch(PDO::FETCH_ASSOC)['active'];
    
    $stmt = $conn->query("SELECT COUNT(*) as inactive FROM courses WHERE is_active = 0");
    $inactive_courses = $stmt->fetch(PDO::FETCH_ASSOC)['inactive'];
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    // Set default values in case of error
    $total_courses_count = 0;
    $active_courses = 0;
    $inactive_courses = 0;
    $courses = [];
    $total_pages = 0;
    $total_courses = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Courses - Student Registration System</title>
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
                        
                        <!-- Page Header -->
                        <div class="mb-6 mt-6">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div>
                                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Manage Courses</h1>
                                    <p class="text-gray-600 mt-2">View and manage all course offerings</p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <a href="add.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                        <i class="fas fa-plus mr-2"></i>
                                        Add Course
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
                                                <i class="fas fa-graduation-cap text-blue-600 text-xl md:text-2xl"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4 md:ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Total Courses</dt>
                                                <dd class="text-2xl md:text-3xl font-bold text-gray-900"><?php echo $total_courses_count; ?></dd>
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
                                                <dt class="text-sm font-medium text-gray-500 truncate">Active Courses</dt>
                                                <dd class="text-2xl md:text-3xl font-bold text-green-600"><?php echo $active_courses; ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 transition-all duration-300 hover:-translate-y-1 hover:shadow-2xl">
                                <div class="p-4 md:p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="bg-red-100 rounded-xl p-3 md:p-4 shadow-inner">
                                                <i class="fas fa-times-circle text-red-600 text-xl md:text-2xl"></i>
                                            </div>
                                        </div>
                                        <div class="ml-4 md:ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Inactive Courses</dt>
                                                <dd class="text-2xl md:text-3xl font-bold text-red-600"><?php echo $inactive_courses; ?></dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>         
               <!-- Courses Table -->
                        <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100">
                            <div class="px-4 md:px-6 py-4 border-b border-gray-200 bg-gray-50">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 md:gap-4">
                                    <h3 class="text-base md:text-lg font-semibold text-gray-900">
                                        <i class="fas fa-list text-gray-500 mr-2"></i>
                                        Course List
                                    </h3>
                                    <div class="flex flex-col sm:flex-row items-stretch sm:items-center space-y-2 sm:space-y-0 sm:space-x-4">
                                        <!-- Search Bar -->
                                        <form method="GET" action="" class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <i class="fas fa-search text-gray-400"></i>
                                            </div>
                                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" 
                                                   placeholder="Search courses..." 
                                                   class="block w-full sm:w-64 pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500 text-sm">
                                            <?php if (!empty($search)): ?>
                                                <a href="index.php" class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                                    <i class="fas fa-times text-gray-400 hover:text-gray-600"></i>
                                                </a>
                                            <?php endif; ?>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (empty($courses)): ?>
                                <div class="text-center py-8 md:py-12">
                                    <div class="bg-gray-100 rounded-full w-12 h-12 md:w-16 md:h-16 flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-graduation-cap text-gray-400 text-xl md:text-2xl"></i>
                                    </div>
                                    <h3 class="text-base md:text-lg font-medium text-gray-900 mb-2">No courses found</h3>
                                    <p class="text-sm md:text-base text-gray-500 mb-4 px-4">
                                        <?php echo !empty($search) ? 'No courses match your search criteria.' : 'Get started by adding your first course to the system.'; ?>
                                    </p>
                                    <?php if (empty($search)): ?>
                                        <a href="add.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                            <i class="fas fa-plus mr-2"></i>
                                            Add Your First Course
                                        </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <!-- Desktop Table View -->
                                <div class="hidden md:block overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course Name</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($courses as $course): ?>
                                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <?php if ($course['is_active']): ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                                                <i class="fas fa-check-circle mr-1"></i>Active
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800 border border-red-200">
                                                                <i class="fas fa-times-circle mr-1"></i>Inactive
                                                            </span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900">
                                                            <?php echo date('M j, Y', strtotime($course['created_at'])); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div class="flex items-center space-x-3">
                                                            <a href="edit.php?id=<?php echo $course['course_id']; ?>" 
                                                               class="text-blue-600 hover:text-blue-900 flex items-center">
                                                                <i class="fas fa-edit mr-1"></i>Edit
                                                            </a>
                                                            
                                                            <form method="POST" action="" class="inline">
                                                                <input type="hidden" name="action" value="toggle_status">
                                                                <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                                                <button type="submit" class="<?php echo $course['is_active'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?> flex items-center">
                                                                    <i class="fas fa-<?php echo $course['is_active'] ? 'times' : 'check'; ?> mr-1"></i>
                                                                    <?php echo $course['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                                </button>
                                                            </form>
                                                            
                                                            <a href="javascript:void(0)" 
                                                               onclick="confirmDelete('<?php echo htmlspecialchars($course['course_name']); ?>', <?php echo $course['course_id']; ?>)"
                                                               class="text-red-600 hover:text-red-900 flex items-center">
                                                                <i class="fas fa-trash mr-1"></i>Delete
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Mobile Card View -->
                                <div class="md:hidden">
                                    <?php foreach ($courses as $course): ?>
                                        <div class="border-b border-gray-200 p-4">
                                            <div class="flex items-start justify-between">
                                                <div class="flex-1">
                                                    <h4 class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars($course['course_name']); ?>
                                                    </h4>
                                                    <p class="text-sm text-gray-500 mt-1">
                                                        Created: <?php echo date('M j, Y', strtotime($course['created_at'])); ?>
                                                    </p>
                                                </div>
                                                <div class="ml-4">
                                                    <?php if ($course['is_active']): ?>
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                            Active
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                                            Inactive
                                                        </span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="mt-3 flex items-center space-x-4 text-sm">
                                                <a href="edit.php?id=<?php echo $course['course_id']; ?>" class="text-blue-600 hover:text-blue-900">
                                                    <i class="fas fa-edit mr-1"></i>Edit
                                                </a>
                                                <form method="POST" action="" class="inline">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="course_id" value="<?php echo $course['course_id']; ?>">
                                                    <button type="submit" class="<?php echo $course['is_active'] ? 'text-red-600 hover:text-red-900' : 'text-green-600 hover:text-green-900'; ?>">
                                                        <i class="fas fa-<?php echo $course['is_active'] ? 'times' : 'check'; ?> mr-1"></i>
                                                        <?php echo $course['is_active'] ? 'Deactivate' : 'Activate'; ?>
                                                    </button>
                                                </form>
                                                <a href="javascript:void(0)" 
                                                   onclick="confirmDelete('<?php echo htmlspecialchars($course['course_name']); ?>', <?php echo $course['course_id']; ?>)"
                                                   class="text-red-600 hover:text-red-900">
                                                    <i class="fas fa-trash mr-1"></i>Delete
                                                </a>
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
                                            Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_courses); ?> of <?php echo $total_courses; ?> courses
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
    
    <?php include '../components/admin-scripts.php'; ?>
</body>
</html>    <script
>
        function confirmDelete(courseName, courseId) {
            if (confirm(`Are you sure you want to delete the course "${courseName}"?\n\nThis action cannot be undone and will permanently remove the course from the system.`)) {
                window.location.href = `?delete=${courseId}`;
            }
        }
    </script>
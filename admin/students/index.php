<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';
require_once '../../includes/system_activity_logger.php';

requireAdmin();

// ============================================================================
// INITIALIZATION
// ============================================================================

$page_title = 'Manage Students';
$error_message = '';
$success_message = '';
$logger = new SystemActivityLogger();

$database = new Database();
$conn = $database->getConnection();

// Check if soft delete column exists
$has_soft_delete = checkSoftDeleteColumn($conn);

// ============================================================================
// HANDLE DELETE ACTION
// ============================================================================

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'delete') {
    $result = handleStudentDelete($conn, $logger, $_GET['id'], $_SESSION['user_id']);
    $error_message = $result['error'] ?? '';
    $success_message = $result['success'] ?? '';
}

// ============================================================================
// GET FILTER PARAMETERS
// ============================================================================

$search = $_GET['search'] ?? '';
$filter_course = $_GET['filter_course'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

// Pagination
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// ============================================================================
// BUILD QUERY CONDITIONS
// ============================================================================

list($where_clause, $params) = buildWhereClause($search, $filter_course, $filter_status, $has_soft_delete);

// ============================================================================
// FETCH DATA
// ============================================================================

$students = [];
$total_students = 0;
$total_pages = 1;
$courses = [];
$statistics = [
    'total' => 0,
    'pending' => 0,
    'approved' => 0,
    'rejected' => 0,
    'completed' => 0
];

try {
    // Get total count for pagination
    $total_students = getTotalStudents($conn, $where_clause, $params);
    $total_pages = ceil($total_students / $limit);
    
    // Get students with filters and pagination
    $students = getStudents($conn, $where_clause, $params, $limit, $offset);
    
    // Get active courses for filter dropdown
    $courses = getActiveCourses($conn);
    
    // Get statistics
    $statistics = getStudentStatistics($conn, $has_soft_delete);
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// Get pending approvals count for sidebar
$pending_approvals = $statistics['pending'];

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

function checkSoftDeleteColumn($conn) {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM students LIKE 'deleted_at'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

function handleStudentDelete($conn, $logger, $student_id, $user_id) {
    try {
        // Get student info before delete
        $stmt = $conn->prepare("SELECT id, first_name, last_name FROM students WHERE id = :id");
        $stmt->bindParam(':id', $student_id);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            return ['error' => 'Student not found.'];
        }
        
        // Soft delete
        $stmt = $conn->prepare("UPDATE students SET deleted_at = NOW() WHERE id = :id");
        $stmt->bindParam(':id', $student_id);
        $stmt->execute();
        
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

function buildWhereClause($search, $filter_course, $filter_status, $has_soft_delete) {
    $where_conditions = [];
    $params = [];
    
    // Filter out soft-deleted records
    if ($has_soft_delete) {
        $where_conditions[] = "deleted_at IS NULL";
    }
    
    // Search filter
    if (!empty($search)) {
        $where_conditions[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR uli LIKE :search OR student_id LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    // Course filter
    if (!empty($filter_course)) {
        $where_conditions[] = "course = :course";
        $params[':course'] = $filter_course;
    }
    
    // Status filter
    if (!empty($filter_status)) {
        $where_conditions[] = "status = :status";
        $params[':status'] = $filter_status;
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    return [$where_clause, $params];
}

function getTotalStudents($conn, $where_clause, $params) {
    $sql = "SELECT COUNT(*) as total FROM students $where_clause";
    $stmt = $conn->prepare($sql);
    
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    
    $stmt->execute();
    return (int)$stmt->fetch(PDO::FETCH_ASSOC)['total'];
}

function getStudents($conn, $where_clause, $params, $limit, $offset) {
    $sql = "SELECT id, student_id, first_name, middle_name, last_name, email, uli, sex, 
                   province, city, contact_number, status, created_at 
            FROM students $where_clause 
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

function getActiveCourses($conn) {
    try {
        $stmt = $conn->query("SELECT course_name FROM courses WHERE is_active = 1 ORDER BY course_name");
        return $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (PDOException $e) {
        return [];
    }
}

function getStudentStatistics($conn, $has_soft_delete) {
    $soft_delete_filter = $has_soft_delete ? " AND deleted_at IS NULL" : "";
    $stats = [
        'total' => 0,
        'pending' => 0,
        'approved' => 0,
        'rejected' => 0,
        'completed' => 0
    ];
    
    try {
        $queries = [
            'total' => "SELECT COUNT(*) as count FROM students WHERE 1=1{$soft_delete_filter}",
            'pending' => "SELECT COUNT(*) as count FROM students WHERE status = 'pending'{$soft_delete_filter}",
            'approved' => "SELECT COUNT(*) as count FROM students WHERE status = 'approved'{$soft_delete_filter}",
            'rejected' => "SELECT COUNT(*) as count FROM students WHERE status = 'rejected'{$soft_delete_filter}",
            'completed' => "SELECT COUNT(*) as count FROM students WHERE status = 'completed'{$soft_delete_filter}"
        ];
        
        foreach ($queries as $key => $query) {
            $stmt = $conn->query($query);
            $stats[$key] = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];
        }
    } catch (PDOException $e) {
        // Return default values
    }
    
    return $stats;
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'approved':
            return 'bg-green-100 text-green-800 border-green-200';
        case 'rejected':
            return 'bg-red-100 text-red-800 border-red-200';
        case 'completed':
            return 'bg-blue-100 text-blue-800 border-blue-200';
        default:
            return 'bg-yellow-100 text-yellow-800 border-yellow-200';
    }
}

function getStudentInitials($first_name, $last_name) {
    return strtoupper(substr($first_name, 0, 1) . substr($last_name, 0, 1));
}

function getStudentFullName($student) {
    return htmlspecialchars(trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Jacobo Z. Gonzales Memorial School of Arts and Trades</title>
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
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    
            <!-- Main content wrapper -->
        <div id="main-content" class="min-h-screen transition-all duration-300 ease-in-out ml-0 md:ml-64">
            <?php include '../components/header.php'; ?>
            
            <!-- Main content area -->
            <main class="overflow-y-auto focus:outline-none">
                <div class="py-4 md:py-6">
                    <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                        
                        <!-- Page Header -->
                        <div class="mb-8 mt-6">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                                <div>
                                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Student Management</h1>
                                    <p class="text-lg text-gray-600 mt-2">View and manage student registrations and applications</p>
                                </div>
                            </div>
                        </div>

                        <!-- Alert Messages -->
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

                    
            <!-- Enhanced Students Table -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                <!-- Table Header with Search Filters -->
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 md:px-8 py-6 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-6">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-xl flex items-center justify-center mr-3">
                                <i class="fas fa-table text-white"></i>
                            </div>
                            <div>
                                <p class="text-sm text-gray-600 mt-1">
                                    Showing <?php echo count($students); ?> of <?php echo number_format($total_students); ?> students
                                </p>
                            </div>
                        </div>
                        <?php if ($total_pages > 1): ?>
                            <div class="mt-3 sm:mt-0 flex items-center">
                                <div class="bg-white rounded-lg px-3 py-2 border border-gray-200 shadow-sm">
                                    <span class="text-sm font-medium text-gray-600">
                                        Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Search and Filter Form -->
                    <form method="GET" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400 text-sm"></i>
                                </div>
                                <input type="text" id="search" name="search" 
                                       placeholder="Search name, email, ID..." 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg bg-white placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                            
                            <select id="filter_status" name="filter_status" 
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo ($filter_status === 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo ($filter_status === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                <option value="completed" <?php echo ($filter_status === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            </select>
                            
                            <select id="filter_course" name="filter_course" 
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">All Courses</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo htmlspecialchars($course); ?>" 
                                            <?php echo ($filter_course == $course) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($course); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
                
                <?php if (empty($students)): ?>
                    <div class="text-center py-12">
                        <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-users text-gray-400 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No students found</h3>
                        <p class="text-gray-600 mb-6">No students match your current search criteria.</p>
                        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            <i class="fas fa-refresh mr-2"></i>Clear Filters
                        </a>
                    </div>
                <?php else: ?>
                    <!-- Desktop Table -->
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
                                <?php foreach ($students as $student): 
                                    $status_class = getStatusBadgeClass($student['status']);
                                    $initials = getStudentInitials($student['first_name'], $student['last_name']);
                                    $full_name = getStudentFullName($student);
                                ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                        <span class="text-sm font-medium text-blue-600">
                                                            <?php echo $initials; ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo $full_name; ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        ULI: <?php echo htmlspecialchars($student['uli']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['email']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['contact_number']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900"><?php echo htmlspecialchars($student['city']); ?></div>
                                            <div class="text-sm text-gray-500"><?php echo htmlspecialchars($student['province']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                                <?php echo ucfirst($student['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <?php echo date('M j, Y', strtotime($student['created_at'])); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div class="flex items-center justify-end space-x-2">
                                                <a href="view.php?id=<?php echo $student['id']; ?>" 
                                                   class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-xs font-semibold rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 hover:border-blue-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                                    <i class="fas fa-eye mr-1"></i>View
                                                </a>
                                                <a href="edit.php?id=<?php echo $student['id']; ?>" 
                                                   class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-semibold rounded-md text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
                                                    <i class="fas fa-edit mr-1"></i>Edit
                                                </a>
                                                <button onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" 
                                                        class="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-semibold rounded-md text-red-700 bg-red-50 hover:bg-red-100 hover:border-red-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200">
                                                    <i class="fas fa-trash mr-1"></i>Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="lg:hidden">
                        <?php foreach ($students as $student): 
                            $status_class = getStatusBadgeClass($student['status']);
                            $initials = getStudentInitials($student['first_name'], $student['last_name']);
                            $full_name = getStudentFullName($student);
                        ?>
                            <div class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors duration-200">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center flex-1 min-w-0">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                <span class="text-sm font-medium text-blue-600">
                                                    <?php echo $initials; ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-3 flex-1 min-w-0">
                                            <div class="text-sm font-medium text-gray-900 truncate">
                                                <?php echo $full_name; ?>
                                            </div>
                                            <div class="text-sm text-gray-500 truncate">
                                                <?php echo htmlspecialchars($student['email']); ?>
                                            </div>
                                            <div class="text-xs text-gray-400 mt-1">
                                                ULI: <?php echo htmlspecialchars($student['uli']); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 ml-2">
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border <?php echo $status_class; ?>">
                                            <?php echo ucfirst($student['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mt-3 grid grid-cols-2 gap-3 text-sm text-gray-600">
                                    <div>
                                        <span class="font-medium">Location:</span>
                                        <div><?php echo htmlspecialchars($student['city'] . ', ' . $student['province']); ?></div>
                                    </div>
                                    <div>
                                        <span class="font-medium">Contact:</span>
                                        <div><?php echo htmlspecialchars($student['contact_number']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="mt-3 text-xs text-gray-500">
                                    Registered: <?php echo date('M j, Y', strtotime($student['created_at'])); ?>
                                </div>
                                
                                <div class="flex items-center space-x-2 mt-3 pt-3 border-t border-gray-100">
                                    <a href="view.php?id=<?php echo $student['id']; ?>" 
                                       class="inline-flex items-center px-3 py-1.5 border border-blue-300 text-xs font-semibold rounded-md text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors duration-200">
                                        <i class="fas fa-eye mr-1"></i>View
                                    </a>
                                    <a href="edit.php?id=<?php echo $student['id']; ?>" 
                                       class="inline-flex items-center px-3 py-1.5 border border-gray-300 text-xs font-semibold rounded-md text-gray-700 bg-white hover:bg-gray-50 transition-colors duration-200">
                                        <i class="fas fa-edit mr-1"></i>Edit
                                    </a>
                                    <button onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" 
                                            class="inline-flex items-center px-3 py-1.5 border border-red-300 text-xs font-semibold rounded-md text-red-700 bg-red-50 hover:bg-red-100 transition-colors duration-200">
                                        <i class="fas fa-trash mr-1"></i>Delete
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <?php if ($page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                <?php if ($page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
                                       class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Next
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Showing <span class="font-medium"><?php echo (($page - 1) * $limit) + 1; ?></span> to 
                                        <span class="font-medium"><?php echo min($page * $limit, $total_students); ?></span> of 
                                        <span class="font-medium"><?php echo $total_students; ?></span> results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <?php if ($page > 1): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" 
                                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo ($i == $page) ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($page < $total_pages): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" 
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
        </main>
    </div>

    <?php include '../components/admin-scripts.php'; ?>
    
    <script>
        function confirmDelete(studentId, studentName) {
            // Create modern confirmation modal
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-lg shadow-xl p-4 sm:p-6 max-w-sm mx-4 transform transition-all">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <h3 class="mt-4 text-lg font-medium text-gray-900 text-center">Delete Student</h3>
                    <p class="mt-2 text-sm text-gray-500 text-center">
                        Are you sure you want to delete <strong>${studentName}</strong>? This action cannot be undone.
                    </p>
                    <div class="mt-6 flex flex-col-reverse sm:flex-row gap-2">
                        <button onclick="this.parentElement.parentElement.parentElement.remove()" 
                                class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                            Cancel
                        </button>
                        <button onclick="window.location.href='?action=delete&id=${studentId}'" 
                                class="flex-1 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors duration-200">
                            Delete
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.remove();
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.parentElement) {
                    modal.remove();
                }
            });
        }
    </script>
</body>
</html>
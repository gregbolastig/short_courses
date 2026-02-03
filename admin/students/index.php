<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

$page_title = 'Manage Students';

$students = [];
$error_message = '';
$success_message = '';

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Get student info for file cleanup
        $stmt = $conn->prepare("SELECT profile_picture FROM students WHERE id = :id");
        $stmt->bindParam(':id', $_GET['id']);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete student record
        $stmt = $conn->prepare("DELETE FROM students WHERE id = :id");
        $stmt->bindParam(':id', $_GET['id']);
        
        if ($stmt->execute()) {
            // Delete profile picture file if exists
            if ($student && !empty($student['profile_picture'])) {
                $file_path = '';
                if (strpos($student['profile_picture'], '../') === 0) {
                    // Old format: use as is
                    $file_path = $student['profile_picture'];
                } else {
                    // New format: add ../
                    $file_path = '../' . $student['profile_picture'];
                }
                
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            $success_message = 'Student deleted successfully.';
        } else {
            $error_message = 'Failed to delete student.';
        }
    } catch (PDOException $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$filter_province = $_GET['filter_province'] ?? '';
$filter_sex = $_GET['filter_sex'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR uli LIKE :search OR student_id LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($filter_province)) {
    $where_conditions[] = "province = :province";
    $params[':province'] = $filter_province;
}

if (!empty($filter_sex)) {
    $where_conditions[] = "sex = :sex";
    $params[':sex'] = $filter_sex;
}

if (!empty($filter_status)) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $filter_status;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM students $where_clause";
    $stmt = $conn->prepare($count_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_students / $limit);
    
    // Get students with filters and pagination
    $sql = "SELECT id, student_id, first_name, middle_name, last_name, email, uli, sex, province, city, contact_number, status, created_at 
            FROM students $where_clause ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique provinces for filter
    $stmt = $conn->query("SELECT DISTINCT province FROM students WHERE province IS NOT NULL AND province != '' ORDER BY province");
    $provinces = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get statistics
    $stmt = $conn->query("SELECT COUNT(*) as total FROM students");
    $total_students_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as pending FROM students WHERE status = 'pending'");
    $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    
    $stmt = $conn->query("SELECT COUNT(*) as approved FROM students WHERE status = 'approved'");
    $approved_count = $stmt->fetch(PDO::FETCH_ASSOC)['approved'];
    
    $stmt = $conn->query("SELECT COUNT(*) as rejected FROM students WHERE status = 'rejected'");
    $rejected_count = $stmt->fetch(PDO::FETCH_ASSOC)['rejected'];
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// Get pending approvals count for sidebar
try {
    $stmt = $conn->query("SELECT COUNT(*) as pending FROM students WHERE status = 'pending'");
    $pending_approvals = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
} catch (PDOException $e) {
    $pending_approvals = 0;
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
    
    <!-- Main Content -->
    <div class="md:ml-64 min-h-screen">
        <!-- Header -->
        <?php include '../components/header.php'; ?>
        
        <!-- Page Content -->
        <main class="p-4 md:p-6 lg:p-8">
            <!-- Page Header with Enhanced Design -->
            <div class="mb-8">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-2xl p-6 md:p-8 text-white shadow-xl">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="mb-4 md:mb-0">
                            <div class="flex items-center mb-3">
                                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mr-4">
                                    <i class="fas fa-users text-2xl text-white"></i>
                                </div>
                                <div>
                                    <h1 class="text-3xl md:text-4xl font-bold mb-1">Manage Students</h1>
                                    <p class="text-blue-100 text-lg">Comprehensive student management system</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-4 text-sm text-blue-100">
                                <div class="flex items-center">
                                    <i class="fas fa-database mr-2"></i>
                                    <span>Total: <?php echo number_format($total_students_count); ?> students</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-clock mr-2"></i>
                                    <span>Pending: <?php echo number_format($pending_count); ?></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle mr-2"></i>
                                    <span>Approved: <?php echo number_format($approved_count); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-4 text-center">
                                <div class="text-2xl font-bold"><?php echo number_format($total_students_count); ?></div>
                                <div class="text-xs text-blue-200">Total Students</div>
                            </div>
                        </div>
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

            <!-- Enhanced Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-users text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Total Students</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($total_students_count); ?></p>
                            </div>
                        </div>
                        <div class="text-blue-500">
                            <i class="fas fa-arrow-up text-sm"></i>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500">All registered students</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-14 h-14 bg-gradient-to-br from-yellow-500 to-orange-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-clock text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Pending</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($pending_count); ?></p>
                            </div>
                        </div>
                        <div class="text-yellow-500">
                            <i class="fas fa-exclamation-triangle text-sm"></i>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500">Awaiting approval</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-check text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Approved</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($approved_count); ?></p>
                            </div>
                        </div>
                        <div class="text-green-500">
                            <i class="fas fa-check-circle text-sm"></i>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500">Successfully approved</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-14 h-14 bg-gradient-to-br from-red-500 to-pink-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-times text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Rejected</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($rejected_count); ?></p>
                            </div>
                        </div>
                        <div class="text-red-500">
                            <i class="fas fa-times-circle text-sm"></i>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500">Application rejected</p>
                    </div>
                </div>
            </div>
            <!-- Enhanced Search and Filter Section -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 md:p-8 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-500 rounded-xl flex items-center justify-center mr-3">
                            <i class="fas fa-search text-white"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Search & Filter Students</h2>
                            <p class="text-sm text-gray-600">Find and filter students by various criteria</p>
                        </div>
                    </div>
                    <div class="hidden md:flex items-center text-sm text-gray-500">
                        <i class="fas fa-filter mr-2"></i>
                        <span>Advanced Filtering</span>
                    </div>
                </div>
                
                <form method="GET" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <label for="search" class="block text-sm font-medium text-gray-700 mb-2">Search</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <i class="fas fa-search text-gray-400"></i>
                                </div>
                                <input type="text" id="search" name="search" 
                                       placeholder="Name, Email, ULI, Student ID..." 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                        </div>
                        
                        <div>
                            <label for="filter_province" class="block text-sm font-medium text-gray-700 mb-2">Province</label>
                            <select id="filter_province" name="filter_province" 
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">All Provinces</option>
                                <?php foreach ($provinces as $province): ?>
                                    <option value="<?php echo htmlspecialchars($province); ?>" 
                                            <?php echo ($filter_province === $province) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($province); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="filter_sex" class="block text-sm font-medium text-gray-700 mb-2">Gender</label>
                            <select id="filter_sex" name="filter_sex" 
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">All Genders</option>
                                <option value="Male" <?php echo ($filter_sex === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($filter_sex === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($filter_sex === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        
                        <div>
                            <label for="filter_status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                            <select id="filter_status" name="filter_status" 
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo ($filter_status === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="approved" <?php echo ($filter_status === 'approved') ? 'selected' : ''; ?>>Approved</option>
                                <option value="rejected" <?php echo ($filter_status === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row gap-3 pt-4 border-t border-gray-200">
                        <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                            <i class="fas fa-search mr-2"></i>Apply Filters
                        </button>
                        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
                            <i class="fas fa-times mr-2"></i>Clear Filters
                        </a>
                    </div>
                </form>
            </div>

            <!-- Enhanced Students Table -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 md:px-8 py-6 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-xl flex items-center justify-center mr-3">
                                <i class="fas fa-table text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">Students Directory</h2>
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
                                <?php foreach ($students as $student): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                        <span class="text-sm font-medium text-blue-600">
                                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name'])); ?>
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
                                            <?php
                                            $status_class = '';
                                            switch ($student['status']) {
                                                case 'approved':
                                                    $status_class = 'bg-green-100 text-green-800 border-green-200';
                                                    break;
                                                case 'rejected':
                                                    $status_class = 'bg-red-100 text-red-800 border-red-200';
                                                    break;
                                                default:
                                                    $status_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                            }
                                            ?>
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
                        <?php foreach ($students as $student): ?>
                            <div class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors duration-200">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center flex-1 min-w-0">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <div class="h-10 w-10 rounded-full bg-blue-100 flex items-center justify-center">
                                                <span class="text-sm font-medium text-blue-600">
                                                    <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="ml-3 flex-1 min-w-0">
                                            <div class="text-sm font-medium text-gray-900 truncate">
                                                <?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name'])); ?>
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
                                        <?php
                                        $status_class = '';
                                        switch ($student['status']) {
                                            case 'approved':
                                                $status_class = 'bg-green-100 text-green-800 border-green-200';
                                                break;
                                            case 'rejected':
                                                $status_class = 'bg-red-100 text-red-800 border-red-200';
                                                break;
                                            default:
                                                $status_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                        }
                                        ?>
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
            if (confirm(`Are you sure you want to delete ${studentName}? This action cannot be undone.`)) {
                window.location.href = `?action=delete&id=${studentId}`;
            }
        }
    </script>
</body>
</html>
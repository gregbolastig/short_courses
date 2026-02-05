<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

// Set page title
$page_title = 'Course Applications - Browse & View';

// Set breadcrumb
$breadcrumb_items = [
    ['title' => 'Course Applications', 'icon' => 'fas fa-file-alt']
];

// Initialize variables
$applications = [];
$total_applications = 0;
$total_pages = 0;
$error_message = '';
$success_message = '';

// Get applications with pagination and filtering
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Pagination
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $per_page = 10;
    $offset = ($page - 1) * $per_page;
    
    // Filtering
    $status_filter = $_GET['status'] ?? '';
    $course_filter = $_GET['course'] ?? '';
    $search = $_GET['search'] ?? '';
    
    // Build WHERE clause
    $where_conditions = [];
    $params = [];
    
    if (!empty($status_filter)) {
        $where_conditions[] = "ca.status = :status";
        $params[':status'] = $status_filter;
    }
    
    if (!empty($course_filter)) {
        $where_conditions[] = "ca.course_name = :course_name";
        $params[':course_name'] = $course_filter;
    }
    
    if (!empty($search)) {
        $where_conditions[] = "(s.first_name LIKE :search OR s.last_name LIKE :search OR s.email LIKE :search OR s.uli LIKE :search OR ca.course_name LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }
    
    $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';
    
    // Get total count
    $count_sql = "SELECT COUNT(*) as total FROM course_applications ca 
                  INNER JOIN students s ON ca.student_id = s.id 
                  LEFT JOIN courses c ON ca.course_name = c.course_name 
                  $where_clause";
    $stmt = $conn->prepare($count_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_applications = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_applications / $per_page);
    
    // Get applications
    $sql = "SELECT ca.*, s.first_name, s.last_name, s.middle_name, s.extension_name, s.email, s.uli, s.contact_number,
                   ca.course_name, ca.adviser, ca.nc_level, ca.training_start, ca.training_end,
                   u.username as reviewed_by_name
            FROM course_applications ca
            INNER JOIN students s ON ca.student_id = s.id
            LEFT JOIN users u ON ca.reviewed_by = u.id
            $where_clause
            ORDER BY ca.applied_at DESC
            LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get courses for filter dropdown
    $stmt = $conn->query("SELECT DISTINCT course_name FROM course_applications WHERE course_name IS NOT NULL ORDER BY course_name");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get statistics
    $stmt = $conn->query("SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
        FROM course_applications");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
    $applications = [];
    $total_applications = 0;
    $total_pages = 0;
    $courses = [];
    $stats = ['total' => 0, 'pending' => 0, 'approved' => 0, 'rejected' => 0];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Student Registration System</title>
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
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-in': 'slideIn 0.3s ease-out'
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
                        
                        <!-- Alerts -->
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
                        <?php endif; ?>
                        
                        <?php if ($success_message): ?>
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

                        <!-- Application Overview Section -->
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 md:p-8 mb-8">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-indigo-500 rounded-xl flex items-center justify-center mr-3">
                                        <i class="fas fa-chart-pie text-white"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-900">Application Overview</h2>
                                        <p class="text-sm text-gray-600">Summary of all course applications in the system</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-4 border border-blue-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-blue-600">Total Applications</p>
                                            <p class="text-2xl font-bold text-blue-900"><?php echo $stats['total']; ?></p>
                                        </div>
                                        <div class="w-10 h-10 bg-blue-200 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-file-alt text-blue-700"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-lg p-4 border border-yellow-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-yellow-600">Pending Review</p>
                                            <p class="text-2xl font-bold text-yellow-900"><?php echo $stats['pending']; ?></p>
                                        </div>
                                        <div class="w-10 h-10 bg-yellow-200 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-clock text-yellow-700"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-4 border border-green-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-green-600">Approved</p>
                                            <p class="text-2xl font-bold text-green-900"><?php echo $stats['approved']; ?></p>
                                        </div>
                                        <div class="w-10 h-10 bg-green-200 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-check text-green-700"></i>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="bg-gradient-to-br from-red-50 to-red-100 rounded-lg p-4 border border-red-200">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-sm font-medium text-red-600">Rejected</p>
                                            <p class="text-2xl font-bold text-red-900"><?php echo $stats['rejected']; ?></p>
                                        </div>
                                        <div class="w-10 h-10 bg-red-200 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-times text-red-700"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Browse Navigation Section -->
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 md:p-8 mb-8">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gradient-to-br from-purple-500 to-indigo-500 rounded-xl flex items-center justify-center mr-3">
                                        <i class="fas fa-compass text-white"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-900">Browse Related Sections</h2>
                                        <p class="text-sm text-gray-600">Navigate to view other system components</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                                <a href="../students/index.php" class="group flex items-center p-4 bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg hover:from-blue-100 hover:to-blue-200 transition-all duration-200 border border-blue-200 hover:border-blue-300">
                                    <div class="w-10 h-10 bg-blue-200 group-hover:bg-blue-300 rounded-lg flex items-center justify-center mr-3 transition-colors duration-200">
                                        <i class="fas fa-users text-blue-700"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-blue-900">Browse Students</p>
                                        <p class="text-xs text-blue-600">View all student records</p>
                                    </div>
                                </a>
                                
                                <a href="../courses/index.php" class="group flex items-center p-4 bg-gradient-to-br from-green-50 to-green-100 rounded-lg hover:from-green-100 hover:to-green-200 transition-all duration-200 border border-green-200 hover:border-green-300">
                                    <div class="w-10 h-10 bg-green-200 group-hover:bg-green-300 rounded-lg flex items-center justify-center mr-3 transition-colors duration-200">
                                        <i class="fas fa-book text-green-700"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-green-900">Browse Courses</p>
                                        <p class="text-xs text-green-600">View course catalog</p>
                                    </div>
                                </a>
                                
                                <a href="../pending_approvals.php" class="group flex items-center p-4 bg-gradient-to-br from-yellow-50 to-yellow-100 rounded-lg hover:from-yellow-100 hover:to-yellow-200 transition-all duration-200 border border-yellow-200 hover:border-yellow-300">
                                    <div class="w-10 h-10 bg-yellow-200 group-hover:bg-yellow-300 rounded-lg flex items-center justify-center mr-3 transition-colors duration-200">
                                        <i class="fas fa-clock text-yellow-700"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-yellow-900">Pending Reviews</p>
                                        <p class="text-xs text-yellow-600">View pending applications</p>
                                    </div>
                                </a>
                                
                                <a href="../dashboard.php" class="group flex items-center p-4 bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg hover:from-purple-100 hover:to-purple-200 transition-all duration-200 border border-purple-200 hover:border-purple-300">
                                    <div class="w-10 h-10 bg-purple-200 group-hover:bg-purple-300 rounded-lg flex items-center justify-center mr-3 transition-colors duration-200">
                                        <i class="fas fa-chart-bar text-purple-700"></i>
                                    </div>
                                    <div>
                                        <p class="text-sm font-semibold text-purple-900">Dashboard</p>
                                        <p class="text-xs text-purple-600">System overview</p>
                                    </div>
                                </a>
                            </div>
                        </div>

                        <!-- Search and Filter Section -->
                        <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 md:p-8 mb-8">
                            <div class="flex items-center justify-between mb-6">
                                <div class="flex items-center">
                                    <div class="w-10 h-10 bg-gradient-to-br from-indigo-500 to-purple-500 rounded-xl flex items-center justify-center mr-3">
                                        <i class="fas fa-filter text-white"></i>
                                    </div>
                                    <div>
                                        <h2 class="text-xl font-bold text-gray-900">Filter & Browse Applications</h2>
                                        <p class="text-sm text-gray-600">Use filters to find specific applications to view</p>
                                    </div>
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
                                                   placeholder="Student name, email, ID..." 
                                                   value="<?php echo htmlspecialchars($search); ?>"
                                                   class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                        </div>
                                    </div>
                                    
                                    <div>
                                        <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                                        <select id="status" name="status" 
                                                class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                            <option value="">All Status</option>
                                            <option value="pending" <?php echo ($status_filter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                                            <option value="approved" <?php echo ($status_filter === 'approved') ? 'selected' : ''; ?>>Approved</option>
                                            <option value="rejected" <?php echo ($status_filter === 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label for="course" class="block text-sm font-medium text-gray-700 mb-2">Course</label>
                                         <select id="course" name="course" 
                                                 class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                             <option value="">All Courses</option>
                                             <?php foreach ($courses as $course): ?>
                                                 <option value="<?php echo htmlspecialchars($course['course_name']); ?>" 
                                                         <?php echo ($course_filter == $course['course_name']) ? 'selected' : ''; ?>>
                                                     <?php echo htmlspecialchars($course['course_name']); ?>
                                                 </option>
                                             <?php endforeach; ?>
                                         </select>
                                    </div>
                                    
                                    <div class="flex items-end">
                                        <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-indigo-600 text-white text-sm font-semibold rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                                            <i class="fas fa-filter mr-2"></i>Apply Filters
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="flex justify-start pt-4 border-t border-gray-200">
                                    <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
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
                                                    Page <?php echo $page; ?> of <?php echo $total_pages; ?>
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
                                        <?php echo !empty($search) || !empty($status_filter) || !empty($course_filter) ? 'No applications match your search criteria. Try adjusting your filters to view different applications.' : 'No course applications have been submitted yet. Check back later for new applications to view.'; ?>
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
                                                                <div class="text-sm text-gray-500">
                                                                    <?php echo htmlspecialchars($app['email']); ?>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($app['course_name']); ?></div>
                                                        <?php if ($app['nc_level']): ?>
                                                            <div class="text-sm text-gray-500">NC Level: <?php echo htmlspecialchars($app['nc_level']); ?></div>
                                                        <?php endif; ?>
                                                        <?php if ($app['adviser']): ?>
                                                            <div class="text-sm text-blue-600">Adviser: <?php echo htmlspecialchars($app['adviser']); ?></div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <div class="text-sm text-gray-900">Applied: <?php echo date('M j, Y', strtotime($app['applied_at'])); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo date('g:i A', strtotime($app['applied_at'])); ?></div>
                                                        <?php if ($app['training_start'] && $app['training_end']): ?>
                                                            <div class="text-sm text-green-600 mt-1">
                                                                Training: <?php echo date('M j', strtotime($app['training_start'])); ?> - <?php echo date('M j, Y', strtotime($app['training_end'])); ?>
                                                            </div>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="px-6 py-4">
                                                        <?php
                                                        $status_class = '';
                                                        switch ($app['status']) {
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
                                                            <?php echo ucfirst($app['status']); ?>
                                                        </span>
                                                    </td>
                                                    <td class="px-6 py-4 text-center">
                                                        <a href="view.php?id=<?php echo $app['application_id']; ?>" 
                                                           class="inline-flex items-center px-4 py-2 border border-indigo-300 text-sm font-semibold rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 hover:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                                                            <i class="fas fa-eye mr-2"></i>View Details
                                                        </a>
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
                                                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($app['email']); ?></p>
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
                                                        <?php echo ucfirst($app['status']); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            
                                            <div class="flex items-center justify-center mt-4">
                                                <a href="view.php?id=<?php echo $app['application_id']; ?>" 
                                                   class="inline-flex items-center justify-center px-6 py-2 border border-indigo-300 text-sm font-semibold rounded-lg text-indigo-700 bg-indigo-50 hover:bg-indigo-100 hover:border-indigo-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-all duration-200">
                                                    <i class="fas fa-eye mr-2"></i>View Details
                                                </a>
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
                                            Viewing <span class="font-bold text-gray-900"><?php echo $offset + 1; ?></span> to <span class="font-bold text-gray-900"><?php echo min($offset + $per_page, $total_applications); ?></span> of <span class="font-bold text-gray-900"><?php echo $total_applications; ?></span> applications
                                        </div>
                                        
                                        <div class="flex items-center space-x-2">
                                            <!-- Previous Button -->
                                            <?php if ($page > 1): ?>
                                                <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($course_filter) ? '&course=' . urlencode($course_filter) : ''; ?>" 
                                                   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm">
                                                    <i class="fas fa-chevron-left mr-2"></i>Previous
                                                </a>
                                            <?php endif; ?>
                                            
                                            <!-- Page Numbers -->
                                            <div class="hidden sm:flex items-center space-x-1">
                                                <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                                                    <?php if ($i == $page): ?>
                                                        <span class="inline-flex items-center justify-center w-10 h-10 border-2 border-indigo-500 rounded-lg text-sm font-bold text-white bg-indigo-600 shadow-md"><?php echo $i; ?></span>
                                                    <?php else: ?>
                                                        <a href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($course_filter) ? '&course=' . urlencode($course_filter) : ''; ?>" 
                                                           class="inline-flex items-center justify-center w-10 h-10 border border-gray-300 rounded-lg text-sm font-semibold text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 transition-all duration-200 shadow-sm"><?php echo $i; ?></a>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                            
                                            <!-- Next Button -->
                                            <?php if ($page < $total_pages): ?>
                                                <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($status_filter) ? '&status=' . urlencode($status_filter) : ''; ?><?php echo !empty($course_filter) ? '&course=' . urlencode($course_filter) : ''; ?>" 
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
            </main>
        </div>
    </div>

    <?php include '../components/admin-scripts.php'; ?>
</body>
</html>
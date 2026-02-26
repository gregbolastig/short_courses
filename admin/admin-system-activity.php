<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

$current_page_title = 'System Activity';

$activities = [];
$error_message = '';
$success_message = '';

// Initialize statistics variables
$total_activities_count = 0;
$today_count = 0;
$week_count = 0;
$month_count = 0;

// Get search parameters
$search = $_GET['search'] ?? '';
$filter_type = $_GET['filter_type'] ?? '';
$filter_user_type = $_GET['filter_user_type'] ?? '';

// Pagination
$current_page = isset($_GET['pg']) ? (int)$_GET['pg'] : 1;
$limit = 20;
$offset = ($current_page - 1) * $limit;

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(sa.activity_description LIKE :search OR sa.activity_type LIKE :search OR u.username LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($filter_type)) {
    $where_conditions[] = "sa.activity_type = :activity_type";
    $params[':activity_type'] = $filter_type;
}

if (!empty($filter_user_type)) {
    $where_conditions[] = "sa.user_type = :user_type";
    $params[':user_type'] = $filter_user_type;
}

// Always exclude system activities - only show student and admin activities
$where_conditions[] = "sa.user_type IN ('student', 'admin')";

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get total count for pagination
    $count_sql = "SELECT COUNT(*) as total FROM shortcourse_system_activities sa 
                  LEFT JOIN shortcourse_users u ON sa.user_id = u.id 
                  $where_clause";
    $stmt = $conn->prepare($count_sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_activities = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_activities / $limit);
    
    // Get activities with filters and pagination
    $sql = "SELECT sa.*, u.username 
            FROM shortcourse_system_activities sa 
            LEFT JOIN shortcourse_users u ON sa.user_id = u.id 
            $where_clause 
            ORDER BY sa.created_at DESC 
            LIMIT :limit OFFSET :offset";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get activity types for filter (excluding system activities)
    $stmt = $conn->query("SELECT DISTINCT activity_type FROM shortcourse_system_activities WHERE user_type IN ('student', 'admin') ORDER BY activity_type");
    $activity_types = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get statistics (excluding system activities)
    $stmt = $conn->query("SELECT COUNT(*) as total FROM shortcourse_system_activities WHERE user_type IN ('student', 'admin')");
    $total_activities_count = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $stmt = $conn->query("SELECT COUNT(*) as today FROM shortcourse_system_activities WHERE DATE(created_at) = CURDATE() AND user_type IN ('student', 'admin')");
    $today_count = $stmt->fetch(PDO::FETCH_ASSOC)['today'];
    
    $stmt = $conn->query("SELECT COUNT(*) as this_week FROM shortcourse_system_activities WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND user_type IN ('student', 'admin')");
    $week_count = $stmt->fetch(PDO::FETCH_ASSOC)['this_week'];
    
    $stmt = $conn->query("SELECT COUNT(*) as this_month FROM shortcourse_system_activities WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) AND user_type IN ('student', 'admin')");
    $month_count = $stmt->fetch(PDO::FETCH_ASSOC)['this_month'];
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    // Set default values for statistics if there's an error
    $total_activities_count = 0;
    $today_count = 0;
    $week_count = 0;
    $month_count = 0;
    $total_activities = 0;
    $total_pages = 0;
    $activities = [];
    $activity_types = [];
}

// Get pending approvals count for sidebar
try {
    $stmt = $conn->query("SELECT COUNT(*) as pending FROM shortcourse_students WHERE status = 'pending'");
    $pending_approvals = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
} catch (PDOException $e) {
    $pending_approvals = 0;
}
?>

<!DOCTYPE html>
<html lang="en" class="<?php echo ($_SESSION['theme_preference'] ?? 'light') === 'dark' ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $current_page_title; ?> - Jacobo Z. Gonzales Memorial School of Arts and Trades</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        // Tailwind config must be set before loading Tailwind CDN
        window.tailwind = {
            config: {
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
        };
    </script>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php include 'components/dark-mode-config.php'; ?>
</head>
<body class="bg-gray-50">
    <?php include 'components/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div id="main-content" class="md:ml-64 min-h-screen">
        <!-- Header -->
        <?php include 'components/header.php'; ?>
        
        <!-- Page Content -->
        <main class="p-4 md:p-6 lg:p-8">
            <!-- Page Header -->
            <div class="mb-8">
                <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-2xl p-6 md:p-8 text-white shadow-xl">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="mb-4 md:mb-0">
                            <div class="flex items-center mb-3">
                                <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mr-4">
                                    <i class="fas fa-history text-2xl text-white"></i>
                                </div>
                                <div>
                                    <h1 class="text-3xl md:text-4xl font-bold mb-1">System Activity</h1>
                                    <p class="text-blue-100 text-lg">Monitor all system activities and events</p>
                                </div>
                            </div>
                            <div class="flex flex-wrap gap-4 text-sm text-blue-100">
                                <div class="flex items-center">
                                    <i class="fas fa-database mr-2"></i>
                                    <span>Total: <?php echo number_format($total_activities_count); ?> activities</span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-calendar-day mr-2"></i>
                                    <span>Today: <?php echo number_format($today_count); ?></span>
                                </div>
                                <div class="flex items-center">
                                    <i class="fas fa-calendar-week mr-2"></i>
                                    <span>This Week: <?php echo number_format($week_count); ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <div class="bg-white bg-opacity-10 backdrop-blur-sm rounded-xl p-4 text-center">
                                <div class="text-2xl font-bold"><?php echo number_format($total_activities_count); ?></div>
                                <div class="text-xs text-blue-200">Total Activities</div>
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

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-history text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Total Activities</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($total_activities_count); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500">All recorded activities</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-14 h-14 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-calendar-day text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">Today</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($today_count); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500">Activities today</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-14 h-14 bg-gradient-to-br from-green-500 to-emerald-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-calendar-week text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">This Week</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($week_count); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500">Activities this week</p>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 hover:shadow-xl transition-all duration-300 transform hover:-translate-y-1">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center">
                            <div class="w-14 h-14 bg-gradient-to-br from-orange-500 to-red-500 rounded-2xl flex items-center justify-center shadow-lg">
                                <i class="fas fa-calendar-alt text-white text-xl"></i>
                            </div>
                            <div class="ml-4">
                                <p class="text-sm font-semibold text-gray-600 uppercase tracking-wide">This Month</p>
                                <p class="text-3xl font-bold text-gray-900"><?php echo number_format($month_count); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <p class="text-xs text-gray-500">Activities this month</p>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 p-6 md:p-8 mb-8">
                <div class="flex items-center justify-between mb-6">
                    <div class="flex items-center">
                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mr-3">
                            <i class="fas fa-filter text-white"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Filter & Search Activities</h2>
                            <p class="text-sm text-gray-600">Use filters to find specific activities</p>
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
                                       placeholder="Activity description, type, user..." 
                                       value="<?php echo htmlspecialchars($search); ?>"
                                       class="block w-full pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                            </div>
                        </div>
                        
                        <div>
                            <label for="filter_type" class="block text-sm font-medium text-gray-700 mb-2">Activity Type</label>
                            <select id="filter_type" name="filter_type" 
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">All Types</option>
                                <?php foreach ($activity_types as $type): ?>
                                    <option value="<?php echo htmlspecialchars($type); ?>" 
                                            <?php echo ($filter_type === $type) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $type))); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="filter_user_type" class="block text-sm font-medium text-gray-700 mb-2">User Type</label>
                            <select id="filter_user_type" name="filter_user_type" 
                                    class="block w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-1 focus:ring-blue-500 focus:border-blue-500 text-sm">
                                <option value="">All User Types</option>
                                <option value="admin" <?php echo ($filter_user_type === 'admin') ? 'selected' : ''; ?>>Admin</option>
                                <option value="student" <?php echo ($filter_user_type === 'student') ? 'selected' : ''; ?>>Student</option>
                            </select>
                        </div>
                        
                        <div class="flex items-end">
                            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                <i class="fas fa-filter mr-2"></i>Apply Filters
                            </button>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center pt-4 border-t border-gray-200">
                        <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
                            <i class="fas fa-times mr-2"></i>Clear Filters
                        </a>
                    </div>
                </form>
            </div>

            <!-- Activities Table -->
            <div class="bg-white rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                <div class="bg-gradient-to-r from-gray-50 to-gray-100 px-6 md:px-8 py-6 border-b border-gray-200">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center mr-3">
                                <i class="fas fa-list text-white"></i>
                            </div>
                            <div>
                                <h2 class="text-xl font-bold text-gray-900">System Activities</h2>
                                <p class="text-sm text-gray-600 mt-1">
                                    Showing <?php echo count($activities); ?> of <?php echo number_format($total_activities); ?> activities
                                </p>
                            </div>
                        </div>
                        <?php if ($total_pages > 1): ?>
                            <div class="mt-3 sm:mt-0 flex items-center">
                                <div class="bg-white rounded-lg px-3 py-2 border border-gray-200 shadow-sm">
                                    <span class="text-sm font-medium text-gray-600">
                                        Page <?php echo $current_page; ?> of <?php echo $total_pages; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <?php if (empty($activities)): ?>
                    <div class="text-center py-12">
                        <div class="w-16 h-16 mx-auto bg-gray-100 rounded-full flex items-center justify-center mb-4">
                            <i class="fas fa-history text-gray-400 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">No activities found</h3>
                        <p class="text-gray-600 mb-6">No activities match your current search criteria.</p>
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
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Activity</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Details</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($activities as $activity): ?>
                                    <tr class="hover:bg-gray-50 transition-colors duration-200">
                                        <td class="px-6 py-4">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0 h-10 w-10">
                                                    <?php
                                                    $icon_class = 'fas fa-circle';
                                                    $bg_class = 'bg-gray-100 text-gray-600';
                                                    
                                                    switch ($activity['activity_type']) {
                                                        case 'login':
                                                            $icon_class = 'fas fa-sign-in-alt';
                                                            $bg_class = 'bg-green-100 text-green-600';
                                                            break;
                                                        case 'logout':
                                                            $icon_class = 'fas fa-sign-out-alt';
                                                            $bg_class = 'bg-red-100 text-red-600';
                                                            break;
                                                        case 'student_registration':
                                                            $icon_class = 'fas fa-user-plus';
                                                            $bg_class = 'bg-blue-100 text-blue-600';
                                                            break;
                                                        case 'student_approval':
                                                            $icon_class = 'fas fa-check-circle';
                                                            $bg_class = 'bg-green-100 text-green-600';
                                                            break;
                                                        case 'student_rejection':
                                                            $icon_class = 'fas fa-times-circle';
                                                            $bg_class = 'bg-red-100 text-red-600';
                                                            break;
                                                        case 'course_created':
                                                            $icon_class = 'fas fa-plus-circle';
                                                            $bg_class = 'bg-blue-100 text-blue-600';
                                                            break;
                                                        case 'course_updated':
                                                            $icon_class = 'fas fa-edit';
                                                            $bg_class = 'bg-yellow-100 text-yellow-600';
                                                            break;
                                                        case 'course_deleted':
                                                            $icon_class = 'fas fa-trash';
                                                            $bg_class = 'bg-red-100 text-red-600';
                                                            break;
                                                        case 'course_application':
                                                            $icon_class = 'fas fa-file-alt';
                                                            $bg_class = 'bg-blue-100 text-blue-600';
                                                            break;
                                                    }
                                                    ?>
                                                    <div class="h-10 w-10 rounded-full <?php echo $bg_class; ?> flex items-center justify-center">
                                                        <i class="<?php echo $icon_class; ?>"></i>
                                                    </div>
                                                </div>
                                                <div class="ml-4">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $activity['activity_type']))); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500 truncate max-w-xs">
                                                        <?php echo htmlspecialchars($activity['activity_description']); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo $activity['username'] ? htmlspecialchars($activity['username']) : 'System'; ?>
                                            </div>
                                            <div class="text-sm text-gray-500">
                                                <?php echo ucfirst($activity['user_type']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php
                                            $type_class = '';
                                            switch ($activity['user_type']) {
                                                case 'admin':
                                                    $type_class = 'bg-blue-100 text-blue-800 border-blue-200';
                                                    break;
                                                case 'student':
                                                    $type_class = 'bg-green-100 text-green-800 border-green-200';
                                                    break;
                                                default:
                                                    $type_class = 'bg-gray-100 text-gray-800 border-gray-200';
                                            }
                                            ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border <?php echo $type_class; ?>">
                                                <?php echo ucfirst($activity['user_type']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            <div><?php echo date('M j, Y', strtotime($activity['created_at'])); ?></div>
                                            <div><?php echo date('g:i A', strtotime($activity['created_at'])); ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-sm text-gray-500">
                                            <?php if ($activity['ip_address']): ?>
                                                <div class="flex items-center text-xs">
                                                    <i class="fas fa-globe mr-1"></i>
                                                    <?php echo htmlspecialchars($activity['ip_address']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($activity['entity_type'] && $activity['entity_id']): ?>
                                                <div class="flex items-center text-xs mt-1">
                                                    <i class="fas fa-tag mr-1"></i>
                                                    <?php echo htmlspecialchars($activity['entity_type']); ?> #<?php echo $activity['entity_id']; ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Mobile Cards -->
                    <div class="lg:hidden">
                        <?php foreach ($activities as $activity): ?>
                            <div class="border-b border-gray-200 p-4 hover:bg-gray-50 transition-colors duration-200">
                                <div class="flex items-start justify-between">
                                    <div class="flex items-center flex-1 min-w-0">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <?php
                                            $icon_class = 'fas fa-circle';
                                            $bg_class = 'bg-gray-100 text-gray-600';
                                            
                                            switch ($activity['activity_type']) {
                                                case 'login':
                                                    $icon_class = 'fas fa-sign-in-alt';
                                                    $bg_class = 'bg-green-100 text-green-600';
                                                    break;
                                                case 'logout':
                                                    $icon_class = 'fas fa-sign-out-alt';
                                                    $bg_class = 'bg-red-100 text-red-600';
                                                    break;
                                                case 'student_registration':
                                                    $icon_class = 'fas fa-user-plus';
                                                    $bg_class = 'bg-blue-100 text-blue-600';
                                                    break;
                                                case 'student_approval':
                                                    $icon_class = 'fas fa-check-circle';
                                                    $bg_class = 'bg-green-100 text-green-600';
                                                    break;
                                                case 'student_rejection':
                                                    $icon_class = 'fas fa-times-circle';
                                                    $bg_class = 'bg-red-100 text-red-600';
                                                    break;
                                                case 'course_created':
                                                    $icon_class = 'fas fa-plus-circle';
                                                    $bg_class = 'bg-blue-100 text-blue-600';
                                                    break;
                                                case 'course_updated':
                                                    $icon_class = 'fas fa-edit';
                                                    $bg_class = 'bg-yellow-100 text-yellow-600';
                                                    break;
                                                case 'course_deleted':
                                                    $icon_class = 'fas fa-trash';
                                                    $bg_class = 'bg-red-100 text-red-600';
                                                    break;
                                                case 'course_application':
                                                    $icon_class = 'fas fa-file-alt';
                                                    $bg_class = 'bg-blue-100 text-blue-600';
                                                    break;
                                            }
                                            ?>
                                            <div class="h-10 w-10 rounded-full <?php echo $bg_class; ?> flex items-center justify-center">
                                                <i class="<?php echo $icon_class; ?>"></i>
                                            </div>
                                        </div>
                                        <div class="ml-3 flex-1 min-w-0">
                                            <div class="text-sm font-medium text-gray-900 truncate">
                                                <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $activity['activity_type']))); ?>
                                            </div>
                                            <div class="text-sm text-gray-500 truncate">
                                                <?php echo htmlspecialchars($activity['activity_description']); ?>
                                            </div>
                                            <div class="text-xs text-gray-400 mt-1">
                                                <?php echo $activity['username'] ? htmlspecialchars($activity['username']) : 'System'; ?> • 
                                                <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 ml-2">
                                        <?php
                                        $type_class = '';
                                        switch ($activity['user_type']) {
                                            case 'admin':
                                                $type_class = 'bg-blue-100 text-blue-800 border-blue-200';
                                                break;
                                            case 'student':
                                                $type_class = 'bg-green-100 text-green-800 border-green-200';
                                                break;
                                            default:
                                                $type_class = 'bg-gray-100 text-gray-800 border-gray-200';
                                        }
                                        ?>
                                        <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium border <?php echo $type_class; ?>">
                                            <?php echo ucfirst($activity['user_type']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <?php if ($activity['ip_address'] || ($activity['entity_type'] && $activity['entity_id'])): ?>
                                    <div class="mt-3 text-xs text-gray-500 space-y-1">
                                        <?php if ($activity['ip_address']): ?>
                                            <div class="flex items-center">
                                                <i class="fas fa-globe mr-2"></i>
                                                <?php echo htmlspecialchars($activity['ip_address']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if ($activity['entity_type'] && $activity['entity_id']): ?>
                                            <div class="flex items-center">
                                                <i class="fas fa-tag mr-2"></i>
                                                <?php echo htmlspecialchars($activity['entity_type']); ?> #<?php echo $activity['entity_id']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="bg-white px-4 py-3 border-t border-gray-200 sm:px-6">
                        <div class="flex items-center justify-between">
                            <div class="flex-1 flex justify-between sm:hidden">
                                <?php if ($current_page > 1): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" 
                                       class="relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Previous
                                    </a>
                                <?php endif; ?>
                                <?php if ($current_page < $total_pages): ?>
                                    <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" 
                                       class="ml-3 relative inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                        Next
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="hidden sm:flex-1 sm:flex sm:items-center sm:justify-between">
                                <div>
                                    <p class="text-sm text-gray-700">
                                        Showing <span class="font-medium"><?php echo (($current_page - 1) * $limit) + 1; ?></span> to 
                                        <span class="font-medium"><?php echo min($current_page * $limit, $total_activities); ?></span> of 
                                        <span class="font-medium"><?php echo $total_activities; ?></span> results
                                    </p>
                                </div>
                                <div>
                                    <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Pagination">
                                        <?php if ($current_page > 1): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page - 1])); ?>" 
                                               class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50">
                                                <i class="fas fa-chevron-left"></i>
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php for ($i = max(1, $current_page - 2); $i <= min($total_pages, $current_page + 2); $i++): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" 
                                               class="relative inline-flex items-center px-4 py-2 border text-sm font-medium <?php echo ($i == $current_page) ? 'z-10 bg-blue-50 border-blue-500 text-blue-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50'; ?>">
                                                <?php echo $i; ?>
                                            </a>
                                        <?php endfor; ?>
                                        
                                        <?php if ($current_page < $total_pages): ?>
                                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $current_page + 1])); ?>" 
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

    <?php include 'components/admin-scripts.php'; ?>
</body>
</html>
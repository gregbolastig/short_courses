<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_middleware.php';
require_once '../includes/system_activity_logger.php';

requireAdmin();

$database = new Database();
$conn = $database->getConnection();
$logger = new SystemActivityLogger($conn);

$page_title = 'Preferences';
$breadcrumb_items = [
    ['title' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'url' => 'admin-dashboard.php'],
    ['title' => 'Preferences', 'icon' => 'fas fa-cog']
];

$error_message = null;
$success_message = null;

// Handle theme preference update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_theme') {
        $theme = $_POST['theme'] ?? 'light';
        
        if (in_array($theme, ['light', 'dark'])) {
            $_SESSION['theme_preference'] = $theme;
            
            $logger->log(
                'theme_changed',
                "Admin changed theme to {$theme} mode",
                'admin',
                $_SESSION['user_id']
            );
            
            $success_message = 'Theme preference updated successfully!';
        }
    }
}

// Get current theme preference
$current_theme = $_SESSION['theme_preference'] ?? 'light';

// Get system statistics
try {
    $stmt = $conn->query("SELECT COUNT(*) as total FROM shortcourse_students WHERE deleted_at IS NULL");
    $total_students = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM shortcourse_courses WHERE deleted_at IS NULL");
    $total_courses = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM shortcourse_course_applications WHERE deleted_at IS NULL");
    $total_applications = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    $stmt = $conn->query("SELECT COUNT(*) as total FROM shortcourse_system_activities");
    $total_activities = $stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
    $total_students = 0;
    $total_courses = 0;
    $total_applications = 0;
    $total_activities = 0;
}
?>
<!DOCTYPE html>
<html lang="en" class="<?php echo $current_theme === 'dark' ? 'dark' : ''; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - Admin Panel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            darkMode: 'class',
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
    <style>
        /* Dark mode styles */
        .dark body {
            background-color: #1a202c;
            color: #e2e8f0;
        }
        .dark .bg-gray-50 {
            background-color: #1a202c;
        }
        .dark .bg-white {
            background-color: #2d3748;
        }
        .dark .text-gray-900 {
            color: #f7fafc;
        }
        .dark .text-gray-800 {
            color: #e2e8f0;
        }
        .dark .text-gray-700 {
            color: #cbd5e0;
        }
        .dark .text-gray-600 {
            color: #a0aec0;
        }
        .dark .text-gray-500 {
            color: #718096;
        }
        .dark .border-gray-100 {
            border-color: #4a5568;
        }
        .dark .border-gray-200 {
            border-color: #4a5568;
        }
        .dark .border-gray-300 {
            border-color: #4a5568;
        }
        .dark .bg-gray-100 {
            background-color: #374151;
        }
        .dark .bg-gray-200 {
            background-color: #4b5563;
        }
        .dark .hover\:bg-gray-50:hover {
            background-color: #374151;
        }
        .dark .hover\:bg-gray-100:hover {
            background-color: #4b5563;
        }
        .dark input, .dark select, .dark textarea {
            background-color: #374151;
            border-color: #4a5568;
            color: #f7fafc;
        }
        .dark input:focus, .dark select:focus, .dark textarea:focus {
            background-color: #2d3748;
            border-color: #60a5fa;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>
    
    <?php include 'components/sidebar.php'; ?>
    
    <div id="main-content" class="min-h-screen transition-all duration-300 ease-in-out ml-0 md:ml-64">
        <?php include 'components/header.php'; ?>
        
        <main class="p-4 md:p-6">
            <div class="max-w-6xl mx-auto">
                <!-- Page Header -->
                <div class="mb-6">
                    <h1 class="text-3xl font-bold text-gray-900">System Preferences</h1>
                    <p class="text-gray-600 mt-2">Customize your interface theme</p>
                </div>
                
                <?php if ($error_message): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        showToast('<?php echo addslashes($error_message); ?>', 'error');
                    });
                </script>
                <?php endif; ?>
                
                <?php if ($success_message): ?>
                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        showToast('<?php echo addslashes($success_message); ?>', 'success');
                    });
                </script>
                <?php endif; ?>
                
                <!-- Theme Preferences -->
                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mb-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-indigo-100 rounded-xl p-3 mr-4">
                            <i class="fas fa-palette text-indigo-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Appearance</h2>
                            <p class="text-sm text-gray-600">Customize your interface theme</p>
                        </div>
                    </div>
                    
                    <form method="POST" id="themeForm">
                        <input type="hidden" name="action" value="update_theme">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <!-- Light Mode -->
                            <label class="relative cursor-pointer">
                                <input type="radio" name="theme" value="light" 
                                       <?php echo $current_theme === 'light' ? 'checked' : ''; ?>
                                       onchange="document.getElementById('themeForm').submit()"
                                       class="peer sr-only">
                                <div class="border-2 border-gray-300 rounded-xl p-6 transition-all duration-200 peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-blue-300">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center">
                                            <div class="bg-yellow-100 rounded-lg p-3 mr-3">
                                                <i class="fas fa-sun text-yellow-500 text-2xl"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-lg font-bold text-gray-900">Light Mode</h3>
                                                <p class="text-sm text-gray-600">Bright and clean</p>
                                            </div>
                                        </div>
                                        <div class="hidden peer-checked:block">
                                            <i class="fas fa-check-circle text-blue-500 text-2xl"></i>
                                        </div>
                                    </div>
                                    <div class="bg-white border border-gray-200 rounded-lg p-4 space-y-2">
                                        <div class="h-2 bg-gray-200 rounded w-3/4"></div>
                                        <div class="h-2 bg-gray-200 rounded w-1/2"></div>
                                        <div class="h-2 bg-gray-200 rounded w-2/3"></div>
                                    </div>
                                </div>
                            </label>
                            
                            <!-- Dark Mode -->
                            <label class="relative cursor-pointer">
                                <input type="radio" name="theme" value="dark" 
                                       <?php echo $current_theme === 'dark' ? 'checked' : ''; ?>
                                       onchange="document.getElementById('themeForm').submit()"
                                       class="peer sr-only">
                                <div class="border-2 border-gray-300 rounded-xl p-6 transition-all duration-200 peer-checked:border-blue-500 peer-checked:bg-blue-50 hover:border-blue-300">
                                    <div class="flex items-center justify-between mb-4">
                                        <div class="flex items-center">
                                            <div class="bg-indigo-100 rounded-lg p-3 mr-3">
                                                <i class="fas fa-moon text-indigo-600 text-2xl"></i>
                                            </div>
                                            <div>
                                                <h3 class="text-lg font-bold text-gray-900">Dark Mode</h3>
                                                <p class="text-sm text-gray-600">Easy on the eyes</p>
                                            </div>
                                        </div>
                                        <div class="hidden peer-checked:block">
                                            <i class="fas fa-check-circle text-blue-500 text-2xl"></i>
                                        </div>
                                    </div>
                                    <div class="bg-gray-800 border border-gray-700 rounded-lg p-4 space-y-2">
                                        <div class="h-2 bg-gray-700 rounded w-3/4"></div>
                                        <div class="h-2 bg-gray-700 rounded w-1/2"></div>
                                        <div class="h-2 bg-gray-700 rounded w-2/3"></div>
                                    </div>
                                </div>
                            </label>
                        </div>
                        
                        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg dark:bg-blue-900 dark:border-blue-700">
                            <div class="flex items-start">
                                <i class="fas fa-info-circle text-blue-500 mt-0.5 mr-3 dark:text-blue-400"></i>
                                <div>
                                    <p class="text-sm text-blue-900 font-medium dark:text-blue-100">Theme Applied</p>
                                    <p class="text-xs text-blue-700 mt-1 dark:text-blue-300">Your theme preference has been saved and is now active. The <?php echo $current_theme === 'dark' ? 'dark' : 'light'; ?> mode provides an optimized viewing experience.</p>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- System Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Students</p>
                                <p class="text-3xl font-bold text-blue-600 mt-2"><?php echo number_format($total_students); ?></p>
                            </div>
                            <div class="bg-blue-100 rounded-xl p-4">
                                <i class="fas fa-users text-blue-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Total Courses</p>
                                <p class="text-3xl font-bold text-green-600 mt-2"><?php echo number_format($total_courses); ?></p>
                            </div>
                            <div class="bg-green-100 rounded-xl p-4">
                                <i class="fas fa-graduation-cap text-green-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">Applications</p>
                                <p class="text-3xl font-bold text-purple-600 mt-2"><?php echo number_format($total_applications); ?></p>
                            </div>
                            <div class="bg-purple-100 rounded-xl p-4">
                                <i class="fas fa-file-alt text-purple-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-600">System Activities</p>
                                <p class="text-3xl font-bold text-orange-600 mt-2"><?php echo number_format($total_activities); ?></p>
                            </div>
                            <div class="bg-orange-100 rounded-xl p-4">
                                <i class="fas fa-history text-orange-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6 mb-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-blue-100 rounded-xl p-3 mr-4">
                            <i class="fas fa-info-circle text-blue-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">System Information</h2>
                            <p class="text-sm text-gray-600">Current system configuration</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-server text-gray-400 mr-2"></i>
                                <span class="text-sm font-medium text-gray-700">PHP Version</span>
                            </div>
                            <p class="text-lg font-semibold text-gray-900"><?php echo phpversion(); ?></p>
                        </div>
                        
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-database text-gray-400 mr-2"></i>
                                <span class="text-sm font-medium text-gray-700">Database</span>
                            </div>
                            <p class="text-lg font-semibold text-gray-900">MySQL/MariaDB</p>
                        </div>
                        
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-clock text-gray-400 mr-2"></i>
                                <span class="text-sm font-medium text-gray-700">Server Time</span>
                            </div>
                            <p class="text-lg font-semibold text-gray-900"><?php echo date('Y-m-d H:i:s'); ?></p>
                        </div>
                        
                        <div class="border border-gray-200 rounded-lg p-4">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-globe text-gray-400 mr-2"></i>
                                <span class="text-sm font-medium text-gray-700">Timezone</span>
                            </div>
                            <p class="text-lg font-semibold text-gray-900"><?php echo date_default_timezone_get(); ?></p>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="bg-white rounded-2xl shadow-xl border border-gray-100 p-6">
                    <div class="flex items-center mb-6">
                        <div class="bg-purple-100 rounded-xl p-3 mr-4">
                            <i class="fas fa-bolt text-purple-600 text-xl"></i>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900">Quick Actions</h2>
                            <p class="text-sm text-gray-600">Common administrative tasks</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <a href="admin-system-activity.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-blue-50 hover:border-blue-300 transition-all duration-200">
                            <div class="bg-blue-100 rounded-lg p-3 mr-4">
                                <i class="fas fa-history text-blue-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">View Activity Log</p>
                                <p class="text-xs text-gray-600">System activities</p>
                            </div>
                        </a>
                        
                        <a href="admin-manage-students.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-green-50 hover:border-green-300 transition-all duration-200">
                            <div class="bg-green-100 rounded-lg p-3 mr-4">
                                <i class="fas fa-users text-green-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Manage Students</p>
                                <p class="text-xs text-gray-600">Student records</p>
                            </div>
                        </a>
                        
                        <a href="admin-manage-courses.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-purple-50 hover:border-purple-300 transition-all duration-200">
                            <div class="bg-purple-100 rounded-lg p-3 mr-4">
                                <i class="fas fa-graduation-cap text-purple-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Manage Courses</p>
                                <p class="text-xs text-gray-600">Course catalog</p>
                            </div>
                        </a>
                        
                        <a href="admin-manage-checklist.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-orange-50 hover:border-orange-300 transition-all duration-200">
                            <div class="bg-orange-100 rounded-lg p-3 mr-4">
                                <i class="fas fa-tasks text-orange-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Manage Checklist</p>
                                <p class="text-xs text-gray-600">Document requirements</p>
                            </div>
                        </a>
                        
                        <a href="admin-course-application.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-red-50 hover:border-red-300 transition-all duration-200">
                            <div class="bg-red-100 rounded-lg p-3 mr-4">
                                <i class="fas fa-file-alt text-red-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Course Applications</p>
                                <p class="text-xs text-gray-600">Review applications</p>
                            </div>
                        </a>
                        
                        <a href="admin-profile.php" class="flex items-center p-4 border border-gray-200 rounded-lg hover:bg-indigo-50 hover:border-indigo-300 transition-all duration-200">
                            <div class="bg-indigo-100 rounded-lg p-3 mr-4">
                                <i class="fas fa-user-cog text-indigo-600"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-gray-900">Account Settings</p>
                                <p class="text-xs text-gray-600">Profile & security</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <?php include 'components/admin-scripts.php'; ?>
</body>
</html>

<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

// Handle approval/rejection actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $student_id = $_GET['id'];
    
    if (in_array($action, ['approve', 'reject'])) {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            $status = ($action === 'approve') ? 'approved' : 'rejected';
            $stmt = $conn->prepare("UPDATE students SET status = :status, approved_by = :admin_id, approved_at = NOW() WHERE id = :id");
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':admin_id', $_SESSION['user_id']);
            $stmt->bindParam(':id', $student_id);
            
            if ($stmt->execute()) {
                $success_message = 'Student registration ' . $status . ' successfully.';
            } else {
                $error_message = 'Failed to update student status.';
            }
        } catch (PDOException $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get statistics
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
    
    // Recent registrations (last 7 days)
    $stmt = $conn->query("SELECT COUNT(*) as recent FROM students WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
    $recent_registrations = $stmt->fetch(PDO::FETCH_ASSOC)['recent'];
    
    // Get recent students
    $stmt = $conn->query("SELECT id, student_id, first_name, last_name, email, status, created_at FROM students ORDER BY created_at DESC LIMIT 10");
    $recent_students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Student Registration System</title>
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
                        },
                        secondary: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            500: '#334155',
                            600: '#475569',
                            700: '#64748b'
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
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes slideIn {
            from { transform: translateX(-20px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .animate-pulse-slow {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 3px;
        }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Glassmorphism effect */
        .glass-effect {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.9);
        }
        
        /* Gradient text */
        .gradient-text {
            background: linear-gradient(135deg, #1e3a8a, #1d4ed8);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Card hover effects */
        .card-hover {
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card-hover:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }
        
        /* Button animations */
        .btn-animate {
            transition: all 0.2s ease-in-out;
        }
        .btn-animate:hover {
            transform: translateY(-1px);
        }
        .btn-animate:active {
            transform: translateY(0);
        }
        
        /* Notification badge pulse */
        .notification-badge {
            animation: pulse 2s infinite;
        }
        
        /* Sidebar navigation improvements */
        .nav-item {
            position: relative;
            overflow: hidden;
        }
        .nav-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.1), transparent);
            transition: left 0.5s;
        }
        .nav-item:hover::before {
            left: 100%;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="flex h-screen overflow-hidden">
        <!-- Sidebar -->
        <div class="hidden md:flex md:flex-shrink-0">
            <div class="flex flex-col w-64">
                <div class="flex flex-col flex-grow pt-5 pb-4 overflow-y-auto bg-gradient-to-b from-primary-800 to-primary-900 shadow-xl">
                    <!-- Logo/Brand -->
                    <div class="flex items-center flex-shrink-0 px-4 mb-8">
                        <div class="bg-white bg-opacity-20 p-3 rounded-lg mr-3">
                            <i class="fas fa-graduation-cap text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-white">Admin Panel</h1>
                            <p class="text-primary-200 text-sm">Student System</p>
                        </div>
                    </div>
                    
                    <!-- Navigation -->
                    <nav class="mt-5 flex-1 px-2 space-y-2">
                        <!-- Dashboard -->
                        <a href="dashboard.php" class="bg-primary-700 text-white group flex items-center px-3 py-3 text-sm font-medium rounded-lg transition-colors duration-200">
                            <i class="fas fa-tachometer-alt text-primary-200 mr-3 text-lg"></i>
                            Dashboard
                        </a>
                        
                        <!-- Student Management -->
                        <div class="space-y-1">
                            <div class="text-primary-300 px-3 py-2 text-xs font-semibold uppercase tracking-wider">
                                Student Management
                            </div>
                            <a href="manage_students.php" class="text-primary-200 hover:bg-primary-700 hover:text-white group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-200">
                                <i class="fas fa-users text-primary-400 group-hover:text-primary-200 mr-3"></i>
                                All Students
                            </a>
                            <a href="pending_approvals.php" class="text-primary-200 hover:bg-primary-700 hover:text-white group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-200">
                                <i class="fas fa-clock text-primary-400 group-hover:text-primary-200 mr-3"></i>
                                Pending Approvals
                                <?php if ($pending_approvals > 0): ?>
                                    <span class="ml-auto bg-yellow-500 text-white text-xs rounded-full px-2 py-1 min-w-[20px] text-center">
                                        <?php echo $pending_approvals; ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </div>
                        
                        <!-- Adviser List -->
                        <div class="space-y-1">
                            <div class="text-primary-300 px-3 py-2 text-xs font-semibold uppercase tracking-wider">
                                Academic Staff
                            </div>
                            <a href="adviser_list.php" class="text-primary-200 hover:bg-primary-700 hover:text-white group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-200">
                                <i class="fas fa-chalkboard-teacher text-primary-400 group-hover:text-primary-200 mr-3"></i>
                                Adviser List
                            </a>
                            <a href="add_adviser.php" class="text-primary-200 hover:bg-primary-700 hover:text-white group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-200">
                                <i class="fas fa-user-plus text-primary-400 group-hover:text-primary-200 mr-3"></i>
                                Add Adviser
                            </a>
                        </div>
                        
                        <!-- Category -->
                        <div class="space-y-1">
                            <div class="text-primary-300 px-3 py-2 text-xs font-semibold uppercase tracking-wider">
                                Categories
                            </div>
                            <a href="categories.php" class="text-primary-200 hover:bg-primary-700 hover:text-white group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-200">
                                <i class="fas fa-tags text-primary-400 group-hover:text-primary-200 mr-3"></i>
                                Manage Categories
                            </a>
                            <a href="add_category.php" class="text-primary-200 hover:bg-primary-700 hover:text-white group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-200">
                                <i class="fas fa-plus-circle text-primary-400 group-hover:text-primary-200 mr-3"></i>
                                Add Category
                            </a>
                        </div>
                        
                        <!-- System -->
                        <div class="space-y-1">
                            <div class="text-primary-300 px-3 py-2 text-xs font-semibold uppercase tracking-wider">
                                System
                            </div>
                            <a href="../student/register.php" class="text-primary-200 hover:bg-primary-700 hover:text-white group flex items-center px-3 py-2 text-sm font-medium rounded-lg transition-colors duration-200">
                                <i class="fas fa-external-link-alt text-primary-400 group-hover:text-primary-200 mr-3"></i>
                                Student Portal
                            </a>
                        </div>
                    </nav>
                    
                    <!-- User Info & Profile Dropdown -->
                    <div class="flex-shrink-0 border-t border-primary-700 p-4">
                        <div class="relative">
                            <button onclick="toggleProfileDropdown()" class="flex items-center w-full text-left hover:bg-primary-700 rounded-lg p-2 transition-colors duration-200 focus:outline-none focus:ring-2 focus:ring-primary-500">
                                <div class="bg-gradient-to-br from-primary-500 to-primary-600 rounded-full p-2 mr-3 shadow-lg relative">
                                    <i class="fas fa-user text-white text-sm"></i>
                                    <?php if ($pending_approvals > 0): ?>
                                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-semibold animate-pulse">
                                            <?php echo $pending_approvals; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-white truncate">
                                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                                    </p>
                                    <p class="text-xs text-primary-300 truncate">
                                        Administrator
                                        <?php if ($pending_approvals > 0): ?>
                                            <span class="ml-2 text-yellow-300">• <?php echo $pending_approvals; ?> notification<?php echo $pending_approvals > 1 ? 's' : ''; ?></span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <i class="fas fa-chevron-up text-primary-300 text-xs transition-transform duration-200" id="profile-chevron"></i>
                            </button>
                            
                            <!-- Profile Dropdown -->
                            <div id="profile-dropdown" class="hidden absolute bottom-full left-0 right-0 mb-2 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-50">
                                <div class="px-4 py-3 border-b border-gray-100">
                                    <div class="flex items-center space-x-3">
                                        <div class="bg-gradient-to-br from-primary-500 to-primary-700 rounded-full p-2">
                                            <i class="fas fa-user text-white text-sm"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($_SESSION['username']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo htmlspecialchars($_SESSION['email'] ?? 'admin@system.com'); ?></p>
                                            <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 mt-1">
                                                <i class="fas fa-circle text-green-400 mr-1" style="font-size: 6px;"></i>
                                                Online
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Notifications Section -->
                                <?php if ($pending_approvals > 0): ?>
                                <div class="px-4 py-3 border-b border-gray-100 bg-yellow-50">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center">
                                            <div class="bg-yellow-100 rounded-full p-2 mr-3">
                                                <i class="fas fa-bell text-yellow-600"></i>
                                            </div>
                                            <div>
                                                <p class="text-sm font-medium text-gray-900">Notifications</p>
                                                <p class="text-xs text-gray-600"><?php echo $pending_approvals; ?> pending approval<?php echo $pending_approvals > 1 ? 's' : ''; ?></p>
                                            </div>
                                        </div>
                                        <span class="bg-red-500 text-white text-xs rounded-full h-6 w-6 flex items-center justify-center font-semibold animate-pulse">
                                            <?php echo $pending_approvals; ?>
                                        </span>
                                    </div>
                                    <div class="mt-2">
                                        <a href="pending_approvals.php" class="text-xs text-yellow-700 hover:text-yellow-800 font-medium flex items-center">
                                            <i class="fas fa-arrow-right mr-1"></i>
                                            Review pending approvals
                                        </a>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="py-1">
                                    <a href="profile.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200">
                                        <div class="bg-blue-100 rounded-lg p-2 mr-3">
                                            <i class="fas fa-user-cog text-blue-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium">Account Settings</p>
                                            <p class="text-xs text-gray-500">Manage your profile</p>
                                        </div>
                                    </a>
                                    <a href="preferences.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200">
                                        <div class="bg-purple-100 rounded-lg p-2 mr-3">
                                            <i class="fas fa-cog text-purple-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium">Preferences</p>
                                            <p class="text-xs text-gray-500">System settings</p>
                                        </div>
                                    </a>
                                    <a href="pending_approvals.php" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200">
                                        <div class="bg-yellow-100 rounded-lg p-2 mr-3">
                                            <i class="fas fa-bell text-yellow-600"></i>
                                        </div>
                                        <div class="flex-1 flex items-center justify-between">
                                            <div>
                                                <p class="font-medium">Notifications</p>
                                                <p class="text-xs text-gray-500">Pending approvals</p>
                                            </div>
                                            <?php if ($pending_approvals > 0): ?>
                                                <span class="bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-semibold">
                                                    <?php echo $pending_approvals; ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                    <div class="border-t border-gray-100 my-1"></div>
                                    <a href="../auth/logout.php" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors duration-200">
                                        <div class="bg-red-100 rounded-lg p-2 mr-3">
                                            <i class="fas fa-sign-out-alt text-red-600"></i>
                                        </div>
                                        <div>
                                            <p class="font-medium">Sign Out</p>
                                            <p class="text-xs text-red-500">End your session</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Mobile sidebar overlay -->
        <div id="mobile-sidebar-overlay" class="fixed inset-0 z-40 md:hidden hidden">
            <div class="fixed inset-0 bg-gray-600 bg-opacity-75" onclick="toggleMobileSidebar()"></div>
            <div class="relative flex-1 flex flex-col max-w-xs w-full bg-gradient-to-b from-primary-800 to-primary-900">
                <!-- Mobile sidebar content (same as desktop) -->
                <div class="flex flex-col flex-grow pt-5 pb-4 overflow-y-auto">
                    <div class="flex items-center justify-between flex-shrink-0 px-4 mb-8">
                        <div class="flex items-center">
                            <div class="bg-white bg-opacity-20 p-3 rounded-lg mr-3">
                                <i class="fas fa-graduation-cap text-2xl text-white"></i>
                            </div>
                            <div>
                                <h1 class="text-xl font-bold text-white">Admin Panel</h1>
                                <p class="text-primary-200 text-sm">Student System</p>
                            </div>
                        </div>
                        <button onclick="toggleMobileSidebar()" class="text-primary-200 hover:text-white">
                            <i class="fas fa-times text-xl"></i>
                        </button>
                    </div>
                    <!-- Same navigation as desktop -->
                </div>
            </div>
        </div>
        
        <!-- Main content -->
        <div class="flex flex-col w-0 flex-1 overflow-hidden">
            <!-- Top header -->
            <div class="relative z-10 flex-shrink-0 flex h-20 bg-white shadow-lg border-b border-gray-200">
                <button onclick="toggleMobileSidebar()" class="px-4 border-r border-gray-200 text-gray-500 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-primary-500 md:hidden">
                    <i class="fas fa-bars text-lg"></i>
                </button>
                <div class="flex-1 px-6 flex justify-between items-center">
                    <div class="flex items-center">
                        <div class="mr-6">
                            <h1 class="text-2xl font-bold gradient-text">Dashboard</h1>
                            <div class="flex items-center space-x-4 mt-1">
                                <p class="text-sm text-gray-600 flex items-center">
                                    <i class="fas fa-calendar-alt text-gray-400 mr-2"></i>
                                    <?php echo date('l, F j, Y'); ?>
                                </p>
                                <p class="text-sm text-gray-600 flex items-center">
                                    <i class="fas fa-clock text-gray-400 mr-2"></i>
                                    <span id="live-clock"></span>
                                </p>
                            </div>
                        </div>
                        <div class="hidden lg:block">
                            <div class="bg-gradient-to-r from-primary-50 to-primary-100 rounded-lg p-3 border border-primary-200">
                                <p class="text-sm text-primary-800">
                                    <i class="fas fa-hand-wave text-primary-600 mr-2"></i>
                                    Welcome back, <span class="font-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></span>!
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center space-x-4">
                        <!-- Search Bar -->
                        <div class="hidden md:block relative">
                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                <i class="fas fa-search text-gray-400"></i>
                            </div>
                            <input type="text" placeholder="Search students..." class="block w-64 pl-10 pr-3 py-2 border border-gray-300 rounded-lg leading-5 bg-white placeholder-gray-500 focus:outline-none focus:placeholder-gray-400 focus:ring-1 focus:ring-primary-500 focus:border-primary-500">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main content area -->
            <main class="flex-1 relative overflow-y-auto focus:outline-none">
                <div class="py-6">
                    <div class="max-w-7xl mx-auto px-4 sm:px-6 md:px-8">
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
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                            <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 card-hover">
                                <div class="p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="bg-gradient-to-br from-blue-100 to-blue-200 rounded-xl p-4 shadow-inner">
                                                <i class="fas fa-users text-blue-600 text-2xl"></i>
                                            </div>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Total Students</dt>
                                                <dd class="text-3xl font-bold text-gray-900 animate-pulse-slow"><?php echo $total_students; ?></dd>
                                                <dd class="text-xs text-green-600 flex items-center mt-1">
                                                    <i class="fas fa-arrow-up mr-1"></i>
                                                    +12% from last month
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-r from-blue-50 to-blue-100 px-6 py-3">
                                    <a href="manage_students.php" class="text-sm text-blue-700 hover:text-blue-800 font-medium flex items-center">
                                        View all students
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 card-hover">
                                <div class="p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="bg-gradient-to-br from-yellow-100 to-yellow-200 rounded-xl p-4 shadow-inner">
                                                <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                                            </div>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Pending Approvals</dt>
                                                <dd class="text-3xl font-bold text-yellow-600 animate-pulse-slow"><?php echo $pending_approvals; ?></dd>
                                                <?php if ($pending_approvals > 0): ?>
                                                    <dd class="text-xs text-red-600 flex items-center mt-1">
                                                        <i class="fas fa-exclamation-triangle mr-1"></i>
                                                        Requires attention
                                                    </dd>
                                                <?php else: ?>
                                                    <dd class="text-xs text-green-600 flex items-center mt-1">
                                                        <i class="fas fa-check-circle mr-1"></i>
                                                        All caught up!
                                                    </dd>
                                                <?php endif; ?>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-r from-yellow-50 to-yellow-100 px-6 py-3">
                                    <a href="pending_approvals.php" class="text-sm text-yellow-700 hover:text-yellow-800 font-medium flex items-center">
                                        Review pending
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 card-hover">
                                <div class="p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="bg-gradient-to-br from-green-100 to-green-200 rounded-xl p-4 shadow-inner">
                                                <i class="fas fa-check-circle text-green-600 text-2xl"></i>
                                            </div>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Approved Students</dt>
                                                <dd class="text-3xl font-bold text-green-600 animate-pulse-slow"><?php echo $approved_students; ?></dd>
                                                <dd class="text-xs text-green-600 flex items-center mt-1">
                                                    <i class="fas fa-check mr-1"></i>
                                                    <?php echo $approved_students > 0 ? round(($approved_students / max($total_students, 1)) * 100) : 0; ?>% approval rate
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-r from-green-50 to-green-100 px-6 py-3">
                                    <a href="manage_students.php?status=approved" class="text-sm text-green-700 hover:text-green-800 font-medium flex items-center">
                                        View approved
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </div>
                            </div>

                            <div class="bg-white overflow-hidden shadow-lg rounded-xl border border-gray-100 card-hover">
                                <div class="p-6">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0">
                                            <div class="bg-gradient-to-br from-purple-100 to-purple-200 rounded-xl p-4 shadow-inner">
                                                <i class="fas fa-chart-line text-purple-600 text-2xl"></i>
                                            </div>
                                        </div>
                                        <div class="ml-5 w-0 flex-1">
                                            <dl>
                                                <dt class="text-sm font-medium text-gray-500 truncate">Recent (7 days)</dt>
                                                <dd class="text-3xl font-bold text-purple-600 animate-pulse-slow"><?php echo $recent_registrations; ?></dd>
                                                <dd class="text-xs text-purple-600 flex items-center mt-1">
                                                    <i class="fas fa-trending-up mr-1"></i>
                                                    This week's activity
                                                </dd>
                                            </dl>
                                        </div>
                                    </div>
                                </div>
                                <div class="bg-gradient-to-r from-purple-50 to-purple-100 px-6 py-3">
                                    <a href="manage_students.php?filter=recent" class="text-sm text-purple-700 hover:text-purple-800 font-medium flex items-center">
                                        View recent
                                        <i class="fas fa-arrow-right ml-2"></i>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Students Table -->
                        <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100">
                            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                                <div class="flex items-center justify-between">
                                    <h3 class="text-lg font-semibold text-gray-900">
                                        <i class="fas fa-history text-gray-500 mr-2"></i>
                                        Recent Student Registrations
                                    </h3>
                                    <a href="manage_students.php" class="text-sm text-primary-600 hover:text-primary-500 font-medium flex items-center">
                                        View all
                                        <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            </div>
                            
                            <?php if (empty($recent_students)): ?>
                                <div class="text-center py-12">
                                    <div class="bg-gray-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                                        <i class="fas fa-users text-gray-400 text-2xl"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-2">No students registered yet</h3>
                                    <p class="text-gray-500 mb-4">Get started by having students register through the student portal.</p>
                                    <a href="../student/register.php" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-lg text-white bg-primary-600 hover:bg-primary-700">
                                        <i class="fas fa-external-link-alt mr-2"></i>
                                        Go to Student Portal
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-200">
                                        <thead class="bg-gray-50">
                                            <tr>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Student ID</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registration Date</th>
                                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="bg-white divide-y divide-gray-200">
                                            <?php foreach ($recent_students as $student): ?>
                                                <tr class="hover:bg-gray-50 transition-colors duration-200">
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm font-medium text-gray-900">
                                                            <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-900 font-mono bg-gray-100 px-2 py-1 rounded">
                                                            <?php echo htmlspecialchars($student['student_id']); ?>
                                                        </div>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap">
                                                        <div class="text-sm text-gray-500">
                                                            <?php echo htmlspecialchars($student['email']); ?>
                                                        </div>
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
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                        <?php echo date('M j, Y g:i A', strtotime($student['created_at'])); ?>
                                                    </td>
                                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                        <div class="flex items-center space-x-3">
                                                            <a href="view_student.php?id=<?php echo $student['id']; ?>" 
                                                               class="text-primary-600 hover:text-primary-900 flex items-center">
                                                                <i class="fas fa-eye mr-1"></i>View
                                                            </a>
                                                            
                                                            <?php if ($student['status'] === 'pending'): ?>
                                                                <a href="?action=approve&id=<?php echo $student['id']; ?>" 
                                                                   class="text-green-600 hover:text-green-900 flex items-center"
                                                                   onclick="return confirm('Are you sure you want to approve this student?')">
                                                                    <i class="fas fa-check mr-1"></i>Approve
                                                                </a>
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
                            <?php endif; ?>
                        </div>

                        <!-- Quick Actions -->
                        <div class="mt-8 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div class="bg-white shadow-lg rounded-xl p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                                <div class="flex items-center mb-4">
                                    <div class="bg-blue-100 rounded-lg p-3 mr-4">
                                        <i class="fas fa-users text-blue-600 text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900">Student Management</h3>
                                </div>
                                <p class="text-gray-600 mb-4">Manage all student records, approvals, and registrations.</p>
                                <div class="space-y-2">
                                    <a href="manage_students.php" class="block text-primary-600 hover:text-primary-700 text-sm font-medium">
                                        <i class="fas fa-arrow-right mr-1"></i>View All Students
                                    </a>
                                    <a href="pending_approvals.php" class="block text-primary-600 hover:text-primary-700 text-sm font-medium">
                                        <i class="fas fa-arrow-right mr-1"></i>Pending Approvals (<?php echo $pending_approvals; ?>)
                                    </a>
                                </div>
                            </div>
                            
                            <div class="bg-white shadow-lg rounded-xl p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                                <div class="flex items-center mb-4">
                                    <div class="bg-green-100 rounded-lg p-3 mr-4">
                                        <i class="fas fa-chalkboard-teacher text-green-600 text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900">Adviser Management</h3>
                                </div>
                                <p class="text-gray-600 mb-4">Manage academic advisers and their assignments.</p>
                                <div class="space-y-2">
                                    <a href="adviser_list.php" class="block text-primary-600 hover:text-primary-700 text-sm font-medium">
                                        <i class="fas fa-arrow-right mr-1"></i>View Adviser List
                                    </a>
                                    <a href="add_adviser.php" class="block text-primary-600 hover:text-primary-700 text-sm font-medium">
                                        <i class="fas fa-arrow-right mr-1"></i>Add New Adviser
                                    </a>
                                </div>
                            </div>
                            
                            <div class="bg-white shadow-lg rounded-xl p-6 border border-gray-100 hover:shadow-xl transition-shadow duration-300">
                                <div class="flex items-center mb-4">
                                    <div class="bg-purple-100 rounded-lg p-3 mr-4">
                                        <i class="fas fa-tags text-purple-600 text-xl"></i>
                                    </div>
                                    <h3 class="text-lg font-semibold text-gray-900">Category Management</h3>
                                </div>
                                <p class="text-gray-600 mb-4">Organize and manage student categories and classifications.</p>
                                <div class="space-y-2">
                                    <a href="categories.php" class="block text-primary-600 hover:text-primary-700 text-sm font-medium">
                                        <i class="fas fa-arrow-right mr-1"></i>Manage Categories
                                    </a>
                                    <a href="add_category.php" class="block text-primary-600 hover:text-primary-700 text-sm font-medium">
                                        <i class="fas fa-arrow-right mr-1"></i>Add New Category
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script>
        function toggleMobileSidebar() {
            const overlay = document.getElementById('mobile-sidebar-overlay');
            overlay.classList.toggle('hidden');
        }
        
        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profile-dropdown');
            const chevron = document.getElementById('profile-chevron');
            
            dropdown.classList.toggle('hidden');
            chevron.classList.toggle('rotate-180');
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            // Close mobile sidebar
            const overlay = document.getElementById('mobile-sidebar-overlay');
            const sidebar = overlay.querySelector('.relative');
            const toggleButton = document.querySelector('[onclick="toggleMobileSidebar()"]');
            
            if (!overlay.classList.contains('hidden') && 
                !sidebar.contains(event.target) && 
                !toggleButton.contains(event.target)) {
                overlay.classList.add('hidden');
            }
            
            // Close profile dropdown
            const profileDropdown = document.getElementById('profile-dropdown');
            const profileButton = document.querySelector('[onclick="toggleProfileDropdown()"]');
            
            if (profileButton && profileDropdown && 
                !profileButton.contains(event.target) && 
                !profileDropdown.contains(event.target)) {
                profileDropdown.classList.add('hidden');
                const chevron = document.getElementById('profile-chevron');
                if (chevron) chevron.classList.remove('rotate-180');
            }
        });
        
        // Add smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });
        
        // Add loading states to action buttons
        document.querySelectorAll('a[href*="action="]').forEach(link => {
            link.addEventListener('click', function() {
                const icon = this.querySelector('i');
                if (icon) {
                    icon.className = 'fas fa-spinner fa-spin mr-1';
                }
                this.style.pointerEvents = 'none';
                this.style.opacity = '0.7';
            });
        });
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.animate-fade-in');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.5s ease-out';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
        
        // Add real-time clock
        function updateClock() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('en-US', { 
                hour12: true, 
                hour: 'numeric', 
                minute: '2-digit' 
            });
            const clockElement = document.getElementById('live-clock');
            if (clockElement) {
                clockElement.textContent = timeString;
            }
        }
        
        // Update clock every minute
        setInterval(updateClock, 60000);
        updateClock(); // Initial call
    </script>
</body>
</html>
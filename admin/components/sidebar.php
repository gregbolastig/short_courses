<?php
// Sidebar component for admin panel
// This component requires $pending_approvals variable to be set
?>

<!-- Sidebar -->
<div id="sidebar" class="fixed top-0 left-0 h-screen w-64 z-40 transition-all duration-300 ease-in-out transform -translate-x-full md:translate-x-0 bg-blue-900 shadow-2xl">
    <div class="flex flex-col h-full">
        <div class="flex flex-col flex-grow pt-4 md:pt-5 pb-4 overflow-y-auto custom-scrollbar">
            <!-- Logo/Brand - Redesigned for consistency -->
            <div class="flex items-center flex-shrink-0 px-3 md:px-4 mb-6 md:mb-8">
                <div class="flex items-center justify-center w-10 h-10 bg-white bg-opacity-20 rounded-lg flex-shrink-0 backdrop-blur-sm">
                    <img src="/JZGMSAT/assets/images/logo.png" alt="Logo" class="w-6 h-6 object-contain" onerror="console.log('Logo failed to load')">
                </div>
                <div id="sidebar-text" class="transition-all duration-200 min-w-0 flex-1 ml-3">
                    <h1 class="text-base md:text-lg font-bold text-white truncate leading-tight">JZGMSAT</h1>
                    <p class="text-blue-200 text-xs truncate">Student System</p>
                </div>
            </div>
            
            <!-- Navigation -->
            <nav class="mt-3 md:mt-5 flex-1 px-2 space-y-1 md:space-y-2">
                <!-- Dashboard -->
                <?php 
                    $in_subfolder = (strpos($_SERVER['PHP_SELF'], '/advisers/') !== false || 
                                    strpos($_SERVER['PHP_SELF'], '/students/') !== false || 
                                    strpos($_SERVER['PHP_SELF'], '/course_application/') !== false || 
                                    strpos($_SERVER['PHP_SELF'], '/checklist/') !== false);
                    $dashboard_path = $in_subfolder ? '../admin-dashboard.php' : 'admin-dashboard.php';
                ?>
                <a href="<?php echo $dashboard_path; ?>" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'admin-dashboard.php') ? 'bg-blue-800 text-white shadow-lg' : 'text-blue-200 hover:bg-blue-800 hover:text-white'; ?> group flex items-center px-2 md:px-3 py-2.5 md:py-3 text-sm font-medium rounded-lg transition-all duration-200 transform hover:scale-105 hover:shadow-lg">
                    <div class="flex items-center justify-center w-10 h-10 flex-shrink-0">
                        <i class="fas fa-tachometer-alt text-lg"></i>
                    </div>
                    <span class="sidebar-text transition-all duration-200 truncate ml-3">Dashboard</span>
                </a>
                
                <!-- Manage Students -->
                <?php 
                    $is_students_page = (basename($_SERVER['PHP_SELF']) === 'admin-manage-students.php') || (strpos($_SERVER['PHP_SELF'], '/students/') !== false);
                    $students_path = $is_students_page ? 'admin-manage-students.php?page=index' : ($in_subfolder ? '../admin-manage-students.php?page=index' : 'admin-manage-students.php?page=index');
                ?>
                <a href="<?php echo $students_path; ?>" class="<?php echo $is_students_page ? 'bg-blue-800 text-white shadow-lg' : 'text-blue-200 hover:bg-blue-800 hover:text-white'; ?> group flex items-center px-2 md:px-3 py-2.5 md:py-3 text-sm font-medium rounded-lg transition-all duration-200 transform hover:scale-105 hover:shadow-lg">
                    <div class="flex items-center justify-center w-10 h-10 flex-shrink-0">
                        <i class="fas fa-users text-lg"></i>
                    </div>
                    <span class="sidebar-text transition-all duration-200 truncate ml-3">Manage Students</span>
                </a>
                
                <!-- Manage Course Applications -->
                <?php 
                    $is_course_app_page = (basename($_SERVER['PHP_SELF']) === 'admin-course-application.php');
                    $course_app_path = $is_course_app_page ? 'admin-course-application.php?page=index' : ($in_subfolder ? '../admin-course-application.php?page=index' : 'admin-course-application.php?page=index');
                ?>
                <a href="<?php echo $course_app_path; ?>" class="<?php echo $is_course_app_page ? 'bg-blue-800 text-white shadow-lg' : 'text-blue-200 hover:bg-blue-800 hover:text-white'; ?> group flex items-center px-2 md:px-3 py-2.5 md:py-3 text-sm font-medium rounded-lg transition-all duration-200 transform hover:scale-105 hover:shadow-lg">
                    <div class="flex items-center justify-center w-10 h-10 flex-shrink-0">
                        <i class="fas fa-file-alt text-lg"></i>
                    </div>
                    <span class="sidebar-text transition-all duration-200 truncate ml-3">Manage Course Applications</span>
                </a>
                
                <!-- Manage Section (Collapsible) -->
                <div class="relative">
                    <button onclick="toggleManageMenu(event)" class="text-blue-200 hover:bg-blue-800 hover:text-white group flex items-center w-full px-2 md:px-3 py-2.5 md:py-3 text-sm font-medium rounded-lg transition-all duration-200 transform hover:scale-105 hover:shadow-lg">
                        <div class="flex items-center justify-center w-10 h-10 flex-shrink-0">
                            <i class="fas fa-cog text-lg"></i>
                        </div>
                        <span class="sidebar-text transition-all duration-200 truncate ml-3 flex-1 text-left">Manage</span>
                        <i class="fas fa-chevron-down text-xs transition-transform duration-200 sidebar-text flex-shrink-0" id="manage-chevron"></i>
                    </button>
                    
                    <!-- Manage Submenu -->
                    <div id="manage-submenu" class="hidden mt-1 space-y-1 ml-2">
                        <!-- Manage Courses -->
                        <?php 
                            $is_courses_page = (basename($_SERVER['PHP_SELF']) === 'admin-manage-courses.php') || (strpos($_SERVER['PHP_SELF'], '/courses/') !== false);
                            $courses_path = $is_courses_page ? 'admin-manage-courses.php' : ($in_subfolder ? '../admin-manage-courses.php' : 'admin-manage-courses.php');
                        ?>
                        <a href="<?php echo $courses_path; ?>" class="<?php echo $is_courses_page ? 'bg-blue-700 text-white' : 'text-blue-200 hover:bg-blue-700 hover:text-white'; ?> group flex items-center pl-10 md:pl-12 pr-2 md:pr-3 py-2 md:py-2.5 text-sm rounded-lg transition-all duration-200">
                            <i class="fas fa-graduation-cap text-sm mr-3 flex-shrink-0"></i>
                            <span class="sidebar-text transition-all duration-200 truncate">Manage Courses</span>
                        </a>
                        
                        <!-- Manage Checklist -->
                        <?php 
                            $is_checklist_page = (basename($_SERVER['PHP_SELF']) === 'admin-manage-checklist.php') || (strpos($_SERVER['PHP_SELF'], '/checklist/') !== false);
                            $checklist_path = $is_checklist_page ? 'admin-manage-checklist.php?page=index' : ($in_subfolder ? '../admin-manage-checklist.php?page=index' : 'admin-manage-checklist.php?page=index');
                        ?>
                        <a href="<?php echo $checklist_path; ?>" class="<?php echo $is_checklist_page ? 'bg-blue-700 text-white' : 'text-blue-200 hover:bg-blue-700 hover:text-white'; ?> group flex items-center pl-10 md:pl-12 pr-2 md:pr-3 py-2 md:py-2.5 text-sm rounded-lg transition-all duration-200">
                            <i class="fas fa-tasks text-sm mr-3 flex-shrink-0"></i>
                            <span class="sidebar-text transition-all duration-200 truncate">Manage Checklist</span>
                        </a>
                    </div>
                </div>
            </nav>
            
            <!-- User Info & Profile Dropdown -->
            <div class="flex-shrink-0 border-t border-blue-700 p-3 md:p-4">
                <div class="relative">
                    <button onclick="toggleProfileDropdown()" id="profile-button" class="flex items-center w-full text-left hover:bg-blue-800 rounded-lg p-1.5 md:p-2 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 hover:shadow-lg">
                        <div class="bg-blue-600 rounded-full p-1.5 md:p-2 shadow-lg relative flex-shrink-0 profile-icon">
                            <i class="fas fa-user text-white text-xs md:text-sm"></i>
                            <?php if (isset($pending_approvals) && $pending_approvals > 0): ?>
                                <span class="absolute -top-0.5 -right-0.5 md:-top-1 md:-right-1 bg-red-500 text-white text-xs rounded-full h-4 w-4 md:h-5 md:w-5 flex items-center justify-center font-semibold animate-pulse">
                                    <?php echo $pending_approvals; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0 sidebar-text transition-all duration-200 ml-2 md:ml-3">
                            <p class="text-xs md:text-sm font-medium text-white truncate">
                                <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>
                            </p>
                            <p class="text-xs text-blue-300 truncate">
                                Administrator
                                <?php if (isset($pending_approvals) && $pending_approvals > 0): ?>
                                    <span class="ml-1 md:ml-2 text-yellow-300">• <?php echo $pending_approvals; ?> notification<?php echo $pending_approvals > 1 ? 's' : ''; ?></span>
                                <?php endif; ?>
                            </p>
                        </div>
                        <i class="fas fa-chevron-up text-blue-300 text-xs transition-transform duration-200 sidebar-text flex-shrink-0 ml-2" id="profile-chevron"></i>
                    </button>
                    
                    <!-- Profile Dropdown -->
                    <div id="profile-dropdown" class="hidden absolute bottom-full left-0 right-0 mb-2 mx-3 bg-white rounded-lg shadow-xl border border-gray-200 py-2 z-50">
                        <div class="px-4 py-3 border-b border-gray-100">
                            <div class="flex items-center space-x-3">
                                <div class="bg-blue-600 rounded-full p-2">
                                    <i class="fas fa-user text-white text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?></p>
                                    <p class="text-xs text-gray-500"><?php echo htmlspecialchars($_SESSION['admin_email'] ?? $_SESSION['email'] ?? 'admin@admin.com'); ?></p>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800 mt-1">
                                        <i class="fas fa-circle text-green-400 mr-1" style="font-size: 6px;"></i>
                                        Online
                                    </span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Notifications Section -->
                        <?php if (isset($pending_approvals) && $pending_approvals > 0): ?>
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
                                <a href="<?php echo $in_subfolder ? '../admin-pending-approvals.php' : 'admin-pending-approvals.php'; ?>" class="text-xs text-yellow-700 hover:text-yellow-800 font-medium flex items-center">
                                    <i class="fas fa-arrow-right mr-1"></i>
                                    Review pending approvals
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="py-1">
                            <a href="<?php echo $in_subfolder ? '../admin-profile.php' : 'admin-profile.php'; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200">
                                <div class="bg-blue-100 rounded-lg p-2 mr-3">
                                    <i class="fas fa-user-cog text-blue-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium">Account Settings</p>
                                    <p class="text-xs text-gray-500">Manage your profile</p>
                                </div>
                            </a>
                            <a href="<?php echo $in_subfolder ? '../admin-preferences.php' : 'admin-preferences.php'; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200">
                                <div class="bg-purple-100 rounded-lg p-2 mr-3">
                                    <i class="fas fa-cog text-purple-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium">Preferences</p>
                                    <p class="text-xs text-gray-500">System settings</p>
                                </div>
                            </a>
                            <?php 
                                $system_activity_path = $in_subfolder ? '../admin-system-activity.php' : 'admin-system-activity.php';
                            ?>
                            <a href="<?php echo $system_activity_path; ?>" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors duration-200">
                                <div class="bg-purple-100 rounded-lg p-2 mr-3">
                                    <i class="fas fa-history text-purple-600"></i>
                                </div>
                                <div>
                                    <p class="font-medium">System Activity</p>
                                    <p class="text-xs text-gray-500">View system activities</p>
                                </div>
                            </a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <a href="<?php echo $in_subfolder ? '../../auth/logout.php' : '../auth/logout.php'; ?>" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors duration-200">
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
<div id="mobile-sidebar-overlay" class="fixed inset-0 z-50 md:hidden hidden">
    <div class="fixed inset-0 bg-gray-900 bg-opacity-75 backdrop-blur-sm transition-opacity duration-300" onclick="toggleMobileSidebar()"></div>
    <div class="relative flex-1 flex flex-col max-w-xs w-full bg-blue-900 shadow-2xl">
        <!-- Mobile sidebar content -->
        <div class="flex flex-col flex-grow pt-4 pb-4 overflow-y-auto custom-scrollbar">
            <div class="flex items-center justify-between flex-shrink-0 px-4 mb-6">
                <div class="flex items-center min-w-0 flex-1">
                    <div class="flex items-center justify-center w-10 h-10 bg-white bg-opacity-20 rounded-lg flex-shrink-0 backdrop-blur-sm">
                        <img src="/JZGMSAT/assets/images/logo.png" alt="Logo" class="w-6 h-6 object-contain">
                    </div>
                    <div class="min-w-0 flex-1 ml-3">
                        <h1 class="text-base font-bold text-white truncate leading-tight">JZGMSAT</h1>
                        <p class="text-blue-200 text-xs truncate">Student System</p>
                    </div>
                </div>
                <button onclick="toggleMobileSidebar()" class="text-blue-200 hover:text-white p-2 rounded-lg hover:bg-white hover:bg-opacity-10 transition-all duration-200 flex-shrink-0">
                    <i class="fas fa-times text-lg"></i>
                </button>
            </div>
            
            <!-- Mobile Navigation -->
            <nav class="mt-3 flex-1 px-2 space-y-1">
                <!-- Dashboard -->
                <a href="<?php echo $dashboard_path; ?>" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'admin-dashboard.php') ? 'bg-blue-800 text-white shadow-lg' : 'text-blue-200 hover:bg-blue-800 hover:text-white'; ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 hover:shadow-lg">
                    <div class="flex items-center justify-center w-10 h-10 flex-shrink-0">
                        <i class="fas fa-tachometer-alt text-lg"></i>
                    </div>
                    <span class="truncate ml-3">Dashboard</span>
                </a>
                
                <!-- Manage Students -->
                <a href="<?php echo $students_path; ?>" class="<?php echo (strpos($_SERVER['PHP_SELF'], 'students/') !== false) ? 'bg-blue-800 text-white shadow-lg' : 'text-blue-200 hover:bg-blue-800 hover:text-white'; ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 hover:shadow-lg">
                    <div class="flex items-center justify-center w-10 h-10 flex-shrink-0">
                        <i class="fas fa-users text-lg"></i>
                    </div>
                    <span class="truncate ml-3">Manage Students</span>
                </a>
                
                <!-- Manage Courses -->
                <a href="<?php echo $courses_path; ?>" class="<?php echo (strpos($_SERVER['PHP_SELF'], 'courses/') !== false) ? 'bg-blue-800 text-white shadow-lg' : 'text-blue-200 hover:bg-blue-800 hover:text-white'; ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 hover:shadow-lg">
                    <div class="flex items-center justify-center w-10 h-10 flex-shrink-0">
                        <i class="fas fa-graduation-cap text-lg"></i>
                    </div>
                    <span class="truncate ml-3">Manage Courses</span>
                </a>
                
                <!-- Manage Course Applications -->
                <a href="<?php echo $course_app_path; ?>" class="<?php echo (strpos($_SERVER['PHP_SELF'], 'course_application/') !== false) ? 'bg-blue-800 text-white shadow-lg' : 'text-blue-200 hover:bg-blue-800 hover:text-white'; ?> group flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-all duration-200 hover:shadow-lg">
                    <div class="flex items-center justify-center w-10 h-10 flex-shrink-0">
                        <i class="fas fa-file-alt text-lg"></i>
                    </div>
                    <span class="truncate ml-3">Manage Course Applications</span>
                </a>
            </nav>
        </div>
    </div>
</div>
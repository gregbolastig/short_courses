<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';
require_once '../../includes/system_activity_logger.php';

// Require admin authentication
requireAdmin();

// Set page title
$page_title = 'Add Course';

// Set breadcrumb
$breadcrumb_items = [
    ['title' => 'Manage Courses', 'icon' => 'fas fa-graduation-cap', 'url' => 'index.php'],
    ['title' => 'Add Course', 'icon' => 'fas fa-plus']
];

// Initialize system activity logger
$logger = new SystemActivityLogger();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $conn = $database->getConnection();
    
    try {
        $stmt = $conn->prepare("INSERT INTO courses (course_name) VALUES (?)");
        
        $stmt->execute([
            $_POST['course_name']
        ]);
        
        // Get the inserted course ID for logging
        $course_id = $conn->lastInsertId();
        
        // Log course creation
        $logger->log(
            'course_created',
            "Admin created new course '{$_POST['course_name']}'",
            'admin',
            $_SESSION['user_id'],
            'course',
            $course_id
        );
        
        // Redirect immediately to index.php with success parameter
        header("Location: index.php?success=created&course_name=" . urlencode($_POST['course_name']));
        exit;
        
    } catch (PDOException $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Course - Student Registration System</title>
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
                        <div class="mb-8 mt-6">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                                <div>
                                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Add New Course</h1>
                                    <p class="text-lg text-gray-600 mt-2">Create a new course offering for your educational program</p>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <a href="index.php" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Back to Courses
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
                                        <p class="text-xs text-green-600 mt-1">Redirecting to courses list...</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>

                        <!-- Course Form -->
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
                                <form method="POST" action="" class="space-y-8">
                                    <div>
                                        <label for="course_name" class="block text-lg font-semibold text-gray-700 mb-3">
                                            Course Name <span class="text-red-500">*</span>
                                        </label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none">
                                                <i class="fas fa-book text-gray-400"></i>
                                            </div>
                                            <input type="text" name="course_name" id="course_name" required 
                                                   value="<?php echo isset($_POST['course_name']) ? htmlspecialchars($_POST['course_name']) : ''; ?>"
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
                                        
                                        <a href="index.php" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-gray-300 text-lg font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                            <i class="fas fa-times mr-3"></i>
                                            Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include '../components/admin-scripts.php'; ?>
</body>
</html>
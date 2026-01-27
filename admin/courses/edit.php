<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

// Set page title
$page_title = 'Edit Course';

// Get course ID from URL
$course_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$course_id) {
    header('Location: index.php');
    exit;
}

// Get course data
$course = null;
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ?");
    $stmt->execute([$course_id]);
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$course) {
        header('Location: index.php');
        exit;
    }
    
    // Update breadcrumb with course name
    $breadcrumb_items = [
        ['title' => 'Manage Courses', 'icon' => 'fas fa-graduation-cap', 'url' => 'index.php'],
        ['title' => 'Edit: ' . $course['course_name'], 'icon' => 'fas fa-edit']
    ];
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $database = new Database();
    $conn = $database->getConnection();
    
    try {
        $stmt = $conn->prepare("UPDATE courses SET course_name = ? WHERE course_id = ?");
        
        $stmt->execute([
            $_POST['course_name'],
            $course_id
        ]);
        
        $success_message = 'Course updated successfully!';
        
        // Refresh course data
        $stmt = $conn->prepare("SELECT * FROM courses WHERE course_id = ?");
        $stmt->execute([$course_id]);
        $course = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Redirect to courses list after 2 seconds
        header("refresh:2;url=index.php");
        
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
    <title>Edit Course - Student Registration System</title>
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
                                    <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Edit Course</h1>
                                    <p class="text-gray-600 mt-2">
                                        Editing: <span class="font-semibold"><?php echo htmlspecialchars($course['course_name'] ?? ''); ?></span>
                                    </p>
                                </div>
                                <div class="flex items-center space-x-3">
                                    <a href="index.php" class="inline-flex items-center px-4 py-2 border border-gray-300 text-sm font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
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
                        <?php if ($course): ?>
                        <div class="bg-white shadow-lg rounded-xl overflow-hidden border border-gray-100">
                            <div class="px-4 md:px-6 py-4 border-b border-gray-200 bg-gray-50">
                                <h3 class="text-base md:text-lg font-semibold text-gray-900">
                                    <i class="fas fa-edit text-gray-500 mr-2"></i>
                                    Course Information
                                </h3>
                            </div>
                            
                            <div class="p-4 md:p-6">
                                <form method="POST" action="" class="space-y-6">
                                    <div>
                                        <label for="course_name" class="block text-sm font-medium text-gray-700 mb-2">
                                            Course Name <span class="text-red-500">*</span>
                                        </label>
                                        <input type="text" name="course_name" id="course_name" required 
                                               value="<?php echo htmlspecialchars($course['course_name']); ?>"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500"
                                               placeholder="Automotive servicing">
                                    </div>
                                    
                                    <div class="flex flex-col sm:flex-row gap-3 pt-4">
                                        <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                            <i class="fas fa-save mr-2"></i>
                                            Update Course
                                        </button>
                                        
                                        <a href="index.php" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-medium rounded-md shadow-sm text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                                            <i class="fas fa-times mr-2"></i>
                                            Cancel
                                        </a>
                                    </div>
                                </form>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <?php include '../components/admin-scripts.php'; ?>
</body>
</html>
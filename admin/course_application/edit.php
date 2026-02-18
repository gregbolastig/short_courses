<?php
ob_start();
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';
require_once '../../includes/system_activity_logger.php';

requireAdmin();

$page_title = 'Edit Course Application';
$logger = new SystemActivityLogger();

// Get application ID
$application_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$application_id) {
    header('Location: index.php');
    exit;
}

// Get application data
$application = null;
$courses = [];
$advisers = [];

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get application with student info
    $stmt = $conn->prepare("
        SELECT ca.*, s.first_name, s.last_name, s.student_id, c.course_name
        FROM course_applications ca
        JOIN students s ON ca.student_id = s.id
        LEFT JOIN courses c ON ca.course_id = c.course_id
        WHERE ca.application_id = ?
    ");
    $stmt->execute([$application_id]);
    $application = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$application) {
        header('Location: index.php');
        exit;
    }
    
    // Get all courses
    $stmt = $conn->query("SELECT course_id, course_name, nc_levels FROM courses WHERE is_active = 1 ORDER BY course_name");
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all advisers
    $stmt = $conn->query("SELECT adviser_id, adviser_name FROM advisers WHERE is_active = 1 ORDER BY adviser_name");
    $advisers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $course_id = $_POST['course_id'];
        $nc_level = $_POST['nc_level'];
        $training_start = $_POST['training_start'];
        $training_end = $_POST['training_end'];
        $adviser = !empty($_POST['adviser']) ? $_POST['adviser'] : null;
        
        $stmt = $conn->prepare("
            UPDATE course_applications 
            SET course_id = ?, nc_level = ?, training_start = ?, training_end = ?, adviser = ?
            WHERE application_id = ?
        ");
        
        $stmt->execute([
            $course_id,
            $nc_level,
            $training_start,
            $training_end,
            $adviser,
            $application_id
        ]);
        
        // Log the update
        $logger->log(
            'application_updated',
            "Admin updated course application for {$application['first_name']} {$application['last_name']} (ID: {$application_id})",
            'admin',
            $_SESSION['user_id'],
            'course_application',
            $application_id
        );
        
        header("Location: index.php?success=updated");
        exit();
        
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
    <title>Edit Course Application - Student Registration System</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <?php include '../components/admin-styles.php'; ?>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen bg-gray-50">
        <?php include '../components/sidebar.php'; ?>
        
        <div id="main-content" class="min-h-screen transition-all duration-300 ease-in-out ml-0 md:ml-64">
            <?php include '../components/header.php'; ?>
            
            <main class="overflow-y-auto focus:outline-none">
                <div class="py-4 md:py-6">
                    <div class="max-w-7xl mx-auto px-3 sm:px-4 md:px-6 lg:px-8">
                        
                        <!-- Page Header -->
                        <div class="mb-8 mt-6">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
                                <div>
                                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">Edit Course Application</h1>
                                    <p class="text-lg text-gray-600 mt-2">
                                        Student: <span class="font-semibold text-blue-600"><?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></span>
                                    </p>
                                </div>
                                <div class="flex items-center space-x-4">
                                    <a href="index.php" class="inline-flex items-center px-6 py-3 border border-gray-300 text-base font-semibold rounded-lg shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                        <i class="fas fa-arrow-left mr-2"></i>
                                        Back to Applications
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

                        <!-- Edit Form -->
                        <?php if ($application): ?>
                        <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                            <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-blue-50 to-blue-100">
                                <div class="flex items-center space-x-3">
                                    <div class="bg-blue-100 rounded-xl p-2">
                                        <i class="fas fa-edit text-blue-600"></i>
                                    </div>
                                    <h3 class="text-xl font-bold text-gray-900">Application Details</h3>
                                </div>
                            </div>
                            
                            <div class="p-8">
                                <form method="POST" action="" class="space-y-6">
                                    
                                    <!-- Student Info (Read-only) -->
                                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-200">
                                        <h4 class="text-sm font-semibold text-gray-700 mb-2">Student Information</h4>
                                        <p class="text-gray-900"><span class="font-medium">Name:</span> <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></p>
                                        <p class="text-gray-900"><span class="font-medium">Student ID:</span> <?php echo htmlspecialchars($application['student_id']); ?></p>
                                    </div>
                                    
                                    <!-- Course Selection -->
                                    <div>
                                        <label for="course_id" class="block text-lg font-semibold text-gray-700 mb-3">
                                            Course <span class="text-red-500">*</span>
                                        </label>
                                        <select name="course_id" id="course_id" required
                                                class="block w-full px-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200">
                                            <option value="">Select Course</option>
                                            <?php foreach ($courses as $course): ?>
                                                <option value="<?php echo $course['course_id']; ?>" 
                                                        <?php echo ($application['course_id'] == $course['course_id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($course['course_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- NC Level -->
                                    <div>
                                        <label for="nc_level" class="block text-lg font-semibold text-gray-700 mb-3">
                                            NC Level <span class="text-red-500">*</span>
                                        </label>
                                        <select name="nc_level" id="nc_level" required
                                                class="block w-full px-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200">
                                            <option value="">Select NC Level</option>
                                            <option value="NC I" <?php echo ($application['nc_level'] == 'NC I') ? 'selected' : ''; ?>>NC I</option>
                                            <option value="NC II" <?php echo ($application['nc_level'] == 'NC II') ? 'selected' : ''; ?>>NC II</option>
                                            <option value="NC III" <?php echo ($application['nc_level'] == 'NC III') ? 'selected' : ''; ?>>NC III</option>
                                            <option value="NC IV" <?php echo ($application['nc_level'] == 'NC IV') ? 'selected' : ''; ?>>NC IV</option>
                                            <option value="NC V" <?php echo ($application['nc_level'] == 'NC V') ? 'selected' : ''; ?>>NC V</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Training Dates -->
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                        <div>
                                            <label for="training_start" class="block text-lg font-semibold text-gray-700 mb-3">
                                                Training Start Date <span class="text-red-500">*</span>
                                            </label>
                                            <input type="date" name="training_start" id="training_start" required
                                                   value="<?php echo htmlspecialchars($application['training_start'] ?? ''); ?>"
                                                   class="block w-full px-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200">
                                        </div>
                                        
                                        <div>
                                            <label for="training_end" class="block text-lg font-semibold text-gray-700 mb-3">
                                                Training End Date <span class="text-red-500">*</span>
                                            </label>
                                            <input type="date" name="training_end" id="training_end" required
                                                   value="<?php echo htmlspecialchars($application['training_end'] ?? ''); ?>"
                                                   class="block w-full px-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200">
                                        </div>
                                    </div>
                                    
                                    <!-- Assigned Adviser -->
                                    <div>
                                        <label for="adviser" class="block text-lg font-semibold text-gray-700 mb-3">
                                            Assigned Adviser (Optional)
                                        </label>
                                        <select name="adviser" id="adviser"
                                                class="block w-full px-4 py-4 border border-gray-300 rounded-xl shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-lg transition-all duration-200">
                                            <option value="">Select Adviser (Optional)</option>
                                            <?php foreach ($advisers as $adv): ?>
                                                <option value="<?php echo htmlspecialchars($adv['adviser_name']); ?>"
                                                        <?php echo ($application['adviser'] == $adv['adviser_name']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($adv['adviser_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <!-- Action Buttons -->
                                    <div class="flex flex-col sm:flex-row gap-4 pt-6">
                                        <button type="submit" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-transparent text-lg font-bold rounded-xl shadow-lg text-white bg-gradient-to-r from-blue-600 to-blue-700 hover:from-blue-700 hover:to-blue-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transform transition-all duration-200 hover:scale-105">
                                            <i class="fas fa-save mr-3"></i>
                                            Update Application
                                        </button>
                                        
                                        <a href="index.php" class="flex-1 inline-flex items-center justify-center px-8 py-4 border border-gray-300 text-lg font-semibold rounded-xl shadow-sm text-gray-700 bg-white hover:bg-gray-50 hover:border-gray-400 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200">
                                            <i class="fas fa-times mr-3"></i>
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

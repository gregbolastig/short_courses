<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/system_activity_logger.php';

$errors = [];
$success_message = '';
$student_profile = null;
$available_courses = [];

// Initialize system activity logger
$logger = new SystemActivityLogger();

// Handle course registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_course') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Get student info
        $stmt = $conn->prepare("SELECT * FROM shortcourse_students WHERE uli = :uli");
        $stmt->bindParam(':uli', $_POST['uli']);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
            // Check if student has any pending course applications
            // Note: Students CAN reapply for rejected courses - only pending applications block new applications
            $stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM shortcourse_course_applications WHERE student_id = :student_id AND status = 'pending'");
            $stmt->bindParam(':student_id', $student['id']);
            $stmt->execute();
            $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];
            
            // Check if student has any approved course applications (not yet completed)
            // Only block if student status is 'approved' (not 'completed')
            $approved_count = 0;
            if ($student['status'] === 'approved') {
                $stmt = $conn->prepare("SELECT COUNT(*) as approved_count FROM shortcourse_course_applications WHERE student_id = :student_id AND status = 'approved'");
                $stmt->bindParam(':student_id', $student['id']);
                $stmt->execute();
                $approved_count = $stmt->fetch(PDO::FETCH_ASSOC)['approved_count'];
            }
            
            // Check if student has an active course that is not completed
            // Students can apply when their status is 'completed' OR 'rejected'
            // Rejected students should be able to apply for new courses
            $has_active_course = false;
            if (!empty($student['course']) && 
                $student['status'] !== 'completed' && 
                $student['status'] !== 'rejected') {
                $has_active_course = true;
            }
            
            // Check if trying to apply for the same course that's already in students table
            // Only block if the course is not completed AND not rejected
            // Rejected students can apply for any course, including the one they were rejected from
            $duplicate_course = false;
            if (!empty($student['course']) && !empty($_POST['course']) && 
                strtolower(trim($student['course'])) === strtolower(trim($_POST['course'])) && 
                $student['status'] !== 'completed' && 
                $student['status'] !== 'rejected') {
                $duplicate_course = true;
            }
            
            if ($pending_count > 0) {
                $errors[] = 'You already have a pending course application. Please wait for admin review before applying for another course.';
            } elseif ($approved_count > 0 && $student['status'] === 'approved') {
                $errors[] = 'You have an approved course application. Please wait for course completion approval before applying for another course.';
            } elseif ($has_active_course) {
                $errors[] = 'You have an active course enrollment (status: ' . ucfirst($student['status']) . '). You can only apply for a new course after your current course is completed.';
            } elseif ($duplicate_course) {
                $errors[] = 'You are already enrolled in this course. You can only apply for a new course after completing your current course.';
            } else {
                // Insert new course application (duplicates allowed)
                $stmt = $conn->prepare("INSERT INTO shortcourse_course_applications 
                    (student_id, course_id, nc_level, status, applied_at) 
                    VALUES (:student_id, :course_id, :nc_level, 'pending', NOW())");
                
                // Store values in variables for bindParam
                $student_id = $student['id'];
                $course_id = $_POST['course'];
                $nc_level = $_POST['nc_level'] ?? null;
                
                $stmt->bindParam(':student_id', $student_id);
                $stmt->bindParam(':course_id', $course_id);
                $stmt->bindParam(':nc_level', $nc_level);
                
                if ($stmt->execute()) {
                    // Get the inserted application ID for logging
                    $application_id = $conn->lastInsertId();
                    
                    // Get the course name from the courses table
                    $stmt = $conn->prepare("SELECT course_name FROM shortcourse_courses WHERE course_id = :course_id");
                    $stmt->bindParam(':course_id', $course_id);
                    $stmt->execute();
                    $course_data = $stmt->fetch(PDO::FETCH_ASSOC);
                    $course_name = $course_data['course_name'] ?? $_POST['course'];
                    
                    // Log course application
                    $logger->log(
                        'course_application',
                        "Student '{$student['first_name']} {$student['last_name']}' applied for course '{$course_name}'",
                        'student',
                        null,
                        'course_application',
                        $application_id
                    );
                    
                    // Set flag to show success modal instead of alert message
                    $show_success_modal = true;
                    $applied_course_name = $course_name;
                    
                    // Clear form data after successful submission
                    $_POST = [];
                } else {
                    $errors[] = 'Failed to submit course application. Please try again.';
                }
            }
        } else {
            $errors[] = 'Student record not found.';
        }
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Get student profile from URL parameter
if (isset($_GET['uli']) && !empty($_GET['uli'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM shortcourse_students WHERE uli = :uli");
        $stmt->bindParam(':uli', $_GET['uli']);
        $stmt->execute();
        $student_profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student_profile) {
            $errors[] = 'Student record not found';
        }
        
        // Get available courses from database
        $stmt = $conn->query("SELECT * FROM shortcourse_courses WHERE is_active = TRUE ORDER BY course_name");
        $available_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Check if student can apply for new courses
        $can_apply = true;
        $restriction_message = '';
        
        // Students can apply if their status is 'completed' or if they have no active course
        // Check for pending course applications
        // Note: Students CAN reapply after rejection - only pending applications block new applications
        $stmt = $conn->prepare("SELECT COUNT(*) as pending_count FROM shortcourse_course_applications WHERE student_id = :student_id AND status = 'pending'");
        $stmt->bindParam(':student_id', $student_profile['id']);
        $stmt->execute();
        $pending_count = $stmt->fetch(PDO::FETCH_ASSOC)['pending_count'];
        
        // Check for approved course applications that are NOT yet completed
        // Only block if student status is 'approved' (not 'completed')
        $approved_count = 0;
        if ($student_profile['status'] === 'approved') {
            $stmt = $conn->prepare("SELECT COUNT(*) as approved_count FROM shortcourse_course_applications WHERE student_id = :student_id AND status = 'approved'");
            $stmt->bindParam(':student_id', $student_profile['id']);
            $stmt->execute();
            $approved_count = $stmt->fetch(PDO::FETCH_ASSOC)['approved_count'];
        }
        
        // Check for active courses - students can apply when status is 'completed' OR 'rejected'
        // Rejected students should be able to apply for new courses
        $has_active_course = false;
        if (!empty($student_profile['course']) && 
            $student_profile['status'] !== 'completed' && 
            $student_profile['status'] !== 'rejected') {
            $has_active_course = true;
        }
        
        if ($pending_count > 0) {
            $can_apply = false;
            $restriction_message = 'You have a pending course application waiting for admin review. Please wait for the review to complete before applying for another course.';
        } elseif ($approved_count > 0 && $student_profile['status'] === 'approved') {
            $can_apply = false;
            $restriction_message = 'You have an approved course application. Please wait for course completion approval before applying for another course.';
        } elseif ($has_active_course) {
            $can_apply = false;
            $restriction_message = 'You have an active course enrollment (status: ' . ucfirst($student_profile['status']) . '). You can only apply for a new course after your current course is completed.';
        }
        
        // Get pending applications for display (latest to oldest)
        $stmt = $conn->prepare("SELECT ca.*, c.course_name 
                                FROM shortcourse_course_applications ca 
                                LEFT JOIN shortcourse_courses c ON ca.course_id = c.course_id 
                                WHERE ca.student_id = :student_id AND ca.status = 'pending' 
                                ORDER BY ca.applied_at DESC");
        $stmt->bindParam(':student_id', $student_profile['id']);
        $stmt->execute();
        $pending_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get rejected applications for display (latest to oldest)
        $stmt = $conn->prepare("SELECT ca.*, c.course_name 
                                FROM shortcourse_course_applications ca 
                                LEFT JOIN shortcourse_courses c ON ca.course_id = c.course_id 
                                WHERE ca.student_id = :student_id AND ca.status = 'rejected' 
                                ORDER BY ca.applied_at DESC");
        $stmt->bindParam(':student_id', $student_profile['id']);
        $stmt->execute();
        $rejected_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get approved/completed applications for display
        // Show all applications that are approved or completed
        // Sort by reviewed_at (completion date) DESC, then applied_at DESC for latest to oldest
        $stmt = $conn->prepare("SELECT ca.*, c.course_name
                               FROM shortcourse_course_applications ca
                               LEFT JOIN shortcourse_courses c ON ca.course_id = c.course_id
                               WHERE ca.student_id = :student_id 
                               AND (ca.status = 'approved' OR ca.status = 'completed')
                               ORDER BY 
                                   CASE WHEN ca.reviewed_at IS NOT NULL THEN ca.reviewed_at ELSE ca.applied_at END DESC,
                                   ca.applied_at DESC");
        $stmt->bindParam(':student_id', $student_profile['id']);
        $stmt->execute();
        $approved_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
} else {
    $errors[] = 'Please provide student ULI';
}

// Set page variables for header component
$show_logo = true;

// Include header component
include '../components/header.php';
?>

    <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php 
        // Set navigation links
        $nav_links = [
            ['url' => 'profile.php?uli=' . urlencode($_GET['uli'] ?? ''), 'text' => 'Back to Profile', 'icon' => 'fas fa-arrow-left']
        ];
        include '../components/navigation.php'; 
        ?>
        
        <?php include '../components/alerts.php'; ?>
        
        <?php if ($student_profile): ?>
            <!-- New Course Registration Form -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
                <div class="bg-gradient-to-r from-red-800 to-red-900 px-6 py-8">
                    <div class="text-center text-white">
                        <h1 class="text-3xl font-bold mb-2">Course Application</h1>
                        <p class="text-red-50">Apply for a new course</p>
                    </div>
                </div>
                
                <div class="px-6 py-8">
                    <!-- Student Info Display -->
                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <h3 class="text-lg font-semibold text-gray-900 mb-3">
                            <i class="fas fa-user text-red-800 mr-2"></i>Student Information
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Full Name</label>
                                <p class="text-sm font-semibold text-gray-900">
                                    <?php echo htmlspecialchars(trim($student_profile['first_name'] . ' ' . ($student_profile['middle_name'] ? $student_profile['middle_name'] . ' ' : '') . $student_profile['last_name'])); ?>
                                    <?php if ($student_profile['extension_name']): ?>
                                        <?php echo htmlspecialchars($student_profile['extension_name']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">ULI</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['uli']); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Email</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['email']); ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-500">Contact Number</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['contact_number']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    
                    <!-- Pending Applications Display -->
                    <?php if (!empty($pending_applications)): ?>
                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-4 mb-6">
                            <h4 class="text-sm font-semibold text-orange-800 mb-2">
                                <i class="fas fa-hourglass-half mr-2"></i>Pending Course Applications
                            </h4>
                            <?php foreach ($pending_applications as $app): ?>
                                <div class="bg-white border border-orange-200 rounded-lg p-3 mb-2">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($app['course_name']); ?></p>
                                            <p class="text-sm text-gray-600">Applied: <?php echo date('M j, Y', strtotime($app['applied_at'])); ?></p>
                                        </div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <i class="fas fa-clock mr-1"></i>Pending Review
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Rejected Applications Display -->
                    <?php if (!empty($rejected_applications)): ?>
                        <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-6">
                            <h4 class="text-sm font-semibold text-red-800 mb-2">
                                <i class="fas fa-times-circle mr-2"></i>Rejected Course Applications
                            </h4>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-3">
                                <p class="text-xs text-blue-700 flex items-center">
                                    <i class="fas fa-info-circle mr-2"></i>
                                    <strong>Good news!</strong> You can reapply for these courses or apply for different ones. Click "Reapply" to quickly select a course below.
                                </p>
                            </div>
                            <?php foreach ($rejected_applications as $app): ?>
                                <div class="bg-white border border-red-200 rounded-lg p-3 mb-2">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="font-medium text-gray-900"><?php echo htmlspecialchars($app['course_name']); ?></p>
                                            <p class="text-sm text-gray-600">Applied: <?php echo date('M j, Y', strtotime($app['applied_at'])); ?></p>
                                            <?php if ($app['reviewed_at']): ?>
                                                <p class="text-sm text-gray-600">Rejected: <?php echo date('M j, Y', strtotime($app['reviewed_at'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                            <i class="fas fa-times-circle mr-1"></i>Rejected
                                        </span>
                                    </div>
                                    <div class="mt-2 pt-2 border-t border-red-100">
                                        <div class="flex items-center justify-between">
                                            <p class="text-xs text-red-700 bg-red-50 rounded p-2 flex-1 mr-3">
                                                <i class="fas fa-info-circle mr-1"></i>
                                                Your application was not approved. You can apply for other courses or reapply for this course.
                                            </p>
                                            <button onclick="reapplyForCourse('<?php echo htmlspecialchars($app['course_name']); ?>')" 
                                                    class="inline-flex items-center px-3 py-1.5 bg-blue-600 text-white text-xs font-medium rounded-lg hover:bg-blue-700 transition-colors duration-200">
                                                <i class="fas fa-redo mr-1"></i>Reapply
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Approved Applications Display -->
                    <?php if (!empty($approved_applications)): ?>
                        <?php 
                        // Separate completed and approved applications
                        // If student status is 'completed', all approved applications are considered completed
                        $completed_apps = [];
                        $awaiting_completion_apps = [];
                        
                        foreach ($approved_applications as $app) {
                            // If student status is completed, show all approved applications as completed
                            if ($student_profile['status'] === 'completed' || $app['status'] === 'completed') {
                                $completed_apps[] = $app;
                            } else {
                                $awaiting_completion_apps[] = $app;
                            }
                        }
                        ?>
                        
                        <!-- Completed Applications -->
                        <?php if (!empty($completed_apps)): ?>
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                                <h4 class="text-sm font-semibold text-green-800 mb-2">
                                    <i class="fas fa-check-circle mr-2"></i>Completed Course Applications
                                </h4>
                                <?php foreach ($completed_apps as $app): ?>
                                    <div class="bg-white border border-green-200 rounded-lg p-3 mb-2">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($app['course_name']); ?></p>
                                                <?php if (!empty($app['nc_level'])): ?>
                                                    <p class="text-sm text-gray-600">NC Level: <?php echo htmlspecialchars($app['nc_level']); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($app['adviser'])): ?>
                                                    <p class="text-sm text-gray-600">Adviser: <?php echo htmlspecialchars($app['adviser']); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($app['training_start']) && !empty($app['training_end'])): ?>
                                                    <p class="text-sm text-gray-600">
                                                        Training Period: <?php echo date('M j, Y', strtotime($app['training_start'])); ?> - 
                                                        <?php echo date('M j, Y', strtotime($app['training_end'])); ?>
                                                    </p>
                                                <?php endif; ?>
                                                <p class="text-sm text-gray-600">
                                                    Completed: <?php echo ($app['reviewed_at'] ?? $app['applied_at']) ? date('M j, Y', strtotime($app['reviewed_at'] ?? $app['applied_at'])) : 'N/A'; ?>
                                                </p>
                                            </div>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check-circle mr-1"></i>Completed
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Approved - Awaiting Completion -->
                        <?php if (!empty($awaiting_completion_apps)): ?>
                            <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                                <h4 class="text-sm font-semibold text-green-800 mb-2">
                                    <i class="fas fa-check-circle mr-2"></i>Approved Course Applications
                                </h4>
                                <?php foreach ($awaiting_completion_apps as $app): ?>
                                    <div class="bg-white border border-green-200 rounded-lg p-3 mb-2">
                                        <div class="flex items-center justify-between">
                                            <div>
                                                <p class="font-medium text-gray-900"><?php echo htmlspecialchars($app['course_name']); ?></p>
                                                <?php if (!empty($app['nc_level'])): ?>
                                                    <p class="text-sm text-gray-600">NC Level: <?php echo htmlspecialchars($app['nc_level']); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($app['adviser'])): ?>
                                                    <p class="text-sm text-gray-600">Adviser: <?php echo htmlspecialchars($app['adviser']); ?></p>
                                                <?php endif; ?>
                                                <p class="text-sm text-gray-600">Approved: <?php echo $app['reviewed_at'] ? date('M j, Y', strtotime($app['reviewed_at'])) : date('M j, Y', strtotime($app['applied_at'])); ?></p>
                                            </div>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                                <i class="fas fa-check mr-1"></i>Approved - Awaiting Completion
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <!-- Current Course Enrollment (only for approved, not completed) -->
                    <?php if (!empty($student_profile['course']) && $student_profile['status'] === 'approved'): ?>
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                            <h4 class="text-sm font-semibold text-blue-800 mb-2">
                                <i class="fas fa-graduation-cap mr-2"></i>Current Course Enrollment
                            </h4>
                            <div class="bg-white border border-blue-200 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-gray-900"><?php echo htmlspecialchars($student_profile['course']); ?></p>
                                        <?php if ($student_profile['nc_level']): ?>
                                            <p class="text-sm text-gray-600">NC Level: <?php echo htmlspecialchars($student_profile['nc_level']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($student_profile['adviser']): ?>
                                            <p class="text-sm text-gray-600">Adviser: <?php echo htmlspecialchars($student_profile['adviser']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($student_profile['training_start'] && $student_profile['training_end']): ?>
                                            <p class="text-sm text-gray-600">
                                                Training Period: <?php echo date('M j, Y', strtotime($student_profile['training_start'])); ?> - 
                                                <?php echo date('M j, Y', strtotime($student_profile['training_end'])); ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <i class="fas fa-clock mr-1"></i>Approved
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Course Registration Form -->
                    <?php if ($can_apply): ?>
                        <form id="courseApplicationForm" method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="register_course">
                            <input type="hidden" name="uli" value="<?php echo htmlspecialchars($student_profile['uli']); ?>">
                            
                            <div>
                                <label for="course" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-graduation-cap text-red-800 mr-2"></i>Select Course
                                </label>
                                <select name="course" id="course" required 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors duration-200">
                                    <option value="">Choose a course...</option>
                                    <?php foreach ($available_courses as $course): ?>
                                        <option value="<?php echo htmlspecialchars($course['course_id']); ?>">
                                            <?php echo htmlspecialchars($course['course_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <h4 class="text-sm font-semibold text-blue-800 mb-2">
                                    <i class="fas fa-info-circle mr-2"></i>Course Application Process
                                </h4>
                                <ul class="text-sm text-blue-700 space-y-1">
                                    <li>• Your application will be reviewed by admin</li>
                                    <li>• Admin will assign NC Level and training schedule</li>
                                    <li>• You can only have one active course at a time</li>
                                    <li>• If rejected, you can reapply for the same or different courses</li>
                                </ul>
                            </div>
                            
                            <div class="flex items-center justify-end pt-4">
                                <button type="button" onclick="showApplicationModal()" 
                                        class="inline-flex items-center px-6 py-3 bg-red-800 text-white font-semibold rounded-lg hover:bg-red-900 transition-colors duration-200">
                                    <i class="fas fa-paper-plane mr-2"></i>Submit Application
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <!-- Restriction Message -->
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-exclamation-triangle text-yellow-600 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Course Application Not Available</h3>
                            <p class="text-gray-600 mb-4 max-w-md mx-auto"><?php echo $restriction_message; ?></p>
                            <a href="profile.php?uli=<?php echo urlencode($student_profile['uli']); ?>" 
                               class="inline-flex items-center px-4 py-2 bg-red-800 text-white font-semibold rounded-lg hover:bg-red-900 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Profile
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Confirmation Modal -->
    <div id="applicationModal" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm overflow-y-auto h-full w-full z-50 hidden flex items-center justify-center p-4">
        <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md mx-auto transform transition-all duration-300 ease-out">
            <!-- Header Section -->
            <div class="bg-gradient-to-r from-red-800 to-red-900 rounded-t-2xl px-6 py-6 text-white relative overflow-hidden">
                <div class="absolute inset-0 bg-black opacity-10"></div>
                <div class="relative flex items-center justify-center">
                    <div class="flex-shrink-0 w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center mr-4">
                        <i class="fas fa-paper-plane text-white text-xl"></i>
                    </div>
                    <div class="text-center">
                        <h3 class="text-xl font-bold mb-1">Confirm Application</h3>
                        <p class="text-red-100 text-sm opacity-90">Review your course application</p>
                    </div>
                </div>
            </div>
            
            <!-- Content Section -->
            <div class="px-6 py-6">
                <div class="text-center mb-6">
                    <p class="text-gray-700 text-sm leading-relaxed mb-4">
                        You are about to submit an application for the following course:
                    </p>
                    
                    <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                        <div class="flex items-center justify-center mb-2">
                            <div class="w-8 h-8 bg-red-100 rounded-full flex items-center justify-center mr-3">
                                <i class="fas fa-graduation-cap text-red-600 text-sm"></i>
                            </div>
                            <div>
                                <p class="font-semibold text-red-800" id="selectedCourseName">Course Name</p>
                                <p class="text-xs text-red-600">Course Application</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-4">
                        <p class="text-xs text-blue-700 flex items-center justify-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            Your application will be reviewed by admin.
                        </p>
                    </div>
                </div>
                
                <!-- Confirmation Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="button" onclick="hideApplicationModal()" 
                            class="flex-1 inline-flex items-center justify-center px-4 py-3 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 transition-colors duration-200">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="button" id="confirmButton" onclick="submitApplication()" 
                            class="flex-1 inline-flex items-center justify-center px-4 py-3 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-colors duration-200">
                        <i class="fas fa-check mr-2"></i>Confirm & Submit
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        <?php if (isset($show_success_modal) && $show_success_modal): ?>
        // Auto-show success toast on page load
        document.addEventListener('DOMContentLoaded', function() {
            showToast('Your application for <?php echo htmlspecialchars($applied_course_name ?? 'the course'); ?> has been submitted successfully and is now pending admin review.', 'success');
            // Redirect to profile after toast shows
            setTimeout(function() {
                window.location.href = 'profile.php?uli=<?php echo urlencode($_GET['uli'] ?? ''); ?>';
            }, 3500);
        });
        <?php endif; ?>
        
        function showErrorToast(message) {
            showToast(message, 'error');
        }
        
        function showApplicationModal() {
            const courseSelect = document.getElementById('course');
            const selectedCourse = courseSelect.value;
            const selectedCourseName = courseSelect.options[courseSelect.selectedIndex].text;
            
            // Validate that a course is selected
            if (!selectedCourse) {
                showErrorToast('Please select a course before submitting your application.');
                courseSelect.focus();
                return;
            }
            
            // Update modal with selected course
            document.getElementById('selectedCourseName').textContent = selectedCourseName;
            
            // Show modal in confirmation state
            document.getElementById('applicationModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        
        function hideApplicationModal() {
            document.getElementById('applicationModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }
        
        function submitApplication() {
            // Show loading state
            const confirmBtn = document.getElementById('confirmButton');
            confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
            confirmBtn.disabled = true;
            
            // Hide the modal before submitting to prevent double modal appearance
            document.getElementById('applicationModal').classList.add('hidden');
            
            // Submit the form
            document.getElementById('courseApplicationForm').submit();
        }
        
        // Close modal when clicking outside
        document.getElementById('applicationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideApplicationModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideApplicationModal();
            }
        });
        
        // Reapply for course function
        function reapplyForCourse(courseName) {
            // Set the course in the dropdown
            const courseSelect = document.getElementById('course');
            if (courseSelect) {
                // Find the option with the matching course name
                for (let i = 0; i < courseSelect.options.length; i++) {
                    if (courseSelect.options[i].text === courseName) {
                        courseSelect.selectedIndex = i;
                        break;
                    }
                }
                
                // Scroll to the form
                courseSelect.scrollIntoView({ behavior: 'smooth', block: 'center' });
                
                // Highlight the form briefly
                const form = document.getElementById('courseApplicationForm');
                if (form) {
                    form.style.border = '2px solid #3b82f6';
                    form.style.borderRadius = '8px';
                    setTimeout(() => {
                        form.style.border = '';
                        form.style.borderRadius = '';
                    }, 3000);
                }
                
                // Focus on the course select
                setTimeout(() => {
                    courseSelect.focus();
                }, 500);
            }
        }
    </script>
    
    <?php include '../components/footer.php'; ?>
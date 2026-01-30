<?php
session_start();
require_once '../../config/database.php';

$errors = [];
$success_message = '';
$student_profile = null;
$available_courses = [];

// Handle course registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_course') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Get student info
        $stmt = $conn->prepare("SELECT * FROM students WHERE uli = :uli");
        $stmt->bindParam(':uli', $_POST['uli']);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($student) {
<<<<<<< HEAD
            // Insert new course application into course_applications table
            $stmt = $conn->prepare("INSERT INTO course_applications 
                (student_id, student_uli, course_name, status, applied_at) 
                VALUES (:student_id, :student_uli, :course_name, 'pending', NOW())");
            
            $stmt->bindParam(':student_id', $student['id']);
            $stmt->bindParam(':student_uli', $student['uli']);
            $stmt->bindParam(':course_name', $_POST['course']);
            
            if ($stmt->execute()) {
                $success_message = 'Course application submitted successfully! Your application is now pending admin review.';
            } else {
                $errors[] = 'Failed to submit course application. Please try again.';
=======
            // Check if student already has a pending or active course
            if (!empty($student['course']) && $student['status'] !== 'completed') {
                $errors[] = 'You already have an active course registration. Please complete your current course before registering for a new one.';
            } else {
                // Register for new course
                $stmt = $conn->prepare("UPDATE students SET 
                    course = :course, 
                    nc_level = :nc_level,
                    status = 'pending',
                    training_start = NULL,
                    training_end = NULL,
                    adviser = NULL,
                    approved_at = NULL
                    WHERE uli = :uli");
                
                $stmt->bindParam(':course', $_POST['course']);
                $stmt->bindParam(':nc_level', $_POST['nc_level']);
                $stmt->bindParam(':uli', $_POST['uli']);
                
                if ($stmt->execute()) {
                    $success_message = 'Course registration submitted successfully! Your registration is now pending admin approval.';
                } else {
                    $errors[] = 'Failed to register for course. Please try again.';
                }
>>>>>>> 719ba2fe487140cf7e0419847c8b1e1d20a1f9f9
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
        
        $stmt = $conn->prepare("SELECT * FROM students WHERE uli = :uli");
        $stmt->bindParam(':uli', $_GET['uli']);
        $stmt->execute();
        $student_profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student_profile) {
            $errors[] = 'Student record not found';
        }
        
<<<<<<< HEAD
        // Get available courses from database
        $stmt = $conn->query("SELECT * FROM courses WHERE is_active = TRUE ORDER BY course_name");
        $available_courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
=======
        // Get available courses (you can modify this based on your courses table structure)
        $available_courses = [
            ['name' => 'Computer Systems Servicing', 'nc_level' => 'NC II'],
            ['name' => 'Electrical Installation and Maintenance', 'nc_level' => 'NC II'],
            ['name' => 'Automotive Servicing', 'nc_level' => 'NC I'],
            ['name' => 'Automotive Servicing', 'nc_level' => 'NC II'],
            ['name' => 'Welding', 'nc_level' => 'NC I'],
            ['name' => 'Welding', 'nc_level' => 'NC II'],
            ['name' => 'Carpentry', 'nc_level' => 'NC II'],
            ['name' => 'Masonry', 'nc_level' => 'NC I'],
            ['name' => 'Plumbing', 'nc_level' => 'NC I'],
            ['name' => 'Electronics Products Assembly and Servicing', 'nc_level' => 'NC II'],
        ];
>>>>>>> 719ba2fe487140cf7e0419847c8b1e1d20a1f9f9
        
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
<<<<<<< HEAD
                        <h1 class="text-3xl font-bold mb-2">Course Application</h1>
                        <p class="text-red-50">Apply for a new course</p>
=======
                        <h1 class="text-3xl font-bold mb-2">New Course Registration</h1>
                        <p class="text-red-50">Register for a new course</p>
>>>>>>> 719ba2fe487140cf7e0419847c8b1e1d20a1f9f9
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
                    
<<<<<<< HEAD
                    <!-- Course Registration Form -->
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
                                    <option value="<?php echo htmlspecialchars($course['course_name']); ?>">
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
                                <li>• You will be notified once your application is approved</li>
                                <li>• You can apply for multiple courses</li>
                            </ul>
                        </div>
                        
                        <div class="flex items-center justify-between pt-4">
                            <a href="profile.php?uli=<?php echo urlencode($student_profile['uli']); ?>" 
                               class="inline-flex items-center px-6 py-3 bg-gray-500 text-white font-semibold rounded-lg hover:bg-gray-600 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>Cancel
                            </a>
                            <button type="button" onclick="showConfirmationModal()" 
                                    class="inline-flex items-center px-6 py-3 bg-red-800 text-white font-semibold rounded-lg hover:bg-red-900 transition-colors duration-200">
                                <i class="fas fa-paper-plane mr-2"></i>Submit Application
                            </button>
                        </div>
                    </form>
=======
                    <!-- Current Course Status -->
                    <?php if (!empty($student_profile['course'])): ?>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                            <h3 class="text-lg font-semibold text-yellow-800 mb-2">
                                <i class="fas fa-exclamation-triangle mr-2"></i>Current Course Status
                            </h3>
                            <p class="text-yellow-700 mb-2">
                                You are currently registered for: <strong><?php echo htmlspecialchars($student_profile['course']); ?></strong>
                                <?php if ($student_profile['nc_level']): ?>
                                    (<?php echo htmlspecialchars($student_profile['nc_level']); ?>)
                                <?php endif; ?>
                            </p>
                            <p class="text-yellow-700 text-sm">
                                Status: <span class="font-semibold"><?php echo ucfirst($student_profile['status']); ?></span>
                            </p>
                            <?php if ($student_profile['status'] !== 'completed'): ?>
                                <p class="text-yellow-700 text-sm mt-2">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    You must complete your current course before registering for a new one.
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Course Registration Form -->
                    <?php if (empty($student_profile['course']) || $student_profile['status'] === 'completed'): ?>
                        <form method="POST" class="space-y-6">
                            <input type="hidden" name="action" value="register_course">
                            <input type="hidden" name="uli" value="<?php echo htmlspecialchars($student_profile['uli']); ?>">
                            
                            <div>
                                <label for="course" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-graduation-cap text-red-800 mr-2"></i>Select Course
                                </label>
                                <select name="course" id="course" required 
                                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500 transition-colors duration-200"
                                        onchange="updateNCLevel()">
                                    <option value="">Choose a course...</option>
                                    <?php foreach ($available_courses as $course): ?>
                                        <option value="<?php echo htmlspecialchars($course['name']); ?>" 
                                                data-nc-level="<?php echo htmlspecialchars($course['nc_level']); ?>">
                                            <?php echo htmlspecialchars($course['name']); ?> (<?php echo htmlspecialchars($course['nc_level']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div>
                                <label for="nc_level" class="block text-sm font-medium text-gray-700 mb-2">
                                    <i class="fas fa-certificate text-red-800 mr-2"></i>NC Level
                                </label>
                                <input type="text" name="nc_level" id="nc_level" readonly
                                       class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-600"
                                       placeholder="NC Level will be auto-filled based on course selection">
                            </div>
                            
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <h4 class="text-sm font-semibold text-blue-800 mb-2">
                                    <i class="fas fa-info-circle mr-2"></i>Registration Process
                                </h4>
                                <ul class="text-sm text-blue-700 space-y-1">
                                    <li>• Your registration will be submitted for admin review</li>
                                    <li>• Admin will assign training dates and adviser</li>
                                    <li>• You will be notified once your registration is approved</li>
                                    <li>• Training schedule will be available in your profile</li>
                                </ul>
                            </div>
                            
                            <div class="flex items-center justify-between pt-4">
                                <a href="profile.php?uli=<?php echo urlencode($student_profile['uli']); ?>" 
                                   class="inline-flex items-center px-6 py-3 bg-gray-500 text-white font-semibold rounded-lg hover:bg-gray-600 transition-colors duration-200">
                                    <i class="fas fa-arrow-left mr-2"></i>Cancel
                                </a>
                                <button type="submit" 
                                        class="inline-flex items-center px-6 py-3 bg-red-800 text-white font-semibold rounded-lg hover:bg-red-900 transition-colors duration-200">
                                    <i class="fas fa-paper-plane mr-2"></i>Submit Registration
                                </button>
                            </div>
                        </form>
                    <?php else: ?>
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Course Registration Not Available</h3>
                            <p class="text-gray-600 mb-4">You must complete your current course before registering for a new one.</p>
                            <a href="profile.php?uli=<?php echo urlencode($student_profile['uli']); ?>" 
                               class="inline-flex items-center px-4 py-2 bg-red-800 text-white font-semibold rounded-lg hover:bg-red-900 transition-colors duration-200">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Profile
                            </a>
                        </div>
                    <?php endif; ?>
>>>>>>> 719ba2fe487140cf7e0419847c8b1e1d20a1f9f9
                </div>
            </div>
        <?php endif; ?>
    </main>
    
<<<<<<< HEAD
    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="fixed inset-0 bg-black bg-opacity-60 backdrop-blur-sm overflow-y-auto h-full w-full z-50 hidden flex items-center justify-center p-4">
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
                            Your application will be reviewed by admin and you will be notified of the decision.
                        </p>
                    </div>
                </div>
                
                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-3">
                    <button type="button" onclick="hideConfirmationModal()" 
                            class="flex-1 inline-flex items-center justify-center px-4 py-3 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 transition-colors duration-200">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button type="button" onclick="submitApplication()" 
                            class="flex-1 inline-flex items-center justify-center px-4 py-3 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-colors duration-200">
                        <i class="fas fa-check mr-2"></i>Confirm & Submit
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function showConfirmationModal() {
            const courseSelect = document.getElementById('course');
            const selectedCourse = courseSelect.value;
            const selectedCourseName = courseSelect.options[courseSelect.selectedIndex].text;
            
            // Validate that a course is selected
            if (!selectedCourse) {
                alert('Please select a course before submitting your application.');
                courseSelect.focus();
                return;
            }
            
            // Update modal with selected course
            document.getElementById('selectedCourseName').textContent = selectedCourseName;
            
            // Show modal
            document.getElementById('confirmationModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
        
        function hideConfirmationModal() {
            document.getElementById('confirmationModal').classList.add('hidden');
            document.body.style.overflow = 'auto'; // Restore scrolling
        }
        
        function submitApplication() {
            // Hide modal
            hideConfirmationModal();
            
            // Show loading state
            const submitBtn = document.querySelector('button[onclick="submitApplication()"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
            submitBtn.disabled = true;
            
            // Submit the form
            document.getElementById('courseApplicationForm').submit();
        }
        
        // Close modal when clicking outside
        document.getElementById('confirmationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideConfirmationModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideConfirmationModal();
            }
        });
=======
    <script>
        function updateNCLevel() {
            const courseSelect = document.getElementById('course');
            const ncLevelInput = document.getElementById('nc_level');
            const selectedOption = courseSelect.options[courseSelect.selectedIndex];
            
            if (selectedOption.value) {
                ncLevelInput.value = selectedOption.getAttribute('data-nc-level');
            } else {
                ncLevelInput.value = '';
            }
        }
>>>>>>> 719ba2fe487140cf7e0419847c8b1e1d20a1f9f9
    </script>
    
    <?php include '../components/footer.php'; ?>
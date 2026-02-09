<?php
session_start();
require_once '../../config/database.php';

$errors = [];
$success_message = '';
$student_profile = null;

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Handle contact number - combine country code and phone number
        $contact_number = '';
        if (!empty($_POST['country_code']) && !empty($_POST['phone_number'])) {
            $country_code = $_POST['country_code'];
            $phone_number = $_POST['phone_number'];
            
            // Validate country code format
            if (!preg_match('/^\+\d{1,4}$/', $country_code)) {
                $errors[] = 'Invalid country code format';
            }
            
            // Validate phone number (should be exactly 10 digits)
            if (!preg_match('/^\d{10}$/', $phone_number)) {
                $errors[] = 'Phone number must be exactly 10 digits';
            }
            
            if (empty($errors)) {
                $contact_number = $country_code . $phone_number;
            }
        } else {
            $errors[] = 'Both country code and phone number are required';
        }
        
        if (empty($errors)) {
            $stmt = $conn->prepare("UPDATE students SET 
                civil_status = :civil_status, 
                contact_number = :contact_number, 
                email = :email,
                province = :province,
                city = :city,
                barangay = :barangay
                WHERE id = :id");
            
            $stmt->bindParam(':civil_status', $_POST['civil_status']);
            $stmt->bindParam(':contact_number', $contact_number);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':province', $_POST['province']);
            $stmt->bindParam(':city', $_POST['city']);
            $stmt->bindParam(':barangay', $_POST['barangay']);
            $stmt->bindParam(':id', $_POST['student_id']);
            
            if ($stmt->execute()) {
                $success_message = 'Profile updated successfully!';
                // Refresh the student profile data
                $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
                $stmt->bindParam(':id', $_POST['student_id']);
                $stmt->execute();
                $student_profile = $stmt->fetch(PDO::FETCH_ASSOC);
            } else {
                $errors[] = 'Failed to update profile. Please try again.';
            }
        }
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
}

// Handle student lookup from URL parameter (supports both ID and ULI)
if ((isset($_GET['student_id']) && is_numeric($_GET['student_id'])) || (isset($_GET['uli']) && !empty($_GET['uli']))) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Determine lookup method - join with courses to get course name
        if (isset($_GET['student_id']) && is_numeric($_GET['student_id'])) {
            $stmt = $conn->prepare("SELECT s.*, c.course_name as course_display_name 
                                   FROM students s 
                                   LEFT JOIN courses c ON s.course = c.course_id 
                                   WHERE s.id = :id");
            $stmt->bindParam(':id', $_GET['student_id']);
        } else {
            $stmt = $conn->prepare("SELECT s.*, c.course_name as course_display_name 
                                   FROM students s 
                                   LEFT JOIN courses c ON s.course = c.course_id 
                                   WHERE s.uli = :uli");
            $stmt->bindParam(':uli', $_GET['uli']);
        }
        
        $stmt->execute();
        $student_profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student_profile) {
            $errors[] = 'Student record not found';
        } else {
            // Create course record from student's assigned course (if any)
            $student_courses = [];
            
            // Get all course applications with their current status
            $stmt = $conn->prepare("
                SELECT ca.*, COALESCE(c.course_name, ca.course_id) as course_name
                FROM course_applications ca
                LEFT JOIN courses c ON ca.course_id = c.course_id
                WHERE ca.student_id = :student_id 
                ORDER BY ca.applied_at DESC
            ");
            $stmt->bindParam(':student_id', $student_profile['id']);
            $stmt->execute();
            $all_applications = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Check if student_enrollments table exists (for two-stage system)
            $stmt = $conn->query("SHOW TABLES LIKE 'student_enrollments'");
            $enrollments_table_exists = $stmt->rowCount() > 0;
            
            $all_enrollments = [];
            if ($enrollments_table_exists) {
                // Get all student enrollments (approved applications that became enrollments)
                $stmt = $conn->prepare("
                    SELECT se.*, COALESCE(c.course_name, se.course_id) as course_name, a.adviser_name, ca.applied_at
                    FROM student_enrollments se
                    LEFT JOIN courses c ON se.course_id = c.course_id
                    LEFT JOIN advisers a ON se.adviser_id = a.adviser_id
                    LEFT JOIN course_applications ca ON se.application_id = ca.application_id
                    WHERE se.student_id = :student_id 
                    ORDER BY se.enrolled_at DESC
                ");
                $stmt->bindParam(':student_id', $student_profile['id']);
                $stmt->execute();
                $all_enrollments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
            
            // Process course applications
            foreach ($all_applications as $app) {
                // Skip if this application has been converted to an enrollment (two-stage system)
                if ($enrollments_table_exists && isset($app['enrollment_created']) && $app['enrollment_created']) {
                    continue;
                }
                
                $app_status = 'pending';
                $completion_date = null;
                $certificate_number = null;
                // Get training data from course_applications table (each course has its own data)
                $training_start = $app['training_start'] ?? null;
                $training_end = $app['training_end'] ?? null;
                $adviser = $app['adviser'] ?? 'Not Assigned';
                
                if ($app['status'] === 'rejected') {
                    $app_status = 'rejected';
                } elseif ($app['status'] === 'completed') {
                    // Admin has approved the course completion
                    $app_status = 'completed';
                    $completion_date = $app['reviewed_at'];
                    $certificate_number = 'CERT-' . date('Y', strtotime($app['reviewed_at'])) . '-' . str_pad($student_profile['id'], 6, '0', STR_PAD_LEFT);
                    // Training data is already in $app from course_applications table
                } elseif ($app['status'] === 'approved') {
                    // In single-stage system, approved means enrolled
                    if (!$enrollments_table_exists) {
                        // Training data is in course_applications, not students table
                        $app_status = 'enrolled';
                    } else {
                        $app_status = 'approved';
                        // For two-stage system, data might be in enrollments (handled separately)
                    }
                } elseif ($app['status'] === 'pending') {
                    $app_status = 'pending';
                }
                
                $student_courses[] = [
                    'id' => 'app_' . $app['application_id'],
                    'course_id' => $app['course_id'],
                    'course_name' => $app['course_name'] ?: ('Course ID: ' . $app['course_id']),
                    'nc_level' => $app['nc_level'] ?: 'Pending Assignment',
                    'training_start' => $training_start,
                    'training_end' => $training_end,
                    'adviser' => $adviser,
                    'status' => $app_status,
                    'completion_date' => $completion_date,
                    'certificate_number' => $certificate_number,
                    'approved_at' => $app['reviewed_at'],
                    'applied_at' => $app['applied_at']
                ];
            }
            
            // Process enrollments (if two-stage system exists)
            if ($enrollments_table_exists) {
                foreach ($all_enrollments as $enrollment) {
                    // Determine enrollment status
                    $course_status = 'enrolled';
                    $completion_date = null;
                    $certificate_number = null;
                    
                    if ($enrollment['completion_status'] === 'approved') {
                        // Course completion has been approved - show as completed
                        $course_status = 'completed';
                        $completion_date = $enrollment['completion_approved_at'];
                        $certificate_number = $enrollment['certificate_number'];
                    } elseif ($enrollment['enrollment_status'] === 'completed' && $enrollment['completion_status'] === 'pending') {
                        // Course is completed but waiting for admin approval
                        $course_status = 'awaiting_completion_approval';
                    } elseif ($enrollment['enrollment_status'] === 'enrolled') {
                        // Check training dates to determine if in progress or pending
                        $today = date('Y-m-d');
                        if (!empty($enrollment['training_start']) && !empty($enrollment['training_end'])) {
                            if ($today < $enrollment['training_start']) {
                                $course_status = 'pending_start'; // Training hasn't started yet
                            } elseif ($today >= $enrollment['training_start'] && $today <= $enrollment['training_end']) {
                                $course_status = 'in_progress'; // Currently in training
                            } else {
                                $course_status = 'training_ended'; // Training period ended, awaiting completion
                            }
                        } else {
                            $course_status = 'enrolled'; // Enrolled but no training dates set
                        }
                    } elseif ($enrollment['enrollment_status'] === 'dropped') {
                        $course_status = 'dropped';
                    }
                    
                    $student_courses[] = [
                        'id' => 'enroll_' . $enrollment['enrollment_id'],
                        'course_id' => $enrollment['course_id'],
                        'course_name' => $enrollment['course_name'] ?: ('Course ID: ' . $enrollment['course_id']),
                        'nc_level' => $enrollment['nc_level'] ?: 'Not specified',
                        'training_start' => $enrollment['training_start'],
                        'training_end' => $enrollment['training_end'],
                        'adviser' => $enrollment['adviser_name'] ?: 'Not Assigned',
                        'status' => $course_status,
                        'completion_date' => $completion_date,
                        'certificate_number' => $certificate_number,
                        'approved_at' => $enrollment['enrolled_at'],
                        'applied_at' => $enrollment['applied_at']
                    ];
                }
            }
            
            // Legacy support: Show course if student has been approved or completed (has course assigned in students table)
            if (($student_profile['status'] === 'approved' || $student_profile['status'] === 'completed') && !empty($student_profile['course'])) {
                // Check if this course is already shown from applications/enrollments
                $course_already_shown = false;
                $legacy_course_id = $student_profile['course'];
                foreach ($student_courses as $existing_course) {
                    // Compare by course_id if available, otherwise by course_name
                    if (isset($existing_course['course_id']) && $existing_course['course_id'] == $legacy_course_id) {
                        $course_already_shown = true;
                        break;
                    } elseif (!isset($existing_course['course_id']) && isset($existing_course['course_name']) && $existing_course['course_name'] === $student_profile['course_display_name']) {
                        $course_already_shown = true;
                        break;
                    }
                }
                
                if (!$course_already_shown) {
                    // Determine course status based on student status and training dates
                    $course_status = 'pending';
                    $completion_date = null;
                    $certificate_number = null;
                    
                    if ($student_profile['status'] === 'completed') {
                        // Admin has approved the course completion - show as completed
                        $course_status = 'completed';
                        $completion_date = $student_profile['approved_at'];
                        $certificate_number = 'CERT-' . date('Y', strtotime($student_profile['approved_at'])) . '-' . str_pad($student_profile['id'], 6, '0', STR_PAD_LEFT);
                    } elseif ($student_profile['status'] === 'approved') {
                        // Only for approved students, check training dates
                        $today = date('Y-m-d');
                        if (!empty($student_profile['training_start']) && !empty($student_profile['training_end'])) {
                            if ($today < $student_profile['training_start']) {
                                $course_status = 'pending_start'; // Training hasn't started yet
                            } elseif ($today >= $student_profile['training_start'] && $today <= $student_profile['training_end']) {
                                $course_status = 'in_progress'; // Currently in training
                            } else {
                                $course_status = 'training_ended'; // Training period ended
                            }
                        } else {
                            $course_status = 'enrolled'; // Approved but no training dates set
                        }
                    }
                    
                    // Get course name - use course_display_name if available, otherwise fallback to course_id
                    $legacy_course_name = !empty($student_profile['course_display_name']) 
                        ? $student_profile['course_display_name'] 
                        : (!empty($student_profile['course']) 
                            ? 'Course ID: ' . $student_profile['course'] 
                            : 'Unknown Course');
                    
                    $student_courses[] = [
                        'id' => 'legacy_1',
                        'course_id' => $student_profile['course'],
                        'course_name' => $legacy_course_name,
                        'nc_level' => $student_profile['nc_level'] ?: 'Not specified',
                        'training_start' => $student_profile['training_start'],
                        'training_end' => $student_profile['training_end'],
                        'adviser' => $student_profile['adviser'],
                        'status' => $course_status,
                        'completion_date' => $completion_date,
                        'certificate_number' => $certificate_number,
                        'approved_at' => $student_profile['approved_at']
                    ];
                }
            }
        }
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
} else {
    $errors[] = 'Please provide either student ID or ULI';
}

// Set page variables for header component
// Using consistent title across all pages
$show_logo = true;

// Include header component
include '../components/header.php';
?>

    <main class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?php 
        // Set navigation links
        $nav_links = [
            ['url' => '../../index.php', 'text' => 'Back to Search', 'icon' => 'fas fa-arrow-left']
        ];
        include '../components/navigation.php'; 
        ?>
        
        <?php include '../components/alerts.php'; ?>
        
        <?php if ($student_profile): ?>
            <!-- Student Profile Display -->
            <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden mb-8">
                <div class="bg-gradient-to-r from-red-800 to-red-900 px-6 py-8">
                    <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                        <div class="flex-shrink-0">
                            <?php 
                            // Handle profile picture path resolution
                            $profile_picture_url = '';
                            $file_exists = false;
                            
                            if (!empty($student_profile['profile_picture'])) {
                                $stored_path = $student_profile['profile_picture'];
                                
                                // Handle both old format (../uploads/profiles/file.jpg) and new format (uploads/profiles/file.jpg)
                                if (strpos($stored_path, '../') === 0) {
                                    // Old format: remove ../ and add ../../
                                    $clean_path = str_replace('../', '', $stored_path);
                                    $profile_picture_url = '../../' . $clean_path;
                                } else {
                                    // New format: just add ../../
                                    $profile_picture_url = '../../' . $stored_path;
                                }
                                
                                $file_exists = file_exists($profile_picture_url);
                            }
                            ?>
                            
                            <?php if (!empty($student_profile['profile_picture']) && $file_exists): ?>
                                <div class="relative group">
                                    <img src="<?php echo htmlspecialchars($profile_picture_url); ?>" 
                                         alt="Profile Picture" 
                                         class="w-32 h-32 md:w-40 md:h-40 rounded-2xl object-cover border-4 border-white shadow-2xl ring-4 ring-white ring-opacity-50"
                                         onerror="this.parentElement.style.display='none'; this.parentElement.nextElementSibling.style.display='block';">
                                    <!-- Professional badge -->
                                    <div class="absolute -bottom-2 -right-2 bg-green-500 text-white p-2 rounded-full shadow-lg">
                                        <i class="fas fa-check text-sm"></i>
                                    </div>
                                </div>
                                <!-- Fallback for broken images -->
                                <div class="relative group" style="display: none;">
                                    <div class="w-32 h-32 md:w-40 md:h-40 rounded-2xl bg-gradient-to-br from-white to-gray-100 border-4 border-white shadow-2xl ring-4 ring-white ring-opacity-50 flex items-center justify-center">
                                        <div class="text-center">
                                            <div class="w-16 h-16 md:w-20 md:h-20 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-2">
                                                <span class="text-xl md:text-2xl font-bold text-red-800">
                                                    <?php echo strtoupper(substr($student_profile['first_name'], 0, 1) . substr($student_profile['last_name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-500 font-medium">Photo Error</p>
                                        </div>
                                    </div>
                                    <!-- Error indicator -->
                                    <div class="absolute -bottom-2 -right-2 bg-red-800 text-white p-2 rounded-full shadow-lg">
                                        <i class="fas fa-exclamation-triangle text-sm"></i>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="relative group">
                                    <div class="w-32 h-32 md:w-40 md:h-40 rounded-2xl bg-gradient-to-br from-white to-gray-100 border-4 border-white shadow-2xl ring-4 ring-white ring-opacity-50 flex items-center justify-center">
                                        <div class="text-center">
                                            <div class="w-16 h-16 md:w-20 md:h-20 rounded-full bg-red-50 flex items-center justify-center mx-auto mb-2">
                                                <span class="text-xl md:text-2xl font-bold text-red-800">
                                                    <?php echo strtoupper(substr($student_profile['first_name'], 0, 1) . substr($student_profile['last_name'], 0, 1)); ?>
                                                </span>
                                            </div>
                                            <p class="text-xs text-gray-500 font-medium">No Photo</p>
                                        </div>
                                    </div>
                                    <!-- Missing photo indicator -->
                                    <div class="absolute -bottom-2 -right-2 bg-gray-400 text-white p-2 rounded-full shadow-lg">
                                        <i class="fas fa-camera text-sm"></i>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                                <!-- Debug information (only shown when ?debug=1 is in URL) -->
                                <div class="mt-2 text-xs text-white bg-black bg-opacity-50 p-2 rounded">
                                    <strong>Debug Info:</strong><br>
                                    Stored Path: <?php echo htmlspecialchars($student_profile['profile_picture'] ?? 'NULL'); ?><br>
                                    Resolved URL: <?php echo htmlspecialchars($profile_picture_url ?? 'NULL'); ?><br>
                                    File Exists: <?php echo $file_exists ? 'YES' : 'NO'; ?><br>
                                    Full Path: <?php echo htmlspecialchars(realpath($profile_picture_url ?? '') ?: 'Not found'); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="text-center md:text-left text-white flex-1">
                            <h2 class="text-2xl md:text-3xl font-bold mb-2">
                                <?php echo htmlspecialchars(trim($student_profile['first_name'] . ' ' . ($student_profile['middle_name'] ? $student_profile['middle_name'] . ' ' : '') . $student_profile['last_name'])); ?>
                                <?php if ($student_profile['extension_name']): ?>
                                    <?php echo htmlspecialchars($student_profile['extension_name']); ?>
                                <?php endif; ?>
                            </h2>
                            

                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-red-50 mb-4">
                                <div class="flex items-center justify-center md:justify-start">
                                    <i class="fas fa-id-card mr-2"></i>
                                    <span>ULI: <?php echo htmlspecialchars($student_profile['uli']); ?></span>
                                </div>
                                <div class="flex items-center justify-center md:justify-start">
                                    <i class="fas fa-envelope mr-2"></i>
                                    <span><?php echo htmlspecialchars($student_profile['email']); ?></span>
                                </div>
                                <div class="flex items-center justify-center md:justify-start">
                                    <i class="fas fa-phone mr-2"></i>
                                    <span><?php echo htmlspecialchars($student_profile['contact_number']); ?></span>
                                </div>
                                <div class="flex items-center justify-center md:justify-start">
                                    <i class="fas fa-calendar mr-2"></i>
                                    <span>Registered: <?php echo date('M j, Y', strtotime($student_profile['created_at'])); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Courses Table -->
                <?php if (!empty($student_courses)): ?>
                    <div class="px-6 py-6 bg-gray-50 border-t border-gray-200">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-lg font-semibold text-gray-900">
                                <i class="fas fa-graduation-cap text-red-800 mr-2"></i>Course History
                            </h3>
                            <div class="flex items-center space-x-4">
                                <span class="text-sm text-gray-600">
                                    Total Courses: <span class="font-semibold text-red-800"><?php echo count($student_courses); ?></span>
                                </span>
                                <a href="new_course.php?uli=<?php echo urlencode($student_profile['uli']); ?>" 
                                   class="inline-flex items-center px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-colors duration-200">
                                    <i class="fas fa-plus mr-2"></i>New Course
                                </a>
                            </div>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border border-gray-200 rounded-lg overflow-hidden">
                                <thead class="bg-red-800 text-white">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Course Name</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">NC Level</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Training Period</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Adviser</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider">Certificate</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($student_courses as $index => $course): ?>
                                        <tr class="<?php echo $index % 2 === 0 ? 'bg-white' : 'bg-gray-50'; ?> hover:bg-red-50 transition-colors duration-200">
                                            <td class="px-4 py-4">
                                                <div class="flex items-center">
                                                    <div class="w-8 h-8 bg-red-50 rounded-full flex items-center justify-center mr-3">
                                                        <i class="fas fa-book text-red-800 text-sm"></i>
                                                    </div>
                                                    <div>
                                                        <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($course['course_name']); ?></p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                                    <?php echo htmlspecialchars($course['nc_level']); ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="text-sm text-gray-900">
                                                    <?php if ($course['training_start'] && $course['training_end']): ?>
                                                        <p class="font-medium"><?php echo date('M j, Y', strtotime($course['training_start'])); ?></p>
                                                        <p class="text-gray-500">to <?php echo date('M j, Y', strtotime($course['training_end'])); ?></p>
                                                    <?php else: ?>
                                                        <p class="text-gray-500">Not scheduled</p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <div class="text-sm text-gray-900">
                                                    <?php if ($course['adviser']): ?>
                                                        <p class="font-medium"><?php echo htmlspecialchars($course['adviser']); ?></p>
                                                    <?php else: ?>
                                                        <p class="text-gray-500">Not assigned</p>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="px-4 py-4">
                                                <?php
                                                $status_class = '';
                                                $status_icon = '';
                                                $status_text = '';
                                                switch ($course['status']) {
                                                    case 'completed':
                                                        $status_class = 'bg-green-100 text-green-800';
                                                        $status_icon = 'fas fa-check-circle';
                                                        $status_text = 'Completed';
                                                        break;
                                                    case 'in_progress':
                                                        $status_class = 'bg-blue-100 text-blue-800';
                                                        $status_icon = 'fas fa-play-circle';
                                                        $status_text = 'In Progress';
                                                        break;
                                                    case 'enrolled':
                                                        $status_class = 'bg-purple-100 text-purple-800';
                                                        $status_icon = 'fas fa-user-graduate';
                                                        $status_text = 'Enrolled';
                                                        break;
                                                    case 'pending_start':
                                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                                        $status_icon = 'fas fa-clock';
                                                        $status_text = 'Pending Start';
                                                        break;
                                                    case 'training_ended':
                                                        $status_class = 'bg-indigo-100 text-indigo-800';
                                                        $status_icon = 'fas fa-flag-checkered';
                                                        $status_text = 'Training Completed';
                                                        break;
                                                    case 'awaiting_completion_approval':
                                                        $status_class = 'bg-amber-100 text-amber-800';
                                                        $status_icon = 'fas fa-hourglass-half';
                                                        $status_text = 'Awaiting Completion Approval';
                                                        break;
                                                    case 'pending':
                                                        $status_class = 'bg-orange-100 text-orange-800';
                                                        $status_icon = 'fas fa-hourglass-half';
                                                        $status_text = 'Application Pending';
                                                        break;
                                                    case 'approved':
                                                        $status_class = 'bg-green-100 text-green-800';
                                                        $status_icon = 'fas fa-check';
                                                        $status_text = 'Approved';
                                                        break;
                                                    case 'rejected':
                                                        $status_class = 'bg-red-100 text-red-800';
                                                        $status_icon = 'fas fa-times-circle';
                                                        $status_text = 'Application Rejected';
                                                        break;
                                                    case 'dropped':
                                                        $status_class = 'bg-gray-100 text-gray-800';
                                                        $status_icon = 'fas fa-user-times';
                                                        $status_text = 'Dropped';
                                                        break;
                                                    default:
                                                        $status_class = 'bg-gray-100 text-gray-800';
                                                        $status_icon = 'fas fa-question-circle';
                                                        $status_text = 'Unknown';
                                                }
                                                ?>
                                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?php echo $status_class; ?>">
                                                    <i class="<?php echo $status_icon; ?> mr-1"></i>
                                                    <?php echo $status_text; ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-4">
                                                <?php if ($course['status'] === 'completed' && $course['certificate_number']): ?>
                                                    <div class="text-sm">
                                                        <p class="font-medium text-green-600"><?php echo htmlspecialchars($course['certificate_number']); ?></p>
                                                        <p class="text-gray-500">Issued: <?php echo date('M j, Y', strtotime($course['completion_date'])); ?></p>
                                                    </div>
                                                <?php elseif (in_array($course['status'], ['enrolled', 'in_progress', 'training_ended', 'awaiting_completion_approval'])): ?>
                                                    <span class="text-xs text-gray-500">In progress</span>
                                                <?php elseif ($course['status'] === 'pending'): ?>
                                                    <span class="text-xs text-gray-500">Application pending</span>
                                                <?php elseif ($course['status'] === 'rejected'): ?>
                                                    <span class="text-xs text-red-500">Not available</span>
                                                <?php else: ?>
                                                    <span class="text-xs text-gray-400">Not available</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Course Statistics -->
                        <div class="mt-6 grid grid-cols-1 md:grid-cols-4 gap-4">
                            <?php
                            $completed_courses = array_filter($student_courses, function($course) { return $course['status'] === 'completed'; });
                            $in_progress_courses = array_filter($student_courses, function($course) { return in_array($course['status'], ['enrolled', 'in_progress', 'training_ended', 'awaiting_completion_approval']); });
                            $pending_courses = array_filter($student_courses, function($course) { return in_array($course['status'], ['pending', 'pending_start']); });
                            ?>
                            <div class="bg-white p-4 rounded-lg border border-gray-200 text-center">
                                <div class="text-2xl font-bold text-green-600"><?php echo count($completed_courses); ?></div>
                                <div class="text-sm text-gray-600">Completed</div>
                            </div>
                            <div class="bg-white p-4 rounded-lg border border-gray-200 text-center">
                                <div class="text-2xl font-bold text-blue-600"><?php echo count($in_progress_courses); ?></div>
                                <div class="text-sm text-gray-600">In Progress</div>
                            </div>
                            <div class="bg-white p-4 rounded-lg border border-gray-200 text-center">
                                <div class="text-2xl font-bold text-yellow-600"><?php echo count($pending_courses); ?></div>
                                <div class="text-sm text-gray-600">Pending</div>
                            </div>
                            <div class="bg-white p-4 rounded-lg border border-gray-200 text-center">
                                <div class="text-2xl font-bold text-red-800"><?php echo count($student_courses); ?></div>
                                <div class="text-sm text-gray-600">Total Courses</div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="px-6 py-6 bg-gray-50 border-t border-gray-200">
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-gray-200 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-graduation-cap text-gray-400 text-2xl"></i>
                            </div>
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No Courses Yet</h3>
                            <?php if ($student_profile['status'] === 'completed'): ?>
                                <p class="text-gray-600 mb-4">Your course information is displayed above.</p>
                                <div class="inline-flex items-center px-4 py-2 bg-green-100 text-green-800 text-sm font-medium rounded-lg">
                                    <i class="fas fa-graduation-cap mr-2"></i>Course Completed
                                </div>
                            <?php elseif ($student_profile['status'] === 'approved'): ?>
                                <!-- No status message for approved students -->
                            <?php elseif ($student_profile['status'] === 'pending'): ?>
                                <p class="text-gray-600 mb-4">Course assignment will be available once admin reviews your registration.</p>
                                <div class="inline-flex items-center px-4 py-2 bg-yellow-100 text-yellow-800 text-sm font-medium rounded-lg">
                                    Waiting for Course Assignment
                                </div>
                            <?php else: ?>
                                <p class="text-gray-600 mb-4">Course enrollment is not available.</p>
                                <div class="inline-flex items-center px-4 py-2 bg-red-50 text-red-800 text-sm font-medium rounded-lg">
                                    <i class="fas fa-times mr-2"></i>Registration Rejected
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Additional Student Information -->
                <div class="px-6 py-6 bg-white border-t border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <i class="fas fa-info-circle text-red-800 mr-2"></i>Personal Information
                        </h3>
                        <div class="flex items-center space-x-2">
                            <button onclick="toggleEditMode()" id="editBtn" class="inline-flex items-center px-4 py-2 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-colors duration-200">
                                <i class="fas fa-edit mr-2"></i>Edit Profile
                            </button>
                            <div id="editControls" class="hidden space-x-2">
                                <button onclick="saveChanges()" class="inline-flex items-center px-4 py-2 bg-red-800 text-white text-xs font-medium rounded-lg hover:bg-red-900 transition-colors duration-200">
                                    <i class="fas fa-save mr-1"></i>Save
                                </button>
                                <button onclick="cancelEdit()" class="inline-flex items-center px-4 py-2 bg-gray-500 text-white text-xs font-medium rounded-lg hover:bg-gray-600 transition-colors duration-200">
                                    <i class="fas fa-times mr-1"></i>Cancel
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <form id="profileForm" method="POST">
                        <input type="hidden" name="student_id" value="<?php echo $student_profile['id']; ?>">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            <!-- Non-editable fields -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Date of Birth</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo date('M j, Y', strtotime($student_profile['birthday'])); ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Age</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo $student_profile['age']; ?> years old</p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Place of Birth</label>
                                <p class="text-sm font-semibold text-gray-900">
                                    <?php 
                                    $birth_location = '';
                                    if (!empty($student_profile['birth_city']) && !empty($student_profile['birth_province'])) {
                                        $birth_location = htmlspecialchars($student_profile['birth_city'] . ', ' . $student_profile['birth_province']);
                                    } elseif (!empty($student_profile['place_of_birth'])) {
                                        $birth_location = htmlspecialchars($student_profile['place_of_birth']);
                                    } else {
                                        $birth_location = 'Not specified';
                                    }
                                    echo $birth_location;
                                    ?>
                                </p>
                            </div>
                            
                            <!-- Non-editable fields (continued) -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Sex</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['sex']); ?></p>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Civil Status</label>
                                <div class="view-mode">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['civil_status']); ?></p>
                                </div>
                                <div class="edit-mode hidden">
                                    <select name="civil_status" class="w-full text-sm border border-gray-300 rounded px-2 py-1">
                                        <option value="Single" <?php echo $student_profile['civil_status'] === 'Single' ? 'selected' : ''; ?>>Single</option>
                                        <option value="Married" <?php echo $student_profile['civil_status'] === 'Married' ? 'selected' : ''; ?>>Married</option>
                                        <option value="Divorced" <?php echo $student_profile['civil_status'] === 'Divorced' ? 'selected' : ''; ?>>Divorced</option>
                                        <option value="Widowed" <?php echo $student_profile['civil_status'] === 'Widowed' ? 'selected' : ''; ?>>Widowed</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Contact Number</label>
                                <div class="view-mode">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['contact_number']); ?></p>
                                </div>
                                <div class="edit-mode hidden">
                                    <?php
                                    // Parse the stored contact number to separate country code and number
                                    $stored_contact = $student_profile['contact_number'];
                                    $country_code = '';
                                    $phone_number = '';
                                    
                                    // Check if it starts with a country code (+ followed by digits)
                                    if (preg_match('/^(\+\d{1,4})(\d+)$/', $stored_contact, $matches)) {
                                        $country_code = $matches[1];
                                        $phone_number = $matches[2];
                                    } else {
                                        // If no country code found, assume it's just the number
                                        $phone_number = $stored_contact;
                                        $country_code = '+63'; // Default to Philippines
                                    }
                                    ?>
                                    <div class="flex space-x-2">
                                        <select name="country_code" id="edit_country_code" class="w-20 text-sm border border-gray-300 rounded px-2 py-1">
                                            <option value="+63" <?php echo $country_code === '+63' ? 'selected' : ''; ?>>🇵🇭 +63</option>
                                            <option value="+1" <?php echo $country_code === '+1' ? 'selected' : ''; ?>>🇺🇸 +1</option>
                                            <option value="+44" <?php echo $country_code === '+44' ? 'selected' : ''; ?>>🇬🇧 +44</option>
                                            <option value="+86" <?php echo $country_code === '+86' ? 'selected' : ''; ?>>🇨🇳 +86</option>
                                            <option value="+81" <?php echo $country_code === '+81' ? 'selected' : ''; ?>>🇯🇵 +81</option>
                                            <option value="+82" <?php echo $country_code === '+82' ? 'selected' : ''; ?>>🇰🇷 +82</option>
                                            <option value="+65" <?php echo $country_code === '+65' ? 'selected' : ''; ?>>🇸🇬 +65</option>
                                        </select>
                                        <input type="text" name="phone_number" value="<?php echo htmlspecialchars($phone_number); ?>" 
                                               placeholder="10-digit number" maxlength="10" pattern="\d{10}"
                                               class="flex-1 text-sm border border-gray-300 rounded px-2 py-1">
                                    </div>
                                    <p class="text-xs text-gray-500 mt-1">Enter 10-digit number without country code</p>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Email</label>
                                <div class="view-mode">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['email']); ?></p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="email" name="email" value="<?php echo htmlspecialchars($student_profile['email']); ?>" 
                                           class="w-full text-sm border border-gray-300 rounded px-2 py-1">
                                </div>
                            </div>
                            
                            <!-- Address fields with API integration -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Province</label>
                                <div class="view-mode">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['province']); ?></p>
                                </div>
                                <div class="edit-mode hidden">
                                    <select name="province" id="edit_province" class="w-full text-sm border border-gray-300 rounded px-2 py-1">
                                        <option value="">Loading provinces...</option>
                                    </select>
                                    <div id="edit_province-loading" class="hidden text-xs text-gray-500 mt-1">
                                        <i class="fas fa-spinner fa-spin mr-1"></i>Loading provinces...
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">City</label>
                                <div class="view-mode">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['city']); ?></p>
                                </div>
                                <div class="edit-mode hidden">
                                    <select name="city" id="edit_city" class="w-full text-sm border border-gray-300 rounded px-2 py-1">
                                        <option value="">Select province first</option>
                                    </select>
                                    <div id="edit_city-loading" class="hidden text-xs text-gray-500 mt-1">
                                        <i class="fas fa-spinner fa-spin mr-1"></i>Loading cities...
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Barangay</label>
                                <div class="view-mode">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['barangay']); ?></p>
                                </div>
                                <div class="edit-mode hidden">
                                    <select name="barangay" id="edit_barangay" class="w-full text-sm border border-gray-300 rounded px-2 py-1">
                                        <option value="">Select city first</option>
                                    </select>
                                    <div id="edit_barangay-loading" class="hidden text-xs text-gray-500 mt-1">
                                        <i class="fas fa-spinner fa-spin mr-1"></i>Loading barangays...
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Last School</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['last_school']); ?></p>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">ULI</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['uli']); ?></p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <!-- Include API utilities -->
    <script src="../components/api-utils.js"></script>
    
    <script>
        let isEditMode = false;
        let originalAddressData = {
            province: '<?php echo htmlspecialchars($student_profile['province']); ?>',
            city: '<?php echo htmlspecialchars($student_profile['city']); ?>',
            barangay: '<?php echo htmlspecialchars($student_profile['barangay']); ?>'
        };
        
        function toggleEditMode() {
            isEditMode = !isEditMode;
            const viewModes = document.querySelectorAll('.view-mode');
            const editModes = document.querySelectorAll('.edit-mode');
            const editControls = document.getElementById('editControls');
            const editBtn = document.getElementById('editBtn');
            
            if (isEditMode) {
                // Switch to edit mode
                viewModes.forEach(el => el.classList.add('hidden'));
                editModes.forEach(el => el.classList.remove('hidden'));
                editControls.classList.remove('hidden');
                editBtn.style.display = 'none';
                
                // Initialize address dropdowns
                initializeAddressDropdowns();
            } else {
                // Switch to view mode
                viewModes.forEach(el => el.classList.remove('hidden'));
                editModes.forEach(el => el.classList.add('hidden'));
                editControls.classList.add('hidden');
                editBtn.style.display = 'inline-flex';
            }
        }
        
        async function initializeAddressDropdowns() {
            try {
                // Load provinces
                await APIUtils.loadProvinces('edit_province', originalAddressData.province);
                
                // Setup cascading behavior
                setupAddressCascade();
                
                // If we have a selected province, load cities
                if (originalAddressData.province) {
                    const provinceSelect = document.getElementById('edit_province');
                    const selectedOption = Array.from(provinceSelect.options).find(option => option.value === originalAddressData.province);
                    if (selectedOption && selectedOption.dataset.code) {
                        await APIUtils.loadCities(selectedOption.dataset.code, 'edit_city', originalAddressData.city);
                        
                        // If we have a selected city, load barangays
                        if (originalAddressData.city) {
                            const citySelect = document.getElementById('edit_city');
                            const selectedCityOption = Array.from(citySelect.options).find(option => option.value === originalAddressData.city);
                            if (selectedCityOption && selectedCityOption.dataset.code) {
                                await APIUtils.loadBarangays(selectedCityOption.dataset.code, 'edit_barangay', originalAddressData.barangay);
                            }
                        }
                    }
                }
            } catch (error) {
                console.error('Error initializing address dropdowns:', error);
                // Fallback to text inputs if API fails
                fallbackToTextInputs();
            }
        }
        
        function setupAddressCascade() {
            const provinceSelect = document.getElementById('edit_province');
            const citySelect = document.getElementById('edit_city');
            const barangaySelect = document.getElementById('edit_barangay');
            
            if (provinceSelect) {
                provinceSelect.addEventListener('change', async function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const provinceCode = selectedOption.dataset.code;
                    
                    // Clear dependent dropdowns
                    citySelect.innerHTML = '<option value="">Select city/municipality</option>';
                    barangaySelect.innerHTML = '<option value="">Select barangay</option>';
                    
                    if (provinceCode) {
                        try {
                            await APIUtils.loadCities(provinceCode, 'edit_city');
                        } catch (error) {
                            console.error('Error loading cities:', error);
                        }
                    }
                });
            }
            
            if (citySelect) {
                citySelect.addEventListener('change', async function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const cityCode = selectedOption.dataset.code;
                    
                    // Clear barangay dropdown
                    barangaySelect.innerHTML = '<option value="">Select barangay</option>';
                    
                    if (cityCode) {
                        try {
                            await APIUtils.loadBarangays(cityCode, 'edit_barangay');
                        } catch (error) {
                            console.error('Error loading barangays:', error);
                        }
                    }
                });
            }
        }
        
        function fallbackToTextInputs() {
            // If API fails, convert dropdowns to text inputs
            const addressFields = ['edit_province', 'edit_city', 'edit_barangay'];
            const originalValues = [originalAddressData.province, originalAddressData.city, originalAddressData.barangay];
            
            addressFields.forEach((fieldId, index) => {
                const select = document.getElementById(fieldId);
                if (select) {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.name = select.name;
                    input.value = originalValues[index];
                    input.className = select.className;
                    input.placeholder = `Enter ${select.name}`;
                    
                    select.parentNode.replaceChild(input, select);
                }
            });
        }
        
        function saveChanges() {
            // Validate phone number before saving
            const phoneInput = document.querySelector('input[name="phone_number"]');
            if (phoneInput) {
                const phoneValue = phoneInput.value.trim();
                if (phoneValue && !/^\d{10}$/.test(phoneValue)) {
                    showModal('Validation Error', 'Phone number must be exactly 10 digits.', 'error');
                    phoneInput.focus();
                    return;
                }
            }
            
            // Validate address fields
            const province = document.getElementById('edit_province')?.value || document.querySelector('input[name="province"]')?.value;
            const city = document.getElementById('edit_city')?.value || document.querySelector('input[name="city"]')?.value;
            const barangay = document.getElementById('edit_barangay')?.value || document.querySelector('input[name="barangay"]')?.value;
            
            if (!province || !city || !barangay) {
                showModal('Validation Error', 'Please select/enter all address fields (Province, City, Barangay).', 'error');
                return;
            }
            
            // Show confirmation modal
            showModal('Confirm Changes', 'Are you sure you want to save these changes?', 'confirm', function() {
                document.getElementById('profileForm').submit();
            });
        }
        
        function cancelEdit() {
            showModal('Confirm Cancel', 'Are you sure you want to cancel? Any unsaved changes will be lost.', 'confirm', function() {
                location.reload();
            });
        }
        
        // Modal functions
        function showModal(title, message, type, callback = null) {
            const modal = document.getElementById('messageModal');
            const modalTitle = document.getElementById('modalTitle');
            const modalMessage = document.getElementById('modalMessage');
            const modalIcon = document.getElementById('modalIcon');
            const confirmBtn = document.getElementById('confirmBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            const okBtn = document.getElementById('okBtn');
            const modalContent = modal.querySelector('.bg-white');
            
            modalTitle.textContent = title;
            modalMessage.textContent = message;
            
            // Reset button visibility
            confirmBtn.classList.add('hidden');
            cancelBtn.classList.add('hidden');
            okBtn.classList.add('hidden');
            
            if (type === 'success') {
                modalIcon.innerHTML = '<div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto"><i class="fas fa-check text-green-600 text-2xl"></i></div>';
                okBtn.classList.remove('hidden');
                okBtn.className = 'px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors duration-200 font-medium';
                okBtn.innerHTML = '<i class="fas fa-check mr-2"></i>OK';
            } else if (type === 'error') {
                modalIcon.innerHTML = '<div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto"><i class="fas fa-exclamation-triangle text-red-600 text-2xl"></i></div>';
                okBtn.classList.remove('hidden');
                okBtn.className = 'px-6 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors duration-200 font-medium';
                okBtn.innerHTML = '<i class="fas fa-times mr-2"></i>OK';
            } else if (type === 'confirm') {
                modalIcon.innerHTML = '<div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto"><i class="fas fa-question-circle text-blue-600 text-2xl"></i></div>';
                confirmBtn.classList.remove('hidden');
                cancelBtn.classList.remove('hidden');
                
                // Set up confirm button click handler
                confirmBtn.onclick = function() {
                    hideModal();
                    if (callback) callback();
                };
            }
            
            // Show modal with animation
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            // Trigger animation
            setTimeout(() => {
                modalContent.classList.remove('scale-95');
                modalContent.classList.add('scale-100');
            }, 10);
        }
        
        function hideModal() {
            const modal = document.getElementById('messageModal');
            const modalContent = modal.querySelector('.bg-white');
            
            // Animate out
            modalContent.classList.remove('scale-100');
            modalContent.classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
                modal.classList.remove('flex');
                
                // Reset button handlers
                const confirmBtn = document.getElementById('confirmBtn');
                confirmBtn.onclick = null;
            }, 200);
        }
        
        // Close modal when clicking outside
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('messageModal');
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    hideModal();
                }
            });
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                    hideModal();
                }
            });
        });
        
        // Add input validation for phone number
        document.addEventListener('DOMContentLoaded', function() {
            const phoneInput = document.querySelector('input[name="phone_number"]');
            if (phoneInput) {
                phoneInput.addEventListener('input', function() {
                    // Remove any non-digit characters
                    this.value = this.value.replace(/\D/g, '');
                    
                    // Limit to 10 digits
                    if (this.value.length > 10) {
                        this.value = this.value.slice(0, 10);
                    }
                });
                
                phoneInput.addEventListener('blur', function() {
                    if (this.value && this.value.length !== 10) {
                        this.setCustomValidity('Phone number must be exactly 10 digits');
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }
        });
    </script>
    
    <!-- Modal for messages -->
    <div id="messageModal" class="hidden fixed inset-0 bg-black bg-opacity-50 items-center justify-center z-50">
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 p-6 transform transition-all duration-300 scale-95">
            <div class="text-center">
                <div id="modalIcon" class="mb-4">
                    <!-- Icon will be inserted here -->
                </div>
                <h3 id="modalTitle" class="text-lg font-semibold text-gray-900 mb-2">
                    <!-- Title will be inserted here -->
                </h3>
                <p id="modalMessage" class="text-gray-600 mb-6 whitespace-pre-line">
                    <!-- Message will be inserted here -->
                </p>
                <div class="flex justify-center space-x-3">
                    <button id="confirmBtn" class="hidden px-6 py-2 bg-red-800 text-white rounded-lg hover:bg-red-900 transition-colors duration-200 font-medium">
                        <i class="fas fa-check mr-2"></i>Confirm
                    </button>
                    <button id="cancelBtn" class="hidden px-6 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors duration-200 font-medium" onclick="hideModal()">
                        <i class="fas fa-times mr-2"></i>Cancel
                    </button>
                    <button id="okBtn" class="hidden px-6 py-2 bg-red-800 text-white rounded-lg hover:bg-red-900 transition-colors duration-200 font-medium" onclick="hideModal()">
                        <i class="fas fa-check mr-2"></i>OK
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <?php include '../components/footer.php'; ?> 
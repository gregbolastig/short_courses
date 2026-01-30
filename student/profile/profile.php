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
        
        $stmt = $conn->prepare("UPDATE students SET 
            sex = :sex, 
            civil_status = :civil_status, 
            contact_number = :contact_number, 
            email = :email, 
            last_school = :last_school 
            WHERE id = :id");
        
        $stmt->bindParam(':sex', $_POST['sex']);
        $stmt->bindParam(':civil_status', $_POST['civil_status']);
        $stmt->bindParam(':contact_number', $_POST['contact_number']);
        $stmt->bindParam(':email', $_POST['email']);
        $stmt->bindParam(':last_school', $_POST['last_school']);
        $stmt->bindParam(':id', $_POST['student_id']);
        
        if ($stmt->execute()) {
            $success_message = 'Profile updated successfully!';
        } else {
            $errors[] = 'Failed to update profile. Please try again.';
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
        
        // Determine lookup method
        if (isset($_GET['student_id']) && is_numeric($_GET['student_id'])) {
            $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
            $stmt->bindParam(':id', $_GET['student_id']);
        } else {
            $stmt = $conn->prepare("SELECT * FROM students WHERE uli = :uli");
            $stmt->bindParam(':uli', $_GET['uli']);
        }
        
        $stmt->execute();
        $student_profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student_profile) {
            $errors[] = 'Student record not found';
        } else {
            // Create course record from student's assigned course (if any)
            $student_courses = [];
            
            // Show course if student has been approved or completed (has course assigned)
            if (($student_profile['status'] === 'approved' || $student_profile['status'] === 'completed') && !empty($student_profile['course'])) {
                // Determine course status based on student status and training dates
                $course_status = 'pending';
                $completion_date = null;
                $certificate_number = null;
                
                if ($student_profile['status'] === 'completed') {
                    // If student status is completed, course is always completed regardless of dates
                    $course_status = 'completed';
                    $completion_date = $student_profile['training_end'];
                    $certificate_number = 'CERT-' . date('Y', strtotime($student_profile['approved_at'])) . '-' . str_pad($student_profile['id'], 3, '0', STR_PAD_LEFT);
                } elseif ($student_profile['status'] === 'approved') {
                    // Only for approved students, check training dates
                    $today = date('Y-m-d');
                    if (!empty($student_profile['training_start']) && !empty($student_profile['training_end'])) {
                        if ($today < $student_profile['training_start']) {
                            $course_status = 'pending'; // Training hasn't started yet
                        } elseif ($today >= $student_profile['training_start'] && $today <= $student_profile['training_end']) {
                            $course_status = 'in_progress'; // Currently in training
                        } else {
                            $course_status = 'completed'; // Training period ended
                            $completion_date = $student_profile['training_end'];
                            $certificate_number = 'CERT-' . date('Y', strtotime($student_profile['approved_at'])) . '-' . str_pad($student_profile['id'], 3, '0', STR_PAD_LEFT);
                        }
                    } else {
                        $course_status = 'approved'; // Approved but no training dates set
                    }
                }
                
                $student_courses[] = [
                    'id' => 1,
                    'course_name' => $student_profile['course'],
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
                                                    case 'approved':
                                                        $status_class = 'bg-purple-100 text-purple-800';
                                                        $status_icon = 'fas fa-thumbs-up';
                                                        $status_text = 'Approved';
                                                        break;
                                                    case 'pending':
                                                        $status_class = 'bg-yellow-100 text-yellow-800';
                                                        $status_icon = 'fas fa-clock';
                                                        $status_text = 'Pending Start';
                                                        break;
                                                    case 'rejected':
                                                        $status_class = 'bg-red-100 text-red-800';
                                                        $status_icon = 'fas fa-times-circle';
                                                        $status_text = 'Rejected';
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
                                                <?php elseif ($course['status'] === 'approved'): ?>
                                                    <span class="text-xs text-gray-500">In progress</span>
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
                            $in_progress_courses = array_filter($student_courses, function($course) { return $course['status'] === 'approved'; });
                            $pending_courses = array_filter($student_courses, function($course) { return $course['status'] === 'pending'; });
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
                                <p class="text-xs text-gray-500 mt-1">Cannot be changed</p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Age</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo $student_profile['age']; ?> years old</p>
                                <p class="text-xs text-gray-500 mt-1">Auto-calculated</p>
                            </div>
                            
                            <!-- Editable fields -->
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Sex</label>
                                <div class="view-mode">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['sex']); ?></p>
                                </div>
                                <div class="edit-mode hidden">
                                    <select name="sex" class="w-full text-sm border border-gray-300 rounded px-2 py-1">
                                        <option value="Male" <?php echo $student_profile['sex'] === 'Male' ? 'selected' : ''; ?>>Male</option>
                                        <option value="Female" <?php echo $student_profile['sex'] === 'Female' ? 'selected' : ''; ?>>Female</option>
                                        <option value="Other" <?php echo $student_profile['sex'] === 'Other' ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
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
                                    <input type="text" name="contact_number" value="<?php echo htmlspecialchars($student_profile['contact_number']); ?>" 
                                           class="w-full text-sm border border-gray-300 rounded px-2 py-1">
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
                            
                            <!-- Restricted fields -->
                            <div class="bg-red-50 p-4 rounded-lg border border-red-100">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Address</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['barangay'] . ', ' . $student_profile['city'] . ', ' . $student_profile['province']); ?></p>
                                <p class="text-xs text-red-800 mt-1">Contact registrar to change</p>
                            </div>
                            
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Last School</label>
                                <div class="view-mode">
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['last_school']); ?></p>
                                </div>
                                <div class="edit-mode hidden">
                                    <input type="text" name="last_school" value="<?php echo htmlspecialchars($student_profile['last_school']); ?>" 
                                           class="w-full text-sm border border-gray-300 rounded px-2 py-1">
                                </div>
                            </div>
                            
                            <div class="bg-red-50 p-4 rounded-lg border border-red-100">
                                <label class="block text-sm font-medium text-gray-500 mb-1">ULI</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['uli']); ?></p>
                                <p class="text-xs text-red-800 mt-1">Cannot be changed</p>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </main>
    
    <script>
        let isEditMode = false;
        
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
                editBtn.innerHTML = '<i class="fas fa-times mr-2"></i>Cancel Edit';
                editBtn.classList.remove('bg-red-800', 'hover:bg-red-900');
                editBtn.classList.add('bg-gray-500', 'hover:bg-gray-600');
            } else {
                // Switch to view mode
                viewModes.forEach(el => el.classList.remove('hidden'));
                editModes.forEach(el => el.classList.add('hidden'));
                editControls.classList.add('hidden');
                editBtn.innerHTML = '<i class="fas fa-edit mr-2"></i>Edit Profile';
                editBtn.classList.remove('bg-gray-500', 'hover:bg-gray-600');
                editBtn.classList.add('bg-red-800', 'hover:bg-red-900');
            }
        }
        
        function saveChanges() {
            if (confirm('Are you sure you want to save these changes?')) {
                document.getElementById('profileForm').submit();
            }
        }
        
        function cancelEdit() {
            if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
                location.reload();
            }
        }
    </script>
    
    <?php include '../components/footer.php'; ?> 
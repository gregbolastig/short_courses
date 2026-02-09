<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

$page_title = 'View Student';

// Set breadcrumb
$breadcrumb_items = [
    ['title' => 'Manage Students', 'icon' => 'fas fa-users', 'url' => 'index.php'],
    ['title' => 'View Student', 'icon' => 'fas fa-eye']
];

$student = null;
$error_message = '';
$success_message = '';

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id']) && is_numeric($_GET['id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Get student info for file cleanup
        $stmt = $conn->prepare("SELECT profile_picture FROM students WHERE id = :id");
        $stmt->bindParam(':id', $_GET['id']);
        $stmt->execute();
        $student_to_delete = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete student record
        $stmt = $conn->prepare("DELETE FROM students WHERE id = :id");
        $stmt->bindParam(':id', $_GET['id']);
        
        if ($stmt->execute()) {
            // Delete profile picture file if exists
            if ($student_to_delete && !empty($student_to_delete['profile_picture'])) {
                $stored_path = $student_to_delete['profile_picture'];
                
                if (strpos($stored_path, '../') === 0) {
                    $file_path = $stored_path;
                } else {
                    $file_path = '../../' . $stored_path;
                }
                
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            
            // Redirect to students list with success message
            header('Location: index.php?deleted=1');
            exit;
        }
    } catch (PDOException $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error_message = 'Invalid student ID.';
} else {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
        $stmt->bindParam(':id', $_GET['id']);
        $stmt->execute();
        
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student) {
            $error_message = 'Student not found.';
        } else {
            // Update breadcrumb with student name
            $breadcrumb_items = [
                ['title' => 'Manage Students', 'icon' => 'fas fa-users', 'url' => 'index.php'],
                ['title' => 'View: ' . $student['first_name'] . ' ' . $student['last_name'], 'icon' => 'fas fa-eye']
            ];
        }
        
    } catch (PDOException $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

// Get pending approvals count for sidebar
try {
    $stmt = $conn->query("SELECT COUNT(*) as pending FROM students WHERE status = 'pending'");
    $pending_approvals = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
} catch (PDOException $e) {
    $pending_approvals = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - Jacobo Z. Gonzales Memorial School of Arts and Trades</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            900: '#1e3a8a'
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50">
    <?php include '../components/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="md:ml-64 min-h-screen">
        <!-- Header -->
        <?php include '../components/header.php'; ?>
        
        <!-- Page Content -->
        <main class="p-4 md:p-6 lg:p-8">

            <?php if ($error_message): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i>
                        <?php echo htmlspecialchars($error_message); ?>
                    </div>
                </div>
                <div class="text-center">
                    <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 transition-colors duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Students List
                    </a>
                </div>
            <?php else: ?>
                
                <!-- Page Header -->
                <div class="mb-6 md:mb-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="mb-4 md:mb-0">
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">Student Details</h1>
                            <p class="text-gray-600">Complete information for <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></p>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <a href="edit.php?id=<?php echo $student['id']; ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm">
                                <i class="fas fa-edit mr-2"></i>Edit Student
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Student Profile Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6 md:mb-8">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-8">
                        <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                            <div class="flex-shrink-0">
                            <?php 
                            // Handle profile picture path resolution for admin view
                            $profile_picture_url = '';
                            $file_exists = false;
                            
                            if (!empty($student['profile_picture'])) {
                                $stored_path = $student['profile_picture'];
                                
                                // Handle both old format (../uploads/profiles/file.jpg) and new format (uploads/profiles/file.jpg)
                                if (strpos($stored_path, '../') === 0) {
                                    // Old format: use as is (already has ../)
                                    $profile_picture_url = $stored_path;
                                } else {
                                    // New format: add ../../ (since we're in admin/students/)
                                    $profile_picture_url = '../../' . $stored_path;
                                }
                                
                                $file_exists = file_exists($profile_picture_url);
                            }
                            ?>
                            
                            <?php if (!empty($student['profile_picture']) && $file_exists): ?>
                                <div class="relative group">
                                    <img src="<?php echo htmlspecialchars($profile_picture_url); ?>" 
                                         alt="Profile Picture" 
                                         class="w-24 h-24 md:w-32 md:h-32 rounded-full object-cover border-4 border-white shadow-lg"
                                         onerror="this.parentElement.style.display='none'; this.parentElement.nextElementSibling.style.display='block';">
                                    <!-- Professional badge -->
                                    <div class="absolute -bottom-1 -right-1 bg-green-500 text-white p-1.5 rounded-full shadow-lg">
                                        <i class="fas fa-check text-xs"></i>
                                    </div>
                                </div>
                                <!-- Fallback for broken images -->
                                <div class="relative group" style="display: none;">
                                    <div class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-white bg-opacity-20 border-4 border-white shadow-lg flex items-center justify-center">
                                        <div class="text-center">
                                            <span class="text-2xl md:text-3xl font-bold text-white">
                                                <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <!-- Error indicator -->
                                    <div class="absolute -bottom-1 -right-1 bg-red-500 text-white p-1.5 rounded-full shadow-lg">
                                        <i class="fas fa-exclamation-triangle text-xs"></i>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="relative group">
                                    <div class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-white bg-opacity-20 border-4 border-white shadow-lg flex items-center justify-center">
                                        <span class="text-2xl md:text-3xl font-bold text-white">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <!-- Missing photo indicator -->
                                    <div class="absolute -bottom-1 -right-1 bg-gray-400 text-white p-1.5 rounded-full shadow-lg">
                                        <i class="fas fa-camera text-xs"></i>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if (isset($_GET['debug']) && $_GET['debug'] == '1'): ?>
                                <!-- Debug information (only shown when ?debug=1 is in URL) -->
                                <div class="mt-2 text-xs text-white bg-black bg-opacity-50 p-2 rounded">
                                    <strong>Debug Info:</strong><br>
                                    Stored Path: <?php echo htmlspecialchars($student['profile_picture'] ?? 'NULL'); ?><br>
                                    Resolved URL: <?php echo htmlspecialchars($profile_picture_url ?? 'NULL'); ?><br>
                                    File Exists: <?php echo $file_exists ? 'YES' : 'NO'; ?><br>
                                    Full Path: <?php echo htmlspecialchars(realpath($profile_picture_url ?? '') ?: 'Not found'); ?>
                                </div>
                            <?php endif; ?>
                            </div>
                            
                            <div class="text-center md:text-left text-white flex-1">
                                <h2 class="text-2xl md:text-3xl font-bold mb-2">
                                    <?php echo htmlspecialchars(trim($student['first_name'] . ' ' . ($student['middle_name'] ? $student['middle_name'] . ' ' : '') . $student['last_name'])); ?>
                                    <?php if ($student['extension_name']): ?>
                                        <?php echo htmlspecialchars($student['extension_name']); ?>
                                    <?php endif; ?>
                                </h2>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-blue-100">
                                    <div class="flex items-center justify-center md:justify-start">
                                        <i class="fas fa-id-card mr-2"></i>
                                        <span>ULI: <?php echo htmlspecialchars($student['uli']); ?></span>
                                    </div>
                                    <div class="flex items-center justify-center md:justify-start">
                                        <i class="fas fa-envelope mr-2"></i>
                                        <span><?php echo htmlspecialchars($student['email']); ?></span>
                                    </div>
                                    <div class="flex items-center justify-center md:justify-start">
                                        <i class="fas fa-phone mr-2"></i>
                                        <span><?php echo htmlspecialchars($student['contact_number']); ?></span>
                                    </div>
                                    <div class="flex items-center justify-center md:justify-start">
                                        <i class="fas fa-calendar mr-2"></i>
                                        <span>Registered: <?php echo date('M j, Y', strtotime($student['created_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Status Badge -->
                                <div class="mt-4">
                                    <?php
                                    $status_class = '';
                                    $status_icon = '';
                                    switch ($student['status']) {
                                        case 'completed':
                                            $status_class = 'bg-green-100 text-green-800 border-green-200';
                                            $status_icon = 'fas fa-graduation-cap';
                                            break;
                                        case 'rejected':
                                            $status_class = 'bg-red-100 text-red-800 border-red-200';
                                            $status_icon = 'fas fa-times-circle';
                                            break;
                                        default:
                                            $status_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                            $status_icon = 'fas fa-clock';
                                    }
                                    ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border <?php echo $status_class; ?>">
                                        <i class="<?php echo $status_icon; ?> mr-2"></i>
                                        <?php echo ucfirst($student['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Information Cards Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8">
                    
                    <!-- Personal Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-user text-blue-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Personal Information</h3>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">First Name</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['first_name']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Middle Name</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['middle_name'] ?: 'N/A'); ?></p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Last Name</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['last_name']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Extension</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['extension_name'] ?: 'N/A'); ?></p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Birthday</label>
                                    <p class="text-sm text-gray-900"><?php echo date('F j, Y', strtotime($student['birthday'])); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Age</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['age']); ?> years old</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Gender</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['sex']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Civil Status</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['civil_status']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-map-marker-alt text-green-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Address Information</h3>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Province</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['province']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">City/Municipality</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['city']); ?></p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Barangay</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['barangay']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Street Address</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['street_address'] ?: 'N/A'); ?></p>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Place of Birth</label>
                                <p class="text-sm text-gray-900">
                                    <?php 
                                    $place_of_birth = '';
                                    if (!empty($student['birth_city']) && !empty($student['birth_province'])) {
                                        $place_of_birth = htmlspecialchars($student['birth_city'] . ', ' . $student['birth_province']);
                                    } elseif (!empty($student['birth_city'])) {
                                        $place_of_birth = htmlspecialchars($student['birth_city']);
                                    } elseif (!empty($student['birth_province'])) {
                                        $place_of_birth = htmlspecialchars($student['birth_province']);
                                    } else {
                                        $place_of_birth = 'N/A';
                                    }
                                    echo $place_of_birth;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Guardian Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-users text-purple-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Guardian Information</h3>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Guardian Name</label>
                                <p class="text-sm text-gray-900">
                                    <?php 
                                    $guardian_name = trim(($student['guardian_first_name'] ?? '') . ' ' . ($student['guardian_middle_name'] ?? '') . ' ' . ($student['guardian_last_name'] ?? ''));
                                    echo htmlspecialchars($guardian_name ?: 'N/A'); 
                                    ?>
                                    <?php if ($student['guardian_extension'] ?? ''): ?>
                                        <?php echo htmlspecialchars($student['guardian_extension']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Contact Number</label>
                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['parent_contact']); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Education Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-graduation-cap text-orange-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Education Information</h3>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Last School Attended</label>
                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['last_school']); ?></p>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">School Province</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['school_province']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">School City</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['school_city']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Course Information (if completed) -->
                <?php if ($student['status'] === 'completed' && ($student['course'] || $student['nc_level'] || $student['adviser'])): ?>
                    <div class="mt-6 md:mt-8 bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-indigo-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-book text-indigo-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Course Information</h3>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <?php if ($student['course']): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Course</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['course']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student['nc_level']): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">NC Level</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['nc_level']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student['training_start']): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Training Start</label>
                                    <p class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($student['training_start'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student['training_end']): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Training End</label>
                                    <p class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($student['training_end'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($student['adviser']): ?>
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Assigned Adviser</label>
                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['adviser']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <!-- Delete Button (Centered) -->
                <div class="mt-6 md:mt-8 flex justify-center">
                    <button onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>')" 
                            class="inline-flex items-center justify-center px-6 py-3 bg-red-600 text-white text-sm font-semibold rounded-lg hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-all duration-200 shadow-sm">
                        <i class="fas fa-trash mr-2"></i>Delete Student
                    </button>
                </div>
                
            <?php endif; ?>
        </main>
    </div>

    <script>
        function confirmDelete(studentId, studentName) {
            // Create modern confirmation modal
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-lg shadow-xl p-4 sm:p-6 max-w-sm mx-4 transform transition-all">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full">
                        <i class="fas fa-exclamation-triangle text-red-600"></i>
                    </div>
                    <h3 class="mt-4 text-lg font-medium text-gray-900 text-center">Delete Student</h3>
                    <p class="mt-2 text-sm text-gray-500 text-center">
                        Are you sure you want to delete <strong>${studentName}</strong>? This action cannot be undone.
                    </p>
                    <div class="mt-6 flex flex-col-reverse sm:flex-row gap-2">
                        <button onclick="this.parentElement.parentElement.parentElement.remove()" 
                                class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors duration-200">
                            Cancel
                        </button>
                        <button onclick="window.location.href='?action=delete&id=${studentId}'" 
                                class="flex-1 px-4 py-2 text-sm font-medium text-white bg-red-600 rounded-lg hover:bg-red-700 transition-colors duration-200">
                            Delete
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Close modal when clicking outside
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.remove();
                }
            });
        }
    </script>
</body>
</html>
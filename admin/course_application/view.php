<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

$page_title = 'View Course Application';

// Set breadcrumb
$breadcrumb_items = [
    ['title' => 'Course Applications', 'icon' => 'fas fa-file-alt', 'url' => 'index.php'],
    ['title' => 'View Application', 'icon' => 'fas fa-eye']
];

$application = null;
$error_message = '';
$success_message = '';

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $error_message = 'Invalid application ID.';
} else {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Get application details with student information
        $stmt = $conn->prepare("SELECT ca.*, s.first_name, s.last_name, s.middle_name, s.extension_name, 
                                       s.email, s.uli, s.contact_number, s.birthday, s.age, s.sex, s.civil_status,
                                       s.province, s.city, s.barangay, s.street_address, s.birth_province, s.birth_city,
                                       s.guardian_first_name, s.guardian_middle_name, s.guardian_last_name, 
                                       s.guardian_extension, s.parent_contact, s.last_school, s.school_province, 
                                       s.school_city, s.profile_picture,
                                       u.username as reviewed_by_name
                               FROM course_applications ca
                               INNER JOIN students s ON ca.student_id = s.id
                               LEFT JOIN users u ON ca.reviewed_by = u.id
                               WHERE ca.application_id = :id");
        $stmt->bindParam(':id', $_GET['id']);
        $stmt->execute();
        
        $application = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$application) {
            $error_message = 'Application not found.';
        } else {
            // Update breadcrumb with student name
            $breadcrumb_items = [
                ['title' => 'Course Applications', 'icon' => 'fas fa-file-alt', 'url' => 'index.php'],
                ['title' => 'View: ' . $application['first_name'] . ' ' . $application['last_name'], 'icon' => 'fas fa-eye']
            ];
        }
        
    } catch (PDOException $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

// Get pending approvals count for sidebar
try {
    if (isset($conn)) {
        $stmt = $conn->query("SELECT COUNT(*) as pending FROM course_applications WHERE status = 'pending'");
        $pending_approvals = $stmt->fetch(PDO::FETCH_ASSOC)['pending'];
    } else {
        $pending_approvals = 0;
    }
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
    <?php include '../components/admin-styles.php'; ?>
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
                        <i class="fas fa-arrow-left mr-2"></i>Back to Applications List
                    </a>
                </div>
            <?php else: ?>
                
                <!-- Page Header -->
                <div class="mb-6 md:mb-8">
                    <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                        <div class="mb-4 md:mb-0">
                            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">Course Application Details</h1>
                            <p class="text-gray-600">Application for <?php echo htmlspecialchars($application['first_name'] . ' ' . $application['last_name']); ?></p>
                        </div>
                        <div class="flex flex-col sm:flex-row gap-3">
                            <a href="index.php" class="inline-flex items-center px-4 py-2 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200 shadow-sm">
                                <i class="fas fa-arrow-left mr-2"></i>Back to Applications
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Application Status Card -->
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6 md:mb-8">
                    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-8">
                        <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                            <div class="flex-shrink-0">
                            <?php 
                            // Handle profile picture path resolution for admin view
                            $profile_picture_url = '';
                            $file_exists = false;
                            
                            if (!empty($application['profile_picture'])) {
                                $stored_path = $application['profile_picture'];
                                
                                // Handle both old format (../uploads/profiles/file.jpg) and new format (uploads/profiles/file.jpg)
                                if (strpos($stored_path, '../') === 0) {
                                    // Old format: use as is (already has ../)
                                    $profile_picture_url = $stored_path;
                                } else {
                                    // New format: add ../../ (since we're in admin/course_application/)
                                    $profile_picture_url = '../../' . $stored_path;
                                }
                                
                                $file_exists = file_exists($profile_picture_url);
                            }
                            ?>
                            
                            <?php if (!empty($application['profile_picture']) && $file_exists): ?>
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
                                                <?php echo strtoupper(substr($application['first_name'], 0, 1) . substr($application['last_name'], 0, 1)); ?>
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
                                            <?php echo strtoupper(substr($application['first_name'], 0, 1) . substr($application['last_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <!-- Missing photo indicator -->
                                    <div class="absolute -bottom-1 -right-1 bg-gray-400 text-white p-1.5 rounded-full shadow-lg">
                                        <i class="fas fa-camera text-xs"></i>
                                    </div>
                                </div>
                            <?php endif; ?>
                            </div>
                            
                            <div class="text-center md:text-left text-white flex-1">
                                <h2 class="text-2xl md:text-3xl font-bold mb-2">
                                    <?php echo htmlspecialchars(trim($application['first_name'] . ' ' . ($application['middle_name'] ? $application['middle_name'] . ' ' : '') . $application['last_name'])); ?>
                                    <?php if ($application['extension_name']): ?>
                                        <?php echo htmlspecialchars($application['extension_name']); ?>
                                    <?php endif; ?>
                                </h2>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-blue-100">
                                    <div class="flex items-center justify-center md:justify-start">
                                        <i class="fas fa-id-card mr-2"></i>
                                        <span>ULI: <?php echo htmlspecialchars($application['uli']); ?></span>
                                    </div>
                                    <div class="flex items-center justify-center md:justify-start">
                                        <i class="fas fa-envelope mr-2"></i>
                                        <span><?php echo htmlspecialchars($application['email']); ?></span>
                                    </div>
                                    <div class="flex items-center justify-center md:justify-start">
                                        <i class="fas fa-phone mr-2"></i>
                                        <span><?php echo htmlspecialchars($application['contact_number']); ?></span>
                                    </div>
                                    <div class="flex items-center justify-center md:justify-start">
                                        <i class="fas fa-calendar mr-2"></i>
                                        <span>Applied: <?php echo date('M j, Y', strtotime($application['applied_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <!-- Status Badge -->
                                <div class="mt-4">
                                    <?php
                                    $status_class = '';
                                    $status_icon = '';
                                    switch ($application['status']) {
                                        case 'approved':
                                            $status_class = 'bg-green-100 text-green-800 border-green-200';
                                            $status_icon = 'fas fa-check-circle';
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
                                        <?php echo ucfirst($application['status']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Information Cards Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8">
                    
                    <!-- Course Application Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-book text-blue-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Course Application Details</h3>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Course Name</label>
                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['course_name']); ?></p>
                            </div>
                            
                            <?php if ($application['nc_level']): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">NC Level</label>
                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['nc_level']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($application['adviser']): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Assigned Adviser</label>
                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['adviser']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($application['training_start'] && $application['training_end']): ?>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Training Start</label>
                                    <p class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($application['training_start'])); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Training End</label>
                                    <p class="text-sm text-gray-900"><?php echo date('M j, Y', strtotime($application['training_end'])); ?></p>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Application Date</label>
                                <p class="text-sm text-gray-900"><?php echo date('F j, Y g:i A', strtotime($application['applied_at'])); ?></p>
                            </div>
                            
                            <?php if ($application['reviewed_by_name']): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Reviewed By</label>
                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['reviewed_by_name']); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($application['reviewed_at']): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Review Date</label>
                                <p class="text-sm text-gray-900"><?php echo date('F j, Y g:i A', strtotime($application['reviewed_at'])); ?></p>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($application['notes']): ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Notes</label>
                                <p class="text-sm text-gray-900 bg-gray-50 p-3 rounded-lg"><?php echo nl2br(htmlspecialchars($application['notes'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Personal Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-user text-green-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Personal Information</h3>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">First Name</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['first_name']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Middle Name</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['middle_name'] ?: 'N/A'); ?></p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Last Name</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['last_name']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Extension</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['extension_name'] ?: 'N/A'); ?></p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Birthday</label>
                                    <p class="text-sm text-gray-900"><?php echo date('F j, Y', strtotime($application['birthday'])); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Age</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['age']); ?> years old</p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Gender</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['sex']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Civil Status</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['civil_status']); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Address Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-map-marker-alt text-purple-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Address Information</h3>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Province</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['province']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">City/Municipality</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['city']); ?></p>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Barangay</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['barangay']); ?></p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Street Address</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['street_address'] ?: 'N/A'); ?></p>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Place of Birth</label>
                                <p class="text-sm text-gray-900">
                                    <?php 
                                    $place_of_birth = '';
                                    if (!empty($application['birth_city']) && !empty($application['birth_province'])) {
                                        $place_of_birth = htmlspecialchars($application['birth_city'] . ', ' . $application['birth_province']);
                                    } elseif (!empty($application['birth_city'])) {
                                        $place_of_birth = htmlspecialchars($application['birth_city']);
                                    } elseif (!empty($application['birth_province'])) {
                                        $place_of_birth = htmlspecialchars($application['birth_province']);
                                    } else {
                                        $place_of_birth = 'N/A';
                                    }
                                    echo $place_of_birth;
                                    ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Guardian & Education Information -->
                    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-10 h-10 bg-orange-100 rounded-lg flex items-center justify-center mr-3">
                                <i class="fas fa-users text-orange-600"></i>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900">Guardian & Education</h3>
                        </div>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Guardian Name</label>
                                <p class="text-sm text-gray-900">
                                    <?php 
                                    $guardian_name = trim(($application['guardian_first_name'] ?? '') . ' ' . ($application['guardian_middle_name'] ?? '') . ' ' . ($application['guardian_last_name'] ?? ''));
                                    echo htmlspecialchars($guardian_name ?: 'N/A'); 
                                    ?>
                                    <?php if ($application['guardian_extension'] ?? ''): ?>
                                        <?php echo htmlspecialchars($application['guardian_extension']); ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-500 mb-1">Guardian Contact</label>
                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['parent_contact']); ?></p>
                            </div>
                            
                            <div class="pt-4 border-t border-gray-200">
                                <div>
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Last School Attended</label>
                                    <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['last_school']); ?></p>
                                </div>
                                
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 mb-1">School Province</label>
                                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['school_province']); ?></p>
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-500 mb-1">School City</label>
                                        <p class="text-sm text-gray-900"><?php echo htmlspecialchars($application['school_city']); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
            <?php endif; ?>
        </main>
    </div>

    <?php include '../components/admin-scripts.php'; ?>
</body>
</html>
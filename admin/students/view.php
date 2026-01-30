<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

$page_title = 'View Student';

$student = null;
$error_message = '';

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
    <title><?php echo $page_title; ?> - JZGMSAT Admin</title>
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
            <!-- Breadcrumb -->
            <nav class="flex mb-6" aria-label="Breadcrumb">
                <ol class="inline-flex items-center space-x-1 md:space-x-3">
                    <li class="inline-flex items-center">
                        <a href="index.php" class="inline-flex items-center text-sm font-medium text-gray-700 hover:text-blue-600">
                            <i class="fas fa-users mr-2"></i>Manage Students
                        </a>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <span class="text-sm font-medium text-gray-500">View Student</span>
                        </div>
                    </li>
                </ol>
            </nav>

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
                                <?php if (!empty($student['profile_picture']) && file_exists('../../' . $student['profile_picture'])): ?>
                                    <img src="../../<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                                         alt="Profile Picture" 
                                         class="w-24 h-24 md:w-32 md:h-32 rounded-full object-cover border-4 border-white shadow-lg">
                                <?php else: ?>
                                    <div class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-white bg-opacity-20 border-4 border-white shadow-lg flex items-center justify-center">
                                        <span class="text-2xl md:text-3xl font-bold text-white">
                                            <?php echo strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1)); ?>
                                        </span>
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
                                <p class="text-sm text-gray-900"><?php echo htmlspecialchars($student['place_of_birth']); ?></p>
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

                <!-- Action Buttons -->
                <div class="mt-6 md:mt-8 flex flex-col sm:flex-row gap-3 justify-center">
                    <a href="edit.php?id=<?php echo $student['id']; ?>" 
                       class="inline-flex items-center justify-center px-6 py-3 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-sm">
                        <i class="fas fa-edit mr-2"></i>Edit Student
                    </a>
                    
                    <a href="index.php" 
                       class="inline-flex items-center justify-center px-6 py-3 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Students List
                    </a>
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
            if (confirm(`Are you sure you want to delete ${studentName}? This action cannot be undone.`)) {
                window.location.href = `?action=delete&id=${studentId}`;
            }
        }
    </script>
</body>
</html>
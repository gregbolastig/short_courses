<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';
require_once '../../includes/system_activity_logger.php';

// Require admin authentication
requireAdmin();

$page_title = 'Edit Student';

// Set breadcrumb
$breadcrumb_items = [
    ['title' => 'Manage Students', 'icon' => 'fas fa-users', 'url' => 'index.php'],
    ['title' => 'Edit Student', 'icon' => 'fas fa-edit']
];

$student = null;
$errors = [];
$success_message = '';

// Initialize system activity logger
$logger = new SystemActivityLogger();

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$student_id = $_GET['id'];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required_fields = [
        'first_name', 'last_name', 'birthday', 'sex', 'civil_status',
        'contact_number', 'province', 'city', 'barangay', 
        'guardian_first_name', 'guardian_last_name', 'parent_contact', 
        'email', 'uli', 'last_school', 'school_province', 'school_city'
    ];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst(str_replace('_', ' ', $field)) . ' is required';
        }
    }
    
    // Validate email format
    if (!empty($_POST['email']) && !filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Invalid email format';
    }
    
    // Validate phone numbers
    $phone_pattern = '/^(\+63|0)[0-9]{10}$/';
    if (!empty($_POST['contact_number']) && !preg_match($phone_pattern, str_replace(' ', '', $_POST['contact_number']))) {
        $errors[] = 'Invalid contact number format';
    }
    if (!empty($_POST['parent_contact']) && !preg_match($phone_pattern, str_replace(' ', '', $_POST['parent_contact']))) {
        $errors[] = 'Invalid parent contact number format';
    }
    
    // Calculate age
    $age = 0;
    if (!empty($_POST['birthday'])) {
        $birthday = new DateTime($_POST['birthday']);
        $today = new DateTime();
        $age = $today->diff($birthday)->y;
    }
    
    // Handle file upload
    $profile_picture_path = $_POST['existing_profile_picture'] ?? '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = 'Profile picture must be JPG, JPEG, or PNG';
        } elseif ($file_size > 2 * 1024 * 1024) {
            $errors[] = 'Profile picture must be less than 2MB';
        } else {
            $upload_dir = '../../uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $full_upload_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $full_upload_path)) {
                // Delete old profile picture if exists
                if (!empty($profile_picture_path)) {
                    $old_file_path = '';
                    if (strpos($profile_picture_path, '../') === 0) {
                        $old_file_path = '../../' . substr($profile_picture_path, 3);
                    } else {
                        $old_file_path = '../../' . $profile_picture_path;
                    }
                    
                    if (file_exists($old_file_path)) {
                        unlink($old_file_path);
                    }
                }
                
                $profile_picture_path = 'uploads/profiles/' . $filename;
            } else {
                $errors[] = 'Failed to upload profile picture';
            }
        }
    }
    
    // If no errors, update database
    if (empty($errors)) {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            $sql = "UPDATE students SET 
                first_name = :first_name, middle_name = :middle_name, last_name = :last_name, extension_name = :extension_name,
                birthday = :birthday, age = :age, sex = :sex, civil_status = :civil_status,
                contact_number = :contact_number, province = :province, city = :city,
                barangay = :barangay, street_address = :street_address, place_of_birth = :place_of_birth,
                guardian_first_name = :guardian_first_name, guardian_middle_name = :guardian_middle_name, 
                guardian_last_name = :guardian_last_name, guardian_extension = :guardian_extension,
                parent_contact = :parent_contact, email = :email, profile_picture = :profile_picture, 
                uli = :uli, last_school = :last_school, school_province = :school_province, school_city = :school_city
                WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            
            $stmt->bindParam(':first_name', $_POST['first_name']);
            $stmt->bindParam(':middle_name', $_POST['middle_name']);
            $stmt->bindParam(':last_name', $_POST['last_name']);
            $stmt->bindParam(':extension_name', $_POST['extension_name']);
            $stmt->bindParam(':birthday', $_POST['birthday']);
            $stmt->bindParam(':age', $age);
            $stmt->bindParam(':sex', $_POST['sex']);
            $stmt->bindParam(':civil_status', $_POST['civil_status']);
            $stmt->bindParam(':contact_number', $_POST['contact_number']);
            $stmt->bindParam(':province', $_POST['province']);
            $stmt->bindParam(':city', $_POST['city']);
            $stmt->bindParam(':barangay', $_POST['barangay']);
            $stmt->bindParam(':street_address', $_POST['street_address']);
            $stmt->bindParam(':place_of_birth', $_POST['place_of_birth']);
            $stmt->bindParam(':guardian_first_name', $_POST['guardian_first_name']);
            $stmt->bindParam(':guardian_middle_name', $_POST['guardian_middle_name']);
            $stmt->bindParam(':guardian_last_name', $_POST['guardian_last_name']);
            $stmt->bindParam(':guardian_extension', $_POST['guardian_extension']);
            $stmt->bindParam(':parent_contact', $_POST['parent_contact']);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':profile_picture', $profile_picture_path);
            $stmt->bindParam(':uli', $_POST['uli']);
            $stmt->bindParam(':last_school', $_POST['last_school']);
            $stmt->bindParam(':school_province', $_POST['school_province']);
            $stmt->bindParam(':school_city', $_POST['school_city']);
            $stmt->bindParam(':id', $student_id);
            
            if ($stmt->execute()) {
                // Log student update
                $logger->log(
                    'student_updated',
                    "Admin updated student information for '{$_POST['first_name']} {$_POST['last_name']}' (ID: {$student_id})",
                    'admin',
                    $_SESSION['user_id'],
                    'student',
                    $student_id
                );
                
                $success_message = 'Student information updated successfully!';
            } else {
                $errors[] = 'Update failed. Please try again.';
            }
            
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $errors[] = 'Email or ULI already exists. Please use different values.';
            } else {
                $errors[] = 'Database error: ' . $e->getMessage();
            }
        }
    }
}

// Get student data
try {
    $database = new Database();
    $conn = $database->getConnection();
    
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
    $stmt->bindParam(':id', $student_id);
    $stmt->execute();
    
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        header('Location: index.php');
        exit;
    } else {
        // Update breadcrumb with student name
        $breadcrumb_items = [
            ['title' => 'Manage Students', 'icon' => 'fas fa-users', 'url' => 'index.php'],
            ['title' => 'View: ' . $student['first_name'] . ' ' . $student['last_name'], 'icon' => 'fas fa-eye', 'url' => 'view.php?id=' . $student_id],
            ['title' => 'Edit', 'icon' => 'fas fa-edit']
        ];
    }
    
} catch (PDOException $e) {
    $errors[] = 'Database error: ' . $e->getMessage();
}

// Get pending approvals count for sidebar
try {
    $database = new Database();
    $conn = $database->getConnection();
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
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-in': 'slideIn 0.3s ease-out'
                    }
                }
            }
        }
    </script>
    <?php include '../components/admin-styles.php'; ?>
    <style>
        /* Modern form styles */
        .profile-preview {
            @apply w-32 h-32 object-cover rounded-lg border-2 border-gray-200 mb-3;
        }
        .upload-placeholder {
            @apply w-32 h-32 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center text-gray-500 text-sm mb-3;
        }
        /* Enhance form inputs */
        input:focus, select:focus, textarea:focus {
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        /* Loading state for form submission */
        .form-loading {
            opacity: 0.6;
            pointer-events: none;
        }
        /* Success animation */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .animate-fade-in-up {
            animation: fadeInUp 0.3s ease-out;
        }
    </style>
    
    <!-- API Utilities for Phone Number Country Codes -->
    <script src="../../student/components/api-utils.js"></script>
</head>
<body class="bg-gray-50">
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
                            <div class="bg-gradient-to-r from-blue-600 to-blue-700 rounded-2xl p-6 md:p-8 text-white shadow-xl">
                                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                                    <div class="mb-4 md:mb-0">
                                        <div class="flex items-center mb-3">
                                            <div class="w-12 h-12 bg-white bg-opacity-20 rounded-xl flex items-center justify-center mr-4">
                                                <i class="fas fa-edit text-2xl text-white"></i>
                                            </div>
                                            <div>
                                                <h1 class="text-3xl md:text-4xl font-bold mb-1">Edit Student</h1>
                                                <p class="text-blue-100 text-lg">Update student information and details</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Alerts -->
                        <?php if (!empty($errors)): ?>
                            <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg animate-fade-in">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <h4 class="text-sm font-medium text-red-800 mb-2">Please fix the following errors:</h4>
                                        <ul class="list-disc list-inside space-y-1 text-sm text-red-700">
                                            <?php foreach ($errors as $error): ?>
                                                <li><?php echo htmlspecialchars($error); ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success_message): ?>
                            <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-r-lg animate-fade-in">
                                <div class="flex">
                                    <div class="flex-shrink-0">
                                        <i class="fas fa-check-circle text-green-400"></i>
                                    </div>
                                    <div class="ml-3">
                                        <p class="text-sm text-green-700"><?php echo htmlspecialchars($success_message); ?></p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
        
        <?php if ($student): ?>
        <form id="edit-form" method="POST" enctype="multipart/form-data" class="space-y-8">
            <input type="hidden" name="existing_profile_picture" value="<?php echo htmlspecialchars($student['profile_picture']); ?>">
            
            <!-- Personal Information -->
            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                    <div class="flex items-center space-x-3">
                        <div class="bg-blue-100 rounded-xl p-2">
                            <i class="fas fa-user text-blue-600"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900">Personal Information</h3>
                    </div>
                </div>
                <div class="p-6 md:p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label for="first_name" class="block text-sm font-medium text-gray-700 mb-2">First Name *</label>
                            <input type="text" id="first_name" name="first_name" required 
                                   value="<?php echo htmlspecialchars($student['first_name']); ?>"
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="middle_name" class="block text-sm font-medium text-gray-700 mb-2">Middle Name</label>
                            <input type="text" id="middle_name" name="middle_name" 
                                   value="<?php echo htmlspecialchars($student['middle_name']); ?>"
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="last_name" class="block text-sm font-medium text-gray-700 mb-2">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" required 
                                   value="<?php echo htmlspecialchars($student['last_name']); ?>"
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
                        <div>
                            <label for="extension_name" class="block text-sm font-medium text-gray-700 mb-2">Extension Name</label>
                            <input type="text" id="extension_name" name="extension_name" 
                                   placeholder="Jr., Sr., III, etc."
                                   value="<?php echo htmlspecialchars($student['extension_name']); ?>"
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="birthday" class="block text-sm font-medium text-gray-700 mb-2">Birthday *</label>
                            <input type="date" id="birthday" name="birthday" required 
                                   value="<?php echo htmlspecialchars($student['birthday']); ?>"
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="age" class="block text-sm font-medium text-gray-700 mb-2">Age</label>
                            <input type="number" id="age" name="age" readonly value="<?php echo htmlspecialchars($student['age']); ?>"
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-500 text-sm shadow-sm">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mt-6">
                        <div>
                            <label for="sex" class="block text-sm font-medium text-gray-700 mb-2">Sex *</label>
                            <select id="sex" name="sex" required 
                                    class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                <option value="">Select Sex</option>
                                <option value="Male" <?php echo ($student['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo ($student['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo ($student['sex'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div>
                            <label for="civil_status" class="block text-sm font-medium text-gray-700 mb-2">Civil Status *</label>
                            <select id="civil_status" name="civil_status" required 
                                    class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                                <option value="">Select Civil Status</option>
                                <option value="Single" <?php echo ($student['civil_status'] === 'Single') ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo ($student['civil_status'] === 'Married') ? 'selected' : ''; ?>>Married</option>
                                <option value="Divorced" <?php echo ($student['civil_status'] === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?php echo ($student['civil_status'] === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                        <div>
                            <label for="contact_number" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-phone text-blue-500 mr-2"></i>Contact Number *
                            </label>
                            <div class="flex flex-col sm:flex-row gap-2">
                                <select id="country_code" name="country_code" 
                                        class="w-full sm:w-24 px-2 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 hover:border-gray-300 bg-gray-50 text-sm shadow-sm">
                                    <option value="">Code</option>
                                </select>
                                <input type="tel" id="contact_number" name="contact_number" required 
                                       class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 hover:border-gray-300 text-sm shadow-sm"
                                       placeholder="9123456789"
                                       pattern="[0-9]{10}"
                                       inputmode="numeric"
                                       value="<?php echo htmlspecialchars($student['contact_number']); ?>">
                            </div>
                            <p class="text-xs text-gray-500 mt-2 flex items-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                Select country code and enter 10-digit phone number (e.g., 9123456789)
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Address Information -->
            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                    <div class="flex items-center space-x-3">
                        <div class="bg-green-100 rounded-xl p-2">
                            <i class="fas fa-map-marker-alt text-green-600"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900">Address Information</h3>
                    </div>
                </div>
                <div class="p-6 md:p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label for="province" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map text-green-600 mr-1"></i>Province *
                            </label>
                            <select id="province" name="province" required 
                                    class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm"
                                    data-selected="<?php echo htmlspecialchars($student['province']); ?>">
                                <option value="">Loading provinces...</option>
                            </select>
                            <div id="province-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Loading provinces...
                            </div>
                        </div>
                        <div>
                            <label for="city" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-city text-green-600 mr-1"></i>City/Municipality *
                            </label>
                            <select id="city" name="city" required 
                                    class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm"
                                    data-selected="<?php echo htmlspecialchars($student['city']); ?>">
                                <option value="">Select city/municipality</option>
                            </select>
                            <div id="city-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Loading cities...
                            </div>
                        </div>
                        <div>
                            <label for="barangay" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-home text-green-600 mr-1"></i>Barangay *
                            </label>
                            <select id="barangay" name="barangay" required 
                                    class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm"
                                    data-selected="<?php echo htmlspecialchars($student['barangay']); ?>">
                                <option value="">Select barangay</option>
                            </select>
                            <div id="barangay-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Loading barangays...
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label for="street_address" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-road text-gray-400 mr-1"></i>Street / Subdivision
                            </label>
                            <input type="text" id="street_address" name="street_address" 
                                   value="<?php echo htmlspecialchars($student['street_address']); ?>"
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="place_of_birth" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-baby text-green-600 mr-1"></i>Place of Birth
                            </label>
                            <input type="text" id="place_of_birth" name="place_of_birth" 
                                   value="<?php echo htmlspecialchars($student['place_of_birth']); ?>"
                                   placeholder="City, Province"
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Parent/Guardian Information -->
            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                    <div class="flex items-center space-x-3">
                        <div class="bg-purple-100 rounded-xl p-2">
                            <i class="fas fa-users text-purple-600"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900">Parent/Guardian Information</h3>
                    </div>
                </div>
                <div class="p-6 md:p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <div>
                            <label for="guardian_first_name" class="block text-sm font-medium text-gray-700 mb-2">Guardian First Name *</label>
                            <input type="text" id="guardian_first_name" name="guardian_first_name" required 
                                   value="<?php echo htmlspecialchars($student['guardian_first_name']); ?>"
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="guardian_middle_name" class="block text-sm font-medium text-gray-700 mb-2">Guardian Middle Name</label>
                            <input type="text" id="guardian_middle_name" name="guardian_middle_name" 
                                   value="<?php echo htmlspecialchars($student['guardian_middle_name']); ?>"
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="guardian_last_name" class="block text-sm font-medium text-gray-700 mb-2">Guardian Last Name *</label>
                            <input type="text" id="guardian_last_name" name="guardian_last_name" required 
                                   value="<?php echo htmlspecialchars($student['guardian_last_name']); ?>"
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                        <div>
                            <label for="guardian_extension" class="block text-sm font-medium text-gray-700 mb-2">Guardian Extension</label>
                            <input type="text" id="guardian_extension" name="guardian_extension" 
                                   placeholder="Jr., Sr., III, etc."
                                   value="<?php echo htmlspecialchars($student['guardian_extension']); ?>"
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="parent_contact" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-phone text-blue-500 mr-2"></i>Guardian Contact Number *
                            </label>
                            <div class="flex flex-col sm:flex-row gap-2">
                                <select id="parent_country_code" name="parent_country_code" 
                                        class="w-full sm:w-24 px-2 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 hover:border-gray-300 bg-gray-50 text-sm shadow-sm">
                                    <option value="">Code</option>
                                </select>
                                <input type="tel" id="parent_contact" name="parent_contact" required 
                                       class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 hover:border-gray-300 text-sm shadow-sm"
                                       placeholder="9123456789"
                                       pattern="[0-9]{10}"
                                       inputmode="numeric"
                                       value="<?php echo htmlspecialchars($student['parent_contact']); ?>">
                            </div>
                            <p class="text-xs text-gray-500 mt-2 flex items-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                Select country code and enter 10-digit phone number (e.g., 9123456789)
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Education Information -->
            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                    <div class="flex items-center space-x-3">
                        <div class="bg-indigo-100 rounded-xl p-2">
                            <i class="fas fa-graduation-cap text-indigo-600"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900">Education Information</h3>
                    </div>
                </div>
                <div class="p-6 md:p-8">
                    <div class="mb-6">
                        <label for="last_school" class="block text-sm font-medium text-gray-700 mb-2">Last School Attended (Full Name, no abbreviations) *</label>
                        <input type="text" id="last_school" name="last_school" required 
                               placeholder="e.g., University of the Philippines Diliman"
                               value="<?php echo htmlspecialchars($student['last_school']); ?>"
                               class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="school_province" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-map text-indigo-600 mr-1"></i>School Province *
                            </label>
                            <select id="school_province" name="school_province" required 
                                    class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm"
                                    data-selected="<?php echo htmlspecialchars($student['school_province']); ?>">
                                <option value="">Loading provinces...</option>
                            </select>
                            <div id="school_province-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Loading provinces...
                            </div>
                        </div>
                        <div>
                            <label for="school_city" class="block text-sm font-medium text-gray-700 mb-2">
                                <i class="fas fa-city text-indigo-600 mr-1"></i>School City/Municipality *
                            </label>
                            <select id="school_city" name="school_city" required 
                                    class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm"
                                    data-selected="<?php echo htmlspecialchars($student['school_city']); ?>">
                                <option value="">Select school city/municipality</option>
                            </select>
                            <div id="school_city-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                <i class="fas fa-spinner fa-spin mr-2"></i>Loading cities...
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Other Information -->
            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                <div class="px-6 py-5 border-b border-gray-200 bg-gradient-to-r from-gray-50 to-gray-100">
                    <div class="flex items-center space-x-3">
                        <div class="bg-orange-100 rounded-xl p-2">
                            <i class="fas fa-info-circle text-orange-600"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900">Other Information</h3>
                    </div>
                </div>
                <div class="p-6 md:p-8">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-700 mb-2">Email Address *</label>
                            <input type="email" id="email" name="email" required 
                                   value="<?php echo htmlspecialchars($student['email']); ?>"
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                        </div>
                        <div>
                            <label for="uli" class="block text-sm font-medium text-gray-700 mb-2">ULI (Unique Learner Identifier) *</label>
                            <input type="text" id="uli" name="uli" required 
                                   value="<?php echo htmlspecialchars($student['uli']); ?>"
                                   class="block w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 text-sm shadow-sm">
                        </div>
                    </div>
                    
                    <div>
                        <label for="profile_picture" class="block text-sm font-medium text-gray-700 mb-2">Profile Picture</label>
                        
                        <?php 
                        // Handle profile picture path resolution for admin edit view
                        $current_profile_url = '';
                        $file_exists = false;
                        
                        if (!empty($student['profile_picture'])) {
                            $stored_path = $student['profile_picture'];
                            
                            if (strpos($stored_path, '../') === 0) {
                                $current_profile_url = $stored_path;
                            } else {
                                $current_profile_url = '../../' . $stored_path;
                            }
                            
                            $file_exists = file_exists($current_profile_url);
                        }
                        ?>
                        
                        <div class="flex items-start space-x-4">
                            <div class="flex-shrink-0">
                                <?php if (!empty($student['profile_picture']) && $file_exists): ?>
                                    <img id="current-profile" src="<?php echo htmlspecialchars($current_profile_url); ?>" 
                                         class="profile-preview" alt="Current Profile Picture">
                                <?php else: ?>
                                    <div id="upload-placeholder" class="upload-placeholder">
                                        <i class="fas fa-camera text-2xl"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <img id="profile-preview" class="profile-preview" style="display: none;">
                            </div>
                            <div class="flex-1">
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/jpg,image/png"
                                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 transition-all duration-200">
                                <p class="mt-2 text-sm text-gray-500">Maximum file size: 2MB. Accepted formats: JPG, JPEG, PNG. Leave empty to keep current photo.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="bg-white shadow-xl rounded-2xl overflow-hidden border border-gray-100">
                <div class="p-6 md:p-8">
                    <div class="flex flex-col sm:flex-row gap-4 justify-center">
                        <button type="submit" class="inline-flex items-center justify-center px-8 py-4 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-all duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                            <i class="fas fa-save mr-2"></i>Update Student Information
                        </button>
                        <a href="view.php?id=<?php echo $student_id; ?>" class="inline-flex items-center justify-center px-8 py-4 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200 shadow-sm hover:shadow-md">
                            <i class="fas fa-times mr-2"></i>Cancel Changes
                        </a>
                        <a href="index.php" class="inline-flex items-center justify-center px-8 py-4 bg-gray-100 text-gray-700 text-sm font-semibold rounded-lg hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-gray-500 transition-all duration-200 shadow-sm hover:shadow-md">
                            <i class="fas fa-arrow-left mr-2"></i>Back to Students List
                        </a>
                    </div>
                </div>
            </div>
        </form>
        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        // Initialize page with API data
        document.addEventListener('DOMContentLoaded', async function() {
            // Load country codes for phone numbers and set Philippines as default
            await loadCountryCodes('country_code', '+63');
            await loadCountryCodes('parent_country_code', '+63');
            
            // Load provinces for address
            await loadProvinces('province');
            
            // Load provinces for school
            await loadProvinces('school_province');
            
            // Get selected values from data attributes
            const provinceSelect = document.getElementById('province');
            const citySelect = document.getElementById('city');
            const barangaySelect = document.getElementById('barangay');
            const schoolProvinceSelect = document.getElementById('school_province');
            const schoolCitySelect = document.getElementById('school_city');
            
            const selectedProvince = provinceSelect.dataset.selected;
            const selectedCity = citySelect.dataset.selected;
            const selectedBarangay = barangaySelect.dataset.selected;
            const selectedSchoolProvince = schoolProvinceSelect.dataset.selected;
            const selectedSchoolCity = schoolCitySelect.dataset.selected;
            
            // Set selected province and load cities
            if (selectedProvince) {
                provinceSelect.value = selectedProvince;
                const selectedOption = provinceSelect.options[provinceSelect.selectedIndex];
                const provinceCode = selectedOption.dataset.code;
                
                if (provinceCode) {
                    await loadCities(provinceCode, 'city', selectedCity);
                    
                    // After cities are loaded, load barangays
                    if (selectedCity) {
                        const cityOption = Array.from(citySelect.options).find(opt => opt.value === selectedCity);
                        if (cityOption) {
                            const cityCode = cityOption.dataset.code;
                            if (cityCode) {
                                await loadBarangays(cityCode, 'barangay', selectedBarangay);
                            }
                        }
                    }
                }
            }
            
            // Set selected school province and load school cities
            if (selectedSchoolProvince) {
                schoolProvinceSelect.value = selectedSchoolProvince;
                const selectedOption = schoolProvinceSelect.options[schoolProvinceSelect.selectedIndex];
                const provinceCode = selectedOption.dataset.code;
                
                if (provinceCode) {
                    await loadCities(provinceCode, 'school_city', selectedSchoolCity);
                }
            }
            
            // Scroll to errors if present
            const errorDiv = document.querySelector('.bg-red-50');
            if (errorDiv) {
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
        
        // Province change handler for address
        document.getElementById('province').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const provinceCode = selectedOption.dataset.code;
            
            // Clear dependent dropdowns
            document.getElementById('city').innerHTML = '<option value="">Select city/municipality</option>';
            document.getElementById('barangay').innerHTML = '<option value="">Select barangay</option>';
            
            if (provinceCode) {
                loadCities(provinceCode, 'city');
            }
        });
        
        // City change handler for address
        document.getElementById('city').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const cityCode = selectedOption.dataset.code;
            
            // Clear barangay dropdown
            document.getElementById('barangay').innerHTML = '<option value="">Select barangay</option>';
            
            if (cityCode) {
                loadBarangays(cityCode, 'barangay');
            }
        });
        
        // School province change handler
        document.getElementById('school_province').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const provinceCode = selectedOption.dataset.code;
            
            // Clear school city dropdown
            document.getElementById('school_city').innerHTML = '<option value="">Select school city/municipality</option>';
            
            if (provinceCode) {
                loadCities(provinceCode, 'school_city');
            }
        });

        // Age calculation on birthday change
        document.getElementById('birthday').addEventListener('change', function() {
            const birthday = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - birthday.getFullYear();
            const monthDiff = today.getMonth() - birthday.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthday.getDate())) {
                age--;
            }
            
            document.getElementById('age').value = age;
        });

        // Profile picture preview
        document.getElementById('profile_picture').addEventListener('change', function() {
            const file = this.files[0];
            const preview = document.getElementById('profile-preview');
            const current = document.getElementById('current-profile');
            const placeholder = document.getElementById('upload-placeholder');
            
            if (file) {
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG, JPEG, or PNG)');
                    this.value = '';
                    return;
                }
                
                // Validate file size (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('File size must be less than 2MB');
                    this.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                    
                    if (current) current.style.display = 'none';
                    if (placeholder) placeholder.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });

        // Form submission with loading state
        document.getElementById('edit-form').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            
            // Add loading state
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Updating...';
            submitBtn.disabled = true;
            this.classList.add('form-loading');
            
            // If there's an error, restore the button (this would be handled by PHP validation)
            setTimeout(() => {
                if (this.classList.contains('form-loading')) {
                    submitBtn.innerHTML = originalText;
                    submitBtn.disabled = false;
                    this.classList.remove('form-loading');
                }
            }, 10000); // 10 second timeout
        });

        // Enhanced form validation feedback
        const inputs = document.querySelectorAll('input[required], select[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                if (this.value.trim() === '') {
                    this.classList.add('border-red-300', 'bg-red-50');
                    this.classList.remove('border-gray-300');
                } else {
                    this.classList.remove('border-red-300', 'bg-red-50');
                    this.classList.add('border-gray-300');
                }
            });
            
            input.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    this.classList.remove('border-red-300', 'bg-red-50');
                    this.classList.add('border-gray-300');
                }
            });
        });
    </script>
</body>
</html>
<?php
session_start();
require_once '../config/database.php';

$errors = [];
$success_message = '';

// Generate verification code for display
if (!isset($_SESSION['verification_code'])) {
    $_SESSION['verification_code'] = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Generate unique student ID
    $student_id = 'STU' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
    
    // Generate 4-digit verification code
    $verification_code = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
    
    // Validate required fields
    $required_fields = [
        'first_name', 'last_name', 'birthday', 'sex', 'civil_status',
        'contact_number', 'province', 'city', 'barangay', 'place_of_birth',
        'guardian_last_name', 'guardian_first_name', 'parent_contact', 'email', 'uli', 'last_school',
        'school_province', 'school_city', 'verification_input'
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
 
    // Validate date of birth (no future dates)
    if (!empty($_POST['birthday'])) {
        $birthday = new DateTime($_POST['birthday']);
        $today = new DateTime();
        if ($birthday > $today) {
            $errors[] = 'Date of birth cannot be in the future';
        }
    }
    
    // Validate verification code
    if (!empty($_POST['verification_input']) && !empty($_SESSION['verification_code'])) {
        if ($_POST['verification_input'] !== $_SESSION['verification_code']) {
            $errors[] = 'Verification code is incorrect';
        }
    } else {
        $errors[] = 'Verification code is required';
    }
    
    // Calculate age
    $age = 0;
    if (!empty($_POST['birthday'])) {
        $birthday = new DateTime($_POST['birthday']);
        $today = new DateTime();
        $age = $today->diff($birthday)->y;
    }
    
    // Handle file upload
    $profile_picture_path = '';
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = 'Profile picture must be JPG, JPEG, or PNG';
        } elseif ($file_size > 2 * 1024 * 1024) {
            $errors[] = 'Profile picture must be less than 2MB';
        } else {
            $upload_dir = '../uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $file_extension;
            $profile_picture_path = $upload_dir . $filename;
            
            if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $profile_picture_path)) {
                $errors[] = 'Failed to upload profile picture';
                $profile_picture_path = '';
            }
        }
    }
    
    // If no errors, save to database
    if (empty($errors)) {
        try {
            $database = new Database();
            $conn = $database->getConnection();
            
            // Check for unique student_id
            $stmt = $conn->prepare("SELECT id FROM students WHERE student_id = :student_id");
            $stmt->bindParam(':student_id', $student_id);
            $stmt->execute();
            
            // Generate new student_id if current one exists
            while ($stmt->fetch()) {
                $student_id = 'STU' . date('Y') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
                $stmt = $conn->prepare("SELECT id FROM students WHERE student_id = :student_id");
                $stmt->bindParam(':student_id', $student_id);
                $stmt->execute();
            } 
           
            $sql = "INSERT INTO students (
                student_id, first_name, middle_name, last_name, extension_name, birthday, age, sex, civil_status,
                contact_number, province, city, barangay, street_address, place_of_birth,
                guardian_last_name, guardian_first_name, guardian_middle_name, guardian_extension, parent_contact, 
                email, profile_picture, uli, last_school, school_province, school_city, 
                verification_code, is_verified, status
            ) VALUES (
                :student_id, :first_name, :middle_name, :last_name, :extension_name, :birthday, :age, :sex, :civil_status,
                :contact_number, :province, :city, :barangay, :street_address, :place_of_birth,
                :guardian_last_name, :guardian_first_name, :guardian_middle_name, :guardian_extension, :parent_contact,
                :email, :profile_picture, :uli, :last_school, :school_province, :school_city,
                :verification_code, TRUE, 'pending'
            )";
            
            $stmt = $conn->prepare($sql);
            
            $stmt->bindParam(':student_id', $student_id);
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
            $stmt->bindParam(':guardian_last_name', $_POST['guardian_last_name']);
            $stmt->bindParam(':guardian_first_name', $_POST['guardian_first_name']);
            $stmt->bindParam(':guardian_middle_name', $_POST['guardian_middle_name']);
            $stmt->bindParam(':guardian_extension', $_POST['guardian_extension']);
            $stmt->bindParam(':parent_contact', $_POST['parent_contact']);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':profile_picture', $profile_picture_path);
            $stmt->bindParam(':uli', $_POST['uli']);
            $stmt->bindParam(':last_school', $_POST['last_school']);
            $stmt->bindParam(':school_province', $_POST['school_province']);
            $stmt->bindParam(':school_city', $_POST['school_city']);
            $stmt->bindParam(':verification_code', $_SESSION['verification_code']);
            
            if ($stmt->execute()) {
                $success_message = 'Registration submitted successfully! Your Student ID is: ' . $student_id . '. Verification code used: ' . $_SESSION['verification_code'] . '. Your registration is pending admin approval.';
                // Clear form data and verification code
                $_POST = [];
                unset($_SESSION['verification_code']);
            } else {
                $errors[] = 'Registration failed. Please try again.';
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Registration - Professional Form</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#fef2f2',
                            100: '#fee2e2',
                            500: '#800000',
                            600: '#660000',
                            700: '#5c0000',
                            800: '#4a0000',
                            900: '#3d0000'
                        },
                        secondary: {
                            500: '#000080',
                            600: '#000066',
                            700: '#000055'
                        }
                    },
                    animation: {
                        'fade-in': 'fadeIn 0.5s ease-in-out',
                        'slide-up': 'slideUp 0.3s ease-out'
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes slideUp {
            from { transform: translateY(20px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .form-step {
            transition: all 0.3s ease-in-out;
        }
        .loading-spinner {
            border: 2px solid #f3f3f3;
            border-top: 2px solid #800000;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 via-white to-gray-100 min-h-screen">
    <!-- Header with improved design -->
    <header class="bg-gradient-to-r from-primary-600 via-primary-500 to-primary-700 shadow-2xl relative overflow-hidden">
        <div class="absolute inset-0 bg-black opacity-10"></div>
        <div class="absolute inset-0" style="background-image: url('data:image/svg+xml,<svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 100 100\"><defs><pattern id=\"grain\" width=\"100\" height=\"100\" patternUnits=\"userSpaceOnUse\"><circle cx=\"25\" cy=\"25\" r=\"1\" fill=\"white\" opacity=\"0.1\"/><circle cx=\"75\" cy=\"75\" r=\"1\" fill=\"white\" opacity=\"0.1\"/></pattern></defs><rect width=\"100\" height=\"100\" fill=\"url(%23grain)\"/></svg></div>
        <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between py-8">
                <div class="text-center flex-1">
                    <div class="flex items-center justify-center mb-4">
                        <div class="bg-white/20 p-3 rounded-full mr-4">
                            <i class="fas fa-graduation-cap text-2xl text-white"></i>
                        </div>
                        <div>
                            <h1 class="text-3xl md:text-4xl font-bold text-white tracking-tight">Student Registration</h1>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>    
    <!-- Main Content -->
    <main class="max-w-5xl mx-auto py-8 px-4 sm:px-6 lg:px-8">
        <!-- Progress Indicator -->
        <div class="mb-8">
            <div class="flex items-center justify-center">
                <div class="flex items-center space-x-4">
                    <div class="flex items-center">
                        <div class="bg-primary-500 text-white rounded-full w-8 h-8 flex items-center justify-center text-sm font-semibold">1</div>
                        <span class="ml-2 text-sm font-medium text-gray-700">Personal</span>
                    </div>
                    <div class="w-16 h-1 bg-gray-200 rounded"></div>
                    <div class="flex items-center">
                        <div class="bg-gray-300 text-gray-600 rounded-full w-8 h-8 flex items-center justify-center text-sm font-semibold">2</div>
                        <span class="ml-2 text-sm font-medium text-gray-500">Address</span>
                    </div>
                    <div class="w-16 h-1 bg-gray-200 rounded"></div>
                    <div class="flex items-center">
                        <div class="bg-gray-300 text-gray-600 rounded-full w-8 h-8 flex items-center justify-center text-sm font-semibold">3</div>
                        <span class="ml-2 text-sm font-medium text-gray-500">Education</span>
                    </div>
                    <div class="w-16 h-1 bg-gray-200 rounded"></div>
                    <div class="flex items-center">
                        <div class="bg-gray-300 text-gray-600 rounded-full w-8 h-8 flex items-center justify-center text-sm font-semibold">4</div>
                        <span class="ml-2 text-sm font-medium text-gray-500">Review</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Alert Messages -->
        <?php if (!empty($errors)): ?>
            <div class="mb-6 bg-red-50 border-l-4 border-red-400 p-4 rounded-r-lg animate-slide-up">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-exclamation-triangle text-red-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-red-800">Please correct the following errors:</h3>
                        <div class="mt-2 text-sm text-red-700">
                            <ul class="list-disc list-inside space-y-1">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="mb-6 bg-green-50 border-l-4 border-green-400 p-4 rounded-r-lg animate-slide-up">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-400"></i>
                    </div>
                    <div class="ml-3">
                        <h3 class="text-sm font-medium text-green-800">Registration Successful!</h3>
                        <div class="mt-2 text-sm text-green-700">
                            <?php echo htmlspecialchars($success_message); ?>
                        </div>
                        <div class="mt-4">
                            <a href="../index.html" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-green-700 bg-green-100 hover:bg-green-200 transition duration-200">
                                <i class="fas fa-home mr-2"></i>
                                Return to Home
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>        <!--
 Registration Form -->
        <div class="bg-white shadow-2xl rounded-2xl overflow-hidden border border-gray-100">
            <!-- Form Header -->
            <div class="bg-gradient-to-r from-primary-500 to-primary-700 px-8 py-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="bg-white/20 p-2 rounded-lg mr-4">
                            <i class="fas fa-user-plus text-white text-lg"></i>
                        </div>
                        <div>
                            <h2 class="text-2xl font-bold text-white">Registration Form</h2>
                            <p class="text-primary-100 text-sm mt-1">Please provide accurate information for your registration</p>
                        </div>
                    </div>
                    <div id="savedDataIndicator" class="hidden">
                        <div class="bg-white/20 px-4 py-2 rounded-lg">
                            <div class="flex items-center text-white text-sm">
                                <i class="fas fa-save mr-2"></i>
                                <span>Draft Saved</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="p-8" id="registrationForm">
                <!-- Personal Information Section -->
                <div class="mb-10">
                    <div class="flex items-center mb-6">
                        <div class="bg-primary-500 text-white rounded-full w-10 h-10 flex items-center justify-center text-sm font-bold mr-4">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Personal Information</h3>
                            <p class="text-gray-600 text-sm">Tell us about yourself</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                        <div class="form-group">
                            <label for="first_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-tag text-primary-500 mr-2"></i>First Name *
                            </label>
                            <input type="text" id="first_name" name="first_name" required 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                                   placeholder="Enter your first name"
                                   value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="middle_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-tag text-gray-400 mr-2"></i>Middle Name
                            </label>
                            <input type="text" id="middle_name" name="middle_name" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                                   placeholder="Enter your middle name"
                                   value="<?php echo htmlspecialchars($_POST['middle_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="last_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-tag text-primary-500 mr-2"></i>Last Name *
                            </label>
                            <input type="text" id="last_name" name="last_name" required 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                                   placeholder="Enter your last name"
                                   value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="extension_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-tag text-gray-400 mr-2"></i>Extension Name
                            </label>
                            <select id="extension_name" name="extension_name" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300">
                                <option value="">Select extension (if any)</option>
                                <option value="Jr." <?php echo (($_POST['extension_name'] ?? '') === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                <option value="Sr." <?php echo (($_POST['extension_name'] ?? '') === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                <option value="II" <?php echo (($_POST['extension_name'] ?? '') === 'II') ? 'selected' : ''; ?>>II</option>
                                <option value="III" <?php echo (($_POST['extension_name'] ?? '') === 'III') ? 'selected' : ''; ?>>III</option>
                                <option value="IV" <?php echo (($_POST['extension_name'] ?? '') === 'IV') ? 'selected' : ''; ?>>IV</option>
                                <option value="V" <?php echo (($_POST['extension_name'] ?? '') === 'V') ? 'selected' : ''; ?>>V</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="form-group">
                            <label for="birthday" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-calendar-alt text-primary-500 mr-2"></i>Date of Birth *
                            </label>
                            <input type="date" id="birthday" name="birthday" required 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                                   value="<?php echo htmlspecialchars($_POST['birthday'] ?? ''); ?>">
                            <p class="text-xs text-gray-500 mt-2 flex items-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                Please enter your actual date of birth (future dates not allowed)
                            </p>
                        </div>
                        <div class="form-group">
                            <label for="age" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-hourglass-half text-gray-400 mr-2"></i>Age
                            </label>
                            <input type="number" id="age" name="age" readonly 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg bg-gray-50 text-gray-600"
                                   placeholder="Auto-calculated">
                        </div>
                    </div>    
                
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="form-group">
                            <label for="sex" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-venus-mars text-primary-500 mr-2"></i>Sex *
                            </label>
                            <select id="sex" name="sex" required 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300">
                                <option value="">Select your sex</option>
                                <option value="Male" <?php echo (($_POST['sex'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                <option value="Female" <?php echo (($_POST['sex'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                <option value="Other" <?php echo (($_POST['sex'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="civil_status" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-heart text-primary-500 mr-2"></i>Civil Status *
                            </label>
                            <select id="civil_status" name="civil_status" required 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300">
                                <option value="">Select civil status</option>
                                <option value="Single" <?php echo (($_POST['civil_status'] ?? '') === 'Single') ? 'selected' : ''; ?>>Single</option>
                                <option value="Married" <?php echo (($_POST['civil_status'] ?? '') === 'Married') ? 'selected' : ''; ?>>Married</option>
                                <option value="Divorced" <?php echo (($_POST['civil_status'] ?? '') === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                                <option value="Widowed" <?php echo (($_POST['civil_status'] ?? '') === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="contact_number" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-phone text-primary-500 mr-2"></i>Contact Number *
                        </label>
                        <input type="tel" id="contact_number" name="contact_number" required 
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                               placeholder="e.g., +639123456789 or 09123456789"
                               value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>">
                        <p class="text-xs text-gray-500 mt-2 flex items-center">
                            <i class="fas fa-info-circle mr-1"></i>
                            Format: +639XXXXXXXXX or 09XXXXXXXXX
                        </p>
                    </div>
                </div>

                <!-- Address Information Section -->
                <div class="mb-10 border-t border-gray-200 pt-8">
                    <div class="flex items-center mb-6">
                        <div class="bg-primary-500 text-white rounded-full w-10 h-10 flex items-center justify-center text-sm font-bold mr-4">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Address Information</h3>
                            <p class="text-gray-600 text-sm">Where do you currently live?</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="form-group">
                            <label for="province" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map text-primary-500 mr-2"></i>Province *
                            </label>
                            <select id="province" name="province" required 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300">
                                <option value="">Loading provinces...</option>
                            </select>
                            <div id="province-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                <div class="loading-spinner mr-2"></div>
                                Loading provinces...
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="city" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-city text-primary-500 mr-2"></i>City/Municipality *
                            </label>
                            <select id="city" name="city" required 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300">
                                <option value="">Select city/municipality</option>
                            </select>
                            <div id="city-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                <div class="loading-spinner mr-2"></div>
                                Loading cities...
                            </div>
                        </div>
                    </div> 
                   
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="form-group">
                            <label for="barangay" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-home text-primary-500 mr-2"></i>Barangay *
                            </label>
                            <select id="barangay" name="barangay" required 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300">
                                <option value="">Select barangay</option>
                            </select>
                            <div id="barangay-loading" class="hidden mt-2 flex items-center text-sm text-gray-500">
                                <div class="loading-spinner mr-2"></div>
                                Loading barangays...
                            </div>
                        </div>
                        <div class="form-group">
                            <label for="street_address" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-road text-gray-400 mr-2"></i>Street / Subdivision
                            </label>
                            <input type="text" id="street_address" name="street_address" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                                   placeholder="Enter street address or subdivision"
                                   value="<?php echo htmlspecialchars($_POST['street_address'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="place_of_birth" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-baby text-primary-500 mr-2"></i>Place of Birth *
                        </label>
                        <input type="text" id="place_of_birth" name="place_of_birth" required 
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                               placeholder="Enter your place of birth"
                               value="<?php echo htmlspecialchars($_POST['place_of_birth'] ?? ''); ?>">
                    </div>
                </div>

                <!-- Parent/Guardian Information Section -->
                <div class="mb-10 border-t border-gray-200 pt-8">
                    <div class="flex items-center mb-6">
                        <div class="bg-primary-500 text-white rounded-full w-10 h-10 flex items-center justify-center text-sm font-bold mr-4">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Parent/Guardian Information</h3>
                            <p class="text-gray-600 text-sm">Emergency contact information</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="form-group">
                            <label for="guardian_last_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-friends text-primary-500 mr-2"></i>Last Name *
                            </label>
                            <input type="text" id="guardian_last_name" name="guardian_last_name" required 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                                   placeholder="Guardian's last name"
                                   value="<?php echo htmlspecialchars($_POST['guardian_last_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="guardian_first_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-friends text-primary-500 mr-2"></i>First Name *
                            </label>
                            <input type="text" id="guardian_first_name" name="guardian_first_name" required 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                                   placeholder="Guardian's first name"
                                   value="<?php echo htmlspecialchars($_POST['guardian_first_name'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label for="guardian_middle_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-friends text-gray-400 mr-2"></i>Middle Name
                            </label>
                            <input type="text" id="guardian_middle_name" name="guardian_middle_name" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                                   placeholder="Guardian's middle name"
                                   value="<?php echo htmlspecialchars($_POST['guardian_middle_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="guardian_extension" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-friends text-gray-400 mr-2"></i>Extension
                            </label>
                            <select id="guardian_extension" name="guardian_extension" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300">
                                <option value="">Select extension (if any)</option>
                                <option value="Jr." <?php echo (($_POST['guardian_extension'] ?? '') === 'Jr.') ? 'selected' : ''; ?>>Jr.</option>
                                <option value="Sr." <?php echo (($_POST['guardian_extension'] ?? '') === 'Sr.') ? 'selected' : ''; ?>>Sr.</option>
                                <option value="II" <?php echo (($_POST['guardian_extension'] ?? '') === 'II') ? 'selected' : ''; ?>>II</option>
                                <option value="III" <?php echo (($_POST['guardian_extension'] ?? '') === 'III') ? 'selected' : ''; ?>>III</option>
                                <option value="IV" <?php echo (($_POST['guardian_extension'] ?? '') === 'IV') ? 'selected' : ''; ?>>IV</option>
                                <option value="V" <?php echo (($_POST['guardian_extension'] ?? '') === 'V') ? 'selected' : ''; ?>>V</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="parent_contact" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-phone text-primary-500 mr-2"></i>Contact Number *
                        </label>
                        <input type="tel" id="parent_contact" name="parent_contact" required 
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                               placeholder="e.g., +639123456789 or 09123456789"
                               value="<?php echo htmlspecialchars($_POST['parent_contact'] ?? ''); ?>">
                    </div>
                </div>   
             <!-- Education Information Section -->
                <div class="mb-10 border-t border-gray-200 pt-8">
                    <div class="flex items-center mb-6">
                        <div class="bg-primary-500 text-white rounded-full w-10 h-10 flex items-center justify-center text-sm font-bold mr-4">
                            <i class="fas fa-school"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Education Information</h3>
                            <p class="text-gray-600 text-sm">Your academic background</p>
                        </div>
                    </div>
                    
                    <div class="form-group mb-6">
                        <label for="last_school" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-university text-primary-500 mr-2"></i>Last School Attended *
                        </label>
                        <input type="text" id="last_school" name="last_school" required 
                               class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                               placeholder="Full school name (no abbreviations)"
                               value="<?php echo htmlspecialchars($_POST['last_school'] ?? ''); ?>">
                        <p class="text-xs text-gray-500 mt-2 flex items-center">
                            <i class="fas fa-info-circle mr-1"></i>
                            Enter the complete school name without abbreviations
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label for="school_province" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-map text-primary-500 mr-2"></i>School Province *
                            </label>
                            <select id="school_province" name="school_province" required 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300">
                                <option value="">Select school province</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="school_city" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-city text-primary-500 mr-2"></i>School City/Municipality *
                            </label>
                            <select id="school_city" name="school_city" required 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300">
                                <option value="">Select school city/municipality</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Additional Information Section -->
                <div class="mb-10 border-t border-gray-200 pt-8">
                    <div class="flex items-center mb-6">
                        <div class="bg-primary-500 text-white rounded-full w-10 h-10 flex items-center justify-center text-sm font-bold mr-4">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Additional Information</h3>
                            <p class="text-gray-600 text-sm">Contact details and identification</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="form-group">
                            <label for="email" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-envelope text-primary-500 mr-2"></i>Email Address *
                            </label>
                            <input type="email" id="email" name="email" required 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                                   placeholder="your.email@example.com"
                                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label for="uli" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-id-card text-primary-500 mr-2"></i>ULI (Unique Learner Identifier) *
                            </label>
                            <input type="text" id="uli" name="uli" required 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                                   placeholder="Enter your ULI"
                                   value="<?php echo htmlspecialchars($_POST['uli'] ?? ''); ?>">
                        </div>
                    </div> 
                   
                    <!-- Profile Picture Upload -->
                    <div class="form-group mb-8">
                        <label class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-camera text-primary-500 mr-2"></i>Profile Picture
                        </label>
                        <div class="flex items-center space-x-6">
                            <div class="shrink-0">
                                <img id="profile-preview" class="h-20 w-20 object-cover rounded-full border-4 border-gray-200 shadow-lg" 
                                     src="data:image/svg+xml,%3csvg width='100' height='100' xmlns='http://www.w3.org/2000/svg'%3e%3crect width='100' height='100' fill='%23f3f4f6'/%3e%3ctext x='50%25' y='50%25' font-size='14' text-anchor='middle' alignment-baseline='middle' fill='%236b7280'%3ePhoto%3c/text%3e%3c/svg%3e" 
                                     alt="Profile preview">
                            </div>
                            <div class="flex-1">
                                <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/jpg,image/png"
                                       class="block w-full text-sm text-gray-500 file:mr-4 file:py-3 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary-50 file:text-primary-700 hover:file:bg-primary-100 transition duration-200">
                                <p class="text-xs text-gray-500 mt-2 flex items-center">
                                    <i class="fas fa-info-circle mr-1"></i>
                                    Maximum file size: 2MB. Accepted formats: JPG, JPEG, PNG
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Verification Code Section -->
                <div class="mb-10 border-t border-gray-200 pt-8">
                    <div class="flex items-center mb-6">
                        <div class="bg-primary-500 text-white rounded-full w-10 h-10 flex items-center justify-center text-sm font-bold mr-4">
                            <i class="fas fa-key"></i>
                        </div>
                        <div>
                            <h3 class="text-xl font-semibold text-gray-900">Verification Code</h3>
                            <p class="text-gray-600 text-sm">Enter the 4-digit code to verify your registration</p>
                        </div>
                    </div>
                    
                    <div class="bg-gradient-to-r from-blue-50 to-blue-100 rounded-xl p-6 border border-blue-200">
                        <div class="text-center mb-4">
                            <div class="inline-flex items-center justify-center w-16 h-16 bg-white rounded-full shadow-lg mb-4">
                                <i class="fas fa-key text-blue-500 text-xl"></i>
                            </div>
                            <div class="text-3xl font-bold text-blue-700 mb-2 font-mono tracking-widest">
                                <?php echo $_SESSION['verification_code'] ?? ''; ?>
                            </div>
                            <p class="text-sm text-blue-600">Enter this code below to verify</p>
                        </div>
                        <div class="max-w-xs mx-auto">
                            <input type="text" id="verification_input" name="verification_input" required 
                                   maxlength="4" pattern="[0-9]{4}"
                                   class="w-full px-4 py-3 border-2 border-blue-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition duration-200 text-center text-lg font-semibold font-mono tracking-widest"
                                   placeholder="0000">
                        </div>
                    </div>
                </div>

                <!-- Submit Section -->
                <div class="border-t border-gray-200 pt-8">
                    <div class="flex justify-center">
                        <button type="submit" 
                                class="inline-flex items-center px-8 py-3 bg-primary-500 text-white font-bold rounded-lg hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
                            <i class="fas fa-paper-plane mr-2"></i>
                            Submit Registration
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </main>
    
    <!-- Confirmation Modal -->
    <div id="confirmationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50 hidden">
        <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-12 w-12 rounded-full bg-yellow-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-xl"></i>
                </div>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Confirm Registration</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500 mb-4">
                        Are you sure all the information you provided is correct? Once submitted, your registration will be sent for admin approval.
                    </p>
                    <div class="bg-gray-50 rounded-lg p-3 mb-4">
                        <p class="text-xs text-gray-600 flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            Please double-check your details before confirming as changes may require resubmission.
                        </p>
                    </div>
                </div>
                <div class="flex items-center justify-center space-x-4 pt-4">
                    <button id="cancelSubmit" type="button" 
                            class="inline-flex items-center px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-200">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button id="confirmSubmit" type="button" 
                            class="inline-flex items-center px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gradient-to-r from-primary-500 to-primary-700 hover:from-primary-600 hover:to-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-200">
                        <i class="fas fa-check mr-2"></i>
                        Yes, Submit
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!--
 JavaScript for Enhanced Functionality -->
    <script>
        // Philippine Address API Configuration
        const PSGC_API_BASE = 'https://psgc.gitlab.io/api';
        
        // Utility functions
        function showLoading(elementId) {
            const loadingDiv = document.getElementById(elementId + '-loading');
            if (loadingDiv) {
                loadingDiv.classList.remove('hidden');
            }
        }
        
        function hideLoading(elementId) {
            const loadingDiv = document.getElementById(elementId + '-loading');
            if (loadingDiv) {
                loadingDiv.classList.add('hidden');
            }
        }
        
        function populateSelect(selectId, options, placeholder = 'Select option') {
            const select = document.getElementById(selectId);
            select.innerHTML = `<option value="">${placeholder}</option>`;
            
            options.forEach(option => {
                const optionElement = document.createElement('option');
                optionElement.value = option.name;
                optionElement.textContent = option.name;
                optionElement.dataset.code = option.code;
                select.appendChild(optionElement);
            });
        }
        
        // Load provinces
        async function loadProvinces(selectId) {
            try {
                showLoading(selectId);
                const response = await fetch(`${PSGC_API_BASE}/provinces/`);
                const provinces = await response.json();
                
                populateSelect(selectId, provinces, 'Select province');
                hideLoading(selectId);
            } catch (error) {
                console.error('Error loading provinces:', error);
                const select = document.getElementById(selectId);
                select.innerHTML = '<option value="">Error loading provinces</option>';
                hideLoading(selectId);
            }
        }
        
        // Load cities/municipalities
        async function loadCities(provinceCode, selectId) {
            try {
                showLoading(selectId);
                const response = await fetch(`${PSGC_API_BASE}/provinces/${provinceCode}/cities-municipalities/`);
                const cities = await response.json();
                
                populateSelect(selectId, cities, 'Select city/municipality');
                hideLoading(selectId);
            } catch (error) {
                console.error('Error loading cities:', error);
                const select = document.getElementById(selectId);
                select.innerHTML = '<option value="">Error loading cities</option>';
                hideLoading(selectId);
            }
        }
        
        // Load barangays
        async function loadBarangays(cityCode, selectId) {
            try {
                showLoading(selectId);
                const response = await fetch(`${PSGC_API_BASE}/cities-municipalities/${cityCode}/barangays/`);
                const barangays = await response.json();
                
                populateSelect(selectId, barangays, 'Select barangay');
                hideLoading(selectId);
            } catch (error) {
                console.error('Error loading barangays:', error);
                const select = document.getElementById(selectId);
                select.innerHTML = '<option value="">Error loading barangays</option>';
                hideLoading(selectId);
            }
        }
        
        // Age calculation with validation
        function calculateAge(birthday) {
            const today = new Date();
            const birthDate = new Date(birthday);
            
            // Check if birthday is in the future
            if (birthDate > today) {
                return -1; // Invalid date
            }
            
            let age = today.getFullYear() - birthDate.getFullYear();
            const monthDiff = today.getMonth() - birthDate.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                age--;
            }
            
            return age;
        }
        
        // Validate date of birth
        function validateDateOfBirth(dateInput) {
            const selectedDate = new Date(dateInput.value);
            const today = new Date();
            const ageField = document.getElementById('age');
            
            // Clear previous validation styles
            dateInput.classList.remove('border-red-500', 'ring-red-500');
            
            if (selectedDate > today) {
                // Future date selected
                dateInput.classList.add('border-red-500', 'ring-red-500');
                ageField.value = '';
                
                // Show error message
                let errorMsg = dateInput.parentNode.querySelector('.date-error');
                if (!errorMsg) {
                    errorMsg = document.createElement('p');
                    errorMsg.className = 'date-error text-xs text-red-500 mt-2 flex items-center';
                    errorMsg.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i>Date of birth cannot be in the future';
                    dateInput.parentNode.appendChild(errorMsg);
                }
                return false;
            } else {
                // Valid date
                dateInput.classList.add('border-green-500');
                
                // Remove error message if exists
                const errorMsg = dateInput.parentNode.querySelector('.date-error');
                if (errorMsg) {
                    errorMsg.remove();
                }
                
                // Calculate and set age
                const age = calculateAge(dateInput.value);
                if (age >= 0) {
                    ageField.value = age;
                    ageField.classList.add('border-green-500');
                }
                return true;
            }
        }
        
        // Profile picture preview
        function previewProfilePicture(input) {
            const file = input.files[0];
            const preview = document.getElementById('profile-preview');
            
            if (file) {
                // Validate file type
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Please select a valid image file (JPG, JPEG, or PNG)');
                    input.value = '';
                    return;
                }
                
                // Validate file size (2MB)
                if (file.size > 2 * 1024 * 1024) {
                    alert('File size must be less than 2MB');
                    input.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.classList.add('ring-4', 'ring-primary-200');
                };
                reader.readAsDataURL(file);
            }
        }        

        // Form validation
        function validateForm() {
            let isValid = true;
            const requiredFields = document.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('border-red-500', 'ring-red-500');
                    field.classList.remove('border-gray-200', 'border-green-500');
                    isValid = false;
                } else {
                    field.classList.remove('border-red-500', 'ring-red-500');
                    field.classList.add('border-gray-200');
                }
            });
            
            // Special validation for date of birth
            const birthdayField = document.getElementById('birthday');
            if (birthdayField && birthdayField.value) {
                if (!validateDateOfBirth(birthdayField)) {
                    isValid = false;
                }
            }
            
            return isValid;
        }
        
        // Form data persistence functions
        function saveFormData() {
            const formData = {};
            const form = document.getElementById('registrationForm');
            const formElements = form.querySelectorAll('input, select, textarea');
            
            formElements.forEach(element => {
                if (element.type === 'file') {
                    // Don't save file inputs
                    return;
                }
                formData[element.name] = element.value;
            });
            
            localStorage.setItem('studentRegistrationData', JSON.stringify(formData));
        }
        
        function loadFormData() {
            const savedData = localStorage.getItem('studentRegistrationData');
            if (savedData) {
                try {
                    const formData = JSON.parse(savedData);
                    let hasData = false;
                    
                    Object.keys(formData).forEach(key => {
                        const element = document.querySelector(`[name="${key}"]`);
                        if (element && formData[key]) {
                            element.value = formData[key];
                            hasData = true;
                            
                            // Trigger change event for calculated fields
                            if (key === 'birthday') {
                                element.dispatchEvent(new Event('change'));
                            }
                        }
                    });
                    
                    // Show saved data indicator if there's data
                    if (hasData) {
                        const indicator = document.getElementById('savedDataIndicator');
                        if (indicator) {
                            indicator.classList.remove('hidden');
                            
                            // Show a notification
                            showNotification('Previous registration data loaded', 'success');
                        }
                    }
                } catch (error) {
                    console.error('Error loading saved form data:', error);
                }
            }
        }
        
        function clearFormData() {
            localStorage.removeItem('studentRegistrationData');
            
            // Hide saved data indicator
            const indicator = document.getElementById('savedDataIndicator');
            if (indicator) {
                indicator.classList.add('hidden');
            }
        }
        
        function showNotification(message, type = 'info') {
            const notification = document.createElement('div');
            const bgColor = type === 'success' ? 'bg-green-500' : type === 'error' ? 'bg-red-500' : 'bg-blue-500';
            const icon = type === 'success' ? 'fa-check' : type === 'error' ? 'fa-times' : 'fa-info';
            
            notification.className = `fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50 animate-slide-up`;
            notification.innerHTML = `<i class="fas ${icon} mr-2"></i>${message}`;
            
            document.body.appendChild(notification);
            
            // Remove notification after 4 seconds
            setTimeout(() => {
                notification.remove();
            }, 4000);
        }
        
        function setupFormPersistence() {
            const form = document.getElementById('registrationForm');
            const formElements = form.querySelectorAll('input, select, textarea');
            
            formElements.forEach(element => {
                if (element.type !== 'file') {
                    element.addEventListener('input', saveFormData);
                    element.addEventListener('change', saveFormData);
                }
            });
        }
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            // Load saved form data from localStorage
            loadFormData();
            
            // Save form data on input changes
            setupFormPersistence();
            
            // Load provinces for address and school
            loadProvinces('province');
            loadProvinces('school_province');
            
            // Birthday change event with validation
            const birthdayField = document.getElementById('birthday');
            if (birthdayField) {
                // Set max date to today
                const today = new Date().toISOString().split('T')[0];
                birthdayField.setAttribute('max', today);
                
                birthdayField.addEventListener('change', function() {
                    validateDateOfBirth(this);
                });
                
                birthdayField.addEventListener('blur', function() {
                    validateDateOfBirth(this);
                });
            }
            
            // Profile picture preview
            const profileInput = document.getElementById('profile_picture');
            if (profileInput) {
                profileInput.addEventListener('change', function() {
                    previewProfilePicture(this);
                });
            }
            
            // Province change handlers
            const provinceSelect = document.getElementById('province');
            if (provinceSelect) {
                provinceSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const provinceCode = selectedOption.dataset.code;
                    
                    // Clear dependent dropdowns
                    document.getElementById('city').innerHTML = '<option value="">Select city/municipality</option>';
                    document.getElementById('barangay').innerHTML = '<option value="">Select barangay</option>';
                    
                    if (provinceCode) {
                        loadCities(provinceCode, 'city');
                    }
                });
            }
            
            // City change handler
            const citySelect = document.getElementById('city');
            if (citySelect) {
                citySelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const cityCode = selectedOption.dataset.code;
                    
                    // Clear barangay dropdown
                    document.getElementById('barangay').innerHTML = '<option value="">Select barangay</option>';
                    
                    if (cityCode) {
                        loadBarangays(cityCode, 'barangay');
                    }
                });
            }
            
            // School province change handler
            const schoolProvinceSelect = document.getElementById('school_province');
            if (schoolProvinceSelect) {
                schoolProvinceSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const provinceCode = selectedOption.dataset.code;
                    
                    // Clear school city dropdown
                    document.getElementById('school_city').innerHTML = '<option value="">Select school city/municipality</option>';
                    
                    if (provinceCode) {
                        loadCities(provinceCode, 'school_city');
                    }
                });
            }
            
            // Modal functions
            function showConfirmationModal() {
                const modal = document.getElementById('confirmationModal');
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden'; // Prevent background scrolling
            }
            
            function hideConfirmationModal() {
                const modal = document.getElementById('confirmationModal');
                modal.classList.add('hidden');
                document.body.style.overflow = 'auto'; // Restore scrolling
            }
            
            // Form submission validation
            const form = document.getElementById('registrationForm');
            if (form) {
                form.addEventListener('submit', function(e) {
                    e.preventDefault(); // Always prevent default submission
                    
                    if (!validateForm()) {
                        alert('Please fill in all required fields correctly.');
                        
                        // Scroll to first error
                        const firstError = document.querySelector('.border-red-500');
                        if (firstError) {
                            firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            firstError.focus();
                        }
                    } else {
                        // Show confirmation modal
                        showConfirmationModal();
                    }
                });
            }
            
            // Modal event handlers
            const confirmSubmitBtn = document.getElementById('confirmSubmit');
            const cancelSubmitBtn = document.getElementById('cancelSubmit');
            const modal = document.getElementById('confirmationModal');
            
            if (confirmSubmitBtn) {
                confirmSubmitBtn.addEventListener('click', function() {
                    // Hide modal
                    hideConfirmationModal();
                    
                    // Clear saved data
                    clearFormData();
                    
                    // Show loading state
                    this.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Submitting...';
                    this.disabled = true;
                    
                    // Submit the form
                    const form = document.getElementById('registrationForm');
                    if (form) {
                        form.submit();
                    }
                });
            }
            
            if (cancelSubmitBtn) {
                cancelSubmitBtn.addEventListener('click', function() {
                    hideConfirmationModal();
                });
            }
            
            // Close modal when clicking outside
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) {
                        hideConfirmationModal();
                    }
                });
            }
            
            // Close modal with Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('confirmationModal');
                    if (modal && !modal.classList.contains('hidden')) {
                        hideConfirmationModal();
                    }
                }
            });
            
            // Add smooth animations to form groups
            const formGroups = document.querySelectorAll('.form-group');
            formGroups.forEach((group, index) => {
                group.style.animationDelay = `${index * 0.1}s`;
                group.classList.add('animate-fade-in');
            });
        });
        
        // Phone number formatting
        function formatPhoneNumber(input) {
            let value = input.value.replace(/\D/g, '');
            if (value.startsWith('63')) {
                value = '+' + value;
            } else if (value.startsWith('9') && value.length === 10) {
                value = '+63' + value;
            } else if (value.startsWith('0') && value.length === 11) {
                value = '+63' + value.substring(1);
            }
            input.value = value;
        }
        
        // Add phone formatting to contact fields
        document.addEventListener('DOMContentLoaded', function() {
            const phoneFields = ['contact_number', 'parent_contact'];
            phoneFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) {
                    field.addEventListener('blur', function() {
                        formatPhoneNumber(this);
                    });
                }
            });
        });
    </script>
</body>
</html>
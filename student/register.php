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
        'country_code', 'contact_number', 'province', 'city', 'barangay', 'birth_province', 'birth_city',
        'guardian_last_name', 'guardian_first_name', 'parent_country_code', 'parent_contact', 'email', 'uli', 'last_school',
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
    
    // Validate ULI format and convert to uppercase
    if (!empty($_POST['uli'])) {
        $uli = strtoupper(trim($_POST['uli']));
        if (!preg_match('/^[A-Z]{3}-\d{2}-\d{3}-\d{5}-\d{3}$/', $uli)) {
            $errors[] = 'Invalid ULI format. Please enter 3 letters followed by numbers (example: abc12123123451234). The system will automatically format it correctly.';
        }
        // Update the POST data with the formatted ULI
        $_POST['uli'] = $uli;
    }
    
    // Validate phone numbers with country codes
    if (!empty($_POST['contact_number'])) {
        $country_code = $_POST['country_code'] ?? '';
        $phone_number = $_POST['contact_number'] ?? '';
        
        if (empty($country_code)) {
            $errors[] = 'Country code is required for contact number';
        } elseif (empty($phone_number)) {
            $errors[] = 'Phone number is required';
        } elseif (!preg_match('/^\+\d{1,4}$/', $country_code)) {
            $errors[] = 'Invalid country code format';
        } elseif (!preg_match('/^\d{10}$/', $phone_number)) {
            $errors[] = "Phone number must be exactly 10 digits. You entered: '$phone_number' (length: " . strlen($phone_number) . ")";
        }
    }
    
    if (!empty($_POST['parent_contact'])) {
        $parent_country_code = $_POST['parent_country_code'] ?? '';
        $parent_phone_number = $_POST['parent_contact'] ?? '';
        
        if (empty($parent_country_code)) {
            $errors[] = 'Country code is required for parent contact number';
        } elseif (empty($parent_phone_number)) {
            $errors[] = 'Parent phone number is required';
        } elseif (!preg_match('/^\+\d{1,4}$/', $parent_country_code)) {
            $errors[] = 'Invalid parent country code format';
        } elseif (!preg_match('/^\d{10}$/', $parent_phone_number)) {
            $errors[] = "Parent phone number must be exactly 10 digits. You entered: '$parent_phone_number' (length: " . strlen($parent_phone_number) . ")";
        }
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
                contact_number, province, city, barangay, street_address, birth_province, birth_city,
                guardian_last_name, guardian_first_name, guardian_middle_name, guardian_extension, parent_contact, 
                email, profile_picture, uli, last_school, school_province, school_city, 
                verification_code, is_verified, status
            ) VALUES (
                :student_id, :first_name, :middle_name, :last_name, :extension_name, :birthday, :age, :sex, :civil_status,
                :contact_number, :province, :city, :barangay, :street_address, :birth_province, :birth_city,
                :guardian_last_name, :guardian_first_name, :guardian_middle_name, :guardian_extension, :parent_contact,
                :email, :profile_picture, :uli, :last_school, :school_province, :school_city,
                :verification_code, TRUE, 'pending'
            )";
            
            $stmt = $conn->prepare($sql);
            
            // Combine country code with phone numbers
            $full_contact_number = ($_POST['country_code'] ?? '') . $_POST['contact_number'];
            $full_parent_contact = ($_POST['parent_country_code'] ?? '') . $_POST['parent_contact'];
            
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':first_name', $_POST['first_name']);
            $stmt->bindParam(':middle_name', $_POST['middle_name']);
            $stmt->bindParam(':last_name', $_POST['last_name']);
            $stmt->bindParam(':extension_name', $_POST['extension_name']);
            $stmt->bindParam(':birthday', $_POST['birthday']);
            $stmt->bindParam(':age', $age);
            $stmt->bindParam(':sex', $_POST['sex']);
            $stmt->bindParam(':civil_status', $_POST['civil_status']);
            $stmt->bindParam(':contact_number', $full_contact_number);
            $stmt->bindParam(':province', $_POST['province']);
            $stmt->bindParam(':city', $_POST['city']);
            $stmt->bindParam(':barangay', $_POST['barangay']);
            $stmt->bindParam(':street_address', $_POST['street_address']);
            $stmt->bindParam(':birth_province', $_POST['birth_province']);
            $stmt->bindParam(':birth_city', $_POST['birth_city']);
            $stmt->bindParam(':guardian_last_name', $_POST['guardian_last_name']);
            $stmt->bindParam(':guardian_first_name', $_POST['guardian_first_name']);
            $stmt->bindParam(':guardian_middle_name', $_POST['guardian_middle_name']);
            $stmt->bindParam(':guardian_extension', $_POST['guardian_extension']);
            $stmt->bindParam(':parent_contact', $full_parent_contact);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':profile_picture', $profile_picture_path);
            $stmt->bindParam(':uli', $_POST['uli']);
            $stmt->bindParam(':last_school', $_POST['last_school']);
            $stmt->bindParam(':school_province', $_POST['school_province']);
            $stmt->bindParam(':school_city', $_POST['school_city']);
            $stmt->bindParam(':verification_code', $_SESSION['verification_code']);
            
            if ($stmt->execute()) {
                // Save ULI for success message before clearing POST data
                $submitted_uli = $_POST['uli'];
                $success_message = 'Registration submitted successfully! Your registration is pending admin approval.';
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
// Set page variables for header component
$page_title = 'Student Registration';
$show_logo = true;

// Include header component
include 'components/register-header.php';
?>
    <!-- Main Content -->
    <main class="max-w-5xl mx-auto py-4 sm:py-6 lg:py-8 px-4 sm:px-6 lg:px-8">
        <?php 
        // Set navigation links
        $nav_links = [
            ['url' => '../index.php', 'text' => 'Back to Portal', 'icon' => 'fas fa-arrow-left']
        ];
        include 'components/navigation.php'; 
        ?>
        
        <?php include 'components/alerts.php'; ?>
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
            
            <form method="POST" enctype="multipart/form-data" class="p-4 sm:p-6 lg:p-8" id="registrationForm">
                <!-- Personal Information Section -->
                <div class="mb-8 sm:mb-10">
                    <div class="flex items-center mb-4 sm:mb-6">
                        <div class="bg-primary-500 text-white rounded-full w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center text-xs sm:text-sm font-bold mr-3 sm:mr-4">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Personal Information</h3>
                            <p class="text-gray-600 text-xs sm:text-sm">Tell us about yourself</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
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
                                <i class="fas fa-user-tag text-gray-400 mr-2"></i>Middle Initial
                            </label>
                            <input type="text" id="middle_name" name="middle_name" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                                   placeholder="M."
                                   maxlength="2"
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
                                <i class="fas fa-user-tag text-gray-400 mr-2"></i>Extension
                            </label>
                            <select id="extension_name" name="extension_name" 
                                    class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300">
                                <option value="">None</option>
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
                        <div class="flex flex-col sm:flex-row gap-2">
                            <select id="country_code" name="country_code" 
                                    class="w-full sm:w-24 px-2 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300 bg-gray-50 text-sm">
                                <option value="">Code</option>
                            </select>
                            <input type="tel" id="contact_number" name="contact_number" required 
                                   class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                                   placeholder="9123456789"
                                   maxlength="10"
                                   pattern="[0-9]{10}"
                                   inputmode="numeric"
                                   value="<?php echo htmlspecialchars($_POST['contact_number'] ?? ''); ?>">
                        </div>
                        <p class="text-xs text-gray-500 mt-2 flex items-center">
                            <i class="fas fa-info-circle mr-1"></i>
                            Select country code and enter 10-digit phone number (e.g., 9123456789)
                        </p>
                    </div>
                </div>

                <!-- Address Information Section -->
                <div class="mb-8 sm:mb-10 border-t border-gray-200 pt-6 sm:pt-8">
                    <div class="flex items-center mb-4 sm:mb-6">
                        <div class="bg-primary-500 text-white rounded-full w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center text-xs sm:text-sm font-bold mr-3 sm:mr-4">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Address Information</h3>
                            <p class="text-gray-600 text-xs sm:text-sm">Where do you currently live?</p>
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
                    
                    <div class="mb-6">
                        <h4 class="text-lg font-semibold text-gray-800 mb-4 flex items-center">
                            <i class="fas fa-baby text-primary-500 mr-2"></i>Place of Birth *
                        </h4>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="form-group">
                                <label for="birth_province" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-map text-primary-500 mr-2"></i>Province *
                                </label>
                                <select id="birth_province" name="birth_province" required 
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300">
                                    <option value="">Select province</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="birth_city" class="block text-sm font-semibold text-gray-700 mb-2">
                                    <i class="fas fa-city text-primary-500 mr-2"></i>City/Municipality *
                                </label>
                                <select id="birth_city" name="birth_city" required 
                                        class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300">
                                    <option value="">Select city/municipality</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Parent/Guardian Information Section -->
                <div class="mb-8 sm:mb-10 border-t border-gray-200 pt-6 sm:pt-8">
                    <div class="flex items-center mb-4 sm:mb-6">
                        <div class="bg-primary-500 text-white rounded-full w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center text-xs sm:text-sm font-bold mr-3 sm:mr-4">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Parent/Guardian Information</h3>
                            <p class="text-gray-600 text-xs sm:text-sm">Emergency contact information</p>
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
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div class="form-group">
                            <label for="guardian_middle_name" class="block text-sm font-semibold text-gray-700 mb-2">
                                <i class="fas fa-user-friends text-gray-400 mr-2"></i>Middle Initial
                            </label>
                            <input type="text" id="guardian_middle_name" name="guardian_middle_name" 
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                                   placeholder="M."
                                   maxlength="2"
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
                    
                    <div class="form-group mb-6">
                        <label for="parent_contact" class="block text-sm font-semibold text-gray-700 mb-2">
                            <i class="fas fa-phone text-primary-500 mr-2"></i>Contact Number *
                        </label>
                        <div class="flex flex-col sm:flex-row gap-2">
                            <select id="parent_country_code" name="parent_country_code" 
                                    class="w-full sm:w-24 px-2 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300 bg-gray-50 text-sm">
                                <option value="">Code</option>
                            </select>
                            <input type="tel" id="parent_contact" name="parent_contact" required 
                                   class="flex-1 px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300"
                                   placeholder="9123456789"
                                   maxlength="10"
                                   pattern="[0-9]{10}"
                                   inputmode="numeric"
                                   value="<?php echo htmlspecialchars($_POST['parent_contact'] ?? ''); ?>">
                        </div>
                        <p class="text-xs text-gray-500 mt-2 flex items-center">
                            <i class="fas fa-info-circle mr-1"></i>
                            Select country code and enter 10-digit phone number (e.g., 9123456789)
                        </p>
                    </div>
                </div>   
             <!-- Education Information Section -->
                <div class="mb-8 sm:mb-10 border-t border-gray-200 pt-6 sm:pt-8">
                    <div class="flex items-center mb-4 sm:mb-6">
                        <div class="bg-primary-500 text-white rounded-full w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center text-xs sm:text-sm font-bold mr-3 sm:mr-4">
                            <i class="fas fa-school"></i>
                        </div>
                        <div>
                            <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Education Information</h3>
                            <p class="text-gray-600 text-xs sm:text-sm">Your academic background</p>
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
                <div class="mb-8 sm:mb-10 border-t border-gray-200 pt-6 sm:pt-8">
                    <div class="flex items-center mb-4 sm:mb-6">
                        <div class="bg-primary-500 text-white rounded-full w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center text-xs sm:text-sm font-bold mr-3 sm:mr-4">
                            <i class="fas fa-info-circle"></i>
                        </div>
                        <div>
                            <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Additional Information</h3>
                            <p class="text-gray-600 text-xs sm:text-sm">Contact details and identification</p>
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
                                   class="w-full px-4 py-3 border-2 border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500 focus:border-primary-500 transition duration-200 hover:border-gray-300 font-mono tracking-wider"
                                   placeholder="ABC-12-123-12345-123"
                                   maxlength="25"
                                   title="Format: ABC-12-123-12345-123 (3 letters, then numbers separated by dashes)"
                                   value="<?php echo htmlspecialchars($_POST['uli'] ?? ''); ?>">
                            <p class="text-xs text-gray-500 mt-2 flex items-center">
                                <i class="fas fa-info-circle mr-1"></i>
                                Format: 3 letters, then numbers (abc-12-123-12345-123). You can type in lowercase - it will automatically convert to uppercase and format with dashes.
                            </p>
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
                <div class="mb-8 sm:mb-10 border-t border-gray-200 pt-6 sm:pt-8">
                    <div class="flex items-center mb-4 sm:mb-6">
                        <div class="bg-primary-500 text-white rounded-full w-8 h-8 sm:w-10 sm:h-10 flex items-center justify-center text-xs sm:text-sm font-bold mr-3 sm:mr-4">
                            <i class="fas fa-key"></i>
                        </div>
                        <div>
                            <h3 class="text-lg sm:text-xl font-semibold text-gray-900">Verification Code</h3>
                            <p class="text-gray-600 text-xs sm:text-sm">Enter the 4-digit code to verify your registration</p>
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
                <div class="border-t border-gray-200 pt-6 sm:pt-8">
                    <div class="flex justify-center">
                        <button type="submit" 
                                class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-3 bg-primary-500 text-white font-bold rounded-lg hover:bg-primary-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-200 shadow-lg hover:shadow-xl transform hover:-translate-y-0.5">
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
        <div class="relative top-10 sm:top-20 mx-auto p-4 sm:p-5 border w-11/12 sm:w-96 max-w-md shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <div class="mx-auto flex items-center justify-center h-10 w-10 sm:h-12 sm:w-12 rounded-full bg-yellow-100 mb-4">
                    <i class="fas fa-exclamation-triangle text-yellow-600 text-lg sm:text-xl"></i>
                </div>
                <h3 class="text-base sm:text-lg font-medium text-gray-900 mb-2">Confirm Registration</h3>
                <div class="mt-2 px-4 sm:px-7 py-3">
                    <p class="text-xs sm:text-sm text-gray-500 mb-4">
                        Are you sure all the information you provided is correct? Once submitted, your registration will be sent for admin approval.
                    </p>
                    <div class="bg-gray-50 rounded-lg p-3 mb-4">
                        <p class="text-xs text-gray-600 flex items-center">
                            <i class="fas fa-info-circle mr-2"></i>
                            Please double-check your details before confirming as changes may require resubmission.
                        </p>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row items-center justify-center space-y-2 sm:space-y-0 sm:space-x-4 pt-4">
                    <button id="cancelSubmit" type="button" 
                            class="w-full sm:w-auto inline-flex items-center justify-center px-4 sm:px-6 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-200">
                        <i class="fas fa-times mr-2"></i>
                        Cancel
                    </button>
                    <button id="confirmSubmit" type="button" 
                            class="w-full sm:w-auto inline-flex items-center justify-center px-4 sm:px-6 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gradient-to-r from-primary-500 to-primary-700 hover:from-primary-600 hover:to-primary-800 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition duration-200">
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
        
        // Country Code API Configuration
        const COUNTRY_API_BASE = 'https://restcountries.com/v3.1';
        
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
                console.log(`Loading provinces for ${selectId}`);
                showLoading(selectId);
                const response = await fetch(`${PSGC_API_BASE}/provinces/`);
                const provinces = await response.json();
                console.log(`Loaded ${provinces.length} provinces for ${selectId}:`, provinces.slice(0, 3));
                
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
                console.log(`Loading cities for province ${provinceCode} into ${selectId}`);
                showLoading(selectId);
                const response = await fetch(`${PSGC_API_BASE}/provinces/${provinceCode}/cities-municipalities/`);
                const cities = await response.json();
                console.log(`Loaded ${cities.length} cities for ${selectId}:`, cities);
                
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
        
        // Load country codes
        async function loadCountryCodes(selectId) {
            try {
                const response = await fetch(`${COUNTRY_API_BASE}/all?fields=name,idd,flag`);
                const countries = await response.json();
                
                // Sort countries by country code for easier finding
                countries.sort((a, b) => {
                    const codeA = a.idd && a.idd.root ? a.idd.root + (a.idd.suffixes[0] || '') : '';
                    const codeB = b.idd && b.idd.root ? b.idd.root + (b.idd.suffixes[0] || '') : '';
                    return codeA.localeCompare(codeB);
                });
                
                const select = document.getElementById(selectId);
                select.innerHTML = '<option value="">Code</option>';
                
                countries.forEach(country => {
                    if (country.idd && country.idd.root && country.idd.suffixes) {
                        const countryCode = country.idd.root + (country.idd.suffixes[0] || '');
                        const option = document.createElement('option');
                        option.value = countryCode;
                        // Show just flag and country code for mobile-friendly display
                        option.textContent = `${country.flag} ${countryCode}`;
                        option.title = `${country.name.common} (${countryCode})`; // Tooltip shows full name
                        
                        // Set Philippines as default
                        if (country.name.common === 'Philippines') {
                            option.selected = true;
                        }
                        
                        select.appendChild(option);
                    }
                });
                
            } catch (error) {
                console.error('Error loading country codes:', error);
                const select = document.getElementById(selectId);
                select.innerHTML = '<option value="">Error loading countries</option>';
            }
        }
        
        // ULI formatting function - maintains ABC-12-123-12345-123 format with automatic uppercase
        function formatULI(input) {
            let value = input.value.toUpperCase(); // Convert to uppercase automatically
            
            // Remove all non-alphanumeric characters to get clean input
            let cleanValue = value.replace(/[^A-Z0-9]/g, '');
            
            // Limit to 16 characters total (3 letters + 13 numbers)
            if (cleanValue.length > 16) {
                cleanValue = cleanValue.substring(0, 16);
            }
            
            // Build formatted string
            let formatted = '';
            
            // First 3 characters (letters only)
            if (cleanValue.length > 0) {
                let letters = cleanValue.substring(0, 3);
                // Ensure first 3 are letters, if not, don't format yet
                if (letters.length > 0) {
                    formatted += letters;
                }
            }
            
            // Add dash and next 2 digits
            if (cleanValue.length > 3) {
                formatted += '-' + cleanValue.substring(3, 5);
            }
            
            // Add dash and next 3 digits  
            if (cleanValue.length > 5) {
                formatted += '-' + cleanValue.substring(5, 8);
            }
            
            // Add dash and next 5 digits
            if (cleanValue.length > 8) {
                formatted += '-' + cleanValue.substring(8, 13);
            }
            
            // Add dash and last 3 digits
            if (cleanValue.length > 13) {
                formatted += '-' + cleanValue.substring(13, 16);
            }
            
            // Update input value
            input.value = formatted;
            
            // Visual feedback
            if (cleanValue.length === 16) {
                // Check if format is correct (3 letters + 13 numbers)
                const letters = cleanValue.substring(0, 3);
                const numbers = cleanValue.substring(3);
                const hasValidLetters = /^[A-Z]{3}$/.test(letters);
                const hasValidNumbers = /^\d{13}$/.test(numbers);
                
                if (hasValidLetters && hasValidNumbers) {
                    input.classList.add('border-green-500');
                    input.classList.remove('border-red-500', 'ring-red-500', 'border-gray-200');
                    
                    // Remove any error message
                    const errorMsg = input.parentNode.querySelector('.uli-error');
                    if (errorMsg) {
                        errorMsg.remove();
                    }
                } else {
                    input.classList.add('border-red-500', 'ring-red-500');
                    input.classList.remove('border-green-500', 'border-gray-200');
                }
            } else {
                // Partial input - neutral styling
                input.classList.remove('border-red-500', 'ring-red-500', 'border-green-500');
                input.classList.add('border-gray-200');
                
                // Remove any error message for partial input
                const errorMsg = input.parentNode.querySelector('.uli-error');
                if (errorMsg) {
                    errorMsg.remove();
                }
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
            const failedFields = []; // Debug array to track failed fields
            
            requiredFields.forEach(field => {
                // Skip ULI field as it has special validation
                if (field.id === 'uli') {
                    return;
                }
                
                if (!field.value.trim()) {
                    field.classList.add('border-red-500', 'ring-red-500');
                    field.classList.remove('border-gray-200', 'border-green-500');
                    isValid = false;
                    failedFields.push(field.name || field.id); // Debug: track failed field
                } else {
                    field.classList.remove('border-red-500', 'ring-red-500');
                    field.classList.add('border-gray-200');
                }
            });
            
            // Debug log for failed fields
            if (failedFields.length > 0) {
                console.log('Failed required fields:', failedFields);
            }
            
            // Special validation for date of birth
            const birthdayField = document.getElementById('birthday');
            if (birthdayField && birthdayField.value) {
                if (!validateDateOfBirth(birthdayField)) {
                    isValid = false;
                    console.log('Birthday validation failed');
                }
            }
            
            // Special validation for ULI format
            const uliField = document.getElementById('uli');
            if (uliField) {
                console.log('ULI validation - Value:', uliField.value); // Debug log
                
                if (!uliField.value.trim()) {
                    uliField.classList.add('border-red-500', 'ring-red-500');
                    uliField.classList.remove('border-gray-200', 'border-green-500');
                    isValid = false;
                    console.log('ULI validation failed: empty');
                    
                    // Show required error message
                    let errorMsg = uliField.parentNode.querySelector('.uli-error');
                    if (!errorMsg) {
                        errorMsg = document.createElement('p');
                        errorMsg.className = 'uli-error text-xs text-red-500 mt-1 flex items-center';
                        errorMsg.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i>ULI is required';
                        uliField.parentNode.appendChild(errorMsg);
                    }
                } else {
                    // Check if ULI has at least 3 letters and 13 numbers (regardless of dash positioning)
                    const cleanULI = uliField.value.replace(/[^A-Za-z0-9]/g, '');
                    const letters = cleanULI.match(/[A-Za-z]/g) || [];
                    const numbers = cleanULI.match(/[0-9]/g) || [];
                    
                    const hasValidContent = letters.length >= 3 && numbers.length >= 13 && cleanULI.length === 16;
                    const uliPattern = /^[A-Z]{3}-\d{2}-\d{3}-\d{5}-\d{3}$/;
                    const isValidFormat = uliPattern.test(uliField.value);
                    
                    console.log('ULI content check - Letters:', letters.length, 'Numbers:', numbers.length, 'Total:', cleanULI.length);
                    console.log('ULI pattern test:', isValidFormat, 'Has valid content:', hasValidContent);
                    
                    // Accept if either perfectly formatted OR has valid content
                    if (!hasValidContent) {
                        uliField.classList.add('border-red-500', 'ring-red-500');
                        uliField.classList.remove('border-gray-200', 'border-green-500');
                        isValid = false;
                        console.log('ULI validation failed: invalid content');
                        
                        // Show format error message
                        let errorMsg = uliField.parentNode.querySelector('.uli-error');
                        if (!errorMsg) {
                            errorMsg = document.createElement('p');
                            errorMsg.className = 'uli-error text-xs text-red-500 mt-1 flex items-center';
                            errorMsg.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i>Please use format: ABC-12-123-12345-123';
                            uliField.parentNode.appendChild(errorMsg);
                        } else {
                            errorMsg.innerHTML = '<i class="fas fa-exclamation-triangle mr-1"></i>Please use format: ABC-12-123-12345-123';
                        }
                    } else {
                        // Valid content - format it properly before submission
                        if (!isValidFormat && hasValidContent) {
                            // Auto-format the ULI properly
                            const letters_part = cleanULI.substring(0, 3);
                            const numbers_part = cleanULI.substring(3);
                            const formatted = `${letters_part}-${numbers_part.substring(0,2)}-${numbers_part.substring(2,5)}-${numbers_part.substring(5,10)}-${numbers_part.substring(10,13)}`;
                            uliField.value = formatted;
                            console.log('Auto-formatted ULI to:', formatted);
                        }
                        
                        uliField.classList.remove('border-red-500', 'ring-red-500');
                        uliField.classList.add('border-green-500');
                        console.log('ULI validation passed');
                        
                        // Remove error message if exists
                        const errorMsg = uliField.parentNode.querySelector('.uli-error');
                        if (errorMsg) {
                            errorMsg.remove();
                        }
                    }
                }
            }
            
            console.log('Form validation result:', isValid); // Debug log
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
            
            // Load provinces for address, birth place, and school
            loadProvinces('province');
            loadProvinces('birth_province');
            loadProvinces('school_province');
            
            // Load country codes for phone numbers
            loadCountryCodes('country_code');
            loadCountryCodes('parent_country_code');
            
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
            
            // ULI formatting with automatic uppercase conversion
            const uliInput = document.getElementById('uli');
            if (uliInput) {
                // Single input handler that does both formatting and uppercase conversion
                uliInput.addEventListener('input', function() {
                    // Store cursor position
                    const cursorPos = this.selectionStart;
                    const oldLength = this.value.length;
                    
                    // Format the ULI (this already converts to uppercase)
                    formatULI(this);
                    
                    // Adjust cursor position if needed
                    const newLength = this.value.length;
                    const lengthDiff = newLength - oldLength;
                    this.setSelectionRange(cursorPos + lengthDiff, cursorPos + lengthDiff);
                });
                
                // Format on paste
                uliInput.addEventListener('paste', function(e) {
                    setTimeout(() => {
                        formatULI(this);
                    }, 10);
                });
                
                // Allow all alphanumeric characters (both upper and lowercase)
                uliInput.addEventListener('keypress', function(e) {
                    // Allow control keys (backspace, delete, tab, escape, enter)
                    if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                        // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                        (e.ctrlKey === true)) {
                        return;
                    }
                    
                    const char = String.fromCharCode(e.which);
                    
                    // Allow both uppercase and lowercase letters, and numbers
                    if (!/[A-Za-z0-9]/.test(char)) {
                        e.preventDefault();
                    }
                });
            }
            
            // Phone number validation - numbers only
            const phoneFields = ['contact_number', 'parent_contact'];
            phoneFields.forEach(fieldId => {
                const phoneInput = document.getElementById(fieldId);
                if (phoneInput) {
                    // Allow only numbers on keypress
                    phoneInput.addEventListener('keypress', function(e) {
                        // Allow control keys (backspace, delete, tab, escape, enter)
                        if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                            // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                            (e.ctrlKey === true)) {
                            return;
                        }
                        
                        const char = String.fromCharCode(e.which);
                        
                        // Only allow numbers
                        if (!/[0-9]/.test(char)) {
                            e.preventDefault();
                        }
                    });
                    
                    // Remove non-numeric characters on input
                    phoneInput.addEventListener('input', function() {
                        // Remove any non-numeric characters
                        const oldValue = this.value;
                        this.value = this.value.replace(/[^0-9]/g, '');
                        
                        // Limit to 10 digits
                        if (this.value.length > 10) {
                            this.value = this.value.substring(0, 10);
                        }
                        
                        // Debug logging
                        if (oldValue !== this.value) {
                            console.log(`Phone field ${fieldId}: "${oldValue}" -> "${this.value}"`);
                        }
                    });
                    
                    // Format on paste - remove non-numeric characters
                    phoneInput.addEventListener('paste', function(e) {
                        setTimeout(() => {
                            this.value = this.value.replace(/[^0-9]/g, '');
                            if (this.value.length > 10) {
                                this.value = this.value.substring(0, 10);
                            }
                        }, 10);
                    });
                }
            });
            
            // Middle initial validation - letters only, max 2 characters
            const middleInitialFields = ['middle_name', 'guardian_middle_name'];
            middleInitialFields.forEach(fieldId => {
                const initialInput = document.getElementById(fieldId);
                if (initialInput) {
                    // Allow only letters and periods on keypress
                    initialInput.addEventListener('keypress', function(e) {
                        // Allow control keys (backspace, delete, tab, escape, enter)
                        if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                            // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                            (e.ctrlKey === true)) {
                            return;
                        }
                        
                        const char = String.fromCharCode(e.which);
                        
                        // Only allow letters and periods
                        if (!/[A-Za-z.]/.test(char)) {
                            e.preventDefault();
                        }
                    });
                    
                    // Auto-format middle initial
                    initialInput.addEventListener('input', function() {
                        // Remove any non-letter/period characters
                        let value = this.value.replace(/[^A-Za-z.]/g, '');
                        
                        // Limit to 2 characters
                        if (value.length > 2) {
                            value = value.substring(0, 2);
                        }
                        
                        // Auto-capitalize first letter
                        if (value.length > 0) {
                            value = value.charAt(0).toUpperCase() + value.slice(1);
                        }
                        
                        this.value = value;
                    });
                }
            });
            
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
            
            // Birth province change handler
            const birthProvinceSelect = document.getElementById('birth_province');
            if (birthProvinceSelect) {
                birthProvinceSelect.addEventListener('change', function() {
                    console.log('Birth province changed:', this.value);
                    const selectedOption = this.options[this.selectedIndex];
                    const provinceCode = selectedOption.dataset.code;
                    console.log('Province code:', provinceCode);
                    
                    // Clear birth city dropdown
                    document.getElementById('birth_city').innerHTML = '<option value="">Select city/municipality</option>';
                    
                    if (provinceCode) {
                        console.log('Loading cities for birth province:', provinceCode);
                        loadCities(provinceCode, 'birth_city');
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
                    
                    // Ensure ULI is properly formatted before validation
                    const uliField = document.getElementById('uli');
                    if (uliField && uliField.value) {
                        formatULI(uliField);
                    }
                    
                    // Debug: Log all required fields and their values
                    const allRequiredFields = document.querySelectorAll('[required]');
                    console.log('=== FORM VALIDATION DEBUG ===');
                    console.log('Total required fields:', allRequiredFields.length);
                    allRequiredFields.forEach(field => {
                        console.log(`Field: ${field.name || field.id} = "${field.value}" (${field.value.trim() ? 'FILLED' : 'EMPTY'})`);
                    });
                    console.log('==============================');
                    
                    // Small delay to ensure formatting is complete
                    setTimeout(() => {
                        if (!validateForm()) {
                            alert('Please fill in all required fields correctly.');
                            
                            // Scroll to first error
                            const firstError = document.querySelector('.border-red-500');
                            if (firstError) {
                                console.log('First error field:', firstError.name || firstError.id);
                                firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                firstError.focus();
                            }
                        } else {
                            // Show confirmation modal
                            showConfirmationModal();
                        }
                    }, 50);
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
        
        // Phone number formatting - DISABLED since we now use separate country code dropdowns
        function formatPhoneNumber(input) {
            // No longer needed - country codes are handled separately
            return;
        }
        
        // Phone formatting removed - using separate country code dropdowns instead
        document.addEventListener('DOMContentLoaded', function() {
            // Phone formatting no longer needed
        });
    </script>
    
    <?php include 'components/footer.php'; ?>
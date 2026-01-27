<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

$page_title = 'Edit Student';

$student = null;
$errors = [];
$success_message = '';

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
        'contact_number', 'province', 'city', 'barangay', 'birth_province', 'birth_city',
        'parent_name', 'parent_contact', 'email', 'uli', 'last_school',
        'school_province', 'school_city'
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
            $new_profile_picture_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $new_profile_picture_path)) {
                // Delete old profile picture if exists
                if (!empty($profile_picture_path) && file_exists($profile_picture_path)) {
                    unlink($profile_picture_path);
                }
                $profile_picture_path = $new_profile_picture_path;
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
                first_name = :first_name, middle_name = :middle_name, last_name = :last_name,
                birthday = :birthday, age = :age, sex = :sex, civil_status = :civil_status,
                contact_number = :contact_number, province = :province, city = :city,
                barangay = :barangay, street_address = :street_address, birth_province = :birth_province, birth_city = :birth_city,
                parent_name = :parent_name, parent_contact = :parent_contact, email = :email,
                profile_picture = :profile_picture, uli = :uli, last_school = :last_school,
                school_province = :school_province, school_city = :school_city
                WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            
            $stmt->bindParam(':first_name', $_POST['first_name']);
            $stmt->bindParam(':middle_name', $_POST['middle_name']);
            $stmt->bindParam(':last_name', $_POST['last_name']);
            $stmt->bindParam(':birthday', $_POST['birthday']);
            $stmt->bindParam(':age', $age);
            $stmt->bindParam(':sex', $_POST['sex']);
            $stmt->bindParam(':civil_status', $_POST['civil_status']);
            $stmt->bindParam(':contact_number', $_POST['contact_number']);
            $stmt->bindParam(':province', $_POST['province']);
            $stmt->bindParam(':city', $_POST['city']);
            $stmt->bindParam(':barangay', $_POST['barangay']);
            $stmt->bindParam(':street_address', $_POST['street_address']);
            $stmt->bindParam(':birth_province', $_POST['birth_province']);
            $stmt->bindParam(':birth_city', $_POST['birth_city']);
            $stmt->bindParam(':parent_name', $_POST['parent_name']);
            $stmt->bindParam(':parent_contact', $_POST['parent_contact']);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':profile_picture', $profile_picture_path);
            $stmt->bindParam(':uli', $_POST['uli']);
            $stmt->bindParam(':last_school', $_POST['last_school']);
            $stmt->bindParam(':school_province', $_POST['school_province']);
            $stmt->bindParam(':school_city', $_POST['school_city']);
            $stmt->bindParam(':id', $student_id);
            
            if ($stmt->execute()) {
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
                    <li>
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <a href="view.php?id=<?php echo $student_id; ?>" class="text-sm font-medium text-gray-700 hover:text-blue-600">View Student</a>
                        </div>
                    </li>
                    <li aria-current="page">
                        <div class="flex items-center">
                            <i class="fas fa-chevron-right text-gray-400 mx-2"></i>
                            <span class="text-sm font-medium text-gray-500">Edit</span>
                        </div>
                    </li>
                </ol>
            </nav>

            <!-- Page Header -->
            <div class="mb-6 md:mb-8">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                    <div class="mb-4 md:mb-0">
                        <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">Edit Student</h1>
                        <p class="text-gray-600">Update student information and details</p>
                    </div>
                </div>
            </div>

            <!-- Alert Messages -->
            <?php if (!empty($errors)): ?>
                <div class="mb-6 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-lg">
                    <div class="flex items-start">
                        <i class="fas fa-exclamation-circle mr-2 mt-0.5"></i>
                        <div>
                            <h4 class="font-medium mb-1">Please fix the following errors:</h4>
                            <ul class="list-disc list-inside space-y-1">
                                <?php foreach ($errors as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="mb-6 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded-lg">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php echo htmlspecialchars($success_message); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if ($student): ?>
        <form id="edit-form" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="existing_profile_picture" value="<?php echo htmlspecialchars($student['profile_picture']); ?>">
            
            <!-- Personal Information -->
            <div class="form-section">
                <h3>Personal Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required 
                               value="<?php echo htmlspecialchars($student['first_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="middle_name">Middle Initial</label>
                        <input type="text" id="middle_name" name="middle_name" 
                               maxlength="2"
                               placeholder="M."
                               value="<?php echo htmlspecialchars($student['middle_name']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required 
                               value="<?php echo htmlspecialchars($student['last_name']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="birthday">Birthday *</label>
                        <input type="date" id="birthday" name="birthday" required 
                               value="<?php echo htmlspecialchars($student['birthday']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="age">Age</label>
                        <input type="number" id="age" name="age" readonly value="<?php echo htmlspecialchars($student['age']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="sex">Sex *</label>
                        <select id="sex" name="sex" required>
                            <option value="">Select Sex</option>
                            <option value="Male" <?php echo ($student['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($student['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($student['sex'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="civil_status">Civil Status *</label>
                        <select id="civil_status" name="civil_status" required>
                            <option value="">Select Civil Status</option>
                            <option value="Single" <?php echo ($student['civil_status'] === 'Single') ? 'selected' : ''; ?>>Single</option>
                            <option value="Married" <?php echo ($student['civil_status'] === 'Married') ? 'selected' : ''; ?>>Married</option>
                            <option value="Divorced" <?php echo ($student['civil_status'] === 'Divorced') ? 'selected' : ''; ?>>Divorced</option>
                            <option value="Widowed" <?php echo ($student['civil_status'] === 'Widowed') ? 'selected' : ''; ?>>Widowed</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="contact_number">Contact Number *</label>
                    <input type="tel" id="contact_number" name="contact_number" required 
                           placeholder="e.g., +639123456789 or 09123456789"
                           value="<?php echo htmlspecialchars($student['contact_number']); ?>">
                </div>
            </div>
            
            <!-- Address Information -->
            <div class="form-section">
                <h3>Address Information</h3>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="province">Province *</label>
                        <input type="text" id="province" name="province" required 
                               value="<?php echo htmlspecialchars($student['province']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="city">City/Municipality *</label>
                        <input type="text" id="city" name="city" required 
                               value="<?php echo htmlspecialchars($student['city']); ?>">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="barangay">Barangay *</label>
                        <input type="text" id="barangay" name="barangay" required 
                               value="<?php echo htmlspecialchars($student['barangay']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="street_address">Street / Subdivision</label>
                        <input type="text" id="street_address" name="street_address" 
                               value="<?php echo htmlspecialchars($student['street_address']); ?>">
                    </div>
                </div>
                
                <div class="form-section">
                    <h4>Place of Birth</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="birth_province">Province *</label>
                            <input type="text" id="birth_province" name="birth_province" required 
                                   value="<?php echo htmlspecialchars($student['birth_province']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="birth_city">City/Municipality *</label>
                            <input type="text" id="birth_city" name="birth_city" required 
                                   value="<?php echo htmlspecialchars($student['birth_city']); ?>">
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Parent/Guardian Information -->
            <div class="form-section">
                <h3>Parent/Guardian Information</h3>
                
                <div class="form-group">
                    <label for="parent_name">Full Name (Last Name, First Name, Middle Initial) *</label>
                    <input type="text" id="parent_name" name="parent_name" required 
                           placeholder="e.g., Dela Cruz, Juan A."
                           value="<?php echo htmlspecialchars($student['parent_name']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="parent_contact">Contact Number *</label>
                    <input type="tel" id="parent_contact" name="parent_contact" required 
                           placeholder="e.g., +639123456789 or 09123456789"
                           value="<?php echo htmlspecialchars($student['parent_contact']); ?>">
                </div>
            </div>
            
            <!-- Education Information -->
            <div class="form-section">
                <h3>Education Information</h3>
                
                <div class="form-group">
                    <label for="last_school">Last School Attended (Full Name, no abbreviations) *</label>
                    <input type="text" id="last_school" name="last_school" required 
                           placeholder="e.g., University of the Philippines Diliman"
                           value="<?php echo htmlspecialchars($student['last_school']); ?>">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="school_province">School Province *</label>
                        <input type="text" id="school_province" name="school_province" required 
                               value="<?php echo htmlspecialchars($student['school_province']); ?>">
                    </div>
                    <div class="form-group">
                        <label for="school_city">School City/Municipality *</label>
                        <input type="text" id="school_city" name="school_city" required 
                               value="<?php echo htmlspecialchars($student['school_city']); ?>">
                    </div>
                </div>
            </div>
            
            <!-- Other Information -->
            <div class="form-section">
                <h3>Other Information</h3>
                
                <div class="form-group">
                    <label for="email">Email Address *</label>
                    <input type="email" id="email" name="email" required 
                           value="<?php echo htmlspecialchars($student['email']); ?>">
                </div>
                
                <div class="form-group">
                    <label for="uli">ULI (Unique Learner Identifier) *</label>
                    <input type="text" id="uli" name="uli" required 
                           value="<?php echo htmlspecialchars($student['uli']); ?>">
                </div>
                
                <div class="form-group profile-upload">
                    <label for="profile_picture">Profile Picture</label>
                    
                    <?php if (!empty($student['profile_picture']) && file_exists($student['profile_picture'])): ?>
                        <img id="current-profile" src="<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                             class="profile-preview" alt="Current Profile Picture">
                    <?php else: ?>
                        <div id="upload-placeholder" class="upload-placeholder">
                            No current photo
                        </div>
                    <?php endif; ?>
                    
                    <img id="profile-preview" class="profile-preview" style="display: none;">
                    <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/jpg,image/png">
                    <small>Maximum file size: 2MB. Accepted formats: JPG, JPEG, PNG. Leave empty to keep current photo.</small>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">Update Student</button>
                <a href="view.php?id=<?php echo $student_id; ?>" class="btn btn-secondary">Cancel</a>
                <a href="index.php" class="btn btn-secondary">Back to Students List</a>
            </div>
        </form>
        <?php endif; ?>
    </div>
</main>

<script>
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
</script>

<?php include '../includes/footer.php'; ?>
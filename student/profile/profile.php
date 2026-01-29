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

// Handle student ID from URL parameter
if (isset($_GET['student_id']) && is_numeric($_GET['student_id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        $stmt = $conn->prepare("SELECT * FROM students WHERE id = :id");
        $stmt->bindParam(':id', $_GET['student_id']);
        $stmt->execute();
        $student_profile = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student_profile) {
            $errors[] = 'Student record not found';
        }
    } catch (PDOException $e) {
        $errors[] = 'Database error: ' . $e->getMessage();
    }
} else {
    $errors[] = 'Invalid student ID';
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
                <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-8">
                    <div class="flex flex-col md:flex-row items-center md:items-start gap-6">
                        <div class="flex-shrink-0">
                            <?php if (!empty($student_profile['profile_picture']) && file_exists('../../' . $student_profile['profile_picture'])): ?>
                                <img src="../../<?php echo htmlspecialchars($student_profile['profile_picture']); ?>" 
                                     alt="Profile Picture" 
                                     class="w-24 h-24 md:w-32 md:h-32 rounded-full object-cover border-4 border-white shadow-lg">
                            <?php else: ?>
                                <div class="w-24 h-24 md:w-32 md:h-32 rounded-full bg-white bg-opacity-20 border-4 border-white shadow-lg flex items-center justify-center">
                                    <span class="text-2xl md:text-3xl font-bold text-white">
                                        <?php echo strtoupper(substr($student_profile['first_name'], 0, 1) . substr($student_profile['last_name'], 0, 1)); ?>
                                    </span>
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
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-red-100 mb-4">
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
                            
                            <!-- Status Badge -->
                            <div>
                                <?php
                                $status_class = '';
                                $status_icon = '';
                                $status_text = '';
                                switch ($student_profile['status']) {
                                    case 'approved':
                                        $status_class = 'bg-green-100 text-green-800 border-green-200';
                                        $status_icon = 'fas fa-check-circle';
                                        $status_text = 'Approved - You can now attend classes';
                                        break;
                                    case 'rejected':
                                        $status_class = 'bg-red-100 text-red-800 border-red-200';
                                        $status_icon = 'fas fa-times-circle';
                                        $status_text = 'Application Rejected - Please contact admin';
                                        break;
                                    default:
                                        $status_class = 'bg-yellow-100 text-yellow-800 border-yellow-200';
                                        $status_icon = 'fas fa-clock';
                                        $status_text = 'Pending Approval - Please wait for admin review';
                                }
                                ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium border <?php echo $status_class; ?>">
                                    <i class="<?php echo $status_icon; ?> mr-2"></i>
                                    <?php echo $status_text; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Course Information -->
                <?php if ($student_profile['status'] === 'approved' && ($student_profile['course'] || $student_profile['nc_level'])): ?>
                    <div class="px-6 py-6 bg-gray-50 border-t border-gray-200">
                        <h3 class="text-lg font-semibold text-gray-900 mb-4">
                            <i class="fas fa-book text-red-800 mr-2"></i>Your Approved Course
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <?php if ($student_profile['course']): ?>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Course</label>
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['course']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student_profile['nc_level']): ?>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <label class="block text-sm font-medium text-gray-500 mb-1">NC Level</label>
                                    <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['nc_level']); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student_profile['training_start']): ?>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Training Start</label>
                                    <p class="text-sm font-semibold text-gray-900"><?php echo date('M j, Y', strtotime($student_profile['training_start'])); ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($student_profile['training_end']): ?>
                                <div class="bg-white p-4 rounded-lg border border-gray-200">
                                    <label class="block text-sm font-medium text-gray-500 mb-1">Training End</label>
                                    <p class="text-sm font-semibold text-gray-900"><?php echo date('M j, Y', strtotime($student_profile['training_end'])); ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($student_profile['adviser']): ?>
                            <div class="mt-4 bg-white p-4 rounded-lg border border-gray-200">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Assigned Adviser</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['adviser']); ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                
                <!-- Additional Student Information -->
                <div class="px-6 py-6 bg-white border-t border-gray-200">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            <i class="fas fa-info-circle text-red-800 mr-2"></i>Personal Information
                        </h3>
                        <div id="editControls" class="hidden space-x-2">
                            <button onclick="saveChanges()" class="inline-flex items-center px-4 py-2 bg-green-600 text-white text-xs font-medium rounded-lg hover:bg-green-700 transition-colors duration-200">
                                <i class="fas fa-save mr-1"></i>Save
                            </button>
                            <button onclick="cancelEdit()" class="inline-flex items-center px-4 py-2 bg-gray-500 text-white text-xs font-medium rounded-lg hover:bg-gray-600 transition-colors duration-200">
                                <i class="fas fa-times mr-1"></i>Cancel
                            </button>
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
                            <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                                <label class="block text-sm font-medium text-gray-500 mb-1">Address</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['barangay'] . ', ' . $student_profile['city'] . ', ' . $student_profile['province']); ?></p>
                                <p class="text-xs text-red-600 mt-1">Contact registrar to change</p>
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
                            
                            <div class="bg-red-50 p-4 rounded-lg border border-red-200">
                                <label class="block text-sm font-medium text-gray-500 mb-1">ULI</label>
                                <p class="text-sm font-semibold text-gray-900"><?php echo htmlspecialchars($student_profile['uli']); ?></p>
                                <p class="text-xs text-red-600 mt-1">Cannot be changed</p>
                            </div>
                        </div>
                    </form>
                </div>
                
                <div class="px-6 py-4 bg-white border-t border-gray-200">
                    <div class="flex justify-center space-x-4">
                        <a href="../../index.php" class="inline-flex items-center px-6 py-3 bg-red-800 text-white text-sm font-semibold rounded-lg hover:bg-red-900 transition-colors duration-200">
                            <i class="fas fa-search mr-2"></i>Search Again
                        </a>
                        <button onclick="toggleEditMode()" id="editBtn" class="inline-flex items-center px-6 py-3 bg-blue-600 text-white text-sm font-semibold rounded-lg hover:bg-blue-700 transition-colors duration-200">
                            <i class="fas fa-edit mr-2"></i>Edit Profile
                        </button>
                        <a href="#" class="inline-flex items-center px-6 py-3 bg-green-600 text-white text-sm font-semibold rounded-lg hover:bg-green-700 transition-colors duration-200">
                            <i class="fas fa-plus mr-2"></i>New Course
                        </a>
                    </div>
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
                editBtn.classList.remove('bg-blue-600', 'hover:bg-blue-700');
                editBtn.classList.add('bg-gray-500', 'hover:bg-gray-600');
            } else {
                // Switch to view mode
                viewModes.forEach(el => el.classList.remove('hidden'));
                editModes.forEach(el => el.classList.add('hidden'));
                editControls.classList.add('hidden');
                editBtn.innerHTML = '<i class="fas fa-edit mr-2"></i>Edit Profile';
                editBtn.classList.remove('bg-gray-500', 'hover:bg-gray-600');
                editBtn.classList.add('bg-blue-600', 'hover:bg-blue-700');
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
<?php
session_start();
require_once '../../config/database.php';
require_once '../../includes/auth_middleware.php';

// Require admin authentication
requireAdmin();

$page_title = 'View Student';
$css_path = '../../assets/css/style.css';
$js_path = '../../assets/js/main.js';

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

include '../includes/header.php';
?>

<header class="admin-header">
    <h1>View Student Details</h1>
</header>

<nav class="admin-nav">
    <ul>
        <li><a href="../dashboard.php">Dashboard</a></li>
        <li><a href="index.php">Manage Students</a></li>
        <li><a href="../../index.php">Back to Home</a></li>
    </ul>
</nav>

<main class="main-content">
    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <?php echo $error_message; ?>
        </div>
        <div style="text-align: center; margin-top: 2rem;">
            <a href="index.php" class="btn btn-secondary">Back to Students List</a>
        </div>
    <?php else: ?>
        
        <div class="student-details-container" style="background: white; padding: 2rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
            
            <!-- Student Header -->
            <div class="student-header" style="display: flex; align-items: center; gap: 2rem; margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 2px solid var(--primary-color);">
                <div class="profile-picture">
                    <?php if (!empty($student['profile_picture']) && file_exists($student['profile_picture'])): ?>
                        <img src="<?php echo htmlspecialchars($student['profile_picture']); ?>" 
                             alt="Profile Picture" 
                             style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid var(--border-color);">
                    <?php else: ?>
                        <div style="width: 120px; height: 120px; border-radius: 50%; background-color: #f8f9fa; border: 3px solid var(--border-color); display: flex; align-items: center; justify-content: center; color: #6c757d;">
                            No Photo
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="student-basic-info">
                    <h2 style="color: var(--primary-color); margin-bottom: 0.5rem;">
                        <?php echo htmlspecialchars(trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name'])); ?>
                    </h2>
                    <p style="margin: 0.25rem 0;"><strong>Student ID:</strong> <?php echo htmlspecialchars($student['id']); ?></p>
                    <p style="margin: 0.25rem 0;"><strong>ULI:</strong> <?php echo htmlspecialchars($student['uli']); ?></p>
                    <p style="margin: 0.25rem 0;"><strong>Email:</strong> <?php echo htmlspecialchars($student['email']); ?></p>
                    <p style="margin: 0.25rem 0;"><strong>Registered:</strong> <?php echo date('F j, Y g:i A', strtotime($student['created_at'])); ?></p>
                </div>
            </div>
            
            <!-- Student Details Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem;">
                
                <!-- Personal Information -->
                <div class="info-section">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">Personal Information</h3>
                    
                    <div class="info-grid" style="display: grid; gap: 0.75rem;">
                        <div><strong>First Name:</strong> <?php echo htmlspecialchars($student['first_name']); ?></div>
                        <div><strong>Middle Name:</strong> <?php echo htmlspecialchars($student['middle_name'] ?: 'N/A'); ?></div>
                        <div><strong>Last Name:</strong> <?php echo htmlspecialchars($student['last_name']); ?></div>
                        <div><strong>Birthday:</strong> <?php echo date('F j, Y', strtotime($student['birthday'])); ?></div>
                        <div><strong>Age:</strong> <?php echo htmlspecialchars($student['age']); ?> years old</div>
                        <div><strong>Sex:</strong> <?php echo htmlspecialchars($student['sex']); ?></div>
                        <div><strong>Civil Status:</strong> <?php echo htmlspecialchars($student['civil_status']); ?></div>
                        <div><strong>Contact Number:</strong> <?php echo htmlspecialchars($student['contact_number']); ?></div>
                    </div>
                </div>
                
                <!-- Address Information -->
                <div class="info-section">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">Address Information</h3>
                    
                    <div class="info-grid" style="display: grid; gap: 0.75rem;">
                        <div><strong>Province:</strong> <?php echo htmlspecialchars($student['province']); ?></div>
                        <div><strong>City/Municipality:</strong> <?php echo htmlspecialchars($student['city']); ?></div>
                        <div><strong>Barangay:</strong> <?php echo htmlspecialchars($student['barangay']); ?></div>
                        <div><strong>Street/Subdivision:</strong> <?php echo htmlspecialchars($student['street_address'] ?: 'N/A'); ?></div>
                        <div><strong>Place of Birth:</strong> <?php echo htmlspecialchars($student['birth_province'] . ', ' . $student['birth_city']); ?></div>
                    </div>
                </div>
                
                <!-- Parent/Guardian Information -->
                <div class="info-section">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">Parent/Guardian Information</h3>
                    
                    <div class="info-grid" style="display: grid; gap: 0.75rem;">
                        <div><strong>Full Name:</strong> <?php echo htmlspecialchars($student['parent_name']); ?></div>
                        <div><strong>Contact Number:</strong> <?php echo htmlspecialchars($student['parent_contact']); ?></div>
                    </div>
                </div>
                
                <!-- Education Information -->
                <div class="info-section">
                    <h3 style="color: var(--primary-color); margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">Education Information</h3>
                    
                    <div class="info-grid" style="display: grid; gap: 0.75rem;">
                        <div><strong>Last School Attended:</strong> <?php echo htmlspecialchars($student['last_school']); ?></div>
                        <div><strong>School Province:</strong> <?php echo htmlspecialchars($student['school_province']); ?></div>
                        <div><strong>School City:</strong> <?php echo htmlspecialchars($student['school_city']); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons" style="margin-top: 2rem; padding-top: 1rem; border-top: 1px solid var(--border-color); text-align: center;">
                <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                    <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn btn-primary">Edit Student</a>
                    <a href="index.php" class="btn btn-secondary">Back to Students List</a>
                    <a href="?action=delete&id=<?php echo $student['id']; ?>" 
                       class="btn" style="background-color: var(--error-color); color: white;"
                       onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.')">Delete Student</a>
                </div>
            </div>
        </div>
        
    <?php endif; ?>
</main>

<?php include '../includes/footer.php'; ?>
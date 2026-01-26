<?php
session_start();
require_once '../config/database.php';

$page_title = 'Manage Students';
$css_path = '../assets/css/style.css';
$js_path = '../assets/js/main.js';

$students = [];
$error_message = '';
$success_message = '';

// Handle delete action
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    try {
        $database = new Database();
        $conn = $database->getConnection();
        
        // Get student info for file cleanup
        $stmt = $conn->prepare("SELECT profile_picture FROM students WHERE id = :id");
        $stmt->bindParam(':id', $_GET['id']);
        $stmt->execute();
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Delete student record
        $stmt = $conn->prepare("DELETE FROM students WHERE id = :id");
        $stmt->bindParam(':id', $_GET['id']);
        
        if ($stmt->execute()) {
            // Delete profile picture file if exists
            if ($student && !empty($student['profile_picture']) && file_exists($student['profile_picture'])) {
                unlink($student['profile_picture']);
            }
            $success_message = 'Student deleted successfully.';
        } else {
            $error_message = 'Failed to delete student.';
        }
    } catch (PDOException $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

// Get search parameters
$search = $_GET['search'] ?? '';
$filter_province = $_GET['filter_province'] ?? '';
$filter_sex = $_GET['filter_sex'] ?? '';

// Build query
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(first_name LIKE :search OR last_name LIKE :search OR email LIKE :search OR uli LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($filter_province)) {
    $where_conditions[] = "province = :province";
    $params[':province'] = $filter_province;
}

if (!empty($filter_sex)) {
    $where_conditions[] = "sex = :sex";
    $params[':sex'] = $filter_sex;
}

$where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get students with filters
    $sql = "SELECT id, first_name, middle_name, last_name, email, sex, province, city, contact_number, created_at 
            FROM students $where_clause ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get unique provinces for filter
    $stmt = $conn->query("SELECT DISTINCT province FROM students ORDER BY province");
    $provinces = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

include '../includes/header.php';
?>

<header class="admin-header">
    <h1>Manage Students</h1>
</header>

<nav class="admin-nav">
    <ul>
        <li><a href="dashboard.php">Dashboard</a></li>
        <li><a href="manage_students.php">Manage Students</a></li>
        <li><a href="../index.php">Back to Home</a></li>
    </ul>
</nav>

<main class="main-content">
    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <?php echo $error_message; ?>
        </div>
    <?php endif; ?>
    
    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <?php echo $success_message; ?>
        </div>
    <?php endif; ?>
    
    <!-- Search and Filter Form -->
    <div class="search-filter-container" style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 2rem;">
        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">Search & Filter Students</h3>
        
        <form method="GET" style="display: grid; gap: 1rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem;">
                <div class="form-group">
                    <label for="search">Search (Name, Email, ULI)</label>
                    <input type="text" id="search" name="search" placeholder="Enter search term..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="form-group">
                    <label for="filter_province">Filter by Province</label>
                    <select id="filter_province" name="filter_province">
                        <option value="">All Provinces</option>
                        <?php foreach ($provinces as $province): ?>
                            <option value="<?php echo htmlspecialchars($province); ?>" 
                                    <?php echo ($filter_province === $province) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($province); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="filter_sex">Filter by Sex</label>
                    <select id="filter_sex" name="filter_sex">
                        <option value="">All</option>
                        <option value="Male" <?php echo ($filter_sex === 'Male') ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($filter_sex === 'Female') ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($filter_sex === 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
            </div>
            
            <div style="display: flex; gap: 1rem; flex-wrap: wrap;">
                <button type="submit" class="btn btn-primary">Search & Filter</button>
                <a href="manage_students.php" class="btn btn-secondary">Clear Filters</a>
                <a href="../student/register.php" class="btn btn-primary">Add New Student</a>
            </div>
        </form>
    </div>
    
    <!-- Students Table -->
    <div class="students-container" style="background: white; padding: 1.5rem; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h3 style="color: var(--primary-color); margin-bottom: 1rem;">
            Students List 
            <span style="font-size: 0.875rem; color: #6c757d;">(<?php echo count($students); ?> found)</span>
        </h3>
        
        <?php if (empty($students)): ?>
            <p>No students found matching your criteria.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Sex</th>
                            <th>Province</th>
                            <th>City</th>
                            <th>Contact</th>
                            <th>Registration Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $student): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($student['id']); ?></td>
                                <td>
                                    <?php 
                                    $full_name = trim($student['first_name'] . ' ' . $student['middle_name'] . ' ' . $student['last_name']);
                                    echo htmlspecialchars($full_name); 
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($student['email']); ?></td>
                                <td><?php echo htmlspecialchars($student['sex']); ?></td>
                                <td><?php echo htmlspecialchars($student['province']); ?></td>
                                <td><?php echo htmlspecialchars($student['city']); ?></td>
                                <td><?php echo htmlspecialchars($student['contact_number']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($student['created_at'])); ?></td>
                                <td>
                                    <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                                        <a href="view_student.php?id=<?php echo $student['id']; ?>" 
                                           class="btn btn-primary" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">View</a>
                                        <a href="edit_student.php?id=<?php echo $student['id']; ?>" 
                                           class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.875rem;">Edit</a>
                                        <a href="?action=delete&id=<?php echo $student['id']; ?>" 
                                           class="btn" style="padding: 0.25rem 0.5rem; font-size: 0.875rem; background-color: var(--error-color); color: white;"
                                           onclick="return confirm('Are you sure you want to delete this student? This action cannot be undone.')">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</main>

<?php include '../includes/footer.php'; ?>
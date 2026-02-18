<?php
/**
 * Migration Script: Move First Course Data from students table to course_applications table
 * 
 * This script migrates existing first course data that was stored in the students table
 * to the course_applications table for consistency.
 * 
 * Run this ONCE after deploying the new dashboard.php changes.
 */

require_once '../config/database.php';

echo "=== First Course Migration Script ===\n\n";

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Find all students who have course data in students table but no corresponding course_application
    $stmt = $conn->query("
        SELECT s.id, s.course, s.nc_level, s.training_start, s.training_end, s.adviser, 
               s.approved_by, s.approved_at, s.created_at, s.first_name, s.last_name,
               c.course_id
        FROM students s
        LEFT JOIN courses c ON s.course = c.course_name
        WHERE s.status IN ('approved', 'completed') 
        AND s.course IS NOT NULL 
        AND s.course != ''
        AND NOT EXISTS (
            SELECT 1 FROM course_applications ca 
            WHERE ca.student_id = s.id 
            AND ca.course_id = c.course_id
        )
        ORDER BY s.id
    ");
    
    $students_to_migrate = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $total = count($students_to_migrate);
    
    echo "Found {$total} students with first course data to migrate.\n\n";
    
    if ($total === 0) {
        echo "No migration needed. All students are already using course_applications table.\n";
        exit(0);
    }
    
    $migrated = 0;
    $skipped = 0;
    $errors = 0;
    
    foreach ($students_to_migrate as $student) {
        $student_id = $student['id'];
        $student_name = $student['first_name'] . ' ' . $student['last_name'];
        
        echo "Processing: {$student_name} (ID: {$student_id})...\n";
        
        // Skip if course_id couldn't be found
        if (empty($student['course_id'])) {
            echo "  ⚠ SKIPPED: Could not find course_id for course '{$student['course']}'\n";
            $skipped++;
            continue;
        }
        
        try {
            $conn->beginTransaction();
            
            // Create course_application record
            $stmt = $conn->prepare("
                INSERT INTO course_applications 
                (student_id, course_id, nc_level, training_start, training_end, adviser, 
                 status, reviewed_by, reviewed_at, applied_at) 
                VALUES 
                (:student_id, :course_id, :nc_level, :training_start, :training_end, :adviser,
                 'completed', :reviewed_by, :reviewed_at, :applied_at)
            ");
            
            $stmt->bindParam(':student_id', $student_id);
            $stmt->bindParam(':course_id', $student['course_id']);
            $stmt->bindParam(':nc_level', $student['nc_level']);
            $stmt->bindParam(':training_start', $student['training_start']);
            $stmt->bindParam(':training_end', $student['training_end']);
            $stmt->bindParam(':adviser', $student['adviser']);
            $stmt->bindParam(':reviewed_by', $student['approved_by']);
            $stmt->bindParam(':reviewed_at', $student['approved_at']);
            $stmt->bindParam(':applied_at', $student['created_at']); // Use registration date as applied date
            
            $stmt->execute();
            
            // Optional: Clear course data from students table (keep for backward compatibility)
            // Uncomment the following lines if you want to remove course data from students table
            /*
            $stmt = $conn->prepare("
                UPDATE students 
                SET course = NULL, nc_level = NULL, training_start = NULL, 
                    training_end = NULL, adviser = NULL
                WHERE id = :id
            ");
            $stmt->bindParam(':id', $student_id);
            $stmt->execute();
            */
            
            $conn->commit();
            
            echo "  ✓ SUCCESS: Created course_application for '{$student['course']}'\n";
            $migrated++;
            
        } catch (PDOException $e) {
            $conn->rollBack();
            echo "  ✗ ERROR: " . $e->getMessage() . "\n";
            $errors++;
        }
    }
    
    echo "\n=== Migration Complete ===\n";
    echo "Total students processed: {$total}\n";
    echo "Successfully migrated: {$migrated}\n";
    echo "Skipped: {$skipped}\n";
    echo "Errors: {$errors}\n";
    
    if ($migrated > 0) {
        echo "\n✓ Migration successful! First course data has been moved to course_applications table.\n";
        echo "  Students can now see their complete course history consistently.\n";
    }
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
    exit(1);
}

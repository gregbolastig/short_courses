<?php
/**
 * Migration: Add birth_province and birth_city fields to students table
 * This separates the place_of_birth field into structured province and city fields
 */

require_once __DIR__ . '/../config/database.php';

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    echo "Starting migration: Add birth_province and birth_city fields...\n\n";
    
    // Check if columns already exist
    $stmt = $conn->query("SHOW COLUMNS FROM students LIKE 'birth_province'");
    $birth_province_exists = $stmt->rowCount() > 0;
    
    $stmt = $conn->query("SHOW COLUMNS FROM students LIKE 'birth_city'");
    $birth_city_exists = $stmt->rowCount() > 0;
    
    if ($birth_province_exists && $birth_city_exists) {
        echo "✓ Columns birth_province and birth_city already exist.\n";
        exit(0);
    }
    
    // Add birth_province column
    if (!$birth_province_exists) {
        echo "Adding birth_province column...\n";
        $conn->exec("ALTER TABLE students ADD COLUMN birth_province VARCHAR(100) NULL AFTER place_of_birth");
        echo "✓ Added birth_province column\n";
    }
    
    // Add birth_city column
    if (!$birth_city_exists) {
        echo "Adding birth_city column...\n";
        $conn->exec("ALTER TABLE students ADD COLUMN birth_city VARCHAR(100) NULL AFTER birth_province");
        echo "✓ Added birth_city column\n";
    }
    
    // Migrate existing data from place_of_birth to birth_province and birth_city
    echo "\nMigrating existing place_of_birth data...\n";
    $stmt = $conn->query("SELECT id, place_of_birth FROM students WHERE place_of_birth IS NOT NULL AND place_of_birth != ''");
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $migrated = 0;
    foreach ($students as $student) {
        $place_of_birth = $student['place_of_birth'];
        
        // Try to parse "City, Province" format
        if (strpos($place_of_birth, ',') !== false) {
            $parts = array_map('trim', explode(',', $place_of_birth));
            $birth_city = $parts[0];
            $birth_province = isset($parts[1]) ? $parts[1] : '';
        } else {
            // If no comma, put everything in city
            $birth_city = $place_of_birth;
            $birth_province = '';
        }
        
        $update = $conn->prepare("UPDATE students SET birth_province = :province, birth_city = :city WHERE id = :id");
        $update->execute([
            ':province' => $birth_province,
            ':city' => $birth_city,
            ':id' => $student['id']
        ]);
        $migrated++;
    }
    
    echo "✓ Migrated $migrated student records\n";
    
    echo "\n✅ Migration completed successfully!\n";
    echo "\nNote: The place_of_birth column is kept for backward compatibility.\n";
    echo "You can remove it later after verifying all data is migrated correctly.\n";
    
} catch (PDOException $e) {
    echo "❌ Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}

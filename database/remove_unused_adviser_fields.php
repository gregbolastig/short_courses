<?php
/**
 * Remove Unused Adviser Fields Migration Script
 * 
 * This script removes unused fields from the advisers table:
 * - email, phone, department, specialization
 * 
 * Only adviser_name is actually used in the system interface.
 */

require_once '../config/database.php';

class RemoveUnusedAdviserFields {
    private $conn;
    
    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        
        if (!$this->conn) {
            throw new Exception("Database connection failed");
        }
    }
    
    public function migrate() {
        try {
            echo "Starting migration to remove unused adviser fields...\n";
            
            // Start transaction
            $this->conn->beginTransaction();
            
            // 1. Check current table structure
            echo "1. Checking current advisers table structure...\n";
            $this->showTableStructure();
            
            // 2. Create backup (optional)
            echo "2. Creating backup table...\n";
            $this->createBackup();
            
            // 3. Remove unused columns
            echo "3. Removing unused columns...\n";
            $this->removeUnusedColumns();
            
            // 4. Verify changes
            echo "4. Verifying changes...\n";
            $this->verifyChanges();
            
            // Commit transaction
            $this->conn->commit();
            
            echo "✅ Migration completed successfully!\n";
            echo "Unused adviser fields have been removed.\n";
            
        } catch (Exception $e) {
            // Rollback on error
            $this->conn->rollback();
            echo "❌ Migration failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    private function showTableStructure() {
        $stmt = $this->conn->query("DESCRIBE advisers");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Current advisers table structure:\n";
        foreach ($columns as $column) {
            echo "  - {$column['Field']} ({$column['Type']})\n";
        }
        echo "\n";
    }
    
    private function createBackup() {
        // Create backup table with timestamp
        $timestamp = date('Y_m_d_H_i_s');
        $backupTable = "advisers_backup_{$timestamp}";
        
        $sql = "CREATE TABLE {$backupTable} AS SELECT * FROM advisers";
        $this->conn->exec($sql);
        
        $count = $this->conn->query("SELECT COUNT(*) FROM {$backupTable}")->fetchColumn();
        echo "  ✅ Backup created: {$backupTable} ({$count} records)\n\n";
    }
    
    private function removeUnusedColumns() {
        // Check which columns exist before trying to drop them
        $stmt = $this->conn->query("SHOW COLUMNS FROM advisers");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $columnsToRemove = ['email', 'phone', 'department', 'specialization'];
        $columnsToRemoveExisting = array_intersect($columnsToRemove, $existingColumns);
        
        if (empty($columnsToRemoveExisting)) {
            echo "  ℹ️  No unused columns found to remove.\n\n";
            return;
        }
        
        // Build ALTER TABLE statement
        $dropStatements = array_map(function($col) {
            return "DROP COLUMN {$col}";
        }, $columnsToRemoveExisting);
        
        $sql = "ALTER TABLE advisers " . implode(', ', $dropStatements);
        
        echo "  Executing: {$sql}\n";
        $this->conn->exec($sql);
        
        echo "  ✅ Removed columns: " . implode(', ', $columnsToRemoveExisting) . "\n\n";
    }
    
    private function verifyChanges() {
        // Show new table structure
        echo "New advisers table structure:\n";
        $this->showTableStructure();
        
        // Count records
        $count = $this->conn->query("SELECT COUNT(*) FROM advisers")->fetchColumn();
        echo "Total advisers: {$count}\n";
        
        // Show sample data
        $stmt = $this->conn->query("SELECT * FROM advisers LIMIT 3");
        $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo "Sample data:\n";
        foreach ($samples as $adviser) {
            echo "  - ID: {$adviser['adviser_id']}, Name: {$adviser['adviser_name']}\n";
        }
        echo "\n";
    }
    
    public function rollback($backupTable = null) {
        try {
            if (!$backupTable) {
                // Find the most recent backup
                $stmt = $this->conn->query("SHOW TABLES LIKE 'advisers_backup_%'");
                $backups = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($backups)) {
                    throw new Exception("No backup table found for rollback");
                }
                
                // Get the most recent backup (last in alphabetical order)
                sort($backups);
                $backupTable = end($backups);
            }
            
            echo "Rolling back using backup table: {$backupTable}\n";
            
            // Start transaction
            $this->conn->beginTransaction();
            
            // Drop current advisers table
            $this->conn->exec("DROP TABLE advisers");
            
            // Recreate from backup
            $this->conn->exec("CREATE TABLE advisers AS SELECT * FROM {$backupTable}");
            
            // Recreate indexes
            $this->conn->exec("ALTER TABLE advisers ADD PRIMARY KEY (adviser_id)");
            $this->conn->exec("ALTER TABLE advisers MODIFY adviser_id INT AUTO_INCREMENT");
            
            $this->conn->commit();
            
            echo "✅ Rollback completed successfully!\n";
            
        } catch (Exception $e) {
            $this->conn->rollback();
            echo "❌ Rollback failed: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
}

// Run migration if called directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    try {
        $migration = new RemoveUnusedAdviserFields();
        
        // Check command line arguments
        if (isset($argv[1]) && $argv[1] === 'rollback') {
            $backupTable = isset($argv[2]) ? $argv[2] : null;
            $migration->rollback($backupTable);
        } else {
            $migration->migrate();
        }
        
    } catch (Exception $e) {
        echo "Error: " . $e->getMessage() . "\n";
        exit(1);
    }
}
?>
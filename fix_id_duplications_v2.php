<?php
include 'db.php';

echo "<h2>Database ID Duplication Fix - Version 2</h2>";

// Start transaction
$conn->autocommit(FALSE);

try {
    echo "<h3>Step 1: Disable Foreign Key Checks</h3>";
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");
    echo "✓ Foreign key checks disabled<br>";
    
    echo "<h3>Step 2: Creating Backup Tables</h3>";
    
    // Create backup tables
    $backupTables = [
        'student_backup' => 'student',
        'faculty_backup' => 'faculty', 
        'companyhr_backup' => 'companyhr',
        'supervisor_backup' => 'supervisor',
        'messages_backup' => 'messages'
    ];
    
    foreach ($backupTables as $backup => $original) {
        $conn->query("DROP TABLE IF EXISTS $backup");
        $result = $conn->query("CREATE TABLE $backup AS SELECT * FROM $original");
        if ($result) {
            echo "✓ Created backup: $backup<br>";
        } else {
            throw new Exception("Failed to create backup: $backup");
        }
    }
    
    // Also backup other related tables if they exist
    $otherTables = ['intern_applications', 'timecard', 'hr_requests', 'interviews', 'resumes', 'student_file_submissions'];
    foreach ($otherTables as $table) {
        $checkTable = $conn->query("SHOW TABLES LIKE '$table'");
        if ($checkTable->num_rows > 0) {
            $conn->query("DROP TABLE IF EXISTS {$table}_backup");
            $result = $conn->query("CREATE TABLE {$table}_backup AS SELECT * FROM $table");
            if ($result) {
                echo "✓ Created backup: {$table}_backup<br>";
            }
        }
    }
    
    echo "<h3>Step 3: Creating ID Mapping Tables</h3>";
    
    // Create temporary mapping tables to track old -> new ID mappings
    $conn->query("DROP TABLE IF EXISTS id_mappings");
    $conn->query("
        CREATE TABLE id_mappings (
            table_name VARCHAR(50),
            old_id INT,
            new_id INT,
            PRIMARY KEY (table_name, old_id)
        )
    ");
    
    echo "✓ Created ID mapping table<br>";
    
    echo "<h3>Step 4: Building ID Mappings</h3>";
    
    // Build all mappings first before updating anything
    $tables = [
        'student' => ['id_column' => 'student_id', 'start_range' => 1000],
        'faculty' => ['id_column' => 'faculty_id', 'start_range' => 2000],
        'companyhr' => ['id_column' => 'hr_id', 'start_range' => 3000],
        'supervisor' => ['id_column' => 'supervisor_id', 'start_range' => 4000]
    ];
    
    foreach ($tables as $table => $config) {
        $result = $conn->query("SELECT {$config['id_column']} FROM $table ORDER BY {$config['id_column']}");
        $newId = $config['start_range'];
        
        while ($row = $result->fetch_assoc()) {
            $oldId = $row[$config['id_column']];
            $conn->query("INSERT INTO id_mappings VALUES ('$table', $oldId, $newId)");
            echo "Mapping $table: $oldId -> $newId<br>";
            $newId++;
        }
    }
    
    echo "<h3>Step 5: Updating All Related Tables First</h3>";
    
    // Update messages table first
    echo "<strong>Updating messages table:</strong><br>";
    
    // Update sender_id based on sender_type
    $senderUpdates = [
        'student' => $conn->query("
            UPDATE messages m 
            JOIN id_mappings im ON m.sender_id = im.old_id AND im.table_name = 'student'
            SET m.sender_id = im.new_id 
            WHERE m.sender_type = 'student'
        "),
        'faculty' => $conn->query("
            UPDATE messages m 
            JOIN id_mappings im ON m.sender_id = im.old_id AND im.table_name = 'faculty'
            SET m.sender_id = im.new_id 
            WHERE m.sender_type = 'faculty'
        "),
        'companyhr' => $conn->query("
            UPDATE messages m 
            JOIN id_mappings im ON m.sender_id = im.old_id AND im.table_name = 'companyhr'
            SET m.sender_id = im.new_id 
            WHERE m.sender_type = 'companyhr'
        "),
        'supervisor' => $conn->query("
            UPDATE messages m 
            JOIN id_mappings im ON m.sender_id = im.old_id AND im.table_name = 'supervisor'
            SET m.sender_id = im.new_id 
            WHERE m.sender_type = 'supervisor'
        ")
    ];
    echo "✓ Updated sender_id in messages<br>";
    
    // Update receiver_id based on receiver_type
    $receiverUpdates = [
        'student' => $conn->query("
            UPDATE messages m 
            JOIN id_mappings im ON m.receiver_id = im.old_id AND im.table_name = 'student'
            SET m.receiver_id = im.new_id 
            WHERE m.receiver_type = 'student'
        "),
        'faculty' => $conn->query("
            UPDATE messages m 
            JOIN id_mappings im ON m.receiver_id = im.old_id AND im.table_name = 'faculty'
            SET m.receiver_id = im.new_id 
            WHERE m.receiver_type = 'faculty'
        "),
        'companyhr' => $conn->query("
            UPDATE messages m 
            JOIN id_mappings im ON m.receiver_id = im.old_id AND im.table_name = 'companyhr'
            SET m.receiver_id = im.new_id 
            WHERE m.receiver_type = 'companyhr'
        "),
        'supervisor' => $conn->query("
            UPDATE messages m 
            JOIN id_mappings im ON m.receiver_id = im.old_id AND im.table_name = 'supervisor'
            SET m.receiver_id = im.new_id 
            WHERE m.receiver_type = 'supervisor'
        ")
    ];
    echo "✓ Updated receiver_id in messages<br>";
    
    // Update other related tables
    $otherTableMappings = [
        'intern_applications' => ['student_id' => 'student'],
        'timecard' => ['student_id' => 'student'], 
        'hr_requests' => ['hr_id' => 'companyhr'],
        'interviews' => ['hr_id' => 'companyhr'],
        'resumes' => ['student_id' => 'student'],
        'student_file_submissions' => ['student_id' => 'student']
    ];
    
    foreach ($otherTableMappings as $table => $columns) {
        $checkTable = $conn->query("SHOW TABLES LIKE '$table'");
        if ($checkTable->num_rows > 0) {
            foreach ($columns as $column => $mappingTable) {
                $result = $conn->query("
                    UPDATE $table t 
                    JOIN id_mappings im ON t.$column = im.old_id AND im.table_name = '$mappingTable'
                    SET t.$column = im.new_id
                ");
                echo "✓ Updated $table.$column<br>";
            }
        }
    }
    
    echo "<h3>Step 6: Updating Primary Tables</h3>";
    
    // Now update the primary tables
    foreach ($tables as $table => $config) {
        echo "<strong>Updating $table IDs:</strong><br>";
        
        $result = $conn->query("
            UPDATE $table t 
            JOIN id_mappings im ON t.{$config['id_column']} = im.old_id AND im.table_name = '$table'
            SET t.{$config['id_column']} = im.new_id
        ");
        
        if ($result) {
            echo "✓ Updated $table table<br>";
        } else {
            throw new Exception("Failed to update $table: " . $conn->error);
        }
    }
    
    echo "<h3>Step 7: Setting AUTO_INCREMENT Values</h3>";
    
    // Set AUTO_INCREMENT to prevent future conflicts
    foreach ($tables as $table => $config) {
        $result = $conn->query("SELECT MAX({$config['id_column']}) as max_id FROM $table");
        $row = $result->fetch_assoc();
        $nextId = $row['max_id'] + 1;
        
        $conn->query("ALTER TABLE $table AUTO_INCREMENT = $nextId");
        echo "✓ Set $table AUTO_INCREMENT to $nextId<br>";
    }
    
    echo "<h3>Step 8: Re-enable Foreign Key Checks</h3>";
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");
    echo "✓ Foreign key checks re-enabled<br>";
    
    echo "<h3>Step 9: Verification</h3>";
    
    // Verify no more conflicts
    $all_ids = [];
    $tableConfigs = [
        'student' => 'student_id',
        'faculty' => 'faculty_id', 
        'companyhr' => 'hr_id',
        'supervisor' => 'supervisor_id'
    ];
    
    foreach ($tableConfigs as $table => $id_column) {
        $result = $conn->query("SELECT $id_column FROM $table");
        while ($row = $result->fetch_assoc()) {
            $id = $row[$id_column];
            if (!isset($all_ids[$id])) {
                $all_ids[$id] = [];
            }
            $all_ids[$id][] = $table;
        }
    }
    
    $conflicts = [];
    foreach ($all_ids as $id => $tables_using_id) {
        if (count($tables_using_id) > 1) {
            $conflicts[$id] = $tables_using_id;
        }
    }
    
    if (empty($conflicts)) {
        echo "✅ <strong>SUCCESS: No more ID conflicts found!</strong><br>";
        
        // Commit transaction
        $conn->commit();
        echo "<br>✅ <strong>All changes committed successfully!</strong><br>";
        
        // Show final ID ranges
        echo "<h3>Final ID Ranges:</h3>";
        foreach ($tableConfigs as $table => $id_column) {
            $result = $conn->query("SELECT MIN($id_column) as min_id, MAX($id_column) as max_id, COUNT(*) as count FROM $table");
            $row = $result->fetch_assoc();
            echo "$table: {$row['min_id']} - {$row['max_id']} ({$row['count']} records)<br>";
        }
        
    } else {
        throw new Exception("Still have conflicts: " . print_r($conflicts, true));
    }
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
    $conn->query("SET FOREIGN_KEY_CHECKS = 1"); // Re-enable foreign keys even on error
    echo "<br>❌ <strong>ERROR: " . $e->getMessage() . "</strong><br>";
    echo "All changes have been rolled back.<br>";
}

// Clean up
$conn->query("DROP TABLE IF EXISTS id_mappings");
$conn->autocommit(TRUE);

echo "<br><h3>Backup Tables Created:</h3>";
echo "If you need to restore, the following backup tables are available:<br>";
foreach ($backupTables as $backup => $original) {
    echo "- $backup (backup of $original)<br>";
}

$conn->close();
?>

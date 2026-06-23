<?php
include 'db.php';

echo "<h2>Database ID Duplication Fix</h2>";

// Start transaction
$conn->autocommit(FALSE);

try {
    echo "<h3>Step 1: Creating Backup Tables</h3>";
    
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
    
    echo "<h3>Step 2: Creating ID Mapping Tables</h3>";
    
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
    
    echo "<h3>Step 3: Updating Student IDs (1000+ range)</h3>";
    
    // Get current students and assign new IDs starting from 1000
    $result = $conn->query("SELECT student_id FROM student ORDER BY student_id");
    $students = [];
    while ($row = $result->fetch_assoc()) {
        $students[] = $row['student_id'];
    }
    
    $newStudentId = 1000;
    foreach ($students as $oldId) {
        // Insert mapping
        $conn->query("INSERT INTO id_mappings VALUES ('student', $oldId, $newStudentId)");
        
        // Update student table
        $conn->query("UPDATE student SET student_id = $newStudentId WHERE student_id = $oldId");
        
        echo "Student ID $oldId -> $newStudentId<br>";
        $newStudentId++;
    }
    
    echo "<h3>Step 4: Updating Faculty IDs (2000+ range)</h3>";
    
    // Get current faculty and assign new IDs starting from 2000
    $result = $conn->query("SELECT faculty_id FROM faculty ORDER BY faculty_id");
    $faculty = [];
    while ($row = $result->fetch_assoc()) {
        $faculty[] = $row['faculty_id'];
    }
    
    $newFacultyId = 2000;
    foreach ($faculty as $oldId) {
        // Insert mapping
        $conn->query("INSERT INTO id_mappings VALUES ('faculty', $oldId, $newFacultyId)");
        
        // Update faculty table
        $conn->query("UPDATE faculty SET faculty_id = $newFacultyId WHERE faculty_id = $oldId");
        
        echo "Faculty ID $oldId -> $newFacultyId<br>";
        $newFacultyId++;
    }
    
    echo "<h3>Step 5: Updating CompanyHR IDs (3000+ range)</h3>";
    
    // Get current companyhr and assign new IDs starting from 3000
    $result = $conn->query("SELECT hr_id FROM companyhr ORDER BY hr_id");
    $companyhr = [];
    while ($row = $result->fetch_assoc()) {
        $companyhr[] = $row['hr_id'];
    }
    
    $newHrId = 3000;
    foreach ($companyhr as $oldId) {
        // Insert mapping
        $conn->query("INSERT INTO id_mappings VALUES ('companyhr', $oldId, $newHrId)");
        
        // Update companyhr table
        $conn->query("UPDATE companyhr SET hr_id = $newHrId WHERE hr_id = $oldId");
        
        echo "CompanyHR ID $oldId -> $newHrId<br>";
        $newHrId++;
    }
    
    echo "<h3>Step 6: Updating Supervisor IDs (4000+ range)</h3>";
    
    // Get current supervisors and assign new IDs starting from 4000
    $result = $conn->query("SELECT supervisor_id FROM supervisor ORDER BY supervisor_id");
    $supervisors = [];
    while ($row = $result->fetch_assoc()) {
        $supervisors[] = $row['supervisor_id'];
    }
    
    $newSupervisorId = 4000;
    foreach ($supervisors as $oldId) {
        // Insert mapping
        $conn->query("INSERT INTO id_mappings VALUES ('supervisor', $oldId, $newSupervisorId)");
        
        // Update supervisor table
        $conn->query("UPDATE supervisor SET supervisor_id = $newSupervisorId WHERE supervisor_id = $oldId");
        
        echo "Supervisor ID $oldId -> $newSupervisorId<br>";
        $newSupervisorId++;
    }
    
    echo "<h3>Step 7: Updating Related Tables</h3>";
    
    // Update messages table
    echo "<strong>Updating messages table:</strong><br>";
    
    // Update sender_id based on sender_type
    $result = $conn->query("
        UPDATE messages m 
        JOIN id_mappings im ON m.sender_id = im.old_id 
        SET m.sender_id = im.new_id 
        WHERE im.table_name = m.sender_type
    ");
    echo "✓ Updated sender_id in messages<br>";
    
    // Update receiver_id based on receiver_type  
    $result = $conn->query("
        UPDATE messages m 
        JOIN id_mappings im ON m.receiver_id = im.old_id 
        SET m.receiver_id = im.new_id 
        WHERE im.table_name = m.receiver_type
    ");
    echo "✓ Updated receiver_id in messages<br>";
    
    // Check if other tables exist and update them
    $otherTables = [
        'intern_applications' => 'student_id',
        'timecard' => 'student_id', 
        'hr_requests' => 'hr_id',
        'interviews' => 'hr_id',
        'resumes' => 'student_id',
        'student_file_submissions' => 'student_id'
    ];
    
    foreach ($otherTables as $table => $idColumn) {
        $checkTable = $conn->query("SHOW TABLES LIKE '$table'");
        if ($checkTable->num_rows > 0) {
            // Determine which mapping to use based on column name
            $mappingTable = '';
            if (strpos($idColumn, 'student') !== false) $mappingTable = 'student';
            elseif (strpos($idColumn, 'faculty') !== false) $mappingTable = 'faculty';
            elseif (strpos($idColumn, 'hr') !== false) $mappingTable = 'companyhr';
            elseif (strpos($idColumn, 'supervisor') !== false) $mappingTable = 'supervisor';
            
            if ($mappingTable) {
                $result = $conn->query("
                    UPDATE $table t 
                    JOIN id_mappings im ON t.$idColumn = im.old_id 
                    SET t.$idColumn = im.new_id 
                    WHERE im.table_name = '$mappingTable'
                ");
                echo "✓ Updated $table.$idColumn<br>";
            }
        }
    }
    
    echo "<h3>Step 8: Setting AUTO_INCREMENT Values</h3>";
    
    // Set AUTO_INCREMENT to prevent future conflicts
    $conn->query("ALTER TABLE student AUTO_INCREMENT = $newStudentId");
    echo "✓ Set student AUTO_INCREMENT to $newStudentId<br>";
    
    $conn->query("ALTER TABLE faculty AUTO_INCREMENT = $newFacultyId");
    echo "✓ Set faculty AUTO_INCREMENT to $newFacultyId<br>";
    
    $conn->query("ALTER TABLE companyhr AUTO_INCREMENT = $newHrId");
    echo "✓ Set companyhr AUTO_INCREMENT to $newHrId<br>";
    
    $conn->query("ALTER TABLE supervisor AUTO_INCREMENT = $newSupervisorId");
    echo "✓ Set supervisor AUTO_INCREMENT to $newSupervisorId<br>";
    
    echo "<h3>Step 9: Verification</h3>";
    
    // Verify no more conflicts
    $all_ids = [];
    $tables = [
        'student' => 'student_id',
        'faculty' => 'faculty_id', 
        'companyhr' => 'hr_id',
        'supervisor' => 'supervisor_id'
    ];
    
    foreach ($tables as $table => $id_column) {
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
        foreach ($tables as $table => $id_column) {
            $result = $conn->query("SELECT MIN($id_column) as min_id, MAX($id_column) as max_id FROM $table");
            $row = $result->fetch_assoc();
            echo "$table: {$row['min_id']} - {$row['max_id']}<br>";
        }
        
    } else {
        throw new Exception("Still have conflicts: " . print_r($conflicts, true));
    }
    
} catch (Exception $e) {
    // Rollback on error
    $conn->rollback();
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

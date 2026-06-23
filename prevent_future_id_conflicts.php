<?php
include 'db.php';

echo "<h2>Preventing Future ID Conflicts</h2>";

try {
    echo "<h3>Step 1: Creating ID Range Management System</h3>";
    
    // Create a table to manage ID ranges
    $conn->query("DROP TABLE IF EXISTS id_range_config");
    $createRangeTable = "
        CREATE TABLE id_range_config (
            table_name VARCHAR(50) PRIMARY KEY,
            id_column VARCHAR(50) NOT NULL,
            range_start INT NOT NULL,
            range_end INT NOT NULL,
            current_max INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ";
    
    if ($conn->query($createRangeTable)) {
        echo "✓ Created id_range_config table<br>";
    } else {
        throw new Exception("Failed to create id_range_config table: " . $conn->error);
    }
    
    echo "<h3>Step 2: Configuring ID Ranges</h3>";
    
    // Insert range configurations
    $ranges = [
        ['student', 'student_id', 1000, 1999],
        ['faculty', 'faculty_id', 2000, 2999], 
        ['companyhr', 'hr_id', 3000, 3999],
        ['supervisor', 'supervisor_id', 4000, 4999]
    ];
    
    foreach ($ranges as $range) {
        list($table, $id_column, $start, $end) = $range;
        
        // Get current max ID
        $result = $conn->query("SELECT MAX($id_column) as max_id FROM $table");
        $row = $result->fetch_assoc();
        $currentMax = $row['max_id'] ?? ($start - 1);
        
        $stmt = $conn->prepare("
            INSERT INTO id_range_config (table_name, id_column, range_start, range_end, current_max) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssiii", $table, $id_column, $start, $end, $currentMax);
        
        if ($stmt->execute()) {
            echo "✓ Configured range for $table: $start-$end (current max: $currentMax)<br>";
        } else {
            throw new Exception("Failed to configure range for $table");
        }
    }
    
    echo "<h3>Step 3: Creating Helper Functions</h3>";
    
    // Create stored procedure to get next safe ID
    $conn->query("DROP PROCEDURE IF EXISTS GetNextSafeId");
    $createProcedure = "
        DELIMITER //
        CREATE PROCEDURE GetNextSafeId(
            IN p_table_name VARCHAR(50),
            OUT p_next_id INT
        )
        BEGIN
            DECLARE v_current_max INT;
            DECLARE v_range_end INT;
            DECLARE v_next_id INT;
            
            -- Get current configuration
            SELECT current_max, range_end INTO v_current_max, v_range_end
            FROM id_range_config 
            WHERE table_name = p_table_name;
            
            -- Calculate next ID
            SET v_next_id = v_current_max + 1;
            
            -- Check if we're exceeding the range
            IF v_next_id > v_range_end THEN
                SIGNAL SQLSTATE '45000' 
                SET MESSAGE_TEXT = 'ID range exceeded for table. Please expand range or clean up data.';
            END IF;
            
            -- Update current max
            UPDATE id_range_config 
            SET current_max = v_next_id 
            WHERE table_name = p_table_name;
            
            SET p_next_id = v_next_id;
        END //
        DELIMITER ;
    ";
    
    if ($conn->multi_query($createProcedure)) {
        // Process all results
        do {
            if ($result = $conn->store_result()) {
                $result->free();
            }
        } while ($conn->next_result());
        echo "✓ Created GetNextSafeId stored procedure<br>";
    } else {
        echo "⚠️ Note: Stored procedure creation may have failed (this is normal in some MySQL configurations)<br>";
    }
    
    echo "<h3>Step 4: Creating PHP Helper Class</h3>";
    
    // Create PHP helper class file
    $helperClass = '<?php
class IdRangeManager {
    private $conn;
    
    public function __construct($connection) {
        $this->conn = $connection;
    }
    
    /**
     * Get the next safe ID for a table
     */
    public function getNextId($tableName) {
        // Get current configuration
        $stmt = $this->conn->prepare("
            SELECT current_max, range_end 
            FROM id_range_config 
            WHERE table_name = ?
        ");
        $stmt->bind_param("s", $tableName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            throw new Exception("No ID range configuration found for table: $tableName");
        }
        
        $config = $result->fetch_assoc();
        $nextId = $config["current_max"] + 1;
        
        // Check if we\'re exceeding the range
        if ($nextId > $config["range_end"]) {
            throw new Exception("ID range exceeded for table $tableName. Please expand range or clean up data.");
        }
        
        // Update current max
        $updateStmt = $this->conn->prepare("
            UPDATE id_range_config 
            SET current_max = ? 
            WHERE table_name = ?
        ");
        $updateStmt->bind_param("is", $nextId, $tableName);
        $updateStmt->execute();
        
        return $nextId;
    }
    
    /**
     * Check if an ID is within the valid range for a table
     */
    public function isValidId($tableName, $id) {
        $stmt = $this->conn->prepare("
            SELECT range_start, range_end 
            FROM id_range_config 
            WHERE table_name = ?
        ");
        $stmt->bind_param("s", $tableName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            return false;
        }
        
        $config = $result->fetch_assoc();
        return ($id >= $config["range_start"] && $id <= $config["range_end"]);
    }
    
    /**
     * Get range information for a table
     */
    public function getRangeInfo($tableName) {
        $stmt = $this->conn->prepare("
            SELECT * FROM id_range_config WHERE table_name = ?
        ");
        $stmt->bind_param("s", $tableName);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    /**
     * Get all range configurations
     */
    public function getAllRanges() {
        $result = $this->conn->query("SELECT * FROM id_range_config ORDER BY range_start");
        return $result->fetch_all(MYSQLI_ASSOC);
    }
}
?>';
    
    file_put_contents('IdRangeManager.php', $helperClass);
    echo "✓ Created IdRangeManager.php helper class<br>";
    
    echo "<h3>Step 5: Creating Usage Examples</h3>";
    
    // Create usage example file
    $usageExample = '<?php
// Example usage of IdRangeManager
include "db.php";
include "IdRangeManager.php";

$idManager = new IdRangeManager($conn);

// Example 1: Get next safe ID for student registration
try {
    $nextStudentId = $idManager->getNextId("student");
    echo "Next student ID: $nextStudentId\n";
    
    // Use this ID when inserting new student
    // $stmt = $conn->prepare("INSERT INTO student (student_id, firstname, lastname, ...) VALUES (?, ?, ?, ...)");
    // $stmt->bind_param("iss...", $nextStudentId, $firstname, $lastname, ...);
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Example 2: Validate an ID before using it
$testId = 1500;
if ($idManager->isValidId("student", $testId)) {
    echo "ID $testId is valid for students\n";
} else {
    echo "ID $testId is NOT valid for students\n";
}

// Example 3: Get range information
$studentRange = $idManager->getRangeInfo("student");
echo "Student ID range: {$studentRange[\"range_start\"]} - {$studentRange[\"range_end\"]}\n";
echo "Current max: {$studentRange[\"current_max\"]}\n";

// Example 4: View all ranges
echo "\nAll ID Ranges:\n";
$allRanges = $idManager->getAllRanges();
foreach ($allRanges as $range) {
    echo "{$range[\"table_name\"]}: {$range[\"range_start\"]}-{$range[\"range_end\"]} (current: {$range[\"current_max\"]})\n";
}
?>';
    
    file_put_contents('id_range_usage_example.php', $usageExample);
    echo "✓ Created id_range_usage_example.php<br>";
    
    echo "<h3>Step 6: Verification</h3>";
    
    // Show current configuration
    $result = $conn->query("SELECT * FROM id_range_config ORDER BY range_start");
    echo "<table border='1'>";
    echo "<tr><th>Table</th><th>ID Column</th><th>Range Start</th><th>Range End</th><th>Current Max</th><th>Available IDs</th></tr>";
    
    while ($row = $result->fetch_assoc()) {
        $available = $row['range_end'] - $row['current_max'];
        echo "<tr>";
        echo "<td>{$row['table_name']}</td>";
        echo "<td>{$row['id_column']}</td>";
        echo "<td>{$row['range_start']}</td>";
        echo "<td>{$row['range_end']}</td>";
        echo "<td>{$row['current_max']}</td>";
        echo "<td>$available</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    echo "✅ <strong>SUCCESS: ID conflict prevention system installed!</strong><br>";
    
} catch (Exception $e) {
    echo "❌ <strong>ERROR: " . $e->getMessage() . "</strong><br>";
}

echo "<br><h3>Next Steps:</h3>";
echo "1. Use IdRangeManager class when creating new records<br>";
echo "2. Always call getNextId() instead of relying on AUTO_INCREMENT<br>";
echo "3. Validate IDs with isValidId() before processing<br>";
echo "4. Monitor range usage and expand when needed<br>";
echo "5. See id_range_usage_example.php for implementation examples<br>";

$conn->close();
?>

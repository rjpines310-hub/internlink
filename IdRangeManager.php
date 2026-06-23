<?php
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
        
        // Check if we're exceeding the range
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
?>

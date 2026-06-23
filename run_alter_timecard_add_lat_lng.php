<?php
include 'db.php';

$sql = "
-- Add latitude and longitude columns to timecard table for geofencing
ALTER TABLE `timecard` ADD COLUMN `latitude` DECIMAL(10,8) NULL AFTER `location_id`;
ALTER TABLE `timecard` ADD COLUMN `longitude` DECIMAL(11,8) NULL AFTER `latitude`;
";

if ($conn->multi_query($sql)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Table timecard altered successfully to add latitude and longitude columns.";
} else {
    echo "Error altering table: " . $conn->error;
}

$conn->close();
?>

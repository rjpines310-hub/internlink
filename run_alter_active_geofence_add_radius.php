<?php
include 'db.php';

$sql = "
-- Add radius column to active_geofence table
ALTER TABLE `active_geofence` ADD COLUMN `radius` INT(11) NOT NULL DEFAULT 100 COMMENT 'Geofence radius in meters'
";

if ($conn->query($sql) === TRUE) {
    echo "Successfully added radius column to active_geofence table.\n";
} else {
    echo "Error adding radius column: " . $conn->error . "\n";
}

$conn->close();
?>

<?php
include 'db.php';

$sql = "ALTER TABLE `active_geofence` DROP INDEX `unique_active_per_company`;";

if ($conn->query($sql) === TRUE) {
    echo "Successfully dropped unique constraint on active_geofence table.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

$conn->close();
?>

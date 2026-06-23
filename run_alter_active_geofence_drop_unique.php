<?php
include 'db.php';

$sql = file_get_contents('alter_active_geofence_drop_unique.sql');

if ($conn->query($sql) === TRUE) {
    echo "Successfully dropped unique constraint on active_geofence table.\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

$conn->close();
?>

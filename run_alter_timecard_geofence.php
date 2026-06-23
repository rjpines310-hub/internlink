<?php
include 'db.php';

$sql = file_get_contents('alter_timecard_geofence.sql');

if ($conn->multi_query($sql)) {
    echo "Timecard table altered successfully for geofencing.\n";
    do {
        // Consume all results
    } while ($conn->next_result());
} else {
    echo "Error altering timecard table: " . $conn->error . "\n";
}

$conn->close();
?>

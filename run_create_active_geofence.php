<?php
include 'db.php';

$sql = file_get_contents('create_active_geofence.sql');

if ($conn->multi_query($sql)) {
    echo "Active geofence table created successfully.\n";
    do {
        // Consume all results
    } while ($conn->next_result());
} else {
    echo "Error creating active geofence table: " . $conn->error . "\n";
}

$conn->close();
?>

<?php
include 'db.php';

$sql = file_get_contents('create_geofence_locations.sql');

if ($conn->multi_query($sql)) {
    echo "Geofence locations table created successfully.\n";
    do {
        // Consume all results
    } while ($conn->next_result());
} else {
    echo "Error creating geofence locations table: " . $conn->error . "\n";
}

$conn->close();
?>

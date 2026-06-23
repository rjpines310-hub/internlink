<?php
include 'db.php';

$sql = file_get_contents('alter_active_geofence_unique_set_by.sql');

if ($conn->multi_query($sql)) {
    echo "Active geofence unique constraint altered successfully.\n";
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
} else {
    echo "Error altering active geofence unique constraint: " . $conn->error . "\n";
}

$conn->close();
?>

<?php
include 'db.php';
$sql = file_get_contents('alter_geofence_locations_add_hr_id.sql');
if ($conn->multi_query($sql)) {
    echo 'Table altered successfully.';
} else {
    echo 'Error altering table: ' . $conn->error;
}
$conn->close();
?>

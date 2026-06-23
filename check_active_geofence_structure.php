<?php
include 'db.php';

$result = $conn->query("SHOW CREATE TABLE active_geofence");

if ($result) {
    $row = $result->fetch_assoc();
    echo "Table structure:\n";
    echo $row['Create Table'] . "\n";
} else {
    echo "Error: " . $conn->error . "\n";
}

$conn->close();
?>

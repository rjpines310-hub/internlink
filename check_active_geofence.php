<?php
include 'db.php';

$result = $conn->query("DESCRIBE active_geofence");
if ($result) {
    echo "active_geofence table schema:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . " - " . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . "\n";
    }
} else {
    echo "Error: " . $conn->error;
}
$conn->close();
?>

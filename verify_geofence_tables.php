<?php
include 'db.php';

$tables = ['geofence_locations', 'timecard', 'active_geofence'];

foreach ($tables as $table) {
    echo "Table: $table\n";
    $result = $conn->query("DESCRIBE $table");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            echo "  " . $row['Field'] . " " . $row['Type'] . " " . ($row['Null'] == 'NO' ? 'NOT NULL' : 'NULL') . " " . ($row['Key'] ? $row['Key'] : '') . "\n";
        }
    } else {
        echo "  Error: " . $conn->error . "\n";
    }
    echo "\n";
}

$conn->close();
?>

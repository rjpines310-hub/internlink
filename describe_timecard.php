<?php
include 'db.php';

$result = $conn->query("DESCRIBE timecard");
if ($result) {
    echo "Timecard table structure:\n";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Null'] . " - " . $row['Key'] . " - " . $row['Default'] . " - " . $row['Extra'] . "\n";
    }
} else {
    echo "Error describing timecard table: " . $conn->error;
}

$conn->close();
?>

<?php
include 'db.php';

$result = $conn->query("SHOW TABLES LIKE 'manual_time_requests'");
if ($result->num_rows > 0) {
    echo "Table 'manual_time_requests' exists.";
} else {
    echo "Table 'manual_time_requests' does not exist.";
}

$conn->close();
?>

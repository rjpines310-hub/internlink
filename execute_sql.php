<?php
include 'db.php';

$sql = file_get_contents('alter_sections_add_ojt_hours.sql');

if ($conn->query($sql) === TRUE) {
    echo "SQL executed successfully";
} else {
    echo "Error executing SQL: " . $conn->error;
}

$conn->close();
?>

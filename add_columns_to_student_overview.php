<?php
require_once 'db.php';

// Use the $conn object directly from db.php
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "ALTER TABLE student_overview DROP COLUMN employment_status_id;";

if ($conn->query($sql) === TRUE) {
    echo "Column 'employment_status_id' dropped from student_overview successfully.";
} else {
    echo "Error dropping column 'employment_status_id': " . $conn->error;
}

$conn->close();
?>

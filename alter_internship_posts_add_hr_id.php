<?php
include 'db.php';

$sql = "ALTER TABLE internship_posts ADD COLUMN hr_id INT(11) NULL AFTER posted_by";

if ($conn->query($sql) === TRUE) {
    echo "Column 'hr_id' added to table 'internship_posts' successfully.";
} else {
    echo "Error altering table: " . $conn->error;
}

$conn->close();
?>

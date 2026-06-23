<?php
session_start();
include 'db.php';

// This script should ideally be run by an admin or HR user, or as a one-off migration.
// For simplicity, we'll assume it's run by an authorized user.

$sql = "UPDATE internship_posts SET hr_id = posted_by WHERE hr_id IS NULL AND posted_by IS NOT NULL";

if ($conn->query($sql) === TRUE) {
    echo "Existing internship posts updated successfully with hr_id.";
} else {
    echo "Error updating existing internship posts: " . $conn->error;
}

$conn->close();
?>

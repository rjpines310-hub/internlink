<?php
include 'db.php';

$sql = "ALTER TABLE intern_applications MODIFY COLUMN status ENUM('Pending', 'Accepted', 'Rejected', 'For Interview', 'Offer Sent') DEFAULT 'Pending'";

if ($conn->query($sql) === TRUE) {
    echo "Table intern_applications altered successfully to add 'Offer Sent' status.";
} else {
    echo "Error altering table: " . $conn->error;
}

$conn->close();
?>

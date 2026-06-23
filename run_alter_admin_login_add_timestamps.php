<?php
include 'db.php';

$sql = file_get_contents('alter_admin_login_add_timestamps.sql');

if ($conn->query($sql)) {
    echo "Admin login table altered successfully.\n";
} else {
    echo "Error altering admin login table: " . $conn->error . "\n";
}

$conn->close();
?>

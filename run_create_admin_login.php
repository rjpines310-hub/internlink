<?php
include 'db.php';

$sql = file_get_contents('create_admin_login.sql');

if ($conn->multi_query($sql)) {
    echo "Admin login table created successfully.\n";
    do {
        // Consume all results
    } while ($conn->next_result());
} else {
    echo "Error creating admin login table: " . $conn->error . "\n";
}

$conn->close();
?>

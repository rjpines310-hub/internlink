<?php
include 'db.php';

$sql = file_get_contents('create_invitations_table.sql');

// Check if table exists
$result = $conn->query("SHOW TABLES LIKE 'invitations'");
if ($result->num_rows == 0) {
    if ($conn->query($sql) === TRUE) {
        echo "Table created successfully.";
    } else {
        echo "Error creating table: " . $conn->error;
    }
} else {
    echo "Table 'invitations' already exists.";
}

$conn->close();
?>

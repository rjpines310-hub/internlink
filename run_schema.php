<?php
include 'db.php';

$sql = file_get_contents('messaging_database_schema.sql');

if ($conn->multi_query($sql)) {
    echo "Schema executed successfully.\n";
    do {
        // Consume all results
    } while ($conn->next_result());
} else {
    echo "Error executing schema: " . $conn->error . "\n";
}

$conn->close();
?>

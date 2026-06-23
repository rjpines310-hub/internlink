<?php
include 'db.php';

$result = $conn->query("SHOW TABLES LIKE 'messages'");
if ($result->num_rows > 0) {
    echo "Messages table exists.\n";
} else {
    echo "Messages table does not exist. Applying schema...\n";
    $sql = file_get_contents('messaging_database_schema.sql');
    if ($conn->multi_query($sql)) {
        echo "Schema applied successfully.\n";
    } else {
        echo "Error applying schema: " . $conn->error . "\n";
    }
}
?>

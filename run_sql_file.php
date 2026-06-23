<?php
include 'db.php';

$sql_file = 'alter_student_overview_to_decimal.sql';

if (!file_exists($sql_file)) {
    die("Error: SQL file not found at " . $sql_file);
}

$sql_content = file_get_contents($sql_file);

if ($conn->multi_query($sql_content)) {
    do {
        // Store first result set
        if ($result = $conn->store_result()) {
            $result->free();
        }
        // While there are more results (MySQLi > 5.0.3)
    } while ($conn->next_result());
    echo "SQL file '" . $sql_file . "' executed successfully.\n";
} else {
    echo "Error executing SQL file '" . $sql_file . "': " . $conn->error . "\n";
}

$conn->close();
?>

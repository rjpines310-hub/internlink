<?php
include 'db.php';

// Get table name from command line argument
if (isset($argv[1])) {
    $tableName = $argv[1];
} else {
    echo "Usage: php get_table_schema.php <table_name>\n";
    exit();
}

// Validate table name to prevent SQL injection
if (!preg_match('/^[a-zA-Z0-9_]+$/', $tableName)) {
    echo "Invalid table name.\n";
    exit();
}

$sql = "SHOW CREATE TABLE `" . $tableName . "`";
$result = $conn->query($sql);

if ($result) {
    $row = $result->fetch_assoc();
    echo "Schema for table '{$tableName}':\n";
    echo $row['Create Table'] . "\n";
} else {
    echo "Error fetching schema for table '{$tableName}': " . $conn->error . "\n";
}

$conn->close();
?>

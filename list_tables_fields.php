<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get all tables
$result = $conn->query("SHOW TABLES");
$tables = [];
while ($row = $result->fetch_array()) {
    $tables[] = $row[0];
}

// For each table, get fields
$database_structure = [];
foreach ($tables as $table) {
    $fields_result = $conn->query("SHOW COLUMNS FROM `$table`");
    $fields = [];
    while ($field_row = $fields_result->fetch_assoc()) {
        $fields[] = $field_row['Field'];
    }
    $database_structure[$table] = $fields;
}

// Output the structure
echo "Database: capstone\n\n";
foreach ($database_structure as $table => $fields) {
    echo "Table: $table\n";
    echo "Fields: " . implode(', ', $fields) . "\n\n";
}

$conn->close();
?>

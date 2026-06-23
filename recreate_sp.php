<?php
include 'db.php';

$sql_file = 'calculate_student_overview_sp.sql';

if (!file_exists($sql_file)) {
    die("Error: SQL file not found at " . $sql_file);
}

$sql_content = file_get_contents($sql_file);

// Drop the existing stored procedure if it exists
$drop_sp_sql = "DROP PROCEDURE IF EXISTS CalculateStudentOverview;";
if ($conn->query($drop_sp_sql) === TRUE) {
    echo "Stored procedure 'CalculateStudentOverview' dropped successfully (if it existed).\n";
} else {
    echo "Error dropping stored procedure: " . $conn->error . "\n";
}

// Recreate the stored procedure
if ($conn->multi_query($sql_content)) {
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->next_result());
    echo "Stored procedure '" . $sql_file . "' recreated successfully.\n";
} else {
    echo "Error recreating stored procedure '" . $sql_file . "': " . $conn->error . "\n";
}

$conn->close();
?>

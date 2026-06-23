<?php
include 'db.php';

// Read the SQL file
$sql_file = 'create_student_overview_triggers.sql';
$sql = file_get_contents($sql_file);

if ($conn->multi_query($sql)) {
    echo "Triggers and stored procedures created successfully.";
    // To fetch results from the first query and allow subsequent queries to run
    do {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    } while ($conn->more_results() && $conn->next_result());
} else {
    echo "Error creating triggers: " . $conn->error;
}

$conn->close();
?>

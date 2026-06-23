<?php
include 'db.php';

$sql_file = 'create_student_overview.sql';
$sql = file_get_contents($sql_file);

if ($conn->multi_query($sql)) {
    echo "Tables `employment_status` and `student_overview` created successfully.";
    // To fetch results from the first query and allow subsequent queries to run
    while ($conn->more_results() && $conn->next_result()) {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
} else {
    echo "Error creating tables: " . $conn->error;
}

$conn->close();
?>

<?php
include 'db.php';

$sql_file = 'recreate_student_overview.sql';
$sql = file_get_contents($sql_file);

if ($conn->multi_query($sql)) {
    echo "Tables dropped and `student_overview` table recreated successfully.";
    // To fetch results from the first query and allow subsequent queries to run
    while ($conn->more_results() && $conn->next_result()) {
        if ($result = $conn->store_result()) {
            $result->free();
        }
    }
} else {
    echo "Error recreating tables: " . $conn->error;
}

$conn->close();
?>

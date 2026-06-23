<?php
include 'db.php';

$sql = file_get_contents('alter_student_add_location.sql');

if ($conn->multi_query($sql)) {
    echo "Student table altered to add location_id successfully.\n";
    do {
        // Consume all results
    } while ($conn->next_result());
} else {
    echo "Error altering student table: " . $conn->error . "\n";
}

$conn->close();
?>

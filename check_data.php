<?php
include 'db.php';

$result = $conn->query("SELECT s.firstname, s.lastname, so.attendance, so.performance, so.file_submissions FROM student s LEFT JOIN student_overview so ON s.student_id = so.student_id");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        echo $row['firstname'] . ' ' . $row['lastname'] . ': attendance=' . $row['attendance'] . ', performance=' . $row['performance'] . ', file_submissions=' . $row['file_submissions'] . "\n";
    }
} else {
    echo "No students found.\n";
}

$conn->close();
?>

<?php
include 'db.php';

// Fetch some student IDs
$result = $conn->query("SELECT student_id FROM student LIMIT 5");
$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = $row['student_id'];
}

// Fetch some supervisor IDs
$result = $conn->query("SELECT supervisor_id FROM supervisor LIMIT 5");
$supervisors = [];
while ($row = $result->fetch_assoc()) {
    $supervisors[] = $row['supervisor_id'];
}

echo "Available student_ids: " . implode(', ', $students) . "\n";
echo "Available supervisor_ids: " . implode(', ', $supervisors) . "\n";

$conn->close();
?>

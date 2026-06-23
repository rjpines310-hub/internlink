<?php
include 'db.php';

$result = $conn->query("SHOW TABLES LIKE 'student_ojt'");
if ($result->num_rows > 0) {
    echo "student_ojt table exists.<br>";
} else {
    echo "student_ojt table does NOT exist.<br>";
}

$conn->close();
?>

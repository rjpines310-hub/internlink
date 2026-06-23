<?php
include 'db.php';

echo "<h2>Adding student_id column to internship_posts table</h2>";

$sql = "ALTER TABLE internship_posts ADD COLUMN student_id INT(11) DEFAULT NULL";

if ($conn->query($sql) === TRUE) {
    echo "✓ Successfully added student_id column to internship_posts table.<br>";
} else {
    echo "❌ Error adding column: " . $conn->error . "<br>";
}

$conn->close();
?>

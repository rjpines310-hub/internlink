<?php
include 'db.php';

echo "<h2>Adding hr_id column to student table</h2>";

$sql = "ALTER TABLE student ADD COLUMN hr_id INT(11) DEFAULT NULL";

if ($conn->query($sql) === TRUE) {
    echo "✓ Successfully added hr_id column to student table.<br>";
} else {
    echo "❌ Error adding column: " . $conn->error . "<br>";
}

$conn->close();
?>

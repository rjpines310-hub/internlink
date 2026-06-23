<?php
include 'db.php';

echo "<h2>Altering Student Table Employment Status</h2>";

// ALTER TABLE to set default 'pending' for employment_status
$sql = "ALTER TABLE student MODIFY COLUMN employment_status ENUM('pending','hired','completed') NOT NULL DEFAULT 'pending'";

if ($conn->query($sql) === TRUE) {
    echo "✓ Successfully altered employment_status field to have default 'pending'.<br>";
} else {
    echo "❌ Error altering table: " . $conn->error . "<br>";
}

// Check if student_ojt table exists (should not)
$result = $conn->query("SHOW TABLES LIKE 'student_ojt'");
if ($result->num_rows > 0) {
    echo "⚠️ Warning: student_ojt table still exists. You may need to drop it.<br>";
} else {
    echo "✓ Confirmed: student_ojt table does not exist, so no connection to it.<br>";
}

$conn->close();
?>

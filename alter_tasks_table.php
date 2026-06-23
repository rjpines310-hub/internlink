<?php
include 'db.php';

echo "<h2>Altering Tasks Table</h2>";

// Enable event scheduler
if ($conn->query("SET GLOBAL event_scheduler = ON")) {
    echo "✓ Event scheduler enabled<br>";
} else {
    echo "❌ Failed to enable event scheduler: " . $conn->error . "<br>";
}

// Modify status enum
$sql1 = "ALTER TABLE tasks MODIFY status ENUM('assigned', 'submitted', 'completed', 'missed') NOT NULL DEFAULT 'assigned'";
if ($conn->query($sql1)) {
    echo "✓ Status enum updated to include 'missed'<br>";
} else {
    echo "❌ Failed to update status: " . $conn->error . "<br>";
}

// Rename verified_at to checked_at
$sql2 = "ALTER TABLE tasks CHANGE verified_at checked_at TIMESTAMP NULL DEFAULT NULL";
if ($conn->query($sql2)) {
    echo "✓ Renamed verified_at to checked_at<br>";
} else {
    echo "❌ Failed to rename field: " . $conn->error . "<br>";
}

// Add submitted_at field
$sql3 = "ALTER TABLE tasks ADD submitted_at TIMESTAMP NULL DEFAULT NULL COMMENT 'Timestamp when task was submitted' AFTER assigned_at";
if ($conn->query($sql3)) {
    echo "✓ Added submitted_at field<br>";
} else {
    echo "❌ Failed to add submitted_at: " . $conn->error . "<br>";
}

// Add score field
$sql4 = "ALTER TABLE tasks ADD score INT(3) UNSIGNED DEFAULT NULL COMMENT 'Score out of 100' AFTER checked_at";
if ($conn->query($sql4)) {
    echo "✓ Added score field<br>";
} else {
    echo "❌ Failed to add score: " . $conn->error . "<br>";
}

// Drop existing event if exists
$conn->query("DROP EVENT IF EXISTS update_missed_tasks");

// Create event
$sql5 = "
CREATE EVENT update_missed_tasks
ON SCHEDULE EVERY 1 DAY STARTS CURRENT_TIMESTAMP
DO
  UPDATE tasks SET status = 'missed' WHERE status = 'assigned' AND due_date < CURDATE()
";
if ($conn->query($sql5)) {
    echo "✓ Created event for automatic missed status<br>";
} else {
    echo "❌ Failed to create event: " . $conn->error . "<br>";
}

echo "<br><h3>Table Structure After Changes:</h3>";
$result = $conn->query("DESCRIBE tasks");
if ($result) {
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . $row['Default'] . "</td>";
        echo "<td>" . $row['Extra'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

$conn->close();
?>

<?php
include 'db.php';

echo "<h2>Foreign Keys referencing student_ojt table</h2>";

$result = $conn->query("SELECT
    TABLE_NAME,
    COLUMN_NAME,
    CONSTRAINT_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME
FROM
    INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
    REFERENCED_TABLE_SCHEMA = DATABASE() AND
    REFERENCED_TABLE_NAME = 'student_ojt'");

if ($result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>Table</th><th>Column</th><th>Constraint</th><th>Referenced Table</th><th>Referenced Column</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['TABLE_NAME'] . "</td>";
        echo "<td>" . $row['COLUMN_NAME'] . "</td>";
        echo "<td>" . $row['CONSTRAINT_NAME'] . "</td>";
        echo "<td>" . $row['REFERENCED_TABLE_NAME'] . "</td>";
        echo "<td>" . $row['REFERENCED_COLUMN_NAME'] . "</td>";
        echo "</tr>";
    }
    echo "</table><br>";
} else {
    echo "No foreign keys referencing student_ojt table found.<br>";
}

$conn->close();
?>

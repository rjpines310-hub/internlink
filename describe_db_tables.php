<?php
include 'db.php';

$tables = ['announcements', 'announcement_audiences', 'hr_requests'];

foreach ($tables as $table) {
    echo "<h2>Table: $table</h2>";
    $result = $conn->query("DESCRIBE $table");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            foreach ($row as $col_val) { // Changed $col to $col_val to avoid conflict
                echo "<td>" . htmlspecialchars($col_val) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Error describing table $table: " . htmlspecialchars($conn->error) . "</p>";
    }
    echo "<br>";

    // Get CREATE TABLE statement
    $createTableResult = $conn->query("SHOW CREATE TABLE $table");
    if ($createTableResult && $createTableResult->num_rows > 0) {
        $row = $createTableResult->fetch_assoc();
        echo "<h3>CREATE TABLE $table:</h3>";
        echo "<pre>" . htmlspecialchars($row['Create Table']) . "</pre>";
    } else {
        echo "<p>Error getting CREATE TABLE for $table: " . htmlspecialchars($conn->error) . "</p>";
    }
    echo "<br>";
}

$conn->close();
?>

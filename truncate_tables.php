<?php
include 'db.php';

echo "<h2>Truncating All Tables Except 'sections' and 'faculty'</h2>";

try {
    // Get all table names from the database
    $result = $conn->query("SHOW TABLES");
    $allTables = [];
    while ($row = $result->fetch_array()) {
        $allTables[] = $row[0];
    }

    // Tables to exclude
    $excludeTables = ['sections', 'faculty'];

    // Tables to truncate
    $tablesToTruncate = array_diff($allTables, $excludeTables);

    echo "<h3>Tables to truncate:</h3>";
    echo "<ul>";
    foreach ($tablesToTruncate as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";

    // Disable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 0");

    // Truncate each table
    foreach ($tablesToTruncate as $table) {
        $conn->query("TRUNCATE TABLE `$table`");
        echo "✓ Truncated table: $table<br>";
    }

    // Re-enable foreign key checks
    $conn->query("SET FOREIGN_KEY_CHECKS = 1");

    // Reset current_max in id_range_config for truncated tables
    $resetTables = ['student', 'supervisor', 'companyhr']; // Only these have id_range_config
    foreach ($resetTables as $table) {
        if (in_array($table, $tablesToTruncate)) {
            $stmt = $conn->prepare("
                UPDATE id_range_config
                SET current_max = range_start - 1
                WHERE table_name = ?
            ");
            $stmt->bind_param("s", $table);
            $stmt->execute();
            echo "✓ Reset current_max for $table<br>";
        }
    }

    echo "<h3>Verification - Updated id_range_config:</h3>";

    // Show updated configuration
    $result = $conn->query("SELECT * FROM id_range_config ORDER BY range_start");
    echo "<table border='1'>";
    echo "<tr><th>Table</th><th>ID Column</th><th>Range Start</th><th>Range End</th><th>Current Max</th><th>Available IDs</th></tr>";

    while ($row = $result->fetch_assoc()) {
        $available = $row['range_end'] - $row['current_max'];
        echo "<tr>";
        echo "<td>{$row['table_name']}</td>";
        echo "<td>{$row['id_column']}</td>";
        echo "<td>{$row['range_start']}</td>";
        echo "<td>{$row['range_end']}</td>";
        echo "<td>{$row['current_max']}</td>";
        echo "<td>$available</td>";
        echo "</tr>";
    }
    echo "</table><br>";

    echo "✅ <strong>SUCCESS: All tables except 'sections' and 'faculty' have been truncated!</strong><br>";

} catch (Exception $e) {
    echo "❌ <strong>ERROR: " . $e->getMessage() . "</strong><br>";
}

$conn->close();
?>

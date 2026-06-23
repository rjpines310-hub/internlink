<?php
include 'db.php';

echo "<h2>Updating ID Ranges for Tables</h2>";

try {
    echo "<h3>New ID Ranges:</h3>";
    echo "<ul>";
    echo "<li>Student: 1-5000</li>";
    echo "<li>Faculty: 5001-6000</li>";
    echo "<li>Supervisor: 6001-8000</li>";
    echo "<li>CompanyHR: 8001-11000</li>";
    echo "</ul>";

    // New range configurations
    $newRanges = [
        ['student', 'student_id', 1, 5000],
        ['faculty', 'faculty_id', 5001, 6000],
        ['supervisor', 'supervisor_id', 6001, 8000],
        ['companyhr', 'hr_id', 8001, 11000]
    ];

    echo "<h3>Updating id_range_config table...</h3>";

    foreach ($newRanges as $range) {
        list($table, $id_column, $start, $end) = $range;

        // Get current max ID from the table
        $result = $conn->query("SELECT MAX($id_column) as max_id FROM $table");
        $row = $result->fetch_assoc();
        $existingMax = $row['max_id'] ?? 0;

        // Determine current_max for the new range
        $currentMax = max($existingMax, $start - 1);

        // Update or insert the configuration
        $stmt = $conn->prepare("
            INSERT INTO id_range_config (table_name, id_column, range_start, range_end, current_max)
            VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
            range_start = VALUES(range_start),
            range_end = VALUES(range_end),
            current_max = VALUES(current_max)
        ");
        $stmt->bind_param("ssiii", $table, $id_column, $start, $end, $currentMax);

        if ($stmt->execute()) {
            echo "✓ Updated range for $table: $start-$end (current max: $currentMax, existing max: $existingMax)<br>";
        } else {
            throw new Exception("Failed to update range for $table: " . $stmt->error);
        }
    }

    echo "<h3>Verification - Current Configuration:</h3>";

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

    echo "✅ <strong>SUCCESS: ID ranges updated!</strong><br>";

} catch (Exception $e) {
    echo "❌ <strong>ERROR: " . $e->getMessage() . "</strong><br>";
}

echo "<br><h3>Next Steps:</h3>";
echo "1. Use IdRangeManager class for new record creation<br>";
echo "2. Monitor range usage and expand if needed<br>";
echo "3. Ensure existing data is within new ranges<br>";

$conn->close();
?>

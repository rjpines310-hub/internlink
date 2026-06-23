<?php
include 'db.php';

echo "<h2>Updating Existing IDs to Fit New Ranges</h2>";

try {
    // Define the new ranges
    $tables = [
        'student' => ['id_column' => 'student_id', 'range_start' => 1],
        'faculty' => ['id_column' => 'faculty_id', 'range_start' => 5001],
        'supervisor' => ['id_column' => 'supervisor_id', 'range_start' => 6001],
        'companyhr' => ['id_column' => 'hr_id', 'range_start' => 8001]
    ];

    foreach ($tables as $table => $config) {
        $id_col = $config['id_column'];
        $start = $config['range_start'];

        echo "<h3>Updating $table IDs...</h3>";

        // Get all existing IDs ordered
        $result = $conn->query("SELECT $id_col FROM $table ORDER BY $id_col");
        $updates = [];
        $new_id = $start;

        while ($row = $result->fetch_assoc()) {
            $old_id = $row[$id_col];
            $updates[$old_id] = $new_id;
            $new_id++;
        }

        // Disable foreign key checks temporarily
        $conn->query("SET FOREIGN_KEY_CHECKS = 0");

        // Update the table IDs
        foreach ($updates as $old => $new) {
            $stmt = $conn->prepare("UPDATE $table SET $id_col = ? WHERE $id_col = ?");
            $stmt->bind_param("ii", $new, $old);
            $stmt->execute();
            echo "Updated $table ID $old to $new<br>";
        }

        // Re-enable foreign key checks
        $conn->query("SET FOREIGN_KEY_CHECKS = 1");

        // Update foreign keys
        if ($table == 'supervisor') {
            foreach ($updates as $old => $new) {
                $conn->query("UPDATE student SET supervisor_id = $new WHERE supervisor_id = $old");
                echo "Updated student.supervisor_id from $old to $new<br>";
            }
        }

        if ($table == 'companyhr') {
            foreach ($updates as $old => $new) {
                $conn->query("UPDATE internship_posts SET hr_id = $new WHERE hr_id = $old");
                $conn->query("UPDATE geofence_locations SET hr_id = $new WHERE hr_id = $old");
                $conn->query("UPDATE active_geofence SET hr_id = $new WHERE hr_id = $old");
                $conn->query("UPDATE student SET hr_id = $new WHERE hr_id = $old");
                echo "Updated foreign hr_id references from $old to $new<br>";
            }
        }

        // Update current_max in id_range_config
        $new_current_max = $new_id - 1;
        $conn->query("UPDATE id_range_config SET current_max = $new_current_max WHERE table_name = '$table'");
        echo "Set current_max for $table to $new_current_max<br>";
    }

    echo "<h3>Verification - Updated Configuration:</h3>";

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

    echo "✅ <strong>SUCCESS: Existing IDs updated to fit new ranges!</strong><br>";

} catch (Exception $e) {
    echo "❌ <strong>ERROR: " . $e->getMessage() . "</strong><br>";
}

$conn->close();
?>

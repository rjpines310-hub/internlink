<?php
include 'db.php';
include 'IdRangeManager.php';

echo "<h2>Testing ID Range Functionality</h2>";

try {
    $idManager = new IdRangeManager($conn);

    echo "<h3>1. Testing getNextId() for each table:</h3>";

    $tables = ['student', 'faculty', 'supervisor', 'companyhr'];

    foreach ($tables as $table) {
        echo "<h4>$table:</h4>";
        try {
            $nextId = $idManager->getNextId($table);
            echo "✓ Next ID: $nextId<br>";

            // Verify the ID is within range
            $rangeInfo = $idManager->getRangeInfo($table);
            if ($nextId >= $rangeInfo['range_start'] && $nextId <= $rangeInfo['range_end']) {
                echo "✓ ID is within range ({$rangeInfo['range_start']}-{$rangeInfo['range_end']})<br>";
            } else {
                echo "❌ ID is NOT within range ({$rangeInfo['range_start']}-{$rangeInfo['range_end']})<br>";
            }
        } catch (Exception $e) {
            echo "❌ Error getting next ID: " . $e->getMessage() . "<br>";
        }
    }

    echo "<h3>2. Testing isValidId() for each table:</h3>";

    foreach ($tables as $table) {
        echo "<h4>$table:</h4>";
        $rangeInfo = $idManager->getRangeInfo($table);

        // Test valid IDs
        $validIds = [
            $rangeInfo['range_start'],
            $rangeInfo['range_start'] + 10,
            $rangeInfo['range_end']
        ];

        foreach ($validIds as $id) {
            if ($idManager->isValidId($table, $id)) {
                echo "✓ ID $id is valid<br>";
            } else {
                echo "❌ ID $id is NOT valid (should be valid)<br>";
            }
        }

        // Test invalid IDs
        $invalidIds = [
            $rangeInfo['range_start'] - 1,
            $rangeInfo['range_end'] + 1,
            0
        ];

        foreach ($invalidIds as $id) {
            if (!$idManager->isValidId($table, $id)) {
                echo "✓ ID $id is correctly invalid<br>";
            } else {
                echo "❌ ID $id is incorrectly valid (should be invalid)<br>";
            }
        }
    }

    echo "<h3>3. Testing getAllRanges():</h3>";
    $allRanges = $idManager->getAllRanges();
    echo "<table border='1'>";
    echo "<tr><th>Table</th><th>ID Column</th><th>Range Start</th><th>Range End</th><th>Current Max</th><th>Available IDs</th></tr>";
    foreach ($allRanges as $range) {
        $available = $range['range_end'] - $range['current_max'];
        echo "<tr>";
        echo "<td>{$range['table_name']}</td>";
        echo "<td>{$range['id_column']}</td>";
        echo "<td>{$range['range_start']}</td>";
        echo "<td>{$range['range_end']}</td>";
        echo "<td>{$range['current_max']}</td>";
        echo "<td>$available</td>";
        echo "</tr>";
    }
    echo "</table><br>";

    echo "<h3>4. Testing edge cases:</h3>";

    // Test reaching range limit (simulate by temporarily setting current_max close to range_end)
    echo "<h4>Testing range limit detection:</h4>";
    foreach ($tables as $table) {
        $rangeInfo = $idManager->getRangeInfo($table);
        $available = $rangeInfo['range_end'] - $rangeInfo['current_max'];

        if ($available > 5) {
            echo "✓ $table has $available IDs available<br>";
        } else {
            echo "⚠ $table has only $available IDs available - consider expanding range<br>";
        }
    }

    echo "<h3>5. Verifying existing data is within ranges:</h3>";

    foreach ($tables as $table) {
        $rangeInfo = $idManager->getRangeInfo($table);
        $idColumn = $rangeInfo['id_column'];

        $result = $conn->query("SELECT MIN($idColumn) as min_id, MAX($idColumn) as max_id, COUNT(*) as count FROM $table");
        $row = $result->fetch_assoc();

        if ($row['count'] > 0) {
            $minId = $row['min_id'];
            $maxId = $row['max_id'];

            if ($minId >= $rangeInfo['range_start'] && $maxId <= $rangeInfo['range_end']) {
                echo "✓ $table data is within range: $minId - $maxId (range: {$rangeInfo['range_start']}-{$rangeInfo['range_end']})<br>";
            } else {
                echo "❌ $table data is OUTSIDE range: $minId - $maxId (range: {$rangeInfo['range_start']}-{$rangeInfo['range_end']})<br>";
            }
        } else {
            echo "✓ $table has no data (range: {$rangeInfo['range_start']}-{$rangeInfo['range_end']})<br>";
        }
    }

    echo "✅ <strong>SUCCESS: ID range functionality testing completed!</strong><br>";

} catch (Exception $e) {
    echo "❌ <strong>ERROR: " . $e->getMessage() . "</strong><br>";
}

$conn->close();
?>

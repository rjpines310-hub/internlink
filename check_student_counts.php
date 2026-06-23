<?php
include 'db.php';

echo "<h2>Student Count by Section</h2>";

try {
    $result = $conn->query("
        SELECT section, COUNT(*) as count
        FROM student
        GROUP BY section
        ORDER BY section
    ");

    echo "<table border='1'>";
    echo "<tr><th>Section</th><th>Student Count</th><th>At Least 20?</th></tr>";
    $allAtLeast20 = true;
    while ($row = $result->fetch_assoc()) {
        $atLeast20 = $row['count'] >= 20 ? 'Yes' : 'No';
        if ($row['count'] < 20) {
            $allAtLeast20 = false;
        }
        echo "<tr>";
        echo "<td>{$row['section']}</td>";
        echo "<td>{$row['count']}</td>";
        echo "<td>$atLeast20</td>";
        echo "</tr>";
    }
    echo "</table><br>";

    if ($allAtLeast20) {
        echo "✅ All sections have at least 20 students.<br>";
    } else {
        echo "❌ Some sections have fewer than 20 students.<br>";
    }

} catch (Exception $e) {
    echo "❌ <strong>ERROR: " . $e->getMessage() . "</strong><br>";
}

$conn->close();
?>

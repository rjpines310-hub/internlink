<?php
include 'db.php';

echo "<h2>Checking for Remaining Duplicates</h2>";

try {
    // Query to find duplicates based on first and last name
    $duplicateQuery = "
        SELECT firstname, lastname, COUNT(*) as count
        FROM student
        GROUP BY firstname, lastname
        HAVING COUNT(*) > 1
        ORDER BY firstname, lastname
    ";

    $result = $conn->query($duplicateQuery);

    if ($result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>First Name</th><th>Last Name</th><th>Count</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>{$row['firstname']}</td>";
            echo "<td>{$row['lastname']}</td>";
            echo "<td>{$row['count']}</td>";
            echo "</tr>";
        }
        echo "</table><br>";
        echo "❌ Duplicates still exist.<br>";
    } else {
        echo "✅ No duplicates found.<br>";
    }

} catch (Exception $e) {
    echo "❌ <strong>ERROR: " . $e->getMessage() . "</strong><br>";
}

$conn->close();
?>

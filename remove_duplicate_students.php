<?php
include 'db.php';

echo "<h2>Removing Duplicate Students (Same First and Last Name)</h2>";

try {
    // Query to delete duplicates, keeping the one with the smallest student_id
    $deleteQuery = "
        DELETE t1 FROM student t1
        INNER JOIN student t2
        WHERE t1.student_id > t2.student_id
        AND t1.firstname = t2.firstname
        AND t1.lastname = t2.lastname
    ";

    $result = $conn->query($deleteQuery);

    if ($result) {
        $affectedRows = $conn->affected_rows;
        echo "✅ <strong>SUCCESS: Removed $affectedRows duplicate students.</strong><br>";
    } else {
        throw new Exception("Failed to remove duplicates: " . $conn->error);
    }

    // Verification: Show remaining student count by section
    echo "<h3>Verification - Student Count by Section (After Removal):</h3>";
    $result = $conn->query("
        SELECT section, COUNT(*) as count
        FROM student
        GROUP BY section
        ORDER BY section
    ");
    echo "<table border='1'>";
    echo "<tr><th>Section</th><th>Student Count</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['section']}</td>";
        echo "<td>{$row['count']}</td>";
        echo "</tr>";
    }
    echo "</table><br>";

} catch (Exception $e) {
    echo "❌ <strong>ERROR: " . $e->getMessage() . "</strong><br>";
}

$conn->close();
?>

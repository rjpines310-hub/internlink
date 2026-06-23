<?php
include 'db.php';

echo "<h2>ID Duplication Check</h2>";

// Function to check for duplications in a table
function checkDuplicates($conn, $table, $id_column) {
    echo "<h3>Checking $table table ($id_column):</h3>";
    
    // Get table structure
    $result = $conn->query("DESCRIBE $table");
    echo "<strong>Table Structure:</strong><br>";
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . " - " . $row['Type'] . " - " . $row['Key'] . "<br>";
    }
    echo "<br>";
    
    // Check for duplicate IDs
    $query = "SELECT $id_column, COUNT(*) as count FROM $table GROUP BY $id_column HAVING COUNT(*) > 1";
    $result = $conn->query($query);
    
    if ($result->num_rows > 0) {
        echo "❌ <strong>DUPLICATES FOUND:</strong><br>";
        while ($row = $result->fetch_assoc()) {
            echo "ID " . $row[$id_column] . " appears " . $row['count'] . " times<br>";
        }
    } else {
        echo "✓ No duplicates found<br>";
    }
    
    // Show all records
    $result = $conn->query("SELECT * FROM $table ORDER BY $id_column");
    echo "<br><strong>All records:</strong><br>";
    echo "<table border='1'>";
    
    // Get column names
    $fields = $conn->query("DESCRIBE $table");
    echo "<tr>";
    while ($field = $fields->fetch_assoc()) {
        echo "<th>" . $field['Field'] . "</th>";
    }
    echo "</tr>";
    
    // Show data
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        foreach ($row as $value) {
            echo "<td>" . htmlspecialchars($value ?? '') . "</td>";
        }
        echo "</tr>";
    }
    echo "</table><br><br>";
}

// Check each table
$tables = [
    'student' => 'student_id',
    'faculty' => 'faculty_id', 
    'companyhr' => 'hr_id',
    'supervisor' => 'supervisor_id'
];

foreach ($tables as $table => $id_column) {
    checkDuplicates($conn, $table, $id_column);
}

// Check for cross-table ID conflicts
echo "<h3>Cross-table ID Conflicts Check:</h3>";
echo "Checking if any IDs are used across different user types...<br><br>";

$all_ids = [];

foreach ($tables as $table => $id_column) {
    $result = $conn->query("SELECT $id_column FROM $table");
    while ($row = $result->fetch_assoc()) {
        $id = $row[$id_column];
        if (!isset($all_ids[$id])) {
            $all_ids[$id] = [];
        }
        $all_ids[$id][] = $table;
    }
}

$conflicts = [];
foreach ($all_ids as $id => $tables_using_id) {
    if (count($tables_using_id) > 1) {
        $conflicts[$id] = $tables_using_id;
    }
}

if (!empty($conflicts)) {
    echo "❌ <strong>CROSS-TABLE ID CONFLICTS FOUND:</strong><br>";
    foreach ($conflicts as $id => $tables_list) {
        echo "ID $id is used in: " . implode(', ', $tables_list) . "<br>";
    }
} else {
    echo "✓ No cross-table ID conflicts found<br>";
}

$conn->close();
?>

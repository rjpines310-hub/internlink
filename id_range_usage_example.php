<?php
// Example usage of IdRangeManager
include "db.php";
include "IdRangeManager.php";

$idManager = new IdRangeManager($conn);

echo "<h2>IdRangeManager Usage Examples</h2>";

// Example 1: Get next safe ID for student registration
echo "<h3>Example 1: Creating a New Student</h3>";
try {
    $nextStudentId = $idManager->getNextId("student");
    echo "Next student ID: $nextStudentId<br>";
    
    // Use this ID when inserting new student
    echo "<strong>Usage in your code:</strong><br>";
    echo "<code>";
    echo "\$nextStudentId = \$idManager->getNextId('student');<br>";
    echo "\$stmt = \$conn->prepare('INSERT INTO student (student_id, firstname, lastname, email, ...) VALUES (?, ?, ?, ?, ...)');<br>";
    echo "\$stmt->bind_param('isss...', \$nextStudentId, \$firstname, \$lastname, \$email, ...);<br>";
    echo "\$stmt->execute();";
    echo "</code><br><br>";
    
    // Rollback for demo purposes
    $conn->query("UPDATE id_range_config SET current_max = current_max - 1 WHERE table_name = 'student'");
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}

// Example 2: Validate an ID before using it
echo "<h3>Example 2: Validating IDs</h3>";
$testIds = [
    ['student', 1005, 'Valid student ID'],
    ['student', 500, 'Invalid student ID (too low)'],
    ['faculty', 2500, 'Valid faculty ID'],
    ['companyhr', 1500, 'Invalid HR ID (in student range)']
];

foreach ($testIds as $test) {
    list($table, $testId, $description) = $test;
    $isValid = $idManager->isValidId($table, $testId);
    $status = $isValid ? "✅ Valid" : "❌ Invalid";
    echo "$status - $description: ID $testId for $table<br>";
}

echo "<br><strong>Usage in your code:</strong><br>";
echo "<code>";
echo "if (\$idManager->isValidId('student', \$studentId)) {<br>";
echo "&nbsp;&nbsp;&nbsp;&nbsp;// Process the student<br>";
echo "} else {<br>";
echo "&nbsp;&nbsp;&nbsp;&nbsp;// Handle invalid ID<br>";
echo "}";
echo "</code><br><br>";

// Example 3: Get range information
echo "<h3>Example 3: Checking Range Information</h3>";
$tables = ['student', 'faculty', 'companyhr', 'supervisor'];

foreach ($tables as $table) {
    $range = $idManager->getRangeInfo($table);
    if ($range) {
        $available = $range['range_end'] - $range['current_max'];
        echo "<strong>{$range['table_name']}:</strong> ";
        echo "Range {$range['range_start']}-{$range['range_end']}, ";
        echo "Current max: {$range['current_max']}, ";
        echo "Available: $available<br>";
    }
}

echo "<br><strong>Usage in your code:</strong><br>";
echo "<code>";
echo "\$studentRange = \$idManager->getRangeInfo('student');<br>";
echo "echo \"Student ID range: {\$studentRange['range_start']} - {\$studentRange['range_end']}\";<br>";
echo "echo \"Current max: {\$studentRange['current_max']}\";";
echo "</code><br><br>";

// Example 4: View all ranges
echo "<h3>Example 4: Monitoring All Ranges</h3>";
echo "<strong>All ID Ranges:</strong><br>";
$allRanges = $idManager->getAllRanges();
echo "<table border='1'>";
echo "<tr><th>Table</th><th>Range</th><th>Current Max</th><th>Available</th><th>Capacity</th></tr>";

foreach ($allRanges as $range) {
    $available = $range['range_end'] - $range['current_max'];
    $capacity = round(($available / ($range['range_end'] - $range['range_start'] + 1)) * 100, 1);
    $capacityColor = $capacity > 80 ? 'green' : ($capacity > 50 ? 'orange' : 'red');
    
    echo "<tr>";
    echo "<td>{$range['table_name']}</td>";
    echo "<td>{$range['range_start']}-{$range['range_end']}</td>";
    echo "<td>{$range['current_max']}</td>";
    echo "<td>$available</td>";
    echo "<td style='color: $capacityColor;'>{$capacity}%</td>";
    echo "</tr>";
}
echo "</table><br>";

echo "<strong>Usage in your code:</strong><br>";
echo "<code>";
echo "\$allRanges = \$idManager->getAllRanges();<br>";
echo "foreach (\$allRanges as \$range) {<br>";
echo "&nbsp;&nbsp;&nbsp;&nbsp;echo \"{\$range['table_name']}: {\$range['range_start']}-{\$range['range_end']}\";<br>";
echo "}";
echo "</code><br><br>";

// Example 5: Integration with existing signup/registration forms
echo "<h3>Example 5: Integration with Registration Forms</h3>";
echo "<strong>Before (Old way - prone to conflicts):</strong><br>";
echo "<code style='color: red;'>";
echo "// DON'T DO THIS ANYMORE<br>";
echo "\$stmt = \$conn->prepare('INSERT INTO student (firstname, lastname, email, ...) VALUES (?, ?, ?, ...)');<br>";
echo "// Relies on AUTO_INCREMENT which can cause conflicts";
echo "</code><br><br>";

echo "<strong>After (New way - conflict-free):</strong><br>";
echo "<code style='color: green;'>";
echo "// DO THIS INSTEAD<br>";
echo "include 'IdRangeManager.php';<br>";
echo "\$idManager = new IdRangeManager(\$conn);<br>";
echo "\$nextStudentId = \$idManager->getNextId('student');<br>";
echo "\$stmt = \$conn->prepare('INSERT INTO student (student_id, firstname, lastname, email, ...) VALUES (?, ?, ?, ?, ...)');<br>";
echo "\$stmt->bind_param('isss...', \$nextStudentId, \$firstname, \$lastname, \$email, ...);<br>";
echo "\$stmt->execute();";
echo "</code><br><br>";

echo "<h3>Important Notes:</h3>";
echo "<div style='background-color: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;'>";
echo "<strong>⚠️ Important Guidelines:</strong><br>";
echo "1. Always use IdRangeManager for new record creation<br>";
echo "2. Never manually assign IDs outside the designated ranges<br>";
echo "3. Check available capacity regularly (especially in production)<br>";
echo "4. Validate IDs when processing user input<br>";
echo "5. Monitor the id_range_config table for capacity planning<br>";
echo "</div>";

$conn->close();
?>

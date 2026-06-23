<?php
include 'db.php';

// --- Test Data ---
$test_student_id = 1000; // A known student ID from previous logs
$test_performance_value = '75%'; // A sample value to test the update

echo "Attempting to update performance for student_id: $test_student_id with value: '$test_performance_value'\n";

// --- The Query from student.php ---
$updatePerformanceStmt = $conn->prepare("UPDATE student_overview SET performance = ? WHERE student_id = ?");

if ($updatePerformanceStmt) {
    $updatePerformanceStmt->bind_param("si", $test_performance_value, $test_student_id);
    
    if ($updatePerformanceStmt->execute()) {
        if ($updatePerformanceStmt->affected_rows > 0) {
            echo "SUCCESS: Performance score updated successfully.\n";
        } else {
            echo "NOTICE: Query executed, but no rows were updated. This might be because the student_id does not exist or the value was already set to '$test_performance_value'.\n";
        }
    } else {
        echo "ERROR: Failed to execute the update query. Error: " . $updatePerformanceStmt->error . "\n";
    }
    
    $updatePerformanceStmt->close();
} else {
    echo "ERROR: Failed to prepare the update statement. Error: " . $conn->error . "\n";
}

$conn->close();
?>

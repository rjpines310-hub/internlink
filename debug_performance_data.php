<?php
include 'db.php';

// --- Test Data ---
$test_student_id = 1000; // Use a known student ID

echo "--- Debugging Performance Calculation for student_id: $test_student_id ---\n\n";

// 1. Fetch and display all tasks for the student
echo "--- Raw Task Data ---\n";
$tasks_result = $conn->query("SELECT id, status, score FROM tasks WHERE student_id = $test_student_id");
if ($tasks_result->num_rows > 0) {
    while ($task = $tasks_result->fetch_assoc()) {
        echo "Task ID: " . $task['id'] . ", Status: " . $task['status'] . ", Score: " . ($task['score'] ?? 'NULL') . "\n";
    }
} else {
    echo "No tasks found for this student.\n";
}
echo "\n";

// 2. Re-run the calculation logic from student.php and show intermediate values
echo "--- Calculation Breakdown ---\n";
$performanceScore = 0;
$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN status = 'Completed' THEN score ELSE 0 END) as total_score,
        COUNT(CASE WHEN status IN ('Completed', 'Missed') THEN 1 END) as scored_task_count
    FROM tasks 
    WHERE student_id = ?
");

if ($stmt) {
    $stmt->bind_param("i", $test_student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $totalScore = $row['total_score'] ?? 0;
        $scoredTaskCount = $row['scored_task_count'] ?? 0;
        
        echo "Total Score (from 'Completed' tasks): $totalScore\n";
        echo "Count of Scored Tasks ('Completed' + 'Missed'): $scoredTaskCount\n";

        if ($scoredTaskCount > 0) {
            $maxPossibleScore = $scoredTaskCount * 100;
            echo "Maximum Possible Score (Count * 100): $maxPossibleScore\n";
            
            $performanceScore = ($totalScore / $maxPossibleScore) * 100;
            echo "Calculated Performance Score: " . round($performanceScore) . "%\n";
        } else {
            echo "No scored tasks found, performance is 0%.\n";
        }
    }
    $stmt->close();
} else {
    echo "ERROR: Failed to prepare the calculation statement. Error: " . $conn->error . "\n";
}

$conn->close();
?>

<?php
include 'db.php';

// Get students with supervisor_id
$students_query = "SELECT student_id, supervisor_id FROM student WHERE supervisor_id IS NOT NULL";
$students_result = $conn->query($students_query);

if (!$students_result) {
    echo "Error fetching students: " . $conn->error . "<br>";
    exit();
}

$tasks_inserted = 0;
while ($row = $students_result->fetch_assoc()) {
    $student_id = $row['student_id'];
    $supervisor_id = $row['supervisor_id'];

    // Insert a sample task if none exists
    $check_task = $conn->prepare("SELECT COUNT(*) FROM tasks WHERE student_id = ? AND supervisor_id = ?");
    $check_task->bind_param("ii", $student_id, $supervisor_id);
    $check_task->execute();
    $check_task->bind_result($count);
    $check_task->fetch();
    $check_task->close();

    if ($count == 0) {
        $title = "Sample Task: Complete Weekly Report";
        $description = "Submit your weekly progress report including achievements and challenges.";
        $task_description = $title . "\n\n" . $description;
        $due_date = date('Y-m-d', strtotime('+7 days'));

        $insert_task = $conn->prepare("INSERT INTO tasks (student_id, supervisor_id, task_description, due_date, status) VALUES (?, ?, ?, ?, 'assigned')");
        $insert_task->bind_param("iiss", $student_id, $supervisor_id, $task_description, $due_date);

        if ($insert_task->execute()) {
            $tasks_inserted++;
            echo "Inserted sample task for student_id $student_id under supervisor_id $supervisor_id<br>";
        } else {
            echo "Error inserting task for student_id $student_id: " . $insert_task->error . "<br>";
        }
        $insert_task->close();
    }
}

echo "Sample tasks populated successfully. Inserted $tasks_inserted tasks.<br>";
$conn->close();
?>

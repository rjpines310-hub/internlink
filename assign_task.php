<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

include 'db.php';

$student_id = $_POST['student_id'] ?? '';
$title = $_POST['title'] ?? '';
$description = $_POST['description'] ?? '';
$due_date = $_POST['due_date'] ?? '';

if (!$student_id || !$title) {
    echo json_encode(['success' => false, 'message' => 'Student and title are required']);
    exit();
}

$supervisor_id = $_SESSION['user_id'];

// Validate student_id belongs to this supervisor
$check_stmt = $conn->prepare("SELECT COUNT(*) FROM student WHERE student_id = ? AND supervisor_id = ?");
$check_stmt->bind_param("ii", $student_id, $supervisor_id);
$check_stmt->execute();
$check_stmt->bind_result($count);
$check_stmt->fetch();
$check_stmt->close();

if ($count == 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student selection']);
    exit();
}

// Combine title and description for task_description
$task_description = $title;
if (!empty($description)) {
    $task_description .= "\n\n" . $description;
}

// Insert task
$stmt = $conn->prepare("INSERT INTO tasks (student_id, supervisor_id, task_description, due_date, status, assigned_at) VALUES (?, ?, ?, ?, 'assigned', NOW())");
$stmt->bind_param("iiss", $student_id, $supervisor_id, $task_description, $due_date);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Task assigned successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to assign task: ' . $conn->error]);
}

$stmt->close();
$conn->close();
?>

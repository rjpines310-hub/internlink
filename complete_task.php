<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit();
}

$student_id = $_SESSION['user_id'];
$task_id = intval($_POST['task_id'] ?? 0);

if (empty($task_id)) {
    echo json_encode(['success' => false, 'message' => 'Task ID required']);
    exit();
}

// Check if task belongs to this student and is pending
$stmt = $conn->prepare("SELECT task_id FROM tasks WHERE task_id = ? AND student_id = ? AND status = 'pending'");
$stmt->bind_param("ii", $task_id, $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Task not found or already completed']);
    $stmt->close();
    $conn->close();
    exit();
}
$stmt->close();

// Update task to completed
$update_stmt = $conn->prepare("UPDATE tasks SET status = 'completed', completed_at = NOW() WHERE task_id = ? AND student_id = ?");
$update_stmt->bind_param("ii", $task_id, $student_id);

if ($update_stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Task completed successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to complete task']);
}

$update_stmt->close();
$conn->close();
?>

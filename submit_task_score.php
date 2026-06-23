<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';

$supervisor_id = $_SESSION['user_id'];
$task_id = $_POST['task_id'] ?? 0;
$score = $_POST['score'] ?? null;

if (!$task_id || $score === null) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Task ID and score are required.']);
    exit();
}

if (!is_numeric($score) || $score < 0 || $score > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid score. Must be between 0 and 100.']);
    exit();
}

try {
    // Verify that the task belongs to an intern supervised by this supervisor
    $verify_stmt = $conn->prepare("
        SELECT COUNT(*)
        FROM tasks t
        JOIN student s ON t.student_id = s.student_id
        WHERE t.id = ? AND s.supervisor_id = ? AND t.status = 'submitted'
    ");
    $verify_stmt->bind_param("ii", $task_id, $supervisor_id);
    $verify_stmt->execute();
    $verify_stmt->bind_result($count);
    $verify_stmt->fetch();
    $verify_stmt->close();

    if ($count == 0) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'You are not authorized to score this task or the task is not in a submitted state.']);
        exit();
    }

    // Update the task with the score and set status to 'completed'
    $update_stmt = $conn->prepare("
        UPDATE tasks
        SET score = ?, status = 'completed', checked_at = NOW()
        WHERE id = ?
    ");
    $update_stmt->bind_param("di", $score, $task_id);

    if ($update_stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Score submitted successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update task.']);
    }
    $update_stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>

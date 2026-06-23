<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'student' && $_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'faculty')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$user_id = $_SESSION['user_id']; // The ID of the user posting the comment
$user_role = $_SESSION['role']; // The role of the user posting the comment
$comment_text = isset($_POST['comment_text']) ? trim($_POST['comment_text']) : '';

if ($task_id === 0 || empty($comment_text)) {
    echo json_encode(['success' => false, 'message' => 'Task ID and comment text are required.']);
    exit();
}

// Optional: Verify the user has permission to comment on this task
// For simplicity, we'll allow any logged-in student/supervisor/faculty to comment on any task.
// More complex logic could check if the task belongs to their student/intern.

try {
    $stmt = $conn->prepare("INSERT INTO task_comments (task_id, user_id, user_role, comment_text) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $task_id, $user_id, $user_role, $comment_text);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Comment posted successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to post comment: ' . $stmt->error]);
    }
    $stmt->close();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>

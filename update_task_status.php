<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';

$task_id = $_POST['task_id'] ?? '';
$status = $_POST['status'] ?? '';

if (!$task_id || !$status) {
    echo json_encode(['success' => false, 'message' => 'Invalid data']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($status === 'submitted') {
    $stmt = $conn->prepare("UPDATE tasks SET status = ?, submitted_at = IF(submitted_at IS NULL, NOW(), submitted_at) WHERE id = ? AND student_id = ?");
} else {
    $stmt = $conn->prepare("UPDATE tasks SET status = ? WHERE id = ? AND student_id = ?");
}
$stmt->bind_param("sii", $status, $task_id, $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}

$stmt->close();
$conn->close();
?>

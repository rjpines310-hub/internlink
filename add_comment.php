<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$submission_id = isset($_POST['submission_id']) ? intval($_POST['submission_id']) : 0;
$file_type = isset($_POST['file_type']) ? $_POST['file_type'] : '';
$comment_text = isset($_POST['comment_text']) ? trim($_POST['comment_text']) : '';

if ($submission_id === 0 || empty($file_type) || empty($comment_text)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$student_id = null;
$faculty_id = null;

if ($_SESSION['role'] === 'student') {
    $student_id = $_SESSION['user_id'];
} elseif ($_SESSION['role'] === 'faculty') {
    $faculty_id = $_SESSION['user_id'];
}

$stmt = $conn->prepare("
    INSERT INTO file_comments (submission_id, student_id, faculty_id, file_type, comment_text)
    VALUES (?, ?, ?, ?, ?)
");

$stmt->bind_param("iiiss", $submission_id, $student_id, $faculty_id, $file_type, $comment_text);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Comment added successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Error adding comment: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

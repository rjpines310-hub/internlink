<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';

$userId = $_SESSION['user_id'];
$postId = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;

if ($postId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid post ID']);
    exit();
}

// Check if student has a resume
$resumeStmt = $conn->prepare("SELECT COUNT(*) FROM resumes WHERE student_id = ?");
$resumeStmt->bind_param("i", $userId);
$resumeStmt->execute();
$resumeStmt->bind_result($resumeCount);
$resumeStmt->fetch();
$resumeStmt->close();

if ($resumeCount === 0) {
    echo json_encode(['success' => false, 'message' => 'You must create a resume before applying.', 'no_resume' => true]);
    exit();
}

// Prevent duplicate application
$checkStmt = $conn->prepare("SELECT COUNT(*) FROM intern_applications WHERE student_id = ? AND post_id = ?");
$checkStmt->bind_param("ii", $userId, $postId);
$checkStmt->execute();
$checkStmt->bind_result($count);
$checkStmt->fetch();
$checkStmt->close();

if ($count > 0) {
    echo json_encode(['success' => false, 'message' => 'You have already applied to this internship.']);
    exit();
}

// Insert application record
$stmt = $conn->prepare("INSERT INTO intern_applications (student_id, post_id, application_date) VALUES (?, ?, NOW())");

if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit();
}

$stmt->bind_param("ii", $userId, $postId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to save application: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>

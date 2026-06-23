<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_id'])) {
    $student_id = intval($_POST['student_id']);

    // Optional: Add a check here to ensure attendance and file submissions are 100%
    // This adds a server-side validation layer in case client-side checks are bypassed.
    // For now, we'll proceed directly with the update as per the task description.

    $stmt = $conn->prepare("UPDATE student SET employment_status = 'completed' WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);

    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'OJT marked as completed successfully.']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update employment status.']);
    }
    $stmt->close();
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
}
?>

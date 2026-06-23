<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';

$student_id = $_SESSION['user_id'];
$requested_time_in = $_POST['requested_time_in'] ?? null;
$requested_time_out = $_POST['requested_time_out'] ?? null;
$reason = $_POST['reason'];

// Get hr_id from student table
$query = "SELECT hr_id FROM student WHERE student_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($hr_id);
$stmt->fetch();
$stmt->close();

if (!$hr_id) {
    echo json_encode(['success' => false, 'message' => 'HR not assigned']);
    exit();
}

$insert = "INSERT INTO manual_time_requests (student_id, hr_id, requested_time_in, requested_time_out, reason) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($insert);
$stmt->bind_param("iisss", $student_id, $hr_id, $requested_time_in, $requested_time_out, $reason);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to submit request']);
}

$stmt->close();
$conn->close();
?>

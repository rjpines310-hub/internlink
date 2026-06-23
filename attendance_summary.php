<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['logged_days' => 0]);
    exit;
}

$student_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT COUNT(DISTINCT date) AS logged_days FROM timecard WHERE student_id=? AND time_in IS NOT NULL");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

echo json_encode(['logged_days' => $row['logged_days'] ?? 0]);
?>

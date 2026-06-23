<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

$response = ['success' => false, 'message' => ''];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    $response['message'] = 'Unauthorized access.';
    echo json_encode($response);
    exit;
}

$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : 0;
$feedback_message = isset($_POST['feedback_message']) ? trim($_POST['feedback_message']) : '';
$given_by = isset($_POST['given_by']) ? $_POST['given_by'] : '';

if ($student_id === 0 || empty($feedback_message) || !in_array($given_by, ['faculty', 'supervisor'])) {
    $response['message'] = 'Invalid input. Please fill all required fields.';
    echo json_encode($response);
    exit;
}

$user_id = $_SESSION['user_id'];
$faculty_id = null;
$supervisor_id = null;

if ($given_by === 'faculty') {
    if ($_SESSION['role'] !== 'faculty') {
        $response['message'] = 'Unauthorized to give feedback as faculty.';
        echo json_encode($response);
        exit;
    }
    $faculty_id = $user_id;
} elseif ($given_by === 'supervisor') {
    if ($_SESSION['role'] !== 'supervisor') {
        $response['message'] = 'Unauthorized to give feedback as supervisor.';
        echo json_encode($response);
        exit;
    }
    $supervisor_id = $user_id;
}

// Prevent duplicate submissions from the same faculty/supervisor for the same student
$check_duplicate_sql = "SELECT COUNT(*) FROM ojt_feedback WHERE student_id = ? AND given_by = ?";
if ($given_by === 'faculty') {
    $check_duplicate_sql .= " AND faculty_id = ?";
} else { // supervisor
    $check_duplicate_sql .= " AND supervisor_id = ?";
}

$stmt_check = $conn->prepare($check_duplicate_sql);
if ($given_by === 'faculty') {
    $stmt_check->bind_param("iis", $student_id, $given_by, $faculty_id);
} else { // supervisor
    $stmt_check->bind_param("iis", $student_id, $given_by, $supervisor_id);
}
$stmt_check->execute();
$stmt_check->bind_result($count);
$stmt_check->fetch();
$stmt_check->close();

if ($count > 0) {
    $response['message'] = 'You have already submitted feedback for this student.';
    echo json_encode($response);
    exit;
}

// Insert feedback into the database
$insert_sql = "INSERT INTO ojt_feedback (student_id, faculty_id, supervisor_id, feedback_message, given_by) VALUES (?, ?, ?, ?, ?)";
$stmt_insert = $conn->prepare($insert_sql);

if ($given_by === 'faculty') {
    $stmt_insert->bind_param("iisss", $student_id, $faculty_id, $supervisor_id, $feedback_message, $given_by);
} else { // supervisor
    $stmt_insert->bind_param("iisss", $student_id, $faculty_id, $supervisor_id, $feedback_message, $given_by);
}


if ($stmt_insert->execute()) {
    $response['success'] = true;
    $response['message'] = 'Feedback submitted successfully.';
} else {
    $response['message'] = 'Failed to submit feedback: ' . $conn->error;
}

$stmt_insert->close();
$conn->close();

echo json_encode($response);
?>

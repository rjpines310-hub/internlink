<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['student_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Student ID is required']);
    exit;
}

$student_id = intval($_GET['student_id']);

// Fetch student details
$student_stmt = $conn->prepare("SELECT studentid, firstname, lastname, profile_picture FROM student WHERE student_id = ?");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();
$student_details = $student_result->fetch_assoc();
$student_stmt->close();

if (!$student_details) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Student not found.']);
    exit;
}

// Fetch file submissions
$stmt = $conn->prepare("SELECT * FROM student_file_submissions WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
$files = $result->fetch_assoc();
$stmt->close();

$approved_count = 0;
if ($files) {
    if ($files['dtr_file_checked']) $approved_count++;
    if ($files['moa_file_checked']) $approved_count++;
    if ($files['letter_of_acceptance_file_checked']) $approved_count++;
    if ($files['evaluation_form_file_checked']) $approved_count++;
}

$student_data = [
    'name' => $student_details['firstname'] . ' ' . $student_details['lastname'],
    'studentid' => $student_details['studentid'],
    'profile_picture' => $student_details['profile_picture'] ? $student_details['profile_picture'] : 'uploads/dp.jpg'
];

echo json_encode([
    'success' => true, 
    'student' => $student_data,
    'files' => $files,
    'approved_count' => $approved_count
]);
?>

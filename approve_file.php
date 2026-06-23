<?php
header('Content-Type: application/json');
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['submission_id']) || !isset($data['file_type'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Submission ID and file type are required']);
    exit;
}

$submission_id = intval($data['submission_id']);
$file_type = $data['file_type'];

// Whitelist file types to prevent SQL injection
$allowed_file_types = [
    'dtr_file',
    'moa_file',
    'letter_of_acceptance_file',
    'evaluation_form_file'
];

if (!in_array($file_type, $allowed_file_types)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid file type']);
    exit;
}

$column_name = $file_type . '_checked';

$stmt = $conn->prepare("UPDATE student_file_submissions SET $column_name = 1 WHERE submission_id = ?");
$stmt->bind_param("i", $submission_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
    exit;
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to approve file.']);
}

$stmt->close();
?>

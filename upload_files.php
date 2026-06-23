<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';

$userId = $_SESSION['user_id'];
$allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'image/jpeg', 'image/png'];
$maxFileSize = 5 * 1024 * 1024; // 5MB
$uploadDir = 'uploads/files/';

// Ensure upload directory exists
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File is required.']);
    exit();
}

$type = strtoupper($_POST['type'] ?? '');
$validTypes = ['DTR', 'MOA', 'EVALUATION', 'LOA'];
if (!in_array($type, $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type.']);
    exit();
}

$file = $_FILES['file'];
if (!in_array($file['type'], $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type.']);
    exit();
}

if ($file['size'] > $maxFileSize) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds 5MB.']);
    exit();
}

$fileName = uniqid() . '_' . basename($file['name']);
$filePath = $uploadDir . $fileName;

if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file.']);
    exit();
}

// Map type to column
$columnMap = [
    'DTR' => 'dtr_file',
    'MOA' => 'moa_file',
    'EVALUATION' => 'evaluation_form_file',
    'LOA' => 'letter_of_acceptance_file'
];
$column = $columnMap[$type];

// Check if record exists
$stmt = $conn->prepare("SELECT submission_id FROM student_file_submissions WHERE student_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    // Update existing
    $stmt->close();
    $stmt = $conn->prepare("UPDATE student_file_submissions SET $column = ?, submitted_at = NOW() WHERE student_id = ?");
    $stmt->bind_param("si", $fileName, $userId);
} else {
    // Insert new
    $stmt->close();
    $stmt = $conn->prepare("INSERT INTO student_file_submissions (student_id, $column, submitted_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("is", $userId, $fileName);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Files uploaded successfully!']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error.']);
}

$stmt->close();
$conn->close();
?>

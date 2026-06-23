<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'student' && $_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'faculty')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$task_id = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
$student_id = isset($_POST['student_id']) ? (int)$_POST['student_id'] : 0; // Student ID for the task owner

if ($task_id === 0 || $student_id === 0) {
    echo json_encode(['success' => false, 'message' => 'Task ID and Student ID are required.']);
    exit();
}

// Verify the user has permission to upload for this task/student
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'];

$can_upload = false;
if ($user_role === 'student' && $user_id === $student_id) {
    // Student can upload for their own tasks
    $can_upload = true;
} else if ($user_role === 'supervisor') {
    // Supervisor can upload for their assigned interns' tasks
    $stmt = $conn->prepare("SELECT COUNT(*) FROM student WHERE student_id = ? AND supervisor_id = ?");
    $stmt->bind_param("ii", $student_id, $user_id);
    $stmt->execute();
    $stmt->bind_result($count);
    $stmt->fetch();
    $stmt->close();
    if ($count > 0) {
        $can_upload = true;
    }
} else if ($user_role === 'faculty') {
    // Faculty can upload for any student's task (assuming they oversee all students)
    // For more granular control, faculty could be linked to sections or specific students
    $can_upload = true; 
}


if (!$can_upload) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You do not have permission to upload files for this task.']);
    exit();
}


if (!isset($_FILES['attachment']) || $_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'File upload failed or no file was uploaded.']);
    exit();
}

$file = $_FILES['attachment'];
$file_name = basename($file['name']);
$file_tmp_name = $file['tmp_name'];
$file_size = $file['size'];
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

$allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx', 'xlsx', 'pptx', 'zip'];
if (!in_array($file_ext, $allowed_extensions)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Allowed types: ' . implode(', ', $allowed_extensions)]);
    exit();
}

// Max file size (e.g., 10MB)
$max_file_size = 10 * 1024 * 1024; 
if ($file_size > $max_file_size) {
    echo json_encode(['success' => false, 'message' => 'File size exceeds the limit (10MB).']);
    exit();
}

$upload_dir = 'uploads/task_files/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generate a unique file name to prevent overwriting and security issues
$unique_file_name = uniqid('task_') . '.' . $file_ext;
$file_path = $upload_dir . $unique_file_name;

if (move_uploaded_file($file_tmp_name, $file_path)) {
    // Check if an attachment already exists for this task and student
    $check_stmt = $conn->prepare("SELECT attachment_id, file_path FROM task_attachments WHERE task_id = ? AND student_id = ?");
    $check_stmt->bind_param("ii", $task_id, $student_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();

    if ($check_result->num_rows > 0) {
        // Existing attachment found, delete old file and update record
        $existing_attachment = $check_result->fetch_assoc();
        if (file_exists($existing_attachment['file_path'])) {
            unlink($existing_attachment['file_path']); // Delete old file
        }
        $update_stmt = $conn->prepare("UPDATE task_attachments SET file_name = ?, file_path = ?, uploaded_at = CURRENT_TIMESTAMP WHERE attachment_id = ?");
        $update_stmt->bind_param("ssi", $file_name, $file_path, $existing_attachment['attachment_id']);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        // No existing attachment, insert new record
        $insert_stmt = $conn->prepare("INSERT INTO task_attachments (task_id, student_id, file_name, file_path) VALUES (?, ?, ?, ?)");
        $insert_stmt->bind_param("iiss", $task_id, $student_id, $file_name, $file_path);
        $insert_stmt->execute();
        $insert_stmt->close();
    }
    $check_stmt->close();

    echo json_encode(['success' => true, 'message' => 'File uploaded successfully.', 'file_name' => $file_name, 'file_path' => $file_path]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
}

$conn->close();
?>

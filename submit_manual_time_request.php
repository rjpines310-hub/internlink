<?php
session_start();

// Set JSON header before any output
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'db.php';

$supervisor_id = $_SESSION['user_id'];

// Retrieve POST data
$request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : null;
$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : null;
$date = isset($_POST['date']) ? $_POST['date'] : null;
$time_in = isset($_POST['time_in']) ? $_POST['time_in'] : null;
$time_out = isset($_POST['time_out']) ? $_POST['time_out'] : null;
$reason = isset($_POST['reason']) ? trim($_POST['reason']) : '';

if (!$student_id || !$date || empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit();
}

// Validate that the student belongs to the supervisor
$check_student_stmt = $conn->prepare("SELECT student_id FROM student WHERE student_id = ? AND supervisor_id = ?");
$check_student_stmt->bind_param("ii", $student_id, $supervisor_id);
$check_student_stmt->execute();
$check_student_stmt->store_result();
if ($check_student_stmt->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized student']);
    exit();
}
$check_student_stmt->close();

try {
    if ($request_id) {
        // Update existing request
        $update_query = "UPDATE manual_time_requests SET requested_time_in = ?, requested_time_out = ?, reason = ?, updated_at = NOW(), status = 'pending' WHERE request_id = ? AND student_id = ?";
        $update_stmt = $conn->prepare($update_query);
        if (!$update_stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        // Construct datetime strings from date and times with fallback to NULL if empty
        $requested_time_in = $time_in ? ($date . ' ' . $time_in . ':00') : null;
        $requested_time_out = $time_out ? ($date . ' ' . $time_out . ':00') : null;

        $update_stmt->bind_param("ssssii", $requested_time_in, $requested_time_out, $reason, $request_id, $student_id);
        if (!$update_stmt->execute()) {
            throw new Exception('Execution failed: ' . $update_stmt->error);
        }
        $update_stmt->close();
        echo json_encode(['success' => true, 'message' => 'Manual time request updated successfully']);
    } else {
        // Insert new request
        $insert_query = "INSERT INTO manual_time_requests (student_id, requested_time_in, requested_time_out, reason, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())";
        $insert_stmt = $conn->prepare($insert_query);
        if (!$insert_stmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $requested_time_in = $time_in ? ($date . ' ' . $time_in . ':00') : null;
        $requested_time_out = $time_out ? ($date . ' ' . $time_out . ':00') : null;

        $insert_stmt->bind_param("isss", $student_id, $requested_time_in, $requested_time_out, $reason);
        if (!$insert_stmt->execute()) {
            throw new Exception('Execution failed: ' . $insert_stmt->error);
        }
        $insert_stmt->close();
        echo json_encode(['success' => true, 'message' => 'Manual time request submitted successfully']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}

$conn->close();
exit();
?>

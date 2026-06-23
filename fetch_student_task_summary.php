<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['student_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Student ID is required']);
    exit;
}

$student_id = intval($_GET['student_id']);

$response = ['success' => false];

// Fetch student details
$student_stmt = $conn->prepare("SELECT firstname, lastname, studentid FROM student WHERE student_id = ?");
$student_stmt->bind_param("i", $student_id);
$student_stmt->execute();
$student_result = $student_stmt->get_result();

if ($student_result->num_rows > 0) {
    $student = $student_result->fetch_assoc();
    $response['student_name'] = htmlspecialchars($student['firstname'] . ' ' . $student['lastname']);
    $response['studentid'] = htmlspecialchars($student['studentid']);

    // Fetch task counts
    $task_counts_query = "
        SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted_tasks,
            SUM(CASE WHEN status = 'missed' THEN 1 ELSE 0 END) as missed_tasks
        FROM tasks 
        WHERE student_id = ?
    ";
    $tasks_stmt = $conn->prepare($task_counts_query);
    $tasks_stmt->bind_param("i", $student_id);
    $tasks_stmt->execute();
    $tasks_result = $tasks_stmt->get_result()->fetch_assoc();

    $response['success'] = true;
    $response['total_tasks'] = (int)$tasks_result['total_tasks'];
    $response['completed_tasks'] = (int)$tasks_result['completed_tasks'];
    $response['submitted_tasks'] = (int)$tasks_result['submitted_tasks'];
    $response['missed_tasks'] = (int)$tasks_result['missed_tasks'];
    
    $tasks_stmt->close();

} else {
    $response['message'] = 'Student not found';
    http_response_code(404);
}

$student_stmt->close();
$conn->close();

echo json_encode($response);
?>

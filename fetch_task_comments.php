<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'student' && $_SESSION['role'] !== 'supervisor' && $_SESSION['role'] !== 'faculty')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['task_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Task ID is required.']);
    exit();
}

$task_id = (int)$_GET['task_id'];
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null; // Optional, for more granular permission checks

try {
    // Fetch comments for this task
    $comments_stmt = $conn->prepare("SELECT tc.comment_text, tc.commented_at, tc.user_role, 
                                    CASE 
                                        WHEN tc.user_role = 'student' THEN CONCAT(s.firstname, ' ', s.lastname)
                                        WHEN tc.user_role = 'supervisor' THEN CONCAT(sup.firstname, ' ', sup.lastname)
                                        WHEN tc.user_role = 'faculty' THEN CONCAT(f.firstname, ' ', f.lastname)
                                        ELSE tc.user_role
                                    END as commenter_name
                                    FROM task_comments tc
                                    LEFT JOIN student s ON tc.user_id = s.student_id AND tc.user_role = 'student'
                                    LEFT JOIN supervisor sup ON tc.user_id = sup.supervisor_id AND tc.user_role = 'supervisor'
                                    LEFT JOIN faculty f ON tc.user_id = f.faculty_id AND tc.user_role = 'faculty'
                                    WHERE tc.task_id = ? ORDER BY tc.commented_at ASC");
    $comments_stmt->bind_param("i", $task_id);
    $comments_stmt->execute();
    $comments_result = $comments_stmt->get_result();
    $comments = [];
    while($comment_row = $comments_result->fetch_assoc()) {
        $comments[] = $comment_row;
    }
    $comments_stmt->close();

    echo json_encode(['success' => true, 'comments' => $comments]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>

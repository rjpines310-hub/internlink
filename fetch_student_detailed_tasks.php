<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include 'db.php';

if (!isset($_GET['student_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Student ID parameter is required.']);
    exit();
}

$student_id = (int)$_GET['student_id'];

try {
    $sql = "
        SELECT id, task_description, due_date, status, assigned_at, submitted_at, checked_at, score
        FROM tasks
        WHERE student_id = ?
        ORDER BY assigned_at DESC
    ";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $tasks = [];
    while ($row = $result->fetch_assoc()) {
        $task_id = $row['id'];
        
        // Fetch attachment for this task
        $attachment_stmt = $conn->prepare("SELECT file_name, file_path FROM task_attachments WHERE task_id = ? AND student_id = ? ORDER BY uploaded_at DESC LIMIT 1");
        $attachment_stmt->bind_param("ii", $task_id, $student_id);
        $attachment_stmt->execute();
        $attachment_result = $attachment_stmt->get_result();
        $attachment = $attachment_result->fetch_assoc();
        $attachment_stmt->close();

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

        $tasks[] = [
            'task_id' => $row['id'],
            'task_description' => $row['task_description'],
            'due_date' => $row['due_date'],
            'status' => $row['status'],
            'assigned_at' => $row['assigned_at'],
            'submitted_at' => $row['submitted_at'],
            'checked_at' => $row['checked_at'],
            'score' => $row['score'],
            'attachment' => $attachment,
            'comments' => $comments
        ];
    }
    $stmt->close();

    echo json_encode(['success' => true, 'tasks' => $tasks]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>

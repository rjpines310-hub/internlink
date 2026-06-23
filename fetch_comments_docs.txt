<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$submission_id = isset($_GET['submission_id']) ? intval($_GET['submission_id']) : 0;
$file_type = isset($_GET['file_type']) ? $_GET['file_type'] : '';

if ($submission_id === 0 || empty($file_type)) {
    echo json_encode(['success' => false, 'message' => 'Missing parameters']);
    exit();
}

$stmt = $conn->prepare("
    SELECT 
        fc.comment_text, 
        fc.commented_at,
        s.firstname as student_firstname, s.lastname as student_lastname,
        f.firstname as faculty_firstname, f.lastname as faculty_lastname
    FROM file_comments fc
    LEFT JOIN student s ON fc.student_id = s.student_id
    LEFT JOIN faculty f ON fc.faculty_id = f.faculty_id
    WHERE fc.submission_id = ? AND fc.file_type = ?
    ORDER BY fc.commented_at ASC
");

$stmt->bind_param("is", $submission_id, $file_type);
$stmt->execute();
$result = $stmt->get_result();
$comments = [];

while ($row = $result->fetch_assoc()) {
    $commenter_name = '';
    if ($row['student_firstname']) {
        $commenter_name = htmlspecialchars($row['student_firstname'] . ' ' . $row['student_lastname']);
    } elseif ($row['faculty_firstname']) {
        $commenter_name = htmlspecialchars($row['faculty_firstname'] . ' ' . $row['faculty_lastname']) . ' (Faculty)';
    } else {
        $commenter_name = 'Unknown';
    }

    $comments[] = [
        'commenter_name' => $commenter_name,
        'comment_text' => htmlspecialchars($row['comment_text']),
        'commented_at' => date('M j, Y g:i A', strtotime($row['commented_at']))
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'comments' => $comments]);
?>

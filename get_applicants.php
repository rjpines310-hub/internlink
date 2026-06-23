<?php
session_start();
header('Content-Type: application/json');

include 'db.php'; // include first so $conn is defined

// Check session/role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if (!isset($_GET['post_id']) || !is_numeric($_GET['post_id'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post ID']);
    exit();
}

$post_id = intval($_GET['post_id']);
$userId = $_SESSION['user_id'];

// Check ownership of the post
$stmt = $conn->prepare("SELECT post_id FROM internship_posts WHERE post_id = ? AND posted_by = ?");
$stmt->bind_param("ii", $post_id, $userId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'You do not have permission to view applicants for this post']);
    exit();
}
$stmt->close();

// Fetch applicants
$stmt = $conn->prepare("
    SELECT s.student_id, s.firstname, s.lastname, s.email, s.profile_picture
    FROM intern_applications ia
    JOIN student s ON ia.student_id = s.student_id
    WHERE ia.post_id = ?
");
$stmt->bind_param("i", $post_id);
$stmt->execute();
$result = $stmt->get_result();

$applicants = [];
while ($row = $result->fetch_assoc()) {
    // Ensure student_id is an integer before adding to the array
    if (isset($row['student_id']) && is_numeric($row['student_id'])) {
        $row['student_id'] = intval($row['student_id']);
        $applicants[] = $row;
    } else {
        // Log an error or handle the case where student_id is invalid/missing
        error_log("Invalid or missing student_id for an applicant in get_applicants.php: " . json_encode($row));
    }
}

$stmt->close();
echo json_encode($applicants);

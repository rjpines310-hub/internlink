<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;

if ($post_id <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid post ID']);
    exit;
}

$stmt = $conn->prepare("SELECT post_id, internship_title, location, internship_description, allowance, application_deadline, email FROM internship_posts WHERE post_id = ? AND posted_by = ?");
$stmt->bind_param("ii", $post_id, $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode($row);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Post not found']);
}

$stmt->close();
?>

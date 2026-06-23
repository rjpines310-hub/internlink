<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

include 'db.php';

$sql = "SELECT post_id, internship_title, location, internship_description, allowance, application_deadline 
        FROM internship_posts 
        WHERE LOWER(status) = 'active' 
        ORDER BY application_deadline ASC";

$result = $conn->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query error: ' . $conn->error]);
    exit();
}

$posts = [];

while ($row = $result->fetch_assoc()) {
    $posts[] = $row;
}

echo json_encode($posts);

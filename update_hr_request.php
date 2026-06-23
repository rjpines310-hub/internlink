<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';

$data = json_decode(file_get_contents('php://input'), true);
$request_id = $data['request_id'] ?? null;
$action = $data['action'] ?? null;

if (!$request_id || !in_array($action, ['accepted', 'rejected'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

// Update the hr_requests status
$stmt = $conn->prepare("UPDATE hr_requests SET status = ? WHERE request_id = ?");
$stmt->bind_param("si", $action, $request_id);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error']);
}
$stmt->close();
$conn->close();
?>

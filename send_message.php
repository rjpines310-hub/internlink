<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    exit();
}

include 'db.php';
$userType = $_SESSION['role'];
$userId = $_SESSION['user_id'];

$other_type = $_POST['other_type'] ?? '';
$other_id = intval($_POST['other_id'] ?? 0);
$message = trim($_POST['message'] ?? '');

if (!$other_type || !$other_id || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

// Validate receiver exists
$table = $other_type;
$id_column = ($other_type === 'companyhr') ? 'hr_id' : $other_type . '_id';
$stmt = $conn->prepare("SELECT COUNT(*) FROM $table WHERE $id_column = ?");
$stmt->bind_param("i", $other_id);
$stmt->execute();
$stmt->bind_result($count);
$stmt->fetch();
$stmt->close();

if ($count == 0) {
    echo json_encode(['success' => false, 'message' => 'Receiver not found']);
    exit();
}

// Insert message
$stmt = $conn->prepare("INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, message) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("sisis", $userType, $userId, $other_type, $other_id, $message);
if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to send message']);
}
$stmt->close();
?>

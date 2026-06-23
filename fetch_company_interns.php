<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['hr_id'])) {
    echo json_encode(['success' => false, 'message' => 'Company ID not provided.']);
    exit;
}

$hr_id = intval($_GET['hr_id']);
$interns = [];

$stmt = $conn->prepare("
    SELECT firstname, lastname, email
    FROM student
    WHERE hr_id = ? AND employment_status = 'hired'
");

if ($stmt === false) {
    echo json_encode(['success' => false, 'message' => 'Failed to prepare statement.']);
    exit;
}

$stmt->bind_param("i", $hr_id);

if ($stmt->execute()) {
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $interns[] = $row;
    }
    echo json_encode(['success' => true, 'interns' => $interns]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to execute statement.']);
}

$stmt->close();
?>

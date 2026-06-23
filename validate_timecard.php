<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['timecard_id'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$timecard_id = intval($_POST['timecard_id']);
$supervisor_id = $_SESSION['user_id'];

// Verify that the supervisor is authorized to validate this timecard
// This query checks if the student associated with the timecard is assigned to the current supervisor
$auth_query = "
    SELECT t.timecard_id
    FROM timecard t
    JOIN student s ON t.student_id = s.student_id
    WHERE t.timecard_id = ? AND s.supervisor_id = ?
";
$stmt_auth = $conn->prepare($auth_query);
$stmt_auth->bind_param("ii", $timecard_id, $supervisor_id);
$stmt_auth->execute();
$result_auth = $stmt_auth->get_result();

if ($result_auth->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'You are not authorized to validate this timecard.']);
    exit;
}
$stmt_auth->close();

// Update the status to 'Validated'
$update_query = "UPDATE timecard SET status = 'Validated' WHERE timecard_id = ?";
$stmt_update = $conn->prepare($update_query);
$stmt_update->bind_param("i", $timecard_id);

if ($stmt_update->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update the timecard status.']);
}

$stmt_update->close();
$conn->close();
?>

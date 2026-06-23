<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $faculty_id = $_SESSION['user_id'];
    $announcement_id = $_POST['announcement_id'] ?? '';

    if (empty($announcement_id)) {
        echo json_encode(['success' => false, 'message' => 'Announcement ID is required.']);
        exit;
    }

    $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ? AND faculty_id = ?");
    $stmt->bind_param("ii", $announcement_id, $faculty_id);

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            echo json_encode(['success' => true, 'message' => 'Announcement deleted successfully!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No announcement found with the given ID for this faculty.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete announcement: ' . $conn->error]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>

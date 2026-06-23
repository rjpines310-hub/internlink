<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$section_name = $input['section_name'] ?? '';
$ojt_hours = $input['ojt_hours'] ?? null;

if (empty($section_name) || $ojt_hours === null || !is_numeric($ojt_hours) || $ojt_hours < 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input. Section name and non-negative OJT hours are required.']);
    exit;
}

try {
    $stmt = $conn->prepare("UPDATE sections SET ojt_hours = ? WHERE section_name = ?");
    if ($stmt === false) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("is", $ojt_hours, $section_name);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'OJT hours updated successfully.']);
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    $stmt->close();
} catch (Exception $e) {
    error_log("Error updating OJT hours: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

$conn->close();
?>

<?php
include 'db.php';
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['user_id'];

// Get student's location_id
$stmt = $conn->prepare("SELECT location_id FROM student WHERE student_id = ?");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$stmt->bind_result($location_id);
$found = $stmt->fetch();
$stmt->close();

if (!$found || !$location_id) {
    echo json_encode(['success' => false, 'error' => 'No location assigned to student']);
    exit();
}

// Get location details
$stmt = $conn->prepare("SELECT location_id, location_name, latitude, longitude FROM geofence_locations WHERE location_id = ?");
$stmt->bind_param('i', $location_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $location = $result->fetch_assoc();
    echo json_encode(['success' => true, 'location' => $location]);
} else {
    echo json_encode(['success' => false, 'error' => 'Location not found']);
}

$stmt->close();
$conn->close();
?>

<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['hr_id']) || !isset($input['lat']) || !isset($input['lng'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$hr_id = intval($input['hr_id']);
$lat = floatval($input['lat']);
$lng = floatval($input['lng']);

// Validate coordinates (basic check)
if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    echo json_encode(['success' => false, 'message' => 'Invalid coordinates']);
    exit;
}

// Check if faculty is authorized (optional, assuming session check)
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Update or insert location in companyhr table (assuming it has lat and lng columns)
$stmt = $conn->prepare("UPDATE companyhr SET latitude = ?, longitude = ? WHERE hr_id = ?");
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("ddi", $lat, $lng, $hr_id);
if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Location updated successfully']);
    } else {
        // If no rows affected, insert new record
        $insert_stmt = $conn->prepare("INSERT INTO companyhr (hr_id, latitude, longitude) VALUES (?, ?, ?)");
        if ($insert_stmt) {
            $insert_stmt->bind_param("idd", $hr_id, $lat, $lng);
            if ($insert_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Location set successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to insert location']);
            }
            $insert_stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
        }
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update location']);
}

$stmt->close();
$conn->close();
?>

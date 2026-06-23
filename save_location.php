<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$set_by = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$location_name = trim($_POST['location_name'] ?? '');
$radius = intval($_POST['radius'] ?? 100);

if (empty($location_name)) {
    echo json_encode(['success' => false, 'error' => 'Location name is required']);
    exit;
}

if ($radius < 1 || $radius > 1000) {
    echo json_encode(['success' => false, 'error' => 'Radius must be between 1 and 1000 meters']);
    exit;
}

// Check if HR already has a location, if yes, update it; else insert new
$stmt = $conn->prepare("SELECT location_id FROM geofence_locations WHERE hr_id = ?");
$stmt->bind_param("i", $set_by);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    // Insert new location
    $stmt_insert = $conn->prepare("INSERT INTO geofence_locations (hr_id, location_name, latitude, longitude) VALUES (?, ?, ?, ?)");
    $stmt_insert->bind_param("isdd", $set_by, $location_name, $_POST['latitude'], $_POST['longitude']);
    if (!$stmt_insert->execute()) {
        echo json_encode(['success' => false, 'error' => 'Failed to insert location']);
        exit;
    }
    $location_id = $conn->insert_id;
    $stmt_insert->close();
} else {
    // Update existing location
    $location = $result->fetch_assoc();
    $location_id = $location['location_id'];
    $stmt_update = $conn->prepare("UPDATE geofence_locations SET location_name = ?, latitude = ?, longitude = ? WHERE location_id = ?");
    $stmt_update->bind_param("sddi", $location_name, $_POST['latitude'], $_POST['longitude'], $location_id);
    if (!$stmt_update->execute()) {
        echo json_encode(['success' => false, 'error' => 'Failed to update location']);
        exit;
    }
    $stmt_update->close();
}

// Ensure only one active geofence per company.
// Delete any existing active_geofence record for this specific HR ID.
$stmt_delete_active = $conn->prepare("DELETE FROM active_geofence WHERE hr_id = ?");
if (!$stmt_delete_active) {
    echo json_encode(['success' => false, 'error' => 'Prepare statement failed for deleting active geofence: ' . $conn->error]);
    exit;
}
$stmt_delete_active->bind_param("i", $set_by);
if (!$stmt_delete_active->execute()) {
    echo json_encode(['success' => false, 'error' => 'Failed to delete old active geofence: ' . $stmt_delete_active->error]);
    exit;
}
$stmt_delete_active->close();

// Insert the new active geofence, including hr_id
$set_by_user_type = 'companyhr';
$stmt_insert = $conn->prepare("INSERT INTO active_geofence (hr_id, location_id, set_by, set_by_user_type, radius) VALUES (?, ?, ?, ?, ?)");
$stmt_insert->bind_param("iiisi", $set_by, $location_id, $set_by, $set_by_user_type, $radius);
if ($stmt_insert->execute()) {
    echo json_encode(['success' => true, 'message' => 'Geofence location set successfully']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to set active location: ' . $stmt_insert->error]);
}
$stmt_insert->close();

$stmt->close();
$conn->close();
?>

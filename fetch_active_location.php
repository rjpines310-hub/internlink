<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

// Fetch the single global active geofence (most recent)
$stmt = $conn->prepare("
    SELECT gl.location_id, gl.location_name, gl.latitude, gl.longitude, ag.radius, ag.set_at
    FROM active_geofence ag
    JOIN geofence_locations gl ON ag.location_id = gl.location_id
    ORDER BY ag.set_at DESC
    LIMIT 1
");
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $location = $result->fetch_assoc();
    echo json_encode(['success' => true, 'location' => $location]);
} else {
    echo json_encode(['success' => false]);
}

$stmt->close();
$conn->close();
?>

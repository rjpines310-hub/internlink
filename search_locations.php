<?php
include 'db.php';

$query = trim($_GET['query'] ?? '');

if (strlen($query) < 2) {
    echo json_encode(['success' => false, 'message' => 'Query too short']);
    exit;
}

// Search for locations in the geofence_locations table
$stmt = $conn->prepare("SELECT location_id, location_name FROM geofence_locations WHERE location_name LIKE ? LIMIT 10");
$searchTerm = '%' . $query . '%';
$stmt->bind_param("s", $searchTerm);
$stmt->execute();
$result = $stmt->get_result();

$locations = [];
while ($row = $result->fetch_assoc()) {
    $locations[] = $row;
}

echo json_encode(['success' => true, 'locations' => $locations]);

$stmt->close();
$conn->close();
?>

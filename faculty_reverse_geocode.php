<?php
header('Content-Type: application/json');

if (!isset($_GET['lat']) || !isset($_GET['lon'])) {
    echo json_encode(['error' => 'Latitude and longitude are required']);
    exit();
}

$lat = floatval($_GET['lat']);
$lon = floatval($_GET['lon']);

// Use Nominatim API for reverse geocoding
$url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon&zoom=18&addressdetails=1";

$context = stream_context_create([
    'http' => [
        'timeout' => 10, // Timeout in seconds
        'user_agent' => 'FacultyDashboard/1.0 (contact@example.com)' // Required by Nominatim
    ]
]);

$response = file_get_contents($url, false, $context);

if ($response === false) {
    echo json_encode(['error' => 'Unable to fetch location data']);
    exit();
}

$data = json_decode($response, true);

if (isset($data['display_name'])) {
    echo json_encode(['display_name' => $data['display_name']]);
} else {
    echo json_encode(['error' => 'Location not found']);
}
?>

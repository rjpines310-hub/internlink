<?php
if (isset($_GET['lat']) && isset($_GET['lon'])) {
    $lat = $_GET['lat'];
    $lon = $_GET['lon'];

    // Use Nominatim API
    $url = "https://nominatim.openstreetmap.org/reverse?format=json&lat=$lat&lon=$lon";

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MyApp/1.0)'); // Required by Nominatim

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 200) {
        header('Content-Type: application/json');
        echo $response;
    } else {
        // Fallback response
        echo json_encode(['display_name' => "Location at $lat, $lon"]);
    }
} else {
    echo json_encode(['error' => 'Missing lat or lon parameters']);
}
?>

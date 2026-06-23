<?php
include 'db.php';

// Sample Philippine locations with coordinates
$locations = [
    ['Manila', 14.5995, 120.9842],
    ['Quezon City', 14.6760, 121.0437],
    ['Makati', 14.5547, 121.0244],
    ['Pasig', 14.5764, 121.0851],
    ['Taguig', 14.5176, 121.0509],
    ['Parañaque', 14.4793, 120.9845],
    ['Pasay', 14.5378, 120.9916],
    ['Caloocan', 14.6576, 120.9828],
    ['Las Piñas', 14.4636, 120.9804],
    ['Muntinlupa', 14.4081, 121.0415],
    ['Cebu City', 10.3157, 123.8854],
    ['Davao City', 7.1907, 125.4553],
    ['Iloilo City', 10.7202, 122.5621],
    ['Baguio', 16.4023, 120.5960],
    ['Batangas', 13.7565, 121.0583],
    ['Bacolod', 10.6407, 122.9680],
    ['Cagayan de Oro', 8.4542, 124.6319],
    ['Zamboanga City', 6.9214, 122.0790],
    ['General Santos', 6.1164, 125.1716],
    ['Butuan', 8.9495, 125.5436]
];

$stmt = $conn->prepare("INSERT INTO geofence_locations (location_name, latitude, longitude, radius) VALUES (?, ?, ?, 100)");

$inserted = 0;
foreach ($locations as $location) {
    $stmt->bind_param("sdd", $location[0], $location[1], $location[2]);
    if ($stmt->execute()) {
        $inserted++;
    } else {
        echo "Error inserting {$location[0]}: " . $stmt->error . "\n";
    }
}

echo "Successfully inserted $inserted Philippine locations.\n";

$stmt->close();
$conn->close();
?>

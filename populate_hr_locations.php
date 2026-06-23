<?php
include 'db.php';

// Get all hr_ids from companyhr
$hr_result = $conn->query("SELECT hr_id FROM companyhr");
$hr_ids = [];
while ($row = $hr_result->fetch_assoc()) {
    $hr_ids[] = $row['hr_id'];
}

// For each hr_id, assign a random location if not already assigned
foreach ($hr_ids as $hr_id) {
    $check = $conn->prepare("SELECT location_id FROM geofence_locations WHERE hr_id = ?");
    $check->bind_param("i", $hr_id);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows == 0) {
        // No location assigned, assign a random one
        $location_result = $conn->query("SELECT location_id, location_name FROM geofence_locations WHERE hr_id IS NULL LIMIT 1");
        if ($location_result && $location_result->num_rows > 0) {
            $location = $location_result->fetch_assoc();
            $update = $conn->prepare("UPDATE geofence_locations SET hr_id = ? WHERE location_id = ?");
            $update->bind_param("ii", $hr_id, $location['location_id']);
            if ($update->execute()) {
                echo "Assigned location '{$location['location_name']}' to HR ID $hr_id<br>";
            } else {
                echo "Error assigning location to HR ID $hr_id: " . $update->error . "<br>";
            }
            $update->close();
        } else {
            echo "No available locations to assign to HR ID $hr_id<br>";
        }
    } else {
        echo "HR ID $hr_id already has a location assigned<br>";
    }
    $check->close();
}

$conn->close();
?>

<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$faculty_id = $_SESSION['user_id'];
$hr_id = isset($_GET['hr_id']) ? intval($_GET['hr_id']) : 0;

if ($hr_id === 0) {
    error_log("fetch_faculty_active_location.php: HR ID is required. Faculty ID: $faculty_id");
    echo json_encode(['success' => false, 'error' => 'HR ID is required.']);
    exit();
}

error_log("fetch_faculty_active_location.php: Fetching geofence for Faculty ID: $faculty_id, HR ID: $hr_id");

// Fetch the active geofence for the specific HR ID, set by a faculty
$stmt = $conn->prepare("
    SELECT gl.location_id, gl.location_name, gl.latitude, gl.longitude, ag.radius, ag.set_at
    FROM active_geofence ag
    JOIN geofence_locations gl ON ag.location_id = gl.location_id
    WHERE ag.hr_id = ?
    ORDER BY ag.set_at DESC
    LIMIT 1
");
if (!$stmt) {
    error_log("fetch_faculty_active_location.php: Prepare statement failed: " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Database error.']);
    exit();
}
$stmt->bind_param("i", $hr_id); // Only bind hr_id now
$stmt->execute();
$result = $stmt->get_result();

if ($result && $result->num_rows > 0) {
    $location = $result->fetch_assoc();
    error_log("fetch_faculty_active_location.php: Found active geofence: " . json_encode($location));
    echo json_encode(['success' => true, 'location' => $location]);
} else {
    error_log("fetch_faculty_active_location.php: No active geofence found for HR ID: $hr_id, Faculty ID: $faculty_id");
    echo json_encode(['success' => false, 'error' => 'No active geofence found for this company.']);
}

$stmt->close();
$conn->close();
?>

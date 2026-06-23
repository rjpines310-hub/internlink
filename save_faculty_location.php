<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit();
}

// Read JSON input from the request body
$input = json_decode(file_get_contents('php://input'), true);

$hr_id = intval($input['hr_id'] ?? 0);
$location_name = trim($input['location_name'] ?? '');
$lat = floatval($input['lat'] ?? 0);
$lng = floatval($input['lng'] ?? 0);
$radius = intval($input['radius'] ?? 100);

if (!$hr_id || empty($location_name) || !$lat || !$lng || $radius < 1 || $radius > 1000) {
    error_log("save_faculty_location.php: Invalid input data. hr_id: $hr_id, location_name: $location_name, lat: $lat, lng: $lng, radius: $radius");
    echo json_encode(['success' => false, 'error' => 'Invalid input data. Please ensure all fields are provided and valid.']);
    exit();
}

$faculty_id = $_SESSION['user_id'];
$set_by_user_type = 'faculty';

error_log("save_faculty_location.php: Faculty ID: $faculty_id, HR ID: $hr_id, Location Name: $location_name, Lat: $lat, Lng: $lng, Radius: $radius");

// Start transaction
$conn->begin_transaction();

try {
    // Temporary cleanup: Delete any active_geofence records with NULL hr_id for 'companyhr'
    // This is a workaround for potential data integrity issues if hr_id was previously nullable
    $stmt_cleanup = $conn->prepare("DELETE FROM active_geofence WHERE hr_id IS NULL AND set_by_user_type = 'companyhr'");
    if (!$stmt_cleanup) {
        throw new Exception('Prepare statement failed for cleanup: ' . $conn->error);
    }
    $stmt_cleanup->execute();
    $stmt_cleanup->close();

    // 1. Insert or update the geofence location in `geofence_locations`
    // This table stores the actual location details.
    // We use ON DUPLICATE KEY UPDATE based on hr_id, assuming each HR has one primary geofence location.
    $stmt_geofence = $conn->prepare("
        INSERT INTO geofence_locations (hr_id, location_name, latitude, longitude, radius)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            location_name = VALUES(location_name),
            latitude = VALUES(latitude),
            longitude = VALUES(longitude),
            radius = VALUES(radius)
    ");
    if (!$stmt_geofence) {
        throw new Exception('Prepare statement failed for geofence_locations: ' . $conn->error);
    }
    $stmt_geofence->bind_param("isddi", $hr_id, $location_name, $lat, $lng, $radius);

    if (!$stmt_geofence->execute()) {
        throw new Exception('Failed to save geofence location: ' . $stmt_geofence->error);
    }

    // Get the location_id of the inserted/updated geofence location
    $location_id = $stmt_geofence->insert_id;
    if ($stmt_geofence->affected_rows == 0) {
        // If no rows were affected by INSERT (meaning it was an UPDATE), we need to fetch the existing location_id
        $stmt_fetch_id = $conn->prepare("SELECT location_id FROM geofence_locations WHERE hr_id = ?");
        if (!$stmt_fetch_id) {
            throw new Exception('Prepare statement failed for fetching location_id: ' . $conn->error);
        }
        $stmt_fetch_id->bind_param("i", $hr_id);
        $stmt_fetch_id->execute();
        $result_fetch_id = $stmt_fetch_id->get_result();
        if ($result_fetch_id->num_rows > 0) {
            $location_id = $result_fetch_id->fetch_assoc()['location_id'];
        } else {
            throw new Exception('Could not retrieve location ID after update.');
        }
        $stmt_fetch_id->close();
    }
    $stmt_geofence->close();

    if (!$location_id) {
        throw new Exception('Could not retrieve location ID after saving.');
    }

    // 2. Insert or update the active geofence record in `active_geofence`
    // This table links a specific location (from geofence_locations) to an HR.
    // We need to ensure that for a given hr_id, there's only one active geofence.
    // If one exists, update it. If not, insert a new one.
    // The `set_by` and `set_by_user_type` fields will reflect the last user who set/updated it.

    // 2. Insert or update the active geofence record in `active_geofence`
    // This table links a specific location (from geofence_locations) to an HR.
    // We need to ensure that for a given hr_id, there's only one active geofence.
    // If one exists, update it. If not, insert a new one.
    // The `set_by` and `set_by_user_type` fields will reflect the last user who set/updated it.

    // First, check if an active geofence already exists for this hr_id
    $stmt_check_active = $conn->prepare("SELECT active_id FROM active_geofence WHERE hr_id = ?");
    if (!$stmt_check_active) {
        throw new Exception('Prepare statement failed for checking active_geofence: ' . $conn->error);
    }
    $stmt_check_active->bind_param("i", $hr_id);
    $stmt_check_active->execute();
    $result_check_active = $stmt_check_active->get_result();

    if ($result_check_active->num_rows > 0) {
        // An active geofence exists, update it
        $stmt_update_active = $conn->prepare("
            UPDATE active_geofence
            SET location_id = ?, set_by = ?, set_by_user_type = ?, radius = ?, set_at = CURRENT_TIMESTAMP()
            WHERE hr_id = ?
        ");
        if (!$stmt_update_active) {
            throw new Exception('Prepare statement failed for updating active_geofence: ' . $conn->error);
        }
        $stmt_update_active->bind_param("iisii", $location_id, $faculty_id, $set_by_user_type, $radius, $hr_id);
        if (!$stmt_update_active->execute()) {
            throw new Exception('Failed to update active geofence: ' . $stmt_update_active->error);
        }
        $stmt_update_active->close();
    } else {
        // No active geofence exists, insert a new one
        $stmt_insert_active = $conn->prepare("
            INSERT INTO active_geofence (hr_id, location_id, set_by, set_by_user_type, radius)
            VALUES (?, ?, ?, ?, ?)
        ");
        if (!$stmt_insert_active) {
            throw new Exception('Prepare statement failed for inserting active_geofence: ' . $conn->error);
        }
        $stmt_insert_active->bind_param("iiisi", $hr_id, $location_id, $faculty_id, $set_by_user_type, $radius);
        if (!$stmt_insert_active->execute()) {
            throw new Exception('Failed to insert active geofence: ' . $stmt_insert_active->error);
        }
        $stmt_insert_active->close();
    }
    $stmt_check_active->close();

    $conn->commit();
    error_log("save_faculty_location.php: Geofence saved successfully for HR ID: $hr_id, Faculty ID: $faculty_id");
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("save_faculty_location.php: Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

$conn->close();
?>

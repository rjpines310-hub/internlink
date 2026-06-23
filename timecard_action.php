<?php
date_default_timezone_set('Asia/Manila');
include 'db.php';
session_start();

// Error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', 'timecard_errors.log');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success'=>false,'message'=>'Not logged in']);
    exit;
}

$student_id = $_SESSION['user_id'];
$action = $_POST['action'];
$today = date('Y-m-d');
$now = date('Y-m-d H:i:s');
$location_id = intval($_POST['location_id'] ?? 0);
$latitude = floatval($_POST['latitude'] ?? 0);
$longitude = floatval($_POST['longitude'] ?? 0);

// Log input data
error_log("Timecard action: student_id=$student_id, action=$action, location_id=$location_id, lat=$latitude, lng=$longitude");

if (!in_array($action, ['timein','timeout'])) {
    echo json_encode(['success'=>false,'message'=>'Invalid action']);
    exit;
}

if (empty($location_id)) {
    echo json_encode(['success'=>false,'message'=>'Location ID required']);
    exit;
}

// Get hr_id from student table
$stmt = $conn->prepare("SELECT hr_id FROM student WHERE student_id = ?");
$stmt->bind_param('i', $student_id);
$stmt->execute();
$stmt->bind_result($hr_id);
$stmt->fetch();
$stmt->close();

if (!$hr_id) {
    echo json_encode(['success'=>false,'message'=>'Student not associated with a company']);
    exit;
}

// Get the single global active geofence
$stmt = $conn->prepare("
    SELECT gl.latitude, gl.longitude, ag.radius
    FROM active_geofence ag
    JOIN geofence_locations gl ON ag.location_id = gl.location_id
    ORDER BY ag.set_at DESC
    LIMIT 1
");
$stmt->execute();
$stmt->bind_result($geofence_lat, $geofence_lng, $radius);
$geofence_found = $stmt->fetch();
$stmt->close();

if (!$geofence_found) {
    echo json_encode(['success'=>false,'message'=>'No active geofence set for your company']);
    exit;
}

// Calculate distance using Haversine formula
function haversine_distance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // meters
    $dlat = deg2rad($lat2 - $lat1);
    $dlon = deg2rad($lon2 - $lon1);
    $a = sin($dlat/2) * sin($dlat/2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dlon/2) * sin($dlon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $earth_radius * $c;
}

$distance = haversine_distance($latitude, $longitude, $geofence_lat, $geofence_lng);

if ($distance > $radius) {
    echo json_encode(['success'=>false,'message'=>'You are outside the allowed geofence area']);
    exit;
}

// Save photo if provided
$photo_path = '';
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $photo_tmp = $_FILES['photo']['tmp_name'];
    $photo_name = $_FILES['photo']['name'];
    $photo_ext = strtolower(pathinfo($photo_name, PATHINFO_EXTENSION));
    $allowed_exts = ['jpg', 'jpeg', 'png'];
    if (in_array($photo_ext, $allowed_exts)) {
        $photo_filename = 'attendance_' . $student_id . '_' . time() . '.' . $photo_ext;
        $photo_path = 'uploads/' . $photo_filename;
        if (move_uploaded_file($photo_tmp, $photo_path)) {
            // Photo saved successfully
        } else {
            // Handle error, but continue without photo
            $photo_path = '';
        }
    }
}

// Check if there's already a record for today
$check = $conn->prepare("SELECT * FROM timecard WHERE student_id=? AND date=?");
$check->bind_param('is', $student_id, $today);
$check->execute();
$res = $check->get_result();

if ($res->num_rows > 0) {
    // Update existing row
    $row = $res->fetch_assoc();
    if ($action === 'timein' && ($row['time_in'] == null || $row['time_in'] == "0000-00-00 00:00:00")) {
        $stmt = $conn->prepare("UPDATE timecard SET time_in=?, location_id=?, time_in_selfie=?, latitude=?, longitude=?, updated_at=NOW() WHERE timecard_id=?");
        $stmt->bind_param('sisddi', $now, $location_id, $photo_path, $latitude, $longitude, $row['timecard_id']);
        if ($stmt->execute()) {
            echo json_encode(['success'=>true,'message'=>'Time In recorded']);
        } else {
            echo json_encode(['success'=>false,'message'=>'Database error: ' . $stmt->error]);
        }
    } elseif ($action === 'timeout' && ($row['time_out'] == null || $row['time_out'] == "0000-00-00 00:00:00")) {
        $stmt = $conn->prepare("UPDATE timecard SET time_out=?, location_id=?, time_out_selfie=?, latitude=?, longitude=?, updated_at=NOW() WHERE timecard_id=?");
        $stmt->bind_param('sisddi', $now, $location_id, $photo_path, $latitude, $longitude, $row['timecard_id']);
        if ($stmt->execute()) {
            echo json_encode(['success'=>true,'message'=>'Time Out recorded']);
        } else {
            echo json_encode(['success'=>false,'message'=>'Database error: ' . $stmt->error]);
        }
    } else {
        echo json_encode(['success'=>false,'message'=>'Already logged for today']);
    }
} else {
    // Insert new row
    if ($action === 'timein') {
        $stmt = $conn->prepare("INSERT INTO timecard (student_id, date, time_in, location_id, time_in_selfie, latitude, longitude, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
        $stmt->bind_param('issisdd', $student_id, $today, $now, $location_id, $photo_path, $latitude, $longitude);
        if ($stmt->execute()) {
            echo json_encode(['success'=>true,'message'=>'Time In recorded']);
        } else {
            echo json_encode(['success'=>false,'message'=>'Database error: ' . $stmt->error]);
        }
    } else {
        echo json_encode(['success'=>false,'message'=>'You need to Time In first today']);
    }
}
?>

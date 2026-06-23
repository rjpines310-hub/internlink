<?php
session_start();
// Disable error reporting to prevent HTML output from warnings/notices
error_reporting(0);
ini_set('display_errors', 0);

include 'db.php';

header('Content-Type: application/json'); // Ensure JSON header is sent

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$student_id = $_SESSION['user_id'];
$today = date('Y-m-d');

$stmt = $conn->prepare("SELECT t.*, gl.location_name AS location FROM timecard t LEFT JOIN geofence_locations gl ON t.location_id = gl.location_id WHERE t.student_id=? AND t.date=?");
$stmt->bind_param('is', $student_id, $today);
$stmt->execute();
$res = $stmt->get_result();

$data = [];
while($row = $res->fetch_assoc()){
    // Combine date with time_in and time_out to form full datetime strings
    if (!empty($row['time_in']) && $row['time_in'] !== "00:00:00") {
        $row['time_in'] = $row['date'] . ' ' . $row['time_in'];
    } else {
        $row['time_in'] = null; // Ensure it's null if not set or 00:00:00
    }
    if (!empty($row['time_out']) && $row['time_out'] !== "00:00:00") {
        $row['time_out'] = $row['date'] . ' ' . $row['time_out'];
    } else {
        $row['time_out'] = null; // Ensure it's null if not set or 00:00:00
    }
    $data[] = $row;
}

echo json_encode($data);
// Remove closing PHP tag to prevent accidental whitespace

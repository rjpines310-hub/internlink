<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'faculty' && $_SESSION['role'] !== 'supervisor')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (!isset($_GET['student_id'])) {
    echo json_encode(['success' => false, 'message' => 'Student ID not provided.']);
    exit;
}

$student_id = intval($_GET['student_id']);

$response = ['success' => true];

// Fetch student details
$stmt = $conn->prepare("
    SELECT s.firstname, s.lastname, s.studentid, s.profile_picture, s.email, p.internship_title as post_name
    FROM student s
    LEFT JOIN internship_posts p ON s.post_id = p.post_id
    WHERE s.student_id = ?
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();
if ($student = $result->fetch_assoc()) {
    $response['student_name'] = $student['firstname'] . ' ' . $student['lastname'];
    $response['studentid'] = $student['studentid'];
    $response['profile_picture'] = !empty($student['profile_picture']) && file_exists($student['profile_picture']) ? $student['profile_picture'] : 'uploads/dp.jpg';
    $response['email'] = $student['email'];
    $response['post_name'] = $student['post_name'] ?? 'N/A';
} else {
    echo json_encode(['success' => false, 'message' => 'Student not found.']);
    exit;
}
$stmt->close();

// Fetch attendance logs
$logs = [];
// Fetch date as well to combine with time_in/time_out
$query = "SELECT timecard_id, date, time_in, time_out, time_in_selfie, time_out_selfie, status FROM timecard WHERE student_id = ? ORDER BY date DESC, time_in DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    // Ensure date is in YYYY-MM-DD format
    $log_date = $row['date'];

    // Combine date with time_in and time_out to form full datetime strings
    $time_in_full = null;
    if (!empty($row['time_in']) && $row['time_in'] !== "00:00:00") {
        $time_in_full = $log_date . ' ' . $row['time_in'];
    }

    $time_out_full = null;
    if (!empty($row['time_out']) && $row['time_out'] !== "00:00:00") {
        $time_out_full = $log_date . ' ' . $row['time_out'];
    }

    $logs[] = [
        'timecard_id' => $row['timecard_id'],
        'date' => $log_date, // Keep date separate for filtering
        'time_in' => $time_in_full,
        'time_out' => $time_out_full,
        'time_in_selfie' => $row['time_in_selfie'],
        'time_out_selfie' => $row['time_out_selfie'],
        'status' => $row['status']
    ];
}
$stmt->close();

$response['logs'] = $logs;

echo json_encode($response);
?>

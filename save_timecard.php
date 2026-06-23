<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$supervisor_id = $_SESSION['user_id'];

// Required POST parameters
$student_id = isset($_POST['student_id']) ? intval($_POST['student_id']) : null;
$date = isset($_POST['date']) ? $_POST['date'] : null;
$time_in = isset($_POST['time_in']) ? $_POST['time_in'] : null;
$time_out = isset($_POST['time_out']) ? $_POST['time_out'] : null;
$action_type = isset($_POST['action_type']) ? $_POST['action_type'] : 'create'; // create or update
$timecard_id = isset($_POST['timecard_id']) ? intval($_POST['timecard_id']) : null;
$notes = isset($_POST['notes']) ? $_POST['notes'] : null;

// Validate required fields
if (!$student_id || !$date || !$time_in || !$time_out) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Validate that supervisor owns the student
$auth_query = "SELECT student_id FROM student WHERE student_id = ? AND supervisor_id = ?";
$stmt_auth = $conn->prepare($auth_query);
$stmt_auth->bind_param("ii", $student_id, $supervisor_id);
$stmt_auth->execute();
$result_auth = $stmt_auth->get_result();
if ($result_auth->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'You are not authorized to edit this student\'s timecard.']);
    exit;
}
$stmt_auth->close();

// Compose datetime strings for time_in and time_out using date + time components
$time_in_datetime = $date . ' ' . $time_in;
$time_out_datetime = $date . ' ' . $time_out;

// Basic validation: time_out must be after time_in
if (strtotime($time_out_datetime) <= strtotime($time_in_datetime)) {
    echo json_encode(['success' => false, 'message' => 'Time out must be later than time in.']);
    exit;
}

if ($action_type === 'update') {
    if (!$timecard_id) {
        echo json_encode(['success' => false, 'message' => 'Missing timecard ID for update.']);
        exit;
    }

    // Verify that timecard belongs to this student and supervisor
    $verify_query = "
        SELECT t.timecard_id
        FROM timecard t
        JOIN student s ON t.student_id = s.student_id
        WHERE t.timecard_id = ? AND s.supervisor_id = ? AND t.student_id = ?
    ";
    $stmt_verify = $conn->prepare($verify_query);
    $stmt_verify->bind_param("iii", $timecard_id, $supervisor_id, $student_id);
    $stmt_verify->execute();
    $verify_result = $stmt_verify->get_result();
    if ($verify_result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'You are not authorized to update this timecard.']);
        exit;
    }
    $stmt_verify->close();

    $update_query = "
        UPDATE timecard
        SET date = ?, time_in = ?, time_out = ?, status = 'Validated', photo_path = NULL
        WHERE timecard_id = ?
    ";
    $stmt_update = $conn->prepare($update_query);
    $stmt_update->bind_param("sssi", $date, $time_in, $time_out, $timecard_id);
    $execute_success = $stmt_update->execute();
    $stmt_update->close();

    if ($execute_success) {
        echo json_encode(['success' => true, 'message' => 'Timecard updated and validated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update timecard.']);
    }
} else {
    // action_type = create

    $insert_query = "
        INSERT INTO timecard (student_id, date, time_in, time_out, status)
        VALUES (?, ?, ?, ?, 'Validated')
    ";
    $stmt_insert = $conn->prepare($insert_query);
    $stmt_insert->bind_param("isss", $student_id, $date, $time_in, $time_out);
    $execute_success = $stmt_insert->execute();
    $stmt_insert->close();

    if ($execute_success) {
        echo json_encode(['success' => true, 'message' => 'Timecard created and validated successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create timecard.']);
    }
}

$conn->close();
exit;
?>

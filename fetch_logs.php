<?php
error_reporting(0); // Suppress all PHP errors and warnings
ob_start(); // Start output buffering
session_start();
include 'db.php';

// Initialize a structured response
$response = ['success' => false, 'message' => 'An unknown error occurred.', 'logs' => []];

if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'User not authenticated.';
} elseif (!isset($_GET['date'])) {
    $response['message'] = 'Date parameter is missing.';
} else {
    $student_id = $_SESSION['user_id'];
    $date = $_GET['date'];

    // Corrected the query to use the 'date' column and select valid columns
    $stmt = $conn->prepare("SELECT time_in, time_out, location_id, time_in_selfie, time_out_selfie, status 
                            FROM timecard 
                            WHERE student_id = ? AND date = ?");

    if ($stmt === false) {
        $response['message'] = 'Failed to prepare statement: ' . $conn->error;
    } else {
        if (!$stmt->bind_param("is", $student_id, $date)) {
            $response['message'] = 'Failed to bind parameters: ' . $stmt->error;
        } elseif (!$stmt->execute()) {
            $response['message'] = 'Failed to execute statement: ' . $stmt->error;
        } else {
            $result = $stmt->get_result();
            $logs = [];
            while ($row = $result->fetch_assoc()) {
              $logs[] = $row;
            }
            $stmt->close();

            $response['success'] = true;
            $response['logs'] = $logs;
            $response['message'] = 'Logs fetched successfully.';
        }
    }
}

ob_clean(); // Clear any previous output
header('Content-Type: application/json');
echo json_encode($response);
exit;
?>

<?php
// Suppress all error reporting to ensure clean JSON output
error_reporting(0);
ini_set('display_errors', 0);

// Set mysqli to throw exceptions on error
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// Set header to JSON early to ensure all responses are in the correct format
header('Content-Type: application/json');

// Centralized error handler to output JSON
function send_json_error($message) {
    echo json_encode(['error' => $message]);
    exit();
}

try {
    session_start();
    include 'db.php';

    // Immediately check for a connection error from db.php
    if ($conn->connect_error) {
        send_json_error("Database connection failed: " . $conn->connect_error);
    }

    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
        send_json_error('Unauthorized');
    }

    if (!isset($_GET['supervisor_id']) || empty($_GET['supervisor_id'])) {
        send_json_error('Supervisor ID not provided.');
    }

    $supervisor_id = $_GET['supervisor_id'];
    $interns = [];

    // Fetch interns assigned to the given supervisor
    // This query assumes that the 'student' table has a 'supervisor_id' column
    $sql = "SELECT student_id, firstname, lastname, email FROM student WHERE supervisor_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $supervisor_id);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $interns[] = $row;
    }

    $stmt->close();
    $conn->close();

    echo json_encode($interns);

} catch (mysqli_sql_exception $e) {
    // Catch any SQL errors and send a clean JSON response
    send_json_error("Database error: " . $e->getMessage());
} catch (Exception $e) {
    // Catch any other general errors
    send_json_error("An unexpected error occurred: " . $e->getMessage());
}
?>

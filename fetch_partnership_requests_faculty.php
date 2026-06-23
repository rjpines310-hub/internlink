<?php
session_start();
include 'db.php';

// Ensure only faculty can access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    exit('Unauthorized');
}

$response = ['success' => false, 'requests' => []];

try {
    $stmt = $conn->prepare("SELECT request_id, hr_id, companyname, location, email, contact, landline, status, created_at FROM hr_requests WHERE status = 'pending' ORDER BY created_at DESC");
    $stmt->execute();
    $result = $stmt->get_result();

    $requests = [];
    while ($row = $result->fetch_assoc()) {
        $requests[] = $row;
    }

    $response['success'] = true;
    $response['requests'] = $requests;
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

$conn->close();
?>

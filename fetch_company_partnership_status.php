<?php
session_start();
include 'db.php';

// Ensure only companyhr can access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    http_response_code(403);
    exit('Unauthorized');
}

$userId = $_SESSION['user_id'];
$response = ['success' => false, 'status' => null];

try {
    $stmt = $conn->prepare("SELECT status FROM hr_requests WHERE hr_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($request_status);
    $stmt->fetch();
    $stmt->close();

    $response['success'] = true;
    $response['status'] = $request_status;
} catch (Exception $e) {
    $response['error'] = $e->getMessage();
}

header('Content-Type: application/json');
echo json_encode($response);

$conn->close();
?>

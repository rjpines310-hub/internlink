<?php
session_start();
header('Content-Type: application/json');

ob_start();

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
        throw new Exception('Unauthorized access');
    }

    include 'db.php';

    $requestId = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;
    $timeIn = isset($_POST['time_in']) ? trim($_POST['time_in']) : null;
    $timeOut = isset($_POST['time_out']) ? trim($_POST['time_out']) : null;

    if ($requestId <= 0) {
        throw new Exception('Invalid request ID');
    }

    if (empty($timeIn) || empty($timeOut)) {
        throw new Exception('Time in and time out are required');
    }

    // Updated: Use request_id and requested_time_in/requested_time_out columns
    $stmt = $conn->prepare("
        UPDATE manual_time_requests 
        SET requested_time_in = ?, requested_time_out = ?
        WHERE request_id = ? AND status = 'pending'
    ");
    
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    // Convert time format to datetime
    $dateTimeIn = date('Y-m-d') . ' ' . $timeIn . ':00';
    $dateTimeOut = date('Y-m-d') . ' ' . $timeOut . ':00';
    
    $stmt->bind_param("ssi", $dateTimeIn, $dateTimeOut, $requestId);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to update request: ' . $stmt->error);
    }
    
    if ($stmt->affected_rows === 0) {
        throw new Exception('Request not found or already processed');
    }
    
    $stmt->close();
    
    ob_clean();
    echo json_encode([
        'success' => true,
        'message' => 'Request updated successfully'
    ]);
    
} catch (Exception $e) {
    ob_clean();
    error_log("Error in update_manual_time_request.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;
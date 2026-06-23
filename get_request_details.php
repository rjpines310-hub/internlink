<?php
session_start();
header('Content-Type: application/json');

// Prevent any output before JSON
ob_start();

try {
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Not authenticated');
    }

    include 'db.php';

    $requestId = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if ($requestId <= 0) {
        throw new Exception('Invalid request ID');
    }

    // Updated: Use request_id instead of id
    $stmt = $conn->prepare("
        SELECT mtr.*, 
        CONCAT(s.firstname, ' ', s.lastname) as student_name
        FROM manual_time_requests mtr
        JOIN student s ON mtr.student_id = s.student_id
        WHERE mtr.request_id = ?
    ");
    
    if (!$stmt) {
        throw new Exception('Database prepare failed: ' . $conn->error);
    }
    
    $stmt->bind_param("i", $requestId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception('Request not found');
    }
    
    $request = $result->fetch_assoc();
    $stmt->close();
    
    // Clear any output buffer
    ob_clean();
    
    echo json_encode([
        'success' => true,
        'request' => $request
    ]);
    
} catch (Exception $e) {
    // Clear any output buffer
    ob_clean();
    
    error_log("Error in get_request_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

exit;
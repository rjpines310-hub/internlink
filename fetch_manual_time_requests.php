<?php
session_start();

// Set JSON header before any output
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

require_once 'db.php';

$supervisor_id = $_SESSION['user_id'];

try {
    $query = "
        SELECT 
            mtr.request_id,
            mtr.student_id,
            mtr.timecard_id,
            mtr.requested_time_in,
            mtr.requested_time_out,
            mtr.reason,
            mtr.status,
            mtr.created_at,
            CONCAT(s.firstname, ' ', s.lastname) as student_name
        FROM manual_time_requests mtr
        JOIN student s ON mtr.student_id = s.student_id
        WHERE s.supervisor_id = ?
        ORDER BY mtr.created_at DESC
    ";

    $stmt = $conn->prepare($query);
    
    if (!$stmt) {
        throw new Exception('Failed to prepare statement: ' . $conn->error);
    }

    $stmt->bind_param("i", $supervisor_id);
    
    if (!$stmt->execute()) {
        throw new Exception('Failed to execute query: ' . $stmt->error);
    }

    $result = $stmt->get_result();
    $requests = [];
    
    while ($row = $result->fetch_assoc()) {
        $requests[] = [
            'id' => $row['request_id'],
            'student_id' => $row['student_id'],
            'student_name' => $row['student_name'],
            'timecard_id' => $row['timecard_id'],
            'date' => date('Y-m-d', strtotime($row['requested_time_in'])),
            'time_in' => date('H:i:s', strtotime($row['requested_time_in'])),
            'time_out' => date('H:i:s', strtotime($row['requested_time_out'])),
            'requested_time_in' => $row['requested_time_in'],
            'requested_time_out' => $row['requested_time_out'],
            'reason' => $row['reason'],
            'status' => $row['status'],
            'admin_notes' => null,
            'created_at' => $row['created_at']
        ];
    }

    echo json_encode([
        'success' => true, 
        'requests' => $requests,
        'count' => count($requests)
    ]);

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage(),
        'requests' => []
    ]);
}
exit();
?>

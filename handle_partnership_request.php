<?php
session_start();
include 'db.php';

// Ensure only faculty can access this
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    exit('Unauthorized');
}

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $request_id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $action = filter_input(INPUT_POST, 'action', FILTER_SANITIZE_STRING); // 'approve' or 'reject'

    if ($request_id && ($action === 'approve' || $action === 'reject')) {
        $status = ($action === 'approve') ? 'approved' : 'rejected';

        try {
            $conn->begin_transaction();

            // Update the status of the request
            $stmt = $conn->prepare("UPDATE hr_requests SET status = ? WHERE request_id = ?");
            $stmt->bind_param("si", $status, $request_id);
            $stmt->execute();

            if ($stmt->affected_rows > 0) {
                if ($action === 'approve') {
                    // If approved, get HR details and update companyhr table
                    $stmt_get_hr = $conn->prepare("SELECT hr_id, companyname, email FROM hr_requests WHERE request_id = ?");
                    $stmt_get_hr->bind_param("i", $request_id);
                    $stmt_get_hr->execute();
                    $result_hr = $stmt_get_hr->get_result();
                    $hr_data = $result_hr->fetch_assoc();
                    $stmt_get_hr->close();

                    if ($hr_data) {
                        $hr_id = $hr_data['hr_id'];
                        $companyname = $hr_data['companyname'];
                        $email = $hr_data['email'];

                        // Update the companyhr table to mark as approved/partnered
                        // Assuming there's a column like 'is_partnered' or similar
                        // For now, let's just assume the request status is enough.
                        // If a new column is needed in companyhr, it should be added.
                        // Example: $stmt_update_companyhr = $conn->prepare("UPDATE companyhr SET is_partnered = 1 WHERE hr_id = ?");
                        // $stmt_update_companyhr->bind_param("i", $hr_id);
                        // $stmt_update_companyhr->execute();
                        // $stmt_update_companyhr->close();
                    }
                }
                $conn->commit();
                $response['success'] = true;
                $response['message'] = "Request $action successfully.";
            } else {
                $conn->rollback();
                $response['error'] = "Request not found or status unchanged.";
            }
            $stmt->close();
        } catch (Exception $e) {
            $conn->rollback();
            $response['error'] = $e->getMessage();
        }
    } else {
        $response['error'] = "Invalid request_id or action.";
    }
} else {
    $response['error'] = "Invalid request method.";
}

header('Content-Type: application/json');
echo json_encode($response);

$conn->close();
?>

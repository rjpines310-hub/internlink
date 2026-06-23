<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$hr_id = isset($_GET['hr_id']) ? intval($_GET['hr_id']) : null;

$query = "
    SELECT ia.application_id, s.firstname, s.lastname, s.email, hr.companyname, ia.applied_at, ia.status
    FROM internship_applications ia
    JOIN student s ON ia.student_id = s.student_id
    JOIN hr_requests hr ON ia.hr_id = hr.hr_id
";
if ($hr_id) {
    $query .= " WHERE ia.hr_id = ?";
}
$query .= " ORDER BY ia.applied_at DESC";

$stmt = $conn->prepare($query);
if ($hr_id) {
    $stmt->bind_param("i", $hr_id);
}
$stmt->execute();
$result = $stmt->get_result();
$applications = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $applications[] = $row;
    }
}
echo json_encode(['success' => true, 'applications' => $applications]);
$conn->close();
?>

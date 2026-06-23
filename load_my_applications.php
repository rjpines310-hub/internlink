<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';
$studentId = $_SESSION['user_id'];

$sql = "SELECT ia.application_id, ia.application_date, ia.status, 
               ip.internship_title AS post_title, ip.companyname AS post_company, ip.location AS job_location,
               intv.interview_id, intv.interview_datetime, intv.location AS interview_location, intv.online_link, intv.remarks, intv.exact_address,
               intv.companyname AS interview_company, intv.internship_title AS interview_position
        FROM intern_applications ia
        INNER JOIN internship_posts ip ON ia.post_id = ip.post_id
        LEFT JOIN interviews intv ON ia.application_id = intv.application_id
        WHERE ia.student_id = ?
        ORDER BY ia.application_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $studentId);
$stmt->execute();
$result = $stmt->get_result();

$applications = [];
while ($row = $result->fetch_assoc()) {
    $applications[] = [
        'application_id' => $row['application_id'],
        'application_date' => date("F j, Y", strtotime($row['application_date'])),
        'status' => $row['status'],
        'internship_title' => $row['post_title'],
        'companyname' => $row['post_company'],
        'job_location' => $row['job_location'],
        'interview' => $row['interview_id'] ? [
            'interview_id' => $row['interview_id'],
            'companyname' => $row['interview_company'],
            'internship_title' => $row['interview_position'],
            'interview_datetime' => date("F j, Y h:i A", strtotime($row['interview_datetime'])),
            'location' => $row['interview_location'],
            'online_link' => $row['online_link'],
            'remarks' => $row['remarks'],
            'exact_address' => $row['exact_address'],
        ] : null
    ];
}

echo json_encode(['success' => true, 'applications' => $applications]);
?>

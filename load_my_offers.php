<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$student_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT 
        ia.application_id,
        ip.internship_title,
        ip.companyname,
        ip.location,
        ip.internship_description,
        ip.allowance
    FROM intern_applications ia
    JOIN internship_posts ip ON ia.post_id = ip.post_id
    WHERE ia.student_id = ? AND ia.status = 'Offer Sent'
");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$result = $stmt->get_result();

$offers = [];
while ($row = $result->fetch_assoc()) {
    $offers[] = $row;
}

echo json_encode(['success' => true, 'offers' => $offers]);

$stmt->close();
$conn->close();
?>

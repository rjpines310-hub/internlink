<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(["success" => false, "message" => "Unauthorized"]);
    exit;
}

if (!isset($_GET['application_id'])) {
    echo json_encode(["success" => false, "message" => "Missing application_id"]);
    exit;
}

$application_id = intval($_GET['application_id']);

$stmt = $conn->prepare("SELECT interview_datetime, location, online_link, remarks, companyname, internship_title 
                        FROM interviews 
                        WHERE application_id = ?");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$result = $stmt->get_result();

if ($row = $result->fetch_assoc()) {
    echo json_encode([
        "success" => true,
        "data" => $row
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "No interview details found."
    ]);
}
$stmt->close();
$conn->close();
?>

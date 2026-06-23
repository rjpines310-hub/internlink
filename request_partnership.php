<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    http_response_code(403);
    exit('Unauthorized');
}

include 'db.php';
$userId = $_SESSION['user_id'];

// Check if already requested
$stmt = $conn->prepare("SELECT request_id FROM hr_requests WHERE hr_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    echo "Already requested.";
    exit();
}
$stmt->close();

// Get HR info
$stmt = $conn->prepare("SELECT companyname, location, email, contact, landline FROM companyhr WHERE hr_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($companyname, $location, $email, $contact, $landline);
$stmt->fetch();
$stmt->close();

// Insert new request
$status = 'pending';
$stmt = $conn->prepare("
    INSERT INTO hr_requests (hr_id, companyname, location, email, contact, landline, status)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("issssss", $userId, $companyname, $location, $email, $contact, $landline, $status);
if ($stmt->execute()) {
    echo "Request submitted successfully.";
} else {
    echo "Error: " . $stmt->error; // show error if insert fails
}
$stmt->close();
?>

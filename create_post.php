<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $internship_title = $_POST['internship_title'] ?? '';
    $companyname = $_POST['companyname'] ?? '';
    $location = $_POST['location'] ?? '';
    $internship_description = $_POST['internship_description'] ?? '';
    $allowance = $_POST['allowance'] ?? '';
    $date_posted = $_POST['date_posted'] ?? '';
    $application_deadline = $_POST['application_deadline'] ?? '';
    $email = $_POST['email'] ?? '';
    $status = $_POST['status'] ?? 'open';
    $posted_by = $_SESSION['user_id']; // Assuming posted_by is the HR's user_id
    $hr_id = $_SESSION['user_id']; // hr_id is the same as posted_by for the HR user

    if (!$internship_title || !$companyname || !$location || !$internship_description || !$allowance || !$date_posted || !$application_deadline || !$email) {
        echo "Please fill in all required fields.";
        exit();
    }

    $stmt = $conn->prepare("INSERT INTO internship_posts (internship_title, companyname, location, internship_description, allowance, date_posted, application_deadline, email, status, posted_by, hr_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssii", $internship_title, $companyname, $location, $internship_description, $allowance, $date_posted, $application_deadline, $email, $status, $posted_by, $hr_id);

    if ($stmt->execute()) {
        echo "Post created successfully.";
    } else {
        echo "Failed to create post.";
    }
    $stmt->close();
} else {
    echo "Invalid request.";
}

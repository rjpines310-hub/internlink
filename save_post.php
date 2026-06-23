<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    http_response_code(403);
    exit('Unauthorized');
}

include 'db.php';

$userId = $_SESSION['user_id'];
$post_id = $_POST['post_id'] ?? null;
$title = $_POST['internship_title'] ?? '';
$location = $_POST['location'] ?? '';
$allowance = $_POST['allowance'] ?? '';
$deadline = $_POST['application_deadline'] ?? '';
$description = $_POST['internship_description'] ?? '';

if (!$title || !$location || !$allowance || !$deadline || !$description) {
    http_response_code(400);
    exit('Please fill all required fields.');
}

// Get company name of logged-in HR
$stmt = $conn->prepare("SELECT companyname FROM companyhr WHERE hr_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($companyname);
$stmt->fetch();
$stmt->close();

if (!$companyname) {
    http_response_code(400);
    exit('Invalid company.');
}

$date_posted = date('Y-m-d');
$status = 'open';

if ($post_id) {
    // Update existing post, verify ownership first
    $stmt = $conn->prepare("SELECT post_id FROM internship_posts WHERE post_id = ? AND companyname = ?");
    $stmt->bind_param("is", $post_id, $companyname);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows === 0) {
        $stmt->close();
        http_response_code(403);
        exit('You do not own this post.');
    }
    $stmt->close();

    $stmt = $conn->prepare("UPDATE internship_posts SET internship_title=?, location=?, allowance=?, application_deadline=?, internship_description=? WHERE post_id=?");
    $stmt->bind_param("sssssi", $title, $location, $allowance, $deadline, $description, $post_id);
    if ($stmt->execute()) {
        echo "Post updated successfully.";
    } else {
        http_response_code(500);
        echo "Failed to update post.";
    }
    $stmt->close();

} else {
    // Insert new post
    $stmt = $conn->prepare("INSERT INTO internship_posts (internship_title, companyname, location, internship_description, allowance, date_posted, application_deadline, status, posted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssi", $title, $companyname, $location, $description, $allowance, $date_posted, $deadline, $status, $userId);
    if ($stmt->execute()) {
        echo "Post created successfully.";
    } else {
        http_response_code(500);
        echo "Failed to create post.";
    }
    $stmt->close();
}

$conn->close();
?>

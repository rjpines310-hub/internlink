<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    http_response_code(403);
    echo "Unauthorized";
    exit();
}

include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_id'])) {
    $postId = intval($_POST['post_id']);

    // Optional: verify this post belongs to current user's company for security
    $stmt = $conn->prepare("SELECT companyname FROM internship_posts WHERE post_id = ?");
    $stmt->bind_param("i", $postId);
    $stmt->execute();
    $stmt->bind_result($postCompany);
    $stmt->fetch();
    $stmt->close();

    if ($postCompany !== $_SESSION['companyname']) {
        http_response_code(403);
        echo "You don't have permission to close this post.";
        exit();
    }

    $stmt = $conn->prepare("UPDATE internship_posts SET status = 'closed' WHERE post_id = ?");
    $stmt->bind_param("i", $postId);
    if ($stmt->execute()) {
        echo "Post closed successfully.";
    } else {
        echo "Failed to close post.";
    }
    $stmt->close();
} else {
    echo "Invalid request.";
}

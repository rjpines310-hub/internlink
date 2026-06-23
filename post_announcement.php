<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $faculty_id = $_SESSION['user_id'];
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $audiences = $_POST['audience'] ?? []; // Expect an array of audiences

    if (empty($title) || empty($content) || empty($audiences)) {
        echo json_encode(['success' => false, 'message' => 'Title, content, and at least one audience are required.']);
        exit;
    }

    // Validate and unique audiences
    $allowedAudiences = ['student', 'supervisor', 'companyhr'];
    $uniqueAudiences = [];
    foreach ($audiences as $audience) {
        if (!in_array($audience, $allowedAudiences)) {
            echo json_encode(['success' => false, 'message' => 'Invalid audience selected.']);
            exit;
        }
        if (!in_array($audience, $uniqueAudiences)) {
            $uniqueAudiences[] = $audience;
        }
    }
    $audiences = $uniqueAudiences; // Use the unique and validated audiences

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert into announcements table
        $stmt = $conn->prepare("INSERT INTO announcements (faculty_id, title, content) VALUES (?, ?, ?)");
        $stmt->bind_param("iss", $faculty_id, $title, $content);
        if (!$stmt->execute()) {
            throw new Exception('Failed to post announcement: ' . $stmt->error);
        }
        $announcement_id = $stmt->insert_id;
        $stmt->close();

        // Insert into announcement_audiences table
        $stmt_audience = $conn->prepare("INSERT INTO announcement_audiences (announcement_id, audience_role) VALUES (?, ?)");
        foreach ($audiences as $audience_role) {
            $stmt_audience->bind_param("is", $announcement_id, $audience_role);
            if (!$stmt_audience->execute()) {
                // Check if the error is due to a duplicate entry
                if ($conn->errno == 1062) { // MySQL error code for duplicate entry
                    // Log or handle the duplicate gracefully, but don't fail the entire transaction
                    error_log("Duplicate audience entry for announcement_id: $announcement_id, audience_role: $audience_role. Skipping.");
                    continue; // Skip this duplicate and continue with other audiences
                } else {
                    throw new Exception('Failed to set announcement audience: ' . $stmt_audience->error);
                }
            }
        }
        $stmt_audience->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Announcement posted successfully!']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>

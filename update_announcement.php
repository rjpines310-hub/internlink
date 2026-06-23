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
    $announcement_id = $_POST['announcement_id'] ?? '';
    $title = $_POST['title'] ?? '';
    $content = $_POST['content'] ?? '';
    $audiences = $_POST['audience'] ?? []; // Expect an array of audiences

    if (empty($announcement_id) || empty($title) || empty($content) || empty($audiences)) {
        echo json_encode(['success' => false, 'message' => 'All fields and at least one audience are required.']);
        exit;
    }

    // Validate audiences
    $allowedAudiences = ['student', 'supervisor', 'companyhr'];
    foreach ($audiences as $audience) {
        if (!in_array($audience, $allowedAudiences)) {
            echo json_encode(['success' => false, 'message' => 'Invalid audience selected.']);
            exit;
        }
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Update announcements table
        $stmt = $conn->prepare("UPDATE announcements SET title = ?, content = ? WHERE id = ? AND faculty_id = ?");
        $stmt->bind_param("ssii", $title, $content, $announcement_id, $faculty_id);
        if (!$stmt->execute()) {
            throw new Exception('Failed to update announcement: ' . $stmt->error);
        }
        $stmt->close();

        // Delete existing audience entries for this announcement
        $delete_audience_stmt = $conn->prepare("DELETE FROM announcement_audiences WHERE announcement_id = ?");
        $delete_audience_stmt->bind_param("i", $announcement_id);
        if (!$delete_audience_stmt->execute()) {
            throw new Exception('Failed to clear existing audiences: ' . $delete_audience_stmt->error);
        }
        $delete_audience_stmt->close();

        // Insert new audience entries
        $insert_audience_stmt = $conn->prepare("INSERT INTO announcement_audiences (announcement_id, audience_role) VALUES (?, ?)");
        foreach ($audiences as $audience_role) {
            $insert_audience_stmt->bind_param("is", $announcement_id, $audience_role);
            if (!$insert_audience_stmt->execute()) {
                throw new Exception('Failed to set new announcement audience: ' . $insert_audience_stmt->error);
            }
        }
        $insert_audience_stmt->close();

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Announcement updated successfully!']);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>

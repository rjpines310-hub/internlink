<?php
session_start();
include 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = $_POST['student_id'];
    $field = $_POST['field'];

    // Get current file path
    $sql = "SELECT $field FROM student_file_submissions WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if ($row && !empty($row[$field]) && file_exists($row[$field])) {
        unlink($row[$field]); // delete from server
    }

    // Remove from DB
    $sql = "UPDATE student_file_submissions SET $field = NULL WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $student_id);
    $stmt->execute();

    header("Location: student.php?tab=file-submissions&removed=1");
    exit;
}

header("Location: student.php?tab=file-submissions&error=1");
exit;

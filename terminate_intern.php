<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'Invalid request method']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$internId = $data['intern_id'] ?? null;

if (!$internId) {
    echo json_encode(['error' => 'Intern ID not provided']);
    exit();
}

try {
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // For now, we'll just delete the student record.
    // In a real-world scenario, you might want to set an 'is_terminated' flag instead.
    $sql = "DELETE FROM student WHERE student_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $internId);

    if ($stmt->execute()) {
        echo json_encode(['success' => 'Intern terminated successfully']);
    } else {
        throw new Exception("Failed to terminate intern.");
    }
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

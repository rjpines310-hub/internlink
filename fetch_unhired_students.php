<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$search_query = isset($_GET['query']) ? $_GET['query'] : '';

// Fetch students who are not 'hired'
// 'unemployed', 'available', or NULL are considered not hired
$stmt = $conn->prepare("
    SELECT student_id, firstname, lastname, email, employment_status
    FROM student
    WHERE (employment_status IS NULL OR employment_status = 'unemployed' OR employment_status = 'available' OR employment_status = 'pending')
    AND (firstname LIKE ? OR lastname LIKE ? OR email LIKE ?)
    LIMIT 10
");

$search_param = '%' . $search_query . '%';
$stmt->bind_param("sss", $search_param, $search_param, $search_param);
$stmt->execute();
$result = $stmt->get_result();

$students = [];
while ($row = $result->fetch_assoc()) {
    $students[] = [
        'id' => $row['student_id'],
        'name' => htmlspecialchars($row['firstname'] . ' ' . $row['lastname']),
        'email' => htmlspecialchars($row['email']),
        'employment_status' => htmlspecialchars($row['employment_status'] ?? 'N/A')
    ];
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'students' => $students]);
?>

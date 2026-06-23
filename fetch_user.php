<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$type = $_GET['type'] ?? '';
$id = intval($_GET['id'] ?? 0);

if (!$type || !$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$table = '';
$id_field = '';
switch ($type) {
    case 'student':
        $table = 'student';
        $id_field = 'student_id';
        break;
    case 'faculty':
        $table = 'faculty';
        $id_field = 'faculty_id';
        break;
    case 'hr':
        $table = 'companyhr';
        $id_field = 'hr_id';
        break;
    case 'supervisor':
        $table = 'supervisor';
        $id_field = 'supervisor_id';
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid user type']);
        exit;
}

$stmt = $conn->prepare("SELECT * FROM $table WHERE $id_field = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($user) {
    echo json_encode(['success' => true, 'user' => $user]);
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}
?>

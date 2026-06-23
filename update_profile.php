<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Role-to-table mapping
$roleTableMap = [
    'student' => ['table' => 'student', 'id_column' => 'studentid', 'fields' => ['studentid', 'firstname', 'lastname', 'section', 'email', 'contact']],
    'faculty' => ['table' => 'faculty', 'id_column' => 'faculty_id', 'fields' => ['firstname', 'lastname', 'email', 'contact']],
    'companyhr' => ['table' => 'companyhr', 'id_column' => 'hr_id', 'fields' => ['companyname', 'location', 'email', 'contact', 'landline']],
    'supervisor' => ['table' => 'supervisor', 'id_column' => 'supervisor_id', 'fields' => ['firstname', 'lastname', 'email', 'contact']]
];

if (!isset($roleTableMap[$role])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid role.']);
    exit();
}

$table = $roleTableMap[$role]['table'];
$id_column = $roleTableMap[$role]['id_column'];
$fields = $roleTableMap[$role]['fields'];

// Gather submitted data
$data = [];
foreach ($fields as $field) {
    if (isset($_POST[$field])) {
        $data[$field] = trim($_POST[$field]);
    }
}

// Optional password update
$password = '';
if (!empty($_POST['password'])) {
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
}

// Build the query
$query = "UPDATE `$table` SET ";
$params = [];
$types = '';

foreach ($data as $key => $value) {
    $query .= "$key = ?, ";
    $params[] = $value;
    $types .= 's';
}

if ($password) {
    $query .= "password = ?, ";
    $params[] = $password;
    $types .= 's';
}

$query = rtrim($query, ', ') . " WHERE $id_column = ?";
$params[] = $userId;
$types .= 'i';

// Prepare and execute
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Failed to prepare the statement.']);
    exit();
}

$stmt->bind_param($types, ...$params);
$stmt->execute();
$stmt->close();

echo json_encode(['status' => 'success', 'message' => 'Profile updated successfully.']);

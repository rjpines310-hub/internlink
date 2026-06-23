<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$user_type = $_POST['user_type'] ?? '';
$user_id = intval($_POST['user_id'] ?? 0);
$firstname = trim($_POST['firstname'] ?? '');
$lastname = trim($_POST['lastname'] ?? '');
$email = trim($_POST['email'] ?? '');
$contact = trim($_POST['contact'] ?? '');
$password = $_POST['password'] ?? '';
$landline = trim($_POST['landline'] ?? '');

if ($user_type === 'hr') {
    $firstname = trim($_POST['companyname'] ?? '');
    $lastname = trim($_POST['location'] ?? '');
}

if (!$user_type || !$user_id || !$firstname || !$lastname || !$email) {
    echo json_encode(['success' => false, 'message' => 'Required fields missing']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email']);
    exit;
}

$table = '';
$id_field = '';
switch ($user_type) {
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

// Handle profile picture upload
$profile_picture = '';
if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/';
    $file_name = basename($_FILES['profile_picture']['name']);
    $file_path = $upload_dir . uniqid() . '_' . $file_name;
    $file_type = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];

    if (in_array($file_type, $allowed_types) && move_uploaded_file($_FILES['profile_picture']['tmp_name'], $file_path)) {
        $profile_picture = $file_path;
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid file type or upload failed']);
        exit;
    }
}

// Prepare update query
if ($user_type === 'hr') {
    $fields = ['companyname = ?', 'location = ?', 'email = ?', 'contact = ?', 'landline = ?'];
    $params = [$firstname, $lastname, $email, $contact, $landline];
    $types = 'sssss';
} else {
    $fields = ['firstname = ?', 'lastname = ?', 'email = ?', 'contact = ?'];
    $params = [$firstname, $lastname, $email, $contact];
    $types = 'ssss';
}

if ($password) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $fields[] = 'password = ?';
    $params[] = $hashed_password;
    $types .= 's';
}

if ($profile_picture) {
    $fields[] = 'profile_picture = ?';
    $params[] = $profile_picture;
    $types .= 's';
}

$params[] = $user_id;
$types .= 'i';

$query = "UPDATE $table SET " . implode(', ', $fields) . " WHERE $id_field = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param($types, ...$params);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Update failed']);
}

$stmt->close();
?>

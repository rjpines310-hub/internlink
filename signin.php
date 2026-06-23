<?php
session_start();
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'capstone';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    exit;
}

$role = $_POST['role'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
$terms_agreed = $_POST['terms_agreed'] ?? '0';

if (!$role || !$email || !$password) {
    echo json_encode(['status' => 'error', 'message' => 'All fields are required.']);
    exit;
}

if ($terms_agreed !== '1') {
    echo json_encode(['status' => 'error', 'message' => 'You must agree to the Terms and Conditions.']);
    exit;
}

// Determine the table, ID column, and redirect page based on role
switch ($role) {
    case 'student':
        $table = 'student';
        $idColumn = 'student_id';
        $redirect = 'student.php';
        break;
    case 'faculty':
        $table = 'faculty';
        $idColumn = 'faculty_id';
        $redirect = 'faculty.php';
        break;
    case 'companyhr':
        $table = 'companyhr';
        $idColumn = 'hr_id';
        $redirect = 'companyhr.php';
        break;
    case 'supervisor':
        $table = 'supervisor';
        $idColumn = 'supervisor_id';
        $redirect = 'supervisor.php';
        break;
    default:
        echo json_encode(['status' => 'error', 'message' => 'Invalid role.']);
        exit;
}

// Use dynamic query with correct ID column
$query = "SELECT $idColumn, password FROM $table WHERE email = ?";
$stmt = $conn->prepare($query);

if (!$stmt) {
    echo json_encode(['status' => 'error', 'message' => 'Query preparation failed.']);
    exit;
}

$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 1) {
    $stmt->bind_result($id, $hashed);
    $stmt->fetch();

    if (password_verify($password, $hashed)) {
        $_SESSION['user_id'] = $id;
        $_SESSION['role'] = $role;

        // Update terms agreed timestamp in the user table (if column exists)
        $updateQuery = "UPDATE $table SET terms_agreed_at = NOW() WHERE $idColumn = ?";
        $updateStmt = $conn->prepare($updateQuery);
        if ($updateStmt) {
            $updateStmt->bind_param("i", $id);
            $updateStmt->execute();
            $updateStmt->close();
        }

        echo json_encode(['status' => 'success', 'redirect' => $redirect]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Incorrect password.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'User not found.']);
}

$stmt->close();
$conn->close();
?>

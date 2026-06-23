<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Map roles to their respective database tables and primary key fields
$roleTableMap = [
    'student' => ['table' => 'student', 'id_column' => 'student_id'],
    'faculty' => ['table' => 'faculty', 'id_column' => 'faculty_id'],
    'companyhr' => ['table' => 'companyhr', 'id_column' => 'hr_id'],
    'supervisor' => ['table' => 'supervisor', 'id_column' => 'supervisor_id']
];

if (!array_key_exists($role, $roleTableMap)) {
    header("Location: login.php");
    exit();
}

$tableInfo = $roleTableMap[$role];
$table = $tableInfo['table'];
$id_column = $tableInfo['id_column'];

if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === 0) {
    $uploadDir = 'uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
    $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($_FILES['profile_picture']['name']));
    $filename = $uploadDir . $role . '_' . $user_id . '_' . time() . '_' . $safeName;

    if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $filename)) {
        $stmt = $conn->prepare("UPDATE $table SET profile_picture = ? WHERE $id_column = ?");
        $stmt->bind_param("si", $filename, $user_id);
        $stmt->execute();
        $stmt->close();
    }
}

// Redirect based on role
switch ($role) {
    case 'student':
        header("Location: student.php");
        break;
    case 'faculty':
        header("Location: faculty.php");
        break;
    case 'companyhr':
        header("Location: companyhr.php");
        break;
    case 'supervisor':
        header("Location: supervisor.php");
        break;
    default:
        header("Location: login.php");
        break;
}
exit();
?>

<?php
session_start();
header('Content-Type: application/json');
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$student_id = $_SESSION['user_id'];
$data = [
    'objective' => '',
    'education' => [],
    'skills' => [],
    'experience' => [],
    'certifications' => []
];

// Get resume
$stmt = $conn->prepare("SELECT resume_id, objective FROM resumes WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($resume_id, $objective);
if ($stmt->fetch()) {
    $data['objective'] = $objective;
}
$stmt->close();

if (!isset($resume_id)) {
    echo json_encode(['success' => true, 'data' => $data]);
    exit();
}

// Education
$result = $conn->query("SELECT school_name, start_year, end_year, description FROM education WHERE resume_id = $resume_id");
while ($row = $result->fetch_assoc()) {
    $data['education'][] = $row;
}
$result->free();

// Skills
$result = $conn->query("SELECT skill_name, proficiency FROM skills WHERE resume_id = $resume_id");
while ($row = $result->fetch_assoc()) {
    $data['skills'][] = $row;
}
$result->free();

// Work Experience
$result = $conn->query("SELECT company_name, position, start_date, end_date, responsibilities FROM work_experience WHERE resume_id = $resume_id");
while ($row = $result->fetch_assoc()) {
    $data['experience'][] = $row;
}
$result->free();

// Certifications
$result = $conn->query("SELECT title, issuer, date_obtained, description FROM certifications WHERE resume_id = $resume_id");
while ($row = $result->fetch_assoc()) {
    $data['certifications'][] = $row;
}
$result->free();

echo json_encode(['success' => true, 'data' => $data]);

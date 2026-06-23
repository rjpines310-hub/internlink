<?php
header('Content-Type: application/json');
include 'db.php';

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if ($student_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID']);
    exit();
}

$data = [
    'student_info' => null,
    'objective' => '',
    'education' => [],
    'skills' => [],
    'experience' => [],
    'certifications' => [],
    'applications' => []
];

// Get HR ID from session to scope the application search
session_start();
$hr_id = isset($_SESSION['user_id']) ? intval($_SESSION['user_id']) : 0;

// Student basic info
$stmt_student = $conn->prepare("SELECT firstname, lastname, email, contact, section, COALESCE(profile_picture, 'uploads/dp.jpg') AS profile_picture FROM student WHERE student_id = ?");
$stmt_student->bind_param("i", $student_id);
$stmt_student->execute();
$result_student = $stmt_student->get_result();
if ($student_info = $result_student->fetch_assoc()) {
    $data['student_info'] = $student_info;
}
$stmt_student->close();

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
    echo json_encode(['success' => false, 'message' => 'No resume found for this student.']);
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

// Fetch applications for the interview form
if ($hr_id > 0) {
    $stmt_apps = $conn->prepare("
        SELECT ia.application_id, ip.internship_title, ch.companyname
        FROM intern_applications ia
        JOIN internship_posts ip ON ia.post_id = ip.post_id
        JOIN companyhr ch ON ch.hr_id = ip.posted_by
        WHERE ia.student_id = ? AND ip.posted_by = ?
    ");
    $stmt_apps->bind_param("ii", $student_id, $hr_id);
    $stmt_apps->execute();
    $result_apps = $stmt_apps->get_result();
    while ($row = $result_apps->fetch_assoc()) {
        $data['applications'][] = $row;
    }
    $stmt_apps->close();
}

echo json_encode(['success' => true, 'data' => $data]);
?>

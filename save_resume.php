<?php
session_start();
header('Content-Type: application/json');
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$student_id = $_SESSION['user_id'];
$objective = isset($_POST['objective']) ? $_POST['objective'] : '';

$conn->begin_transaction();

try {
    // Check if resume already exists
    $stmt = $conn->prepare("SELECT resume_id FROM resumes WHERE student_id = ?");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->bind_result($resume_id);
    $hasResume = $stmt->fetch();
    $stmt->close();

    if ($hasResume) {
        // Update resume
        $stmt = $conn->prepare("UPDATE resumes SET objective = ?, updated_at = NOW() WHERE resume_id = ?");
        $stmt->bind_param("si", $objective, $resume_id);
        $stmt->execute();
        $stmt->close();

        // Clear old data before inserting new
        $conn->query("DELETE FROM education WHERE resume_id = $resume_id");
        $conn->query("DELETE FROM skills WHERE resume_id = $resume_id");
        $conn->query("DELETE FROM work_experience WHERE resume_id = $resume_id");
        $conn->query("DELETE FROM certifications WHERE resume_id = $resume_id");
    } else {
        // Insert new resume
        $stmt = $conn->prepare("INSERT INTO resumes (student_id, objective, created_at, updated_at) VALUES (?, ?, NOW(), NOW())");
        $stmt->bind_param("is", $student_id, $objective);
        $stmt->execute();
        $resume_id = $stmt->insert_id;
        $stmt->close();
    }

    // Education
    if (isset($_POST['education_school_name'])) {
        $schools = $_POST['education_school_name'];
        $starts = $_POST['education_start_year'];
        $ends = $_POST['education_end_year'];
        $descs = $_POST['education_description'];
        for ($i = 0; $i < count($schools); $i++) {
            $stmt = $conn->prepare("INSERT INTO education (resume_id, school_name, start_year, end_year, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $resume_id, $schools[$i], $starts[$i], $ends[$i], $descs[$i]);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Skills
    if (isset($_POST['skill_name'])) {
        $skills = $_POST['skill_name'];
        $profs = $_POST['skill_proficiency'];
        for ($i = 0; $i < count($skills); $i++) {
            $stmt = $conn->prepare("INSERT INTO skills (resume_id, skill_name, proficiency) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $resume_id, $skills[$i], $profs[$i]);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Work Experience
    if (isset($_POST['experience_company_name'])) {
        $companies = $_POST['experience_company_name'];
        $positions = $_POST['experience_position'];
        $starts = $_POST['experience_start_date'];
        $ends = $_POST['experience_end_date'];
        $responsibilities = $_POST['experience_responsibilities'];
        for ($i = 0; $i < count($companies); $i++) {
            $stmt = $conn->prepare("INSERT INTO work_experience (resume_id, company_name, position, start_date, end_date, responsibilities) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("isssss", $resume_id, $companies[$i], $positions[$i], $starts[$i], $ends[$i], $responsibilities[$i]);
            $stmt->execute();
            $stmt->close();
        }
    }

    // Certifications
    if (isset($_POST['certification_title'])) {
        $titles = $_POST['certification_title'];
        $issuers = $_POST['certification_issuer'];
        $dates = $_POST['certification_date_obtained'];
        $descs = $_POST['certification_description'];
        for ($i = 0; $i < count($titles); $i++) {
            $stmt = $conn->prepare("INSERT INTO certifications (resume_id, title, issuer, date_obtained, description) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("issss", $resume_id, $titles[$i], $issuers[$i], $dates[$i], $descs[$i]);
            $stmt->execute();
            $stmt->close();
        }
    }

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}

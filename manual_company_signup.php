<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $companyname = $_POST['companyname'] ?? '';
    $location = $_POST['location'] ?? '';
    $email = $_POST['email'] ?? '';
    $contact = $_POST['contact'] ?? '';
    $student_post = $_POST['student_post'] ?? '';

    if (empty($companyname) || empty($location) || empty($email) || empty($contact) || empty($student_post)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required.']);
        exit;
    }

    $student_id = $_POST['student_id'] ?? null;
    if (empty($student_id)) {
        echo json_encode(['success' => false, 'message' => 'Assign Student is required.']);
        exit;
    }

    $conn->begin_transaction();

    try {
        // 1. Insert into companyhr table without password
        $stmt_company = $conn->prepare("INSERT INTO companyhr (companyname, location, email, contact, manual) VALUES (?, ?, ?, ?, 'yes')");
        $stmt_company->bind_param("ssss", $companyname, $location, $email, $contact);
        if (!$stmt_company->execute()) {
            throw new Exception("Failed to insert company: " . $stmt_company->error);
        }
        $hr_id = $stmt_company->insert_id;
        $stmt_company->close();

        // 2. Insert into internship_posts table
        $posted_by = $_SESSION['user_id'];
        $post_status = 'active';
        $description = "Manual entry post for a student.";

        $stmt_post = $conn->prepare("INSERT INTO internship_posts (internship_title, companyname, location, internship_description, date_posted, email, status, posted_by, hr_id) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?)");
        $stmt_post->bind_param("sssssissi", $student_post, $companyname, $location, $description, $email, $post_status, $posted_by, $hr_id);
        if (!$stmt_post->execute()) {
            throw new Exception("Failed to insert internship post: " . $stmt_post->error);
        }
        $post_id = $stmt_post->insert_id;
        $stmt_post->close();

        // 3. Update student's employment_status, hr_id, and post_id
        $stmt_student_status = $conn->prepare("UPDATE student SET employment_status = 'hired', hr_id = ?, post_id = ? WHERE student_id = ?");
        $stmt_student_status->bind_param("iii", $hr_id, $post_id, $student_id);
        if (!$stmt_student_status->execute()) {
            throw new Exception("Failed to update student employment status: " . $stmt_student_status->error);
        }
        $stmt_student_status->close();



        $company_data = [
            'hr_id' => $hr_id,
            'companyname' => $companyname,
            'location' => $location,
            'email' => $email,
            'profile_picture' => 'uploads/dp.jpg',
            'manual' => 'yes'
        ];

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Manual company added successfully.', 'company_data' => $company_data]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>

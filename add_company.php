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
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $radius = $_POST['radius'] ?? 100; // Default radius

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
        // 1. Insert into companyhr table
        $stmt_company = $conn->prepare("INSERT INTO companyhr (companyname, location, email, contact, manual) VALUES (?, ?, ?, ?, 'yes')");
        $stmt_company->bind_param("ssss", $companyname, $location, $email, $contact);
        if (!$stmt_company->execute()) {
            throw new Exception("Failed to insert company: " . $stmt_company->error);
        }
        $hr_id = $stmt_company->insert_id;
        $stmt_company->close();

        // 2. Insert into geofence_locations table for location data
        $location_name = $companyname . " - " . $location; // Combine for readable name
        $stmt_geofence = $conn->prepare("INSERT INTO geofence_locations (hr_id, location_name, latitude, longitude, radius) VALUES (?, ?, ?, ?, ?)");
        $stmt_geofence->bind_param("isddd", $hr_id, $location_name, $latitude, $longitude, $radius);
        if (!$stmt_geofence->execute()) {
            throw new Exception("Failed to insert geofence location: " . $stmt_geofence->error);
        }
        $location_id = $stmt_geofence->insert_id;
        $stmt_geofence->close();

        // 3. Insert into active_geofence table
        $set_by = $_SESSION['user_id'];
        $set_by_user_type = 'faculty';
        $stmt_active_geofence = $conn->prepare("INSERT INTO active_geofence (location_id, set_by, set_by_user_type, radius) VALUES (?, ?, ?, ?)");
        $stmt_active_geofence->bind_param("iisi", $location_id, $set_by, $set_by_user_type, $radius);
        if (!$stmt_active_geofence->execute()) {
            throw new Exception("Failed to insert active geofence: " . $stmt_active_geofence->error);
        }
        $stmt_active_geofence->close();

        // 4. Insert into hr_requests table with 'approved' status
        $stmt_hr_request = $conn->prepare("INSERT INTO hr_requests (hr_id, companyname, location, email, contact, status) VALUES (?, ?, ?, ?, ?, 'approved')");
        $stmt_hr_request->bind_param("issss", $hr_id, $companyname, $location, $email, $contact);
        if (!$stmt_hr_request->execute()) {
            throw new Exception("Failed to insert into hr_requests: " . $stmt_hr_request->error);
        }
        $stmt_hr_request->close();

        // 5. Insert into internship_posts table
        $posted_by = $_SESSION['user_id'];
        $post_status = 'active';

        $stmt_post = $conn->prepare("INSERT INTO internship_posts (internship_title, companyname, location, internship_description, date_posted, email, status, posted_by, hr_id) VALUES (?, ?, ?, ?, CURDATE(), ?, ?, ?, ?)");
        $description = "Manual entry post for a student.";
        $stmt_post->bind_param("sssssisi", $student_post, $companyname, $location, $description, $email, $post_status, $posted_by, $hr_id);
        if (!$stmt_post->execute()) {
            throw new Exception("Failed to insert internship post: " . $stmt_post->error);
        }
        $post_id = $stmt_post->insert_id;
        $stmt_post->close();

        // 6. Update student's employment_status if a student is assigned
        if (isset($_POST['student_id']) && !empty($_POST['student_id'])) {
            $student_id = intval($_POST['student_id']);
            $stmt_student_status = $conn->prepare("UPDATE student SET employment_status = 'hired', hr_id = ?, post_id = ? WHERE student_id = ?");
            $stmt_student_status->bind_param("iii", $hr_id, $post_id, $student_id);
            if (!$stmt_student_status->execute()) {
                throw new Exception("Failed to update student employment status: " . $stmt_student_status->error);
            }
            $stmt_student_status->close();
        }

        $company_data = [
            'hr_id' => $hr_id,
            'companyname' => $companyname,
            'location' => $location,
            'email' => $email,
            'profile_picture' => 'uploads/dp.jpg',
            'manual' => 'yes'
        ];

        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Company and post added successfully.', 'company_data' => $company_data]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>

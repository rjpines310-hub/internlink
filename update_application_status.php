<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = intval($_POST['student_id']);
    $hr_id      = intval($_POST['hr_id']);
    $action     = $_POST['action'];

    // Get latest application of this student for this HR
    $stmt = $conn->prepare("
        SELECT ia.application_id, ip.companyname, ia.post_id
        FROM intern_applications ia
        JOIN internship_posts ip ON ia.post_id = ip.post_id
        WHERE ia.student_id = ? AND ip.posted_by = ?
        ORDER BY ia.application_date DESC LIMIT 1
    ");
    $stmt->bind_param("ii", $student_id, $hr_id);
    $stmt->execute();
    $stmt->bind_result($application_id, $companyname, $post_id);
    $stmt->fetch();
    $stmt->close();

    if (!$application_id) {
        die("Application not found.");
    }

    if ($action === 'hire') {
        // Use a transaction to ensure atomicity
        $conn->begin_transaction();

        try {
            // 1. Update application status
            $stmt1 = $conn->prepare("UPDATE intern_applications SET status = 'Accepted' WHERE application_id = ?");
            $stmt1->bind_param("i", $application_id);
            $stmt1->execute();
            $stmt1->close();

            // 2. Update student record
            $stmt2 = $conn->prepare("UPDATE student SET employment_status = 'hired', hr_id = ?, post_id = ? WHERE student_id = ?");
            $stmt2->bind_param("iii", $hr_id, $post_id, $student_id);
            $stmt2->execute();
            $stmt2->close();

            // 3. Update internship post
            $stmt3 = $conn->prepare("UPDATE internship_posts SET student_id = ?, status = 'inactive' WHERE post_id = ?");
            $stmt3->bind_param("ii", $student_id, $post_id);
            $stmt3->execute();
            $stmt3->close();

            // Commit the transaction
            $conn->commit();

            echo json_encode(['success' => true, 'message' => 'Student has been hired successfully!']);

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Error hiring student: ' . $exception->getMessage()]);
        }

    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE intern_applications SET status = 'Rejected' WHERE application_id = ?");
        if ($stmt === false) {
            echo json_encode(['success' => false, 'message' => 'Prepare failed for reject action: ' . $conn->error]);
            exit();
        }
        $stmt->bind_param("i", $application_id);
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Application rejected.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not reject application. Error: ' . $stmt->error]);
        }
    } elseif ($action === 'offer') {
        $stmt = $conn->prepare("UPDATE intern_applications SET status = 'Offer Sent' WHERE application_id = ?");
        if ($stmt === false) {
            echo json_encode(['success' => false, 'message' => 'Prepare failed for offer action: ' . $conn->error]);
            exit();
        }
        $stmt->bind_param("i", $application_id);
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Offer sent to student.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not send offer. Error: ' . $stmt->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
}
?>

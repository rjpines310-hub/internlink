<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $application_id = intval($_POST['application_id']);
    $action = $_POST['action'];
    $student_id = $_SESSION['user_id'];

    if ($action === 'accept') {
        // Use a transaction to ensure atomicity
        $conn->begin_transaction();

        try {
            // 1. Update the accepted offer
            $stmt1 = $conn->prepare("UPDATE intern_applications SET status = 'Accepted' WHERE application_id = ? AND student_id = ?");
            if ($stmt1 === false) {
                throw new mysqli_sql_exception("Prepare failed for stmt1: " . $conn->error);
            }
            $stmt1->bind_param("ii", $application_id, $student_id);
            if (!$stmt1->execute()) {
                throw new mysqli_sql_exception("Execute failed for stmt1: " . $stmt1->error);
            }
            $stmt1->close();

            // 2. Reject all other offers for this student
            $stmt2 = $conn->prepare("UPDATE intern_applications SET status = 'Rejected' WHERE student_id = ? AND application_id != ? AND status = 'Offer Sent'");
            if ($stmt2 === false) {
                throw new mysqli_sql_exception("Prepare failed for stmt2: " . $conn->error);
            }
            $stmt2->bind_param("ii", $student_id, $application_id);
            if (!$stmt2->execute()) {
                throw new mysqli_sql_exception("Execute failed for stmt2: " . $stmt2->error);
            }
            $stmt2->close();

            // 3. Update the student's employment status
            $stmt3 = $conn->prepare("UPDATE student SET employment_status = 'hired' WHERE student_id = ?");
            if ($stmt3 === false) {
                throw new mysqli_sql_exception("Prepare failed for stmt3: " . $conn->error);
            }
            $stmt3->bind_param("i", $student_id);
            if (!$stmt3->execute()) {
                throw new mysqli_sql_exception("Execute failed for stmt3: " . $stmt3->error);
            }
            $stmt3->close();

            // 4. Fetch post_id and hr_id for the accepted offer
            $stmt4 = $conn->prepare("
                SELECT ia.post_id, ip.hr_id 
                FROM intern_applications ia
                JOIN internship_posts ip ON ia.post_id = ip.post_id
                WHERE ia.application_id = ?
            ");
            if ($stmt4 === false) {
                throw new mysqli_sql_exception("Prepare failed for stmt4: " . $conn->error);
            }
            $stmt4->bind_param("i", $application_id);
            if (!$stmt4->execute()) {
                throw new mysqli_sql_exception("Execute failed for stmt4: " . $stmt4->error);
            }
            $result4 = $stmt4->get_result();
            $offer_details = $result4->fetch_assoc();
            $stmt4->close();

            if ($offer_details) {
                $accepted_post_id = $offer_details['post_id'];
                $accepted_hr_id = $offer_details['hr_id'];

                // 5. Update student table with post_id and hr_id
                $stmt5 = $conn->prepare("UPDATE student SET post_id = ?, hr_id = ? WHERE student_id = ?");
                if ($stmt5 === false) {
                    throw new mysqli_sql_exception("Prepare failed for stmt5: " . $conn->error);
                }
                $stmt5->bind_param("iii", $accepted_post_id, $accepted_hr_id, $student_id);
                if (!$stmt5->execute()) {
                    throw new mysqli_sql_exception("Execute failed for stmt5: " . $stmt5->error);
                }
                $stmt5->close();
            } else {
                throw new mysqli_sql_exception("Offer details not found for application_id: " . $application_id);
            }

            // Commit the transaction
            $conn->commit();

            echo json_encode(['success' => true, 'message' => 'Offer accepted successfully!']);
        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'An error occurred while accepting the offer: ' . $exception->getMessage()]);
        }
    } elseif ($action === 'reject') {
        $stmt = $conn->prepare("UPDATE intern_applications SET status = 'Rejected' WHERE application_id = ? AND student_id = ?");
        if ($stmt === false) {
            echo json_encode(['success' => false, 'message' => 'Prepare failed for reject action: ' . $conn->error]);
            exit();
        }
        $stmt->bind_param("ii", $application_id, $student_id);
        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(['success' => true, 'message' => 'Offer rejected.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Could not reject offer. Error: ' . $stmt->error]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
    }
}
?>

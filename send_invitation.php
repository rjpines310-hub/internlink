<?php
session_start();
include 'db.php';

// Ensure no HTML error output leaks to the client and always return JSON
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
header('Content-Type: application/json');

// Start output buffering to capture any accidental output (warnings/notices)
ob_start();

// PHPMailer Autoload
require 'vendor/autoload.php'; // Adjust path as necessary

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

try {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
        // discard buffer but log if any unexpected output occurred
        $buf = ob_get_clean();
        if (!empty($buf)) error_log("send_invitation unexpected output (unauth): " . $buf);
        exit;
    }

    // Log the incoming request (server-side)
    error_log("send_invitation request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? '';
        $roleInput = $_POST['role'] ?? '';

        if (empty($email) || empty($roleInput)) {
            echo json_encode(['success' => false, 'message' => 'Email and Role are required.']);
            $buf = ob_get_clean();
            if (!empty($buf)) error_log("send_invitation unexpected output (missing fields): " . $buf);
            exit;
        }

        // Normalize email and role input
        $email = trim($email);
        $roleRaw = trim($roleInput);

        // Map common role variants to canonical roles
        $lc = strtolower($roleRaw);
        if ($lc === 'student') {
            $role = 'Student';
        } elseif (in_array($lc, ['company hr','company','companyhr','hr','company_hr'], true)) {
            $role = 'Company HR';
        } elseif ($lc === 'supervisor') {
            $role = 'Supervisor';
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid role specified.']);
            $buf = ob_get_clean();
            if (!empty($buf)) error_log("send_invitation unexpected output (invalid role mapping): " . $buf);
            exit;
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']);
            $buf = ob_get_clean();
            if (!empty($buf)) error_log("send_invitation unexpected output (invalid email): " . $buf);
            exit;
        }

        $code = '';
        $invitation_id = null;
        $success_db_op = false;

        // Check for an existing unused invitation for this email and role (case-insensitive role match)
        $check_stmt = $conn->prepare("SELECT id, code FROM invitations WHERE email = ? AND LOWER(role) = LOWER(?) AND status = 'unused' LIMIT 1");
        if ($check_stmt) {
            $check_stmt->bind_param("ss", $email, $role);
            $check_stmt->execute();
            $check_stmt->bind_result($invitation_id, $code);
            $check_stmt->fetch();
            $check_stmt->close();
        }

        if ($invitation_id) {
            // Reuse existing invitation code and update sent_at timestamp
            $update_stmt = $conn->prepare("UPDATE invitations SET sent_at = CURRENT_TIMESTAMP WHERE id = ?");
            if ($update_stmt) {
                $update_stmt->bind_param("i", $invitation_id);
                $update_stmt->execute();
                $update_stmt->close();
                $success_db_op = true;
            }
        } else {
            // Generate a new unique invitation code
            do {
                $code = bin2hex(random_bytes(4)); // 8 characters
                $stmt_check_code = $conn->prepare("SELECT id FROM invitations WHERE code = ? LIMIT 1");
                if ($stmt_check_code) {
                    $stmt_check_code->bind_param("s", $code);
                    $stmt_check_code->execute();
                    $stmt_check_code->store_result();
                    $code_exists = $stmt_check_code->num_rows > 0;
                    $stmt_check_code->close();
                } else {
                    $code_exists = false;
                }
            } while ($code_exists);

            // Save new invitation to database using canonical role value
            $insert_stmt = $conn->prepare("INSERT INTO invitations (email, code, role, status, sent_at) VALUES (?, ?, ?, 'unused', CURRENT_TIMESTAMP)");
            if ($insert_stmt) {
                $insert_stmt->bind_param("sss", $email, $code, $role);
                $success_db_op = $insert_stmt->execute();
                $insert_stmt->close();
            } else {
                $success_db_op = false;
            }
        }

        if ($success_db_op) {
            // Send invitation email
            $mail = new PHPMailer(true);
            try {
                //Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username   = 'rjpines310@gmail.com'; // SMTP username
                $mail->Password   = 'rj12329pogiako123';    // SMTP password
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = 587;

                //Recipients
                $mail->setFrom('rjpines310@gmail.com', 'InternLink Invitations');
                $mail->addAddress($email);

                // Content
                $registrationLink = "http://localhost/capstone/signup.php?invite=" . urlencode($code);
                $mail->isHTML(true);
                $mail->Subject = 'Your InternLink Invitation';
                $mail->Body    = "Hello,<br><br>You have been invited to register as a <b>" . htmlspecialchars($role) . "</b> on InternLink.<br>Your invitation code is: <b>" . htmlspecialchars($code) . "</b><br><br>Please use the following link to register: <a href='" . htmlspecialchars($registrationLink) . "'>" . htmlspecialchars($registrationLink) . "</a><br><br>Thank you!";
                $mail->AltBody = "Hello,\n\nYou have been invited to register as a " . htmlspecialchars($role) . " on InternLink.\nYour invitation code is: " . htmlspecialchars($code) . "\n\nPlease use the following link to register: " . htmlspecialchars($registrationLink) . "\n\nThank you!";

                $mail->send();

                // Clean any accidental output and respond with JSON
                $buf = ob_get_clean();
                if (!empty($buf)) error_log("send_invitation unexpected output (after send): " . $buf);

                echo json_encode(['success' => true, 'message' => 'Invitation sent successfully.']);
                exit;
            } catch (Exception $e) {
                // Log and return JSON without exposing HTML
                error_log("send_invitation mailer error: " . $mail->ErrorInfo . " | Exception: " . $e->getMessage());
                $buf = ob_get_clean();
                if (!empty($buf)) error_log("send_invitation unexpected output (mailer exception): " . $buf);

                echo json_encode(['success' => false, 'message' => 'Invitation saved, but email could not be sent. Please check mailer configuration.']);
                exit;
            }
        } else {
            $buf = ob_get_clean();
            if (!empty($buf)) error_log("send_invitation unexpected output (db fail): " . $buf);
            echo json_encode(['success' => false, 'message' => 'Failed to save invitation to database.']);
            exit;
        }
    } else {
        $buf = ob_get_clean();
        if (!empty($buf)) error_log("send_invitation unexpected output (bad method): " . $buf);
        echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
        exit;
    }
} catch (\Throwable $t) {
    // Ensure any unexpected fatal errors also produce JSON and get logged
    $buf = ob_get_clean();
    if (!empty($buf)) error_log("send_invitation unexpected output (throwable): " . $buf);
    error_log("send_invitation fatal error: " . $t->getMessage() . " in " . $t->getFile() . ":" . $t->getLine());
    echo json_encode(['success' => false, 'message' => 'An internal error occurred.']);
    exit;
}
?>

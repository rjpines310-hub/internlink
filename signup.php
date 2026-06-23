<?php
$host = 'localhost';
$dbname = 'capstone';
$user = 'root';
$pass = '';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die(json_encode(['status' => 'error', 'messages' => ['Database connection failed']]));
}

// Sanitize and fetch input
$role = trim($_POST['role'] ?? '');
$email = trim($_POST['email'] ?? '');

// Create a normalized role string for invitation comparison
$invitation_check_role = $role;
if (strtolower($invitation_check_role) === 'companyhr') {
    $invitation_check_role = 'Company HR';
}
$contact = trim($_POST['contact'] ?? '');
$password = $_POST['password'] ?? '';
$confirm_password = $_POST['confirm'] ?? '';
$invite_code = $_POST['invite_code'] ?? ''; // Invitation code

$errors = [];

// Validate invitation code
if (empty($invite_code)) {
    $errors[] = "An invitation code is required to register.";
} else {
    $stmt_invite = $conn->prepare("SELECT id, status, role FROM invitations WHERE email = ? AND code = ?");
    $stmt_invite->bind_param("ss", $email, $invite_code);
    $stmt_invite->execute();
    $stmt_invite->store_result();

    if ($stmt_invite->num_rows === 0) {
        $errors[] = "Invalid invitation code or email.";
    } else {
        $stmt_invite->bind_result($invitation_id, $invitation_status, $invitation_role);
        $stmt_invite->fetch();

        if ($invitation_status === 'used') {
            $errors[] = "Invitation code has already been used.";
        }
        // Ensure the role from the invitation matches the role the user is trying to register as
        if (strtolower(trim($invitation_role)) !== strtolower(trim($invitation_check_role))) {
            $errors[] = "Invitation is for a different role.";
        }
    }
    $stmt_invite->close();
}

// Validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/@[\w]+\.(com)$/', $email)) {
    $errors[] = "Invalid email format";
}

if (!preg_match('/^09\d{9}$/', $contact)) {
    $errors[] = "Invalid contact number";
}

if (strlen($password) < 6) {
    $errors[] = "Password too short";
}

if ($password !== $confirm_password) {
    $errors[] = "Passwords do not match";
}

if (empty($role)) {
    $errors[] = "Role is required";
}

if (!empty($errors)) {
    echo json_encode(['status' => 'error', 'messages' => $errors]);
    exit;
}

$hashed_password = password_hash($password, PASSWORD_DEFAULT);

// Role-based insertion
switch ($role) {
    case 'student':
        $studentid = trim($_POST['studentid'] ?? '');
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');
        $section = trim($_POST['section'] ?? '');

        $stmt = $conn->prepare("INSERT INTO student (studentid, firstname, lastname, section, email, contact, password) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssss", $studentid, $firstname, $lastname, $section, $email, $contact, $hashed_password);
        break;

    case 'faculty':
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');

        $stmt = $conn->prepare("INSERT INTO faculty (firstname, lastname, email, contact, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $firstname, $lastname, $email, $contact, $hashed_password);
        break;

    case 'companyhr':
        $companyname = trim($_POST['companyname'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $landline = trim($_POST['landline'] ?? '');
        $profile_picture_path = 'uploads/dp.jpg'; // Default profile picture

        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "uploads/";
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $new_file_name = uniqid('profile_') . '.' . $file_extension;
            $target_file = $target_dir . $new_file_name;

            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_file)) {
                $profile_picture_path = $target_file;
            } else {
                $errors[] = "Failed to upload profile picture.";
            }
        }

        if (!empty($errors)) {
            echo json_encode(['status' => 'error', 'messages' => $errors]);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO companyhr (companyname, location, email, contact, landline, password, manual, profile_picture) VALUES (?, ?, ?, ?, ?, ?, 'no', ?)");
        $stmt->bind_param("sssssss", $companyname, $location, $email, $contact, $landline, $hashed_password, $profile_picture_path);
        
        if (!$stmt->execute()) {
            echo json_encode(['status' => 'error', 'messages' => ['Company HR signup failed: ' . $stmt->error]]);
            exit;
        }
        $hr_id = $stmt->insert_id;
        $stmt->close();

        // If registration is successful, mark the invitation as 'used'
        if (!empty($invite_code)) {
            $update_invite_stmt = $conn->prepare("UPDATE invitations SET status = 'used' WHERE email = ? AND code = ?");
            $update_invite_stmt->bind_param("ss", $email, $invite_code);
            $update_invite_stmt->execute();
            $update_invite_stmt->close();
        }

        $company_data = [
            'hr_id' => $hr_id,
            'companyname' => $companyname,
            'location' => $location,
            'email' => $email,
            'profile_picture' => $profile_picture_path,
            'manual' => 'no'
        ];
        echo json_encode(['status' => 'success', 'message' => 'Company HR account created successfully.', 'company_data' => $company_data]);
        exit; // Exit after companyhr case to prevent default success message
        break;
    case 'supervisor':
        $firstname = trim($_POST['firstname'] ?? '');
        $lastname = trim($_POST['lastname'] ?? '');
        $hr_id = trim($_POST['hr_id'] ?? '');

        // Fetch companyname from companyhr table
        $company_stmt = $conn->prepare("SELECT companyname FROM companyhr WHERE hr_id = ?");
        $company_stmt->bind_param("i", $hr_id);
        $company_stmt->execute();
        $company_stmt->bind_result($companyname);
        $company_stmt->fetch();
        $company_stmt->close();

        if (empty($companyname)) {
            echo json_encode(['status' => 'error', 'messages' => ['Invalid HR ID']]);
            exit;
        }

        $stmt = $conn->prepare("INSERT INTO supervisor (firstname, lastname, companyname, email, contact, password, hr_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssi", $firstname, $lastname, $companyname, $email, $contact, $hashed_password, $hr_id);
        break;

    default:
        echo json_encode(['status' => 'error', 'messages' => ['Invalid role']]);
        exit;
}

if ($stmt->execute()) {
    // If registration is successful, mark the invitation as 'used'
    if (!empty($invite_code)) {
        $update_invite_stmt = $conn->prepare("UPDATE invitations SET status = 'used' WHERE email = ? AND code = ?");
        $update_invite_stmt->bind_param("ss", $email, $invite_code);
        $update_invite_stmt->execute();
        $update_invite_stmt->close();
    }
    echo json_encode(['status' => 'success', 'message' => 'Account created successfully.']);
} else {
    echo json_encode(['status' => 'error', 'messages' => ['Signup failed: ' . $stmt->error]]);
}

$stmt->close();
$conn->close();
?>

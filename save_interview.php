<?php
// Minimal robust save_interview.php that always returns JSON
// Do NOT output any HTML or debugging text here.
// Errors are logged to the PHP error log.

ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please login.']);
    exit;
}

require_once 'db.php'; // must set $conn (mysqli)

// Read inputs (prefer $_POST, but allow JSON payload)
$raw_input = file_get_contents('php://input');

$post = $_POST;
$json = json_decode($raw_input, true);
if (!is_array($post)) $post = [];
if (is_array($json)) {
    // don't override explicit $_POST values
    $post = array_merge($json, $post);
}

$hr_id = isset($post['hr_id']) ? (int)$post['hr_id'] : (int)$_SESSION['user_id'];
$student_id = isset($post['student_id']) ? (int)$post['student_id'] : 0;
$application_id = isset($post['application_id']) ? (int)$post['application_id'] : 0;
$interview_datetime = isset($post['interview_datetime']) ? trim($post['interview_datetime']) : '';
$location = isset($post['location']) ? trim($post['location']) : '';
$online_link = isset($post['online_link']) ? trim($post['online_link']) : '';
$exact_address = isset($post['exact_address']) ? trim($post['exact_address']) : '';
$remarks = isset($post['remarks']) ? trim($post['remarks']) : '';

// company + title from candidates of keys
$companyname = '';
$internship_title = '';

$companyKeys = ['companyname','company_name','company'];
$titleKeys = ['internship_title','post_title','internship_post_title','title','internshipTitle'];

foreach ($companyKeys as $k) {
    if (isset($post[$k]) && $companyname === '') $companyname = trim($post[$k]);
}
foreach ($titleKeys as $k) {
    if (isset($post[$k]) && $internship_title === '') $internship_title = trim($post[$k]);
}

// DB fallback: if missing, resolve via application -> post_id -> internship_posts
if (($companyname === '' || $internship_title === '') && $application_id > 0) {
    $post_id = 0;
    $pq = $conn->prepare("SELECT post_id FROM intern_applications WHERE application_id = ? LIMIT 1");
    if ($pq) {
        $pq->bind_param('i', $application_id);
        $pq->execute();
        $pq->bind_result($post_id);
        $pq->fetch();
        $pq->close();
    } else {
        error_log("save_interview: failed prepare intern_applications lookup: " . $conn->error);
    }

    if ($post_id) {
        $post_id_int = (int)$post_id;
        $res = $conn->prepare("SELECT companyname, company_name, company, post_title, internship_post_title, title FROM internship_posts WHERE post_id = ? LIMIT 1");
        if ($res) {
            $res->bind_param('i', $post_id_int);
            $res->execute();
            $res->bind_result($c1, $c2, $c3, $t1, $t2, $t3);
            if ($res->fetch()) {
                if ($companyname === '') {
                    if (!empty($c1)) $companyname = trim($c1);
                    elseif (!empty($c2)) $companyname = trim($c2);
                    elseif (!empty($c3)) $companyname = trim($c3);
                }
                if ($internship_title === '') {
                    if (!empty($t1)) $internship_title = trim($t1);
                    elseif (!empty($t2)) $internship_title = trim($t2);
                    elseif (!empty($t3)) $internship_title = trim($t3);
                }
            }
            $res->close();
        } else {
            error_log("save_interview: failed prepare internship_posts lookup: " . $conn->error);
        }
    } else {
        error_log("save_interview: no post_id found for application_id={$application_id}");
    }
}

// Quick debug log for incoming parsed values (remove in production if desired)
error_log("save_interview parsed: hr_id={$hr_id} student_id={$student_id} application_id={$application_id} companyname={$companyname} internship_title={$internship_title} content_type=" . ($_SERVER['CONTENT_TYPE'] ?? ''));

// Validate required fields
if ($hr_id <= 0 || $student_id <= 0 || $application_id <= 0 || $interview_datetime === '' || $location === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}
if ($companyname === '' || $internship_title === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Company name and internship title are required.']);
    exit;
}

// Validate datetime
$dt = date_create($interview_datetime);
if ($dt === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid interview date/time.']);
    exit;
}

// Insert interview and update application status
$conn->begin_transaction();

try {
    $sql = "INSERT INTO interviews (hr_id, student_id, application_id, companyname, internship_title, interview_datetime, location, online_link, exact_address, remarks, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    if (!$stmt) throw new Exception('Prepare failed (INSERT): ' . $conn->error);

    // bind: i i i s s s s s s s
    if (!$stmt->bind_param('iiisssssss', $hr_id, $student_id, $application_id, $companyname, $internship_title, $interview_datetime, $location, $online_link, $exact_address, $remarks)) {
        throw new Exception('bind_param failed (INSERT): ' . $stmt->error);
    }
    if (!$stmt->execute()) throw new Exception('Execute failed (INSERT): ' . $stmt->error);

    $interview_id = $stmt->insert_id;
    $stmt->close();

    $u = $conn->prepare("UPDATE intern_applications SET status = 'for interview' WHERE application_id = ? LIMIT 1");
    if (!$u) throw new Exception('Prepare failed (UPDATE): ' . $conn->error);
    if (!$u->bind_param('i', $application_id)) throw new Exception('bind_param failed (UPDATE): ' . $u->error);
    if (!$u->execute()) throw new Exception('Execute failed (UPDATE): ' . $u->error);

    if ($u->affected_rows === 0) {
        error_log("save_interview: UPDATE affected_rows = 0 for application_id={$application_id}");
    }
    $u->close();

    $conn->commit();

    echo json_encode(['success' => true, 'message' => 'Interview saved successfully.', 'interview_id' => $interview_id]);
    exit;
} catch (Exception $e) {
    $conn->rollback();
    error_log("save_interview.php error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error while saving interview.']);
    exit;
}
?>

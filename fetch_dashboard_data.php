<?php
 session_start();
include 'db.php';

// Check database connection
if ($conn->connect_error) {
    error_log("Database connection failed: " . $conn->connect_error);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
    exit();
}

header('Content-Type: application/json');

$response = ['success' => false, 'message' => 'Invalid request'];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    $response['message'] = 'User not authenticated.';
    echo json_encode($response);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];
$action = $_GET['action'] ?? '';

if ($action == 'welcome') {
    error_log("Request: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);
    $response = ['success' => true, 'message' => 'Welcome to the Capstone Project Dashboard!'];
    echo json_encode($response);
    $conn->close();
    exit();
}

try {
    switch ($role) {
        case 'student':
            switch ($action) {
                case 'top_companies_ojt':
                    $query = "SELECT ia.companyname, COUNT(ia.student_id) AS total_students, ch.email, ch.profile_picture AS logo
                                     FROM intern_applications ia
                                     JOIN companyhr ch ON ia.companyname = ch.companyname
                                     WHERE ia.status='Accepted'
                                     GROUP BY ia.companyname, ch.email, ch.profile_picture
                                     ORDER BY total_students DESC LIMIT 3";
                    $result = $conn->query($query);
                    if ($result) {
                        $companies = [];
                        while ($row = $result->fetch_assoc()) {
                            $companies[] = $row;
                        }
                        $response = ['success' => true, 'companies' => $companies];
                        if (empty($companies)) {
                            $response['message'] = 'No data available for top companies with OJT students. Query: ' . $query;
                        }
                    } else {
                        error_log("Error fetching top companies with OJT students: " . $conn->error . " Query: " . $query);
                        $response['message'] = 'Error fetching top companies with OJT students: ' . $conn->error . '. Query: ' . $query;
                    }
                    break;
                case 'top_companies_posts':
                    $query = "SELECT ch.companyname, ch.profile_picture AS logo, COUNT(ip.post_id) AS total_posts 
                              FROM internship_posts ip
                              JOIN companyhr ch ON ip.hr_id = ch.hr_id
                              GROUP BY ch.companyname, ch.profile_picture
                              ORDER BY total_posts DESC LIMIT 3";
                    $result = $conn->query($query);
                    if ($result) {
                        $companies = [];
                        while ($row = $result->fetch_assoc()) {
                            $companies[] = $row;
                        }
                        $response = ['success' => true, 'companies' => $companies];
                    } else {
                        error_log("Error fetching top companies with internship posts: " . $conn->error . " Query: " . $query);
                        $response['message'] = 'Error fetching top companies with internship posts: ' . $conn->error . '. Query: ' . $query;
                    }
                    break;
                case 'newest_internship_posts':
                    $query = "SELECT ip.post_id, ip.internship_title, ch.companyname, ch.profile_picture AS logo, ip.date_posted
                              FROM internship_posts ip
                              JOIN companyhr ch ON ip.hr_id = ch.hr_id
                              WHERE ip.status = 'Active'
                              ORDER BY ip.date_posted DESC
                              LIMIT 3";
                    $result = $conn->query($query);
                    if ($result) {
                        $posts = [];
                        while ($row = $result->fetch_assoc()) {
                            $posts[] = $row;
                        }
                        $response = ['success' => true, 'posts' => $posts];
                    } else {
                        error_log("Error fetching newest internship posts: " . $conn->error . " Query: " . $query);
                        $response['message'] = 'Error fetching newest internship posts: ' . $conn->error . '. Query: ' . $query;
                    }
                    break;
                case 'top_companies_ojt_students':
                    $query = "SELECT ch.companyname, COUNT(ia.student_id) AS total_students, ch.email, ch.profile_picture AS logo
                              FROM intern_applications ia
                              JOIN internship_posts ip ON ia.post_id = ip.post_id
                              JOIN companyhr ch ON ip.hr_id = ch.hr_id
                              WHERE ia.status='Accepted'
                              GROUP BY ch.companyname, ch.email, ch.profile_picture
                              ORDER BY total_students DESC LIMIT 3";
                    $result = $conn->query($query);
                    if ($result) {
                        $companies = [];
                        while ($row = $result->fetch_assoc()) {
                            $companies[] = $row;
                        }
                        $response = ['success' => true, 'companies' => $companies];
                        if (empty($companies)) {
                            $response['message'] = 'No data available for top companies with OJT students.';
                        }
                    } else {
                        error_log("Error fetching top companies with OJT students: " . $conn->error . " Query: " . $query);
                        $response['message'] = 'Error fetching top companies with OJT students: ' . $conn->error . '. Query: ' . $query;
                    }
                    break;
                default:
                    $response['message'] = 'Invalid action for student dashboard.';
                    break;
            }
            break;

        case 'faculty':
            switch ($action) {
                case 'top_performing_interns':
                    $query = "SELECT s.student_id, s.firstname, s.lastname, s.profile_picture, o.overall_average, ch.companyname
                              FROM student_overview o
                              JOIN student s ON s.student_id = o.student_id
                              LEFT JOIN companyhr ch ON s.hr_id = ch.hr_id
                              WHERE o.employment_status != 'completed'
                              ORDER BY o.overall_average DESC
                              LIMIT 3";
                    $result = $conn->query($query);
                    if ($result) {
                        $interns = [];
                        while ($row = $result->fetch_assoc()) {
                            $interns[] = $row;
                        }
                        $response = ['success' => true, 'interns' => $interns];
                    } else {
                        error_log("Error fetching top performing interns: " . $conn->error);
                        $response['message'] = 'Error fetching top performing interns.';
                    }
                    break;
                case 'interns_need_help':
                    $query = "SELECT s.student_id, s.firstname, s.lastname, s.profile_picture, o.overall_average, ch.companyname
                              FROM student_overview o
                              JOIN student s ON s.student_id = o.student_id
                              LEFT JOIN companyhr ch ON s.hr_id = ch.hr_id
                              WHERE o.employment_status != 'completed'
                              ORDER BY o.overall_average ASC
                              LIMIT 3";
                    $result = $conn->query($query);
                    if ($result) {
                        $interns = [];
                        while ($row = $result->fetch_assoc()) {
                            $interns[] = $row;
                        }
                        $response = ['success' => true, 'interns' => $interns];
                    } else {
                        error_log("Error fetching interns who need help: " . $conn->error);
                        $response['message'] = 'Error fetching interns who need help.';
                    }
                    break;
            case 'top_companies_posts': // Re-using from student dashboard
                $query = "SELECT ch.companyname, ch.profile_picture AS logo, COUNT(ip.post_id) AS total_posts 
                          FROM internship_posts ip
                          JOIN companyhr ch ON ip.hr_id = ch.hr_id
                          GROUP BY ch.companyname, ch.profile_picture
                          ORDER BY total_posts DESC LIMIT 3";
                $result = $conn->query($query);
                    if ($result) {
                        $companies = [];
                        while ($row = $result->fetch_assoc()) {
                            $companies[] = $row;
                        }
                        $response = ['success' => true, 'companies' => $companies];
                    } else {
                        error_log("Error fetching top companies with internship posts: " . $conn->error);
                        $response['message'] = 'Error fetching top companies with internship posts.';
                    }
                    break;
                default:
                    $response['message'] = 'Invalid action for faculty dashboard.';
                    break;
            }
            break;

        case 'supervisor':
            $supervisor_id = $user_id; // Assuming user_id is supervisor_id for supervisor role
            switch ($action) {
                case 'most_interns_in_progress':
                    $query = "SELECT s.student_id, CONCAT(s.firstname, ' ', s.lastname) AS student_name, s.profile_picture, o.overall_average, ch.companyname
                              FROM student s
                              JOIN student_overview o ON s.student_id = o.student_id
                              LEFT JOIN companyhr ch ON s.hr_id = ch.hr_id
                              WHERE s.supervisor_id = ?
                              ORDER BY o.overall_average DESC
                              LIMIT 3";
                    $stmt = $conn->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param("i", $supervisor_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $interns = [];
                        while ($row = $result->fetch_assoc()) {
                            $interns[] = $row;
                        }
                        $response = ['success' => true, 'interns' => $interns];
                        $stmt->close();
                    } else {
                        error_log("Error preparing statement for most interns in progress: " . $conn->error);
                        $response['message'] = 'Error preparing statement for most interns in progress.';
                    }
                    break;
                case 'newest_submitted_tasks':
                    $query = "SELECT t.id, t.task_description, t.submitted_at AS submission_date, CONCAT(s.firstname, ' ', s.lastname) AS student_name, s.profile_picture
                              FROM tasks t
                              JOIN student s ON t.student_id = s.student_id
                              WHERE t.supervisor_id = ? AND t.status='submitted' 
                              ORDER BY t.submitted_at DESC LIMIT 3";
                    $stmt = $conn->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param("i", $supervisor_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $tasks = [];
                        while ($row = $result->fetch_assoc()) {
                            // Extract task title from the first line of task_description
                            $description_lines = explode("\n", $row['task_description']);
                            $row['task_title'] = trim($description_lines[0]);
                            unset($row['task_description']); // Remove original description if not needed
                            $tasks[] = $row;
                        }
                        $response = ['success' => true, 'tasks' => $tasks];
                        $stmt->close();
                    } else {
                        error_log("Error preparing statement for newest submitted tasks: " . $conn->error);
                        $response['message'] = 'Error preparing statement for newest submitted tasks.';
                    }
                    break;
                case 'newest_attendance_validation':
                    $query = "SELECT tc.timecard_id, tc.time_in, tc.time_out, tc.status, CONCAT(s.firstname, ' ', s.lastname) AS student_name, s.profile_picture
                              FROM timecard tc
                              JOIN student s ON tc.student_id = s.student_id
                              WHERE s.supervisor_id = ? AND tc.status='pending' 
                              ORDER BY tc.time_in DESC LIMIT 3";
                    $stmt = $conn->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param("i", $supervisor_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $attendance = [];
                        while ($row = $result->fetch_assoc()) {
                            $attendance[] = $row;
                        }
                        $response = ['success' => true, 'attendance' => $attendance];
                        $stmt->close();
                    } else {
                        error_log("Error preparing statement for attendance needing validation: " . $conn->error);
                        $response['message'] = 'Error preparing statement for attendance needing validation.';
                    }
                    break;
                default:
                    $response['message'] = 'Invalid action for supervisor dashboard.';
                    break;
            }
            break;

        case 'companyhr':
            $hr_id = $user_id; // Assuming user_id is hr_id for companyhr role
            switch ($action) {
                case 'newest_applicants':
                    $query = "SELECT ia.application_id, CONCAT(s.firstname, ' ', s.lastname) AS applicant_name, s.email, s.profile_picture, ip.internship_title, ia.application_date AS date_applied
                              FROM intern_applications ia
                              LEFT JOIN student s ON ia.student_id = s.student_id
                              LEFT JOIN internship_posts ip ON ia.post_id = ip.post_id
                              WHERE ip.hr_id = ?
                              ORDER BY ia.application_date DESC LIMIT 5";
                    $stmt = $conn->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param("i", $hr_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $applicants = [];
                        while ($row = $result->fetch_assoc()) {
                            $applicants[] = $row;
                        }
                        $response = ['success' => true, 'applicants' => $applicants];
                        $stmt->close();
                    } else {
                        error_log("Error preparing statement for newest applicants: " . $conn->error);
                        $response['message'] = 'Error preparing statement for newest applicants.';
                    }
                    break;
                case 'interns_in_progress_count': // This case is not directly used for a card, but for a count. No changes needed here.
                    $query = "SELECT COUNT(*) AS total_interns 
                              FROM intern_applications ia
                              JOIN internship_posts ip ON ia.post_id = ip.post_id
                              WHERE ip.hr_id = ? AND ia.status='accepted'"; // Assuming 'accepted' means 'in progress' for HR dashboard
                    $stmt = $conn->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param("i", $hr_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $count = $result->fetch_assoc()['total_interns'] ?? 0;
                        $response = ['success' => true, 'total_interns' => $count];
                        $stmt->close();
                    } else {
                        error_log("Error preparing statement for interns in progress count: " . $conn->error);
                        $response['message'] = 'Error preparing statement for interns in progress count.';
                    }
                    break;
                case 'interns_currently_in_progress':
                    $query = "SELECT s.student_id, CONCAT(s.firstname, ' ', s.lastname) AS intern_name, s.email, s.profile_picture, so.overall_average AS progress_percent, ia.application_date AS start_date
                              FROM intern_applications ia
                              JOIN student s ON ia.student_id = s.student_id
                              JOIN internship_posts ip ON ia.post_id = ip.post_id
                              LEFT JOIN student_overview so ON s.student_id = so.student_id
                              WHERE ip.hr_id = ? AND ia.status='accepted'
                              ORDER BY ia.application_date DESC LIMIT 5";
                    $stmt = $conn->prepare($query);
                    if ($stmt) {
                        $stmt->bind_param("i", $hr_id);
                        $stmt->execute();
                        $result = $stmt->get_result();
                        $interns = [];
                        while ($row = $result->fetch_assoc()) {
                            $interns[] = $row;
                        }
                        $response = ['success' => true, 'interns' => $interns];
                        $stmt->close();
                    } else {
                        error_log("Error preparing statement for interns currently in progress: " . $conn->error);
                        $response['message'] = 'Error preparing statement for interns currently in progress.';
                    }
                    break;
                default:
                    $response['message'] = 'Invalid action for company HR dashboard.';
                    break;
            }
            break;

        default:
            $response['message'] = 'Invalid user role.';
            break;
    }
} catch (Exception $e) {
    error_log("Caught exception in fetch_dashboard_data.php: " . $e->getMessage());
    $response = ['success' => false, 'message' => 'An unexpected error occurred: ' . $e->getMessage()];
}

echo json_encode($response);
$conn->close();
?>

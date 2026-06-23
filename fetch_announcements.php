<?php
error_reporting(0); // Suppress all PHP errors and warnings
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $user_role = $_SESSION['role']; // Get the role of the logged-in user

    // Validate user_role against allowed roles
    $allowedRoles = ['student', 'supervisor', 'companyhr'];
    if (!in_array($user_role, $allowedRoles)) {
        echo json_encode(['success' => false, 'message' => 'Invalid user role for fetching announcements.']);
        exit;
    }

    $stmt = $conn->prepare("
        SELECT 
            a.id, a.title, a.content, a.date_posted,
            f.firstname, f.lastname
        FROM announcements a
        JOIN announcement_audiences aa ON a.id = aa.announcement_id
        JOIN faculty f ON a.faculty_id = f.faculty_id
        WHERE aa.audience_role = ?
        ORDER BY a.date_posted DESC
    ");
    $stmt->bind_param("s", $user_role);
    $stmt->execute();
    $result = $stmt->get_result();

    $announcements = [];
    while ($row = $result->fetch_assoc()) {
        $row['faculty_name'] = htmlspecialchars($row['firstname'] . ' ' . $row['lastname']);
        unset($row['firstname']); // Remove raw names
        unset($row['lastname']);
        $announcements[] = $row;
    }

    echo json_encode(['success' => true, 'announcements' => $announcements]);

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
ob_end_flush(); // Ensure the buffer is sent
?>

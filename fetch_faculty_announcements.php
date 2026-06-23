<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $faculty_id = $_SESSION['user_id'];

    $stmt = $conn->prepare("
        SELECT 
            a.id, a.title, a.content, a.date_posted,
            GROUP_CONCAT(aa.audience_role ORDER BY aa.audience_role ASC) AS audiences
        FROM announcements a
        LEFT JOIN announcement_audiences aa ON a.id = aa.announcement_id
        WHERE a.faculty_id = ?
        GROUP BY a.id
        ORDER BY a.date_posted DESC
    ");
    $stmt->bind_param("i", $faculty_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $announcements = [];
    while ($row = $result->fetch_assoc()) {
        $row['audiences'] = $row['audiences'] ? explode(',', $row['audiences']) : [];
        $announcements[] = $row;
    }

    echo json_encode(['success' => true, 'announcements' => $announcements]);

    $stmt->close();
    $conn->close();
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
?>

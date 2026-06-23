<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    exit();
}

include 'db.php';
$userId = $_SESSION['user_id'];

$action = $_GET['action'] ?? '';

if ($action === 'conversations') {
    $conversations = [];

    $stmt = $conn->prepare("SELECT DISTINCT
        CASE WHEN sender_type = 'faculty' THEN receiver_type ELSE sender_type END as other_type,
        CASE WHEN sender_type = 'faculty' THEN receiver_id ELSE sender_id END as other_id
    FROM messages
    WHERE (sender_type = 'faculty' AND sender_id = ?) OR (receiver_type = 'faculty' AND receiver_id = ?)
    ");

    $stmt->bind_param("ii", $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $other_type = $row['other_type'];
        $other_id = $row['other_id'];
        $name = getName($conn, $other_type, $other_id);
        if ($name) {
            // Get last message
            $stmt2 = $conn->prepare("SELECT message, sent_at FROM messages WHERE ((sender_type = 'faculty' AND sender_id = ?) AND (receiver_type = ? AND receiver_id = ?)) OR ((receiver_type = 'faculty' AND receiver_id = ?) AND (sender_type = ? AND sender_id = ?)) ORDER BY sent_at DESC LIMIT 1");
            $stmt2->bind_param("isisii", $userId, $other_type, $other_id, $userId, $other_type, $other_id);
            $stmt2->execute();
            $result2 = $stmt2->get_result();
            $last_message = '';
            $last_time = '';
            if ($row2 = $result2->fetch_assoc()) {
                $last_message = $row2['message'];
                $last_time = $row2['sent_at'];
            }
            $stmt2->close();

            // Get unread count
            $stmt3 = $conn->prepare("SELECT COUNT(*) as unread FROM messages WHERE receiver_type = 'faculty' AND receiver_id = ? AND sender_type = ? AND sender_id = ? AND is_read = 0");
            $stmt3->bind_param("isi", $userId, $other_type, $other_id);
            $stmt3->execute();
            $result3 = $stmt3->get_result();
            $unread_count = 0;
            if ($row3 = $result3->fetch_assoc()) {
                $unread_count = $row3['unread'];
            }
            $stmt3->close();

            $conversations[] = [
                'other_type' => $other_type,
                'other_id' => $other_id,
                'name' => $name,
                'last_message' => $last_message,
                'last_time' => $last_time,
                'unread_count' => $unread_count
            ];
        }
    }
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'conversations' => $conversations]);

} elseif ($action === 'messages') {
    $other_type = $_GET['other_type'] ?? '';
    $other_id = intval($_GET['other_id'] ?? 0);

    if (!$other_type || !$other_id) {
        echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
        exit();
    }

    $stmt = $conn->prepare("
        SELECT id, sender_type, sender_id, receiver_type, receiver_id, message, sent_at, is_read
        FROM messages
        WHERE ((sender_type = 'faculty' AND sender_id = ?) AND (receiver_type = ? AND receiver_id = ?)) OR
              ((receiver_type = 'faculty' AND receiver_id = ?) AND (sender_type = ? AND sender_id = ?))
        ORDER BY sent_at ASC
    ");
    $stmt->bind_param("isisii", $userId, $other_type, $other_id, $userId, $other_type, $other_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();

    // Mark messages as read
    $stmt = $conn->prepare("
        UPDATE messages SET is_read = 1
        WHERE receiver_type = 'faculty' AND receiver_id = ? AND sender_type = ? AND sender_id = ? AND is_read = 0
    ");
    $stmt->bind_param("isi", $userId, $other_type, $other_id);
    $stmt->execute();
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'messages' => $messages]);

} elseif ($action === 'search_users') {
    $query = trim($_GET['query'] ?? '');
    if (empty($query)) {
        echo json_encode(['success' => true, 'users' => []]);
        exit();
    }

    $users = [];

    // Search students
    $stmt = $conn->prepare("SELECT student_id as id, firstname, lastname FROM student WHERE (firstname LIKE ? OR lastname LIKE ? OR CONCAT(firstname, ' ', lastname) LIKE ?)");
    $like = '%' . $query . '%';
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'type' => 'student',
            'id' => $row['id'],
            'name' => trim($row['firstname'] . ' ' . $row['lastname'])
        ];
    }
    $stmt->close();

    // Search company HR
    $stmt = $conn->prepare("SELECT hr_id as id, companyname FROM companyhr WHERE companyname LIKE ?");
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'type' => 'companyhr',
            'id' => $row['id'],
            'name' => $row['companyname']
        ];
    }
    $stmt->close();

    // Search supervisors
    $stmt = $conn->prepare("SELECT supervisor_id as id, firstname, lastname FROM supervisor WHERE (firstname LIKE ? OR lastname LIKE ? OR CONCAT(firstname, ' ', lastname) LIKE ?)");
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'type' => 'supervisor',
            'id' => $row['id'],
            'name' => trim($row['firstname'] . ' ' . $row['lastname'])
        ];
    }
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'users' => $users]);

} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getName($conn, $type, $id) {
    if ($type === 'companyhr') {
        $stmt = $conn->prepare("SELECT companyname FROM companyhr WHERE hr_id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($name);
        if ($stmt->fetch()) {
            $stmt->close();
            return $name;
        }
    } else {
        $id_column = $type . '_id';
        $stmt = $conn->prepare("SELECT firstname, lastname FROM $type WHERE $id_column = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->bind_result($firstname, $lastname);
        if ($stmt->fetch()) {
            $stmt->close();
            return trim($firstname . ' ' . $lastname);
        }
    }
    $stmt->close();
    return null;
}
?>

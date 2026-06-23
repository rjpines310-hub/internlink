<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    http_response_code(403);
    exit();
}

include 'db.php';
$userId = $_SESSION['user_id'];

$action = $_GET['action'] ?? '';

if ($action === 'conversations') {
    // Simplified conversations query
    $stmt = $conn->prepare("
        SELECT 
            other_type,
            other_id,
            other_name,
            last_message,
            last_message_time,
            unread_count
        FROM (
            SELECT 
                CASE 
                    WHEN sender_type = 'student' AND sender_id = ? THEN receiver_type 
                    ELSE sender_type 
                END as other_type,
                CASE 
                    WHEN sender_type = 'student' AND sender_id = ? THEN receiver_id 
                    ELSE sender_id 
                END as other_id,
                MAX(sent_at) as last_message_time,
                (SELECT message FROM messages m2 
                 WHERE ((m2.sender_type = 'student' AND m2.sender_id = ? AND m2.receiver_type = CASE WHEN sender_type = 'student' AND sender_id = ? THEN receiver_type ELSE sender_type END AND m2.receiver_id = CASE WHEN sender_type = 'student' AND sender_id = ? THEN receiver_id ELSE sender_id END) OR
                        (m2.receiver_type = 'student' AND m2.receiver_id = ? AND m2.sender_type = CASE WHEN sender_type = 'student' AND sender_id = ? THEN receiver_type ELSE sender_type END AND m2.sender_id = CASE WHEN sender_type = 'student' AND sender_id = ? THEN receiver_id ELSE sender_id END))
                 ORDER BY m2.sent_at DESC LIMIT 1) as last_message,
                (SELECT COUNT(*) FROM messages m3 
                 WHERE m3.receiver_type = 'student' AND m3.receiver_id = ? AND m3.is_read = 0 AND
                       m3.sender_type = CASE WHEN sender_type = 'student' AND sender_id = ? THEN receiver_type ELSE sender_type END AND 
                       m3.sender_id = CASE WHEN sender_type = 'student' AND sender_id = ? THEN receiver_id ELSE sender_id END) as unread_count
            FROM messages
            WHERE (sender_type = 'student' AND sender_id = ?) OR (receiver_type = 'student' AND receiver_id = ?)
            GROUP BY other_type, other_id
        ) conv
        ORDER BY last_message_time DESC
    ");
    
    $stmt->bind_param("iiiiiiiiiiiii", $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $conversations = [];
    while ($row = $result->fetch_assoc()) {
        $name = getName($conn, $row['other_type'], $row['other_id']);
        if ($name) {
            $conversations[] = [
                'other_type' => $row['other_type'],
                'other_id' => $row['other_id'],
                'name' => $name,
                'last_message' => $row['last_message'],
                'last_time' => $row['last_message_time'],
                'unread_count' => $row['unread_count']
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
        WHERE ((sender_type = 'student' AND sender_id = ?) AND (receiver_type = ? AND receiver_id = ?)) OR
              ((receiver_type = 'student' AND receiver_id = ?) AND (sender_type = ? AND sender_id = ?))
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
        WHERE receiver_type = 'student' AND receiver_id = ? AND sender_type = ? AND sender_id = ? AND is_read = 0
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
    
    // Search faculty
    $stmt = $conn->prepare("SELECT faculty_id as id, firstname, lastname FROM faculty WHERE (firstname LIKE ? OR lastname LIKE ? OR CONCAT(firstname, ' ', lastname) LIKE ?)");
    $like = '%' . $query . '%';
    $stmt->bind_param("sss", $like, $like, $like);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'type' => 'faculty',
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

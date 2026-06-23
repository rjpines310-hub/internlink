<?php
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    exit();
}

include 'db.php';
$userType = $_SESSION['role'];
$userId = $_SESSION['user_id'];

$action = $_GET['action'] ?? '';

if ($action === 'conversations') {
    // Fetch all messages for the user
    $stmt = $conn->prepare("
        SELECT id, sender_type, sender_id, receiver_type, receiver_id, message, sent_at, is_read
        FROM messages
        WHERE (sender_type = ? AND sender_id = ?) OR (receiver_type = ? AND receiver_id = ?)
        ORDER BY sent_at DESC
    ");
    $stmt->bind_param("sisi", $userType, $userId, $userType, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $conversations = [];
    $convMap = [];
    while ($row = $result->fetch_assoc()) {
        $other_type = ($row['sender_type'] == $userType && $row['sender_id'] == $userId) ? $row['receiver_type'] : $row['sender_type'];
        $other_id = ($row['sender_type'] == $userType && $row['sender_id'] == $userId) ? $row['receiver_id'] : $row['sender_id'];
        $key = $other_type . '-' . $other_id;

        if (!isset($convMap[$key])) {
            $convMap[$key] = [
                'other_type' => $other_type,
                'other_id' => $other_id,
                'last_message' => $row['message'],
                'last_time' => $row['sent_at'],
                'unread_count' => 0
            ];
        } else {
            // Update if this is newer
            if ($row['sent_at'] > $convMap[$key]['last_time']) {
                $convMap[$key]['last_message'] = $row['message'];
                $convMap[$key]['last_time'] = $row['sent_at'];
            }
        }

        // Count unread if received by user
        if ($row['receiver_type'] == $userType && $row['receiver_id'] == $userId && $row['is_read'] == 0) {
            $convMap[$key]['unread_count']++;
        }
    }
    $stmt->close();

    // Convert to array and sort by last_time desc
    $conversations = array_values($convMap);
    usort($conversations, function($a, $b) {
        return strtotime($b['last_time']) - strtotime($a['last_time']);
    });

    // Add names
    foreach ($conversations as &$conv) {
        $conv['name'] = getName($conn, $conv['other_type'], $conv['other_id']);
    }

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
        WHERE ((sender_type = ? AND sender_id = ?) AND (receiver_type = ? AND receiver_id = ?)) OR
              ((receiver_type = ? AND receiver_id = ?) AND (sender_type = ? AND sender_id = ?))
        ORDER BY sent_at ASC
    ");
    $stmt->bind_param("sisisisi", $userType, $userId, $other_type, $other_id, $userType, $userId, $other_type, $other_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = $row;
    }
    $stmt->close();

    // Mark messages as read if received
    $stmt = $conn->prepare("
        UPDATE messages SET is_read = 1
        WHERE receiver_type = ? AND receiver_id = ? AND sender_type = ? AND sender_id = ? AND is_read = 0
    ");
    $stmt->bind_param("sisi", $userType, $userId, $other_type, $other_id);
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

    // Search faculty
    $stmt = $conn->prepare("SELECT faculty_id as id, firstname, lastname FROM faculty WHERE (firstname LIKE ? OR lastname LIKE ? OR CONCAT(firstname, ' ', lastname) LIKE ?)");
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
    $table = $type;
    
    // Handle different table structures and ID column names
    if ($type === 'companyhr') {
        $id_column = 'hr_id';
        $stmt = $conn->prepare("SELECT companyname as firstname, '' as lastname FROM $table WHERE $id_column = ?");
    } else {
        $id_column = $type . '_id';
        $stmt = $conn->prepare("SELECT firstname, lastname FROM $table WHERE $id_column = ?");
    }
    
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($firstname, $lastname);
    if ($stmt->fetch()) {
        $stmt->close();
        if ($type === 'companyhr') {
            return $firstname; // Just return company name
        } else {
            return trim($firstname . ' ' . $lastname);
        }
    }
    $stmt->close();
    return null;
}
?>

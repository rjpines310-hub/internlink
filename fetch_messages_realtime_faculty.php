<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    exit();
}

include 'db.php';
$userType = $_SESSION['role'];
$userId = $_SESSION['user_id'];

// Set headers for SSE
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');

// Disable output buffering
if (ob_get_level()) {
    ob_end_clean();
}

// Function to send SSE event
function sendEvent($event, $data) {
    echo "event: $event\n";
    echo "data: " . json_encode($data) . "\n\n";
    flush();
}

// Get last message timestamp for this user
$lastMessageTime = isset($_GET['last_time']) ? $_GET['last_time'] : date('Y-m-d H:i:s', time() - 3600); // Default to 1 hour ago

// Main loop for SSE
while (true) {
    // Check for new messages
    $stmt = $conn->prepare("
        SELECT id, sender_type, sender_id, receiver_type, receiver_id, message, sent_at, is_read
        FROM messages
        WHERE sent_at > ? AND ((sender_type = ? AND sender_id = ?) OR (receiver_type = ? AND receiver_id = ?))
        ORDER BY sent_at ASC
    ");
    $stmt->bind_param("sisis", $lastMessageTime, $userType, $userId, $userType, $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $newMessages = [];
    while ($row = $result->fetch_assoc()) {
        $newMessages[] = $row;
        $lastMessageTime = $row['sent_at'];
    }
    $stmt->close();

    if (!empty($newMessages)) {
        // Send new messages event
        sendEvent('new_messages', ['messages' => $newMessages]);

        // Also send updated conversations
        $convStmt = $conn->prepare("
            SELECT
                CASE
                    WHEN sender_type = ? AND sender_id = ? THEN receiver_type
                    ELSE sender_type
                END as other_type,
                CASE
                    WHEN sender_type = ? AND sender_id = ? THEN receiver_id
                    ELSE sender_id
                END as other_id,
                MAX(sent_at) as last_message_time,
                (SELECT message FROM messages m2
                 WHERE ((m2.sender_type = ? AND m2.sender_id = ? AND m2.receiver_type = CASE WHEN sender_type = ? AND sender_id = ? THEN receiver_type ELSE sender_type END AND m2.receiver_id = CASE WHEN sender_type = ? AND sender_id = ? THEN receiver_id ELSE sender_id END) OR
                        (m2.receiver_type = ? AND m2.receiver_id = ? AND m2.sender_type = CASE WHEN sender_type = ? AND sender_id = ? THEN receiver_type ELSE sender_type END AND m2.sender_id = CASE WHEN sender_type = ? AND sender_id = ? THEN receiver_id ELSE sender_id END))
                 ORDER BY m2.sent_at DESC LIMIT 1) as last_message,
                (SELECT COUNT(*) FROM messages m3
                 WHERE m3.receiver_type = ? AND m3.receiver_id = ? AND m3.is_read = 0 AND
                       m3.sender_type = CASE WHEN sender_type = ? AND sender_id = ? THEN receiver_type ELSE sender_type END AND
                       m3.sender_id = CASE WHEN sender_type = ? AND sender_id = ? THEN receiver_id ELSE sender_id END) as unread_count
            FROM messages
            WHERE (sender_type = ? AND sender_id = ?) OR (receiver_type = ? AND receiver_id = ?)
            GROUP BY other_type, other_id
            ORDER BY last_message_time DESC
        ");
        $convStmt->bind_param("iiiiiiiiiiiiiiiiiiiiii", $userType, $userId, $userType, $userId, $userType, $userId, $userType, $userId, $userType, $userId, $userType, $userId, $userType, $userId, $userType, $userId, $userType, $userId, $userType, $userId, $userType, $userId);
        $convStmt->execute();
        $convResult = $convStmt->get_result();

        $conversations = [];
        while ($row = $convResult->fetch_assoc()) {
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
        $convStmt->close();

        sendEvent('conversations_update', ['conversations' => $conversations]);
    }

    // Sleep for 1 second before checking again
    sleep(1);
}

// Helper function to get name (copied from fetch_messages_improved.php)
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

<?php
include 'db.php';

$result = $conn->query("SHOW TABLES LIKE 'messages'");
if ($result->num_rows > 0) {
    echo "Messages table exists.\n";
    $count = $conn->query("SELECT COUNT(*) as count FROM messages")->fetch_assoc()['count'];
    echo "Messages count: $count\n";
} else {
    echo "Messages table does not exist.\n";
}

$conn->close();
?>

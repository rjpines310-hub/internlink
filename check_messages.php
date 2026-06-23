<?php
include 'db.php';

$result = $conn->query("SELECT COUNT(*) as count FROM messages");
$row = $result->fetch_assoc();
echo "Total messages: " . $row['count'] . "\n";

$result = $conn->query("SELECT * FROM messages LIMIT 5");
while ($row = $result->fetch_assoc()) {
    print_r($row);
}
?>

<?php
include 'db.php';

$sql = file_get_contents('alter_invitations_add_sent_at.sql');
if ($conn->query($sql) === TRUE) {
    echo 'Column added successfully.';
} else {
    echo 'Error: ' . $conn->error;
}
?>

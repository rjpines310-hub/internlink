<?php
include 'db.php';

$username = 'internlink';
$new_password = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = $conn->prepare("UPDATE admin_login SET password = ? WHERE username = ?");
$stmt->bind_param("ss", $new_password, $username);

if ($stmt->execute()) {
    echo "Admin password updated successfully.\n";
} else {
    echo "Error updating admin password: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();
?>

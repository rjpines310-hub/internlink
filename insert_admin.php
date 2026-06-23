<?php
include 'db.php';

// Data to insert
$username = 'internlink';
$first_name = 'Renato';
$last_name = 'Pines';
$password = password_hash('admin123', PASSWORD_DEFAULT);

// Prepare and bind
$stmt = $conn->prepare("INSERT INTO admin_login (username, first_name, last_name, password, created_at) VALUES (?, ?, ?, ?, NOW())");
$stmt->bind_param("ssss", $username, $first_name, $last_name, $password);

if ($stmt->execute()) {
    echo "Admin data inserted successfully.\n";
} else {
    echo "Error inserting admin data: " . $stmt->error . "\n";
}

$stmt->close();
$conn->close();
?>

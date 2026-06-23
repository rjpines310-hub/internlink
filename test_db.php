<?php
include 'db.php';

echo "<h2>Database Connection Test</h2>";

// Test connection
if ($conn->connect_error) {
    echo "Connection failed: " . $conn->connect_error;
    exit();
} else {
    echo "✓ Database connection successful<br><br>";
}

// Check if messages table exists
$result = $conn->query("SHOW TABLES LIKE 'messages'");
if ($result->num_rows > 0) {
    echo "✓ Messages table exists<br><br>";
    
    // Show table structure
    echo "<h3>Messages Table Structure:</h3>";
    $result = $conn->query("DESCRIBE messages");
    if ($result) {
        echo "<table border='1'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['Field'] . "</td>";
            echo "<td>" . $row['Type'] . "</td>";
            echo "<td>" . $row['Null'] . "</td>";
            echo "<td>" . $row['Key'] . "</td>";
            echo "<td>" . $row['Default'] . "</td>";
            echo "<td>" . $row['Extra'] . "</td>";
            echo "</tr>";
        }
        echo "</table><br>";
    }
    
    // Count messages
    $result = $conn->query("SELECT COUNT(*) as count FROM messages");
    $row = $result->fetch_assoc();
    echo "Total messages in database: " . $row['count'] . "<br><br>";
    
} else {
    echo "❌ Messages table does NOT exist<br>";
    echo "You need to create the messages table first.<br><br>";
    
    echo "<h3>Creating Messages Table...</h3>";
    $createTable = "
    CREATE TABLE `messages` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `sender_type` enum('student','faculty','companyhr','supervisor') NOT NULL,
      `sender_id` int(11) NOT NULL,
      `receiver_type` enum('student','faculty','companyhr','supervisor') NOT NULL,
      `receiver_id` int(11) NOT NULL,
      `message` text NOT NULL,
      `sent_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `is_read` tinyint(1) NOT NULL DEFAULT 0,
      PRIMARY KEY (`id`),
      KEY `idx_sender` (`sender_type`, `sender_id`),
      KEY `idx_receiver` (`receiver_type`, `receiver_id`),
      KEY `idx_sent_at` (`sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    ";
    
    if ($conn->query($createTable)) {
        echo "✓ Messages table created successfully!<br>";
    } else {
        echo "❌ Error creating messages table: " . $conn->error . "<br>";
    }
}

// Test other required tables
$requiredTables = ['student', 'faculty', 'companyhr', 'supervisor'];
echo "<h3>Checking Required Tables:</h3>";
foreach ($requiredTables as $table) {
    $result = $conn->query("SHOW TABLES LIKE '$table'");
    if ($result->num_rows > 0) {
        echo "✓ $table table exists<br>";
    } else {
        echo "❌ $table table does NOT exist<br>";
    }
}

// Test fetch_messages.php endpoint
echo "<br><h3>Testing fetch_messages.php endpoint:</h3>";
echo "<a href='fetch_messages.php?action=conversations' target='_blank'>Test Conversations Endpoint</a><br>";
echo "<a href='fetch_messages.php?action=search_users&query=test' target='_blank'>Test Search Users Endpoint</a><br>";

$conn->close();
?>

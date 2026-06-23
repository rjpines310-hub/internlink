<?php
include 'db.php';

// Add some sample messages for testing
echo "<h2>Adding Sample Messages for Testing</h2>";

// First, let's check if we have users in the database
$tables = ['student', 'faculty', 'companyhr', 'supervisor'];
$users = [];

foreach ($tables as $table) {
    $id_col = ($table === 'companyhr') ? 'hr_id' : $table . '_id';
    $name_cols = ($table === 'companyhr') ? 'companyname as name' : "CONCAT(firstname, ' ', lastname) as name";
    
    $result = $conn->query("SELECT $id_col as id, $name_cols FROM $table LIMIT 3");
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $users[$table][] = [
                'id' => $row['id'],
                'name' => $row['name']
            ];
        }
    }
}

echo "<h3>Available Users:</h3>";
foreach ($users as $type => $userList) {
    echo "<strong>$type:</strong><br>";
    foreach ($userList as $user) {
        echo "- ID: {$user['id']}, Name: {$user['name']}<br>";
    }
    echo "<br>";
}

// Add sample messages if we have users
if (!empty($users['student']) && !empty($users['faculty'])) {
    $student = $users['student'][0];
    $faculty = $users['faculty'][0];
    
    echo "<h3>Adding Sample Messages...</h3>";
    
    // Message from faculty to student
    $stmt = $conn->prepare("INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, message) VALUES (?, ?, ?, ?, ?)");
    
    $messages = [
        ['faculty', $faculty['id'], 'student', $student['id'], 'Hello! I hope your internship is going well. Do you have any questions about your requirements?'],
        ['student', $student['id'], 'faculty', $faculty['id'], 'Hi! Thank you for checking in. I do have a question about the final report format.'],
        ['faculty', $faculty['id'], 'student', $student['id'], 'Of course! The final report should be in APA format, minimum 10 pages. I can send you a template if needed.'],
        ['student', $student['id'], 'faculty', $faculty['id'], 'That would be very helpful! Thank you so much.'],
    ];
    
    foreach ($messages as $msg) {
        $stmt->bind_param("sisis", $msg[0], $msg[1], $msg[2], $msg[3], $msg[4]);
        if ($stmt->execute()) {
            echo "✓ Added message: " . substr($msg[4], 0, 50) . "...<br>";
        } else {
            echo "❌ Failed to add message: " . $conn->error . "<br>";
        }
    }
    $stmt->close();
}

// Add messages with company HR if available
if (!empty($users['student']) && !empty($users['companyhr'])) {
    $student = $users['student'][0];
    $company = $users['companyhr'][0];
    
    $stmt = $conn->prepare("INSERT INTO messages (sender_type, sender_id, receiver_type, receiver_id, message) VALUES (?, ?, ?, ?, ?)");
    
    $messages = [
        ['companyhr', $company['id'], 'student', $student['id'], 'Welcome to our company! We are excited to have you as an intern.'],
        ['student', $student['id'], 'companyhr', $company['id'], 'Thank you! I am very excited to start working with your team.'],
        ['companyhr', $company['id'], 'student', $student['id'], 'Your supervisor will contact you tomorrow with your first assignment. Good luck!'],
    ];
    
    foreach ($messages as $msg) {
        $stmt->bind_param("sisis", $msg[0], $msg[1], $msg[2], $msg[3], $msg[4]);
        if ($stmt->execute()) {
            echo "✓ Added message: " . substr($msg[4], 0, 50) . "...<br>";
        } else {
            echo "❌ Failed to add message: " . $conn->error . "<br>";
        }
    }
    $stmt->close();
}

// Check total messages
$result = $conn->query("SELECT COUNT(*) as count FROM messages");
$row = $result->fetch_assoc();
echo "<br><strong>Total messages in database: " . $row['count'] . "</strong><br>";

echo "<br><h3>Test the messaging system:</h3>";
echo "<a href='student.php' target='_blank'>Go to Student Dashboard</a><br>";
echo "<a href='fetch_messages.php?action=conversations' target='_blank'>Test Conversations API</a><br>";

$conn->close();
?>

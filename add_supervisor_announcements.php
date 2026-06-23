<?php
include 'db.php';

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Assume a faculty_id exists (e.g., 1). In a real scenario, get from session or select one.
$faculty_id = 1; // Replace with actual faculty_id if needed

// Sample announcements for supervisors
$announcements = [
    [
        'title' => 'Supervisor Training Session',
        'content' => 'A mandatory training session for all supervisors on best practices for intern management will be held next week.',
        'date_posted' => date('Y-m-d H:i:s'),
    ],
    [
        'title' => 'New Intern Evaluation Guidelines',
        'content' => 'Please review the updated guidelines for evaluating intern performance and attendance.',
        'date_posted' => date('Y-m-d H:i:s'),
    ],
    [
        'title' => 'Holiday Schedule Reminder',
        'content' => 'Remember to update intern schedules for the upcoming holidays and ensure compliance with company policies.',
        'date_posted' => date('Y-m-d H:i:s'),
    ],
];

foreach ($announcements as $ann) {
    // Insert into announcements table
    $stmt = $conn->prepare("INSERT INTO announcements (faculty_id, title, content, date_posted) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("isss", $faculty_id, $ann['title'], $ann['content'], $ann['date_posted']);
    if ($stmt->execute()) {
        $announcement_id = $stmt->insert_id;

        // Insert into announcement_audiences for supervisors
        $audience_stmt = $conn->prepare("INSERT INTO announcement_audiences (announcement_id, audience_role) VALUES (?, 'supervisor')");
        $audience_stmt->bind_param("i", $announcement_id);
        $audience_stmt->execute();
        $audience_stmt->close();

        echo "Announcement '{$ann['title']}' added for supervisors.<br>";
    } else {
        echo "Error adding announcement '{$ann['title']}': " . $stmt->error . "<br>";
    }
    $stmt->close();
}

$conn->close();
echo "Sample announcements for supervisors have been added.";
?>

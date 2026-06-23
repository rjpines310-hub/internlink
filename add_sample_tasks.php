<?php
include 'db.php';

// First, let's check what supervisors and students exist
echo "Checking existing supervisors and students...\n";

// Get supervisors
$supervisor_query = "SELECT supervisor_id, firstname, lastname FROM supervisor LIMIT 5";
$supervisor_result = $conn->query($supervisor_query);

$supervisors = [];
if ($supervisor_result->num_rows > 0) {
    while ($row = $supervisor_result->fetch_assoc()) {
        $supervisors[] = $row;
        echo "Supervisor: {$row['supervisor_id']} - {$row['firstname']} {$row['lastname']}\n";
    }
} else {
    echo "No supervisors found. Please add supervisors first.\n";
    exit();
}

// Get students
$student_query = "SELECT student_id, firstname, lastname FROM student LIMIT 5";
$student_result = $conn->query($student_query);

$students = [];
if ($student_result->num_rows > 0) {
    while ($row = $student_result->fetch_assoc()) {
        $students[] = $row;
        echo "Student: {$row['student_id']} - {$row['firstname']} {$row['lastname']}\n";
    }
} else {
    echo "No students found. Please add students first.\n";
    exit();
}

// Sample tasks data
$sample_tasks = [
    [
        'title' => 'Complete Weekly Report',
        'description' => 'Submit your weekly progress report including tasks completed, challenges faced, and goals for next week.',
        'due_date' => date('Y-m-d', strtotime('+7 days')),
        'status' => 'pending'
    ],
    [
        'title' => 'Attend Team Meeting',
        'description' => 'Participate in the weekly team meeting to discuss project updates and coordinate with team members.',
        'due_date' => date('Y-m-d', strtotime('+3 days')),
        'status' => 'pending'
    ],
    [
        'title' => 'Update Documentation',
        'description' => 'Review and update the project documentation to reflect recent changes and improvements.',
        'due_date' => date('Y-m-d', strtotime('+10 days')),
        'status' => 'pending'
    ],
    [
        'title' => 'Code Review Preparation',
        'description' => 'Prepare your code for the upcoming code review session by ensuring proper comments and documentation.',
        'due_date' => date('Y-m-d', strtotime('+5 days')),
        'status' => 'pending'
    ],
    [
        'title' => 'Client Presentation',
        'description' => 'Prepare and deliver a presentation to the client about the current project status and upcoming features.',
        'due_date' => date('Y-m-d', strtotime('+14 days')),
        'status' => 'pending'
    ],
    [
        'title' => 'Database Optimization',
        'description' => 'Analyze and optimize database queries to improve application performance.',
        'due_date' => date('Y-m-d', strtotime('+12 days')),
        'status' => 'pending'
    ]
];

// Assign tasks to students
$task_count = 0;
foreach ($students as $student) {
    $supervisor = $supervisors[array_rand($supervisors)]; // Random supervisor

    // Assign 2-4 random tasks to each student
    $num_tasks = rand(2, 4);
    $selected_tasks = array_rand($sample_tasks, $num_tasks);
    if (!is_array($selected_tasks)) {
        $selected_tasks = [$selected_tasks];
    }

    foreach ($selected_tasks as $task_index) {
        $task = $sample_tasks[$task_index];

        $stmt = $conn->prepare("INSERT INTO tasks (supervisor_id, student_id, title, description, due_date, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iissss", $supervisor['supervisor_id'], $student['student_id'], $task['title'], $task['description'], $task['due_date'], $task['status']);

        if ($stmt->execute()) {
            echo "✓ Added task '{$task['title']}' for student {$student['firstname']} {$student['lastname']} (supervisor: {$supervisor['firstname']} {$supervisor['lastname']})\n";
            $task_count++;
        } else {
            echo "✗ Failed to add task '{$task['title']}' for student {$student['student_id']}: " . $stmt->error . "\n";
        }

        $stmt->close();
    }
}

// Add one completed task for demonstration
if (count($students) > 0 && count($supervisors) > 0) {
    $student = $students[0];
    $supervisor = $supervisors[0];

    $completed_task = [
        'title' => 'Setup Development Environment',
        'description' => 'Install and configure all necessary development tools and environment for the project.',
        'due_date' => date('Y-m-d', strtotime('-5 days')),
        'status' => 'completed'
    ];

    $stmt = $conn->prepare("INSERT INTO tasks (supervisor_id, student_id, title, description, due_date, status, completed_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iissss", $supervisor['supervisor_id'], $student['student_id'], $completed_task['title'], $completed_task['description'], $completed_task['due_date'], $completed_task['status']);

    if ($stmt->execute()) {
        echo "✓ Added completed task '{$completed_task['title']}' for student {$student['firstname']} {$student['lastname']}\n";
        $task_count++;
    } else {
        echo "✗ Failed to add completed task: " . $stmt->error . "\n";
    }

    $stmt->close();
}

echo "\nSample data insertion completed! Total tasks added: $task_count\n";
echo "You can now log in as a student to view the tasks in the Performance tab.\n";

$conn->close();
?>

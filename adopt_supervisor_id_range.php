<?php
include 'db.php';

// Disable foreign key checks temporarily
$conn->query("SET FOREIGN_KEY_CHECKS = 0");

// Step 1: Get current supervisors
$result = $conn->query("SELECT supervisor_id FROM supervisor ORDER BY supervisor_id");
$supervisors = [];
while ($row = $result->fetch_assoc()) {
    $supervisors[] = $row['supervisor_id'];
}

// Step 2: Calculate new IDs starting from 4000
$new_ids = [];
foreach ($supervisors as $index => $old_id) {
    $new_ids[$old_id] = 4000 + $index;
}

// Step 3: Update tasks table first (child table)
foreach ($new_ids as $old_id => $new_id) {
    $conn->query("UPDATE tasks SET supervisor_id = $new_id WHERE supervisor_id = $old_id");
    echo "Updated tasks supervisor_id from $old_id to $new_id<br>";
}

// Step 4: Update student table
foreach ($new_ids as $old_id => $new_id) {
    $conn->query("UPDATE student SET supervisor_id = $new_id WHERE supervisor_id = $old_id");
    echo "Updated student supervisor_id from $old_id to $new_id<br>";
}

// Step 5: Update user_mappings for supervisors
foreach ($new_ids as $old_id => $new_id) {
    $conn->query("UPDATE user_mappings SET role_id = $new_id WHERE role_type = 'supervisor' AND role_id = $old_id");
    echo "Updated user_mappings role_id from $old_id to $new_id for supervisor<br>";
}

// Step 6: Update supervisor table
foreach ($new_ids as $old_id => $new_id) {
    $conn->query("UPDATE supervisor SET supervisor_id = $new_id WHERE supervisor_id = $old_id");
    echo "Updated supervisor_id from $old_id to $new_id<br>";
}

// Re-enable foreign key checks
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "Supervisor ID range adoption completed successfully.<br>";
$conn->close();
?>

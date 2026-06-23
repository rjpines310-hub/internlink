<?php
include 'db.php';

// Get all supervisors grouped by hr_id
$supervisors_query = "SELECT hr_id, supervisor_id FROM supervisor ORDER BY hr_id, supervisor_id";
$supervisors_result = $conn->query($supervisors_query);

$supervisors_by_hr = [];
while ($row = $supervisors_result->fetch_assoc()) {
    $hr_id = $row['hr_id'];
    if (!isset($supervisors_by_hr[$hr_id])) {
        $supervisors_by_hr[$hr_id] = [];
    }
    $supervisors_by_hr[$hr_id][] = $row['supervisor_id'];
}

// Now, for each hr_id, assign the first supervisor to all students under that hr
foreach ($supervisors_by_hr as $hr_id => $supervisor_ids) {
    $supervisor_id = $supervisor_ids[0]; // Take the first one

    $update_query = "UPDATE student SET supervisor_id = ? WHERE hr_id = ?";
    $update_stmt = $conn->prepare($update_query);
    $update_stmt->bind_param("ii", $supervisor_id, $hr_id);
    $update_stmt->execute();
    $update_stmt->close();

    echo "Assigned supervisor_id $supervisor_id to students under hr_id $hr_id<br>";
}

echo "Supervisor IDs populated successfully.<br>";
$conn->close();
?>

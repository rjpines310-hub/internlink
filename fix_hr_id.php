<?php
include 'db.php';

echo "Fixing HR ID from 1 to 3000...\n";

// Disable foreign key checks temporarily
$conn->query('SET FOREIGN_KEY_CHECKS = 0');

// Update the HR ID
$result = $conn->query('UPDATE companyhr SET hr_id = 3000 WHERE hr_id = 1');

if ($result) {
    echo "Updated HR ID successfully\n";
} else {
    echo "Failed to update HR ID: " . $conn->error . "\n";
}

// Update any references in related tables
$conn->query('UPDATE internship_posts SET posted_by = 3000 WHERE posted_by = 1');
$conn->query('UPDATE supervisor SET hr_id = 3000 WHERE hr_id = 1');
$conn->query('UPDATE hr_requests SET hr_id = 3000 WHERE hr_id = 1');
$conn->query('UPDATE interviews SET hr_id = 3000 WHERE hr_id = 1');
$conn->query('UPDATE student SET hr_id = 3000 WHERE hr_id = 1');

// Re-enable foreign key checks
$conn->query('SET FOREIGN_KEY_CHECKS = 1');

echo "All references updated\n";

$conn->close();
?>

<?php
include 'db.php';

$result = $conn->query('SELECT hr_id, companyname FROM companyhr ORDER BY hr_id');
echo "Current HR IDs:\n";
while($row = $result->fetch_assoc()) {
    echo $row['hr_id'] . ' - ' . $row['companyname'] . "\n";
}

$conn->close();
?>

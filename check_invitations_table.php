<?php
include 'db.php';

$result = $conn->query('DESCRIBE invitations');
if ($result) {
    while ($row = $result->fetch_assoc()) {
        echo $row['Field'] . ' - ' . $row['Type'] . ' - ' . ($row['Null'] == 'YES' ? 'NULL' : 'NOT NULL') . ' - ' . ($row['Default'] ?? 'NULL') . PHP_EOL;
    }
} else {
    echo "Error: " . $conn->error;
}
?>

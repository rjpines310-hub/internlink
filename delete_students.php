<?php
include 'db.php';

$conn->query('SET FOREIGN_KEY_CHECKS = 0');
$conn->query('TRUNCATE TABLE student');
$conn->query('UPDATE id_range_config SET current_max = 999 WHERE table_name = "student"');
$conn->query('SET FOREIGN_KEY_CHECKS = 1');

echo 'All students deleted and ID range reset.';
?>

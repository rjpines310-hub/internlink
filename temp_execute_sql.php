<?php
include 'db.php';
$sql = file_get_contents('alter_companyhr_add_manual_column.sql');
if ($conn->multi_query($sql)) {
    echo 'Table altered successfully.';
} else {
    echo 'Error altering table: ' . $conn->error;
}
$conn->close();
?>

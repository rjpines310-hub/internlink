<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Connection errors will be handled by the individual scripts
// using try...catch blocks to ensure JSON-friendly responses for AJAX calls.
?>

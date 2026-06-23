<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(["success"=>false,"message"=>"Unauthorized"]);
    exit;
}

$student_id = $_SESSION['user_id'];
$action = $_POST['action'];
$date = date("Y-m-d");
$time = date("H:i:s");

// Check if record exists
$result = $conn->query("SELECT * FROM timecard WHERE student_id=$student_id AND date='$date'");

if($action === "timein") {
    if($result->num_rows == 0){
        $conn->query("INSERT INTO timecard (student_id, date, time_in, status, created_at) VALUES ($student_id,'$date','$time','Present',NOW())");
        echo json_encode(["success"=>true,"message"=>"Time In recorded at $time"]);
    } else {
        echo json_encode(["success"=>false,"message"=>"You already timed in today."]);
    }
} elseif($action === "timeout") {
    if($result->num_rows > 0){
        $row = $result->fetch_assoc();
        if(empty($row['time_out'])){
            $conn->query("UPDATE timecard SET time_out='$time', updated_at=NOW() WHERE timecard_id={$row['timecard_id']}");
            echo json_encode(["success"=>true,"message"=>"Time Out recorded at $time"]);
        } else {
            echo json_encode(["success"=>false,"message"=>"You already timed out today."]);
        }
    } else {
        echo json_encode(["success"=>false,"message"=>"No Time In record found for today."]);
    }
} else {
    echo json_encode(["success"=>false,"message"=>"Invalid action."]);
}

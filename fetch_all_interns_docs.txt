<?php
session_start();
include 'db.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => 'User not logged in']);
    exit();
}

$hrId = $_SESSION['user_id'];

try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    $conn = new mysqli($servername, $username, $password, $dbname);
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    $sql = "SELECT s.student_id as id, s.firstname, s.lastname, s.email, s.profile_picture, ip.internship_title as post, CONCAT(sup.firstname, ' ', sup.lastname) AS supervisor_name
            FROM student s
            LEFT JOIN supervisor sup ON s.supervisor_id = sup.supervisor_id
            LEFT JOIN internship_posts ip ON s.post_id = ip.post_id
            WHERE s.hr_id = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $hrId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $interns = [];
    while ($row = $result->fetch_assoc()) {
        $interns[] = $row;
    }
    
    echo json_encode($interns);
    
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>

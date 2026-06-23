<?php
include 'db.php';

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "<h2>Verifying Student Overview</h2>";

// First, find a student with employment_status = 'pending'
$sql_find_pending_student = "SELECT student_id, employment_status FROM student WHERE employment_status = 'pending' LIMIT 1";
$result_find_pending = $conn->query($sql_find_pending_student);

if ($result_find_pending->num_rows > 0) {
    $row_pending = $result_find_pending->fetch_assoc();
    $student_id_to_check = $row_pending['student_id'];
    $employment_status_to_check = $row_pending['employment_status'];

    echo "Found student with ID: " . $student_id_to_check . " and employment_status: " . $employment_status_to_check . "<br>";

    // Explicitly call the stored procedure to ensure student_overview is updated
    $sql_call_sp = "CALL CalculateStudentOverview(" . $student_id_to_check . ")";
    if ($conn->query($sql_call_sp) === TRUE) {
        echo "Successfully called CalculateStudentOverview for student ID: " . $student_id_to_check . "<br>";
    } else {
        echo "Error calling CalculateStudentOverview: " . $conn->error . "<br>";
    }

    // Now, fetch their performance from student_overview
    $sql_check_overview = "SELECT performance FROM student_overview WHERE student_id = " . $student_id_to_check;
    $result_check_overview = $conn->query($sql_check_overview);

    if ($result_check_overview->num_rows > 0) {
        $row_overview = $result_check_overview->fetch_assoc();
        echo "Performance for student " . $student_id_to_check . ": " . $row_overview['performance'] . "<br>";

        if ($row_overview['performance'] == 0) {
            echo "Verification successful: Performance is 0 for pending student.<br>";
        } else {
            echo "Verification failed: Performance is NOT 0 for pending student. Expected 0, got " . $row_overview['performance'] . "<br>";
        }
    } else {
        echo "No student_overview entry found for student ID: " . $student_id_to_check . "<br>";
    }
} else {
    echo "No student with employment_status 'pending' found in the 'student' table. Please ensure there is at least one such student for verification.<br>";
    echo "Attempting to find any student and display their overview for general check:<br>";

    // Fallback: if no pending student, just show any student's overview
    $sql_any_student = "SELECT s.student_id, s.employment_status, so.performance
                        FROM student s
                        LEFT JOIN student_overview so ON s.student_id = so.student_id
                        LIMIT 1";
    $result_any_student = $conn->query($sql_any_student);
    if ($result_any_student->num_rows > 0) {
        $row_any = $result_any_student->fetch_assoc();
        echo "Any Student ID: " . $row_any['student_id'] . ", Employment Status: " . $row_any['employment_status'] . ", Performance: " . $row_any['performance'] . "<br>";
    } else {
        echo "No students found in the 'student' table.<br>";
    }
}

$conn->close();
?>

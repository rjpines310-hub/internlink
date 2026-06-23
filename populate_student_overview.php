<?php
include 'db.php';

// Function to calculate attendance for a student
function calculateAttendance($student_id, $conn) {
    $totalHours = 0;
    $stmt = $conn->prepare("SELECT time_in, time_out FROM timecard WHERE student_id = ? AND status = 'Validated'");
    $stmt->bind_param("i", $student_id);
    $stmt->execute();
    $stmt->bind_result($timeIn, $timeOut);

    while ($stmt->fetch()) {
        if (!empty($timeIn) && !empty($timeOut)) {
            $in = new DateTime($timeIn);
            $out = new DateTime($timeOut);
            $diff = $in->diff($out);
            $hours = $diff->h + ($diff->days * 24) + ($diff->i / 60);
            $totalHours += $hours;
        }
    }
    $stmt->close();

    // Fetch target hours from sections table based on student's section
    $targetHours = 200; // Default fallback
    $section_name = '';
    $stmt_section = $conn->prepare("SELECT section FROM student WHERE student_id = ?");
    if ($stmt_section) {
        $stmt_section->bind_param("i", $student_id);
        $stmt_section->execute();
        $stmt_section->bind_result($section_name);
        $stmt_section->fetch();
        $stmt_section->close();
    }

    if (!empty($section_name)) {
        $stmt_ojt = $conn->prepare("SELECT ojt_hours FROM sections WHERE section_name = ?");
        if ($stmt_ojt) {
            $stmt_ojt->bind_param("s", $section_name);
            $stmt_ojt->execute();
            $stmt_ojt->bind_result($ojtHours);
            if ($stmt_ojt->fetch() && $ojtHours > 0) {
                $targetHours = $ojtHours;
            }
            $stmt_ojt->close();
        }
    }

    $progressPercent = ($targetHours > 0) ? min(100, ($totalHours / $targetHours) * 100) : 0;
    return round($progressPercent, 2); // Return as decimal percentage
}

// Function to calculate performance for a student
function calculatePerformance($student_id, $conn) {
    $performanceScore = 0;
    $stmt = $conn->prepare("
        SELECT
            SUM(CASE WHEN status = 'completed' THEN COALESCE(score, 0) WHEN status = 'missed' THEN 50 ELSE 0 END) as total_score,
            COUNT(CASE WHEN status IN ('completed', 'missed') THEN 1 END) as scored_task_count
        FROM tasks
        WHERE student_id = ?
    ");
    if ($stmt) {
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $totalScore = $row['total_score'] ?? 0;
            $scoredTaskCount = $row['scored_task_count'] ?? 0;

            if ($scoredTaskCount > 0) {
                $maxPossibleScore = $scoredTaskCount * 100;
                $performanceScore = ($totalScore / $maxPossibleScore) * 100;
            } else {
                $performanceScore = 100;
            }
        }
        $stmt->close();
    }
    return round($performanceScore, 2); // Return as decimal percentage
}

// Function to calculate file submissions for a student
function calculateFileSubmissions($student_id, $conn) {
    $totalFiles = 4; // DTR, MOA, LOA, Evaluation
    $approvedFiles = 0;
    $stmt = $conn->prepare("SELECT dtr_file_checked, moa_file_checked, letter_of_acceptance_file_checked, evaluation_form_file_checked FROM student_file_submissions WHERE student_id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $student_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            if($row['dtr_file_checked']) $approvedFiles++;
            if($row['moa_file_checked']) $approvedFiles++;
            if($row['letter_of_acceptance_file_checked']) $approvedFiles++;
            if($row['evaluation_form_file_checked']) $approvedFiles++;
        }
        $stmt->close();
    }

    $fileProgressPercent = ($totalFiles > 0) ? (($approvedFiles / $totalFiles) * 100) : 0;
    return round($fileProgressPercent, 2); // Return as decimal percentage
}

// Get all students and their employment status, hr_id, and post_id
$students_result = $conn->query("SELECT s.student_id, s.employment_status, s.hr_id, s.post_id FROM student s");

if ($students_result->num_rows > 0) {
    while ($student = $students_result->fetch_assoc()) {
        $student_id = $student['student_id'];
        $employment_status = $conn->real_escape_string($student['employment_status']);
        $hr_id = $student['hr_id'] ?? 'NULL';
        $post_id = $student['post_id'] ?? 'NULL';

        // Calculate current values
        $attendance_score = (float) calculateAttendance($student_id, $conn);
        $performance_score = (float) calculatePerformance($student_id, $conn);
        $file_submissions_score = (float) calculateFileSubmissions($student_id, $conn);

        $overall_average = ($attendance_score + $performance_score + $file_submissions_score) / 3;

        // Check if student already exists in student_overview
        $check_result = $conn->query("SELECT student_id FROM student_overview WHERE student_id = $student_id");

        if ($check_result->num_rows == 0) {
            // Insert student into student_overview with calculated values
            $insert_sql = "INSERT INTO student_overview (student_id, employment_status, attendance, performance, file_submissions, overall_average, hr_id, post_id) VALUES ($student_id, '$employment_status', $attendance_score, $performance_score, $file_submissions_score, $overall_average, " . ($hr_id === 'NULL' ? 'NULL' : $hr_id) . ", " . ($post_id === 'NULL' ? 'NULL' : $post_id) . ")";
            if (!$conn->query($insert_sql)) {
                echo "Error inserting student ID $student_id: " . $conn->error . "\n";
            } else {
                echo "Successfully inserted student ID $student_id into student_overview with calculated values.\n";
            }
        } else {
            // Update existing record with calculated values
            $update_sql = "UPDATE student_overview SET employment_status = '$employment_status', attendance = $attendance_score, performance = $performance_score, file_submissions = $file_submissions_score, overall_average = $overall_average, hr_id = " . ($hr_id === 'NULL' ? 'NULL' : $hr_id) . ", post_id = " . ($post_id === 'NULL' ? 'NULL' : $post_id) . " WHERE student_id = $student_id";
            if (!$conn->query($update_sql)) {
                echo "Error updating student ID $student_id: " . $conn->error . "\n";
            } else {
                echo "Successfully updated student ID $student_id in student_overview with calculated values.\n";
            }
        }
    }
    echo "Student overview population/update complete.\n";
} else {
    echo "No students found to populate.\n";
}

$conn->close();
?>

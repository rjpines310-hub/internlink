<?php
include 'db.php';

// Get all students
$result = $conn->query("SELECT student_id FROM student");

if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $student_id = $row['student_id'];

        // Calculate attendance
        $totalHours = 0;
        $stmt = $conn->prepare("SELECT time_in, time_out FROM timecard WHERE student_id = ?");
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

        // Fetch student's section
        $student_section = '';
        $stmt_section = $conn->prepare("SELECT section FROM student WHERE student_id = ?");
        $stmt_section->bind_param("i", $student_id);
        $stmt_section->execute();
        $stmt_section->bind_result($student_section);
        $stmt_section->fetch();
        $stmt_section->close();

        // Fetch target hours from sections table based on student's section
        $targetHours = 200; // Default fallback
        if (!empty($student_section)) {
            $stmt_ojt = $conn->prepare("SELECT ojt_hours FROM sections WHERE section_name = ?");
            if ($stmt_ojt) {
                $stmt_ojt->bind_param("s", $student_section);
                $stmt_ojt->execute();
                $stmt_ojt->bind_result($ojtHours);
                if ($stmt_ojt->fetch() && $ojtHours > 0) {
                    $targetHours = $ojtHours;
                }
                $stmt_ojt->close();
            }
        }
        
        $progressPercent = ($targetHours > 0) ? min(100, ($totalHours / $targetHours) * 100) : 0;
        $attendance = round($progressPercent) . '%';

        // Calculate performance
        $performanceScore = 0;
        $stmt = $conn->prepare("
            SELECT
                SUM(CASE WHEN status = 'Completed' AND score IS NOT NULL THEN score ELSE 0 END) as total_score,
                COUNT(CASE WHEN (status = 'Completed' AND score IS NOT NULL) OR status = 'Missed' THEN 1 END) as scored_task_count
            FROM tasks
            WHERE student_id = ?
        ");
        if ($stmt) {
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result_perf = $stmt->get_result();
            if ($row_perf = $result_perf->fetch_assoc()) {
                $totalScore = $row_perf['total_score'] ?? 0;
                $scoredTaskCount = $row_perf['scored_task_count'] ?? 0;

                if ($scoredTaskCount > 0) {
                    $maxPossibleScore = $scoredTaskCount * 100;
                    $performanceScore = ($totalScore / $maxPossibleScore) * 100;
                }
            }
            $stmt->close();
        }
        $performance = round($performanceScore) . '%';

        // Calculate file submissions
        $totalFiles = 4; // DTR, MOA, LOA, Evaluation
        $approvedFiles = 0;
        $stmt = $conn->prepare("SELECT dtr_file_checked, moa_file_checked, letter_of_acceptance_file_checked, evaluation_form_file_checked FROM student_file_submissions WHERE student_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $student_id);
            $stmt->execute();
            $result_file = $stmt->get_result();
            if ($row_file = $result_file->fetch_assoc()) {
                if($row_file['dtr_file_checked']) $approvedFiles++;
                if($row_file['moa_file_checked']) $approvedFiles++;
                if($row_file['letter_of_acceptance_file_checked']) $approvedFiles++;
                if($row_file['evaluation_form_file_checked']) $approvedFiles++;
            }
            $stmt->close();
        }

        $fileProgressPercent = ($totalFiles > 0) ? (($approvedFiles / $totalFiles) * 100) : 0;
        $file_submissions = round($fileProgressPercent) . '%';

        // Update student_overview
        $stmt = $conn->prepare("UPDATE student_overview SET attendance = ?, performance = ?, file_submissions = ? WHERE student_id = ?");
        $stmt->bind_param("sssi", $attendance, $performance, $file_submissions, $student_id);
        $stmt->execute();
        $stmt->close();

        echo "Updated student $student_id: attendance $attendance, performance $performance, file_submissions $file_submissions\n";
    }
} else {
    echo "No students found.\n";
}

$conn->close();
?>

<?php
include 'db.php';

$student_id = isset($_GET['student_id']) ? intval($_GET['student_id']) : 0;

if ($student_id > 0) {
    // This is a placeholder. You should fetch the actual resume summary from your database.
    $resume_summary = [
        'objective' => 'To obtain a challenging position in a high-quality engineering environment where my resourceful experience and academic skills will add value to organizational operations.',
        'skills' => 'PHP, JavaScript, MySQL, HTML, CSS',
        'experience' => 'Web Developer Intern at XYZ Company (Summer 2023)',
        'education' => 'Bachelor of Science in Information Technology, Universidad De Manila (2020-2024)'
    ];

    echo json_encode(['success' => true, 'summary' => $resume_summary]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid student ID.']);
}
?>

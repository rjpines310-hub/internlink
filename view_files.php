<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

include 'db.php';

$userId = $_SESSION['user_id'];
$type = strtoupper($_GET['type'] ?? '');
$validTypes = ['DTR', 'MOA', 'EVALUATION', 'LOA'];
if (!in_array($type, $validTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid type']);
    exit();
}

$columnMap = [
    'DTR' => ['file' => 'dtr_file', 'checked' => 'dtr_file_checked'],
    'MOA' => ['file' => 'moa_file', 'checked' => 'moa_file_checked'],
    'EVALUATION' => ['file' => 'evaluation_form_file', 'checked' => 'evaluation_form_file_checked'],
    'LOA' => ['file' => 'letter_of_acceptance_file', 'checked' => 'letter_of_acceptance_file_checked']
];
$fileColumn = $columnMap[$type]['file'];
$checkedColumn = $columnMap[$type]['checked'];

$stmt = $conn->prepare("SELECT submission_id, $fileColumn, $checkedColumn FROM student_file_submissions WHERE student_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($submissionId, $fileName, $isChecked);
$stmt->fetch();

$files = [];
if ($fileName) {
    $files[] = [
        'submission_id' => $submissionId,
        'filename' => $fileName,
        'checked' => (bool)$isChecked
    ];
}

echo json_encode(['success' => true, 'files' => $files]);

$stmt->close();
$conn->close();
?>

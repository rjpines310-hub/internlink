<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: login.php");
    exit();
}

include 'db.php';
$userId = $_SESSION['user_id'];
$firstname = '';
$lastname = '';
$profile_picture = 'uploads/dp.jpg'; // default

// Fetch supervisor profile picture
$stmt = $conn->prepare("SELECT firstname, lastname, email, contact, password, profile_picture FROM supervisor WHERE supervisor_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result(
    $supervisor['firstname'],
    $supervisor['lastname'],
    $supervisor['email'],
    $supervisor['contact'],
    $supervisor['password'],
    $db_picture
);
$stmt->fetch();
$stmt->close();

$firstname = $supervisor['firstname'];
$lastname = $supervisor['lastname'];

if ($db_picture && file_exists($db_picture)) {
    $profile_picture = $db_picture;
}

// Fetch interns under this supervisor
$interns = [];
$interns_query = "
    SELECT
        s.student_id, s.studentid, s.firstname, s.lastname, s.email, s.profile_picture, s.contact, s.section, s.employment_status,
        p.internship_title as post_name,
        COALESCE(so.attendance, 0) as attendance,
        COALESCE(so.performance, 0) as performance,
        (SELECT COUNT(*) FROM ojt_feedback WHERE student_id = s.student_id AND supervisor_id = ? AND given_by = 'supervisor') as supervisor_feedback_given
    FROM student s
    LEFT JOIN student_overview so ON s.student_id = so.student_id
    LEFT JOIN internship_posts p ON s.post_id = p.post_id
    WHERE s.supervisor_id = ?
";
$interns_stmt = $conn->prepare($interns_query);
$interns_stmt->bind_param("ii", $userId, $userId); // Bind supervisor_id twice for the main query and subquery
$interns_stmt->execute();
$interns_result = $interns_stmt->get_result();
while ($row = $interns_result->fetch_assoc()) {
    // Set default profile picture if not provided
    if (empty($row['profile_picture']) || !file_exists($row['profile_picture'])) {
        $row['profile_picture'] = 'uploads/dp.jpg';
    }
    $interns[] = $row;
}
$interns_stmt->close();

// Calculate intern statistics
$completed_interns = 0;
$active_interns = 0;
foreach ($interns as $intern) {
    if ($intern['employment_status'] === 'completed') {
        $completed_interns++;
    } else {
        $active_interns++;
    }
}

// Helper function for progress bar class
function getProgressBarClass($value) {
    $numericValue = floatval(preg_replace('/[^0-9.]/', '', $value));
    if ($numericValue <= 25) return 'red';
    if ($numericValue <= 50) return 'orange';
    if ($numericValue <= 75) return 'yellow';
    return 'green';
}


?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Supervisor Dashboard | Universidad De Manila</title>
  <link rel="icon" href="assets/logo.png" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="supervisor_custom.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    a {
      text-decoration: none;
    }
    .interns-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
      margin-top: 20px;
    }
    .company-card {
      border: 1px solid #ccc;
      border-radius: 8px;
      padding: 15px;
      background: #fff;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      text-align: center;
      transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    /* Make profile images inside cards smaller and consistent */
    .company-card .profile-logo {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      object-fit: cover;
      display: block;
      margin: 0 auto 10px;
      /* Green border ONLY for card profile images */
      box-sizing: border-box;
      border: 3px solid var(--green);
    }

    /* Sidebar user profile (separate from card avatars) */
    .sidebar .profile-pic-container {
      width: 120px;
      height: 120px;
      margin: 18px auto;
      position: relative;
      cursor: pointer;
      display: block;
    }
    .sidebar .profile-pic-container img {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      object-fit: cover;
      box-sizing: border-box;
      border: 3px solid transparent; /* ensure no green border for user profile */
      display: block;
      background-color: #f4f4f4;
    }
    /* Overlay shown on hover to indicate change picture action */
    .sidebar .profile-pic-container .overlay {
      position: absolute;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(0,0,0,0.55);
      color: #fff;
      text-align: center;
      padding: 6px 8px;
      border-radius: 0 0 50% 50%;
      font-size: 0.9rem;
      opacity: 0;
      transition: opacity 0.15s ease;
      pointer-events: none;
    }
    .sidebar .profile-pic-container:hover .overlay {
      opacity: 1;
      pointer-events: auto;
    }
    /* Hide native file input — label triggers upload */
    #profileInput {
      display: none;
    }

    /* Keep sidebar profile larger (NO green border) */
    .sidebar .profile-pic-container img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      box-sizing: border-box;
      border-radius: 50%;
      background-color: #f4f4f4;
      border: 3px solid transparent;
      /* removed green border so user profile remains unchanged */
    }

    /* Remove green border on modal / detailed profile images (only cards get green border) */
    #internDetailsModal img,
    #resumeModal .resume-header img,
    #studentAttendanceModal img {
      box-sizing: border-box;
      border-radius: 50%;
      object-fit: cover;
      /* no border here */
    }
    #internOverviewContent .company-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }
    .company-card h4 {
      margin: 0 0 5px 0;
    }
    .company-card p {
      margin: 0;
      color: #666;
      font-size: 0.9rem;
    }
    .company-card-buttons {
      margin-top: 15px;
      display: flex;
      justify-content: center;
      gap: 10px;
    }
    .company-card-buttons button {
      background-color: #116530;
      border: none;
      color: white;
      padding: 8px 12px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.9rem;
      transition: background-color 0.3s ease;
    }
    .company-card-buttons button:hover {
      background-color: #0e5128;
    }
    .company-card-buttons button.secondary {
      background-color: #6c757d;
    }
    .company-card-buttons button.secondary:hover {
      background-color: #5a6268;
    }
    /* Form styling */
    #assignTaskForm {
      background: #fff;
      padding: 30px;
      border-radius: 8px;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      margin-top: 20px;
      width: 1000px;
    }
    .form-group {
      margin-bottom: 15px;
    }
    .form-group label {
      display: block;
      margin-bottom: 5px;
      font-weight: 600;
    }
    .form-group input[type="text"],
    .form-group input[type="date"],
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      box-sizing: border-box;
    }
    #assignTaskForm button[type="submit"] {
      background-color: #116530;
      color: white;
      padding: 10px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 1rem;
      display: block;
      margin: 0 auto;
    }
    #assignTaskForm button[type="submit"]:hover {
      background-color: #0e5a2a;
    }
    .back-btn {
        display: inline-block;
        margin-top: 20px;
        padding: 10px 20px;
        background-color: #6c757d;
        color: white;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        text-decoration: none;
    }
    .back-btn:hover {
        background-color: #5a6268;
    }
    #viewTasksContent {
        position: relative;
    }
    .calendar-and-range-container {
        width: 100%;
        max-width: 900px;
        margin: 20px auto;
        display: flex;
        flex-direction: column;
    }
    .performance-calendar-container {
        flex-shrink: 0;
    }
    .performance-calendar {
        width: 100%;
        max-width: 900px;
        border-collapse: collapse;
        background: #f0f9f0;
        border-radius: 8px;
        font-family: 'Inter', sans-serif;
    }
    .performance-calendar th {
        background: #116530;
        color: #fff;
        padding: 12px 10px;
        text-align: center;
        font-weight: 600;
        font-size: 1rem;
    }
    .performance-calendar th, .performance-calendar td {
        width: 40px;
        padding: 8px 3px;
        text-align: center;
        border: 1px solid #d4edda;
        box-sizing: border-box;
        border-radius: 8px;
    }
    .performance-calendar .calendar-date {
        cursor: pointer;
        font-size: 0.8rem;
        font-weight: 500;
        color: #116530;
    }
    .performance-calendar .calendar-date.current-day {
        background-color: #a8d5ba;
        color: #fff;
        border: none;
    }
    .performance-calendar .calendar-date.selected {
        background: #28a745;
        color: #fff;
        border-radius: 8px;
    }
    .nav-btn {
        background: #28a745;
        color: #fff;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        font-size: 1.2rem;
        font-weight: bold;
        cursor: pointer;
    }
    .nav-btn:hover {
        background: #218838;
    }
    .selected-date-tasks {
        flex: 1;
        padding: 15px;
        background: #f8fff8;
        border-radius: 8px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
    }
    .selected-date-tasks h3 {
        color: #116530;
        margin-bottom: 10px;
        flex-shrink: 0;
    }
    .selected-date-tasks p {
        flex: 1;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    .tasks-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
        max-height: 60vh;
        overflow-y: auto;
        padding: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        background-color: #fdfdfd;
    }
    .task-item {
        background: #fff;
        border-radius: 6px;
        padding: 12px;
        border-left: 4px solid #28a745;
    }
    .task-item.completed {
        border-left-color: #28a745;
        background: #f0f9f0;
    }
    .task-item.pending {
        border-left-color: #ffc107;
    }
    .task-item.overdue,
    .task-item.missed {
        border-left-color: #dc3545;
    }
    .task-status {
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 0.75rem;
        font-weight: bold;
        text-transform: uppercase;
    }
    .task-status.completed {
        background: #d4edda;
        color: #155724;
    }
    .task-status.pending {
        background: #fff3cd;
        color: #856404;
    }
    .task-status.submitted {
        background: #d1ecf1;
        color: #0c5460;
    }
    .task-status.overdue,
    .task-status.missed {
        background: #f8d7da;
        color: #721c24;
    }
    .task-filters {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        border-bottom: 1px solid #ccc;
        padding-bottom: 10px;
    }
    .task-filter-btn {
        padding: 8px 16px;
        border: 1px solid #ccc;
        background-color: #f8f9fa;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
        color: #495057;
    }
    .task-filter-btn:hover {
        background-color: #e9ecef;
    }
    .task-filter-btn.active {
        background-color: #116530;
        color: white;
        border-color: #116530;
    }
    .score-input {
        width: 60px;
        padding: 5px;
        margin-right: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .submit-score-btn {
        padding: 6px 12px;
        background-color: #28a745;
        color: white;
        border: none;
        border-radius: 4px;
        cursor: pointer;
    }
    .submit-score-btn:hover {
        background-color: #218838;
    }

    :root {
      --red: red;
      --orange: orange;
      --yellow: #FFBF00;
      --green: #28a745;
    }

    /* Circular progress styles */
    .student-overview-card {
      cursor: pointer;
    }
    .progress-circles-container {
      display: flex;
      justify-content: space-around;
      margin-top: 15px;
    }
    .progress-circle-container {
      position: relative;
      width: 140px;
      height: 140px;
      display: flex;
      align-items: center;
      justify-content: center;
      text-align: center;
      overflow: visible;
    }
    .progress-ring {
      transform: rotate(-90deg);
    }
    .progress-ring__circle-bg {
        stroke: #e6e6e6;
    }
    .progress-ring__circle {
        transition: stroke-dashoffset 0.5s ease;
    }
    .progress-ring .progress-ring__circle.red { stroke: var(--red); }
    .progress-ring .progress-ring__circle.orange { stroke: var(--orange); }
    .progress-ring .progress-ring__circle.yellow { stroke: var(--yellow); }
    .progress-ring .progress-ring__circle.green { stroke: var(--green); }
    .progress-text {
        position: absolute;
        font-size: 1.8rem;
        font-weight: bold;
        color: #116530;
    }
    .progress-circle-container label {
      position: absolute;
      bottom: -15px;
      left: 0;
      right: 0;
      font-size: 0.8rem;
      color: #333;
    }

    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.4);
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background-color: #fefefe;
      margin: 10% auto;
      padding: 20px;
      border: 1px solid #888;
      width: 80%;
      max-width: 500px;
      border-radius: 8px;
      position: relative;
    }
    .close-btn {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
      position: absolute;
      top: 10px;
      right: 20px;
    }
    .close-btn:hover,
    .close-btn:focus {
      color: black;
      text-decoration: none;
      cursor: pointer;
    }

    .view-resume-btn {
      display: inline-block;
      margin-top: 10px;
      padding: 8px 16px;
      background-color: #116530;
      color: white;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      text-decoration: none;
      font-size: 0.9rem;
      transition: background-color 0.3s ease;
    }

    .view-resume-btn:hover {
      background-color: #0e5128;
    }

    #resumeModal .modal-content {
      text-align: left;
    }

    #resumeModal h4 {
      color: #116530;
      border-bottom: 2px solid #116530;
      padding-bottom: 10px;
      margin-bottom: 20px;
    }

    #resumeModal p {
      margin-bottom: 10px;
      line-height: 1.6;
    }

    #resumeModal p strong {
      color: #333;
    }
    #resumeModal .modal-content {
      max-width: 700px;
    }
    #resumeModal .resume-header {
      text-align: center;
      margin-bottom: 20px;
      padding-bottom: 20px;
      border-bottom: 1px solid #eee;
    }
    #resumeModal .resume-header img {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
      border: 3px solid #116530;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    #resumeModal .resume-header h3 {
      margin: 10px 0 5px;
      color: #333;
    }
    #resumeModal .resume-header p {
      margin: 0;
      color: #666;
      font-size: 0.9rem;
    }
    #resumeModal h5 {
        color: #116530;
        border-bottom: 1px solid #eee;
        padding-bottom: 5px;
        margin-top: 20px;
        font-size: 1.1rem;
    }
    #resumeModal ul {
        list-style-type: none;
        padding-left: 0;
    }
    #resumeModal ul li {
        background: #f9f9f9;
        margin-bottom: 8px;
        padding: 12px;
        border-radius: 4px;
        border-left: 3px solid #116530;
    }

    .search-bar {
      width: 100%;
      font-size: 16px;
      padding: 12px 20px 12px 40px;
      border: 1px solid #ddd;
      margin-bottom: 12px;
      background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A//www.w3.org/2000/svg%22%20viewBox%3D%220%200%2024%2024%22%20fill%3D%22%23aaa%22%3E%3Cpath%20d%3D%22M23.7%2022.3l-6.2-6.2c1.4-1.7%202.3-3.9%202.3-6.3%200-5.5-4.5-10-10-10S-0.2%204.3-0.2%209.8s4.5%2010%2010%2010c2.4%200%204.6-0.9%206.3-2.3l6.2%206.2%201.4-1.4zm-13.9-3.5c-4.4%200-8-3.6-8-8s3.6-8%208-8%208%203.6%208%208-3.6%208-8%208z%22/%3E%3C/svg%3E');
      background-position: 10px 12px;
      background-repeat: no-repeat;
      background-size: 20px;
      border-radius: 25px;
      transition: all 0.3s ease;
    }
    .search-bar:focus {
      border-color: #116530;
      box-shadow: 0 0 5px rgba(17, 101, 48, 0.5);
      outline: none;
    }
    .progress-bar-container {
      text-align: center;
    }
    .progress-bar-bg {
      width: 100px;
      height: 10px;
      background-color: #e0e0e0;
      border-radius: 5px;
      overflow: hidden;
      margin: 0 auto;
    }
    .progress-bar-fill {
      height: 100%;
      border-radius: 10px;
      transition: width 0.3s ease;
    }
    .progress-bar-container .progress-value {
      display: block;
      margin-bottom: 5px;
      font-size: 1.2rem;
      font-weight: bold;
      color: #116530;
    }
    .progress-bar-container label {
      margin-top: 5px;
      font-size: 0.8rem;
      color: #333;
    }

    .progress-bar-fill.red {
      background: red;
    }

    .progress-bar-fill.orange {
      background: orange;
    }

    .progress-bar-fill.green {
      background: green;
    }

    .progress-bar-fill.yellow {
      background: #FFBF00;
    }
    #studentAttendanceGrid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 20px;
      width: 100%;
    }
    
    .student-attendance-grid .company-card {
        cursor: pointer;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
    }
    .attendance-tracker {
        display: flex;
        gap: 20px;
        margin-top: 20px;
    }
    .calendar-section {
        width: 50%;
    }
    .log-section {
        width: 50%;
        max-height: 400px;
        overflow-y: auto;
    }
    #attendanceCalendar table {
        width: 100%;
        border-collapse: collapse;
    }
    #attendanceCalendar th, #attendanceCalendar td {
        text-align: center;
        padding: 10px;
        border: 1px solid #ddd;
    }
    #attendanceCalendar td.day-with-log {
        background-color: #d4edda;
        cursor: pointer;
    }
    #attendanceCalendar td.selected {
        background-color: #116530;
        color: white;
    }
    .log-card {
        background: #f9f9f9;
        border-left: 4px solid #116530;
        padding: 12px;
        border-radius: 8px;
        display: flex;
        justify-content: space-around;
        align-items: center;
        margin-bottom: 10px;
    }
    .log-selfie {
        width: 60px;
        height: 60px;
        border-radius: 8px;
        object-fit: cover;
        margin-top: 8px;
    }
    .no-selfie-placeholder {
        width: 60px;
        height: 60px;
        border-radius: 8px;
        background: #f0f0f0;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #aaa;
        margin-top: 8px;
    }
    .log-validation-section {
        padding-left: 15px;
    }
    .validate-btn {
        background-color: #28a745;
        color: white;
        border: none;
        padding: 8px 12px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.9rem;
        transition: background-color 0.3s ease;
    }
    .validate-btn:hover {
        background-color: #218838;
    }
    .validate-btn:disabled {
        background-color: #6c757d;
        cursor: not-allowed;
    }
    .validated-status {
        color: #28a745;
        font-weight: bold;
    }
    #imageModal {
        z-index: 1003; /* Higher than the attendance modal */
    }
    /* Dashboard Cards Grid for Supervisor Home */
    .dashboard-cards-grid {
      display: grid;
      grid-template-columns: 1fr; /* Always single-column layout for full width */
      gap: 20px;
      width: 100%;
      max-width: 1200px;
      margin: 30px auto 0;
    }

    .welcome-message {
      text-align: center;
    }

    .welcome-wrapper {
      max-width: 1200px;
      margin: 0 auto;
      text-align: center;
    }

    /* Kard Styles (similar to faculty_dashboard.css) */
    .kard {
      background: rgba(255, 255, 255, 0.95);
      border-radius: 16px;
      box-shadow: 0 4px 20px rgba(17, 101, 48, 0.1);
      padding: 0;
      width: 100%;
      display: flex;
      flex-direction: column;
      transition: all 0.3s ease;
      text-align: left;
      border: 1px solid rgba(17, 101, 48, 0.1);
      position: relative;
      overflow: visible;
      min-height: auto;
    }

    .kard:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 25px rgba(17, 101, 48, 0.15);
      border-color: rgba(17, 101, 48, 0.2);
    }

    .kard-header {
      padding: 20px 24px 16px 24px;
      background: linear-gradient(135deg, #f0f9f0 0%, #e0f2e0 100%); /* Modern gradient background */
      border-radius: 16px 16px 0 0;
      position: relative;
      border-bottom: 1px solid rgba(17, 101, 48, 0.1); /* Subtle border */
      display: flex; /* Use flexbox for alignment */
      align-items: center; /* Vertically align items */
      gap: 10px; /* Space between icon (if added) and title */
    }

    .kard h3 {
      margin: 0;
      color: #116530;
      font-size: 1.1rem; /* Smaller font size */
      font-weight: 700;
      line-height: 1.3;
      letter-spacing: -0.3px; /* Adjusted letter spacing */
    }

    .kard-content {
      padding: 20px 24px 24px 24px;
      flex: 1;
      display: flex;
      flex-direction: column;
      gap: 14px;
    }

    .kard-content .info-list ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .kard-content .info-list li {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 0;
      border-bottom: 1px solid #eee;
      font-size: 0.95rem;
      color: #333;
    }

    .kard-content .info-list li:last-child {
      border-bottom: none;
    }

    .kard-content .info-list li span:first-child {
      font-weight: 600;
      color: #116530;
    }

    .kard-content .info-list li span:last-child {
      color: #666;
    }

    .kard-content .no-data {
      text-align: center;
      color: #777;
      padding: 20px;
    }

    /* Specific styles for the announcement card */
    .announcement-kard .kard-content {
      padding: 15px 20px;
    }

    .announcement-kard .kard-content ul {
      list-style: none;
      padding: 0;
    }

    .announcement-kard .kard-content .info-item {
      background: #f9f9f9;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      padding: 12px;
      margin-bottom: 10px;
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .announcement-kard .kard-content .info-item:last-child {
      margin-bottom: 0;
    }

    .announcement-kard .kard-content .info-label {
      font-weight: 600;
      color: #116530;
      font-size: 1rem;
    }

    .announcement-kard .kard-content .info-value {
      color: #333;
      font-size: 0.9rem;
      line-height: 1.4;
    }

    .announcement-kard .kard-content .muted {
      font-size: 0.75rem;
      color: #666;
      margin-top: 5px;
    }

    .announcement-kard .new-company-btn {
      margin-top: 20px;
      align-self: center;
    }

    /* Styles for Most Interns in Progress */
    .interns-tile-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
      gap: 15px;
      margin-top: 10px;
    }

    .intern-tile {
      background: #fff;
      border: 1px solid #e0e0e0;
      border-radius: 8px;
      padding: 15px;
      text-align: center;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
      transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .intern-tile:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 8px rgba(0,0,0,0.15);
    }

    .tile-profile-pic {
      width: 60px;
      height: 60px;
      border-radius: 50%;
      object-fit: cover;
      margin: 0 auto 10px;
      display: block;
      border: 2px solid #116530;
    }

    .tile-info {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .tile-name {
      font-weight: 600;
      color: #116530;
      font-size: 0.9rem;
    }

    .tile-progress {
      font-size: 0.8rem;
      color: #28a745;
      font-weight: bold;
    }

    .tile-company {
      font-size: 0.75rem;
      color: #666;
    }

    /* Mobile Responsive for Kard */
    @media (max-width: 768px) {
      /* The dashboard-cards-grid is already 1fr globally, so no need for specific mobile override here */
      gap: 15px;
      margin: 20px auto 0;

      .kard {
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(17, 101, 48, 0.1);
      }

      .kard-header {
        padding: 15px 20px 10px 20px;
      }

      .kard h3 {
        font-size: 1rem;
      }

      .kard-content {
        padding: 15px 20px;
        gap: 10px;
      }

      .kard-content .info-list li {
        padding: 8px 0;
        font-size: 0.9rem;
      }

      .announcement-kard .kard-content .info-item {
        padding: 10px;
        margin-bottom: 8px;
      }

      .announcement-kard .kard-content .info-label {
        font-size: 0.95rem;
      }

      .announcement-kard .kard-content .info-value {
        font-size: 0.85rem;
      }

      .announcement-kard .kard-content .muted {
        font-size: 0.7rem;
      }
    }

    @media (max-width: 480px) {
      .dashboard-cards-grid {
        gap: 10px;
        margin-top: 15px;
      }

      .kard {
        border-radius: 10px;
      }

      .kard-header {
        padding: 12px 15px 8px 15px;
      }

      .kard h3 {
        font-size: 0.9rem;
      }

      .kard-content {
        padding: 12px 15px;
        gap: 8px;
      }

      .kard-content .info-list li {
        padding: 6px 0;
        font-size: 0.85rem;
      }

      .announcement-kard .kard-content .info-item {
        padding: 8px;
        margin-bottom: 6px;
      }

      .announcement-kard .kard-content .info-label {
        font-size: 0.9rem;
      }

      .announcement-kard .kard-content .info-value {
        font-size: 0.8rem;
      }

      .announcement-kard .kard-content .muted {
        font-size: 0.65rem;
      }
    }

    /* General styling for task cards */
  .tasks-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
  }
  .manual-time-request-list {
    max-height: 400px;
    overflow-y: auto;
  }
  .manual-time-request-item {
    background: #f9f9f9;
    padding: 12px;
    margin-bottom: 10px;
    border-radius: 8px;
    border: 1px solid #ddd;
  }
  .manual-time-request-actions {
    margin-top: 8px;
  }
  .manual-time-request-actions button {
    margin-right: 10px;
    background-color: #116530;
    color: white;
    border: none;
    padding: 6px 10px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 0.85rem;
  }
  .manual-time-request-actions button:hover {
    background-color: #0e5128;
  }
  </style>
</head>
<body>
<header>
  <div class="header-left">
    <!-- Hamburger Menu Button for Mobile -->
    <button class="hamburger-btn" onclick="toggleSidebar()">
      <span></span>
      <span></span>
      <span></span>
    </button>
    <h2>
      <img src="header.png" alt="Intern Icon" style="border-radius: 50%; margin-right: 5px;">
      <div>
        <span style="color: #DAA520; font-weight: 700; display: block; margin: 0 0 2px 0; text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);">INTERNLINK</span>
        <span style="color: #006400; font-weight: 700; display: block; margin: -5px 0 0 0; text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);">UNIVERSIDAD DE MANILA</span>
      </div>
      </div>
  <div class="nav-buttons">
    <a href="#" class="home-link" onclick="goHome()">Home</a>

    <div class="dropdown">
      <button class="dropbtn">Profile▼</button>
      <div class="dropdown-content">
        <a href="#" onclick="showProfile()">Edit Profile</a>
        <a href="logout.php">Log Out</a>
      </div>
    </div>
  </div>
</header>

  <div class="dashboard-wrapper">
    <div class="sidebar">
      <form action="update_profile_picture.php" method="POST" enctype="multipart/form-data" id="uploadForm">
        <label for="profileInput">
          <div class="profile-pic-container">
            <img src="<?php echo htmlspecialchars($profile_picture); ?>?t=<?php echo time(); ?>" alt="Profile Picture" />
            <div class="overlay">Change Picture</div>
          </div>
        </label>
        <input type="file" id="profileInput" name="profile_picture" onchange="document.getElementById('uploadForm').submit();" />
      </form>
      <div class="student-name"><?php echo htmlspecialchars($firstname . ' ' . $lastname); ?></div>
      <a href="#" onclick="showTab('internOverviewContent')">Intern Overview</a>
      <a href="#" onclick="showTab('internAttendanceContent')">Intern Attendance</a>
      <a href="#" onclick="showTab('internPerformanceContent')">Intern Performance</a>
      <a href="#" onclick="showTab('manualTimeRequestsContent')">Manual Time Requests</a>
      <a href="#" onclick="showTab('messageContent')">Messages</a>
    </div>

    <div class="main-content active" id="mainContent">
      <div class="welcome-wrapper">
        <h2 class="welcome-message">Welcome, <?php echo htmlspecialchars($firstname . ' ' . $lastname); ?>!</h2>
      </div>
      <div class="dashboard-cards-grid">
        <!-- Supervisor announcements will be loaded here -->
        <div class="kard announcement-kard">
          <div class="kard-header">
            <h3>Announcements</h3>
          </div>
          <div id="supervisorAnnouncementsContainer" class="kard-content">Loading announcements...</div>
        </div>

        <!-- Intern Statistics -->
        <div class="kard">
          <div class="kard-header">
            <h3>Intern Statistics</h3>
          </div>
          <div class="kard-content info-list">
            <div>
              <div><span>Active Interns:</span> <span><?php echo $active_interns; ?></span></div>
              <div><span>Completed Interns:</span> <span><?php echo $completed_interns; ?></span></div>
            </div>
          </div>
        </div>

        <!-- Most Interns in Progress -->
        <div class="kard">
          <div class="kard-header">
            <h3>Most Interns in Progress</h3>
          </div>
          <div id="mostInternsInProgress" class="kard-content info-list">Loading...</div>
        </div>

        <!-- Newest Submitted Tasks -->
        <div class="kard">
          <div class="kard-header">
            <h3>Newest Submitted Tasks</h3>
          </div>
          <div id="newestSubmittedTasks" class="kard-content info-list">Loading...</div>
        </div>

        <!-- Newest Attendance Records Needing Validation -->
        <div class="kard">
          <div class="kard-header">
            <h3>Newest Attendance Needing Validation</h3>
          </div>
          <div id="newestAttendanceValidation" class="kard-content info-list">Loading...</div>
        </div>
      </div>
    </div>

    <div class="profile" id="profileContent">
      <h2>Edit Profile</h2>
      <form id="profileForm">
        <?php foreach (['firstname','lastname','email','contact'] as $field): ?>
        <div class="form-row">
          <label><?= ucfirst($field) ?></label>
          <input type="text" name="<?= $field ?>" id="<?= $field ?>" value="<?= htmlspecialchars($supervisor[$field]) ?>" disabled />
          <span class="edit-icon" onclick="enableEdit('<?= $field ?>')">&#9998;</span>
        </div>
        <?php endforeach; ?>

        <div class="form-row">
          <label>Password</label>
          <input type="password" id="password_display" value="******" disabled />
          <span class="edit-icon" onclick="enablePassword()">&#9998;</span>
        </div>
        <div id="passwordFields" style="display:none;">
          <div class="form-row">
            <label>New Password</label>
            <input type="password" name="password" id="password" />
          </div>
          <div class="form-row">
            <label>Confirm</label>
            <input type="password" id="confirm_password" />
          </div>
        </div>

        <div class="action-buttons" id="actionButtons" style="display:none;">
          <button type="button" class="save-btn" onclick="saveProfile()">Save Changes</button>
          <button type="button" class="cancel-btn" onclick="cancelEdit()">Cancel</button>
        </div>
      </form>
      <hr />
      
      <button class="back-btn" onclick="goHome()">Back</button>
    </div>

    <div class="tab-content" id="internOverviewContent">
      <div class="welcome-wrapper">
        <h2 class="welcome-message">Intern Overview</h2>
      </div>
      <input type="text" id="internSearch" class="search-bar" onkeyup="searchInterns()" placeholder="Search by name or ID..." title="Type in a name or ID">
      <div class="interns-grid">
        <?php if (empty($interns)): ?>
          <p>No interns are currently assigned to you.</p>
        <?php else: ?>
          <?php foreach ($interns as $intern): ?>
            <div class="company-card student-overview-card" 
                 data-intern='<?php echo htmlspecialchars(json_encode($intern)); ?>' 
                 onclick="showInternDetails(this)"
                 data-name="<?php echo htmlspecialchars(strtolower($intern['firstname'] . ' ' . $intern['lastname'])); ?>"
                 data-studentid="<?php echo htmlspecialchars($intern['studentid']); ?>">
              <img src="<?php echo htmlspecialchars($intern['profile_picture']); ?>" alt="Profile Picture" class="profile-logo">
              <h4><?php echo htmlspecialchars($intern['firstname'] . ' ' . $intern['lastname']); ?></h4>
              <p><?php echo htmlspecialchars($intern['post_name'] ?? 'N/A'); ?></p>
              <p><?php echo htmlspecialchars($intern['email']); ?></p>
              
              <div class="progress-circles-container">
                <div class="progress-circle-container">
                  <svg class="progress-ring" width="140" height="140">
                    <circle class="progress-ring__circle-bg" stroke-width="10" fill="transparent" r="60" cx="70" cy="70"/>
                    <circle id="attendance-circle-<?php echo $intern['student_id']; ?>" class="progress-ring__circle <?php echo getProgressBarClass($intern['attendance']); ?>" stroke-width="10" fill="transparent" r="60" cx="70" cy="70"/>
                  </svg>
                  <span class="progress-text"><?php echo round(floatval(str_replace('%', '', $intern['attendance']))); ?>%</span>
                  <label>OJT Hrs Rendered</label>
                </div>
                <div class="progress-circle-container">
                  <svg class="progress-ring" width="140" height="140">
                    <circle class="progress-ring__circle-bg" stroke-width="10" fill="transparent" r="60" cx="70" cy="70"/>
                    <circle id="performance-circle-<?php echo $intern['student_id']; ?>" class="progress-ring__circle <?php echo getProgressBarClass($intern['performance']); ?>" stroke-width="10" fill="transparent" r="60" cx="70" cy="70"/>
                  </svg>
                  <span class="progress-text"><?php echo round(floatval(str_replace('%', '', $intern['performance']))); ?>%</span>
                  <label>Performance</label>
                </div>
              </div>
              <?php if ($intern['employment_status'] === 'completed'): ?>
                <button class="give-feedback-btn" data-student-id="<?php echo $intern['student_id']; ?>" data-student-name="<?php echo htmlspecialchars($intern['firstname'] . ' ' . $intern['lastname']); ?>" <?php echo ($intern['supervisor_feedback_given'] > 0) ? 'disabled' : ''; ?>>
                  <?php echo ($intern['supervisor_feedback_given'] > 0) ? 'Feedback Submitted' : 'Give Feedback'; ?>
                </button>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="tab-content" id="internAttendanceContent" style="position: relative;">
      <div class="welcome-wrapper">
        <h2 class="welcome-message">Intern Attendance Overview</h2>
      </div>
      <input type="text" id="internAttendanceSearch" class="search-bar" onkeyup="filterAttendanceStudents()" placeholder="Search by name or ID...">
      <div class="companies-grid student-attendance-grid" id="studentAttendanceGrid">
        <?php
        $attendance_query = "
            SELECT 
                s.student_id, s.studentid, s.firstname, s.lastname, s.section, s.profile_picture, s.email,
                p.internship_title as post_name
            FROM student s
            LEFT JOIN internship_posts p ON s.post_id = p.post_id
            WHERE s.supervisor_id = ?
            ORDER BY s.lastname, s.firstname
        ";
        $attendance_stmt = $conn->prepare($attendance_query);
        $attendance_stmt->bind_param("i", $userId);
        $attendance_stmt->execute();
        $attendance_res = $attendance_stmt->get_result();

        if ($attendance_res && $attendance_res->num_rows > 0) {
          while ($student = $attendance_res->fetch_assoc()) {
            $student_picture = 'uploads/dp.jpg';
            if (!empty($student['profile_picture']) && file_exists($student['profile_picture'])) {
              $student_picture = $student['profile_picture'];
            }

            // Calculate attendance percentage for each student
            $totalHours = 0;
            $stmt_hours = $conn->prepare("SELECT time_in, time_out FROM timecard WHERE student_id = ? AND status = 'Validated'");
            $stmt_hours->bind_param("i", $student['student_id']);
            $stmt_hours->execute();
            $result_hours = $stmt_hours->get_result();
            while ($row_hours = $result_hours->fetch_assoc()) {
                if (!empty($row_hours['time_in']) && !empty($row_hours['time_out'])) {
                    $in = new DateTime($row_hours['time_in']);
                    $out = new DateTime($row_hours['time_out']);
                    $diff = $in->diff($out);
                    $hours = $diff->h + ($diff->days * 24) + ($diff->i / 60);
                    $totalHours += $hours;
                }
            }
            $stmt_hours->close();
            
            $targetHours = 200; // Assuming a 200-hour target
            $progressPercent = min(100, ($totalHours / $targetHours) * 100);
        ?>
            <div class="company-card" 
                 onclick="showStudentAttendanceDetails(<?php echo $student['student_id']; ?>)"
                 data-name="<?php echo htmlspecialchars(strtolower($student['firstname'] . ' ' . $student['lastname'])); ?>"
                 data-studentid="<?php echo htmlspecialchars($student['studentid']); ?>">
              <img src="<?php echo htmlspecialchars($student_picture); ?>" alt="Student Picture" class="profile-logo">
              <h4><?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></h4>
              <p><?php echo htmlspecialchars($student['email']); ?></p>
              <p><?php echo htmlspecialchars($student['post_name'] ?? 'N/A'); ?></p>
              <div class="progress-bar-container">
                <div class="progress-bar-bg">
                  <div class="progress-bar-fill <?php echo getProgressBarClass($progressPercent); ?>" style="width: <?php echo $progressPercent; ?>%;"></div>
                </div>
                <span class="progress-value"><?php echo round($totalHours, 1); ?> / <?php echo $targetHours; ?> hrs</span>
                <label>Attendance</label>
              </div>
            </div>
        <?php
          }
        } else {
          echo '<p>No students found.</p>';
        }
        $attendance_stmt->close();
        ?>
      </div>
    </div>

    <div class="tab-content" id="internPerformanceContent">
      <div class="welcome-wrapper">
        <h2 class="welcome-message">Intern Performance</h2>
      </div>
      <input type="text" id="performanceSearch" class="search-bar" onkeyup="searchPerformanceInterns()" placeholder="Search by name or ID..." title="Type in a name or ID">
      <div class="interns-grid">
        <?php if (empty($interns)): ?>
          <p>No interns are currently assigned to you.</p>
        <?php else: ?>
          <?php foreach ($interns as $intern): ?>
            <div class="company-card"
                 data-name="<?php echo htmlspecialchars(strtolower($intern['firstname'] . ' ' . $intern['lastname'])); ?>"
                 data-studentid="<?php echo htmlspecialchars($intern['studentid']); ?>">
              <img src="<?php echo htmlspecialchars($intern['profile_picture']); ?>" alt="Profile Picture" class="profile-logo">
              <h4><?php echo htmlspecialchars($intern['firstname'] . ' ' . $intern['lastname']); ?></h4>
              <p><?php echo htmlspecialchars($intern['email']); ?></p>
              <div class="progress-circles-container">
                <div class="progress-circle-container">
                  <svg class="progress-ring" width="140" height="140">
                    <circle class="progress-ring__circle-bg" stroke-width="10" fill="transparent" r="60" cx="70" cy="70"/>
                    <circle id="performance-circle-perf-<?php echo $intern['student_id']; ?>" class="progress-ring__circle <?php echo getProgressBarClass($intern['performance']); ?>" stroke-width="10" fill="transparent" r="60" cx="70" cy="70"/>
                  </svg>
                  <span class="progress-text"><?php echo round(floatval(str_replace('%', '', $intern['performance']))); ?>%</span>
                  <label>Performance</label>
                </div>
              </div>
              <div class="company-card-buttons">
                <button onclick="assignTask(<?php echo $intern['student_id']; ?>)">Assign Task</button>
                <button class="secondary" onclick="viewTasks(<?php echo $intern['student_id']; ?>)">View Tasks</button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <div class="main-content" id="assignTasksContent">
      <h2>Assign Tasks</h2>
      <form id="assignTaskForm">
        <div class="form-group">
          <label for="student_id">Select Student:</label>
          <select name="student_id" id="student_id" required>
            <option value="">Choose an intern...</option>
            <?php foreach ($interns as $intern): ?>
              <option value="<?php echo $intern['student_id']; ?>">
                <?php echo htmlspecialchars($intern['firstname'] . ' ' . $intern['lastname']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="title">Task Title:</label>
          <input type="text" name="title" id="title" required placeholder="Enter task title">
        </div>
        <div class="form-group">
          <label for="description">Description:</label>
          <textarea name="description" id="description" rows="4" placeholder="Enter task description"></textarea>
        </div>
        <div class="form-group">
          <label for="due_date">Due Date:</label>
          <input type="date" name="due_date" id="due_date">
        </div>
        <button type="submit">Assign Task</button>
      </form>
      <?php if (empty($interns)): ?>
        <p>No interns assigned to you yet.</p>
      <?php endif; ?>
      <button class="back-btn" onclick="showTab('internPerformanceContent')">Back</button>
    </div>

    <div class="main-content" id="viewTasksContent">
        <h2 id="internTasksTitle">Intern Tasks</h2>
        <div class="task-filters">
            <button class="task-filter-btn active" onclick="filterTasks('all')">All</button>
            <button class="task-filter-btn" onclick="filterTasks('assigned')">Assigned</button>
            <button class="task-filter-btn" onclick="filterTasks('submitted')">Submitted</button>
            <button class="task-filter-btn" onclick="filterTasks('completed')">Completed</button>
            <button class="task-filter-btn" onclick="filterTasks('missed')">Missed</button>
        </div>
        <div id="tasksListContainer" class="tasks-list">
            <p>Select a filter to view tasks.</p>
        </div>
        <button class="back-btn" onclick="showTab('internPerformanceContent')">Back</button>
    </div>

    <div class="tab-content" id="manualTimeRequestsContent">
      <div class="welcome-wrapper">
        <h2 class="welcome-message">Manual Time Requests</h2>
      </div>
      <div id="manualTimeRequestsContainer">
        <p>Loading manual time requests...</p>
      </div>
    </div>

    <div class="tab-content" id="messageContent">
      <div class="messaging-container">
        <!-- Conversations List -->
        <div class="conversations-list">
          <div class="conversations-header">
            <h3>Messages</h3>
            <button class="new-chat-btn" id="newChatBtn">New Chat</button>
          </div>
          <div class="conversations-body">
            <div id="conversations">
              <div class="loading">Loading conversations...</div>
            </div>
          </div>
        </div>

        <!-- Chat Area -->
        <div class="chat-area">
          <div class="chat-header">
            <button class="back-to-conversations" onclick="showConversationsList()">← Back</button>
            <h3 id="chat-title">Select a conversation</h3>
            <div class="chat-actions">
              <!-- Future: Add video call, phone call buttons here -->
            </div>
          </div>
          
          <div class="messages" id="messages">
            <div class="no-conversation-selected">
              <div style="text-align: center; color: #666; padding: 40px;">
                <h4>Welcome to Messages</h4>
                <p>Select a conversation from the left to start chatting, or click "New Chat" to start a new conversation.</p>
              </div>
            </div>
          </div>
          
          <div class="message-input">
            <input 
              type="text" 
              id="messageText" 
              placeholder="Type a message..." 
              maxlength="500"
              disabled
            >
            <button id="sendBtn" disabled>Send</button>
          </div>
        </div>
      </div>
    </div>
  </div>

<!-- New Chat Modal -->
<div id="newChatModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Start New Conversation</h3>
      <button class="close-modal" onclick="hideNewChatModal()">&times;</button>
    </div>
    <div class="modal-body">
      <input 
        type="text" 
        id="userSearchInput" 
        class="search-input" 
        placeholder="Search for students, faculty, supervisors, or companies..."
        autocomplete="off"
      >
      <div id="searchResults" class="search-results">
        <div class="search-placeholder">
          Type at least 2 characters to search for users
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Feedback Modal -->
<div id="feedbackModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3>Give Feedback for <span id="feedbackStudentName"></span></h3>
      <button class="close-btn" onclick="closeFeedbackModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form id="feedbackForm">
        <input type="hidden" id="feedbackStudentId" name="student_id">
        <input type="hidden" id="feedbackGivenBy" name="given_by" value="supervisor">
        
        <label for="feedbackMessage">Feedback Message:</label>
        <textarea id="feedbackMessage" name="feedback_message" rows="5" required></textarea>
        
        <button type="submit" class="save-btn">Submit Feedback</button>
      </form>
    </div>
  </div>
</div>

<!-- Intern Details Modal -->
<div id="internDetailsModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeInternDetailsModal()">&times;</span>
    <div id="internDetailsBody">
      <!-- Details will be populated by JS -->
    </div>
  </div>
</div>

<!-- Student Attendance Details Modal -->
<div id="studentAttendanceModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1002; justify-content: center; align-items: center;">
  <div style="background: white; padding: 20px; border-radius: 10px; width: 90%; max-width: 900px;">
    <h3>Student Attendance Details</h3>
    <div id="studentAttendanceModalBody"></div>
    <button onclick="document.getElementById('studentAttendanceModal').style.display = 'none'" style="padding: 10px 20px; background: #ccc; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px;">Close</button>
  </div>
</div>

<!-- Resume Summary Modal -->
<div id="resumeModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="closeResumeModal()">&times;</span>
    <div id="resumeBody">
      <!-- Resume summary will be populated by JS -->
    </div>
  </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="modal" onclick="closeImageModal()">
  <div class="modal-content">
    <img id="modalImage" src="" alt="Enlarged Selfie">
  </div>
</div>

<!-- Manual Time Request Modal -->
<div id="manualTimeRequestModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="hideManualTimeRequestModal()">&times;</span>
    <h3>Request Manual Time Adjustment</h3>
      <form id="manualTimeRequestForm">
        <input type="hidden" id="request_id" name="request_id" value="">
      <div class="form-group">
        <label for="request_student_id">Select Student:</label>
        <select id="request_student_id" name="student_id" required>
          <option value="">-- Select Student --</option>
          <?php foreach ($interns as $intern): ?>
            <option value="<?php echo $intern['student_id']; ?>">
              <?php echo htmlspecialchars($intern['firstname'] . ' ' . $intern['lastname']); ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label for="request_date">Date:</label>
        <input type="date" id="request_date" name="date" required>
      </div>
      <div class="form-group">
        <label for="request_time_in">Time In:</label>
        <input type="time" id="request_time_in" name="time_in">
      </div>
      <div class="form-group">
        <label for="request_time_out">Time Out:</label>
        <input type="time" id="request_time_out" name="time_out">
      </div>
      <div class="form-group">
        <label for="request_reason">Reason:</label>
        <textarea id="request_reason" name="reason" rows="4" required placeholder="Explain why this manual adjustment is needed..."></textarea>
      </div>
      <button type="submit" class="submit-btn">Submit Request</button>
      <button type="button" id="cancelEditBtn" style="margin-left: 10px; display:none;">Cancel Edit</button>
    </form>
    
    <hr style="margin: 30px 0; border: none; border-top: 1px solid #ddd;">
    
    <h4>Previous Requests</h4>
    <div id="manualTimeListContainer">
      <p>Loading requests...</p>
    </div>
  </div>
</div>

<!-- Edit Manual Time Request Modal -->
<div id="editManualTimeModal" class="modal">
  <div class="modal-content">
    <span class="close-btn" onclick="hideEditManualTimeModal()">&times;</span>
    <h3>Edit Manual Time Request</h3>
    <form id="editManualTimeForm">
      <input type="hidden" id="edit_request_id" name="request_id" value="">
      <div class="form-group">
        <label>Student:</label>
        <input type="text" id="edit_student_name" disabled style="background: #f0f0f0;">
      </div>
      <div class="form-group">
        <label>Date:</label>
        <input type="text" id="edit_date" disabled style="background: #f0f0f0;">
      </div>
      <div class="form-group">
        <label for="edit_time_in">Time In:</label>
        <input type="time" id="edit_time_in" name="time_in" required>
      </div>
      <div class="form-group">
        <label for="edit_time_out">Time Out:</label>
        <input type="time" id="edit_time_out" name="time_out" required>
      </div>
      <div class="form-group">
        <label>Reason:</label>
        <textarea id="edit_reason" disabled style="background: #f0f0f0;" rows="3"></textarea>
      </div>
      <button type="submit" class="submit-btn">Save Changes</button>
      <button type="button" class="back-btn" onclick="hideEditManualTimeModal()">Cancel</button>
    </form>
  </div>
</div>

<script>
    // Function to load and display supervisor announcements
    async function loadSupervisorAnnouncements() {
      const announcementsContainer = document.getElementById('supervisorAnnouncementsContainer');
      if (!announcementsContainer) return;

      announcementsContainer.innerHTML = '<p>Loading announcements...</p>';

      try {
        const response = await fetch('fetch_announcements.php?audience=supervisor'); // Use the generic fetch_announcements.php
        const data = await response.json();

        if (data.success) {
          if (data.announcements.length > 0) {
            let html = '<ul'; // Wrap in ul as per .kard-content .info-list
            data.announcements.forEach(announcement => {
              html += `
                <li class="info-item">
                  <div class="info-label">${escapeHtml(announcement.title)}</div>
                  <div class="info-value">${escapeHtml(announcement.content)}</div>
                  <div class="muted"><strong>Posted by:</strong> ${escapeHtml(announcement.faculty_name)}</div>
                  <div class="muted"><strong>Date:</strong> ${new Date(announcement.date_posted).toLocaleString()}</div>
                </li>
              `;
            });
            html += '</ul>';
            announcementsContainer.innerHTML = html;
          } else {
            announcementsContainer.innerHTML = '<p>No announcements for supervisors at this time.</p>';
          }
        } else {
          announcementsContainer.innerHTML = `<p>Error loading announcements: ${data.message || 'Unknown error'}</p>`;
        }
      } catch (error) {
        console.error('Error fetching supervisor announcements:', error);
        announcementsContainer.innerHTML = '<p>Error loading announcements.</p>';
      }
    }

    // Function to load and display "Most Interns in Progress"
    async function loadMostInternsInProgress() {
      const container = document.getElementById('mostInternsInProgress');
      if (!container) return;

      container.innerHTML = '<p class="no-data">Loading...</p>';

      try {
        const response = await fetch('fetch_dashboard_data.php?action=most_interns_in_progress');
        const data = await response.json();

        if (data.success) {
          if (data.interns && data.interns.length > 0) {
            let html = '<div class="interns-tile-grid">';
            data.interns.forEach(intern => {
              const profilePic = intern.profile_picture && intern.profile_picture !== '' ? intern.profile_picture : 'uploads/dp.jpg';
              html += `
                <div class="intern-tile">
                  <img src="${escapeHtml(profilePic)}" alt="Profile" class="tile-profile-pic">
                  <div class="tile-info">
                    <div class="tile-name">${escapeHtml(intern.student_name)}</div>
                    <div class="tile-progress">${escapeHtml(intern.overall_average)}%</div>
                    <div class="tile-company">${escapeHtml(intern.companyname || 'N/A')}</div>
                  </div>
                </div>
              `;
            });
            html += '</div>';
            container.innerHTML = html;
          } else {
            container.innerHTML = '<p class="no-data">No interns in progress found.</p>';
          }
        } else {
          container.innerHTML = `<p class="no-data">Error: ${data.message || 'Unknown error'}</p>`;
        }
      } catch (error) {
        console.error('Error fetching most interns in progress:', error);
        container.innerHTML = '<p class="no-data">Error loading data.</p>';
      }
    }

    // Function to load and display "Newest Submitted Tasks"
    async function loadNewestSubmittedTasks() {
      const container = document.getElementById('newestSubmittedTasks');
      if (!container) return;

      container.innerHTML = '<p class="no-data">Loading...</p>';

      try {
        const response = await fetch('fetch_dashboard_data.php?action=newest_submitted_tasks');
        const data = await response.json();

        if (data.success) {
          if (data.tasks && data.tasks.length > 0) {
            let html = '<ul>';
            data.tasks.forEach(task => {
              html += `
                <li>
                  <span>${escapeHtml(task.student_name)}</span>
                  <span>${escapeHtml(task.task_title)} - ${new Date(task.submission_date).toLocaleDateString()}</span>
                </li>
              `;
            });
            html += '</ul>';
            container.innerHTML = html;
          } else {
            container.innerHTML = '<p class="no-data">No newest submitted tasks found.</p>';
          }
        } else {
          container.innerHTML = `<p class="no-data">Error: ${data.message || 'Unknown error'}</p>`;
        }
      } catch (error) {
        console.error('Error fetching newest submitted tasks:', error);
        container.innerHTML = '<p class="no-data">Error loading data.</p>';
      }
    }

    // Function to load and display "Newest Attendance Needing Validation"
    async function loadNewestAttendanceValidation() {
      const container = document.getElementById('newestAttendanceValidation');
      if (!container) return;

      container.innerHTML = '<p class="no-data">Loading...</p>';

      try {
        const response = await fetch('fetch_dashboard_data.php?action=newest_attendance_validation');
        const data = await response.json();

        if (data.success) {
          if (data.attendance && data.attendance.length > 0) {
            let html = '<ul>';
            data.attendance.forEach(record => {
              const timeIn = record.time_in ? new Date(record.time_in).toLocaleTimeString() : 'N/A';
              const timeOut = record.time_out ? new Date(record.time_out).toLocaleTimeString() : 'N/A';
              html += `
                <li>
                  <span>${escapeHtml(record.student_name)}</span>
                  <span>${timeIn} - ${timeOut} (${escapeHtml(record.status)})</span>
                </li>
              `;
            });
            html += '</ul>';
            container.innerHTML = html;
          } else {
            container.innerHTML = '<p class="no-data">No attendance needing validation found.</p>';
          }
        } else {
          container.innerHTML = `<p class="no-data">Error: ${data.message || 'Unknown error'}</p>`;
        }
      } catch (error) {
        console.error('Error fetching newest attendance validation:', error);
        container.innerHTML = '<p class="no-data">Error loading data.</p>';
      }
    }

    // Override showTab to include dashboard data loading for mainContent
    const originalShowTab = showTab;
    showTab = function(tabId) {
      originalShowTab(tabId);
      if (tabId === 'mainContent') {
        loadSupervisorAnnouncements();
        loadMostInternsInProgress();
        loadNewestSubmittedTasks();
        loadNewestAttendanceValidation();
      }
      if (tabId === 'manualTimeRequestsContent') {
        loadManualTimeRequests();
      }
    };

    // Initial load if mainContent is active by default
    document.addEventListener('DOMContentLoaded', () => {
      if (document.getElementById('mainContent').classList.contains('active')) {
        loadSupervisorAnnouncements();
        loadMostInternsInProgress();
        loadNewestSubmittedTasks();
        loadNewestAttendanceValidation();
      }
    });

function showTab(tabId) {
  document.querySelectorAll('.main-content, .profile, .tab-content').forEach(c => c.classList.remove('active'));
  const tab = document.getElementById(tabId);
  if (tab) {
    tab.classList.add('active');
  }

  if (tabId === "messageContent") {
    // Clean up any existing messaging state
    if (typeof cleanupMessaging === 'function') {
      cleanupMessaging();
    }

    // Initialize messaging with improved functionality
    setTimeout(() => {
      if (typeof initializeMessaging === 'function') {
        initializeMessaging();
      } else {
        // Fallback to original messaging functions
        loadConversations();
      }
    }, 100);
  } else {
    // Clean up messaging when leaving the tab
    if (typeof cleanupMessaging === 'function') {
      cleanupMessaging();
    }
  }
}

function filterAttendanceStudents() {
  const searchInput = document.getElementById('internAttendanceSearch').value.toLowerCase();
  const studentCards = document.querySelectorAll('#studentAttendanceGrid .company-card');

  studentCards.forEach(card => {
    const name = card.dataset.name;
    const studentid = card.dataset.studentid;

    const searchMatch = name.includes(searchInput) || studentid.includes(searchInput);

    if (searchMatch) {
      card.style.display = '';
    } else {
      card.style.display = 'none';
    }
  });
}

function showStudentAttendanceDetails(studentId) {
  const modal = document.getElementById('studentAttendanceModal');
  const modalBody = document.getElementById('studentAttendanceModalBody');
  modalBody.innerHTML = '<p>Loading attendance details...</p>';
  modal.style.display = 'flex';

  fetch(`fetch_student_attendance.php?student_id=${studentId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        let html = `
          <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
            <img src="${data.profile_picture}" alt="Student Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;">
            <div>
              <h4>${data.student_name}</h4>
              <p>${data.email}</p>
              <p>${data.post_name}</p>
            </div>
          </div>
          <div class="attendance-tracker">
            <div class="calendar-section">
              <div class="calendar-header">
                <button id="prevMonthBtn" onclick="changeMonth(-1, ${studentId})"><</button>
                <h3 id="monthYearText"></h3>
                <button id="nextMonthBtn" onclick="changeMonth(1, ${studentId})">></button>
              </div>
              <div id="attendanceCalendar"></div>
            </div>
            <div class="log-section">
              <h3>Logs for <span id="selectedDateText">[Select a date]</span></h3>
              <div id="logsContainer" class="modern-logs-container">
                <p class="no-logs-message">Select a date to view logs.</p>
              </div>
            </div>
          </div>
        `;
        modalBody.innerHTML = html;
        initializeCalendar(studentId, new Date());
      } else {
        modalBody.innerHTML = `<p>${data.message || 'Error loading attendance details'}</p>`;
      }
    })
    .catch(error => {
      console.error('Error fetching attendance details:', error);
      modalBody.innerHTML = '<p>Error loading attendance details</p>';
    });
}

let currentCalendarDate = new Date();

function changeMonth(offset, studentId) {
    currentCalendarDate.setMonth(currentCalendarDate.getMonth() + offset);
    initializeCalendar(studentId, currentCalendarDate);
}

function initializeCalendar(studentId, date) {
    const calendarContainer = document.getElementById("attendanceCalendar");
    const monthYearText = document.getElementById("monthYearText");
    
    const year = date.getFullYear();
    const month = date.getMonth();

    monthYearText.textContent = date.toLocaleString('default', { month: 'long', year: 'numeric' });

    fetch(`fetch_student_attendance.php?student_id=${studentId}&month=${month + 1}&year=${year}`)
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                renderCalendar(year, month, data.logs);
            }
        });
}

function renderCalendar(year, month, logs) {
    const calendarContainer = document.getElementById("attendanceCalendar");
    const firstDay = new Date(year, month, 1).getDay();
    const lastDate = new Date(year, month + 1, 0).getDate();

    let html = "<table><thead><tr>";
    const days = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];
    days.forEach(d => html += `<th>${d}</th>`);
    html += "</tr></thead><tbody><tr>";

    for (let i = 0; i < firstDay; i++) {
      html += "<td></td>";
    }

    for (let d = 1; d <= lastDate; d++) {
      const fullDate = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const logData = logs.find(log => log.date === fullDate);
      let cellClass = '';
      if (logData) {
          cellClass = 'day-with-log';
      }

      html += `<td class="${cellClass}" data-date="${fullDate}">${d}</td>`;
      if ((firstDay + d) % 7 === 0) html += "</tr><tr>";
    }

    html += "</tr></tbody></table>";
    calendarContainer.innerHTML = html;

    calendarContainer.querySelectorAll("td[data-date]").forEach(td => {
      td.addEventListener("click", () => {
        calendarContainer.querySelectorAll("td.selected").forEach(x => x.classList.remove("selected"));
        td.classList.add("selected");
        displayLogsForDate(td.dataset.date, logs);
      });
    });
}

function displayLogsForDate(date, allLogs) {
    const selectedDateText = document.getElementById("selectedDateText");
    const logsContainer = document.getElementById('logsContainer');
    selectedDateText.textContent = new Date(date + 'T00:00:00').toLocaleDateString([], { year: 'numeric', month: 'long', day: 'numeric' });
    
    const logsForDate = allLogs.filter(log => log.date === date);
    logsContainer.innerHTML = '';

    if (logsForDate.length === 0) {
        logsContainer.innerHTML = `<p class="no-logs-message">No logs found for this date.</p>`;
    } else {
        logsForDate.forEach(log => {
            const timeIn = log.time_in ? new Date(log.time_in).toLocaleTimeString() : 'N/A';
            const timeOut = log.time_out ? new Date(log.time_out).toLocaleTimeString() : 'N/A';
            const selfieIn = log.time_in_selfie ? `<img src="${log.time_in_selfie}" alt="Time-in Selfie" class="log-selfie" onclick="showImageModal('${log.time_in_selfie}')" style="cursor: pointer;">` : '<div class="no-selfie-placeholder">No Selfie</div>';
            const selfieOut = log.time_out_selfie ? `<img src="${log.time_out_selfie}" alt="Time-out Selfie" class="log-selfie" onclick="showImageModal('${log.time_out_selfie}')" style="cursor: pointer;">` : '<div class="no-selfie-placeholder">No Selfie</div>';
            
            let validationButton = '';
            if (log.status === 'Validated') {
                validationButton = '<span class="validated-status">Validated</span>';
            } else {
                validationButton = `<button class="validate-btn" onclick="validateTimecard(${log.timecard_id}, this)">Validate</button>`;
            }

            const logCard = `
              <div class="log-card" id="timecard-${log.timecard_id}">
                <div class="log-time-section">
                  <strong>Time In</strong>
                  <p>${timeIn}</p>
                  ${selfieIn}
                </div>
                <div class="log-time-section">
                  <strong>Time Out</strong>
                  <p>${timeOut}</p>
                  ${selfieOut}
                </div>
                <div class="log-validation-section">
                  ${validationButton}
                </div>
              </div>
            `;
            logsContainer.innerHTML += logCard;
        });
    }
}

function validateTimecard(timecardId, buttonElement) {
    buttonElement.disabled = true;
    buttonElement.textContent = 'Validating...';

    fetch('validate_timecard.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `timecard_id=${timecardId}`
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            buttonElement.outerHTML = '<span class="validated-status">Validated</span>';
        } else {
            alert('Failed to validate: ' + data.message);
            buttonElement.disabled = false;
            buttonElement.textContent = 'Validate';
        }
    })
    .catch(error => {
        console.error('Validation error:', error);
        alert('An error occurred during validation.');
        buttonElement.disabled = false;
        buttonElement.textContent = 'Validate';
    });
}

function openFeedbackModal(studentId, studentName) {
  document.getElementById('feedbackStudentId').value = studentId;
  document.getElementById('feedbackStudentName').textContent = studentName;
  document.getElementById('feedbackModal').style.display = 'flex';
}

function closeFeedbackModal() {
  document.getElementById('feedbackModal').style.display = 'none';
  document.getElementById('feedbackForm').reset(); // Clear form fields
}

function showImageModal(src) {
  document.getElementById('modalImage').src = src;
  document.getElementById('imageModal').style.display = 'flex';
}

function closeImageModal() {
  document.getElementById('imageModal').style.display = 'none';
}

const currentUserId = <?php echo json_encode((int)$userId); ?>;

// Utility functions (moved to top for scope)
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function getTimeAgo(dateString) {
  const now = new Date();
  const date = new Date(dateString);
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);
  
  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;
  return date.toLocaleDateString();
}

function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

function showError(message) {
  console.error(message);
  alert(message);
}

// Improved messaging functionality
var currentConversation = null; // Changed from let to var
var messageRefreshInterval = null; // Changed from let to var
var conversationRefreshInterval = null; // Changed from let to var
var isSendingMessage = false; // Changed from let to var

// Initialize messaging when tab is shown
function initializeMessaging() {
  loadConversations();
  startAutoRefresh();
  setupEventListeners();
}

// Setup event listeners
function setupEventListeners() {
  const sendBtn = document.getElementById('sendBtn');
  const messageInput = document.getElementById('messageText');
  const newChatBtn = document.getElementById('newChatBtn');
  const searchInput = document.getElementById('userSearchInput');

  if (sendBtn) {
    sendBtn.addEventListener('click', sendMessage);
  }

  if (messageInput) {
    messageInput.addEventListener('keypress', (e) => {
      if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        sendMessage();
      }
    });
  }

  if (newChatBtn) {
    newChatBtn.addEventListener('click', showNewChatModal);
  }

  if (searchInput) {
    searchInput.addEventListener('input', debounce(searchUsers, 300));
  }
}

// Load conversations with unread count
function loadConversations() {
  fetch('fetch_messages.php?action=conversations&user_type=supervisor')
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        displayConversations(data.conversations);
      } else {
        showError('Error loading conversations');
      }
    })
    .catch(() => showError('Error loading conversations'));
}

// Display conversations with unread indicators

function displayConversations(conversations) {
  const container = document.getElementById('conversations');
  if (!container) return;
  
  container.innerHTML = '';

  if (conversations.length === 0) {
    container.innerHTML = '<p class="no-conversations">No conversations yet. <button onclick="showNewChatModal()" class="start-chat-btn">Start a chat</button></p>';
    return;
  }

  conversations.forEach(conv => {
    const div = document.createElement('div');
    div.className = 'conversation-item';
    if (conv.unread_count && conv.unread_count > 0) {
      div.classList.add('unread');
    }
    
    const timeAgo = conv.last_time ? getTimeAgo(conv.last_time) : '';
    const unreadBadge = (conv.unread_count && conv.unread_count > 0) ? `<span class="unread-badge">${conv.unread_count}</span>` : '';
    
    div.innerHTML = `
      <div class="conversation-header">
        <strong class="contact-name">${escapeHtml(conv.name)}</strong>
        <span class="conversation-time">${timeAgo}</span>
        ${unreadBadge}
      </div>
      <div class="last-message">${escapeHtml(conv.last_message || 'No messages')}</div>
    `;
    
    div.onclick = () => selectConversation(conv.other_type, conv.other_id, conv.name);
    container.appendChild(div);
  });
}

// Select conversation and load messages
function selectConversation(other_type, other_id, name) {
  currentConversation = { other_type, other_id, name };
  const chatTitle = document.getElementById('chat-title');
  if (chatTitle) {
    chatTitle.textContent = `Chat with ${name}`;
  }
  
  // Update UI to show selected conversation
  document.querySelectorAll('.conversation-item').forEach(item => {
    item.classList.remove('active');
  });
  
  // Enable message input
  const messageInput = document.getElementById('messageText');
  const sendBtn = document.getElementById('sendBtn');
  if (messageInput) messageInput.disabled = false;
  if (sendBtn) sendBtn.disabled = false;
  
  loadMessages();
  showChatArea();
}

// Load messages for current conversation
function loadMessages() {
  if (!currentConversation) return;
  
  const { other_type, other_id } = currentConversation;
  console.log('Loading messages for conversation:', other_type, other_id);
  fetch(`fetch_messages.php?action=messages&other_type=${other_type}&other_id=${other_id}&user_type=supervisor`)
    .then(res => {
      if (!res.ok) {
        console.error('Network response was not ok:', res.statusText);
        throw new Error('Network response was not ok');
      }
      return res.json();
    })
    .then(data => {
      console.log('Messages fetch response:', data);
      if (data.success) {
        displayMessages(data.messages);
        // Refresh conversations to update unread count
        loadConversations();
      } else {
        showError('Error loading messages: ' + (data.message || 'Unknown error'));
      }
    })
    .catch(error => {
      console.error('Error loading messages:', error);
      showError('Error loading messages');
    });
}

// Display messages with better formatting
function displayMessages(messages) {
  console.log('Displaying messages:', messages);
  const container = document.getElementById('messages');
  container.innerHTML = '';
  
  if (messages.length === 0) {
    container.innerHTML = '<div class="no-messages">No messages yet. Start the conversation!</div>';
    return;
  }
  
  messages.forEach(msg => {
    const div = document.createElement('div');
    // Determine if the message is sent by current user
    const isSent = msg.sender_id === currentUserId;
    div.className = `message ${isSent ? 'sent' : 'received'}`;
    
    const time = new Date(msg.sent_at).toLocaleString();
    const readStatus = isSent ? (msg.is_read ? '✓✓' : '✓') : '';
    
    div.innerHTML = `
      <div class="message-content">${escapeHtml(msg.message)}</div>
      <div class="message-meta">
        <span class="message-time">${time}</span>
        <span class="read-status">${readStatus}</span>
      </div>
    `;
    
    container.appendChild(div);
  });
  
  // Scroll to bottom
  container.scrollTop = container.scrollHeight;
}

// Send message with better error handling
function sendMessage() {
  const input = document.getElementById('messageText');
  const message = input.value.trim();
  
  if (!message || !currentConversation) {
    return;
  }
  
  if (message.length > 500) {
    showError('Message too long. Maximum 500 characters.');
    return;
  }
  
  if (isSendingMessage) {
    // Prevent duplicate sends
    return;
  }
  isSendingMessage = true;
  
  const { other_type, other_id } = currentConversation;
  const formData = new FormData();
  formData.append('other_type', other_type);
  formData.append('other_id', other_id);
  formData.append('message', message);
  formData.append('sender_type', 'supervisor');
  
  // Disable send button temporarily
  const sendBtn = document.getElementById('sendBtn');
  sendBtn.disabled = true;
  sendBtn.textContent = 'Sending...';
  
  fetch('send_message.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        input.value = '';
        loadMessages();
        loadConversations();
      } else {
        showError('Error: ' + data.message);
      }
    })
    .catch(() => showError('Error sending message'))
    .finally(() => {
      sendBtn.disabled = false;
      sendBtn.textContent = 'Send';
      isSendingMessage = false;
    });
}

// Show new chat modal
function showNewChatModal() {
  const modal = document.getElementById('newChatModal');
  if (modal) {
    modal.style.display = 'block';
    document.getElementById('userSearchInput').focus();
  }
}

// Hide new chat modal
function hideNewChatModal() {
  const modal = document.getElementById('newChatModal');
  if (modal) {
    modal.style.display = 'none';
    document.getElementById('userSearchInput').value = '';
    document.getElementById('searchResults').innerHTML = '';
  }
}

// Search users for new conversation
function searchUsers() {
  const query = document.getElementById('userSearchInput').value.trim();
  if (query.length < 2) {
    document.getElementById('searchResults').innerHTML = '';
    return;
  }
  
  fetch(`fetch_messages.php?action=search_users&query=${encodeURIComponent(query)}&user_type=supervisor`)
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        displaySearchResults(data.users);
      }
    })
    .catch(() => showError('Error searching users'));
}

// Display search results
function displaySearchResults(users) {
  const container = document.getElementById('searchResults');
  container.innerHTML = '';
  
  if (users.length === 0) {
    container.innerHTML = '<div class="no-results">No users found</div>';
    return;
  }
  
  users.forEach(user => {
    const div = document.createElement('div');
    div.className = 'search-result-item';
    div.innerHTML = `
      <span class="user-name">${escapeHtml(user.name)}</span>
      <span class="user-type">${user.type}</span>
    `;
    div.onclick = () => startNewConversation(user.type, user.id, user.name);
    container.appendChild(div);
  });
}

// Start new conversation
function startNewConversation(type, id, name) {
  hideNewChatModal();
  selectConversation(type, id, name);
}

// Auto-refresh functionality
function startAutoRefresh() {
  // Refresh conversations every 30 seconds
  conversationRefreshInterval = setInterval(loadConversations, 30000);
  
  // Refresh messages every 10 seconds if a conversation is selected
  messageRefreshInterval = setInterval(() => {
    if (currentConversation) {
      loadMessages();
    }
  }, 10000);
}

function stopAutoRefresh() {
  if (conversationRefreshInterval) {
    clearInterval(conversationRefreshInterval);
  }
  if (messageRefreshInterval) {
    clearInterval(messageRefreshInterval);
  }
}

// Show chat area (for mobile responsiveness)
function showChatArea() {
  const chatArea = document.querySelector('.chat-area');
  const conversationsList = document.querySelector('.conversations-list');
  
  if (window.innerWidth <= 768) {
    conversationsList.classList.add('mobile-hidden');
    chatArea.classList.remove('mobile-hidden');
  }
}

// Show conversations list (for mobile)
function showConversationsList() {
  const chatArea = document.querySelector('.chat-area');
  const conversationsList = document.querySelector('.conversations-list');
  
  if (window.innerWidth <= 768) {
    conversationsList.classList.remove('mobile-hidden');
    chatArea.classList.add('mobile-hidden');
  }
}

// Utility functions
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function getTimeAgo(dateString) {
  const now = new Date();
  const date = new Date(dateString);
  const diffMs = now - date;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);
  
  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;
  return date.toLocaleDateString();
}

function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

function showError(message) {
  console.error(message);
  alert(message);
}

// Clean up when leaving messaging tab
function cleanupMessaging() {
  stopAutoRefresh();
  currentConversation = null;
}

function showInternDetails(cardElement) {
  const intern = JSON.parse(cardElement.dataset.intern);
  const modal = document.getElementById('internDetailsModal');
  const body = document.getElementById('internDetailsBody');
  
  // Basic HTML sanitation
  // The escapeHtml function is now defined globally at the top of the script.
  body.innerHTML = `
    <div style="text-align: center;">
      <img src="${escapeHtml(intern.profile_picture)}" alt="Profile Picture" style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin-bottom: 15px;">
      <h3>${escapeHtml(intern.firstname)} ${escapeHtml(intern.lastname)}</h3>
    </div>
    <p><strong>Student ID:</strong> ${escapeHtml(intern.studentid || 'N/A')}</p>
    <p><strong>Email:</strong> ${escapeHtml(intern.email)}</p>
    <p><strong>Contact:</strong> ${escapeHtml(intern.contact || 'N/A')}</p>
    <p><strong>Post:</strong> ${escapeHtml(intern.post_name || 'N/A')}</p>
    <p><a href="#" class="view-resume-btn" onclick='showResumeModal(${JSON.stringify(intern)})'>View Resume</a></p>
  `;
  
  modal.style.display = 'block';
}

function closeInternDetailsModal() {
  const modal = document.getElementById('internDetailsModal');
  modal.style.display = 'none';
}

function showResumeModal(intern) {
  const modal = document.getElementById('resumeModal');
  const body = document.getElementById('resumeBody');
  body.innerHTML = '<p>Loading resume...</p>';
  modal.style.display = 'block';

  // Fetch full resume
  fetch(`fetch_full_resume.php?student_id=${intern.student_id}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        let html = `
          <div class="resume-header">
            <img src="${intern.profile_picture}" alt="Profile Picture">
            <h3>${intern.firstname} ${intern.lastname}</h3>
            <p>${intern.email} | ${intern.contact || 'N/A'}</p>
          </div>
        `;
        html += '<h4>Resume Details</h4>';
        html += `<p><strong>Objective:</strong> ${data.data.objective || 'N/A'}</p>`;

        if (data.data.education && data.data.education.length > 0) {
          html += '<h5>Education</h5>';
          data.data.education.forEach(edu => {
            html += `<p><strong>${edu.school_name}</strong> (${edu.start_year} - ${edu.end_year})<br>${edu.description}</p>`;
          });
        }

        if (data.data.skills && data.data.skills.length > 0) {
          html += '<h5>Skills</h5>';
          html += '<ul>';
          data.data.skills.forEach(skill => {
            html += `<li>${skill.skill_name} - <em>${skill.proficiency}</em></li>`;
          });
          html += '</ul>';
        }

        if (data.data.experience && data.data.experience.length > 0) {
          html += '<h5>Work Experience</h5>';
          data.data.experience.forEach(exp => {
            html += `<p><strong>${exp.position}</strong> at ${exp.company_name} (${exp.start_date} - ${exp.end_date})<br>${exp.responsibilities}</p>`;
          });
        }

        if (data.data.certifications && data.data.certifications.length > 0) {
          html += '<h5>Certifications</h5>';
          data.data.certifications.forEach(cert => {
            html += `<p><strong>${cert.title}</strong> - ${cert.issuer} (${cert.date_obtained})<br>${cert.description}</p>`;
          });
        }

        body.innerHTML = html;
      } else {
        body.innerHTML = `<p>${data.message || 'Error loading resume'}</p>`;
      }
    })
    .catch(error => {
      console.error('Error fetching resume:', error);
      body.innerHTML = '<p>Error loading resume</p>';
    });
}

function closeResumeModal() {
  const modal = document.getElementById('resumeModal');
  modal.style.display = 'none';
}

function searchInterns() {
  const input = document.getElementById('internSearch');
  const filter = input.value.toLowerCase();
  const grid = document.querySelector('#internOverviewContent .interns-grid');
  const cards = grid.getElementsByClassName('company-card');

  for (let i = 0; i < cards.length; i++) {
    const card = cards[i];
    const name = card.dataset.name || '';
    const studentid = card.dataset.studentid || '';

    if (name.includes(filter) || studentid.includes(filter)) {
      card.style.display = "";
    } else {
      card.style.display = "none";
    }
  }
}

function searchPerformanceInterns() {
  const input = document.getElementById('performanceSearch');
  const filter = input.value.toLowerCase();
  const grid = document.querySelector('#internPerformanceContent .interns-grid');
  const cards = grid.getElementsByClassName('company-card');

  for (let i = 0; i < cards.length; i++) {
    const card = cards[i];
    const name = card.dataset.name || '';
    const studentid = card.dataset.studentid || '';

    if (name.includes(filter) || studentid.includes(filter)) {
      card.style.display = "";
    } else {
      card.style.display = "none";
    }
  }
}

/* Manual Time Request Modal */
function showManualTimeRequestModal() {
  document.getElementById('manualTimeRequestModal').style.display = 'flex';
  loadManualTimeRequests();
}

function hideManualTimeRequestModal() {
  document.getElementById('manualTimeRequestModal').style.display = 'none';
  document.getElementById('manualTimeRequestForm').reset();
}

function loadManualTimeRequests() {
  const container = document.getElementById('manualTimeRequestsContainer');
  if (!container) return;

  container.innerHTML = '<p>Loading manual time requests...</p>';

  fetch('fetch_manual_time_requests.php')
    .then(response => {
      if (!response.ok) {
        throw new Error('Network response was not ok');
      }
      return response.json();
    })
    .then(data => {
      if (!data.success) {
        container.innerHTML = `<p style="color: red;">Error: ${data.message || 'Failed to load requests'}</p>`;
        return;
      }

      if (data.requests.length === 0) {
        container.innerHTML = '<p>No manual time requests found.</p>';
        return;
      }

      let html = '<div style="max-height: 400px; overflow-y: auto;">';
      data.requests.forEach(request => {
        const statusColor = request.status === 'approved' ? 'green' : 
                           request.status === 'rejected' ? 'red' : 'orange';
        
        html += `
          <div style="border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 8px; background: #f9f9f9;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 10px;">
              <strong>${request.student_name}</strong>
              <span style="background: ${statusColor}; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.85em;">
                ${request.status.toUpperCase()}
              </span>
            </div>
            <p style="margin: 5px 0;"><strong>Date:</strong> ${new Date(request.date).toLocaleDateString()}</p>
            ${request.time_in ? `<p style="margin: 5px 0;"><strong>Time In:</strong> ${request.time_in}</p>` : ''}
            ${request.time_out ? `<p style="margin: 5px 0;"><strong>Time Out:</strong> ${request.time_out}</p>` : ''}
            <p style="margin: 5px 0;"><strong>Reason:</strong> ${escapeHtml(request.reason)}</p>
            <p style="margin: 5px 0; color: #666; font-size: 0.9em;">
              <strong>Requested:</strong> ${new Date(request.created_at).toLocaleString()}
            </p>
            ${request.admin_notes ? `<p style="margin: 5px 0; color: #666;"><strong>Admin Notes:</strong> ${escapeHtml(request.admin_notes)}</p>` : ''}
            ${request.status === 'pending' ? `
              <button onclick="openEditManualTimeModal(${request.id})" style="background-color: #116530; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer; margin-top: 10px;">
                Edit Request
              </button>
            ` : ''}
          </div>
        `;
      });
      html += '</div>';
      
      container.innerHTML = html;
    })
    .catch(error => {
      console.error('Error loading manual time requests:', error);
      container.innerHTML = `<p style="color: red;">Error loading requests: ${error.message}</p>`;
    });
}

/* Edit Manual Time Request functions */
function openEditManualTimeModal(requestId) {
    fetch('get_request_details.php?id=' + requestId)
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.text();
        })
        .then(text => {
            console.log('Raw response:', text);
            if (!text || text.trim() === '') {
                throw new Error('Empty response received from server');
            }
            
            // Try to parse JSON
            try {
                return JSON.parse(text);
            } catch (e) {
                console.error('JSON parse error:', e);
                console.error('Response text:', text);
                throw new Error('Invalid JSON response: ' + text.substring(0, 100));
            }
        })
        .then(data => {
            console.log('Parsed data:', data);
            if (data.success) {
                const request = data.request;
                document.getElementById('edit_request_id').value = request.id;
                document.getElementById('edit_student_name').value = request.student_name;
                document.getElementById('edit_date').value = new Date(request.date + 'T00:00:00').toLocaleDateString();
                document.getElementById('edit_time_in').value = request.time_in || '';
                document.getElementById('edit_time_out').value = request.time_out || '';
                document.getElementById('edit_reason').value = request.reason;
                
                document.getElementById('editManualTimeModal').style.display = 'flex';
            } else {
                Swal.fire('Error', data.message || 'Failed to load request details', 'error');
            }
        })
        .catch(error => {
            console.error('Full error details:', error);
            Swal.fire({
                icon: 'error',
                title: 'Error Loading Request',
                text: error.message || 'Failed to load request details',
                footer: 'Check browser console for more details'
            });
        });
}

function hideEditManualTimeModal() {
  document.getElementById('editManualTimeModal').style.display = 'none';
  document.getElementById('editManualTimeForm').reset();
}

// Task management functions
let currentStudentId = null;

function assignTask(studentId) {
  showTab('assignTasksContent');
  const studentSelect = document.getElementById('student_id');
  if (studentSelect) {
    studentSelect.value = studentId;
  }
}

function viewTasks(studentId) {
  currentStudentId = studentId;
  showTab('viewTasksContent');
  loadTasks(studentId);
}

function loadTasks(studentId) {
  const container = document.getElementById('tasksListContainer');
  if (!container) return;

  container.innerHTML = '<p>Loading tasks...</p>';

  fetch(`fetch_supervisor_intern_tasks.php?student_id=${studentId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displayTasks(data.tasks);
      } else {
        container.innerHTML = `<p>Error: ${data.message || 'Failed to load tasks'}</p>`;
      }
    })
    .catch(error => {
      console.error('Error loading tasks:', error);
      container.innerHTML = '<p>Error loading tasks</p>';
    });
}

function displayTasks(tasks) {
  const container = document.getElementById('tasksListContainer');
  if (!container) return;

  if (tasks.length === 0) {
    container.innerHTML = '<p>No tasks found for this student.</p>';
    return;
  }

  let html = '<div class="tasks-list">';
  tasks.forEach(task => {
    const statusClass = task.status.toLowerCase();
    const dueDate = task.due_date ? new Date(task.due_date).toLocaleDateString() : 'No due date';
    const assignedAt = new Date(task.assigned_at).toLocaleDateString();

    html += `
      <div class="task-item ${statusClass}" data-task-id="${task.task_id}">
        <div class="task-header">
          <h4>${escapeHtml(task.task_description.split('\n')[0])}</h4>
          <span class="task-status ${statusClass}">${task.status}</span>
        </div>
        <div class="task-details">
          <p><strong>Assigned:</strong> ${assignedAt}</p>
          <p><strong>Due:</strong> ${dueDate}</p>
          ${task.submitted_at ? `<p><strong>Submitted:</strong> ${new Date(task.submitted_at).toLocaleDateString()}</p>` : ''}
        </div>
        ${task.task_description.includes('\n') ? `<div class="task-description">${escapeHtml(task.task_description.replace(/\n/g, '<br>'))}</div>` : ''}
      </div>
    `;
  });
  html += '</div>';

  container.innerHTML = html;
}

function filterTasks(status) {
  const tasks = document.querySelectorAll('.task-item');
  const filterBtns = document.querySelectorAll('.task-filter-btn');

  // Update active button
  filterBtns.forEach(btn => btn.classList.remove('active'));
  event.target.classList.add('active');

  tasks.forEach(task => {
    const taskStatus = task.querySelector('.task-status').textContent.toLowerCase();
    if (status === 'all' || taskStatus === status) {
      task.style.display = '';
    } else {
      task.style.display = 'none';
    }
  });
}

// Add event listener for edit form submission
document.addEventListener('DOMContentLoaded', () => {
  // ...existing code...

  // Edit Manual Time Request Form
  const editManualTimeForm = document.getElementById('editManualTimeForm');
  if (editManualTimeForm) {
    editManualTimeForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);

      const submitBtn = this.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Saving...';

      fetch('update_manual_time_request.php', {
        method: 'POST',
        body: formData
      })
      .then(res => {
        if (!res.ok) {
          throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
      })
      .then(data => {
        if (data.success) {
          Swal.fire('Success', 'Time request updated successfully!', 'success').then(() => {
            hideEditManualTimeModal();
            loadManualTimeRequests();
          });
        } else {
          Swal.fire('Error', data.message || 'Failed to update request.', 'error');
        }
      })
      .catch(error => {
        console.error('Error updating request:', error);
        Swal.fire('Error', 'An error occurred while updating the request: ' + error.message, 'error');
      })
      .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Save Changes';
      });
    });
  }

  // Assign Task Form
  const assignTaskForm = document.getElementById('assignTaskForm');
  if (assignTaskForm) {
    assignTaskForm.addEventListener('submit', function(e) {
      e.preventDefault();
      const formData = new FormData(this);

      const submitBtn = this.querySelector('button[type="submit"]');
      submitBtn.disabled = true;
      submitBtn.textContent = 'Assigning...';

      fetch('assign_task.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          Swal.fire('Success', 'Task assigned successfully!', 'success').then(() => {
            assignTaskForm.reset();
            showTab('internPerformanceContent');
          });
        } else {
          Swal.fire('Error', data.message || 'Failed to assign task.', 'error');
        }
      })
      .catch(error => {
        console.error('Error assigning task:', error);
        Swal.fire('Error', 'An error occurred while assigning the task.', 'error');
      })
      .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Assign Task';
      });
    });
  }
});
  </script>
</body>
</html>

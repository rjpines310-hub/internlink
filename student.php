<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: login.php");
    exit();
}

include 'db.php';
$userId = $_SESSION['user_id'];
$firstname = '';
$lastname = '';
$profile_picture = 'uploads/dp.jpg'; // default
$employment_status = 'pending'; // Initialize employment_status

// Fetch student details
$student = [
    'studentid' => '',
    'firstname' => '',
    'lastname' => '',
    'section' => '',
    'email' => '',
    'contact' => '',
    'password' => '',
    'employment_status' => 'pending',
    'hr_id' => null // Initialize hr_id
];

$stmt = $conn->prepare("SELECT studentid, firstname, lastname, section, email, contact, profile_picture, employment_status, hr_id FROM student WHERE student_id = ?");
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $userId);
if ($stmt->execute() === false) {
    die('Execute failed: ' . htmlspecialchars($stmt->error));
}
$result = $stmt->get_result();
if ($row = $result->fetch_assoc()) {
    $student = array_merge($student, $row);
    $firstname = $student['firstname'];
    $lastname = $student['lastname'];
    $profile_picture = $student['profile_picture'] ?? 'uploads/dp.jpg';
    $employment_status = $student['employment_status'];
} else {
    // Student not found, redirect to login
    header("Location: login.php");
    exit();
}
$stmt->close();

// Fetch feedback for the student
$feedback_entries = [];
$feedback_query = "
    SELECT 
        of.feedback_message, of.given_by, of.submitted_at,
        f.firstname as faculty_firstname, f.lastname as faculty_lastname,
        s.firstname as supervisor_firstname, s.lastname as supervisor_lastname
    FROM ojt_feedback of
    LEFT JOIN faculty f ON of.faculty_id = f.faculty_id
    LEFT JOIN supervisor s ON of.supervisor_id = s.supervisor_id
    WHERE of.student_id = ?
    ORDER BY of.submitted_at DESC
";
$feedback_stmt = $conn->prepare($feedback_query);
if ($feedback_stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$feedback_stmt->bind_param("i", $userId);
if ($feedback_stmt->execute() === false) {
    die('Execute failed: ' . htmlspecialchars($feedback_stmt->error));
}
$feedback_result = $feedback_stmt->get_result();
while ($row = $feedback_result->fetch_assoc()) {
    $feedback_entries[] = $row;
}
$feedback_stmt->close();

// Fetch all post_ids the student has applied to
$appliedPostIds = [];
$stmt = $conn->prepare("SELECT post_id FROM intern_applications WHERE student_id = ?");
if ($stmt === false) {
    die('Prepare failed: ' . htmlspecialchars($conn->error));
}
$stmt->bind_param("i", $userId);
if ($stmt->execute() === false) {
    die('Execute failed: ' . htmlspecialchars($stmt->error));
}
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $appliedPostIds[] = $row['post_id'];
}
$stmt->close();

// 2. Top Companies with Most Internship Posts
$topCompaniesPosts = [];
$queryTopPosts = "SELECT ch.companyname, COUNT(ip.post_id) AS post_count
                  FROM internship_posts ip
                  JOIN companyhr ch ON ip.hr_id = ch.hr_id
                  GROUP BY ch.companyname
                  ORDER BY post_count DESC
                  LIMIT 3";
$resultTopPosts = $conn->query($queryTopPosts);
if ($resultTopPosts) {
    while ($row = $resultTopPosts->fetch_assoc()) {
        $topCompaniesPosts[] = $row;
    }
}

// 3. 2-3 Newest Internship Posts
$newestInternshipPosts = [];
$queryNewestPosts = "SELECT ip.post_id, ip.internship_title, ch.companyname, ip.date_posted
                     FROM internship_posts ip
                     JOIN companyhr ch ON ip.hr_id = ch.hr_id
                     WHERE ip.status = 'Active'
                     ORDER BY ip.date_posted DESC
                     LIMIT 3";
$resultNewestPosts = $conn->query($queryNewestPosts);
if ($resultNewestPosts) {
    while ($row = $resultNewestPosts->fetch_assoc()) {
        $newestInternshipPosts[] = $row;
    }
}

// 1. Top Companies with Most OJT Students
$topCompaniesMostOJTStudents = [];
$queryTopOJT = "SELECT ch.companyname, COUNT(ia.student_id) AS total_students, ch.email, ch.profile_picture
                FROM intern_applications ia
                JOIN internship_posts ip ON ia.post_id = ip.post_id
                JOIN companyhr ch ON ip.hr_id = ch.hr_id
                WHERE ia.status='Accepted'
                GROUP BY ch.companyname, ch.email, ch.profile_picture
                ORDER BY total_students DESC LIMIT 3";
$resultTopOJT = $conn->query($queryTopOJT);
if ($resultTopOJT) {
    while ($row = $resultTopOJT->fetch_assoc()) {
        $topCompaniesMostOJTStudents[] = $row;
    }
}



// Fetch active internship posts and build cards
$internshipCards = '';
$query = "SELECT post_id, internship_title, companyname, location, internship_description, allowance, date_posted, email 
          FROM internship_posts 
          WHERE status = 'Active' ORDER BY date_posted DESC";

if ($result = $conn->query($query)) {
    while ($row = $result->fetch_assoc()) {
        $postId = intval($row['post_id']);
        $isApplied = in_array($postId, $appliedPostIds);
        $buttonText = $isApplied ? "Already applied" : "Apply";
        $buttonAttrs = $isApplied ? "disabled class='apply-btn applied'" : "class='apply-btn'";
        
        $internshipCards .= '
        <div class="internship-card">
          <div class="internship-card-header">
            <h3>' . htmlspecialchars($row['internship_title']) . '</h3>
            <div class="company-badge">' . htmlspecialchars($row['companyname']) . '</div>
          </div>
          <div class="job-details">
            <div class="job-info-grid">
              <div class="job-info-item">
                <div class="job-info-label">Location</div>
                <div class="job-info-value">' . htmlspecialchars($row['location']) . '</div>
              </div>
              <div class="job-info-item job-contact">
                <div class="job-info-label">Contact</div>
                <div class="job-info-value">
                  <a href="mailto:' . htmlspecialchars($row['email']) . '">' . htmlspecialchars($row['email']) . '</a>
                </div>
              </div>
            </div>
            
            <div class="job-description">
              <div class="job-info-label">Job Description</div>
              <div class="job-info-value">' . htmlspecialchars($row['internship_description']) . '</div>
            </div>
            
            <div class="job-meta">
              <div class="job-allowance">' . htmlspecialchars($row['allowance']) . '</div>
              <div class="job-date">' . htmlspecialchars(date("M j, Y", strtotime($row['date_posted']))) . '</div>
            </div>
            
            <button ' . $buttonAttrs . ' data-postid="' . $postId . '" onclick="applyInternship(' . $postId . ')">' . $buttonText . '</button>
          </div>
        </div>
        ';
    }
    $result->free();
} else {
    $internshipCards = '<p>No active internship offers found.</p>';
}

// Note: Attendance calculation now handled by database triggers
// Fetch student overview data for display
$studentOverview = [
    'attendance' => 0,
    'performance' => 0,
    'file_submissions' => 0,
    'overall_average' => 0
];
$stmt = $conn->prepare("SELECT attendance, performance, file_submissions, overall_average FROM student_overview WHERE student_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result(); // Get the result set
    if ($row = $result->fetch_assoc()) { // Fetch the row
        $studentOverview = array_merge($studentOverview, $row); // Merge fetched data
        $overviewExists = true; // Set to true if a row was found
    }
    $stmt->close();
}
// Determine the target performance based on employment status
$targetPerformance = null;
if ($employment_status === 'pending') {
    $targetPerformance = '0';
} elseif ($employment_status === 'hired') {
    $targetPerformance = '100';
}

// Apply conditional performance logic and update/insert into database
if ($targetPerformance !== null) {
    if ($overviewExists) {
        // Only update if the current performance in DB is different from the target
        if ($studentOverview['performance'] !== $targetPerformance) {
            $updateStmt = $conn->prepare("UPDATE student_overview SET performance = ? WHERE student_id = ?");
            if ($updateStmt) {
                $updateStmt->bind_param("si", $targetPerformance, $userId);
                $updateStmt->execute();
                $updateStmt->close();
                $studentOverview['performance'] = $targetPerformance; // Update in-memory
            } else {
                error_log("Error preparing performance update statement: " . $conn->error);
            }
        }
    } else {
        // No record exists, insert a new one
        $insertStmt = $conn->prepare("INSERT INTO student_overview (student_id, attendance, performance, file_submissions, overall_average) VALUES (?, ?, ?, ?, ?)");
        if ($insertStmt) {
            $defaultAttendance = '0';
            $defaultFileSubmissions = '0';
            $defaultOverallAverage = '0.00'; // DECIMAL(5,2)
            $insertStmt->bind_param("isssd", $userId, $defaultAttendance, $targetPerformance, $defaultFileSubmissions, $defaultOverallAverage);
            $insertStmt->execute();
            $insertStmt->close();
            $studentOverview['performance'] = $targetPerformance; // Update in-memory
            $studentOverview['attendance'] = $defaultAttendance;
            $studentOverview['file_submissions'] = $defaultFileSubmissions;
            $studentOverview['overall_average'] = $defaultOverallAverage;
        } else {
            error_log("Error preparing performance insert statement: " . $conn->error);
        }
    }
}

$progressPercent = $studentOverview['attendance']; // Attendance percentage from student_overview
$performanceScore = $studentOverview['performance']; // Performance percentage from student_overview
$fileProgressPercent = $studentOverview['file_submissions']; // File submissions percentage from student_overview

// Fetch task counts for the summary (still needed for task summary display)
$taskCounts = [
    'missed' => 0,
    'submitted' => 0,
    'completed' => 0,
    'all' => 0
];

$stmt = $conn->prepare("
    SELECT 
        SUM(CASE WHEN status = 'Missed' THEN 1 ELSE 0 END) as missed,
        SUM(CASE WHEN status = 'Submitted' THEN 1 ELSE 0 END) as submitted,
        SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
        COUNT(*) as all_tasks
    FROM tasks 
    WHERE student_id = ?
");
if ($stmt) {
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $taskCounts['missed'] = $row['missed'] ?? 0;
        $taskCounts['submitted'] = $row['submitted'] ?? 0;
        $taskCounts['completed'] = $row['completed'] ?? 0;
        $taskCounts['all'] = $row['all_tasks'] ?? 0;
    }
    $stmt->close();
}

// Fetch total attendance hours and target hours for display in Attendance Tracker
$totalHours = 0;
$targetHours = 200; // Default fallback
$stmt = $conn->prepare("SELECT time_in, time_out FROM timecard WHERE student_id = ? AND status = 'Validated'");
$stmt->bind_param("i", $userId);
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

if (!empty($student['section'])) {
    $stmt = $conn->prepare("SELECT ojt_hours FROM sections WHERE section_name = ?");
    if ($stmt) {
        $stmt->bind_param("s", $student['section']);
        $stmt->execute();
        $stmt->bind_result($ojtHours);
        if ($stmt->fetch() && $ojtHours > 0) {
            $targetHours = $ojtHours;
        }
        $stmt->close();
    } else {
        error_log("Error preparing statement to fetch ojt_hours: " . $conn->error);
    }
}

// Calculate approved files for display in File Submissions
$approvedFiles = 0;
$totalFiles = 4; // DTR, MOA, LOA, Evaluation
$stmt = $conn->prepare("SELECT dtr_file_checked, moa_file_checked, letter_of_acceptance_file_checked, evaluation_form_file_checked FROM student_file_submissions WHERE student_id = ?");
if ($stmt) {
    $stmt->bind_param("i", $userId);
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

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Student Dashboard | Universidad De Manila</title>
  <link rel="icon" href="logo.png" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="student.css" />
  <link rel="stylesheet" href="dashboard.css" />
  <link rel="stylesheet" href="announcement.css" />
  <link rel="stylesheet" href="http://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="http://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <style>
    #imageModal {
        z-index: 1003; /* Higher than the attendance modal */
    }
    #imageModal .modal-content {
        background: transparent;
        box-shadow: none;
        border: none;
        width: auto;
        height: auto;
    }
    .log-selfie {
        width: 60px;
        height: 60px;
        border-radius: 8px;
        object-fit: cover;
        margin-top: 8px;
        cursor: pointer;
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
    .completion-banner {
      background-color: #d4edda; /* Light green */
      color: #155724; /* Dark green text */
      padding: 15px 20px;
      margin-bottom: 20px;
      border-radius: 8px;
      text-align: center;
      font-size: 1.1rem;
      font-weight: bold;
      border: 1px solid #c3e6cb;
      box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    .completion-banner p {
      margin: 0;
    }
  </style>
</head>
<body>
<script>

    // Function to load and display student announcements
    async function loadStudentAnnouncements() {
      const announcementsContainer = document.getElementById('studentAnnouncementsContainer');
      if (!announcementsContainer) return;

      announcementsContainer.innerHTML = '<p>Loading announcements...</p>';

      try {
        const response = await fetch('fetch_announcements.php?audience=student'); // Create this file
        const data = await response.json();

        if (data.success) {
          if (data.announcements.length > 0) {
            let html = '';
            data.announcements.forEach(announcement => {
              html += `
                <div class="announcement-display-card">
                  <h4>${escapeHtml(announcement.title)}</h4>
                  <p>${escapeHtml(announcement.content)}</p>
                  <p class="muted"><strong>Posted by:</strong> ${escapeHtml(announcement.faculty_name)} on ${new Date(announcement.date_posted).toLocaleString()}</p>
                </div>
              `;
            });
            announcementsContainer.innerHTML = html;
          } else {
            announcementsContainer.innerHTML = '<p class="no-data">No announcements for students at this time.</p>';
          }
        } else {
          announcementsContainer.innerHTML = `<p class="no-data">Error loading announcements: ${data.message || 'Unknown error'}</p>`;
        }
      } catch (error) {
        console.error('Error fetching student announcements:', error);
        announcementsContainer.innerHTML = '<p class="no-data">Error loading announcements.</p>';
      }
    }

// Core UI functions that need to be globally available immediately
function hideAllTabsExcept(keepId) {
  const allContents = [
    "mainContent",
    "profileContent",
    "jobOffersContent",
    "myOffersContent",
    "logAttendanceContent",
    "performanceContent",
    "taskcontent",
    "summaryTasksContent",
    "fileSubmissionsContent",
    "myApplicationsContent",
    "AttendanceTrackerContent",
    "messageContent",
    "resumeContent",
    "feedbackContent"
  ];
  allContents.forEach((id) => {
    const el = document.getElementById(id);
    if (el) {
      if (id === keepId) {
        el.classList.add("active");
      } else {
        el.classList.remove("active");
      }
    }
  });
}

function showProfile() {
  hideAllTabsExcept("profileContent");
}

function goHome() {
  hideAllTabsExcept("mainContent");
  loadStudentAnnouncements();
  loadTopCompaniesPosts();
  loadNewestInternshipPosts();
  loadTopCompaniesMostOJTStudents(); // New call
}

// Toggle sidebar for mobile
function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  const hamburgerBtn = document.querySelector('.hamburger-btn');

  sidebar.classList.toggle('mobile-open');
  document.body.classList.toggle('sidebar-open');
  hamburgerBtn.classList.toggle('active');

  // Prevent body scroll when sidebar is open on mobile
  if (sidebar.classList.contains('mobile-open')) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
}

// Close sidebar when clicking on sidebar links (mobile)
function closeSidebarOnLinkClick() {
  const sidebarLinks = document.querySelectorAll('.sidebar a');
  sidebarLinks.forEach(link => {
    link.addEventListener('click', () => {
      // Only close sidebar on mobile (when mobile-open class exists)
      const sidebar = document.querySelector('.sidebar');
      if (sidebar.classList.contains('mobile-open')) {
        toggleSidebar();
      }
    });
  });
}

// Load file submissions status when the tab is shown
function showTab(tabId) {
  hideAllTabsExcept(tabId);

  const tab = document.getElementById(tabId);
  if (!tab) return;

  // Restricted tabs for pending students
  const restrictedTabs = ["logAttendanceContent", "performanceContent", "taskcontent", "fileSubmissionsContent"];

  // Remove any previous warning messages first
  const prevWarning = tab.querySelector('.pending-warning');
  if (prevWarning) prevWarning.remove();

  if (employmentStatus === "pending" && restrictedTabs.includes(tabId)) {
    // Clear the content and show only the warning
    tab.innerHTML = `
      <h2>${tab.querySelector('h2') ? tab.querySelector('h2').textContent : ''}</h2>
      <p class="pending-warning" style="color: red; font-weight: bold; margin-top: 15px;">
        You must apply first as an intern to access this section.
      </p>
    `;
    return;
  } else if (employmentStatus === "completed") {
    // Disable OJT-related actions for completed students
    const ojtActionElements = document.querySelectorAll(
      '#logAttendanceContent .time-btn, ' +
      '#logAttendanceContent .submit-btn, ' +
      '#fileSubmissionsContent .attach-btn, ' +
      '#fileSubmissionsContent .submit-btn, ' +
      '#fileSubmissionsContent .change-file-btn, ' +
      '#taskcontent .mark-done-btn, ' +
      '#taskcontent .attachments-section button, ' +
      '#taskcontent .comments-section button'
    );
    ojtActionElements.forEach(el => {
      el.disabled = true;
      el.style.opacity = '0.6';
      el.style.cursor = 'not-allowed';
    });

    // Hide "Job Offers" and "My Offers" for completed students
    const jobOffersLink = document.querySelector('.sidebar a[onclick*="jobOffersContent"]');
    const myOffersLink = document.querySelector('.sidebar a[onclick*="myOffersContent"]');
    if (jobOffersLink) jobOffersLink.style.display = 'none';
    if (myOffersLink) myOffersLink.style.display = 'none';
  }

  // Load file submissions when accessing the file submissions tab
  if (tabId === "fileSubmissionsContent") {
    loadFileSubmissions();
  }

  // Initialize performance calendar when accessing the task tab
  if (tabId === "taskcontent") {
    initializePerformanceCalendar();
  }

  // If user is hired, do nothing – leave the HTML as it is in your page
  if (tabId === "myApplicationsContent") {
    loadMyApplications();
  }

  if (tabId === "myOffersContent") {
    console.log("Loading My Offers tab, calling loadMyOffers()");
    loadMyOffers();
  }

  if (tabId === "AttendanceTrackerContent") {
    initializeAttendanceTracker();
  }

  // Initialize map when showing log attendance tab
  if (tabId === "logAttendanceContent") {
    initializeMap();
    loadGeofence();
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

    // Function to load and display student announcements
    async function loadStudentAnnouncements() {
      const announcementsContainer = document.getElementById('studentAnnouncementsContainer');
      if (!announcementsContainer) return;

      announcementsContainer.innerHTML = '<p>Loading announcements...</p>';

      try {
        const response = await fetch('fetch_announcements.php?audience=student'); // Create this file
        const data = await response.json();

        if (data.success) {
          if (data.announcements.length > 0) {
            let html = '';
            data.announcements.forEach(announcement => {
              html += `
                <div class="announcement-display-card">
                  <h4>${escapeHtml(announcement.title)}</h4>
                  <p>${escapeHtml(announcement.content)}</p>
                  <p class="muted"><strong>Posted by:</strong> ${escapeHtml(announcement.faculty_name)} on ${new Date(announcement.date_posted).toLocaleString()}</p>
                </div>
              `;
            });
            announcementsContainer.innerHTML = html;
          } else {
            announcementsContainer.innerHTML = '<p class="no-data">No announcements for students at this time.</p>';
          }
        } else {
          announcementsContainer.innerHTML = `<p class="no-data">Error loading announcements: ${data.message || 'Unknown error'}</p>`;
        }
      } catch (error) {
        console.error('Error fetching student announcements:', error);
        announcementsContainer.innerHTML = '<p class="no-data">Error loading announcements.</p>';
      }
    }

    // Initial load if mainContent is active by default
    document.addEventListener('DOMContentLoaded', () => {
      if (document.getElementById('mainContent').classList.contains('active')) {
        loadStudentAnnouncements();
        loadTopCompaniesPosts();
        loadNewestInternshipPosts();
        loadTopCompaniesMostOJTStudents(); // New call
      }
    });

    // Function to load and display top companies with most internship posts
    async function loadTopCompaniesPosts() {
      const container = document.getElementById('topCompaniesPosts');
      if (!container) return;
      container.innerHTML = '<p>Loading...</p>';
      try {
        const response = await fetch('fetch_dashboard_data.php?action=top_companies_posts&role=student');
        const data = await response.json();
        if (data.success && data.companies.length > 0) {
          let html = '<div class="card-grid">';
          data.companies.forEach(company => {
            const logo = company.profile_picture ? `uploads/${company.profile_picture}` : 'uploads/dp.jpg'; // Assuming profile_picture is the correct field
            html += `
              <div class="company-card">
                <img src="${escapeHtml(logo)}" alt="${escapeHtml(company.companyname)} Logo" class="profile-logo">
                <h4 class="card-title">${escapeHtml(company.companyname)}</h4>
                <p class="card-summary">Total Posts: ${company.total_posts}</p>
                <button class="view-details-btn" onclick="viewCompanyDetails('${escapeHtml(company.companyname)}')">View Details</button>
              </div>
            `;
          });
          html += '</div>';
          container.innerHTML = html;
        } else {
          container.innerHTML = `<p class="no-data">${data.message || 'No data available.'}</p>`;
          console.warn('No real data for top companies (posts). Message:', data.message || 'Unknown error');
        }
      } catch (error) {
        console.error('Error fetching top companies (posts):', error);
        container.innerHTML = `<p class="no-data">Error loading data: ${error.message}</p>`;
      }
    }

    // Function to load and display newest internship posts
    async function loadNewestInternshipPosts() {
      const container = document.getElementById('newestInternshipPosts');
      if (!container) return;
      container.innerHTML = '<p>Loading...</p>';
      try {
        const response = await fetch('fetch_dashboard_data.php?action=newest_internship_posts&role=student');
        const data = await response.json();
        if (data.success && data.posts.length > 0) {
          let html = '<div class="card-grid">';
          data.posts.forEach(post => {
            html += `
              <div class="company-card">
                <h4 class="card-title">${escapeHtml(post.internship_title)}</h4>
                <p class="card-summary">Company: ${escapeHtml(post.companyname)}</p>
                <p class="card-summary">Posted: ${new Date(post.date_posted).toLocaleDateString()}</p>
                <button class="view-details-btn" onclick="viewInternshipPost(${post.post_id})">View Details</button>
              </div>
            `;
          });
          html += '</div>';
          container.innerHTML = html;
        } else {
          container.innerHTML = `<p class="no-data">${data.message || 'No new posts.'}</p>`;
          console.warn('No real data for newest internship posts. Message:', data.message || 'Unknown error');
        }
      } catch (error) {
        console.error('Error fetching newest internship posts:', error);
        container.innerHTML = `<p class="no-data">Error loading data: ${error.message}</p>`;
      }
    }

    // Placeholder for viewCompanyDetails function
    function viewCompanyDetails(companyName) {
      Swal.fire('Company Details', `Viewing details for company: ${companyName}`, 'info');
      // In a real application, this would navigate to a company profile page
    }

    // Placeholder for viewInternshipPost function (to be implemented or linked to existing)
    function viewInternshipPost(postId) {
      // This function should navigate to a page or modal displaying full post details
      // For now, we can just log it or show an alert
      Swal.fire('Internship Post', `Viewing details for Post ID: ${postId}`, 'info');
      // Or, if you have a dedicated page:
      // window.location.href = `view_internship_post.php?post_id=${postId}`;
    }

    // Function to load and display top companies with most OJT students
    async function loadTopCompaniesMostOJTStudents() {
      const container = document.getElementById('topCompaniesMostOJTStudents');
      if (!container) return;
      container.innerHTML = '<p>Loading...</p>';
      try {
        const response = await fetch('fetch_dashboard_data.php?action=top_companies_ojt_students&role=student');
        const data = await response.json();
        if (data.success && data.companies.length > 0) {
          let html = '<div class="card-grid">';
          data.companies.forEach(company => {
            const logo = company.profile_picture ? `uploads/${company.profile_picture}` : 'uploads/dp.jpg';
            html += `
              <div class="company-card">
                <img src="${escapeHtml(logo)}" alt="${escapeHtml(company.companyname)} Logo" class="profile-logo">
                <h4 class="card-title">${escapeHtml(company.companyname)}</h4>
                <p class="card-summary">Total OJT Students: ${company.total_students}</p>
                <button class="view-details-btn" onclick="viewCompanyDetails('${escapeHtml(company.companyname)}')">View Details</button>
              </div>
            `;
          });
          html += '</div>';
          container.innerHTML = html;
        } else {
          container.innerHTML = `<p class="no-data">${data.message || 'No data available.'}</p>`;
          console.warn('No data for top companies with most OJT students. Message:', data.message || 'Unknown error');
        }
      } catch (error) {
        console.error('Error fetching top companies with most OJT students:', error);
        container.innerHTML = `<p class="no-data">Error loading data: ${error.message}</p>`;
      }
    }
</script>
<header>
  <div class="header-left">
    <!-- Hamburger Menu Button for Mobile -->
    <button class="hamburger-btn" id="hamburgerBtn">
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
        <a href="#" onclick="showTab('resumeContent')">My Resume</a>
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
      <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" />
      <div class="overlay">Change Profile</div>
    </div>
  </label>
  <input type="file" id="profileInput" name="profile_picture" accept="image/*" capture="user" onchange="document.getElementById('uploadForm').submit();" />
</form>
      <div class="student-name"><?php echo htmlspecialchars($firstname . ' ' . $lastname); ?></div>
      <a href="#" onclick="showTab('jobOffersContent')">Job Offers</a>
      <a href="#" onclick="showTab('logAttendanceContent')">Log Attendance</a>
      <a href="#" onclick="showTab('performanceContent')">Performance</a>
      <a href="#" onclick="showTab('fileSubmissionsContent')">File Submissions</a>
      <a href="#" onclick="showTab('messageContent')">Message</a>
      <?php if ($employment_status === 'completed'): ?>
        <a href="#" onclick="showTab('feedbackContent')">View Feedback</a>
      <?php endif; ?>
    </div>

    <div class="main-content active" id="mainContent">
      <?php if ($employment_status === 'completed'): ?>
        <div class="completion-banner">
          <p>🎉 Your OJT is now complete!</p>
          <p>Congratulations on successfully finishing your internship.</p>
        </div>
      <?php endif; ?>
      <h2 class="welcome-message">Welcome, <?php echo htmlspecialchars($firstname . ' ' . $lastname); ?>!</h2>
    
      <div class="dashboard-cards-grid">
        <!-- Student announcements will be loaded here -->
        <div class="kard announcement-kard">
          <div class="kard-header">
            <h3 class="kard-title">Announcements</h3>
          </div>
          <div id="studentAnnouncementsContainer" class="kard-content">Loading announcements...</div>
        </div>

        <!-- Top Companies with Most OJT Students -->
        <div class="kard">
          <div class="kard-header">
            <h3 class="kard-title">Top Companies with Most OJT Students</h3>
          </div>
          <div id="topCompaniesMostOJTStudents" class="kard-content info-list">
            <p>Loading...</p>
          </div>
        </div>

        <!-- Top Companies with Most Internship Posts -->
        <div class="kard">
          <div class="kard-header">
            <h3 class="kard-title">Top Companies with Most Internship Posts</h3>
          </div>
          <div id="topCompaniesPosts" class="kard-content info-list">
            <p>Loading...</p>
          </div>
        </div>

        <!-- Newest Internship Posts -->
        <div class="kard">
          <div class="kard-header">
            <h3 class="kard-title">Newest Internship Posts</h3>
          </div>
          <div id="newestInternshipPosts" class="kard-content info-list">
            <p>Loading...</p>
          </div>
        </div>
      </div>
    </div>

    <div class="profile" id="profileContent">
      <h2>Edit Profile</h2>
      <form id="profileForm">
        <?php foreach (['studentid','firstname','lastname','section','email','contact'] as $field): ?>
        <div class="form-row">
          <label><?= ucfirst($field) ?></label>
          <input type="text" name="<?= $field ?>" id="<?= $field ?>" value="<?= htmlspecialchars($student[$field]) ?>" disabled />
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
      <hr style="margin: 20px 0;" />
      
      <button class="back-btn" onclick="goHome()">Back</button>
    </div>

    <div class="tab-content" id="jobOffersContent">
      <h2>Job Offers</h2>
      <div class="job-offers-controls">
        <div class="job-offers-toolbar">
          <div class="toolbar-left">
            <input
              type="text"
              id="searchInput"
              placeholder="Search job offers..."
              onkeyup="filterInternships()"
            />
          </div>
          <div class="toolbar-right">
            <div class="job-stats">
              <div class="stat-item">
                <span class="stat-number" id="totalJobs">0</span>
                <span>Available</span>
              </div>
            </div>
            <button type="button" class="view-applications-btn" onclick="showTab('myApplicationsContent'); loadMyApplications();">
              View My Applications
            </button>
            <button type="button" class="view-applications-btn" onclick="showTab('myOffersContent'); loadMyOffers();">
              My Offers
            </button>
          </div>
        </div>
      </div>
      <div class="internship-cards-container" id="internshipCardsContainer">
        <?php echo $internshipCards; ?>
      </div>
    </div>

    <div class="tab-content" id="myOffersContent">
      <h2>My Offers</h2>
      <div class="internship-cards-container" id="myOffersContainer">
        <!-- Offers will be loaded here by JavaScript -->
      </div>
    </div>

    <div class="tab-content" id="logAttendanceContent">
<div class="attendance-container">
  <div class="attendance-header">
      <h2>Log Attendance</h2>
  </div>

  <div class="attendance-wrapper">
    <!-- LEFT SIDE: MAP + CAMERA -->
    <div class="attendance-left">
      <!-- Leaflet Map -->
      <div id="map" style="height: 300px; border-radius: 12px;"></div>

      <!-- Geofence Radius Display -->
      <div id="geofenceInfo" style="margin-top: 10px; text-align: center; font-weight: bold; color: #116530;">
        Geofence Radius: <span id="geofenceRadius">Not set</span> meters
      </div>

      <!-- Selfie is handled via modal on Time In/Out -->

   </br>
      <button type="button" class="submit-btn" onclick="showTab('AttendanceTrackerContent');">
Attendance Tracker
      </button>
    </div>

    <!-- RIGHT SIDE: CLOCK + BUTTONS + DETAILS -->
<!-- Clock + Time In/Time Out -->
<div class="attendance-right">
  <div class="clock-box">
    <canvas id="analogClock" width="250" height="250"></canvas>
    <div id="digitalTime" style="text-align:center; margin-top:10px; font-weight:bold;"></div>
  </div>

  <div class="btn-box">
    <button type="button" class="time-btn timein">TIME IN</button>
    <button type="button" class="time-btn timeout">TIME OUT</button>
  </div>

  <div class="log-details">
    <h4 style="margin-bottom: 15px;">Today's Logs</h4>
    <div id="logDetailsContainer" style="display: flex; flex-direction: column; gap: 15px;">
      <!-- Dynamic content will be injected here -->
    </div>
  </div>

  <!-- Manual Time Request Section -->
  <div class="manual-time-request" style="margin-top: 20px; padding: 15px; border: 1px solid #ddd; border-radius: 8px; background: #f9f9f9;">
    <h4>Request Manual Time Adjustment</h4>
    <form id="manualTimeForm">
      <div style="margin-bottom: 10px;">
        <label for="requested_time_in">Requested Time In:</label>
        <input type="datetime-local" id="requested_time_in" name="requested_time_in" required>
      </div>
      <div style="margin-bottom: 10px;">
        <label for="requested_time_out">Requested Time Out:</label>
        <input type="datetime-local" id="requested_time_out" name="requested_time_out" required>
      </div>
      <div style="margin-bottom: 10px;">
        <label for="reason">Reason:</label>
        <textarea id="reason" name="reason" rows="3" required></textarea>
      </div>
      <button type="submit" class="submit-btn">Submit Request</button>
    </form>
  </div>
</div>

</div>
    </div>
    </div>
<div class="tab-content" id="performanceContent">
      <h2>Performance</h2>
      <style>
        .performance-summary-container {
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 40px;
          margin-bottom: 20px;
          flex-wrap: wrap;
        }
        .progress-circle-container {
          position: relative;
          width: 120px;
          height: 120px;
          display: flex;
          align-items: center;
          justify-content: center;
          overflow: visible;
        }
        .progress-ring {
          transform: rotate(-90deg);
        }
        .progress-ring__circle-bg {
            stroke: #e6e6e6;
        }
        .progress-ring__circle {
            stroke: #28a745;
            transition: stroke-dashoffset 0.5s ease;
        }
        .progress-text {
            position: absolute;
            font-size: 1.8rem;
            font-weight: bold;
            color: #116530;
        }
        .task-summary {
          display: flex;
          justify-content: space-around;
          gap: 15px;
          flex-wrap: wrap;
        }
        .summary-item {
          background: #f0f9f0;
          padding: 15px;
          border-radius: 8px;
          text-align: center;
          cursor: pointer;
          border: 1px solid #d4edda;
          width: 120px;
          transition: background-color: 0.3s;
        }
        .summary-item:hover {
          background: #e0f2e0;
        }
        .summary-item h4 {
          margin: 0 0 5px 0;
          color: #116530;
          font-size: 0.9rem;
        }
        .summary-item p {
          margin: 0;
          font-size: 1.5rem;
          font-weight: bold;
          color: #155724;
        }
      </style>
      <div class="performance-summary-container">
        <div class="progress-circle-container">
            <svg class="progress-ring" width="120" height="120">
                <circle class="progress-ring__circle-bg" stroke-width="10" fill="transparent" r="50" cx="60" cy="60"/>
                <circle id="performance-circle" class="progress-ring__circle" stroke-width="10" fill="transparent" r="50" cx="60" cy="60"/>
            </svg>
            <span class="progress-text"><?php echo round($performanceScore); ?>%</span>
        </div>
        <div class="task-summary">
            <div class="summary-item" data-status="missed">
              <h4>Missed</h4>
              <p><?php echo $taskCounts['missed']; ?></p>
            </div>
            <div class="summary-item" data-status="submitted">
              <h4>Submitted</h4>
              <p><?php echo $taskCounts['submitted']; ?></p>
            </div>
            <div class="summary-item" data-status="completed">
              <h4>Completed</h4>
              <p><?php echo $taskCounts['completed']; ?></p>
            </div>
            <div class="summary-item" data-status="all">
              <h4>All Tasks</h4>
              <p><?php echo $taskCounts['all']; ?></p>
            </div>
        </div>
      </div>
      <button onclick="showTab('taskcontent')" style="padding: 12px 24px; background: #116530; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem;">View Tasks</button>
    </div>

    <div class="tab-content" id="taskcontent">
      <style>
        #taskcontent {
          position: relative;
          max-height: 100vh;
          overflow: auto;
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
          flex: 1;
          display: flex;
          flex-direction: column;
          gap: 10px;
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
        .mark-done-btn {
          padding: 8px 16px;
          background: #28a745;
          color: white;
          border: none;
          border-radius: 4px;
          cursor: pointer;
          margin-top: 10px;
        }
        .mark-done-btn:hover {
          background: #218838;
        }
      </style>
      <div class="calendar-and-range-container">
        <h3 id="weekRange" style="color: #116530; text-align: center; margin-bottom: 20px; font-size: 1.5rem;">Week of [Date]</h3>
        <div class="performance-calendar-container">
          <table class="performance-calendar">
            <thead>
              <tr>
                <th></th>
                <th>Mon</th>
                <th>Tue</th>
                <th>Wed</th>
                <th>Thu</th>
                <th>Fri</th>
                <th>Sat</th>
                <th>Sun</th>
                <th></th>
              </tr>
            </thead>
            <tbody id="calendarBody">
              <!-- Calendar rows will be generated here -->
            </tbody>
          </table>
        </div>
      </div>
      <div id="selectedDateTasks" class="selected-date-tasks">
        <p>Select a date to view tasks</p>
      </div>
    </div>

    <div class="tab-content" id="summaryTasksContent">
      <style>
        /* Reusing styles from taskcontent for consistency */
        .tasks-list {
          flex: 1;
          display: flex;
          flex-direction: column;
          gap: 10px;
          margin-top: 20px;
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
      </style>
      <h2 id="summaryTasksTitle">Tasks Summary</h2>
      <button onclick="showTab('performanceContent')" style="padding: 12px 24px; background: #116530; color: white; border: none; border-radius: 8px; cursor: pointer; font-size: 1rem;">Back to Performance</button>
      <div id="summaryTasksList" class="tasks-list">
        <!-- Tasks will be loaded here -->
      </div>
    </div>

    <div class="tab-content" id="fileSubmissionsContent">
      <h2>File Submission</h2>
      <div class="file-submission-container">
        <!-- Progress Card -->
        <div class="file-card" id="progress-card">
            <h3>Progress</h3>
            <div class="file-progress-container">
                <svg class="file-progress-ring" width="120" height="120">
                    <circle class="file-progress-ring__circle-bg" stroke-width="10" fill="transparent" r="50" cx="60" cy="60"/>
                    <circle id="file-progress-circle" class="file-progress-ring__circle" stroke-width="10" fill="transparent" r="50" cx="60" cy="60"/>
                </svg>
                <span id="file-progress-text" class="file-progress-text"><?php echo round($fileProgressPercent); ?>%</span>
            </div>
        </div>
        <!-- DTR Card -->
        <div class="file-card" id="dtr-card">
          <h3>DTR</h3>
          <input type="file" id="dtr_file" name="dtr_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none" onchange="previewFile('dtr_file', 'dtr')" />
          <div class="file-preview" id="dtr-preview" style="display:none;">
            <span class="file-name" id="dtr-filename"></span>
            <button class="remove-file-btn" id="dtr-remove-btn" onclick="removeSelectedFile('dtr_file', 'dtr')">×</button>
          </div>
          <div class="status-container">
            <span id="dtr-approved-status" style="display:none; color: #116530; font-weight: bold;">Approved</span>
          </div>
          <button class="attach-btn" id="dtr-attach-btn" onclick="document.getElementById('dtr_file').click()">Attach File</button>
        
          <button class="download-btn" id="dtr-download-btn" onclick="downloadFile('DTR')" style="display:none;">Download</button>
          <button class="change-file-btn" id="dtr-change-btn" onclick="document.getElementById('dtr_file').click()" style="display:none;">Change File</button>
          <button class="submit-btn" id="dtr-submit-btn" onclick="submitFile('dtr_file', 'DTR')" style="display:none;">Submit</button>
          <div class="comments-section">
            <div class="comments-header" onclick="toggleComments('dtr')">Comments</div>
            <div class="comments-container" id="dtr-comments-container" style="display:none;">
              <div class="comments-list" id="dtr-comments-list"></div>
              <div class="comment-form">
                <textarea id="dtr-comment-text" placeholder="Add a comment..."></textarea>
                <button onclick="addComment('dtr')">Post</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Memorandum of Agreement Card -->
        <div class="file-card" id="moa-card">
          <h3>Memorandum of Agreement</h3>
          <input type="file" id="moa_file" name="moa_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none" onchange="previewFile('moa_file', 'moa')" />
          <div class="file-preview" id="moa-preview" style="display:none;">
            <span class="file-name" id="moa-filename"></span>
            <button class="remove-file-btn" id="moa-remove-btn" onclick="removeSelectedFile('moa_file', 'moa')">×</button>
          </div>
          <div class="status-container">
            <span id="moa-approved-status" style="display:none; color: #116530; font-weight: bold;">Approved</span>
          </div>
          <button class="attach-btn" id="moa-attach-btn" onclick="document.getElementById('moa_file').click()">Attach File</button>
          <button class="download-btn" id="moa-download-btn" onclick="downloadFile('MOA')" style="display:none;">Download</button>
          <button class="change-file-btn" id="moa-change-btn" onclick="document.getElementById('moa_file').click()" style="display:none;">Change File</button>
          <button class="submit-btn" id="moa-submit-btn" onclick="submitFile('moa_file', 'MOA')" style="display:none;">Submit</button>
          <div class="comments-section">
            <div class="comments-header" onclick="toggleComments('moa')">Comments</div>
            <div class="comments-container" id="moa-comments-container" style="display:none;">
              <div class="comments-list" id="moa-comments-list"></div>
              <div class="comment-form">
                <textarea id="moa-comment-text" placeholder="Add a comment..."></textarea>
                <button onclick="addComment('moa')">Post</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Letter of Acceptance Card -->
        <div class="file-card" id="loa-card">
          <h3>Letter of Acceptance</h3>
          <input type="file" id="loa_file" name="loa_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none" onchange="previewFile('loa_file', 'loa')" />
          <div class="file-preview" id="loa-preview" style="display:none;">
            <span class="file-name" id="loa-filename"></span>
            <button class="remove-file-btn" id="loa-remove-btn" onclick="removeSelectedFile('loa_file', 'loa')">×</button>
          </div>
          <div class="status-container">
            <span id="loa-approved-status" style="display:none; color: #116530; font-weight: bold;">Approved</span>
          </div>
          <button class="attach-btn" id="loa-attach-btn" onclick="document.getElementById('loa_file').click()">Attach File</button>
          <button class="download-btn" id="loa-download-btn" onclick="downloadFile('LOA')" style="display:none;">Download</button>
          <button class="change-file-btn" id="loa-change-btn" onclick="document.getElementById('loa_file').click()" style="display:none;">Change File</button>
          <button class="submit-btn" id="loa-submit-btn" onclick="submitFile('loa_file', 'LOA')" style="display:none;">Submit</button>
          <div class="comments-section">
            <div class="comments-header" onclick="toggleComments('loa')">Comments</div>
            <div class="comments-container" id="loa-comments-container" style="display:none;">
              <div class="comments-list" id="loa-comments-list"></div>
              <div class="comment-form">
                <textarea id="loa-comment-text" placeholder="Add a comment..."></textarea>
                <button onclick="addComment('loa')">Post</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Evaluation Form Card -->
        <div class="file-card" id="evaluation-card">
          <h3>Evaluation Form</h3>
          <input type="file" id="evaluation_file" name="evaluation_file" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="display:none" onchange="previewFile('evaluation_file', 'evaluation')" />
          <div class="file-preview" id="evaluation-preview" style="display:none;">
            <span class="file-name" id="evaluation-filename"></span>
            <button class="remove-file-btn" id="evaluation-remove-btn" onclick="removeSelectedFile('evaluation_file', 'evaluation')">×</button>
          </div>
          <div class="status-container">
            <span id="evaluation-approved-status" style="display:none; color: #116530; font-weight: bold;">Approved</span>
          </div>
          <button class="attach-btn" id="evaluation-attach-btn" onclick="document.getElementById('evaluation_file').click()">Attach File</button>
          <button class="download-btn" id="evaluation-download-btn" onclick="downloadFile('EVALUATION')" style="display:none;">Download</button>
          <button class="change-file-btn" id="evaluation-change-btn" onclick="document.getElementById('evaluation_file').click()" style="display:none;">Change File</button>
          <button class="submit-btn" id="evaluation-submit-btn" onclick="submitFile('evaluation_file', 'EVALUATION')" style="display:none;">Submit</button>
          <div class="comments-section">
            <div class="comments-header" onclick="toggleComments('evaluation')">Comments</div>
            <div class="comments-container" id="evaluation-comments-container" style="display:none;">
              <div class="comments-list" id="evaluation-comments-list"></div>
              <div class="comment-form">
                <textarea id="evaluation-comment-text" placeholder="Add a comment..."></textarea>
                <button onclick="addComment('evaluation')">Post</button>
              </div>
            </div>
          </div>
        </div>
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

    <!-- Feedback Display Tab -->
    <div class="tab-content" id="feedbackContent">
      <h2>Feedback Received</h2>
      <?php if (!empty($feedback_entries)): ?>
        <div class="feedback-list">
          <?php foreach ($feedback_entries as $feedback): ?>
            <div class="feedback-card">
              <div class="feedback-header">
                <h4>Feedback from 
                  <?php 
                    if ($feedback['given_by'] === 'faculty') {
                      echo htmlspecialchars($feedback['faculty_firstname'] . ' ' . $feedback['faculty_lastname']) . ' (Faculty)';
                    } elseif ($feedback['given_by'] === 'supervisor') {
                      echo htmlspecialchars($feedback['supervisor_firstname'] . ' ' . $feedback['supervisor_lastname']) . ' (Supervisor)';
                    }
                  ?>
                </h4>
                <span class="feedback-date"><?php echo htmlspecialchars(date("M j, Y H:i", strtotime($feedback['submitted_at']))); ?></span>
              </div>
              <div class="feedback-body">
                <p><?php echo nl2br(htmlspecialchars($feedback['feedback_message'])); ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p>No feedback has been submitted for you yet.</p>
      <?php endif; ?>
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
<div class="tab-content" id="resumeContent">
  <h2>My Resume</h2>
  <form id="resumeForm" onsubmit="return saveResume(event)">
    <div class="section">
      <label for="fullName">Full Name</label>
      <input type="text" id="fullName" name="fullName" value="<?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?>" readonly>
    </div>
    <div class="section">
      <label for="phoneNumber">Phone Number</label>
      <input type="text" id="phoneNumber" name="phoneNumber" value="<?php echo htmlspecialchars($student['contact']); ?>" readonly>
    </div>
    <div class="section">
      <label for="emailAddress">Email Address</label>
      <input type="email" id="emailAddress" name="emailAddress" value="<?php echo htmlspecialchars($student['email']); ?>" readonly>
    </div>
    <div class="section">
      <label for="objective">Career Objective / Summary</label>
      <textarea id="objective" name="objective" rows="3" placeholder="Write a brief career objective..."></textarea>
    </div>
    <!-- Education -->
    <div class="section">
      <h3>Education</h3>
      <div id="educationList"></div>
      <button type="button" id="addEducationBtn">+ Add Education</button>
    </div>
    <!-- Skills -->
    <div class="section">
      <h3>Skills</h3>
      <div id="skillsList"></div>
      <button type="button" id="addSkillBtn">+ Add Skill</button>
    </div>
    <!-- Work Experience -->
    <div class="section">
      <h3>Work Experience</h3>
      <div id="experienceList"></div>
      <button type="button" id="addExperienceBtn">+ Add Experience</button>
    </div>
    <!-- Certifications -->
    <div class="section">
      <h3>Certifications</h3>
      <div id="certificationsList"></div>
      <button type="button" id="addCertificationBtn">+ Add Certification</button>
    </div>
    <button type="submit" id="submitResumeBtn">Save Resume</button>
    <button type="button" onclick="viewResume()">View Summary</button>
    <button type="button" onclick="showTab('mainContent')">Cancel</button>

  </form>
</div>
<div class="tab-content" id="myApplicationsContent">
  <h2>My Applications</h2>
  <button class="view-applications-btn" type="button" onclick="showTab('jobOffersContent')">Back to Job Offers</button>

      <input
        type="text"
        id="applicationsSearchInput"
        placeholder="Your applications..."
        onkeyup="filterInternships()"
        style="width: 100%; max-width: 400px; padding: 8px; margin-bottom: 20px; border-radius: 6px; border: 1px solid #ccc; font-size: 1rem;"
      />
      <div class="internship-cards-container" id="myApplicationsContainer">
        <!-- Applications cards will be loaded here -->
      </div>
</div>
<div class="tab-content" id="AttendanceTrackerContent">
<div class="attendance-progress-container">
  <h3>Total Hours Logged</h3>
  <div class="progress-bar-wrapper">
    <div class="progress-bar-fill" id="attendanceProgress"
         style="width: <?php echo $progressPercent; ?>%;">
    </div>
    <span class="progress-bar-text"><?php echo round($progressPercent); ?>%</span>
  </div>
    <p id="attendanceText">Total Logged: <?php echo round($totalHours, 1); ?> / Required: <?php echo $targetHours; ?> hours</p>
  <div class="attendance-tracker">
  <div class="calendar-section">
    <div class="calendar-header">
      <button id="prevMonthBtn"><</button>
      <h3 id="monthYearText"></h3>
      <button id="nextMonthBtn">></button>
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

</div>


<button class="view-applications-btn" type="button" onclick="showTab('logAttendanceContent')">Back</button>
</div>

<!-- Interview Details Modal -->
<div id="interviewDetailsModal" class="modal">
  <div class="modal-content" style="max-width: 500px;">
    <div class="modal-header">
      <h3>Interview Details</h3>
      <button class="close-modal" onclick="closeInterviewDetailsModal()">&times;</button>
    </div>
    <div class="modal-body" id="interviewDetailsBody">
      <!-- Details will be loaded here -->
    </div>
  </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="modal" onclick="closeImageModal()">
  <div class="modal-content">
    <img id="modalImage" src="" alt="Enlarged Selfie">
  </div>
</div>

<!-- Selfie Modal -->
<div id="selfieModal" class="modal" style="display:none;">
  <div class="modal-content" style="max-width: 400px;">
    <div class="modal-header">
      <h3>Take Selfie for Verification</h3>
      <button class="close-modal" onclick="closeSelfieModal()">&times;</button>
    </div>
    <div class="modal-body" style="text-align: center;">
      <div id="cameraContainer">
        <video id="cameraFeed" width="300" height="225" autoplay style="border: 1px solid #ccc; border-radius: 8px;"></video>
      </div>
      <div id="previewContainer" style="display:none;">
        <img id="capturedImage" style="border: 1px solid #ccc; max-width: 300px; border-radius: 8px;">
      </div>
      <canvas id="captureCanvas" style="display:none;"></canvas>
      <br>
      <div id="modalButtons" style="margin-top: 10px;">
        <button id="captureBtn" class="submit-btn">Capture</button>
        <button id="retakeBtn" class="submit-btn" style="display:none;">Retake</button>
        <button id="submitBtn" class="submit-btn" style="display:none;">Submit</button>
      </div>
    </div>
  </div>
</div>
  </div>
<script>
  

// Pass PHP employment_status and userId to JS
const employmentStatus = "<?php echo $employment_status; ?>";
const currentUserId = <?php echo json_encode((int)$userId); ?>;
const hrId = <?php echo json_encode($student['hr_id'] ?? null); ?>;

// Geofence variables
let geofenceLocationId = null;
let geofenceLat = null;
let geofenceLng = null;
let geofenceRadius = null;
let map = null;
let studentMarker = null;
let studentLat = null;
let studentLng = null;

// Initialize Leaflet map
function initializeMap() {
  if (!map) {
    map = L.map('map').setView([14.5906, 120.9830], 13); // Default to Manila City Hall area
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© OpenStreetMap contributors'
    }).addTo(map);
  }
}

// Load active geofence
function loadGeofence() {
  if (!hrId) {
    Swal.fire({
      icon: 'warning',
      title: 'No Company Assigned',
      text: 'You are not assigned to any company yet. Please contact your faculty.',
      confirmButtonText: 'OK'
    });
    return;
  }

  fetch(`fetch_active_location.php?hr_id=${hrId}`)
    .then(res => res.json())
    .then(data => {
      if (data.success && data.location) {
        geofenceLocationId = data.location.location_id;
        geofenceLat = data.location.latitude;
        geofenceLng = data.location.longitude;
        geofenceRadius = data.location.radius;

        // Initialize map if not already done
        if (!map) {
          initializeMap();
        }

        // Clear existing layers (markers, circles) before adding new ones
        map.eachLayer(function (layer) {
          if (layer instanceof L.Marker || layer instanceof L.Circle) {
            map.removeLayer(layer);
          }
        });

        // Center map on geofence location
        map.setView([geofenceLat, geofenceLng], 15);

        // Add geofence circle
        L.circle([geofenceLat, geofenceLng], {
          color: 'red',
          fillColor: '#f03',
          fillOpacity: 0.5,
          radius: geofenceRadius
        }).addTo(map);

        // Update geofence info
        document.getElementById('geofenceRadius').textContent = geofenceRadius;

        // Get student's current location
        getStudentLocation();
      } else {
        Swal.fire({
          icon: 'warning',
          title: 'No Geofence Set',
          text: 'No active geofence set for your company. Please contact your HR.',
          confirmButtonText: 'OK'
        });
      }
    })
    .catch((error) => {
      console.error('Error loading geofence data:', error);
      Swal.fire({
        icon: 'error',
        title: 'Error',
        text: 'Error loading geofence data. Please check console for details.',
        confirmButtonText: 'OK'
      });
    });
}

// Get student's current location
function getStudentLocation() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(
      (position) => {
        studentLat = position.coords.latitude;
        studentLng = position.coords.longitude;

        // Add or update student marker
        if (studentMarker) {
          map.removeLayer(studentMarker);
        }

        // Check if inside geofence
        const distance = haversine(studentLat, studentLng, geofenceLat, geofenceLng);
        const isInside = distance <= geofenceRadius;

        studentMarker = L.marker([studentLat, studentLng], {
          icon: L.divIcon({
            className: 'student-marker',
            html: `<div style="background-color: ${isInside ? 'green' : 'red'}; width: 20px; height: 20px; border-radius: 50%; border: 2px solid white;"></div>`,
            iconSize: [20, 20],
            iconAnchor: [10, 10]
          })
        }).addTo(map);

        // Add popup
        studentMarker.bindPopup(`<b>Your Location</b><br>${isInside ? 'Inside' : 'Outside'} geofence<br>Distance: ${distance.toFixed(2)} meters`).openPopup();

        // Fit map to show both geofence and student location
        const group = new L.featureGroup([
          L.marker([geofenceLat, geofenceLng]),
          L.marker([studentLat, studentLng])
        ]);
        map.fitBounds(group.getBounds().pad(0.1));
      },
      (error) => {
        console.error('Error getting location:', error);
        alert('Unable to get your location. Please enable location services.');
      },
      {
        enableHighAccuracy: true,
        timeout: 10000,
        maximumAge: 300000 // 5 minutes
      }
    );
  } else {
    alert('Geolocation is not supported by this browser.');
  }
}

// Haversine formula for distance calculation
function haversine(lat1, lon1, lat2, lon2) {
  const R = 6371000; // Earth radius in meters
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLon = (lon2 - lon1) * Math.PI / 180;
  const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
            Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
            Math.sin(dLon/2) * Math.sin(dLon/2);
  const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
  return R * c;
}




// Profile edit functions
function enableEdit(fieldId) {
  const field = document.getElementById(fieldId);
  field.disabled = false;
  field.focus();
  showActions();
}

function enablePassword() {
  document.getElementById("passwordFields").style.display = "block";
  document.getElementById("password_display").style.display = "none";
  showActions();
}

function showActions() {
  document.getElementById("actionButtons").style.display = "flex";
}

function cancelEdit() {
  const form = document.getElementById("profileForm");
  form.reset();

  const inputs = form.querySelectorAll("input");
  inputs.forEach((input) => {
    if (input.type !== "hidden" && input.id !== "confirm_password") {
      input.disabled = true;
    }
  });

  document.getElementById("passwordFields").style.display = "none";
  document.getElementById("password_display").style.display = "inline-block";
  document.getElementById("actionButtons").style.display = "none";
}

function saveProfile() {
  const form = document.getElementById("profileForm");
  const formData = new FormData(form);
  const newPassword = formData.get("password");
  const confirmPassword = document.getElementById("confirm_password").value;

  if (newPassword && newPassword !== confirmPassword) {
    alert("Passwords do not match.");
    return;
  }

  fetch("update_profile.php", {
    method: "POST",
    body: formData,
  })
    .then((res) => res.text())
    .then((res) => {
      alert("Profile updated successfully!");
      location.reload();
    })
    .catch(() => {
      alert("Error updating profile.");
    });
}



// Apply internship function
function applyInternship(postId) {
  Swal.fire({
    title: 'Apply for this Internship?',
    text: "Are you sure you want to submit your application?",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, Apply Now!'
  }).then((result) => {
    if (result.isConfirmed) {
      fetch("apply_internship.php", {
        method: "POST",
        headers: {
          "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "post_id=" + encodeURIComponent(postId),
      })
      .then((response) => {
        if (!response.ok) throw new Error("Network response was not OK");
        return response.json();
      })
      .then((data) => {
        if (data.success) {
          Swal.fire({
            title: 'Success!',
            text: 'Application submitted successfully!',
            icon: 'success'
          });
          const btn = document.querySelector(`button.apply-btn[data-postid='${postId}']`);
          if (btn) {
            btn.textContent = "Already applied";
            btn.disabled = true;
            btn.classList.add("applied");
          }
        } else {
          if (data.no_resume) {
            Swal.fire({
              title: 'No Resume Found',
              text: "You must create a resume before you can apply for an internship.",
              icon: 'warning',
              showCancelButton: true,
              confirmButtonColor: '#3085d6',
              cancelButtonColor: '#d33',
              confirmButtonText: 'Create Resume Now'
            }).then((result) => {
              if (result.isConfirmed) {
                showTab('resumeContent');
              }
            });
          } else {
            Swal.fire({
              title: 'Error!',
              text: data.message,
              icon: 'error'
            });
          }
        }
      })
      .catch((error) => {
        console.error("Fetch error:", error);
        Swal.fire({
          title: 'Error!',
          text: 'An error occurred while submitting your application.',
          icon: 'error'
        });
      });
    }
  });
}

// Search filter for internships
function filterInternships() {
  let input, container;
  if (document.getElementById("myApplicationsContent").classList.contains("active")) {
    input = document.getElementById("applicationsSearchInput");
    container = document.getElementById("myApplicationsContainer");
  } else {
    input = document.getElementById("searchInput");
    container = document.getElementById("internshipCardsContainer");
  }
  const filter = input.value.toLowerCase();
  const cards = container.getElementsByClassName("internship-card");

  let visibleCount = 0;
  for (let card of cards) {
    const cardText = card.textContent.toLowerCase();

    if (cardText.includes(filter)) {
      card.style.display = "";
      visibleCount++;
    } else {
      card.style.display = "none";
    }
  }
  
  // Update job count in toolbar
  if (document.getElementById("jobOffersContent").classList.contains("active")) {
    updateJobCount(visibleCount);
  }
}

// Update job count in toolbar
function updateJobCount(count) {
  const totalJobsElement = document.getElementById('totalJobs');
  if (totalJobsElement) {
    totalJobsElement.textContent = count;
  }
}

// Initialize job count when page loads
document.addEventListener('DOMContentLoaded', function() {
  setTimeout(() => {
    const cards = document.getElementsByClassName("internship-card");
    updateJobCount(cards.length);
  }, 100);
});

// Resume dynamic form logic
const skillsList = document.getElementById('skillsList');
const educationList = document.getElementById('educationList');
const experienceList = document.getElementById('experienceList');
const certificationsList = document.getElementById('certificationsList');

document.getElementById('addSkillBtn').addEventListener('click', addSkill);
document.getElementById('addEducationBtn').addEventListener('click', addEducation);
document.getElementById('addExperienceBtn').addEventListener('click', addExperience);
document.getElementById('addCertificationBtn').addEventListener('click', addCertification);

function createRemoveButton(onclickFunc) {
  const btn = document.createElement('button');
  btn.type = 'button';
  btn.textContent = 'Remove';
  btn.className = 'remove-btn';
  btn.onclick = onclickFunc;
  return btn;
}

// Resume add functions
function addEducation(data = {}) {
  const div = document.createElement('div');
  div.className = 'entry';
  div.innerHTML = `
    <input type="text" name="education_school_name[]" placeholder="School Name" value="${data.school_name || ''}" required>
    <input type="text" name="education_start_year[]" placeholder="Start Year" value="${data.start_year || ''}" required>
    <input type="text" name="education_end_year[]" placeholder="End Year" value="${data.end_year || ''}" required>
    <input type="text" name="education_description[]" placeholder="Description" value="${data.description || ''}">
  `;
  div.appendChild(createRemoveButton(() => div.remove()));
  educationList.appendChild(div);
}

function addSkill(data = {}) {
  const div = document.createElement('div');
  div.className = 'entry';
  div.innerHTML = `
    <input type="text" name="skill_name[]" placeholder="Skill Name" value="${data.skill_name || ''}" required>
    <input type="text" name="skill_proficiency[]" placeholder="Proficiency" value="${data.proficiency || ''}">
  `;
  div.appendChild(createRemoveButton(() => div.remove()));
  skillsList.appendChild(div);
}

function addExperience(data = {}) {
  const div = document.createElement('div');
  div.className = 'entry';
  div.innerHTML = `
    <input type="text" name="experience_company_name[]" placeholder="Company Name" value="${data.company_name || ''}" required>
    <input type="text" name="experience_position[]" placeholder="Position" value="${data.position || ''}" required>
    <input type="date" name="experience_start_date[]" placeholder="Start Date" value="${data.start_date || ''}">
    <input type="date" name="experience_end_date[]" placeholder="End Date" value="${data.end_date || ''}">
    <input type="text" name="experience_responsibilities[]" placeholder="Responsibilities" value="${data.responsibilities || ''}">
  `;
  div.appendChild(createRemoveButton(() => div.remove()));
  experienceList.appendChild(div);
}

function addCertification(data = {}) {
  const div = document.createElement('div');
  div.className = 'entry';
  div.innerHTML = `
    <input type="text" name="certification_title[]" placeholder="Title" value="${data.title || ''}" required>
    <input type="text" name="certification_issuer[]" placeholder="Issuer" value="${data.issuer || ''}">
    <input type="date" name="certification_date_obtained[]" placeholder="Date Obtained" value="${data.date_obtained || ''}">
    <input type="text" name="certification_description[]" placeholder="Description" value="${data.description || ''}">
  `;
  div.appendChild(createRemoveButton(() => div.remove()));
  certificationsList.appendChild(div);
}

// Initialize default entries
addEducation({ school_name: 'Universidad De Manila', start_year: '2021', end_year: '2025', description: 'Bachelor of Science in Information Technology' });
addSkill();
addExperience();
addCertification();

// Save Resume
function saveResume(event) {
  event.preventDefault();
  const formData = new FormData(event.target);

  fetch('save_resume.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        Swal.fire({
          title: 'Success!',
          text: 'Resume saved successfully!',
          icon: 'success'
        }).then(() => {
            location.reload();
        });
      } else {
        Swal.fire({
          title: 'Error!',
          text: data.message,
          icon: 'error'
        });
      }
    })
    .catch(() => {
        Swal.fire({
          title: 'Error!',
          text: 'An error occurred while saving the resume.',
          icon: 'error'
        });
    });
}

// View resume
function viewResume() {
  const studentId = <?php echo (int)$_SESSION['user_id']; ?>;
  window.open('view_summary.php?student_id=' + studentId, '_blank', 'width=1000,height=800,resizable=yes,scrollbars=yes');
}



// Load My Applications
function loadMyApplications() {
  const container = document.getElementById('myApplicationsContainer');
  container.innerHTML = "<p>Loading applications...</p>";

  fetch('load_my_applications.php')
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        container.innerHTML = '<p>Error loading applications.</p>';
        return;
      }

      container.innerHTML = '';
      if (data.applications.length === 0) {
        container.innerHTML = '<p>You have no applications yet.</p>';
        return;
      }

      data.applications.forEach(app => {
        const card = document.createElement('div');
        card.className = 'internship-card';

        const interviewButton = app.status === 'For Interview' && app.interview ?
            `<button
              class="interview-btn"
              style="
                padding: 8px 16px;
                background-color: #2e7d32;
                color: #fff;
                border: none;
                border-radius: 8px;
                font-size: 0.95rem;
                cursor: pointer;
                transition: background 0.3s;
                margin-top: 10px;
              "
              onmouseover="this.style.backgroundColor='#1b5e20'"
              onmouseout="this.style.backgroundColor='#2e7d32'"
              onclick='viewInterview(${JSON.stringify(app.interview)})'>
              View Interview Details
            </button>` : '';

        card.innerHTML = `
          <div class="internship-card-header">
            <h3>${app.internship_title}</h3>
            <div class="company-badge">${app.companyname}</div>
          </div>
          <div class="job-details">
            <div class="job-info-grid">
              <div class="job-info-item">
                <div class="job-info-label">Location</div>
                <div class="job-info-value">${app.job_location}</div>
              </div>
              <div class="job-info-item">
                <div class="job-info-label">Status</div>
                <div class="job-info-value">${app.status}</div>
              </div>
            </div>
            
            <div class="job-meta">
              <div class="job-date">Applied on: ${new Date(app.application_date).toLocaleDateString()}</div>
            </div>
            
            ${interviewButton}
          </div>
        `;
        container.appendChild(card);
      });
    })
    .catch(() => {
      container.innerHTML = '<p>Error fetching applications.</p>';
    });
}

function loadMyOffers() {
  const container = document.getElementById('myOffersContainer');
  container.innerHTML = "<p>Loading offers...</p>";

  fetch('load_my_offers.php')
    .then(res => res.json())
    .then(data => {
      if (!data.success) {
        container.innerHTML = '<p>Error loading offers.</p>';
        return;
      }

      container.innerHTML = '';
      if (data.offers.length === 0) {
        container.innerHTML = '<p>You have no offers yet.</p>';
        return;
      }

      data.offers.forEach(offer => {
        const card = document.createElement('div');
        card.className = 'internship-card';

        card.innerHTML = `
          <div class="internship-card-header">
            <h3>${offer.internship_title}</h3>
            <div class="company-badge">${offer.companyname}</div>
          </div>
          <div class="job-details">
            <div class="job-info-grid">
              <div class="job-info-item">
                <div class="job-info-label">Location</div>
                <div class="job-info-value">${offer.location}</div>
              </div>
              <div class="job-info-item">
                <div class="job-info-label">Allowance</div>
                <div class="job-info-value">${offer.allowance}</div>
              </div>
            </div>
            <div class="job-description">
              <div class="job-info-label">Job Description</div>
              <div class="job-info-value">${offer.internship_description}</div>
            </div>
            <div class="job-card-buttons">
                <button class="btn-accept" onclick="handleOfferAction(${offer.application_id}, 'accept')">Accept</button>
                <button class="btn-reject" onclick="handleOfferAction(${offer.application_id}, 'reject')">Reject</button>
            </div>
          </div>
        `;
        container.appendChild(card);
      });
    })
    .catch(() => {
      container.innerHTML = '<p>Error fetching offers.</p>';
    });
}

function handleOfferAction(applicationId, action) {
    fetch('handle_offer.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `application_id=${applicationId}&action=${action}`
    })
    .then(res => {
        console.log('Raw fetch response:', res); // Debugging line
        if (!res.ok) {
            throw new Error(`HTTP error! status: ${res.status}`);
        }
        return res.json();
    })
    .then(data => {
        console.log('Offer action response:', data); // Debugging line
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
            }).then(() => {
                loadMyOffers();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: data.message,
            });
        }
    })
    .catch((error) => { // Added error parameter for more specific logging
        console.error('Fetch error in handleOfferAction:', error); // Debugging line
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An unexpected error occurred. Check console for details.', // Updated message
        });
    });
}

// Modern interview popup
function viewInterview(interview) {
    const modal = document.getElementById('interviewDetailsModal');
    const modalBody = document.getElementById('interviewDetailsBody');

    let locationDetails = '';
    if (interview.location === 'Online' && interview.online_link) {
        locationDetails = `<div class="interview-detail-item"><strong>Link:</strong> <span><a href="${interview.online_link}" target="_blank">Join Meeting</a></span></div>`;
    } else if (interview.location === 'On-Site' && interview.exact_address) {
        locationDetails = `<div class="interview-detail-item"><strong>Address:</strong> <span>${interview.exact_address}</span></div>`;
    }

    modalBody.innerHTML = `
        <div class="interview-details">
            <div class="interview-detail-item"><strong>Company:</strong> <span>${interview.companyname}</span></div>
            <div class="interview-detail-item"><strong>Position:</strong> <span>${interview.internship_title}</span></div>
            <div class="interview-detail-item"><strong>Date & Time:</strong> <span>${new Date(interview.interview_datetime).toLocaleString()}</span></div>
            <div class="interview-detail-item"><strong>Type:</strong> <span>${interview.location}</span></div>
            ${locationDetails}
            <div class="interview-detail-item"><strong>Remarks:</strong> <span>${interview.remarks || 'None'}</span></div>
        </div>
    `;

    modal.style.display = 'block';
}

function closeInterviewDetailsModal() {
    const modal = document.getElementById('interviewDetailsModal');
    modal.style.display = 'none';
}

  // ------------------ MODERN ANALOG CLOCK ------------------
  function drawClock() {
    const canvas = document.getElementById("analogClock");
    const ctx = canvas.getContext("2d");
    const radius = canvas.height / 2;
    ctx.translate(radius, radius);

    function drawFace() {
      // Outer circle
      ctx.beginPath();
      ctx.arc(0, 0, radius * 0.95, 0, 2 * Math.PI);
      ctx.fillStyle = 'white';
      ctx.fill();
      
      // Inner shadow for depth
      const grad = ctx.createRadialGradient(0, 0, radius * 0.9, 0, 0, radius);
      grad.addColorStop(0, '#f0f0f0');
      grad.addColorStop(0.95, 'white');
      grad.addColorStop(1, '#e0e0e0');
      ctx.strokeStyle = grad;
      ctx.lineWidth = radius * 0.1;
      ctx.stroke();

      // Center point
      ctx.beginPath();
      ctx.arc(0, 0, radius * 0.05, 0, 2 * Math.PI);
      ctx.fillStyle = '#116530';
      ctx.fill();
    }

    function drawNumbers() {
      ctx.font = `bold ${radius * 0.15}px Inter, sans-serif`;
      ctx.textBaseline = "middle";
      ctx.textAlign = "center";
      ctx.fillStyle = '#333';
      for(let num = 1; num <= 12; num++){
        let ang = num * Math.PI / 6;
        ctx.rotate(ang);
        ctx.translate(0, -radius * 0.78);
        ctx.rotate(-ang);
        ctx.fillText(num.toString(), 0, 0);
        ctx.rotate(ang);
        ctx.translate(0, radius * 0.78);
        ctx.rotate(-ang);
      }
    }

    function drawTime() {
      const now = new Date();
      let hour = now.getHours();
      let minute = now.getMinutes();
      let second = now.getSeconds();

      // Hour hand
      hour = hour % 12;
      hour = (hour * Math.PI / 6) + (minute * Math.PI / (6 * 60)) + (second * Math.PI / (360 * 60));
      drawHand(ctx, hour, radius * 0.5, radius * 0.07, '#333');

      // Minute hand
      minute = (minute * Math.PI / 30) + (second * Math.PI / (30 * 60));
      drawHand(ctx, minute, radius * 0.7, radius * 0.05, '#555');

      // Second hand
      second = (second * Math.PI / 30);
      drawHand(ctx, second, radius * 0.8, radius * 0.02, '#DAA520');

      const digitalTimeElement = document.getElementById("digitalTime");
      if (digitalTimeElement) {
        digitalTimeElement.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
      }
    }

    function drawHand(ctx, pos, length, width, color) {
      ctx.beginPath();
      ctx.lineWidth = width;
      ctx.lineCap = "round";
      ctx.moveTo(0,0);
      ctx.rotate(pos);
      ctx.lineTo(0, -length);
      ctx.strokeStyle = color;
      ctx.stroke();
      ctx.rotate(-pos);
    }

    function updateClock() {
      ctx.clearRect(-radius, -radius, canvas.width, canvas.height);
      drawFace();
      drawNumbers();
      drawTime();
    }

    updateClock();
    setInterval(updateClock, 1000);
  }
  document.addEventListener('DOMContentLoaded', drawClock);

  // ------------------ TIME LOGS & PROGRESS ------------------
  function loadLogs() {
    fetch('fetch_timecard.php')
      .then(res => res.json())
      .then(data => {
        const container = document.getElementById('logDetailsContainer');
        container.innerHTML = ''; // Clear previous entries

        let lastTimeIn = null;
        let lastTimeOut = null;

        if (data.length === 0) {
          container.innerHTML = '<p style="text-align: center; color: #777;">No attendance logged for today.</p>';
        } else {
          data.forEach(row => {
            const timeIn = (row.time_in && row.time_in !== "0000-00-00 00:00:00") ? new Date(row.time_in).toLocaleTimeString() : 'Not Logged';
            const timeOut = (row.time_out && row.time_out !== "0000-00-00 00:00:00") ? new Date(row.time_out).toLocaleTimeString() : 'Not Logged';

            const selfieIn = row.time_in_selfie ? `<img src="${row.time_in_selfie}" alt="Time-in Selfie" style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover; margin-top: 8px;">` : '<div style="width: 60px; height: 60px; border-radius: 8px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #aaa; margin-top: 8px;">No Selfie</div>';
            const selfieOut = row.time_out_selfie ? `<img src="${row.time_out_selfie}" alt="Time-out Selfie" style="width: 60px; height: 60px; border-radius: 8px; object-fit: cover; margin-top: 8px;">` : '<div style="width: 60px; height: 60px; border-radius: 8px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #aaa; margin-top: 8px;">No Selfie</div>';
            
            let statusText = row.status === 'Validated' ? '<span style="color: green; font-weight: bold;">Validated</span>' : '<span style="color: orange; font-weight: bold;">Waiting for validation</span>';

            const logEntry = `
              <div class="log-entry" style="background: #f9f9f9; border-left: 4px solid #116530; padding: 12px; border-radius: 8px;">
                <div style="display: flex; justify-content: space-around; align-items: center;">
                    <div style="text-align: center;">
                      <strong>Time In</strong>
                      <p style="margin: 4px 0; font-size: 0.9rem;">${timeIn}</p>
                      ${selfieIn}
                    </div>
                    <div style="text-align: center;">
                      <strong>Time Out</strong>
                      <p style="margin: 4px 0; font-size: 0.9rem;">${timeOut}</p>
                      ${selfieOut}
                    </div>
                </div>
                <div style="text-align: center; margin-top: 10px;">
                    <strong>Status:</strong> ${statusText}
                </div>
              </div>
            `;
            container.innerHTML += logEntry;

            if(row.time_in && row.time_in !== "0000-00-00 00:00:00") lastTimeIn = row.time_in;
            if(row.time_out && row.time_out !== "0000-00-00 00:00:00") lastTimeOut = row.time_out;
          });
        }
        updateButtons(lastTimeIn, lastTimeOut);
      });
  }

  loadLogs();

  function updateButtons(lastIn, lastOut) {
    const btnIn = document.querySelector('.timein');
    const btnOut = document.querySelector('.timeout');

    btnIn.disabled = lastIn && (!lastOut || new Date(lastOut) > new Date(lastIn));
    btnOut.disabled = !lastIn || (lastIn && lastOut && new Date(lastOut) > new Date(lastIn));
  }

  // ------------------ TIME IN / TIME OUT with Selfie Verification ------------------
  let pendingAction = null;
  let cameraStream = null;
  let capturedFile = null;

  function logAttendance(action) {
    // 1. Geofence Check
    if (!geofenceLocationId) {
      Swal.fire('Error', 'No geofence set by your company. Please contact your HR.', 'error');
      return;
    }
    if (studentLat === null || studentLng === null) {
      Swal.fire('Error', 'Unable to get your current location. Please enable location services and try again.', 'error');
      return;
    }
    const distance = haversine(studentLat, studentLng, geofenceLat, geofenceLng);
    if (distance > geofenceRadius) {
      Swal.fire('Out of Range', `You must be inside your assigned location radius to log attendance. You are currently ${distance.toFixed(0)} meters away.`, 'error');
      return;
    }

    // 2. Open Modal and Start Camera
    pendingAction = action;
    const modal = document.getElementById('selfieModal');
    modal.style.display = 'block';
    
    navigator.mediaDevices.getUserMedia({ video: true })
      .then(stream => {
        cameraStream = stream;
        const video = document.getElementById('cameraFeed');
        video.srcObject = stream;
        resetModalToCaptureState();
      })
      .catch(err => {
        Swal.fire('Camera Error', 'Could not access the camera. Please ensure you have granted permission.', 'error');
        closeSelfieModal();
      });
  }

  function captureSelfie() {
    const video = document.getElementById('cameraFeed');
    const canvas = document.getElementById('captureCanvas');
    const capturedImage = document.getElementById('capturedImage');

    canvas.width = video.videoWidth;
    canvas.height = video.videoHeight;
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    
    capturedImage.src = canvas.toDataURL('image/jpeg');

    // Switch to preview state
    document.getElementById('cameraContainer').style.display = 'none';
    document.getElementById('previewContainer').style.display = 'block';
    document.getElementById('captureBtn').style.display = 'none';
    document.getElementById('retakeBtn').style.display = 'inline-block';
    document.getElementById('submitBtn').style.display = 'inline-block';
  }

  function retakeSelfie() {
    resetModalToCaptureState();
  }

  function submitSelfie() {
    const canvas = document.getElementById('captureCanvas');
    canvas.toBlob(blob => {
      capturedFile = new File([blob], "selfie.jpg", { type: "image/jpeg" });
      
      const formData = new FormData();
      formData.append('action', pendingAction);
      formData.append('location_id', geofenceLocationId);
      formData.append('latitude', studentLat);
      formData.append('longitude', studentLng);
      formData.append('photo', capturedFile);

      // Show loading state
      document.getElementById('submitBtn').disabled = true;
      document.getElementById('submitBtn').textContent = 'Submitting...';

      fetch('timecard_action.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          Swal.fire('Success', data.message, 'success');
          loadLogs(); // Refresh attendance logs
        } else {
          Swal.fire('Error', data.message, 'error');
        }
      })
      .catch(() => Swal.fire('Error', 'An error occurred while submitting your attendance.', 'error'))
      .finally(() => {
        closeSelfieModal();
      });
    }, 'image/jpeg');
  }

  function closeSelfieModal() {
    if (cameraStream) {
      cameraStream.getTracks().forEach(track => track.stop());
      cameraStream = null;
    }
    document.getElementById('selfieModal').style.display = 'none';
    pendingAction = null;
    capturedFile = null;
  }

  function resetModalToCaptureState() {
    document.getElementById('cameraContainer').style.display = 'block';
    document.getElementById('previewContainer').style.display = 'none';
    document.getElementById('captureBtn').style.display = 'inline-block';
    document.getElementById('retakeBtn').style.display = 'none';
    document.getElementById('submitBtn').style.display = 'none';
    document.getElementById('submitBtn').disabled = false;
    document.getElementById('submitBtn').textContent = 'Submit';
  }

  // Event Listeners
  document.querySelector('.timein').addEventListener('click', () => logAttendance('timein'));
  document.querySelector('.timeout').addEventListener('click', () => logAttendance('timeout'));
  document.getElementById('captureBtn').addEventListener('click', captureSelfie);
  document.getElementById('retakeBtn').addEventListener('click', retakeSelfie);
  document.getElementById('submitBtn').addEventListener('click', submitSelfie);


function showImageModal(src) {
  document.getElementById('modalImage').src = src;
  document.getElementById('imageModal').style.display = 'flex';
}

function closeImageModal() {
  document.getElementById('imageModal').style.display = 'none';
}

function initializeAttendanceTracker() {
  const calendarContainer = document.getElementById("attendanceCalendar");
  const selectedDateText = document.getElementById("selectedDateText");
  const monthYearText = document.getElementById("monthYearText");
  const prevMonthBtn = document.getElementById("prevMonthBtn");
  const nextMonthBtn = document.getElementById("nextMonthBtn");

  let currentDate = new Date();

  function renderCalendar(year, month) {
    if (!calendarContainer) return;
    const firstDay = new Date(year, month, 1).getDay();
    const lastDate = new Date(year, month + 1, 0).getDate();
    
    if (monthYearText) {
        monthYearText.textContent = new Date(year, month).toLocaleString('default', { month: 'long', year: 'numeric' });
    }

    let html = "<table><thead><tr>";
    const days = ["Sun","Mon","Tue","Wed","Thu","Fri","Sat"];
    days.forEach(d => html += `<th>${d}</th>`);
    html += "</tr></thead><tbody><tr>";

    for (let i = 0; i < firstDay; i++) {
      html += "<td></td>";
    }

    for (let d = 1; d <= lastDate; d++) {
      const fullDate = `${year}-${String(month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      html += `<td data-date="${fullDate}">${d}</td>`;
      if ((firstDay + d) % 7 === 0) html += "</tr><tr>";
    }

    html += "</tr></tbody></table>";
    calendarContainer.innerHTML = html;

    calendarContainer.querySelectorAll("td[data-date]").forEach(td => {
      td.addEventListener("click", () => {
        calendarContainer.querySelectorAll("td.selected").forEach(x => x.classList.remove("selected"));
        td.classList.add("selected");
        loadLogs(td.dataset.date);
      });
    });
  }

  function changeMonth(offset) {
      currentDate.setMonth(currentDate.getMonth() + offset);
      renderCalendar(currentDate.getFullYear(), currentDate.getMonth());
  }

  if (prevMonthBtn && nextMonthBtn && !prevMonthBtn.dataset.listener) {
    prevMonthBtn.addEventListener("click", () => changeMonth(-1));
    nextMonthBtn.addEventListener("click", () => changeMonth(1));
    prevMonthBtn.dataset.listener = 'true';
    nextMonthBtn.dataset.listener = 'true';
  }

  renderCalendar(currentDate.getFullYear(), currentDate.getMonth());

  function loadLogs(date) {
    if (!selectedDateText) return;
    selectedDateText.textContent = new Date(date).toLocaleDateString([], { year: 'numeric', month: 'long', day: 'numeric' });
    const logsContainer = document.getElementById('logsContainer');
    logsContainer.innerHTML = '<p class="loading">Loading logs...</p>';

    fetch(`fetch_logs.php?date=${date}`)
      .then(res => res.json())
      .then(data => {
        logsContainer.innerHTML = '';
        if (data.success) {
            if (data.logs.length === 0) {
              logsContainer.innerHTML = `<p class="no-logs-message">No logs found for this date.</p>`;
            } else {
              data.logs.forEach(log => {
                const timeIn = log.time_in ? new Date(log.time_in).toLocaleTimeString() : 'N/A';
                const timeOut = log.time_out ? new Date(log.time_out).toLocaleTimeString() : 'N/A';

                const selfieIn = log.time_in_selfie ? `<img src="${log.time_in_selfie}" alt="Time-in Selfie" class="log-selfie" onclick="showImageModal('${log.time_in_selfie}')">` : '<div class="no-selfie-placeholder">No Selfie</div>';
                const selfieOut = log.time_out_selfie ? `<img src="${log.time_out_selfie}" alt="Time-out Selfie" class="log-selfie" onclick="showImageModal('${log.time_out_selfie}')">` : '<div class="no-selfie-placeholder">No Selfie</div>';
                
                let statusText = log.status === 'Validated' ? '<span style="color: green; font-weight: bold;">Validated</span>' : '<span style="color: orange; font-weight: bold;">Waiting for validation</span>';

                const logCard = `
                  <div class="log-card">
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
                    <div style="text-align: center; margin-top: 10px;">
                        <strong>Status:</strong> ${statusText}
                    </div>
                  </div>
                `;
                logsContainer.innerHTML += logCard;
              });
            }
        } else {
            logsContainer.innerHTML = `<p class="no-logs-message" style="color: red;">Error: ${data.message}</p>`;
        }
      })
      .catch((error) => {
        logsContainer.innerHTML = '<p class="no-logs-message" style="color: red;">Error loading logs. Check console for details.</p>';
        console.error('Fetch error:', error);
      });
  }
}

document.addEventListener("DOMContentLoaded", () => {
    const dropdowns = document.querySelectorAll(".dropdown");

    dropdowns.forEach(drop => {
      const btn = drop.querySelector(".dropbtn");
      btn.addEventListener("click", (e) => {
        e.stopPropagation();
        drop.classList.toggle("active");
        dropdowns.forEach(d => { if (d !== drop) d.classList.remove("active"); });
      });
    });

    document.addEventListener("click", () => {
      dropdowns.forEach(d => d.classList.remove("active"));
    });

    // Initialize sidebar link click handlers
    closeSidebarOnLinkClick();

    document.querySelectorAll('.summary-item').forEach(item => {
        item.addEventListener('click', () => {
            const status = item.dataset.status;
            const statusTitle = item.querySelector('h4').textContent;
            showSummaryTasks(status, statusTitle);
        });
    });
});

// File submission functions
function previewFile(fileInputId, cardType) {
  const fileInput = document.getElementById(fileInputId);
  const preview = document.getElementById(cardType + '-preview');
  const filename = document.getElementById(cardType + '-filename');
  const attachBtn = document.getElementById(cardType + '-attach-btn');
  const submitBtn = document.getElementById(cardType + '-submit-btn');
  
  if (fileInput.files[0]) {
    filename.textContent = fileInput.files[0].name;
    preview.style.display = 'block';
    attachBtn.style.display = 'none';
    submitBtn.style.display = 'inline-block';
  }
}

function removeSelectedFile(fileInputId, cardType) {
  const fileInput = document.getElementById(fileInputId);
  const preview = document.getElementById(cardType + '-preview');
  const attachBtn = document.getElementById(cardType + '-attach-btn');
  const submitBtn = document.getElementById(cardType + '-submit-btn');
  
  fileInput.value = '';
  preview.style.display = 'none';
  attachBtn.style.display = 'inline-block';
  submitBtn.style.display = 'none';
}

function submitFile(fileInputId, type) {
  const fileInput = document.getElementById(fileInputId);
  const cardType = fileInputId.replace('_file', '');
  
  if (!fileInput.files[0]) {
    Swal.fire({
      icon: 'warning',
      title: 'No File Selected',
      text: 'Please select a file first.',
      confirmButtonText: 'OK'
    });
    return;
  }
  
  const formData = new FormData();
  formData.append('file', fileInput.files[0]);
  formData.append('type', type);
  
  fetch('upload_files.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'File submitted successfully!',
        confirmButtonText: 'OK'
      }).then(() => {
        // After successful submission, update UI
        const removeBtn = document.getElementById(cardType + '-remove-btn');
        const attachBtn = document.getElementById(cardType + '-attach-btn');
        const submitBtn = document.getElementById(cardType + '-submit-btn');
        const downloadBtn = document.getElementById(cardType + '-download-btn');
        const changeBtn = document.getElementById(cardType + '-change-btn');
        
        // Hide remove button and attach button
        removeBtn.style.display = 'none';
        attachBtn.style.display = 'none';
        submitBtn.style.display = 'none';
        
        // Show download and change file buttons
        downloadBtn.style.display = 'inline-block';
        changeBtn.style.display = 'inline-block';
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: data.message,
        confirmButtonText: 'OK'
      });
    }
  })
  .catch(() => {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'An error occurred while uploading the file.',
      confirmButtonText: 'OK'
    });
  });
}

function downloadFile(type) {
  fetch(`view_files.php?type=${type}`)
  .then(res => res.json())
  .then(data => {
    if (data.success && data.files.length > 0) {
      const filename = data.files[0].filename;
      const link = document.createElement('a');
      link.href = `uploads/files/${filename}`;
      link.download = filename;
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    } else {
      Swal.fire({
        icon: 'info',
        title: 'No File Found',
        text: 'No file available for download.',
        confirmButtonText: 'OK'
      });
    }
  })
  .catch(() => {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Error fetching file information.',
      confirmButtonText: 'OK'
    });
  });
}

function updateFileProgressCircle(approved, total) {
    const circle = document.getElementById('file-progress-circle');
    const progressText = document.getElementById('file-progress-text');
    if (!circle || !progressText) return;

    const radius = circle.r.baseVal.value;
    const circumference = 2 * Math.PI * radius;
    const percent = total > 0 ? (approved / total) * 100 : 0;

    // Initial setup for the animation
    circle.style.strokeDasharray = `${circumference} ${circumference}`;
    
    // Defer the animation start slightly to ensure transition is applied
    setTimeout(() => {
        const offset = circumference - (percent / 100) * circumference;
        circle.style.strokeDashoffset = offset;
    }, 100);

    progressText.textContent = `${approved}/${total}`;
}


function loadFileSubmissions() {
  const fileTypes = [
    { type: 'DTR', cardType: 'dtr' },
    { type: 'MOA', cardType: 'moa' },
    { type: 'LOA', cardType: 'loa' },
    { type: 'EVALUATION', cardType: 'evaluation' }
  ];
  
  fileTypes.forEach(({ type, cardType }) => {
    fetch(`view_files.php?type=${type}`)
    .then(res => res.json())
    .then(data => {
      const attachBtn = document.getElementById(cardType + '-attach-btn');
      const downloadBtn = document.getElementById(cardType + '-download-btn');
      const changeBtn = document.getElementById(cardType + '-change-btn');
      const submitBtn = document.getElementById(cardType + '-submit-btn');
      const preview = document.getElementById(cardType + '-preview');
      const filename = document.getElementById(cardType + '-filename');
      const removeBtn = document.getElementById(cardType + '-remove-btn');
      
      if (data.success && data.files.length > 0) {
        // File already submitted - show submitted state
        filename.textContent = data.files[0].filename;
        preview.style.display = 'block';
        removeBtn.style.display = 'none';
        attachBtn.style.display = 'none';
        submitBtn.style.display = 'none';
        downloadBtn.style.display = 'inline-block';
        changeBtn.style.display = 'inline-block';
      } else {
        // No file submitted - show initial state
        preview.style.display = 'none';
        attachBtn.style.display = 'inline-block';
        downloadBtn.style.display = 'none';
        changeBtn.style.display = 'none';
        submitBtn.style.display = 'none';
      }
    })
    .catch(() => {
      console.error(`Error loading ${type} file status`);
    });
  });
}

// Load file submissions status when the tab is shown
function showTab(tabId) {
  hideAllTabsExcept(tabId);

  const tab = document.getElementById(tabId);
  if (!tab) return;

  // Restricted tabs for pending students
  const restrictedTabs = ["logAttendanceContent", "performanceContent", "taskcontent", "fileSubmissionsContent"];

  // Remove any previous warning messages first
  const prevWarning = tab.querySelector('.pending-warning');
  if (prevWarning) prevWarning.remove();

  if (employmentStatus === "pending" && restrictedTabs.includes(tabId)) {
    // Clear the content and show only the warning
    tab.innerHTML = `
      <h2>${tab.querySelector('h2') ? tab.querySelector('h2').textContent : ''}</h2>
      <p class="pending-warning" style="color: red; font-weight: bold; margin-top: 15px;">
        You must apply first as an intern to access this section.
      </p>
    `;
    return;
  }

  // Load file submissions when accessing the file submissions tab
  if (tabId === "fileSubmissionsContent") {
    loadFileSubmissions();
  }

  // Initialize performance calendar when accessing the task tab
  if (tabId === "taskcontent") {
    initializePerformanceCalendar();
  }

  // If user is hired, do nothing – leave the HTML as it is in your page
  if (tabId === "myApplicationsContent") {
    loadMyApplications();
  }

  if (tabId === "myOffersContent") {
    console.log("Loading My Offers tab, calling loadMyOffers()");
    loadMyOffers();
  }

  if (tabId === "AttendanceTrackerContent") {
    initializeAttendanceTracker();
  }

  // Initialize map when showing log attendance tab
  if (tabId === "logAttendanceContent") {
    initializeMap();
    loadGeofence();
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


  // Declare global variables for messaging intervals
  let conversationRefreshInterval;
  let messageRefreshInterval;
  let currentConversation = null; // Also declare currentConversation globally

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
  fetch('fetch_messages.php?action=conversations&user_type=student')
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
  fetch(`fetch_messages.php?action=messages&other_type=${other_type}&other_id=${other_id}&user_type=student`)
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
  
  const { other_type, other_id } = currentConversation;
  const formData = new FormData();
  formData.append('other_type', other_type);
  formData.append('other_id', other_id);
  formData.append('message', message);
  formData.append('sender_type', 'student');
  
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
  
  fetch(`fetch_messages.php?action=search_users&query=${encodeURIComponent(query)}&user_type=student`)
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

// Performance Calendar Functions
let selectedDate = null;
let currentWeekStart = null;

function formatLocalDate(date) {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
}

function initializePerformanceCalendar() {
  const today = new Date();
  currentWeekStart = getMonday(today);
  selectedDate = formatLocalDate(today); // Default to today in local time
  renderCalendar();
  loadTasksForDate(selectedDate);
}

function getMonday(date) {
  const d = new Date(date);
  const day = d.getDay();
  const diff = d.getDate() - day + (day === 0 ? -6 : 1); // Adjust for Sunday
  return new Date(d.setDate(diff));
}

function renderCalendar() {
  const calendarBody = document.getElementById('calendarBody');
  const weekRange = document.getElementById('weekRange');
  calendarBody.innerHTML = '';

  // Calculate week dates
  const weekDates = [];
  for (let i = 0; i < 7; i++) {
    const date = new Date(currentWeekStart);
    date.setDate(currentWeekStart.getDate() + i);
    weekDates.push(date);
  }

  // Update week range display
  const startDate = weekDates[0];
  const endDate = weekDates[6];
  const startMonth = startDate.toLocaleString('default', { month: 'short' });
  const endMonth = endDate.toLocaleString('default', { month: 'short' });
  const startYear = startDate.getFullYear();
  const endYear = endDate.getFullYear();
  const rangeText = startYear === endYear ?
    `${startMonth} ${startDate.getDate()} - ${endMonth} ${endDate.getDate()}, ${startYear}` :
    `${startMonth} ${startDate.getDate()}, ${startYear} - ${endMonth} ${endDate.getDate()}, ${endYear}`;
  weekRange.textContent = `Week of ${rangeText}`;

  // Create table row
  const row = document.createElement('tr');

  // Add prev button cell
  const prevCell = document.createElement('td');
  prevCell.innerHTML = '<button id="prevWeekBtn" class="nav-btn">&larr;</button>';
  row.appendChild(prevCell);

  // Add date cells
  weekDates.forEach(date => {
    const td = document.createElement('td');
    td.textContent = date.getDate();
    td.dataset.date = formatLocalDate(date);
    td.classList.add('calendar-date');

    // Highlight current day
    const today = formatLocalDate(new Date());
    if (td.dataset.date === today) {
      td.classList.add('current-day');
    }

    // Highlight selected date
    if (td.dataset.date === selectedDate) {
      td.classList.add('selected');
    }

    td.addEventListener('click', () => selectDate(td.dataset.date));
    row.appendChild(td);
  });

  // Add next button cell
  const nextCell = document.createElement('td');
  nextCell.innerHTML = '<button id="nextWeekBtn" class="nav-btn">&rarr;</button>';
  row.appendChild(nextCell);

  calendarBody.appendChild(row);

  // Add event listeners for navigation buttons
  document.getElementById('prevWeekBtn').addEventListener('click', prevWeek);
  document.getElementById('nextWeekBtn').addEventListener('click', nextWeek);
}

function selectDate(date) {
  selectedDate = date;
  renderCalendar();
  loadTasksForDate(date);
}

function loadTasksForDate(date) {
  const tasksContainer = document.getElementById('selectedDateTasks');
  tasksContainer.innerHTML = '<p>Loading tasks...</p>';

  fetch(`fetch_student_tasks.php?date=${date}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displayTasks(data.tasks, date);
      } else {
        tasksContainer.innerHTML = '<p>Error loading tasks</p>';
      }
    })
    .catch(error => {
      console.error('Error fetching tasks:', error);
      tasksContainer.innerHTML = '<p>Error loading tasks</p>';
    });
}

function displayTasks(tasks, date) {
  const tasksContainer = document.getElementById('selectedDateTasks');

  if (tasks.length === 0) {
    tasksContainer.innerHTML = `<p>No tasks for ${new Date(date).toLocaleDateString()}</p>`;
    return;
  }

  let html = `<h3>Tasks for ${new Date(date).toLocaleDateString()}</h3>`;
  html += '<div class="tasks-list">';

  tasks.forEach(task => {
    const statusClass = task.status.toLowerCase();
    const statusText = task.status.charAt(0).toUpperCase() + task.status.slice(1);

    html += `
      <div class="task-item ${statusClass}">
        <div class="task-header">
          <h4>${escapeHtml(task.task_description)}</h4>
          <span class="task-status ${statusClass}">${statusText}</span>
        </div>
        <div class="task-details">
          <p><strong>Assigned:</strong> ${new Date(task.assigned_at).toLocaleDateString()}</p>
          <p><strong>Due:</strong> ${new Date(task.due_date).toLocaleDateString()}</p>
          ${task.submitted_at ? `<p><strong>Submitted:</strong> ${new Date(task.submitted_at).toLocaleDateString()}</p>` : ''}
          ${task.checked_at ? `<p><strong>Verified:</strong> ${new Date(task.checked_at).toLocaleDateString()}</p>` : ''}
          ${task.score !== null ? `<p><strong>Score:</strong> ${task.score}</p>` : ''}
        </div>
        ${task.status === 'assigned' ? `<button onclick="markTaskDone(${task.task_id})" class="mark-done-btn">Mark as Done</button>` : ''}

        <div class="attachments-section">
          <h5>Attachments</h5>
          <div id="attachment-display-${task.task_id}">
            ${task.attachment ? `
              <p><strong>File:</strong> ${escapeHtml(task.attachment.file_name)}</p>
              <a href="${escapeHtml(task.attachment.file_path)}" target="_blank" class="button">View/Download</a>
            ` : '<p>No file attached.</p>'}
          </div>
          <input type="file" id="file-upload-${task.task_id}" style="display: none;" onchange="uploadTaskAttachment(${task.task_id}, ${currentUserId}, this.files[0])">
          <button onclick="document.getElementById('file-upload-${task.task_id}').click()">Attach File</button>
        </div>

        <div class="comments-section">
          <h5 class="comments-header" onclick="toggleTaskComments(${task.task_id}, ${currentUserId})">Comments <span id="comment-count-${task.task_id}">(${task.comments.length})</span></h5>
          <div class="comments-container" id="task-comments-container-${task.task_id}" style="display:none;">
            <div class="comments-list" id="task-comments-list-${task.task_id}">
              ${task.comments.map(comment => `
                <div class="comment">
                  <p><strong>${escapeHtml(comment.commenter_name || comment.user_role)}</strong> <span class="comment-date">${comment.commented_at}</span></p>
                  <p>${escapeHtml(comment.comment_text)}</p>
                </div>
              `).join('')}
              ${task.comments.length === 0 ? '<p>No comments yet.</p>' : ''}
            </div>
            <div class="comment-form">
              <textarea id="task-comment-text-${task.task_id}" placeholder="Add a comment..."></textarea>
              <button onclick="addTaskComment(${task.task_id}, ${currentUserId}, 'student')">Post Comment</button>
            </div>
          </div>
        </div>
      </div>
    `;
  });

  html += '</div>';
  tasksContainer.innerHTML = html;
}

function markTaskDone(taskId) {
  fetch('update_task_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'task_id=' + taskId + '&status=submitted'
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Task marked as done!',
        confirmButtonText: 'OK'
      }).then(() => {
        loadTasksForDate(selectedDate); // Reload tasks
      });
    } else {
      alert('Error: ' + data.message);
    }
  })
  .catch(() => alert('Error updating task status.'));
}

function prevWeek() {
  currentWeekStart.setDate(currentWeekStart.getDate() - 7);
  renderCalendar();
}

function nextWeek() {
  currentWeekStart.setDate(currentWeekStart.getDate() + 7);
  renderCalendar();
}

function showSummaryTasks(status, title) {
  showTab('summaryTasksContent');
  const tasksContainer = document.getElementById('summaryTasksList');
  const titleElement = document.getElementById('summaryTasksTitle');
  
  titleElement.textContent = title + ' Tasks';
  tasksContainer.innerHTML = '<p>Loading tasks...</p>';

  fetch(`fetch_student_tasks.php?status=${status}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        displaySummaryTasks(data.tasks);
      } else {
        tasksContainer.innerHTML = '<p>Error loading tasks</p>';
      }
    })
    .catch(error => {
      console.error('Error fetching summary tasks:', error);
      tasksContainer.innerHTML = '<p>Error loading tasks</p>';
    });
}

function displaySummaryTasks(tasks) {
    const tasksContainer = document.getElementById('summaryTasksList');

    if (tasks.length === 0) {
        tasksContainer.innerHTML = `<p>No tasks found for this category.</p>`;
        return;
    }

    let html = '';
    tasks.forEach(task => {
        const statusClass = task.status.toLowerCase();
        const statusText = task.status.charAt(0).toUpperCase() + task.status.slice(1);

        html += `
          <div class="task-item ${statusClass}">
            <div class="task-header">
              <h4>${task.task_description}</h4>
              <span class="task-status ${statusClass}">${statusText}</span>
            </div>
            <div class="task-details">
              <p><strong>Assigned:</strong> ${new Date(task.assigned_at).toLocaleDateString()}</p>
              <p><strong>Due:</strong> ${new Date(task.due_date).toLocaleDateString()}</p>
              ${task.submitted_at ? `<p><strong>Submitted:</strong> ${new Date(task.submitted_at).toLocaleDateString()}</p>` : ''}
              ${task.checked_at ? `<p><strong>Verified:</strong> ${new Date(task.checked_at).toLocaleDateString()}</p>` : ''}
            </div>
          </div>
        `;
    });

    tasksContainer.innerHTML = html;
}



window.onclick = function(event) {
  const newChatModal = document.getElementById('newChatModal');
  const interviewDetailsModal = document.getElementById('interviewDetailsModal');
  if (event.target === newChatModal) {
    hideNewChatModal();
  }
  if (event.target === interviewDetailsModal) {
    closeInterviewDetailsModal();
  }
}

document.addEventListener('DOMContentLoaded', function() {
    const circle = document.getElementById('performance-circle');
    const radius = circle.r.baseVal.value;
    const circumference = 2 * Math.PI * radius;
    const performancePercent = <?php echo $performanceScore; ?>;

    circle.style.strokeDasharray = `${circumference} ${circumference}`;
    circle.style.strokeDashoffset = circumference;

    const offset = circumference - (performancePercent / 100) * circumference;
    circle.style.strokeDashoffset = offset;
});

let fileSubmissions = {};
let commentInterval;
let taskCommentIntervals = {}; // For task-specific comments

function toggleComments(cardType) {
  const container = document.getElementById(`${cardType}-comments-container`);
  if (container.style.display === 'none') {
    container.style.display = 'block';
    loadComments(cardType);
    commentInterval = setInterval(() => loadComments(cardType), 5000); // Refresh every 5 seconds
  } else {
    container.style.display = 'none';
    clearInterval(commentInterval);
  }
}

function loadComments(cardType) {
  const fileTypeMap = {
    'dtr': 'DTR',
    'moa': 'MOA',
    'loa': 'LOA',
    'evaluation': 'EVALUATION'
  };
  const fileType = fileTypeMap[cardType];

  if (!fileSubmissions[fileType] || !fileSubmissions[fileType].submission_id) {
    console.error('Submission ID not found for', fileType);
    return;
  }
  const submissionId = fileSubmissions[fileType].submission_id;

  fetch(`fetch_comments.php?submission_id=${submissionId}&file_type=${fileType}`)
    .then(res => res.json())
    .then(data => {
      const list = document.getElementById(`${cardType}-comments-list`);
      list.innerHTML = '';
      if (data.success && data.comments.length > 0) {
        data.comments.forEach(comment => {
          const commentDiv = document.createElement('div');
          commentDiv.className = 'comment';
          commentDiv.innerHTML = `
            <p><strong>${comment.commenter_name}</strong> <span class="comment-date">${comment.commented_at}</span></p>
            <p>${comment.comment_text}</p>
          `;
          list.appendChild(commentDiv);
        });
      } else {
        list.innerHTML = '<p>No comments yet.</p>';
      }
    });
}

function addComment(cardType) {
  const fileTypeMap = {
    'dtr': 'DTR',
    'moa': 'MOA',
    'loa': 'LOA',
    'evaluation': 'EVALUATION'
  };
  const fileType = fileTypeMap[cardType];
  
  if (!fileSubmissions[fileType] || !fileSubmissions[fileType].submission_id) {
    Swal.fire('Error', 'Cannot comment on a file that has not been submitted.', 'error');
    return;
  }
  const submissionId = fileSubmissions[fileType].submission_id;
  const commentText = document.getElementById(`${cardType}-comment-text`).value;

  if (!commentText.trim()) {
    Swal.fire('Error', 'Comment cannot be empty.', 'error');
    return;
  }

  const formData = new FormData();
  formData.append('submission_id', submissionId);
  formData.append('file_type', fileType);
  formData.append('comment_text', commentText);

  fetch('add_comment.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      document.getElementById(`${cardType}-comment-text`).value = '';
      loadComments(cardType);
    } else {
      Swal.fire('Error', data.message, 'error');
    }
  });
}

function loadFileSubmissions() {
  const fileTypes = [
    { type: 'DTR', cardType: 'dtr' },
    { type: 'MOA', cardType: 'moa' },
    { type: 'LOA', cardType: 'loa' },
    { type: 'EVALUATION', cardType: 'evaluation' }
  ];
  
  let approvedCount = 0;
  const totalFiles = fileTypes.length;

  const fetchPromises = fileTypes.map(({ type, cardType }) => {
    return fetch(`view_files.php?type=${type}`)
      .then(res => res.json())
      .then(data => {
        const attachBtn = document.getElementById(cardType + '-attach-btn');
        const downloadBtn = document.getElementById(cardType + '-download-btn');
        const changeBtn = document.getElementById(cardType + '-change-btn');
        const submitBtn = document.getElementById(cardType + '-submit-btn');
        const preview = document.getElementById(cardType + '-preview');
        const filename = document.getElementById(cardType + '-filename');
        const removeBtn = document.getElementById(cardType + '-remove-btn');
        const approvedStatus = document.getElementById(cardType + '-approved-status');
        
        if (data.success && data.files.length > 0) {
          const fileData = data.files[0];
          fileSubmissions[type] = { submission_id: fileData.submission_id };
          filename.textContent = fileData.filename;
          preview.style.display = 'block';
          removeBtn.style.display = 'none';
          attachBtn.style.display = 'none';
          submitBtn.style.display = 'none';
          
          if (fileData.checked) {
            approvedCount++;
            downloadBtn.style.display = 'inline-block';
            changeBtn.style.display = 'none';
            approvedStatus.style.display = 'inline-block';
          } else {
            downloadBtn.style.display = 'inline-block';
            changeBtn.style.display = 'inline-block';
            approvedStatus.style.display = 'none';
          }
        } else {
          preview.style.display = 'none';
          attachBtn.style.display = 'inline-block';
          downloadBtn.style.display = 'none';
          changeBtn.style.display = 'none';
          submitBtn.style.display = 'none';
          approvedStatus.style.display = 'none';
        }
      })
      .catch(() => {
        console.error(`Error loading ${type} file status`);
      });
  });

  Promise.all(fetchPromises).then(() => {
    updateFileProgressCircle(approvedCount, totalFiles);
  });
}

document.addEventListener('DOMContentLoaded', function() {
    const progressFill = document.getElementById('attendanceProgress');
    const progressPercent = <?php echo $progressPercent; ?>;

    if (progressPercent < 30) {
        progressFill.style.backgroundColor = '#dc3545'; // Red
    } else if (progressPercent < 70) {
        progressFill.style.backgroundColor = '#ffc107'; // Orange
    } else {
        progressFill.style.backgroundColor = '#28a745'; // Green
    }

    // Handle manual time request form submission
    const manualTimeForm = document.getElementById('manualTimeForm');
    if (manualTimeForm) {
        manualTimeForm.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData(this);

            fetch('submit_manual_request.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: 'Manual time request submitted successfully.',
                        confirmButtonText: 'OK'
                    }).then(() => {
                        // Reset the form
                        manualTimeForm.reset();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Failed to submit request.',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while submitting the request.',
                    confirmButtonText: 'OK'
                });
            });
        });
    }
});
</script>
<style>
  /* General styling for task cards */
  .tasks-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
    margin-top: 20px;
  }

  .task-item {
    background: #fff;
    border-radius: 8px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    display: flex;
    flex-direction: column;
    gap: 10px;
  }

  .task-item .task-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
  }

  .task-item h4 {
    margin: 0;
    color: #116530;
    font-size: 1.2rem;
  }

  .task-item .task-status {
    padding: 5px 10px;
    border-radius: 15px;
    font-size: 0.8rem;
    font-weight: bold;
    text-transform: uppercase;
  }

  .task-item .task-details p {
    margin: 5px 0;
    font-size: 0.9rem;
    color: #555;
  }

  .task-item .button, .task-item button {
    background-color: #116530;
    color: white;
    padding: 8px 15px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    text-decoration: none;
    display: inline-block;
    font-size: 0.9rem;
    transition: background-color 0.3s ease;
  }

  .task-item .button:hover, .task-item button:hover {
    background-color: #0e5128;
  }

  /* Attachments Section */
  .attachments-section {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
  }

  .attachments-section h5 {
    margin-top: 0;
    margin-bottom: 10px;
    color: #116530;
    font-size: 1rem;
  }

  .attachments-section p {
    margin: 5px 0;
    font-size: 0.9rem;
  }

  /* Comments Section */
  .comments-section {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid #eee;
  }

  .comments-section h5.comments-header {
    margin-top: 0;
    margin-bottom: 10px;
    color: #116530;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }

  .comments-section .comments-container {
    background: #f9f9f9;
    border: 1px solid #eee;
    border-radius: 5px;
    padding: 10px;
    max-height: 200px;
    overflow-y: auto;
  }

  .comments-section .comments-list {
    margin-bottom: 10px;
  }

  .comments-section .comment {
    border-bottom: 1px solid #e0e0e0;
    padding: 8px 0;
  }

  .comments-section .comment:last-child {
    border-bottom: none;
  }

  .comments-section .comment p {
    margin: 0;
    font-size: 0.85rem;
    color: #333;
  }

  .comments-section .comment .comment-date {
    font-size: 0.75rem;
    color: #888;
    margin-left: 5px;
  }

  .comments-section .comment-form textarea {
    width: calc(100% - 10px);
    min-height: 60px;
    border: 1px solid #ccc;
    border-radius: 5px;
    padding: 5px;
    margin-bottom: 5px;
    resize: vertical;
  }

  .comments-section .comment-form button {
    width: 100%;
    padding: 8px;
    background-color: #116530;
    color: white;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 0.9rem;
  }

  .comments-section .comment-form button:hover {
    background-color: #0e5128;
  }
</style>


</body>
</html>

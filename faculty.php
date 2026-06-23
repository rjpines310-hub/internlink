<?php
session_start();
include 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'faculty') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$userId = $_SESSION['user_id'];
$firstname = '';
$lastname = '';
$profile_picture = 'uploads/dp.jpg'; // default
$faculty = [
    'faculty_id' => '',
    'firstname' => '',
    'lastname' => '',
    'email' => '',
    'contact' => '',
    'password' => ''
];

// Fetch data for Faculty Dashboard
// 1. Top Students with Most Progress (based on overall_average)
$topStudentsProgress = [];
$queryTopStudents = "SELECT s.student_id, s.firstname, s.lastname, so.overall_average
                     FROM student_overview so
                     JOIN student s ON so.student_id = s.student_id
                     ORDER BY so.overall_average DESC
                     LIMIT 3";
$resultTopStudents = $conn->query($queryTopStudents);
if ($resultTopStudents) {
    while ($row = $resultTopStudents->fetch_assoc()) {
        $topStudentsProgress[] = $row;
    }
}

// 2. Students that need the most help (lowest overall_average or incomplete requirements)
$studentsNeedHelp = [];
$queryStudentsHelp = "SELECT s.student_id, s.firstname, s.lastname, so.overall_average, so.attendance, so.performance, so.file_submissions
                      FROM student_overview so
                      JOIN student s ON so.student_id = s.student_id
                      ORDER BY so.overall_average ASC, so.attendance ASC, so.performance ASC, so.file_submissions ASC
                      LIMIT 3";
$resultStudentsHelp = $conn->query($queryStudentsHelp);
if ($resultStudentsHelp) {
    while ($row = $resultStudentsHelp->fetch_assoc()) {
        $studentsNeedHelp[] = $row;
    }
}

// 3. Companies with the most internship posts
$companiesMostPosts = [];
$queryCompaniesPosts = "SELECT ch.companyname, COUNT(ip.post_id) AS post_count
                        FROM internship_posts ip
                        JOIN companyhr ch ON ip.hr_id = ch.hr_id
                        GROUP BY ch.companyname
                        ORDER BY post_count DESC
                        LIMIT 3";
$resultCompaniesPosts = $conn->query($queryCompaniesPosts);
if ($resultCompaniesPosts) {
    while ($row = $resultCompaniesPosts->fetch_assoc()) {
        $companiesMostPosts[] = $row;
    }
}

$stmt = $conn->prepare("SELECT faculty_id, firstname, lastname, email, contact, password, profile_picture FROM faculty WHERE faculty_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($faculty['faculty_id'], $faculty['firstname'], $faculty['lastname'], $faculty['email'], $faculty['contact'], $faculty['password'], $db_picture);
$stmt->fetch();
$stmt->close();

$firstname = $faculty['firstname'];
$lastname = $faculty['lastname'];
if ($db_picture && file_exists($db_picture)) {
    $profile_picture = $db_picture;
}

    // Handle invitation form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_action']) && $_POST['form_action'] === 'send_invitation') {
        require 'send_invitation.php'; // Include the invitation sending logic
        exit; // Exit after handling the invitation to prevent further output
    }

    // Handle AJAX requests for accept/reject from JS
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && isset($_POST['request_id'])) {
        $action = $_POST['action'];
        $request_id = intval($_POST['request_id']);
        if ($action === 'accept') {
            $status = 'approved';
        } elseif ($action === 'reject') {
            $status = 'rejected';
        } else {
            http_response_code(400);
            echo 'Invalid action';
            exit;
        }

        $update = $conn->prepare("UPDATE hr_requests SET status = ? WHERE request_id = ?");
        $update->bind_param("si", $status, $request_id);
        if ($update->execute()) {
            echo 'success';
        } else {
            http_response_code(500);
            echo 'Failed to update';
        }
        $update->close();
        exit;
    }
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
  <meta charset="UTF-8" />
  <title>Faculty Dashboard | Universidad De Manila</title>
  <link rel="icon" href="logo.png" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="student.css" />
  <link rel="stylesheet" href="ojt_hours.css" /> <!-- Added ojt_hours.css link -->
  <link rel="stylesheet" href="faculty_dashboard.css" />
  <link rel="stylesheet" href="file_submissions.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Leaflet CSS and JS for interactive maps -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
  <style>
    .progress-bar-fill {
      height: 100%;
      border-radius: 10px;
      transition: width 0.3s ease;
    }
  </style>
  <!-- Leaflet plugins for additional functionality -->
  <script src="https://unpkg.com/leaflet-control-geocoder@2.4.0/dist/Control.Geocoder.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder@2.4.0/dist/Control.Geocoder.css" />
  <style>
    /* Hover and clickable style for job cards */
    .job-card {
      border: 1px solid #ccc;
      padding: 15px;
      margin-bottom: 15px;
      border-radius: 8px;
      background-color: white;
      cursor: pointer;
      transition: box-shadow 0.3s ease, background-color 0.3s ease;
    }
    .job-card:hover {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
      background-color: #f9f9f9;
    }
    .job-card h3 {
      margin: 0 0 5px 0;
    }
    .job-card-buttons button {
      margin-right: 10px;
      background-color: #116530;
      border: none;
      color: white;
      padding: 7px 14px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.9rem;
    }
    .job-card-buttons button:hover {
      background-color: #0e5128;
    }
    .job-summary {
      display: none;
      margin-top: 10px;
      padding: 10px 15px;
      border-left: 4px solid #116530;
      background-color: #eef7ee;
      border-radius: 0 8px 8px 8px;
    }
    .applicants-list {
      margin-top: 10px;
      font-size: 0.9rem;
      max-height: 150px;
      overflow-y: auto;
      border-top: 1px solid #ccc;
      padding-top: 8px;
    }
    .applicants-list ul {
      padding-left: 20px;
      margin: 5px 0;
    }
    .applicants-list li {
      margin-bottom: 4px;
    }
    /* Modal styles */
    .modal {
      display: none; 
      position: fixed; 
      z-index: 1001; 
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto; 
      background-color: rgba(0,0,0,0.4); 
    }

    .modal-content {
      background-color: #fefefe;
      margin: 5% auto; 
      padding: 20px;
      border: 1px solid #888;
      width: 80%;
      max-width: 800px;
      border-radius: 10px;
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 15px;
    }
    #resumeModal .modal-header {
      background: #f7f7f7;
      color: #333;
      border-bottom: 1px solid #e5e7eb;
      padding: 15px 20px;
      margin-bottom: 0;
      border-radius: 10px 10px 0 0;
    }
    #resumeModal .modal-content {
      padding: 0;
    }
    #resumeModal .modal-body {
      padding: 20px;
    }
    .modal-header h3 {
      margin: 0;
      flex-grow: 1;
    }
    .modal-actions {
      display: flex;
      gap: 8px;
      flex-shrink: 0;
    }

    .close-modal {
      color: #aaa;
      float: right;
      font-size: 28px;
      font-weight: bold;
      background: none;
      border: none;
      cursor: pointer;
    }

    .close-modal:hover,
    .close-modal:focus {
      color: black;
      text-decoration: none;
      cursor: pointer;
    }

    .modal-body {
      max-height: 70vh;
      overflow-y: auto;
    }
    
    /* Resume Preview Styles */
    .resume-container {
      --green: #116530;
      --green-dark: #0e5128;
      --muted: #eef7ee;
      --text: #1e293b;
      font-family: Inter, system-ui, Arial, sans-serif;
      color: var(--text);
      background: #f8f9fa;
    }
    .resume-container .card {
      background: #fff;
      border: 1px solid #e5e7eb;
      border-radius: 18px;
      box-shadow: 0 10px 30px rgba(0,0,0,.08);
      overflow: hidden;
    }
    .resume-container .header {
      display: flex;
      gap: 18px;
      align-items: center;
      padding: 22px;
      border-bottom: 1px solid #e5e7eb;
      background: linear-gradient(180deg,#ffffff,#f7fff9);
    }
    .resume-container .avatar {
      width: 82px;
      height: 82px;
      border-radius: 999px;
      object-fit: cover;
      border: 4px solid var(--green);
    }
    .resume-container .title h1 { margin: 0; font-size: 24px; line-height: 1.2; }
    .resume-container .sub { margin-top: 6px; font-size: 14px; color: #475569; }
    .resume-container .chips { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
    .resume-container .chip { background: var(--muted); color: var(--green); padding: 6px 10px; border-radius: 999px; font-weight: 600; font-size: 12px; }
    .resume-container .content { padding: 24px; }
    .resume-container section { margin-bottom: 24px; }
    .resume-container section h2 {
      margin: 0 0 12px 0;
      font-size: 18px;
      color: var(--green);
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .resume-container .block { background: #ffffff; border: 1px solid #e5e7eb; border-radius: 14px; padding: 16px; }
    .resume-container .row { display: flex; gap: 12px; justify-content: space-between; align-items: flex-start; }
    .resume-container .left { font-weight: 600; }
    .resume-container .muted { color: #64748b; }
    .resume-container ul { margin: 0; padding-left: 18px; }
    .resume-container li { margin: 6px 0; }
    .resume-container .grid { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }
    .resume-container .skill-grid { display: grid; grid-template-columns: repeat(auto-fit,minmax(180px,1fr)); gap: 10px; }
    .resume-container .skill { background: #f8fafc; border: 1px solid #e5e7eb; border-radius: 10px; padding: 10px; }
    .resume-container .actions { display: flex; gap: 8px; margin-top: 16px; flex-wrap: wrap; }
    .resume-container .btn { appearance: none; border: none; padding: 10px 14px; border-radius: 10px; font-weight: 600; cursor: pointer; }
    .resume-container .btn-primary { background: var(--green); color: #fff; }
    .resume-container .btn-primary:hover { background: var(--green-dark); }
    .resume-container .btn-ghost { background: #fff; border: 1px solid #e5e7eb; transition: background-color 0.2s ease; }
    .resume-container .btn-ghost:hover { background-color: #f9fafb; }
    .resume-container .form-control { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }

    /* Ensure modal action buttons are styled correctly */
    #resumeModal .modal-actions .btn {
        appearance: none;
        border: 1px solid transparent;
        padding: 8px 12px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        font-size: 14px;
        transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
    }
    #resumeModal .modal-actions .btn-primary {
        background-color: #116530;
        color: #fff;
        border-color: #116530;
    }
    #resumeModal .modal-actions .btn-primary:hover {
        background-color: #0e5128;
        border-color: #0e5128;
    }
    #resumeModal .modal-actions .btn-ghost {
        background-color: #fff;
        border-color: #d1d5db;
        color: #374151;
    }
    #resumeModal .modal-actions .btn-ghost:hover {
        background-color: #f0f0f0;
        border-color: #9ca3af;
    }

    /* Interview Modal Specific Styles */
    #interviewModal .modal-content {
      max-width: 500px;
    }

    #interviewModal .form-control {
      width: 100%;
      padding: 10px;
      margin-top: 5px;
      margin-bottom: 15px;
      border: 1px solid #ccc;
      border-radius: 6px;
      box-sizing: border-box; /* Important */
    }

    #interviewModal label {
      font-weight: 600;
      display: block;
      margin-top: 10px;
    }

    #interviewModal .btn-primary {
      background-color: #116530;
      color: white;
      padding: 12px 20px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      width: 100%;
      font-size: 16px;
    }

    #interviewModal .btn-primary:hover {
      background-color: #0e5128;
    }
    .manual-entry-badge {
      background-color: #ffc107; /* Yellow color for manual entry */
      color: #343a40;
      padding: 3px 8px;
      border-radius: 5px;
      font-size: 0.75rem;
      font-weight: bold;
      margin-left: 8px;
      vertical-align: middle;
    }
    .student-suggestions {
      border: 1px solid #ccc;
      max-height: 150px;
      overflow-y: auto;
      position: absolute;
      background-color: white;
      width: calc(100% - 16px); /* Adjust based on padding of parent form */
      z-index: 100;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      left: 8px; /* Align with input field */
      right: 8px; /* Align with input field */
      border-radius: 0 0 4px 4px;
    }
    .student-suggestion-item {
      padding: 8px;
      cursor: pointer;
    }
    .student-suggestion-item:hover {
      background-color: #f0f0f0;
    }
    .no-results, .error-message {
      padding: 8px;
      color: #666;
      font-style: italic;
    }
    .mark-completed-btn {
      background-color: #28a745; /* Green success button */
      color: white;
      padding: 8px 12px;
      border: none;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.9rem;
      margin-top: 10px;
      transition: background-color 0.3s ease;
    }
    .mark-completed-btn:hover {
      background-color: #218838;
    }
    .completed-badge {
      background-color: #116530; /* Darker green for completed badge */
      color: white;
      padding: 5px 10px;
      border-radius: 5px;
      font-size: 0.85rem;
      font-weight: bold;
      margin-top: 10px;
      display: inline-block;
    }
  </style>
  <script>
function markOjtCompleted(studentId, buttonElement) {
  Swal.fire({
    title: 'Are you sure?',
    text: "Do you want to mark this student's OJT as completed?",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#28a745',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, mark as completed!'
  }).then((result) => {
    if (result.isConfirmed) {
      fetch('mark_ojt_completed.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          student_id: studentId
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Completed!',
            text: data.message,
            confirmButtonColor: '#116530'
          }).then(() => {
            // Update UI: replace button with badge and reload page
            const parentCard = buttonElement.closest('.company-card');
            if (parentCard) {
              buttonElement.remove(); // Remove the button
              const completedBadge = document.createElement('span');
              completedBadge.className = 'completed-badge';
              completedBadge.textContent = 'OJT Completed';
              parentCard.appendChild(completedBadge);
              // Optionally, reload the page to reflect the status change everywhere
              location.reload();
            }
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: data.message,
            confirmButtonColor: '#116530'
          });
        }
      })
      .catch(error => {
        console.error('Error marking OJT completed:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: 'An error occurred while marking OJT as completed.',
          confirmButtonColor: '#116530'
        });
      });
    }
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

// Moved showTab and related functions to global scope
function showTab(tabId) {
  const allContents = [
    "mainContent",
    "profileContent",
    "studentOverviewContent",
    "companiesContent",
    "ojtHoursContent", // Added new tab content
    "logAttendanceContent",
    "performanceContent",
    "fileSubmissionsContent",
    "messageContent",
    "sendInvitationsContent",
    "geoLocationContent"
  ];
  allContents.forEach((id) => {
    const el = document.getElementById(id);
    if (el) {
      if (id === tabId) {
        el.classList.add("active");
      } else {
        el.classList.remove("active");
      }
    }
  });

  // Specific initialization for geoLocationContent
  if (tabId === "geoLocationContent") {
    const hrId = document.getElementById('currentCompanyHrId').value;
    if (hrId) {
      loadActiveLocation(hrId);
    } else {
      // If no hrId is set (e.g., first time opening tab without selecting company),
      // initialize map with default view and clear location info.
      document.getElementById('activeLocationText').innerHTML = `Please select a company to set its geofence.`;
      initializeMap(); // Initialize map without specific location
    }
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

function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  sidebar.classList.toggle('mobile-open');
}

function showProfile() {
  showTab('profileContent');
}

function goHome() {
  showTab('mainContent');
}

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
  document.getElementById('actionButtons').style.display = 'flex';
}

function cancelEdit() {
  // Reset all fields and re-disable inputs
  const form = document.getElementById("profileForm");
  form.reset();

  const inputs = form.querySelectorAll("input");
  inputs.forEach(input => {
    if (input.type !== 'hidden' && input.id !== 'confirm_password') {
      input.disabled = true;
    }
  });

  document.getElementById("passwordFields").style.display = "none";
  document.getElementById("password_display").style.display = "inline-block";
  document.getElementById('actionButtons').style.display = 'none';
}

function saveProfile() {
  const form = document.getElementById("profileForm");
  const formData = new FormData(form);
  const newPassword = formData.get("password");
  const confirmPassword = document.getElementById("confirm_password").value;

  if (newPassword && newPassword !== confirmPassword) {
    Swal.fire({
      icon: 'error',
      title: 'Password Mismatch',
      text: 'Passwords do not match.',
      confirmButtonColor: '#116530'
    });
    return;
  }

  fetch("update_profile.php", {
    method: "POST",
    body: formData
  })
  .then(res => res.text())
  .then(res => {
    Swal.fire({
      icon: 'success',
      title: 'Success!',
      text: 'Profile updated successfully!',
      confirmButtonColor: '#116530'
    }).then(() => {
      location.reload();
    });
  }).catch(err => {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Error updating profile.',
      confirmButtonColor: '#116530'
    });
  });
}
    // Handle accept or reject button click
function handleRequest(request_id, action, buttonElem) {
  Swal.fire({
    title: 'Are you sure?',
    text: `Do you want to ${action} this company request?`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#116530',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, ' + action + ' it!'
  }).then((result) => {
    if (result.isConfirmed) {
      fetch('', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          request_id: request_id,
          action: action
        })
      })
        .then(response => response.text())
        .then(data => {
          if (data.trim() === 'success') {
            // Remove card from pending list
            const card = buttonElem.closest('.company-card');
            card.remove();

            // Show success message and reload
            Swal.fire({
              icon: 'success',
              title: 'Success!',
              text: `Company request ${action}ed successfully.`,
              confirmButtonColor: '#116530'
            }).then(() => {
              location.reload(); // Reload to refresh lists
            });
          } else {
            Swal.fire({
              icon: 'error',
              title: 'Error!',
              text: 'Failed to update status: ' + data,
              confirmButtonColor: '#116530'
            });
          }
        })
        .catch(err => {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'An error occurred: ' + err.message,
            confirmButtonColor: '#116530'
          });
        });
    }
  });
}

// Event listener for dropdowns
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

        // Event listener for "Mark as Completed" buttons
        document.querySelectorAll('.mark-completed-btn').forEach(button => {
            button.addEventListener('click', function() {
                const studentId = this.dataset.studentId;
                markOjtCompleted(studentId, this);
            });
        });

        // Event listener for "Give Feedback" buttons
        document.querySelectorAll('.give-feedback-btn').forEach(button => {
            button.addEventListener('click', function() {
                const studentId = this.dataset.studentId;
                const studentName = this.dataset.studentName;
                openFeedbackModal(studentId, studentName);
            });
        });

        // Handle feedback form submission
        const feedbackForm = document.getElementById('feedbackForm');
        if (feedbackForm) {
            feedbackForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                
                try {
                    const response = await fetch('submit_feedback.php', { // This file will be created next
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        Swal.fire('Success', result.message, 'success').then(() => {
                            closeFeedbackModal();
                            location.reload(); // Reload to update button status
                        });
                    } else {
                        Swal.fire('Error', result.message, 'error');
                    }
                } catch (error) {
                    console.error('Error submitting feedback:', error);
                    Swal.fire('Error', 'An unexpected error occurred while submitting feedback.', 'error');
                }
            });
        }
});

// Improved messaging functionality
let currentConversation = null;
let messageRefreshInterval = null;
let conversationRefreshInterval = null;
const currentUserId = <?php echo json_encode((int)$userId); ?>;
let currentAnnouncementId = null; // Global variable to store the ID of the announcement being edited


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
  fetch('fetch_messages.php?action=conversations&user_type=faculty')
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
  fetch(`fetch_messages.php?action=messages&other_type=${other_type}&other_id=${other_id}&user_type=faculty`)
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
  formData.append('sender_type', 'faculty');
  
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
  
  fetch(`fetch_messages.php?action=search_users&query=${encodeURIComponent(query)}&user_type=faculty`)
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
  Swal.fire({
    icon: 'error',
    title: 'Error',
    text: message,
    confirmButtonColor: '#116530'
  });
}

    // Clean up when leaving messaging tab
    function cleanupMessaging() {
      stopAutoRefresh();
      currentConversation = null;
    }

    // Announcement Modal Functions
    function openAnnouncementModal(announcement = null) {
      console.log('openAnnouncementModal called with announcement:', announcement);
      const modal = document.getElementById('announcementModal');
      const titleInput = document.getElementById('announcementTitle');
      const contentInput = document.getElementById('announcementContent');
      const audienceCheckboxes = document.querySelectorAll('#announcementAudienceCheckboxes input[type="checkbox"]');
      const announcementIdInput = document.getElementById('announcementId');
      const modalTitle = document.getElementById('announcementModalTitle');
      const submitButton = modal.querySelector('.save-btn');

      // Reset checkboxes
      audienceCheckboxes.forEach(checkbox => checkbox.checked = false);

      if (announcement) {
        modalTitle.textContent = 'Edit Announcement';
        titleInput.value = announcement.title;
        contentInput.value = announcement.content;
        announcementIdInput.value = announcement.id;
        submitButton.textContent = 'Update';
        console.log('announcementIdInput.value set to:', announcementIdInput.value);

        // Set checked state for audience checkboxes
        let audiencesArray = [];
        if (Array.isArray(announcement.audiences)) {
            audiencesArray = announcement.audiences;
        }
        
        audiencesArray.forEach(aud => {
            const checkbox = document.querySelector(`#announcementAudienceCheckboxes input[value="${aud.trim()}"]`);
            if (checkbox) checkbox.checked = true;
        });
      } else {
        modalTitle.textContent = 'Post New Announcement';
        titleInput.value = '';
        contentInput.value = '';
        announcementIdInput.value = ''; // Ensure it's empty for new posts
        submitButton.textContent = 'Post';
        console.log('announcementIdInput.value reset for new post:', announcementIdInput.value);
      }
      modal.style.display = 'flex';
      console.log('announcementModal display style:', modal.style.display);
    }

    function closeAnnouncementModal() {
      console.log('closeAnnouncementModal called');
      document.getElementById('announcementModal').style.display = 'none';
      document.getElementById('announcementForm').reset();
      document.getElementById('announcementId').value = ''; // Explicitly clear hidden ID
    }

    // Function to load and display faculty's own announcements
    async function loadFacultyAnnouncements() {
      const announcementsContainer = document.getElementById('facultyAnnouncementsContainer');
      if (!announcementsContainer) return;

      announcementsContainer.innerHTML = '<p>Loading announcements...</p>';

      try {
        const response = await fetch('fetch_faculty_announcements.php'); // Create this file
        const data = await response.json();

        if (data.success) {
          if (data.announcements.length > 0) {
            let html = '';
            data.announcements.forEach(announcement => {
              html += `
                <div class="company-card announcement-card">
                  <h4>${escapeHtml(announcement.title)}</h4>
                  <p>${escapeHtml(announcement.content)}</p>
                  <p><strong>Who can see:</strong> ${escapeHtml(announcement.audiences.join(', '))}</p>
                  <p><strong>Posted:</strong> ${new Date(announcement.date_posted).toLocaleString()}</p>
                  <div class="company-card-buttons">
                    <button onclick="editAnnouncement(${announcement.id}, '${escapeHtml(announcement.title)}', '${escapeHtml(announcement.content)}', ${JSON.stringify(announcement.audiences)})">Edit</button>
                    <button class="secondary" onclick="deleteAnnouncement(${announcement.id})">Delete</button>
                  </div>
                </div>
              `;
            });
            announcementsContainer.innerHTML = html;
          } else {
            announcementsContainer.innerHTML = '<p>No announcements posted yet.</p>';
          }
        } else {
          announcementsContainer.innerHTML = `<p>Error loading announcements: ${data.message || 'Unknown error'}</p>`;
        }
      } catch (error) {
        console.error('Error fetching faculty announcements:', error);
        announcementsContainer.innerHTML = '<p>Error loading announcements.</p>';
      }
    }

    function editAnnouncement(id, title, content, audiences) {
      console.log('editAnnouncement called with id:', id, 'audiences:', audiences);
      openAnnouncementModal({ id, title, content, audiences });
    }

    // Handle announcement form submission
    document.addEventListener('DOMContentLoaded', () => {
      const announcementForm = document.getElementById('announcementForm');
      if (announcementForm) {
        announcementForm.addEventListener('submit', async function(e) {
          e.preventDefault();

          const formData = new FormData(this);
          const selectedAudiences = Array.from(document.querySelectorAll('#announcementAudienceCheckboxes input[name="audience[]"]:checked')).map(cb => cb.value);
          
          if (selectedAudiences.length === 0) {
            Swal.fire('Error', 'Please select at least one audience.', 'error');
            return;
          }

          // Remove individual audience field if it exists from previous structure
          formData.delete('audience');
          // Append selected audiences as an array
          selectedAudiences.forEach(audience => {
            formData.append('audience[]', audience);
          });

          const announcementId = document.getElementById('announcementId').value; // Directly get value from input
          console.log('Submitting form. announcementId (direct):', announcementId);
          
          // Ensure formData contains the correct announcement_id for updates
          if (announcementId) {
              formData.set('announcement_id', announcementId);
          } else {
              formData.delete('announcement_id'); // Ensure it's not sent if empty
          }

          const url = announcementId ? 'update_announcement.php' : 'post_announcement.php';
          console.log('Submission URL:', url);

          try {
            const response = await fetch(url, {
              method: 'POST',
              body: formData
            });
            const result = await response.json();

            if (result.success) {
              Swal.fire('Success', result.message, 'success').then(() => {
                closeAnnouncementModal();
                loadFacultyAnnouncements(); // Reload announcements after post/update
              });
            } else {
              Swal.fire('Error', result.message, 'error');
            }
          } catch (error) {
            console.error('Error submitting announcement:', error);
            Swal.fire('Error', 'An unexpected error occurred while submitting the announcement.', 'error');
          }
        });
      }
    });

    // Function to load and display faculty's own announcements
    async function loadFacultyAnnouncements() {
      const announcementsContainer = document.getElementById('facultyAnnouncementsContainer');
      if (!announcementsContainer) return;

      announcementsContainer.innerHTML = '<p>Loading announcements...</p>';

      try {
        const response = await fetch('fetch_faculty_announcements.php'); // Create this file
        const data = await response.json();

        if (data.success) {
          if (data.announcements.length > 0) {
            let html = '';
            data.announcements.forEach(announcement => {
              html += `
                <div class="company-card announcement-card">
                  <h4>${escapeHtml(announcement.title)}</h4>
                  <p>${escapeHtml(announcement.content)}</p>
                  <p><strong>Who can see:</strong> ${escapeHtml(announcement.audiences.join(', '))}</p>
                  <p><strong>Posted:</strong> ${new Date(announcement.date_posted).toLocaleString()}</p>
                  <div class="company-card-buttons">
                    <button onclick="editAnnouncement(${announcement.id}, '${escapeHtml(announcement.title)}', '${escapeHtml(announcement.content)}', ${JSON.stringify(announcement.audiences)})">Edit</button>
                    <button class="secondary" onclick="deleteAnnouncement(${announcement.id})">Delete</button>
                  </div>
                </div>
              `;
            });
            announcementsContainer.innerHTML = html;
          } else {
            announcementsContainer.innerHTML = '<p>No announcements posted yet.</p>';
          }
        } else {
          announcementsContainer.innerHTML = `<p>Error loading announcements: ${data.message || 'Unknown error'}</p>`;
        }
      } catch (error) {
        console.error('Error fetching faculty announcements:', error);
        announcementsContainer.innerHTML = '<p>Error loading announcements.</p>';
      }
    }

    function editAnnouncement(id, title, content, audiences) {
      openAnnouncementModal({ id, title, content, audiences });
    }

    function deleteAnnouncement(id) {
      Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#116530',
        confirmButtonText: 'Yes, delete it!'
      }).then(async (result) => {
        if (result.isConfirmed) {
          try {
            const response = await fetch('delete_announcement.php', { // Create this file
              method: 'POST',
              headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
              },
              body: `announcement_id=${id}`
            });
            const data = await response.json();

            if (data.success) {
              Swal.fire('Deleted!', data.message, 'success').then(() => {
                loadFacultyAnnouncements(); // Reload announcements after deletion
              });
            } else {
              Swal.fire('Error!', data.message, 'error');
            }
          } catch (error) {
            console.error('Error deleting announcement:', error);
            Swal.fire('Error!', 'An error occurred while deleting the announcement.', 'error');
          }
        }
      });
    }

    // Call loadFacultyAnnouncements when the mainContent tab is activated
    document.addEventListener('DOMContentLoaded', () => {
      // Initial load if mainContent is active by default
      if (document.getElementById('mainContent').classList.contains('active')) {
        loadFacultyAnnouncements();
        loadPendingPartnershipRequests(); // Load partnership requests on initial load
      }
    });

    // Override showTab to include announcement and partnership request loading
    const originalShowTab = showTab;
    showTab = function(tabId) {
      originalShowTab(tabId);
      if (tabId === 'mainContent') {
        loadFacultyAnnouncements();
      }
      if (tabId === 'companiesContent') {
        startPartnershipRequestAutoRefresh();
      } else {
        stopPartnershipRequestAutoRefresh();
      }
    };

    // Real-time partnership request refresh for Faculty
    let partnershipRequestRefreshInterval = null;

    function startPartnershipRequestAutoRefresh() {
      if (partnershipRequestRefreshInterval) {
        clearInterval(partnershipRequestRefreshInterval);
      }
      partnershipRequestRefreshInterval = setInterval(loadPendingPartnershipRequests, 10000); // Refresh every 10 seconds
    }

    function stopPartnershipRequestAutoRefresh() {
      if (partnershipRequestRefreshInterval) {
        clearInterval(partnershipRequestRefreshInterval);
      }
    }

    // Modify showTab to manage partnership request auto-refresh
    const originalShowTabForPartnership = showTab;
    showTab = function(tabId) {
      originalShowTabForPartnership(tabId);
      if (tabId === 'companiesContent') {
        startPartnershipRequestAutoRefresh();
      } else {
        stopPartnershipRequestAutoRefresh();
      }
    };

    // Function to load and display pending partnership requests
    async function loadPendingPartnershipRequests() {
      const pendingCompaniesCards = document.getElementById('pendingCompaniesCards');
      if (!pendingCompaniesCards) return;

      try {
        const response = await fetch('fetch_partnership_requests_faculty.php');
        const data = await response.json();

        if (data.success) {
          pendingCompaniesCards.innerHTML = ''; // Clear existing requests
          if (data.requests.length > 0) {
            data.requests.forEach(request => {
              const companyCard = document.createElement('div');
              companyCard.className = 'company-card';
              companyCard.dataset.hrid = request.hr_id;
              // Assuming a default profile picture if not available
              const profilePicture = 'uploads/dp.jpg'; // You might want to fetch actual company profile pictures
              companyCard.innerHTML = `
                <img src="${profilePicture}" alt="Company Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">
                <h4>${escapeHtml(request.companyname)}</h4>
                <p>${escapeHtml(request.location)}</p>
                <p>${escapeHtml(request.email)}</p>
                <div class="company-card-buttons">
                  <button onclick="handleRequest(${request.request_id}, 'accept', this)">Accept</button>
                  <button class="secondary" onclick="handleRequest(${request.request_id}, 'reject', this)">Reject</button>
                </div>
              `;
              pendingCompaniesCards.appendChild(companyCard);
            });
          } else {
            pendingCompaniesCards.innerHTML = '<p>No pending requests found.</p>';
          }
        } else {
          console.error('Error loading pending partnership requests:', data.error || 'Unknown error');
          pendingCompaniesCards.innerHTML = `<p>Error loading pending requests: ${data.error || 'Unknown error'}</p>`;
        }
      } catch (error) {
        console.error('Error fetching pending partnership requests:', error);
        pendingCompaniesCards.innerHTML = '<p>Error loading pending requests.</p>';
      }
    }

    // OJT Hours management functions
    function enableEditOjtHours(sectionName) {
      const inputField = document.getElementById(`ojt_hours_${sectionName}`);
      const actionButtons = document.getElementById(`ojtActionButtons_${sectionName}`);
      inputField.disabled = false;
      inputField.focus();
      actionButtons.style.display = 'flex';
    }

    function cancelEditOjtHours(sectionName, originalValue) {
      const inputField = document.getElementById(`ojt_hours_${sectionName}`);
      const actionButtons = document.getElementById(`ojtActionButtons_${sectionName}`);
      inputField.value = originalValue; // Revert to original value
      inputField.disabled = true;
      actionButtons.style.display = 'none';
    }

    function saveOjtHours(sectionName) {
      const inputField = document.getElementById(`ojt_hours_${sectionName}`);
      const newOjtHours = parseInt(inputField.value);

      if (isNaN(newOjtHours) || newOjtHours < 0) {
        Swal.fire({
          icon: 'error',
          title: 'Invalid Input',
          text: 'Please enter a valid non-negative number for OJT hours.',
          confirmButtonColor: '#116530'
        });
        return;
      }

      fetch('update_ojt_hours.php', { // This file will be created next
        method: 'POST',
        headers: {
          'Content-Type': 'application/json'
        },
        body: JSON.stringify({
          section_name: sectionName,
          ojt_hours: newOjtHours
        })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: 'OJT hours updated successfully.',
            confirmButtonColor: '#116530'
          }).then(() => {
            // Update the original value for future cancels
            const actionButtons = document.getElementById(`ojtActionButtons_${sectionName}`);
            actionButtons.style.display = 'none';
            inputField.disabled = true;
            // Optionally, reload the page or just update the displayed value
            // location.reload(); 
          });
        } else {
          Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: data.message || 'Failed to update OJT hours.',
            confirmButtonColor: '#116530'
          });
        }
      })
      .catch(error => {
        console.error('Error updating OJT hours:', error);
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: 'An error occurred while updating OJT hours.',
          confirmButtonColor: '#116530'
        });
      });
    }

    // Handle invitation form submission
    document.addEventListener('DOMContentLoaded', () => {
      const sendInvitationForm = document.getElementById('sendInvitationForm');
      if (sendInvitationForm) {
        sendInvitationForm.addEventListener('submit', async function(e) {
          e.preventDefault();

          const email = document.getElementById('inviteEmail').value;
          const role = document.getElementById('inviteRole').value;

          if (!email || !role) {
            Swal.fire('Error', 'Please fill in both email and role.', 'error');
            return;
          }

          const formData = new FormData();
          formData.append('form_action', 'send_invitation');
          formData.append('email', email);
          formData.append('role', role);

          try {
            const response = await fetch('faculty.php', {
              method: 'POST',
              body: formData
            });
            const result = await response.json();

            if (result.success) {
              Swal.fire('Success', result.message, 'success');
              sendInvitationForm.reset();
            } else {
              Swal.fire('Error', result.message, 'error');
            }
          } catch (error) {
            console.error('Error sending invitation:', error);
            Swal.fire('Error', 'An unexpected error occurred while sending the invitation.', 'error');
          }
        });
      }
    });

function filterOverviewStudents() {
  const searchInput = document.getElementById('overviewStudentSearchInput').value.toLowerCase();
  const sectionInput = document.getElementById('overviewSectionFilterInput').value;
  const studentCards = document.querySelectorAll('#studentOverviewGrid .company-card');

  studentCards.forEach(card => {
    const name = card.dataset.name;
    const email = card.dataset.email;
    const section = card.dataset.section;

    const searchMatch = name.includes(searchInput) || email.includes(searchInput);
    const sectionMatch = sectionInput === '' || section === sectionInput;

    if (searchMatch && sectionMatch) {
      card.style.display = '';
    } else {
      card.style.display = 'none';
    }
  });
}

function filterStudents() {
  const searchInput = document.getElementById('performanceStudentSearchInput').value.toLowerCase();
  const sectionInput = document.getElementById('sectionFilterInput').value;
  const studentCards = document.querySelectorAll('#studentPerformanceGrid .student-card');

  studentCards.forEach(card => {
    const name = card.dataset.name;
    const section = card.dataset.section;

    const nameMatch = name.includes(searchInput);
    const sectionMatch = sectionInput === '' || section === sectionInput;

    if (nameMatch && sectionMatch) {
      card.style.display = '';
    } else {
      card.style.display = 'none';
    }
  });
}

function filterStudentList() {
    const searchInput = document.getElementById('fileSubmissionsStudentSearchInput').value.toLowerCase();
    const sectionInput = document.getElementById('fileSubmissionsSectionFilterInput').value;
    const studentItems = document.querySelectorAll('#student-list .student-list-item');

    studentItems.forEach(item => {
        const name = item.dataset.name;
        const section = item.dataset.section;

        const nameMatch = name.includes(searchInput);
        const sectionMatch = sectionInput === '' || section === sectionInput;

        if (nameMatch && sectionMatch) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

function loadStudentFiles(studentId) {
    const studentDetailsHeader = document.getElementById('student-details-header');
    const cardsContainer = document.getElementById('file-submission-cards');
    
    studentDetailsHeader.style.display = 'none';
    cardsContainer.innerHTML = '<p>Loading files...</p>';
    cardsContainer.style.display = 'grid';

    document.querySelectorAll('.student-list-item').forEach(item => item.classList.remove('active'));
    if (event && event.currentTarget) {
        event.currentTarget.classList.add('active');
    }

    fetch(`fetch_student_files.php?student_id=${studentId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const student = data.student;
                document.getElementById('student-details-img').src = student.profile_picture || 'uploads/dp.jpg';
                document.getElementById('student-details-name').textContent = student.name;
                document.getElementById('student-details-id').textContent = `Student ID: ${student.studentid}`;
                studentDetailsHeader.style.display = 'flex';

                const approvedCount = data.approved_count || 0;
                const totalFiles = 4;
                const percentage = totalFiles > 0 ? (approvedCount / totalFiles) * 100 : 0;
                const progressDegrees = percentage * 3.6;

                const progressCircle = document.getElementById('file-progress-circle');
                const progressValue = document.getElementById('file-progress-value');
                progressCircle.style.background = `conic-gradient(#116530 ${progressDegrees}deg, #e0e0e0 0deg)`;
                progressValue.textContent = `${approvedCount}/${totalFiles}`;

                const files = data.files;
                const fileTypes = {
                    'dtr_file': { displayName: 'DTR', identifier: 'DTR' },
                    'moa_file': { displayName: 'Memorandum of Agreement', identifier: 'MOA' },
                    'letter_of_acceptance_file': { displayName: 'Letter of Acceptance', identifier: 'LOA' },
                    'evaluation_form_file': { displayName: 'Evaluation Form', identifier: 'EVALUATION' }
                };

                let html = '';
                for (const fileTypeKey in fileTypes) {
                    const fileInfo = fileTypes[fileTypeKey];
                    const fileName = files ? files[fileTypeKey] : null;
                    const checked = files ? files[fileTypeKey + '_checked'] : false;
                    const submissionId = files ? files.submission_id : null;

                    html += `<div class="file-card" id="${fileInfo.identifier.toLowerCase()}-card">`;
                    html += `<h4>${fileInfo.displayName}</h4>`;

                    if (fileName) {
                        html += `<p><strong>File:</strong> ${fileName}</p>`;
                        html += `<div class="file-actions">`;
                        html += `<a href="uploads/files/${fileName}" target="_blank" class="button">View</a>`;
                        html += `<a href="uploads/files/${fileName}" download class="button">Download</a>`;
                        if (checked) {
                            html += `<span class="approved-text">Approved</span>`;
                        } else {
                            html += `<button onclick="approveFile(this, ${submissionId}, '${fileTypeKey}')">Approve</button>`;
                        }
                        html += `</div>`;
                        
                        html += `<div class="comments-section">`;
                        html += `<div class="comments-header" onclick="toggleFacultyComments(${submissionId}, '${fileInfo.identifier}')">Comments</div>`;
                        html += `<div class="comments-container" id="faculty-comments-${submissionId}-${fileInfo.identifier}" style="display:none;">`;
                        html += `<div class="comments-list" id="faculty-comments-list-${submissionId}-${fileInfo.identifier}"></div>`;
                        html += `<div class="comment-form">`;
                        html += `<textarea id="faculty-comment-text-${submissionId}-${fileInfo.identifier}" placeholder="Add a comment..."></textarea>`;
                        html += `<button onclick="addFacultyComment(${submissionId}, '${fileInfo.identifier}')">Post</button>`;
                        html += `</div></div></div>`;

                    } else {
                        html += `<p>Not Submitted</p>`;
                    }
                    html += `</div>`;
                }
                cardsContainer.innerHTML = html;
            } else {
                studentDetailsHeader.style.display = 'none';
                cardsContainer.innerHTML = `<p>${data.message || 'No files submitted by this student.'}</p>`;
            }
        })
        .catch(error => {
            console.error('Error fetching files:', error);
            cardsContainer.innerHTML = '<p>Error loading files.</p>';
        });
}

let commentInterval;

function toggleFacultyComments(submissionId, fileType) {
  const container = document.getElementById(`faculty-comments-${submissionId}-${fileType}`);
  if (container.style.display === 'none') {
    container.style.display = 'block';
    loadFacultyComments(submissionId, fileType);
    commentInterval = setInterval(() => loadFacultyComments(submissionId, fileType), 5000); // Refresh every 5 seconds
  } else {
    container.style.display = 'none';
    clearInterval(commentInterval);
  }
}

function loadFacultyComments(submissionId, fileType) {
  fetch(`fetch_comments.php?submission_id=${submissionId}&file_type=${fileType}`)
    .then(res => res.json())
    .then(data => {
      const list = document.getElementById(`faculty-comments-list-${submissionId}-${fileType}`);
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

function addFacultyComment(submissionId, fileType) {
  const commentText = document.getElementById(`faculty-comment-text-${submissionId}-${fileType}`).value;

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
      document.getElementById(`faculty-comment-text-${submissionId}-${fileType}`).value = '';
      loadFacultyComments(submissionId, fileType);
    } else {
      Swal.fire('Error', data.message, 'error');
    }
  });
}

function approveFile(button, submissionId, fileType) {
  button.disabled = true;
  button.textContent = 'Approving...';

  fetch('approve_file.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      submission_id: submissionId,
      file_type: fileType
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      button.outerHTML = '<span class="approved-text">Approved</span>';
    } else {
      alert('Failed to approve file: ' + (data.message || 'Unknown error'));
      button.disabled = false;
      button.textContent = 'Approve';
    }
  })
  .catch(error => {
    console.error('Error approving file:', error);
    alert('Error approving file');
    button.disabled = false;
    button.textContent = 'Approve';
  });
}

function viewInterns(companyId) {
  const modal = document.getElementById('internsModal');
  const modalBody = document.getElementById('internsModalBody');
  modalBody.innerHTML = '<p>Loading interns...</p>';
  modal.style.display = 'flex';

  fetch(`fetch_company_interns.php?hr_id=${companyId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        if (data.interns.length > 0) {
          let html = '<ul class="interns-list">';
          data.interns.forEach(intern => {
            html += `<li>${intern.firstname} ${intern.lastname} (${intern.email})</li>`;
          });
          html += '</ul>';
          modalBody.innerHTML = html;
        } else {
          modalBody.innerHTML = '<p>No interns found for this company.</p>';
        }
      } else {
        modalBody.innerHTML = `<p>${data.message || 'Error loading interns'}</p>`;
      }
    })
    .catch(error => {
      console.error('Error fetching interns:', error);
      modalBody.innerHTML = '<p>Error loading interns</p>';
    });
}

function showStudentTaskSummary(studentId) {
  const modal = document.getElementById('studentTaskModal');
  const modalBody = document.getElementById('studentTaskModalBody');
  modalBody.innerHTML = '<p>Loading summary...</p>';
  modal.style.display = 'flex';

  fetch(`fetch_student_task_summary.php?student_id=${studentId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        let html = `
            <h4>${data.student_name}</h4>
            <p>Student ID: ${data.studentid}</p>
            <div class="task-summary" style="justify-content: center; gap: 20px;">
                <div class="task-count"><strong>${data.total_tasks}</strong><span>All Tasks</span></div>
                <div class="task-count"><strong>${data.completed_tasks}</strong><span>Completed</span></div>
                <div class="task-count"><strong>${data.submitted_tasks}</strong><span>Submitted</span></div>
                <div class="task-count"><strong>${data.missed_tasks}</strong><span>Missed</span></div>
            </div>
        `;
        modalBody.innerHTML = html;
      } else {
        modalBody.innerHTML = `<p>${data.message || 'Error loading summary'}</p>`;
      }
    })
    .catch(error => {
      console.error('Error fetching task summary:', error);
      modalBody.innerHTML = '<p>Error loading summary</p>';
    });
}

function filterAttendanceStudents() {
  const searchInput = document.getElementById('attendanceStudentSearchInput').value.toLowerCase();
  const sectionInput = document.getElementById('attendanceSectionFilterInput').value;
  const studentCards = document.querySelectorAll('#studentAttendanceGrid .company-card');

  studentCards.forEach(card => {
    const name = card.dataset.name;
    const studentid = card.dataset.studentid;
    const section = card.dataset.section;

    const searchMatch = name.includes(searchInput) || studentid.includes(searchInput);
    const sectionMatch = sectionInput === '' || section === sectionInput;

    if (searchMatch && sectionMatch) {
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

  // We will create this fetch file in the next step
  fetch(`fetch_student_attendance.php?student_id=${studentId}`)
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        let html = `
          <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 20px;">
            <img src="${data.profile_picture}" alt="Student Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover;">
            <div>
              <h4>${data.student_name}</h4>
              <p>Student ID: ${data.studentid}</p>
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

let map;
let marker;
let circle;
let geocoder;
let mapInitialized = false; // Flag to track map initialization

function handleSetGeoLocation(hr_id, companyName) {
  document.getElementById('currentCompanyHrId').value = hr_id; // Store hr_id in hidden input
  document.getElementById('currentCompanyName').textContent = companyName; // Display company name
  console.log('Selected Company HR ID:', hr_id); // Debugging: Log the HR ID
  showTab('geoLocationContent'); // Show the new tab
  loadActiveLocation(hr_id); // Load existing location
}

function loadActiveLocation(hr_id) {
  fetch(`fetch_faculty_active_location.php?hr_id=${hr_id}`, { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
      const activeLocationText = document.getElementById('activeLocationText');
      const currentCompanyName = document.getElementById('currentCompanyName').textContent;
      const saveBtn = document.getElementById('saveBtn');

      if (data.success && data.location) {
        window.currentLocation = data.location; // Store for potential editing
        activeLocationText.innerHTML = `<strong>${data.location.location_name}</strong> (Lat: ${data.location.latitude}, Lng: ${data.location.longitude}, Radius: ${data.location.radius}m)`;
        saveBtn.textContent = 'Update Location'; // Change button text
        initializeMap(data.location.latitude, data.location.longitude, data.location.radius, data.location.location_name);
      } else {
        window.currentLocation = null; // No active location
        activeLocationText.innerHTML = `No active location set for <strong>${currentCompanyName}</strong>.`;
        saveBtn.textContent = 'Save Location'; // Change button text
        initializeMap(); // Initialize map with default view
      }
    })
    .catch(err => {
      console.error('Error loading active location:', err);
      document.getElementById('activeLocationText').textContent = 'Error loading location.';
      document.getElementById('saveBtn').textContent = 'Save Location'; // Reset button text on error
      initializeMap(); // Initialize map with default view on error
    });
}

function initializeMap(initialLat = 12.8797, initialLng = 121.7740, initialRadius = 100, initialLocationName = '') {
  // Remove existing map if any and reinitialize
  if (map) {
    map.remove();
    map = null;
    mapInitialized = false;
  }

  // Initialize map centered on provided coordinates or Philippines default
  map = L.map('mapContainer').setView([initialLat, initialLng], initialLocationName ? 13 : 6); // Zoom in if a specific location is provided

  // Add OpenStreetMap tiles
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  // Initialize geocoder (not added to map to avoid built-in control)
  geocoder = L.Control.geocoder({
    defaultMarkGeocode: false
  });

  // Add event listener for save button
  const saveBtn = document.getElementById('saveBtn');
  if (saveBtn) {
    saveBtn.onclick = function() {
      saveGeofenceLocation();
    };
  }

  // Add search functionality
  const searchBtn = document.getElementById('searchBtn');
  if (searchBtn) {
    searchBtn.onclick = function() {
      const query = document.getElementById('mapSearch').value.trim();
      if (query) {
        geocoder.geocode(query, function(results) {
          if (results.length > 0) {
            const result = results[0];
            map.setView(result.center, 13);
            placeMarker(result.center);
            document.getElementById('selectedLocation').value = result.name;
            hideMapSearchSuggestions();
          }
        });
      }
    };
  }

  // Add input event listener for suggestions
  const mapSearch = document.getElementById('mapSearch');
  if (mapSearch) {
    mapSearch.addEventListener('input', debounce(showMapSearchSuggestions, 300));
  }

  // Allow clicking on map to place marker
  map.on('click', function(e) {
    placeMarker(e.latlng);
  });

  // If initial location name is provided, set it
  if (initialLocationName) {
    document.getElementById('selectedLocation').value = initialLocationName;
  }

  // Place marker and circle if initial coordinates are provided (i.e., an active geofence exists)
  if (initialLat !== 12.8797 || initialLng !== 121.7740 || initialLocationName) { // Check if it's not the default Philippines center
    const latlng = L.latLng(initialLat, initialLng);
    placeMarker(latlng, initialRadius);
    document.getElementById('locationRadius').value = initialRadius;
  } else if (navigator.geolocation) {
    // Otherwise, try to get user's current location
    navigator.geolocation.getCurrentPosition(function(position) {
      const latlng = L.latLng(position.coords.latitude, position.coords.longitude);
      map.setView(latlng, 13);
      placeMarker(latlng);
    }, function(error) {
      console.log('Geolocation error:', error);
    });
  } else {
    console.log('Geolocation not supported');
  }
  mapInitialized = true; // Set flag to true after initialization
}

function placeMarker(latlng, initialRadius = 100) {
  // Remove existing marker and circle
  if (marker) {
    map.removeLayer(marker);
  }
  if (circle) {
    map.removeLayer(circle);
  }

  // Add new marker
  marker = L.marker(latlng, {draggable: true}).addTo(map);

  // Add circle with initial radius
  const radius = initialRadius; // Use initialRadius if provided, otherwise default
  circle = L.circle(latlng, {
    color: '#116530',
    fillColor: '#116530',
    fillOpacity: 0.2,
    radius: radius
  }).addTo(map);

  // Make circle resizable by dragging edge
  circle.on('mousedown', function(e) {
    map.dragging.disable();
    const originalLatLng = circle.getLatLng();

    function onMouseMove(e) {
      const distance = map.distance(originalLatLng, e.latlng);
      circle.setRadius(distance);
      document.getElementById('locationRadius').value = Math.round(distance);
    }

    function onMouseUp(e) {
      map.off('mousemove', onMouseMove);
      map.off('mouseup', onMouseUp);
      map.dragging.enable();
    }

    map.on('mousemove', onMouseMove);
    map.on('mouseup', onMouseUp);
  });

  // Update position when marker is dragged
  marker.on('dragend', function(e) {
    const newLatLng = e.target.getLatLng();
    circle.setLatLng(newLatLng);
    updateLocationFields(newLatLng);
  });

  // Update location fields
  updateLocationFields(latlng);

  // Fit map to show marker and circle
  const bounds = L.latLngBounds([latlng]).extend(circle.getBounds());
  map.fitBounds(bounds, {padding: [20, 20]});
}

function updateLocationFields(latlng) {
  document.getElementById('latitude').value = latlng.lat;
  document.getElementById('longitude').value = latlng.lng;

  // Reverse geocode to get location name using local proxy
  fetch(`faculty_reverse_geocode.php?lat=${latlng.lat}&lon=${latlng.lng}`)
    .then(response => response.json())
    .then(data => {
      if (data && data.display_name) {
        document.getElementById('selectedLocation').value = data.display_name;
      } else {
        document.getElementById('selectedLocation').value = `Location at ${latlng.lat.toFixed(4)}, ${latlng.lng.toFixed(4)}`;
      }
    })
    .catch(error => {
      console.log('Reverse geocoding error:', error);
      document.getElementById('selectedLocation').value = `Location at ${latlng.lat.toFixed(4)}, ${latlng.lng.toFixed(4)}`;
    });
}

// Update circle radius when input changes
document.addEventListener('DOMContentLoaded', () => {
  const locationRadiusInput = document.getElementById('locationRadius');
  if (locationRadiusInput) {
    locationRadiusInput.addEventListener('input', function() {
      const radius = parseInt(this.value);
      if (circle && radius >= 1 && radius <= 1000) {
        circle.setRadius(radius);
        if (marker) {
          const bounds = L.latLngBounds([marker.getLatLng()]).extend(circle.getBounds());
          map.fitBounds(bounds, {padding: [20, 20]});
        }
      }
    });
  }
});


function saveGeofenceLocation() {
  const locationName = document.getElementById('selectedLocation').value.trim();
  const radius = parseInt(document.getElementById('locationRadius').value);
  const lat = document.getElementById('latitude').value;
  const lng = document.getElementById('longitude').value;
  const hrId = document.getElementById('currentCompanyHrId').value; // Get hr_id from hidden input

  if (!locationName || !lat || !lng || isNaN(radius) || !hrId) {
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'Please fill in all fields, select a location on the map, and ensure a company is selected.',
      confirmButtonColor: '#116530'
    });
    return;
  }

  fetch('save_faculty_location.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      hr_id: hrId, // Use hr_id from hidden input
      location_name: locationName,
      lat: lat,
      lng: lng,
      radius: radius
    })
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: 'Geo location set successfully.',
        confirmButtonColor: '#116530'
      }).then(() => {
        // After saving, reload active location and re-initialize map
        loadActiveLocation(hrId);
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: data.message || 'Failed to set geo location.',
        confirmButtonColor: '#116530'
      });
    }
  })
  .catch(error => {
    console.error('Error setting geo location:', error);
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'An error occurred while setting geo location.',
      confirmButtonColor: '#116530'
    });
  });
}

// Show map search suggestions
function showMapSearchSuggestions() {
  const query = document.getElementById('mapSearch').value.trim();
  const suggestionsDiv = document.getElementById('mapSearchSuggestions');

  if (query.length < 2) {
    suggestionsDiv.style.display = 'none';
    return;
  }

  // Use Nominatim API for suggestions
  fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&countrycodes=ph`)
    .then(response => response.json())
    .then(data => {
      suggestionsDiv.innerHTML = '';
      if (data.length > 0) {
        data.forEach(item => {
          const div = document.createElement('div');
          div.className = 'map-search-suggestion';
          div.textContent = item.display_name;
          div.style.padding = '8px';
          div.style.cursor = 'pointer';
          div.style.borderBottom = '1px solid #eee';
          div.onmouseover = () => div.style.backgroundColor = '#f0f0f0';
          div.onmouseout = () => div.style.backgroundColor = '';
          div.onclick = () => selectMapSearchSuggestion({ name: item.display_name, center: L.latLng(item.lat, item.lon) });
          suggestionsDiv.appendChild(div);
        });
        suggestionsDiv.style.display = 'block';
      } else {
        suggestionsDiv.style.display = 'none';
      }
    })
    .catch(err => {
      console.error('Error fetching suggestions:', err);
      suggestionsDiv.style.display = 'none';
    });
}

// Select map search suggestion
function selectMapSearchSuggestion(result) {
  document.getElementById('mapSearch').value = result.name;
  document.getElementById('selectedLocation').value = result.name;
  document.getElementById('mapSearchSuggestions').style.display = 'none';
  map.setView(result.center, 13);
  placeMarker(result.center);
}

// Hide map search suggestions
function hideMapSearchSuggestions() {
  document.getElementById('mapSearchSuggestions').style.display = 'none';
}

// Add event listener for location search input
document.addEventListener('DOMContentLoaded', () => {
  const mapSearchInput = document.getElementById('mapSearch');
  if (mapSearchInput) {
    mapSearchInput.addEventListener('input', debounce(showMapSearchSuggestions, 300));
  }
});

function cancelLocationEdit() {
  // Simply reload the active location to revert any unsaved changes and reset the map
  const hrId = document.getElementById('currentCompanyHrId').value;
  if (hrId) {
    loadActiveLocation(hrId);
  } else {
    // If no hrId, just re-initialize map to default state
    initializeMap();
  }
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
}

// New Company Modal Functions
let manualMap;
let manualMarker;
let manualCircle;
let createMap;
let createMarker;
let createCircle;

function openNewCompanyModal() {
  document.getElementById('newCompanyModal').style.display = 'flex';
  resetNewCompanyModal();
}

function closeNewCompanyModal() {
  document.getElementById('newCompanyModal').style.display = 'none';
  // Clean up maps if they were initialized
  if (manualMap) manualMap.remove();
  if (createMap) createMap.remove();
  manualMap = null;
  createMap = null;
  manualMarker = null;
  manualCircle = null;
  createMarker = null;
  createCircle = null;
}

function resetNewCompanyModal() {
  document.getElementById('newCompanyOptions').style.display = 'block';
  document.getElementById('manualCompanyForm').style.display = 'none';
  document.getElementById('createAccountForm').style.display = 'none';
  
  // Reset forms
  document.getElementById('manualCompanyDetailsForm').reset();
  document.getElementById('createCompanyAccountForm').reset();

  // Clear map containers and remove map instances if they exist
  if (manualMap) {
    manualMap.remove();
    manualMap = null;
    manualMarker = null;
    manualCircle = null;
    document.getElementById('manualMapContainer').innerHTML = '';
  }
  if (createMap) {
    createMap.remove();
    createMap = null;
    createMarker = null;
    createCircle = null;
    document.getElementById('createMapContainer').innerHTML = '';
  }
}

function showManualCompanyForm() {
  document.getElementById('newCompanyOptions').style.display = 'none';
  document.getElementById('manualCompanyForm').style.display = 'block';
  initializeNewCompanyMap('manualMapContainer', 'manual_latitude', 'manual_longitude', 'manual_selected_location', 'manual_radius', 'manualMap', 'manualMarker', 'manualCircle');
}

function showCreateAccountForm() {
  document.getElementById('newCompanyOptions').style.display = 'none';
  document.getElementById('createAccountForm').style.display = 'block';
  initializeNewCompanyMap('createMapContainer', 'create_latitude', 'create_longitude', 'create_selected_location', 'create_radius', 'createMap', 'createMarker', 'createCircle');
}

function initializeNewCompanyMap(containerId, latInputId, lngInputId, selectedLocationId, radiusInputId, mapVarName) {
  let currentMap;

  if (mapVarName === 'manualMap') {
    currentMap = manualMap;
  } else if (mapVarName === 'createMap') {
    currentMap = createMap;
  }

  if (currentMap) {
    currentMap.invalidateSize();
    return;
  }

  currentMap = L.map(containerId).setView([12.8797, 121.7740], 6); // Centered on Philippines

  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(currentMap);

  const currentGeocoder = L.Control.geocoder({
    defaultMarkGeocode: false
  });

  // Add search functionality for the new company forms
  const searchInputId = containerId.replace('MapContainer', 'MapSearch');
  const searchButtonId = containerId.replace('MapContainer', 'SearchBtn');
  const suggestionsContainerId = containerId.replace('MapContainer', 'SearchSuggestions');

  const searchButtonElement = document.getElementById(searchButtonId);
  if (searchButtonElement) { // Check if element exists before adding listener
    searchButtonElement.addEventListener('click', function() {
      const query = document.getElementById(searchInputId).value.trim();
      if (query) {
        currentGeocoder.geocode(query, function(results) {
          if (results.length > 0) {
            const result = results[0];
            currentMap.setView(result.center, 13);
            placeNewCompanyMarker(currentMap, result.center, latInputId, lngInputId, selectedLocationId, radiusInputId, mapVarName);
            document.getElementById(selectedLocationId).value = result.name;
            hideNewCompanyMapSearchSuggestions(suggestionsContainerId);
          }
        });
      }
    });
  } else {
    console.error(`Element with ID ${searchButtonId} not found.`);
  }


  const searchInputElement = document.getElementById(searchInputId);
  if (searchInputElement) { // Check if element exists before adding listener
    searchInputElement.addEventListener('input', debounce(function() {
      showNewCompanyMapSearchSuggestions(document.getElementById(searchInputId).value, suggestionsContainerId, currentMap, latInputId, lngInputId, selectedLocationId, radiusInputId, mapVarName);
    }, 300));
  } else {
    console.error(`Element with ID ${searchInputId} not found.`);
  }

  currentMap.on('click', function(e) {
    placeNewCompanyMarker(currentMap, e.latlng, latInputId, lngInputId, selectedLocationId, radiusInputId, mapVarName);
  });

  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
      const latlng = L.latLng(position.coords.latitude, position.coords.longitude);
      currentMap.setView(latlng, 13);
      placeNewCompanyMarker(currentMap, latlng, latInputId, lngInputId, selectedLocationId, radiusInputId, mapVarName);
    }, function(error) {
      console.log('Geolocation error:', error);
    });
  } else {
    console.log('Geolocation not supported');
  }

  if (mapVarName === 'manualMap') {
    manualMap = currentMap;
  } else if (mapVarName === 'createMap') {
    createMap = currentMap;
  }
}

function placeNewCompanyMarker(mapInstance, latlng, latInputId, lngInputId, selectedLocationId, radiusInputId, mapVarName) {
  let targetMarker, targetCircle;

  if (mapVarName === 'manualMap') {
    targetMarker = manualMarker;
    targetCircle = manualCircle;
  } else if (mapVarName === 'createMap') {
    targetMarker = createMarker;
    targetCircle = createCircle;
  }

  if (targetMarker) {
    mapInstance.removeLayer(targetMarker);
  }
  if (targetCircle) {
    mapInstance.removeLayer(targetCircle);
  }

  targetMarker = L.marker(latlng, {draggable: true}).addTo(mapInstance);
  const radius = parseInt(document.getElementById(radiusInputId).value) || 100;
  targetCircle = L.circle(latlng, {
    color: '#116530',
    fillColor: '#116530',
    fillOpacity: 0.2,
    radius: radius
  }).addTo(mapInstance);

  // Update the global variables directly
  if (mapVarName === 'manualMap') {
    manualMarker = targetMarker;
    manualCircle = targetCircle;
  } else if (mapVarName === 'createMap') {
    createMarker = targetMarker;
    createCircle = targetCircle;
  }

  targetCircle.on('mousedown', function(e) {
    mapInstance.dragging.disable();
    const originalLatLng = targetCircle.getLatLng();

    function onMouseMove(e) {
      const distance = mapInstance.distance(originalLatLng, e.latlng);
      targetCircle.setRadius(distance);
      document.getElementById(radiusInputId).value = Math.round(distance);
    }

    function onMouseUp() {
      mapInstance.off('mousemove', onMouseMove);
      mapInstance.off('mouseup', onMouseUp);
      mapInstance.dragging.enable();
    }

    mapInstance.on('mousemove', onMouseMove);
    mapInstance.on('mouseup', onMouseUp);
  });

  targetMarker.on('dragend', function(e) {
    const newLatLng = e.target.getLatLng();
    targetCircle.setLatLng(newLatLng);
    updateNewCompanyLocationFields(newLatLng, latInputId, lngInputId, selectedLocationId);
  });

  updateNewCompanyLocationFields(latlng, latInputId, lngInputId, selectedLocationId);

  const bounds = L.latLngBounds([latlng]).extend(targetCircle.getBounds());
  mapInstance.fitBounds(bounds, {padding: [20, 20]});
}

function updateNewCompanyLocationFields(latlng, latInputId, lngInputId, selectedLocationId) {
  document.getElementById(latInputId).value = latlng.lat;
  document.getElementById(lngInputId).value = latlng.lng;

  fetch(`faculty_reverse_geocode.php?lat=${latlng.lat}&lon=${latlng.lng}`)
    .then(response => response.json())
    .then(data => {
      if (data && data.display_name) {
        document.getElementById(selectedLocationId).value = data.display_name;
      } else {
        document.getElementById(selectedLocationId).value = `Location at ${latlng.lat.toFixed(4)}, ${latlng.lng.toFixed(4)}`;
      }
    })
    .catch(error => {
      console.log('Reverse geocoding error:', error);
      document.getElementById(selectedLocationId).value = `Location at ${latlng.lat.toFixed(4)}, ${latlng.lng.toFixed(4)}`;
    });
}

// Show map search suggestions for new company forms
async function showNewCompanyMapSearchSuggestions(query, suggestionsContainerId, mapInstance, latInputId, lngInputId, selectedLocationId, radiusInputId, mapVarName) {
  const suggestionsDiv = document.getElementById(suggestionsContainerId);

  if (query.length < 2) {
    suggestionsDiv.innerHTML = '';
    suggestionsDiv.style.display = 'none';
    return;
  }

  try {
    const response = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5&countrycodes=ph`);
    const data = await response.json();

    suggestionsDiv.innerHTML = '';
    if (data.length > 0) {
      data.forEach(item => {
        const div = document.createElement('div');
        div.className = 'map-search-suggestion';
        div.textContent = item.display_name;
        div.onclick = () => {
          document.getElementById(suggestionsContainerId.replace('Suggestions', '')).value = item.display_name;
          document.getElementById(selectedLocationId).value = item.display_name;
          suggestionsDiv.style.display = 'none';
          const latlng = L.latLng(item.lat, item.lon);
          mapInstance.setView(latlng, 13);
          placeNewCompanyMarker(mapInstance, latlng, latInputId, lngInputId, selectedLocationId, radiusInputId, mapVarName);
        };
        suggestionsDiv.appendChild(div);
      });
      suggestionsContainer.style.display = 'block';
    } else {
      suggestionsDiv.style.display = 'none';
    }
  } catch (err) {
    console.error('Error fetching suggestions:', err);
    suggestionsDiv.style.display = 'none';
  }
}

// Hide map search suggestions for new company forms
function hideNewCompanyMapSearchSuggestions(suggestionsContainerId) {
  document.getElementById(suggestionsContainerId).style.display = 'none';
}

// Event listeners for radius input in new company modal
document.addEventListener('DOMContentLoaded', () => {
  const manualRadiusInput = document.getElementById('manual_radius');
  if (manualRadiusInput) {
    manualRadiusInput.addEventListener('input', function() {
      const radius = parseInt(this.value);
      if (manualCircle && radius >= 1 && radius <= 1000) {
        manualCircle.setRadius(radius);
        if (manualMarker) {
          const bounds = L.latLngBounds([manualMarker.getLatLng()]).extend(manualCircle.getBounds());
          manualMap.fitBounds(bounds, {padding: [20, 20]});
        }
      }
    });
  }

  const createRadiusInput = document.getElementById('create_radius');
  if (createRadiusInput) {
    createRadiusInput.addEventListener('input', function() {
      const radius = parseInt(this.value);
      if (createCircle && radius >= 1 && radius <= 1000) {
        createCircle.setRadius(radius);
        if (createMarker) {
          const bounds = L.latLngBounds([createMarker.getLatLng()]).extend(createCircle.getBounds());
          createMap.fitBounds(bounds, {padding: [20, 20]});
        }
      }
    });
  }
});

// Form submission handlers
const manualForm = document.getElementById('manualCompanyDetailsForm');
if (manualForm) {
  manualForm.addEventListener('submit', function(e) {
    e.preventDefault();
    saveManualCompany();
  });
}

const createForm = document.getElementById('createCompanyAccountForm');
if (createForm) {
  createForm.addEventListener('submit', function(e) {
    e.preventDefault();
    createCompanyAccount();
  });
}

async function saveManualCompany() {
  const form = document.getElementById('manualCompanyDetailsForm');
  const formData = new FormData(form);

  // Add manual flag
  formData.append('manual', 'yes');

  // Basic validation
  if (!formData.get('companyname') || !formData.get('location') || !formData.get('email') || !formData.get('contact') || !formData.get('student_post') || !formData.get('latitude') || !formData.get('longitude')) {
    Swal.fire('Error', 'Please fill all required fields and select a location on the map.', 'error');
    return;
  }

  try {
    const response = await fetch('manual_company_signup.php', {
      method: 'POST',
      body: formData
    });

    if (!response.ok) {
      const errorText = await response.text();
      console.error('Server error:', response.status, errorText);
      Swal.fire('Error', `Server error: ${response.status} - ${errorText}`, 'error');
      return;
    }

    const result = await response.json();

    if (result.success) {
      Swal.fire('Success', 'Manual company added successfully!', 'success').then(() => {
        closeNewCompanyModal();
        addCompanyCardToDisplay(result.company_data);
      });
    } else {
      Swal.fire('Error', result.message || 'Failed to add manual company.', 'error');
    }
  } catch (error) {
    console.error('Error adding manual company:', error);
    Swal.fire('Error', 'An unexpected error occurred. Check console for details.', 'error');
  }
}

async function createCompanyAccount() {
  const form = document.getElementById('createCompanyAccountForm');
  const formData = new FormData(form);

  const password = formData.get('password');
  const confirmPassword = formData.get('confirm');

  if (password !== confirmPassword) {
    Swal.fire('Error', 'Passwords do not match.', 'error');
    return;
  }

  // Add manual flag
  formData.append('manual', 'no');

  // Basic validation
  if (!formData.get('companyname') || !formData.get('location') || !formData.get('landline') || !formData.get('email') || !formData.get('contact') || !password || !confirmPassword || !formData.get('latitude') || !formData.get('longitude')) {
    Swal.fire('Error', 'Please fill all required fields and select a location on the map.', 'error');
    return;
  }

  try {
    const response = await fetch('signup.php', { // Re-use signup.php for company account creation
      method: 'POST',
      body: formData
    });
    
    if (!response.ok) {
      const errorText = await response.text();
      console.error('Server error:', response.status, errorText);
      Swal.fire('Error', `Server error: ${response.status} - ${errorText}`, 'error');
      return;
    }

    const result = await response.json();

    if (result.status === 'success') {
      Swal.fire('Success', 'Company account created successfully!', 'success').then(() => {
        closeNewCompanyModal();
        addCompanyCardToDisplay(result.company_data); // Assuming signup.php returns company_data
      });
    } else {
      Swal.fire('Error', result.message || 'Failed to create company account.', 'error');
    }
  } catch (error) {
    console.error('Error creating company account:', error);
    Swal.fire('Error', 'An unexpected error occurred. Check console for details.', 'error');
  }
}

// Function to search for unhired students
async function searchStudents(query, suggestionsContainerId, hiddenInputId) {
  const suggestionsContainer = document.getElementById(suggestionsContainerId);
  if (query.length < 2) {
    suggestionsContainer.innerHTML = '';
    suggestionsContainer.style.display = 'none';
    return;
  }

  try {
    const response = await fetch(`fetch_unhired_students.php?query=${encodeURIComponent(query)}`);
    const data = await response.json();

    suggestionsContainer.innerHTML = '';
    if (data.success && data.students.length > 0) {
      data.students.forEach(student => {
        const div = document.createElement('div');
        div.className = 'student-suggestion-item';
        div.textContent = `${student.name} (${student.email})`;
        div.onclick = () => {
          document.getElementById(suggestionsContainerId.replace('_suggestions', '_search')).value = student.name;
          document.getElementById(hiddenInputId).value = student.id;
          suggestionsContainer.style.display = 'none';
        };
        suggestionsContainer.appendChild(div);
      });
      suggestionsContainer.style.display = 'block';
    } else {
      suggestionsContainer.innerHTML = '<div class="no-results">No unhired students found.</div>';
      suggestionsContainer.style.display = 'block';
    }
  } catch (error) {
    console.error('Error searching students:', error);
    suggestionsContainer.innerHTML = '<div class="error-message">Error searching students.</div>';
    suggestionsContainer.style.display = 'block';
  }
}

// Function to add a new company card to the display dynamically
function addCompanyCardToDisplay(companyData) {
  const registeredCompaniesCards = document.getElementById('registeredCompaniesCards');
  const company_picture = companyData.profile_picture && companyData.profile_picture !== '' ? companyData.profile_picture : 'uploads/dp.jpg';
  const manualBadge = companyData.manual === 'yes' ? '<span class="manual-entry-badge">Manual Entry</span>' : '';

  const newCard = document.createElement('div');
  newCard.className = 'company-card';
  newCard.innerHTML = `
    <img src="${company_picture}" alt="Company Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">
    <h4>${companyData.companyname} ${manualBadge}</h4>
    <p>${companyData.location}</p>
    <p>${companyData.email}</p>
    <div class="company-card-buttons">
      <button onclick="viewInterns(${companyData.hr_id})">View Interns</button>
      <button onclick="handleSetGeoLocation(${companyData.hr_id}, '${companyData.companyname}')">Set Geo Location</button>
    </div>
  `;
  registeredCompaniesCards.prepend(newCard); // Add to the beginning of the list
}
  </script>
</head>
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
            <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture" />
            <div class="overlay">Change Picture</div>
          </div>
        </label>
        <input type="file" id="profileInput" name="profile_picture" onchange="document.getElementById('uploadForm').submit();" />
      </form>
      <div class="student-name"><?php echo htmlspecialchars($firstname . ' ' . $lastname); ?></div>
      <a href="#" onclick="showTab('studentOverviewContent')">Student Overview</a>
      <a href="#" onclick="showTab('companiesContent')">Companies</a>
      <a href="#" onclick="showTab('logAttendanceContent')">Log Attendance</a>
      <a href="#" onclick="showTab('performanceContent')">Performance</a>
      <a href="#" onclick="showTab('fileSubmissionsContent')">File Submissions</a>
      <a href="#" onclick="showTab('messageContent')">Message</a>
      <a href="#" onclick="showTab('sendInvitationsContent')">Send Invitations</a>
    </div>

    <div class="main-content active" id="mainContent">
      <h2 class="welcome-message">Welcome, <?php echo htmlspecialchars($firstname . ' ' . $lastname); ?>!</h2>

      <div class="dashboard-cards-grid">
        <!-- Faculty announcements will be loaded here -->
        <div class="kard announcement-kard">
          <div class="kard-header">
            <h3>My Announcements</h3>
          </div>
          <div id="facultyAnnouncementsContainer" class="kard-content">Loading announcements...</div>
          <button class="new-company-btn" onclick="openAnnouncementModal()">Post Announcement</button>
        </div>

        <!-- Top Students with Most Progress -->
        <div class="kard">
          <div class="kard-header">
            <h3>Top Performing Interns</h3>
          </div>
          <div id="topStudentsProgress" class="kard-content">
            <?php if (!empty($topStudentsProgress)): ?>
              <div class="card-grid">
                <?php foreach ($topStudentsProgress as $student):
                  // Fetch student's profile picture and company name
                  $student_picture = 'uploads/dp.jpg'; // default
                  $company_name = 'N/A';

                  $stmt_student_details = $conn->prepare("SELECT s.profile_picture, ch.companyname FROM student s LEFT JOIN companyhr ch ON s.hr_id = ch.hr_id WHERE s.student_id = ?");
                  if ($stmt_student_details) {
                      $stmt_student_details->bind_param("i", $student['student_id']);
                      $stmt_student_details->execute();
                      $result_student_details = $stmt_student_details->get_result();
                      if ($row_student_details = $result_student_details->fetch_assoc()) {
                          if (!empty($row_student_details['profile_picture']) && file_exists($row_student_details['profile_picture'])) {
                              $student_picture = $row_student_details['profile_picture'];
                          }
                          $company_name = htmlspecialchars($row_student_details['companyname'] ?? 'N/A');
                      }
                      $stmt_student_details->close();
                  }
                ?>
                  <div class="company-card">
                    <img src="<?php echo htmlspecialchars($student_picture); ?>" alt="Profile Picture" class="profile-logo">
                    <h4 class="card-title"><?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></h4>
                    <p class="card-summary">Overall Average: <?php echo htmlspecialchars(round($student['overall_average'], 2)); ?>%</p>
                    <p class="card-summary">Company: <?php echo $company_name; ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="no-data">No data available.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Students that Need the Most Help -->
        <div class="kard">
          <div class="kard-header">
            <h3>Interns Who Need the Most Help</h3>
          </div>
          <div id="studentsNeedHelp" class="kard-content">
            <?php if (!empty($studentsNeedHelp)): ?>
              <div class="card-grid">
                <?php foreach ($studentsNeedHelp as $student):
                  // Fetch student's profile picture and company name
                  $student_picture = 'uploads/dp.jpg'; // default
                  $company_name = 'N/A';

                  $stmt_student_details = $conn->prepare("SELECT s.profile_picture, ch.companyname FROM student s LEFT JOIN companyhr ch ON s.hr_id = ch.hr_id WHERE s.student_id = ?");
                  if ($stmt_student_details) {
                      $stmt_student_details->bind_param("i", $student['student_id']);
                      $stmt_student_details->execute();
                      $result_student_details = $stmt_student_details->get_result();
                      if ($row_student_details = $result_student_details->fetch_assoc()) {
                          if (!empty($row_student_details['profile_picture']) && file_exists($row_student_details['profile_picture'])) {
                              $student_picture = $row_student_details['profile_picture'];
                          }
                          $company_name = htmlspecialchars($row_student_details['companyname'] ?? 'N/A');
                      }
                      $stmt_student_details->close();
                  }
                ?>
                  <div class="company-card">
                    <img src="<?php echo htmlspecialchars($student_picture); ?>" alt="Profile Picture" class="profile-logo">
                    <h4 class="card-title"><?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></h4>
                    <p class="card-summary">Overall Average: <?php echo htmlspecialchars(round($student['overall_average'], 2)); ?>%</p>
                    <p class="card-summary">Company: <?php echo $company_name; ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="no-data">No data available.</p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Companies with Most Internship Posts -->
        <div class="kard">
          <div class="kard-header">
            <h3>Companies with Most Internship Posts</h3>
          </div>
          <div id="companiesMostPosts" class="kard-content">
            <?php if (!empty($companiesMostPosts)): ?>
              <div class="card-grid">
                <?php foreach ($companiesMostPosts as $company):
                  // Fetch company profile picture
                  $company_picture = 'uploads/dp.jpg'; // default
                  $stmt_company_details = $conn->prepare("SELECT profile_picture FROM companyhr WHERE companyname = ?");
                  if ($stmt_company_details) {
                      $stmt_company_details->bind_param("s", $company['companyname']);
                      $stmt_company_details->execute();
                      $result_company_details = $stmt_company_details->get_result();
                      if ($row_company_details = $result_company_details->fetch_assoc()) {
                          if (!empty($row_company_details['profile_picture']) && file_exists($row_company_details['profile_picture'])) {
                              $company_picture = $row_company_details['profile_picture'];
                          }
                      }
                      $stmt_company_details->close();
                  }
                ?>
                  <div class="company-card">
                    <img src="<?php echo htmlspecialchars($company_picture); ?>" alt="Company Logo" class="profile-logo">
                    <h4 class="card-title"><?php echo htmlspecialchars($company['companyname']); ?></h4>
                    <p class="card-summary">Total Posts: <?php echo htmlspecialchars($company['post_count']); ?></p>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="no-data">No data available.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="profile" id="profileContent">
      <h2>Edit Profile</h2>
      <form id="profileForm">
        <?php foreach (['firstname','lastname','email','contact'] as $field): ?>
        <div class="form-row">
          <label><?= ucfirst($field) ?></label>
          <input type="text" name="<?= $field ?>" id="<?= $field ?>" value="<?= htmlspecialchars($faculty[$field]) ?>" disabled>
          <span class="edit-icon" onclick="enableEdit('<?= $field ?>')">&#9998;</span>
        </div>
        <?php endforeach; ?>

        <div class="form-row">
          <label>Password</label>
          <input type="password" id="password_display" value="******" disabled>
          <span class="edit-icon" onclick="enablePassword()">&#9998;</span>
        </div>
        <div id="passwordFields" style="display:none;">
          <div class="form-row"><label>New Password</label><input type="password" name="password" id="password"></div>
          <div class="form-row"><label>Confirm</label><input type="password" id="confirm_password"></div>
        </div>

        <div class="action-buttons" id="actionButtons">
          <button type="button" class="save-btn" onclick="saveProfile()">Save Changes</button>
          <button type="button" class="cancel-btn" onclick="cancelEdit()">Cancel</button>
        </div>
      </form>
      <button class="back-btn" onclick="goHome()">Back</button>
    </div>

    <!-- Student Overview tab -->
    <div class="tab-content" id="studentOverviewContent">
      <h2>Student Overview</h2>
      <div class="performance-controls">
        <input type="text" id="overviewStudentSearchInput" onkeyup="filterOverviewStudents()" placeholder="Search by name or email...">
        <select id="overviewSectionFilterInput" onchange="filterOverviewStudents()">
          <option value="">All Sections</option>
          <?php
          $sections_res_overview = $conn->query("SELECT DISTINCT section_name FROM sections ORDER BY section_name");
          if ($sections_res_overview) {
              while ($section_row = $sections_res_overview->fetch_assoc()) {
                  echo '<option value="' . htmlspecialchars($section_row['section_name']) . '">' . htmlspecialchars($section_row['section_name']) . '</option>';
              }
          }
          ?>
        </select>
      </div>
      <div class="companies-grid" id="studentOverviewGrid">
        <?php
        $overview_query = "
            SELECT 
                s.student_id, s.studentid, s.firstname, s.lastname, s.email, s.section, s.profile_picture, s.employment_status,
                COALESCE(so.attendance, 0) as attendance,
                COALESCE(so.performance, 0) as performance,
                COALESCE(so.file_submissions, 0) as file_submissions,
                sec.ojt_hours,
                (SELECT COUNT(*) FROM ojt_feedback WHERE student_id = s.student_id AND faculty_id = ? AND given_by = 'faculty') as faculty_feedback_given
            FROM student s
            LEFT JOIN student_overview so ON s.student_id = so.student_id
            LEFT JOIN sections sec ON s.section = sec.section_name
            ORDER BY s.lastname, s.firstname
        ";
        $stmt_overview = $conn->prepare($overview_query);
        $stmt_overview->bind_param("i", $userId); // Bind faculty_id for feedback check
        $stmt_overview->execute();
        $overview_res = $stmt_overview->get_result();
        // Helper function for progress bar class
        function getProgressBarClass($value) {
            if ($value <= 25) return 'red';
            elseif ($value <= 50) return 'orange';
            elseif ($value <= 75) return 'yellow';
            else return 'green';
        }
        if ($overview_res && $overview_res->num_rows > 0) {
          while ($student = $overview_res->fetch_assoc()) {
            $student_picture = 'uploads/dp.jpg';
            if (!empty($student['profile_picture']) && file_exists($student['profile_picture'])) {
              $student_picture = $student['profile_picture'];
            }
            
            $ojt_status = '';
            $ojt_status_color = '';
            if ($student['employment_status'] === 'hired') {
                $ojt_status = 'in progress';
                $ojt_status_color = 'orange';
            } elseif ($student['employment_status'] === 'completed') {
                $ojt_status = 'Completed';
                $ojt_status_color = 'green';
            } else {
                $ojt_status = 'Not Started';
                $ojt_status_color = 'red';
            }

            $attendance = (int)$student['attendance'];
            $performance = (int)$student['performance'];
            $file_submissions = (int)$student['file_submissions'];
            $ojt_hours_required = (int)$student['ojt_hours'];
        ?>
            <div class="company-card" 
                 data-name="<?php echo htmlspecialchars(strtolower($student['firstname'] . ' ' . $student['lastname'])); ?>"
                 data-email="<?php echo htmlspecialchars(strtolower($student['email'])); ?>"
                 data-section="<?php echo htmlspecialchars($student['section']); ?>">
              <img src="<?php echo htmlspecialchars($student_picture); ?>" alt="Student Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">
              <h4><?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></h4>
              <p>Student ID: <?php echo htmlspecialchars($student['studentid']); ?></p>
              <p><?php echo htmlspecialchars($student['email']); ?></p>
              <p><strong>OJT Status:</strong> <span style="color: <?php echo $ojt_status_color; ?>; font-weight: bold;"><?php echo $ojt_status; ?></span></p>
              
              <div class="progress-bars-container">
                <div class="progress-bar-container">
                  <div class="progress-bar-bg">
                    <div class="progress-bar-fill <?php echo getProgressBarClass($attendance); ?>" style="width: <?php echo $attendance; ?>%;"></div>
                  </div>
                  <span class="progress-value"><?php echo $attendance; ?>%</span>
                  <label>Attendance</label>
                </div>
                <div class="progress-bar-container">
                  <div class="progress-bar-bg">
                    <div class="progress-bar-fill <?php echo getProgressBarClass($performance); ?>" style="width: <?php echo $performance; ?>%;"></div>
                  </div>
                  <span class="progress-value"><?php echo $performance; ?>%</span>
                  <label>Performance</label>
                </div>
                <div class="progress-bar-container">
                  <div class="progress-bar-bg">
                    <div class="progress-bar-fill <?php echo getProgressBarClass($file_submissions); ?>" style="width: <?php echo $file_submissions; ?>%;"></div>
                  </div>
                  <span class="progress-value"><?php echo $file_submissions; ?>%</span>
                  <label>File Submissions</label>
                </div>
              </div>
              <?php if ($attendance >= 100 && $file_submissions >= 100 && $student['employment_status'] !== 'completed'): ?>
                <button class="mark-completed-btn" data-student-id="<?php echo $student['student_id']; ?>">Mark as Completed</button>
              <?php elseif ($student['employment_status'] === 'completed'): ?>
                <span class="completed-badge">OJT Completed</span>
                <button class="give-feedback-btn" data-student-id="<?php echo $student['student_id']; ?>" data-student-name="<?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?>" <?php echo ($student['faculty_feedback_given'] > 0) ? 'disabled' : ''; ?>>
                  <?php echo ($student['faculty_feedback_given'] > 0) ? 'Feedback Submitted' : 'Give Feedback'; ?>
                </button>
              <?php endif; ?>
            </div>
        <?php
          }
        } else {
          echo '<p>No students found.</p>';
        }
        ?>
      </div>
    </div>

    <!-- Companies tab -->
    <div class="tab-content" id="companiesContent">
      <h2>Companies</h2>

      <div class="company-section" id="registeredCompaniesSection">
        <h3>Registered Companies</h3>
        <div class="companies-grid" id="registeredCompaniesCards">
          <?php
          // Fetch approved companies from hr_requests
          $resApproved = $conn->query("SELECT hr.hr_id, hr.companyname, hr.location, hr.email, hr.contact, hr.landline, c.profile_picture FROM hr_requests hr LEFT JOIN companyhr c ON hr.hr_id = c.hr_id WHERE hr.status = 'approved' ORDER BY hr.companyname ASC");
          if ($resApproved && $resApproved->num_rows > 0) {
              while ($row = $resApproved->fetch_assoc()) {
                  $company_picture = 'uploads/dp.jpg'; // default
                  if (!empty($row['profile_picture']) && file_exists($row['profile_picture'])) {
                      $company_picture = $row['profile_picture'];
                  }
                  echo '<div class="company-card">';
                  echo '<img src="' . htmlspecialchars($company_picture) . '" alt="Company Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">';
                  echo '<h4>' . htmlspecialchars($row['companyname']) . '</h4>';
                  echo '<p>' . htmlspecialchars($row['location']) . '</p>';
                  echo '<p>' . htmlspecialchars($row['email']) . '</p>';
                  echo '<div class="company-card-buttons">';
                  echo '<button onclick="viewInterns(' . $row['hr_id'] . ')">View Interns</button>';
                  echo '<button onclick="handleSetGeoLocation(' . $row['hr_id'] . ', \'' . htmlspecialchars($row['companyname']) . '\')">Set Geo Location</button>';
                  echo '</div>';
                  echo '</div>';
              }
          } else {
              echo '<p>No registered companies found.</p>';
          }
          ?>
        </div>
      </div>

      <div class="company-section" id="pendingCompaniesSection">
        <h3>Pending Requests</h3>
        <div class="companies-grid" id="pendingCompaniesCards">
          <?php
          // Fetch pending companies from hr_requests
          $resPending = $conn->query("SELECT hr.request_id, hr.hr_id, hr.companyname, hr.location, hr.email, hr.contact, hr.landline, c.profile_picture FROM hr_requests hr LEFT JOIN companyhr c ON hr.hr_id = c.hr_id WHERE hr.status = 'pending' ORDER BY hr.companyname ASC");
          if ($resPending && $resPending->num_rows > 0) {
              while ($row = $resPending->fetch_assoc()) {
                  $hr_id = intval($row['hr_id']);
                  $company_picture = 'uploads/dp.jpg'; // default
                  if (!empty($row['profile_picture']) && file_exists($row['profile_picture'])) {
                      $company_picture = $row['profile_picture'];
                  }
                  echo '<div class="company-card" data-hrid="' . $hr_id . '">';
                  echo '<img src="' . htmlspecialchars($company_picture) . '" alt="Company Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">';
                  echo '<h4>' . htmlspecialchars($row['companyname']) . '</h4>';
                  echo '<p>' . htmlspecialchars($row['location']) . '</p>';
                  echo '<p>' . htmlspecialchars($row['email']) . '</p>';
                  echo '<div class="company-card-buttons">';
                  echo '<button onclick="handleRequest(' . $row['request_id'] . ', \'accept\', this)">Accept</button>';
                  echo '<button class="secondary" onclick="handleRequest(' . $row['request_id'] . ', \'reject\', this)">Reject</button>';
                  echo '</div>';
                  echo '</div>';
              }
          } else {
              echo '<p>No pending requests found.</p>';
          }
          ?>
        </div>
      </div>
    </div>

    <!-- Log Attendance tab -->
    <div class="tab-content" id="logAttendanceContent">
      <div style="text-align: center; width: 100%; max-width: 1000px; margin-bottom: 20px;">
        <h3 style="font-size: 1.5rem; margin-bottom: 0; color: #0b3d0b; padding-bottom: 5px; border-bottom: none;">Student Attendance Overview</h3>
      </div>
      <div class="performance-controls" style="display: flex; justify-content: center; align-items: center; gap: 10px; flex-wrap: wrap;">
        <input type="text" id="attendanceStudentSearchInput" onkeyup="filterAttendanceStudents()" placeholder="Search by name or ID..." style="flex-grow: 1; max-width: 300px;">
        <select id="attendanceSectionFilterInput" onchange="filterAttendanceStudents()" style="flex-grow: 1; max-width: 150px;">
          <option value="">All Sections</option>
          <?php
          $sections_res_attendance = $conn->query("SELECT DISTINCT section FROM student WHERE section IS NOT NULL AND section != '' ORDER BY section");
          if ($sections_res_attendance) {
              while ($section_row = $sections_res_attendance->fetch_assoc()) {
                  echo '<option value="' . htmlspecialchars($section_row['section']) . '">' . htmlspecialchars($section_row['section']) . '</option>';
              }
          }
          ?>
        </select>
        <button class="new-company-btn" onclick="showTab('ojtHoursContent')" style="white-space: nowrap;">Manage OJT Hours</button>
      </div>
      <div class="companies-grid student-attendance-grid" id="studentAttendanceGrid">
        <?php
        $attendance_query = "
            SELECT 
                s.student_id, s.studentid, s.firstname, s.lastname, s.section, s.profile_picture, s.email,
                hr.companyname
            FROM student s
            LEFT JOIN hr_requests hr ON s.hr_id = hr.hr_id
            ORDER BY s.lastname, s.firstname
        ";
        $attendance_res = $conn->query($attendance_query);
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
            
            // Fetch student's section
            $student_section_attendance = '';
            $stmt_section_attendance = $conn->prepare("SELECT section FROM student WHERE student_id = ?");
            $stmt_section_attendance->bind_param("i", $student['student_id']);
            $stmt_section_attendance->execute();
            $stmt_section_attendance->bind_result($student_section_attendance);
            $stmt_section_attendance->fetch();
            $stmt_section_attendance->close();

            // Fetch target hours from sections table based on student's section
            $targetHours = 200; // Default fallback
            if (!empty($student_section_attendance)) {
                $stmt_ojt_attendance = $conn->prepare("SELECT ojt_hours FROM sections WHERE section_name = ?");
                if ($stmt_ojt_attendance) {
                    $stmt_ojt_attendance->bind_param("s", $student_section_attendance);
                    $stmt_ojt_attendance->execute();
                    $stmt_ojt_attendance->bind_result($ojtHoursAttendance);
                    if ($stmt_ojt_attendance->fetch() && $ojtHoursAttendance > 0) {
                        $targetHours = $ojtHoursAttendance;
                    }
                    $stmt_ojt_attendance->close();
                }
            }

            $progressPercent = ($targetHours > 0) ? min(100, ($totalHours / $targetHours) * 100) : 0;
        ?>
            <div class="company-card" 
                 onclick="showStudentAttendanceDetails(<?php echo $student['student_id']; ?>)"
                 data-name="<?php echo htmlspecialchars(strtolower($student['firstname'] . ' ' . $student['lastname'])); ?>"
                 data-studentid="<?php echo htmlspecialchars($student['studentid']); ?>"
                 data-section="<?php echo htmlspecialchars($student['section']); ?>">
              <img src="<?php echo htmlspecialchars($student_picture); ?>" alt="Student Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">
              <h4><?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></h4>
              <p>Student ID: <?php echo htmlspecialchars($student['studentid']); ?></p>
              <p><?php echo htmlspecialchars($student['email']); ?></p>
              <p><strong>Company:</strong> <?php echo htmlspecialchars($student['companyname'] ?? 'N/A'); ?></p>
              <div class="progress-bar-container" style="margin-top: 10px;">
                <div class="progress-bar-bg">
                  <div class="progress-bar-fill <?php echo getProgressBarClass($progressPercent); ?>" style="width: <?php echo $progressPercent; ?>%;"></div>
                </div>
                <span class="progress-value" style="font-size: 1rem;"><?php echo round($totalHours, 1); ?> / <?php echo $targetHours; ?> hrs</span>
                <label>Attendance</label>
              </div>
            </div>
        <?php
          }
        } else {
          echo '<p>No students found.</p>';
        }
        ?>
      </div>
    </div>

    <!-- Performance tab -->
    <div class="tab-content" id="performanceContent">
      <h2>Student Performance</h2>
      <div class="performance-controls">
        <input type="text" id="performanceStudentSearchInput" onkeyup="filterStudents()" placeholder="Search by name...">
        <select id="sectionFilterInput" onchange="filterStudents()">
          <option value="">All Sections</option>
          <?php
          $sections_res = $conn->query("SELECT DISTINCT section FROM student WHERE section IS NOT NULL AND section != '' ORDER BY section");
          if ($sections_res) {
              while ($section_row = $sections_res->fetch_assoc()) {
                  echo '<option value="' . htmlspecialchars($section_row['section']) . '">' . htmlspecialchars($section_row['section']) . '</option>';
              }
          }
          ?>
        </select>
      </div>
      <div class="companies-grid" id="studentPerformanceGrid">
        <?php
        $student_query = "
            SELECT 
                s.student_id, s.studentid, s.firstname, s.lastname, s.email, s.section, s.profile_picture,
                COALESCE(so.performance, 0) as performance
            FROM student s
            LEFT JOIN student_overview so ON s.student_id = so.student_id
            ORDER BY s.lastname, s.firstname
        ";
        $students_res = $conn->query($student_query);
        if ($students_res && $students_res->num_rows > 0) {
          while ($student = $students_res->fetch_assoc()) {
            $student_picture = 'uploads/dp.jpg'; // default
            if (!empty($student['profile_picture']) && file_exists($student['profile_picture'])) {
              $student_picture = $student['profile_picture'];
            }
            $performance = round((float) rtrim($student['performance'], '%'));
            $progress_degrees = $performance * 3.6;
        ?>
            <div class="company-card" 
                 onclick="showStudentTaskSummary(<?php echo $student['student_id']; ?>)" 
                 data-name="<?php echo htmlspecialchars(strtolower($student['firstname'] . ' ' . $student['lastname'])); ?>" 
                 data-section="<?php echo htmlspecialchars($student['section']); ?>">
              <img src="<?php echo htmlspecialchars($student_picture); ?>" alt="Student Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">
              <h4><?php echo htmlspecialchars($student['firstname'] . ' ' . $student['lastname']); ?></h4>
              <p>Student ID: <?php echo htmlspecialchars($student['studentid']); ?></p>
              <p><?php echo htmlspecialchars($student['email']); ?></p>
              <div class="progress-circle-container">
                <div class="progress-circle" style="background: conic-gradient(#116530 <?php echo $progress_degrees; ?>deg, #e0e0e0 0deg)">
                  <span class="progress-value"><?php echo $performance; ?>%</span>
                </div>
              </div>
            </div>
        <?php
          }
        } else {
          echo '<p>No students found.</p>';
        }
        ?>
      </div>
    </div>

    <!-- File Submissions tab -->
    <div class="tab-content" id="fileSubmissionsContent">
        <h2>Student File Submissions</h2>
        <div class="file-submission-layout">
            <div class="student-list-container">
                <h3>Students</h3>
                <div class="file-submission-controls">
                    <input type="text" id="fileSubmissionsStudentSearchInput" onkeyup="filterStudentList()" placeholder="Search students...">
                    <select id="fileSubmissionsSectionFilterInput" onchange="filterStudentList()">
                        <option value="">All Sections</option>
                        <?php
                        $sections_res_files = $conn->query("SELECT DISTINCT section FROM student WHERE section IS NOT NULL AND section != '' ORDER BY section");
                        if ($sections_res_files) {
                            while ($section_row = $sections_res_files->fetch_assoc()) {
                                echo '<option value="' . htmlspecialchars($section_row['section']) . '">' . htmlspecialchars($section_row['section']) . '</option>';
                            }
                        }
                        ?>
                    </select>
                </div>
                <div class="student-list" id="student-list">
                    <?php
                    $student_query_files = "
                        SELECT 
                            s.student_id, s.firstname, s.lastname, s.section
                        FROM student s
                        ORDER BY s.lastname, s.firstname
                    ";
                    $students_res_files = $conn->query($student_query_files);
                    if ($students_res_files && $students_res_files->num_rows > 0) {
                        while ($student = $students_res_files->fetch_assoc()) {
                            $student_name = htmlspecialchars($student['firstname'] . ' ' . $student['lastname']);
                            echo '<div class="student-list-item" onclick="loadStudentFiles(' . $student['student_id'] . ')" data-name="' . strtolower($student_name) . '" data-section="' . htmlspecialchars($student['section']) . '">' . $student_name . '</div>';
                        }
                    } else {
                        echo '<p>No students found.</p>';
                    }
                    ?>
                </div>
            </div>
            <div class="file-display-container">
                <div id="student-details-header" style="display: none;">
                    <img id="student-details-img" src="uploads/dp.jpg" alt="Student Picture">
                    <div class="student-info">
                        <h4 id="student-details-name"></h4>
                        <p id="student-details-id"></p>
                    </div>
                    <div class="progress-circle-container">
                        <div class="progress-circle" id="file-progress-circle">
                            <span class="progress-value" id="file-progress-value"></span>
                        </div>
                    </div>
                </div>
                <div id="file-submission-cards" style="display: none;">
                    <!-- File cards will be dynamically inserted here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Messaging tab -->
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

    <!-- OJT Hours Tab Content -->
    <div class="tab-content" id="ojtHoursContent">
      <h2>Manage OJT Hours per Section</h2>
      <div class="section-hours-container">
        <?php
        $sections_query = "SELECT section_name, ojt_hours FROM sections ORDER BY section_name";
        $sections_result = $conn->query($sections_query);
        if ($sections_result && $sections_result->num_rows > 0) {
            while ($section = $sections_result->fetch_assoc()) {
                echo '<div class="section-card">';
                echo '<h3>' . htmlspecialchars($section['section_name']) . '</h3>';
                echo '<div class="form-row">';
                echo '<label for="ojt_hours_' . htmlspecialchars($section['section_name']) . '">Required OJT Hours:</label>';
                echo '<div class="input-container">';
                echo '<input type="number" id="ojt_hours_' . htmlspecialchars($section['section_name']) . '" value="' . htmlspecialchars($section['ojt_hours']) . '" min="0" disabled>';
                echo '<span class="edit-icon" onclick="enableEditOjtHours(\'' . htmlspecialchars($section['section_name']) . '\')">&#9998;</span>';
                echo '</div>';
                echo '</div>';
                echo '<div class="action-buttons" id="ojtActionButtons_' . htmlspecialchars($section['section_name']) . '" style="display:none;">';
                echo '<button type="button" class="save-btn" onclick="saveOjtHours(\'' . htmlspecialchars($section['section_name']) . '\')">Save</button>';
                echo '<button type="button" class="cancel-btn" onclick="cancelEditOjtHours(\'' . htmlspecialchars($section['section_name']) . '\', ' . htmlspecialchars($section['ojt_hours']) . ')">Cancel</button>';
                echo '</div>';
                echo '</div>';
            }
        } else {
            echo '<p>No sections found.</p>';
        }
        ?>
      </div>
    </div>

    <!-- Send Invitations Tab Content -->
    <div class="tab-content" id="sendInvitationsContent">
      <h2>Send Invitation</h2>
      <div class="form-container" style="max-width: 500px; margin: 20px auto; padding: 20px; border: 1px solid #ddd; border-radius: 8px; background-color: #fff;">
        <form id="sendInvitationForm">
          <div class="form-row">
            <label for="inviteEmail">Email</label>
            <input type="email" id="inviteEmail" name="email" required style="width: 100%; padding: 8px; margin-top: 5px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
          </div>
          <div class="form-row">
            <label for="inviteRole">Role</label>
            <select id="inviteRole" name="role" required style="width: 100%; padding: 8px; margin-top: 5px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box;">
              <option value="">Select Role</option>
              <option value="Student">Student</option>
              <option value="Company">Company HR</option>
              <option value="Supervisor">Supervisor</option>
            </select>
          </div>
          <div class="action-buttons" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px;">
            <button type="submit" class="save-btn" style="background-color: #116530; color: white; padding: 10px 15px; border: none; border-radius: 8px; cursor: pointer;">Send Invitation</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Geo Location Tab Content -->
    <div class="tab-content" id="geoLocationContent">
      <h2>Set Geofence Location</h2>

      <!-- Geo Location Content -->
      <div id="currentLocationInfo" style="margin-bottom: 20px; padding: 15px; background: #f0f0f0; border-radius: 5px; border-left: 4px solid #116530;">
        <h3 style="margin-top: 0; color: #116530;">Current Active Location for <span id="currentCompanyName" style="color: #0e5128;"></span></h3>
        <p id="activeLocationText">Loading...</p>
      </div>

      <!-- Map Tools Section -->
      <div id="geoLocationMapTools">
        <!-- Map Controls -->
        <div id="mapControls" style="margin-bottom: 10px; position: relative;">
          <input type="text" id="mapSearch" placeholder="Search locations..." style="width: 200px; padding: 5px; border: 1px solid #ccc; border-radius: 4px;" autocomplete="off">
          <button id="searchBtn" class="save-btn" style="padding: 5px 10px; background: #116530; color: white; border: none; border-radius: 4px; cursor: pointer;">Search</button>
          <button id="saveBtn" class="save-btn" style="padding: 5px 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">Save Location</button>
          <div id="mapSearchSuggestions" style="position: absolute; top: 100%; left: 0; right: 0; background: white; border: 1px solid #ccc; border-radius: 4px; max-height: 200px; overflow-y: auto; z-index: 1000; display: none;"></div>
        </div>

        <!-- Interactive Map Container -->
        <div id="mapContainer" style="height: 400px; width: 100%; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 8px;"></div>

        <!-- Location Details -->
        <div class="form-row">
          <label for="selectedLocation">Selected Location</label>
          <input type="text" id="selectedLocation" readonly style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; background: #f9f9f9;" />
        </div>
        <div class="form-row">
          <label for="locationRadius">Radius (meters) - Drag circle edge to adjust</label>
          <input type="number" id="locationRadius" value="100" min="1" max="1000" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" />
        </div>

        <!-- Hidden inputs for coordinates -->
        <input type="hidden" id="latitude" />
        <input type="hidden" id="longitude" />
        <input type="hidden" id="currentCompanyHrId" value="" /> <!-- Hidden input to store hr_id -->

        <div class="action-buttons">
          <button type="button" class="save-btn" id="saveBtn" onclick="saveGeofenceLocation()">Set Location</button>
          <button type="button" class="cancel-btn" onclick="cancelLocationEdit()">Cancel</button>
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
  <!-- Student Task Summary Modal -->
  <div id="studentTaskModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1001; justify-content: center; align-items: center;">
    <div style="background: white; padding: 20px; border-radius: 10px; width: 600px; text-align: center;">
      <h3>Student Task Summary</h3>
      <div id="studentTaskModalBody"></div>
      <button onclick="document.getElementById('studentTaskModal').style.display = 'none'" style="padding: 10px 20px; background: #ccc; border: none; border-radius: 5px; cursor: pointer; margin-top: 20px;">Close</button>
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

  <!-- Interns Modal -->
  <div id="internsModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; padding: 20px; border-radius: 10px; width: 600px;">
      <h3>Hired Interns</h3>
      <div id="internsModalBody"></div>
      <button onclick="document.getElementById('internsModal').style.display = 'none'" style="padding: 10px 20px; background: #ccc; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px;">Close</button>
    </div>
  </div>

  <!-- Submitted Files Modal -->
  <div id="submittedFilesModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; padding: 20px; border-radius: 10px; width: 800px;">
      <h3>Submitted Files</h3>
      <div id="submittedFilesModalBody"></div>
      <button onclick="document.getElementById('submittedFilesModal').style.display = 'none'" style="padding: 10px 20px; background: #ccc; border: none; border-radius: 5px; cursor: pointer; margin-top: 10px;">Close</button>
    </div>
  </div>


  </div>

  <!-- Feedback Modal -->
  <div id="feedbackModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3>Give Feedback for <span id="feedbackStudentName"></span></h3>
        <button class="close-modal" onclick="closeFeedbackModal()">&times;</button>
      </div>
      <div class="modal-body">
        <form id="feedbackForm">
          <input type="hidden" id="feedbackStudentId" name="student_id">
          <input type="hidden" id="feedbackGivenBy" name="given_by" value="faculty">
          
          <label for="feedbackMessage">Feedback Message:</label>
          <textarea id="feedbackMessage" name="feedback_message" rows="5" required></textarea>
          
          <button type="submit" class="save-btn">Submit Feedback</button>
        </form>
      </div>
    </div>
  </div>

<!-- Announcement Modal -->
<div id="announcementModal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 id="announcementModalTitle">Post New Announcement</h3>
      <button class="close-modal" onclick="closeAnnouncementModal()">&times;</button>
    </div>
    <div class="modal-body">
      <form id="announcementForm">
        <input type="hidden" id="announcementId" name="announcement_id">
        <div class="form-row">
          <label for="announcementTitle">Title</label>
          <input type="text" id="announcementTitle" name="title" required>
        </div>
        <div class="form-row">
          <label for="announcementContent">Content</label>
          <textarea id="announcementContent" name="content" rows="5" required></textarea>
        </div>
        <div class="form-row">
          <label>Audience</label>
          <div id="announcementAudienceCheckboxes" style="display: flex; flex-direction: column; gap: 5px;">
            <label><input type="checkbox" name="audience[]" value="student"> Student</label>
            <label><input type="checkbox" name="audience[]" value="supervisor"> Supervisor</label>
            <label><input type="checkbox" name="audience[]" value="companyhr"> Company</label>
          </div>
        </div>
        <div class="action-buttons">
          <button type="submit" class="save-btn">Post</button>
          <button type="button" class="cancel-btn" onclick="closeAnnouncementModal()">Cancel</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Image Modal -->
<div id="imageModal" class="modal" onclick="closeImageModal()">
  <div class="modal-content">
    <img id="modalImage" src="" alt="Enlarged Selfie">
  </div>
</div>

  <style>
    .new-company-btn {
      background: linear-gradient(135deg, #116530 0%, #28a745 100%); /* Gradient for primary buttons */
      border: none;
      color: white;
      padding: 10px 15px; /* Larger padding */
      border-radius: 8px; /* More rounded buttons */
      cursor: pointer;
      font-size: 0.95rem; /* Slightly larger font */
      font-weight: 600;
      transition: all 0.3s ease;
      text-decoration: none;
      display: block; /* Make buttons block level for stacking */
      width: auto; /* Allow buttons to size to content */
      box-shadow: 0 2px 8px rgba(17, 101, 48, 0.3); /* Button shadow */
    }
    .new-company-btn:hover {
      background: linear-gradient(135deg, #0e5128 0%, #1e7e34 100%); /* Darker gradient on hover */
      transform: translateY(-2px); /* Slight lift on hover */
      box-shadow: 0 4px 12px rgba(17, 101, 48, 0.4); /* Enhanced shadow on hover */
    }
    .btn-option {
      background-color: #007bff;
      color: white;
      padding: 12px 20px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-size: 1.1rem;
      margin: 0 10px;
      transition: background-color 0.3s ease;
    }
    .btn-option:hover {
      background-color: #0056b3;
    }
    #newCompanyModal .modal-content {
      max-width: 900px;
    }
    #newCompanyModal .modal-body form label {
      display: block;
      margin-top: 10px;
      font-weight: 600;
    }
    #newCompanyModal .modal-body form input[type="text"],
    #newCompanyModal .modal-body form input[type="email"],
    #newCompanyModal .modal-body form input[type="password"],
    #newCompanyModal .modal-body form input[type="file"] {
      width: 100%;
      padding: 8px;
      margin-top: 5px;
      margin-bottom: 10px;
      border: 1px solid #ccc;
      border-radius: 4px;
      box-sizing: border-box;
    }
    #newCompanyModal .modal-body form .save-btn,
    #newCompanyModal .modal-body form .cancel-btn {
      padding: 10px 15px;
      border-radius: 8px;
      cursor: pointer;
      font-size: 1rem;
      margin-top: 10px;
      width: auto;
    }
    #newCompanyModal .modal-body form .save-btn {
      background-color: #116530;
      color: white;
      border: none;
    }
    #newCompanyModal .modal-body form .save-btn:hover {
      background-color: #0e5128;
    }
    #newCompanyModal .modal-body form .cancel-btn {
      background-color: #ccc;
      color: #333;
      border: 1px solid #bbb;
      margin-left: 10px;
    }
    #newCompanyModal .modal-body form .cancel-btn:hover {
      background-color: #bbb;
    }
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

    /* Tab display rules for faculty.php (embedded due to user constraint) */
    .main-content,
    .profile,
    .tab-content {
      display: none !important;
    }
    .main-content.active,
    .profile.active,
    .tab-content.active {
      display: flex !important; /* Use flex as other content areas seem to be flex containers */
      flex-direction: column !important; /* Ensure content stacks vertically */
      justify-content: flex-start !important;
      align-items: center !important; /* Explicitly align to center */
      text-align: center !important; /* Explicitly align text to center */
    }

    /* Geo Location Tab Styles */
    #geoLocationContent {
      padding: 20px;
      box-sizing: border-box;
      justify-content: flex-start;
      align-items: stretch;
      text-align: left;
    }
    #geoLocationContent.active {
      display: block !important;
    }
    #geoLocationContent h2 {
      text-align: center;
      color: #116530;
    }

    /* Ensure action buttons in announcement modal are visible */
    #announcementModal .action-buttons {
      display: flex !important;
    }

    /* Map specific styles */
    #mapContainer {
      height: 400px;
      width: 100%;
      margin-bottom: 20px;
      border: 1px solid #ccc;
      border-radius: 8px;
    }

    #mapControls {
      margin-bottom: 10px;
      position: relative;
      display: flex;
      gap: 10px;
      align-items: center;
    }

    #mapSearch {
      flex-grow: 1;
    }

    #mapSearchSuggestions {
      position: absolute;
      top: 100%;
      left: 0;
      right: 0;
      background: white;
      border: 1px solid #ccc;
      border-radius: 4px;
      max-height: 200px;
      overflow-y: auto;
      z-index: 1000;
      display: none;
      box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    }

    .map-search-suggestion {
      padding: 8px;
      cursor: pointer;
      border-bottom: 1px solid #eee;
    }

    .map-search-suggestion:hover {
      background-color: #f0f0f0;
    }

    /* Company-specific styles for faculty */
    #companiesContent {
      display: none;
      padding: 40px;
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 255, 248, 0.95) 100%);
      border-radius: 20px;
      overflow-y: auto;
      height: auto; /* Allow content to dictate height */
      min-height: calc(100vh - 200px); /* Ensure minimum height */
      color: #116530;
      text-align: left;
      font-family: 'Inter', sans-serif;
      box-shadow: 0 10px 30px rgba(17, 101, 48, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(17, 101, 48, 0.1);
      scroll-behavior: smooth;
      scrollbar-width: thin;
      scrollbar-color: #116530 #f1f1f1;
    }

    #companiesContent.active {
      display: flex !important;
      flex-direction: column !important;
      justify-content: flex-start !important;
      align-items: center !important; /* Center content within the tab */
    }

    #companiesContent h2 {
      text-align: center;
      color: #116530;
      margin-bottom: 30px;
      font-size: 2rem;
      font-weight: 700;
      letter-spacing: -0.5px;
    }

    .company-section {
      margin-bottom: 40px;
      width: 100%;
      max-width: 1000px; /* Max width for sections */
    }

    .company-section h3 {
      font-size: 1.5rem;
      margin-bottom: 15px;
      color: #0b3d0b;
      border-bottom: 2px solid #116530;
      padding-bottom: 5px;
      text-align: center; /* Center section titles */
    }

    .companies-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); /* Slightly smaller min-width for more cards */
      gap: 20px;
      margin-top: 20px;
      width: 100%;
      justify-items: center; /* Center items within the grid */
    }
    .company-card {
      border: 1px solid rgba(17, 101, 48, 0.1); /* Subtle border */
      border-radius: 12px; /* More rounded corners */
      padding: 20px; /* Increased padding */
      background: #fff;
      box-shadow: 0 4px 15px rgba(0,0,0,0.08); /* Softer, more pronounced shadow */
      text-align: center;
      transition: transform 0.3s ease-in-out, box-shadow 0.3s ease-in-out;
      cursor: pointer;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: space-between; /* Distribute content vertically */
      min-height: 220px; /* Ensure consistent card height */
    }
    .company-card:hover {
      transform: translateY(-5px); /* Lift effect on hover */
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15); /* Enhanced shadow on hover */
    }
    .company-card img {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      object-fit: cover;
      margin-bottom: 15px; /* Increased margin */
      border: 3px solid #116530; /* Green border for profile pics */
    }
    .company-card h4 {
      margin: 0 0 8px 0; /* Adjusted margin */
      font-size: 1.2rem; /* Larger title */
      color: #116530;
      font-weight: 700;
    }
    .company-card p {
      margin: 0 0 5px 0; /* Adjusted margin */
      color: #555;
      font-size: 0.95rem; /* Slightly larger text */
    }
    .company-card-buttons {
      margin-top: 20px; /* Increased margin */
      display: flex;
      flex-direction: column; /* Stack buttons vertically */
      gap: 10px; /* Space between stacked buttons */
      width: 100%; /* Buttons take full width of card */
    }
    .company-card-buttons button,
    .company-card-buttons a.button {
      background: linear-gradient(135deg, #116530 0%, #28a745 100%); /* Gradient for primary buttons */
      border: none;
      color: white;
      padding: 10px 15px; /* Larger padding */
      border-radius: 8px; /* More rounded buttons */
      cursor: pointer;
      font-size: 0.95rem; /* Slightly larger font */
      font-weight: 600;
      transition: all 0.3s ease;
      text-decoration: none;
      display: block; /* Make buttons block level for stacking */
      width: 100%; /* Full width */
      box-shadow: 0 2px 8px rgba(17, 101, 48, 0.3); /* Button shadow */
    }
    .company-card-buttons button:hover,
    .company-card-buttons a.button:hover {
      background: linear-gradient(135deg, #0e5128 0%, #1e7e34 100%); /* Darker gradient on hover */
      transform: translateY(-2px); /* Slight lift on hover */
      box-shadow: 0 4px 12px rgba(17, 101, 48, 0.4); /* Enhanced shadow on hover */
    }
    .company-card-buttons button.secondary {
      background: linear-gradient(135deg, #dc3545 0%, #e65c5c 100%); /* Red gradient for secondary */
      box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
    }
    .company-card-buttons button.secondary:hover {
      background: linear-gradient(135deg, #c82333 0%, #dc3545 100%); /* Darker red gradient on hover */
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
    }

    /* Mobile responsiveness for companies tab */
    @media (max-width: 768px) {
      #companiesContent {
        padding: 20px;
        margin: 15px;
        width: calc(100% - 30px);
        min-height: calc(100vh - 112px);
        border-radius: 16px;
      }

      #companiesContent h2 {
        font-size: 1.8rem;
        margin-bottom: 20px;
      }

      .company-section {
        margin-bottom: 30px;
      }

      .company-section h3 {
        font-size: 1.3rem;
        margin-bottom: 10px;
      }

      .companies-grid {
        grid-template-columns: 1fr; /* Single column on small screens */
        gap: 15px;
      }

      .company-card {
        padding: 15px;
        min-height: auto;
      }

      .company-card img {
        width: 70px;
        height: 70px;
        margin-bottom: 10px;
      }

      .company-card h4 {
        font-size: 1.1rem;
        margin-bottom: 5px;
      }

      .company-card p {
        font-size: 0.9rem;
        margin-bottom: 3px;
      }

      .company-card-buttons {
        margin-top: 15px;
        gap: 8px;
      }

      .company-card-buttons button,
      .company-card-buttons a.button {
        padding: 8px 12px;
        font-size: 0.9rem;
        border-radius: 6px;
      }
    }

    @media (max-width: 480px) {
      #companiesContent {
        padding: 15px;
        margin: 10px;
        width: calc(100% - 20px);
        border-radius: 12px;
      }

      #companiesContent h2 {
        font-size: 1.5rem;
        margin-bottom: 15px;
      }

      .company-section {
        margin-bottom: 20px;
      }

      .company-section h3 {
        font-size: 1.1rem;
      }

      .companies-grid {
        gap: 10px;
      }

      .company-card {
        padding: 12px;
      }

      .company-card img {
        width: 60px;
        height: 60px;
      }

      .company-card h4 {
        font-size: 1rem;
      }

      .company-card p {
        font-size: 0.85rem;
      }

      .company-card-buttons {
        margin-top: 10px;
        gap: 6px;
      }

      .company-card-buttons button,
      .company-card-buttons a.button {
        padding: 6px 10px;
        font-size: 0.85rem;
        border-radius: 5px;
      }
    }

    /* Specific styles for announcement card buttons */
    .announcement-card .company-card-buttons {
      flex-direction: row; /* Arrange buttons horizontally */
      justify-content: center; /* Center buttons */
      gap: 10px; /* Space between buttons */
      margin-top: 15px;
    }

    .announcement-card .company-card-buttons button {
      width: auto; /* Allow buttons to size to content */
      flex-grow: 0; /* Prevent buttons from growing to fill space */
      padding: 8px 15px; /* Adjust padding */
      font-size: 0.9rem; /* Adjust font size */
      border-radius: 6px; /* Match other buttons */
      box-shadow: 0 2px 5px rgba(0,0,0,0.1); /* Softer shadow */
    }

    .announcement-card .company-card-buttons button:hover {
      transform: translateY(-1px); /* Less pronounced lift on hover */
      box-shadow: 0 3px 8px rgba(0,0,0,0.15); /* Adjusted shadow on hover */
    }

    .announcement-card .company-card-buttons button.secondary {
      background: linear-gradient(135deg, #dc3545 0%, #e65c5c 100%);
    }

    .announcement-card .company-card-buttons button.secondary:hover {
      background: linear-gradient(135deg, #c82333 0%, #dc3545 100%);
    }

    .interns-list {
      list-style-type: none;
      padding: 0;
    }

    .interns-list li {
      background: #f9f9f9;
      border: 1px solid #ddd;
      margin-bottom: 5px;
      padding: 10px;
      border-radius: 4px;
    }

    #submittedFilesModalBody .file-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px;
      border-bottom: 1px solid #eee;
    }

    #submittedFilesModalBody .file-item:last-child {
      border-bottom: none;
    }

    #submittedFilesModalBody .file-actions button,
    #submittedFilesModalBody .file-actions .button {
      margin-left: 10px;
      text-decoration: none;
      background-color: #116530;
      border: none;
      color: white;
      padding: 8px 12px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.9rem;
      transition: background-color 0.3s ease;
      display: inline-block;
    }
    #submittedFilesModalBody .file-actions .button:hover {
        background-color: #0e5128;
    }
    #submittedFilesModalBody .file-actions .approved-text {
        margin-left: 10px;
        color: #116530;
        font-weight: bold;
    }

    /* Performance Tab */
    #performanceContent,  {
      padding: 20px;
      box-sizing: border-box;
      justify-content: flex-start;
      align-items: stretch;
      text-align: left;
    }
    #performanceContent h2 {
      text-align: center;
      color: #116530;
    }
    .performance-controls {
      display: flex;
      justify-content: center;
      gap: 20px;
      margin-bottom: 20px;
      width: 100%;
      max-width: 800px;
    }
    .performance-controls input,
    .performance-controls select {
      padding: 10px;
      border-radius: 5px;
      border: 1px solid #ccc;
      flex-grow: 1; /* Allow items to grow */
    }
    .performance-controls input {
      max-width: 300px; /* Limit width of search input */
    }
    .performance-controls select {
      max-width: 150px; /* Limit width of section filter */
    }
    .progress-circle-container {
      margin-top: 15px;
    }
    .progress-circle {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto;
      position: relative;
    }
    .progress-circle .progress-value {
      position: absolute;
      width: 85px;
      height: 85px;
      background: #fff;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      font-weight: bold;
      color: #116530;
    }

    .task-summary {
      display: flex;
      justify-content: space-around;
      margin-top: 15px;
      padding-top: 10px;
      border-top: 1px solid #eee;
    }
    .task-count {
      text-align: center;
    }
    .task-count strong {
      display: block;
      font-size: 1.2rem;
    }
    .task-count span {
      font-size: 0.8rem;
      color: #666;
    }

    /* New styles for file submission layout */
    .file-submission-layout {
        display: flex;
        gap: 20px;
        width: 100%; /* Ensure it takes full width to allow left alignment */
    }
    #fileSubmissionsContent.active .file-submission-layout {
        margin-left: 0 !important; /* Push to the left */
        margin-right: auto !important; /* Allow it to take up remaining space on the right */
    }
    #fileSubmissionsContent.active .file-submission-layout {
        margin-left: 0 !important; /* Push to the left */
        margin-right: auto !important; /* Allow it to take up remaining space on the right */
    }
    .student-list-container {
        width: 250px;
        flex-shrink: 0;
        background: #f9f9f9;
        padding: 15px;
        border-radius: 8px;
        height: 70vh;
        overflow-y: auto;
    }
    .student-list-container h3 {
        margin-top: 0;
    }
    .student-list-container input {
        width: 100%;
        padding: 8px;
        margin-bottom: 10px;
        border: 1px solid #ccc;
        border-radius: 4px;
    }
    .student-list-item {
        padding: 10px;
        cursor: pointer;
        border-radius: 4px;
    }
    .student-list-item:hover, .student-list-item.active {
        background-color: #e0e0e0;
    }
    .file-display-container {
        flex-grow: 1;
    }
    /* New styles for file submission layout */
    .file-submission-layout {
        width: 100%; /* Ensure it takes full width to allow left alignment */
    }
    #fileSubmissionsContent.active .file-submission-layout {
        margin-left: 0 !important; /* Push to the left */
        margin-right: auto !important; /* Allow it to take up remaining space on the right */
    }
      .approved-text {
          color: #116530;
          font-weight: bold;
      }
      .comments-section {
          margin-top: 15px;
      }
      .comments-header {
          cursor: pointer;
          font-weight: bold;
          padding: 5px;
          background: #f0f0f0;
          border-radius: 4px;
      }
      .comments-container {
          padding: 10px;
          border: 1px solid #f0f0f0;
          border-top: none;
      }
      .comment {
          border-bottom: 1px solid #eee;
          padding: 5px 0;
      }
      .comment p {
          margin: 0;
      }
      .comment-date {
          font-size: 0.8em;
          color: #888;
      }
      .comment-form textarea {
          width: 100%;
          min-height: 60px;
          margin-top: 10px;
      }
      .comment-form button {
          background-color: #116530;
          color: white;
          border: none;
          border-radius: 6px; /* Unified with company-card-buttons */
          padding: 8px 12px; /* Unified with company-card-buttons */
          font-weight: 600;
          cursor: pointer;
          width: 100%;
          font-size: 0.9rem; /* Unified with company-card-buttons */
          transition: background-color 0.3s ease;
          /* Removed box-shadow for consistency, relying on card shadow */
          margin-top: 5px;
      }
      .comment-form button:hover {
        background-color: #0e5128; /* Unified hover effect */
      }
      #student-details-header img {
          width: 80px;
          height: 80px;
          border-radius: 50%;
          object-fit: cover;
      }
      #student-details-header .student-info {
          flex-grow: 1;
      }
      #student-details-header h3, #student-details-header p {
          margin: 0;
      }
  
      /* Student Overview Styles */
      #studentOverviewContent {
        padding: 20px;
      }
      #studentOverviewGrid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
        gap: 20px;
        width: 100%;
      }
      .progress-circles-container {
        display: flex;
        justify-content: space-around;
        margin-top: 15px;
      }
      .progress-circle-container {
        text-align: center;
      }
      .progress-circle-container label {
        margin-top: 5px;
        font-size: 0.8rem;
        color: #333;
      }
  
      .progress-bars-container {
        display: flex;
        justify-content: space-around;
        margin-top: 15px;
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
    /* Custom CSS for File Submissions and Profile Tabs */
    .file-submission-layout {
        width: 100%; /* Ensure it takes full width to allow left alignment */
    }
    #fileSubmissionsContent.active .file-submission-layout {
        margin-left: 0 !important; /* Push to the left */
        margin-right: auto !important; /* Allow it to take up remaining space on the right */
    }
    #fileSubmissionsContent #file-submission-cards {
        gap: 30px; /* Increase gap between grid items */
    }
    #fileSubmissionsContent #file-submission-cards .company-card {
        margin-bottom: 15px; /* Add vertical spacing between cards */
        min-height: 200px; /* Ensure consistent minimum height */
        display: flex; /* Make card content a flex container */
        flex-direction: column; /* Stack content vertically */
        justify-content: space-between; /* Distribute content vertically */
        align-items: center; /* Center content horizontally within the card */
        text-align: center; /* Center text within the card */
    }
    #fileSubmissionsContent #file-submission-cards .company-card h4 {
        white-space: nowrap; /* Prevent text wrapping */
        overflow: hidden; /* Hide overflowing text */
        text-overflow: ellipsis; /* Show ellipsis for truncated text */
        max-width: 100%; /* Ensure it respects card width */
    }
    #profileContent.active #profileForm {
        margin-left: 0 !important; /* Align form to the left */
        margin-right: auto !important; /* Allow it to take up remaining space on the right */
    }
    </style>
  </body>
  </html>

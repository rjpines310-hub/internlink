<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    header("Location: login.php");
    exit();
}

include 'db.php';
$userId = $_SESSION['user_id'];
$companyname = '';
$location = '';
$profile_picture = 'uploads/dp.jpg'; // default
$request_status = null;

// Fetch HR profile
$stmt = $conn->prepare("SELECT hr_id, companyname, location, email, contact, landline, password, profile_picture 
                        FROM companyhr WHERE hr_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($hr_id, $companyname, $location, $email, $contact, $landline, $password, $db_picture);
$stmt->fetch();
$stmt->close();

if ($db_picture && file_exists($db_picture)) {
    $profile_picture = $db_picture;
}

// Check HR request status
$stmt = $conn->prepare("SELECT status FROM hr_requests WHERE hr_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($request_status);
$stmt->fetch();
$stmt->close();

$companyhr = [
    'hr_id' => $hr_id,
    'companyname' => $companyname,
    'location' => $location,
    'email' => $email,
    'contact' => $contact,
    'landline' => $landline,
    'password' => $password
];
// Fetch internship posts for this company only (status = 'Active' assumed)
$posts = [];
$stmt = $conn->prepare("
    SELECT 
        ip.post_id,
        ip.internship_title,
        ip.companyname,
        ip.location,
        ip.internship_description,
        ip.allowance,
        ip.date_posted,
        ip.application_deadline,
        ip.email,
        ip.status,
        (SELECT COUNT(*) FROM intern_applications ia WHERE ia.post_id = ip.post_id) AS applicant_count
    FROM internship_posts ip
    WHERE ip.posted_by = ? AND ip.status = 'Active'
    ORDER BY ip.date_posted DESC
");


if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("i", $hr_id);

if (!$stmt->execute()) {
    var_dump("Logged HR ID from session: ", $userId);
    var_dump("Fetched HR ID from companyhr table: ", $hr_id);

    die("Execute failed: " . $stmt->error);
}

$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $posts[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Company Dashboard | Universidad De Manila</title>
  <link rel="icon" href="logo.png" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="companyhr.css" />
  <link rel="stylesheet" href="company_dashboard.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- Leaflet CSS and JS for interactive maps -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
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
    .sidebar a.disabled-link {
      color: #999; /* Grey out the text */
      cursor: not-allowed; /* Change cursor to indicate it's not clickable */
      pointer-events: none; /* Prevent click events */
    }

  /* Partnership request buttons (main dashboard + request tab) */
  #requestBtn,
  #requestBtnMain {
    display: block;               /* center by margin auto */
    margin: 12px auto;            /* vertical spacing + center */
    padding: 10px 18px;
    max-width: 320px;
    width: calc(100% - 40px);
    background-color: #116530;
    color: #fff;
    border: none;
    border-radius: 10px;
    font-weight: 700;
    font-size: 14px;
    text-align: center;
    cursor: pointer;
    box-shadow: 0 6px 18px rgba(17,101,48,0.18);
    transition: transform 120ms ease, box-shadow 120ms ease, background-color 120ms ease;
    line-height: 1;
  }

  #requestBtn:hover,
  #requestBtnMain:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 28px rgba(17,101,48,0.22);
  }

  #requestBtn:active,
  #requestBtnMain:active {
    transform: translateY(0);
    box-shadow: 0 6px 14px rgba(17,101,48,0.18);
  }

  #requestBtn:disabled,
  #requestBtnMain:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    box-shadow: none;
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
      <button class="dropbtn" onclick="toggleDropdown()">Profile▼</button>
      <div class="dropdown-content" id="profileDropdown">
        <a href="#" onclick="showProfile(); closeDropdown()">Edit Profile</a>
        <a href="#" onclick="showLocation(); closeDropdown()">Location</a>
        <a href="logout.php" onclick="closeDropdown()">Log Out</a>
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
            <div class="overlay">Take Selfie</div>
          </div>
        </label>
        <input type="file" id="profileInput" name="profile_picture" onchange="document.getElementById('uploadForm').submit();" />
      </form>
      <div class="student-name"><?php echo htmlspecialchars($companyname); ?></div>
      <div class="location"><?php echo htmlspecialchars($location); ?></div>

      <?php
      $tabs = ['Job post', 'Interns', 'Supervisors', 'Message'];
      foreach ($tabs as $tab) {
          $tabId = strtolower(str_replace(' ', '', $tab)); // e.g. jobpost, interns
          echo "      <a href='#' onclick=\"showTab('$tabId')\">$tab</a>";
      }
      ?>
    </div>

    <div class="main-content active" id="mainContent">
      <h2 class="welcome-message">Welcome, <?php echo htmlspecialchars($companyname); ?>!</h2>
      <p id="partnershipStatusMessage">Loading partnership status...</p>

      <div class="dashboard-cards-grid">
        <!-- Company HR announcements will be loaded here -->
        <div class="kard announcement-display-card">
          <div class="kard-header">
            <h3>Announcements</h3>
          </div>
          <div id="companyhrAnnouncementsContainer" class="kard-content">Loading announcements...</div>
        </div>

        <!-- Newest Applicants -->
        <div class="kard">
          <div class="kard-header">
            <h3>Newest Applicants</h3>
          </div>
          <div id="newestApplicants" class="kard-content info-list">Loading...</div>
        </div>

        <!-- Interns Currently in Progress -->
        <div class="kard">
          <div class="kard-header">
            <h3>Interns Currently in Progress</h3>
          </div>
          <div id="internsInProgress" class="kard-content info-list">Loading...</div>
        </div>
      </div>
    </div>

    <div class="profile" id="profileContent">
      <h2>Edit Profile</h2>
      <form id="profileForm">
        <?php foreach (['companyname','location','email','contact','landline'] as $field): ?>
        <div class="form-row">
          <label><?= ucfirst($field) ?></label>
          <input type="text" name="<?= $field ?>" id="<?= $field ?>" value="<?= htmlspecialchars($companyhr[$field]) ?>" disabled />
          <span class="edit-icon" onclick="enableEdit('<?= $field ?>')">&#9998;</span>
        </div>
        <?php endforeach; ?>
        <div class="form-row">
          <label>Password</label>
          <input type="password" id="password_display" value="******" disabled />
          <span class="edit-icon" onclick="enablePassword()">&#9998;</span>
        </div>
        <div id="passwordFields" style="display:none;">
          <div class="form-row"><label>New Password</label><input type="password" name="password" id="password" /></div>
          <div class="form-row"><label>Confirm</label><input type="password" id="confirm_password" /></div>
        </div>
        <div class="action-buttons" id="actionButtons" style="display:none;">
          <button type="button" class="save-btn" onclick="saveProfile()">Save Changes</button>
          <button type="button" class="cancel-btn" onclick="cancelEdit()">Cancel</button>
        </div>
      </form>
      <button class="back-btn" onclick="goHome()">Back</button>
    </div>

    <div class="profile" id="locationContent">
      <h2>Set Geofence Location</h2>
      <div id="currentLocation" style="margin-bottom: 20px; padding: 10px; background: #f0f0f0; border-radius: 5px;">
        <strong>Current Active Location:</strong> <span id="activeLocationText">Loading...</span>
      </div>

      <!-- Map Controls -->
      <div id="mapControls" style="margin-bottom: 10px; position: relative;">
        <input type="text" id="mapSearch" placeholder="Search locations..." style="width: 200px; padding: 5px; border: 1px solid #ccc; border-radius: 4px;" autocomplete="off">
        <button id="searchBtn" style="padding: 5px 10px; background: #116530; color: white; border: none; border-radius: 4px; cursor: pointer;">Search</button>
        <button id="saveBtn" style="padding: 5px 10px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">Save Location</button>
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

      <div class="action-buttons">
        <button type="button" class="save-btn" onclick="saveGeofenceLocation()">Set Location</button>
        <button type="button" class="cancel-btn" onclick="goHome()">Cancel</button>
      </div>
      <button class="back-btn" onclick="goHome()">Back</button>
    </div>

    <div class="main-content" id="requestTab">
      <h2 id="requestTabTitle">Partnership Request</h2>
      <div id="requestTabContent">
        <!-- Content will be dynamically loaded here based on status -->
      </div>
    </div>

    <!-- Tabs content placeholders -->
    <div class="main-content" id="jobpostContent">
      <h2>Job Post</h2>

      <button id="createPostBtn" class="action-btn" style="margin-bottom: 20px; align-self: flex-start;">
        + Create New Post
      </button>

      <!-- Posts list container -->
      <div id="postsList" class="internship-cards-container">
        <?php if (count($posts) === 0): ?>
          <p>No active job posts found.</p>
        <?php else: ?>
          <?php foreach ($posts as $post): ?>
            <div class="internship-card">
              <div class="internship-card-header">
                <h3><?= htmlspecialchars($post['internship_title']) ?></h3>
                <div class="company-badge">Applicants: <?= (int)$post['applicant_count'] ?></div>
              </div>
              <div class="job-details">
                <div class="job-info-grid">
                  <div class="job-info-item">
                    <div class="job-info-label">Location</div>
                    <div class="job-info-value"><?= htmlspecialchars($post['location']) ?></div>
                  </div>
                  <div class="job-info-item job-contact">
                    <div class="job-info-label">Contact</div>
                    <div class="job-info-value">
                      <a href="mailto:<?= htmlspecialchars($post['email']) ?>"><?= htmlspecialchars($post['email']) ?></a>
                    </div>
                  </div>
                </div>
                
                <div class="job-description">
                  <div class="job-info-label">Job Description</div>
                  <div class="job-info-value"><?= htmlspecialchars($post['internship_description']) ?></div>
                </div>
                
                <div class="job-meta">
                  <div class="job-allowance"><?= htmlspecialchars($post['allowance']) ?></div>
                  <div class="job-date"><?= htmlspecialchars(date("M j, Y", strtotime($post['date_posted']))) ?></div>
                </div>
                
                <div class="job-card-buttons">
                    <button class="action-btn" onclick="editPost(<?= $post['post_id'] ?>)">Edit</button>
                    <button class="action-btn secondary" onclick="closePost(<?= $post['post_id'] ?>)">Close</button>
                    <button class="action-btn" onclick="viewApplicants(<?= $post['post_id'] ?>)">View Applicants</button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <!-- Create post form - initially hidden -->
      <form id="createPostForm" style="display: none; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); text-align: left;">
        <h3>Create New Internship Post</h3>
        <div class="form-row">
          <label for="internship_title">Title</label>
          <input type="text" id="internship_title" name="internship_title" required />
        </div>
        <div class="form-row">
          <label for="location">Location</label>
          <input type="text" id="location" name="location" required />
        </div>
        <div class="form-row">
          <label for="internship_description">Description</label>
          <textarea id="internship_description" name="internship_description" rows="4" required style="width: 100%;"></textarea>
        </div>
        <div class="form-row">
          <label for="allowance">Allowance</label>
          <input type="text" id="allowance" name="allowance" required />
        </div>
        <div class="form-row">
          <label for="application_deadline">Application Deadline</label>
          <input type="date" id="application_deadline" name="application_deadline" required />
        </div>
        <div class="form-row">
          <label for="post_email">Contact Email</label>
          <input type="email" id="post_email" name="email" required value="<?= htmlspecialchars($email) ?>" />
        </div>
        <div style="margin-top: 15px;">
          <button type="submit" style="background-color: #116530; color: white; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;">Submit</button>
          <button type="button" onclick="cancelCreate()" style="margin-left: 10px; background-color: #ccc; color: #333; padding: 10px 20px; border: none; border-radius: 6px; cursor: pointer;">Cancel</button>
        </div>
      </form>

      <button class="back-btn" onclick="goHome()">Back</button>
    </div>

    <div class="main-content" id="internsContent">
      <h2>Interns</h2>
      <input type="text" id="internSearch" onkeyup="filterInterns()" placeholder="Search for interns..." style="width: 100%; padding: 10px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 5px;">
      <div id="internsList" class="supervisor-cards-container"></div>
      <button class="back-btn" onclick="goHome()">Back</button>
    </div>

    <div class="main-content" id="supervisorsContent">
      <h2>Supervisors</h2>
      <input type="text" id="supervisorSearch" onkeyup="filterSupervisors()" placeholder="Search for supervisors..." style="width: 100%; padding: 10px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 5px;">
      <div id="supervisorList" class="supervisor-cards-container"></div>
      <button class="back-btn" onclick="goHome()">Back</button>
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

    <div class="main-content" id="applicantsContent">
      <h2>Applicants</h2>
      <input type="text" id="applicantSearch" onkeyup="filterApplicants()" placeholder="Search for applicants..." style="width: 100%; padding: 10px; margin-bottom: 20px; border: 1px solid #ccc; border-radius: 5px;">
      <div id="applicantsList"></div>
      <button class="back-btn" onclick="goHome()">Back</button>
    </div>

    <!-- Resume Modal -->
    <div id="resumeModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Applicant Resume</h3>
          <div id="resumeModalActions" class="modal-actions"></div>
          <button class="close-modal" onclick="closeResumeModal()">&times;</button>
        </div>
        <div class="modal-body resume-container" id="resumeModalBody">
          <!-- Resume content will be loaded here -->
        </div>
      </div>
    </div>

    <!-- Interview Modal -->
    <div id="interviewModal" class="modal">
      <div class="modal-content">
        <div class="modal-header">
          <h3>Set Interview</h3>
          <button class="close-modal" onclick="closeInterviewModal()">&times;</button>
        </div>
        <div class="modal-body" id="interviewModalBody">
          <!-- Interview form will be loaded here dynamically -->
        </div>
      </div>
    </div>
  </div>

  <!-- PDF script -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
<script>
const currentUserId = <?php echo json_encode((int)$userId); ?>;

function showProfile() {
  hideAllTabsExcept('profileContent');
}

function showLocation() {
  hideAllTabsExcept('locationContent');
  loadActiveLocation();
  // Initialize map if not already done
  if (!map) {
    setTimeout(initializeMap, 100);
  }
}

function goHome() {
  // also hide any .tab-content (message panel uses .tab-content class)
  document.querySelectorAll('.main-content, .profile, .tab-content').forEach(c => c.classList.remove('active'));
  document.getElementById('mainContent').classList.add('active');
}

function showRequestTab(tabName) {
  document.querySelectorAll('.main-content, .profile').forEach(c => c.classList.remove('active'));
  document.getElementById('requestTab').classList.add('active');
  document.getElementById('requestTabTitle').innerText = tabName;
  loadPartnershipRequestStatus(); // Load status when tab is shown
}

function hideAllTabsExcept(keepId) {
  const allContents = [
    "mainContent",
    "profileContent",
    "locationContent",
    "requestTab",
    "jobpostContent",
    "internsContent",
    "supervisorsContent",
    "messageContent",
    "applicantsContent"
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

function showTab(tabId, clickedLink) {
    // clickedLink may be undefined when showTab is called programmatically.
    // Find the sidebar link that triggered this tab if not provided.
    if (!clickedLink) {
        const links = document.querySelectorAll('.sidebar a');
        for (const l of links) {
            const onclickAttr = l.getAttribute('onclick') || '';
            if (onclickAttr.includes(`showTab('${tabId}'`) || l.dataset.tab === tabId) {
                clickedLink = l;
                break;
            }
        }
    }

    // If the link is disabled, do nothing
    if (clickedLink && clickedLink.classList.contains('disabled-link')) {
        return;
    }

    const contentId = tabId.endsWith('Content') ? tabId : tabId + 'Content';
    hideAllTabsExcept(contentId);

    if (tabId === 'supervisors') {
        loadSupervisors();
    }
    if (tabId === 'interns') {
        loadInterns();
    }
    if (tabId === "message") {
        // Clean up any existing messaging state
        if (typeof cleanupMessaging === 'function') {
            cleanupMessaging();
        }

        // Initialize messaging with improved functionality
        setTimeout(() => {
            if (typeof initializeMessaging === 'function') {
                initializeMessaging();
            }
        }, 100);
    } else {
        // Clean up messaging when leaving the tab
        if (typeof cleanupMessaging === 'function') {
            cleanupMessaging();
        }
    }
}

function requestPartnership() {
  fetch('request_partnership.php', {
    method: 'POST',
    credentials: 'include', // Send session cookie
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: 'action=request'
  })
  .then(response => {
    if (!response.ok) {
      if (response.status === 403) {
        Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: 'Session expired or unauthorized. Please login again.',
        });
        window.location.href = 'login.php';
        return;
      }
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    return response.text();
  })
  .then(data => {
    Swal.fire({
      icon: 'success',
      title: 'Success!',
      text: data,
    });
    // Update the request tab content after successful request
    loadPartnershipRequestStatus();
  })
  .catch(error => {
    console.error(error);
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'An error occurred. Please try again later.',
    });
  });
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
  location.reload();
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

  fetch("update_profile.php", { method: "POST", body: formData })
  .then(res => res.text())
  .then(res => {
    alert("Profile updated successfully!");
    location.reload();
  }).catch(err => {
    alert("Error updating profile.");
  });
}

// Improved messaging functionality
let currentConversation = null;
let messageRefreshInterval = null;
let conversationRefreshInterval = null;

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
  fetch('fetch_messages.php?action=conversations&user_type=companyhr')
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
  fetch(`fetch_messages.php?action=messages&other_type=${other_type}&other_id=${other_id}&user_type=companyhr`)
    .then(res => res.json())
    .then(data => {
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
  formData.append('sender_type', 'companyhr');
  
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
  
  fetch(`fetch_messages.php?action=search_users&query=${encodeURIComponent(query)}&user_type=companyhr`)
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

function updateApplicationStatus(studentId, action) {
  const hrId = <?php echo json_encode($hr_id); ?>;

  const formData = new FormData();
  formData.append('student_id', studentId);
  formData.append('hr_id', hrId);
  formData.append('action', action);

  fetch('update_application_status.php', {
    method: 'POST',
    body: formData
  })
  .then(response => {
    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }
    return response.json();
  })
  .then(data => {
    if (data.success) {
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: data.message,
      }).then(() => {
        closeResumeModal();
        // Optionally, refresh the applicants list if it's visible
        if (document.getElementById('applicantsContent').classList.contains('active')) {
          const activePostId = document.getElementById('applicantsContent').dataset.postId;
          if(activePostId) viewApplicants(activePostId);
        }
      });
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Error!',
        text: data.message,
      });
    }
  })
  .catch(error => {
    console.error('Error:', error);
    Swal.fire({
      icon: 'error',
      title: 'Error!',
      text: 'An error occurred while updating the application status. Check console for details.',
    });
  });
}

function openInterviewModal() {
  const modal = document.getElementById('interviewModal');
  if (modal) {
    modal.style.display = 'block';
  }
}

function closeInterviewModal() {
  const modal = document.getElementById('interviewModal');
  if (modal) {
    modal.style.display = 'none';
  }
}

function downloadPDF(studentId, studentName) {
  const element = document.getElementById(`resume-content-${studentId}`);
  const opt = {
    margin:       0.5,
    filename:     `resume_${studentName.replace(/ /g, '_')}.pdf`,
    image:        { type: 'jpeg', quality: 0.98 },
    html2canvas:  { scale: 2 },
    jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
  };
  html2pdf().from(element).set(opt).save();
}

function printResume(studentId) {
    const content = document.getElementById(`resume-content-${studentId}`).innerHTML;
    const printWindow = window.open('', '', 'height=800,width=800');
    printWindow.document.write('<html><head><title>Print Resume</title>');
    // Re-inject all the styles
    const styles = Array.from(document.styleSheets)
        .map(s => Array.from(s.cssRules).map(r => r.cssText).join('\n'))
        .join('\n');
    printWindow.document.write('<style>' + styles + '</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write(content);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => { printWindow.print(); }, 500);
}


function toggleOnlineLink() {
  const locationSelect = document.getElementById("locationSelect");
  const onlineLinkField = document.getElementById("onlineLinkField");
  if (locationSelect && onlineLinkField) {
      onlineLinkField.style.display = locationSelect.value === "Online" ? "block" : "none";
  }
}

function toggleOnlineLinkInterview() {
    const locationSelect = document.getElementById("locationSelectInterview");
    const onlineLinkField = document.getElementById("onlineLinkFieldInterview");
    const onSiteAddressField = document.getElementById("onSiteAddressField");
    if (locationSelect && onlineLinkField && onSiteAddressField) {
        onlineLinkField.style.display = locationSelect.value === "Online" ? "block" : "none";
        onSiteAddressField.style.display = locationSelect.value === "On-Site" ? "block" : "none";
    }
}

function showResumeModal(studentId) {
  const modal = document.getElementById('resumeModal');
  const modalBody = document.getElementById('resumeModalBody');
  const modalActions = document.getElementById('resumeModalActions');
  
  modalBody.innerHTML = '<p>Loading resume...</p>';
  modalActions.innerHTML = ''; // Clear previous buttons
  modal.style.display = 'block';

  // Validate studentId
  if (!studentId || isNaN(studentId) || parseInt(studentId) <= 0) {
    modalBody.innerHTML = '<p>Invalid student ID provided. Cannot load resume.</p>';
    console.error('Invalid student ID:', studentId);
    return; // Stop execution if studentId is invalid
  }

  fetch(`fetch_full_resume.php?student_id=${studentId}`)
    .then(response => response.json())
    .then(response => {
      if (!response.success) {
        modalBody.innerHTML = `<p>${response.message || 'Could not load resume.'}</p>`;
        return;
      }

      const data = response.data;
      const student = data.student_info;
      
      // Helper for safe HTML
      const h = (str) => {
        const p = document.createElement('p');
        p.textContent = str;
        return p.innerHTML;
      };

      let html = `
        <div class="card" id="resume-content-${studentId}">
          <div class="header">
            <img class="avatar" src="${h(student.profile_picture)}" alt="Profile">
            <div class="title">
              <h1>${h(student.firstname)} ${h(student.lastname)}</h1>
              <div class="sub">
                <span>${h(student.email)}</span> •
                <span>${h(student.contact)}</span> •
                <span>Section: ${h(student.section)}</span>
              </div>
            </div>
          </div>
          <div class="content">
            <section>
              <h2>Objective</h2>
              <div class="block"><p style="margin:0;white-space:pre-wrap;">${h(data.objective)}</p></div>
            </section>
            
            <section>
              <h2>Education</h2>
              <div class="grid">
                ${data.education.map(edu => `
                  <div class="block">
                    <div class="row">
                      <div class="left">${h(edu.school_name)}</div>
                      <div class="muted">${h(edu.start_year)} - ${h(edu.end_year)}</div>
                    </div>
                    ${edu.description ? `<div class="muted" style="margin-top:8px;">${h(edu.description)}</div>` : ''}
                  </div>
                `).join('') || '<div class="block muted">No education entries.</div>'}
              </div>
            </section>

            <section>
              <h2>Certifications</h2>
              <div class="grid">
                ${data.certifications.map(cert => `
                  <div class="block">
                    <div class="left">${h(cert.title)}</div>
                    <div class="muted">${h(cert.issuer)} • ${h(new Date(cert.date_obtained).toLocaleDateString())}</div>
                    ${cert.description ? `<div class="muted" style="margin-top:8px;">${h(cert.description)}</div>` : ''}
                  </div>
                `).join('') || '<div class="block muted">No certifications.</div>'}
              </div>
            </section>

            <section>
              <h2>Skills</h2>
              <div class="skill-grid">
                ${data.skills.map(skill => `
                  <div class="skill">
                    <div style="font-weight:600;">${h(skill.skill_name)}</div>
                    <div class="muted">Proficiency: ${h(skill.proficiency)}</div>
                  </div>
                `).join('') || '<div class="block muted">No skills added.</div>'}
              </div>
            </section>

            <section>
              <h2>Work Experience</h2>
              <div class="grid">
                ${data.experience.map(exp => `
                  <div class="block">
                    <div class="left">${h(exp.company_name)}</div>
                    <div class="muted">${h(exp.position)}</div>
                    <div class="muted">${h(new Date(exp.start_date).toLocaleDateString())} – ${exp.end_date ? h(new Date(exp.end_date).toLocaleDateString()) : 'Present'}</div>
                    ${exp.responsibilities ? `<ul>${exp.responsibilities.split('\\n').map(line => `<li>${h(line)}</li>`).join('')}</ul>` : ''}
                  </div>
                `).join('') || '<div class="block muted">No work experience.</div>'}
              </div>
            </section>

            <div class="actions">
                <button class="btn btn-ghost" onclick="printResume(${studentId})">Print</button>
                <button class="btn btn-ghost" onclick="downloadPDF(${studentId}, '${student.firstname} ${student.lastname}')">Download PDF</button>
            </div>
          </div>
        </div>
      `;
      modalBody.innerHTML = html;

      // Populate the interview modal
      const interviewModalBody = document.getElementById('interviewModalBody');
      const interviewFormHtml = `
        <form action="save_interview.php" method="POST">
            <input type="hidden" name="hr_id" value="<?php echo htmlspecialchars($hr_id); ?>">
            <input type="hidden" name="student_id" value="${studentId}">
            <!-- Hidden fields to send company and internship title -->
            <input type="hidden" name="companyname" id="companyname_input" value="">
            <input type="hidden" name="internship_title" id="internship_title_input" value="">

            <label for="application_id">Application:</label>
            <select name="application_id" id="application_id" required class="form-control">
              <option value="">-- Select Internship Application --</option>
              ${data.applications.map(app => `
                <option value="${h(app.application_id)}" data-company="${h(app.companyname)}" data-title="${h(app.internship_title)}">
                  ${h(app.internship_title)} – ${h(app.companyname)}
                </option>
              `).join('')}
            </select>

            <label for="interview_datetime">Interview Date/Time:</label>
            <input type="datetime-local" id="interview_datetime" name="interview_datetime" required class="form-control">

            <label for="locationSelectInterview">Location:</label>
            <select name="location" id="locationSelectInterview" onchange="toggleOnlineLinkInterview()" class="form-control" required>
                <option value="On-Site">On-Site</option>
                <option value="Online">Online</option>
            </select>

            <div id="onlineLinkFieldInterview" style="display:none;">
                <label for="online_link">Online Meeting Link:</label>
                <input type="url" id="online_link" name="online_link" placeholder="Enter meeting link" class="form-control">
            </div>

            <div id="onSiteAddressField" style="display:block;">
                <label for="exact_address">Interview Address:</label>
                <input type="text" id="exact_address" name="exact_address" placeholder="Enter exact address" class="form-control">
            </div>

            <label for="remarks">Remarks (optional):</label>
            <textarea name="remarks" id="remarks" class="form-control" placeholder="Enter remarks..."></textarea>

            <button type="submit" class="btn btn-primary">Save Interview</button>
        </form>
      `;
      interviewModalBody.innerHTML = interviewFormHtml;

      // Sync selected application's company/title into hidden inputs so save_interview.php gets them directly
      (function syncAppCompanyTitle() {
        const appSelect = document.getElementById('application_id');
        const companyInput = document.getElementById('companyname_input');
        const titleInput = document.getElementById('internship_title_input');
        if (!appSelect || !companyInput || !titleInput) return;
        const setFromSelected = () => {
          const opt = appSelect.options[appSelect.selectedIndex];
          companyInput.value = (opt && opt.dataset && opt.dataset.company) ? opt.dataset.company : '';
          titleInput.value = (opt && opt.dataset && opt.dataset.title) ? opt.dataset.title : '';
        };
        appSelect.addEventListener('change', setFromSelected);
        // set initial values if an application is preselected
        setFromSelected();
      })();
      
      // Also populate the header actions
      modalActions.innerHTML = `
        <button class="btn btn-primary" onclick="openInterviewModal()">Set Interview</button>
        <button onclick="updateApplicationStatus(${studentId}, 'offer')" class="btn btn-primary">Send Offer</button>
        <button onclick="updateApplicationStatus(${studentId}, 'reject')" class="btn btn-ghost" style="color:#dc3545; border-color:#dc3545;">Reject</button>
      `;
    })
    .catch(error => {
      console.error('Error fetching resume:', error);
      modalBody.innerHTML = '<p>Could not load resume. Please try again later.</p>';
    });
}

function closeResumeModal() {
  const modal = document.getElementById('resumeModal');
  modal.style.display = 'none';
}

// Close modal if user clicks outside of it
window.onclick = function(event) {
  const resumeModal = document.getElementById('resumeModal');
  const newChatModal = document.getElementById('newChatModal');
  const interviewModal = document.getElementById('interviewModal');
  if (event.target == resumeModal) {
    closeResumeModal();
  }
  if (event.target == newChatModal) {
    hideNewChatModal();
  }
  if (event.target == interviewModal) {
    closeInterviewModal();
  }
}

function loadInterns() {
  const container = document.getElementById('internsList');
  container.innerHTML = "<p>Loading interns...</p>";

  fetch('fetch_all_interns.php', { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        container.innerHTML = `<p>${data.error}</p>`;
        return;
      }
      if (data.length === 0) {
        container.innerHTML = "<p>No interns found.</p>";
        return;
      }

      let html = '';
      data.forEach(intern => {
        const profilePicture = intern.profile_picture ? intern.profile_picture : 'uploads/dp.jpg';
        html += `
          <div class="supervisor-card">
            <img src="${profilePicture}" alt="Profile Picture" class="supervisor-dp">
            <h4>${intern.firstname} ${intern.lastname}</h4>
            <p>${intern.email}</p>
            <p><strong>Post:</strong> ${intern.post || 'N/A'}</p>
            <p><strong>Supervisor:</strong> ${intern.supervisor_name || 'N/A'}</p>
            <div class="supervisor-card-buttons">
              <button onclick="terminateIntern(${intern.id})">Terminate</button>
            </div>
          </div>
        `;
      });
      container.innerHTML = html;
    })
    .catch(err => {
      console.error(err);
      container.innerHTML = "<p>Error loading interns.</p>";
    });
}

function terminateIntern(internId) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#3085d6',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Yes, terminate them!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('terminate_intern.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ intern_id: internId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    Swal.fire(
                        'Terminated!',
                        'The intern has been terminated.',
                        'success'
                    ).then(() => {
                        loadInterns(); // Refresh the list
                    });
                } else {
                    Swal.fire(
                        'Error!',
                        data.error || 'Something went wrong.',
                        'error'
                    );
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire(
                    'Error!',
                    'Could not terminate the intern.',
                    'error'
                );
            });
        }
    })
}

function loadSupervisors() {
  const container = document.getElementById('supervisorList');
  container.innerHTML = "<p>Loading supervisors...</p>";

  fetch('fetch_supervisors.php', { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        container.innerHTML = `<p>${data.error}</p>`;
        return;
      }
      if (data.length === 0) {
        container.innerHTML = "<p>No supervisors found.</p>";
        return;
      }

      let html = '';
      data.forEach(supervisor => {
        html += `
          <div class="supervisor-card">
            <img src="${supervisor.profile_picture}" alt="Profile Picture" class="supervisor-dp">
            <h4>${supervisor.name}</h4>
            <p>${supervisor.email}</p>
            <div class="supervisor-card-buttons">
              <button onclick="viewInterns(${supervisor.supervisor_id})">View Interns</button>
              <button onclick="assignIntern(${supervisor.supervisor_id})">Assign Intern</button>
            </div>
          </div>
        `;
      });
      container.innerHTML = html;
    })
    .catch(err => {
      console.error(err);
      container.innerHTML = "<p>Error loading supervisors.</p>";
    });
}

function openSupervisorTab(supervisorId) {
  // This will open a new browser tab. 
  // The URL can be changed to a page that shows supervisor details.
  window.open(`supervisor_details.php?id=${supervisorId}`, '_blank');
}

function viewInterns(supervisorId) {
  fetch(`fetch_supervisor_interns.php?supervisor_id=${supervisorId}`, { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        Swal.fire('Error', data.error, 'error');
        return;
      }

      let internListHtml = '<ul style="list-style-type: none; padding: 0; text-align: left;">';
      if (data.length > 0) {
        data.forEach(intern => {
          internListHtml += `<li style="padding: 8px; border-bottom: 1px solid #eee;">${intern.firstname} ${intern.lastname} (${intern.email})</li>`;
        });
      } else {
        internListHtml += '<li>No interns found for this supervisor.</li>';
      }
      internListHtml += '</ul>';

      Swal.fire({
        title: 'Supervisor\'s Interns',
        html: internListHtml,
        confirmButtonText: 'Close'
      });
    })
    .catch(err => {
      console.error(err);
      Swal.fire('Error', 'Could not fetch interns.', 'error');
    });
}

function assignIntern(supervisorId) {
  fetch('fetch_unassigned_interns.php', { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        Swal.fire('Error', data.error, 'error');
        return;
      }

      let optionsHtml = '';
      if (data.length > 0) {
        data.forEach(intern => {
          optionsHtml += `<option value="${intern.student_id}">${intern.firstname} ${intern.lastname}</option>`;
        });
      } else {
        optionsHtml = '<option value="">No unassigned interns available</option>';
      }

      Swal.fire({
        title: 'Assign Intern',
        html: `
          <select id="intern-select" class="swal2-input">
            ${optionsHtml}
          </select>
        `,
        confirmButtonText: 'Assign',
        showCancelButton: true,
        preConfirm: () => {
          const studentId = document.getElementById('intern-select').value;
          if (!studentId) {
            Swal.showValidationMessage('Please select an intern');
            return false;
          }
          return studentId;
        }
      }).then((result) => {
        if (result.isConfirmed) {
          const studentId = result.value;
          const formData = new FormData();
          formData.append('supervisor_id', supervisorId);
          formData.append('student_id', studentId);

          fetch('assign_intern_to_supervisor.php', {
            method: 'POST',
            body: formData,
            credentials: 'include'
          })
          .then(res => res.json())
          .then(response => {
            if (response.success) {
              Swal.fire('Success', response.success, 'success');
            } else {
              Swal.fire('Error', response.error, 'error');
            }
          })
          .catch(err => {
            console.error(err);
            Swal.fire('Error', 'Could not assign intern.', 'error');
          });
        }
      });
    })
    .catch(err => {
      console.error(err);
      Swal.fire('Error', 'Could not fetch unassigned interns.', 'error');
    });
}

// Toggle summary visibility and load applicants
document.querySelectorAll('.job-card').forEach(card => {
  card.addEventListener('click', function(e) {
    // Prevent triggering when clicking buttons inside summary
    if (e.target.tagName === 'BUTTON' || e.target.classList.contains('edit-icon')) return;

    const postId =
    this.dataset.postid;
    const summary = document.getElementById('summary-' + postId);

    if (!summary) return;

    const isVisible = summary.style.display === 'block';
    // Hide all summaries first
    document.querySelectorAll('.job-summary').forEach(s => s.style.display = 'none');

    if (!isVisible) {
      summary.style.display = 'block';
      loadApplicants(postId);
    } else {
      summary.style.display = 'none';
    }
  });
});

function loadApplicants(postId) {
  const applicantsDiv = document.getElementById('applicants-' + postId);
  applicantsDiv.innerHTML = '<strong>Applicants:</strong><p>Loading applicants...</p>';

  fetch(`get_applicants.php?post_id=${postId}`, { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        applicantsDiv.innerHTML = `<strong>Applicants:</strong><p>${data.error}</p>`;
        return;
      }
      if (data.length === 0) {
        applicantsDiv.innerHTML = `<strong>Applicants:</strong><p>No applicants yet.</p>`;
        return;
      }
let html = '<strong>Applicants:</strong><ul>';
data.forEach(applicant => {
  html += `<li>
    ${applicant.firstname} ${applicant.lastname} - ${applicant.email}

  </li>`;
});
html += '</ul>';
applicantsDiv.innerHTML = html;
    })
    
    .catch(err => {
      applicantsDiv.innerHTML = `<strong>Applicants:</strong><p>Error loading applicants.</p>`;
      console.error(err);
    });
}

function editPost(postId) {
  fetch(`get_post.php?post_id=${postId}`, {credentials: 'include'})
    .then(res => {
      if (!res.ok) throw new Error('Failed to fetch post data');
      return res.json();
    })
    .then(data => {
      if (data.error) {
        alert(data.error);
        return;
      }
      createPostBtn.style.display = 'none';
      postsList.style.display = 'none';
      createPostForm.style.display = 'block';

      document.getElementById('internship_title').value = data.internship_title;
      document.getElementById('location').value = data.location;
      document.getElementById('internship_description').value = data.internship_description;
      document.getElementById('allowance').value = data.allowance;
      document.getElementById('application_deadline').value = data.application_deadline;
      document.getElementById('email').value = data.email;

      createPostForm.dataset.editPostId = postId;
    })
    .catch(err => {
      alert('Error loading post data: ' + err.message);
    });
}

function closePost(postId) {
  if (!confirm("Are you sure you want to close this post?")) return;

  fetch('close_post.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
    body: `post_id=${postId}`
  })
  .then(res => res.text())
  .then(data => {
    alert(data);
    location.reload();
  })
  .catch(err => alert("Error closing post."));
}

const createPostBtn = document.getElementById('createPostBtn');
const postsList = document.getElementById('postsList');
const createPostForm = document.getElementById('createPostForm');

createPostBtn.addEventListener('click', () => {
  createPostBtn.style.display = 'none';
  postsList.style.display = 'none';
  createPostForm.style.display = 'block';
});

function cancelCreate() {
  createPostForm.style.display = 'none';
  postsList.style.display = 'block';
  createPostBtn.style.display = 'inline-block';
  createPostForm.reset();
}

createPostForm.addEventListener('submit', function(e) {
  e.preventDefault();

  const formData = new FormData(createPostForm);
  
  formData.append('companyname', "<?= addslashes($companyname) ?>");
  formData.append('posted_by', "<?= addslashes($companyhr['hr_id']) ?>");
  formData.append('status', 'Active');
  
  // Add the current date for date_posted
  const today = new Date();
  const yyyy = today.getFullYear();
  let mm = today.getMonth() + 1; // Months start at 0!
  let dd = today.getDate();
  if (dd < 10) dd = '0' + dd;
  if (mm < 10) mm = '0' + mm;
  const formattedToday = yyyy + '-' + mm + '-' + dd;
  formData.append('date_posted', formattedToday);

  fetch('create_post.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.text())
  .then(data => {
    if (data.toLowerCase().includes('success')) {
      Swal.fire({
        title: 'Success!',
        text: 'Job post created successfully!',
        icon: 'success'
      }).then(() => {
        location.reload();
      });
    } else {
      Swal.fire({
        title: 'Error!',
        text: data,
        icon: 'error'
      });
    }
  })
  .catch(() => {
    Swal.fire({
      title: 'Error!',
      text: 'Failed to create post.',
      icon: 'error'
    });
  });
});
function viewApplicants(postId) {
  // Switch tab
  document.querySelectorAll('.main-content, .profile').forEach(c => c.classList.remove('active'));
  document.getElementById('applicantsContent').classList.add('active');

  const container = document.getElementById('applicantsList');
  container.innerHTML = "<p>Loading applicants...</p>";
  container.className = 'supervisor-cards-container';

  fetch(`get_applicants.php?post_id=${postId}`, { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
      if (data.error) {
        container.innerHTML = `<p>${data.error}</p>`;
        return;
      }
      if (data.length === 0) {
        container.innerHTML = "<p>No applicants yet.</p>";
        return;
      }

      let html = '';
      data.forEach(applicant => {
        const profilePic = applicant.profile_picture ? applicant.profile_picture : 'uploads/dp.jpg';
        html += `
          <div class="supervisor-card">
            <img src="${profilePic}" alt="Profile Picture" class="supervisor-dp">
            <h4>${applicant.firstname} ${applicant.lastname}</h4>
            <p>${applicant.email}</p>
            <div class="supervisor-card-buttons">
              <button onclick="showResumeModal(${applicant.student_id})">
                View Resume
              </button>
            </div>
          </div>
        `;
      });
      container.innerHTML = html;
    })
    .catch(err => {
      console.error(err);
      container.innerHTML = "<p>Error loading applicants.</p>";
    });
}

function filterApplicants() {
  const input = document.getElementById('applicantSearch');
  const filter = input.value.toUpperCase();
  const grid = document.getElementById('applicantsList');
  const cards = grid.getElementsByClassName('supervisor-card');

  for (let i = 0; i < cards.length; i++) {
    const h4 = cards[i].getElementsByTagName("h4")[0];
    const p = cards[i].getElementsByTagName("p")[0];
    if (h4 || p) {
      const txtValue = (h4.textContent || h4.innerText) + (p.textContent || p.innerText);
      if (txtValue.toUpperCase().indexOf(filter) > -1) {
        cards[i].style.display = "";
      } else {
        cards[i].style.display = "none";
      }
    }       
  }
}

function filterInterns() {
  const input = document.getElementById('internSearch');
  const filter = input.value.toUpperCase();
  const grid = document.getElementById('internsList');
  const cards = grid.getElementsByClassName('supervisor-card'); // Assuming interns also use supervisor-card class

  for (let i = 0; i < cards.length; i++) {
    const h4 = cards[i].getElementsByTagName("h4")[0];
    const p = cards[i].getElementsByTagName("p")[0]; // Assuming email is in the first p tag
    if (h4 || p) {
      const txtValue = (h4.textContent || h4.innerText) + (p.textContent || p.innerText);
      if (txtValue.toUpperCase().indexOf(filter) > -1) {
        cards[i].style.display = "";
      } else {
        cards[i].style.display = "none";
      }
    }       
  }
}

function filterSupervisors() {
  const input = document.getElementById('supervisorSearch');
  const filter = input.value.toUpperCase();
  const grid = document.getElementById('supervisorList');
  const cards = grid.getElementsByClassName('supervisor-card');

  for (let i = 0; i < cards.length; i++) {
    const h4 = cards[i].getElementsByTagName("h4")[0];
    const p = cards[i].getElementsByTagName("p")[0]; // Assuming email is in the first p tag
    if (h4 || p) {
      const txtValue = (h4.textContent || h4.innerText) + (p.textContent || p.innerText);
      if (txtValue.toUpperCase().indexOf(filter) > -1) {
        cards[i].style.display = "";
      } else {
        cards[i].style.display = "none";
      }
    }       
  }
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

// Location functionality
function loadActiveLocation() {
  fetch('fetch_active_location.php', { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
      const activeLocationText = document.getElementById('activeLocationText');
      if (data.success && data.location) {
        activeLocationText.textContent = `${data.location.location_name} (${data.location.radius}m radius)`;
      } else {
        activeLocationText.textContent = 'No active location set';
      }
    })
    .catch(err => {
      console.error('Error loading active location:', err);
      document.getElementById('activeLocationText').textContent = 'Error loading location';
    });
}

function searchLocations() {
  const query = document.getElementById('locationSearch').value.trim();
  const suggestionsDiv = document.getElementById('locationSuggestions');

  if (query.length < 2) {
    suggestionsDiv.style.display = 'none';
    return;
  }

  fetch(`search_locations.php?query=${encodeURIComponent(query)}`, { credentials: 'include' })
    .then(res => res.json())
    .then(data => {
      suggestionsDiv.innerHTML = '';
      if (data.success && data.locations.length > 0) {
        data.locations.forEach(location => {
          const div = document.createElement('div');
          div.className = 'location-suggestion';
          div.textContent = location.location_name;
          div.style.padding = '8px';
          div.style.cursor = 'pointer';
          div.style.borderBottom = '1px solid #eee';
          div.onmouseover = () => div.style.backgroundColor = '#f0f0f0';
          div.onmouseout = () => div.style.backgroundColor = '';
          div.onclick = () => selectLocation(location);
          suggestionsDiv.appendChild(div);
        });
        suggestionsDiv.style.display = 'block';
      } else {
        suggestionsDiv.style.display = 'none';
      }
    })
    .catch(err => {
      console.error('Error searching locations:', err);
      suggestionsDiv.style.display = 'none';
    });
}

function selectLocation(location) {
  document.getElementById('selectedLocation').value = location.location_name;
  document.getElementById('locationSearch').value = location.location_name;
  document.getElementById('locationSuggestions').style.display = 'none';
}

function saveGeofenceLocation() {
  const locationName = document.getElementById('selectedLocation').value.trim();
  const radius = parseInt(document.getElementById('locationRadius').value);
  const latitude = document.getElementById('latitude').value;
  const longitude = document.getElementById('longitude').value;

  if (!locationName) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Please select a location first.'
    });
    return;
  }

  const formData = new FormData();
  formData.append('location_name', locationName);
  formData.append('radius', radius);
  formData.append('latitude', latitude);
  formData.append('longitude', longitude);

  Swal.fire({
    title: 'Save Geofence Location?',
    html: `<strong>${locationName}</strong><br>Radius: <strong>${radius} m</strong>`,
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Save',
    cancelButtonText: 'Cancel',
    allowOutsideClick: () => !Swal.isLoading(),
    preConfirm: () => {
      return fetch('save_location.php', {
        method: 'POST',
        body: formData,
        credentials: 'include'
      })
      .then(async (res) => {
        const text = await res.text();
        // Try to parse JSON; if parse fails, throw full text for debug
        let parsed;
        try {
          parsed = text ? JSON.parse(text) : {};
        } catch (err) {
          throw new Error('Invalid server response: ' + text);
        }
        if (!res.ok) {
          const msg = parsed && (parsed.error || parsed.message) ? (parsed.error || parsed.message) : `HTTP ${res.status}`;
          throw new Error(msg);
        }
        if (!parsed.success) {
          throw new Error(parsed.error || parsed.message || 'Save failed');
        }
        return parsed;
      })
      .catch(err => {
        // Show validation message inside Swal and keep the modal open
        Swal.showValidationMessage(`Request failed: ${err.message}`);
      });
    }
  }).then((result) => {
    if (result.isConfirmed && result.value) {
      const data = result.value;
      Swal.fire({
        icon: 'success',
        title: 'Saved',
        text: data.message || 'Geofence location saved successfully!'
      }).then(() => {
        loadActiveLocation();
        goHome();
      });
    }
    // If cancelled or validation failed, nothing to do (preConfirm already handled errors)
  });
}

// Add event listener for location search input
document.addEventListener('DOMContentLoaded', () => {
  const locationSearchInput = document.getElementById('locationSearch');
  if (locationSearchInput) {
    locationSearchInput.addEventListener('input', debounce(searchLocations, 300));
  }
});

// Interactive Map Functionality
let map;
let marker;
let circle;
let geocoder;

function initializeMap() {
  // Initialize map centered on Philippines
  map = L.map('mapContainer').setView([12.8797, 121.7740], 6);

  // Add OpenStreetMap tiles
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap contributors'
  }).addTo(map);

  // Initialize geocoder (not added to map to avoid built-in control)
  geocoder = L.Control.geocoder({
    defaultMarkGeocode: false
  });

  // Add event listener for save button
  document.getElementById('saveBtn').addEventListener('click', function() {
    saveGeofenceLocation();
  });

  // Add search functionality
  document.getElementById('searchBtn').addEventListener('click', function() {
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
  });

  // Add input event listener for suggestions
  document.getElementById('mapSearch').addEventListener('input', debounce(showMapSearchSuggestions, 300));

  // Allow clicking on map to place marker
  map.on('click', function(e) {
    placeMarker(e.latlng);
  });

  // Try to get user's current location
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(function(position) {
      const latlng = L.latLng(position.coords.latitude, position.coords.longitude);
      map.setView(latlng, 13);
      placeMarker(latlng);
    }, function(error) {
      console.log('Geolocation error:', error);
      // No marker placed if geolocation fails
    });
  } else {
    // No marker placed if geolocation not supported
    console.log('Geolocation not supported');
  }
}

function placeMarker(latlng) {
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
  const radius = parseInt(document.getElementById('locationRadius').value) || 100;
  circle = L.circle(latlng, {
    color: '#116530',
    fillColor: '#116530',
    fillOpacity: 0.2,
    radius: radius
  }).addTo(map);

  // Make circle resizable by dragging edge
  circle.on('mousedown', function(e) {
    map.dragging.disable();
    const originalRadius = circle.getRadius();
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
  fetch(`reverse_geocode.php?lat=${latlng.lat}&lon=${latlng.lng}`)
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
document.getElementById('locationRadius').addEventListener('input', function() {
  const radius = parseInt(this.value);
  if (circle && radius >= 1 && radius <= 1000) {
    circle.setRadius(radius);
    if (marker) {
      const bounds = L.latLngBounds([marker.getLatLng()]).extend(circle.getBounds());
      map.fitBounds(bounds, {padding: [20, 20]});
    }
  }
});

// Initialize map when location tab is shown
function showLocation() {
  document.querySelectorAll('.main-content, .profile').forEach(c => c.classList.remove('active'));
  document.getElementById('locationContent').classList.add('active');
  loadActiveLocation();

  // Initialize map if not already done
  if (!map) {
    setTimeout(initializeMap, 100);
  }
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

    // Function to load and display companyhr announcements
    async function loadCompanyhrAnnouncements() {
      const announcementsContainer = document.getElementById('companyhrAnnouncementsContainer');
      if (!announcementsContainer) return;

      announcementsContainer.innerHTML = '<p>Loading announcements...</p>';

      try {
        const response = await fetch('fetch_announcements.php?audience=companyhr'); // Use the generic fetch_announcements.php
        const data = await response.json();

        if (data.success) {
          if (data.announcements.length > 0) {
            let html = '';
            data.announcements.forEach(announcement => {
              html += `
                <div class="job-card announcement-card">
                  <h4>${escapeHtml(announcement.title)}</h4>
                  <p>${escapeHtml(announcement.content)}</p>
                  <p><strong>Posted by:</strong> ${escapeHtml(announcement.faculty_name)}</p>
                  <p><strong>Date:</strong> ${new Date(announcement.date_posted).toLocaleString()}</p>
                </div>
              `;
            });
            announcementsContainer.innerHTML = html;
          } else {
            announcementsContainer.innerHTML = '<p>No announcements for companies at this time.</p>';
          }
        } else {
          announcementsContainer.innerHTML = `<p class="info-list">Error: ${data.message || 'Unknown error'}</p>`;
        }
      } catch (error) {
        console.error('Error fetching companyhr announcements:', error);
        announcementsContainer.innerHTML = '<p class="info-list">Error loading data.</p>';
      }
    }

    // Function to load and display "Newest Applicants"
    async function loadNewestApplicants() {
      const container = document.getElementById('newestApplicants');
      if (!container) return;

      container.innerHTML = '<p>Loading...</p>';

      try {
        const response = await fetch('fetch_dashboard_data.php?action=newest_applicants&role=companyhr');
        const data = await response.json();

        if (data.success) {
          if (data.applicants && data.applicants.length > 0) {
            let html = '<div class="supervisor-cards-container">'; // Use a container for cards
            data.applicants.forEach(applicant => {
              const profilePic = applicant.profile_picture && applicant.profile_picture !== '' ? applicant.profile_picture : 'uploads/dp.jpg';
              html += `
                <div class="supervisor-card">
                  <img src="${escapeHtml(profilePic)}" alt="Profile Picture" class="supervisor-dp">
                  <h4>${escapeHtml(applicant.applicant_name)}</h4>
                  <p>${escapeHtml(applicant.email)}</p>
                  <p><strong>Applied for:</strong> ${escapeHtml(applicant.internship_title)}</p>
                  <p><strong>Date Applied:</strong> ${new Date(applicant.date_applied).toLocaleDateString()}</p>
                  <div class="supervisor-card-buttons">
                    <!-- View Resume button removed as per user request -->
                  </div>
                </div>
              `;
            });
            html += '</div>';
            container.innerHTML = html;
          } else {
            container.innerHTML = '<p class="info-list">No newest applicants found. Make sure you have active job posts and students have applied to them.</p>';
          }
        } else {
          container.innerHTML = `<p class="info-list">Error: ${data.message || 'Unknown error'}</p>`;
        }
      } catch (error) {
        console.error('Error fetching newest applicants:', error);
        container.innerHTML = '<p class="info-list">Error loading data.</p>';
      }
    }

    // Function to load and display "Interns Currently in Progress"
    async function loadInternsInProgress() {
      const container = document.getElementById('internsInProgress');
      if (!container) return;

      container.innerHTML = '<p>Loading...</p>';

      try {
        const response = await fetch('fetch_dashboard_data.php?action=interns_currently_in_progress&role=companyhr');
        const data = await response.json();

        if (data.success) {
          if (data.interns && data.interns.length > 0) {
            let html = '<div class="supervisor-cards-container">'; // Use a container for cards
            data.interns.forEach(intern => {
              const profilePic = intern.profile_picture && intern.profile_picture !== '' ? intern.profile_picture : 'uploads/dp.jpg';
              html += `
                <div class="supervisor-card">
                  <img src="${escapeHtml(profilePic)}" alt="Profile Picture" class="supervisor-dp">
                  <h4>${escapeHtml(intern.intern_name)}</h4>
                  <p>${escapeHtml(intern.email)}</p>
                  <p><strong>Progress:</strong> ${escapeHtml(intern.progress_percent)}%</p>
                  <p><strong>Start Date:</strong> ${new Date(intern.start_date).toLocaleDateString()}</p>
                  <!-- Add more intern details or actions here if needed -->
                </div>
              `;
            });
            html += '</div>';
            container.innerHTML = html;
          } else {
            container.innerHTML = '<p class="info-list">No interns in progress found.</p>';
          }
        } else {
          container.innerHTML = `<p class="info-list">Error: ${data.message || 'Unknown error'}</p>`;
        }
      } catch (error) {
        console.error('Error fetching interns in progress:', error);
        container.innerHTML = '<p class="info-list">Error loading data.</p>';
      }
    }

    // Partnership Request Status
    let partnershipStatusRefreshInterval = null;

    function startPartnershipStatusAutoRefresh() {
      if (partnershipStatusRefreshInterval) {
        clearInterval(partnershipStatusRefreshInterval);
      }
      partnershipStatusRefreshInterval = setInterval(loadPartnershipRequestStatus, 10000); // Refresh every 10 seconds
    }

    function stopPartnershipStatusAutoRefresh() {
      if (partnershipStatusRefreshInterval) {
        clearInterval(partnershipStatusRefreshInterval);
      }
    }

    async function loadPartnershipRequestStatus() {
      const statusMessageElement = document.getElementById('partnershipStatusMessage');
      const requestTabContent = document.getElementById('requestTabContent');
      const sidebarLinks = document.querySelectorAll('.sidebar a');

      try {
        const response = await fetch('fetch_company_partnership_status.php');
        const data = await response.json();

        if (data.success) {
          const status = data.status;
          let messageHtml = '';
          let requestTabHtml = '';

          // First, reset all links to enabled state
          sidebarLinks.forEach(link => {
              if (link.dataset.originalHref) {
                  link.href = link.dataset.originalHref;
                  link.classList.remove('disabled-link');
                  delete link.dataset.originalHref; // Clean up stored href
              }
          });

          // Update main dashboard message and apply disabling logic based on status
          if (status === 'approved') {
            messageHtml = "<strong style='color:green;'>Now an official partner of UDM</strong>";
            requestTabHtml = "<p>Your partnership request has been <strong style='color:green;'>approved!</strong> You now have full access to all features.</p>";
            // All links are already enabled by the reset above
          } else if (status === 'pending') {
            messageHtml = "Wait 1–3 days while we're reviewing your request.";
            requestTabHtml = "<p>Your partnership request is currently <strong style='color:orange;'>pending</strong>. Please wait 1-3 days for review.</p>";
            // Disable restricted sidebar links
            sidebarLinks.forEach(link => {
                const onclickAttr = link.getAttribute('onclick') || '';
                const match = onclickAttr.match(/'([^']+)'/);
                const tabId = match ? match[1] : null;
                if (tabId && ['jobpost','interns','supervisors'].includes(tabId)) {
                    if (!link.dataset.originalHref) link.dataset.originalHref = link.href; // Store original href
                    link.href = '#'; // Disable link
                    link.classList.add('disabled-link');
                }
            });
          } else { // null or rejected
            messageHtml = "Some features are locked until you request partnership with UDM.";
            requestTabHtml = `
              <p>You currently do not have a partnership with UDM. Request one to unlock all features.</p>
              <button id="requestBtn" onclick="requestPartnership()">Request Partnership with UDM</button>
            `;
            // Disable restricted sidebar links
            sidebarLinks.forEach(link => {
                const onclickAttr = link.getAttribute('onclick') || '';
                const match = onclickAttr.match(/'([^']+)'/);
                const tabId = match ? match[1] : null;
                if (tabId && ['jobpost','interns','supervisors'].includes(tabId)) {
                    if (!link.dataset.originalHref) link.dataset.originalHref = link.href; // Store original href
                    link.href = '#'; // Disable link
                    link.classList.add('disabled-link');
                }
            });
          }

          // Also show the "Request Partnership" button in the main dashboard area
          // only when not approved and not pending (i.e. no partnership or rejected).
          let mainActionHtml = messageHtml;
          if (status !== 'approved' && status !== 'pending') {
            mainActionHtml += ` <button id="requestBtnMain" onclick="requestPartnership()">Request Partnership with UDM</button>`;
          }

          statusMessageElement.innerHTML = mainActionHtml;
          requestTabContent.innerHTML = requestTabHtml;
        } else {
          statusMessageElement.innerHTML = `<p class="info-list">Error: ${data.message || 'Unknown error'}</p>`;
          requestTabContent.innerHTML = `<p class="info-list">Error loading status: ${data.error || 'Unknown error'}</p>`;
        }
      } catch (error) {
        console.error('Error fetching partnership status:', error);
        statusMessageElement.innerHTML = '<p class="info-list">Error loading partnership status.</p>';
        requestTabContent.innerHTML = '<p class="info-list">Error loading partnership status.</p>';
      }
    }

    // Override showTab to include dashboard data loading for mainContent and manage auto-refresh
    const originalShowTabForCompany = showTab;
    showTab = function(tabId) {
      originalShowTabForCompany(tabId);
      if (tabId === 'mainContent') {
        loadCompanyhrAnnouncements();
        loadNewestApplicants();
        loadInternsInProgress();
        // Partnership status is now loaded globally on script execution, no need to re-load here
        startPartnershipStatusAutoRefresh(); // Restart auto-refresh if returning to main content
      } else if (tabId === 'requestTab') {
        // Partnership status is now loaded globally on script execution, no need to re-load here
        startPartnershipStatusAutoRefresh(); // Restart auto-refresh if returning to request tab
      } else {
        stopPartnershipStatusAutoRefresh(); // Stop auto-refresh when leaving relevant tabs
      }
    };

    // Initial load of partnership status and dashboard data
    // This ensures links are disabled immediately on page load if not approved
    loadPartnershipRequestStatus();
    startPartnershipStatusAutoRefresh(); // Start auto-refresh immediately

    document.addEventListener('DOMContentLoaded', () => {
      // Load dashboard specific data only if mainContent is active
      if (document.getElementById('mainContent').classList.contains('active')) {
        loadCompanyhrAnnouncements();
        loadNewestApplicants();
        loadInternsInProgress();
      }
    });

    // Handle interview form submission with SweetAlert
    document.addEventListener('DOMContentLoaded', () => {
        const interviewModalBody = document.getElementById('interviewModalBody');
        if (interviewModalBody) {
            // Use a MutationObserver to detect when the form is added to the DOM
            const observer = new MutationObserver((mutationsList, observer) => {
                for (const mutation of mutationsList) {
                    if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                        const form = interviewModalBody.querySelector('form');
                        if (form) {
                            form.addEventListener('submit', async function(event) {
                                event.preventDefault();

                                const formData = new FormData(this);

                                // Basic validation
                                if (!formData.get('application_id')) {
                                    Swal.fire('Error', 'Please select an internship application.', 'error');
                                    return;
                                }
                                if (!formData.get('interview_datetime')) {
                                    Swal.fire('Error', 'Please select an interview date and time.', 'error');
                                    return;
                                }
                                const locationType = formData.get('location');
                                if (locationType === 'Online' && !formData.get('online_link')) {
                                    Swal.fire('Error', 'Please provide an online meeting link.', 'error');
                                    return;
                                }
                                if (locationType === 'On-Site' && !formData.get('exact_address')) {
                                    Swal.fire('Error', 'Please provide an exact interview address.', 'error');
                                    return;
                                }

                                try {
                                    const response = await fetch('save_interview.php', {
                                        method: 'POST',
                                        body: formData,
                                        credentials: 'include',
                                        headers: {
                                            'X-Requested-With': 'XMLHttpRequest',
                                            'Accept': 'application/json'
                                        }
                                    });

                                    const contentType = response.headers.get('content-type') || '';
                                    let parsed = null;

                                    // Read raw text first so we can handle HTML error pages or JSON
                                    const raw = await response.text();

                                    // If response is JSON-like, attempt parse; otherwise treat as non-JSON
                                    if (contentType.includes('application/json') || raw.trim().startsWith('{') || raw.trim().startsWith('[')) {
                                        try {
                                            parsed = JSON.parse(raw);
                                        } catch (err) {
                                            console.error('Failed to parse JSON from save_interview.php response:', raw);
                                            Swal.fire('Error', 'Unexpected server response. Check console for details.', 'error');
                                            return;
                                        }
                                    } else {
                                        // Non-JSON response (likely HTML) — log full response for debugging and show generic error
                                        console.error('Non-JSON response from save_interview.php:', raw);
                                        // If response contains a login page, detect and force redirect
                                        if (raw.includes('<!DOCTYPE') && raw.toLowerCase().includes('login')) {
                                            Swal.fire({
                                                icon: 'error',
                                                title: 'Session expired',
                                                text: 'Please login again.'
                                            }).then(() => window.location.href = 'login.php');
                                            return;
                                        }
                                        Swal.fire('Error', 'Server returned an unexpected response. Check console for details.', 'error');
                                        return;
                                    }

                                    // At this point parsed should be an object
                                    if (!response.ok) {
                                        // server responded with JSON error
                                        const msg = (parsed && parsed.message) ? parsed.message : 'Server returned an error.';
                                        Swal.fire('Error', msg, 'error');
                                        return;
                                    }

                                    if (parsed && parsed.success) {
                                        Swal.fire({
                                            icon: 'success',
                                            title: 'Interview Saved',
                                            text: parsed.message || 'Interview scheduled successfully.'
                                        }).then(() => {
                                            closeInterviewModal();
                                            // refresh relevant UI if needed
                                            if (typeof viewApplicants === 'function') {
                                                const activePostId = document.getElementById('applicantsContent')?.dataset?.postId;
                                                if (activePostId) viewApplicants(activePostId);
                                            }
                                        });
                                    } else {
                                        Swal.fire('Error', (parsed && parsed.message) ? parsed.message : 'An unknown error occurred.', 'error');
                                    }
                                } catch (networkErr) {
                                    console.error('Network or unexpected error while saving interview:', networkErr);
                                    Swal.fire('Error', 'Network error while saving interview. Check console for details.', 'error');
                                }
                            });
                            observer.disconnect(); // Stop observing once the form is found and handled
                        }
                    }
                }
            });

            // Start observing the interviewModalBody for childList changes
            observer.observe(interviewModalBody, { childList: true, subtree: true });
        }
    });

    // Function to toggle the dropdown menu
    function toggleDropdown() {
        const dropdown = document.querySelector('.dropdown'); // Target the parent .dropdown element
        dropdown.classList.toggle("active");
    }

    // Close the dropdown if the user clicks outside of it
    window.addEventListener('click', function(event) {
        if (!event.target.matches('.dropbtn')) {
            const dropdowns = document.getElementsByClassName("dropdown"); // Get all parent .dropdown elements
            for (let i = 0; i < dropdowns.length; i++) {
                const openDropdown = dropdowns[i];
                if (openDropdown.classList.contains('active')) {
                    openDropdown.classList.remove('active');
                }
            }
        }
    });
</script>
</body>
</html>

<?php
session_start();
include 'db.php';  // ✅ ensure DB connection is included

// Check HR session
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    header("Location: login.php");
    exit();
}

$hr_id = $_SESSION['user_id'];

// Fetch HR Company Name
$stmt = $conn->prepare("SELECT companyname FROM companyhr WHERE hr_id = ?");
$stmt->bind_param("i", $hr_id);
$stmt->execute();
$stmt->bind_result($companyname);
$stmt->fetch();
$stmt->close();

// Fetch Internship Title of applicant
if (isset($_GET['student_id'])) {
    $student_id = $_GET['student_id'];

$stmt = $conn->prepare("
    SELECT ia.application_id, ip.internship_title, ch.companyname
    FROM intern_applications ia
    JOIN internship_posts ip ON ia.post_id = ip.post_id
    JOIN companyhr ch ON ch.hr_id = ip.posted_by
    WHERE ia.student_id = ? AND ip.posted_by = ?
");
$stmt->bind_param("ii", $student_id, $hr_id);
$stmt->execute();
$result = $stmt->get_result();
$applications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
}

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    header("Location: login.php");
    exit();
}

if (!isset($_GET['student_id']) || !is_numeric($_GET['student_id'])) {
    die("Invalid student ID.");
}

$student_id = intval($_GET['student_id']);

// Student basic info (include profile picture if available)
$stmt = $conn->prepare("SELECT firstname, lastname, email, contact, section, COALESCE(profile_picture, 'uploads/dp.jpg') AS profile_picture FROM student WHERE student_id = ?");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($firstname, $lastname, $email, $contact, $section, $profile_picture);
$stmt->fetch();
$stmt->close();

// Resume main record
$stmt = $conn->prepare("SELECT resume_id, objective, created_at, updated_at FROM resumes WHERE student_id = ? ORDER BY updated_at DESC, created_at DESC LIMIT 1");
$stmt->bind_param("i", $student_id);
$stmt->execute();
$stmt->bind_result($resume_id, $objective, $created_at, $updated_at);
$stmt->fetch();
$stmt->close();

if (!$resume_id) {
    // Minimal themed page if no resume
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
      <meta charset="UTF-8">
      <title>No Resume</title>
      <style>
        :root { --green:#116530; --green-dark:#0e5128; --muted:#eef7ee; --text:#1e293b; }
        *{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,Arial,sans-serif;background:#fff;color:var(--text)}
        .wrap{max-width:900px;margin:32px auto;padding:24px}
        .card{background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 6px 20px rgba(0,0,0,.06);overflow:hidden}
        .header{display:flex;align-items:center;gap:16px;padding:20px;border-bottom:1px solid #e5e7eb;background:linear-gradient(180deg,#ffffff, #f8fff9)}
        .avatar{width:72px;height:72px;border-radius:999px;object-fit:cover;border:3px solid var(--green)}
        h1{margin:0;font-size:22px}
        .badge{display:inline-block;background:var(--muted);color:var(--green);padding:6px 10px;border-radius:999px;font-weight:600;font-size:12px;margin-top:6px}
        .content{padding:22px}
        .note{background:#fff8f0;border:1px solid #fde7c7;color:#92400e;padding:14px;border-radius:12px}
        .actions{display:flex;gap:8px;margin-top:16px;flex-wrap:wrap}
        .btn{appearance:none;border:none;padding:10px 14px;border-radius:10px;font-weight:600;cursor:pointer}
        .btn-primary{background:var(--green);color:#fff}
        .btn-primary:hover{background:var(--green-dark)}
        .btn-ghost{background:#fff;border:1px solid #e5e7eb}
        @media print {.actions{display:none}}
      </style>
    </head>
    <body>
      <div class="wrap">
        <div class="card">
          <div class="header">
            <img class="avatar" src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile">
            <div>
              <h1><?php echo htmlspecialchars("$firstname $lastname"); ?></h1>
              <div class="badge">No resume on file</div>
            </div>
          </div>
          <div class="content">
            <p class="note">This applicant hasn’t created a resume yet.</p>
            <div class="actions">
              <button class="btn btn-primary" onclick="window.close()">Close</button>
              <button class="btn btn-ghost" onclick="history.back()">Back</button>
            </div>
          </div>
        </div>
      </div>
    </body>
    </html>
    <?php
    exit();
}

// Helper to fetch related rows
function fetchRows($conn, $table, $resume_id) {
    $rows = [];
    $sql = "SELECT * FROM $table WHERE resume_id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $resume_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) { $rows[] = $row; }
    $stmt->close();
    return $rows;
}

$certifications = fetchRows($conn, "certifications", $resume_id);
$education      = fetchRows($conn, "education", $resume_id);
$skills         = fetchRows($conn, "skills", $resume_id);
$experience     = fetchRows($conn, "work_experience", $resume_id);

// Simple format helpers
function h($v){ return htmlspecialchars((string)$v ?? ''); }
function dt($v){
    if (!$v) return '';
    $t = strtotime($v);
    return $t ? date('M d, Y', $t) : $v;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Resume – <?php echo h("$firstname $lastname"); ?></title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <style>
    :root { --green:#116530; --green-dark:#0e5128; --muted:#eef7ee; --text:#1e293b; }
    *{box-sizing:border-box}
    body{margin:0;background:#ffffff;color:var(--text);font-family:Inter,system-ui,Arial,sans-serif}
    .wrap{max-width:1000px;margin:32px auto;padding:24px}
    .card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 10px 30px rgba(0,0,0,.08);overflow:hidden}
    .header{
      display:flex;gap:18px;align-items:center;padding:22px;border-bottom:1px solid #e5e7eb;
      background:linear-gradient(180deg,#ffffff,#f7fff9);
    }
    .avatar{width:82px;height:82px;border-radius:999px;object-fit:cover;border:4px solid var(--green)}
    .title h1{margin:0;font-size:24px;line-height:1.2}
    .sub{margin-top:6px;font-size:14px;color:#475569}
    .chips{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
    .chip{background:var(--muted);color:var(--green);padding:6px 10px;border-radius:999px;font-weight:600;font-size:12px}

    .content{padding:24px}
    section{margin-bottom:24px}
    section h2{
      margin:0 0 12px 0;font-size:18px;color:var(--green);
      display:flex;align-items:center;gap:8px;
    }
    .block{background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;padding:16px}
    .row{display:flex;gap:12px;justify-content:space-between;align-items:flex-start}
    .left{font-weight:600}
    .muted{color:#64748b}
    ul{margin:0;padding-left:18px}
    li{margin:6px 0}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
    .skill-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}
    .skill{background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:10px}

    .actions{display:flex;gap:8px;margin-top:6px;flex-wrap:wrap}
    .btn{appearance:none;border:none;padding:10px 14px;border-radius:10px;font-weight:600;cursor:pointer}
    .btn-primary{background:var(--green);color:#fff}
    .btn-primary:hover{background:var(--green-dark)}
    .btn-ghost{background:#fff;border:1px solid #e5e7eb}
    @media (max-width:720px){ .grid{grid-template-columns:1fr} .header{flex-direction:column;align-items:flex-start} }
    @media print {.actions{display:none} .wrap{margin:0;max-width:none;padding:0} .card{border:none;box-shadow:none;border-radius:0} }
  </style>
</head>
<body>
  <div class="wrap" id="resume-content">
    <div class="card">
      <div class="header">
        <img class="avatar" src="<?php echo h($profile_picture); ?>" alt="Profile">
        <div class="title">
          <h1><?php echo h("$firstname $lastname"); ?></h1>
          <div class="sub">
            <span><?php echo h($email); ?></span> •
            <span><?php echo h($contact); ?></span> •
            <span>Section: <?php echo h($section); ?></span>
          </div>
          <div class="chips">
            <span class="chip">Resume ID: <?php echo (int)$resume_id; ?></span>
            <?php if ($updated_at): ?><span class="chip">Updated: <?php echo h(dt($updated_at)); ?></span><?php endif; ?>
            <?php if ($created_at): ?><span class="chip">Created: <?php echo h(dt($created_at)); ?></span><?php endif; ?>
          </div>
        </div>
      </div>

      <div class="content">

        <section>
          <h2>Objective</h2>
          <div class="block">
            <p style="margin:0;white-space:pre-wrap;"><?php echo nl2br(h($objective)); ?></p>
          </div>
        </section>

        <section>
          <h2>Education</h2>
          <?php if (count($education) === 0): ?>
            <div class="block muted">No education entries.</div>
          <?php else: ?>
            <div class="grid">
            <?php foreach ($education as $edu): ?>
              <div class="block">
                <div class="row">
                  <div class="left"><?php echo h($edu['school_name']); ?></div>
                  <div class="muted"><?php echo h($edu['start_year'])." - ".h($edu['end_year']); ?></div>
                </div>
                <?php if (!empty($edu['description'])): ?>
                  <div class="muted" style="margin-top:8px;"><?php echo h($edu['description']); ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <section>
          <h2>Certifications</h2>
          <?php if (count($certifications) === 0): ?>
            <div class="block muted">No certifications.</div>
          <?php else: ?>
            <div class="grid">
            <?php foreach ($certifications as $cert): ?>
              <div class="block">
                <div class="left"><?php echo h($cert['title']); ?></div>
                <div class="muted"><?php echo h($cert['issuer']); ?> • <?php echo h(dt($cert['date_obtained'])); ?></div>
                <?php if (!empty($cert['description'])): ?>
                  <div class="muted" style="margin-top:8px;"><?php echo h($cert['description']); ?></div>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <section>
          <h2>Skills</h2>
          <?php if (count($skills) === 0): ?>
            <div class="block muted">No skills added.</div>
          <?php else: ?>
            <div class="skill-grid">
            <?php foreach ($skills as $skill): ?>
              <div class="skill">
                <div style="font-weight:600;"><?php echo h($skill['skill_name']); ?></div>
                <div class="muted">Proficiency: <?php echo h($skill['proficiency']); ?></div>
              </div>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>

        <section>
          <h2>Work Experience</h2>
          <?php if (count($experience) === 0): ?>
            <div class="block muted">No work experience.</div>
          <?php else: ?>
            <div class="grid">
            <?php foreach ($experience as $exp): ?>
              <div class="block">
                <div class="left"><?php echo h($exp['company_name']); ?></div>
                <div class="muted"><?php echo h($exp['position']); ?></div>
                <div class="muted"><?php echo h(dt($exp['start_date'])); ?> – <?php echo h($exp['end_date'] ? dt($exp['end_date']) : 'Present'); ?></div>
                <?php if (!empty($exp['responsibilities'])): ?>
                  <ul>
                  <?php
                    // Split responsibilities by newline for nicer bullets
                    $lines = preg_split('/\r\n|\r|\n/', (string)$exp['responsibilities']);
                    foreach ($lines as $line):
                      $line = trim($line);
                      if ($line === '') continue;
                  ?>
                    <li><?php echo h($line); ?></li>
                  <?php endforeach; ?>
                  </ul>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </section>
<!-- Interview Form -->
<div id="interviewForm" style="display:none; margin-top:20px;">
  <h2>Set Interview</h2>
  <form action="save_interview.php" method="POST">
      <input type="hidden" name="hr_id" value="<?php echo htmlspecialchars($hr_id); ?>">
      <input type="hidden" name="student_id" value="<?php echo htmlspecialchars($student_id); ?>">

      <label>Application:</label>
<select name="application_id" required class="form-control" style="width:100%;padding:8px;">
  <option value="">-- Select Internship Application --</option>
  <?php foreach ($applications as $app): ?>
    <option value="<?php echo htmlspecialchars($app['application_id']); ?>">
      <?php echo htmlspecialchars($app['internship_title'] . " – " . $app['companyname']); ?>
    </option>
  <?php endforeach; ?>
</select>


      <label>Interview Date/Time:</label>
      <input type="datetime-local" name="interview_datetime" required 
             class="form-control" style="width:100%;padding:8px;">

      <label>Location:</label>
      <select name="location" id="locationSelect" onchange="toggleOnlineLink()" 
              class="form-control" style="width:100%;padding:8px;" required>
          <option value="On-Site">On-Site</option>
          <option value="Online">Online</option>
      </select>

      <!-- Extra field for Online Meeting Link -->
      <div id="onlineLinkField" style="display:none; margin-top:10px;">
          <label>Online Meeting Link:</label>
          <input type="url" name="online_link" placeholder="Enter meeting link" 
                 class="form-control" style="width:100%;padding:8px;">
      </div>

      <label>Remarks (optional):</label>
      <textarea name="remarks" class="form-control" 
                style="width:100%;padding:8px;" placeholder="Enter remarks..."></textarea>

      <button type="submit" class="btn btn-success" style="margin-top:10px;">Save Interview</button>
  </form>
</div>


<div class="actions">
  <button class="btn btn-primary" onclick="window.print()">Print</button>
  <button class="btn btn-primary" onclick="downloadPDF()">Download PDF</button>
  <button class="btn btn-ghost" onclick="window.close()">Close</button>
  <button class="btn btn-primary" onclick="showInterviewForm()">Set Interview</button>

  <!-- Hire & Reject Buttons -->
  <button onclick="updateApplicationStatus('hire')" class="btn btn-primary">Hire</button>
  <button onclick="updateApplicationStatus('reject')" class="btn btn-ghost" style="color:#dc3545; border-color:#dc3545;">Reject</button>
</div>


      </div>
    </div>
  </div>

  <!-- PDF script -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
  <script>
    function updateApplicationStatus(action) {
      const studentId = <?php echo json_encode($student_id); ?>;
      const hrId = <?php echo json_encode($hr_id); ?>;

      const formData = new FormData();
      formData.append('student_id', studentId);
      formData.append('hr_id', hrId);
      formData.append('action', action);

      fetch('update_application_status.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.text())
      .then(text => {
        // Extract the message from the script tag
        const match = text.match(/alert\('([^']+)'\)/);
        if (match && match[1]) {
          alert(match[1]);
          if (match[1].toLowerCase().includes('success')) {
            // Reload the opener window (companyhr.php) and close this one
            if (window.opener) {
              window.opener.location.reload();
            }
            window.close();
          }
        } else {
          // Fallback for unexpected responses
          alert('An unexpected error occurred.');
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the application status.');
      });
    }
    function downloadPDF() {
      const element = document.getElementById("resume-content");
      html2pdf().from(element).save("resume_<?php echo h($firstname."_".$lastname); ?>.pdf");
    }
    function showInterviewForm() {
      document.getElementById("interviewForm").style.display = "block";
      window.scrollTo({ top: document.getElementById("interviewForm").offsetTop, behavior: 'smooth' });
    }
    function hideInterviewForm() {
      document.getElementById("interviewForm").style.display = "none";
    }
    function toggleOnlineLink() {
      const locationSelect = document.getElementById("locationSelect");
      const onlineLinkField = document.getElementById("onlineLinkField");
      if (locationSelect.value === "Online") {
        onlineLinkField.style.display = "block";
      } else {
        onlineLinkField.style.display = "none";
      }
    }
  </script>
</body>
</html>

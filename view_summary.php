<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    die("Not logged in.");
}

include 'db.php';

// If student_id is provided (for HR view), otherwise use session user
$student_id = isset($_GET['student_id']) && is_numeric($_GET['student_id']) 
    ? intval($_GET['student_id']) 
    : intval($_SESSION['user_id']);

// Student info
$stmt = $conn->prepare("SELECT firstname, lastname, email, contact, section, COALESCE(profile_picture, 'uploads/dp.jpg') AS profile_picture FROM student WHERE student_id=?");
$stmt->bind_param("i",$student_id);
$stmt->execute();
$stmt->bind_result($firstname, $lastname, $email, $contact, $section, $profile_picture);
$stmt->fetch();
$stmt->close();

// Resume main
$stmt = $conn->prepare("SELECT resume_id, objective, created_at, updated_at FROM resumes WHERE student_id=? ORDER BY updated_at DESC, created_at DESC LIMIT 1");
$stmt->bind_param("i",$student_id);
$stmt->execute();
$stmt->bind_result($resume_id, $objective, $created_at, $updated_at);
$stmt->fetch();
$stmt->close();

if(!$resume_id){
    echo "<h2>No resume found for $firstname $lastname.</h2>";
    exit();
}

// Fetch related tables
function fetchRows($conn,$table,$resume_id){
    $rows=[];
    $stmt=$conn->prepare("SELECT * FROM $table WHERE resume_id=?");
    $stmt->bind_param("i",$resume_id);
    $stmt->execute();
    $result=$stmt->get_result();
    while($row=$result->fetch_assoc()) $rows[]=$row;
    $stmt->close();
    return $rows;
}
$education=fetchRows($conn,"education",$resume_id);
$skills=fetchRows($conn,"skills",$resume_id);
$experience=fetchRows($conn,"work_experience",$resume_id);
$certifications=fetchRows($conn,"certifications",$resume_id);

// Helpers
function h($v){return htmlspecialchars((string)$v);}
function dt($v){if(!$v) return ''; $t=strtotime($v); return $t?date('M d, Y',$t):$v;}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Resume – <?php echo h("$firstname $lastname"); ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
<style>
:root{--green:#116530;--green-dark:#0e5128;--muted:#eef7ee;--text:#1e293b;}
*{box-sizing:border-box} body{margin:0;font-family:Inter,system-ui,Arial,sans-serif;background:#fff;color:var(--text)}
.wrap{max-width:1000px;margin:32px auto;padding:24px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;box-shadow:0 10px 30px rgba(0,0,0,.08);overflow:hidden}
.header{display:flex;gap:18px;align-items:center;padding:22px;border-bottom:1px solid #e5e7eb;background:linear-gradient(180deg,#ffffff,#f7fff9);}
.avatar{width:82px;height:82px;border-radius:999px;object-fit:cover;border:4px solid var(--green);}
.title h1{margin:0;font-size:24px;line-height:1.2}
.sub{margin-top:6px;font-size:14px;color:#475569}
.chips{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
.chip{background:var(--muted);color:var(--green);padding:6px 10px;border-radius:999px;font-weight:600;font-size:12px}
.content{padding:24px} section{margin-bottom:24px} section h2{margin:0 0 12px 0;font-size:18px;color:var(--green);display:flex;align-items:center;gap:8px;}
.block{background:#ffffff;border:1px solid #e5e7eb;border-radius:14px;padding:16px}
.row{display:flex;gap:12px;justify-content:space-between;align-items:flex-start}
.left{font-weight:600}
.muted{color:#64748b}
ul{margin:0;padding-left:18px} li{margin:6px 0}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.skill-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:10px}
.skill{background:#f8fafc;border:1px solid #e5e7eb;border-radius:10px;padding:10px}
.actions{display:flex;gap:8px;margin-top:6px;flex-wrap:wrap}
.btn{appearance:none;border:none;padding:10px 14px;border-radius:10px;font-weight:600;cursor:pointer}
.btn-primary{background:var(--green);color:#fff}
.btn-primary:hover{background:var(--green-dark)}
.btn-ghost{background:#fff;border:1px solid #e5e7eb}
@media (max-width:720px){.grid{grid-template-columns:1fr}.header{flex-direction:column;align-items:flex-start}}
@media print{.actions{display:none}.wrap{margin:0;max-width:none;padding:0}.card{border:none;box-shadow:none;border-radius:0}}
</style>
</head>
<body>
<div class="wrap" id="resume-content">
  <div class="card">
    <div class="header">
      <img class="avatar" src="<?php echo h($profile_picture); ?>" alt="Profile">
      <div class="title">
        <h1><?php echo h("$firstname $lastname"); ?></h1>
        <div class="sub"><?php echo h($email); ?> • <?php echo h($contact); ?> • Section: <?php echo h($section); ?></div>
        <div class="chips">
          <span class="chip">Resume ID: <?php echo (int)$resume_id; ?></span>
          <?php if($updated_at):?><span class="chip">Updated: <?php echo h(dt($updated_at)); ?></span><?php endif;?>
          <?php if($created_at):?><span class="chip">Created: <?php echo h(dt($created_at)); ?></span><?php endif;?>
        </div>
      </div>
    </div>

    <div class="content">

      <section>
        <h2>Objective</h2>
        <div class="block"><p style="margin:0;white-space:pre-wrap;"><?php echo nl2br(h($objective)); ?></p></div>
      </section>

      <section>
        <h2>Education</h2>
        <?php if(count($education)===0):?><div class="block muted">No education entries.</div>
        <?php else:?><div class="grid"><?php foreach($education as $edu):?>
          <div class="block"><div class="row"><div class="left"><?php echo h($edu['school_name']);?></div><div class="muted"><?php echo h($edu['start_year'])." - ".h($edu['end_year']);?></div></div>
          <?php if(!empty($edu['description'])):?><div class="muted" style="margin-top:8px;"><?php echo h($edu['description']);?></div><?php endif;?></div>
        <?php endforeach;?></div><?php endif;?>
      </section>

      <section>
        <h2>Certifications</h2>
        <?php if(count($certifications)===0):?><div class="block muted">No certifications.</div>
        <?php else:?><div class="grid"><?php foreach($certifications as $cert):?>
          <div class="block"><div class="left"><?php echo h($cert['title']);?></div>
          <div class="muted"><?php echo h($cert['issuer']);?> • <?php echo h(dt($cert['date_obtained']));?></div>
          <?php if(!empty($cert['description'])):?><div class="muted" style="margin-top:8px;"><?php echo h($cert['description']);?></div><?php endif;?></div>
        <?php endforeach;?></div><?php endif;?>
      </section>

      <section>
        <h2>Skills</h2>
        <?php if(count($skills)===0):?><div class="block muted">No skills added.</div>
        <?php else:?><div class="skill-grid"><?php foreach($skills as $skill):?>
          <div class="skill"><div style="font-weight:600;"><?php echo h($skill['skill_name']);?></div>
          <div class="muted">Proficiency: <?php echo h($skill['proficiency']);?></div></div>
        <?php endforeach;?></div><?php endif;?>
      </section>

      <section>
        <h2>Work Experience</h2>
        <?php if(count($experience)===0):?><div class="block muted">No work experience.</div>
        <?php else:?><div class="grid"><?php foreach($experience as $exp):?>
          <div class="block"><div class="left"><?php echo h($exp['company_name']);?></div>
          <div class="muted"><?php echo h($exp['position']);?></div>
          <div class="muted"><?php echo h(dt($exp['start_date']));?> – <?php echo h($exp['end_date'] ? dt($exp['end_date']):'Present');?></div>
          <?php if(!empty($exp['responsibilities'])):?><ul><?php
          $lines=preg_split('/\r\n|\r|\n/',(string)$exp['responsibilities']);
          foreach($lines as $line): $line=trim($line); if($line==='') continue;
          ?><li><?php echo h($line);?></li><?php endforeach;?></ul><?php endif;?></div>
        <?php endforeach;?></div><?php endif;?>
      </section>

      <div class="actions">
        <button class="btn btn-primary" onclick="window.print()">Print</button>
        <button class="btn btn-primary" onclick="downloadPDF()">Download PDF</button>
        <button class="btn btn-ghost" onclick="window.close()">Close</button>
      </div>

    </div>
  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.3/html2pdf.bundle.min.js"></script>
<script>
function downloadPDF(){
  html2pdf().from(document.getElementById('resume-content')).save("resume_<?php echo h($firstname.'_'.$lastname);?>.pdf");
}
</script>
</body>
</html>

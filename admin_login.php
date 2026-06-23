<?php
session_start();
$error = '';

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: admin_login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    include 'db.php';

    $username = $_POST['username'];
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM admin_login WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        if (password_verify($password, $row['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $row['admin_id'];
            $_SESSION['admin_name'] = $row['first_name'] . ' ' . $row['last_name'];

            // Update last_login
            $update_stmt = $conn->prepare("UPDATE admin_login SET last_login = NOW() WHERE admin_id = ?");
            $update_stmt->bind_param("i", $row['admin_id']);
            $update_stmt->execute();
            $update_stmt->close();

            $_SESSION['login_success'] = true;
            header('Location: admin_dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password.';
        }
    } else {
        $error = 'Invalid username or password.';
    }

    $stmt->close();
    $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Universidad De Manila - Admin Login</title>
  <style>
  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
  }

  body {
    background: linear-gradient(rgba(0, 0, 0, 0.1), rgba(0, 0, 0, 0.1)), url('bg.png') no-repeat center center fixed;
    background-size: cover;
    color: #333;
  }

  /* HEADER */
  header {
    background: rgba(255, 255, 255, 0.9);
    color: #006400;
    padding: 12px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: fixed;
    top: 0;
    width: 100%;
    z-index: 10;
    backdrop-filter: blur(6px);
    box-shadow: 0 3px 12px rgba(0,0,0,0.3);
  }

  header h2 {
    display: flex;
    align-items: flex-start;
    font-size: 14px;
    flex-wrap: wrap;
    gap: 5px;
  }

  header img {
    width: 4rem;
    height: 4rem;
    border-radius: 50%;
  }

  .nav-buttons {
    display: flex;
    gap: 10px;
    flex-wrap: nowrap;
  }

  .nav-buttons button {
    background: white;
    color: #006400;
    border: 1px solid #006400;
    padding: 7px 14px;
    font-size: 14px;
    cursor: pointer;
    border-radius: 20px;
    transition: all 0.3s ease;
    white-space: nowrap; /* Prevent text from breaking */
  }

  .nav-buttons button.active,
  .nav-buttons button:hover {
    background: #006400;
    color: white;
    border-color: #006400;
  }

  /* Highlight Create Account */
  #btn-signup {
    background: #006400;
    color: white;
    font-weight: bold;
    border: 1px solid #006400;
  }

  #btn-signup:hover {
    background: white;
    color: #006400;
  }

  /* MAIN CONTAINER */
  .landing-wrapper {
    display: flex;
    flex-direction: column;
    align-items: center;
    margin-top: 100px;
    padding: 20px;
  }

  .landing-container {
    max-width: 1600px;
    padding: 30px 20px;
    border-radius: 10px;
    text-align: center;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    gap: 30px;
  }

  .left-section {
    flex: 1;
    text-align: left;
    padding: 30px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 100, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }

  .left-section:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px rgba(0, 100, 0, 0.3);
  }

  .middle-section {
    flex: 1;
    text-align: center;
    padding: 30px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 100, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }

  .middle-section:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px rgba(0, 100, 0, 0.3);
  }

  .right-section {
    flex: 1;
    text-align: left;
    padding: 30px;
    background: rgba(255, 255, 255, 0.7);
    backdrop-filter: blur(20px);
    border-radius: 20px;
    box-shadow: 0 8px 32px rgba(0, 100, 0, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
  }

  .right-section:hover {
    transform: translateY(-5px);
    box-shadow: 0 12px 40px rgba(0, 100, 0, 0.3);
  }

  .left-section h1 {
    font-size: 3.5rem;
    font-weight: 700;
    margin-bottom: 20px;
    color: #006400;
    text-shadow: 2px 2px 6px rgba(0, 100, 0, 0.4);
    letter-spacing: 2px;
    text-transform: uppercase;
  }

  .left-section p {
    color: #333;
    font-size: 1.1rem;
    line-height: 1.6;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
  }

  .right-section h3 {
    color: #006400;
    font-size: 1.5rem;
    font-weight: 700;
    margin-bottom: 10px;
    text-shadow: 1px 1px 2px rgba(0, 100, 0, 0.3);
  }

  .right-section p {
    color: #555;
    font-size: 1rem;
    line-height: 1.5;
    margin-bottom: 20px;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
  }

  .landing-container h2 {
    margin-bottom: 10px;
    font-size: 1.6rem;
    color: #000000ff;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
  }

  .landing-container p {
    font-size: 1.1rem;
    color: #000000ff;
    line-height: 1.4;
    text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
  }

  /* SLIDESHOW ROW */
  .slideshow-row {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 40px;
    max-width: 1400px;
    width: 100%;
    margin: 40px auto;
  }

  .slideshow-box {
    flex: 1;
    min-width: 400px;
    max-width: 650px;
    background: none;
    padding: 0;
    border: none;
    position: relative;
  }

  .slide {
    opacity: 0;
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    border-radius: 25px;
    box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5),
                0 10px 30px rgba(0, 0, 0, 0.1);
    transition: opacity 2s ease-in-out;
  }

  .slide.active {
    opacity: 1;
    position: relative;
    display: block;
  }

  .slide img {
    width: 100%;
    border-radius: 25px;
    display: block;
  }

  /* FORM SLIDES */
  .form-slide {
    position: fixed;
    top: 60px;
    right: -100%;
    width: 400px;
    height: calc(100% - 80px);
    background: #fff;
    box-shadow: -4px 0 10px rgba(0, 0, 0, 0.2);
    padding: 25px;
    overflow-y: auto;
    transition: right 0.4s ease;
    z-index: 9;
  }

  .form-slide.active {
    right: 0;
  }

  /* Mobile form slide */
  @media (max-width: 600px) {
    .form-slide {
      top: 50px;
      left: 0;
      right: auto;
      width: 100%;
      height: calc(100% - 50px);
      transform: translateY(100%);
      transition: transform 0.4s ease;
      box-shadow: 0 -4px 10px rgba(0, 0, 0, 0.2);
    }

    .form-slide.active {
      transform: translateY(0);
    }
  }

  .form-slide h2 {
    margin-bottom: 20px;
    color: #006400;
    font-size: 22px;
  }

  .form-slide label {
    display: block;
    margin-top: 10px;
    font-weight: bold;
    font-size: 14px;
  }

  .form-slide input,
  .form-slide select {
    width: 100%;
    padding: 8px;
    margin-top: 5px;
    margin-bottom: 10px;
    border: 1px solid #ccc;
    border-radius: 4px;
  }

  .form-slide button[type="submit"] {
    background: #006400;
    color: white;
    border: none;
    padding: 10px;
    width: 100%;
    margin-top: 10px;
    cursor: pointer;
    border-radius: 4px;
  }

  .form-slide button[type="submit"]:hover {
    background: #004d00;
  }

  .close-btn {
    position: absolute;
    top: 15px;
    right: 20px;
    font-size: 22px;
    color: #333;
    cursor: pointer;
  }

  /* HAMBURGER MENU */
  .hamburger {
    display: none;
    flex-direction: column;
    cursor: pointer;
    background: transparent;
    border: none;
    padding: 5px;
  }

  .hamburger span {
    width: 25px;
    height: 3px;
    background: #006400;
    margin: 3px 0;
    transition: 0.3s;
  }

  .hamburger.active span:nth-child(1) {
    transform: rotate(-45deg) translate(-5px, 6px);
  }

  .hamburger.active span:nth-child(2) {
    opacity: 0;
  }

  .hamburger.active span:nth-child(3) {
    transform: rotate(45deg) translate(-5px, -6px);
  }

  /* RESPONSIVE HEADER */
  @media (max-width: 600px) {
    header {
      flex-direction: row;
      justify-content: space-between;
      align-items: center;
      padding: 10px;
    }

    header h2 {
      font-size: 14px;
    }

    header img {
      width: 4rem;
      height: 4rem;
    }

    .hamburger {
      display: flex;
    }

    .nav-buttons {
      position: fixed;
      top: 50px;
      left: 0;
      width: 100vw;
      height: calc(100vh - 50px);
      background: rgba(255, 255, 255, 0.95);
      flex-direction: column;
      justify-content: center;
      align-items: center;
      padding: 20px;
      transform: translateY(-100%);
      opacity: 0;
      visibility: hidden;
      transition: all 0.3s ease;
      z-index: 12;
    }

    .nav-buttons.mobile-open {
      transform: translateY(0);
      opacity: 1;
      visibility: visible;
    }

    .nav-buttons button {
      font-size: 18px;
      padding: 15px 30px;
      margin: 10px 0;
      width: 80%;
      max-width: 300px;
    }

    /* Mobile slideshow fix */
    .slide {
      position: static;
      opacity: 1;
    }

    .slide.active {
      display: block;
    }

    .slide:not(.active) {
      display: none;
    }
  }

  /* Responsive landing container */
  @media (max-width: 768px) {
    .landing-container {
      flex-direction: column;
      gap: 15px;
    }

    .left-section h1 {
      font-size: 2.5rem;
    }
  }

  </style>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>

  <header>
    <h2>
      <img src="header.png" alt="Intern Icon" style="width: 4rem; height: 4rem; border-radius: 50%; margin-right: 5px;">
      <div>
        <span style="color: #DAA520; font-weight: 700; font-size: 1.75rem; display: block; margin: 0 0 2px 0; text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);">INTERNLINK</span>
        <span style="color: #006400; font-weight: 700; font-size: 0.84rem; display: block; margin: -5px 0 0 0; text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);">UNIVERSIDAD DE MANILA</span>
      </div>
    </h2>
    <button class="hamburger" onclick="toggleMenu()">
      <span></span>
      <span></span>
      <span></span>
    </button>
    <div class="nav-buttons">
      <button onclick="window.location.href='login.php'" id="btn-home" class="active">Home</button>
      <button onclick="toggleForm('admin')" id="btn-admin">Admin Login</button>
    </div>
  </header>

  <div class="landing-wrapper">
    <div class="landing-container" id="defaultContent">
      <div class="left-section">
        <h1>ADMIN LOGIN</h1>
        <div class="underline"></div>
        <p>Access the administrative panel for InternLink. Manage users, monitor activities, and oversee the platform's operations securely.</p>
        <img src="icon.png" alt="Icon" style="display: block; margin: 20px auto; width: 120px; height: 120px; filter: drop-shadow(1px 1px 2px rgba(0, 0, 0, 0.1));">
      </div>
      <div class="middle-section">
        <div style="text-align: center; margin-bottom: 20px;">
          <div style="display: flex; justify-content: center; gap: 10px; margin-bottom: 10px;">
            <img src="header.png" alt="Intern Icon" style="width: 80px; height: 80px; filter: drop-shadow(1px 1px 2px rgba(0, 0, 0, 0.1));">
            <img src="logo.png" alt="UDM Logo" style="width: 80px; height: 80px; filter: drop-shadow(1px 1px 2px rgba(0, 0, 0, 0.1));">
          </div>
          <div style="text-align: center;">
            <h2 style="color: #DAA520; font-weight: 700; font-size: 4rem; margin: 0 0 2px 0; text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);">INTERNLINK</h2>
            <p style="color: #006400; font-weight: 700; font-size: 2rem; margin: -10px 0 0 0; text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);">UNIVERSIDAD DE MANILA</p>
          </div>
        </div>
        <p style="color: #004d00; font-size: 1.2rem; line-height: 1.5; margin-bottom: 20px; text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);">"Secure access for administrators to manage the platform."</p>
        <button onclick="toggleForm('admin')" style="margin-top: 10px; padding: 12px 28px; background-color: #006400; color: white; border: none; border-radius: 30px; cursor: pointer; font-size: 18px; font-weight: 600; transition: background-color 0.3s ease;">Admin Login</button>
      </div>
      <div class="right-section">
        <h3><img src="vision.png" alt="Vision Icon" style="width: 30px; height: 30px; margin-right: 10px; filter: drop-shadow(1px 1px 2px rgba(0, 0, 0, 0.1));">Vision</h3>
        <p>"To be the premier digital platform that connects Universidad De Manila interns with companies, fostering growth, innovation, and real-world opportunities that shape future-ready professionals."</p>
        <h3><img src="mission.png" alt="Mission Icon" style="width: 30px; height: 30px; margin-right: 10px; filter: drop-shadow(1px 1px 2px rgba(0, 0, 0, 0.1));">Mission</h3>
        <p>"Our mission is to bridge interns and industry partners through an efficient, secure, and user-friendly system that streamlines applications, monitors attendance with precision, and tracks performance—empowering students to succeed and enabling companies to cultivate the next generation of talent."</p>
      </div>
    </div>

<!-- Two Slideshows Row -->
<div class="slideshow-row">
  <!-- Left Slideshow -->
  <div class="slideshow-box">
    <div class="slideshow-container" id="slideshow-left">
      <div class="slide"><img src="1.png" alt="Slide 1"></div>
      <div class="slide"><img src="2.png" alt="Slide 2"></div>
      <div class="slide"><img src="3.png" alt="Slide 3"></div>
    </div>
  </div>

  <!-- Right Slideshow -->
  <div class="slideshow-box">
    <div class="slideshow-container" id="slideshow-right">
      <div class="slide"><img src="4.png" alt="Slide 4"></div>
      <div class="slide"><img src="5.png" alt="Slide 5"></div>
      <div class="slide"><img src="6.png" alt="Slide 6"></div>
    </div>
  </div>
</div>


  <!-- Admin Login Form -->
  <div class="form-slide" id="adminForm">
    <span class="close-btn" onclick="closeForm('admin')">&times;</span>
    <h2>Admin Login</h2>
    <form id="admin-login-form" method="POST">
      <label for="username">Username:</label>
      <input type="text" id="username" name="username" placeholder="Enter username" required>
      <label for="password">Password:</label>
      <input type="password" id="password" name="password" placeholder="Enter password" required>
      <button type="submit" name="login">Login</button>
    </form>
  </div>

  <script>
    function setActive(btnId) {
      document.querySelectorAll(".nav-buttons button").forEach(btn => btn.classList.remove("active"));
      document.getElementById(btnId).classList.add("active");
    }

    function toggleForm(type) {
      closeAllForms();
      setActive("btn-" + type);
      document.getElementById(type + 'Form').classList.add('active');
      closeMenu();
    }

    function closeForm(type) {
      document.getElementById(type + 'Form').classList.remove('active');
      setActive("btn-home");
    }

    function closeAllForms() {
      document.getElementById('adminForm').classList.remove('active');
    }

    function goHome() {
      closeAllForms();
      setActive("btn-home");
      document.getElementById('defaultContent').scrollIntoView({ behavior: "smooth" });
      closeMenu();
    }

    function toggleMenu() {
      const navButtons = document.querySelector('.nav-buttons');
      const hamburger = document.querySelector('.hamburger');
      navButtons.classList.toggle('mobile-open');
      hamburger.classList.toggle('active');
    }

    function closeMenu() {
      const navButtons = document.querySelector('.nav-buttons');
      const hamburger = document.querySelector('.hamburger');
      navButtons.classList.remove('mobile-open');
      hamburger.classList.remove('active');
    }

    function adjustMenuPosition() {
      const header = document.querySelector('header');
      const navButtons = document.querySelector('.nav-buttons');
      if (window.innerWidth <= 600) {
        const headerHeight = header.offsetHeight;
        navButtons.style.top = headerHeight + 'px';
        navButtons.style.height = 'calc(100vh - ' + headerHeight + 'px)';
      } else {
        navButtons.style.top = '';
        navButtons.style.height = '';
      }
    }

    adjustMenuPosition();
    window.addEventListener('resize', adjustMenuPosition);

    function adjustFormPosition() {
      const header = document.querySelector('header');
      const forms = document.querySelectorAll('.form-slide');
      const headerHeight = header.offsetHeight;
      forms.forEach(form => {
        form.style.top = headerHeight + 'px';
        form.style.height = 'calc(100vh - ' + headerHeight + 'px)';
      });
    }

    adjustFormPosition();
    window.addEventListener('resize', adjustFormPosition);

  // Slideshow function with fade transition
  function startSlideshow(containerId) {
    let slideIndex = 0;
    const slides = document.querySelectorAll(`#${containerId} .slide`);
    if (window.innerWidth > 600) {
      slides.forEach(s => s.style.position = 'absolute');
    }
    function showSlides() {
      slides.forEach(s => s.classList.remove('active'));
      slideIndex++;
      if (slideIndex > slides.length) slideIndex = 1;
      slides[slideIndex-1].classList.add('active');
      setTimeout(showSlides, 5000); // slower 5 seconds per slide
    }
    showSlides();
  }

  startSlideshow("slideshow-left");
  startSlideshow("slideshow-right");

  <?php if (!empty($error)): ?>
  Swal.fire({ icon: 'error', title: '<?php echo $error; ?>' });
  <?php endif; ?>
  </script>
</body>
</html>

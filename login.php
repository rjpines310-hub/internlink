<?php ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Universidad De Manila - Login</title>
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
    max-width: none; /* removed max-width to allow full expansion */
    padding: 30px 20px;
    border-radius: 10px;
    text-align: center;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    gap: 20px; /* reduced gap to allow more room */
  }
  
  .left-section {
    flex: 1; /* changed from 1.5 to 1 */
  }
  
  .middle-section {
    flex: 1; /* keep middle section default */
  }
  
  .right-section {
    flex: 1; /* changed from 1.5 to 1 */
  }

  .left-section {
    flex: 1;
    text-align: left;
    padding: 10px;
    background: transparent; /* changed from rgba(255, 255, 255, 0.7) to transparent */
    backdrop-filter: none; /* removed blur effect */
    border-radius: 20px;
    box-shadow: none; /* removed shadow */
    border: none; /* removed border */
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    box-sizing: border-box;
  }

  .left-section:hover {
    transform: translateY(-5px);
    box-shadow: none; /* removed shadow on hover */
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
    padding: 10px;
    background: transparent; /* changed from rgba(255, 255, 255, 0.7) to transparent */
    backdrop-filter: none; /* removed blur effect */
    border-radius: 20px;
    box-shadow: none; /* removed shadow */
    border: none; /* removed border */
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    box-sizing: border-box;
  }

  .right-section:hover {
    transform: translateY(-5px);
    box-shadow: none; /* removed shadow on hover */
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

  /* Adjust slideshow container sizes inside left and right sections */
  .left-section .slideshow-container,
  .right-section .slideshow-container {
    width: 100%;
    height: 500px; /* fixed height for uniform size */
    position: relative;
    overflow: hidden;
  }

  /* Make slides fill the container and keep image aspect ratio */
  .left-section .slide,
  .right-section .slide {
    position: absolute !important;
    top: 0;
    left: 0;
    width: 100% !important;
    height: 100% !important;
    border-radius: 25px;
    box-sizing: border-box;
  }

  .left-section .slide.active,
  .right-section .slide.active {
    position: relative !important; /* relative for active slide */
  }

  .left-section .slide img,
  .right-section .slide img {
    width: 100% !important;
    height: 100% !important; /* fill the container height */
    max-width: 100% !important;
    max-height: 100% !important;
    object-fit: cover !important; /* crop to fill, maintaining aspect ratio */
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
      <button onclick="goHome()" id="btn-home" class="active">Home</button>
      <button id="btn-signin">Log In</button>
      <button id="btn-signup">Create an Account</button>
    </div>
  </header>

  <div class="landing-wrapper">
    <div class="landing-container" id="defaultContent">
      <div class="left-section">
        <div class="slideshow-container" id="slideshow-left">
          <div class="slide"><img src="1.png" alt="Slide 1"></div>
          <div class="slide"><img src="2.png" alt="Slide 2"></div>
          <div class="slide"><img src="3.png" alt="Slide 3"></div>
        </div>
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
        <p style="color: #004d00; font-size: 1.2rem; line-height: 1.5; margin-bottom: 20px; text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);">"Your future begins here. Linking skills to opportunities for growth and success."</p>
        <button id="btn-signup-middle" style="margin-top: 10px; padding: 12px 28px; background-color: #006400; color: white; border: none; border-radius: 30px; cursor: pointer; font-size: 18px; font-weight: 600; transition: background-color 0.3s ease;">Be part of us, Sign Up Now</button>
      </div>
      <div class="right-section">
        <div class="slideshow-container" id="slideshow-right">
          <div class="slide"><img src="4.png" alt="Slide 4"></div>
          <div class="slide"><img src="5.png" alt="Slide 5"></div>
          <div class="slide"><img src="6.png" alt="Slide 6"></div>
        </div>
      </div>
    </div>



  <!-- Sign In Form -->
  <div class="form-slide" id="signinForm">
    <span class="close-btn" onclick="closeForm('signin')">&times;</span>
    <h2>Log In Your Account</h2>
    <label for="signin-role">Who's logging in?</label>
    <select id="signin-role" required>
      <option value="">Select account type</option>
      <option value="student">Student</option>
      <option value="faculty">Faculty</option>
      <option value="companyhr">Company</option>
      <option value="supervisor">Supervisor</option>
    </select>

    <form id="signin-form" method="POST">
      <label for="signin-email">Email:</label>
      <input type="email" id="signin-email" name="email" required>
      <label for="signin-password">Password:</label>
      <input type="password" id="signin-password" name="password" required>
      <button type="submit">Login</button>
      <div style="margin-top: 10px; text-align: left; display: flex; align-items: center; gap: 8px;">
        <label for="termsCheckbox" style="font-size: 0.9rem; color: #004d00; margin: 0;">I agree to the <a href="#" id="termsLink" style="color: #006400; text-decoration: underline;">Terms and Conditions</a></label>
        <input type="checkbox" id="termsCheckbox" style="width: 16px; height: 16px; margin: 0;" />
      </div>
    </form>
  </div>

  <!-- Terms and Conditions Modal -->
  <div id="termsModal" style="display:none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div style="background-color: white; margin: 10% auto; padding: 20px; border-radius: 8px; max-width: 600px; box-shadow: 0 5px 15px rgba(0,0,0,0.3);">
      <h2 style="color: #006400;">Terms and Conditions</h2>
      <div id="termsContent" style="max-height: 300px; overflow-y: auto; font-size: 0.9rem; color: #004d00; margin-bottom: 20px;">
        <p>By signing in, you agree to the collection, processing, and use of your data in this system in accordance with our privacy policy and data protection regulations.</p>
        <p>By signing in, I acknowledge and agree to the following Terms, Conditions, and Data Processing Agreement for use of this system:<br/><br/>

1. **Consent to Collection and Use of Data.** I consent to the collection, processing, and storage of my personal data and attendance-related information including, but not limited to, name, student ID, date and time stamps, time-in/time-out entries, photos/selfies taken during clock-in and clock-out, device geolocation (latitude/longitude), IP address, and any other information required to operate the attendance system. This data will be used for attendance verification, intern performance monitoring, security, and administrative purposes only.<br/><br/>

2. **Mandatory Agreement.** I understand that I cannot sign in or access the system unless I check the box confirming I have read and agree to these Terms and Conditions. Checking the box constitutes my electronic signature and consent.<br/><br/>

3. **Accuracy of Information.** I confirm that the information I provide and the photos/selfies I submit are accurate and belong to me. I will not submit false, misleading, or fraudulent attendance records. Any deliberate falsification may result in disciplinary action in accordance with institutional policies.<br/><br/>

4. **Photo & Biometric-like Data.** I consent to photos/selfies being captured and stored for identity verification related to my attendance entries. I understand that the system uses these images only for manual review or automated verification as permitted by the institution and not for unrelated biometric profiling or distribution without separate consent.<br/><br/>

5. **Location Data.** I agree that the system may collect geolocation data at time of clock-in and clock-out to verify presence at the approved site or office location. I acknowledge that location accuracy may vary by device and that location collection is necessary for geofencing-based attendance validation.<br/><br/>

6. **Storage, Retention & Security.** Collected data will be stored securely and retained for the period required by institutional policy or applicable law. The institution will implement reasonable technical and organizational measures to protect personal data from unauthorized access, loss, or disclosure.<br/><br/>

7. **Access, Correction & Deletion.** Where permitted by law, I may request access to, correction of, or deletion of my personal data by contacting the system administrator or data protection officer. Requests will be handled according to institutional procedures and applicable legal requirements.<br/><br/>

8. **Use by Authorized Personnel.** Only authorized supervisors, administrators, and system personnel may access my attendance data for auditing, validation, reporting, or compliance purposes. Access logs may be maintained to monitor who viewed or modified records.<br/><br/>

9. **Third-Party Processors.** The system may use trusted third-party service providers for hosting, backups, or analytics. Such processors will only process data under contract and to the extent necessary to provide their services and in accordance with this agreement.<br/><br/>

10. **Retention of Evidence.** I acknowledge that attendance photos, timestamps, and location data may be retained as evidence in case of disputes, investigations, or disciplinary proceedings.<br/><br/>

11. **Changes to Terms.** The institution may update these Terms and Conditions from time to time. Material changes will be communicated appropriately. Continued use of the system after notice of changes constitutes acceptance of the updated terms.<br/><br/>

12. **Limitation of Liability.** To the extent permitted by law, the institution is not liable for incidental errors due to device malfunctions, connectivity issues, or inaccuracies in device-reported location. Supervisors will follow established procedures to resolve legitimate discrepancies.<br/><br/>

13. **Contact & Questions.** For questions, complaints, or to exercise data subject rights, I may contact the system administrator or designated data protection officer at the contact details provided on the system or institutional policy page.<br/><br/>

14. **Acknowledgment.** By checking the box below and signing in, I confirm that I have read, understood, and agree to be bound by these Terms, Conditions, and Data Processing Agreement.<br/><br/>

**Effective Date:** This agreement is effective as of the moment I check the agreement box and sign in.<br/><br/>

If you want this wording shortened, expanded, or adapted for a modal or a printable policy page, tell me which style you prefer and I’ll prepare that version.
</p>
      </div>
      <button id="closeTermsBtn" style="background-color: #006400; color: white; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer;">Close</button>
    </div>
  </div>

  <!-- Sign Up Form -->
  <div class="form-slide" id="signupForm">
    <span class="close-btn" onclick="closeForm('signup')">&times;</span>
    <h2>Sign Up</h2>
    <label for="signup-role">Select User Type:</label>
    <select id="signup-role" name="role" onchange="updateSignupForm()">
      <option value="student">Student</option>
      <option value="faculty">Faculty</option>
      <option value="companyhr">Company HR</option>
      <option value="supervisor">Supervisor</option>
    </select>
    <form id="signupFields" action="signup.php" method="POST"></form>
  </div>

  <script>
    // Global functions
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
      document.getElementById('signinForm').classList.remove('active');
      document.getElementById('signupForm').classList.remove('active');
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

    // Function to load companies for supervisor dropdown
    async function loadCompaniesForSupervisor() {
      try {
        const response = await fetch('fetch_companies.php');
        const data = await response.json();

        if (data.success) {
          const dropdown = document.getElementById('supervisor-company-dropdown');
          if (dropdown) {
            // Clear existing options except the first one
            dropdown.innerHTML = '<option value="">Select a company</option>';

            // Add company options
            data.companies.forEach(company => {
              const option = document.createElement('option');
              option.value = company.id;
              option.textContent = company.name;
              dropdown.appendChild(option);
            });
          }
        } else {
          console.error('Failed to load companies:', data.message);
        }
      } catch (error) {
        console.error('Error loading companies:', error);
      }
    }

    function updateSignupForm() {
      const role = document.getElementById('signup-role').value;
      const form = document.getElementById('signupFields');
      let html = `<input type="hidden" name="role" value="${role}">`;

      switch(role) {
        case 'student':
          html += `
            <label>Student ID:</label>
            <input type="text" name="studentid" required>
            <label>First Name:</label>
            <input type="text" name="firstname" required>
            <label>Last Name:</label>
            <input type="text" name="lastname" required>
            <label>Section:</label>
            <select name="section" required>
              <option value="">Select Section</option>
              <option value="DS-41">DS-41</option>
              <option value="DS-42">DS-42</option>
              <option value="CYB-41">CYB-41</option>
              <option value="CYB-42">CYB-42</option>
              <option value="IT-41">IT-41</option>
              <option value="IT-42">IT-42</option>
              <option value="IT-43">IT-43</option>
            </select>
          `;
          break;

        case 'faculty':
          html += `
            <label>First Name:</label>
            <input type="text" name="firstname" required>
            <label>Last Name:</label>
            <input type="text" name="lastname" required>
          `;
          break;

        case 'companyhr':
          html += `
            <label>Company Name:</label>
            <input type="text" name="companyname" required>
            <label>Location:</label>
            <input type="text" name="location" required>
            <label>Landline:</label>
            <input type="text" name="landline" required>
          `;
          break;

        case 'supervisor':
          html += `
            <label>First Name:</label>
            <input type="text" name="firstname" required>
            <label>Last Name:</label>
            <input type="text" name="lastname" required>
            <label>Company:</label>
            <select name="hr_id" id="supervisor-company-dropdown" required>
              <option value="">Select a company</option>
            </select>
          `;
          // Load companies for supervisor dropdown
          setTimeout(() => loadCompaniesForSupervisor(), 100);
          break;
      }

      html += `
        <label>Email:</label>
        <input type="email" name="email" required>
        <label>Contact Number:</label>
        <input type="text" name="contact" required>
        <label>Password:</label>
        <input type="password" name="password" required>
        <label>Confirm Password:</label>
        <input type="password" name="confirm" required>
        <label>Invitation Code:</label>
        <input type="text" name="invite_code" id="invite-code-input" required>
        <button type="submit">Sign Up</button>
      `;

      form.innerHTML = html;

      // Pre-fill invitation code and email if present in URL
      const urlParams = new URLSearchParams(window.location.search);
      const inviteCode = urlParams.get('invite');
      const inviteEmail = urlParams.get('email'); // Assuming email might also be passed

      if (inviteCode) {
        document.getElementById('invite-code-input').value = inviteCode;
      }

      form.onsubmit = async function (e) {
        e.preventDefault();
        const formData = new FormData(form);
        const response = await fetch('signup.php', { method: 'POST', body: formData });
        const text = await response.text();
        try {
          const result = JSON.parse(text);
          if (result.status === 'success') {
            Swal.fire({ icon: 'success', title: 'Signed up successfully!' });
            toggleForm('signin');
          } else {
            Swal.fire({ icon: 'error', title: 'Signup failed', text: result.messages.join('\n') });
          }
        } catch (e) {
          console.error('Signup error:', text);
          Swal.fire({ icon: 'error', title: 'Unexpected error', text: 'Check console for response.' });
        }
      };
    }

    document.getElementById("signin-form").addEventListener("submit", async function (e) {
      e.preventDefault();
      const email = document.getElementById("signin-email").value;
      const password = document.getElementById("signin-password").value;
      const role = document.getElementById("signin-role").value;

      const termsCheckbox = document.getElementById("termsCheckbox");
      if (!termsCheckbox.checked) {
        Swal.fire({ icon: "error", title: "Agreement Required", text: "You must agree to the Terms and Conditions before logging in." });
        return;
      }

      const formData = new FormData();
      formData.append("email", email);
      formData.append("password", password);
      formData.append("role", role);
      formData.append("terms_agreed", termsCheckbox.checked ? "1" : "0");

      const response = await fetch("signin.php", { method: "POST", body: formData });
      const text = await response.text();
      try {
        const data = JSON.parse(text);
        if (data.status === "success") {
          Swal.fire({
            icon: "success",
            title: "Login successful",
            timer: 1000,
            showConfirmButton: false
          }).then(() => {
            window.location.href = data.redirect;
          });
        } else {
          Swal.fire({ icon: "error", title: "Login failed", text: data.message });
        }
      } catch (err) {
        console.error('Login error:', text);
        Swal.fire({ icon: "error", title: "Unexpected error", text: "Invalid response from server." });
      }
    });

    document.getElementById("termsLink").addEventListener("click", function(event) {
      event.preventDefault();
      document.getElementById("termsModal").style.display = "block";
    });

    document.getElementById("closeTermsBtn").addEventListener("click", function() {
      document.getElementById("termsModal").style.display = "none";
    });

    window.onclick = function(event) {
      const modal = document.getElementById("termsModal");
      if (event.target === modal) {
        modal.style.display = "none";
      }
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

    // Event listeners for navigation buttons
    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('btn-signin').addEventListener('click', () => toggleForm('signin'));
      document.getElementById('btn-signup').addEventListener('click', () => toggleForm('signup'));
      document.getElementById('btn-signup-middle').addEventListener('click', () => toggleForm('signup')); // Add event listener for the middle signup button
      updateSignupForm(); // Call updateSignupForm after all functions are defined and buttons are ready
    });
  </script>
</body>
</html>

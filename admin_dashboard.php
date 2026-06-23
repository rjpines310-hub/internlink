<?php
session_start();
include 'db.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$admin_name = $_SESSION['admin_name'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Admin Account Management | Universidad De Manila</title>
  <link rel="icon" href="assets/logo.png" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="student.css" />
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
function showImageModal(src) {
  document.getElementById('modalImage').src = src;
  document.getElementById('imageModal').style.display = 'flex';
}

function closeImageModal() {
  document.getElementById('imageModal').style.display = 'none';
}

function showTab(tabId) {
  const allContents = [
    "mainContent",
    "studentsContent",
    "facultyContent",
    "hrContent",
    "supervisorsContent"
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
}

function toggleSidebar() {
  const sidebar = document.querySelector('.sidebar');
  sidebar.classList.toggle('mobile-open');
}

function goHome() {
  showTab('mainContent');
}

function editUser(userType, userId) {
  // Fetch user data and open edit modal
  fetch(`fetch_user.php?type=${userType}&id=${userId}`)
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        populateEditForm(userType, data.user);
        document.getElementById('editModal').style.display = 'flex';
      } else {
        Swal.fire('Error', 'Failed to load user data', 'error');
      }
    })
    .catch(() => Swal.fire('Error', 'Failed to load user data', 'error'));
}

function populateEditForm(userType, user) {
  document.getElementById('editUserType').value = userType;
  document.getElementById('editUserId').value = user[userType + '_id'] || user.id;
  if (userType === 'hr') {
    document.getElementById('labelFirst').textContent = 'Company Name:';
    document.getElementById('editFirstname').name = 'companyname';
    document.getElementById('editFirstname').value = user.companyname || '';
    document.getElementById('labelLast').textContent = 'Location:';
    document.getElementById('editLastname').name = 'location';
    document.getElementById('editLastname').value = user.location || '';
    document.getElementById('editEmail').value = user.email || '';
    document.getElementById('editContact').value = user.contact || '';
    document.getElementById('labelLandline').style.display = 'block';
    document.getElementById('editLandline').style.display = 'block';
    document.getElementById('editLandline').value = user.landline || '';
  } else {
    document.getElementById('labelFirst').textContent = 'First Name:';
    document.getElementById('editFirstname').name = 'firstname';
    document.getElementById('editFirstname').value = user.firstname || '';
    document.getElementById('labelLast').textContent = 'Last Name:';
    document.getElementById('editLastname').name = 'lastname';
    document.getElementById('editLastname').value = user.lastname || '';
    document.getElementById('editEmail').value = user.email || '';
    document.getElementById('editContact').value = user.contact || '';
    document.getElementById('labelLandline').style.display = 'none';
    document.getElementById('editLandline').style.display = 'none';
  }
  document.getElementById('editPassword').value = '';
  document.getElementById('editConfirmPassword').value = '';
  const img = document.getElementById('editProfileImg');
  img.src = user.profile_picture || 'uploads/dp.jpg';
}

function saveUserEdit() {
  const form = document.getElementById('editForm');
  const formData = new FormData(form);
  const password = formData.get('password');
  const confirmPassword = formData.get('confirm_password');

  if (password && password !== confirmPassword) {
    Swal.fire('Error', 'Passwords do not match', 'error');
    return;
  }

  fetch('update_user.php', {
    method: 'POST',
    body: formData
  })
  .then(res => res.json())
  .then(data => {
    if (data.success) {
      Swal.fire('Success', 'User updated successfully', 'success');
      document.getElementById('editModal').style.display = 'none';
      location.reload(); // Refresh to show updated data
    } else {
      Swal.fire('Error', data.message || 'Failed to update user', 'error');
    }
  })
  .catch(() => Swal.fire('Error', 'Failed to update user', 'error'));
}

function cancelEdit() {
  document.getElementById('editModal').style.display = 'none';
}

function filterUsers(tabId) {
  const searchInput = document.getElementById(`${tabId}SearchInput`).value.toLowerCase();
  const userCards = document.querySelectorAll(`#${tabId}Grid .user-card`);

  userCards.forEach(card => {
    const name = card.dataset.name.toLowerCase();
    const email = card.dataset.email.toLowerCase();
    const contact = card.dataset.contact.toLowerCase();

    if (name.includes(searchInput) || email.includes(searchInput) || contact.includes(searchInput)) {
      card.style.display = '';
    } else {
      card.style.display = 'none';
    }
  });
}
  </script>
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
    </h2>
  </div>
  <div class="nav-buttons">
    <a href="#" class="home-link" onclick="goHome()">Home</a>
    <a href="admin_logout.php" class="logout-btn">Log Out</a>
  </div>
</header>

  <div class="dashboard-wrapper">
    <div class="sidebar">
      <a href="#" onclick="showTab('studentsContent')">Students</a>
      <a href="#" onclick="showTab('facultyContent')">Faculty</a>
      <a href="#" onclick="showTab('hrContent')">HR</a>
      <a href="#" onclick="showTab('supervisorsContent')">Supervisors</a>
    </div>

    <div class="main-content active" id="mainContent">
      <h2>Welcome, <?php echo htmlspecialchars($admin_name); ?>!</h2>
      <p>
        Welcome to the Admin Account Management Panel.<br>
        Manage user accounts for Students, Faculty, HR, and Supervisors.
      </p>
    </div>

    <!-- Students Tab -->
    <div class="tab-content" id="studentsContent">
      <h2>Manage Students</h2>
      <div class="search-controls">
        <input type="text" id="studentsSearchInput" onkeyup="filterUsers('students')" placeholder="Search by name, email, or contact...">
      </div>
      <div class="users-grid" id="studentsGrid">
        <?php
        $students_query = "SELECT student_id, studentid, firstname, lastname, email, contact, profile_picture FROM student ORDER BY lastname, firstname";
        $students_res = $conn->query($students_query);
        if ($students_res && $students_res->num_rows > 0) {
          while ($student = $students_res->fetch_assoc()) {
            $student_picture = $student['profile_picture'] && file_exists($student['profile_picture']) ? $student['profile_picture'] : 'uploads/dp.jpg';
            $full_name = htmlspecialchars($student['firstname'] . ' ' . $student['lastname']);
            echo '<div class="user-card" data-name="' . strtolower($full_name) . '" data-email="' . strtolower($student['email']) . '" data-contact="' . $student['contact'] . '">';
            echo '<img src="' . htmlspecialchars($student_picture) . '" alt="Profile Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">';
            echo '<h4>' . $full_name . '</h4>';
            echo '<p>Email: ' . htmlspecialchars($student['email']) . '</p>';
            echo '<p>Contact: ' . htmlspecialchars($student['contact']) . '</p>';
            echo '<button onclick="editUser(\'student\', ' . $student['student_id'] . ')">Edit</button>';
            echo '</div>';
          }
        } else {
          echo '<p>No students found.</p>';
        }
        ?>
      </div>
    </div>

    <!-- Faculty Tab -->
    <div class="tab-content" id="facultyContent">
      <h2>Manage Faculty</h2>
      <div class="search-controls">
        <input type="text" id="facultySearchInput" onkeyup="filterUsers('faculty')" placeholder="Search by name, email, or contact...">
      </div>
      <div class="users-grid" id="facultyGrid">
        <?php
        $faculty_query = "SELECT faculty_id, firstname, lastname, email, contact, profile_picture FROM faculty ORDER BY lastname, firstname";
        $faculty_res = $conn->query($faculty_query);
        if ($faculty_res && $faculty_res->num_rows > 0) {
          while ($fac = $faculty_res->fetch_assoc()) {
            $fac_picture = $fac['profile_picture'] && file_exists($fac['profile_picture']) ? $fac['profile_picture'] : 'uploads/dp.jpg';
            $full_name = htmlspecialchars($fac['firstname'] . ' ' . $fac['lastname']);
            echo '<div class="user-card" data-name="' . strtolower($full_name) . '" data-email="' . strtolower($fac['email']) . '" data-contact="' . $fac['contact'] . '">';
            echo '<img src="' . htmlspecialchars($fac_picture) . '" alt="Profile Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">';
            echo '<h4>' . $full_name . '</h4>';
            echo '<p>Email: ' . htmlspecialchars($fac['email']) . '</p>';
            echo '<p>Contact: ' . htmlspecialchars($fac['contact']) . '</p>';
            echo '<button onclick="editUser(\'faculty\', ' . $fac['faculty_id'] . ')">Edit</button>';
            echo '</div>';
          }
        } else {
          echo '<p>No faculty found.</p>';
        }
        ?>
      </div>
    </div>

    <!-- HR Tab -->
    <div class="tab-content" id="hrContent">
      <h2>Manage HR</h2>
      <div class="search-controls">
        <input type="text" id="hrSearchInput" onkeyup="filterUsers('hr')" placeholder="Search by company, email, or contact...">
      </div>
      <div class="users-grid" id="hrGrid">
        <?php
        $hr_query = "SELECT hr_id, companyname, location, email, contact, landline, profile_picture FROM companyhr ORDER BY companyname";
        $hr_res = $conn->query($hr_query);
        if ($hr_res && $hr_res->num_rows > 0) {
          while ($hr = $hr_res->fetch_assoc()) {
            $hr_picture = $hr['profile_picture'] && file_exists($hr['profile_picture']) ? $hr['profile_picture'] : 'uploads/dp.jpg';
            $company_name = htmlspecialchars($hr['companyname']);
            echo '<div class="user-card" data-name="' . strtolower($company_name) . '" data-email="' . strtolower($hr['email']) . '" data-contact="' . $hr['contact'] . '">';
            echo '<img src="' . htmlspecialchars($hr_picture) . '" alt="Profile Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">';
            echo '<h4>' . $company_name . '</h4>';
            echo '<p>Location: ' . htmlspecialchars($hr['location']) . '</p>';
            echo '<p>Email: ' . htmlspecialchars($hr['email']) . '</p>';
            echo '<p>Contact: ' . htmlspecialchars($hr['contact']) . '</p>';
            echo '<p>Landline: ' . htmlspecialchars($hr['landline']) . '</p>';
            echo '<button onclick="editUser(\'hr\', ' . $hr['hr_id'] . ')">Edit</button>';
            echo '</div>';
          }
        } else {
          echo '<p>No HR found.</p>';
        }
        ?>
      </div>
    </div>

    <!-- Supervisors Tab -->
    <div class="tab-content" id="supervisorsContent">
      <h2>Manage Supervisors</h2>
      <div class="search-controls">
        <input type="text" id="supervisorsSearchInput" onkeyup="filterUsers('supervisors')" placeholder="Search by name, email, or contact...">
      </div>
      <div class="users-grid" id="supervisorsGrid">
        <?php
        $sup_query = "SELECT supervisor_id, firstname, lastname, email, contact, profile_picture FROM supervisor ORDER BY lastname, firstname";
        $sup_res = $conn->query($sup_query);
        if ($sup_res && $sup_res->num_rows > 0) {
          while ($sup = $sup_res->fetch_assoc()) {
            $sup_picture = $sup['profile_picture'] && file_exists($sup['profile_picture']) ? $sup['profile_picture'] : 'uploads/dp.jpg';
            $full_name = htmlspecialchars($sup['firstname'] . ' ' . $sup['lastname']);
            echo '<div class="user-card" data-name="' . strtolower($full_name) . '" data-email="' . strtolower($sup['email']) . '" data-contact="' . $sup['contact'] . '">';
            echo '<img src="' . htmlspecialchars($sup_picture) . '" alt="Profile Picture" style="width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 10px;">';
            echo '<h4>' . $full_name . '</h4>';
            echo '<p>Email: ' . htmlspecialchars($sup['email']) . '</p>';
            echo '<p>Contact: ' . htmlspecialchars($sup['contact']) . '</p>';
            echo '<button onclick="editUser(\'supervisor\', ' . $sup['supervisor_id'] . ')">Edit</button>';
            echo '</div>';
          }
        } else {
          echo '<p>No supervisors found.</p>';
        }
        ?>
      </div>
    </div>
  </div>

  <!-- Edit User Modal -->
  <div id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center;">
    <div style="background: white; padding: 20px; border-radius: 10px; width: 500px;">
      <h3>Edit User</h3>
      <form id="editForm">
        <input type="hidden" id="editUserType" name="user_type">
        <input type="hidden" id="editUserId" name="user_id">
        <div style="text-align: center; margin-bottom: 20px;">
          <img id="editProfileImg" src="uploads/dp.jpg" alt="Profile Picture" style="width: 100px; height: 100px; border-radius: 50%; object-fit: cover;">
          <br><input type="file" name="profile_picture" accept="image/*">
        </div>
        <label id="labelFirst">First Name:</label>
        <input type="text" id="editFirstname" name="firstname" required>
        <label id="labelLast">Last Name:</label>
        <input type="text" id="editLastname" name="lastname" required>
        <label>Email:</label>
        <input type="email" id="editEmail" name="email" required>
        <label>Contact:</label>
        <input type="text" id="editContact" name="contact">
        <label id="labelLandline" style="display:none;">Landline:</label>
        <input type="text" id="editLandline" name="landline" style="display:none;">
        <label>New Password (optional):</label>
        <input type="password" id="editPassword" name="password">
        <label>Confirm Password:</label>
        <input type="password" id="editConfirmPassword" name="confirm_password">
        <div style="text-align: center; margin-top: 20px;">
          <button type="button" onclick="saveUserEdit()">Save</button>
          <button type="button" onclick="cancelEdit()">Cancel</button>
        </div>
      </form>
    </div>
  </div>

<!-- Image Modal -->
<div id="imageModal" class="modal" onclick="closeImageModal()">
  <div class="modal-content">
    <img id="modalImage" src="" alt="Enlarged Image">
  </div>
</div>

  <style>
    /* Copy relevant styles from faculty.php */
    .tab-content {
      display: none;
      padding: 20px;
      box-sizing: border-box;
      justify-content: flex-start;
      align-items: stretch;
      text-align: left;
    }
    .tab-content.active {
      display: block;
    }
    .tab-content h2 {
      text-align: center;
      color: #116530;
    }
    .search-controls {
      display: flex;
      justify-content: center;
      margin-bottom: 20px;
    }
    .search-controls input {
      padding: 10px;
      border-radius: 5px;
      border: 1px solid #ccc;
      width: 300px;
    }
    .users-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
      gap: 20px;
    }
    .user-card {
      border: 1px solid #ccc;
      border-radius: 8px;
      padding: 15px;
      background: #fff;
      box-shadow: 0 2px 5px rgba(0,0,0,0.1);
      text-align: center;
      transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    }
    .user-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 4px 10px rgba(0,0,0,0.15);
    }
    .user-card h4 {
      margin: 10px 0;
    }
    .user-card p {
      margin: 5px 0;
      color: #666;
      font-size: 0.9rem;
    }
    .user-card button {
      background-color: #116530;
      border: none;
      color: white;
      padding: 8px 12px;
      border-radius: 6px;
      cursor: pointer;
      font-size: 0.9rem;
      transition: background-color 0.3s ease;
      margin-top: 10px;
    }
    .user-card button:hover {
      background-color: #0e5128;
    }
    #editModal form {
      display: flex;
      flex-direction: column;
    }
    #editModal label {
      margin-top: 10px;
      font-weight: bold;
    }
    #editModal input {
      padding: 8px;
      margin-top: 5px;
      border: 1px solid #ccc;
      border-radius: 4px;
    }
    #editModal button {
      background-color: #116530;
      border: none;
      color: white;
      padding: 10px;
      border-radius: 6px;
      cursor: pointer;
      margin: 5px;
    }
    #editModal button:hover {
      background-color: #0e5128;
    }
    #imageModal {
      z-index: 1003;
    }
    #imageModal .modal-content {
      background: transparent;
      box-shadow: none;
      border: none;
      width: auto;
      height: auto;
    }
    .logout-btn {
      background-color: #116530;
      color: white;
      padding: 8px 12px;
      border-radius: 6px;
      text-decoration: none;
      font-size: 0.9rem;
      transition: background-color 0.3s ease;
    }
    .logout-btn:hover {
      background-color: #0e5128;
    }
  </style>
</body>
</html>

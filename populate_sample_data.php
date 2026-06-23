<?php
include 'db.php';

// Function to generate a random password hash
function generateRandomPasswordHash($password = 'password123') {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Clear existing data (optional, for fresh start)
$conn->query("SET FOREIGN_KEY_CHECKS = 0");
$conn->query("TRUNCATE TABLE companyhr");
$conn->query("TRUNCATE TABLE internship_posts");
$conn->query("TRUNCATE TABLE student");
$conn->query("TRUNCATE TABLE intern_applications");
$conn->query("TRUNCATE TABLE hr_requests");
$conn->query("SET FOREIGN_KEY_CHECKS = 1");

echo "Truncated tables: companyhr, internship_posts, student, intern_applications, hr_requests.<br>";

// 1. Insert Sample Company HRs and Companies
$companies = [
    [
        'companyname' => 'Tech Solutions Inc.',
        'location' => 'Makati City',
        'email' => 'hr1@techsolutions.com',
        'contact' => '09171234567',
        'landline' => '81234567',
        'password' => generateRandomPasswordHash(),
        'profile_picture' => 'uploads/company_techsolutions.jpg'
    ],
    [
        'companyname' => 'Creative Minds Co.',
        'location' => 'Taguig City',
        'email' => 'hr2@creativeminds.com',
        'contact' => '09177654321',
        'landline' => '87654321',
        'password' => generateRandomPasswordHash(),
        'profile_picture' => 'uploads/company_creativeminds.jpg'
    ],
    [
        'companyname' => 'Global Innovations',
        'location' => 'Pasig City',
        'email' => 'hr3@globalinnovations.com',
        'contact' => '09179876543',
        'landline' => '89876543',
        'password' => generateRandomPasswordHash(),
        'profile_picture' => 'uploads/company_globalinnovations.jpg'
    ]
];

$hr_ids = [];
foreach ($companies as $company) {
    $stmt = $conn->prepare("INSERT INTO companyhr (companyname, location, email, contact, landline, password, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssss", $company['companyname'], $company['location'], $company['email'], $company['contact'], $company['landline'], $company['password'], $company['profile_picture']);
    if ($stmt->execute()) {
        $hr_id = $stmt->insert_id;
        $hr_ids[] = $hr_id;
        echo "Inserted Company HR: " . $company['companyname'] . " (ID: " . $hr_id . ")<br>";

        // Also insert into hr_requests as approved
        $stmt_hr_req = $conn->prepare("INSERT INTO hr_requests (hr_id, companyname, location, email, contact, landline, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $status = 'approved';
        $stmt_hr_req->bind_param("issssss", $hr_id, $company['companyname'], $company['location'], $company['email'], $company['contact'], $company['landline'], $status);
        $stmt_hr_req->execute();
        $stmt_hr_req->close();
        echo "Approved HR Request for: " . $company['companyname'] . "<br>";

    } else {
        echo "Error inserting Company HR " . $company['companyname'] . ": " . $stmt->error . "<br>";
    }
    $stmt->close();
}

// 2. Insert Sample Internship Posts
$internship_posts = [
    [
        'hr_id' => $hr_ids[0], // Tech Solutions Inc.
        'internship_title' => 'Software Development Intern',
        'companyname' => 'Tech Solutions Inc.',
        'location' => 'Makati City',
        'internship_description' => 'Assist in developing and maintaining web applications using modern frameworks.',
        'allowance' => '5000 PHP/month',
        'date_posted' => date('Y-m-d H:i:s', strtotime('-5 days')),
        'email' => 'hr1@techsolutions.com',
        'status' => 'Active'
    ],
    [
        'hr_id' => $hr_ids[0], // Tech Solutions Inc.
        'internship_title' => 'QA Testing Intern',
        'companyname' => 'Tech Solutions Inc.',
        'location' => 'Makati City',
        'internship_description' => 'Perform manual and automated testing for software products.',
        'allowance' => '4500 PHP/month',
        'date_posted' => date('Y-m-d H:i:s', strtotime('-7 days')),
        'email' => 'hr1@techsolutions.com',
        'status' => 'Active'
    ],
    [
        'hr_id' => $hr_ids[1], // Creative Minds Co.
        'internship_title' => 'Graphic Design Intern',
        'companyname' => 'Creative Minds Co.',
        'location' => 'Taguig City',
        'internship_description' => 'Create visual content for marketing campaigns and digital platforms.',
        'allowance' => '4000 PHP/month',
        'date_posted' => date('Y-m-d H:i:s', strtotime('-3 days')),
        'email' => 'hr2@creativeminds.com',
        'status' => 'Active'
    ],
    [
        'hr_id' => $hr_ids[2], // Global Innovations
        'internship_title' => 'Marketing Intern',
        'companyname' => 'Global Innovations',
        'location' => 'Pasig City',
        'internship_description' => 'Support the marketing team in various promotional activities.',
        'allowance' => '3500 PHP/month',
        'date_posted' => date('Y-m-d H:i:s', strtotime('-2 days')),
        'email' => 'hr3@globalinnovations.com',
        'status' => 'Active'
    ]
];

$post_ids = [];
foreach ($internship_posts as $post) {
    $stmt = $conn->prepare("INSERT INTO internship_posts (hr_id, internship_title, companyname, location, internship_description, allowance, date_posted, email, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("issssssss", $post['hr_id'], $post['internship_title'], $post['companyname'], $post['location'], $post['internship_description'], $post['allowance'], $post['date_posted'], $post['email'], $post['status']);
    if ($stmt->execute()) {
        $post_ids[] = $stmt->insert_id;
        echo "Inserted Internship Post: " . $post['internship_title'] . "<br>";
    } else {
        echo "Error inserting Internship Post " . $post['internship_title'] . ": " . $stmt->error . "<br>";
    }
    $stmt->close();
}

// 3. Insert Sample Student
$students = [
    [
        'studentid' => '2021-0001',
        'firstname' => 'Renato',
        'lastname' => 'Pines Jr',
        'section' => 'BSIT-4A',
        'email' => 'renato@example.com',
        'contact' => '09123456789',
        'password' => generateRandomPasswordHash(),
        'profile_picture' => 'uploads/dp.jpg',
        'employment_status' => 'pending',
        'hr_id' => null // Not yet assigned to a company
    ],
    [
        'studentid' => '2021-0002',
        'firstname' => 'Maria',
        'lastname' => 'Clara',
        'section' => 'BSIT-4B',
        'email' => 'maria@example.com',
        'contact' => '09123456780',
        'password' => generateRandomPasswordHash(),
        'profile_picture' => 'uploads/dp.jpg',
        'employment_status' => 'hired',
        'hr_id' => $hr_ids[0] // Hired by Tech Solutions Inc.
    ],
    [
        'studentid' => '2021-0003',
        'firstname' => 'Jose',
        'lastname' => 'Rizal',
        'section' => 'BSIT-4A',
        'email' => 'jose@example.com',
        'contact' => '09123456781',
        'password' => generateRandomPasswordHash(),
        'profile_picture' => 'uploads/dp.jpg',
        'employment_status' => 'hired',
        'hr_id' => $hr_ids[1] // Hired by Creative Minds Co.
    ]
];

$student_ids = [];
foreach ($students as $student_data) {
    $stmt = $conn->prepare("INSERT INTO student (studentid, firstname, lastname, section, email, contact, password, profile_picture, employment_status, hr_id) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("sssssssssi", $student_data['studentid'], $student_data['firstname'], $student_data['lastname'], $student_data['section'], $student_data['email'], $student_data['contact'], $student_data['password'], $student_data['profile_picture'], $student_data['employment_status'], $student_data['hr_id']);
    if ($stmt->execute()) {
        $student_ids[] = $stmt->insert_id;
        echo "Inserted Student: " . $student_data['firstname'] . " " . $student_data['lastname'] . "<br>";
    } else {
        echo "Error inserting Student " . $student_data['firstname'] . ": " . $stmt->error . "<br>";
    }
    $stmt->close();
}

// 4. Insert Sample Intern Applications
$intern_applications = [
    [
        'student_id' => $student_ids[0], // Renato Pines Jr
        'post_id' => $post_ids[0], // Software Development Intern
        'status' => 'Pending',
        'application_date' => date('Y-m-d H:i:s', strtotime('-1 day'))
    ],
    [
        'student_id' => $student_ids[1], // Maria Clara
        'post_id' => $post_ids[0], // Software Development Intern
        'status' => 'Accepted',
        'application_date' => date('Y-m-d H:i:s', strtotime('-2 days'))
    ],
    [
        'student_id' => $student_ids[2], // Jose Rizal
        'post_id' => $post_ids[2], // Graphic Design Intern
        'status' => 'Accepted',
        'application_date' => date('Y-m-d H:i:s', strtotime('-1 day'))
    ]
];

foreach ($intern_applications as $app) {
    $stmt = $conn->prepare("INSERT INTO intern_applications (student_id, post_id, status, application_date) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iiss", $app['student_id'], $app['post_id'], $app['status'], $app['application_date']);
    if ($stmt->execute()) {
        echo "Inserted Intern Application for Student ID " . $app['student_id'] . " to Post ID " . $app['post_id'] . "<br>";
    } else {
        echo "Error inserting Intern Application for Student ID " . $app['student_id'] . ": " . $stmt->error . "<br>";
    }
    $stmt->close();
}

echo "<br>Sample data population complete!";

$conn->close();
?>

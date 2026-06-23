<?php
include 'db.php';
include 'IdRangeManager.php';

echo "<h2>Populating Students for Each Section</h2>";

try {
    $idManager = new IdRangeManager($conn);

    // Get all sections
    $sectionsResult = $conn->query("SELECT section_name FROM sections ORDER BY section_name");
    $sections = [];
    while ($row = $sectionsResult->fetch_assoc()) {
        $sections[] = $row['section_name'];
    }

    echo "<h3>Sections found:</h3>";
    echo "<ul>";
    foreach ($sections as $section) {
        echo "<li>$section</li>";
    }
    echo "</ul>";

    // Sample names for students
    $firstNames = [
        "John", "Jane", "Michael", "Sarah", "David", "Emma", "Christopher", "Olivia", "Daniel", "Sophia",
        "Matthew", "Isabella", "Anthony", "Ava", "Mark", "Mia", "Paul", "Charlotte", "Steven", "Amelia",
        "Andrew", "Harper", "Joshua", "Evelyn", "Kevin", "Abigail", "Brian", "Emily", "Charles", "Elizabeth",
        "Thomas", "Sofia", "Ryan", "Grace", "James", "Chloe", "William", "Victoria", "Alexander", "Lily",
        "Joseph", "Hannah", "Daniel", "Natalie", "Samuel", "Madison", "Nicholas", "Avery", "Jonathan", "Ella"
    ];

    $lastNames = [
        "Smith", "Johnson", "Williams", "Brown", "Jones", "Garcia", "Miller", "Davis", "Rodriguez", "Martinez",
        "Hernandez", "Lopez", "Gonzalez", "Wilson", "Anderson", "Thomas", "Taylor", "Moore", "Jackson", "Martin",
        "Lee", "Perez", "Thompson", "White", "Harris", "Sanchez", "Clark", "Ramirez", "Lewis", "Robinson",
        "Walker", "Young", "Allen", "King", "Wright", "Scott", "Torres", "Nguyen", "Hill", "Flores",
        "Green", "Adams", "Nelson", "Baker", "Hall", "Rivera", "Campbell", "Mitchell", "Carter", "Roberts"
    ];

    // For each section, add students with different numbers
    $sectionCounts = [
        "DS-41" => 20,
        "DS-42" => 20,
        "CYB-41" => 20,
        "CYB-42" => 20,
        "IT-41" => 20,
        "IT-42" => 20,
        "IT-43" => 20
    ];

    $globalStudentIdCounter = 1; // Global counter for unique studentid
    $usedNames = []; // Track used first-last name combinations

    foreach ($sections as $section) {
        $count = $sectionCounts[$section] ?? 50;
        echo "<h3>Adding $count students to section: $section</h3>";

        for ($i = 1; $i <= $count; $i++) {
            // Get next student ID
            $studentId = $idManager->getNextId('student');

            // Generate unique student details
            do {
                $firstName = $firstNames[array_rand($firstNames)];
                $lastName = $lastNames[array_rand($lastNames)];
                $nameKey = $firstName . '|' . $lastName;
            } while (in_array($nameKey, $usedNames));

            $usedNames[] = $nameKey;

            $studentid = '25-00-' . str_pad($globalStudentIdCounter, 3, '0', STR_PAD_LEFT);
            $email = strtolower($firstName . '.' . $lastName . $studentId) . "@test.edu";
            $contact = "09" . str_pad(mt_rand(10000000, 99999999), 9, '0', STR_PAD_LEFT);
            $password = password_hash("test123", PASSWORD_DEFAULT);
            $profilePicture = "uploads/dp.jpg"; // Default profile picture

            // Check if studentid already exists
            $checkStmt = $conn->prepare("SELECT student_id FROM student WHERE studentid = ?");
            $checkStmt->bind_param("s", $studentid);
            $checkStmt->execute();
            $checkStmt->store_result();

            if ($checkStmt->num_rows > 0) {
                echo "⚠ Skipped student: $firstName $lastName ($studentid) - already exists<br>";
                $checkStmt->close();
                $globalStudentIdCounter++; // Increment counter even if skipped
                continue;
            }
            $checkStmt->close();

            // Insert student
            $stmt = $conn->prepare("
                INSERT INTO student (student_id, studentid, firstname, lastname, section, email, contact, password, profile_picture, employment_status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
            ");
            $stmt->bind_param("issssssss", $studentId, $studentid, $firstName, $lastName, $section, $email, $contact, $password, $profilePicture);

            if ($stmt->execute()) {
                echo "✓ Added student: $firstName $lastName ($studentid, ID: $studentId)<br>";
                $globalStudentIdCounter++;
            } else {
                echo "❌ Error adding student $studentid: " . $stmt->error . "<br>";
                $globalStudentIdCounter++;
            }
        }
    }

    echo "<h3>Verification - Student Count by Section:</h3>";

    // Show student count by section
    $result = $conn->query("
        SELECT section, COUNT(*) as count
        FROM student
        GROUP BY section
        ORDER BY section
    ");
    echo "<table border='1'>";
    echo "<tr><th>Section</th><th>Student Count</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>{$row['section']}</td>";
        echo "<td>{$row['count']}</td>";
        echo "</tr>";
    }
    echo "</table><br>";

    echo "<h3>Updated ID Range Configuration:</h3>";

    // Show updated id_range_config
    $result = $conn->query("SELECT * FROM id_range_config WHERE table_name = 'student'");
    $config = $result->fetch_assoc();
    echo "<table border='1'>";
    echo "<tr><th>Table</th><th>Range Start</th><th>Range End</th><th>Current Max</th><th>Available IDs</th></tr>";
    echo "<tr>";
    echo "<td>{$config['table_name']}</td>";
    echo "<td>{$config['range_start']}</td>";
    echo "<td>{$config['range_end']}</td>";
    echo "<td>{$config['current_max']}</td>";
    echo "<td>" . ($config['range_end'] - $config['current_max']) . "</td>";
    echo "</tr>";
    echo "</table><br>";

    echo "✅ <strong>SUCCESS: Added 20 students to each section!</strong><br>";

} catch (Exception $e) {
    echo "❌ <strong>ERROR: " . $e->getMessage() . "</strong><br>";
}

$conn->close();
?>

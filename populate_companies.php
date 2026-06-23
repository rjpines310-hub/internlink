<?php
include 'db.php';
include 'IdRangeManager.php';

echo "<h2>Populating Companies: 8 Registered (Approved) and 2 Pending Requests</h2>";

try {
    $idManager = new IdRangeManager($conn);

    // Sample company data
    $companies = [
        // 8 Approved Companies
        [
            'companyname' => 'InnovateTech Solutions',
            'location' => 'Makati City',
            'email' => 'hr@innovatetech.com',
            'contact' => '09171234567',
            'landline' => '81234567',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'profile_picture' => 'uploads/dp.jpg',
            'status' => 'approved'
        ],
        [
            'companyname' => 'Digital Dynamics Corp',
            'location' => 'Taguig City',
            'email' => 'hr@digitaldynamics.com',
            'contact' => '09177654321',
            'landline' => '87654321',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'profile_picture' => 'uploads/dp.jpg',
            'status' => 'approved'
        ],
        [
            'companyname' => 'Future Enterprises Ltd',
            'location' => 'Pasig City',
            'email' => 'hr@futureenterprises.com',
            'contact' => '09179876543',
            'landline' => '89876543',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'profile_picture' => 'uploads/dp.jpg',
            'status' => 'approved'
        ],
        [
            'companyname' => 'Smart Systems Inc',
            'location' => 'Quezon City',
            'email' => 'hr@smartsystems.com',
            'contact' => '09170987654',
            'landline' => '80987654',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'profile_picture' => 'uploads/dp.jpg',
            'status' => 'approved'
        ],
        [
            'companyname' => 'NextGen Technologies',
            'location' => 'Manila',
            'email' => 'hr@nextgentech.com',
            'contact' => '09176543210',
            'landline' => '86543210',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'profile_picture' => 'uploads/dp.jpg',
            'status' => 'approved'
        ],
        [
            'companyname' => 'Creative Labs Co',
            'location' => 'Parañaque City',
            'email' => 'hr@creativelabs.com',
            'contact' => '09172345678',
            'landline' => '82345678',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'profile_picture' => 'uploads/dp.jpg',
            'status' => 'approved'
        ],
        [
            'companyname' => 'Global Networks Ltd',
            'location' => 'Mandaluyong City',
            'email' => 'hr@globalnetworks.com',
            'contact' => '09173456789',
            'landline' => '83456789',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'profile_picture' => 'uploads/dp.jpg',
            'status' => 'approved'
        ],
        [
            'companyname' => 'Tech Pioneers Inc',
            'location' => 'Las Piñas City',
            'email' => 'hr@techpioneers.com',
            'contact' => '09174567890',
            'landline' => '84567890',
            'password' => password_hash('test123', PASSWORD_DEFAULT),
            'profile_picture' => 'uploads/dp.jpg',
            'status' => 'approved'
        ],
        // 2 Pending Companies
        [
            'companyname' => 'Emerging Innovations',
            'location' => 'Valenzuela City',
            'email' => 'hr@emerginginnovations.com',
            'contact' => '09175678901',
            'landline' => '85678901',
            'status' => 'pending'
        ],
        [
            'companyname' => 'Startup Ventures',
            'location' => 'Caloocan City',
            'email' => 'hr@startupventures.com',
            'contact' => '09176789012',
            'landline' => '86789012',
            'status' => 'pending'
        ]
    ];

    foreach ($companies as $company) {
        if ($company['status'] === 'approved') {
            // For approved companies, insert into companyhr first to get hr_id
            $stmt = $conn->prepare("INSERT INTO companyhr (companyname, location, email, contact, landline, password, profile_picture) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("sssssss", $company['companyname'], $company['location'], $company['email'], $company['contact'], $company['landline'], $company['password'], $company['profile_picture']);
            if ($stmt->execute()) {
                $hr_id = $stmt->insert_id;
                echo "✓ Added approved company: {$company['companyname']} (HR ID: $hr_id)<br>";
            } else {
                echo "❌ Error adding approved company {$company['companyname']}: " . $stmt->error . "<br>";
                continue;
            }
            $stmt->close();
        } else {
            // For pending companies, get hr_id from IdRangeManager
            $hr_id = $idManager->getNextId('companyhr');
        }

        // Insert into hr_requests for both approved and pending
        $stmt_req = $conn->prepare("INSERT INTO hr_requests (hr_id, companyname, location, email, contact, landline, status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt_req->bind_param("issssss", $hr_id, $company['companyname'], $company['location'], $company['email'], $company['contact'], $company['landline'], $company['status']);
        if ($stmt_req->execute()) {
            echo "✓ Added HR request for: {$company['companyname']} (Status: {$company['status']})<br>";
        } else {
            echo "❌ Error adding HR request for {$company['companyname']}: " . $stmt_req->error . "<br>";
        }
        $stmt_req->close();
    }

    echo "<h3>Verification - Company Counts:</h3>";

    // Count approved companies in companyhr
    $approvedCount = $conn->query("SELECT COUNT(*) as count FROM companyhr")->fetch_assoc()['count'];
    echo "<p>Approved Companies (in companyhr): $approvedCount</p>";

    // Count pending requests in hr_requests
    $pendingCount = $conn->query("SELECT COUNT(*) as count FROM hr_requests WHERE status = 'pending'")->fetch_assoc()['count'];
    echo "<p>Pending Requests (in hr_requests): $pendingCount</p>";

    // Total requests
    $totalRequests = $conn->query("SELECT COUNT(*) as count FROM hr_requests")->fetch_assoc()['count'];
    echo "<p>Total HR Requests: $totalRequests</p>";

    echo "<h3>Updated ID Range Configuration:</h3>";
    $result = $conn->query("SELECT * FROM id_range_config WHERE table_name = 'companyhr'");
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

    echo "✅ <strong>SUCCESS: Added 8 approved companies and 2 pending requests!</strong><br>";

} catch (Exception $e) {
    echo "❌ <strong>ERROR: " . $e->getMessage() . "</strong><br>";
}

$conn->close();
?>

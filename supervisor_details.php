<?php
session_start();
include 'db.php';

// Check if user is logged in and is a company HR
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'companyhr') {
    header("Location: login.php");
    exit();
}

// Check if supervisor ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    echo "Supervisor ID not provided.";
    exit();
}

$supervisor_id = $_GET['id'];
$supervisor = null;

// Fetch supervisor details from the database
$stmt = $conn->prepare("SELECT firstname, lastname, email, profile_picture FROM supervisor WHERE supervisor_id = ?");
$stmt->bind_param("i", $supervisor_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $supervisor = $result->fetch_assoc();
}
$stmt->close();
$conn->close();

if (!$supervisor) {
    echo "Supervisor not found.";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Supervisor Details</title>
    <link rel="stylesheet" href="companyhr.css">
    <style>
        body {
            background: #f4f4f4;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .details-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 400px;
        }
        .details-container img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #116530;
        }
        .details-container h2 {
            color: #116530;
            margin: 15px 0;
        }
    </style>
</head>
<body>
    <div class="details-container">
        <img src="<?php echo htmlspecialchars($supervisor['profile_picture'] ?: 'uploads/dp.jpg'); ?>" alt="Profile Picture">
        <h2><?php echo htmlspecialchars($supervisor['firstname'] . ' ' . $supervisor['lastname']); ?></h2>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($supervisor['email']); ?></p>
        
        <!-- Future content can be added here -->
        <p style="margin-top: 20px; color: #888;">Further details and management options will be available here in the future.</p>
    </div>
</body>
</html>

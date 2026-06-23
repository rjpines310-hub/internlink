<?php
include 'db.php';

header('Content-Type: application/json');

try {
    // Fetch all companies from companyhr table
    $stmt = $conn->prepare("SELECT hr_id, companyname FROM companyhr ORDER BY companyname ASC");
    $stmt->execute();
    $result = $stmt->get_result();
    
    $companies = [];
    while ($row = $result->fetch_assoc()) {
        $companies[] = [
            'id' => $row['hr_id'],
            'name' => $row['companyname']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    echo json_encode([
        'success' => true,
        'companies' => $companies
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error fetching companies: ' . $e->getMessage()
    ]);
}
?>

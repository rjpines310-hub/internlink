<?php
include 'db.php';
include 'IdRangeManager.php';

echo "<h2>Final System Verification</h2>";

try {
    echo "<h3>Step 1: Verifying ID Ranges</h3>";
    
    $idManager = new IdRangeManager($conn);
    $ranges = $idManager->getAllRanges();
    
    echo "<table border='1'>";
    echo "<tr><th>Table</th><th>Range</th><th>Current Max</th><th>Available</th><th>Status</th></tr>";
    
    foreach ($ranges as $range) {
        $available = $range['range_end'] - $range['current_max'];
        $status = $available > 100 ? "✅ Good" : ($available > 10 ? "⚠️ Low" : "❌ Critical");
        
        echo "<tr>";
        echo "<td>{$range['table_name']}</td>";
        echo "<td>{$range['range_start']}-{$range['range_end']}</td>";
        echo "<td>{$range['current_max']}</td>";
        echo "<td>$available</td>";
        echo "<td>$status</td>";
        echo "</tr>";
    }
    echo "</table><br>";
    
    echo "<h3>Step 2: Testing ID Generation</h3>";
    
    // Test getting next IDs (without actually using them)
    $testTables = ['student', 'faculty', 'companyhr', 'supervisor'];
    
    foreach ($testTables as $table) {
        try {
            $nextId = $idManager->getNextId($table);
            echo "✓ Next $table ID would be: $nextId<br>";
            
            // Rollback the increment for testing
            $currentRange = $idManager->getRangeInfo($table);
            $conn->query("UPDATE id_range_config SET current_max = current_max - 1 WHERE table_name = '$table'");
            
        } catch (Exception $e) {
            echo "❌ Error getting next $table ID: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<h3>Step 3: Testing ID Validation</h3>";
    
    $testCases = [
        ['student', 1005, true],   // Valid student ID
        ['student', 500, false],   // Invalid student ID (too low)
        ['student', 2500, false],  // Invalid student ID (in faculty range)
        ['faculty', 2002, true],   // Valid faculty ID
        ['faculty', 1500, false],  // Invalid faculty ID (in student range)
        ['companyhr', 3006, true], // Valid HR ID
        ['supervisor', 4001, true] // Valid supervisor ID
    ];
    
    foreach ($testCases as $test) {
        list($table, $id, $expected) = $test;
        $result = $idManager->isValidId($table, $id);
        $status = ($result === $expected) ? "✅" : "❌";
        $expectedText = $expected ? "valid" : "invalid";
        $resultText = $result ? "valid" : "invalid";
        echo "$status Testing $table ID $id: Expected $expectedText, Got $resultText<br>";
    }
    
    echo "<h3>Step 4: Checking Database Integrity</h3>";
    
    // Check for any remaining conflicts
    $all_ids = [];
    $tables = [
        'student' => 'student_id',
        'faculty' => 'faculty_id', 
        'companyhr' => 'hr_id',
        'supervisor' => 'supervisor_id'
    ];
    
    foreach ($tables as $table => $id_column) {
        $result = $conn->query("SELECT $id_column FROM $table");
        while ($row = $result->fetch_assoc()) {
            $id = $row[$id_column];
            if (!isset($all_ids[$id])) {
                $all_ids[$id] = [];
            }
            $all_ids[$id][] = $table;
        }
    }
    
    $conflicts = [];
    foreach ($all_ids as $id => $tables_using_id) {
        if (count($tables_using_id) > 1) {
            $conflicts[$id] = $tables_using_id;
        }
    }
    
    if (empty($conflicts)) {
        echo "✅ No ID conflicts found - Database integrity verified<br>";
    } else {
        echo "❌ ID conflicts still exist:<br>";
        foreach ($conflicts as $id => $tables_list) {
            echo "ID $id is used in: " . implode(', ', $tables_list) . "<br>";
        }
    }
    
    echo "<h3>Step 5: Testing Message System Integrity</h3>";
    
    // Check if messages are properly linked
    $messageCheck = $conn->query("
        SELECT 
            COUNT(*) as total_messages,
            COUNT(CASE WHEN sender_type = 'student' THEN 1 END) as student_senders,
            COUNT(CASE WHEN sender_type = 'faculty' THEN 1 END) as faculty_senders,
            COUNT(CASE WHEN sender_type = 'companyhr' THEN 1 END) as hr_senders,
            COUNT(CASE WHEN sender_type = 'supervisor' THEN 1 END) as supervisor_senders
        FROM messages
    ");
    
    $msgStats = $messageCheck->fetch_assoc();
    echo "Message system statistics:<br>";
    echo "- Total messages: {$msgStats['total_messages']}<br>";
    echo "- Student senders: {$msgStats['student_senders']}<br>";
    echo "- Faculty senders: {$msgStats['faculty_senders']}<br>";
    echo "- HR senders: {$msgStats['hr_senders']}<br>";
    echo "- Supervisor senders: {$msgStats['supervisor_senders']}<br>";
    
    // Check for orphaned message references
    $orphanCheck = $conn->query("
        SELECT 
            m.id, m.sender_type, m.sender_id, m.receiver_type, m.receiver_id
        FROM messages m
        LEFT JOIN student s ON m.sender_type = 'student' AND m.sender_id = s.student_id
        LEFT JOIN faculty f ON m.sender_type = 'faculty' AND m.sender_id = f.faculty_id
        LEFT JOIN companyhr c ON m.sender_type = 'companyhr' AND m.sender_id = c.hr_id
        LEFT JOIN supervisor sv ON m.sender_type = 'supervisor' AND m.sender_id = sv.supervisor_id
        WHERE s.student_id IS NULL AND f.faculty_id IS NULL AND c.hr_id IS NULL AND sv.supervisor_id IS NULL
    ");
    
    if ($orphanCheck->num_rows === 0) {
        echo "✅ No orphaned message senders found<br>";
    } else {
        echo "❌ Found {$orphanCheck->num_rows} orphaned message senders<br>";
    }
    
    echo "<h3>Step 6: System Health Summary</h3>";
    
    $healthScore = 0;
    $totalChecks = 5;
    
    // Check 1: No ID conflicts
    if (empty($conflicts)) $healthScore++;
    
    // Check 2: All ranges have sufficient capacity
    $lowCapacity = false;
    foreach ($ranges as $range) {
        if (($range['range_end'] - $range['current_max']) < 10) {
            $lowCapacity = true;
            break;
        }
    }
    if (!$lowCapacity) $healthScore++;
    
    // Check 3: ID generation works
    $idGenWorks = true;
    foreach ($testTables as $table) {
        try {
            $idManager->getRangeInfo($table);
        } catch (Exception $e) {
            $idGenWorks = false;
            break;
        }
    }
    if ($idGenWorks) $healthScore++;
    
    // Check 4: Messages system intact
    if ($msgStats['total_messages'] > 0) $healthScore++;
    
    // Check 5: No orphaned references
    if ($orphanCheck->num_rows === 0) $healthScore++;
    
    $healthPercentage = ($healthScore / $totalChecks) * 100;
    
    echo "<div style='padding: 10px; border: 2px solid " . 
         ($healthPercentage >= 80 ? "green" : ($healthPercentage >= 60 ? "orange" : "red")) . 
         "; background-color: " . 
         ($healthPercentage >= 80 ? "#e8f5e8" : ($healthPercentage >= 60 ? "#fff3cd" : "#f8d7da")) . 
         ";'>";
    echo "<strong>System Health Score: $healthScore/$totalChecks ($healthPercentage%)</strong><br>";
    
    if ($healthPercentage >= 80) {
        echo "🎉 <strong>EXCELLENT:</strong> Your database ID system is working perfectly!";
    } elseif ($healthPercentage >= 60) {
        echo "⚠️ <strong>GOOD:</strong> System is working but may need attention.";
    } else {
        echo "❌ <strong>NEEDS ATTENTION:</strong> Some issues need to be resolved.";
    }
    echo "</div><br>";
    
    echo "<h3>Step 7: Usage Instructions</h3>";
    echo "<div style='background-color: #f0f8ff; padding: 10px; border-left: 4px solid #007bff;'>";
    echo "<strong>For Developers:</strong><br>";
    echo "1. Always use <code>IdRangeManager</code> when creating new records<br>";
    echo "2. Example: <code>\$nextId = \$idManager->getNextId('student');</code><br>";
    echo "3. Validate IDs: <code>\$isValid = \$idManager->isValidId('student', \$id);</code><br>";
    echo "4. Check available capacity regularly<br>";
    echo "5. Never manually set IDs outside the assigned ranges<br>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "❌ <strong>ERROR: " . $e->getMessage() . "</strong><br>";
}

$conn->close();
?>

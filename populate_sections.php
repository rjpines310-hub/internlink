<?php
include 'db.php';

$sections_to_add = [
    "DS-41",
    "DS-42",
    "CYB-41",
    "CYB-42",
    "IT-41",
    "IT-42",
    "IT-43"
];

echo "Attempting to populate sections table...\n";

foreach ($sections_to_add as $section_name) {
    // Check if section already exists
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM sections WHERE section_name = ?");
    if ($stmt_check === false) {
        echo "Error preparing check statement for $section_name: " . $conn->error . "\n";
        continue;
    }
    $stmt_check->bind_param("s", $section_name);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count == 0) {
        // Insert new section with default ojt_hours (0)
        $stmt_insert = $conn->prepare("INSERT INTO sections (section_name, ojt_hours) VALUES (?, 0)");
        if ($stmt_insert === false) {
            echo "Error preparing insert statement for $section_name: " . $conn->error . "\n";
            continue;
        }
        $stmt_insert->bind_param("s", $section_name);
        if ($stmt_insert->execute()) {
            echo "Successfully added section: $section_name\n";
        } else {
            echo "Error adding section $section_name: " . $stmt_insert->error . "\n";
        }
        $stmt_insert->close();
    } else {
        echo "Section $section_name already exists. Skipping.\n";
    }
}

$conn->close();
echo "Section population process completed.\n";
?>

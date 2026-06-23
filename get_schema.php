<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "capstone";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$sql = "SELECT table_name FROM information_schema.tables WHERE table_schema = '$dbname'";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // output data of each row
    while($row = $result->fetch_assoc()) {
        $tableName = $row["table_name"];
        echo "<h3>" . $tableName . "</h3>";

        $sqlColumns = "
            SELECT 
                c.COLUMN_NAME, 
                c.COLUMN_TYPE, 
                c.COLUMN_KEY,
                kcu.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME
            FROM 
                INFORMATION_SCHEMA.COLUMNS c
            LEFT JOIN 
                INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu 
            ON 
                c.TABLE_SCHEMA = kcu.TABLE_SCHEMA 
                AND c.TABLE_NAME = kcu.TABLE_NAME 
                AND c.COLUMN_NAME = kcu.COLUMN_NAME
            WHERE 
                c.TABLE_SCHEMA = '$dbname' 
                AND c.TABLE_NAME = '$tableName'
        ";

        $resultColumns = $conn->query($sqlColumns);

        if ($resultColumns->num_rows > 0) {
            echo "<table border='1'>";
            echo "<tr><th>Column Name</th><th>Column Type</th><th>Key</th></tr>";
            while($col = $resultColumns->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $col["COLUMN_NAME"] . "</td>";
                echo "<td>" . $col["COLUMN_TYPE"] . "</td>";
                $keyInfo = '';
                if ($col["COLUMN_KEY"] == 'PRI') {
                    $keyInfo = 'PK';
                } elseif ($col["COLUMN_KEY"] == 'MUL' || $col["COLUMN_KEY"] == 'UNI') {
                    if ($col["REFERENCED_TABLE_NAME"]) {
                        $keyInfo = 'FK to ' . $col["REFERENCED_TABLE_NAME"] . '(' . $col["REFERENCED_COLUMN_NAME"] . ')';
                    } else {
                         $keyInfo = $col["COLUMN_KEY"];
                    }
                }
                echo "<td>" . $keyInfo . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "0 columns";
        }
        echo "<br>";
    }
} else {
    echo "0 tables";
}
$conn->close();
?>

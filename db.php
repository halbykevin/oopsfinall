<?php
// db.php - simple local connection (XAMPP)

$servername = "localhost";
$username = "root";    // default XAMPP username
$password = "";        // default XAMPP password is empty
$dbname = "oopsz";

// create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// optional: uncomment to test
// echo "Connected successfully";
?>

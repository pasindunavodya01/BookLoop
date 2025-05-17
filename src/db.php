<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}



$servername = "localhost";
$username = "root"; 
$password = ""; 
$dbname = "bookloopFinal";

// $servername = "sql311.iceiy.com";
// $username = "icei_38787331"; 
// $password = "123Pasindu"; 
// $dbname = "bookloopFinal";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("❌ Database connection failed: " . $conn->connect_error);
}
?>

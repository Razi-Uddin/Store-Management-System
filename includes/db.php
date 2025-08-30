<?php
$host = 'localhost';
$user = 'root';
$password = ''; // Change if you have a password
$dbname = 'crescent_stores';

$conn = new mysqli($host, $user, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>

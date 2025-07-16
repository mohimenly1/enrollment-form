<?php
$conn = new mysqli('localhost', 'root', '', 'school_enrollment', 3308);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
echo "Connected successfully";
?>
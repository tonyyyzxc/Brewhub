<?php

require_once __DIR__ . '/includes/app_keys.php';

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "brewhub";

$conn = new mysqli($servername, $username, $password, $dbname);

if($conn -> connect_error){
    die("Connection failed: " . $conn -> connect_error);
}

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_USERNAME', 'brewhub8@gmail.com');
define('SMTP_PASSWORD', 'vyru rwcr qoru pucs');
define('SMTP_PORT', 587);
define('FROM_EMAIL', 'brewhub8@gmail.com');
define('FROM_NAME', 'Brewhub');

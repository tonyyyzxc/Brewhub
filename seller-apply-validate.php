<?php
session_start();
include 'config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: BecomeSeller.php');
    exit;
}

if (empty($_SESSION['loggedin']) || empty($_SESSION['user_id'])) {
    header('Location: Login.php');
    exit;
}

$user_id     = (int) $_SESSION['user_id'];
$first_name  = trim($_SESSION['FirstName'] ?? '');
$last_name   = trim($_SESSION['LastName'] ?? '');
$email       = trim($_SESSION['email']    ?? '');
$shop_name   = trim($_POST['shop_name']      ?? '');
$seller_type = trim($_POST['seller_type']    ?? '');
$description = trim($_POST['shop_description'] ?? '');
$contact     = trim($_POST['contact_number'] ?? '');
$address     = trim($_POST['shop_address']   ?? '');

if (!$shop_name || !$seller_type || !$description || !$contact || !$address) {
    header('Location: BecomeSeller.php?error=missing_fields');
    exit;
}

// Prevent duplicate pending requests
$check = $conn->prepare("SELECT request_id FROM seller_requests WHERE user_id = ? AND status = 'pending'");
$check->bind_param("i", $user_id);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    header('Location: BecomeSeller.php?error=already_pending');
    exit;
}
$check->close();

$stmt = $conn->prepare(
    "INSERT INTO seller_requests 
        (user_id, first_name, last_name, email, contact, shop_name, seller_type, description, address)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
);
$stmt->bind_param("issssssss", $user_id, $first_name, $last_name, $email, $contact, $shop_name, $seller_type, $description, $address);

if ($stmt->execute()) {
    header('Location: Buyer/Profile.php?seller_applied=1');
} else {
    header('Location: BecomeSeller.php?error=db_error');
}

$stmt->close();
$conn->close();
<?php

session_start();
include 'config.php';
require_once __DIR__ . '/includes/auth_helpers.php';

header('Content-Type: application/json');

// Admin credentials
$adminEmail     = 'admin@gmail.com';
$adminPassword  = 'adminpass';
$adminFirstname = 'admin';
$adminLastname  = 'admin';
$adminUsername  = 'admin';
$adminRole      = 'admin';

$check = $conn->prepare("SELECT user_id, role FROM users WHERE email = ?");
$check->bind_param("s", $adminEmail);
$check->execute();
$check->store_result();
$check->bind_result($adminId, $existingRole);
$check->fetch();

if ($check->num_rows === 0) {
    $check->close();
    $hashed = password_hash($adminPassword, PASSWORD_DEFAULT);
    $insert = $conn->prepare(
        "INSERT INTO users (email, FirstName, LastName, password, username, role)
         VALUES (?, ?, ?, ?, ?, ?)"
    );
    $insert->bind_param("ssssss", $adminEmail, $adminFirstname, $adminLastname, $hashed, $adminUsername, $adminRole);
    $insert->execute();
    $insert->close();
} else if (is_null($existingRole) || $existingRole === '' || $existingRole !== 'admin') {
    $check->close();
    $fix = $conn->prepare("UPDATE users SET role = ? WHERE email = ?");
    $fix->bind_param("ss", $adminRole, $adminEmail);
    $fix->execute();
    $fix->close();
} else {
    $check->close();
}

// ============ //

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request.']);
    exit();
}

if (empty($_POST['email']) || empty($_POST['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Email and password are required.']);
    exit();
}

if (!bh_verify_recaptcha($_POST['g-recaptcha-response'] ?? null)) {
    echo json_encode(['status' => 'error', 'message' => 'Please complete the reCAPTCHA check.']);
    exit();
}

$sql = "SELECT user_id, email, FirstName, LastName, password, username, role FROM users WHERE email = ?";

if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("s", $param_email);

    $param_email = $_POST['email'];

    if ($stmt->execute()) {
        $stmt->store_result();

        if ($stmt->num_rows == 1) {
            $stmt->bind_result($ID, $email, $firstname, $lastname, $hashed_password, $username, $role);

            if ($stmt->fetch() && password_verify($_POST['password'], (string) $hashed_password)) {
                if (empty($role)) $role = 'buyer';

                // Legacy compatibility: convert 'both' to 'seller'
                if (strtolower((string) $role) === 'both') {
                    $role = 'seller';
                    $fixRole = $conn->prepare("UPDATE users SET role = 'seller' WHERE user_id = ?");
                    $fixRole->bind_param('i', $ID);
                    $fixRole->execute();
                    $fixRole->close();
                }

                bh_set_login_session([
                    'user_id'   => $ID,
                    'email'     => $email,
                    'FirstName' => $firstname,
                    'LastName'  => $lastname,
                    'username'  => $username,
                    'role'      => $role,
                ]);

                echo json_encode(['status' => 'success', 'redirect' => bh_role_redirect($role)]);
                exit();

            } else {
                echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email or password']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Something went wrong!']);
    }

    $stmt->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Something went wrong!']);
}

$conn->close();
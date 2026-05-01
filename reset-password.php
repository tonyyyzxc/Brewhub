<?php
session_start();
require 'config.php';

$email = $_SESSION['reset_email'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = trim($_POST['password'] ?? '');
    $confirmPassword = trim($_POST['confirm_password'] ?? '');

    if ($password === '' || $confirmPassword === '') {
        $error = 'Please fill in both password fields.';
    } elseif ($password !== $confirmPassword) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters long.';
    } else {
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->bind_param('ss', $hashedPassword, $email);

        if ($stmt->execute()) {
            unset($_SESSION['reset_email'], $_SESSION['email']);
            $_SESSION['success'] = 'Your password has been reset. Please log in with your new password.';
            header('Location: Login.php');
            exit();
        }

        $error = 'Unable to update your password. Please try again.';
    }
}

function mask_email($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $email;
    }

    [$local, $domain] = explode('@', $email, 2);
    $maskedLocal = strlen($local) <= 2 ? substr($local, 0, 1) . '***' : substr($local, 0, 2) . str_repeat('*', max(1, strlen($local) - 2));
    return $maskedLocal . '@' . $domain;
}

$maskedEmail = mask_email($email);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Brewhub Reset Password</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="Style.css" rel="stylesheet">
</head>
<body class="auth-page">
    <main class="auth-card container-fluid p-0">
        <div class="row g-0 h-100">
            <aside class="col-lg-5 auth-visual">
                <img src="Assets/Suplies.png" alt="Coffee shop supplies">
            </aside>

            <section class="col-lg-7 auth-pane">
                <div class="form-shell">
                    <header class="text-center">
                        <img src="Assets/Brew_Hub.png" alt="" style="width: 150px; height: 150px;">
                        <h1>Reset Password</h1>
                        <p>Your account email is <strong><?php echo htmlspecialchars($maskedEmail, ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
                        <p>Please enter a new password to finish resetting your account.</p>
                    </header>

                    <?php if ($error): ?>
                        <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>

                    <form action="" method="post" autocomplete="off">
                        <label for="password" class="form-label">New Password</label>
                        <div class="mb-2">
                            <input id="password" name="password" type="password" class="form-control" required>
                        </div>

                        <label for="confirm_password" class="form-label">Confirm Password</label>
                        <div class="mb-3">
                            <input id="confirm_password" name="confirm_password" type="password" class="form-control" required>
                        </div>

                        <button class="btn btn-login w-100" type="submit">Reset Password</button>
                    </form>

                    <p class="signup-note mt-3">Remembered your password? <a href="Login.php">Log in</a>.</p>
                </div>
            </section>
        </div>
    </main>
</body>
</html>

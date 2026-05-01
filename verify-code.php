<?php
session_start();
require 'config.php';

if (!isset($_SESSION['email'])) {
    header('Location: ForgotPassword.php');
    exit();
}


$email = $_SESSION['email'];
[$local, $domain] = explode('@', $email);
$maskedEmail = substr($local, 0, 2) . str_repeat('*', strlen($local) - 2) . '@' . $domain;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enteredCode = trim($_POST['verification_code']);

    if (strlen($enteredCode) !== 6 || !is_numeric($enteredCode)) {
        $_SESSION['error'] = 'Please enter a valid 6-digit code.';
        header('Location: verify-code.php');
        exit();
    }

    $email = $_SESSION['email'];  
    $stmt = $conn->prepare("SELECT reset_code FROM users WHERE email = ?");
    $stmt -> bind_param("s", $email);
    $stmt -> execute();

    $result = $stmt -> get_result();
    $user = $result -> fetch_assoc();


    if ($user && $enteredCode == $user['reset_code']) {
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_code_verified'] = true;
        header('Location: reset-password.php');
        exit();
    } else {
        $_SESSION['error'] = 'Invalid verification code. Please try again.';
        header('Location: verify-code.php');
        exit();
    }
}
?>


<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Brewhub Verify Code</title>
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
                        <h1>Verify Code</h1>
                        <p>A verification code has been sent to <strong><?php echo htmlspecialchars($maskedEmail, ENT_QUOTES, 'UTF-8'); ?></strong>.</p>
                        <p>Please enter the code from your email below.</p>
                    </header>

                     <?php if(isset($_SESSION['error'])): ?>
            			<div style="color: #dc3545; background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 5px; margin-bottom: 15px; text-align: center;">
                		<?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            			</div>
        				<?php endif; ?>

                    <form action="" method="post" autocomplete="off">
                        <label for="verification_code" class="form-label">Verification Code</label>
                        <div class="mb-2">
                            <input id="verification_code" name="verification_code" type="text" class="form-control" required>
                        </div>
                        <button class="btn btn-login w-100" type="submit">Verify Code</button>
                    </form>

                    <p class="signup-note mt-3">Didn't receive the code? <a href="ForgotPassword.php">Request a new one</a>.</p>
                    <p class="signup-note">Back to <a href="Login.php">Log In</a></p>
                </div>
            </section>
        </div>
    </main>
</body>
</html>

<?php
require_once __DIR__ . '/includes/app_keys.php';
$googleConfigured = defined('GOOGLE_CLIENT_ID') && defined('GOOGLE_CLIENT_SECRET')
	&& trim((string) GOOGLE_CLIENT_ID) !== ''
	&& trim((string) GOOGLE_CLIENT_SECRET) !== '';
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Brewhub Sign Up</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<link href="Style.css" rel="stylesheet">
</head>

<body class="auth-page auth-signup">
	<main class="auth-card container-fluid p-0">
		<div class="row g-0 h-100">
			<aside class="col-lg-5 auth-visual">
				<img src="Assets/Suplies.png" alt="Coffee shop supplies">
			</aside>

			<section class="col-lg-7 auth-pane">
				<div class="form-shell">
					<header class="text-center">
							<img src="Assets/Brew_Hub.png" alt="" style="width: 50px; height: 50px;">
						<h1>Create Account</h1>
						<p>Join Brewhub to manage your coffee shop essentials</p>
					</header>

					<form id="signupForm" method="POST" autocomplete="off">

						<div class="mb-2">
							<label for="Firstname" class="form-label">First Name</label>
							<input id="Firstname" name="Firstname" type="text" class="form-control" required>
						</div>

						<div class="mb-2">
							<label for="Lastname" class="form-label">Last Name</label>
							<input id="Lastname" name="Lastname" type="text" class="form-control" required>
						</div>

						<div class="mb-2">
							<label for="username" class="form-label">Username</label>
							<input id="username" name="username" type="text" class="form-control" required>
						</div>

						<div class="mb-3">
							<label for="email" class="form-label">Email</label>
							<input id="email" name="email" type="email" class="form-control" required>
						</div>

						<div class="mb-3">
							<label for="password" class="form-label">Password</label>
							<input id="password" name="password" type="password" class="form-control" required>
						</div>

						<button type="submit" class="btn btn-login w-100">Sign Up</button>
					</form>

					<div class="or-divider" aria-hidden="true"><span>OR</span></div>

					<div class="social-row">
						<a class="social-btn<?php echo $googleConfigured ? '' : ' disabled'; ?>" href="<?php echo $googleConfigured ? 'google-auth.php' : '#'; ?>" <?php echo $googleConfigured ? '' : 'aria-disabled="true"'; ?>>
							<i class="bi bi-google"></i> Sign up with Google
						</a>
					</div>

					<p class="signup-note">Already have an account? <a href="Login.php">Log In</a></p>
				</div>
			</section>
		</div>
	</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
	document.getElementById('signupForm').addEventListener('submit', function(e) {
		e.preventDefault();

		const formData = new FormData(this);

		fetch('signUp-validate.php', {
			method: 'POST',
			body: formData
		})
		.then(response => response.json())
		.then(data => {
			if (data.status === 'success') {
				Swal.fire({
					icon: 'success',
					title: 'Welcome to BrewHub!',
					text: data.message,
					confirmButtonText: 'Go to Login',
					confirmButtonColor: '#3085d6'
				}).then(() => {
					window.location.href = 'Login.php';
				});
			} else if (data.status === 'exists') {
				Swal.fire({
					icon: 'warning',
					title: 'Email Already Exists!',
					text: data.message,
					confirmButtonText: 'Try again',
					confirmButtonColor: '#f0ad4e'
				});
			} else {
				Swal.fire({
					icon: 'error',
					title: 'Oops!',
					text: data.message,
					confirmButtonText: 'OK',
					confirmButtonColor: '#d33'
				});
			}
		})
		.catch(error => {
			Swal.fire({
				icon: 'error',
				title: 'Connection Error',
				text: 'Could not connect to server.',
				confirmButtonColor: '#d33'
			});
		});
	});
</script>
</body>
</html>
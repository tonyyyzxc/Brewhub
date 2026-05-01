<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Brewhub</title>
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
				<img src="Assets/Suplies.png">
				
			</aside>

			<section class="col-lg-7 auth-pane">
				<div class="form-shell">
					<header class="text-center">
						<img src="Assets/Brew_Hub.png" alt="" Style="width: 150px; height: 150px;">
						<h1>Brewhub</h1>
						<p>Your Shop's One-Stop Supply</p>
					</header>

					<form id="loginForm" method="post">

					<div class="mb-3">
							<label for="email" class="form-label">Email</label>
							<input id="email" name="email" type="email" class="form-control" required>
						</div>

						<div class="mb-3">
							<label for="password" class="form-label">Password</label>
							<input id="password" name="password" type="password" class="form-control border-0 border-bottom rounded-0"  required>
						</div>

						<div class="text-end mb-3">
							<a href="ForgotPassword.php" class="forgot-link link-underline link-underline-opacity-0 link-underline-opacity-100-hover">Forgot your password?</a>
						</div>

						<button type="submit" class="btn btn-login w-100">Log In</button>
					</form>

					<div class="or-divider" aria-hidden="true"><span>OR</span></div>

					<div class="social-row">			
						<a class="social-btn" href="#"><i class="bi bi-google"></i> Sign in with Google</a>
					</div>

					<p class="signup-note">Don't have an account? <a href="Signup.php">Sign Up</a></p>
				</div>
			</section>
		</div>
	</main>

	<!-- Error Toast -->
	<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 9999;">
		<div id="errorToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true">
			<div class="d-flex">
				<div class="toast-body">
					<i class="bi bi-exclamation-circle me-2"></i>
					<span id="toastMessage">Invalid email or password</span>
				</div>
				<button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
			</div>
		</div>
	</div>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<script>
		document.getElementById('loginForm').addEventListener('submit', function(e){
			e.preventDefault();

			const formData = new FormData(this);

			fetch('validate.php', {
				method: "POST",
				body: formData
			})
			.then(response => response.json())
			.then(data => {
				if(data.status === 'success'){
					window.location.href = data.redirect;
				} else {
					const toastEl = document.getElementById('errorToast');
					const toastMessage = document.getElementById('toastMessage');
					toastMessage.textContent = data.message;
					const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
					toast.show();
				}
			})
			.catch(error => {
				const toastEl = document.getElementById('errorToast');
				const toastMessage = document.getElementById('toastMessage');
				toastMessage.textContent = 'Wrong credentials. Please try again.';
				const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
				toast.show();
			});
		});
	</script>

</body>
</html>

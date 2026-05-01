<?php
if (session_status() === PHP_SESSION_NONE) {
	session_start();
}

$fullName = '';
$email = '';

if (!empty($_SESSION['loggedin'])) {
	$fullName = (string) ($_SESSION['fullname'] ?? '');
	$email = (string) ($_SESSION['email'] ?? '');
}

if ($fullName === '' && isset($_GET['fullname'])) {
	$fullName = (string) $_GET['fullname'];
}
if ($email === '' && isset($_GET['email'])) {
	$email = (string) $_GET['email'];
}

$fullName = trim($fullName);
$email = trim($email);

if ($fullName === '' || $email === '') {
	header('Location: Login.php');
	exit;
}

if (!isset($_SESSION['bh_cart']) || !is_array($_SESSION['bh_cart'])) {
	$_SESSION['bh_cart'] = [];
}

$cartCount = array_sum(array_map('intval', (array) $_SESSION['bh_cart']));
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Apply to Become a Seller</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<link href="Style.css?v=20260423" rel="stylesheet">
</head>
<body class="dashboard-page">
	<nav class="navbar navbar-expand-md navbar-light fixed-top bh-navbar">
		<div class="container-fluid px-4 px-lg-5 bh-nav-container">
			<a class="navbar-brand bh-brand" href="Buyer/Dashboard.php">Brewhub</a>

			<div class="d-flex align-items-center gap-2 order-md-3 bh-nav-actions">
				<a class="btn bh-icon-btn" href=" Buyer/Profile.php" aria-label="Profile">
					<i class="bi bi-person"></i>
				</a>
				<a class="btn bh-icon-btn position-relative" href="Buyer/Cart.php" aria-label="Cart">
					<i class="bi bi-bag"></i>
					<span class="bh-cart-count"><?php echo (int) $cartCount; ?></span>
				</a>
				<button class="navbar-toggler border-0 shadow-none p-0 ms-1" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
					<span class="navbar-toggler-icon"></span>
				</button>
			</div>

			<div class="collapse navbar-collapse justify-content-center order-md-2" id="navbarNav">
				<ul class="navbar-nav align-items-md-center gap-md-4 gap-lg-5 bh-nav-links">
					<li class="nav-item">
						<a class="nav-link" href="Buyer/Dashboard.php">Home</a>
					</li>
					<li class="nav-item dropdown">
						<a class="nav-link dropdown-toggle" href="#" id="productCategoriesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Product Categories</a>
						<ul class="dropdown-menu" aria-labelledby="productCategoriesDropdown">
							<li><a class="dropdown-item" href="Buyer/CoffeeIngredients.php">Coffee &amp; Ingredients</a></li>
							<li><a class="dropdown-item" href="Buyer/CupsPackaging.php">Cups &amp; Packaging</a></li>
							<li><a class="dropdown-item" href="Buyer/Equipments.php">Equipments</a></li>
							<li><a class="dropdown-item" href="Buyer/Pastry.php">Pastry</a></li>
						</ul>
					</li>
					<li class="nav-item">
						<a class="nav-link" href="Buyer/Orders.php">Orders</a>
					</li>
				</ul>
			</div>
		</div>
	</nav>

	<main class="profile-page-main py-5">
		<div class="container seller-application-container">
			<div class="row justify-content-center">
				<div class="col-12 col-lg-10 col-xl-9">
					<section class="card border-0 seller-application-card">
						<div class="card-body p-4 p-md-5">
							<h1 class="seller-title mb-2">Apply to Become a Seller</h1>
							<p class="seller-intro mb-4">Start selling your coffee products. Please fill out the form below. Your application will be reviewed before approval.</p>

							<form action="Seller/SellerDashboard.php" method="post" autocomplete="on" class="row g-4">
								<div class="col-12">
									<h2 class="seller-section-title"><i class="bi bi-person-badge me-2"></i>Basic Information</h2>
								</div>

								<div class="col-12 col-md-6">
									<label for="fullName" class="form-label seller-form-label">Full Name</label>
									<input id="fullName" name="full_name" type="text" class="form-control seller-form-control" value="<?php echo htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8'); ?>" readonly>
								</div>

								<div class="col-12 col-md-6">
									<label for="email" class="form-label seller-form-label">Email</label>
									<input id="email" name="email" type="email" class="form-control seller-form-control" value="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>" readonly>
								</div>

								<div class="col-12 col-md-6">
									<label for="contactNumber" class="form-label seller-form-label">Contact Number</label>
									<input id="contactNumber" name="contact_number" type="tel" class="form-control seller-form-control" placeholder="e.g. +63 912 345 6789" required>
								</div>

								<div class="col-12">
									<h2 class="seller-section-title"><i class="bi bi-shop me-2"></i>Shop Information</h2>
								</div>

								<div class="col-12 col-md-6">
									<label for="shopName" class="form-label seller-form-label">Shop Name</label>
									<input id="shopName" name="shop_name" type="text" class="form-control seller-form-control" required>
								</div>

								<div class="col-12 col-md-6">
									<label for="sellerType" class="form-label seller-form-label">Type</label>
									<select id="sellerType" name="seller_type" class="form-select seller-form-control" required>
										<option value="" selected disabled>Select seller type</option>
										<option value="coffee-shop">Coffee Shop</option>
										<option value="home-based">Home-based seller</option>
										<option value="supplier">Supplier</option>
									</select>
								</div>

								<div class="col-12">
									<label for="shopDescription" class="form-label seller-form-label">Shop Description</label>
									<textarea id="shopDescription" name="shop_description" rows="4" class="form-control seller-form-control" placeholder="Tell us about your coffee business and what you sell." required></textarea>
								</div>

								<div class="col-12">
									<label for="shopAddress" class="form-label seller-form-label">Address / Location</label>
									<input id="shopAddress" name="shop_address" type="text" class="form-control seller-form-control" required>
								</div>

								<div class="col-12 pt-2">
									<button type="submit" class="btn profile-btn profile-btn-seller seller-submit-btn"><i class="bi bi-send-check me-2"></i>Submit Application</button>
								</div>
							</form>
						</div>
					</section>
				</div>
			</div>
		</div>
	</main>

	<footer class="bh-footer-bar px-4 px-lg-5 py-4 mt-5">
		<div class="container-fluid bh-footer-bar-container">
			<div class="bh-footer-bar-left">
				<div class="bh-footer-bar-logo-box">
					<img src="Assets/Brew_Hub.png" alt="Brewhub Logo" class="bh-footer-bar-logo">
				</div>

				<div class="bh-footer-bar-meta">
					<div class="bh-footer-bar-copy">&copy; 2026 Brewhub</div>
					<div class="bh-footer-bar-legal" aria-label="Legal links">
						<a class="bh-footer-bar-legal-link" href="#">Terms</a>
						<a class="bh-footer-bar-legal-link" href="#">Privacy</a>
						<a class="bh-footer-bar-legal-link" href="#">Cookies</a>
					</div>
				</div>
			</div>

			<nav class="bh-footer-bar-nav" aria-label="Footer navigation">
				<a class="bh-footer-bar-link" href="Buyer/Dashboard.php">Home</a>
				<a class="bh-footer-bar-link" href="Buyer/Orders.php">Orders</a>
				<a class="bh-footer-bar-link" href="Buyer/CoffeeIngredients.php">Coffee &amp; Ingredients</a>
				<a class="bh-footer-bar-link" href="Buyer/CupsPackaging.php">Cups &amp; Packaging</a>
				<a class="bh-footer-bar-link" href="Buyer/Equipments.php">Equipments</a>
				<a class="bh-footer-bar-link" href="Buyer/Pastry.php">Pastry</a>
			</nav>
		</div>
	</footer>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

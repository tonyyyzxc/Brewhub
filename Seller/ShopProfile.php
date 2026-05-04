<?php
declare(strict_types=1);

session_start();
require '../config.php';
require '../includes/db_helpers.php';

bh_require_role(['seller'], '../Login.php');

$sellerId = bh_current_user_id();
$profile = bh_fetch_seller_profile($conn, $sellerId);
$message = null;

if (!isset($_SESSION['logout_token']) || !is_string($_SESSION['logout_token']) || $_SESSION['logout_token'] === '') {
	$_SESSION['logout_token'] = function_exists('random_bytes')
		? bin2hex(random_bytes(16))
		: hash('sha256', uniqid('', true));
}
$logoutToken = (string) $_SESSION['logout_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout'])) {
	$postedToken = (string) ($_POST['logout_token'] ?? '');
	if (hash_equals($logoutToken, $postedToken)) {
		session_unset();
		session_destroy();
		header('Location: ../Login.php');
		exit;
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['logout'])) {
	$postedProfile = [
		'shop_name' => trim((string) ($_POST['shop_name'] ?? '')),
		'contact' => trim((string) ($_POST['shop_contact'] ?? '')),
		'seller_type' => trim((string) ($_POST['seller_type'] ?? '')),
		'description' => trim((string) ($_POST['shop_description'] ?? '')),
		'address' => trim((string) ($_POST['shop_address'] ?? '')),
	];

	if ($postedProfile['shop_name'] === '' || $postedProfile['contact'] === '' || $postedProfile['description'] === '') {
		$message = ['type' => 'danger', 'text' => 'Shop name, contact, and description are required.'];
		$profile = array_merge($profile, $postedProfile);
	} else {
		$ok = bh_save_seller_profile($conn, $sellerId, $postedProfile);
		$profile = bh_fetch_seller_profile($conn, $sellerId);
		$message = $ok
			? ['type' => 'success', 'text' => 'Shop profile saved successfully.']
			: ['type' => 'danger', 'text' => 'Unable to save shop profile.'];
	}
}
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Shop Profile</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<link href="../Style.css" rel="stylesheet">
</head>
<body class="dashboard-page">
	<nav class="navbar navbar-expand-md navbar-light fixed-top bh-navbar">
		<div class="container-fluid px-4 px-lg-5 bh-nav-container">
			<a class="navbar-brand bh-brand" href="../Buyer/Dashboard.php">Brewhub</a>

			<div class="d-flex align-items-center gap-2 order-md-3 bh-nav-actions">
				<span class="navbar-text" style="color: #8B4513; font-weight: 500;">
					<i class="bi bi-shop me-2"></i><?php echo htmlspecialchars($profile['shop_name'], ENT_QUOTES, 'UTF-8'); ?>
				</span>
			</div>

			<div class="collapse navbar-collapse justify-content-center order-md-2" id="navbarNav">
				<ul class="navbar-nav align-items-md-center gap-md-4 gap-lg-5 bh-nav-links">
					<li class="nav-item"><a class="nav-link" href="SellerDashboard.php">Dashboard</a></li>
					<li class="nav-item"><a class="nav-link" href="Products.php">Products</a></li>
					<li class="nav-item"><a class="nav-link" href="Orders.php">Orders</a></li>
					<li class="nav-item"><a class="nav-link active" aria-current="page" href="ShopProfile.php">Shop Profile</a></li>
				</ul>
			</div>
		</div>
	</nav>

	<main class="seller-dashboard-main py-4 py-lg-5">
		<div class="container seller-dashboard-container">
			<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
				<div>
					<p class="profile-kicker mb-2"><i class="bi bi-shop me-2"></i>Seller Center</p>
					<h1 class="seller-dashboard-title mb-0">Shop Profile</h1>
				</div>
			</div>

			<section class="seller-section-card mb-4">
				<h2 class="seller-section-heading mb-3"><i class="bi bi-shop-window me-2"></i>Shop Profile</h2>
				<?php if ($message): ?>
					<div class="alert alert-<?php echo $message['type']; ?> border-0" role="alert"><?php echo htmlspecialchars($message['text'], ENT_QUOTES, 'UTF-8'); ?></div>
				<?php endif; ?>
				<form action="ShopProfile.php" method="post" class="row g-3">
					<input type="hidden" name="logout_token" value="<?php echo htmlspecialchars($logoutToken, ENT_QUOTES, 'UTF-8'); ?>">
					<div class="col-12 col-md-6">
						<label class="form-label seller-form-label" for="shopName">Shop Name</label>
						<input id="shopName" name="shop_name" type="text" class="form-control seller-form-control" value="<?php echo htmlspecialchars((string) $profile['shop_name'], ENT_QUOTES, 'UTF-8'); ?>" required>
					</div>
					<div class="col-12 col-md-6">
						<label class="form-label seller-form-label" for="shopContact">Contact Info</label>
						<input id="shopContact" name="shop_contact" type="text" class="form-control seller-form-control" value="<?php echo htmlspecialchars((string) $profile['contact'], ENT_QUOTES, 'UTF-8'); ?>" required>
					</div>
					<div class="col-12 col-md-6">
						<label class="form-label seller-form-label" for="sellerType">Seller Type</label>
						<input id="sellerType" name="seller_type" type="text" class="form-control seller-form-control" value="<?php echo htmlspecialchars((string) $profile['seller_type'], ENT_QUOTES, 'UTF-8'); ?>">
					</div>
					<div class="col-12 col-md-6">
						<label class="form-label seller-form-label" for="shopAddress">Address</label>
						<input id="shopAddress" name="shop_address" type="text" class="form-control seller-form-control" value="<?php echo htmlspecialchars((string) $profile['address'], ENT_QUOTES, 'UTF-8'); ?>">
					</div>
					<div class="col-12">
						<label class="form-label seller-form-label" for="shopDescription">Description</label>
						<textarea id="shopDescription" name="shop_description" rows="4" class="form-control seller-form-control" required><?php echo htmlspecialchars((string) $profile['description'], ENT_QUOTES, 'UTF-8'); ?></textarea>
					</div>
					<div class="col-12 d-flex flex-wrap gap-2">
						<button type="submit" class="btn profile-btn profile-btn-seller"><i class="bi bi-floppy me-2"></i>Save Shop Profile</button>
						<button type="submit" name="logout" value="1" formnovalidate class="btn profile-btn profile-btn-seller"><i class="bi bi-box-arrow-right me-2"></i>Logout</button>
					</div>
				</form>
			</section>
		</div>
	</main>

	<footer class="bh-footer-bar px-4 px-lg-5 py-4 mt-5">
		<div class="container-fluid bh-footer-bar-container">
			<div class="bh-footer-bar-left">
				<div class="bh-footer-bar-logo-box"><img src="../Assets/Brew_Hub.png" alt="Brewhub Logo" class="bh-footer-bar-logo"></div>
				<div class="bh-footer-bar-meta">
					<div class="bh-footer-bar-copy">&copy; 2026 Brewhub Seller</div>
					<div class="bh-footer-bar-legal" aria-label="Legal links">
						<a class="bh-footer-bar-legal-link" href="#">Terms</a>
						<a class="bh-footer-bar-legal-link" href="#">Privacy</a>
						<a class="bh-footer-bar-legal-link" href="#">Cookies</a>
					</div>
				</div>
			</div>
			<nav class="bh-footer-bar-nav" aria-label="Footer navigation">
				<a class="bh-footer-bar-link" href="SellerDashboard.php">Dashboard</a>
				<a class="bh-footer-bar-link" href="Products.php">Products</a>
				<a class="bh-footer-bar-link" href="Orders.php">Orders</a>
				<a class="bh-footer-bar-link" href="ShopProfile.php">Shop Profile</a>
			</nav>
		</div>
	</footer>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
</body>
</html>

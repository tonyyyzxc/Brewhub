<?php
declare(strict_types=1);

session_start();
require '../config.php';
require '../includes/db_helpers.php';

bh_require_role(['seller'], '../Login.php');

$sellerId = bh_current_user_id();
$sellerProfile = bh_fetch_seller_profile($conn, $sellerId);

$stmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM listings WHERE user_id = ?");
$stmt->bind_param('i', $sellerId);
$stmt->execute();
$totalProducts = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("
	SELECT
		COUNT(DISTINCT o.order_id) AS total_orders,
		COALESCE(SUM(oi.subtotal), 0) AS total_sales
	FROM order_items oi
	JOIN orders o ON oi.order_id = o.order_id
	JOIN listings l ON oi.listing_id = l.listing_id
	WHERE l.user_id = ?
");
$stmt->bind_param('i', $sellerId);
$stmt->execute();
$totals = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

$totalOrders = (int) ($totals['total_orders'] ?? 0);
$totalSales = (float) ($totals['total_sales'] ?? 0);

$stmt = $conn->prepare("
	SELECT p.product_name, COALESCE(SUM(oi.subtotal), 0) AS sales
	FROM order_items oi
	JOIN listings l ON oi.listing_id = l.listing_id
	JOIN products p ON l.product_id = p.product_id
	WHERE l.user_id = ?
	GROUP BY p.product_id, p.product_name
	ORDER BY sales DESC
");
$stmt->bind_param('i', $sellerId);
$stmt->execute();
$result = $stmt->get_result();

$productSales = [];
while ($row = $result->fetch_assoc()) {
	$productSales[(string) $row['product_name']] = (float) $row['sales'];
}
$stmt->close();

$chartLabels = array_keys($productSales);
$chartData = array_values($productSales);
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Seller Dashboard</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<link href="../Style.css" rel="stylesheet">
	<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
</head>
<body class="dashboard-page">
	<nav class="navbar navbar-expand-md navbar-light fixed-top bh-navbar">
		<div class="container-fluid px-4 px-lg-5 bh-nav-container">
			<a class="navbar-brand bh-brand" href="../Buyer/Dashboard.php">Brewhub</a>
			<div class="d-flex align-items-center gap-2 order-md-3 bh-nav-actions">
				<span class="navbar-text" style="color: #8B4513; font-weight: 500;"><i class="bi bi-shop me-2"></i><?php echo htmlspecialchars($sellerProfile['shop_name'], ENT_QUOTES, 'UTF-8'); ?></span>
			</div>
			<div class="collapse navbar-collapse justify-content-center order-md-2" id="navbarNav">
				<ul class="navbar-nav align-items-md-center gap-md-4 gap-lg-5 bh-nav-links">
					<li class="nav-item"><a class="nav-link active" href="SellerDashboard.php">Dashboard</a></li>
					<li class="nav-item"><a class="nav-link" href="Products.php">Products</a></li>
					<li class="nav-item"><a class="nav-link" href="Orders.php">Orders</a></li>
					<li class="nav-item"><a class="nav-link" href="ShopProfile.php">Shop Profile</a></li>
				</ul>
			</div>
		</div>
	</nav>

	<main class="seller-dashboard-main py-4 py-lg-5">
		<div class="container seller-dashboard-container">
			<div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
				<div>
					<p class="profile-kicker mb-2"><i class="bi bi-shop me-2"></i>Seller Center</p>
					<h1 class="seller-dashboard-title mb-0">Seller Dashboard</h1>
				</div>
			</div>

			<section id="overview" class="mb-4">
				<div class="row g-3">
					<div class="col-12 col-md-4"><div class="seller-stat-card h-100"><div class="seller-stat-icon"><i class="bi bi-cash-coin"></i></div><p class="seller-stat-label mb-1">Total Sales</p><h3 class="seller-stat-value mb-0">PHP <?php echo number_format($totalSales, 2); ?></h3></div></div>
					<div class="col-12 col-md-4"><div class="seller-stat-card h-100"><div class="seller-stat-icon"><i class="bi bi-receipt"></i></div><p class="seller-stat-label mb-1">Total Orders</p><h3 class="seller-stat-value mb-0"><?php echo (int) $totalOrders; ?></h3></div></div>
					<div class="col-12 col-md-4"><div class="seller-stat-card h-100"><div class="seller-stat-icon"><i class="bi bi-box-seam"></i></div><p class="seller-stat-label mb-1">Total Products</p><h3 class="seller-stat-value mb-0"><?php echo (int) $totalProducts; ?></h3></div></div>
				</div>
			</section>

			<section id="best-sellers" class="mb-4">
				<div class="seller-stat-card">
					<h2 class="seller-stat-label mb-4" style="font-size: 1.25rem; font-weight: 600;">Best Selling Products</h2>
					<?php if (empty($productSales)): ?>
						<div class="text-center py-5" style="color: #8B4513; opacity: 0.7;">
							<i class="bi bi-graph-up" style="font-size: 3rem; margin-bottom: 1rem; display: block;"></i>
							<p class="mb-0">No sales data yet. Start selling to see your best sellers!</p>
						</div>
					<?php else: ?>
						<div style="position: relative; height: 400px;"><canvas id="bestSellersChart"></canvas></div>
					<?php endif; ?>
				</div>
			</section>
		</div>
	</main>

	<footer class="bh-footer-bar px-4 px-lg-5 py-4 mt-5">
		<div class="container-fluid bh-footer-bar-container">
			<div class="bh-footer-bar-left">
				<div class="bh-footer-bar-logo-box"><img src="../Assets/Brew_Hub.png" alt="Brewhub Logo" class="bh-footer-bar-logo"></div>
				<div class="bh-footer-bar-meta"><div class="bh-footer-bar-copy">&copy; 2026 Brewhub Seller</div><div class="bh-footer-bar-legal" aria-label="Legal links"><a class="bh-footer-bar-legal-link" href="#">Terms</a><a class="bh-footer-bar-legal-link" href="#">Privacy</a><a class="bh-footer-bar-legal-link" href="#">Cookies</a></div></div>
			</div>
			<nav class="bh-footer-bar-nav" aria-label="Footer navigation"><a class="bh-footer-bar-link" href="SellerDashboard.php">Dashboard</a><a class="bh-footer-bar-link" href="Products.php">Products</a><a class="bh-footer-bar-link" href="Orders.php">Orders</a><a class="bh-footer-bar-link" href="ShopProfile.php">Shop Profile</a></nav>
		</div>
	</footer>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<?php if (!empty($productSales)): ?>
	<script>
		const ctx = document.getElementById('bestSellersChart');
		new Chart(ctx, {
			type: 'bar',
			data: {
				labels: <?php echo json_encode($chartLabels); ?>,
				datasets: [{ label: 'Sales (PHP)', data: <?php echo json_encode($chartData); ?>, backgroundColor: 'rgba(139, 69, 19, 0.7)', borderColor: 'rgba(139, 69, 19, 1)', borderWidth: 1 }]
			},
			options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { callback: function(value) { return 'PHP ' + value.toLocaleString(); } } } } }
		});
	</script>
	<?php endif; ?>
</body>
</html>

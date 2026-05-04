<?php
declare(strict_types=1);

session_start();
require '../config.php';
require '../includes/db_helpers.php';

bh_require_role(['seller'], '../Login.php');

$sellerId = bh_current_user_id();
$sellerProfile = bh_fetch_seller_profile($conn, $sellerId);
$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
	$orderId = (int) ($_POST['order_id'] ?? 0);
	$status = strtolower(trim((string) ($_POST['status'] ?? 'pending')));
	$allowedStatuses = ['pending', 'completed', 'cancelled'];

	if ($orderId > 0 && in_array($status, $allowedStatuses, true)) {
		$checkStmt = $conn->prepare("
			SELECT COUNT(*) AS cnt
			FROM order_items oi
			JOIN listings l ON oi.listing_id = l.listing_id
			WHERE oi.order_id = ? AND l.user_id = ?
		");
		$checkStmt->bind_param('ii', $orderId, $sellerId);
		$checkStmt->execute();
		$ownsOrder = (int) ($checkStmt->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;
		$checkStmt->close();

		if ($ownsOrder) {
			$updateStmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
			$updateStmt->bind_param('si', $status, $orderId);
			$updateStmt->execute();
			$updateStmt->close();
			$flash = ['type' => 'success', 'text' => 'Order status updated.'];
		} else {
			$flash = ['type' => 'danger', 'text' => 'Order not found for this seller.'];
		}
	}
}

$stmt = $conn->prepare("
	SELECT
		o.order_id,
		o.status,
		o.order_date,
		u.FirstName,
		u.LastName,
		p.product_name,
		oi.quantity,
		oi.subtotal
	FROM order_items oi
	JOIN orders o ON oi.order_id = o.order_id
	JOIN listings l ON oi.listing_id = l.listing_id
	JOIN products p ON l.product_id = p.product_id
	JOIN users u ON o.buyer_id = u.user_id
	WHERE l.user_id = ?
	ORDER BY o.order_date DESC, oi.order_item_id DESC
");
$stmt->bind_param('i', $sellerId);
$stmt->execute();
$result = $stmt->get_result();

$orders = [];
while ($row = $result->fetch_assoc()) {
	$orders[] = $row;
}
$stmt->close();
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Seller Orders</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<link href="../Style.css" rel="stylesheet">
</head>
<body class="dashboard-page d-flex flex-column min-vh-100">
	<nav class="navbar navbar-expand-md navbar-light fixed-top bh-navbar">
		<div class="container-fluid px-4 px-lg-5 bh-nav-container">
			<a class="navbar-brand bh-brand" href="../Buyer/Dashboard.php">Brewhub</a>

			<div class="d-flex align-items-center gap-2 order-md-3 bh-nav-actions">
				<span class="navbar-text" style="color: #8B4513; font-weight: 500;">
					<i class="bi bi-shop me-2"></i><?php echo htmlspecialchars($sellerProfile['shop_name'], ENT_QUOTES, 'UTF-8'); ?>
				</span>
			</div>

			<div class="collapse navbar-collapse justify-content-center order-md-2" id="navbarNav">
				<ul class="navbar-nav align-items-md-center gap-md-4 gap-lg-5 bh-nav-links">
					<li class="nav-item"><a class="nav-link" href="SellerDashboard.php">Dashboard</a></li>
					<li class="nav-item"><a class="nav-link" href="Products.php">Products</a></li>
					<li class="nav-item"><a class="nav-link active" aria-current="page" href="Orders.php">Orders</a></li>
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
					<h1 class="seller-dashboard-title mb-0">Orders</h1>
				</div>
			</div>

			<section class="seller-section-card mb-4">
				<h2 class="seller-section-heading mb-3"><i class="bi bi-bag-check me-2"></i>Orders</h2>
				<?php if ($flash): ?>
					<div class="alert alert-<?php echo $flash['type']; ?> border-0" role="alert"><?php echo htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8'); ?></div>
				<?php endif; ?>
				<div class="table-responsive">
					<table class="table seller-table align-middle">
						<thead>
							<tr>
								<th>Order ID</th>
								<th>Customer</th>
								<th>Item</th>
								<th>Qty</th>
								<th>Subtotal</th>
								<th style="width: 180px;">Status</th>
								<th style="width: 150px;">Action</th>
							</tr>
						</thead>
						<tbody>
							<?php if (empty($orders)): ?>
								<tr><td colspan="7" class="text-center text-muted py-4">No orders yet.</td></tr>
							<?php else: ?>
								<?php foreach ($orders as $order): ?>
									<tr>
										<td>#<?php echo (int) $order['order_id']; ?></td>
										<td><?php echo htmlspecialchars(trim((string) $order['FirstName'] . ' ' . (string) $order['LastName']), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo htmlspecialchars((string) ($order['product_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
										<td><?php echo (int) ($order['quantity'] ?? 0); ?></td>
										<td>PHP <?php echo number_format((float) ($order['subtotal'] ?? 0), 2); ?></td>
										<td>
											<form method="post" class="d-flex gap-2 align-items-center">
												<input type="hidden" name="order_id" value="<?php echo (int) $order['order_id']; ?>">
												<select name="status" class="form-select seller-form-control">
													<option value="pending" <?php echo ((string) ($order['status'] ?? '')) === 'pending' ? 'selected' : ''; ?>>Pending</option>
													<option value="completed" <?php echo ((string) ($order['status'] ?? '')) === 'completed' ? 'selected' : ''; ?>>Completed</option>
													<option value="cancelled" <?php echo ((string) ($order['status'] ?? '')) === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
												</select>
										</td>
										<td>
												<button type="submit" class="btn btn-sm profile-btn profile-btn-edit"><i class="bi bi-arrow-repeat me-1"></i>Update</button>
											</form>
										</td>
									</tr>
								<?php endforeach; ?>
							<?php endif; ?>
						</tbody>
					</table>
				</div>
			</section>
		</div>
	</main>

	<footer class="bh-footer-bar px-4 px-lg-5 py-4 mt-auto">
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

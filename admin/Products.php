<?php
declare(strict_types=1);

session_start();
require '../config.php';

// Guard: admin only
if (!isset($_SESSION['loggedin']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../Login.php");
    exit();
}

$flash = null;

// Handle delete listing
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action    = (string) ($_POST['action'] ?? '');
    $listingId = (int)    ($_POST['listing_id'] ?? 0);

    if ($action === 'delete') {
        if ($listingId <= 0) {
            $flash = ['type' => 'warning', 'text' => 'Invalid listing ID.'];
        } else {
            $stmt = $conn->prepare("DELETE FROM listings WHERE listing_id = ?");
            $stmt->bind_param('i', $listingId);
            $ok = $stmt->execute();
            $stmt->close();
            $flash = $ok
                ? ['type' => 'success', 'text' => 'Listing removed successfully.']
                : ['type' => 'danger',  'text' => 'Listing not found.'];
        }
    }
}

// Fetch all listings joined with products and seller info
$listings = [];
$result = $conn->query("
    SELECT 
        l.listing_id,
        p.product_id,
        p.product_name,
        p.category,
        p.description,
        l.price,
        l.stock,
        l.created_at,
        u.username AS seller_username,
        CONCAT(u.FirstName, ' ', u.LastName) AS seller_name
    FROM listings l
    JOIN products p ON l.product_id = p.product_id
    JOIN users u ON l.user_id = u.user_id
    ORDER BY l.listing_id DESC
");
while ($row = $result->fetch_assoc()) {
    $listings[] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Products – Brewhub Admin</title>
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
	<link href="../Style.css?v=20260420" rel="stylesheet">
</head>

<body class="admin-page admin-sidebar-layout d-flex flex-column min-vh-100">
	<nav class="admin-topbar">
		<div class="admin-topbar-container">
			<button class="admin-sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
				<i class="bi bi-list"></i>
			</button>
			<a class="admin-topbar-brand" href="admin.php">Brewhub Admin</a>
			<div class="admin-topbar-actions">
				<a class="btn bh-icon-btn position-relative" href="SellerRequests.php" aria-label="Notifications">
					<i class="bi bi-bell"></i>
				</a>
				<a class="btn bh-icon-btn" href="#" aria-label="Settings">
					<i class="bi bi-gear"></i>
				</a>
			</div>
		</div>
	</nav>

	<aside class="admin-sidebar" id="adminSidebar">
		<div class="admin-sidebar-header">
			<a class="admin-sidebar-brand" href="admin.php">Brewhub</a>
		</div>
		<nav class="admin-sidebar-nav">
			<a class="admin-sidebar-link" href="admin.php">
				<i class="bi bi-speedometer2"></i>
				<span>Dashboard</span>
			</a>
			<a class="admin-sidebar-link" href="UserManagement.php">
				<i class="bi bi-people"></i>
				<span>User Management</span>
			</a>
			<a class="admin-sidebar-link" href="SellerRequests.php">
				<i class="bi bi-shop"></i>
				<span>Seller Requests</span>
			</a>
			<a class="admin-sidebar-link active" href="Products.php">
				<i class="bi bi-box-seam"></i>
				<span>Products</span>
			</a>
		</nav>
		<div class="admin-sidebar-footer">
			<a class="admin-sidebar-logout" href="../Login.php">
				<i class="bi bi-box-arrow-right"></i>
				<span>Logout</span>
			</a>
		</div>
	</aside>

	<main class="admin-main admin-main-with-sidebar">
		<section class="admin-dashboard py-5">
			<div class="container-fluid px-4 px-lg-5">
				<div class="admin-dashboard-header mb-4">
					<div class="d-flex align-items-center justify-content-between gap-3 flex-wrap">
						<div>
							<h2 class="admin-dashboard-title mb-1">Products & Listings</h2>
							<div class="text-muted">All active seller listings from the database.</div>
						</div>
						<a class="btn admin-btn admin-btn-ghost btn-sm" href="admin.php">
							<i class="bi bi-arrow-left me-1"></i>Back
						</a>
					</div>
				</div>

						<?php if ($flash): ?>
							<div class="bh-toast-container position-fixed top-0 end-0 p-3">
								<div id="bhToast" class="toast bh-toast bh-toast--<?php echo htmlspecialchars($flash['type'], ENT_QUOTES, 'UTF-8'); ?>" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000">
									<div class="toast-body d-flex align-items-start gap-2">
										<?php if ($flash['type'] === 'danger'): ?>
											<i class="bi bi-exclamation-triangle-fill bh-toast-icon" aria-hidden="true"></i>
										<?php elseif ($flash['type'] === 'warning'): ?>
											<i class="bi bi-exclamation-circle-fill bh-toast-icon" aria-hidden="true"></i>
										<?php else: ?>
											<i class="bi bi-check2-circle bh-toast-icon" aria-hidden="true"></i>
										<?php endif; ?>
										<div class="bh-toast-text"><?php echo htmlspecialchars($flash['text'], ENT_QUOTES, 'UTF-8'); ?></div>
										<button type="button" class="btn-close ms-auto" data-bs-dismiss="toast" aria-label="Close"></button>
									</div>
								</div>
							</div>
						<?php endif; ?>

				<div class="row">
					<div class="col-12">
						<div class="admin-section-card">
							<div class="admin-card-header">
								<div class="d-flex align-items-center gap-2">
									<span class="admin-card-icon"><i class="bi bi-box-seam"></i></span>
									<h3 class="admin-card-title mb-0">All Listings (<?php echo count($listings); ?>)</h3>
								</div>
								<input type="text" id="searchListings" class="form-control form-control-sm"
									placeholder="Search products..." style="max-width:220px;">
							</div>

							<?php if (count($listings) === 0): ?>
								<div class="d-flex align-items-center gap-2 p-4 text-muted">
									<span class="admin-card-icon"><i class="bi bi-inbox"></i></span>
									<div>
										<div class="fw-bold">No listings found</div>
										<div class="small">Sellers haven't listed any products yet.</div>
									</div>
								</div>
							<?php else: ?>
								<div class="table-responsive">
									<table class="table table-sm align-middle admin-table mb-0" id="listingsTable">
										<thead>
											<tr>
												<th>ID</th>
												<th>Product Name</th>
												<th>Category</th>
												<th>Description</th>
												<th>Price</th>
												<th>Stock</th>
												<th>Seller</th>
												<th>Listed On</th>
												<th class="text-end">Actions</th>
											</tr>
										</thead>
										<tbody>
											<?php foreach ($listings as $l): ?>
												<tr>
													<td class="fw-semibold">#<?php echo (int)$l['listing_id']; ?></td>
													<td class="fw-semibold"><?php echo htmlspecialchars($l['product_name'], ENT_QUOTES, 'UTF-8'); ?></td>
													<td><span class="admin-badge"><?php echo htmlspecialchars($l['category'], ENT_QUOTES, 'UTF-8'); ?></span></td>
													<td class="text-muted" style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
														<?php echo htmlspecialchars($l['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
													</td>
													<td class="fw-semibold">₱<?php echo number_format((float)$l['price'], 2); ?></td>
													<td class="text-muted"><?php echo (int)$l['stock']; ?></td>
													<td>
														<div class="fw-semibold"><?php echo htmlspecialchars($l['seller_name'], ENT_QUOTES, 'UTF-8'); ?></div>
														<div class="text-muted small">@<?php echo htmlspecialchars($l['seller_username'], ENT_QUOTES, 'UTF-8'); ?></div>
													</td>
													<td class="text-muted">
														<?php echo $l['created_at'] ? date('M d, Y', strtotime($l['created_at'])) : '-'; ?>
													</td>
													<td class="text-end">
														<form method="post" class="m-0"
															onsubmit="return confirm('Remove this listing?');">
															<input type="hidden" name="action" value="delete">
															<input type="hidden" name="listing_id" value="<?php echo (int)$l['listing_id']; ?>">
															<button type="submit" class="btn admin-btn admin-btn-danger btn-sm">
																<i class="bi bi-trash3 me-1"></i>Remove
															</button>
														</form>
													</td>
												</tr>
											<?php endforeach; ?>
										</tbody>
									</table>
								</div>
							<?php endif; ?>
						</div>
					</div>
				</div>

			</div>
		</section>
	</main>

	<footer class="bh-footer-bar px-4 px-lg-5 py-4 mt-auto">
		<div class="container-fluid bh-footer-bar-container">
			<div class="bh-footer-bar-left">
				<div class="bh-footer-bar-logo-box">
					<img src="../Assets/Brew_Hub.png" alt="Brewhub Logo" class="bh-footer-bar-logo">
				</div>
				<div class="bh-footer-bar-meta">
					<div class="bh-footer-bar-copy">&copy; 2026 Brewhub Admin</div>
					<div class="bh-footer-bar-legal">
						<a class="bh-footer-bar-legal-link" href="#">Terms</a>
						<a class="bh-footer-bar-legal-link" href="#">Privacy</a>
						<a class="bh-footer-bar-legal-link" href="#">Cookies</a>
					</div>
				</div>
			</div>
			<nav class="bh-footer-bar-nav">
				<a class="bh-footer-bar-link" href="admin.php">Dashboard</a>
				<a class="bh-footer-bar-link" href="UserManagement.php">User Management</a>
				<a class="bh-footer-bar-link" href="SellerRequests.php">Seller Requests</a>
				<a class="bh-footer-bar-link" href="Products.php">Products</a>
			</nav>
		</div>
	</footer>

	<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
	<script>
		// Toast (success/error feedback)
		document.addEventListener('DOMContentLoaded', () => {
			const toastEl = document.getElementById('bhToast');
			if (toastEl && window.bootstrap && bootstrap.Toast) {
				new bootstrap.Toast(toastEl).show();
			}
		});

		document.getElementById('sidebarToggle').addEventListener('click', () => {
			document.body.classList.toggle('admin-sidebar-collapsed');
		});

		document.getElementById('searchListings').addEventListener('keyup', function () {
			const val = this.value.toLowerCase();
			document.querySelectorAll('#listingsTable tbody tr').forEach(row => {
				row.style.display = row.textContent.toLowerCase().includes(val) ? '' : 'none';
			});
		});
	</script>
</body>
</html>
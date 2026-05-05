<?php
declare(strict_types=1);

session_start();
require '../config.php';
require '../includes/db_helpers.php';

bh_require_role(['seller'], '../Login.php');

$sellerId = bh_current_user_id();
$sellerProfile = bh_fetch_seller_profile($conn, $sellerId);

function generateProductImageName(string $extension): string
{
	if (function_exists('random_bytes')) {
		return 'product_p_' . bin2hex(random_bytes(8)) . '.' . $extension;
	}
	return 'product_p_' . str_replace('.', '', uniqid('', true)) . '.' . $extension;
}

function tryDeleteUploadedImage(string $imagePath): void
{
	$uploadsPrefix = '../Assets/ProductUploads/';
	if (strpos($imagePath, $uploadsPrefix) !== 0) {
		return;
	}

	$targetPath = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'ProductUploads' . DIRECTORY_SEPARATOR . basename($imagePath);
	if (is_file($targetPath)) {
		@unlink($targetPath);
	}
}

$uploadError = null;
$formAction = 'add';
$listingId = 0;
$postedName = '';
$postedCategory = '';
$postedDescription = '';
$postedPrice = '';
$postedStock = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$formAction = (string) ($_POST['form_action'] ?? 'add');
	$listingId = (int) ($_POST['listing_id'] ?? 0);
	$postedName = (string) ($_POST['product_name'] ?? '');
	$postedCategory = (string) ($_POST['product_category'] ?? '');
	$postedDescription = (string) ($_POST['product_description'] ?? '');
	$postedPrice = (string) ($_POST['product_price'] ?? '');
	$postedStock = (string) ($_POST['product_stock'] ?? '');

	if ($formAction === 'delete') {
		if ($listingId <= 0) {
			$uploadError = 'Missing product id.';
		} else {
			$stmt = $conn->prepare("
				SELECT l.product_id, p.image_path
				FROM listings l
				JOIN products p ON l.product_id = p.product_id
				WHERE l.listing_id = ? AND l.user_id = ?
			");
			$stmt->bind_param('ii', $listingId, $sellerId);
			$stmt->execute();
			$row = $stmt->get_result()->fetch_assoc();
			$stmt->close();

			if (!$row) {
				$uploadError = 'Product not found.';
			} else {
				$productId = (int) $row['product_id'];
				$imagePath = (string) ($row['image_path'] ?? '');
				$stmt = $conn->prepare("DELETE FROM products WHERE product_id = ?");
				$stmt->bind_param('i', $productId);
				if ($stmt->execute()) {
					$stmt->close();
					if ($imagePath !== '') {
						tryDeleteUploadedImage($imagePath);
					}
					header('Location: Products.php');
					exit;
				}
				$stmt->close();
				$uploadError = 'Failed to delete product.';
			}
		}
	}

	if ($formAction !== 'delete') {
		$productName = trim($postedName);
		$productCategory = trim($postedCategory);
		$productDescription = trim($postedDescription);
		$productPrice = (float) $postedPrice;
		$productStock = (int) $postedStock;

		if ($productName === '') {
			$uploadError = 'Product name is required.';
		} elseif ($productCategory === '') {
			$uploadError = 'Product category is required.';
		} elseif ($productDescription === '') {
			$uploadError = 'Product description is required.';
		} elseif ($productPrice <= 0) {
			$uploadError = 'Product price must be greater than 0.';
		} elseif ($productStock < 0) {
			$uploadError = 'Product stock cannot be negative.';
		} elseif ($formAction === 'edit' && $listingId <= 0) {
			$uploadError = 'Missing product id.';
		}

		$existing = null;
		if ($uploadError === null && $formAction === 'edit') {
			$stmt = $conn->prepare("
				SELECT l.product_id, p.image_path
				FROM listings l
				JOIN products p ON l.product_id = p.product_id
				WHERE l.listing_id = ? AND l.user_id = ?
			");
			$stmt->bind_param('ii', $listingId, $sellerId);
			$stmt->execute();
			$existing = $stmt->get_result()->fetch_assoc();
			$stmt->close();
			if (!$existing) {
				$uploadError = 'Product not found.';
			}
		}

		$imagePath = null;
		$hasNewImage = isset($_FILES['product_image']) && is_array($_FILES['product_image'])
			&& (int) ($_FILES['product_image']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK;

		if ($uploadError === null && ($hasNewImage || $formAction === 'add')) {
			if (!$hasNewImage) {
				$uploadError = 'Product image is required.';
			} else {
				$uploadDir = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Assets' . DIRECTORY_SEPARATOR . 'ProductUploads';
				if (!is_dir($uploadDir)) {
					@mkdir($uploadDir, 0775, true);
				}

				$tmpPath = (string) $_FILES['product_image']['tmp_name'];
				$originalName = (string) $_FILES['product_image']['name'];
				$fileSizeBytes = (int) ($_FILES['product_image']['size'] ?? 0);
				$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
				$allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

				if ($fileSizeBytes <= 0 || $fileSizeBytes > 2 * 1024 * 1024) {
					$uploadError = 'Image must be 2MB or smaller.';
				} elseif (@getimagesize($tmpPath) === false) {
					$uploadError = 'Uploaded file must be an image.';
				} elseif (!in_array($extension, $allowedExtensions, true)) {
					$uploadError = 'Allowed image types: JPG, JPEG, PNG, WEBP.';
				} else {
					$imageFileName = generateProductImageName($extension);
					$targetPath = $uploadDir . DIRECTORY_SEPARATOR . $imageFileName;
					if (!move_uploaded_file($tmpPath, $targetPath)) {
						$uploadError = 'Failed to save uploaded image.';
					} else {
						$imagePath = '../Assets/ProductUploads/' . $imageFileName;
					}
				}
			}
		}

		if ($uploadError === null) {
			if ($formAction === 'add') {
				$conn->begin_transaction();
				try {
					$stmt = $conn->prepare("INSERT INTO products (user_id, product_name, category, description, image_path) VALUES (?, ?, ?, ?, ?)");
					$stmt->bind_param('issss', $sellerId, $productName, $productCategory, $productDescription, $imagePath);
					$stmt->execute();
					$productId = (int) $conn->insert_id;
					$stmt->close();

					$stmt = $conn->prepare("INSERT INTO listings (user_id, product_id, price, stock) VALUES (?, ?, ?, ?)");
					$stmt->bind_param('iidi', $sellerId, $productId, $productPrice, $productStock);
					$stmt->execute();
					$stmt->close();
					$conn->commit();
					header('Location: Products.php');
					exit;
				} catch (Throwable $e) {
					$conn->rollback();
					$uploadError = 'Failed to save product.';
				}
			} elseif ($formAction === 'edit' && $existing) {
				$productId = (int) $existing['product_id'];
				$oldImage = (string) ($existing['image_path'] ?? '');
				$newImage = $imagePath ?? $oldImage;

				$conn->begin_transaction();
				try {
					$stmt = $conn->prepare("UPDATE products SET product_name = ?, category = ?, description = ?, image_path = ? WHERE product_id = ?");
					$stmt->bind_param('ssssi', $productName, $productCategory, $productDescription, $newImage, $productId);
					$stmt->execute();
					$stmt->close();

					$stmt = $conn->prepare("UPDATE listings SET price = ?, stock = ? WHERE listing_id = ? AND user_id = ?");
					$stmt->bind_param('diii', $productPrice, $productStock, $listingId, $sellerId);
					$stmt->execute();
					$stmt->close();
					$conn->commit();

					if ($imagePath !== null && $oldImage !== '') {
						tryDeleteUploadedImage($oldImage);
					}
					header('Location: Products.php');
					exit;
				} catch (Throwable $e) {
					$conn->rollback();
					$uploadError = 'Failed to update product.';
				}
			}
		}
	}
}

$products = bh_fetch_listings($conn, null, $sellerId);
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Seller Products</title>
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
					<li class="nav-item"><a class="nav-link active" aria-current="page" href="Products.php">Products</a></li>
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
					<h1 class="seller-dashboard-title mb-0">Products</h1>
				</div>
			</div>

			<section class="seller-section-card mb-4">
				<div class="d-flex justify-content-between align-items-center mb-3">
					<h2 class="seller-section-heading mb-0"><i class="bi bi-tags me-2"></i>Products</h2>
					<button id="openAddProductBtn" type="button" class="btn profile-btn profile-btn-seller" data-bs-toggle="modal" data-bs-target="#addProductModal">
						<i class="bi bi-plus-circle me-2"></i>Add Product
					</button>
				</div>

				<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
					<div class="modal-dialog modal-lg modal-dialog-centered">
						<div class="modal-content">
							<div class="modal-header">
								<h1 class="modal-title fs-5" id="addProductModalLabel">Add Product</h1>
								<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
							</div>
							<form action="Products.php" method="post" enctype="multipart/form-data">
								<div class="modal-body">
									<input type="hidden" name="form_action" id="formAction" value="add">
									<input type="hidden" name="listing_id" id="listingId" value="">
									<?php if ($uploadError !== null): ?>
										<div class="alert alert-danger mb-3" role="alert"><?php echo htmlspecialchars($uploadError, ENT_QUOTES, 'UTF-8'); ?></div>
									<?php endif; ?>

									<div class="row g-3">
										<div class="col-12 col-md-6">
											<label class="form-label seller-form-label" for="productName">Product Name</label>
											<input id="productName" name="product_name" type="text" class="form-control seller-form-control" value="<?php echo $uploadError !== null ? htmlspecialchars($postedName, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
										</div>
										<div class="col-12 col-md-6">
											<label class="form-label seller-form-label" for="productCategory">Category</label>
											<select id="productCategory" name="product_category" class="form-select seller-form-control" required>
												<?php foreach (['Coffee & Ingredients', 'Cups & Packaging', 'Equipments', 'Pastry'] as $category): ?>
													<option value="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $postedCategory === $category ? 'selected' : ''; ?>><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
										<div class="col-12 col-md-6">
											<label class="form-label seller-form-label" for="productImage">Product Image</label>
											<input id="productImage" name="product_image" type="file" class="form-control seller-form-control" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" required>
										</div>
										<div class="col-12 col-md-3">
											<label class="form-label seller-form-label" for="productPrice">Price</label>
											<input id="productPrice" name="product_price" type="number" step="0.01" min="0" class="form-control seller-form-control" value="<?php echo $uploadError !== null ? htmlspecialchars($postedPrice, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
										</div>
										<div class="col-12 col-md-3">
											<label class="form-label seller-form-label" for="productStock">Stock</label>
											<input id="productStock" name="product_stock" type="number" min="0" class="form-control seller-form-control" value="<?php echo $uploadError !== null ? htmlspecialchars($postedStock, ENT_QUOTES, 'UTF-8') : ''; ?>" required>
										</div>
										<div class="col-12">
											<label class="form-label seller-form-label" for="productDescription">Product Description</label>
											<textarea id="productDescription" name="product_description" rows="4" class="form-control seller-form-control" placeholder="Describe your product..." required><?php echo $uploadError !== null ? htmlspecialchars($postedDescription, ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
										</div>
									</div>
								</div>
								<div class="modal-footer">
									<button type="button" class="btn btn-sm seller-btn-delete" data-bs-dismiss="modal">Cancel</button>
									<button id="submitProductBtn" type="submit" class="btn profile-btn profile-btn-seller"><i class="bi bi-plus-circle me-2"></i>Add Product</button>
								</div>
							</form>
						</div>
					</div>
				</div>

				<div class="container-fluid px-0 products-grid">
					<div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 row-cols-xl-4 g-3">
						<?php foreach ($products as $product): ?>
							<?php
								$image = bh_seller_image_path((string) ($product['image_path'] ?? ''));
								$name = (string) ($product['product_name'] ?? '');
							?>
							<div class="col">
								<div class="card h-100 shadow-sm reference-product-card">
									<div class="reference-product-image-wrap">
										<?php if ($image !== ''): ?>
											<img src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>" class="reference-product-image">
										<?php endif; ?>
									</div>
									<div class="card-body reference-product-body">
										<h5 class="reference-product-title mb-0"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h5>
										<p class="reference-product-seller mb-0"><?php echo htmlspecialchars((string) ($product['category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></p>
										<p class="reference-product-seller mb-0"><?php echo nl2br(htmlspecialchars((string) ($product['description'] ?? ''), ENT_QUOTES, 'UTF-8')); ?></p>
										<div class="d-flex justify-content-between align-items-center">
											<h5 class="reference-product-price mb-0">PHP <?php echo number_format((float) ($product['price'] ?? 0), 2); ?></h5>
											<span class="reference-product-seller">Stock: <?php echo (int) ($product['stock'] ?? 0); ?></span>
										</div>
										<form action="Products.php" method="post" class="d-flex gap-2 pt-2">
											<input type="hidden" name="form_action" value="delete">
											<input type="hidden" name="listing_id" value="<?php echo (int) ($product['listing_id'] ?? 0); ?>">
											<button type="button" class="btn btn-sm profile-btn profile-btn-edit flex-grow-1 js-edit-product" data-listing-id="<?php echo (int) ($product['listing_id'] ?? 0); ?>">
												<i class="bi bi-pencil-square me-1"></i>Edit
											</button>
											<button type="submit" class="btn btn-sm seller-btn-delete flex-grow-1" onclick="return confirm('Delete this product?');">
												<i class="bi bi-trash me-1"></i>Delete
											</button>
										</form>
									</div>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
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
	<script>
		window.sellerProducts = <?php echo json_encode($products, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
	</script>
	<script>
		document.addEventListener('DOMContentLoaded', function () {
			var products = Array.isArray(window.sellerProducts) ? window.sellerProducts : [];
			var byId = new Map(products.map(function (p) { return [String(p.listing_id || ''), p]; }));
			var modalEl = document.getElementById('addProductModal');
			if (!modalEl) return;

			var modalTitle = document.getElementById('addProductModalLabel');
			var formActionInput = document.getElementById('formAction');
			var listingIdInput = document.getElementById('listingId');
			var nameInput = document.getElementById('productName');
			var categoryInput = document.getElementById('productCategory');
			var imageInput = document.getElementById('productImage');
			var priceInput = document.getElementById('productPrice');
			var stockInput = document.getElementById('productStock');
			var descInput = document.getElementById('productDescription');
			var submitBtn = document.getElementById('submitProductBtn');

			function openAdd() {
				if (modalTitle) modalTitle.textContent = 'Add Product';
				if (formActionInput) formActionInput.value = 'add';
				if (listingIdInput) listingIdInput.value = '';
				if (nameInput) nameInput.value = '';
				if (categoryInput) categoryInput.value = 'Coffee & Ingredients';
				if (priceInput) priceInput.value = '';
				if (stockInput) stockInput.value = '';
				if (descInput) descInput.value = '';
				if (imageInput) {
					imageInput.value = '';
					imageInput.required = true;
				}
				if (submitBtn) submitBtn.innerHTML = '<i class="bi bi-plus-circle me-2"></i>Add Product';
			}

			function openEdit(listingId) {
				var p = byId.get(String(listingId));
				if (!p) return;
				if (modalTitle) modalTitle.textContent = 'Edit Product';
				if (formActionInput) formActionInput.value = 'edit';
				if (listingIdInput) listingIdInput.value = String(p.listing_id || '');
				if (nameInput) nameInput.value = String(p.product_name || '');
				if (categoryInput) categoryInput.value = String(p.category || 'Coffee & Ingredients');
				if (priceInput) priceInput.value = String(p.price || '');
				if (stockInput) stockInput.value = String(p.stock || '');
				if (descInput) descInput.value = String(p.description || '');
				if (imageInput) {
					imageInput.value = '';
					imageInput.required = false;
				}
				if (submitBtn) submitBtn.innerHTML = '<i class="bi bi-floppy me-2"></i>Save Changes';
				bootstrap.Modal.getOrCreateInstance(modalEl).show();
			}

			var addBtn = document.getElementById('openAddProductBtn');
			if (addBtn) addBtn.addEventListener('click', openAdd);
			document.querySelectorAll('.js-edit-product').forEach(function (btn) {
				btn.addEventListener('click', function () {
					openEdit(btn.getAttribute('data-listing-id'));
				});
			});
		});
	</script>
	<?php if ($uploadError !== null): ?>
		<script>
			document.addEventListener('DOMContentLoaded', function () {
				var modalEl = document.getElementById('addProductModal');
				if (modalEl) bootstrap.Modal.getOrCreateInstance(modalEl).show();
			});
		</script>
	<?php endif; ?>
</body>
</html>
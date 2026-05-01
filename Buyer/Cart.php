<?php
declare(strict_types=1);

session_start();

function bh_products_by_id_from_session(): array
{
	$byId = [];

	$cache = $_SESSION['bh_product_cache'] ?? [];
	if (is_array($cache)) {
		foreach ($cache as $id => $p) {
			if (!is_array($p)) {
				continue;
			}
			$productId = (int) ($p['id'] ?? $id);
			if ($productId <= 0) {
				continue;
			}
			$byId[$productId] = [
				'id' => $productId,
				'name' => (string) ($p['name'] ?? ''),
				'category' => (string) ($p['category'] ?? ''),
				'price' => (float) ($p['price'] ?? 0),
				'image' => (string) ($p['image'] ?? ''),
			];
		}
	}

	$products = $_SESSION['bh_products'] ?? [];
	if (is_array($products)) {
		foreach ($products as $p) {
			if (!is_array($p)) {
				continue;
			}
			$productId = (int) ($p['id'] ?? 0);
			if ($productId <= 0 || isset($byId[$productId])) {
				continue;
			}
			$byId[$productId] = [
				'id' => $productId,
				'name' => (string) ($p['name'] ?? ''),
				'category' => (string) ($p['category'] ?? ''),
				'price' => (float) ($p['price'] ?? 0),
				'image' => (string) ($p['image'] ?? ''),
			];
		}
	}

	return $byId;
}

function bh_normalize_buyer_image(string $image): string
{
  $image = trim($image);
  if ($image === '') {
    return '../Assets/Carousel.png';
  }

  if (preg_match('~^https?://~i', $image) || strncmp($image, '//', 2) === 0) {
    return $image;
  }

  if (strncmp($image, '../', 3) === 0 || strncmp($image, '/', 1) === 0) {
    return $image;
  }

  return '../' . ltrim($image, './');
}

if (!isset($_SESSION['bh_cart']) || !is_array($_SESSION['bh_cart'])) {
  $_SESSION['bh_cart'] = [];
}

$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
  $action = (string) ($_POST['action'] ?? '');
  $productId = (int) ($_POST['product_id'] ?? 0);

  if ($action === 'update_quantity' && $productId > 0) {
    $quantity = (int) ($_POST['quantity'] ?? 1);
    if ($quantity > 0) {
      $_SESSION['bh_cart'][$productId] = $quantity;
      $flash = 'Cart updated.';
    } else {
      unset($_SESSION['bh_cart'][$productId]);
      $flash = 'Item removed from cart.';
    }
  } elseif ($action === 'remove' && $productId > 0) {
    unset($_SESSION['bh_cart'][$productId]);
    $flash = 'Item removed from cart.';
  } elseif ($action === 'clear_cart') {
    $_SESSION['bh_cart'] = [];
    $flash = 'Cart cleared.';
  } elseif ($action === 'checkout' || isset($_POST['clear_cart_after_checkout'])) {
    // Process checkout and clear cart
    $_SESSION['bh_cart'] = [];
    // Here you can add code to save order to database
    // For now, we just clear the cart
    exit; // Exit to prevent page reload
  }
}

$productsById = bh_products_by_id_from_session();

$cartItems = [];
$subtotal = 0;

foreach ($_SESSION['bh_cart'] as $productId => $quantity) {
  $productId = (int) $productId;
  $quantity = (int) $quantity;
  
  if ($quantity <= 0 || !isset($productsById[$productId])) {
    continue;
  }

  $product = $productsById[$productId];
  $price = (float) ($product['price'] ?? 0);
  $itemTotal = $price * $quantity;
  $subtotal += $itemTotal;

  $cartItems[] = [
    'id' => $productId,
    'name' => (string) ($product['name'] ?? ''),
    'category' => (string) ($product['category'] ?? ''),
    'price' => $price,
    'image' => bh_normalize_buyer_image((string) ($product['image'] ?? '')),
    'quantity' => $quantity,
    'total' => $itemTotal,
  ];
}

$cartCount = array_sum(array_map('intval', (array) $_SESSION['bh_cart']));
$shippingFee = 50.00;
$total = $subtotal + $shippingFee;
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Shopping Cart</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="../Style.css?v=20260423" rel="stylesheet">
</head>
<body class="dashboard-page">
  <nav class="navbar navbar-expand-md navbar-light fixed-top bh-navbar">
    <div class="container-fluid px-4 px-lg-5 bh-nav-container">
      <a class="navbar-brand bh-brand" href="Dashboard.php">Brewhub</a>

      <div class="d-flex align-items-center gap-2 order-md-3 bh-nav-actions">
        <a class="btn bh-icon-btn" href="../Buyer/Profile.php" aria-label="Profile">
          <i class="bi bi-person"></i>
        </a>
        <a class="btn bh-icon-btn position-relative active" href="Cart.php" aria-label="Cart">
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
            <a class="nav-link" href="Dashboard.php">Home</a>
          </li>
          <li class="nav-item dropdown">
            <a class="nav-link dropdown-toggle" href="#" id="productCategoriesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Product Categories</a>
            <ul class="dropdown-menu" aria-labelledby="productCategoriesDropdown">
              <li><a class="dropdown-item" href="CoffeeIngredients.php">Coffee &amp; Ingredients</a></li>
              <li><a class="dropdown-item" href="CupsPackaging.php">Cups &amp; Packaging</a></li>
              <li><a class="dropdown-item" href="Equipments.php">Equipments</a></li>
              <li><a class="dropdown-item" href="Pastry.php">Pastry</a></li>
            </ul>
          </li>
          <li class="nav-item">
            <a class="nav-link" href="Orders.php">Orders</a>
          </li>
        </ul>
      </div>
    </div>
  </nav>

  <main class="dashboard-main">
    <div class="container-fluid px-4 px-lg-5 py-4">
      <div class="row justify-content-center">
        <div class="col-12 col-xl-10">
          <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
              <h1 class="cart-title mb-1">Shopping Cart</h1>
              <p class="cart-subtitle mb-0"><?php echo count($cartItems); ?> item<?php echo count($cartItems) !== 1 ? 's' : ''; ?> in your cart</p>
            </div>
            <a href="Dashboard.php" class="btn bh-btn bh-btn-ghost btn-sm">
              <i class="bi bi-arrow-left me-1"></i>Continue Shopping
            </a>
          </div>

          <?php if ($flash): ?>
            <div class="alert alert-success border-0 mb-4" role="alert">
              <?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?>
            </div>
          <?php endif; ?>

          <?php if (count($cartItems) === 0): ?>
            <div class="bh-section-card text-center py-5">
              <i class="bi bi-cart-x" style="font-size: 4rem; color: rgba(150, 75, 0, 0.3);"></i>
              <h3 class="mt-3 mb-2">Your cart is empty</h3>
              <p class="text-muted mb-4">Add some products to get started!</p>
              <a href="Dashboard.php" class="btn bh-btn bh-btn-primary">
                <i class="bi bi-shop me-2"></i>Start Shopping
              </a>
            </div>
          <?php else: ?>
            <div class="row g-4">
              <div class="col-12 col-lg-8">
                <div class="bh-section-card">
                  <div class="cart-items">
                    <?php foreach ($cartItems as $item): ?>
                      <div class="cart-item">
                        <div class="cart-item-image">
                          <img src="<?php echo htmlspecialchars($item['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <div class="cart-item-details">
                          <h3 class="cart-item-name"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
                          <p class="cart-item-category"><?php echo htmlspecialchars($item['category'], ENT_QUOTES, 'UTF-8'); ?></p>
                          <p class="cart-item-price">₱<?php echo number_format($item['price'], 2); ?></p>
                        </div>
                        <div class="cart-item-actions">
                          <form method="post" class="cart-quantity-form">
                            <input type="hidden" name="action" value="update_quantity">
                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                            <div class="quantity-control">
                              <button type="button" class="quantity-btn quantity-minus" data-product-id="<?php echo $item['id']; ?>">
                                <i class="bi bi-dash"></i>
                              </button>
                              <input type="number" name="quantity" class="quantity-input" value="<?php echo $item['quantity']; ?>" min="1" max="99" data-product-id="<?php echo $item['id']; ?>">
                              <button type="button" class="quantity-btn quantity-plus" data-product-id="<?php echo $item['id']; ?>">
                                <i class="bi bi-plus"></i>
                              </button>
                            </div>
                          </form>
                          <div class="cart-item-total">₱<?php echo number_format($item['total'], 2); ?></div>
                          <form method="post" class="m-0">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?php echo $item['id']; ?>">
                            <button type="submit" class="btn-remove" title="Remove item">
                              <i class="bi bi-trash3"></i>
                            </button>
                          </form>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                  <div class="cart-actions mt-3">
                    <form method="post" class="m-0">
                      <input type="hidden" name="action" value="clear_cart">
                      <button type="submit" class="btn bh-btn bh-btn-ghost btn-sm" onclick="return confirm('Clear all items from cart?');">
                        <i class="bi bi-trash3 me-1"></i>Clear Cart
                      </button>
                    </form>
                  </div>
                </div>
              </div>

              <div class="col-12 col-lg-4">
                <div class="bh-section-card cart-summary">
                  <h3 class="cart-summary-title">Order Summary</h3>
                  <div class="cart-summary-row">
                    <span>Subtotal</span>
                    <span>₱<?php echo number_format($subtotal, 2); ?></span>
                  </div>
                  <div class="cart-summary-row">
                    <span>Shipping Fee</span>
                    <span>₱<?php echo number_format($shippingFee, 2); ?></span>
                  </div>
                  <hr class="cart-summary-divider">
                  <div class="cart-summary-row cart-summary-total">
                    <span>Total</span>
                    <span>₱<?php echo number_format($total, 2); ?></span>
                  </div>
                  <button type="button" class="btn bh-btn bh-btn-primary w-100 mt-3" data-bs-toggle="modal" data-bs-target="#checkoutModal">
                    <i class="bi bi-credit-card me-2"></i>Proceed to Checkout
                  </button>
                  <a href="Dashboard.php" class="btn bh-btn bh-btn-ghost w-100 mt-2">
                    <i class="bi bi-arrow-left me-2"></i>Continue Shopping
                  </a>
                </div>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>

  <!-- Checkout Modal -->
  <div class="modal fade" id="checkoutModal" tabindex="-1" aria-labelledby="checkoutModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content checkout-modal">
        <div class="modal-header">
          <h5 class="modal-title" id="checkoutModalLabel">
            <i class="bi bi-credit-card me-2"></i>Checkout
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form method="post" action="#" id="checkoutForm">
          <div class="modal-body">
            <input type="hidden" name="action" value="checkout">
            
            <!-- Order Summary -->
            <div class="checkout-section mb-4">
              <h6 class="checkout-section-title">
                <i class="bi bi-receipt me-2"></i>Order Summary
              </h6>
              <div class="checkout-summary-box">
                <div class="checkout-summary-row">
                  <span>Items (<?php echo count($cartItems); ?>)</span>
                  <span>₱<?php echo number_format($subtotal, 2); ?></span>
                </div>
                <div class="checkout-summary-row">
                  <span>Shipping Fee</span>
                  <span>₱<?php echo number_format($shippingFee, 2); ?></span>
                </div>
                <hr class="my-2">
                <div class="checkout-summary-row checkout-total">
                  <span>Total Amount</span>
                  <span>₱<?php echo number_format($total, 2); ?></span>
                </div>
              </div>
            </div>

            <!-- Customer Information -->
            <div class="checkout-section mb-4">
              <h6 class="checkout-section-title">
                <i class="bi bi-person me-2"></i>Your Information
              </h6>
              <div class="row g-3">
                <div class="col-12">
                  <label for="checkoutName" class="form-label checkout-label">Full Name</label>
                  <input type="text" class="form-control checkout-input" id="checkoutName" name="full_name" placeholder="Juan Dela Cruz" required>
                </div>
                <div class="col-12">
                  <label for="checkoutPhone" class="form-label checkout-label">Phone Number</label>
                  <input type="tel" class="form-control checkout-input" id="checkoutPhone" name="phone" placeholder="09XX XXX XXXX" required>
                </div>
                <div class="col-12">
                  <label for="checkoutAddress" class="form-label checkout-label">Delivery Address</label>
                  <textarea class="form-control checkout-input" id="checkoutAddress" name="address" rows="2" placeholder="Complete address with street, city, and province" required></textarea>
                </div>
              </div>
            </div>

            <!-- Payment Method -->
            <div class="checkout-section">
              <h6 class="checkout-section-title">
                <i class="bi bi-wallet2 me-2"></i>Payment Method
              </h6>
              <div class="payment-methods">
                <div class="form-check payment-option">
                  <input class="form-check-input" type="radio" name="payment_method" id="paymentCOD" value="cod" checked>
                  <label class="form-check-label" for="paymentCOD">
                    <i class="bi bi-cash-coin me-2"></i>
                    <span>
                      <strong>Cash on Delivery</strong>
                      <small class="d-block text-muted">Pay when you receive</small>
                    </span>
                  </label>
                </div>
                <div class="form-check payment-option">
                  <input class="form-check-input" type="radio" name="payment_method" id="paymentOnline" value="online">
                  <label class="form-check-label" for="paymentOnline">
                    <i class="bi bi-credit-card me-2"></i>
                    <span>
                      <strong>Online Payment</strong>
                      <small class="d-block text-muted">GCash, Card, or Bank Transfer</small>
                    </span>
                  </label>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn bh-btn bh-btn-ghost" data-bs-dismiss="modal">
              <i class="bi bi-x-circle me-1"></i>Cancel
            </button>
            <button type="submit" class="btn bh-btn bh-btn-primary">
              <i class="bi bi-check-circle me-1"></i>Place Order - ₱<?php echo number_format($total, 2); ?>
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <footer class="bh-footer-bar px-4 px-lg-5 py-4 mt-5">
    <div class="container-fluid bh-footer-bar-container">
      <div class="bh-footer-bar-left">
        <div class="bh-footer-bar-logo-box">
          <img src="../Assets/Brew_Hub.png" alt="Brewhub Logo" class="bh-footer-bar-logo">
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
        <a class="bh-footer-bar-link" href="Dashboard.php">Home</a>
        <a class="bh-footer-bar-link" href="Orders.php">Orders</a>
        <a class="bh-footer-bar-link" href="CoffeeIngredients.php">Coffee &amp; Ingredients</a>
        <a class="bh-footer-bar-link" href="CupsPackaging.php">Cups &amp; Packaging</a>
        <a class="bh-footer-bar-link" href="Equipments.php">Equipments</a>
        <a class="bh-footer-bar-link" href="Pastry.php">Pastry</a>
      </nav>
    </div>
  </footer>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
  <script src="cart.js"></script>
</body>
</html>

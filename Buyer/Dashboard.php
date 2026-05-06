<?php
declare(strict_types=1);

session_start();
require '../config.php';
require '../includes/db_helpers.php';

bh_require_login('../Login.php');

$userId = bh_current_user_id();
$flash = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $listingId = (int) ($_POST['listing_id'] ?? 0);

    if ($listingId > 0 && ($action === 'add_to_cart' || $action === 'buy_now')) {
        $ok = bh_add_to_cart($conn, $userId, $listingId);
        if ($ok && $action === 'buy_now') {
            header('Location: Cart.php');
            exit;
        }
        $flash = $ok ? 'Added to cart.' : 'Unable to add this product. It may be out of stock.';
    }
}

$cartCount = bh_cart_count($conn, $userId);
$coffeeProducts = bh_fetch_listings($conn, 'coffee');
$cupsProducts = bh_fetch_listings($conn, 'cups');
$equipmentProducts = bh_fetch_listings($conn, 'equipment');
$pastryProducts = bh_fetch_listings($conn, 'pastry');

$coffeePreview = array_slice($coffeeProducts, 0, 4);
$cupsPreview = array_slice($cupsProducts, 0, 4);
$equipmentPreview = array_slice($equipmentProducts, 0, 4);
$pastryPreview = array_slice($pastryProducts, 0, 4);

function renderProductRow(array $products, string $shopUrl, string $shopLabel, string $shopClass): void {
    if (empty($products)) return;
    ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-5 g-3 mb-3">
        <?php foreach ($products as $p):
            $listingId = (int) ($p['listing_id'] ?? 0);
            $name = (string) ($p['product_name'] ?? '');
            $category = strtoupper((string) ($p['category'] ?? ''));
            $price = (float) ($p['price'] ?? 0);
            $stock = (int) ($p['stock'] ?? 0);
            $description = (string) ($p['description'] ?? '');
            $image = bh_buyer_image_path((string) ($p['image_path'] ?? ''));
            $shopNameDb = trim((string) ($p['shop_name'] ?? ''));
            $sellerName = trim((string) ($p['seller_name'] ?? ''));
            $sellerUsername = trim((string) ($p['seller_username'] ?? ''));
            $shopName = $shopNameDb !== '' ? $shopNameDb : ($sellerName !== '' ? $sellerName : ($sellerUsername !== '' ? $sellerUsername . "'s Shop" : 'Unknown Shop'));
            $shopSellerId = (int) ($p['user_id'] ?? 0);
        ?>
        <div class="col">
            <div class="bh-product-card h-100 js-product-preview"
                role="button" tabindex="0"
                data-product-id="<?php echo $listingId; ?>"
                data-listing-id="<?php echo $listingId; ?>"
                data-seller-id="<?php echo $shopSellerId; ?>"
                data-name="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>"
                data-category="<?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?>"
                data-price="PHP <?php echo number_format($price, 2); ?>"
                data-image="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>"
                data-description="<?php echo htmlspecialchars($description, ENT_QUOTES, 'UTF-8'); ?>"
                data-stock="<?php echo $stock; ?>"
                data-shop="<?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?>">
                <div class="bh-product-media">
                    <img class="bh-product-img" src="<?php echo htmlspecialchars($image, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="bh-product-body">
                    <div class="bh-product-top">
                        <h3 class="bh-product-title"><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <div class="bh-product-price">PHP <?php echo number_format($price, 2); ?></div>
                    </div>
                    <div class="bh-product-seller">
                        <?php if ($shopSellerId > 0): ?>
                            <a href="ShopPage.php?seller_id=<?php echo $shopSellerId; ?>" class="bh-shop-link" onclick="event.stopPropagation();">
                                <i class="bi bi-shop"></i>
                                <span><?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?></span>
                            </a>
                        <?php else: ?>
                            <i class="bi bi-shop"></i>
                            <?php echo htmlspecialchars($shopName, ENT_QUOTES, 'UTF-8'); ?>
                        <?php endif; ?>
                    </div>
                    <div class="bh-product-meta">
                        <span class="bh-product-badge"><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></span>
                        <span class="bh-product-stock text-muted small">Stock: <?php echo $stock; ?></span>
                    </div>
                    <div class="bh-product-actions">
                        <form method="post" class="m-0">
                            <input type="hidden" name="action" value="add_to_cart">
                            <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
                            <button type="submit" class="btn bh-btn bh-btn-primary btn-sm"><i class="bi bi-bag-plus me-1"></i>Add to cart</button>
                        </form>
                        <form method="post" class="m-0">
                            <input type="hidden" name="action" value="buy_now">
                            <input type="hidden" name="listing_id" value="<?php echo $listingId; ?>">
                            <button type="submit" class="btn bh-btn bh-btn-ghost btn-sm"><i class="bi bi-lightning-charge me-1"></i>Buy now</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <div class="col">
            <a href="<?php echo htmlspecialchars($shopUrl, ENT_QUOTES, 'UTF-8'); ?>" class="bh-shop-card <?php echo $shopClass; ?> h-100 text-decoration-none">
                <span class="bh-shop-card-content">
                    <span class="bh-shop-card-title"><?php echo $shopLabel; ?></span>
                    <span class="bh-shop-card-btn">Shop</span>
                </span>
            </a>
        </div>
    </div>
    <?php
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Dashboard - Brewhub</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="../Style.css?v=20260423" rel="stylesheet">
</head>
<body class="dashboard-page">
    <nav class="navbar navbar-expand-md navbar-light fixed-top bh-navbar">
        <div class="container-fluid px-4 px-lg-5 bh-nav-container">
            <a class="navbar-brand bh-brand" href="Dashboard.php">Brewhub</a>
            <div class="d-flex align-items-center gap-2 order-md-3 bh-nav-actions">
                <a class="btn bh-icon-btn" href="Profile.php" aria-label="Profile"><i class="bi bi-person"></i></a>
                <a class="btn bh-icon-btn position-relative" href="Cart.php" aria-label="Cart">
                    <i class="bi bi-bag"></i>
                    <span class="bh-cart-count"><?php echo (int) $cartCount; ?></span>
                </a>
                <button class="navbar-toggler border-0 shadow-none p-0 ms-1" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <span class="navbar-toggler-icon"></span>
                </button>
            </div>
            <div class="collapse navbar-collapse justify-content-center order-md-2" id="navbarNav">
                <ul class="navbar-nav align-items-md-center gap-md-4 gap-lg-5 bh-nav-links">
                    <li class="nav-item"><a class="nav-link active" href="Dashboard.php">Home</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="productCategoriesDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">Product Categories</a>
                        <ul class="dropdown-menu" aria-labelledby="productCategoriesDropdown">
                            <li><a class="dropdown-item" href="CoffeeIngredients.php">Coffee &amp; Ingredients</a></li>
                            <li><a class="dropdown-item" href="CupsPackaging.php">Cups &amp; Packaging</a></li>
                            <li><a class="dropdown-item" href="Equipments.php">Equipments</a></li>
                            <li><a class="dropdown-item" href="Pastry.php">Pastry</a></li>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="Orders.php">Orders</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="dashboard-main">
        <section class="dashboard-hero">
            <div class="dashboard-hero-overlay"></div>
            <div class="container-fluid px-5 py-5 position-relative">
                <div class="dashboard-hero-content">
                    <p class="hero-kicker mb-2">Brewhub Marketplace</p>
                    <h1 class="display-5 fw-semibold mb-3">Everything your coffee shop needs, all in one hub.</h1>
                    <p class="lead mb-4">Discover premium beans, cups, milks, and equipment curated for busy cafes and small businesses.</p>
                    <div class="d-flex flex-wrap gap-3">
                        <a class="btn btn-light btn-lg px-4" href="CoffeeIngredients.php">Shop Supplies</a>
                        <a class="btn btn-outline-light btn-lg px-4" href="Orders.php">View Orders</a>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <h2 class="section-divider-title">Explore our Website</h2>
    <div class="container-fluid px-5 pb-5 mt-4">
        <div class="row g-3">
            <div class="col-12 col-sm-6 col-lg-3"><a href="CoffeeIngredients.php" class="p-4 rounded-3 shadow-sm h-100 text-center text-white d-flex align-items-end justify-content-center coffee-ingredients-card category-tile text-decoration-none"><span class="category-title">Coffee &amp; Ingredients</span></a></div>
            <div class="col-12 col-sm-6 col-lg-3"><a href="CupsPackaging.php" class="p-4 rounded-3 shadow-sm h-100 text-center text-white d-flex align-items-end justify-content-center cups-card category-tile text-decoration-none"><span class="category-title">Cups &amp; Packaging</span></a></div>
            <div class="col-12 col-sm-6 col-lg-3"><a href="Equipments.php" class="p-4 rounded-3 shadow-sm h-100 text-center text-white d-flex align-items-end justify-content-center equipments-card category-tile text-decoration-none"><span class="category-title">Equipments</span></a></div>
            <div class="col-12 col-sm-6 col-lg-3"><a href="Pastry.php" class="p-4 rounded-3 shadow-sm h-100 text-center text-white d-flex align-items-end justify-content-center pastries-card category-tile text-decoration-none"><span class="category-title">Pastry</span></a></div>
        </div>
    </div>

    <h3 class="section-divider-title text-center mt-5">Products</h3>
    <div class="container-fluid px-5 pb-5 mt-4 products-grid">
        <?php if ($flash): ?>
            <div class="alert alert-warning border-0" role="alert"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php endif; ?>

        <?php if (empty($coffeeProducts) && empty($cupsProducts) && empty($equipmentProducts) && empty($pastryProducts)): ?>
            <p class="text-muted text-center py-5">No products available yet.</p>
        <?php else: ?>
            <?php renderProductRow($coffeePreview, 'CoffeeIngredients.php', "Shop Coffee's", 'bh-shop-card--coffee'); ?>
            <?php renderProductRow($cupsPreview, 'CupsPackaging.php', "Shop Cups &amp; Packaging", 'bh-shop-card--cups'); ?>
            <?php renderProductRow($equipmentPreview, 'Equipments.php', 'Shop Equipments', 'bh-shop-card--equip'); ?>
            <?php renderProductRow($pastryPreview, 'Pastry.php', 'Shop Pastry', 'bh-shop-card--pastry'); ?>
        <?php endif; ?>
    </div>

    <footer class="bh-footer-bar px-4 px-lg-5 py-4 mt-5">
        <div class="container-fluid bh-footer-bar-container">
            <div class="bh-footer-bar-left">
                <div class="bh-footer-bar-logo-box"><img src="../Assets/Brew_Hub.png" alt="Brewhub Logo" class="bh-footer-bar-logo"></div>
                <div class="bh-footer-bar-meta">
                    <div class="bh-footer-bar-copy">&copy; 2026 Brewhub</div>
                    <div class="bh-footer-bar-legal">
                        <a class="bh-footer-bar-legal-link" href="#">Terms</a>
                        <a class="bh-footer-bar-legal-link" href="#">Privacy</a>
                        <a class="bh-footer-bar-legal-link" href="#">Cookies</a>
                    </div>
                </div>
            </div>
            <nav class="bh-footer-bar-nav">
                <a class="bh-footer-bar-link" href="Dashboard.php">Home</a>
                <a class="bh-footer-bar-link" href="Orders.php">Orders</a>
                <a class="bh-footer-bar-link" href="CoffeeIngredients.php">Coffee &amp; Ingredients</a>
                <a class="bh-footer-bar-link" href="CupsPackaging.php">Cups &amp; Packaging</a>
                <a class="bh-footer-bar-link" href="Equipments.php">Equipments</a>
                <a class="bh-footer-bar-link" href="Pastry.php">Pastry</a>
            </nav>
        </div>
    </footer>

        <div class="bh-preview-backdrop" id="bhProductPreview" hidden>
            <div class="bh-preview-dialog" role="dialog" aria-modal="true" aria-labelledby="bhPreviewTitle">
                <div class="bh-product-card bh-preview-card">
                    <button type="button" class="bh-preview-close" id="bhPreviewClose" aria-label="Close preview"><i class="bi bi-x-lg"></i></button>
                    <div class="bh-product-media"><img class="bh-product-img" id="bhPreviewImage" src="" alt=""></div>
                    <div class="bh-product-body">
                        <div class="bh-product-top">
                            <h3 class="bh-product-title" id="bhPreviewTitle"></h3>
                            <div class="bh-product-price" id="bhPreviewPrice"></div>
                        </div>
                        <div class="bh-product-seller" id="bhPreviewShop" hidden>
                            <a class="bh-shop-link" id="bhPreviewShopLink" href="#">
                                <i class="bi bi-shop"></i>
                                <span id="bhPreviewShopName"></span>
                            </a>
                        </div>
                        <div class="bh-product-meta"><span class="bh-product-badge" id="bhPreviewCategory"></span></div>
                        <form class="bh-preview-form" onsubmit="return false;">
                            <label class="bh-preview-label" for="bhPreviewDescription">Description</label>
                            <textarea class="form-control bh-preview-description" id="bhPreviewDescription" rows="4" readonly></textarea>
                        </form>
                        <div class="bh-product-actions bh-preview-actions">
                            <form method="post" class="m-0">
                                <input type="hidden" name="action" value="add_to_cart">
                                <input type="hidden" name="listing_id" id="bhPreviewAddProductId" value="">
                                <button type="submit" class="btn bh-btn bh-btn-primary btn-sm"><i class="bi bi-bag-plus me-1"></i>Add to cart</button>
                            </form>
                            <form method="post" class="m-0">
                                <input type="hidden" name="action" value="buy_now">
                                <input type="hidden" name="listing_id" id="bhPreviewBuyProductId" value="">
                                <button type="submit" class="btn bh-btn bh-btn-ghost btn-sm"><i class="bi bi-lightning-charge me-1"></i>Buy now</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script src="product-preview.js?v=20260506"></script>
</body>
</html>

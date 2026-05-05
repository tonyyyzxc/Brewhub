<?php
declare(strict_types=1);

function bh_require_login(string $redirect = '../Login.php'): void
{
	if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
		header('Location: ' . $redirect);
		exit;
	}
}

function bh_require_role(array $roles, string $redirect = '../Login.php'): void
{
	bh_require_login($redirect);
	$role = strtolower((string) ($_SESSION['role'] ?? ''));
	if ($role === 'both') {
		$role = 'seller';
		$_SESSION['role'] = 'seller';
	}
	$normalizedRoles = array_map(static fn($r): string => strtolower((string) $r), $roles);
	if (!in_array($role, $normalizedRoles, true)) {
		global $conn;

		$userId = bh_current_user_id();
		if ($userId > 0 && $conn instanceof mysqli) {
			$stmt = $conn->prepare("SELECT role FROM users WHERE user_id = ?");
			$stmt->bind_param('i', $userId);
			$stmt->execute();
			$freshRole = $stmt->get_result()->fetch_assoc()['role'] ?? null;
			$stmt->close();
			if ($freshRole !== null && $freshRole !== '') {
				$_SESSION['role'] = (string) $freshRole;
			}
		}

		$role = (string) ($_SESSION['role'] ?? '');
		if (!in_array($role, $roles, true)) {
			header('Location: ' . $redirect);
			exit;
		}
	}
}

function bh_current_user_id(): int
{
	return (int) ($_SESSION['user_id'] ?? 0);
}

function bh_buyer_image_path(?string $imagePath): string
{
	$imagePath = trim((string) $imagePath);
	if ($imagePath === '') {
		return '../Assets/Carousel.png';
	}
	if (preg_match('~^https?://~i', $imagePath) || strncmp($imagePath, '//', 2) === 0) {
		return $imagePath;
	}
	if (strncmp($imagePath, '../', 3) === 0 || strncmp($imagePath, '/', 1) === 0) {
		return $imagePath;
	}
	return '../' . ltrim($imagePath, './');
}

function bh_seller_image_path(?string $imagePath): string
{
	$imagePath = trim((string) $imagePath);
	if ($imagePath === '') {
		return '';
	}
	if (preg_match('~^https?://~i', $imagePath) || strncmp($imagePath, '//', 2) === 0) {
		return $imagePath;
	}
	if (strncmp($imagePath, '../', 3) === 0 || strncmp($imagePath, '/', 1) === 0) {
		return $imagePath;
	}
	return '../' . ltrim($imagePath, './');
}

function bh_category_matches(string $category, string $group): bool
{
	$category = strtolower(trim($category));
	if ($category === '') {
		return $group === 'coffee';
	}

	return match ($group) {
		'coffee'    => str_contains($category, 'coffee') || str_contains($category, 'ingredient') || str_contains($category, 'bean'),
		'cups'      => str_contains($category, 'cup') || str_contains($category, 'pack'),
		'equipment' => str_contains($category, 'equip') || str_contains($category, 'machine') || str_contains($category, 'grinder'),
		'pastry'    => str_contains($category, 'pastry') || str_contains($category, 'bake'),
		default     => false,
	};
}

function bh_fetch_listings(mysqli $conn, ?string $categoryGroup = null, ?int $sellerId = null): array
{
	$sql = "
		SELECT
			l.listing_id,
			l.user_id,
			l.product_id,
			l.price,
			l.stock,
			l.created_at,
			p.product_name,
			p.category,
			p.description,
			p.image_path,
			u.username AS seller_username,
			CONCAT(COALESCE(u.FirstName, ''), ' ', COALESCE(u.LastName, '')) AS seller_name
		FROM listings l
		JOIN products p ON l.product_id = p.product_id
		JOIN users u ON l.user_id = u.user_id
		WHERE l.stock > 0
	";

	$params = [];
	$types = '';
	if ($sellerId !== null) {
		$sql .= " AND l.user_id = ?";
		$params[] = $sellerId;
		$types .= 'i';
	}
	$sql .= " ORDER BY l.listing_id DESC";

	$stmt = $conn->prepare($sql);
	if ($types !== '') {
		$stmt->bind_param($types, ...$params);
	}
	$stmt->execute();
	$result = $stmt->get_result();

	$listings = [];
	while ($row = $result->fetch_assoc()) {
		if ($categoryGroup !== null && !bh_category_matches((string) ($row['category'] ?? ''), $categoryGroup)) {
			continue;
		}
		$listings[] = $row;
	}
	$stmt->close();

	return $listings;
}

function bh_cart_count(mysqli $conn, int $buyerId): int
{
	if ($buyerId <= 0) {
		return 0;
	}
	$stmt = $conn->prepare("SELECT COALESCE(SUM(quantity), 0) AS cnt FROM cart_items WHERE buyer_id = ?");
	$stmt->bind_param('i', $buyerId);
	$stmt->execute();
	$count = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0);
	$stmt->close();
	return $count;
}

function bh_add_to_cart(mysqli $conn, int $buyerId, int $listingId, int $quantity = 1): bool
{
	if ($buyerId <= 0 || $listingId <= 0 || $quantity <= 0) {
		return false;
	}

	$stmt = $conn->prepare("
		INSERT INTO cart_items (buyer_id, listing_id, quantity)
		SELECT ?, ?, ?
		FROM listings
		WHERE listing_id = ? AND stock > 0
		ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)
	");
	$stmt->bind_param('iiii', $buyerId, $listingId, $quantity, $listingId);
	$ok = $stmt->execute() && $stmt->affected_rows > 0;
	$stmt->close();
	return $ok;
}

function bh_fetch_cart_items(mysqli $conn, int $buyerId): array
{
	$stmt = $conn->prepare("
		SELECT
			ci.cart_item_id,
			ci.listing_id,
			ci.quantity,
			l.price,
			l.stock,
			p.product_name,
			p.category,
			p.image_path
		FROM cart_items ci
		JOIN listings l ON ci.listing_id = l.listing_id
		JOIN products p ON l.product_id = p.product_id
		WHERE ci.buyer_id = ?
		ORDER BY ci.cart_item_id DESC
	");
	$stmt->bind_param('i', $buyerId);
	$stmt->execute();
	$result = $stmt->get_result();

	$items = [];
	while ($row = $result->fetch_assoc()) {
		$quantity = max(1, min((int) $row['quantity'], (int) $row['stock']));
		$price = (float) $row['price'];
		$items[] = [
			'id'         => (int) $row['listing_id'],
			'listing_id' => (int) $row['listing_id'],
			'name'       => (string) $row['product_name'],
			'category'   => (string) $row['category'],
			'price'      => $price,
			'image'      => bh_buyer_image_path((string) ($row['image_path'] ?? '')),
			'quantity'   => $quantity,
			'stock'      => (int) $row['stock'],
			'total'      => $price * $quantity,
		];
	}
	$stmt->close();

	return $items;
}

function bh_ensure_checkout_columns(mysqli $conn): void
{
	$columns = [
		'customer_name'    => "ADD COLUMN customer_name VARCHAR(150) NULL AFTER buyer_id",
		'customer_phone'   => "ADD COLUMN customer_phone VARCHAR(30) NULL AFTER customer_name",
		'customer_address' => "ADD COLUMN customer_address TEXT NULL AFTER customer_phone",
		'payment_method'   => "ADD COLUMN payment_method ENUM('cod','online') NULL AFTER customer_address",
	];

	foreach ($columns as $column => $alterSql) {
		$stmt = $conn->prepare("
			SELECT COUNT(*) AS cnt
			FROM information_schema.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = 'orders'
				AND COLUMN_NAME = ?
		");
		$stmt->bind_param('s', $column);
		$stmt->execute();
		$exists = (int) ($stmt->get_result()->fetch_assoc()['cnt'] ?? 0) > 0;
		$stmt->close();

		if (!$exists) {
			$conn->query("ALTER TABLE orders $alterSql");
		}
	}
}

function bh_fetch_checkout_profile(mysqli $conn, int $buyerId): array
{
	bh_ensure_checkout_columns($conn);

	$stmt = $conn->prepare("
		SELECT
			TRIM(CONCAT(COALESCE(u.FirstName, ''), ' ', COALESCE(u.LastName, ''))) AS account_name,
			o.customer_name,
			o.customer_phone,
			o.customer_address,
			o.payment_method
		FROM users u
		LEFT JOIN orders o
			ON o.order_id = (
				SELECT o2.order_id
				FROM orders o2
				WHERE o2.buyer_id = u.user_id
					AND (
						COALESCE(o2.customer_phone, '') <> ''
						OR COALESCE(o2.customer_address, '') <> ''
						OR COALESCE(o2.customer_name, '') <> ''
					)
				ORDER BY o2.order_date DESC, o2.order_id DESC
				LIMIT 1
			)
		WHERE u.user_id = ?
	");
	$stmt->bind_param('i', $buyerId);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc() ?: [];
	$stmt->close();

	$accountName  = trim((string) ($row['account_name'] ?? ''));
	$customerName = trim((string) ($row['customer_name'] ?? ''));
	return [
		'full_name'      => $customerName !== '' ? $customerName : $accountName,
		'phone'          => (string) ($row['customer_phone'] ?? ''),
		'address'        => (string) ($row['customer_address'] ?? ''),
		'payment_method' => in_array((string) ($row['payment_method'] ?? ''), ['cod', 'online'], true)
			? (string) $row['payment_method']
			: 'cod',
	];
}

function bh_create_order_from_cart(mysqli $conn, int $buyerId, float $shippingFee, array $checkoutDetails = []): int
{
	$cartItems = bh_fetch_cart_items($conn, $buyerId);
	if ($buyerId <= 0 || empty($cartItems)) {
		return 0;
	}

	$subtotal       = array_sum(array_map(static fn(array $item): float => (float) $item['total'], $cartItems));
	$total          = $subtotal + $shippingFee;
	$customerName   = trim((string) ($checkoutDetails['full_name'] ?? ''));
	$customerPhone  = trim((string) ($checkoutDetails['phone'] ?? ''));
	$customerAddress = trim((string) ($checkoutDetails['address'] ?? ''));
	$paymentMethod  = strtolower(trim((string) ($checkoutDetails['payment_method'] ?? 'cod')));
	if (!in_array($paymentMethod, ['cod', 'online'], true)) {
		$paymentMethod = 'cod';
	}

	bh_ensure_checkout_columns($conn);
	$conn->begin_transaction();
	try {
		$stmt = $conn->prepare("
			INSERT INTO orders (buyer_id, customer_name, customer_phone, customer_address, payment_method, total_amount, status)
			VALUES (?, ?, ?, ?, ?, ?, 'pending')
		");
		$stmt->bind_param('issssd', $buyerId, $customerName, $customerPhone, $customerAddress, $paymentMethod, $total);
		$stmt->execute();
		$orderId = (int) $conn->insert_id;
		$stmt->close();

		$itemStmt  = $conn->prepare("INSERT INTO order_items (order_id, listing_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
		$stockStmt = $conn->prepare("UPDATE listings SET stock = GREATEST(stock - ?, 0) WHERE listing_id = ?");
		foreach ($cartItems as $item) {
			$listingId    = (int) $item['listing_id'];
			$quantity     = (int) $item['quantity'];
			$itemSubtotal = (float) $item['total'];
			$itemStmt->bind_param('iiid', $orderId, $listingId, $quantity, $itemSubtotal);
			$itemStmt->execute();
			$stockStmt->bind_param('ii', $quantity, $listingId);
			$stockStmt->execute();
		}
		$itemStmt->close();
		$stockStmt->close();

		$clearStmt = $conn->prepare("DELETE FROM cart_items WHERE buyer_id = ?");
		$clearStmt->bind_param('i', $buyerId);
		$clearStmt->execute();
		$clearStmt->close();

		$conn->commit();
		return $orderId;
	} catch (Throwable $e) {
		$conn->rollback();
		return 0;
	}
}

function bh_fetch_seller_profile(mysqli $conn, int $sellerId): array
{
	$stmt = $conn->prepare("
		SELECT
			COALESCE(NULLIF(sp.shop_name, ''), sr.shop_name) AS shop_name,
			COALESCE(NULLIF(sp.contact, ''), sr.contact) AS contact,
			COALESCE(NULLIF(sp.seller_type, ''), sr.seller_type) AS seller_type,
			COALESCE(NULLIF(sp.description, ''), sr.description) AS description,
			COALESCE(NULLIF(sp.address, ''), sr.address) AS address,
			u.username,
			COALESCE(NULLIF(u.FirstName, ''), sr.first_name) AS FirstName,
			COALESCE(NULLIF(u.LastName, ''), sr.last_name) AS LastName,
			COALESCE(NULLIF(u.email, ''), sr.email) AS email
		FROM users u
		LEFT JOIN seller_profiles sp ON sp.user_id = u.user_id
		LEFT JOIN seller_requests sr
			ON sr.user_id = u.user_id
			AND sr.status = 'approved'
			AND sr.request_id = (
				SELECT MAX(sr2.request_id)
				FROM seller_requests sr2
				WHERE sr2.user_id = u.user_id
					AND sr2.status = 'approved'
			)
		WHERE u.user_id = ?
	");
	$stmt->bind_param('i', $sellerId);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc() ?: [];
	$stmt->close();

	$username = trim((string) ($row['username'] ?? 'Seller'));
	return [
		'first_name'  => (string) ($row['FirstName'] ?? ''),
		'last_name'   => (string) ($row['LastName'] ?? ''),
		'email'       => (string) ($row['email'] ?? ''),
		'username'    => (string) ($row['username'] ?? ''),
		'shop_name'   => trim((string) ($row['shop_name'] ?? '')) ?: $username . "'s Shop",
		'contact'     => (string) ($row['contact'] ?? ''),
		'seller_type' => (string) ($row['seller_type'] ?? ''),
		'description' => (string) ($row['description'] ?? ''),
		'address'     => (string) ($row['address'] ?? ''),
	];
}

function bh_save_seller_profile(mysqli $conn, int $sellerId, array $profile): bool
{
	$stmt = $conn->prepare("
		INSERT INTO seller_profiles (user_id, shop_name, contact, seller_type, description, address)
		VALUES (?, ?, ?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE
			shop_name   = VALUES(shop_name),
			contact     = VALUES(contact),
			seller_type = VALUES(seller_type),
			description = VALUES(description),
			address     = VALUES(address)
	");
	$shopName    = trim((string) ($profile['shop_name'] ?? ''));
	$contact     = trim((string) ($profile['contact'] ?? ''));
	$sellerType  = trim((string) ($profile['seller_type'] ?? ''));
	$description = trim((string) ($profile['description'] ?? ''));
	$address     = trim((string) ($profile['address'] ?? ''));
	$stmt->bind_param('isssss', $sellerId, $shopName, $contact, $sellerType, $description, $address);
	$ok = $stmt->execute();
	$stmt->close();
	return $ok;
}

function bh_save_seller_account(mysqli $conn, int $sellerId, array $account): bool
{
	$stmt = $conn->prepare("
		UPDATE users
		SET FirstName = ?, LastName = ?, username = ?, email = ?
		WHERE user_id = ?
	");
	$firstName = trim((string) ($account['first_name'] ?? ''));
	$lastName  = trim((string) ($account['last_name'] ?? ''));
	$username  = trim((string) ($account['username'] ?? ''));
	$email     = trim((string) ($account['email'] ?? ''));
	$stmt->bind_param('ssssi', $firstName, $lastName, $username, $email, $sellerId);
	$ok = $stmt->execute();
	$stmt->close();
	return $ok;
}
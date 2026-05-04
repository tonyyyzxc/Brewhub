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
		header('Location: ' . $redirect);
		exit;
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
		'coffee' => str_contains($category, 'coffee') || str_contains($category, 'ingredient') || str_contains($category, 'bean'),
		'cups' => str_contains($category, 'cup') || str_contains($category, 'pack'),
		'equipment' => str_contains($category, 'equip') || str_contains($category, 'machine') || str_contains($category, 'grinder'),
		'pastry' => str_contains($category, 'pastry') || str_contains($category, 'bake'),
		default => false,
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
			'id' => (int) $row['listing_id'],
			'listing_id' => (int) $row['listing_id'],
			'name' => (string) $row['product_name'],
			'category' => (string) $row['category'],
			'price' => $price,
			'image' => bh_buyer_image_path((string) ($row['image_path'] ?? '')),
			'quantity' => $quantity,
			'stock' => (int) $row['stock'],
			'total' => $price * $quantity,
		];
	}
	$stmt->close();

	return $items;
}

function bh_create_order_from_cart(mysqli $conn, int $buyerId, float $shippingFee): int
{
	$cartItems = bh_fetch_cart_items($conn, $buyerId);
	if ($buyerId <= 0 || empty($cartItems)) {
		return 0;
	}

	$subtotal = array_sum(array_map(static fn(array $item): float => (float) $item['total'], $cartItems));
	$total = $subtotal + $shippingFee;

	$conn->begin_transaction();
	try {
		$stmt = $conn->prepare("INSERT INTO orders (buyer_id, total_amount, status) VALUES (?, ?, 'pending')");
		$stmt->bind_param('id', $buyerId, $total);
		$stmt->execute();
		$orderId = (int) $conn->insert_id;
		$stmt->close();

		$itemStmt = $conn->prepare("INSERT INTO order_items (order_id, listing_id, quantity, subtotal) VALUES (?, ?, ?, ?)");
		$stockStmt = $conn->prepare("UPDATE listings SET stock = GREATEST(stock - ?, 0) WHERE listing_id = ?");
		foreach ($cartItems as $item) {
			$listingId = (int) $item['listing_id'];
			$quantity = (int) $item['quantity'];
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
			sp.shop_name,
			sp.contact,
			sp.seller_type,
			sp.description,
			sp.address,
			u.username
		FROM users u
		LEFT JOIN seller_profiles sp ON sp.user_id = u.user_id
		WHERE u.user_id = ?
	");
	$stmt->bind_param('i', $sellerId);
	$stmt->execute();
	$row = $stmt->get_result()->fetch_assoc() ?: [];
	$stmt->close();

	$username = trim((string) ($row['username'] ?? 'Seller'));
	return [
		'shop_name' => trim((string) ($row['shop_name'] ?? '')) ?: $username . "'s Shop",
		'contact' => (string) ($row['contact'] ?? ''),
		'seller_type' => (string) ($row['seller_type'] ?? ''),
		'description' => (string) ($row['description'] ?? ''),
		'address' => (string) ($row['address'] ?? ''),
	];
}

function bh_save_seller_profile(mysqli $conn, int $sellerId, array $profile): bool
{
	$stmt = $conn->prepare("
		INSERT INTO seller_profiles (user_id, shop_name, contact, seller_type, description, address)
		VALUES (?, ?, ?, ?, ?, ?)
		ON DUPLICATE KEY UPDATE
			shop_name = VALUES(shop_name),
			contact = VALUES(contact),
			seller_type = VALUES(seller_type),
			description = VALUES(description),
			address = VALUES(address)
	");
	$shopName = trim((string) ($profile['shop_name'] ?? ''));
	$contact = trim((string) ($profile['contact'] ?? ''));
	$sellerType = trim((string) ($profile['seller_type'] ?? ''));
	$description = trim((string) ($profile['description'] ?? ''));
	$address = trim((string) ($profile['address'] ?? ''));
	$stmt->bind_param('isssss', $sellerId, $shopName, $contact, $sellerType, $description, $address);
	$ok = $stmt->execute();
	$stmt->close();
	return $ok;
}

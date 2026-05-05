<?php
declare(strict_types=1);

function bh_role_redirect(?string $role): string
{
	$role = $role ?: 'buyer';
	if ($role === 'admin') {
		return 'admin/admin.php';
	}
	if (in_array($role, ['seller', 'both'], true)) {
		return 'Seller/SellerDashboard.php';
	}
	return 'Buyer/Dashboard.php';
}

function bh_set_login_session(array $user): void
{
	$role = (string) ($user['role'] ?? 'buyer');
	if ($role === '') {
		$role = 'buyer';
	}

	$_SESSION['loggedin'] = true;
	$_SESSION['user_id'] = (int) $user['user_id'];
	$_SESSION['email'] = (string) ($user['email'] ?? '');
	$_SESSION['FirstName'] = (string) ($user['FirstName'] ?? '');
	$_SESSION['LastName'] = (string) ($user['LastName'] ?? '');
	$_SESSION['username'] = (string) ($user['username'] ?? '');
	$_SESSION['role'] = $role;
}

function bh_recaptcha_is_configured(): bool
{
	return defined('RECAPTCHA_SECRET_KEY') && trim((string) RECAPTCHA_SECRET_KEY) !== '';
}

function bh_verify_recaptcha(?string $token): bool
{
	if (!bh_recaptcha_is_configured()) {
		return true;
	}
	if ($token === null || trim($token) === '') {
		return false;
	}

	$postData = http_build_query([
		'secret' => RECAPTCHA_SECRET_KEY,
		'response' => $token,
		'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
	]);

	$response = bh_http_post('https://www.google.com/recaptcha/api/siteverify', $postData, [
		'Content-Type: application/x-www-form-urlencoded',
	]);
	if ($response === null) {
		return false;
	}

	$result = json_decode($response, true);
	return is_array($result) && ($result['success'] ?? false) === true;
}

function bh_http_post(string $url, string $postData, array $headers = []): ?string
{
	if (function_exists('curl_init')) {
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => $postData,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 12,
			CURLOPT_HTTPHEADER => $headers,
		]);
		$response = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($response !== false && $status >= 200 && $status < 300) {
			return (string) $response;
		}
		return null;
	}

	$context = stream_context_create([
		'http' => [
			'method' => 'POST',
			'header' => implode("\r\n", $headers),
			'content' => $postData,
			'timeout' => 12,
		],
	]);
	$response = @file_get_contents($url, false, $context);
	return $response === false ? null : (string) $response;
}

function bh_http_get_json(string $url, string $bearerToken): ?array
{
	$headers = ['Authorization: Bearer ' . $bearerToken];
	if (function_exists('curl_init')) {
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 12,
			CURLOPT_HTTPHEADER => $headers,
		]);
		$response = curl_exec($ch);
		$status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		if ($response === false || $status < 200 || $status >= 300) {
			return null;
		}
		$data = json_decode((string) $response, true);
		return is_array($data) ? $data : null;
	}

	$context = stream_context_create([
		'http' => [
			'method' => 'GET',
			'header' => implode("\r\n", $headers),
			'timeout' => 12,
		],
	]);
	$response = @file_get_contents($url, false, $context);
	if ($response === false) {
		return null;
	}
	$data = json_decode((string) $response, true);
	return is_array($data) ? $data : null;
}


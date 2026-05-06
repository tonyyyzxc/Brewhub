<?php

session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_helpers.php';

function bh_google_login_error(string $message): void
{
	header('Location: Login.php?error=' . urlencode($message));
	exit;
}

if (
	!defined('GOOGLE_CLIENT_ID')
	|| !defined('GOOGLE_CLIENT_SECRET')
	|| trim((string) GOOGLE_CLIENT_ID) === ''
	|| trim((string) GOOGLE_CLIENT_SECRET) === ''
) {
	bh_google_login_error('Google sign-in is not configured yet.');
}

if (isset($_GET['error'])) {
	bh_google_login_error('Google sign-in was cancelled or denied.');
}

$state = (string) ($_GET['state'] ?? '');
$expectedState = (string) ($_SESSION['google_oauth_state'] ?? '');
unset($_SESSION['google_oauth_state']);

if ($state === '' || $expectedState === '' || !hash_equals($expectedState, $state)) {
	bh_google_login_error('Google sign-in expired. Please try again.');
}

$code = (string) ($_GET['code'] ?? '');
if ($code === '') {
	bh_google_login_error('Google did not return a sign-in code.');
}

$tokenResponse = bh_http_post(
	'https://oauth2.googleapis.com/token',
	http_build_query([
		'code' => $code,
		'client_id' => GOOGLE_CLIENT_ID,
		'client_secret' => GOOGLE_CLIENT_SECRET,
		'redirect_uri' => GOOGLE_REDIRECT_URI,
		'grant_type' => 'authorization_code',
	]),
	['Content-Type: application/x-www-form-urlencoded']
);

$tokenData = json_decode($tokenResponse, true);
if (!is_array($tokenData) || empty($tokenData['access_token'])) {
	bh_google_login_error('Google sign-in did not return a valid access token.');
}

$profile = bh_http_get_json('https://www.googleapis.com/oauth2/v3/userinfo', (string) $tokenData['access_token']);
if (!is_array($profile) || empty($profile['email'])) {
	bh_google_login_error('Could not read your Google profile.');
}

$emailVerified = $profile['email_verified'] ?? false;
if ($emailVerified !== true && $emailVerified !== 'true' && $emailVerified !== 1) {
	bh_google_login_error('Your Google email must be verified before signing in.');
}

$email = strtolower(trim((string) $profile['email']));
$firstName = trim((string) ($profile['given_name'] ?? ''));
$lastName = trim((string) ($profile['family_name'] ?? ''));
$fullName = trim((string) ($profile['name'] ?? ''));
if ($firstName === '' && $fullName !== '') {
	$nameParts = preg_split('/\s+/', $fullName, 2);
	$firstName = $nameParts[0] ?? '';
	$lastName = $nameParts[1] ?? '';
}

$stmt = $conn->prepare("SELECT user_id, email, FirstName, LastName, username, role FROM users WHERE email = ?");
$stmt->bind_param('s', $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
	$usernameBase = preg_replace('/[^a-z0-9_]+/i', '', strtok($email, '@') ?: 'user');
	$username = strtolower($usernameBase ?: 'user') . random_int(1000, 9999);
	$role = 'buyer';

	$insert = $conn->prepare("
		INSERT INTO users (email, FirstName, LastName, password, username, role)
		VALUES (?, ?, ?, NULL, ?, ?)
	");
	$insert->bind_param('sssss', $email, $firstName, $lastName, $username, $role);
	if (!$insert->execute()) {
		$insert->close();
		bh_google_login_error('Could not create your Brewhub account.');
	}
	$userId = (int) $conn->insert_id;
	$insert->close();

	$user = [
		'user_id' => $userId,
		'email' => $email,
		'FirstName' => $firstName,
		'LastName' => $lastName,
		'username' => $username,
		'role' => $role,
	];
}

if (($user['role'] ?? '') === 'admin') {
	bh_google_login_error('Admin accounts must sign in with email and password.');
}

bh_set_login_session($user);
header('Location: ' . bh_role_redirect((string) ($user['role'] ?? 'buyer')));
exit;
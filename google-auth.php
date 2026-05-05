<?php

// testtt paniiiiii




session_start();
require_once __DIR__ . '/config.php';

if (
	!defined('GOOGLE_CLIENT_ID')
	|| !defined('GOOGLE_CLIENT_SECRET')
	|| trim((string) GOOGLE_CLIENT_ID) === ''
	|| trim((string) GOOGLE_CLIENT_SECRET) === ''
) {
	header('Location: Login.php?error=' . urlencode('Google sign-in is not configured yet.'));
	exit;
}

$_SESSION['google_oauth_state'] = bin2hex(random_bytes(16));

$params = [
	'client_id' => GOOGLE_CLIENT_ID,
	'redirect_uri' => GOOGLE_REDIRECT_URI,
	'response_type' => 'code',
	'scope' => 'openid email profile',
	'state' => $_SESSION['google_oauth_state'],
	'prompt' => 'select_account',
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;


<?php
/**
 * Load environment variables from the .env file in the root directory.
 */
$envPath = __DIR__ . '/../.env';

if (file_exists($envPath)) {
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue; // Skip comments

        list($name, $value) = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);

        if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
            putenv(sprintf('%s=%s', $name, $value));
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }
    }
}

// Google reCAPTCHA v2 checkbox keys
defined('RECAPTCHA_SITE_KEY')   || define('RECAPTCHA_SITE_KEY',   getenv('RECAPTCHA_SITE_KEY'));
defined('RECAPTCHA_SECRET_KEY') || define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY'));

// Google OAuth client credentials
defined('GOOGLE_CLIENT_ID')     || define('GOOGLE_CLIENT_ID',     getenv('GOOGLE_CLIENT_ID'));
defined('GOOGLE_CLIENT_SECRET') || define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET'));
defined('GOOGLE_REDIRECT_URI')  || define('GOOGLE_REDIRECT_URI',  getenv('GOOGLE_REDIRECT_URI'));
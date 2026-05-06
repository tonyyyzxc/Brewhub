<?php

$envPath = realpath(__DIR__ . '/../.env');

if ($envPath && file_exists($envPath)) {
    $env = parse_ini_file($envPath);
    
    if ($env) {
        foreach ($env as $key => $value) {
            putenv("$key=$value");
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}

// Google reCAPTCHA
defined('RECAPTCHA_SITE_KEY')   || define('RECAPTCHA_SITE_KEY',   getenv('RECAPTCHA_SITE_KEY'));
defined('RECAPTCHA_SECRET_KEY') || define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY'));

// Google OAuth
defined('GOOGLE_CLIENT_ID')     || define('GOOGLE_CLIENT_ID',     getenv('GOOGLE_CLIENT_ID'));
defined('GOOGLE_CLIENT_SECRET') || define('GOOGLE_CLIENT_SECRET', getenv('GOOGLE_CLIENT_SECRET'));
defined('GOOGLE_REDIRECT_URI')  || define('GOOGLE_REDIRECT_URI',  getenv('GOOGLE_REDIRECT_URI'));
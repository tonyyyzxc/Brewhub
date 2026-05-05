<?php

// Google reCAPTCHA v2 checkbox keys.
// Get these from https://www.google.com/recaptcha/admin and paste them here.
defined('RECAPTCHA_SITE_KEY') || define('RECAPTCHA_SITE_KEY', '');
defined('RECAPTCHA_SECRET_KEY') || define('RECAPTCHA_SECRET_KEY', '');

// Google OAuth client credentials.
// In Google Cloud Console, add this exact redirect URI to the OAuth client:
// http://localhost/Brewhub-master/google-callback.php
defined('GOOGLE_CLIENT_ID') || define('GOOGLE_CLIENT_ID', '');
defined('GOOGLE_CLIENT_SECRET') || define('GOOGLE_CLIENT_SECRET', '');
defined('GOOGLE_REDIRECT_URI') || define('GOOGLE_REDIRECT_URI', 'http://localhost/Brewhub-master/google-callback.php');

<?php
/**
 * Configuration & SSO Settings
 * Values are dynamically loaded from environment variables (.env)
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (!defined('CLIENT_ID')) {
    define('CLIENT_ID', env('SSO_CLIENT_ID', 'prasan_phithi'));
}

if (!defined('CLIENT_SECRET')) {
    define('CLIENT_SECRET', env('SSO_CLIENT_SECRET', ''));
}

if (!defined('SSO_BASE_URL')) {
    define('SSO_BASE_URL', rtrim((string)env('SSO_BASE_URL', 'https://sso.dpt.go.th/openId/public'), '/'));
}

if (!defined('REDIRECT_URL')) {
    define('REDIRECT_URL', env('SSO_REDIRECT_URL', 'https://info.dpt.go.th/prasan-phithi'));
}

if (!defined('SCOPE')) {
    define('SCOPE', env('SSO_SCOPE', 'openid profile email'));
}

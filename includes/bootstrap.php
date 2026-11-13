<?php
/**
 * Application Bootstrap & Initialization
 */

require_once __DIR__ . '/env.php';

// Default timezone for Thailand government agencies
date_default_timezone_set('Asia/Bangkok');

// Environment settings
$appEnv = env('APP_ENV', 'development');
$appDebug = env('APP_DEBUG', true);

if ($appEnv === 'production' && !$appDebug) {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    ini_set('log_errors', '1');
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
} else {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
}

// Secure session configuration
if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => (int)env('SESSION_LIFETIME', 7200),
        'path'     => '/',
        'domain'   => '',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

if (!function_exists('app_env')) {
    function app_env(): string
    {
        return (string)env('APP_ENV', 'development');
    }
}

if (!function_exists('is_production')) {
    function is_production(): bool
    {
        return app_env() === 'production';
    }
}

if (!function_exists('is_demo_mode')) {
    function is_demo_mode(): bool
    {
        // Demo mode is explicitly forbidden in production
        if (is_production()) {
            return false;
        }
        return (bool)env('DEMO_MODE', false);
    }
}


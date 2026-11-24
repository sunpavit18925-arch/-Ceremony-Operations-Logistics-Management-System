<?php
/**
 * CSRF Protection Helpers
 */

require_once __DIR__ . '/bootstrap.php';

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        $token = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }
}

if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token(?string $token = null): bool
    {
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        }

        $sessionToken = $_SESSION['csrf_token'] ?? '';
        if (empty($sessionToken) || empty($token)) {
            return false;
        }

        return hash_equals($sessionToken, (string)$token);
    }
}

if (!function_exists('require_csrf')) {
    function require_csrf(): void
    {
        if (!verify_csrf_token()) {
            http_response_code(403);
            die('<div style="text-align:center; margin-top:50px; font-family:sans-serif; color:#721c24; background:#f8d7da; padding:20px; border-radius:8px; max-width:600px; margin-left:auto; margin-right:auto;">' .
                '<h3>403 Forbidden - คำขอไม่ถูกต้องหรือหมดอายุ (CSRF Token Invalid)</h3>' .
                '<p>กรุณาย้อนกลับไปหน้ารายการ รีเฟรช แล้วลองทำรายการใหม่อีกครั้ง</p>' .
                '<p><a href="javascript:history.back()" style="color:#721c24; font-weight:bold;">กลับสู่หน้าก่อนหน้า</a></p>' .
                '</div>');
        }
    }
}


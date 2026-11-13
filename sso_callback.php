<?php
/**
 * DPT SSO Callback Handler
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/sso.php';
require_once __DIR__ . '/includes/security.php';

$code = $_GET['code'] ?? '';
$state = $_GET['state'] ?? '';

[$success, $message, $user] = sso_handle_callback($code, $state);

if (!$success) {
    http_response_code(400);
    die('<div style="text-align:center; margin-top:50px; font-family:sans-serif; color:#721c24; background:#f8d7da; padding:20px; border-radius:8px; max-width:600px; margin-left:auto; margin-right:auto;">' .
        '<h3>ข้อผิดพลาดในการเข้าสู่ระบบ SSO</h3>' .
        '<p>' . e($message) . '</p>' .
        '<p><a href="index.php" style="color:#721c24; font-weight:bold;">กลับสู่หน้าเข้าสู่ระบบ</a></p>' .
        '</div>');
}

redirect('welcome.php');

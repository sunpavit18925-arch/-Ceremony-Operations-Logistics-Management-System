<?php
/**
 * Local / Demo Authentication Handler
 * ONLY available in non-production environments with DEMO_MODE=true
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/security.php';

// Strict guard: DEMO mode must NOT be active in production
if (!is_demo_mode()) {
    http_response_code(403);
    die('<div style="text-align:center; margin-top:50px; font-family:sans-serif; color:#721c24;"><h3>403 Forbidden - Demo Mode is Disabled</h3></div>');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method Not Allowed');
}

require_csrf();

$conn = get_db_connection();
$user = null;

// Mode 1: Quick Role Login (via button click)
if (!empty($_POST['demo_role'])) {
    $role = clean_string($_POST['demo_role']);
    $stmt = $conn->prepare("SELECT * FROM users WHERE role = ? ORDER BY id ASC LIMIT 1");
    $stmt->execute([$role]);
    $user = $stmt->fetch();

    if (!$user) {
        redirect('index.php?demo_error=' . urlencode("ไม่พบบัญชีทดสอบสำหรับสิทธิ์: {$role} กรุณารัน seed ก่อน"));
    }
}
// Mode 2: Username / Password Login
elseif (!empty($_POST['username']) && !empty($_POST['password'])) {
    $username = clean_string($_POST['username']);
    $password = (string)$_POST['password'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE username_sso = ? LIMIT 1");
    $stmt->execute([$username]);
    $foundUser = $stmt->fetch();

    if ($foundUser && !empty($foundUser['password_hash']) && password_verify($password, $foundUser['password_hash'])) {
        $user = $foundUser;
    } else {
        redirect('index.php?demo_error=' . urlencode('ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง'));
    }
} else {
    redirect('index.php?demo_error=' . urlencode('กรุณาระบุข้อมูลการเข้าสู่ระบบ'));
}

// Log in user
session_regenerate_id(true);

$_SESSION['user_id']      = (int)$user['id'];
$_SESSION['role']         = $user['role'];
$_SESSION['username']     = $user['username_sso'] ?? ($user['fname'] . ' ' . $user['lname']);
$_SESSION['username_sso'] = $user['username_sso'] ?? '';
$_SESSION['user_fname']   = $user['fname'];
$_SESSION['fname']        = $user['fname'];
$_SESSION['lname']        = $user['lname'];
$_SESSION['email']        = $user['email'];
$_SESSION['mobile_phone'] = $user['mobile_phone'];
$_SESSION['cid']          = $user['cid'];
$_SESSION['depart_name']  = $user['depart_name'];
$_SESSION['depart_code']  = $user['depart_code'];
$_SESSION['last_activity']= time();

redirect('welcome.php');


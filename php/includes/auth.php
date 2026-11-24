<?php
/**
 * Authentication and Authorization Manager
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

// Valid system roles
const ROLE_SUPER_ADMIN           = 'super_admin';
const ROLE_ADMIN                 = 'admin';
const ROLE_HEAD_OPERATION        = 'head_operation';
const ROLE_COORDINATOR_OPERATION = 'coordinator_operation';
const ROLE_STAFF_OPERATION       = 'staff_operation';
const ROLE_HEAD_VEHICLE          = 'head_vehicle';
const ROLE_STAFF_VEHICLE         = 'staff_vehicle';
const ROLE_PENDING               = 'pending';

const ALL_ROLES = [
    ROLE_SUPER_ADMIN,
    ROLE_ADMIN,
    ROLE_HEAD_OPERATION,
    ROLE_COORDINATOR_OPERATION,
    ROLE_STAFF_OPERATION,
    ROLE_HEAD_VEHICLE,
    ROLE_STAFF_VEHICLE,
    ROLE_PENDING,
];

/**
 * Enforce session timeout & synchronize role status from database
 */
function auth_sync_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return;
    }

    $timeout = (int)env('SESSION_LIFETIME', 7200);

    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > $timeout) {
        auth_logout(true);
        redirect('index.php?timeout=1');
    }

    $_SESSION['last_activity'] = time();

    // If logged in, verify user still exists in DB and sync role
    if (!empty($_SESSION['user_id'])) {
        try {
            $conn = get_db_connection();
            $stmt = $conn->prepare("SELECT id, username_sso, fname, lname, email, role, depart_name, cid FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([(int)$_SESSION['user_id']]);
            $user = $stmt->fetch();

            if (!$user) {
                // Account deleted or removed
                auth_logout(false);
                redirect('index.php?error=account_not_found');
            }

            // Keep session role synchronized with database
            $_SESSION['role'] = $user['role'];
            $_SESSION['fname'] = $user['fname'];
            $_SESSION['lname'] = $user['lname'];
            $_SESSION['user_fname'] = $user['fname'];
            if (!empty($user['depart_name'])) {
                $_SESSION['depart_name'] = $user['depart_name'];
            }
        } catch (\PDOException $e) {
            error_log('Session sync db error: ' . $e->getMessage());
        }
    }
}

// Auto-run session sync on include
auth_sync_session();

function current_user_id(): ?int
{
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function current_user(): ?array
{
    $id = current_user_id();
    if (!$id) {
        return null;
    }
    try {
        $conn = get_db_connection();
        $stmt = $conn->prepare("SELECT id, username_sso, fname, lname, email, role, depart_name, cid FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    } catch (\PDOException $e) {
        return null;
    }
}

function current_user_role(): string
{
    return (string)($_SESSION['role'] ?? '');
}

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function require_login(): void
{
    if (!is_logged_in()) {
        redirect('index.php');
    }
}

function has_role(array|string $roles): bool
{
    if (!is_logged_in()) {
        return false;
    }

    $currentRole = current_user_role();

    // super_admin has overarching access to operations, vehicles, and admin views
    if ($currentRole === ROLE_SUPER_ADMIN) {
        return true;
    }

    if (is_string($roles)) {
        $roles = [$roles];
    }

    return in_array($currentRole, $roles, true);
}

function render_403_page(string $message = 'บัญชีของคุณไม่มีสิทธิ์ที่ได้รับอนุญาตสำหรับส่วนนี้ หรืออยู่นอกขอบเขตหน้าที่ที่ได้รับมอบหมาย', string $returnUrl = 'welcome.php'): void
{
    http_response_code(403);
    $user_fullname = trim(($_SESSION['user_fname'] ?? $_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? ''));
    $role = current_user_role();
    $roleName = match ($role) {
        'super_admin'           => 'ผู้ดูแลระบบสูงสุด',
        'admin'                 => 'ผู้ดูแลระบบ',
        'head_operation'        => 'หัวหน้าฝ่ายปฏิบัติการ',
        'coordinator_operation' => 'ฝ่ายปฏิบัติการ (ผู้ประสานงาน)',
        'staff_operation'       => 'เจ้าหน้าที่ฝ่ายปฏิบัติการ',
        'head_vehicle'          => 'หัวหน้าฝ่ายยานยนต์',
        'staff_vehicle'         => 'เจ้าหน้าที่ฝ่ายยานยนต์',
        'pending'               => 'รออนุมัติสิทธิ์',
        default                 => 'ผู้ใช้งานทั่วไป'
    };
    ?>
    <!DOCTYPE html>
    <html lang="th">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>403 ปฏิเสธการเข้าถึง - ระบบพลับพลาพิธี</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
        <style>
            body { font-family: 'Prompt', sans-serif; background-color: #f1f5f9; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; padding: 1.5rem; }
            .error-card { background: #ffffff; border-radius: 1.25rem; box-shadow: 0 10px 30px rgba(0,0,0,0.08); border: 1px solid #e2e8f0; width: 100%; max-width: 520px; text-align: center; overflow: hidden; }
            .error-header { background: #fff1f2; border-bottom: 1px solid #ffe4e6; padding: 2rem 1.5rem 1.5rem; }
            .error-icon { width: 70px; height: 70px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: inline-flex; align-items: center; justify-content: center; font-size: 2rem; margin-bottom: 1rem; }
            .btn-back { background: #0f2027; color: #ffffff; border-radius: 50px; padding: 0.6rem 1.75rem; font-weight: 500; text-decoration: none; display: inline-block; transition: all 0.2s; }
            .btn-back:hover { background: #203a43; color: #ffffff; transform: translateY(-1px); }
        </style>
    </head>
    <body>
        <div class="error-card">
            <div class="error-header">
                <div class="error-icon"><i class="bi bi-shield-x"></i></div>
                <h4 class="fw-bold text-danger mb-1">403 Forbidden - ปฏิเสธการเข้าถึง</h4>
                <div class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25 px-3 py-1 mt-1">ไม่อนุญาตให้เข้าถึงข้อมูลนี้</div>
            </div>
            <div class="p-4">
                <p class="text-secondary mb-3"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if (!empty($user_fullname)): ?>
                    <div class="bg-light rounded-3 p-2 text-muted small mb-4">
                        ผู้ใช้งาน: <strong><?php echo htmlspecialchars($user_fullname, ENT_QUOTES, 'UTF-8'); ?></strong> (<?php echo htmlspecialchars($roleName, ENT_QUOTES, 'UTF-8'); ?>)
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-center gap-2">
                    <a href="<?php echo htmlspecialchars($returnUrl, ENT_QUOTES, 'UTF-8'); ?>" class="btn-back">
                        <i class="bi bi-house-door me-1"></i> กลับสู่หน้าหลัก
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

function require_role(array|string $roles): void
{
    require_login();

    if (!has_role($roles)) {
        render_403_page();
    }
}

/**
 * Validate whether a user can assign or change another user's role
 */
function can_assign_role(string $callerRole, string $targetNewRole, string $targetCurrentRole, int $targetUserId): array
{
    if (!in_array($callerRole, [ROLE_ADMIN, ROLE_SUPER_ADMIN], true)) {
        return [false, 'คุณไม่มีสิทธิ์จัดการสิทธิ์ผู้ใช้งาน'];
    }

    if (!in_array($targetNewRole, ALL_ROLES, true)) {
        return [false, 'สิทธิ์ที่ระบุไม่ถูกต้อง'];
    }

    // A regular admin cannot assign super_admin
    if ($callerRole !== ROLE_SUPER_ADMIN && $targetNewRole === ROLE_SUPER_ADMIN) {
        return [false, 'ผู้ดูแลระบบทั่วไปไม่สามารถแต่งตั้งผู้ดูแลระบบสูงสุด (Super Admin) ได้'];
    }

    // A regular admin cannot modify a super_admin account
    if ($callerRole !== ROLE_SUPER_ADMIN && $targetCurrentRole === ROLE_SUPER_ADMIN) {
        return [false, 'ผู้ดูแลระบบทั่วไปไม่สามารถแก้ไขบัญชีของผู้ดูแลระบบสูงสุด (Super Admin) ได้'];
    }

    // Protect the last super_admin from being demoted
    if ($targetCurrentRole === ROLE_SUPER_ADMIN && $targetNewRole !== ROLE_SUPER_ADMIN) {
        $conn = get_db_connection();
        $stmt = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'super_admin'");
        $count = (int)$stmt->fetchColumn();
        if ($count <= 1) {
            return [false, 'ไม่สามารถลดสิทธิ์ผู้ดูแลระบบสูงสุด (Super Admin) คนสุดท้ายของระบบได้'];
        }
    }

    return [true, ''];
}

/**
 * Completely clean up session and cookies
 */
function auth_logout(bool $keepNotice = false): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Optional SSO token revocation
    if (!empty($_SESSION['access_token'])) {
        $ssoBase = env('SSO_BASE_URL', 'https://sso.dpt.go.th/openId/public');
        $revokeUrl = rtrim($ssoBase, '/') . '/revokeToken';

        $ch = curl_init($revokeUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['token' => $_SESSION['access_token']]));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        @curl_exec($ch);
        curl_close($ch);
    }

    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }

    session_destroy();
}

<?php
/**
 * Application Entry Point & Login
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/sso.php';
require_once __DIR__ . '/includes/security.php';

// Redirect if already logged in
if (is_logged_in()) {
    redirect('welcome.php');
}

// Support SSO callbacks routed directly to index.php
if (!empty($_GET['code'])) {
    $code = (string)$_GET['code'];
    $state = (string)($_GET['state'] ?? '');
    [$success, $msg, $user] = sso_handle_callback($code, $state);
    if ($success) {
        redirect('welcome.php');
    }
    $loginError = $msg;
}

$demoError = $_GET['demo_error'] ?? '';
$isDemo = is_demo_mode();
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เข้าสู่ระบบ - ระบบพลับพลาพิธี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        .login-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.25);
            padding: 35px 30px;
            text-align: center;
            max-width: 480px;
            width: 100%;
            background: #ffffff;
        }
        .sso-btn {
            background: linear-gradient(135deg, #1d976c, #11998e);
            color: white;
            border-radius: 12px;
            padding: 12px 20px;
            font-weight: 500;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 15px;
            transition: all 0.3s;
            border: none;
        }
        .sso-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(29, 151, 108, 0.4);
            color: white;
        }
        .demo-panel {
            background-color: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            padding: 18px;
            margin-top: 25px;
            text-align: left;
        }
        .role-btn {
            font-size: 0.8rem;
            padding: 6px 10px;
            border-radius: 8px;
            transition: all 0.2s;
            margin-bottom: 6px;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="mb-3 text-center">
            <div class="d-inline-flex align-items-center justify-content-center bg-light rounded-circle p-3 mb-2" style="width: 75px; height: 75px;">
                <i class="bi bi-bank2 text-success fs-1"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1">ระบบพลับพลาพิธี</h4>
            <p class="text-muted small mb-0">กรมโยธาธิการและผังเมือง</p>
        </div>

        <?php if (!empty($loginError)): ?>
            <div class="alert alert-danger py-2 px-3 small rounded-3 mb-3 text-start">
                <i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo e($loginError); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($demoError)): ?>
            <div class="alert alert-warning py-2 px-3 small rounded-3 mb-3 text-start">
                <i class="bi bi-info-circle-fill me-1"></i> <?php echo e($demoError); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['logged_out'])): ?>
            <div class="alert alert-success py-2 px-3 small rounded-3 mb-3 text-start">
                <i class="bi bi-check-circle-fill me-1"></i> ออกจากระบบเรียบร้อยแล้ว
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['timeout'])): ?>
            <div class="alert alert-warning py-2 px-3 small rounded-3 mb-3 text-start">
                <i class="bi bi-clock-history me-1"></i> เซสชันหมดอายุเนื่องจากไม่มีการใช้งาน กรุณาเข้าสู่ระบบใหม่
            </div>
        <?php endif; ?>

        <!-- DPT Single Sign-On Button -->
      <!--  <a href="sso_login.php" class="sso-btn">
            <i class="bi bi-shield-lock-fill me-2 fs-5"></i> เข้าสู่ระบบผ่าน SSO DPT
        </a> -->

        <?php if ($isDemo): ?>
            <!-- Portfolio / Local Demo Sandbox Panel -->
            <div class="demo-panel">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge bg-warning text-dark px-2 py-1">
                        <i class="bi bi-code-slash me-1"></i> Portfolio Demo Mode
                    </span>
                    <small class="text-muted" style="font-size: 0.75rem;">ข้อมูลจำลอง 100%</small>
                </div>
                <p class="text-muted small mb-3" style="font-size: 0.8rem;">
                    สามารถเลือกทดสอบสิทธิ์ของแต่ละบทบาทได้ทันทีโดยไม่ต้องเชื่อมต่อ SSO จริง:
                </p>

                <form method="POST" action="login_demo.php" class="d-flex flex-wrap gap-1">
                    <?php echo csrf_field(); ?>
                    <button type="submit" name="demo_role" value="super_admin" class="btn btn-outline-danger role-btn">
                        <i class="bi bi-shield-shaded me-1"></i> Super Admin
                    </button>
                    <button type="submit" name="demo_role" value="admin" class="btn btn-outline-dark role-btn">
                        <i class="bi bi-person-fill-gear me-1"></i> Admin
                    </button>
                    <button type="submit" name="demo_role" value="head_operation" class="btn btn-outline-warning role-btn text-dark">
                        <i class="bi bi-person-badge-fill me-1"></i> หน.ปฏิบัติการ
                    </button>
                    <button type="submit" name="demo_role" value="coordinator_operation" class="btn btn-outline-info role-btn text-dark">
                        <i class="bi bi-clipboard-check-fill me-1"></i> ผู้ประสานงาน
                    </button>
                    <button type="submit" name="demo_role" value="staff_operation" class="btn btn-outline-primary role-btn">
                        <i class="bi bi-person-workspace me-1"></i> จนท.ปฏิบัติการ
                    </button>
                    <button type="submit" name="demo_role" value="head_vehicle" class="btn btn-outline-success role-btn">
                        <i class="bi bi-truck-front-fill me-1"></i> หน.ยานยนต์
                    </button>
                    <button type="submit" name="demo_role" value="staff_vehicle" class="btn btn-outline-secondary role-btn">
                        <i class="bi bi-car-front-fill me-1"></i> จนท.ยานยนต์
                    </button>
                    <button type="submit" name="demo_role" value="pending" class="btn btn-outline-light text-muted border role-btn">
                        <i class="bi bi-hourglass-split me-1"></i> รออนุมัติ
                    </button>
                </form>

                <hr class="my-2 border-secondary-subtle">
                <small class="text-muted d-block" style="font-size: 0.72rem;">
                    * รหัสผ่านสำหรับบัญชีทดสอบทุกบทบาทคือ <code>password123</code>
                </small>
            </div>
        <?php endif; ?>

        <div class="mt-4 text-center">
            <small class="text-muted" style="font-size: 0.75rem;">
                &copy; <?php echo date('Y'); ?> กรมโยธาธิการและผังเมือง
            </small>
        </div>
    </div>
</body>
</html>

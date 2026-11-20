<?php
/**
 * Welcome Screen - Role Landing Redirect
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

require_login();

$userId = current_user_id();
$userRole = current_user_role();
$conn = get_db_connection();

$searchStmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$searchStmt->execute([$userId]);
$userData = $searchStmt->fetch();

$roleNameTh = [
    'super_admin'           => 'ผู้ดูแลระบบสูงสุด (Super Admin)',
    'admin'                 => 'ผู้ดูแลระบบ (Admin)',
    'head_operation'        => 'หัวหน้าฝ่ายปฏิบัติการ',
    'coordinator_operation' => 'ฝ่ายปฏิบัติการ (ผู้ประสานงาน)',
    'staff_operation'       => 'เจ้าหน้าที่ฝ่ายปฏิบัติการ',
    'head_vehicle'          => 'หัวหน้าฝ่ายยานยนต์',
    'staff_vehicle'         => 'เจ้าหน้าที่ฝ่ายยานยนต์',
    'pending'               => 'รออนุมัติสิทธิ์การใช้งาน'
];

$targetPage = "index.php";

switch ($userRole) {
    case 'super_admin':
    case 'admin':
        $targetPage = "home_admin.php";
        break;
    case 'head_operation':
        $targetPage = "home_head_operation.php";
        break;
    case 'coordinator_operation':
        $targetPage = "home_operation.php";
        break;
    case 'staff_operation':
        $targetPage = "staff_view_operation.php";
        break;
    case 'head_vehicle':
        $targetPage = "home_head_vehicle.php";
        break;
    case 'staff_vehicle':
        $targetPage = "home_vehicle.php";
        break;
    case 'pending':
    default:
        $targetPage = "#";
        break;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <title>ยินดีต้อนรับ - ระบบพลับพลาพิธี</title>
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #1d976c 0%, #11998e 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            padding: 20px;
        }
        .welcome-card {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 1.5rem;
            border: none;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            width: 100%;
            max-width: 480px;
        }
        .card-header-custom {
            background: #ffffff;
            border-bottom: 1px solid #f0f0f0;
            padding: 1.75rem 1rem 1rem;
        }
        .icon-circle {
            width: 90px;
            height: 90px;
            background: #e8f5e9;
            color: #2e7d32;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.25rem;
            font-size: 2.75rem;
        }
        .role-badge {
            display: inline-block;
            padding: 0.5rem 1.25rem;
            background: linear-gradient(45deg, #2e7d32, #43a047);
            color: white;
            border-radius: 50px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        .btn-main {
            background: linear-gradient(45deg, #11998e, #38ef7d);
            border: none;
            color: white;
            padding: 12px 30px;
            border-radius: 50px;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }
        .btn-main:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(17, 153, 142, 0.3);
            color: white;
        }
    </style>
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>
    <div class="welcome-card">
        <div class="card-header-custom text-center">
            <div class="d-flex align-items-center justify-content-center text-success fw-bold fs-5 mb-1">
                <i class="bi bi-bank2 me-2"></i> กรมโยธาธิการและผังเมือง
            </div>
            <small class="text-muted">ระบบพลับพลาพิธี (DPT Ceremonial System)</small>
        </div>

        <div class="card-body p-4 text-center">
            <?php if ($userData): ?>
                <div class="icon-circle">
                    <i class="bi bi-person-check-fill"></i>
                </div>

                <h6 class="text-muted mb-1">ยินดีต้อนรับ</h6>
                <h4 class="fw-bold mb-3 text-dark">
                    <?php echo e($userData['fname'] . ' ' . $userData['lname']); ?>
                </h4>

                <div class="mb-4">
                    <p class="mb-1 text-secondary small">สถานะการเข้าใช้งาน</p>
                    <div class="role-badge">
                        <i class="bi bi-shield-lock me-1"></i>
                        <?php echo e($roleNameTh[$userRole] ?? $userRole); ?>
                    </div>
                    <?php if (!empty($userData['depart_name'])): ?>
                        <div class="mt-2 text-muted small">
                            <i class="bi bi-building me-1"></i> <?php echo e($userData['depart_name']); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($userRole === 'pending'): ?>
                    <div class="alert alert-warning py-2 small mb-4">
                        <i class="bi bi-hourglass-split me-1"></i> บัญชีของคุณอยู่ระหว่างรอผู้ดูแลระบบกำหนดสิทธิ์การใช้งาน
                    </div>
                <?php else: ?>
                    <div class="d-grid gap-2">
                        <a href="<?php echo e($targetPage); ?>" class="btn btn-main btn-lg">
                            เข้าสู่หน้าหลัก <i class="bi bi-chevron-right ms-1"></i>
                        </a>
                    </div>
                <?php endif; ?>

            <?php else: ?>
                <div class="text-danger mb-3">
                    <i class="bi bi-exclamation-octagon fs-1"></i>
                </div>
                <h5 class="fw-bold text-dark">ไม่พบข้อมูลผู้ใช้งาน</h5>
                <p class="text-muted small">กรุณาลองใหม่อีกครั้ง</p>
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">กลับหน้าเข้าสู่ระบบ</a>
            <?php endif; ?>

            <div class="mt-4 pt-2 border-top">
                <a href="logout.php" class="text-danger small text-decoration-none">
                    <i class="bi bi-box-arrow-right me-1"></i> ออกจากระบบ
                </a>
            </div>
        </div>
    </div>
</body>
</html>

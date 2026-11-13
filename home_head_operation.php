<?php
/**
 * Head of Operation Dashboard
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/db.php';

require_role([ROLE_HEAD_OPERATION, ROLE_SUPER_ADMIN]);

$conn = get_db_connection();

// Count unassigned documents
$unassigned_count = 0;
try {
    $stmtBadge = $conn->query("SELECT COUNT(*) FROM documents d WHERE NOT EXISTS (SELECT 1 FROM document_assignments da WHERE da.doc_id = d.id)");
    $unassigned_count = (int)$stmtBadge->fetchColumn();
} catch (\PDOException $e) {
    // silent
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลัก - หัวหน้าฝ่ายปฏิบัติการ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        html, body {
            height: 100%;
            margin: 0;
        }
        body { 
            font-family: 'Prompt', sans-serif; 
            background-color: #f4f6f9; 
            display: flex;
            flex-direction: column;
        }
        .content-wrapper { 
            flex: 1 0 auto;
            padding-bottom: 3rem; 
        }
        .hero-section { background-color: #1e293b; padding: 3.5rem 0; text-align: center; border-bottom: 4px solid #eab308; margin-bottom: 2.5rem; }
        .hero-title { color: #facc15; font-weight: 700; font-size: 2.2rem; margin-bottom: 0.5rem; }
        .hero-subtitle { color: #ffffff; font-weight: 400; font-size: 1.1rem; opacity: 0.9; margin-bottom: 0; }
        .role-card { display: block; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem 1.5rem; text-align: center; text-decoration: none; color: #334155; transition: all 0.2s ease; height: 100%; position: relative; }
        .role-card:hover { background-color: #f8fafc; border-color: #eab308; color: #0f172a; transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .role-card::after { content: "\F14B"; font-family: "bootstrap-icons"; position: absolute; top: 10px; right: 10px; font-size: 0.9rem; color: #cbd5e1; opacity: 0; transition: opacity 0.2s; }
        .role-card:hover::after { opacity: 1; }
        .role-icon { font-size: 2.5rem; color: #64748b; margin-bottom: 1rem; }
        .role-title { font-weight: 600; font-size: 1.1rem; margin: 0; }

        @media (max-width: 767.98px) {
            .hero-section { padding: 2rem 0; margin-bottom: 1.5rem; }
            .hero-title { font-size: 1.6rem; }
            .hero-subtitle { font-size: 0.95rem; }
        }
    </style>
</head>
<body>

    <?php require 'menu.php'; ?>

    <div class="content-wrapper">
        <div class="hero-section">
            <div class="container">
                <h1 class="hero-title">ระบบพลับพลาพิธี</h1>
                <h2 class="hero-subtitle">กรมโยธาธิการและผังเมือง ศูนย์เทคโนโลยีสารสนเทศ</h2>
            </div>
        </div>
        
        <div class="container">
            <div class="row g-4 justify-content-center">
                <div class="col-12">
                    <?php
                    $is_super = (current_user_role() === ROLE_SUPER_ADMIN);
                    $user_fullname = trim(($_SESSION['user_fname'] ?? $_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? ''));
                    $role_banner_text = $is_super ? 'เห็นเมนูหัวหน้าฝ่ายปฏิบัติการ' : 'หัวหน้าฝ่ายปฏิบัติการ';
                    ?>
                    <div class="alert alert-info shadow-sm rounded-3" role="alert" style="padding: 1.25rem; border-left: 5px solid #0ea5e9;">
                        <i class="bi bi-info-circle fs-4 me-2 text-info"></i> 
                        <strong>ยินดีต้อนรับ 
                            <?php echo e($user_fullname); ?> (<?php echo e($role_banner_text); ?>)
                            <?php if (!empty($_SESSION['depart_name'])): ?>
                                <br><small class="text-secondary"><?php echo e($_SESSION['depart_name']); ?></small>
                            <?php endif; ?>
                        </strong>
                        <br>
                        <span class="small text-secondary">คลิกเลือกลิงก์ที่เมนูบาร์ หรือเลือกเมนูด้านล่างเพื่อเริ่มใช้งานระบบ</span>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <a href="manage_coordinator_operation.php" class="role-card text-decoration-none">
                        <div class="role-icon">
                            <i class="bi bi-shield-lock-fill text-primary"></i> 
                        </div>
                        <h3 class="role-title">กำหนดสิทธิ์ผู้ประสานงาน</h3>
                        <p class="small text-muted mt-2 mb-0">
                            กำหนดสิทธิ์สำหรับการเป็นผู้ประสานงานในโครงการ
                        </p>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <a href="manage_operation2.php" class="role-card text-decoration-none">
                        <div class="role-icon position-relative d-inline-block">
                            <i class="bi bi-person-badge text-info"></i>
                            <?php if ($unassigned_count > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.75rem;">
                                    <?php echo $unassigned_count; ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        <h3 class="role-title">มอบหมายงานและอุปกรณ์</h3>
                        <p class="small text-muted mt-2 mb-0">
                            มอบหมายผู้ประสานงาน จัดชุดปฏิบัติงาน และยืม-คืนอุปกรณ์
                        </p>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <a href="manage_team.php" class="role-card text-decoration-none">
                        <div class="role-icon">
                            <i class="bi bi-people-fill text-success"></i> 
                        </div>
                        <h3 class="role-title">จัดชุดปฏิบัติงาน</h3>
                        <p class="small text-muted mt-2 mb-0">
                            สร้างและบริหารจัดการชุดเจ้าหน้าที่สำหรับลงพื้นที่
                        </p>
                    </a>
                </div>

                <div class="col-12 col-sm-6 col-md-4 col-lg-3">
                    <a href="view_system_logs.php" class="role-card text-decoration-none">
                        <div class="role-icon">
                            <i class="bi bi-clock-history text-warning"></i> 
                        </div>
                        <h3 class="role-title">ประวัติการทำงานของระบบ</h3>
                        <p class="small text-muted mt-2 mb-0">
                            แสดงรายการประวัติการมอบหมายงานและบันทึกกิจกรรม
                        </p>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php require 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

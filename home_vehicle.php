<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';

require_role([ROLE_STAFF_VEHICLE, ROLE_SUPER_ADMIN]);
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลัก - ระบบพับพลาพิธี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        /* --- จุดที่ต้องแก้/เพิ่ม --- */
        html, body {
            height: 100%; /* บังคับให้ความสูงของ html/body เต็มจอ */
            margin: 0;
        }
        body { 
            font-family: 'Prompt', sans-serif; 
            background-color: #f4f6f9; 
            display: flex; /* ใช้ Flexbox */
            flex-direction: column; /* เรียงเป็นแนวตั้ง */
        }
        .content-wrapper { 
            flex: 1 0 auto; /* หัวใจสำคัญ: ดันให้ส่วนนี้ยืดจนสุดพื้นที่ เพื่อเบียด footer ลงไป */
            padding-bottom: 3rem; 
        }
        /* ----------------------- */

        .hero-section { background-color: #1e293b; padding: 4rem 0; text-align: center; border-bottom: 4px solid #eab308; margin-bottom: 3rem; }
        .hero-title { color: #facc15; font-weight: 700; font-size: 2.5rem; margin-bottom: 0.5rem; }
        .hero-subtitle { color: #ffffff; font-weight: 400; font-size: 1.2rem; opacity: 0.9; margin-bottom: 0; }
        .role-card { display: block; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem 1.5rem; text-align: center; text-decoration: none; color: #334155; transition: all 0.2s ease; height: 100%; position: relative; }
        .role-card:hover { background-color: #f8fafc; border-color: #eab308; color: #0f172a; transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .role-card::after { content: "\F14B"; font-family: "bootstrap-icons"; position: absolute; top: 10px; right: 10px; font-size: 0.9rem; color: #cbd5e1; opacity: 0; transition: opacity 0.2s; }
        .role-card:hover::after { opacity: 1; }
        .role-icon { font-size: 2.5rem; color: #64748b; margin-bottom: 1rem; }
        .role-title { font-weight: 600; font-size: 1.1rem; margin: 0; }

        /* Responsive */
        @media (max-width: 767.98px) {
            .hero-section { padding: 2.5rem 0; margin-bottom: 2rem; }
            .hero-title { font-size: 1.8rem; }
            .hero-subtitle { font-size: 1rem; }
            .alert-lg { padding: 1.2rem !important; font-size: 1.05rem !important; }
            .alert-lg .bi { font-size: 1.2rem !important; }
            .alert-lg span { font-size: 0.9rem !important; }
        }
    </style>
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>

    <?php if(file_exists('menu.php')) require 'menu.php'; ?>

    <div class="content-wrapper">
        <div class="hero-section">
            <div class="container">
                <h1 class="hero-title">ระบบพลับพลาพิธี</h1>
                <h2 class="hero-subtitle">กรมโยธาธิการและผังเมือง ศูนย์เทคโนโลยีสารสนเทศ</h2>
            </div>
        </div>
        
        <div class="container"> <div class="row g-4 justify-content-center">
                <div class="col-12">
                    <?php
                    $is_super = (current_user_role() === ROLE_SUPER_ADMIN);
                    $user_fullname = trim(($_SESSION['user_fname'] ?? $_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? ''));
                    $role_banner_text = $is_super ? 'เห็นเมนูฝ่ายยานยนต์' : 'ฝ่ายยานยนต์';
                    ?>
                    <div class="alert alert-info alert-lg shadow-sm" role="alert" style="padding: 1.5rem; font-size: 1.2rem; border-left: 5px solid #0ea5e9;">
                        <i class="bi bi-info-circle" style="font-size: 1.5rem; margin-right: 0.5rem; color: #0ea5e9;"></i> 
                        <strong>ยินดีต้อนรับ 
                            <?php echo htmlspecialchars($user_fullname, ENT_QUOTES, 'UTF-8'); ?> (<?php echo htmlspecialchars($role_banner_text, ENT_QUOTES, 'UTF-8'); ?>)
                            <?php if (!empty($_SESSION['depart_name'])): ?>
                                <br><small class="text-secondary" style="font-size: 0.95rem; font-weight: normal;"><?php echo htmlspecialchars($_SESSION['depart_name'], ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </strong>
                        <br>
                        <span style="font-size: 1rem; color: #475569;">คลิกเลือกลิงก์ที่เมนูบาร์ หรือเลือกเมนูด้านล่างเพื่อเริ่มใช้งานระบบพลับพลาพิธี</span>
                    </div>
                </div>

             <div class="col-12 col-sm-6 col-md-4 col-lg-3">
    <a href="select_mission.php" class="role-card">
        <div class="role-icon">
            <i class="bi bi-car-front" style="color: #10b981;"></i>
        </div>
        <h3 class="role-title">การบริหารจัดการรถ</h3>
        <p style="font-size: 0.85rem; color: #64748b; margin-top: 0.5rem; margin-bottom: 0;">
            จัดรถ (ทีม) จัดคนขับรถ และระบุประเภทรถให้เหมาะสม
        </p>
    </a>
</div>

<div class="col-12 col-sm-6 col-md-4 col-lg-3">
    <a href="manage_vehicles.php" class="role-card">
        <div class="role-icon">
            <i class="bi bi-truck" style="color: #10b981;"></i>
        </div>
        <h3 class="role-title">จัดการรถ</h3>
        <p style="font-size: 0.85rem; color: #64748b; margin-top: 0.5rem; margin-bottom: 0;">
            จัดการข้อมูลยานพาหนะ
        </p>
    </a>
</div>

<div class="col-12 col-sm-6 col-md-4 col-lg-3">
    <a href="manage_drivers.php" class="role-card">
        <div class="role-icon">
            <i class="bi bi-person-gear" style="color: #10b981;"></i>
        </div>
        <h3 class="role-title">จัดการคน</h3>
        <p style="font-size: 0.85rem; color: #64748b; margin-top: 0.5rem; margin-bottom: 0;">
            จัดการข้อมูลพนักงานขับรถ
        </p>
    </a>
</div>
            </div>
        </div>
    </div> <?php if(file_exists('footer.php')) require 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
session_start();
require_once 'db.php'; // เรียกใช้การเชื่อมต่อฐานข้อมูล

// =========================================================================
// 1. ตรวจสอบ Session Timeout (2 ชั่วโมง = 7200 วินาที)
// =========================================================================
$timeout_duration = 7200;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: index.php"); // กลับไปหน้า login
    exit();
}
$_SESSION['last_activity'] = time(); // อัปเดตเวลาใช้งานล่าสุด

// =========================================================================
// 2. จำลองข้อมูล SSO และบังคับอัปเดต Role เป็น super_admin เมื่อเข้าหน้า Home
// =========================================================================
$_SESSION['fname'] = $_SESSION['fname'] ?? "ปวิตร"; 
$_SESSION['lname'] = $_SESSION['lname'] ?? "ตรงประกอบ";
$_SESSION['cid'] = $_SESSION['cid'] ?? "1100100123456"; 
$_SESSION['username'] = $_SESSION['username'] ?? "pavit_sso";
$_SESSION['depart_name'] = $_SESSION['depart_name'] ?? "ศูนย์เทคโนโลยีสารสนเทศ";
$_SESSION['depart_code'] = $_SESSION['depart_code'] ?? "1234";
$_SESSION['email'] = $_SESSION['email'] ?? "pavit@dpt.go.th";
$_SESSION['mobile_phone'] = $_SESSION['mobile_phone'] ?? "0812345678";

$cid = $_SESSION['cid'];

// ตรวจสอบว่ามีข้อมูลใน Database หรือยัง
$check_stmt = $conn->prepare("SELECT * FROM users WHERE cid = ? LIMIT 1");
$check_stmt->execute([$cid]);
$user_data = $check_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_data) {
    // ถ้าไม่มีข้อมูลใน DB ให้ Insert ใหม่ และกำหนดสิทธิ์ super_admin
    $sql_insert = "INSERT INTO users (username_sso, fname, lname, cid, role) 
                   VALUES (?, ?, ?, ?, 'super_admin')";
    $conn->prepare($sql_insert)->execute([
        $_SESSION['username'], $_SESSION['fname'], $_SESSION['lname'], $cid
    ]);
    
    // ตั้งค่า Session
    $_SESSION['user_id'] = $conn->lastInsertId();
    $_SESSION['user_fname'] = $_SESSION['fname'];
    $_SESSION['role'] = 'super_admin';
} else {
    // ถ้ามีข้อมูลอยู่แล้ว ให้ Update บังคับเปลี่ยน role เป็น super_admin ทันที
    $sql_update = "UPDATE users SET role = 'super_admin' WHERE cid = ?";
    $conn->prepare($sql_update)->execute([$cid]);

    // ตั้งค่า Session จากข้อมูลใน Database
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['user_fname'] = $user_data['fname']; 
    $_SESSION['role'] = 'super_admin'; 
}
// =========================================================================
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลัก - ระบบพลับพลาพิธี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        /* ปรับปรุง CSS โครงสร้างหลัก */
        body { 
            font-family: 'Prompt', sans-serif; 
            background-color: #f4f6f9; 
            margin: 0; 
            padding: 0; 
            display: flex;
            flex-direction: column;
            min-height: 100vh; /* ให้หน้าเว็บสูงอย่างน้อยเท่าหน้าจอเสมอ */
        }
        .content-wrapper { 
            flex: 1; /* ดันเนื้อหาให้เต็มพื้นที่ว่าง เพื่อผลัก Footer ลงไป */
            padding-bottom: 3rem; 
        }
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

        <div class="container">
            <div class="alert alert-success text-center mb-4 shadow-sm" role="alert">
                <i class="bi bi-shield-lock-fill me-2"></i> ขณะนี้คุณเข้าใช้งานในสถานะ: <strong>Super Admin 
				<!-- (<?php echo htmlspecialchars($_SESSION['user_fname']); ?>) -->
				</strong>
            </div>

           <h2 style="text-align: center; margin-bottom: 2rem;">กรุณาเลือกบทบาทของคุณเพื่อเริ่มทดสอบระบบ</h2>
            
            <div class="row g-4 justify-content-center">
                <div class="col-lg-3 col-md-6">
                    <a href="index3.php?test_role=admin_op" target="_blank" class="role-card shadow-sm">
                        <i class="bi bi-person-fill-gear role-icon"></i>
                        <h3 class="role-title">หัวหน้าฝ่ายปฏิบัติงาน</h3>
                    </a>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <a href="index01.php?test_role=staff_op" target="_blank" class="role-card shadow-sm">
                        <i class="bi bi-person-badge role-icon"></i>
                        <h3 class="role-title">ฝ่ายปฏิบัติงาน</h3>
                    </a>
                </div>

                <div class="col-lg-3 col-md-6">
                    <a href="index4.php?test_role=admin_car" target="_blank" class="role-card shadow-sm">
                        <i class="bi bi-person-vcard role-icon"></i>
                        <h3 class="role-title">หัวหน้าฝ่ายยานยนต์</h3>
                    </a>
                </div>

                <div class="col-lg-3 col-md-6">
                    <a href="index2.php?test_role=staff_car" target="_blank" class="role-card shadow-sm">
                        <i class="bi bi-car-front role-icon"></i>
                        <h3 class="role-title">ฝ่ายยานยนต์</h3>
                    </a>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <a href="index5.php?test_role=admin" target="_blank" class="role-card shadow-sm">
                        <i class="bi bi-people role-icon"></i>
                        <h3 class="role-title">ผู้ดูแลระบบ ( admin )</h3>
                    </a>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <a href="dashboard.php" target="_blank" class="role-card shadow-sm">
                        <i class="bi bi-bar-chart-fill role-icon" style="color: #3b82f6;"></i>
                        <h3 class="role-title">หน้าแดชบอร์ด (Dashboard)</h3>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if(file_exists('footer.php')) require 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
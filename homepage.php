<?php
session_start();
require 'db.php'; // เชื่อมต่อฐานข้อมูล
// ตรวจสอบ Session Timeout (2 ชั่วโมง = 7200 วินาที)
$timeout_duration = 7200;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: index.php"); // หรือกลับไปหน้า login
    exit();
}
$_SESSION['last_activity'] = time(); // อัปเดตเวลาใช้งานล่าสุด


// ตรวจสอบการล็อคอิน
// if (!isset($_SESSION['cid'])) {
//     header("Location: login.php");
//     exit();
// }

// ดึงข้อมูลผู้ใช้งานเบื้องต้น (สมมติว่าในตาราง users มี fname, lname, role)
$current_cid = $_SESSION['cid'];
$stmt = $conn->prepare("SELECT fname, lname, role FROM users WHERE cid = ? LIMIT 1");
$stmt->execute([$current_cid]);
$user = $stmt->fetch();

$user_fullname = $user ? htmlspecialchars($user['fname'] . ' ' . $user['lname']) : 'ผู้ใช้งานระบบ';
$user_role = $user ? htmlspecialchars($user['role']) : 'N/A';
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลัก - ระบบพลับพลาพิธี กรมโยธาธิการและผังเมือง</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-dpt: #0f4c81; /* สีกรมท่า */
            --secondary-dpt: #1a365d;
            --gold-dpt: #c5a059; /* สีทอง */
            --gold-light: #fdfaf2;
            --bg-body: #f4f7f9;
        }
        body {
            font-family: 'Prompt', sans-serif;
            background-color: var(--bg-body);
            color: #333;
        }
        /* Hero Welcome Section */
        .welcome-hero {
            background: linear-gradient(135deg, var(--primary-dpt) 0%, var(--secondary-dpt) 100%);
            border-radius: 15px;
            padding: 40px;
            color: white;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 20px rgba(15, 76, 129, 0.15);
            margin-bottom: 30px;
        }
        .welcome-hero::after {
            content: "\F142"; /* bi-bank icon code */
            font-family: "bootstrap-icons";
            position: absolute;
            right: -20px;
            bottom: -40px;
            font-size: 12rem;
            opacity: 0.05;
            color: white;
            transform: rotate(-10deg);
        }
        .welcome-subtitle {
            color: var(--gold-dpt);
            font-weight: 500;
            letter-spacing: 1px;
            margin-bottom: 10px;
        }
        .user-badge {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 8px 15px;
            border-radius: 50px;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            backdrop-filter: blur(5px);
        }

        /* Menu Cards */
        .menu-card {
            background: white;
            border-radius: 15px;
            padding: 30px 20px;
            text-align: center;
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
            border: 1px solid #edf2f9;
            height: 100%;
            text-decoration: none;
            display: block;
            color: inherit;
        }
        .menu-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.08);
            border-color: var(--gold-dpt);
        }
        .menu-icon-wrapper {
            width: 70px;
            height: 70px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            font-size: 2rem;
            transition: all 0.3s ease;
        }
        /* สีประจำแต่ละเมนู */
        .menu-1 .menu-icon-wrapper { background: #e3f2fd; color: #1976d2; }
        .menu-2 .menu-icon-wrapper { background: #fbe9e7; color: #d84315; }
        .menu-3 .menu-icon-wrapper { background: #e8f5e9; color: #2e7d32; }
        .menu-4 .menu-icon-wrapper { background: #f3e5f5; color: #6a1b9a; }
        
        .menu-card:hover .menu-icon-wrapper {
            background: var(--primary-dpt);
            color: white;
            transform: scale(1.1);
        }
        
        .menu-title { font-weight: 600; font-size: 1.15rem; color: var(--secondary-dpt); margin-bottom: 8px; }
        .menu-desc { font-size: 0.85rem; color: #6c757d; line-height: 1.5; }

        /* Summary Stat Cards */
        .stat-card {
            background: white;
            border-left: 4px solid var(--gold-dpt);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .stat-value { font-size: 1.8rem; font-weight: 700; color: var(--primary-dpt); line-height: 1; }
        .stat-label { font-size: 0.9rem; color: #6c757d; margin-top: 5px; font-weight: 500; }
        .stat-icon { font-size: 2.5rem; color: #e9ecef; }
    </style>
</head>
<body>

    <?php require 'menu.php'; ?>

    <div class="content-wrapper">
        <div class="container py-4">
            
            <div class="welcome-hero mt-3">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="welcome-subtitle"><i class="bi bi-stars"></i> กรมโยธาธิการและผังเมือง</div>
                        <h1 class="fw-bold mb-3">ระบบบริหารจัดการงานพลับพลาพิธี</h1>
                        <p class="mb-4 opacity-75 fs-5">ศูนย์รวมข้อมูลการขอความอนุเคราะห์ รับเสด็จฯ และบริหารทรัพยากร</p>
                        
                        <div class="user-badge">
                            <i class="bi bi-person-circle me-2 fs-5"></i>
                            <span>สวัสดี, <strong><?php echo $user_fullname; ?></strong> (สถานะ: <?php echo $user_role; ?>)</span>
                        </div>
                    </div>
                    <div class="col-md-4 d-none d-md-block text-end">
                        <img src="https://upload.wikimedia.org/wikipedia/th/thumb/3/30/Dpt_Logo.png/220px-Dpt_Logo.png" alt="DPT Logo" style="max-height: 120px; filter: drop-shadow(0px 4px 6px rgba(0,0,0,0.3));" onerror="this.style.display='none'">
                    </div>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="stat-card">
                        <div>
                            <div class="stat-value">12</div>
                            <div class="stat-label">คิวงานเดือนนี้</div>
                        </div>
                        <i class="bi bi-calendar-event stat-icon"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card" style="border-left-color: #2e7d32;">
                        <div>
                            <div class="stat-value">5</div>
                            <div class="stat-label">ชุดปฏิบัติงานที่พร้อม</div>
                        </div>
                        <i class="bi bi-people stat-icon"></i>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stat-card" style="border-left-color: #d84315;">
                        <div>
                            <div class="stat-value">18</div>
                            <div class="stat-label">ยานพาหนะพร้อมใช้งาน</div>
                        </div>
                        <i class="bi bi-truck stat-icon"></i>
                    </div>
                </div>
            </div>

            <h5 class="fw-bold mb-3 mt-5" style="color: var(--primary-dpt);">เมนูการจัดการระบบ (Main Modules)</h5>
            <div class="row g-4">
                
                <div class="col-xl-3 col-md-6">
                    <a href="job_input.php" class="menu-card menu-1">
                        <div class="menu-icon-wrapper">
                            <i class="bi bi-file-earmark-text"></i>
                        </div>
                        <h3 class="menu-title">บันทึกข้อมูลงาน</h3>
                        <p class="menu-desc">สร้างรหัสงานใหม่ บันทึกรายละเอียดงานรับเสด็จฯ และงานขอความอนุเคราะห์</p>
                    </a>
                </div>

                <div class="col-xl-3 col-md-6">
                    <a href="home_operation.php" class="menu-card menu-2">
                        <div class="menu-icon-wrapper">
                            <i class="bi bi-diagram-3"></i>
                        </div>
                        <h3 class="menu-title">จัดชุดปฏิบัติการ</h3>
                        <p class="menu-desc">มอบหมายผู้ประสานงาน ชุดปฏิบัติงาน ฝ่ายพัสดุ และจัดสรรยานพาหนะ</p>
                    </a>
                </div>

                <div class="col-xl-3 col-md-6">
                    <a href="calendar_view.php" class="menu-card menu-3">
                        <div class="menu-icon-wrapper">
                            <i class="bi bi-calendar-range"></i>
                        </div>
                        <h3 class="menu-title">คิวงาน / ปฏิทิน</h3>
                        <p class="menu-desc">แสดงคิวงานแบบแถบสี ปฏิทิน 1-3 เดือน ปักหมุดวันประชุมล่วงหน้าและวันงานจริง</p>
                    </a>
                </div>

                <div class="col-xl-3 col-md-6">
                    <a href="backup_data.php" class="menu-card menu-4">
                        <div class="menu-icon-wrapper">
                            <i class="bi bi-database"></i>
                        </div>
                        <h3 class="menu-title">ฐานข้อมูลบุคลากร/รถ</h3>
                        <p class="menu-desc">จัดการข้อมูลบุคลากร (Back up) ทะเบียนรถกระบะ รถบรรทุก รถห้องสรง</p>
                    </a>
                </div>

            </div>
            
        </div>
    </div>

    <?php require 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
session_start();
require 'db.php';

if (!isset($_SESSION['cid'])) {
    header("Location: index01.php");
    exit();
}

$cid = $_SESSION['cid'];

$search_stmt = $conn->prepare("SELECT * FROM users WHERE cid = ?");
$search_stmt->execute([$cid]);
$user_data = $search_stmt->fetch();

$role_name_th = [
    'staff_operation' => 'ฝ่ายปฏิบัติงาน',
    'staff_vehicle'   => 'ฝ่ายยานยนต์',
    'head_operation'  => 'หัวหน้าฝ่ายปฏิบัติงาน',
    'head_vehicle'    => 'หัวหน้าฝ่ายยานยนต์',
    'pending'         => 'รออนุมัติ',
    'admin'           => 'ผู้ดูแลระบบ'
];
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <title>ยินดีต้อนรับ - ระบบพลับพลาพิธี</title>

    <style>
    body {
        font-family: 'Kanit', sans-serif;
        background: linear-gradient(135deg, #1d976c 0%, #11998e 100%);
        min-height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0;
        overflow-x: hidden;
    }

    .welcome-card {
        background: rgba(255, 255, 255, 0.95);
        backdrop-filter: blur(10px);
        border-radius: 2rem;
        border: none;
        box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        transition: transform 0.3s ease;
        overflow: hidden;
        animation: fadeInUp 0.8s ease-out;
    }

    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .card-header-custom {
        background: #ffffff;
        border-bottom: 1px solid #eee;
        padding: 2rem 1rem 1rem;
    }

    .icon-circle {
        width: 100px;
        height: 100px;
        background: #e8f5e9;
        color: #2e7d32;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
        font-size: 3rem;
        box-shadow: 0 10px 20px rgba(46, 125, 50, 0.1);
    }

    .role-badge {
        display: inline-block;
        padding: 0.5rem 1.5rem;
        background: linear-gradient(45deg, #2e7d32, #43a047);
        color: white;
        border-radius: 50px;
        font-weight: 500;
        box-shadow: 0 4px 15px rgba(46, 125, 50, 0.3);
        margin-top: 0.5rem;
    }

    .btn-main {
        background: linear-gradient(45deg, #11998e, #38ef7d);
        border: none;
        color: white;
        padding: 12px 40px;
        border-radius: 50px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(17, 153, 142, 0.3);
    }

    .btn-main:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 25px rgba(17, 153, 142, 0.4);
        color: white;
    }

    .logout-link {
        text-decoration: none;
        color: #888;
        font-size: 0.9rem;
        transition: color 0.3s;
    }

    .logout-link:hover {
        color: #d32f2f;
    }

    .dpt-logo-text {
        color: #2e7d32;
        font-weight: 600;
        font-size: 1.2rem;
        margin-bottom: 0.5rem;
    }
    </style>
</head>

<body>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-11 col-sm-9 col-md-7 col-lg-5">
                <div class="card welcome-card">
                    <div class="card-header-custom text-center">
                        <div class="dpt-logo-text">
                            <i class="bi bi-building-fill me-2"></i>กรมโยธาธิการและผังเมือง
                        </div>
                        <small class="text-muted">ระบบพลับพลาพิธี (DPT Ceremonial System)</small>
                    </div>

                    <div class="card-body p-4 p-md-5 text-center">
                        <?php if ($user_data): ?>
                        <div class="icon-circle">
                            <i class="bi bi-person-check-fill"></i>
                        </div>

                        <h5 class="text-muted mb-1">ยินดีต้อนรับคุณ</h5>
                        <h2 class="fw-bold mb-4 text-dark">
                            <?php echo $user_data['fname'] . " " . $user_data['lname']; ?>
                        </h2>

                        <div class="mb-4">
                            <p class="mb-1 text-secondary">สถานะการเข้าใช้งาน</p>
                            <div class="role-badge">
                                <i class="bi bi-shield-lock me-2"></i>
                                <?php 
                                    if ($user_data['role'] === 'pending') {
                                        echo 'แอดมินยังไม่กำหนดสิทธิ์';
                                    } else {
                                        echo $role_name_th[$user_data['role']] ?? $user_data['role'];
                                    }
                                ?>
                            </div>
                        </div>

                        <div class="alert alert-light border-0 bg-light rounded-4 py-3 mb-4">
                            <small class="text-muted d-block mb-1">เลขบัตรประชาชน (CID)</small>
                            <span class="fw-medium"><?php echo $user_data['cid']; ?></span>
                        </div>

                        <?php
// สมมติว่าตัวแปร $user_role เก็บค่า role จาก Session หรือ Database
$user_role = $_SESSION['role']; 
$target_page = "home.php"; // ค่า default กันเหนียว

switch ($user_role) {
    case 'staff_operation':
        $target_page = "home_operation.php";
        break;
    case 'head_operation':
        $target_page = "home_head_operation.php";
        break;
    case 'staff_vehicle':
        $target_page = "home_vehicle.php";
        break;
    case 'head_vehicle':
        $target_page = "home_head_vehicle.php";
        break;
case 'pending':
        $target_page = "home_no_role.php";
        break;

        }
?>

                        <div class="d-grid gap-3">
                            <a href="<?php echo $target_page; ?>" class="btn btn-main btn-lg">
                                เข้าสู่หน้าหลัก <i class="bi bi-chevron-right ms-2"></i>
                            </a>
                        </div>

                        <?php else: ?>
                        <div class="text-danger mb-4">
                            <i class="bi bi-exclamation-octagon" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="fw-bold text-dark">ไม่พบข้อมูลผู้ใช้งาน</h4>
                        <p class="text-muted">ไม่สามารถดึงข้อมูลจากระบบได้ กรุณาลองใหม่อีกครั้ง</p>
                        <a href="index01.php" class="btn btn-outline-secondary rounded-pill px-4">กลับหน้าเข้าสู่ระบบ</a>
                        <?php endif; ?>
                    </div>

                    <div class="card-footer bg-transparent border-0 text-center pb-4">
                        <a href="" class="logout-link">
                            <i class="bi bi-box-arrow-right me-1"></i> ออกจากระบบ
                        </a>
                    </div>
                </div>

                <p class="text-center text-white-50 mt-4 small">
                    &copy; 2024 ศูนย์เทคโนโลยีสารสนเทศ กรมโยธาธิการและผังเมือง
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
<?php
session_start();
require 'db.php';
if(!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header ("Location: home.php");
    exit();
}
// ตรวจสอบ Session Timeout (2 ชั่วโมง = 7200 วินาที)
$timeout_duration = 7200;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: index.php"); // หรือกลับไปหน้า login
    exit();
}
$_SESSION['last_activity'] = time(); // อัปเดตเวลาใช้งานล่าสุด

// 1. รับค่าการอัปเดต Role จาก Dropdown
if (isset($_POST['user_id']) && isset($_POST['new_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['new_role'];
    
    $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
    $stmt->execute([$new_role, $user_id]);
    
    header("Location: admin.php"); 
    exit();
}

$users = $conn->query("SELECT * FROM users WHERE role != 'admin' ORDER BY id DESC")->fetchAll();

// ปรับสี Badge ให้ดูซอฟต์และมินิมอลเข้ากับดีไซน์ในรูป
function getRoleBadge($role) {
    switch ($role) {
        case 'staff_operation': return 'bg-primary text-white';
        case 'staff_vehicle':   return 'bg-success text-white';
        case 'head_operation':  return 'bg-warning text-dark';
        case 'head_vehicle':    return 'bg-danger text-white';
        case 'pending':         return 'bg-secondary text-white';
        default:                return 'bg-info text-dark';
    }
}

function getRoleThai($role) {
    $roles = [
        'staff_operation' => 'ฝ่ายปฏิบัติงาน',
        'staff_vehicle'   => 'ฝ่ายยานยนต์',
        'head_operation'  => 'หัวหน้าฝ่ายปฏิบัติงาน',
        'head_vehicle'    => 'หัวหน้าฝ่ายยานยนต์',
        'pending'         => 'รออนุมัติ',
        'user'            => 'ผู้ใช้งานทั่วไป'
    ];
    return $roles[$role] ?? $role;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - จัดการสิทธิ์พลับพลา</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        /* บังคับใช้ฟอนต์ Kanit กับทุก element */
        body, input, button, select, textarea, .badge {
            font-family: 'Kanit', sans-serif !important;
        }
        
        body {
            background-color: #f4f6f9;
        }

        /* แถบด้านบนสีน้ำเงิน */
        .top-banner {
            background-color: #1e3a8a;
            padding: 2rem 0 5rem 0;
            position: relative;
        }

        .page-title-center {
            color: white;
            font-weight: 600;
            font-size: 1.5rem;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        /* ปุ่มย้อนกลับ */
        .btn-back {
            background-color: white;
            color: #1e3a8a;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.4rem 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-back:hover {
            background-color: #f8f9fa;
            color: #152960;
        }

        /* การ์ดเนื้อหา */
        .main-content {
            margin-top: -3rem;
            margin-bottom: 3rem;
            position: relative;
            z-index: 10;
        }

        .admin-card {
            background: white;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            max-width: 1000px; /* เพิ่มการกำหนดความกว้างสูงสุดของการ์ด */
            margin: 0 auto;    /* จัดให้อยู่ตรงกลาง */
        }

        .card-header-custom {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header-title {
            color: #1e3a8a;
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* สไตล์ตาราง */
        .table th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 500;
            border-bottom: none;
            padding: 1rem;
        }

        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f0f2f5;
        }

        .custom-badge {
            border-radius: 50rem;
            padding: 0.4em 1em;
            font-weight: 400;
            font-size: 0.85rem;
        }

        .role-select {
            border-radius: 6px;
            border: 1px solid #dee2e6;
            font-size: 0.9rem;
            padding: 0.3rem 2rem 0.3rem 0.75rem;
            color: #495057;
        }
    </style>
</head>
<body>
<?php require 'menu.php'; ?>

    <div class="top-banner">
        <div class="container position-relative h-100">
            <div class="row align-items-center">
                <div class="col-12 col-md-3 text-center text-md-start mb-3 mb-md-0">
                    <a href="home_admin.php" class="btn btn-back position-relative w-auto d-inline-block" style="transform: none; left: 0; top: 0;">
                        <i class="bi bi-chevron-left me-1"></i> ย้อนกลับ
                    </a>
                </div>
                <div class="col-12 col-md-6 text-center">
                    <h1 class="page-title-center" style="font-size:30px;">
                        <i class="bi bi-person-lines-fill"></i> กำหนดสิทธิ์
                    </h1>
                </div>
                <div class="col-12 col-md-3"></div>
            </div>
        </div>
    </div>

    <div class="container main-content"> 
        <div class="admin-card"> <div class="card-header-custom">
                <h5 class="header-title">
                    <i class="bi bi-list-ul"></i> รายชื่อผู้ใช้งานในระบบ
                </h5>
                <div class="badge bg-light text-dark border">
                    ผู้ใช้งานทั้งหมด <?php echo count($users); ?> รายการ
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th class="ps-4">ข้อมูลผู้ใช้งาน</th>
                                <th class="text-center">สถานะปัจจุบัน</th>
                                <th class="text-center" style="width: 250px;">จัดการสิทธิ์ (Role)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($row['fname'] . " " . $row['lname']); ?></div>
                                    <div class="text-muted" style="font-size: 0.85rem;">
                                        SSO: <?php echo htmlspecialchars($row['username_sso']); ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo getRoleBadge($row['role']); ?> custom-badge">
                                        <?php echo getRoleThai($row['role']); ?>
                                    </span>
                                </td>
                                <td class="text-center pe-4">
                                    <form method="post" class="m-0">
                                        <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                        <select name="new_role" class="form-select role-select" onchange="this.form.submit()">
                                            <option value="pending" <?php echo ($row['role'] == 'pending') ? 'selected' : ''; ?>>รออนุมัติ</option>
                                            <option value="staff_operation" <?php echo ($row['role'] == 'staff_operation') ? 'selected' : ''; ?>>ฝ่ายปฏิบัติงาน</option>
                                            <option value="staff_vehicle" <?php echo ($row['role'] == 'staff_vehicle') ? 'selected' : ''; ?>>ฝ่ายยานยนต์</option>
                                            <option value="head_operation" <?php echo ($row['role'] == 'head_operation') ? 'selected' : ''; ?>>หัวหน้าฝ่ายปฏิบัติงาน</option>
                                            <option value="head_vehicle" <?php echo ($row['role'] == 'head_vehicle') ? 'selected' : ''; ?>>หัวหน้าฝ่ายยานยนต์</option>
                                            <option value="user" <?php echo ($row['role'] == 'user') ? 'selected' : ''; ?>>ผู้ใช้งานทั่วไป</option>
                                        </select>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </div>
    
<?php require 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
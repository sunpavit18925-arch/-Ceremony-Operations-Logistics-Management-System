<?php
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

$users = $conn->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();

// ฟังก์ชันช่วยแสดงสี Badge ตาม Role (สำหรับแสดงในตาราง)
function getRoleBadge($role) {
    switch ($role) {
        case 'staff_operation': return 'bg-primary';
        case 'staff_vehicle':   return 'bg-success';
        case 'head_operation':  return 'bg-warning text-dark';
        case 'head_vehicle':    return 'bg-danger';
        case 'pending':         return 'bg-secondary';
        default:                return 'bg-info text-dark';
    }
}

// ฟังก์ชันช่วยแปลชื่อ Role เป็นไทย
function getRoleThai($role) {
    $roles = [
        'staff_operation' => 'ปฏิบัติงาน',
        'staff_vehicle'   => 'ยานยนต์',
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
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f0f2f5; /* สีพื้นหลังสบายตา */
        }
        .admin-card {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 8px 24px rgba(0,0,0,0.08) !important;
        }
        .admin-header {
            background: linear-gradient(135deg, #0f2027, #203a43, #2c5364); /* ไล่สีโทนเข้มหรูหรา */
        }
        .table > :not(caption) > * > * {
            padding: 1rem 0.75rem; /* เพิ่มพื้นที่ให้ตารางดูโปร่ง */
        }
        .table-hover tbody tr {
            transition: all 0.2s ease;
        }
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
            transform: scale(1.005);
        }
        .custom-badge {
            font-weight: 500;
            font-size: 0.85rem;
            border-radius: 50rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .role-select {
            border-radius: 8px;
            cursor: pointer;
            transition: border-color 0.2s;
        }
        .role-select:hover {
            border-color: #0d6efd;
        }
        .role-select:focus {
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.15);
        }
        .user-icon-wrapper {
            width: 40px;
            height: 40px;
            background-color: #e9ecef;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container mt-5 mb-5">
        <div class="card border-0 admin-card">
            <div class="card-header admin-header text-white d-flex justify-content-between align-items-center py-4 px-4">
                <h5 class="mb-0 fw-semibold">
                    <i class="bi bi-shield-lock-fill me-2 text-info"></i>จัดการสิทธิ์ผู้ใช้งาน (ระบบพลับพลาพิธี)
                </h5>
                <span class="badge bg-light text-dark custom-badge px-3 py-2">
                    <i class="bi bi-people-fill me-1"></i> ผู้ใช้งานทั้งหมด <?php echo count($users); ?> รายการ
                </span>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive px-3 pb-3">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="text-muted fw-semibold ps-4">ข้อมูลผู้ใช้งาน</th>
                                <th class="text-center text-muted fw-semibold">สถานะปัจจุบัน</th>
                                <th class="text-center text-muted fw-semibold" style="width: 280px;">กำหนดบทบาท (Role)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $row): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="d-flex align-items-center">
                                        <div class="user-icon-wrapper me-3">
                                            <i class="bi bi-person-fill fs-5"></i>
                                        </div>
                                        <div>
                                            <div class="fw-semibold text-dark"><?php echo htmlspecialchars($row['fname'] . " " . $row['lname']); ?></div>
                                            <small class="text-muted d-flex align-items-center mt-1">
                                                <i class="bi bi-fingerprint me-1"></i>SSO: <?php echo htmlspecialchars($row['username_sso']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?php echo getRoleBadge($row['role']); ?> custom-badge px-3 py-2">
                                        <?php echo getRoleThai($row['role']); ?>
                                    </span>
                                </td>
                                <td class="text-center pe-4">
                                    <form method="post" class="m-0">
                                        <input type="hidden" name="user_id" value="<?php echo $row['id']; ?>">
                                        <select name="new_role" class="form-select form-select-sm role-select border-secondary-subtle" onchange="this.form.submit()">
                                            <option value="pending" <?php echo ($row['role'] == 'pending') ? 'selected' : ''; ?>>⏳ รออนุมัติ</option>
                                            <option value="staff_operation" <?php echo ($row['role'] == 'staff_operation') ? 'selected' : ''; ?>>🎯 ปฏิบัติงาน</option>
                                            <option value="staff_vehicle" <?php echo ($row['role'] == 'staff_vehicle') ? 'selected' : ''; ?>>🚗 ยานยนต์</option>
                                            <option value="head_operation" <?php echo ($row['role'] == 'head_operation') ? 'selected' : ''; ?>>👑 หัวหน้าฝ่ายปฏิบัติงาน</option>
                                            <option value="head_vehicle" <?php echo ($row['role'] == 'head_vehicle') ? 'selected' : ''; ?>>👑 หัวหน้าฝ่ายยานยนต์</option>
                                            <option value="user" <?php echo ($row['role'] == 'user') ? 'selected' : ''; ?>>👤 ผู้ใช้งานทั่วไป</option>
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
        
        <div class="text-center mt-4">
            <a href="index01.php" class="btn btn-outline-secondary px-4 py-2 rounded-pill">
                <i class="bi bi-arrow-left me-1"></i> กลับหน้าหลัก (Dashboard)
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
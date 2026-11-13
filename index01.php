<?php


session_start();
require 'db.php'; 

// 1. รับค่าจาก Session (ค่าจาก SSO)
$_SESSION['fname'] = "ปวิตร"; 
$_SESSION['lname'] = "ตรงประกอบ";
$_SESSION['cid'] = "1100100123456"; 
$_SESSION['username'] = "pavit_sso";
$_SESSION['depart_name'] = "ศูนย์เทคโนโลยีสารสนเทศ";
$_SESSION['depart_code'] = "1234";
$_SESSION['email'] = "pavit@dpt.go.th";
$_SESSION['mobile_phone'] = "0812345678";

$cid = $_SESSION['cid'];

$role_name_th = [
    'coordinator_operation' => 'ฝ่ายปฏิบัติงาน',
    'staff_vehicle'   => 'ฝ่ายยานยนต์',
    'head_operation'  => 'หัวหน้าฝ่ายปฏิบัติงาน',
    'head_vehicle'    => 'หัวหน้าฝ่ายยานยนต์',
    'pending'         => 'รออนุมัติ',
    'admin'           => 'ผู้ดูแลระบบ'
];

// 2. ตรวจสอบว่ามีข้อมูลใน Database หรือยัง
$check_stmt = $conn->prepare("SELECT * FROM users WHERE cid = ? LIMIT 1");
$check_stmt->execute([$cid]);
$user_data = $check_stmt->fetch(PDO::FETCH_ASSOC); // แก้เป็น $user_data

if (!$user_data) {
    // 3. ถ้าไม่มีข้อมูลใน DB ให้ Insert ใหม่
    $sql_insert = "INSERT INTO users (username_sso, fname, lname, cid, role) 
                   VALUES (?, ?, ?, ?, 'coordinator_operation')";
    $conn->prepare($sql_insert)->execute([
        $_SESSION['username'], $_SESSION['fname'], $_SESSION['lname'], $cid
    ]);
    
    // ดึง ID ที่เพิ่ง Insert
    $new_id = $conn->lastInsertId();
    
    // ตั้งค่า Session จากข้อมูลที่เพิ่ง Insert
    $_SESSION['user_id'] = $new_id;
    $_SESSION['user_fname'] = $_SESSION['fname'];
    $_SESSION['role'] = 'coordinator_operation';
} else {
    // 4. ถ้ามีข้อมูลอยู่แล้ว ให้ Update (ถ้าต้องการบังคับเปลี่ยน role)
    $sql_update = "UPDATE users SET role = 'coordinator_operation' WHERE cid = ?";
    $conn->prepare($sql_update)->execute([$cid]);

    // *** ตั้งค่า Session จากข้อมูลใน Database (เพื่อให้ชื่อเปลี่ยนตาม DB จริง) ***
    $_SESSION['user_id'] = $user_data['id'];
    $_SESSION['user_fname'] = $user_data['fname']; 
    $_SESSION['role'] = 'coordinator_operation'; // หรือใช้ $user['role'] ถ้าไม่ต้องการบังคับเปลี่ยน
}

// 5. ส่งไปหน้า Welcome หรือหน้าหลัก
header("Location: welcome.php");
exit();
?>


<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <title>ระบบพลับพลาพิธี - ตรวจสอบสิทธิ์</title>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-7">
                <div class="card shadow-lg border-0">
                    <div class="card-header bg-primary text-white text-center py-3">
                         <h5 class="mb-0">ระบบพลับพลาพิธี (DPT)</h5>
                    </div>
                    <div class="card-body p-5 text-center">

                        <?php if ($user_data): ?>
                            <div class="mb-4">
                                <i class="bi bi-person-badge text-primary" style="font-size: 4rem;"></i>
                            </div>
                            
                            <h2 class="fw-bold">ยินดีต้อนรับ</h2>
                            <h4 class="text-secondary mb-4">คุณ <?php echo $user_data['fname'] . " " . $user_data['lname']; ?></h4>
                            
                            <div class="p-4 rounded-3 bg-light border">
                                <p class="text-muted mb-1 small">ตรวจสอบสิทธิ์จากเลขบัตรประชาชน: <?php echo $user_data['cid']; ?></p>
                                <hr>
                                <p class="mb-1">บทบาทหน้าที่ของคุณคือ:</p>
                                <h3 class="text-primary fw-bold">
                                    <?php echo $role_name_th[$user_data['role']] ?? $user_data['role']; ?>
                                </h3>
                            </div>

                            <div class="mt-4">
                                <a href="main_menu.php" class="btn btn-primary btn-lg px-5 shadow">
                                    เข้าสู่ระบบงาน <i class="bi bi-arrow-right-short"></i>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">ไม่พบข้อมูลผู้ใช้งานในระบบ</div>
                        <?php endif; ?>

                    </div>
                    <div class="card-footer bg-white text-center py-3">
                        <small class="text-muted">CID Query Search System v1.0</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
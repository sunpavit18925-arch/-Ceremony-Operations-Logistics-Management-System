<?php
session_start();
require 'db.php';

// ตรวจสอบสถานะล่าสุดจาก DB เพื่อใช้แสดงผล Role ปัจจุบัน
$username = $_SESSION['username'] ?? '';
$stmt = $conn->prepare("SELECT role FROM users WHERE username_sso = ?");
$stmt->execute([$username]);
$user = $stmt->fetch();

// ถ้าแอดมินอนุมัติแล้ว (เปลี่ยนจาก pending เป็นอย่างอื่น) ให้ดีดกลับไปหน้า index01.php ทันที
if ($user && $user['role'] !== 'pending') {
    header("Location: index01.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <title>รอการอนุมัติสิทธิ์</title>
    
    <script>
        // เช็คสถานะทุก 5 วินาที
        setInterval(function(){
            fetch('check_status.php')
                .then(response => response.json())
                .then(data => {
                    if(data.role !== 'pending') {
                        window.location.href = 'index01.php';
                    }
                });
        }, 5000);
    </script>
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow border-0 mb-4">
                    <div class="card-body text-center p-5">
                        <div class="mb-4">
                            <i class="bi bi-hourglass-split text-warning" style="font-size: 5rem;"></i>
                        </div>
                        <h2 class="fw-bold text-dark">บันทึกข้อมูลเรียบร้อย</h2>
                        <hr class="my-4">
                        
                        <div class="py-3 px-4 mb-4 rounded-3" style="background-color: #fff3cd; border: 1px solid #ffeeba;">
                            <h5 class="text-warning-emphasis mb-1">
                                <strong>สถานะปัจจุบัน: <?php echo $user['role'] ?? 'pending'; ?></strong>
                            </h5>
                            <p class="mb-0 text-dark">รอแอดมินตรวจสอบและกำหนดสิทธิ์การใช้งาน</p>
                        </div>

                        <div class="d-flex align-items-center justify-content-center text-secondary">
                            <div class="spinner-grow spinner-grow-sm text-warning me-2" role="status"></div>
                            <span>ระบบจะนำท่านเข้าสู่หน้าหลักอัตโนมัติเมื่อได้รับการอนุมัติ...</span>
                        </div>
                    </div>
                </div>

                <div class="card shadow border-0">
                    <div class="card-header bg-secondary text-white">
                        <i class="bi bi-person-lines-fill me-2"></i>รายละเอียดข้อมูลที่ส่งคำขอ
                    </div>
                    <div class="card-body">
                        <table class="table table-bordered table-striped mb-0">
                            <tbody>
                                <tr>
                                    <th class="table-light" width="35%">Username (SSO)</th>
                                    <td><?php echo $_SESSION['username'] ?? '-'; ?></td>
                                </tr>
                                <tr>
                                    <th class="table-light">ชื่อ-นามสกุล</th>
                                    <td><?php echo ($_SESSION['fname'] ?? '-') . " " . ($_SESSION['lname'] ?? '-'); ?></td>
                                </tr>
                                <tr>
                                    <th class="table-light">เลขประจำตัวประชาชน (CID)</th>
                                    <td><?php echo $_SESSION['cid'] ?? '-'; ?></td>
                                </tr>
                                <tr>
                                    <th class="table-light">หน่วยงาน</th>
                                    <td><?php echo ($_SESSION['depart_name'] ?? '-') . " (รหัส: " . ($_SESSION['depart_code'] ?? '-') . ")"; ?></td>
                                </tr>
                                <tr>
                                    <th class="table-light">อีเมล</th>
                                    <td><?php echo $_SESSION['email'] ?? '-'; ?></td>
                                </tr>
                                <tr>
                                    <th class="table-light">เบอร์โทรศัพท์มือถือ</th>
                                    <td><?php echo $_SESSION['mobile_phone'] ?? '-'; ?></td>
                                </tr>
                                <tr class="table-warning">
                                    <th class="fw-bold text-dark">Role ปัจจุบัน</th>
                                    <td class="fw-bold text-dark"><?php echo $user['role'] ?? 'pending'; ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <p class="text-center mt-4 text-muted small">ศูนย์เทคโนโลยีสารสนเทศ กรมโยธาธิการและผังเมือง</p>
            </div>
        </div>
    </div>
</body>
</html>
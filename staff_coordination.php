<?php
session_start();

// ==========================================
// 1. นำเข้าไฟล์เชื่อมต่อฐานข้อมูล (db.php)
// ==========================================
require 'db.php'; 

if(!isset($_SESSION['role']) || (!in_array($_SESSION['role'], ['head_operation', 'staff_operation']) && $_SESSION['role'] !== 'super_admin')) {
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

// ==========================================
// 2. จัดการบันทึกข้อมูลเมื่อมีการกด Submit
// ==========================================
$success_msg = '';
$error_msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // รับค่าจากฟอร์ม
        $location = $_POST['location'] ?? null;
        $host_province = $_POST['host_province'] ?? null;
        
        // จับชื่อกับเบอร์โทรมาต่อกันเพื่อเก็บลงฟิลด์ coordinator
        $c_name = $_POST['coordinator_name'] ?? '';
        $c_phone = $_POST['coordinator_phone'] ?? '';
        $coordinator = trim($c_name . ' ' . $c_phone);

        // จัดการเรื่องวันที่ (ถ้าไม่ได้กรอกมาให้เป็น null)
        $event_start_date = !empty($_POST['event_start_date']) ? $_POST['event_start_date'] : null;
        $event_end_date = !empty($_POST['event_end_date']) ? $_POST['event_end_date'] : null;
        $pre_meeting_date = !empty($_POST['pre_meeting_date']) ? $_POST['pre_meeting_date'] : null;
        $dpt_work_start = !empty($_POST['dpt_start_date']) ? $_POST['dpt_start_date'] : null;
        $dpt_work_end = !empty($_POST['dpt_end_date']) ? $_POST['dpt_end_date'] : null;

        // *** สำคัญ: ในฐานข้อมูล doc_name เป็น NOT NULL ต้องมีค่าเสมอ ***
        $doc_name = 'บันทึกรายละเอียดงานเพิ่มเติม'; 
        
        $created_by = $_SESSION['username_sso'] ?? 'System'; // สมมติว่าเก็บ username ใน session
        $received_date = date('Y-m-d H:i:s');

        // คำสั่ง SQL
        $sql = "INSERT INTO documents (
                    doc_name, location, host_agency, coordinator, 
                    event_start_date, event_end_date, pre_meeting_date, 
                    dpt_work_start, dpt_work_end, received_date, created_by
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $doc_name, $location, $host_province, $coordinator,
            $event_start_date, $event_end_date, $pre_meeting_date,
            $dpt_work_start, $dpt_work_end, $received_date, $created_by
        ]);

        $success_msg = "บันทึกข้อมูลรายละเอียดงานลงฐานข้อมูลเรียบร้อยแล้วฮว๊าฟฟฟ!";
        
    } catch (\PDOException $e) {
        $error_msg = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ฟอร์มบันทึกรายละเอียดงาน</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1; /* ดัน Footer ลงไปด้านล่าง */
        }

        .form-card {
            border: none;
            border-radius: 1.5rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            background-color: #ffffff;
            overflow: hidden;
        }

        .form-header {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }

        .form-control {
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            border: 1px solid #dee2e6;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15);
        }

        .section-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #e2e8f0;
        }
    </style>
</head>
<body>

    <?php include 'menu.php'; ?>

    <div class="main-content container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-8 col-md-10">
                
                <?php if (!empty($success_msg)): ?>
                    <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center">
                        <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                        <div>
                            <h6 class="mb-0 fw-bold">สำเร็จ!</h6>
                            <small><?php echo htmlspecialchars($success_msg); ?></small>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                        <div>
                            <h6 class="mb-0 fw-bold">เกิดข้อผิดพลาด</h6>
                            <small><?php echo htmlspecialchars($error_msg); ?></small>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card form-card">
                    <div class="form-header">
                        <h4 class="mb-0 fw-bold"><i class="bi bi-file-earmark-text me-2"></i>รายละเอียดการจัดงาน</h4>
                        <p class="mb-0 mt-1 text-white-50 small">กรุณากรอกข้อมูลให้ครบถ้วนตามแบบฟอร์ม</p>
                    </div>
                    
                    <div class="card-body p-4 p-md-5">
                        <form action="" method="POST">
                            
                            <div class="mb-4">
                                <label class="form-label fw-bold text-dark">สถานที่จัดงาน</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="bi bi-geo-alt-fill text-danger"></i></span>
                                    <input type="text" class="form-control" name="location" placeholder="สำนักงาน... ตำบล.... อำเภอ.... จังหวัด......" required>
                                </div>
                            </div>

                            <div class="mb-4 mt-5">
                                <h5 class="section-title"><i class="bi bi-building me-2 text-primary"></i>หน่วยงานเจ้าภาพ</h5>
                                <div class="row g-3">
                                    <div class="col-md-12">
                                        <label class="form-label">จังหวัด</label>
                                        <input type="text" class="form-control" name="host_province" placeholder="ระบุจังหวัด..." required>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">ผู้ประสานงาน</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="bi bi-person-fill text-muted"></i></span>
                                            <input type="text" class="form-control" name="coordinator_name" placeholder="ชื่อ-นามสกุล">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">เบอร์โทร</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="bi bi-telephone-fill text-muted"></i></span>
                                            <input type="tel" class="form-control" name="coordinator_phone" placeholder="08X-XXX-XXXX">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4 mt-5">
                                <h5 class="section-title"><i class="bi bi-calendar-range me-2 text-primary"></i>กำหนดการและระยะเวลา</h5>
                                
                                <div class="row g-3 mb-3">
                                    <div class="col-12"><label class="form-label fw-bold mb-0">ระยะเวลางาน</label></div>
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">วันที่เริ่ม</span>
                                            <input type="date" class="form-control" name="event_start_date">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">ถึงวันที่</span>
                                            <input type="date" class="form-control" name="event_end_date">
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3 mb-3 mt-2">
                                    <div class="col-md-6">
                                        <label class="form-label fw-bold">วันประชุมส่วนล่วงหน้า</label>
                                        <div class="input-group">
                                            <span class="input-group-text bg-light"><i class="bi bi-calendar-event"></i></span>
                                            <input type="date" class="form-control" name="pre_meeting_date">
                                        </div>
                                    </div>
                                </div>

                                <div class="row g-3 mt-2">
                                    <div class="col-12"><label class="form-label fw-bold mb-0">ระยะเวลา ทำงาน กอพ.</label></div>
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">วันที่เริ่ม</span>
                                            <input type="date" class="form-control" name="dpt_start_date">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="input-group">
                                            <span class="input-group-text bg-light">ถึงวันที่</span>
                                            <input type="date" class="form-control" name="dpt_end_date">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="text-center mt-5">
                                <button type="submit" class="btn btn-primary btn-lg px-5 rounded-pill shadow-sm fw-bold">
                                    <i class="bi bi-save me-2"></i> บันทึกข้อมูล
                                </button>
                            </div>

                        </form>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
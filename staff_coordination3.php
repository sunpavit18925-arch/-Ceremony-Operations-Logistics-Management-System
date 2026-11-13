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

$success_msg = '';
$error_msg = '';

// ==========================================
// ส่วนที่เพิ่มเข้ามา: เช็คว่ามีข้อความสำเร็จค้างอยู่ใน Session ไหม
// ==========================================
if (isset($_SESSION['success_status'])) {
    $success_msg = $_SESSION['success_status'];
    unset($_SESSION['success_status']); // ลบค่าทิ้งทันที เพื่อไม่ให้แจ้งเตือนซ้ำถ้ารีเฟรชอีกรอบ
}

// ==========================================
// 2. จัดการบันทึกข้อมูลเมื่อมีการกด Submit (POST)
// ==========================================
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

        // คำสั่ง SQL (รองรับ MySQL/MariaDB)
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

        // ==========================================
        // จุดที่แก้ไข: เปลี่ยนมาเก็บลง Session แล้วทำการ Redirect
        // ==========================================
        $_SESSION['success_status'] = "บันทึกข้อมูลรายละเอียดการประชุมลงฐานข้อมูลสำเร็จ !";
        
        // รีไดเรกต์กลับมาที่หน้าเดิม
        header("Location: " . $_SERVER['PHP_SELF']);
        exit(); // **สำคัญมาก** ต้องมี exit เสมอเพื่อหยุดการทำงานของสคริปต์ทันที
        
    } catch (\PDOException $e) {
        $error_msg = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
    }
}

// ... โค้ดดึงข้อมูล (SELECT) และส่วนอื่นๆ ตามเดิม ...

// ==========================================
// 3. ดึงข้อมูลมาแสดงผลในตาราง (GET)
// ==========================================
try {
    // ดึงมาเฉพาะข้อมูลที่บันทึกจากฟอร์ม และเรียงจากวันที่บันทึกล่าสุดไปเก่าสุด
    $stmt = $conn->query("SELECT * FROM documents WHERE doc_name = 'บันทึกรายละเอียดงานเพิ่มเติม' ORDER BY received_date DESC");
    $documents = $stmt->fetchAll();
} catch (\PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
}

// ==========================================
// 4. ฟังก์ชันตัวช่วยแสดงผล
// ==========================================
function getJobBadge($type) {
    if (empty($type)) {
        return '<span class="badge bg-success rounded-pill shadow-sm px-3 py-2"><i class="bi bi-file-earmark-plus me-1"></i> รายละเอียดงานเพิ่มเติม</span>';
    }

    switch ($type) {
        case 'รับเสด็จฯ':
            return '<span class="badge bg-warning text-dark rounded-pill shadow-sm px-3 py-2"><i class="bi bi-crown-fill me-1"></i> รับเสด็จฯ</span>';
        case 'ขอความอนุเคราะห์':
            return '<span class="badge bg-info text-dark rounded-pill shadow-sm px-3 py-2"><i class="bi bi-heart-fill text-danger me-1"></i> ขอความอนุเคราะห์</span>';
        case 'การประสานงาน':
            return '<span class="badge bg-primary rounded-pill shadow-sm px-3 py-2"><i class="bi bi-headset me-1"></i> การประสานงาน</span>';
        default:
            return '<span class="badge bg-secondary rounded-pill shadow-sm px-3 py-2"><i class="bi bi-file-earmark-text me-1"></i> ' . htmlspecialchars($type) . '</span>';
    }
}

function formatDate($date) {
    if (empty($date) || $date == '0000-00-00') return '<span class="text-muted small">-</span>';
    return date('d/m/Y', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กระดานข้อมูลงาน | Dashboard</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding-bottom: 3rem;
        }

        /* Styles สำหรับ Header และ Table (จากหน้า List) */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0 5rem 0;
            border-radius: 0 0 2rem 2rem;
            box-shadow: 0 10px 30px rgba(118, 75, 162, 0.2);
            position: relative;
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
            margin-top: -3.5rem;
            overflow: hidden;
        }

        .table-custom { margin-bottom: 0; }
        
        .table-custom thead th {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 1.2rem 1rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .table-custom tbody td {
            padding: 1.2rem 1rem;
            vertical-align: middle;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.3s ease;
        }

        .table-custom tbody tr:hover td {
            background-color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            position: relative;
            z-index: 1;
        }

        .btn-action {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            border: none;
        }
        .btn-view { background-color: #e0e7ff; color: #4f46e5; }
        .btn-view:hover { background-color: #4f46e5; color: #fff; transform: scale(1.1); }
        
        .btn-add-new {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            border: none;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn-add-new:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(56, 239, 125, 0.4);
            color: white;
        }

        /* Styles สำหรับ Form ภายใน Modal */
        .form-control {
            border-radius: 0.5rem;
            padding: 0.6rem 1rem;
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
            font-size: 1.1rem;
        }
    </style>
</head>
<body>

    <?php include 'menu.php'; ?>

    <div class="page-header">
        <div class="container text-center text-md-start d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-kanban me-2"></i>รายละเอียดการประชุม</h2>
                <p class="text-white-50 mb-0">แสดงเฉพาะรายการรายละเอียดการประชุมที่บันทึกเพิ่มเติม</p>
            </div>
            <div class="mt-3 mt-md-0">
                <button type="button" class="btn btn-add-new rounded-pill px-4 py-2 fw-bold shadow" data-bs-toggle="modal" data-bs-target="#addJobModal">
                    <i class="bi bi-plus-lg me-1"></i> เพิ่มรายละเอียดการประชุม
                </button>
            </div>
        </div>
    </div>

    <div class="main-content container">
        
        <?php if (!empty($success_msg)): ?>
            <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 mt-3 d-flex align-items-center">
                <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                <div>
                    <h6 class="mb-0 fw-bold">สำเร็จ!</h6>
                    <small><?php echo htmlspecialchars($success_msg); ?></small>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
			<br><br>
        <?php endif; ?>

        <?php if (!empty($error_msg)): ?>
            <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 mt-3 d-flex align-items-center">
                <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                <div>
                    <h6 class="mb-0 fw-bold">เกิดข้อผิดพลาด</h6>
                    <small><?php echo htmlspecialchars($error_msg); ?></small>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card glass-card">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-list-task text-primary me-2"></i>รายการล่าสุด</h5>
                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                    ทั้งหมด <?php echo count($documents); ?> รายการ
                </span>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">ชื่องาน / รายละเอียด</th>
                                <th>ประเภท</th>
                                <th>สถานที่จัดงาน</th>
                                <th>วันที่เริ่มงาน</th>
                                <th>วันประชุมล่วงหน้า</th>
                                <th>ผู้ประสานงาน</th>
                                <th class="text-center pe-4">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                                            <h5 class="fw-bold">ยังไม่มีข้อมูลในระบบ</h5>
                                            <p class="small">คลิก "เพิ่มรายละเอียดการประชุม" เพื่อเริ่มต้นบันทึกข้อมูล</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark" style="font-size: 1.05rem;">
                                                <?php echo htmlspecialchars($doc['doc_name']); ?>
                                            </div>
                                            <div class="text-muted small mt-1">
                                                <i class="bi bi-clock-history me-1"></i> บันทึกเมื่อ: <?php echo date('d/m/Y H:i', strtotime($doc['received_date'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo getJobBadge($doc['job_type'] ?? ''); ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center text-secondary">
                                                <i class="bi bi-geo-alt-fill text-danger me-2"></i>
                                                <span class="text-truncate" style="max-width: 180px; display: inline-block;" title="<?php echo htmlspecialchars($doc['location'] ?? 'ไม่ระบุ'); ?>">
                                                    <?php echo htmlspecialchars($doc['location'] ?? '-'); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-medium text-dark">
                                                <i class="bi bi-calendar2-check text-success me-1"></i> 
                                                <?php echo formatDate($doc['event_start_date']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-medium text-secondary">
                                                <i class="bi bi-calendar-event text-warning me-1"></i> 
                                                <?php echo formatDate($doc['pre_meeting_date']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <div class="fw-bold text-dark"><i class="bi bi-building me-1"></i> จ.<?php echo htmlspecialchars($doc['host_agency'] ?? '-'); ?></div>
                                                <div class="text-muted"><i class="bi bi-telephone me-1"></i> <?php echo htmlspecialchars($doc['coordinator'] ?? '-'); ?></div>
                                            </div>
                                        </td>
                                        <td class="text-center pe-4">
                                            <a href="#" class="btn-action btn-view shadow-sm" title="ดูรายละเอียด">
                                                <i class="bi bi-search"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
<div class="modal fade" id="addJobModal" tabindex="-1" aria-labelledby="addJobModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        
        <form action="" method="POST" class="modal-content border-0 rounded-4 shadow">
            
            <div class="modal-header" style="background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%); color: white; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                <h5 class="modal-title fw-bold" id="addJobModalLabel">
                    <i class="bi bi-file-earmark-text me-2"></i> บันทึกรายละเอียดการจัดงาน
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body p-4">
                
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark">สถานที่จัดงาน</label>
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-geo-alt-fill text-danger"></i></span>
                        <input type="text" class="form-control" name="location" placeholder="สำนักงาน... ตำบล.... อำเภอ.... จังหวัด......" required>
                    </div>
                </div>

                <div class="mb-4">
                    <h6 class="section-title"><i class="bi bi-building me-2 text-primary"></i>หน่วยงานเจ้าภาพ</h6>
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

                <div class="mb-2">
                    <h6 class="section-title"><i class="bi bi-calendar-range me-2 text-primary"></i>กำหนดการและระยะเวลา</h6>
                    
                    <div class="row g-3 mb-3">
                        <div class="col-12"><label class="form-label fw-bold mb-0 text-secondary" style="font-size: 0.9rem;">ระยะเวลางาน</label></div>
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

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold text-secondary" style="font-size: 0.9rem;">วันประชุมส่วนล่วงหน้า</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-calendar-event"></i></span>
                                <input type="date" class="form-control" name="pre_meeting_date">
                            </div>
                        </div>
                    </div>

                    <div class="row g-3">
                        <div class="col-12"><label class="form-label fw-bold mb-0 text-secondary" style="font-size: 0.9rem;">ระยะเวลาทำงาน กอพ.</label></div>
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

            </div>
            
            <div class="modal-footer bg-light border-top-0" style="border-bottom-left-radius: 1rem; border-bottom-right-radius: 1rem;">
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary px-4 rounded-pill shadow-sm fw-bold">
                    <i class="bi bi-save me-2"></i> บันทึกข้อมูล
                </button>
            </div>
            
        </form>
    </div>
</div>

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <?php if (!empty($error_msg)): ?>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var myModal = new bootstrap.Modal(document.getElementById('addJobModal'));
            myModal.show();
        });
    </script>
    <?php endif; ?>

</body>
</html>
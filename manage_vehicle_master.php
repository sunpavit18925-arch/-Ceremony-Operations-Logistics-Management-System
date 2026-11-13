<?php
session_start();
require 'db.php';

if(!isset($_SESSION['role']) || (!in_array($_SESSION['role'], ['head_vehicle', 'staff_vehicle']) && $_SESSION['role'] !== 'super_admin')) {
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

// สร้างตารางอัตโนมัติหากยังไม่มีในฐานข้อมูล
try {
    $conn->exec("
    CREATE TABLE IF NOT EXISTS `vehicle_types` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `type_name` varchar(255) NOT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $conn->exec("
    CREATE TABLE IF NOT EXISTS `vehicles` (
      `id` int(11) NOT NULL AUTO_INCREMENT,
      `vehicle_type` varchar(255) NOT NULL,
      `license_plate` varchar(100) NOT NULL,
      `brand` varchar(100) DEFAULT NULL,
      `details` text DEFAULT NULL,
      PRIMARY KEY (`id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {
    // ข้ามไปหากตารางมีอยู่แล้วหรือไม่มีสิทธิ์สร้าง
}

// 1. จัดการคำสั่ง POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $action = $_POST['action'];

        // 1.1 เพิ่มประเภทรถ
        if ($action === 'add_type') {
            $type_name = trim($_POST['type_name']);
            if (!empty($type_name)) {
                $stmt = $conn->prepare("INSERT INTO vehicle_types (type_name) VALUES (?)");
                $stmt->execute([$type_name]);
                header("Location: manage_vehicle_master.php?success=add_type");
                exit();
            }
        }

        // 1.2 เพิ่มรถยนต์คันใหม่
        elseif ($action === 'add_vehicle') {
            $vehicle_type = trim($_POST['vehicle_type']);
            $license_plate = trim($_POST['license_plate']);
            $brand = trim($_POST['brand'] ?? '');
            $details = trim($_POST['details'] ?? '');

            if (!empty($vehicle_type) && !empty($license_plate)) {
                $stmt = $conn->prepare("INSERT INTO vehicles (vehicle_type, license_plate, brand, details) VALUES (?, ?, ?, ?)");
                $stmt->execute([$vehicle_type, $license_plate, $brand, $details]);
                header("Location: manage_vehicle_master.php?success=add_vehicle");
                exit();
            }
        }
        
        // 1.3 ลบประเภทรถ
        elseif ($action === 'delete_type') {
            $type_name = $_POST['type_name'];
            $stmt = $conn->prepare("DELETE FROM vehicle_types WHERE type_name = ?");
            $stmt->execute([$type_name]);
            // ลบรถที่อยู่ในประเภทนี้ตามไปด้วย
            $stmt2 = $conn->prepare("DELETE FROM vehicles WHERE vehicle_type = ?");
            $stmt2->execute([$type_name]);
            
            header("Location: manage_vehicle_master.php?success=delete_type");
            exit();
        }
        
        // 1.4 ลบรถยนต์
        elseif ($action === 'delete_vehicle') {
            $vehicle_id = $_POST['vehicle_id'];
            $stmt = $conn->prepare("DELETE FROM vehicles WHERE id = ?");
            $stmt->execute([$vehicle_id]);
            header("Location: manage_vehicle_master.php?success=delete_vehicle");
            exit();
        }
    }
}

// 2. ดึงข้อมูลมาแสดงผล
$stmt_types = $conn->query("SELECT * FROM vehicle_types ORDER BY id ASC");
$vehicle_types = $stmt_types->fetchAll(PDO::FETCH_ASSOC);

$stmt_vehicles = $conn->query("SELECT * FROM vehicles ORDER BY id ASC");
$vehicles_db = $stmt_vehicles->fetchAll(PDO::FETCH_ASSOC);

// จัดกลุ่มรถตามประเภท
$vehicles_by_type = [];
foreach ($vehicles_db as $v) {
    $vehicles_by_type[$v['vehicle_type']][] = $v;
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการข้อมูลยานพาหนะหลัก - ระบบพับพลาพิธี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { background: #f4f6f9; font-family: 'Prompt', sans-serif; min-height: 100vh; }
        .page-header { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 2.5rem 0; border-radius: 0 0 1.5rem 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .card-custom { border: none; border-radius: 1.25rem; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.05); background: #ffffff; margin-bottom: 2rem; overflow: hidden; }
        .accordion-button:not(.collapsed) { background-color: #e0f2fe; color: #0284c7; font-weight: 600; }
        .form-control, .form-select { border-radius: 0.75rem; }
        .btn-add { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); border: none; color: white; font-weight: 500; box-shadow: 0 4px 10px rgba(14, 165, 233, 0.3); border-radius: 0.75rem; transition: all 0.2s; }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(14, 165, 233, 0.4); color: white; }
    </style>
</head>
<body>

<?php include 'menu.php'; ?>

<div class="content-wrapper">
    <div class="page-header text-center position-relative">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-12 col-md-2 text-center text-md-start mb-3 mb-md-0">
                    <a href="home_head_vehicle.php" class="btn btn-outline-light rounded-pill d-inline-flex align-items-center">
                        <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
                    </a>
                </div>
                <div class="col-12 col-md-8 text-center">
                    <h2 class="fw-bold mb-2"><i class="bi bi-truck me-2"></i>จัดการข้อมูลยานพาหนะ (Master Data)</h2>
                    <p class="text-white-50 mb-0">เพิ่มประเภทรถ และระบุข้อมูลทะเบียนรถแต่ละคันในระบบ</p>
                </div>
                <div class="col-12 col-md-2"></div>
            </div>
        </div>
    </div>

    <div class="container">
        
        <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center alert-dismissible fade show">
            <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
            <div><h6 class="mb-0 fw-bold">บันทึกข้อมูลสำเร็จ!</h6></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row g-4">
            
            <div class="col-lg-4">
                <div class="card custom-card">
                    <div class="card-header bg-white pt-4 pb-3 border-bottom-0">
                        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-tags-fill text-primary me-2"></i>1. ประเภทรถ</h5>
                    </div>
                    <div class="card-body bg-light">
                        <form method="POST" class="mb-4">
                            <input type="hidden" name="action" value="add_type">
                            <div class="mb-3">
                                <input type="text" name="type_name" class="form-control" required placeholder="เช่น รถกระบะ 4 ประตู, รถห้องสรง...">
                            </div>
                            <button type="submit" class="btn btn-add w-100 py-2"><i class="bi bi-plus-circle me-1"></i> เพิ่มประเภทรถ</button>
                        </form>
                        
                        <div class="list-group shadow-sm">
                            <?php if(empty($vehicle_types)): ?>
                                <div class="list-group-item text-center text-muted py-4">ยังไม่มีประเภทรถ</div>
                            <?php else: ?>
                                <?php foreach($vehicle_types as $type): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center border-0 mb-1 rounded bg-white shadow-sm">
                                    <span class="fw-medium text-dark"><?= htmlspecialchars($type['type_name']) ?></span>
                                    <form method="POST" onsubmit="return confirm('ยืนยันการลบประเภทรถนี้? (รถที่อยู่ในประเภทนี้จะถูกลบด้วย)');" class="m-0">
                                        <input type="hidden" name="action" value="delete_type">
                                        <input type="hidden" name="type_name" value="<?= htmlspecialchars($type['type_name']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0"><i class="bi bi-trash3-fill"></i></button>
                                    </form>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-8">
                <div class="card custom-card">
                    <div class="card-header bg-white pt-4 pb-3 border-bottom-0 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold text-dark mb-0"><i class="bi bi-car-front-fill text-success me-2"></i>2. ข้อมูลรถในระบบ</h5>
                        <button class="btn btn-add px-4" data-bs-toggle="modal" data-bs-target="#addVehicleModal" <?= empty($vehicle_types) ? 'disabled' : '' ?>>
                            <i class="bi bi-plus-lg me-1"></i> เพิ่มรถคันใหม่
                        </button>
                    </div>
                    <div class="card-body">
                        
                        <?php if(empty($vehicle_types)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-truck fs-1 d-block mb-3 opacity-50"></i>
                                <h6>กรุณาเพิ่ม "ประเภทรถ" ทางด้านซ้ายมือก่อน</h6>
                            </div>
                        <?php else: ?>
                            <div class="accordion shadow-sm" id="vehicleAccordion">
                                <?php $i=0; foreach($vehicle_types as $type): $i++; $tname = $type['type_name']; ?>
                                <div class="accordion-item border-0 mb-2 rounded-3 overflow-hidden shadow-sm">
                                    <h2 class="accordion-header">
                                        <button class="accordion-button <?= $i===1 ? '' : 'collapsed' ?> bg-light text-dark fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?= $i ?>">
                                            <i class="bi bi-collection text-primary me-2"></i> <?= htmlspecialchars($tname) ?>
                                            <span class="badge bg-secondary ms-auto rounded-pill"><?= count($vehicles_by_type[$tname] ?? []) ?> คัน</span>
                                        </button>
                                    </h2>
                                    <div id="collapse<?= $i ?>" class="accordion-collapse collapse <?= $i===1 ? 'show' : '' ?>" data-bs-parent="#vehicleAccordion">
                                        <div class="accordion-body p-0">
                                            <?php if(empty($vehicles_by_type[$tname])): ?>
                                                <div class="text-center text-muted py-4 small">ยังไม่มีรถในประเภทนี้</div>
                                            <?php else: ?>
                                                <div class="table-responsive">
                                                    <table class="table table-hover align-middle mb-0">
                                                        <thead class="table-light text-muted small">
                                                            <tr>
                                                                <th class="ps-4">ทะเบียนรถ</th>
                                                                <th>ยี่ห้อ / รุ่น</th>
                                                                <th>รายละเอียดเพิ่มเติม</th>
                                                                <th class="text-center pe-4">จัดการ</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach($vehicles_by_type[$tname] as $v): ?>
                                                            <tr>
                                                                <td class="ps-4 fw-bold text-dark">
                                                                    <div class="bg-light d-inline-block px-3 py-1 rounded border shadow-sm">
                                                                        <?=  htmlspecialchars($v['license_plate']) ?>
                                                                    </div>
                                                                </td>
                                                                <td><?= htmlspecialchars($v['brand'] ?? '-') ?></td>
                                                                <td class="text-muted small"><?= htmlspecialchars($v['details'] ?? '-') ?></td>
                                                                <td class="text-center pe-4">
                                                                    <form method="POST" onsubmit="return confirm('ยืนยันการลบรถทะเบียน <?= htmlspecialchars($v['license_plate'], ENT_QUOTES) ?> ?');" class="m-0">
                                                                        <input type="hidden" name="action" value="delete_vehicle">
                                                                        <input type="hidden" name="vehicle_id" value="<?= $v['id'] ?>">
                                                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle" title="ลบข้อมูลรถ"><i class="bi bi-trash3-fill fs-5"></i></button>
                                                                    </form>
                                                                </td>
                                                            </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal เพิ่มรถคันใหม่ -->
<div class="modal fade" id="addVehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom-0 bg-primary text-white pt-4 pb-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>เพิ่มข้อมูลรถคันใหม่</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="action" value="add_vehicle">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small">ประเภทรถ <span class="text-danger">*</span></label>
                        <select name="vehicle_type" class="form-select" required>
                            <option value="" disabled selected>-- เลือกประเภทรถ --</option>
                            <?php foreach($vehicle_types as $type): ?>
                                <option value="<?= htmlspecialchars($type['type_name']) ?>"><?= htmlspecialchars($type['type_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small">ทะเบียนรถ <span class="text-danger">*</span></label>
                        <input type="text" name="license_plate" class="form-control" required placeholder="เช่น กข 1234 กรุงเทพฯ">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small">ยี่ห้อ / รุ่น</label>
                        <input type="text" name="brand" class="form-control" placeholder="เช่น Toyota Hilux Revo">
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small">รายละเอียดเพิ่มเติม</label>
                        <textarea name="details" class="form-control" rows="2" placeholder="เช่น สีขาว, สภาพดี..."></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-white px-4 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-add rounded-pill px-5"><i class="bi bi-save me-1"></i> บันทึกข้อมูล</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php if(file_exists('footer.php')) include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // เคลียร์ค่าตัวแปร success ออกจาก URL ป้องกันการแสดง Alert ซ้ำเวลาย้อนกลับหรือโหลดหน้าใหม่
    if (window.history.replaceState) {
        const url = new URL(window.location.href);
        if (url.searchParams.has('success')) {
            url.searchParams.delete('success');
            window.history.replaceState({}, '', url.href);
        }
    }
</script>
</body>
</html>

<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

require_role([ROLE_HEAD_VEHICLE, ROLE_SUPER_ADMIN]);

$conn = get_db();
$user_role = $_SESSION['role'] ?? '';
$is_head = has_role([ROLE_HEAD_VEHICLE, ROLE_SUPER_ADMIN]);

// 1. จัดการการบันทึกข้อมูล (POST Request)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_qualifications') {
    if (!$is_head) {
        render_403_page("คุณไม่มีสิทธิ์ทำรายการนี้ เฉพาะหัวหน้าฝ่ายยานยนต์เท่านั้น");
    }
    require_csrf();
    $user_id = (int)($_POST['user_id'] ?? 0);
    $allowed_vehicles = isset($_POST['vehicles']) && is_array($_POST['vehicles']) ? $_POST['vehicles'] : [];

    try {
        $conn->beginTransaction();

        // ลบข้อมูลคุณสมบัติเดิมของ user คนนี้ออกก่อน
        $stmtDelete = $conn->prepare("DELETE FROM driver_qualifications WHERE user_id = ?");
        $stmtDelete->execute([$user_id]);

        // บันทึกคุณสมบัติใหม่ที่ถูกเลือก (1 คน เลือกได้หลายประเภท)
        if (!empty($allowed_vehicles)) {
            $stmtInsert = $conn->prepare("INSERT INTO driver_qualifications (user_id, vehicle_type) VALUES (?, ?)");
            foreach ($allowed_vehicles as $v_type) {
                $stmtInsert->execute([$user_id, trim($v_type)]);
            }
        }

        $conn->commit();
        $success_message = "บันทึกข้อมูลคุณสมบัติการขับรถเรียบร้อยแล้ว";
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error_message = "เกิดข้อผิดพลาด: " . $e->getMessage();
    }
}

// 2. ดึงข้อมูลบุคลากรฝ่ายยานยนต์ (อิงจาก Role: staff_vehicle, head_vehicle)
$stmtUsers = $conn->query("
    SELECT id, fname, lname, role, depart_name 
    FROM users 
    WHERE role IN ('staff_vehicle', 'head_vehicle')
    ORDER BY fname ASC
");
$drivers = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

// 3. ดึงข้อมูลประเภทรถทั้งหมดที่มีในระบบ (เพื่อสร้าง Checkbox แบบไม่ซ้ำ)
try {
    $stmt_vtypes = $conn->query("SELECT type_name FROM vehicle_types ORDER BY type_name ASC");
    $vehicle_types = $stmt_vtypes->fetchAll(PDO::FETCH_COLUMN);
    if (empty($vehicle_types)) {
        $stmtVehicles = $conn->query("SELECT DISTINCT vehicle_type FROM vehicles WHERE vehicle_type IS NOT NULL AND vehicle_type != '' ORDER BY vehicle_type ASC");
        $vehicle_types = $stmtVehicles->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    // หากยังไม่มีตาราง vehicle_types ให้ดึงจากตาราง vehicles แทน
    $stmtVehicles = $conn->query("SELECT DISTINCT vehicle_type FROM vehicles WHERE vehicle_type IS NOT NULL AND vehicle_type != '' ORDER BY vehicle_type ASC");
    $vehicle_types = $stmtVehicles->fetchAll(PDO::FETCH_COLUMN);
}

// 4. ดึงข้อมูลคุณสมบัติการขับรถของแต่ละคนเพื่อเอาไปแสดงผล
$stmtQual = $conn->query("SELECT user_id, vehicle_type FROM driver_qualifications");
$qualifications = [];
while ($row = $stmtQual->fetch(PDO::FETCH_ASSOC)) {
    $qualifications[$row['user_id']][] = $row['vehicle_type'];
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>จัดการคุณสมบัติคนขับรถ - ระบบพลับพลาพิธี</title>
       <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
  
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">

<?php include 'menu.php'; ?>
<div class="container py-5">
    <div class="row align-items-center mb-4 pb-3 border-bottom">
        <div class="col-12 col-md-3 text-start mb-3 mb-md-0">
            <a href="<?php echo $is_head ? 'home_head_vehicle.php' : 'home_vehicle.php'; ?>" class="btn btn-white border shadow-sm rounded-pill d-inline-flex align-items-center hover-primary transition-all px-4 py-2 bg-white" style="font-size: 0.95rem; font-weight: 500;">
                <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
            </a>
        </div>
        <div class="col-12 col-md-6 text-center">
            <h2 class="fw-bold mb-0">จัดการคุณสมบัติพนักงานขับรถ</h2>
            <p class="text-muted mt-2 mb-0">กำหนดประเภทรถที่พนักงานขับรถแต่ละคนสามารถขับได้</p>
        </div>
        <div class="col-12 col-md-3"></div>
    </div>

    <?php if (isset($success_message)): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success_message) ?></div>
    <?php endif; ?>
    <?php if (isset($error_message)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error_message) ?></div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle" style="min-width: 800px;">
                    <thead class="table-dark">
                        <tr>
                            <th width="5%">ลำดับ</th>
                            <th width="25%">ชื่อ - นามสกุล</th>
                            <th width="15%">ตำแหน่ง/สิทธิ์</th>
                            <th width="<?php echo $is_head ? '40%' : '55%'; ?>">ประเภทรถที่ขับได้ (ใบอนุญาต)</th>
                            <?php if($is_head): ?>
                            <th width="15%" class="text-center">จัดการ</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($drivers) > 0): ?>
                            <?php foreach ($drivers as $index => $driver): ?>
                                <?php 
                                    $driver_id = $driver['id'];
                                    $driver_qualifications = isset($qualifications[$driver_id]) ? $qualifications[$driver_id] : [];
                                ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($driver['fname'] . ' ' . $driver['lname']) ?></td>
                                    <td>
                                        <?php 
                                            if($driver['role'] == 'head_vehicle') echo 'หัวหน้าฝ่ายยานยนต์';
                                            else if($driver['role'] == 'staff_vehicle') echo 'ฝ่ายยานยนต์';
                                            else echo htmlspecialchars($driver['role']);
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($driver_qualifications)): ?>
                                            <?php foreach ($driver_qualifications as $vq): ?>
                                                <span class="badge bg-primary mb-1"><?= htmlspecialchars($vq) ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">ยังไม่กำหนดประเภทรถ</span>
                                        <?php endif; ?>
                                    </td>
                                <?php if($is_head): ?>
                                    <td class="text-center">
                                        <button class="btn btn-warning btn-sm text-dark" 
                                                onclick="openManageModal(<?= $driver_id ?>, '<?= htmlspecialchars($driver['fname'] . ' ' . $driver['lname']) ?>', '<?= implode(',', $driver_qualifications) ?>')">
                                            กำหนดใบอนุญาต
                                        </button>
                                    </td>
                                <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                            <td colspan="<?php echo $is_head ? '5' : '4'; ?>" class="text-center text-muted">ไม่พบบุคลากรฝ่ายยานยนต์ในระบบ</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="manageDriverModal" tabindex="-1" aria-labelledby="manageDriverModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="manage_drivers.php">
          <?= csrf_field() ?>
          <div class="modal-header bg-warning">
            <h5 class="modal-title" id="manageDriverModalLabel">กำหนดประเภทรถที่ขับได้</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
              <input type="hidden" name="action" value="save_qualifications">
              <input type="hidden" name="user_id" id="modal_user_id" value="">
              
              <div class="mb-3">
                  <label class="form-label fw-bold">ชื่อพนักงานขับรถ:</label>
                  <input type="text" class="form-control" id="modal_driver_name" readonly>
              </div>

              <div class="mb-3">
                  <label class="form-label fw-bold">เลือกประเภทรถที่สามารถขับได้ (เลือกได้หลายข้อ):</label>
                  <div class="border p-3 rounded bg-light">
                      <?php if(count($vehicle_types) > 0): ?>
                          <?php foreach($vehicle_types as $v_type): ?>
                              <div class="form-check mb-2">
                                  <input class="form-check-input vehicle-checkbox" type="checkbox" name="vehicles[]" value="<?= htmlspecialchars($v_type) ?>" id="chk_<?= md5($v_type) ?>">
                                  <label class="form-check-label" for="chk_<?= md5($v_type) ?>">
                                      <?= htmlspecialchars($v_type) ?>
                                  </label>
                              </div>
                          <?php endforeach; ?>
                      <?php else: ?>
                          <span class="text-danger">ยังไม่มีข้อมูลประเภทรถในตาราง vehicles</span>
                      <?php endif; ?>
                  </div>
              </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
            <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
          </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// ฟังก์ชันสำหรับเปิด Modal และติ๊ก Checkbox ตามข้อมูลที่มีอยู่เดิม
function openManageModal(userId, driverName, currentQualificationsStr) {
    // กำหนดค่าลงในฟอร์ม Modal
    document.getElementById('modal_user_id').value = userId;
    document.getElementById('modal_driver_name').value = driverName;

    // เคลียร์ Checkbox ทั้งหมดก่อน
    let checkboxes = document.querySelectorAll('.vehicle-checkbox');
    checkboxes.forEach(chk => chk.checked = false);

    // แยกสตริงเป็น Array (ถ้ามีข้อมูล)
    if (currentQualificationsStr.trim() !== '') {
        let currentQuals = currentQualificationsStr.split(',');
        
        // ติ๊ก Checkbox ตามประเภทรถที่ผู้ใช้มี
        checkboxes.forEach(chk => {
            if (currentQuals.includes(chk.value)) {
                chk.checked = true;
            }
        });
    }

    // เปิด Modal
    var myModal = new bootstrap.Modal(document.getElementById('manageDriverModal'));
    myModal.show();
}
</script>

</body>
</html>

<?php if(file_exists('footer.php')) require 'footer.php'; ?>
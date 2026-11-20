<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

require_role([ROLE_HEAD_VEHICLE, ROLE_SUPER_ADMIN, ROLE_STAFF_VEHICLE]);

$conn = get_db();
$user_role = $_SESSION['role'] ?? '';
$is_head = has_role([ROLE_HEAD_VEHICLE, ROLE_SUPER_ADMIN]);

// ==========================================
// จัดการคำสั่ง POST (CRUD ทำงานและจบในหน้านี้)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_head) {
        render_403_page("คุณไม่มีสิทธิ์ทำรายการนี้ เฉพาะหัวหน้าฝ่ายยานยนต์เท่านั้น");
    }
    require_csrf();

    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        $current_page = $_SERVER['PHP_SELF'];

        if ($action === 'add_type') {
            $type_name = trim($_POST['type_name'] ?? '');
            if (!empty($type_name)) {
                $stmt = $conn->prepare("INSERT INTO vehicle_types (type_name) VALUES (?)");
                $stmt->execute([$type_name]);
                header("Location: " . $current_page . "?success=add_type");
                exit();
            }
        }
        
        elseif ($action === 'edit_type') {
            $old_type_name = trim($_POST['old_type_name'] ?? '');
            $new_type_name = trim($_POST['type_name'] ?? '');
            if (!empty($new_type_name) && !empty($old_type_name) && $old_type_name !== $new_type_name) {
                try {
                    $conn->beginTransaction();
                    $stmt = $conn->prepare("UPDATE vehicle_types SET type_name = ? WHERE type_name = ?");
                    $stmt->execute([$new_type_name, $old_type_name]);
                    $stmt2 = $conn->prepare("UPDATE vehicles SET vehicle_type = ? WHERE vehicle_type = ?");
                    $stmt2->execute([$new_type_name, $old_type_name]);
                    $conn->commit();
                    header("Location: " . $current_page . "?success=edit_type");
                    exit();
                } catch (Exception $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    error_log("Edit type error: " . $e->getMessage());
                    header("Location: " . $current_page . "?error=edit_failed");
                    exit();
                }
            } else {
                header("Location: " . $current_page);
                exit();
            }
        }
        
        elseif ($action === 'delete_type') {
            $type_name = trim($_POST['type_name'] ?? '');
            if (!empty($type_name)) {
                try {
                    $conn->beginTransaction();
                    $stmt = $conn->prepare("DELETE FROM vehicle_types WHERE type_name = ?");
                    $stmt->execute([$type_name]);
                    $stmt2 = $conn->prepare("DELETE FROM vehicles WHERE vehicle_type = ?");
                    $stmt2->execute([$type_name]);
                    $conn->commit();
                    header("Location: " . $current_page . "?success=delete_type");
                    exit();
                } catch (Exception $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    error_log("Delete type error: " . $e->getMessage());
                    header("Location: " . $current_page . "?error=delete_failed");
                    exit();
                }
            }
        }

        elseif ($action === 'add_vehicle') {
            $vehicle_type = trim($_POST['vehicle_type'] ?? '');
            $plate_char = trim($_POST['plate_char'] ?? '');
            $plate_number = trim($_POST['plate_number'] ?? '');
            $plate_province = trim($_POST['plate_province'] ?? '');
            
            $license_plate = $plate_char . ' ' . $plate_number . ' ' . $plate_province;

            if (!empty($vehicle_type) && !empty($plate_char) && !empty($plate_number) && !empty($plate_province)) {
                $stmt = $conn->prepare("INSERT INTO vehicles (vehicle_type, license_plate) VALUES (?, ?)");
                $stmt->execute([$vehicle_type, $license_plate]);
                header("Location: " . $current_page . "?success=add_vehicle");
                exit();
            }
        }

        elseif ($action === 'edit_vehicle') {
            $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
            $plate_char = trim($_POST['plate_char'] ?? '');
            $plate_number = trim($_POST['plate_number'] ?? '');
            $plate_province = trim($_POST['plate_province'] ?? '');
            
            $license_plate = $plate_char . ' ' . $plate_number . ' ' . $plate_province;

            if (!empty($license_plate) && $vehicle_id > 0) {
                $stmt = $conn->prepare("UPDATE vehicles SET license_plate = ? WHERE id = ?");
                $stmt->execute([$license_plate, $vehicle_id]);
                header("Location: " . $current_page . "?success=edit_vehicle");
                exit();
            }
        }
        
        elseif ($action === 'delete_vehicle') {
            $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
            if ($vehicle_id > 0) {
                $stmt = $conn->prepare("DELETE FROM vehicles WHERE id = ?");
                $stmt->execute([$vehicle_id]);
                header("Location: " . $current_page . "?success=delete_vehicle");
                exit();
            }
        }
    }
}

// ==========================================
// 4. ดึงข้อมูลมาแสดงผล
// ==========================================
$stmt_types = $conn->query("SELECT * FROM vehicle_types ORDER BY id ASC");
$vehicle_types = $stmt_types->fetchAll(PDO::FETCH_ASSOC);

if (empty($vehicle_types)) {
    $stmt_distinct = $conn->query("SELECT DISTINCT vehicle_type FROM vehicles WHERE vehicle_type IS NOT NULL AND vehicle_type != ''");
    $distinct_types = $stmt_distinct->fetchAll(PDO::FETCH_COLUMN);
    foreach($distinct_types as $dt) {
        $conn->exec("INSERT INTO vehicle_types (type_name) VALUES (".$conn->quote($dt).")");
    }
    $stmt_types = $conn->query("SELECT * FROM vehicle_types ORDER BY id ASC");
    $vehicle_types = $stmt_types->fetchAll(PDO::FETCH_ASSOC);
}

$stmt_vehicles = $conn->query("SELECT * FROM vehicles ORDER BY id ASC");
$vehicles_db = $stmt_vehicles->fetchAll(PDO::FETCH_ASSOC);

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
    <title>จัดการข้อมูลยานพาหนะ - ระบบพับพลาพิธี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        body { background: #f4f6f9; font-family: 'Prompt', sans-serif; min-height: 100vh; }
        .page-header { background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); color: white; padding: 2.5rem 0; box-shadow: 0 4px 15px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .card-custom { border: none; border-radius: 1.25rem; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.05); background: #ffffff; overflow: hidden; }
        .form-control, .form-select { border-radius: 0.75rem; }
        .select2-container--bootstrap-5 .select2-selection {
            border-radius: 0.75rem;
            min-height: 38px;
        }
        .btn-add { background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%); border: none; color: white; font-weight: 500; box-shadow: 0 4px 10px rgba(14, 165, 233, 0.3); transition: all 0.2s; }
        .btn-add:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(14, 165, 233, 0.4); color: white; }
        .table-hover tbody tr:hover { background-color: #f8fafc; }
        .cursor-not-allowed { cursor: not-allowed; background-color: #e9ecef; }
        .search-bar-container { background-color: #f8f9fa; border-bottom: 1px solid #dee2e6; padding: 15px 25px; }
    </style>
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>

<?php include 'menu.php'; ?>

<div class="content-wrapper">
    <div class="page-header text-center mt-0">
        <div class="container position-relative text-start">
            <div class="row align-items-center"> 
                <div class="col-12 col-md-2 text-md-start mb-3 mb-md-0">
                    <a href="<?php echo $is_head ? 'home_head_vehicle.php' : 'home_vehicle.php'; ?>" class="btn btn-outline-light rounded-pill d-inline-flex align-items-center mt-3 mt-md-0">
                        <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
                    </a>
                </div>
                <div class="col-12 col-md-8 text-center">
                    <h2 class="fw-bold mb-2 mt-3 mt-md-0"><i class="bi bi-gear-fill me-2"></i>จัดการข้อมูลยานพาหนะ</h2>
                    <p class="text-white-50 mb-0">เพิ่ม ลบ แก้ไข ประเภทรถและทะเบียนรถในระบบ</p>
                </div>
                <div class="col-12 col-md-2"></div>
            </div>
        </div>
    </div>

    <div class="container pb-5">
        
        <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center alert-dismissible fade show">
            <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
            <div><h6 class="mb-0 fw-bold">บันทึกข้อมูลสำเร็จ!</h6></div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card custom-card mb-4">
            <div class="card-header bg-white p-4 border-bottom shadow-sm">
                <div class="row align-items-center gy-3">
                    <div class="col-md-8 d-flex align-items-center flex-wrap gap-2">
                        <label class="fw-bold text-secondary me-2 mb-0">ตัวกรอง :</label>
                        <?php if(empty($vehicle_types)): ?>
                            <span class="text-muted small">-- ยังไม่มีประเภทรถ --</span>
                        <?php else: ?>
                            <button class="btn btn-sm btn-primary filter-btn rounded-pill px-3 shadow-sm" data-type="all" onclick="showTable('all')">รถทั้งหมด</button>
                                <?php foreach($vehicle_types as $type): ?>
                                    <button class="btn btn-sm btn-outline-primary filter-btn rounded-pill px-3 shadow-sm" data-type="<?= htmlspecialchars($type['type_name']) ?>" onclick="showTable('<?= htmlspecialchars($type['type_name']) ?>')"><?= htmlspecialchars($type['type_name']) ?></button>
                                <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                    <div class="col-md-4 text-md-end">
                        <?php if($is_head): ?>
                        <button class="btn btn-outline-primary rounded-pill btn-sm shadow-sm px-3 mt-2 mt-md-0" onclick="openAddTypeModal()">
                            <i class="bi bi-tags-fill me-1"></i> เพิ่มประเภทใหม่
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php if(!empty($vehicle_types)): ?>
            <div class="search-bar-container d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center" style="max-width: 400px; width: 100%;">
                    <i class="bi bi-search text-muted me-2 fs-5"></i>
                    <input type="text" id="searchInput" class="form-control rounded-pill border-secondary border-opacity-25 shadow-sm" placeholder="ค้นหาป้ายทะเบียนรถ..." onkeyup="filterTable()">
                </div>
                <small class="text-muted"><i class="bi bi-info-circle me-1"></i>ค้นหาเฉพาะในประเภทที่เลือก</small>
            </div>
            <?php endif; ?>
        </div>
        <div id="vehicleListsContainer">
            <?php if(empty($vehicle_types)): ?>
                <div class="card custom-card">
                    <div class="card-body text-center text-muted py-5">
                        <i class="bi bi-truck fs-1 d-block mb-3 opacity-50"></i>
                        <h6>ยังไม่มีข้อมูลประเภทรถในระบบ</h6>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach($vehicle_types as $type): $tname = $type['type_name']; ?>
                    <div class="card custom-card mb-4 type-container shadow-sm" data-type="<?= htmlspecialchars($tname) ?>" style="display:none;">
                        <div class="card-header bg-white p-4 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <h5 class="fw-bold text-dark mb-0"><i class="bi bi-collection-fill text-primary me-2"></i>รายการรถ: <?= htmlspecialchars($tname) ?></h5>
                            <?php if($is_head): ?>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-warning btn-sm rounded-pill px-3 shadow-sm" onclick="openEditTypeModal('<?= htmlspecialchars($tname, ENT_QUOTES) ?>')" title="แก้ไขชื่อประเภทนี้">
                                    <i class="bi bi-pencil-square"></i> <span class="d-none d-sm-inline">แก้ไขประเภท</span>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-sm" onclick="deleteSpecificType('<?= htmlspecialchars($tname, ENT_QUOTES) ?>')" title="ลบประเภทนี้">
                                    <i class="bi bi-trash3"></i> <span class="d-none d-sm-inline">ลบประเภท</span>
                                </button>
                                <button class="btn btn-add rounded-pill px-3 btn-sm shadow-sm" onclick="openAddVehicleModal('<?= htmlspecialchars($tname, ENT_QUOTES) ?>')" title="เพิ่มรถในประเภทนี้">
                                    <i class="bi bi-plus-lg me-1"></i> เพิ่มรถ
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="card-body p-0">
                            <?php if(empty($vehicles_by_type[$tname])): ?>
                                <div class="text-center text-muted py-5">ยังไม่มีรถในประเภทนี้ <?php if($is_head) echo '(คลิกปุ่ม "+ เพิ่มรถ" ที่มุมขวาเพื่อเพิ่มข้อมูล)'; ?></div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light text-muted small">
                                            <tr>
                                                <th class="ps-5 w-25">ลำดับ</th>
                                                <th class="<?php echo $is_head ? 'w-50' : 'w-75'; ?>">ป้ายทะเบียนรถ</th>
                                                <?php if($is_head): ?>
                                                <th class="text-center pe-5 w-25">จัดการ</th>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                       <tbody>
                                            <?php $row_num = 1; foreach($vehicles_by_type[$tname] as $v): ?>
                                            <tr class="vehicle-row">
                                                <td class="ps-5 text-muted"><?= $row_num++ ?></td>
                                                <td class="fw-bold text-dark fs-6 vehicle-plate">
                                                    <div class="bg-light d-inline-block px-4 py-2 rounded-3 border shadow-sm">
                                                        <?= htmlspecialchars($v['license_plate']) ?>
                                                    </div>
                                                </td>
                                                <?php if($is_head): ?>
                                                <td class="text-center pe-5">
                                                    <div class="d-flex justify-content-center align-items-center">
                                                        <button type="button" class="btn btn-sm btn-outline-warning border-0 rounded-circle me-1" 
                                                            onclick="openEditVehicleModal(<?= $v['id'] ?>, '<?= htmlspecialchars($v['license_plate'], ENT_QUOTES) ?>')">
                                                            <i class="bi bi-pencil-square fs-5"></i>
                                                        </button>
                                                        <form method="POST" onsubmit="return confirm('ยืนยันการลบรถทะเบียน <?= htmlspecialchars($v['license_plate'], ENT_QUOTES) ?> ?');" class="m-0">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="delete_vehicle">
                                                            <input type="hidden" name="vehicle_id" value="<?= $v['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger border-0 rounded-circle"><i class="bi bi-trash3-fill fs-5"></i></button>
                                                        </form>
                                                    </div>
                                                </td>
                                                <?php endif; ?>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                    <div class="no-result-msg text-center text-muted py-4" style="display: none;">
                                        <i class="bi bi-search fs-3 d-block mb-2 opacity-50"></i>
                                        ไม่พบข้อมูลป้ายทะเบียนที่ค้นหา
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<form id="formDeleteType" method="POST" style="display:none;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="delete_type">
    <input type="hidden" name="type_name" id="delete_type_name">
</form>

<div class="modal fade" id="addTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom-0 bg-primary text-white pt-4 pb-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-tags-fill me-2"></i>สร้างประเภทรถใหม่</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="action" value="add_type">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small">ชื่อประเภทรถ <span class="text-danger">*</span></label>
                        <input type="text" name="type_name" class="form-control" required placeholder="เช่น รถกระบะ 4 ประตู, รถห้องสรง...">
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

<div class="modal fade" id="editTypeModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom-0 bg-warning text-dark pt-4 pb-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>แก้ไขชื่อประเภทรถ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="action" value="edit_type">
                    <input type="hidden" name="old_type_name" id="old_type_name">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small">ชื่อประเภทรถใหม่ <span class="text-danger">*</span></label>
                        <input type="text" name="type_name" id="edit_type_name" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-white px-4 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-5 fw-bold"><i class="bi bi-save me-1"></i> บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="addVehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom-0 bg-primary text-white pt-4 pb-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-plus-circle me-2"></i>เพิ่มข้อมูลรถคันใหม่</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="action" value="add_vehicle">
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small">เพิ่มเข้ารถประเภท</label>
                        <input type="text" id="modal_add_vehicle_type_display" class="form-control text-muted fw-bold cursor-not-allowed" readonly tabindex="-1">
                        <input type="hidden" name="vehicle_type" id="modal_add_vehicle_type">
                    </div>
                    
                    <label class="form-label fw-bold text-secondary small">ทะเบียนรถ <span class="text-danger">*</span></label>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <input type="text" name="plate_char" class="form-control text-center" required placeholder="พยัญชนะ" title="เช่น กข หรือ 1กข">
                        </div>
                        <div class="col-4">
                            <input type="text" name="plate_number" class="form-control text-center" required placeholder="เลขทะเบียน" title="เช่น 1234">
                        </div>
                        <div class="col-4">
                            <select name="plate_province" id="plate_province" class="form-select text-center" required>
                                <option value="" selected disabled>-- จังหวัด --</option>
                            </select>
                        </div>
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

<div class="modal fade" id="editVehicleModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 rounded-4 shadow-lg">
            <div class="modal-header border-bottom-0 bg-warning text-dark pt-4 pb-3 px-4 rounded-top-4">
                <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square me-2"></i>แก้ไขป้ายทะเบียนรถ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <div class="modal-body p-4 bg-light">
                    <input type="hidden" name="action" value="edit_vehicle">
                    <input type="hidden" name="vehicle_id" id="edit_vehicle_id">
                    
                    <label class="form-label fw-bold text-secondary small">ทะเบียนรถ <span class="text-danger">*</span></label>
                    <div class="row g-2 mb-3">
                        <div class="col-4">
                            <input type="text" name="plate_char" id="edit_plate_char" class="form-control text-center" required placeholder="พยัญชนะ">
                        </div>
                        <div class="col-4">
                            <input type="text" name="plate_number" id="edit_plate_number" class="form-control text-center" required placeholder="เลขทะเบียน">
                        </div>
                        <div class="col-4">
                            <select name="plate_province" id="edit_plate_province" class="form-select text-center" required>
                                <option value="" selected disabled>-- จังหวัด --</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 bg-white px-4 py-3 rounded-bottom-4">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-warning rounded-pill px-5 fw-bold"><i class="bi bi-save me-1"></i> บันทึกการแก้ไข</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function checkBootstrap() {
        if (typeof bootstrap === 'undefined') {
            alert('⚠️ ไม่สามารถโหลดไฟล์สคริปต์ได้!\nกรุณาตรวจสอบการเชื่อมต่ออินเทอร์เน็ต หากใช้งานผ่าน Intranet คุณต้องดาวน์โหลดไฟล์ Bootstrap JS มาไว้ในเซิร์ฟเวอร์ครับ');
            return false;
        }
        return true;
    }

    if (window.history.replaceState) {
        const url = new URL(window.location.href);
        if (url.searchParams.has('success')) {
            url.searchParams.delete('success');
            window.history.replaceState({}, '', url.href);
        }
    }

    let currentSelectedType = 'all';

    function showTable(selectedType) {
        currentSelectedType = selectedType;
        
        document.querySelectorAll('.filter-btn').forEach(btn => {
            if (btn.getAttribute('data-type') === selectedType) {
                btn.classList.remove('btn-outline-primary');
                btn.classList.add('btn-primary');
            } else {
                btn.classList.remove('btn-primary');
                btn.classList.add('btn-outline-primary');
            }
        });

        const searchInput = document.getElementById('searchInput');
        if (searchInput) searchInput.value = '';

        document.querySelectorAll('.type-container').forEach(el => {
            if(selectedType === 'all' || el.getAttribute('data-type') === selectedType) {
                el.style.display = 'flex'; // ปรับเป็น flex/block ให้แสดงเป็น card
                el.classList.add('d-flex', 'flex-column');
                el.querySelectorAll('.vehicle-row').forEach(row => row.style.display = '');
                const noResultMsg = el.querySelector('.no-result-msg');
                if (noResultMsg) noResultMsg.style.display = 'none';
            } else {
                el.style.display = 'none';
                el.classList.remove('d-flex', 'flex-column');
            }
        });
        
        localStorage.setItem('lastSelectedType', selectedType);
    }

    function filterTable() {
        const input = document.getElementById('searchInput').value.toLowerCase();
        const activeContainers = document.querySelectorAll('.type-container[style*="display: flex"], .type-container[style*="display: block"]');
        
        activeContainers.forEach(activeContainer => {
            const rows = activeContainer.querySelectorAll('.vehicle-row');
            let visibleCount = 0;

            rows.forEach(row => {
                const plateText = row.querySelector('.vehicle-plate').textContent.toLowerCase();
                if (plateText.indexOf(input) > -1) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            const noResultMsg = activeContainer.querySelector('.no-result-msg');
            if (noResultMsg) {
                noResultMsg.style.display = (visibleCount === 0 && input !== '') ? 'block' : 'none';
            }
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        const hasTypes = document.querySelector('.filter-btn');
        if (hasTypes) {
            let savedType = localStorage.getItem('lastSelectedType') || 'all';
            const btnExists = document.querySelector(`.filter-btn[data-type="${savedType}"]`);
            if (!btnExists) {
                savedType = 'all';
            }
            showTable(savedType);
        }
    });

    function openAddTypeModal() {
        if(!checkBootstrap()) return;
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('addTypeModal'));
        modal.show();
    }

    // ฟังก์ชันเปิด Modal แก้ไขโดยรับชื่อประเภทตรงๆ
    function openEditTypeModal(typeName) {
        if(!checkBootstrap()) return;
        document.getElementById('old_type_name').value = typeName;
        document.getElementById('edit_type_name').value = typeName;
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editTypeModal'));
        modal.show();
    }

    function openAddVehicleModal(typeName = null) {
        if(!checkBootstrap()) return;
        const hiddenEl = document.getElementById('modal_add_vehicle_type');
        const displayEl = document.getElementById('modal_add_vehicle_type_display');
        
        let selectedType = typeName;
        if (!selectedType && currentSelectedType !== 'all') {
            selectedType = currentSelectedType;
        }
        
        if (hiddenEl && displayEl) {
            hiddenEl.value = selectedType || '';
            displayEl.value = selectedType || '';
        }
        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('addVehicleModal'));
        modal.show();
    }

    function openEditVehicleModal(id, plate) {
        if(!checkBootstrap()) return;
        document.getElementById('edit_vehicle_id').value = id;

        let parts = plate.split(' ').filter(p => p.trim() !== '');
        let char = '', num = '', prov = '';

        if (parts.length >= 3) {
            char = parts[0];
            num = parts[1];
            prov = parts.slice(2).join(' '); 
        } else if (parts.length === 2) {
            char = parts[0];
            num = parts[1];
        } else {
            char = plate; 
        }

        document.getElementById('edit_plate_char').value = char;
        document.getElementById('edit_plate_number').value = num;
        
        let $editProv = $('#edit_plate_province');
        if ($editProv.find("option[value='" + prov + "']").length) {
            $editProv.val(prov).trigger('change');
        } else {
            if (prov) {
                var newOption = new Option(prov, prov, true, true);
                $editProv.append(newOption).trigger('change');
            } else {
                $editProv.val('').trigger('change');
            }
        }

        const modal = bootstrap.Modal.getOrCreateInstance(document.getElementById('editVehicleModal'));
        modal.show();
    }

    // ฟังก์ชันลบประเภทโดยรับชื่อประเภทตรงๆ
    function deleteSpecificType(typeName) {
        if(confirm('ยืนยันการลบประเภทรถ "' + typeName + '" ใช่หรือไม่?\n\n*คำเตือน: รถยนต์ทุกคันที่อยู่ในประเภทนี้จะถูกลบไปด้วยทั้งหมด!')) {
            document.getElementById('delete_type_name').value = typeName;
            if(currentSelectedType === typeName) {
                localStorage.removeItem('lastSelectedType'); 
            }
            document.getElementById('formDeleteType').submit();
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const jsonUrl = './thai-province-data-master/api/latest/province_with_district_and_sub_district.json';
        let thaiData = []; 

        fetch(jsonUrl)
            .then(response => {
                if (!response.ok) throw new Error('ไม่พบไฟล์ JSON');
                return response.json();
            })
            .then(data => {
                thaiData = data;
                
                const addProvSel = document.getElementById('plate_province');
                const editProvSel = document.getElementById('edit_plate_province');
                
                let options = '<option value="" selected disabled>-- จังหวัด --</option>';
                thaiData.sort((a, b) => a.name_th.localeCompare(b.name_th, 'th')).forEach(p => {
                    options += `<option value="${p.name_th}">${p.name_th}</option>`;
                });
                
                if(addProvSel) {
                    addProvSel.innerHTML = options;
                    $(addProvSel).select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#addVehicleModal') });
                }
                
                if(editProvSel) {
                    editProvSel.innerHTML = options;
                    $(editProvSel).select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $('#editVehicleModal') });
                }
            })
            .catch(error => console.error('Fetch Error:', error));
    });
</script>
<?php include 'footer.php'; ?>
</body>
</html>
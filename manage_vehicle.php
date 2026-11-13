<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

require_role([ROLE_HEAD_VEHICLE, ROLE_SUPER_ADMIN, ROLE_STAFF_VEHICLE]);

$conn = get_db();
$user_role = $_SESSION['role'] ?? '';
$is_head = has_role([ROLE_HEAD_VEHICLE, ROLE_SUPER_ADMIN]);

$job_id = clean_int($_GET['job_id'] ?? $_POST['job_id'] ?? 0);

if (!$job_id) {
    die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'><h2>กรุณาระบุรหัสงาน (job_id)</h2></div>");
}

// 1. ดึงข้อมูลหนังสือ
$stmt_doc = $conn->prepare("SELECT doc_name, job_type FROM documents WHERE id = ?");
$stmt_doc->execute([$job_id]);
$document = $stmt_doc->fetch();

if (!$document) {
    die("<div style='text-align:center; margin-top:50px; font-family:sans-serif;'><h2>ไม่พบข้อมูลหนังสือรหัสนี้</h2></div>");
}

// ---------------------------------------------------------
// ตรวจสอบ Foreign Key ของ Jobs
$stmt_check_job = $conn->prepare("SELECT id FROM jobs WHERE id = ?");
$stmt_check_job->execute([$job_id]);
if (!$stmt_check_job->fetch()) {
    $job_type = !empty($document['job_type']) ? $document['job_type'] : 'อื่นๆ';
    $stmt_insert_job = $conn->prepare("INSERT INTO jobs (id, job_name, job_type) VALUES (?, ?, ?)");
    $stmt_insert_job->execute([$job_id, $document['doc_name'], $job_type]);
}

// 2. ดึง "ชุดปฏิบัติงาน" (เฉพาะชุดที่มีพนักงานฝ่ายปฏิบัติการ และไม่ใช่ผู้ประสานงาน)
$stmt_sets = $conn->prepare("
    SELECT DISTINCT ja.staff_role 
    FROM job_assignments ja
    INNER JOIN users u ON ja.staff_name = CONCAT(u.fname, ' ', u.lname)
    INNER JOIN documents d ON ja.job_id = d.id
    WHERE ja.job_id = ? 
      AND u.role IN ('staff_operation', 'head_operation') 
      AND (d.sent_to IS NULL OR u.id != d.sent_to)
");
$stmt_sets->execute([$job_id]);
$existing_roles = $stmt_sets->fetchAll();

$operation_sets = [];
foreach ($existing_roles as $role) {
    if (!empty($role['staff_role'])) {
        $operation_sets[] = $role['staff_role'];
    }
}
// รวมชื่อชุดทั้งหมดเป็น String เดียว (เช่น "ชุดที่ 1, ชุดที่ 2")
$operation_sets_str = implode(', ', $operation_sets);

// ดึงรายชื่อบุคคลในชุดปฏิบัติงานเพื่อนำไปแสดง
$stmt_members = $conn->prepare("
    SELECT ja.staff_role, ja.staff_name
    FROM job_assignments ja
    INNER JOIN users u ON ja.staff_name = CONCAT(u.fname, ' ', u.lname)
    INNER JOIN documents d ON ja.job_id = d.id
    WHERE ja.job_id = ?
      AND u.role IN ('staff_operation', 'head_operation')
      AND (d.sent_to IS NULL OR u.id != d.sent_to)
");
$stmt_members->execute([$job_id]);
$set_members_raw = $stmt_members->fetchAll(PDO::FETCH_ASSOC);
$set_members = [];
foreach($set_members_raw as $row) {
    $set_members[$row['staff_role']][] = $row['staff_name'];
}

// 3. ดึงรายชื่อฝ่ายยานพาหนะ (ปรับ Query ให้ตรงกับ manage_drivers.php)
$stmt_veh = $conn->query("
    SELECT id, fname, lname, role, depart_name 
    FROM users 
    WHERE role IN ('staff_vehicle', 'head_vehicle')
    ORDER BY fname ASC
");
$veh_staff_raw = $stmt_veh->fetchAll(PDO::FETCH_ASSOC);

// ดึงคุณสมบัติการขับรถ
$stmt_qual = $conn->query("SELECT user_id, vehicle_type FROM driver_qualifications");
$driver_quals = $stmt_qual->fetchAll(PDO::FETCH_ASSOC);
$quals_by_driver = [];
foreach($driver_quals as $q) {
    $quals_by_driver[$q['user_id']][] = $q['vehicle_type'];
}

// ดึงงานทั้งหมดของพนักงานขับรถแต่ละคนเพื่อนำไปแสดงใน Modal
$jobs_by_driver = [];
$stmt_driver_jobs = $conn->query("
    SELECT va.driver_name, d.id as doc_id, d.doc_name, d.doc_number, d.dpt_work_start, d.dpt_work_end
    FROM vehicle_assignments va
    JOIN documents d ON va.job_id = d.id
    ORDER BY d.dpt_work_start ASC
");
$all_driver_jobs = $stmt_driver_jobs->fetchAll(PDO::FETCH_ASSOC);

$thai_months_short = [
    1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
    5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
    9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
];

foreach ($all_driver_jobs as $dj) {
    $driver = $dj['driver_name'];
    $start = $dj['dpt_work_start'];
    $end = $dj['dpt_work_end'];
    
    $date_text = "ยังไม่ระบุเวลาทำการ";
    if ($start && $end) {
        $s_d = date('j', strtotime($start));
        $s_m = $thai_months_short[(int)date('m', strtotime($start))];
        $s_y = date('Y', strtotime($start)) + 543;
        $e_d = date('j', strtotime($end));
        $e_m = $thai_months_short[(int)date('m', strtotime($end))];
        $e_y = date('Y', strtotime($end)) + 543;

        if ($start == $end) {
            $date_text = "$s_d $s_m $s_y";
        } elseif ($s_m == $e_m && $s_y == $e_y) {
            $date_text = "$s_d - $e_d $s_m $s_y";
        } elseif ($s_y == $e_y) {
            $date_text = "$s_d $s_m - $e_d $e_m $s_y";
        } else {
            $date_text = "$s_d $s_m $s_y - $e_d $e_m $e_y";
        }
    } elseif ($start) {
        $s_d = date('j', strtotime($start));
        $s_m = $thai_months_short[(int)date('m', strtotime($start))];
        $s_y = date('Y', strtotime($start)) + 543;
        $date_text = "เริ่ม $s_d $s_m $s_y";
    }

    $jobs_by_driver[$driver][] = [
        'doc_name' => $dj['doc_name'],
        'date_text' => $date_text,
        'doc_number' => $dj['doc_number']
    ];
}

// ดึงวันที่ของงานปัจจุบันเพื่อเช็คงานซ้อน
$stmt_dates = $conn->prepare("SELECT dpt_work_start, dpt_work_end FROM documents WHERE id = ?");
$stmt_dates->execute([$job_id]);
$job_dates = $stmt_dates->fetch(PDO::FETCH_ASSOC);
$current_start = $job_dates['dpt_work_start'] ?? null;
$current_end = $job_dates['dpt_work_end'] ?? null;

$veh_staff = [];
foreach ($veh_staff_raw as $v) {
    $driver_name = $v['fname'] . ' ' . $v['lname'];
    $overlapping_jobs = [];
    
    if ($current_start && $current_end) {
        $stmt_overlap = $conn->prepare("
            SELECT d.doc_name, d.dpt_work_start, d.dpt_work_end 
            FROM vehicle_assignments va
            JOIN documents d ON va.job_id = d.id
            WHERE va.driver_name = ?
              AND d.id != ?
              AND d.dpt_work_start <= ? AND d.dpt_work_end >= ?
        ");
        $stmt_overlap->execute([$driver_name, $job_id, $current_end, $current_start]);
        $overlapping_jobs = $stmt_overlap->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $veh_staff[] = [
        'id' => $v['id'],
        'name' => $driver_name,
        'overlaps' => $overlapping_jobs,
        'qualifications' => $quals_by_driver[$v['id']] ?? []
    ];
}
$veh_staff_json = json_encode($veh_staff, JSON_UNESCAPED_UNICODE);

// ดึงข้อมูลทะเบียนรถแยกตามประเภทจากตาราง vehicles
$stmt_vehicles = $conn->prepare("SELECT vehicle_type, license_plate FROM vehicles");
$stmt_vehicles->execute();
$vehicles_db = $stmt_vehicles->fetchAll(PDO::FETCH_ASSOC);

$vehicles_grouped = [];
foreach ($vehicles_db as $v) {
    // จัดกลุ่มตามประเภทรถ
    $vehicles_grouped[$v['vehicle_type']][] = $v['license_plate'];
}
// แปลงเป็น JSON เพื่อให้ JavaScript นำไปใช้งานต่อ
$vehicles_json = json_encode($vehicles_grouped, JSON_UNESCAPED_UNICODE);

// ดึงข้อมูลประเภทรถทั้งหมดที่มีในระบบ (ปรับ Query ให้ตรงกับ manage_drivers.php)
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

// 4. บันทึกข้อมูล
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!$is_head) {
        render_403_page("คุณไม่มีสิทธิ์ทำรายการนี้ เฉพาะหัวหน้าฝ่ายยานยนต์เท่านั้น");
    }
    require_csrf();
    try {
        $vehicle_type = trim($_POST['vehicle_type'] ?? '');
        $license_plate = trim($_POST['license_plate'] ?? '');
        $driver_name = trim($_POST['driver_name'] ?? '');
        $assigned_set = trim($_POST['assigned_set'] ?? '');
        $assistant_1 = trim($_POST['assistant_1'] ?? '') ?: null;
        $assistant_2 = trim($_POST['assistant_2'] ?? '') ?: null;

        if (empty($vehicle_type) || empty($license_plate) || empty($driver_name) || empty($assigned_set)) {
            throw new Exception("กรุณากรอกข้อมูลให้ครบถ้วน");
        }

        $stmt = $conn->prepare("INSERT INTO vehicle_assignments (job_id, vehicle_type, license_plate, driver_name, assistant_1, assistant_2) 
                                VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$job_id, "$vehicle_type ($assigned_set)", $license_plate, $driver_name, $assistant_1, $assistant_2]);

        header("Location: manage_vehicle.php?job_id=$job_id&success=1");
        exit();
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// 5. รายการรถที่จัดแล้ว
$stmt_saved = $conn->prepare("SELECT * FROM vehicle_assignments WHERE job_id = ? ORDER BY id ASC");
$stmt_saved->execute([$job_id]);
$assigned_vehicles = $stmt_saved->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>จัดการยานพาหนะ - ระบบพับพลาพิธี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    
    <style>
        :root {
            --primary-color: #0ea5e9;
            --primary-hover: #0284c7;
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
        }

        body { 
            background-color: var(--bg-color); 
            font-family: 'Prompt', sans-serif; 
            color: var(--text-main);
        }

        /* Typography */
        .page-title { font-weight: 700; color: var(--text-main); letter-spacing: -0.5px; }
        .section-title { font-weight: 600; color: var(--primary-color); font-size: 1.1rem; }

        /* Cards */
        .card-custom { 
            background: var(--card-bg); 
            border-radius: 20px; 
            border: none; 
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.04); 
            overflow: hidden;
        }

        /* Form Controls */
        .form-label { font-weight: 500; color: #475569; margin-bottom: 0.5rem; font-size: 0.95rem; }
        .form-control, .form-select { 
            border-radius: 12px; 
            border: 1.5px solid #e2e8f0; 
            padding: 0.75rem 1rem; 
            font-size: 0.95rem; 
            background-color: #f8fafc; 
            transition: all 0.3s ease; 
        }
        .form-control:focus, .form-select:focus { 
            border-color: var(--primary-color); 
            box-shadow: 0 0 0 4px rgba(14, 165, 233, 0.15); 
            background-color: #fff; 
        }
        
        /* Select2 Theme adjustments */
        .select2-container--bootstrap-5 .select2-selection {
            border-radius: 12px;
            border: 1.5px solid #e2e8f0;
            padding: 0.35rem 0.5rem;
            min-height: 46px;
            background-color: #f8fafc;
        }
        
        /* สไตล์สำหรับ input readonly ให้ดูเป็นสีเทา */
        .form-control[readonly] {
            background-color: #e9ecef;
            cursor: not-allowed;
            border-color: #ced4da;
            color: #495057;
            font-weight: 500;
        }

        /* Primary Button */
        .btn-primary-custom { 
            background: linear-gradient(135deg, var(--primary-color) 0%, #2563eb 100%); 
            border: none; 
            border-radius: 12px; 
            color: white; 
            font-weight: 600; 
            padding: 0.8rem 1.5rem; 
            transition: all 0.3s ease; 
        }
        .btn-primary-custom:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 8px 20px rgba(37, 99, 235, 0.25); 
            color: white; 
        }

        /* Vehicle List Cards */
        .vehicle-card { 
            background: var(--card-bg); 
            border-radius: 16px; 
            border: 1px solid #f1f5f9; 
            border-left: 5px solid var(--primary-color); 
            transition: all 0.3s ease; 
            cursor: pointer; 
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .vehicle-card:hover { 
            transform: translateX(6px); 
            box-shadow: 0 8px 15px rgba(0,0,0,0.05); 
            border-left-color: #2563eb; 
            background-color: #f8fafc;
        }
        .icon-circle { 
            width: 45px; 
            height: 45px; 
            background: #f0f9ff; 
            color: var(--primary-color); 
            border-radius: 50%; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            font-size: 1.25rem; 
            margin-right: 1.2rem; 
        }

        /* Empty State */
        .empty-state { text-align: center; padding: 3rem 1rem; color: var(--text-muted); }
        .empty-state i { font-size: 3.5rem; color: #cbd5e1; margin-bottom: 1rem; display: block; }

        /* Success Alert */
        .alert-success-custom {
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            font-weight: 500;
        }

        /* ซ่อนฟอร์มเบื้องต้นหากยังไม่มีข้อมูลชุด */
        <?php if(empty($operation_sets)): ?>
        #main-content { display: none; }
        <?php endif; ?>
    </style>
</head>
<body>

<?php include 'menu.php'; ?>

<div class="container py-5" id="main-content">
    <div class="mb-3">
        <a href="select_mission.php" class="text-decoration-none text-secondary">
            <i class="bi bi-arrow-left-circle me-1"></i> ย้อนกลับ
        </a>
    </div>
    <div class="text-center mb-5">
        <h2 class="page-title mb-2">จัดการยานพาหนะ</h2>
        <p class="text-muted"><i class="bi bi-info-circle me-1"></i> เลือกรถและพนักงานขับรถให้ตรงกับชุดปฏิบัติงาน</p>
    </div>

    <?php if(isset($_GET['success'])): ?>
        <div id="success-alert" class="alert alert-success-custom shadow-sm mb-4 text-center">
            <i class="bi bi-check-circle-fill me-2"></i> บันทึกยานพาหนะเข้าชุดปฏิบัติงานสำเร็จ!
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card card-custom p-4 p-md-5">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($job_id); ?>">
                    
                    <div class="d-flex align-items-center mb-4 pb-3 border-bottom">
                        <div class="icon-circle" style="width: 40px; height: 40px; margin-right: 0.8rem;">
                            <i class="bi bi-plus-lg"></i>
                        </div>
                        <h5 class="section-title mb-0">ขั้นตอนการจัดรถ</h5>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">1. ชุดปฏิบัติงานที่รับผิดชอบภารกิจนี้ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" value="สำหรับ: <?php echo htmlspecialchars($operation_sets_str); ?>" readonly>
                        <input type="hidden" name="assigned_set" value="<?php echo htmlspecialchars($operation_sets_str); ?>">
                        <div class="form-text text-muted mt-1" style="font-size: 0.8rem;">
                            <i class="bi bi-lock-fill"></i> ข้อมูลชุดปฏิบัติงานถูกกำหนดโดยหัวหน้าฝ่ายปฏิบัติการ (ไม่สามารถแก้ไขได้)
                        </div>
                        
                        <div class="accordion mt-2" id="accordionSetMembers">
                            <div class="accordion-item border-0 bg-light rounded-3">
                                <h2 class="accordion-header">
                                    <button class="accordion-button collapsed bg-light rounded-3 py-2 px-3 shadow-none text-primary fw-bold" type="button" data-bs-toggle="collapse" data-bs-target="#collapseMembers" aria-expanded="false" style="font-size: 0.85rem;">
                                        <i class="bi bi-people-fill me-2"></i> กดขยายเพื่อดูผู้ปฏิบัติงานในชุด
                                    </button>
                                </h2>
                                <div id="collapseMembers" class="accordion-collapse collapse" data-bs-parent="#accordionSetMembers">
                                    <div class="accordion-body px-3 py-2" style="font-size: 0.85rem;">
                                        <?php foreach($set_members as $role => $members): ?>
                                            <div class="fw-bold text-dark mt-2 mb-1"><?= htmlspecialchars($role) ?></div>
                                            <ul class="list-unstyled ps-3 mb-0 text-muted">
                                                <?php foreach($members as $m): ?>
                                                    <li><i class="bi bi-person-check me-1 text-success"></i> <?= htmlspecialchars($m) ?></li>
                                                <?php endforeach; ?>
                                            </ul>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">ประเภทรถ <span class="text-danger">*</span></label>
                            <select name="vehicle_type" id="vehicleTypeSelect" class="form-select select2-custom" required>
                                <option value="" selected disabled>-- เลือกประเภท --</option>
                                <?php foreach($vehicle_types as $v_type): ?>
                                    <option value="<?php echo htmlspecialchars($v_type); ?>"><?php echo htmlspecialchars($v_type); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ทะเบียนรถ <span class="text-danger">*</span></label>
                            <select name="license_plate" id="licensePlateSelect" class="form-select select2-custom" required disabled>
                                <option value="" selected disabled>-- กรุณาเลือกประเภทรถก่อน --</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-5">
                        <div class="d-flex justify-content-between align-items-end mb-2">
                            <label class="form-label mb-0">คนขับรถ <span class="text-danger">*</span></label>
                            <button type="button" class="btn btn-sm btn-outline-info rounded-pill" data-bs-toggle="modal" data-bs-target="#driverJobsModal">
                                <i class="bi bi-calendar-check me-1"></i> ดูตารางงานคนขับรถ
                            </button>
                        </div>
                        <select name="driver_name" id="driverSelect" class="form-select select2-custom" required disabled>
                            <option value="" selected disabled>-- กรุณาเลือกประเภทรถก่อน --</option>
                        </select>
                        <div id="driverOverlapInfo" class="mt-2 text-danger small d-none p-2 bg-danger bg-opacity-10 rounded border border-danger border-opacity-25">
                            <i class="bi bi-exclamation-triangle-fill me-1"></i> <strong>แจ้งเตือน:</strong> พนักงานขับรถท่านนี้มีงานซ้อนในช่วงเวลาเดียวกับภารกิจนี้
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-custom w-100 shadow-sm">
                        <i class="bi bi-save me-2"></i> บันทึกและจัดรถเข้าชุด
                    </button>
                </form>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card card-custom p-4 p-md-5 sticky-top" style="top: 20px;">
                <h5 class="page-title mb-4 pb-2 border-bottom fs-5">ยานพาหนะที่จัดเสร็จแล้ว</h5>
                
                <?php if(empty($assigned_vehicles)): ?>
                    <div class="empty-state">
                        <i class="bi bi-inboxes"></i>
                        <h6 class="fw-bold text-secondary">ยังไม่มีการจัดยานพาหนะ</h6>
                        <p class="text-muted small">กรอกข้อมูลด้านซ้ายเพื่อเพิ่มรถเข้าสู่ระบบ</p>
                    </div>
                <?php else: ?>
                    <div class="vehicle-list">
                        <?php foreach($assigned_vehicles as $v): ?>
                            <div class="vehicle-card" data-bs-toggle="modal" data-bs-target="#vModal<?php echo $v['id']; ?>">
                                <div class="d-flex align-items-center w-100">
                                    <div class="icon-circle">
                                        <i class="bi bi-truck"></i>
                                    </div>
                                    <div class="flex-grow-1">
                                        <h6 class="fw-bold mb-1 text-dark" style="font-size: 0.95rem;"><?php echo htmlspecialchars($v['vehicle_type']); ?></h6>
                                        <div class="text-muted" style="font-size: 0.85rem;">
                                            <i class="bi bi-card-text me-1"></i> <?php echo htmlspecialchars($v['license_plate']); ?>
                                        </div>
                                    </div>
                                    <i class="bi bi-chevron-right text-muted ms-2"></i>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <a href="select_mission.php" class="btn btn-outline-secondary w-100 mt-4 rounded-pill fw-bold">
                    <i class="bi bi-arrow-left me-1"></i> กลับหน้าเลือกภารกิจ
                </a>
            </div>
        </div>
    </div>
</div>

<?php foreach($assigned_vehicles as $v): ?>
<div class="modal fade" id="vModal<?php echo $v['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1);">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 p-md-5 text-center pt-0">
                <div class="icon-circle mx-auto mb-3" style="width: 70px; height: 70px; font-size: 2rem;">
                    <i class="bi bi-truck"></i>
                </div>
                <h4 class="fw-bold mb-2"><?php echo htmlspecialchars($v['vehicle_type']); ?></h4>
                <div class="bg-light rounded-pill d-inline-block px-4 py-2 mb-4">
                    <span class="text-muted me-2">ทะเบียน:</span> 
                    <strong class="text-dark fs-5"><?php echo htmlspecialchars($v['license_plate']); ?></strong>
                </div>
                <hr class="text-muted opacity-25 mb-4">
                <p class="text-muted mb-1 text-uppercase" style="font-size: 0.85rem; letter-spacing: 1px;">พนักงานขับรถ</p>
                <h5 class="text-primary fw-bold mb-0"><?php echo htmlspecialchars($v['driver_name']); ?></h5>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<div class="modal fade" id="driverJobsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content border-0" style="border-radius: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.1);">
            <div class="modal-header border-bottom bg-info bg-opacity-10 px-4 py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-calendar-check text-info me-2"></i>ตารางงานพนักงานขับรถ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <div class="accordion" id="accordionDriverJobs">
                    <?php $i=0; foreach($veh_staff_raw as $v): 
                        $driver_name = $v['fname'] . ' ' . $v['lname'];
                        $d_jobs = $jobs_by_driver[$driver_name] ?? [];
                        $i++;
                    ?>
                        <div class="accordion-item border-0 mb-2 shadow-sm rounded-3 overflow-hidden">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed bg-white fw-bold text-dark" type="button" data-bs-toggle="collapse" data-bs-target="#collapseDriver_<?php echo $i; ?>">
                                    <i class="bi bi-person-fill me-2 text-secondary"></i> <?php echo htmlspecialchars($driver_name); ?> 
                                    <span class="badge <?php echo empty($d_jobs) ? 'bg-secondary' : 'bg-primary'; ?> ms-2 rounded-pill"><?php echo count($d_jobs); ?> งาน</span>
                                </button>
                            </h2>
                            <div id="collapseDriver_<?php echo $i; ?>" class="accordion-collapse collapse" data-bs-parent="#accordionDriverJobs">
                                <div class="accordion-body bg-light p-3" style="max-height: 300px; overflow-y: auto;">
                                    <?php if(empty($d_jobs)): ?>
                                        <div class="text-center text-muted small py-3">- ว่าง (ยังไม่มีงาน) -</div>
                                    <?php else: ?>
                                        <div class="list-group list-group-flush rounded-3 border">
                                            <?php foreach($d_jobs as $dj): ?>
                                                <div class="list-group-item p-3 border-bottom-0 mb-1">
                                                    <div class="fw-bold text-primary mb-1"><?php echo htmlspecialchars($dj['doc_name']); ?></div>
                                                    <div class="text-muted small"><i class="bi bi-calendar-event me-1"></i> <?php echo $dj['date_text']; ?></div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // 1. เรียกใช้งาน Select2 ให้กับทุก Dropdown ที่ใช้ Class .select2-custom
    $('.select2-custom').select2({
        theme: 'bootstrap-5',
        width: '100%'
    });

    // ดึงข้อมูลทะเบียนรถจากตาราง vehicles ที่ฝั่ง PHP เตรียมไว้ให้
    const vehiclesData = <?php echo isset($vehicles_json) ? $vehicles_json : '{}'; ?>;
    const driversData = <?php echo isset($veh_staff_json) ? $veh_staff_json : '[]'; ?>;

    // 2. ตรวจสอบการเลือกเปลี่ยน "ประเภทรถ" ผ่าน jQuery Event ของ Select2
    $('#vehicleTypeSelect').on('change', function() {
        const licenseSelect = $('#licensePlateSelect');
        const driverSelect = $('#driverSelect');
        const selectedType = $(this).val() ? $(this).val().trim() : '';

        // ล้างค่าข้อมูลทะเบียนและคนขับเดิมออกทั้งหมด
        licenseSelect.empty();
        if (driverSelect.length) {
            driverSelect.empty();
        }
        
        // ค้นหาข้อมูลกลุ่มทะเบียนรถที่ตรงกับประเภทรถที่ผู้ใช้งานกดเลือก
        let matchedPlates = [];
        for (let key in vehiclesData) {
            if (key.trim() === selectedType) {
                matchedPlates = vehiclesData[key];
                break;
            }
        }

        // 3. ตรวจสอบเงื่อนไขกลุ่มข้อมูลทะเบียนรถ
        if (selectedType && matchedPlates && matchedPlates.length > 0) {
            // เพิ่ม Option เริ่มต้นตัวแรกสำหรับทะเบียน
            licenseSelect.append(new Option('-- เลือกทะเบียนรถ --', '', true, true));
            
            // วนลูปข้อมูลใน Array เพื่อสร้างรายการทะเบียนรถยนต์ที่มีในระบบ
            matchedPlates.forEach(function(plate) {
                licenseSelect.append(new Option(plate, plate));
            });
            licenseSelect.prop('disabled', false);
            
            // --- NEW LOGIC: ดึงเฉพาะคนขับที่มีใบอนุญาตตรงกับประเภทรถที่เลือก ---
            if (driverSelect.length) {
                driverSelect.append(new Option('-- เลือกคนขับรถ --', '', true, true));
                let hasDriver = false;
                
                driversData.forEach(function(driver) {
                    // ตรวจสอบว่าใน array qualifications ของคนขับ มีประเภทรถที่เลือกหรือไม่
                    if (driver.qualifications && driver.qualifications.includes(selectedType)) {
                        let hasOverlap = driver.overlaps.length > 0;
                        let overlapText = hasOverlap ? ' (มีงานซ้อน)' : '';
                        let newOpt = new Option(driver.name + overlapText, driver.name);
                        $(newOpt).attr('data-overlap', hasOverlap ? 'true' : 'false');
                        driverSelect.append(newOpt);
                        hasDriver = true;
                    }
                });
                
                if (hasDriver) {
                    driverSelect.prop('disabled', false);
                } else {
                    driverSelect.empty();
                    driverSelect.append(new Option('-- ไม่พบคนขับที่มีใบอนุญาตรถประเภทนี้ --', '', true, true));
                    driverSelect.prop('disabled', true);
                }
            }

        } else {
            // กรณีไม่มีข้อมูลทะเบียนรถคันดังกล่าวบันทึกอยู่ในตาราง
            licenseSelect.append(new Option('-- ไม่พบข้อมูลทะเบียนรถประเภทนี้ --', '', true, true));
            licenseSelect.prop('disabled', true);
            
            if (driverSelect.length) {
                driverSelect.append(new Option('-- กรุณาเลือกประเภทรถก่อน --', '', true, true));
                driverSelect.prop('disabled', true);
            }
        }

        // 4. สั่งให้ปลั๊กอิน Select2 วาดหน้าตาแสดงผล UI บน Browser ใหม่ทันที
        licenseSelect.trigger('change');
        if (driverSelect.length) driverSelect.trigger('change');
    });

    // 5. ควบคุมการแจ้งเตือนพนักงานขับรถมีภารกิจงานซ้อน
    $('#driverSelect').on('change', function() {
        const selectedOption = $(this).find('option:selected');
        const isOverlap = selectedOption.data('overlap') === true || selectedOption.data('overlap') === 'true';
        
        if (isOverlap) {
            $('#driverOverlapInfo').removeClass('d-none');
        } else {
            $('#driverOverlapInfo').addClass('d-none');
        }
    });

    // 6. ตรวจสอบข้อมูลชุดปฏิบัติงานหลักเริ่มต้นจากฝั่ง PHP
    const hasOperationSets = <?php echo empty($operation_sets) ? 'false' : 'true'; ?>;
    if (!hasOperationSets) {
        Swal.fire({
            title: 'ยังไม่จัดชุดปฏิบัติงาน!',
            text: 'กรุณาไปที่หน้าจัดบุคคลเข้าชุดปฏิบัติงานก่อนดำเนินการจัดการยานพาหนะ',
            icon: 'warning',
            confirmButtonColor: '#0ea5e9',
            allowOutsideClick: false,
            customClass: { popup: 'rounded-4' }
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'select_mission.php'; 
            }
        });
    }

    // 7. เคลียร์ค่า URL Success Alert
    if (window.history.replaceState) {
        const url = new URL(window.location.href);
        url.searchParams.delete('success');
        window.history.replaceState({}, '', url.href);
    }
    setTimeout(() => {
        const alert = document.getElementById('success-alert');
        if (alert) { 
            alert.style.transition = "opacity 0.5s ease";
            alert.style.opacity = "0";
            setTimeout(() => alert.style.display = 'none', 500);
        }
    }, 2500);
});
</script>
<?php include 'footer.php'; ?>
</body>
</html>
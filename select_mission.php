<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/equipment.php';

require_role([ROLE_HEAD_VEHICLE, ROLE_STAFF_VEHICLE, ROLE_SUPER_ADMIN]);

$conn = get_db();
$user_role = $_SESSION['role'] ?? '';
$is_head = has_role([ROLE_HEAD_VEHICLE, ROLE_SUPER_ADMIN]);

// --- 1. จัดการการยืมอุปกรณ์ (Borrow Equipment) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrow_equipment'])) {
    require_csrf();
    $job_id = clean_int($_POST['job_id'] ?? 0);
    $equipment_code = clean_string($_POST['equipment_code'] ?? '');
    $quantity = clean_int($_POST['quantity'] ?? 0);
    $page = clean_int($_GET['page'] ?? 1);

    $res = equipment_borrow($conn, $job_id, $equipment_code, $quantity, $_SESSION['username'] ?? 'system');
    if ($res['success']) {
        header("Location: select_mission.php?page={$page}&borrow_success=1&open_modal={$job_id}");
    } else {
        header("Location: select_mission.php?page={$page}&borrow_error=" . urlencode($res['message']) . "&open_modal={$job_id}");
    }
    exit();
}

// --- 2. จัดการการคืนอุปกรณ์ (Return Equipment) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return_item') {
    require_csrf();
    $borrow_id = clean_int($_POST['borrow_id'] ?? 0);
    $job_id = clean_int($_POST['job_id'] ?? 0);
    $page = clean_int($_GET['page'] ?? 1);

    $res = equipment_return($conn, $borrow_id);
    if ($res['success']) {
        header("Location: select_mission.php?page={$page}&return_success=1&open_modal={$job_id}");
    } else {
        header("Location: select_mission.php?page={$page}&return_error=" . urlencode($res['message']) . "&open_modal={$job_id}");
    }
    exit();
}

// --- Pagination Setup ---
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$stmt_total = $conn->query("SELECT COUNT(*) FROM documents");
$total_rows = $stmt_total->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// Query ดึงจดหมาย พร้อมเช็คสถานะการจัดชุด (ฝ่ายปฏิบัติการที่ไม่ใช่ผู้ประสานงาน) และจัดรถ
$query = "
    SELECT d.*,
    -- นับเฉพาะคนที่อยู่ใน 'ฝ่ายปฏิบัติการ' และไม่ใช่ 'ผู้ประสานงาน' (sent_to)
    (
        SELECT COUNT(ja.id) 
        FROM job_assignments ja 
        INNER JOIN users u ON ja.staff_name = CONCAT(u.fname, ' ', u.lname)
        WHERE ja.job_id = d.id 
          AND u.role IN ('staff_operation', 'head_operation') 
          AND (d.sent_to IS NULL OR u.id != d.sent_to)
    ) as valid_team_count,
    (SELECT COUNT(va.id) FROM vehicle_assignments va WHERE va.job_id = d.id) as vehicle_count
    FROM documents d 
    ORDER BY d.id DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$documents = $stmt->fetchAll();

// --- ดึงข้อมูลอุปกรณ์ (Master Data) เพื่อนำไปแสดงใน Modal ---
$stmt_equipments = $conn->query("SELECT * FROM equipments ORDER BY equipment_category ASC, equipment_name ASC");
$equipments_list = $stmt_equipments->fetchAll(PDO::FETCH_ASSOC);

// --- ดึงข้อมูลการยืมที่มีอยู่แล้วทั้งหมด ---
$stmt_borrowings = $conn->query("
    SELECT eb.*, e.equipment_name, e.unit 
    FROM equipment_borrowings eb
    JOIN equipments e ON eb.equipment_code = e.equipment_code
    ORDER BY eb.created_at DESC
");
$borrowings_by_job = [];
foreach ($stmt_borrowings->fetchAll(PDO::FETCH_ASSOC) as $b) {
    $borrowings_by_job[$b['job_id']][] = $b;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>เลือกงานภารกิจ - ระบบพับพลาพิธี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #f0f4f8 0%, #d9e2ec 100%);
            font-family: 'Prompt', sans-serif; 
            min-height: 100vh;
        }
        .page-header {
            color: #1e3a8a;
            text-shadow: 1px 1px 2px rgba(0,0,0,0.05);
            position: relative;
            display: inline-block;
        }
        .page-header::after {
            content: '';
            position: absolute;
            width: 50%;
            height: 4px;
            background: #3b82f6;
            bottom: -10px;
            left: 25%;
            border-radius: 2px;
        }
        
        /* Table Container */
        .table-container {
            background-color: #ffffff;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.04);
            border-top: 5px solid #3b82f6;
            padding: 15px;
            margin-bottom: 2rem;
        }
        
        /* Table Styling */
        .table {
            min-width: 900px; /* บังคับให้ตารางกว้างพอที่จะแสดงข้อความครบ ไม่ให้บีบจนพังบนมือถือ */
        }
        .table th {
            background-color: #f8fafc;
            color: #475569;
            font-size: 0.95rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            vertical-align: middle;
        }
        .table td {
            vertical-align: middle;
            padding: 1rem;
        }
        
        /* Typography & Badges */
        .doc-title {
            color: #1f2937;
            font-weight: 600;
            line-height: 1.4;
        }
        .status-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            padding: 6px 14px;
            border-radius: 50rem;
            font-size: 0.85rem;
            font-weight: 500;
            white-space: nowrap;
        }
        .status-success { background-color: #dcfce7; color: #166534; }
        .status-danger { background-color: #fee2e2; color: #991b1b; }
        
        /* Buttons */
        .btn-action {
            background: linear-gradient(135deg, #0ea5e9 0%, #0284c7 100%);
            border: none;
            color: white;
            font-weight: 500;
            box-shadow: 0 4px 10px rgba(14, 165, 233, 0.3);
            transition: all 0.2s ease;
            white-space: nowrap;
        }
        .btn-action:hover {
            background: linear-gradient(135deg, #0284c7 0%, #0369a1 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(14, 165, 233, 0.4);
        }
        .transition-all { transition: all 0.2s ease; }
        .hover-primary:hover { color: #3b82f6 !important; transform: translateX(-5px); }
        
        /* Pagination */
        .pagination .page-link { color: #0ea5e9; border: none; margin: 0 4px; border-radius: 8px !important; font-weight: 500; transition: all 0.2s; }
        .pagination .page-item.active .page-link { background-color: #0ea5e9; color: white; box-shadow: 0 4px 10px rgba(14, 165, 233, 0.3); }
        .pagination .page-link:hover:not(.active) { background-color: #e0f2fe; color: #0284c7; }

        @media (max-width: 767.98px) {
            .page-header { font-size: 1.5rem; }
            .page-header::after { bottom: -5px; height: 3px; }
            .search-container { max-width: 100% !important; }
        }
    </style>
</head>
<body>

<?php include 'menu.php'; ?>

<div class="container py-5">
    <div class="row align-items-center mb-4 pb-3 border-bottom">
        <div class="col-12 col-md-3 text-start mb-3 mb-md-0">
            <a href="<?php echo $is_head ? 'home_head_vehicle.php' : 'home_vehicle.php'; ?>" class="btn btn-white border shadow-sm rounded-pill d-inline-flex align-items-center hover-primary transition-all px-4 py-2 bg-white" style="font-size: 0.95rem; font-weight: 500;">
                <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
            </a>
        </div>
        <div class="col-12 col-md-6 text-center">
            <h2 class="fw-bold page-header mb-0 pb-2"><i class="bi bi-car-front" style="color: #10b981;"></i> &nbsp;การบริหารจัดการรถ</h2>
            <p class="text-muted mt-2 mb-0">ระบบจัดการและจัดสรรทรัพยากรสำหรับงานพิธี</p>
        </div>
        <div class="col-12 col-md-3"></div>
    </div>

    <div class="d-flex justify-content-center justify-content-md-end align-items-center gap-3 mb-4">
        <button type="button" class="btn btn-outline-info fw-bold shadow-sm bg-white" data-bs-toggle="modal" data-bs-target="#equipmentModal">
            <i class="bi bi-tools me-1"></i> รายการอุปกรณ์
        </button>
        <div class="position-relative w-100 search-container" style="max-width: 350px;">
            <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"><i class="bi bi-search"></i></span>
            <input type="text" id="searchMission" class="form-control bg-white border-0 shadow-sm ps-5 py-2" style="border-radius: 20px;" placeholder="ค้นหาชื่องาน, เลขที่หนังสือ...">
        </div>
    </div>

    <div class="table-container">
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col" style="width: 40%;">ชื่องาน / เลขที่หนังสือ</th>
                        <th scope="col" class="text-center" style="width: 20%;">ชุดปฏิบัติงาน</th>
                        <th scope="col" class="text-center" style="width: 20%;">ยานพาหนะ</th>
                        <?php if($is_head): ?>
                        <th scope="col" class="text-center" style="width: 20%;">จัดการ</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr id="noMissionFound" style="display: none;">
                        <td colspan="<?php echo $is_head ? '4' : '3'; ?>" class="text-center py-5">
                            <i class="bi bi-search text-muted fs-1 opacity-50 mb-3 d-block"></i>
                            <h5 class="fw-bold text-muted">ไม่พบข้อมูลภารกิจที่ค้นหา</h5>
                        </td>
                    </tr>

                    <?php if (count($documents) > 0): ?>
                        <?php foreach($documents as $doc): ?>
                            <tr class="mission-item transition-all">
                                <td>
                                    <div class="doc-title">
                                        <?php echo htmlspecialchars($doc['doc_name']); ?>
                                    </div>
                                    <?php if(!empty($doc['doc_number'])): ?>
                                        <small class="text-muted d-block mt-1">
                                            เลขที่: <?php echo htmlspecialchars($doc['doc_number']); ?>
                                        </small>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill d-inline-flex align-items-center shadow-sm mt-2" 
                                            data-bs-toggle="modal" data-bs-target="#viewDocModal<?php echo $doc['id']; ?>" 
                                            style="font-size: 0.75rem; padding: 0.2rem 0.75rem; border-width: 1px; transition: all 0.2s;">
                                        <i class="bi bi-eye-fill me-1"></i> ดูรายละเอียด
                                    </button>
                                </td>
                                <td class="text-center">
                                    <?php if($doc['valid_team_count'] > 0): ?>
                                        <div class="status-badge status-success">
                                            <i class="bi bi-check-circle-fill"></i> จัดชุดแล้ว
                                        </div>
                                    <?php else: ?>
                                        <div class="status-badge status-danger">
                                            <i class="bi bi-x-circle-fill"></i> รอผู้ปฏิบัติการ
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if($doc['vehicle_count'] > 0): ?>
                                        <div class="status-badge status-success">
                                            <i class="bi bi-check-circle-fill"></i> จัดเรียบร้อย
                                        </div>
                                    <?php else: ?>
                                        <div class="status-badge status-danger">
                                            <i class="bi bi-x-circle-fill"></i> ยังไม่ได้จัด
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <?php if($is_head): ?>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-2">
                                        <?php if($doc['valid_team_count'] > 0): ?>
                                            <a href="manage_vehicle.php?job_id=<?php echo $doc['id']; ?>" class="btn btn-action px-3 py-2 rounded-pill btn-sm d-inline-flex align-items-center">
                                                <i class="bi bi-truck me-1"></i> จัดรถ
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary px-3 py-2 rounded-pill btn-sm opacity-75 d-inline-flex align-items-center" disabled style="cursor: not-allowed;">
                                                <i class="bi bi-truck me-1"></i> รอชุด
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-outline-success px-3 py-2 rounded-pill btn-sm d-inline-flex align-items-center" data-bs-toggle="modal" data-bs-target="#borrowEquipmentModal<?php echo $doc['id']; ?>">
                                            <i class="bi bi-box-seam me-1"></i> ยืมอุปกรณ์
                                        </button>
                                    </div>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $is_head ? '4' : '3'; ?>" class="text-center py-5">
                                <h5 class="fw-bold text-muted mb-0">ไม่มีข้อมูลภารกิจในระบบ</h5>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <?php foreach ($documents as $doc): ?>
    <!-- Modal ดูรายละเอียดงาน -->
    <div class="modal fade" id="viewDocModal<?php echo $doc['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header border-bottom bg-light px-4 py-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-info bg-opacity-10 text-info p-2 rounded-3 me-3 d-none d-md-flex">
                            <i class="bi bi-eye-fill fs-4"></i>
                        </div>
                        <h5 class="modal-title fw-bold text-dark mb-0 pe-4">รายละเอียดข้อมูลงาน<br class="d-md-none"><span class="fs-6 text-muted ms-md-2">(รหัส: <?php echo htmlspecialchars($doc['doc_number'] ?: $doc['id']); ?>)</span></h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 pb-4" style="background-color: #f8f9fa;">
                    <!-- ส่วนที่ 1 -->
                    <div class="bg-white p-4 rounded-4 shadow-sm mb-4 mt-3">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-box-arrow-in-right me-2"></i>ข้อมูลการรับเรื่อง</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="text-muted small fw-bold">ปีงบประมาณ</div>
                                <div class="text-dark"><?php echo !empty($doc['fiscal_year']) ? htmlspecialchars($doc['fiscal_year']) : '-'; ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small fw-bold">เลขรับ กรมฯ</div>
                                <div class="text-dark"><?php echo !empty($doc['dpt_receive_number']) ? htmlspecialchars($doc['dpt_receive_number']) : '-'; ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small fw-bold">วันที่รับ กรมฯ</div>
                                <div class="text-dark"><?php echo !empty($doc['dpt_receive_date']) ? date('d/m/Y', strtotime($doc['dpt_receive_date'])) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">เลขรับ กอพ.</div>
                                <div class="text-dark"><?php echo !empty($doc['division_receive_number']) ? htmlspecialchars($doc['division_receive_number']) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">วันที่รับ กอพ.</div>
                                <div class="text-dark"><?php echo !empty($doc['division_receive_date']) ? date('d/m/Y', strtotime($doc['division_receive_date'])) : '-'; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 2 -->
                    <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-file-earmark-text me-2"></i>ข้อมูลหนังสือและชื่องาน</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">หน่วยงานต้นเรื่อง</div>
                                <div class="text-dark" style="word-break: break-word;"><?php echo !empty($doc['host_agency']) ? htmlspecialchars($doc['host_agency']) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">สังกัดของหน่วยงานต้นเรื่อง</div>
                                <div class="text-dark" style="word-break: break-word;"><?php echo !empty($doc['host_agency_affiliation']) ? htmlspecialchars($doc['host_agency_affiliation']) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">เลขที่หนังสือหน่วยงานต้นทาง</div>
                                <div class="text-dark" style="word-break: break-word;"><?php echo !empty($doc['source_doc_number']) ? htmlspecialchars($doc['source_doc_number']) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">ประเภทงาน</div>
                                <div class="text-dark" style="word-break: break-word;">
                                    <?php 
                                        $v_job_type_text = ($doc['job_type'] === 'อื่นๆ' && !empty($doc['job_type_other'])) 
                                                         ? "อื่นๆ: " . $doc['job_type_other'] 
                                                         : ($doc['job_type'] ?: '-');
                                        echo htmlspecialchars($v_job_type_text);
                                    ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="text-muted small fw-bold">ชื่องาน</div>
                                <div class="text-dark fw-bold" style="word-break: break-word;"><?php echo !empty($doc['doc_name']) ? htmlspecialchars($doc['doc_name']) : '-'; ?></div>
                            </div>
                            <div class="col-md-12">
                                <div class="text-muted small fw-bold">รายละเอียด</div>
                                <div class="text-dark" style="word-break: break-word;"><?php echo !empty($doc['description']) ? nl2br(htmlspecialchars($doc['description'])) : '-'; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 3 -->
                    <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-geo-alt me-2"></i>สถานที่จัดงาน</h6>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <div class="text-muted small fw-bold">หน่วยงาน / ชื่อสถานที่จัดงาน</div>
                                <div class="text-dark" style="word-break: break-word;"><?php echo !empty($doc['venue_name']) ? htmlspecialchars($doc['venue_name']) : '-'; ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small fw-bold">จังหวัด</div>
                                <div class="text-dark"><?php echo !empty($doc['venue_province']) ? htmlspecialchars($doc['venue_province']) : '-'; ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small fw-bold">อำเภอ/เขต</div>
                                <div class="text-dark"><?php echo !empty($doc['venue_district']) ? htmlspecialchars($doc['venue_district']) : '-'; ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small fw-bold">ตำบล/แขวง</div>
                                <div class="text-dark"><?php echo !empty($doc['venue_subdistrict']) ? htmlspecialchars($doc['venue_subdistrict']) : '-'; ?></div>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 4 -->
                    <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-calendar-event me-2"></i>กำหนดการและระยะเวลาดำเนินงาน</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">วันที่จัดงาน (เริ่ม)</div>
                                <div class="text-dark"><?php echo !empty($doc['event_start_date']) ? date('d/m/Y', strtotime($doc['event_start_date'])) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">วันที่จัดงาน (สิ้นสุด)</div>
                                <div class="text-dark"><?php echo !empty($doc['event_end_date']) ? date('d/m/Y', strtotime($doc['event_end_date'])) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">วันที่ประชุมล่วงหน้า</div>
                                <div class="text-dark"><?php echo !empty($doc['pre_meeting_date']) ? date('d/m/Y', strtotime($doc['pre_meeting_date'])) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">วันที่ขอรับอุปกรณ์ยืม</div>
                                <div class="text-dark"><?php echo !empty($doc['equipment_borrow_date']) ? date('d/m/Y', strtotime($doc['equipment_borrow_date'])) : '-'; ?></div>
                            </div>
                            <hr class="my-3 text-muted">
                            <div class="col-md-6">
                                <div class="text-primary small fw-bold">ระยะเวลาทำงาน กอพ. (เริ่ม)</div>
                                <div class="text-dark"><?php echo !empty($doc['dpt_work_start']) ? date('d/m/Y', strtotime($doc['dpt_work_start'])) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-primary small fw-bold">ระยะเวลาทำงาน กอพ. (สิ้นสุด)</div>
                                <div class="text-dark"><?php echo !empty($doc['dpt_work_end']) ? date('d/m/Y', strtotime($doc['dpt_work_end'])) : '-'; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- ส่วนที่ 5 -->
                    <div class="bg-white p-4 rounded-4 shadow-sm mb-2">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-paperclip me-2"></i>ไฟล์แนบและหมายเหตุ</h6>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <div class="text-muted small fw-bold">ไฟล์เอกสารแนบ (PDF)</div>
                                <div class="mt-1">
                                    <?php if (!empty($doc['attachment_file'])): ?>
                                        <a href="download.php?id=<?php echo $doc['id']; ?>" target="_blank" class="badge bg-danger text-decoration-none px-3 py-2">
                                            <i class="bi bi-file-earmark-pdf me-1"></i> ดาวน์โหลดไฟล์แนบ
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="text-muted small fw-bold mt-2">หมายเหตุ</div>
                                <div class="text-dark" style="word-break: break-word;"><?php echo !empty($doc['remark']) ? nl2br(htmlspecialchars($doc['remark'])) : '-'; ?></div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-top-0 px-4 pb-4 pt-3 bg-light">
                    <button type="button" class="btn btn-secondary rounded-pill px-5" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php foreach ($documents as $doc): ?>
    <!-- Modal ยืมอุปกรณ์ -->
    <div class="modal fade" id="borrowEquipmentModal<?php echo $doc['id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-success-subtle border-bottom-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-box-seam-fill text-success me-2"></i>ยืมอุปกรณ์สำหรับงาน: <span class="text-success"><?php echo htmlspecialchars($doc['doc_name']); ?></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <div class="row g-4">
                        <!-- Left side: Form to borrow -->
                        <div class="col-md-5">
                            <div class="bg-white p-4 rounded-3 shadow-sm border h-100">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-plus-circle-fill text-success me-2"></i>เพิ่มรายการยืมใหม่</h6>
                                <form method="POST" action="select_mission.php?page=<?php echo $page; ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="job_id" value="<?php echo $doc['id']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label small fw-medium">เลือกอุปกรณ์</label>
                                        <select name="equipment_code" class="form-select" required>
                                            <option value="" disabled selected>-- เลือกอุปกรณ์ --</option>
                                            <?php foreach($equipments_list as $eq): ?>
                                                <?php if ($eq['available_quantity'] > 0): ?>
                                                    <option value="<?php echo htmlspecialchars($eq['equipment_code']); ?>">
                                                        <?php echo htmlspecialchars($eq['equipment_name']); ?> (คงเหลือ: <?php echo $eq['available_quantity']; ?> <?php echo $eq['unit']; ?>)
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-medium">จำนวนที่ต้องการยืม</label>
                                        <input type="number" name="quantity" class="form-control" min="1" required placeholder="ระบุจำนวน">
                                    </div>
                                    <button type="submit" name="borrow_equipment" class="btn btn-success w-100 fw-bold shadow-sm">
                                        <i class="bi bi-save me-1"></i> บันทึกการยืม
                                    </button>
                                </form>
                            </div>
                        </div>
                        <!-- Right side: List of borrowed items -->
                        <div class="col-md-7">
                            <div class="bg-white p-4 rounded-3 shadow-sm border h-100">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-list-check text-primary me-2"></i>รายการที่ยืมแล้วสำหรับงานนี้</h6>
                                <?php 
                                    $current_borrowings = $borrowings_by_job[$doc['id']] ?? [];
                                    if (empty($current_borrowings)): 
                                ?>
                                    <div class="text-center text-muted py-5 border border-dashed rounded-3">
                                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                        ยังไม่มีการยืมอุปกรณ์สำหรับงานนี้
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive" style="max-height: 300px;">
                                        <table class="table table-striped table-sm">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>ชื่ออุปกรณ์</th>
                                                    <th class="text-center">จำนวน</th>
                                                    <th class="text-center">จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach($current_borrowings as $item): ?>
                                                <tr>
                                                    <td class="align-middle"><?php echo htmlspecialchars($item['equipment_name']); ?></td>
                                                    <td class="text-center align-middle"><?php echo htmlspecialchars($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                                    <td class="text-center">
                                                        <form method="POST" action="select_mission.php?page=<?php echo $page; ?>" style="display:inline;" onsubmit="return confirm('ยืนยันการคืนอุปกรณ์นี้?')">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="return_item">
                                                            <input type="hidden" name="borrow_id" value="<?php echo (int)$item['id']; ?>">
                                                            <input type="hidden" name="job_id" value="<?php echo (int)$doc['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-return-left"></i> คืน</button>
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
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php if (isset($total_pages) && $total_pages > 1): ?>
    <div class="d-flex justify-content-center mt-4 mb-5">
        <nav>
            <ul class="pagination pagination-sm mb-0 shadow-sm rounded-3 flex-wrap justify-content-center gap-1 p-2 bg-white">
                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link px-3 py-2" href="?page=<?php echo $page - 1; ?>"><i class="bi bi-chevron-left"></i> ก่อนหน้า</a>
                </li>
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                    <a class="page-link px-3 py-2" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                </li>
                <?php endfor; ?>
                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                    <a class="page-link px-3 py-2" href="?page=<?php echo $page + 1; ?>">ถัดไป <i class="bi bi-chevron-right"></i></a>
                </li>
            </ul>
        </nav>
    </div>
    <?php endif; ?>
</div>

<!-- Modal รายการอุปกรณ์ -->
<div class="modal fade" id="equipmentModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header border-bottom bg-info px-4 py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-tools text-dark me-2"></i>รายการอุปกรณ์และสถานะคงเหลือ</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                <?php if (empty($equipments_list)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                        <span class="fw-medium">ยังไม่มีข้อมูลอุปกรณ์ในระบบ</span>
                    </div>
                <?php else: ?>
                    <div class="table-responsive bg-white rounded-3 shadow-sm border">
                        <table class="table table-hover mb-0 align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">รหัส / ชื่ออุปกรณ์</th>
                                    <th>หมวดหมู่</th>
                                    <th class="text-center">ทั้งหมด</th>
                                    <th class="text-center">คงเหลือ (พร้อมใช้)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($equipments_list as $eq): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($eq['equipment_name']); ?></div>
                                            <small class="text-muted">รหัส: <?php echo htmlspecialchars($eq['equipment_code']); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                                <?php echo htmlspecialchars($eq['equipment_category'] ?? '-'); ?>
                                            </span>
                                        </td>
                                        <td class="text-center fw-medium">
                                            <?php echo htmlspecialchars($eq['total_quantity']); ?> <?php echo htmlspecialchars($eq['unit']); ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($eq['available_quantity'] > 0): ?>
                                                <span class="badge bg-success-subtle text-success px-2 py-1" style="font-size: 0.85rem;">
                                                    <?php echo htmlspecialchars($eq['available_quantity']); ?> <?php echo htmlspecialchars($eq['unit']); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-subtle text-danger px-2 py-1" style="font-size: 0.85rem;">
                                                    หมด
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer border-top-0 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ค้นหาภารกิจ (Real-time Filter)
document.addEventListener("DOMContentLoaded", function() {
    const urlParams = new URLSearchParams(window.location.search);
    const openModalId = urlParams.get('open_modal');

    if (openModalId) {
        const modalElement = document.getElementById('borrowEquipmentModal' + openModalId);
        if (modalElement) {
            const myModal = new bootstrap.Modal(modalElement);
            myModal.show();
        }
    }

    // Clean up URL to prevent re-opening modal on refresh
    if (window.history.replaceState) {
        const cleanUrl = new URL(window.location.href);
        const paramsToClear = ['borrow_success', 'return_success', 'borrow_error', 'return_error', 'open_modal'];
        paramsToClear.forEach(param => cleanUrl.searchParams.delete(param));
        window.history.replaceState({ path: cleanUrl.href }, '', cleanUrl.href);
    }

    // Auto-close alerts
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            new bootstrap.Alert(alert).close();
        }, 4000);
    });

    const searchInput = document.getElementById('searchMission');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const items = document.querySelectorAll('.mission-item');
            let hasVisibleItem = false;

            items.forEach(function(item) {
                const textContent = item.textContent.toLowerCase();
                if (textContent.includes(filter)) {
                    item.style.display = '';
                    hasVisibleItem = true;
                } else {
                    item.style.display = 'none';
                }
            });

            // แสดง/ซ่อน แถว "ไม่พบข้อมูล" 
            const noDataEl = document.getElementById('noMissionFound');
            if (noDataEl) {
                if (!hasVisibleItem && items.length > 0) {
                    noDataEl.style.display = 'table-row';
                } else {
                    noDataEl.style.display = 'none';
                }
            }
        });
    }
});
</script>
</body>
</html>
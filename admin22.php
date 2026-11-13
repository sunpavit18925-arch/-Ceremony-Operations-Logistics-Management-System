<?php
session_start();
require 'db.php';

if(!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header ("Location: home.php");
    exit();
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ตรวจสอบ Session Timeout (2 ชั่วโมง = 7200 วินาที)
$timeout_duration = 7200;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: index.php"); // หรือกลับไปหน้า login
    exit();
}
$_SESSION['last_activity'] = time(); // อัปเดตเวลาใช้งานล่าสุด

// สร้างโฟลเดอร์สำหรับเก็บไฟล์แนบถ้ายังไม่มี
$upload_dir = 'uploads/documents/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// ฟังก์ชันสำหรับแปลงค่าว่างให้เป็น NULL (สำหรับฟิลด์วันที่และตัวเลข)
function nullIfEmpty($val) {
    return (isset($val) && trim($val) !== '') ? trim($val) : null;
}

// --- 1. ส่วนจัดการการลบข้อมูล ---
if (isset($_GET['delete_id'])) {
    try {
        $id = $_GET['delete_id'];
        
        // ลบไฟล์แนบ (ถ้ามี)
        $stmt_file = $conn->prepare("SELECT attachment_file FROM documents WHERE id = ?");
        $stmt_file->execute([$id]);
        $file_data = $stmt_file->fetch();
        if ($file_data && !empty($file_data['attachment_file'])) {
            $file_path = $upload_dir . $file_data['attachment_file'];
            if (file_exists($file_path)) unlink($file_path);
        }

        $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin2.php?delete_success=1");
        exit();
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการลบข้อมูล: " . $e->getMessage());
    }
}

// --- 2. จัดการการเพิ่มข้อมูลงาน (INSERT) ---
if (isset($_POST['add_document'])) {
    try {
        // รับค่าข้อมูลรับเรื่อง
        $fiscal_year = nullIfEmpty($_POST['fiscal_year']);
        $dpt_receive_number = nullIfEmpty($_POST['dpt_receive_number']);
        $dpt_receive_date = nullIfEmpty($_POST['dpt_receive_date']);
        $division_receive_number = nullIfEmpty($_POST['division_receive_number']);
        $division_receive_date = nullIfEmpty($_POST['division_receive_date']);
        
        // รับค่าหนังสือต้นทาง
        $host_agency = nullIfEmpty($_POST['host_agency']);
        $host_agency_affiliation = nullIfEmpty($_POST['host_agency_affiliation']);
        $source_doc_number = nullIfEmpty($_POST['source_doc_number']);
        $doc_name = nullIfEmpty($_POST['doc_name']);
        $description = nullIfEmpty($_POST['description']);
        
        $job_type = $_POST['job_type'] ?? '';
        if ($job_type === 'อื่นๆ' && !empty($_POST['job_type_other'])) {
            $job_type = $_POST['job_type_other'];
        }

        // สถานที่จัดงาน
        $venue_name = nullIfEmpty($_POST['venue_name']);
        $venue_province = nullIfEmpty($_POST['venue_province']);
        $venue_district = nullIfEmpty($_POST['venue_district']);
        $venue_subdistrict = nullIfEmpty($_POST['venue_subdistrict']);

        // กำหนดการ
        $event_start_date = nullIfEmpty($_POST['event_start_date']);
        $event_end_date = nullIfEmpty($_POST['event_end_date']);
        $pre_meeting_date = nullIfEmpty($_POST['pre_meeting_date']);
        $equipment_borrow_date = nullIfEmpty($_POST['equipment_borrow_date']);
        $dpt_work_start = nullIfEmpty($_POST['dpt_work_start']);
        $dpt_work_end = nullIfEmpty($_POST['dpt_work_end']);

        // ผู้ประสานงานและหมายเหตุ
        $coordinator_1_name = nullIfEmpty($_POST['coordinator_1_name']);
        $coordinator_1_phone = nullIfEmpty($_POST['coordinator_1_phone']);
        $coordinator_2_name = nullIfEmpty($_POST['coordinator_2_name']);
        $coordinator_2_phone = nullIfEmpty($_POST['coordinator_2_phone']);
        $remark = nullIfEmpty($_POST['remark']);

        $date = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
        $received_date = $date->format('Y-m-d H:i:s');
        $created_by = $_SESSION['username'] ?? 'Admin';
        
        // จัดการอัปโหลดไฟล์ PDF
        $attachment_file = null;
        if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] == UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['attachment_file']['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $filename = uniqid('doc_') . '.pdf';
                if (move_uploaded_file($_FILES['attachment_file']['tmp_name'], $upload_dir . $filename)) {
                    $attachment_file = $filename;
                }
            }
        }

        $sql = "INSERT INTO documents (
            fiscal_year, dpt_receive_number, dpt_receive_date, division_receive_number, division_receive_date,
            host_agency, host_agency_affiliation, source_doc_number, doc_name, description, job_type,
            venue_name, venue_province, venue_district, venue_subdistrict,
            event_start_date, event_end_date, pre_meeting_date, equipment_borrow_date, dpt_work_start, dpt_work_end,
            coordinator_1_name, coordinator_1_phone, coordinator_2_name, coordinator_2_phone,
            remark, attachment_file, received_date, created_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $fiscal_year, $dpt_receive_number, $dpt_receive_date, $division_receive_number, $division_receive_date,
            $host_agency, $host_agency_affiliation, $source_doc_number, $doc_name, $description, $job_type,
            $venue_name, $venue_province, $venue_district, $venue_subdistrict,
            $event_start_date, $event_end_date, $pre_meeting_date, $equipment_borrow_date, $dpt_work_start, $dpt_work_end,
            $coordinator_1_name, $coordinator_1_phone, $coordinator_2_name, $coordinator_2_phone,
            $remark, $attachment_file, $received_date, $created_by
        ]); 
        
        header("Location: admin2.php?success=1");
        exit();
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการบันทึกข้อมูล: " . $e->getMessage());
    }
}

// --- 3. จัดการการแก้ไขข้อมูลงาน (UPDATE) ---
if (isset($_POST['edit_document'])) {
    try {
        $id = $_POST['id'];
        
        $fiscal_year = nullIfEmpty($_POST['fiscal_year']);
        $dpt_receive_number = nullIfEmpty($_POST['dpt_receive_number']);
        $dpt_receive_date = nullIfEmpty($_POST['dpt_receive_date']);
        $division_receive_number = nullIfEmpty($_POST['division_receive_number']);
        $division_receive_date = nullIfEmpty($_POST['division_receive_date']);
        
        $host_agency = nullIfEmpty($_POST['host_agency']);
        $host_agency_affiliation = nullIfEmpty($_POST['host_agency_affiliation']);
        $source_doc_number = nullIfEmpty($_POST['source_doc_number']);
        $doc_name = nullIfEmpty($_POST['doc_name']);
        $description = nullIfEmpty($_POST['description']);
        
        $job_type = $_POST['job_type'] ?? '';
        if ($job_type === 'อื่นๆ' && !empty($_POST['job_type_other'])) {
            $job_type = $_POST['job_type_other'];
        }

        $venue_name = nullIfEmpty($_POST['venue_name']);
        $venue_province = nullIfEmpty($_POST['venue_province']);
        $venue_district = nullIfEmpty($_POST['venue_district']);
        $venue_subdistrict = nullIfEmpty($_POST['venue_subdistrict']);

        $event_start_date = nullIfEmpty($_POST['event_start_date']);
        $event_end_date = nullIfEmpty($_POST['event_end_date']);
        $pre_meeting_date = nullIfEmpty($_POST['pre_meeting_date']);
        $equipment_borrow_date = nullIfEmpty($_POST['equipment_borrow_date']);
        $dpt_work_start = nullIfEmpty($_POST['dpt_work_start']);
        $dpt_work_end = nullIfEmpty($_POST['dpt_work_end']);

        $coordinator_1_name = nullIfEmpty($_POST['coordinator_1_name']);
        $coordinator_1_phone = nullIfEmpty($_POST['coordinator_1_phone']);
        $coordinator_2_name = nullIfEmpty($_POST['coordinator_2_name']);
        $coordinator_2_phone = nullIfEmpty($_POST['coordinator_2_phone']);
        $remark = nullIfEmpty($_POST['remark']);

        // จัดการไฟล์แนบกรณีแก้ไข
        $attachment_sql = "";
        $params = [
            $fiscal_year, $dpt_receive_number, $dpt_receive_date, $division_receive_number, $division_receive_date,
            $host_agency, $host_agency_affiliation, $source_doc_number, $doc_name, $description, $job_type,
            $venue_name, $venue_province, $venue_district, $venue_subdistrict,
            $event_start_date, $event_end_date, $pre_meeting_date, $equipment_borrow_date, $dpt_work_start, $dpt_work_end,
            $coordinator_1_name, $coordinator_1_phone, $coordinator_2_name, $coordinator_2_phone,
            $remark
        ];

        if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] == UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['attachment_file']['name'], PATHINFO_EXTENSION));
            if ($ext === 'pdf') {
                $filename = uniqid('doc_') . '.pdf';
                if (move_uploaded_file($_FILES['attachment_file']['tmp_name'], $upload_dir . $filename)) {
                    $attachment_sql = ", attachment_file = ?";
                    $params[] = $filename;
                }
            }
        }
        $params[] = $id;

        $sql = "UPDATE documents SET 
            fiscal_year = ?, dpt_receive_number = ?, dpt_receive_date = ?, division_receive_number = ?, division_receive_date = ?,
            host_agency = ?, host_agency_affiliation = ?, source_doc_number = ?, doc_name = ?, description = ?, job_type = ?,
            venue_name = ?, venue_province = ?, venue_district = ?, venue_subdistrict = ?,
            event_start_date = ?, event_end_date = ?, pre_meeting_date = ?, equipment_borrow_date = ?, dpt_work_start = ?, dpt_work_end = ?,
            coordinator_1_name = ?, coordinator_1_phone = ?, coordinator_2_name = ?, coordinator_2_phone = ?,
            remark = ?
            $attachment_sql
            WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute($params); 
        
        header("Location: admin2.php?edit_success=1");
        exit();
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการแก้ไขข้อมูล: " . $e->getMessage());
    }
}

// --- 4. จัดการการ "ดึงหนังสือกลับ" (Cancel Forward) ---
if (isset($_GET['cancel_forward_id'])) {
    try {
        $id = $_GET['cancel_forward_id'];
        $stmt = $conn->prepare("UPDATE documents SET sent_to = NULL WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: admin2.php?cancel_success=1");
        exit();
    } catch (PDOException $e) {
         die("เกิดข้อผิดพลาดในการยกเลิกการส่งต่อ: " . $e->getMessage());
    }
}

// --- ดึงข้อมูลมาแสดง ---
try {
    if(isset($conn)) {
        $docs = $conn->query("SELECT * FROM documents ORDER BY id DESC")->fetchAll();
    } else {
        $docs = []; 
    }
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูลมาแสดง: " . $e->getMessage());
}

function getBadgeClass($type) {
    switch ($type) {
        case 'รับเสด็จฯ': 
        case 'รับเสด็จ': 
            return 'bg-warning text-dark';
        case 'ขอความอนุเคราะห์': 
            return 'bg-info text-dark';
        default: 
            return 'bg-primary text-white'; 
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบรับข้อมูลงาน | พลับพลาพิธี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        html, body { height: 100%; }
        body {
            display: flex;
            flex-direction: column;
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa;
        }
        .content-wrapper { flex: 1 0 auto; }
        footer { flex-shrink: 0; }

        .page-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: -3rem;
            border-radius: 0 0 1.5rem 1.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .custom-card { 
            border: none; 
            border-radius: 1.25rem; 
            box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.08); 
            background: #ffffff;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .form-control, .form-select {
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            padding: 0.6rem 1rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #2a5298;
            box-shadow: 0 0 0 0.25rem rgba(42, 82, 152, 0.15);
        }
        .btn-primary-gradient {
            background: linear-gradient(45deg, #2a5298, #1e3c72);
            border: none;
            color: white;
            transition: all 0.3s ease;
        }
        .btn-primary-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(30, 60, 114, 0.4);
            color: white;
        }

        .table-custom thead th {
            background-color: #f1f4f8;
            color: #495057;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            border-bottom: none;
            padding: 1rem;
        }
        .table-custom tbody td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f4f8;
        }
        .badge-custom {
            padding: 0.5em 1em;
            border-radius: 2rem;
            font-weight: 500;
            letter-spacing: 0.3px;
        }
        
        .btn-action {
            width: 35px;
            height: 35px;
            border-radius: 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .btn-view { background-color: #e0f7fa; color: #00838f; border: 1px solid #b2ebf2; }
        .btn-view:hover { background-color: #00bcd4; color: #fff; border-color: #00bcd4; }

        .btn-edit { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .btn-edit:hover { background-color: #ffc107; color: #fff; border-color: #ffc107; }
        
        .btn-delete { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn-delete:hover { background-color: #dc3545; color: #fff; border-color: #dc3545; }
        
        .btn-disabled { background-color: #e9ecef; color: #adb5bd; border: 1px solid #dee2e6; cursor: not-allowed; opacity: 0.6; }
        
        .modal-content {
            border-radius: 1.25rem;
            border: none;
        }
        .btn-back-rect {
            background-color: #ffffff;
            color: #000000 !important;
            padding: 6px 16px;
            border-radius: 4px; 
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            font-size: 0.9rem;
            font-weight: 500;
            transition: all 0.2s;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .btn-back-rect:hover {
            background-color: #f0f0f0;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        .header-container {
            display: grid;
            grid-template-columns: 1fr auto 1fr;
            align-items: center;
        }
        .form-section-title {
            font-size: 1.1rem;
            color: #1e3c72;
            margin-bottom: 1rem;
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 0.5rem;
        }
        .view-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 600;
            margin-bottom: 0.2rem;
        }
        .view-data {
            font-size: 1rem;
            color: #212529;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<?php include 'menu.php'; ?>

<div class="content-wrapper">
    <div class="page-header">
        <div class="container">
            <div class="header-container">
                <div class="text-start">
                    <a href="home_admin.php" class="btn-back-rect">
                        <i class="bi bi-chevron-left me-2"></i>ย้อนกลับ
                    </a>
                </div>
                <div class="text-center">
                    <h2 class="fw-bold mb-0">
                        <i class="bi bi-inbox-fill me-2"></i>รับหนังสือ
                    </h2>
                </div>
                <div class="d-none d-md-block"></div>
            </div>
            <br>
        </div>
    </div>
    
    <div class="container" style="position: relative;">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                
                <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success fade show border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                    <div>
                        <h6 class="mb-0 fw-bold">เพิ่มข้อมูลสำเร็จ!</h6>
                        <small>บันทึกข้อมูลเข้าระบบเรียบร้อยแล้ว</small>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if(isset($_GET['edit_success'])): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center">
                    <i class="bi bi-pencil-square fs-4 me-3 text-success"></i>
                    <div>
                        <h6 class="mb-0 fw-bold">อัปเดตสำเร็จ!</h6>
                        <small>แก้ไขข้อมูลในระบบเรียบร้อยแล้ว</small>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if(isset($_GET['delete_success'])): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center">
                    <i class="bi bi-trash-fill fs-4 me-3 text-danger"></i>
                    <div>
                        <h6 class="mb-0 fw-bold">ลบข้อมูลแล้ว</h6>
                        <small>รายการถูกนำออกจากระบบเรียบร้อยแล้ว</small>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="card custom-card mb-5">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="bi bi-list-columns-reverse me-2 text-primary"></i>รายการงานหนังสือที่รับ
                        </h5>
                        <button type="button" class="btn btn-primary-gradient px-4 py-2 fw-bold rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addDocumentModal">
                            <i class="bi bi-plus-lg me-1"></i> เพิ่ม
                        </button>
                    </div>
                    
                    <div class="card-body p-0">
                        <div class="table-responsive bg-white rounded-3 shadow-sm border" style="-webkit-overflow-scrolling: touch;">
                            <table class="table table-hover table-custom mb-0 align-middle" style="table-layout: fixed; width: 100%; min-width: 900px;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4" style="width: 15%; min-width: 140px;">เลขรับ / วันที่</th>
                                        <th style="width: 20%; min-width: 180px;">หนังสือต้นทาง</th>
                                        <th style="width: 15%; min-width: 140px;">ประเภทงาน</th>
                                        <th style="width: 35%; min-width: 300px;">ชื่องาน / รายละเอียด</th>
                                        <th class="text-center pe-4" style="width: 15%; min-width: 120px;">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($docs)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted border-0">
                                                <i class="bi bi-folder-x fs-1 d-block mb-3 text-light"></i>
                                                <span class="fw-semibold">ยังไม่มีข้อมูลในระบบ</span>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    
                                    <!-- แถวแจ้งเตือนกรณีค้นหาไม่เจอ -->
                                    <tr id="noDataFound" style="display: none;">
                                        <td colspan="5" class="text-center py-5 text-muted border-0">
                                            <i class="bi bi-search fs-1 d-block mb-3 opacity-50"></i>
                                            <span class="fw-semibold">ไม่พบข้อมูลที่ค้นหา</span>
                                        </td>
                                    </tr>

                                    <?php foreach ($docs as $doc): 
                                        $is_forwarded = !empty($doc['sent_to']);
                                    ?>
                                    <tr class="document-row">
                                        <td class="ps-4">
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold text-dark mb-1">
                                                    <?php echo !empty($doc['division_receive_number']) ? htmlspecialchars($doc['division_receive_number']) : '-'; ?>
                                                </span>
                                                <small class="text-muted">
                                                    <i class="bi bi-calendar3 me-1"></i>
                                                    <?php echo !empty($doc['division_receive_date']) ? date('d/m/Y', strtotime($doc['division_receive_date'])) : 'ยังไม่ระบุ'; ?>
                                                </small>
                                            </div>
                                        </td>
                                        <td style="word-break: break-all;">
                                            <div class="d-flex flex-column">
                                                <span class="text-primary fw-bold" style="font-size: 0.9rem;">
                                                    <?php echo !empty($doc['source_doc_number']) ? htmlspecialchars($doc['source_doc_number']) : '-'; ?>
                                                </span>
                                                <small class="text-muted text-truncate" title="<?php echo htmlspecialchars($doc['host_agency'] ?? ''); ?>">
                                                    <?php echo !empty($doc['host_agency']) ? htmlspecialchars($doc['host_agency']) : 'ไม่ระบุหน่วยงาน'; ?>
                                                </small>
                                                <?php if($is_forwarded): ?>
                                                    <div class="badge bg-success bg-opacity-10 text-success mt-1 d-inline-block text-start w-auto" style="font-size: 0.7rem;">
                                                        <i class="bi bi-check-circle-fill me-1"></i>ส่งต่อแล้ว
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="word-break: break-all;">
                                            <?php 
                                                $job_type_text = ($doc['job_type'] === 'อื่นๆ' && !empty($doc['job_type_other'])) 
                                                                 ? "อื่นๆ: " . $doc['job_type_other'] 
                                                                 : ($doc['job_type'] ?: '-');
                                                $badge_class = getBadgeClass($doc['job_type']);
                                            ?>
                                            <span class="badge badge-custom <?php echo $badge_class; ?>" style="white-space: normal; text-align: left;">
                                                <?php echo htmlspecialchars($job_type_text); ?>
                                            </span>
                                        </td>
                                        <td style="word-break: break-all;">
                                            <div>
                                                <span class="text-dark fw-bold"><?php echo htmlspecialchars($doc['doc_name'] ?: 'ไม่ระบุชื่องาน'); ?></span>
                                            </div>
                                            <?php if (!empty($doc['description'])): ?>
                                                <div class="text-muted small mt-1 text-truncate" style="max-width: 300px;" title="<?php echo htmlspecialchars($doc['description']); ?>">
                                                    <?php echo htmlspecialchars($doc['description']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if (!empty($doc['attachment_file'])): ?>
                                                <a href="uploads/documents/<?php echo $doc['attachment_file']; ?>" target="_blank" class="badge bg-danger mt-2 text-decoration-none">
                                                    <i class="bi bi-file-earmark-pdf me-1"></i> ดูไฟล์แนบ
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center pe-4">
                                            <div class="d-flex justify-content-center gap-2">
                                                <!-- ปุ่มดูรายละเอียด -->
                                                <button type="button" class="btn-action btn-view border-0" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $doc['id']; ?>" title="ดูรายละเอียดทั้งหมด">
                                                    <i class="bi bi-eye-fill"></i>
                                                </button>
                                                <!-- ปุ่มแก้ไข -->
                                                <button type="button" class="btn-action btn-edit border-0" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $doc['id']; ?>" title="แก้ไขรายละเอียดทั้งหมด">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <!-- ปุ่มลบ -->
                                                <?php if($is_forwarded): ?>
                                                    <button type="button" class="btn-action btn-disabled border-0" title="หนังสือถูกส่งให้ฝ่ายปฏิบัติการแล้ว ไม่สามารถลบได้" disabled>
                                                        <i class="bi bi-trash3-fill"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <a href="admin2.php?delete_id=<?php echo $doc['id']; ?>" class="btn-action btn-delete" onclick="return confirm('⚠️ คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้ ?')" title="ลบ">
                                                        <i class="bi bi-trash3-fill"></i>
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="addDocumentModal" tabindex="-1" aria-labelledby="addDocumentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        
        <form method="post" action="admin2.php" enctype="multipart/form-data" class="modal-content shadow-lg">
            
            <div class="modal-header border-bottom-0 pt-4 pb-2 px-4 bg-light">
                <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3 me-3">
                    <i class="bi bi-plus-square fs-4"></i>
                </div>
                <h5 class="modal-title fw-bold" id="addDocumentModalLabel">เพิ่มข้อมูลงานหนังสือใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body px-4 pb-4" style="background-color: #f8f9fa;">
                
                <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-box-arrow-in-right me-2"></i>ส่วนที่ 1: ข้อมูลการรับเรื่อง</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">ปีงบประมาณ</label>
                            <input type="text" name="fiscal_year" class="form-control" placeholder="เช่น 2569">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">เลขรับ กรมฯ</label>
                            <input type="text" name="dpt_receive_number" class="form-control" placeholder="ระบุเลขรับกรม">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">วันที่รับ กรมฯ</label>
                            <input type="date" name="dpt_receive_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">เลขรับ กอพ.</label>
                            <input type="text" name="division_receive_number" class="form-control" placeholder="ระบุเลขรับกอง">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">วันที่รับ กอพ.</label>
                            <input type="date" name="division_receive_date" class="form-control">
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-file-earmark-text me-2"></i>ส่วนที่ 2: ข้อมูลหนังสือและชื่องาน</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">หน่วยงานต้นเรื่อง</label>
                            <input type="text" name="host_agency" class="form-control" placeholder="ชื่อหน่วยงานผู้ขอ">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">สังกัดของหน่วยงานต้นเรื่อง</label>
                            <input type="text" name="host_agency_affiliation" class="form-control" placeholder="เช่น กรม..., กระทรวง...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">เลขที่หนังสือหน่วยงานต้นทาง</label>
                            <input type="text" name="source_doc_number" class="form-control" placeholder="อ้างอิงเลขที่หนังสือ">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">ประเภทงาน <span class="text-danger">*</span></label>
                            <select name="job_type" class="form-select" id="addJobTypeSelect" onchange="toggleAddOtherInput()" required>
                                <option value="" disabled selected>-- เลือกประเภท --</option>
                                <option value="รับเสด็จฯ">👑 รับเสด็จฯ</option>
                                <option value="ขอความอนุเคราะห์">🤝 ขอความอนุเคราะห์</option>
                                <option value="อื่นๆ">📝 อื่นๆ</option>
                            </select>
                            <input type="text" name="job_type_other" id="addJobTypeOther" class="form-control mt-2 d-none" placeholder="โปรดระบุประเภทงาน...">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold text-muted small">ชื่องาน <span class="text-danger">*</span></label>
                            <input type="text" name="doc_name" class="form-control" placeholder="ระบุชื่องาน" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold text-muted small">รายละเอียด</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="รายละเอียดเพิ่มเติม"></textarea>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-geo-alt me-2"></i>ส่วนที่ 3: สถานที่จัดงาน</h6>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold text-muted small">หน่วยงาน / ชื่อสถานที่จัดงาน</label>
                            <input type="text" name="venue_name" class="form-control" placeholder="ระบุสถานที่จัดงาน">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">จังหวัด</label>
                            <input type="text" name="venue_province" class="form-control" placeholder="จังหวัด">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">อำเภอ/เขต</label>
                            <input type="text" name="venue_district" class="form-control" placeholder="อำเภอ/เขต">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">ตำบล/แขวง</label>
                            <input type="text" name="venue_subdistrict" class="form-control" placeholder="ตำบล/แขวง">
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-calendar-event me-2"></i>ส่วนที่ 4: กำหนดการและระยะเวลาดำเนินงาน</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">วันที่จัดงาน (เริ่ม)</label>
                            <input type="date" name="event_start_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">วันที่จัดงาน (สิ้นสุด)</label>
                            <input type="date" name="event_end_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">วันที่ประชุมล่วงหน้า</label>
                            <input type="date" name="pre_meeting_date" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">วันที่ขอรับอุปกรณ์ยืม</label>
                            <input type="date" name="equipment_borrow_date" class="form-control">
                        </div>
                        <hr class="my-3 text-muted">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small text-primary">ระยะเวลาทำงาน กอพ. (เริ่ม)</label>
                            <input type="date" name="dpt_work_start" class="form-control border-primary">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small text-primary">ระยะเวลาทำงาน กอพ. (สิ้นสุด)</label>
                            <input type="date" name="dpt_work_end" class="form-control border-primary">
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-4 shadow-sm mb-2">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-person-lines-fill me-2"></i>ส่วนที่ 5: ผู้ประสานงานและไฟล์แนบ</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">ชื่อผู้ประสานงาน 1</label>
                            <input type="text" name="coordinator_1_name" class="form-control" placeholder="ชื่อ-สกุล">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">เบอร์โทร 1</label>
                            <input type="text" name="coordinator_1_phone" class="form-control" placeholder="เบอร์ติดต่อ">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">ชื่อผู้ประสานงาน 2</label>
                            <input type="text" name="coordinator_2_name" class="form-control" placeholder="ชื่อ-สกุล">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">เบอร์โทร 2</label>
                            <input type="text" name="coordinator_2_phone" class="form-control" placeholder="เบอร์ติดต่อ">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold text-muted small">ไฟล์เอกสารแนบ (PDF)</label>
                            <input type="file" name="attachment_file" class="form-control" accept=".pdf">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold text-muted small">หมายเหตุ</label>
                            <textarea name="remark" class="form-control" rows="2" placeholder="ข้อความหมายเหตุเพิ่มเติม"></textarea>
                        </div>
                    </div>
                </div>

            </div>
            <div class="modal-footer border-top-0 px-4 pb-4 pt-3 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" name="add_document" class="btn btn-primary-gradient px-5 py-2 fw-bold rounded-pill shadow-sm">
                    <i class="bi bi-save2 me-2"></i> บันทึกข้อมูล
                </button>
            </div>
            
        </form> 
    </div>
</div>
<script>
    function toggleAddOtherInput() {
        const select = document.getElementById('addJobTypeSelect');
        const otherInput = document.getElementById('addJobTypeOther');
        if (select.value === 'อื่นๆ') {
            otherInput.classList.remove('d-none');
            otherInput.required = true;
        } else {
            otherInput.classList.add('d-none');
            otherInput.required = false;
            otherInput.value = ''; 
        }
    }
</script>

<?php foreach ($docs as $doc): ?>

<!-- ==============================================
     MODAL ดูรายละเอียดข้อมูล (View Modal)
=============================================== -->
<div class="modal fade" id="viewModal<?php echo $doc['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            
            <div class="modal-header border-bottom-0 pt-4 pb-2 px-4 bg-light">
                <div class="bg-info bg-opacity-10 text-info p-2 rounded-3 me-3">
                    <i class="bi bi-eye-fill fs-4"></i>
                </div>
                <h5 class="modal-title fw-bold">รายละเอียดข้อมูลงาน (รหัส: <?php echo htmlspecialchars($doc['division_receive_number'] ?? $doc['id']); ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body px-4 pb-4" style="background-color: #f8f9fa;">
                
               <!-- ส่วนที่ 1 -->
                <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-box-arrow-in-right me-2"></i>ส่วนที่ 1: ข้อมูลการรับเรื่อง</h6>
                    <div class="row g-3">
                        
                        <!-- แถวบน: 3 คอลัมน์ -->
                        <div class="col-md-4 position-relative">
                            <div class="view-label">ปีงบประมาณ</div>
                            <div class="view-data fw-bold text-primary fs-5"><?php echo !empty($doc['fiscal_year']) ? htmlspecialchars($doc['fiscal_year']) : '-'; ?></div>
                            <!-- เส้นคั่นแนวตั้ง (จะแสดงเฉพาะบนจอคอมพิวเตอร์) -->
                            <div class="d-none d-md-block position-absolute top-0 end-0 h-100 border-end" style="width: 1px; margin-right: 15px;"></div>
                        </div>
                        <div class="col-md-4 ps-md-4">
                            <div class="view-label"><i class="bi bi-building text-muted me-1"></i>เลขรับ กรมฯ</div>
                            <div class="view-data fw-bold text-dark"><?php echo !empty($doc['dpt_receive_number']) ? htmlspecialchars($doc['dpt_receive_number']) : '-'; ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="view-label"><i class="bi bi-calendar-event text-muted me-1"></i>วันที่รับ กรมฯ</div>
                            <div class="view-data"><?php echo !empty($doc['dpt_receive_date']) ? date('d/m/Y', strtotime($doc['dpt_receive_date'])) : '-'; ?></div>
                        </div>
                        
                        <!-- แถวล่าง: 2 คอลัมน์ (ใช้ offset-md-4 ดันให้ตรงกับคอลัมน์ด้านบน) -->
                        <div class="col-md-4 offset-md-4 ps-md-4 pt-md-3 mt-md-1 border-top">
                            <div class="view-label"><i class="bi bi-diagram-3 text-muted me-1"></i>เลขรับ กอพ.</div>
                            <div class="view-data mb-0 fw-bold text-dark"><?php echo !empty($doc['division_receive_number']) ? htmlspecialchars($doc['division_receive_number']) : '-'; ?></div>
                        </div>
                        <div class="col-md-4 pt-md-3 mt-md-1 border-top">
                            <div class="view-label"><i class="bi bi-calendar-check text-muted me-1"></i>วันที่รับ กอพ.</div>
                            <div class="view-data mb-0"><?php echo !empty($doc['division_receive_date']) ? date('d/m/Y', strtotime($doc['division_receive_date'])) : '-'; ?></div>
                        </div>
                        
                    </div>
                </div>

                <!-- ส่วนที่ 2 -->
                <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-file-earmark-text me-2"></i>ส่วนที่ 2: ข้อมูลหนังสือและชื่องาน</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="view-label">หน่วยงานต้นเรื่อง</div>
                            <div class="view-data"><?php echo !empty($doc['host_agency']) ? htmlspecialchars($doc['host_agency']) : '-'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="view-label">สังกัดของหน่วยงานต้นเรื่อง</div>
                            <div class="view-data"><?php echo !empty($doc['host_agency_affiliation']) ? htmlspecialchars($doc['host_agency_affiliation']) : '-'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="view-label">เลขที่หนังสือหน่วยงานต้นทาง</div>
                            <div class="view-data"><?php echo !empty($doc['source_doc_number']) ? htmlspecialchars($doc['source_doc_number']) : '-'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="view-label">ประเภทงาน</div>
                            <div class="view-data">
                                <?php 
                                    $v_job_type_text = ($doc['job_type'] === 'อื่นๆ' && !empty($doc['job_type_other'])) 
                                                     ? "อื่นๆ: " . $doc['job_type_other'] 
                                                     : ($doc['job_type'] ?: '-');
                                    echo htmlspecialchars($v_job_type_text);
                                ?>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="view-label">ชื่องาน</div>
                            <div class="view-data fw-bold text-dark"><?php echo !empty($doc['doc_name']) ? htmlspecialchars($doc['doc_name']) : '-'; ?></div>
                        </div>
                        <div class="col-md-12">
                            <div class="view-label">รายละเอียด</div>
                            <div class="view-data"><?php echo !empty($doc['description']) ? nl2br(htmlspecialchars($doc['description'])) : '-'; ?></div>
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 3 -->
                <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-geo-alt me-2"></i>ส่วนที่ 3: สถานที่จัดงาน</h6>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <div class="view-label">หน่วยงาน / ชื่อสถานที่จัดงาน</div>
                            <div class="view-data"><?php echo !empty($doc['venue_name']) ? htmlspecialchars($doc['venue_name']) : '-'; ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="view-label">จังหวัด</div>
                            <div class="view-data"><?php echo !empty($doc['venue_province']) ? htmlspecialchars($doc['venue_province']) : '-'; ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="view-label">อำเภอ/เขต</div>
                            <div class="view-data"><?php echo !empty($doc['venue_district']) ? htmlspecialchars($doc['venue_district']) : '-'; ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="view-label">ตำบล/แขวง</div>
                            <div class="view-data"><?php echo !empty($doc['venue_subdistrict']) ? htmlspecialchars($doc['venue_subdistrict']) : '-'; ?></div>
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 4 -->
                <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-calendar-event me-2"></i>ส่วนที่ 4: กำหนดการและระยะเวลาดำเนินงาน</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="view-label">วันที่จัดงาน (เริ่ม)</div>
                            <div class="view-data"><?php echo !empty($doc['event_start_date']) ? date('d/m/Y', strtotime($doc['event_start_date'])) : '-'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="view-label">วันที่จัดงาน (สิ้นสุด)</div>
                            <div class="view-data"><?php echo !empty($doc['event_end_date']) ? date('d/m/Y', strtotime($doc['event_end_date'])) : '-'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="view-label">วันที่ประชุมล่วงหน้า</div>
                            <div class="view-data"><?php echo !empty($doc['pre_meeting_date']) ? date('d/m/Y', strtotime($doc['pre_meeting_date'])) : '-'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="view-label">วันที่ขอรับอุปกรณ์ยืม</div>
                            <div class="view-data"><?php echo !empty($doc['equipment_borrow_date']) ? date('d/m/Y', strtotime($doc['equipment_borrow_date'])) : '-'; ?></div>
                        </div>
                        <hr class="my-3 text-muted">
                        <div class="col-md-6">
                            <div class="view-label text-primary">ระยะเวลาทำงาน กอพ. (เริ่ม)</div>
                            <div class="view-data"><?php echo !empty($doc['dpt_work_start']) ? date('d/m/Y', strtotime($doc['dpt_work_start'])) : '-'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="view-label text-primary">ระยะเวลาทำงาน กอพ. (สิ้นสุด)</div>
                            <div class="view-data"><?php echo !empty($doc['dpt_work_end']) ? date('d/m/Y', strtotime($doc['dpt_work_end'])) : '-'; ?></div>
                        </div>
                    </div>
                </div>

                <!-- ส่วนที่ 5 -->
                <div class="bg-white p-4 rounded-4 shadow-sm mb-2">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-person-lines-fill me-2"></i>ส่วนที่ 5: ผู้ประสานงานและไฟล์แนบ</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="view-label">ชื่อผู้ประสานงาน 1</div>
                            <div class="view-data"><?php echo !empty($doc['coordinator_1_name']) ? htmlspecialchars($doc['coordinator_1_name']) : '-'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="view-label">เบอร์โทร 1</div>
                            <div class="view-data"><?php echo !empty($doc['coordinator_1_phone']) ? htmlspecialchars($doc['coordinator_1_phone']) : '-'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="view-label">ชื่อผู้ประสานงาน 2</div>
                            <div class="view-data"><?php echo !empty($doc['coordinator_2_name']) ? htmlspecialchars($doc['coordinator_2_name']) : '-'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="view-label">เบอร์โทร 2</div>
                            <div class="view-data"><?php echo !empty($doc['coordinator_2_phone']) ? htmlspecialchars($doc['coordinator_2_phone']) : '-'; ?></div>
                        </div>
                        <div class="col-md-12">
                            <div class="view-label">ไฟล์เอกสารแนบ (PDF)</div>
                            <div class="view-data">
                                <?php if (!empty($doc['attachment_file'])): ?>
                                    <a href="uploads/documents/<?php echo $doc['attachment_file']; ?>" target="_blank" class="badge bg-danger text-decoration-none">
                                        <i class="bi bi-file-earmark-pdf me-1"></i> ดูไฟล์แนบ
                                    </a>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="view-label">หมายเหตุ</div>
                            <div class="view-data"><?php echo !empty($doc['remark']) ? nl2br(htmlspecialchars($doc['remark'])) : '-'; ?></div>
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

<!-- ==============================================
     MODAL แก้ไขข้อมูล (Edit Modal)
=============================================== -->
<div class="modal fade" id="editModal<?php echo $doc['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        
        <form method="post" action="admin2.php" enctype="multipart/form-data" class="modal-content shadow-lg">
            
            <div class="modal-header border-bottom-0 pt-4 pb-2 px-4 bg-light">
                <div class="bg-warning bg-opacity-10 text-warning p-2 rounded-3 me-3">
                    <i class="bi bi-pencil-square fs-4"></i>
                </div>
                <h5 class="modal-title fw-bold">แก้ไขข้อมูลงาน (รหัส: <?php echo htmlspecialchars($doc['division_receive_number'] ?? $doc['id']); ?>)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">

            <div class="modal-body px-4 pb-4" style="background-color: #f8f9fa;">
                
                <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-box-arrow-in-right me-2"></i>ส่วนที่ 1: ข้อมูลการรับเรื่อง</h6>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">ปีงบประมาณ</label>
                            <input type="text" name="fiscal_year" class="form-control" value="<?php echo htmlspecialchars($doc['fiscal_year'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">เลขรับ กรมฯ</label>
                            <input type="text" name="dpt_receive_number" class="form-control" value="<?php echo htmlspecialchars($doc['dpt_receive_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">วันที่รับ กรมฯ</label>
                            <input type="date" name="dpt_receive_date" class="form-control" value="<?php echo $doc['dpt_receive_date'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">เลขรับ กอพ.</label>
                            <input type="text" name="division_receive_number" class="form-control" value="<?php echo htmlspecialchars($doc['division_receive_number'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">วันที่รับ กอพ.</label>
                            <input type="date" name="division_receive_date" class="form-control" value="<?php echo $doc['division_receive_date'] ?? ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-file-earmark-text me-2"></i>ส่วนที่ 2: ข้อมูลหนังสือและชื่องาน</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">หน่วยงานต้นเรื่อง</label>
                            <input type="text" name="host_agency" class="form-control" value="<?php echo htmlspecialchars($doc['host_agency'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">สังกัดของหน่วยงานต้นเรื่อง</label>
                            <input type="text" name="host_agency_affiliation" class="form-control" value="<?php echo htmlspecialchars($doc['host_agency_affiliation'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">เลขที่หนังสือหน่วยงานต้นทาง</label>
                            <input type="text" name="source_doc_number" class="form-control" value="<?php echo htmlspecialchars($doc['source_doc_number'] ?? ''); ?>">
                        </div>
                        
                        <?php
                            $main_types = ['รับเสด็จฯ', 'ขอความอนุเคราะห์'];
                            $is_other = !in_array($doc['job_type'], $main_types) && !empty($doc['job_type']);
                        ?>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">ประเภทงาน <span class="text-danger">*</span></label>
                            <select name="job_type" id="editJobTypeSelect<?php echo $doc['id']; ?>" class="form-select" required onchange="toggleEditOtherInput(<?php echo $doc['id']; ?>)">
                                <option value="รับเสด็จฯ" <?php echo ($doc['job_type'] == 'รับเสด็จฯ') ? 'selected' : ''; ?>>👑 รับเสด็จฯ</option>
                                <option value="ขอความอนุเคราะห์" <?php echo ($doc['job_type'] == 'ขอความอนุเคราะห์') ? 'selected' : ''; ?>>🤝 ขอความอนุเคราะห์</option>
                                <option value="อื่นๆ" <?php echo ($is_other || $doc['job_type'] == 'อื่นๆ') ? 'selected' : ''; ?>>📝 อื่นๆ</option>
                            </select>
                            <input type="text" name="job_type_other" id="editJobTypeOther<?php echo $doc['id']; ?>" 
                                   class="form-control mt-2 <?php echo $is_other ? '' : 'd-none'; ?>" 
                                   value="<?php echo $is_other ? htmlspecialchars($doc['job_type']) : ''; ?>" placeholder="ระบุประเภทงาน...">
                        </div>
                        
                        <div class="col-md-12">
                            <label class="form-label fw-semibold text-muted small">ชื่องาน <span class="text-danger">*</span></label>
                            <input type="text" name="doc_name" class="form-control" value="<?php echo htmlspecialchars($doc['doc_name'] ?? ''); ?>" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold text-muted small">รายละเอียด</label>
                            <textarea name="description" class="form-control" rows="2"><?php echo htmlspecialchars($doc['description'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-geo-alt me-2"></i>ส่วนที่ 3: สถานที่จัดงาน</h6>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold text-muted small">หน่วยงาน / ชื่อสถานที่จัดงาน</label>
                            <input type="text" name="venue_name" class="form-control" value="<?php echo htmlspecialchars($doc['venue_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">จังหวัด</label>
                            <input type="text" name="venue_province" class="form-control" value="<?php echo htmlspecialchars($doc['venue_province'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">อำเภอ/เขต</label>
                            <input type="text" name="venue_district" class="form-control" value="<?php echo htmlspecialchars($doc['venue_district'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">ตำบล/แขวง</label>
                            <input type="text" name="venue_subdistrict" class="form-control" value="<?php echo htmlspecialchars($doc['venue_subdistrict'] ?? ''); ?>">
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-calendar-event me-2"></i>ส่วนที่ 4: กำหนดการและระยะเวลาดำเนินงาน</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">วันที่จัดงาน (เริ่ม)</label>
                            <input type="date" name="event_start_date" class="form-control" value="<?php echo $doc['event_start_date'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">วันที่จัดงาน (สิ้นสุด)</label>
                            <input type="date" name="event_end_date" class="form-control" value="<?php echo $doc['event_end_date'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">วันที่ประชุมล่วงหน้า</label>
                            <input type="date" name="pre_meeting_date" class="form-control" value="<?php echo $doc['pre_meeting_date'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">วันที่ขอรับอุปกรณ์ยืม</label>
                            <input type="date" name="equipment_borrow_date" class="form-control" value="<?php echo $doc['equipment_borrow_date'] ?? ''; ?>">
                        </div>
                        <hr class="my-3 text-muted">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small text-primary">ระยะเวลาทำงาน กอพ. (เริ่ม)</label>
                            <input type="date" name="dpt_work_start" class="form-control border-primary" value="<?php echo $doc['dpt_work_start'] ?? ''; ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small text-primary">ระยะเวลาทำงาน กอพ. (สิ้นสุด)</label>
                            <input type="date" name="dpt_work_end" class="form-control border-primary" value="<?php echo $doc['dpt_work_end'] ?? ''; ?>">
                        </div>
                    </div>
                </div>

                <div class="bg-white p-4 rounded-4 shadow-sm mb-2">
                    <h6 class="fw-bold form-section-title"><i class="bi bi-person-lines-fill me-2"></i>ส่วนที่ 5: ผู้ประสานงานและไฟล์แนบ</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">ชื่อผู้ประสานงาน 1</label>
                            <input type="text" name="coordinator_1_name" class="form-control" value="<?php echo htmlspecialchars($doc['coordinator_1_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">เบอร์โทร 1</label>
                            <input type="text" name="coordinator_1_phone" class="form-control" value="<?php echo htmlspecialchars($doc['coordinator_1_phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">ชื่อผู้ประสานงาน 2</label>
                            <input type="text" name="coordinator_2_name" class="form-control" value="<?php echo htmlspecialchars($doc['coordinator_2_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">เบอร์โทร 2</label>
                            <input type="text" name="coordinator_2_phone" class="form-control" value="<?php echo htmlspecialchars($doc['coordinator_2_phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold text-muted small">อัปเดตไฟล์เอกสารใหม่ (PDF) <span class="fw-normal text-muted">*ปล่อยว่างหากใช้ไฟล์เดิม</span></label>
                            <input type="file" name="attachment_file" class="form-control" accept=".pdf">
                            <?php if (!empty($doc['attachment_file'])): ?>
                                <div class="mt-2 small text-success"><i class="bi bi-check-circle-fill me-1"></i> มีไฟล์แนบเดิมอยู่แล้ว: <a href="uploads/documents/<?php echo $doc['attachment_file']; ?>" target="_blank" class="text-decoration-none fw-bold">คลิกดูไฟล์</a></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label fw-semibold text-muted small">หมายเหตุ</label>
                            <textarea name="remark" class="form-control" rows="2"><?php echo htmlspecialchars($doc['remark'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

            </div>
            
            <div class="modal-footer border-top-0 px-4 pb-4 pt-3 bg-light">
                <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" name="edit_document" class="btn btn-primary-gradient px-5 py-2 fw-bold rounded-pill shadow-sm">
                    <i class="bi bi-save2 me-2"></i> บันทึก
                </button>
            </div>
            
        </form>
    </div>
</div>

<script>
function toggleEditOtherInput(id) {
    const select = document.getElementById('editJobTypeSelect' + id);
    const otherInput = document.getElementById('editJobTypeOther' + id);
    if (select.value === 'อื่นๆ') {
        otherInput.classList.remove('d-none');
        otherInput.required = true;
    } else {
        otherInput.classList.add('d-none');
        otherInput.required = false;
    }
}
</script>
<?php endforeach; ?>

<?php include 'footer.php'; ?>

<script>
const successAlert = document.querySelector('.alert');
if (successAlert) {
    setTimeout(() => {
        const bsAlert = new bootstrap.Alert(successAlert);
        bsAlert.close();
    }, 2000); 
}
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
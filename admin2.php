<?php
/**
 * Admin Document Management View
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/storage.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/controllers/admin_document_controller.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

// Process incoming mutations (Add, Edit, Delete, Cancel Forward)
handle_admin_document_actions();

$conn = get_db_connection();

// Pagination and listing
$limit = 10;
$page = clean_int($_GET['page'] ?? 1, 1);
$offset = ($page - 1) * $limit;

$total_rows = (int)$conn->query("SELECT COUNT(*) FROM documents")->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$stmt_docs = $conn->prepare("SELECT * FROM documents ORDER BY id DESC LIMIT :limit OFFSET :offset");
$stmt_docs->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt_docs->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_docs->execute();
$docs = $stmt_docs->fetchAll();

// Fetch assigned coordinators
$all_assigned_coords = $conn->query("
    SELECT da.doc_id, da.user_id, da.is_acknowledged, u.fname, u.lname 
    FROM document_assignments da
    JOIN users u ON da.user_id = u.id
")->fetchAll();

$coords_by_doc = [];
foreach ($all_assigned_coords as $c) {
    $coords_by_doc[$c['doc_id']][] = $c;
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
    
    <!-- เพิ่ม jQuery และ Select2 สำหรับทำ Dropdown ค้นหาได้ -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
    <style>
        html {
            height: 100%;
        }
        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            font-family: 'Prompt', sans-serif;
            background-color: #f8f9fa;
            margin: 0;
        }
        .content-wrapper {
            flex: 1 0 auto;
        }
        footer, .main-footer {
            flex-shrink: 0;
            margin-top: auto;
        }

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
        /* ปรับแต่ง Select2 ให้เข้ากับธีมของระบบ */
        .select2-container--bootstrap-5 .select2-selection {
            border-radius: 0.5rem;
            border: 1px solid #dee2e6;
            min-height: 42px;
        }
        .select2-container--bootstrap-5.select2-container--focus .select2-selection {
            border-color: #2a5298;
            box-shadow: 0 0 0 0.25rem rgba(42, 82, 152, 0.15);
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
            word-break: break-word;
        }
        
        /* Pagination */
        .pagination .page-link { color: #2a5298; border-radius: 0.5rem !important; margin: 0 3px; border: 1px solid #e2e8f0; }
        .pagination .page-item.active .page-link { background-color: #2a5298; border-color: #2a5298; color: white; }
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
                <?php if (!empty($_SESSION['upload_error'])): ?>
                    <div class="alert alert-danger fade show border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                        <div>
                            <h6 class="mb-0 fw-bold">ข้อผิดพลาดในการอัปโหลดไฟล์!</h6>
                            <div class="small"><?php echo e($_SESSION['upload_error']); unset($_SESSION['upload_error']); ?></div>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                <?php if (!empty($_SESSION['error_msg'])): ?>
                    <div class="alert alert-danger fade show border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                        <div>
                            <h6 class="mb-0 fw-bold">เกิดข้อผิดพลาด!</h6>
                            <div class="small"><?php echo e($_SESSION['error_msg']); unset($_SESSION['error_msg']); ?></div>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

 <?php 
// เช็คทั้ง Session และ GET เพื่อความครอบคลุม
if (isset($_SESSION['success_msg']) || isset($_SESSION['edit_success']) || isset($_SESSION['delete_success']) || isset($_SESSION['delete_error']) || isset($_GET['success']) || isset($_GET['edit_success']) || isset($_GET['delete_success']) || isset($_GET['delete_error'])): 
    
    // ตั้งค่าตัวแปรเบื้องต้น
    $alert_type = "alert-success";
    $icon = "bi-check-circle-fill text-success";
    $title = "สำเร็จ!";
    $text = "ดำเนินการเรียบร้อยแล้ว";

    // ตรวจสอบเงื่อนไขการแสดงผล
    if (isset($_SESSION['delete_success']) || isset($_GET['delete_success'])) {
        $alert_type = "alert-danger";
        $icon = "bi-trash-fill text-danger";
        $title = "ลบข้อมูลแล้ว";
        $text = "รายการถูกนำออกจากระบบเรียบร้อยแล้ว";
        unset($_SESSION['delete_success']);
    } elseif (isset($_SESSION['delete_error']) || isset($_GET['delete_error'])) {
        $alert_type = "alert-warning";
        $icon = "bi-exclamation-triangle-fill text-warning";
        $title = "ไม่สามารถลบได้!";
        $text = "เนื่องจากมีการรับเรื่องหรือมอบหมายคนแล้ว";
        unset($_SESSION['delete_error']);
    } elseif (isset($_SESSION['edit_success']) || isset($_GET['edit_success'])) {
        $alert_type = "alert-success";
        $icon = "bi-pencil-square text-success";
        $title = "อัปเดตสำเร็จ!";
        $text = "แก้ไขข้อมูลในระบบเรียบร้อยแล้ว";
        unset($_SESSION['edit_success']);
    } else {
        $alert_type = "alert-success";
        $icon = "bi-check-circle-fill text-success";
        $title = "บันทึกสำเร็จ!";
        $text = $_SESSION['success_msg'] ?? "บันทึกข้อมูลเข้าระบบเรียบร้อยแล้ว";
        unset($_SESSION['success_msg']);
    }
?>
    <div id="msg-alert" class="alert <?php echo $alert_type; ?> fade show border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center" role="alert">
        <i class="bi <?php echo $icon; ?> fs-4 me-3"></i>
        <div>
            <h6 class="mb-0 fw-bold"><?php echo $title; ?></h6>
            <small><?php echo $text; ?></small>
        </div>
        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
    </div>

    <script>
        // 1. ลบ Parameter บน URL ทันที (ถ้ามี) เพื่อให้รีเฟรชแล้วหายไปแน่นอน
        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            ['success', 'edit_success', 'delete_success', 'delete_error'].forEach(param => url.searchParams.delete(param));
            window.history.replaceState({}, '', url.href);
        }

        // 2. ปิด Alert อัตโนมัติใน 2 วินาที
        setTimeout(() => {
            const alertNode = document.getElementById('msg-alert');
            if (alertNode) {
                const bsAlert = new bootstrap.Alert(alertNode);
                bsAlert.close();
            }
        }, 2000);
    </script>
<?php endif; ?>


                <div class="card custom-card mb-5">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-3 px-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="bi bi-list-columns-reverse me-2 text-primary"></i>รายการงานหนังสือที่รับ
                        </h5>
                        <div class="d-flex flex-column flex-md-row gap-2 align-items-md-center w-100" style="max-width: 450px; justify-content: flex-end;">
                            <!-- ช่องค้นหา (Search Box) -->
                            <div class="position-relative w-100">
                                <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"><i class="bi bi-search"></i></span>
                                <input type="text" id="searchDocument" class="form-control bg-light border-0 shadow-sm ps-5 py-2" style="border-radius: 20px;" placeholder="ค้นหาเลขรับ, ชื่องาน, สถานที่...">
                            </div>
                            <button type="button" class="btn btn-primary-gradient px-4 py-2 fw-bold rounded-pill shadow-sm text-nowrap" data-bs-toggle="modal" data-bs-target="#addDocumentModal">
                                <i class="bi bi-plus-lg me-1"></i> เพิ่ม
                            </button>
                        </div>
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
                                        $assigned_coords = $coords_by_doc[$doc['id']] ?? [];
                                        $is_forwarded = !empty($doc['sent_to']) || !empty($assigned_coords);
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
                                                <?php if(!empty($doc['sent_to'])): ?>
                                                    <div class="badge bg-success bg-opacity-10 text-success mt-1 d-inline-block text-start w-auto" style="font-size: 0.7rem;">
                                                        <i class="bi bi-check-circle-fill me-1"></i>ส่งต่อแล้ว
                                                    </div>
                                                <?php elseif(!empty($assigned_coords)): ?>
                                                    <div class="badge bg-success bg-opacity-10 text-success mt-1 d-inline-block text-start w-auto" style="font-size: 0.7rem;">
                                                        <i class="bi bi-check-circle-fill me-1"></i>มอบหมายคนแล้ว
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
                                                <a href="download.php?id=<?php echo (int)$doc['id']; ?>" target="_blank" class="badge bg-danger mt-2 text-decoration-none">
                                                    <i class="bi bi-file-earmark-pdf me-1"></i> ดูไฟล์แนบ
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center pe-4">
                                            <div class="d-flex justify-content-center gap-2">
                                                <button type="button" class="btn-action btn-view border-0" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $doc['id']; ?>" title="ดูรายละเอียดทั้งหมด">
                                                    <i class="bi bi-eye-fill"></i>
                                                </button>
                                                <button type="button" class="btn-action btn-edit border-0" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $doc['id']; ?>" title="แก้ไขรายละเอียดทั้งหมด">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <?php if($is_forwarded): ?>
                                                    <button type="button" class="btn-action btn-disabled border-0" title="มีการรับเรื่องหรือมอบหมายคนแล้ว ไม่สามารถลบได้" disabled>
                                                        <i class="bi bi-trash3-fill"></i>
                                                    </button>
                                                <?php else: ?>
                                                    <form method="POST" action="admin2.php" class="d-inline m-0 p-0" onsubmit="return confirm('⚠️ คุณแน่ใจหรือไม่ว่าต้องการลบหนังสือ: \n\n&quot;<?php echo htmlspecialchars(addslashes($doc['doc_name'])); ?>&quot;\n\nการกระทำนี้ไม่สามารถย้อนกลับได้ ?');">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="delete_document">
                                                        <input type="hidden" name="id" value="<?php echo (int)$doc['id']; ?>">
                                                        <button type="submit" class="btn-action btn-delete border-0" title="ลบ">
                                                            <i class="bi bi-trash3-fill"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (isset($total_pages) && $total_pages > 1): ?>
                        <div class="d-flex justify-content-center p-3 mt-2 bg-white border-top">
                            <nav>
                                <ul class="pagination pagination-sm mb-0 shadow-sm rounded-3">
                                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page - 1; ?>"><i class="bi bi-chevron-left"></i> ก่อนหน้า</a>
                                    </li>
                                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                    </li>
                                    <?php endfor; ?>
                                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                        <a class="page-link" href="?page=<?php echo $page + 1; ?>">ถัดไป <i class="bi bi-chevron-right"></i></a>
                                    </li>
                                </ul>
                            </nav>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>

<div class="modal fade" id="addDocumentModal" aria-labelledby="addDocumentModalLabel" aria-hidden="true">  <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        
        <form method="post" action="admin2.php" enctype="multipart/form-data" class="modal-content shadow-lg">
            <?php echo csrf_field(); ?>
            
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
                            <?php 
                                $current_month = (int)date('m');
                                $current_year_be = (int)date('Y') + 543;
                                $default_fiscal_year = ($current_month >= 10) ? $current_year_be + 1 : $current_year_be;
                            ?>
                            <label class="form-label fw-semibold text-muted small">ปีงบประมาณ</label>
                            <input type="text" name="fiscal_year" class="form-control" value="<?php echo $default_fiscal_year; ?>" placeholder="เช่น <?php echo $default_fiscal_year; ?>">
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

                    <!-- <div class="col-md-12">
                        <hr class="my-2 opacity-50">
                        <label class="form-label fw-bold text-primary small mb-2"><i class="bi bi-person-badge-fill me-1"></i> ผู้ประสานงานฝ่ายปฏิบัติการ (กอพ.)</label>
                        <div class="text-muted small px-3 py-2 bg-light border rounded-3">- สามารถมอบหมายผู้ประสานงานได้หลังจากบันทึกเอกสารเรียบร้อยแล้ว ผ่านเมนู "มอบหมายผู้ประสานงาน" -</div>
                    </div> -->

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
                            <select name="venue_province" id="province" class="form-select">
                                <option value="" selected disabled>-- กำลังโหลดข้อมูล... --</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">อำเภอ/เขต</label>
                            <select name="venue_district" id="district" class="form-select" disabled>
                                <option value="" selected disabled>-- เลือกจังหวัดก่อน --</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">ตำบล/แขวง</label>
                            <select name="venue_subdistrict" id="subdistrict" class="form-select" disabled>
                                <option value="" selected disabled>-- เลือกอำเภอก่อน --</option>
                            </select>
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
                        <!-- <div class="col-md-6">
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
                        </div> -->
    <div class="col-md-12">
                    <label class="form-label fw-bold text-primary small mb-2"><i class="bi bi-person-badge-fill me-1"></i> ผู้ประสานงานฝ่ายปฏิบัติการ (กอพ.)</label>
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="text-muted small">- รอหัวหน้าฝ่ายปฏิบัติการมอบหมาย -</span>
                    </div>
                </div>
                        <div class="col-md-12">
                            <hr class="my-2 opacity-50">
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
                        <div class="col-md-4">
                            <div class="view-label">ปีงบประมาณ</div>
                            <div class="view-data"><?php echo !empty($doc['fiscal_year']) ? htmlspecialchars($doc['fiscal_year']) : '-'; ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="view-label">เลขรับ กรมฯ</div>
                            <div class="view-data"><?php echo !empty($doc['dpt_receive_number']) ? htmlspecialchars($doc['dpt_receive_number']) : '-'; ?></div>
                        </div>
                        <div class="col-md-4">
                            <div class="view-label">วันที่รับ กรมฯ</div>
                            <div class="view-data"><?php echo !empty($doc['dpt_receive_date']) ? date('d/m/Y', strtotime($doc['dpt_receive_date'])) : '-'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="view-label">เลขรับ กอพ.</div>
                            <div class="view-data"><?php echo !empty($doc['division_receive_number']) ? htmlspecialchars($doc['division_receive_number']) : '-'; ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="view-label">วันที่รับ กอพ.</div>
                            <div class="view-data"><?php echo !empty($doc['division_receive_date']) ? date('d/m/Y', strtotime($doc['division_receive_date'])) : '-'; ?></div>
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
                        <!-- <div class="col-md-6">
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
                        </div> -->
                                        <div class="col-md-12">
                    <div class="view-label text-primary"><i class="bi bi-person-badge-fill me-1"></i> ผู้ประสานงานฝ่ายปฏิบัติการ (กอพ.) ที่รับผิดชอบงานนี้</div>
                    <div class="p-3 bg-light rounded-3 border mt-2">
                        <?php 
                        $assigned_coords = $coords_by_doc[$doc['id']] ?? [];
                        if(!empty($assigned_coords)): 
                        ?>
                            <ul class="list-unstyled mb-0">
                            <?php foreach($assigned_coords as $coord): ?>
                                <li class="mb-1">
                                    <i class="bi bi-person-check-fill text-success me-2"></i> 
                                    <span class="fw-medium text-dark"><?php echo htmlspecialchars($coord['fname'] . ' ' . $coord['lname']); ?></span>
                                    <?php if(isset($coord['is_acknowledged']) && $coord['is_acknowledged'] == 1): ?>
                                        <span class="badge bg-success-subtle text-success ms-2 px-2 py-1" style="font-size: 0.75rem;"><i class="bi bi-check-circle-fill"></i> รับทราบแล้ว</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning-subtle text-dark ms-2 px-2 py-1" style="font-size: 0.75rem;"><i class="bi bi-clock"></i> รอรับทราบ</span>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                            </ul>
                        <?php else: ?>
                            <span class="text-muted small">- ยังไม่ได้มอบหมายผู้ประสานงาน -</span>
                        <?php endif; ?>
                    </div>
                </div>
                        <div class="col-md-12">
                            <div class="view-label">ไฟล์เอกสารแนบ (PDF)</div>
                            <div class="view-data">
                                <?php if (!empty($doc['attachment_file'])): ?>
                                    <a href="download.php?id=<?php echo (int)$doc['id']; ?>" target="_blank" class="badge bg-danger text-decoration-none">
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

<div class="modal fade" id="editModal<?php echo $doc['id']; ?>" aria-hidden="true">  <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
        
        <form method="post" action="admin2.php" enctype="multipart/form-data" class="modal-content shadow-lg">
            <?php echo csrf_field(); ?>
            
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
                            <?php 
                                $current_month_edit = (int)date('m');
                                $current_year_be_edit = (int)date('Y') + 543;
                                $default_fiscal_year_edit = ($current_month_edit >= 10) ? $current_year_be_edit + 1 : $current_year_be_edit;
                                $edit_fiscal_year = !empty($doc['fiscal_year']) ? htmlspecialchars($doc['fiscal_year']) : $default_fiscal_year_edit;
                            ?>
                            <label class="form-label fw-semibold text-muted small">ปีงบประมาณ</label>
                            <input type="text" name="fiscal_year" class="form-control" value="<?php echo $edit_fiscal_year; ?>">
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
                    <h6 class="fw-bold form-section-title"><i class="bi bi-geo-alt me-2"></i>ส่วนที่ 3: สถานที่จัดงาน (แก้ไข)</h6>
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label fw-semibold text-muted small">หน่วยงาน / ชื่อสถานที่จัดงาน</label>
                            <input type="text" name="venue_name" class="form-control" value="<?php echo htmlspecialchars($doc['venue_name'] ?? ''); ?>">
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">จังหวัด</label>
                            <select name="venue_province" id="edit_province_<?php echo $doc['id']; ?>" class="form-select edit-province" data-docid="<?php echo $doc['id']; ?>" data-saved="<?php echo htmlspecialchars($doc['venue_province'] ?? ''); ?>">
                                <option value="" selected disabled>-- กำลังโหลดข้อมูล... --</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">อำเภอ/เขต</label>
                            <select name="venue_district" id="edit_district_<?php echo $doc['id']; ?>" class="form-select" data-saved="<?php echo htmlspecialchars($doc['venue_district'] ?? ''); ?>" disabled>
                                <option value="" selected disabled>-- เลือกจังหวัดก่อน --</option>
                            </select>
                        </div>
                        
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted small">ตำบล/แขวง</label>
                            <select name="venue_subdistrict" id="edit_subdistrict_<?php echo $doc['id']; ?>" class="form-select" data-saved="<?php echo htmlspecialchars($doc['venue_subdistrict'] ?? ''); ?>" disabled>
                                <option value="" selected disabled>-- เลือกอำเภอก่อน --</option>
                            </select>
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
                        <!-- <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">ชื่อผู้ประสานงาน 1 (หน่วยงานต้นเรื่อง)</label>
                            <input type="text" name="coordinator_1_name" class="form-control" value="<?php echo htmlspecialchars($doc['coordinator_1_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">เบอร์โทร 1</label>
                            <input type="text" name="coordinator_1_phone" class="form-control" value="<?php echo htmlspecialchars($doc['coordinator_1_phone'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">ชื่อผู้ประสานงาน 2 (หน่วยงานต้นเรื่อง)</label>
                            <input type="text" name="coordinator_2_name" class="form-control" value="<?php echo htmlspecialchars($doc['coordinator_2_name'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-muted small">เบอร์โทร 2</label>
                            <input type="text" name="coordinator_2_phone" class="form-control" value="<?php echo htmlspecialchars($doc['coordinator_2_phone'] ?? ''); ?>">
                        </div> -->

                        <div class="col-md-12">
                            <!-- <hr class="my-2 opacity-50"> -->
                            <label class="form-label fw-bold text-primary small mb-2"><i class="bi bi-person-badge-fill me-1"></i> ผู้ประสานงานฝ่ายปฏิบัติการ (กอพ.) ที่รับผิดชอบงานนี้</label>
                            <div class="p-3 bg-light rounded-3 border">
                                <?php 
                                $assigned_coords = $coords_by_doc[$doc['id']] ?? [];
                                if(!empty($assigned_coords)): 
                                ?>
                                    <ul class="list-unstyled mb-0">
                                    <?php foreach($assigned_coords as $coord): ?>
                                        <li class="mb-1">
                                            <i class="bi bi-person-check-fill text-success me-2"></i> 
                                            <span class="fw-medium text-dark"><?php echo htmlspecialchars($coord['fname'] . ' ' . $coord['lname']); ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                    </ul>
                                <?php else: ?>
                                    <span class="text-muted small">- ยังไม่ได้มอบหมายผู้ประสานงาน -</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <hr class="my-2 opacity-50">
                            <label class="form-label fw-semibold text-muted small">อัปเดตไฟล์เอกสารใหม่ (PDF) <span class="fw-normal text-muted">*ปล่อยว่างหากใช้ไฟล์เดิม</span></label>
                            <input type="file" name="attachment_file" class="form-control" accept=".pdf">
                            <?php if (!empty($doc['attachment_file'])): ?>
                                <div class="mt-2 small text-success"><i class="bi bi-check-circle-fill me-1"></i> มีไฟล์แนบเดิมอยู่แล้ว: <a href="download.php?id=<?php echo (int)$doc['id']; ?>" target="_blank" class="text-decoration-none fw-bold">คลิกดูไฟล์</a></div>
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

<script>
    // 1. ตรวจสอบว่ามี success ใน URL หรือไม่
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('success')) {
        // ลบ parameter ออกจาก URL โดยไม่ทำให้หน้าจอรีเฟรซ (เปลี่ยน History แทน)
        const newUrl = window.location.pathname;
        window.history.replaceState({}, document.title, newUrl);
    }

    // 2. Logic เดิมของคุณในการปิด Alert
    const successAlert = document.querySelector('.alert');
    if (successAlert) {
        setTimeout(() => {
            const bsAlert = new bootstrap.Alert(successAlert);
            bsAlert.close();
        }, 2000); 
    }
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const jsonUrl = './thai-province-data-master/api/latest/province_with_district_and_sub_district.json';
    let thaiData = []; 

    // ดึงข้อมูลแค่ครั้งเดียว
    fetch(jsonUrl)
        .then(response => {
            if (!response.ok) throw new Error('ไม่พบไฟล์ JSON');
            return response.json();
        })
        .then(data => {
            thaiData = data;
            initAddForm();        // จัดการฟอร์มเพิ่มข้อมูล
            initAllEditForms();   // จัดการฟอร์มแก้ไขข้อมูลทั้งหมด
        })
        .catch(error => {
            console.error('Fetch Error:', error);
            alert('ไม่สามารถโหลดข้อมูลจังหวัดได้');
        });

    // ==========================================
    // ส่วนที่ 1: ควบคุม Modal "เพิ่มข้อมูล" (Add)
    // ==========================================
    function initAddForm() {
        const pSel = document.getElementById('province');
        const dSel = document.getElementById('district');
        const sSel = document.getElementById('subdistrict');

        if (!pSel || !dSel || !sSel) return;

        pSel.innerHTML = '<option value="" selected disabled>-- เลือกจังหวัด --</option>';
        dSel.innerHTML = '<option value="" selected disabled>-- เลือกอำเภอ/เขต --</option>';
        sSel.innerHTML = '<option value="" selected disabled>-- เลือกตำบล/แขวง --</option>';
        
        pSel.disabled = false;
        dSel.disabled = true;
        sSel.disabled = true;

        if (thaiData.length > 0) {
            thaiData.sort((a, b) => a.name_th.localeCompare(b.name_th, 'th')).forEach(p => {
                pSel.innerHTML += `<option value="${p.name_th}">${p.name_th}</option>`;
            });
        }

             // เรียกใช้ Select2 และผูกไว้กับ Modal ที่กำลังเปิดอยู่เพื่อไม่ให้โดนบัง
       $(pSel).select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $(pSel).closest('.modal') });
$(dSel).select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $(dSel).closest('.modal') });
$(sSel).select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $(sSel).closest('.modal') });
            $(pSel).on('change', function() {
                const pName = this.value;
                const pData = thaiData.find(item => item.name_th === pName);

                dSel.innerHTML = '<option value="" selected disabled>-- เลือกอำเภอ/เขต --</option>';
                sSel.innerHTML = '<option value="" selected disabled>-- เลือกตำบล/แขวง --</option>';
                dSel.disabled = true;
                sSel.disabled = true;

                if (pData && pData.districts) {
                    pData.districts.sort((a, b) => a.name_th.localeCompare(b.name_th, 'th')).forEach(d => {
                        dSel.innerHTML += `<option value="${d.name_th}">${d.name_th}</option>`;
                    });
                    dSel.disabled = false;
                }
                $(dSel).select2({ theme: 'bootstrap-5', dropdownParent: $(dSel).closest('.modal') });
                $(sSel).select2({ theme: 'bootstrap-5', dropdownParent: $(sSel).closest('.modal') });
            });

            $(dSel).on('change', function() {
                const pData = thaiData.find(item => item.name_th === pSel.value);
                const dData = pData ? pData.districts.find(item => item.name_th === this.value) : null;

                sSel.innerHTML = '<option value="" selected disabled>-- เลือกตำบล/แขวง --</option>';
                sSel.disabled = true;

                if (dData && dData.sub_districts) {
                    dData.sub_districts.sort((a, b) => a.name_th.localeCompare(b.name_th, 'th')).forEach(s => {
                        sSel.innerHTML += `<option value="${s.name_th}">${s.name_th}</option>`;
                    });
                    sSel.disabled = false; 
                }
                $(sSel).select2({ theme: 'bootstrap-5', dropdownParent: $(sSel).closest('.modal') });
            });
    }

    // ==========================================
    // ส่วนที่ 2: ควบคุม Modal "แก้ไขข้อมูล" ทุกอันพร้อมกัน
    // ==========================================
    function initAllEditForms() {
        // ค้นหาช่องเลือกจังหวัดของทุก Modal ที่มีคลาส .edit-province
        const provinceSelects = document.querySelectorAll('.edit-province');
        
        provinceSelects.forEach(epSel => {
            const docId = epSel.getAttribute('data-docid');
            const edSel = document.getElementById('edit_district_' + docId);
            const esSel = document.getElementById('edit_subdistrict_' + docId);

            if (!edSel || !esSel) return;

            let savedProvince = (epSel.getAttribute('data-saved') || '').trim();
            let savedDistrict = (edSel.getAttribute('data-saved') || '').trim();
            let savedSubdistrict = (esSel.getAttribute('data-saved') || '').trim();

            const nullWords = ['null', 'undefined', '-', ''];
            if (nullWords.includes(savedProvince.toLowerCase())) savedProvince = '';
            if (nullWords.includes(savedDistrict.toLowerCase())) savedDistrict = '';
            if (nullWords.includes(savedSubdistrict.toLowerCase())) savedSubdistrict = '';

            if (savedProvince === 'กรุงเทพ' || savedProvince === 'กทม' || savedProvince === 'กรุงเทพฯ') {
                savedProvince = 'กรุงเทพมหานคร';
            }

            epSel.innerHTML = '<option value="" selected disabled>-- เลือกจังหวัด --</option>';
            edSel.innerHTML = '<option value="" selected disabled>-- เลือกอำเภอ/เขต --</option>';
            esSel.innerHTML = '<option value="" selected disabled>-- เลือกตำบล/แขวง --</option>';
            
            epSel.disabled = false;
            edSel.disabled = true;
            esSel.disabled = true;

            // โหลดรายชื่อจังหวัด
            if (thaiData.length > 0) {
                thaiData.sort((a, b) => a.name_th.localeCompare(b.name_th, 'th')).forEach(p => {
                    const isSelected = (savedProvince && p.name_th === savedProvince) ? 'selected' : '';
                    epSel.innerHTML += `<option value="${p.name_th}" ${isSelected}>${p.name_th}</option>`;
                });
            }

            // เรียกใช้ Select2 ให้กับช่องใน Edit Modal
      $(epSel).select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $(epSel).closest('.modal') });
$(edSel).select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $(edSel).closest('.modal') });
$(esSel).select2({ theme: 'bootstrap-5', width: '100%', dropdownParent: $(esSel).closest('.modal') });
            if (savedProvince !== '') {
                loadEditDistricts(savedProvince, savedDistrict, epSel, edSel, esSel);
            } else {
                esSel.innerHTML = '<option value="" selected disabled>-- เลือกอำเภอก่อน --</option>';
            }

            $(epSel).on('change', function() {
                savedDistrict = ''; 
                savedSubdistrict = '';
                loadEditDistricts(this.value, null, epSel, edSel, esSel);
            });

            $(edSel).on('change', function() {
                savedSubdistrict = '';
                loadEditSubdistricts(epSel.value, this.value, null, esSel);
            });
        });

        function loadEditDistricts(pName, dToSelect, epSel, edSel, esSel) {
            edSel.innerHTML = '<option value="" selected disabled>-- เลือกอำเภอ/เขต --</option>';
            esSel.innerHTML = '<option value="" selected disabled>-- เลือกอำเภอก่อน --</option>';
            esSel.disabled = true;

            const pData = thaiData.find(item => item.name_th === pName);
            if (pData && pData.districts) {
                pData.districts.sort((a, b) => a.name_th.localeCompare(b.name_th, 'th')).forEach(d => {
                    const isSelected = (dToSelect && d.name_th === dToSelect) ? 'selected' : '';
                    edSel.innerHTML += `<option value="${d.name_th}" ${isSelected}>${d.name_th}</option>`;
                });
                edSel.disabled = false;

                if (dToSelect && dToSelect !== '') {
                    const sSaved = (esSel.getAttribute('data-saved') || '').trim();
                    loadEditSubdistricts(pName, dToSelect, sSaved, esSel);
                } else {
                    esSel.innerHTML = '<option value="" selected disabled>-- เลือกอำเภอก่อน --</option>';
                }
            }
                
                $(edSel).select2({ theme: 'bootstrap-5', dropdownParent: $(edSel).closest('.modal') });
                $(esSel).select2({ theme: 'bootstrap-5', dropdownParent: $(esSel).closest('.modal') });
        }

        function loadEditSubdistricts(pName, dName, sToSelect, esSel) {
            esSel.innerHTML = '<option value="" selected disabled>-- เลือกตำบล/แขวง --</option>';
            const pData = thaiData.find(item => item.name_th === pName);
            const dData = pData ? pData.districts.find(item => item.name_th === dName) : null;

            if (dData && dData.sub_districts) {
                dData.sub_districts.sort((a, b) => a.name_th.localeCompare(b.name_th, 'th')).forEach(s => {
                    const isSelected = (sToSelect && s.name_th === sToSelect) ? 'selected' : '';
                    esSel.innerHTML += `<option value="${s.name_th}" ${isSelected}>${s.name_th}</option>`;
                });
                esSel.disabled = false;
            }
                $(esSel).select2({ theme: 'bootstrap-5', dropdownParent: $(esSel).closest('.modal') });
        }
    }
});
</script>


<script>
// ค้นหาข้อมูล (Real-time Filter)
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById('searchDocument');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr.document-row');
            let hasVisibleRow = false;

            rows.forEach(function(row) {
                const textContent = row.textContent.toLowerCase();
                // ตรวจสอบว่ามีคำที่พิมพ์ในแถวหรือไม่
                if (textContent.includes(filter)) {
                    row.style.display = '';
                    hasVisibleRow = true;
                } else {
                    row.style.display = 'none';
                }
            });

            // ควบคุมการแสดงผล "ไม่พบข้อมูลที่ค้นหา"
            const noDataRow = document.getElementById('noDataFound');
            if (noDataRow) {
                noDataRow.style.display = (!hasVisibleRow && rows.length > 0) ? '' : 'none';
            }
        });
    }
});
</script>

</body>
</html>
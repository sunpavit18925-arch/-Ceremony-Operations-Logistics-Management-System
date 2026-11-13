<?php
session_start();
require 'db.php';
if(!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
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

// เปิดการแสดง Error เผื่อมีปัญหา (ถ้าเว็บใช้งานจริงแล้วให้เอา 3 บรรทัดนี้ออกครับ)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- 1. ส่วนจัดการการลบข้อมูล ---
if (isset($_GET['delete_id'])) {
    try {
        $id = $_GET['delete_id'];
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
        $doc_number = $_POST['doc_number'];
        
        // จัดการประเภทงาน
        $job_type = $_POST['job_type'];
        if ($job_type === 'อื่นๆ' && !empty($_POST['job_type_other'])) {
            $job_type = $_POST['job_type_other']; // เอาค่าที่พิมพ์มาใช้แทน
        }
        
        $doc_name = $_POST['doc_name'];
        $description = $_POST['description'];
     $date = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
$received_date = $date->format('Y-m-d H:i:s');
 $created_by = $_SESSION['username'] ?? 'Admin';
        
        $sql = "INSERT INTO documents (doc_number, job_type, doc_name, description, received_date, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $doc_number, 
            $job_type, 
            $doc_name, 
            $description,
            $received_date, 
            $created_by
        ]); 
        
        header("Location: admin2.php?success=1");
        exit();
    } catch (PDOException $e) {
        // ดัก Error ตอน Insert
        die("เกิดข้อผิดพลาดในการบันทึกข้อมูล (เพิ่มข้อมูล): " . $e->getMessage());
    }
}

// --- 3. จัดการการแก้ไขข้อมูลงาน (UPDATE) ---
if (isset($_POST['edit_document'])) {
    try {
        $id = $_POST['id'];
        $doc_number = $_POST['doc_number'];
        
        // จัดการประเภทงาน
        $job_type = $_POST['job_type'];
        if ($job_type === 'อื่นๆ' && !empty($_POST['job_type_other'])) {
            $job_type = $_POST['job_type_other'];
        }
        
        $doc_name = $_POST['doc_name'];
        $description = $_POST['description'];
        
        $sql = "UPDATE documents 
                SET doc_number = ?, job_type = ?, doc_name = ?, description = ? 
                WHERE id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([
            $doc_number, 
            $job_type, 
            $doc_name, 
            $description,
            $id
        ]); 
        
        header("Location: admin2.php?edit_success=1");
        exit();
    } catch (PDOException $e) {
        // ดัก Error ตอน Update
        die("เกิดข้อผิดพลาดในการบันทึกข้อมูล (แก้ไขข้อมูล): " . $e->getMessage());
    }
}

// --- 4. จัดการการ "ดึงหนังสือกลับ" (Cancel Forward) ---
if (isset($_GET['cancel_forward_id'])) {
    try {
        $id = $_GET['cancel_forward_id'];
        // เซ็ต sent_to ให้กลับมาเป็นค่าว่าง (NULL)
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
            // ถ้าเป็นค่าอื่นๆ ที่พิมพ์มาเอง ให้ใช้สีน้ำเงิน (Primary)
            return 'bg-primary text-white'; 
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบรับข้อมูลงาน | Admin</title>
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
            border-radius: 0.75rem;
            border: 1px solid #dee2e6;
            padding: 0.75rem 1rem;
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
        
        /* ปุ่ม Action ต่างๆ */
        .btn-action {
            width: 35px;
            height: 35px;
            border-radius: 0.5rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        .btn-edit { background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
        .btn-edit:hover { background-color: #ffc107; color: #fff; border-color: #ffc107; }
        
        .btn-delete { background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .btn-delete:hover { background-color: #dc3545; color: #fff; border-color: #dc3545; }
        
        /* สไตล์สำหรับปุ่มที่ถูกส่งแล้ว (ลบไม่ได้) */
        .btn-disabled { background-color: #e9ecef; color: #adb5bd; border: 1px solid #dee2e6; cursor: not-allowed; opacity: 0.6; }
        
        /* ปุ่มดึงข้อมูลกลับ (Undo) */
        .btn-undo { background-color: #e2e8f0; color: #475569; border: 1px solid #cbd5e1; }
        .btn-undo:hover { background-color: #cbd5e1; color: #1e293b; border-color: #94a3b8; }

        .modal-content {
            border-radius: 1.25rem;
            border: none;
        }
    </style>
</head>
<body>

<?php include 'menu.php'; ?>

<div class="content-wrapper">
    <div class="page-header text-center">
        <div class="container">
            <h2 class="fw-bold mb-2"><i class="bi bi-inbox-fill me-2"></i>รับหนังสือ</h2>
            <br>
        </div>
    </div>

    <div class="container" style="position: relative;">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
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

                <?php if(isset($_GET['cancel_success'])): ?>
                <div class="alert alert-warning border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center">
                    <i class="bi bi-arrow-counterclockwise fs-4 me-3 text-warning"></i>
                    <div>
                        <h6 class="mb-0 fw-bold">ดึงหนังสือกลับสำเร็จ!</h6>
                        <small>ยกเลิกการส่งต่อเรียบร้อยแล้ว ตอนนี้สามารถลบข้อมูลได้ปกติ</small>
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
    <table class="table table-hover table-custom mb-0 align-middle" style="table-layout: fixed; width: 100%; min-width: 850px;">
        <thead class="table-light">
            <tr>
                 <th class="ps-4" style="width: 20%; min-width: 160px;">วันที่</th>
                <th class="ps-4" style="width: 20%; min-width: 160px;">รหัสงาน</th>
                <th style="width: 15%; min-width: 140px;">ประเภท</th>
                <th style="width: 50%; min-width: 350px;">ชื่องาน / รายละเอียด</th>
                <th class="text-center pe-4" style="width: 15%; min-width: 120px;">จัดการ</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($docs)): ?>
                <tr>
                    <td colspan="4" class="text-center py-5 text-muted border-0">
                        <i class="bi bi-folder-x fs-1 d-block mb-3 text-light"></i>
                        <span class="fw-semibold">ยังไม่มีข้อมูลในระบบ</span>
                    </td>
                </tr>
            <?php endif; ?>
            
            <?php foreach ($docs as $doc): 
                $is_forwarded = !empty($doc['sent_to']);
            ?>
            <tr>
              <td class="ps-4">
        <div class="d-flex flex-column">
            <span class="fw-bold text-dark" style="font-size: 0.9rem;">
                <i class="bi bi-calendar3 me-1 text-secondary"></i>
                <?php echo date('d/m/Y', strtotime($doc['received_date'])); ?>
            </span>
            <small class="text-muted">
                <i class="bi bi-clock me-1"></i>
                <?php echo date('H:i', strtotime($doc['received_date'])); ?> น.
            </small>
        </div>
    </td>
                <td class="ps-4 text-primary" style="word-break: break-all;">
                    <?php 
                    $max_id_len = 20;
                    $doc_number = $doc['doc_number'] ?: '-';
                    
                    if (mb_strlen($doc_number, 'UTF-8') > $max_id_len) {
                        echo '<div>'; 
                        echo '  <div class="d-flex align-items-center text-short">';
                        echo '      <span class="fw-bold text-truncate">' . htmlspecialchars($doc_number) . '</span>';
                        echo '      <a href="javascript:void(0);" class="text-decoration-none ms-1 flex-shrink-0" onclick="toggleText(this)"><small>อ่านต่อ</small></a>';
                        echo '  </div>';
                        echo '  <div class="text-full d-none">';
                        echo '      <span class="fw-bold">' . htmlspecialchars($doc_number) . '</span>';
                        echo '      <a href="javascript:void(0);" class="text-decoration-none ms-1 text-secondary" onclick="toggleText(this)"><small>ย่อลง</small></a>';
                        echo '  </div>';
                        echo '</div>';
                    } else {
                        echo '<div class="fw-bold">' . htmlspecialchars($doc_number) . '</div>';
                    }
                    ?>
                    
                    <?php if($is_forwarded): ?>
                        <div class="badge bg-success bg-opacity-10 text-success mt-1 d-block w-100 text-wrap text-start" style="font-size: 0.7rem; line-height: 1.5;">
                            <i class="bi bi-check-circle-fill me-1"></i>มอบหมายผู้ประสานงานแล้ว
                        </div>
                    <?php endif; ?>
                </td>

                <td style="word-break: break-all;">
                    <?php 
                        $job_type_text = ($doc['job_type'] === 'อื่นๆ' && !empty($doc['job_type_other'])) 
                                         ? "อื่นๆ: " . $doc['job_type_other'] 
                                         : $doc['job_type'];

                        $badge_class = getBadgeClass($doc['job_type']);
                        $max_type_len = 15; 
                        $exclude_words = ['รับเสด็จฯ', 'ขอความอนุเคราะห์', 'รับเสด็จ']; 

                        if (!in_array(trim($doc['job_type']), $exclude_words) && mb_strlen($job_type_text, 'UTF-8') > $max_type_len) {
                            
                            echo '<div>';
                            echo '  <span class="badge badge-custom ' . $badge_class . ' text-short d-inline-flex align-items-center" style="max-width: 100%; white-space: nowrap;">';
                            echo '      <span class="text-truncate">' . htmlspecialchars($job_type_text) . '</span>';
                            echo '      <a href="javascript:void(0);" style="color: #fff; text-decoration: underline; margin-left: 5px; font-weight: normal;" class="flex-shrink-0" onclick="toggleText(this)"><small>อ่านต่อ</small></a>';
                            echo '  </span>';
                            
                            echo '  <span class="badge badge-custom ' . $badge_class . ' text-full d-none" style="white-space: normal; text-align: left; max-width: 100%;">';
                            echo '      ' . htmlspecialchars($job_type_text);
                            echo '      <a href="javascript:void(0);" style="color: #fff; text-decoration: underline; margin-left: 5px; font-weight: normal; opacity: 0.8;" onclick="toggleText(this)"><small>ย่อลง</small></a>';
                            echo '  </span>';
                            echo '</div>';
                            
                        } else {
                            echo '<span class="badge badge-custom ' . $badge_class . '" style="white-space: normal; text-align: left; display: inline-block; max-width: 100%;">';
                            echo htmlspecialchars($job_type_text);
                            echo '</span>';
                        }
                    ?>
                </td>

                <td style="word-break: break-all;">
                    <?php 
                    $max_name_len = 45; 
                    $max_desc_len = 50;
                    $doc_name = htmlspecialchars($doc['doc_name']);
                    
                    // --- 1. จัดการชื่องาน ---
                    if (mb_strlen($doc_name, 'UTF-8') > $max_name_len) {
                        echo '<div>'; 
                        echo '  <div class="d-flex align-items-center text-short">';
                        echo '      <span class="text-dark fw-medium text-truncate">' . $doc_name . '</span>';
                        echo '      <a href="javascript:void(0);" class="text-primary text-decoration-none ms-1 fw-bold flex-shrink-0" onclick="toggleText(this)"><small>อ่านต่อ</small></a>';
                        echo '  </div>';
                        echo '  <div class="text-full d-none">';
                        echo '      <span class="text-dark fw-medium">' . $doc_name . '</span>';
                        echo '      <a href="javascript:void(0);" class="text-secondary text-decoration-none ms-1 fw-bold" onclick="toggleText(this)"><small>ย่อลง</small></a>';
                        echo '  </div>';
                        echo '</div>';
                    } else {
                        echo '<div><span class="text-dark fw-medium">' . $doc_name . '</span></div>';
                    }

                    // --- 2. จัดการรายละเอียด ---
                    if (!empty($doc['description'])) {
                        $desc = htmlspecialchars($doc['description']);
                        if (mb_strlen($desc, 'UTF-8') > $max_desc_len) {
                            echo '<div class="mt-1">'; 
                            echo '  <div class="d-flex align-items-center text-muted small text-short">';
                            echo '      <span class="text-truncate">' . $desc . '</span>';
                            echo '      <a href="javascript:void(0);" class="text-primary text-decoration-none ms-1 fw-bold flex-shrink-0" onclick="toggleText(this)">อ่านต่อ</a>';
                            echo '  </div>';
                            echo '  <div class="text-muted small text-full d-none">';
                            echo '      <span>' . $desc . '</span>';
                            echo '      <a href="javascript:void(0);" class="text-secondary text-decoration-none ms-1 fw-bold" onclick="toggleText(this)">ย่อลง</a>';
                            echo '  </div>';
                            echo '</div>';
                        } else {
                            echo '<div class="text-muted small mt-1">' . $desc . '</div>';
                        }
                    }
                    ?>
                </td>

                <td class="text-center pe-4">
                    <div class="d-flex justify-content-center gap-2">
                        <button type="button" class="btn-action btn-edit border-0" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $doc['id']; ?>" title="แก้ไข">
                            <i class="bi bi-pencil-square"></i>
                        </button>

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
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-bottom-0 pt-4 pb-2 px-4 d-flex align-items-center">
                <div class="bg-primary bg-opacity-10 text-primary p-2 rounded-3 me-3">
                    <i class="bi bi-plus-square fs-4"></i>
                </div>
                <h5 class="modal-title fw-bold" id="addDocumentModalLabel">เพิ่มข้อมูลใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="admin2.php">
                <div class="modal-body px-4 pb-4">
                    <div class="row g-4">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold text-muted">เลขหนังสือ</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-hash text-muted"></i></span>
                                <input type="text" name="doc_number" class="form-control border-start-0 ps-0" placeholder="เช่น 001/2569">
                            </div>
                        </div>
                       <div class="col-md-7">
    <label class="form-label fw-semibold text-muted">ประเภทงาน <span class="text-danger">*</span></label>
   <select name="job_type" class="form-select" id="jobTypeSelect" onchange="toggleOtherInput()">
      <option value="" disabled selected>-- เลือกประเภท --</option>
        <option value="รับเสด็จฯ">👑 รับเสด็จฯ</option>
        <option value="ขอความอนุเคราะห์">🤝 ขอความอนุเคราะห์</option>
        <option value="อื่นๆ">📝 อื่นๆ</option>
    </select>
    
   <input type="text" name="job_type_other" id="jobTypeOther" class="form-control mt-2 d-none" placeholder="ระบุประเภทงาน...">
</div>

<script>
    function toggleOtherInput() {
        const select = document.getElementById('jobTypeSelect');
        const otherInput = document.getElementById('jobTypeOther');
        
        if (select.value === 'อื่นๆ') {
            // ถ้าเลือกอื่นๆ ให้เอาคลาส d-none ออกเพื่อแสดงช่อง และตั้งให้บังคับกรอก (required)
            otherInput.classList.remove('d-none');
            otherInput.required = true;
        } else {
            // ถ้าเลือกตัวเลือกอื่น ให้ใส่คลาส d-none เพื่อซ่อนช่อง ยกเลิกบังคับกรอก และล้างค่าทิ้ง
            otherInput.classList.add('d-none');
            otherInput.required = false;
            otherInput.value = ''; 
        }
    }
</script>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-muted">ชื่อ <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-card-text text-muted"></i></span>
                                <input type="text" name="doc_name" class="form-control border-start-0 ps-0" placeholder="ระบุชื่องาน..." required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-muted">รายละเอียด</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-card-list text-muted"></i></span>
                                <textarea name="description" class="form-control border-start-0 ps-0" rows="3" placeholder="ระบุรายละเอียดเพิ่มเติม (ถ้ามี)"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="add_document" class="btn btn-primary-gradient px-4 py-2 fw-bold rounded-pill shadow-sm">
                        <i class="bi bi-save2 me-2"></i> บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php foreach ($docs as $doc): ?>
<div class="modal fade" id="editModal<?php echo $doc['id']; ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-bottom-0 pt-4 pb-2 px-4 d-flex align-items-center">
                <div class="bg-warning bg-opacity-10 text-warning p-2 rounded-3 me-3">
                    <i class="bi bi-pencil-square fs-4"></i>
                </div>
                <h5 class="modal-title fw-bold">แก้ไขข้อมูลงาน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="post" action="admin2.php">
                <input type="hidden" name="id" value="<?php echo $doc['id']; ?>">
                <div class="modal-body px-4 pb-4">
                    <div class="row g-4">
                        <div class="col-md-5">
                            <label class="form-label fw-semibold text-muted">เลขหนังสือ</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-hash text-muted"></i></span>
                                <input type="text" name="doc_number" class="form-control border-start-0 ps-0" value="<?php echo htmlspecialchars($doc['doc_number']); ?>" placeholder="เช่น 001/2569">
                            </div>
                        </div>
                    <?php
// 1. กำหนดตัวเลือกหลักไว้ใน Array เพื่อใช้ตรวจสอบ
$main_types = ['รับเสด็จฯ', 'ขอความอนุเคราะห์'];

// 2. เช็คว่าค่าจาก DB เป็น "ค่าอื่นๆ" หรือไม่
// ถ้าไม่อยู่ในตัวเลือกหลัก และไม่ว่าง แสดงว่าเป็นค่าที่พิมพ์เพิ่มเอง
$is_other = !in_array($doc['job_type'], $main_types) && !empty($doc['job_type']);
?>

<div class="col-md-7">
    <label class="form-label fw-semibold text-muted">ประเภทงาน <span class="text-danger">*</span></label>
    
    <select name="job_type" id="editJobTypeSelect" class="form-select" required onchange="toggleEditOtherInput()">
        <option value="" disabled>-- เลือกประเภท --</option>
        
        <option value="รับเสด็จฯ" <?php echo ($doc['job_type'] == 'รับเสด็จฯ') ? 'selected' : ''; ?>>👑 รับเสด็จฯ</option>
        
        <option value="ขอความอนุเคราะห์" <?php echo ($doc['job_type'] == 'ขอความอนุเคราะห์') ? 'selected' : ''; ?>>🤝 ขอความอนุเคราะห์</option>
        
        <option value="อื่นๆ" <?php echo ($is_other || $doc['job_type'] == 'อื่นๆ') ? 'selected' : ''; ?>>📝 อื่นๆ</option>
    </select>

    <input type="text" name="job_type_other" id="editJobTypeOther" 
           class="form-control mt-2 <?php echo $is_other ? '' : 'd-none'; ?>" 
           placeholder="โปรดระบุประเภทงาน..."
           value="<?php echo $is_other ? htmlspecialchars($doc['job_type']) : ''; ?>">
</div>

<script>
function toggleEditOtherInput() {
    const select = document.getElementById('editJobTypeSelect');
    const otherInput = document.getElementById('editJobTypeOther');
    
    if (select.value === 'อื่นๆ') {
        otherInput.classList.remove('d-none');
        otherInput.required = true;
    } else {
        otherInput.classList.add('d-none');
        otherInput.required = false;
        // ไม่ต้องล้าง value ทิ้งในโหมดแก้ไข เผื่อผู้ใช้กดเปลี่ยนใจกลับมาเลือกอันเดิม
    }
}
</script>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-muted">ชื่อ <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-card-text text-muted"></i></span>
                                <input type="text" name="doc_name" class="form-control border-start-0 ps-0" value="<?php echo htmlspecialchars($doc['doc_name']); ?>" required>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label fw-semibold text-muted">รายละเอียด</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="bi bi-card-list text-muted"></i></span>
                                <textarea name="description" class="form-control border-start-0 ps-0" rows="3"><?php echo htmlspecialchars($doc['description']); ?></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4 pt-0">
                    <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" name="edit_document" class="btn btn-primary-gradient px-4 py-2 fw-bold rounded-pill shadow-sm">
                        <i class="bi bi-save2 me-2"></i> บันทึกการแก้ไข
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php include 'footer.php'; ?>
<script>
  // เลือก Alert ที่ต้องการ (ในที่นี้เลือกผ่าน class .alert)
const successAlert = document.querySelector('.alert');

if (successAlert) {
    // ตั้งเวลา 2000 มิลลิวินาที (2 วินาที)
    setTimeout(() => {
        // ใช้ Bootstrap API ในการสั่งปิด Alert อย่างนุ่มนวล
        const bsAlert = new bootstrap.Alert(successAlert);
        bsAlert.close();
    }, 2000); 
}
</script>

<script>
// ฟังก์ชันสำหรับสลับโชว์ข้อความ อ่านต่อ / ย่อลง
function toggleText(btn) {
    let parentSpan = btn.parentElement;
    
    // ถ้าคลิกจาก "อ่านต่อ" (ซ่อนตัวย่อ, โชว์ตัวเต็ม)
    if (parentSpan.classList.contains('text-short')) {
        parentSpan.classList.add('d-none');
        parentSpan.nextElementSibling.classList.remove('d-none');
    } 
    // ถ้าคลิกจาก "ย่อลง" (ซ่อนตัวเต็ม, โชว์ตัวย่อ)
    else {
        parentSpan.classList.add('d-none');
        parentSpan.previousElementSibling.classList.remove('d-none');
    }
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
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

// --- 1. ส่วนจัดการการลบข้อมูล ---
if (isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: admin2.php?delete_success=1");
    exit();
}

// --- 2. จัดการการเพิ่มข้อมูลงาน (INSERT) ---
if (isset($_POST['add_document'])) {
    $doc_number = $_POST['doc_number'];
    $job_type = $_POST['job_type'];
    $doc_name = $_POST['doc_name'];
    $description = $_POST['description'];
    $received_date = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO documents (doc_number, job_type, doc_name, description, received_date, created_by) 
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([
        $doc_number, 
        $job_type, 
        $doc_name, 
        $description,
        $received_date, 
        $_SESSION['username'] ?? 'Admin'
    ]); 
    
    header("Location: admin2.php?success=1");
    exit();
}

// --- 3. จัดการการแก้ไขข้อมูลงาน (UPDATE) ---
if (isset($_POST['edit_document'])) {
    $id = $_POST['id'];
    $doc_number = $_POST['doc_number'];
    $job_type = $_POST['job_type'];
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
}

// --- ดึงข้อมูลมาแสดง ---
if(isset($conn)) {
    $docs = $conn->query("SELECT * FROM documents ORDER BY id DESC")->fetchAll();
} else {
    $docs = []; 
}

function getBadgeClass($type) {
    switch ($type) {
        case 'รับเสด็จฯ': return 'bg-warning text-dark';
        case 'ขอความอนุเคราะห์': return 'bg-info text-dark';
        default: return 'bg-secondary text-white';
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
            <h2 class="fw-bold mb-2"><i class="bi bi-inbox-fill me-2"></i>ระบบรับหนังสือ</h2>
            <br>
        </div>
    </div>

    <div class="container" style="position: relative;">
        <div class="row justify-content-center">
            <div class="col-lg-10">
                
                <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center">
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
                            <i class="bi bi-list-columns-reverse me-2 text-primary"></i>รายการงานในระบบ
                        </h5>
                        <button type="button" class="btn btn-primary-gradient px-4 py-2 fw-bold rounded-pill shadow-sm" data-bs-toggle="modal" data-bs-target="#addDocumentModal">
                            <i class="bi bi-plus-lg me-1"></i> เพิ่มข้อมูลใหม่
                        </button>
                    </div>
                    
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover table-custom mb-0 align-middle">
                                <thead>
                                    <tr>
                                        <th class="ps-4" style="width: 20%;">รหัสงาน</th>
                                        <th style="width: 20%;">ประเภท</th>
                                        <th style="width: 45%;">ชื่องาน / รายละเอียด</th>
                                        <th class="text-center pe-4" style="width: 15%;">จัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($docs)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <i class="bi bi-folder-x fs-1 d-block mb-3 text-light"></i>
                                                <span class="fw-semibold">ยังไม่มีข้อมูลในระบบ</span>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    
                                    <?php foreach ($docs as $doc): ?>
                                    <tr>
                                        <td class="ps-4 fw-bold text-primary">
                                            <?php echo htmlspecialchars($doc['doc_number'] ?: '-'); ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-custom <?php echo getBadgeClass($doc['job_type']); ?>">
                                                <?php echo htmlspecialchars($doc['job_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="text-dark fw-medium"><?php echo htmlspecialchars($doc['doc_name']); ?></span>
                                            <?php if(!empty($doc['description'])): ?>
                                                <div class="text-muted small mt-1"><?php echo htmlspecialchars($doc['description']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center pe-4">
                                            <div class="d-flex justify-content-center gap-2">
                                                <button type="button" class="btn-action btn-edit border-0" data-bs-toggle="modal" data-bs-target="#editModal<?php echo $doc['id']; ?>" title="แก้ไข">
                                                    <i class="bi bi-pencil-square"></i>
                                                </button>
                                                <a href="admin2.php?delete_id=<?php echo $doc['id']; ?>" 
                                                   class="btn-action btn-delete" 
                                                   onclick="return confirm('⚠️ คุณแน่ใจหรือไม่ว่าต้องการลบรายการนี้ ?')" title="ลบ">
                                                    <i class="bi bi-trash3-fill"></i>
                                                </a>
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
                            <select name="job_type" class="form-select" required>
                                <option value="" disabled selected>-- เลือกประเภท --</option>
                                <option value="รับเสด็จฯ">👑 รับเสด็จฯ</option>
                                <option value="ขอความอนุเคราะห์">🤝 ขอความอนุเคราะห์</option>
                                <option value="อื่นๆ">📝 อื่นๆ</option>
                            </select>
                        </div>
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
                        <div class="col-md-7">
                            <label class="form-label fw-semibold text-muted">ประเภทงาน <span class="text-danger">*</span></label>
                            <select name="job_type" class="form-select" required>
                                <option value="" disabled>-- เลือกประเภท --</option>
                                <option value="รับเสด็จฯ" <?php echo ($doc['job_type'] == 'รับเสด็จฯ') ? 'selected' : ''; ?>>👑 รับเสด็จฯ</option>
                                <option value="ขอความอนุเคราะห์" <?php echo ($doc['job_type'] == 'ขอความอนุเคราะห์') ? 'selected' : ''; ?>>🤝 ขอความอนุเคราะห์</option>
                                <option value="อื่นๆ" <?php echo ($doc['job_type'] == 'อื่นๆ') ? 'selected' : ''; ?>>📝 อื่นๆ</option>
                            </select>
                        </div>
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
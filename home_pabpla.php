<?php
session_start();
require 'db.php';
// ตรวจสอบ Session Timeout (2 ชั่วโมง = 7200 วินาที)
$timeout_duration = 7200;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: index.php"); // หรือกลับไปหน้า login
    exit();
}
$_SESSION['last_activity'] = time(); // อัปเดตเวลาใช้งานล่าสุด


// 1. ตรวจสอบก่อนว่ามีการ Login มาหรือยัง
if (!isset($_SESSION['cid'])) {
    header("Location: login.php");
    exit();
}

$current_cid = $_SESSION['cid'];

// 2. ดึงข้อมูล Role จาก Database
$stmt_check = $conn->prepare("SELECT role FROM users WHERE cid = ? LIMIT 1");
$stmt_check->execute([$current_cid]);
$user_data = $stmt_check->fetch();
$user_role = $user_data ? trim(strtolower($user_data['role'])) : '';

// 3. เช็คสิทธิ์: ต้องเป็น head_operation เท่านั้น
if ($user_role !== 'head_operation' && $user_role !== 'super_admin') {
    echo "<script>alert('ปฏิเสธการเข้าถึง! อนุญาตเฉพาะหัวหน้าฝ่ายปฏิบัติการ'); window.location='welcome.php';</script>";
    exit(); 
}

// 4. ดึงรายชื่อลูกน้อง (เฉพาะ staff_operation)
$staff_list = [];
$stmt_staff = $conn->prepare("SELECT id, fname, lname FROM users WHERE role = 'staff_operation'");
$stmt_staff->execute();
$staff_list = $stmt_staff->fetchAll();

// 5. จัดการการส่งต่อหนังสือ (Action)
if (isset($_POST['forward_doc'])) {
    $doc_id = $_POST['doc_id'];
    $send_to = $_POST['send_to'];
    
    if (!empty($send_to)) {
       $sql = "UPDATE documents SET sent_to = :staff_id WHERE id = :doc_id";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$send_to, $doc_id]);
        header("Location: home_operation.php?forward_success=1");
        exit();
    }
}

// ---------------------------------------------------------
// 6. ระบบค้นหา & ดึงข้อมูลหนังสือ (Pagination + Search)
// ---------------------------------------------------------
$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// รับค่าคำค้นหาจาก GET
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$search_param = "%{$search}%";
$where_clause = "";

// สร้างเงื่อนไข WHERE ถ้ามีการพิมพ์ค้นหา
if (!empty($search)) {
    // ค้นหาจาก ชื่องาน หรือ รายละเอียดงาน
    $where_clause = " WHERE d.doc_name LIKE :search OR d.doc_detail LIKE :search ";
}

// นับจำนวนแถวทั้งหมด (เพื่อทำ Pagination)
$count_sql = "SELECT COUNT(*) FROM documents d" . $where_clause;
$total_stmt = $conn->prepare($count_sql);
if (!empty($search)) {
    $total_stmt->bindValue(':search', $search_param, PDO::PARAM_STR);
}
$total_stmt->execute();
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// คิวรีดึงข้อมูลจริง
$data_sql = "
    SELECT d.*, u.fname AS sender_fname, u.lname AS sender_lname 
    FROM documents d
    LEFT JOIN users u ON d.created_by = u.username_sso 
    $where_clause
    ORDER BY d.id DESC 
    LIMIT :limit OFFSET :offset
";
$stmt_docs = $conn->prepare($data_sql);
if (!empty($search)) {
    $stmt_docs->bindValue(':search', $search_param, PDO::PARAM_STR);
}
$stmt_docs->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt_docs->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt_docs->execute();
$docs = $stmt_docs->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการมอบหมายงาน - ระบบพลับพลาพิธี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        :root {
            --primary-dpt: #0f4c81; 
            --secondary-dpt: #1a365d;
            --gold-dpt: #c5a059; 
            --gold-light: #fdfaf2;
            --bg-body: #f4f7f9;
        }
        body { font-family: 'Prompt', sans-serif; background-color: var(--bg-body); color: #333; }
        
        .page-header-container {
            background: linear-gradient(135deg, var(--primary-dpt) 0%, var(--secondary-dpt) 100%);
            padding: 30px 0 40px; color: white; border-bottom: 4px solid var(--gold-dpt); margin-bottom: -30px;
        }
        .page-title { font-weight: 600; letter-spacing: 0.5px; }
        .page-subtitle { color: #d1e1f0; font-weight: 300; }
        
        .nav-system { background: white; padding: 10px 20px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); margin-bottom: 25px; display: inline-flex; }
        .nav-system .nav-link { color: #6c757d; font-weight: 500; border-radius: 8px; padding: 10px 20px; transition: all 0.3s ease; }
        .nav-system .nav-link:hover { background-color: #f8f9fa; color: var(--primary-dpt); }
        .nav-system .nav-link.active { background-color: var(--gold-light); color: var(--primary-dpt); border: 1px solid var(--gold-dpt); }

        .card-custom { border: none; border-radius: 12px; box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05); overflow: hidden; }
        .card-header-custom { background-color: white; border-bottom: 2px solid #f0f2f5; padding: 20px 25px; }
        
        .table-custom th { background-color: #f8f9fc; color: #4e5d78; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; border-bottom: 2px solid #e2e8f0; padding-top: 15px; padding-bottom: 15px; }
        .table-custom td { padding: 18px 10px; border-bottom: 1px solid #f0f2f5; vertical-align: middle; }
        .table-custom tbody tr:hover { background-color: #fcfcfc; }
        
        .doc-title { font-weight: 600; color: var(--primary-dpt); font-size: 1.05rem; margin-bottom: 4px; }
        .doc-desc { color: #6c757d; font-size: 0.9rem; }
        .badge-user { background-color: #eef2f7; color: #4e5d78; padding: 6px 12px; border-radius: 20px; font-size: 0.85rem; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; }
        
        .forward-wrapper { background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 4px; box-shadow: inset 0 1px 3px rgba(0,0,0,0.02); }
        .forward-select { border: none; font-size: 0.9rem; background: transparent; cursor: pointer; }
        .forward-select:focus { box-shadow: none; outline: none; }
        .btn-forward { background-color: var(--primary-dpt); border: none; border-radius: 6px; padding: 6px 15px; font-weight: 500; }
        .btn-forward:hover:not(:disabled) { background-color: var(--secondary-dpt); }
        .btn-forward:disabled { background-color: #a0aec0; cursor: not-allowed; }

        .pagination .page-link { color: var(--primary-dpt); border-radius: 6px; margin: 0 3px; border: 1px solid #e2e8f0; }
        .pagination .page-item.active .page-link { background-color: var(--primary-dpt); border-color: var(--primary-dpt); color: white; }
        
        /* สไตล์สำหรับ Search Bar */
        .search-container .input-group { border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; background: white; }
        .search-container .form-control { border: none; padding: 12px 15px; font-size: 0.95rem; }
        .search-container .form-control:focus { box-shadow: none; }
        .search-container .btn { border: none; padding: 12px 20px; background-color: white; border-left: 1px solid #f0f2f5; }
        .search-container .btn:hover { background-color: #f8f9fa; }
    </style>
</head>
<body>

    <?php require 'menu.php'; ?>

    <div class="content-wrapper">
        <div class="page-header-container">
            <div class="container">
                <div class="d-flex align-items-center">
                    <div class="icon-circle bg-white text-primary rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 50px; height: 50px;">
                        <i class="bi bi-bank fs-4" style="color: var(--gold-dpt);"></i>
                    </div>
                    <div>
                        <h3 class="page-title mb-0">ระบบบริหารจัดการงานพลับพลาพิธี</h3>
                        <div class="page-subtitle mt-1">กรมโยธาธิการและผังเมือง (ส่วนควบคุมการปฏิบัติการ)</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container py-5">
            
            <?php if(isset($_GET['forward_success'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm d-flex align-items-center rounded-3 border-0 border-start border-5 border-success mb-4" role="alert">
                <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                <div>
                    <strong class="text-dark">ดำเนินการสำเร็จ!</strong> 
                    <span class="text-muted">ระบบได้ทำการส่งต่อภาระงานให้เจ้าหน้าที่เรียบร้อยแล้ว</span>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <div class="d-flex justify-content-start align-items-end mb-3 flex-wrap gap-3">
                
                <div class="search-container mb-0">
                    <form action="home_operation.php" method="GET" class="m-0">
                        <div class="input-group">
                            <input type="text" name="search" class="form-control" placeholder="ค้นหารหัสงาน, ชื่องาน..." value="<?php echo htmlspecialchars($search); ?>" style="min-width: 300px;">
                            <?php if(!empty($search)): ?>
                                <a href="home_operation.php" class="btn btn-outline-secondary d-flex align-items-center" title="ล้างการค้นหา">
                                    <i class="bi bi-x-lg text-danger"></i>
                                </a>
                            <?php endif; ?>
                            <button class="btn btn-outline-secondary" type="submit">
                                <i class="bi bi-search" style="color: var(--primary-dpt);"></i> ค้นหา
                            </button>
                        </div>
                    </form>
                </div>

                <!-- <div class="nav-system mb-0"> -->
                    <!-- <ul class="nav nav-pills mb-0"> -->
                        <!-- <li class="nav-item">
                            <a class="nav-link active" href="home_operation.php">
                                <i class="bi bi-inbox-fill me-2"></i> มอบหมายงาน (หัวหน้าส่วน)
                            </a>
                        </li> -->
                        <!-- <li class="nav-item">
                            <a class="nav-link" href="display_support.php">
                                <i class="bi bi-diagram-3-fill me-2"></i> ข้อมูลการจัดบุคลากร/ยานพาหนะ
                            </a>
                        </li> -->
                    <!-- </ul> -->
                <!-- </div> -->
            </div> 

            <div class="card card-custom">
                <div class="card-header-custom d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold d-flex align-items-center" style="color: var(--primary-dpt);">
                        <i class="bi bi-journal-check fs-4 me-2" style="color: var(--gold-dpt);"></i>
                        รายการแจ้งงาน / รับเสด็จฯ
                        <?php if(!empty($search)): ?>
                            <span class="ms-3 fs-6 fw-normal text-muted">ผลการค้นหาสำหรับ: "<strong class="text-dark"><?php echo htmlspecialchars($search); ?></strong>"</span>
                        <?php endif; ?>
                    </h5>
                    <span class="badge rounded-pill px-3 py-2 fw-medium" style="background-color: var(--gold-light); color: var(--gold-dpt); border: 1px solid var(--gold-dpt);">
                        พบ <?php echo $total_rows; ?> รายการ
                    </span>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-custom align-middle mb-0">
                            <thead>
                                <tr>
                                    <th class="ps-4 text-start" style="width: 45%;">รายละเอียดงาน (รหัส/ชื่องาน)</th>
                                    <th class="text-center" style="width: 15%;">วันที่บันทึกระบบ</th>
                                    <th class="text-center" style="width: 15%;">ผู้ประสานงานต้นทาง</th>
                                    <th style="width: 25%;">มอบหมายชุดปฏิบัติการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($docs)): ?>
                                <tr>
                                    <td colspan="4" class="text-center py-5">
                                        <div class="text-muted d-flex flex-column align-items-center py-4">
                                            <i class="bi bi-search fs-1 mb-3 text-light"></i>
                                            <span class="fs-5 fw-medium">ไม่พบรายการข้อมูลที่ค้นหา</span>
                                            <small>ลองใช้คำค้นหาอื่น หรือตรวจสอบตัวสะกดอีกครั้ง</small>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($docs as $doc): ?>
                                <tr>
                                    <td class="ps-4">
                                        <div class="doc-title">
                                            <i class="bi bi-file-earmark-text text-muted me-1 fs-6"></i> 
                                            <?php echo htmlspecialchars($doc['doc_name']); ?>
                                        </div>
                                        <div class="doc-desc text-truncate mt-1" style="max-width: 400px;" title="<?php echo htmlspecialchars($doc['doc_detail']); ?>">
                                            <?php echo htmlspecialchars($doc['doc_detail']); ?>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-flex flex-column align-items-center text-muted">
                                            <span class="fw-medium text-dark">
                                                <?php echo date('d/m/Y', strtotime($doc['received_date'])); ?>
                                            </span>
                                            <span class="small" style="font-size: 0.8rem;">
                                                <i class="bi bi-clock me-1"></i><?php echo date('H:i', strtotime($doc['received_date'])); ?> น.
                                            </span>
                                        </div>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge-user">
                                            <i class="bi bi-person-circle text-secondary"></i>
                                            <?php 
                                            if (!empty($doc['sender_fname'])) {
                                                echo htmlspecialchars($doc['sender_fname'] . " " . $doc['sender_lname']);
                                            } else {
                                                echo htmlspecialchars($doc['created_by']); 
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td class="pe-4"> 
                                        <form method="post" class="forward-wrapper d-flex gap-1 align-items-center m-0">
                                            <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                            
                                            <select name="send_to" class="form-select form-select-sm forward-select w-100" required onchange="checkStaffStatus(this)">
                                                <option value="" disabled selected>-- ระบุเจ้าหน้าที่ --</option>
                                                <?php foreach($staff_list as $s): 
                                                    $already_sent = (($doc['sent_to'] ?? '') == $s['id']); 
                                                ?>
                                                    <option value="<?php echo $s['id']; ?>" 
                                                            data-sent="<?php echo $already_sent ? 'true' : 'false'; ?>"
                                                            class="<?php echo $already_sent ? 'text-success fw-bold' : 'text-dark'; ?>"
                                                            <?php echo $already_sent ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($s['fname'] . " " . $s['lname']); ?>
                                                        <?php echo $already_sent ? ' (มอบหมายแล้ว)' : ''; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>

                                            <button type="submit" name="forward_doc" class="btn btn-sm btn-forward text-white d-flex align-items-center justify-content-center gap-1" style="min-width: 85px; transition: all 0.3s;">
                                                <span class="btn-text">สั่งการ</span> <i class="bi bi-send ms-1" style="font-size: 0.8rem;"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if ($total_pages > 1): ?>
                    <div class="d-flex justify-content-center p-3 mt-2 bg-white border-top">
                        <nav>
                            <?php $search_query = !empty($search) ? "&search=" . urlencode($search) : ""; ?>
                            <ul class="pagination pagination-sm mb-0 flex-wrap justify-content-center gap-1">
                                <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $search_query; ?>"><i class="bi bi-chevron-left"></i> ย้อนกลับ</a>
                                </li>
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $i; ?><?php echo $search_query; ?>"><?php echo $i; ?></a>
                                </li>
                                <?php endfor; ?>
                                <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                    <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $search_query; ?>">หน้าถัดไป <i class="bi bi-chevron-right"></i></a>
                                </li>
                            </ul>
                        </nav>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <?php require 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function checkStaffStatus(selectElement) {
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        const isSent = selectedOption.getAttribute('data-sent') === 'true';
        const form = selectElement.closest('form');
        const submitBtn = form.querySelector('button[name="forward_doc"]');
        const btnText = submitBtn.querySelector('.btn-text');
        const btnIcon = submitBtn.querySelector('i.bi-send, i.bi-check2-all');

        if (isSent) {
            selectElement.style.color = '#198754'; 
            selectElement.classList.add('fw-bold');
            
            submitBtn.disabled = true;
            submitBtn.style.backgroundColor = '#e9ecef';
            submitBtn.style.color = '#6c757d';
            btnText.innerText = 'สั่งแล้ว';
            if(btnIcon) btnIcon.className = 'bi bi-check2-all ms-1';
        } else {
            selectElement.style.color = '#212529'; 
            selectElement.classList.remove('fw-bold');
            
            submitBtn.disabled = false;
            submitBtn.style.backgroundColor = 'var(--primary-dpt)'; 
            submitBtn.style.color = 'white';
            btnText.innerText = 'สั่งการ';
            if(btnIcon) btnIcon.className = 'bi bi-send ms-1';
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll('.forward-select').forEach(select => {
            checkStaffStatus(select);
        });
    });
    </script>
</body>
</html>
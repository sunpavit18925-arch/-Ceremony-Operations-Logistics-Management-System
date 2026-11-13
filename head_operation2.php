<?php
session_start();
require 'db.php';

if(!isset($_SESSION['role']) || ($_SESSION['role'] !== 'head_operation' && $_SESSION['role'] !== 'super_admin')) {
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

// 1. ตรวจสอบก่อนว่ามีการ Login มาหรือยัง (เช็คจาก CID ใน Session)
if (!isset($_SESSION['cid'])) {
    header("Location: login.php"); // หรือหน้า Login ของคุณ
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

$current_cid = $_SESSION['cid'];

// 2. ดึงข้อมูล Role จาก Database โดยตรง (สดใหม่ที่สุด)
$stmt_check = $conn->prepare("SELECT role FROM users WHERE cid = ? LIMIT 1");
$stmt_check->execute([$current_cid]);
$user_data = $stmt_check->fetch();

// กำหนดตัวแปร role จาก DB (ถ้าไม่เจอให้เป็นค่าว่าง)
$user_role = $user_data ? trim(strtolower($user_data['role'])) : '';

// 3. เช็คสิทธิ์: ต้องเป็น head_operation เท่านั้น
if ($user_role !== 'head_operation' && $user_role !== 'super_admin') {
    ?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@400;500&display=swap" rel="stylesheet">
    <style>
    body {
        font-family: 'Prompt', sans-serif;
        background-color: #f4f7fc;
        display: flex;
        justify-content: center;
        align-items: center;
        height: 100vh;
        margin: 0;
    }

    .error-box {
        background: white;
        padding: 40px;
        border-radius: 16px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        text-align: center;
        border-top: 5px solid #dc3545;
        width: 90%;
        max-width: 400px;
    }

    h2 {
        color: #dc3545;
        margin-bottom: 10px;
    }

    p {
        color: #6c757d;
        margin-bottom: 25px;
    }

    .btn-back {
        background: #1C3B47;
        color: white;
        text-decoration: none;
        padding: 10px 20px;
        border-radius: 8px;
        font-size: 14px;
        display: inline-block;
    }
    </style>
</head>

<body>
    <div class="error-box">
        <h2>ปฏิเสธการเข้าถึง</h2>
        <p>สิทธิ์ของคุณในระบบคือ:
            <strong><?php echo $user_role ?: 'ไม่พบข้อมูลสิทธิ์'; ?></strong><br>หน้านี้อนุญาตเฉพาะหัวหน้าฝ่ายปฏิบัติการเท่านั้น
        </p>
        <a href="welcome.php" class="btn-back">กลับหน้าหลัก</a>
    </div>
</body>

</html>
<?php
    exit(); 
}

// --- ถ้าผ่านเงื่อนไขด้านบนมาได้ แสดงว่าเป็น head_operation จริง ---

// 4. ดึงรายชื่อลูกน้อง (เฉพาะ staff_operation) เพื่อใช้ส่งต่อ
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
        header("Location: head_operation.php?forward_success=1");
        exit();
    }
}

// 6. ดึงข้อมูลหนังสือ (Pagination)
$limit = 10; 
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$total_stmt = $conn->query("SELECT COUNT(*) FROM documents");
$total_rows = $total_stmt->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// --- แก้ไขส่วนที่ 6 ใน head_operation.php ---
$stmt_docs = $conn->prepare("
    SELECT d.*, u.fname AS sender_fname, u.lname AS sender_lname 
    FROM documents d
    LEFT JOIN users u ON d.created_by = u.username_sso 
    ORDER BY d.id DESC 
    LIMIT :limit OFFSET :offset
");
// หมายเหตุ: ผม Join ด้วย username_sso ตามที่เห็นในรูป Database ของคุณ 
// แต่ถ้าในอนาคตคุณเก็บ created_by เป็น ID ตัวเลข ให้เปลี่ยน u.username_sso เป็น u.id ครับ
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
    <title>รายการจัดการหนังสือ - ฝ่ายปฏิบัติการ</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
    body {
        font-family: 'Prompt', sans-serif;
        background-color: #f0f2f5;
        color: #2c3e50;
    }

    /* Card Design */
    .card {
        border: none;
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.06);
        overflow: hidden;
    }

    .bg-primary-custom {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%) !important;
        color: white;
        padding: 1.25rem 1.5rem;
        border-bottom: none;
    }

    /* Table Design */
    .table-custom {
        margin-bottom: 0;
    }

    .table-custom thead th {
        background-color: #f8f9fa;
        color: #6c757d;
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        padding: 1rem;
        border-bottom: 2px solid #e9ecef;
        text-align: center;
    }

    .table-custom tbody td {
        padding: 1.25rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f3f5;
        transition: background-color 0.2s ease;
    }

    .table-custom tbody tr:hover td {
        background-color: #f8faff;
    }

    /* Typography & Badges */
    .doc-title {
        font-weight: 600;
        color: #1e293b;
        font-size: 1.05rem;
        margin-bottom: 0.3rem;
    }

    .doc-desc {
        font-size: 0.85rem;
        color: #64748b;
        line-height: 1.4;
    }

    .badge-user {
        background-color: #e0f2fe;
        color: #0369a1;
        padding: 0.5rem 0.9rem;
        border-radius: 50rem;
        font-size: 0.85rem;
        font-weight: 500;
        display: inline-flex;
        align-items: center;
        gap: 0.4rem;
    }

    /* Form Controls */
    .forward-wrapper {
        background-color: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 12px;
        padding: 0.4rem;
        transition: border-color 0.2s;
    }

    .forward-wrapper:focus-within {
        border-color: #2a5298;
        background-color: #fff;
        box-shadow: 0 0 0 0.2rem rgba(42, 82, 152, 0.1);
    }

    .forward-select {
        font-size: 0.9rem;
        border: none;
        background-color: transparent;
        box-shadow: none !important;
    }

    .btn-forward {
        border-radius: 8px;
        padding: 0.4rem 1rem;
        font-weight: 500;
        background: #2a5298;
        color: white;
        border: none;
        transition: all 0.2s;
    }

    .btn-forward:hover {
        background: #1e3c72;
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(30, 60, 114, 0.25);
        color: white;
    }

    /* Pagination */
    .pagination {
        gap: 0.3rem;
    }

    .page-link {
        border-radius: 8px !important;
        border: none;
        color: #2a5298;
        font-weight: 500;
        padding: 0.5rem 0.85rem;
    }

    .page-item.active .page-link {
        background-color: #2a5298;
        color: white;
        box-shadow: 0 4px 8px rgba(42, 82, 152, 0.2);
    }

    /* Alert */
    .alert-custom {
        border-radius: 16px;
        background-color: #d1e7dd;
        color: #0a3622;
        border: none;
        box-shadow: 0 4px 12px rgba(25, 135, 84, 0.1);
    }
    </style>
</head>

<body>

    <div class="container py-5">

        <?php if(isset($_GET['forward_success'])): ?>
        <div class="alert alert-custom alert-dismissible fade show mb-4 d-flex align-items-center" role="alert">
            <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
            <div>
                <strong>สำเร็จ!</strong> ส่งต่อหนังสือให้เจ้าหน้าที่เรียบร้อยแล้ว
            </div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header bg-primary-custom d-flex justify-content-between align-items-center">
                <h5 class="mb-0 fw-bold d-flex align-items-center">
                    <i class="bi bi-journal-bookmark-fill fs-4 me-2"></i>รายการหนังสือล่าสุด <span
                        class="fs-6 fw-normal ms-2 opacity-75">(สำหรับหัวหน้าฝ่ายปฏิบัติการ)</span>
                </h5>
                <span class="badge bg-white text-primary rounded-pill px-3 py-2 fw-semibold shadow-sm">
                    ทั้งหมด <?php echo $total_rows; ?> รายการ
                </span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4 text-start" style="width: 40%;">ข้อมูลหนังสือ</th>
                                <th style="width: 15%;">วันที่รับ</th>
                                <th style="width: 20%;">ผู้บันทึก</th>
                                <th style="width: 25%;">ดำเนินการส่งต่อ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($docs)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-5">
                                    <div class="text-muted d-flex flex-column align-items-center">
                                        <i class="bi bi-inbox fs-1 mb-2 opacity-50"></i>
                                        <span>ไม่มีรายการหนังสือในขณะนี้</span>
                                    </div>
                                </td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ($docs as $doc): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="doc-title"><?php echo htmlspecialchars($doc['doc_name']); ?></div>
                                    <div class="doc-desc text-truncate" style="max-width: 350px;">
                                        <?php echo htmlspecialchars($doc['doc_detail']); ?>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex flex-column align-items-center text-muted">
                                        <i class="bi bi-calendar3 small mb-1"></i>
                                        <span
                                            class="small fw-medium"><?php echo date('d/m/Y', strtotime($doc['received_date'])); ?></span>
                                        <span class="small"
                                            style="font-size: 0.75rem;"><?php echo date('H:i', strtotime($doc['received_date'])); ?>
                                            น.</span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge-user">
                                        <i class="bi bi-person-circle"></i>
                                        <?php 
            // ถ้า Join สำเร็จและมีชื่อ ให้แสดงชื่อ-นามสกุล
            if (!empty($doc['sender_fname'])) {
                echo htmlspecialchars($doc['sender_fname'] . " " . $doc['sender_lname']);
            } else {
                // ถ้าไม่มีข้อมูล (เช่น user นั้นถูกลบ) ให้แสดง username_sso เดิมไปก่อน
                echo htmlspecialchars($doc['created_by']); 
            }
        ?>
                                    </span>
                                </td>
                                <td class="pe-4">
                                    <form method="post" class="forward-wrapper d-flex gap-1">
                                        <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                        <select name="send_to" class="form-select form-select-sm forward-select"
                                            required>
                                            <option value="" disabled selected>-- เลือกเจ้าหน้าที่ --</option>
                                            <?php foreach($staff_list as $s): ?>
                                            <option value="<?php echo $s['id']; ?>"
                                                <?php echo (($doc['sent_to'] ?? '') == $s['id']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($s['fname'] . " " . $s['lname']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="forward_doc"
                                            class="btn btn-sm btn-forward d-flex align-items-center gap-1">
                                            <span>ส่ง</span> <i class="bi bi-send-fill small"></i>
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
                <div class="d-flex justify-content-center border-top p-4 bg-white">
                    <nav>
                        <ul class="pagination pagination-sm mb-0 shadow-sm rounded-3 flex-wrap justify-content-center gap-1">
                            <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page - 1; ?>"><i
                                        class="bi bi-chevron-left"></i> ก่อนหน้า</a>
                            </li>
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $page + 1; ?>">ถัดไป <i
                                        class="bi bi-chevron-right"></i></a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
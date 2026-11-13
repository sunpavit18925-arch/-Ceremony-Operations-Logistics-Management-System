<?php
require 'db.php';


if(!isset($_SESSION['role']) || (!in_array($_SESSION['role'], ['head_vehicle', 'staff_vehicle']) && $_SESSION['role'] !== 'super_admin')) {
    header ("Location: home.php");
    exit();
}

// --- Pagination Setup ---
$limit = 9;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$stmt_total = $conn->query("SELECT COUNT(*) FROM documents");
$total_rows = $stmt_total->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// ดึงรายการหนังสือทั้งหมดจากตาราง documents 
// พร้อมเช็คว่ามีการจัดชุดในตาราง job_assignments ไปแล้วกี่คน
$query = "
    SELECT 
        d.id, 
        d.doc_number, 
        d.doc_name, 
        d.job_type, 
        d.sent_to,
        (SELECT COUNT(*) FROM job_assignments ja WHERE ja.job_id = d.id) as team_assigned_count
    FROM documents d
    ORDER BY d.id DESC
    LIMIT :limit OFFSET :offset
";
$stmt = $conn->prepare($query);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$documents = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>เลือกหนังสือเพื่อจัดชุดปฏิบัติงาน - ระบบพับพลาพิธี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        body { background-color: #f8fafc; font-family: 'Prompt', sans-serif; }
        .job-card { cursor: pointer; transition: 0.3s; border: none; border-radius: 15px; }
        .job-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
        .status-badge { font-size: 0.8rem; padding: 0.4em 0.8em; border-radius: 30px; }
        
        /* Pagination Styles */
        .pagination .page-link { color: #0ea5e9; border: none; margin: 0 4px; border-radius: 8px !important; font-weight: 500; transition: all 0.2s; }
        .pagination .page-item.active .page-link { background-color: #0ea5e9; color: white; box-shadow: 0 4px 10px rgba(14, 165, 233, 0.3); }
        .pagination .page-link:hover:not(.active) { background-color: #e0f2fe; color: #0284c7; }
    </style>
</head>
<body>

<?php include 'menu.php'; ?>

<div class="container py-5">
    <div class="text-center mb-5">
        <h2 class="fw-bold text-dark">เลือกหนังสือเพื่อจัดการชุดปฏิบัติงาน</h2>
        <p class="text-muted">คลิกที่รายการหนังสือเพื่อเข้าไปดำเนินการจัดรายชื่อ</p>
    </div>

    <div class="row g-4 justify-content-center">
        <?php if (empty($documents)): ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="bi bi-file-earmark-x fs-1 opacity-50 d-block mb-3"></i>
                <p>ยังไม่มีข้อมูลหนังสือในระบบ</p>
            </div>
        <?php else: ?>
            <?php foreach ($documents as $doc): 
                
                // --- ตรรกะการคำนวณสถานะและสี ---
                if ($doc['team_assigned_count'] > 0) {
                    // มีรายชื่ออยู่ในตาราง job_assignments แล้ว
                    $status_text = "จัดชุดแล้ว";
                    $status_class = "bg-success text-white";
                    $status_icon = "bi-check-circle-fill";
                } elseif (!empty($doc['sent_to'])) {
                    // ไม่มีรายชื่อใน job_assignments แต่ sent_to ไม่ใช่ NULL (ส่งหนังสือให้คนรับผิดชอบแล้ว)
                    $status_text = "มอบหมายผู้ประสานงานแล้ว";
                    $status_class = "bg-warning text-dark";
                    $status_icon = "bi-person-lines-fill";
                } else {
                    // ไม่มีรายชื่อจัดชุด และ ยังไม่ได้ส่งหนังสือให้ใคร
                    $status_text = "ยังไม่จัดชุด";
                    $status_class = "bg-danger text-white";
                    $status_icon = "bi-x-circle-fill";
                }

                // ป้องกันกรณีไม่ระบุประเภทงาน
                $job_type_display = !empty($doc['job_type']) ? $doc['job_type'] : 'ไม่ระบุประเภท';
            ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card job-card shadow-sm h-100" onclick="location.href='manage_operation.php?job_id=<?php echo $doc['id']; ?>'">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary">
                                    <i class="bi bi-tag-fill me-1"></i> <?php echo htmlspecialchars($job_type_display); ?>
                                </span>
                                <span class="badge status-badge <?php echo $status_class; ?>">
                                    <i class="bi <?php echo $status_icon; ?> me-1"></i> <?php echo $status_text; ?>
                                </span>
                            </div>
                            
                            <h5 class="card-title fw-bold text-dark mb-2 line-clamp-2">
                                <?php echo htmlspecialchars($doc['doc_name']); ?>
                            </h5>
                            <p class="text-muted small mb-0">
                                <i class="bi bi-file-text me-1"></i> เลขที่หนังสือ: <span class="fw-medium text-dark"><?php echo htmlspecialchars($doc['doc_number'] ?: '-'); ?></span>
                            </p>
                        </div>
                        <div class="card-footer bg-transparent border-top-0 text-end pb-3 pt-0">
                            <span class="btn btn-outline-primary btn-sm rounded-pill px-3 fw-bold">
                                จัดการชุดปฏิบัติงาน <i class="bi bi-arrow-right-short fs-5 align-middle"></i>
                            </span>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

<?php if (isset($total_pages) && $total_pages > 1): ?>
<div class="d-flex justify-content-center mt-5">
    <nav>
        <ul class="pagination pagination-sm mb-0 shadow-sm rounded-3 flex-wrap justify-content-center gap-1">
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

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
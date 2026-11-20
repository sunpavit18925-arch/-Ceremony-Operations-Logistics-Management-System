<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

require_role([ROLE_HEAD_OPERATION, ROLE_SUPER_ADMIN]);

$conn = get_db();
$current_page = 'view_system_logs.php';

// 4. ดึงข้อมูล Logs
$limit = 15;
$page = clean_int($_GET['page'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$stmt_total = $conn->query("SELECT COUNT(*) FROM system_logs");
$total_rows = $stmt_total->fetchColumn();
$total_pages = ceil($total_rows / $limit);

$logs = [];
try {
    $stmt = $conn->prepare("
        SELECT sl.*, d.doc_name, d.doc_number 
        FROM system_logs sl
        LEFT JOIN documents d ON sl.job_id = d.id
        ORDER BY sl.created_at DESC, sl.id DESC
        LIMIT :limit OFFSET :offset
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DB Error in view_system_logs: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ประวัติการทำงานของระบบ | ระบบพลับพลาพิธี</title>
    
    <!-- ใช้ CSS ชุดเดียวกับหน้า manage_operation2 ที่เมนูขึ้นปกติ -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f8f9fa; }
        .page-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white; padding: 2.5rem 0; margin-bottom: -3rem;
            border-radius: 0 0 1.5rem 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .custom-card { 
            border: none; border-radius: 1.25rem; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.08); 
            background: #ffffff; margin-bottom: 2rem; overflow: hidden;
        }
        .table-custom thead th { 
            background-color: #f1f4f8; color: #495057; font-weight: 600; 
            font-size: 0.85rem; padding: 1rem; border-bottom: none;
        }
        .table-custom tbody td { padding: 1rem; border-bottom: 1px solid #f1f4f8; }
        
        /* Pagination */
        .pagination .page-link { color: #2a5298; border-radius: 0.5rem !important; margin: 0 3px; border: 1px solid #e2e8f0; }
        .pagination .page-item.active .page-link { background-color: #2a5298; border-color: #2a5298; color: white; }
        .pagination .page-link:hover:not(.active) { background-color: #f1f4f8; }
    </style>
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>

<!-- เรียกเมนูขึ้นมาเหมือนหน้า manage_operation2 -->
<?php include 'menu.php'; ?>

<div class="content-wrapper">
    <div class="page-header text-center">
        <div class="container position-relative">
            <!-- ปุ่มย้อนกลับ -->
            <a href="home_head_operation.php" class="btn btn-outline-light rounded-pill position-absolute start-0 top-0 d-inline-flex align-items-center mt-1 ms-2 ms-md-0">
                <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
            </a>
            <h2 class="fw-bold mb-2 pt-5 pt-md-0"><i class="bi bi-clock-history me-2"></i>ประวัติการทำงานของระบบ </h2>
            <br>
        </div>
    </div>

    <div class="container mt-5">
        <div class="card custom-card">
            <div class="card-header bg-white border-bottom-0 pt-4 px-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h5 class="fw-bold text-dark mb-0 d-inline-block me-2">รายการอัปเดตข้อมูล</h5>
                    <span class="badge bg-primary rounded-pill align-text-bottom">ทั้งหมด <?= $total_rows ?> รายการ</span>
                </div>
                <div class="position-relative">
                    <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchLog" class="form-control bg-light border-0 shadow-sm ps-5 py-2" style="border-radius: 20px; width: 100%;" placeholder="ค้นหารหัสงาน, รายละเอียด...">
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-custom mb-0 align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">วันเวลา</th>
                                <th>รหัสงาน</th>
                                <th>ประเภท</th>
                                <th>รายละเอียด</th>
                                <th class="pe-4 text-center">ผู้ทำรายการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($logs)): ?>
                                <tr><td colspan="5" class="text-center py-5 text-muted">ไม่พบประวัติการทำงาน</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                            <tr class="log-row">
                                    <td class="ps-4 text-muted" style="font-size: 0.85rem;">
                                        <i class="bi bi-calendar-event me-1"></i>
                                        <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?> น.
                                    </td>
                                <td class="fw-bold text-primary">
                                    #<?= htmlspecialchars($log['job_id'] ?? '-') ?>
                                    <?php if (!empty($log['doc_name'])): ?>
                                        <div class="text-muted fw-normal small mt-1" style="font-size: 0.75rem; line-height: 1.4;">
                                            <i class="bi bi-file-earmark-text"></i> <?= htmlspecialchars($log['doc_number'] ? $log['doc_number'].' - '.$log['doc_name'] : $log['doc_name']) ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                    <td>
                                        <?php 
                                            $type = $log['action_type'];
                                            if ($type === 'CREATE_SET') echo '<span class="badge bg-info text-dark">สร้างชุดงาน</span>';
                                            elseif ($type === 'ASSIGN_STAFF') echo '<span class="badge bg-success">เพิ่มบุคลากร</span>';
                                            elseif ($type === 'REMOVE_STAFF') echo '<span class="badge bg-danger">ลบบุคลากร</span>';
                                            else echo '<span class="badge bg-secondary">'.htmlspecialchars($type).'</span>';
                                        ?>
                                    </td>
                                    <td class="small text-dark"><?= htmlspecialchars($log['action_detail']) ?></td>
                                    <td class="pe-4 text-center fw-medium text-secondary">
                                        <i class="bi bi-person-circle me-1"></i>
                                        <?= htmlspecialchars($log['performed_by']) ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <!-- แถวสำหรับแสดงเมื่อค้นหาไม่พบข้อมูล -->
                            <tr id="noLogFound" style="display: none;">
                                <td colspan="5" class="text-center py-5">
                                    <i class="bi bi-search text-muted fs-1 opacity-50 mb-3 d-block"></i>
                                    <h6 class="text-muted fw-bold">ไม่พบประวัติการทำงานที่ค้นหา</h6>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (isset($total_pages) && $total_pages > 1): ?>
                <div class="d-flex justify-content-center p-4 border-top bg-white">
                    <nav>
                        <ul class="pagination pagination-sm mb-0 shadow-sm rounded-3 flex-wrap justify-content-center gap-1">
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

<?php if(file_exists('footer.php')) include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ค้นหาประวัติการทำงาน (Real-time Filter)
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById('searchLog');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const rows = document.querySelectorAll('tbody tr.log-row');
            let hasVisibleRow = false;

            rows.forEach(function(row) {
                const textContent = row.textContent.toLowerCase();
                if (textContent.includes(filter)) {
                    row.style.display = '';
                    hasVisibleRow = true;
                } else {
                    row.style.display = 'none';
                }
            });

            const noDataRow = document.getElementById('noLogFound');
            if (!hasVisibleRow && rows.length > 0) {
                if (noDataRow) noDataRow.style.display = '';
            } else {
                if (noDataRow) noDataRow.style.display = 'none';
            }
        });
    }
});
</script>
</body>
</html>

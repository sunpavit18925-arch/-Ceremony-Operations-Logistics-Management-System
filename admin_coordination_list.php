<?php
session_start();
require 'db.php'; // ดึงไฟล์เชื่อมต่อฐานข้อมูลของคุณมาใช้

if(!isset($_SESSION['role']) || ($_SESSION['role'] !== 'admin' && $_SESSION['role'] !== 'super_admin')) {
    header ("Location: home.php");
    exit();
}
?>

<?php
// --- Pagination Setup ---
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

$stmt_total = $conn->query("SELECT COUNT(*) FROM documents");
$total_rows = $stmt_total->fetchColumn();
$total_pages = ceil($total_rows / $limit);

// ดึงข้อมูลทั้งหมดจากตาราง documents เรียงจากใหม่ไปเก่า
try {
    $stmt = $conn->prepare("SELECT * FROM documents ORDER BY created_at DESC LIMIT :limit OFFSET :offset");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $documents = $stmt->fetchAll();
} catch (\PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
}

// ฟังก์ชันช่วยแสดงป้ายสีตามประเภทงานให้ดูเฟี้ยวๆ
function getJobBadge($type) {
    switch ($type) {
        case 'รับเสด็จฯ':
            return '<span class="badge bg-warning text-dark rounded-pill shadow-sm px-3 py-2"><i class="bi bi-crown-fill me-1"></i> รับเสด็จฯ</span>';
        case 'ขอความอนุเคราะห์':
            return '<span class="badge bg-info text-dark rounded-pill shadow-sm px-3 py-2"><i class="bi bi-heart-fill text-danger me-1"></i> ขอความอนุเคราะห์</span>';
        default:
            return '<span class="badge bg-secondary rounded-pill shadow-sm px-3 py-2"><i class="bi bi-file-earmark-text me-1"></i> ' . htmlspecialchars($type) . '</span>';
    }
}

// ฟังก์ชันแปลงวันที่ให้ดูง่ายขึ้น (ด/ม/ป)
function formatDate($date) {
    if (empty($date)) return '<span class="text-muted">-</span>';
    return date('d/m/Y', strtotime($date));
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการข้อมูลงาน | Dashboard</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

    <style>
        /* จัด Footer ให้อยู่ล่างสุดเสมอ */
        body {
            font-family: 'Prompt', sans-serif;
            background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1;
            padding-bottom: 3rem;
        }

        /* ตกแต่ง Header แบบ Gradient สุดจ๊าบ */
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 3rem 0 5rem 0;
            border-radius: 0 0 2rem 2rem;
            box-shadow: 0 10px 30px rgba(118, 75, 162, 0.2);
            position: relative;
        }

        /* การ์ดตารางแบบ Glassmorphism */
        .glass-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.5);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.05);
            margin-top: -3.5rem; /* ดันการ์ดให้ทับ Header นิดนึงให้ดูมีมิติ */
            overflow: hidden;
        }

        /* ตกแต่งตาราง */
        .table-custom {
            margin-bottom: 0;
        }
        
        .table-custom thead th {
            background-color: #f8fafc;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
            padding: 1.2rem 1rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .table-custom tbody td {
            padding: 1.2rem 1rem;
            vertical-align: middle;
            color: #334155;
            border-bottom: 1px solid #f1f5f9;
            transition: all 0.3s ease;
        }

        /* Hover Effect สุดเท่ เวลารูดเมาส์ผ่าน */
        .table-custom tbody tr {
            transition: all 0.3s ease;
        }
        
        .table-custom tbody tr:hover td {
            background-color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            position: relative;
            z-index: 1;
        }

        /* ปุ่ม Action */
        .btn-action {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            border: none;
        }
        .btn-view { background-color: #e0e7ff; color: #4f46e5; }
        .btn-view:hover { background-color: #4f46e5; color: #fff; transform: scale(1.1); }
        
        .btn-add-new {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            border: none;
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .btn-add-new:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(56, 239, 125, 0.4);
            color: white;
        }
        
        /* Pagination Styles */
        .pagination .page-link { color: #4f46e5; border-radius: 0.5rem !important; margin: 0 3px; border: 1px solid #e2e8f0; font-weight: 500; transition: all 0.2s; }
        .pagination .page-item.active .page-link { background-color: #4f46e5; border-color: #4f46e5; color: white; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
        .pagination .page-link:hover:not(.active) { background-color: #e0e7ff; color: #4338ca; }
    </style>
</head>
<body>

    <?php include 'menu.php'; ?>

    <div class="page-header">
        <div class="container text-center text-md-start d-flex flex-column flex-md-row justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold mb-1"><i class="bi bi-kanban me-2"></i>กระดานข้อมูลงาน</h2>
                <p class="text-white-50 mb-0">แสดงรายการหนังสือและรายละเอียดการจัดงานทั้งหมดในระบบ</p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="admin2.php" class="btn btn-add-new rounded-pill px-4 py-2 fw-bold shadow">
                    <i class="bi bi-plus-lg me-1"></i> เพิ่มงานใหม่
                </a>
            </div>
        </div>
    </div>

    <div class="main-content container">
        <div class="card glass-card">
            <div class="card-header bg-white border-bottom-0 pt-4 pb-2 px-4 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0"><i class="bi bi-list-task text-primary me-2"></i>รายการล่าสุด</h5>
                <span class="badge bg-light text-dark border px-3 py-2 rounded-pill">
                    ทั้งหมด <?php echo $total_rows; ?> รายการ
                </span>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-custom align-middle">
                        <thead>
                            <tr>
                                <th class="ps-4">ชื่องาน / รายละเอียด</th>
                                <th>ประเภท</th>
                                <th>สถานที่จัดงาน</th>
                                <th>วันที่เริ่มงาน</th>
                                <th>วันประชุมล่วงหน้า</th>
                                <th>ผู้ประสานงาน</th>
                                <th class="text-center pe-4">จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($documents)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5">
                                        <div class="text-muted">
                                            <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                                            <h5 class="fw-bold">ยังไม่มีข้อมูลในระบบ</h5>
                                            <p class="small">คลิก "เพิ่มงานใหม่" เพื่อเริ่มต้นบันทึกข้อมูล</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($documents as $doc): ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="fw-bold text-dark" style="font-size: 1.05rem;">
                                                <?php echo htmlspecialchars($doc['doc_name']); ?>
                                            </div>
                                            <div class="text-muted small mt-1">
                                                <i class="bi bi-clock-history me-1"></i> รับเข้า: <?php echo date('d/m/Y H:i', strtotime($doc['received_date'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php echo getJobBadge($doc['job_type']); ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center text-secondary">
                                                <i class="bi bi-geo-alt-fill text-danger me-2"></i>
                                                <span class="text-truncate" style="max-width: 180px; display: inline-block;" title="<?php echo htmlspecialchars($doc['location'] ?? 'ไม่ระบุ'); ?>">
                                                    <?php echo htmlspecialchars($doc['location'] ?? '-'); ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-medium text-dark">
                                                <i class="bi bi-calendar2-check text-success me-1"></i> 
                                                <?php echo formatDate($doc['event_start_date']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-medium text-secondary">
                                                <i class="bi bi-calendar-event text-warning me-1"></i> 
                                                <?php echo formatDate($doc['pre_meeting_date']); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="small">
                                                <div class="fw-bold text-dark"><i class="bi bi-person-circle me-1"></i> <?php echo htmlspecialchars($doc['host_agency'] ?? '-'); ?></div>
                                                <div class="text-muted"><i class="bi bi-telephone me-1"></i> <?php echo htmlspecialchars($doc['coordinator'] ?? '-'); ?></div>
                                            </div>
                                        </td>
                                        <td class="text-center pe-4">
                                            <a href="#" class="btn-action btn-view shadow-sm" title="ดูรายละเอียด">
                                                <i class="bi bi-search"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
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

    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
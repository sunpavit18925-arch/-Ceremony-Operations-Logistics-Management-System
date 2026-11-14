<?php
/**
 * Manage Coordinator Operation (Head of Operation / Super Admin)
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/db.php';

require_role([ROLE_HEAD_OPERATION, ROLE_SUPER_ADMIN]);

$conn = get_db_connection();
$admin_name = $_SESSION['username'] ?? 'Admin';

// 1. จัดการเพิ่มสิทธิ์ผู้ประสานงาน (POST with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_coordinator'])) {
    require_csrf();

    $staff_id = clean_int($_POST['staff_id'] ?? 0);
    if ($staff_id > 0) {
        try {
            $conn->beginTransaction();

            $stmt_name = $conn->prepare("SELECT fname, lname FROM users WHERE id = ?");
            $stmt_name->execute([$staff_id]);
            $user_info = $stmt_name->fetch();
            $full_name = $user_info ? ($user_info['fname'] . ' ' . $user_info['lname']) : 'ไม่ทราบชื่อ';

            $stmt = $conn->prepare("UPDATE users SET role = 'coordinator_operation' WHERE id = ?");
            $stmt->execute([$staff_id]);

            $log_stmt = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (NULL, 'ASSIGN_COORD', ?, ?)");
            $log_stmt->execute(["มอบหมายสิทธิ์ผู้ประสานงานให้: " . $full_name, $admin_name]);

            $conn->commit();
            redirect("manage_coordinator_operation.php?success=1");
        } catch (\Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log("Error assigning coordinator: " . $e->getMessage());
            redirect("manage_coordinator_operation.php?error=1");
        }
    }
}

// 2. จัดการปลดสิทธิ์ผู้ประสานงาน (POST with CSRF)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'revoke_coordinator') {
    require_csrf();

    $revoke_id = clean_int($_POST['user_id'] ?? 0);
    if ($revoke_id > 0) {
        try {
            $conn->beginTransaction();

            $stmt_name = $conn->prepare("SELECT fname, lname FROM users WHERE id = ?");
            $stmt_name->execute([$revoke_id]);
            $user_info = $stmt_name->fetch();
            $full_name = $user_info ? ($user_info['fname'] . ' ' . $user_info['lname']) : 'ไม่ทราบชื่อ';

            $stmt = $conn->prepare("UPDATE users SET role = 'staff_operation' WHERE id = ?");
            $stmt->execute([$revoke_id]);

            $log_stmt = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (NULL, 'REVOKE_COORD', ?, ?)");
            $log_stmt->execute(["ปลดสิทธิ์ผู้ประสานงานของ: " . $full_name, $admin_name]);

            $conn->commit();
            redirect("manage_coordinator_operation.php?revoke_success=1");
        } catch (\Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log("Error revoking coordinator: " . $e->getMessage());
            redirect("manage_coordinator_operation.php?error=1");
        }
    }
}

// 3. ดึงข้อมูลแสดงผล
try {
    // ดึงรายชื่อพนักงานฝ่ายปฏิบัติการ
    $stmt_staff = $conn->prepare("
        SELECT u.id, u.fname, u.lname 
        FROM users u
        WHERE u.role = 'staff_operation'
          AND CONCAT(u.fname, ' ', u.lname) NOT IN (
              SELECT staff_name FROM job_assignments WHERE staff_name IS NOT NULL
          )
        ORDER BY u.fname ASC
    ");
    $stmt_staff->execute();
    $staff_list = $stmt_staff->fetchAll();

    // ดึงรายชื่อผู้ประสานงานปัจจุบัน
    $stmt_coord = $conn->prepare("
        SELECT id, fname, lname, depart_name 
        FROM users 
        WHERE role = 'coordinator_operation' 
        ORDER BY fname ASC
    ");
    $stmt_coord->execute();
    $coordinators = $stmt_coord->fetchAll();
    $total_coords = count($coordinators);

    // ดึงประวัติการมอบหมาย
    $stmt_history = $conn->prepare("
        SELECT * FROM system_logs 
        WHERE action_type IN ('ASSIGN_COORD', 'REVOKE_COORD') 
        ORDER BY created_at DESC 
        LIMIT 50
    ");
    $stmt_history->execute();
    $assignment_history = $stmt_history->fetchAll();
} catch (\PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูล: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>กำหนดสิทธิ์ผู้ประสานงาน | Head of Operations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        html, body { height: 100%; }
        body { 
            display: flex; 
            flex-direction: column; 
            font-family: 'Prompt', sans-serif; 
            background-color: #f4f6f9; 
        }
        .content-wrapper { flex: 1 0 auto; }
        footer { flex-shrink: 0; }

        /* Header สุดจึ้ง */
        .page-header {
            background: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            color: white; 
            padding: 3rem 0 4rem 0; 
            margin-bottom: -3.5rem;
            border-radius: 0 0 2rem 2rem; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.15);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: -50%; left: -10%;
            width: 50%; height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 60%);
            transform: rotate(30deg);
        }

        /* Card Styles */
        .custom-card { 
            border: none; 
            border-radius: 1.5rem; 
            box-shadow: 0 10px 40px rgba(0,0,0,0.06); 
            background: #ffffff; 
            margin-bottom: 2rem; 
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        .custom-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.1);
        }
        
        /* Form Controls */
        .form-select-custom { 
            border-radius: 1rem; 
            border: 2px solid #e9ecef; 
            padding: 1rem 1.25rem; 
            font-size: 1.05rem;
            transition: all 0.3s;
            cursor: pointer;
            background-color: #f8f9fa;
        }
        .form-select-custom:focus { 
            border-color: #2c5364; 
            background-color: #ffffff;
            box-shadow: 0 0 0 0.25rem rgba(44, 83, 100, 0.15); 
        }
        
        /* Buttons */
        .btn-gradient {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            border: none;
            color: white;
            padding: 0.8rem 2rem;
            border-radius: 1rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s;
            box-shadow: 0 8px 20px rgba(56, 239, 125, 0.3);
        }
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 25px rgba(56, 239, 125, 0.4);
            color: white;
        }

        .btn-revoke {
            background-color: #fff0f0;
            color: #dc3545;
            border: 1px solid #ffdbdb;
            border-radius: 0.75rem;
            padding: 0.4rem 1rem;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .btn-revoke:hover {
            background-color: #dc3545;
            color: #ffffff;
        }

        /* Avatar / List items */
        .coordinator-item {
            background: #ffffff;
            border: 1px solid #edf2f7;
            border-radius: 1.25rem;
            padding: 1.25rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: all 0.2s;
        }
        .coordinator-item:hover {
            border-color: #cbd5e1;
            background: #f8fafc;
        }
        .avatar-circle {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #2c5364, #203a43);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: bold;
            box-shadow: 0 4px 10px rgba(44, 83, 100, 0.2);
        }

        .icon-wrapper {
            width: 80px; height: 80px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem auto;
        }
        
        /* Pagination Styles */
        .page-link {
            color: #2c5364;
            border-radius: 0.5rem !important;
            margin: 0 2px;
            border: 1px solid #e9ecef;
        }
        .page-item.active .page-link {
            background-color: #2c5364;
            border-color: #2c5364;
        }
    </style>
</head>
<body>

<?php include 'menu.php'; ?>

<div class="content-wrapper">
    <!-- Header Section -->
    <div class="page-header text-center">
        <div class="container position-relative z-1">
            <div class="row align-items-center">
                <div class="col-12 col-md-2 text-start mb-3 mb-md-0">
                    <a href="home_head_operation.php" class="btn btn-outline-light rounded-pill d-inline-flex align-items-center" style="font-weight: 500; padding: 0.4rem 1.2rem; transition: all 0.2s; border-color: rgba(255,255,255,0.4);">
                        <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
                    </a>
                </div>
                <div class="col-12 col-md-8 text-center">
                    <h2 class="fw-bold mb-2"><i class="bi bi-person-badge-fill me-2 text-warning"></i>กำหนดสิทธิ์ผู้ประสานงาน</h2>
                    <p class="text-white-50 fs-6 mb-0">กำหนดสิทธิ์ให้บุคลากรในฝ่ายเพื่อทำหน้าที่ประสานงาน</p>
                </div>
                <div class="col-12 col-md-2"></div>
            </div>
        </div>
    </div>
    
    <div class="container" style="position: relative; z-index: 2;">
        
        <!-- Alerts -->
        <?php if(isset($_GET['success'])): ?>
        <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close mx-auto" style="max-width: 800px;">
            <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
            <div><h6 class="mb-0 fw-bold">มอบหมายสิทธิ์ผู้ประสานงานสำเร็จ!</h6></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <?php if(isset($_GET['revoke_success'])): ?>
        <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close mx-auto" style="max-width: 800px;">
            <i class="bi bi-person-dash-fill fs-4 me-3 text-danger"></i>
            <div><h6 class="mb-0 fw-bold">ปลดสิทธิ์ผู้ประสานงานเรียบร้อยแล้ว</h6></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>

        <div class="row justify-content-center g-4">
            
            <!-- ฝั่งซ้าย: ฟอร์มกำหนดสิทธิ์ -->
            <div class="col-lg-6">
                <div class="card custom-card h-100 border-top border-4 border-success">
                    <div class="card-body p-4 p-md-5">
                        <div class="text-center mb-4">
                            <div class="icon-wrapper bg-success bg-opacity-10 text-success">
                                <i class="bi bi-person-plus-fill fs-1"></i>
                            </div>
                            <h4 class="fw-bold text-dark">มอบหมายผู้ประสานงาน</h4>
                            <p class="text-muted small">เลือกรายชื่อบุคลากรจากฝ่ายปฏิบัติการ (เฉพาะผู้ที่ยังไม่มีสิทธิ์)</p>
                        </div>

                        <form method="POST" action="">
                            <div class="mb-4 position-relative">
                                <label class="form-label fw-bold text-secondary ms-2 mb-2"><i class="bi bi-list-ul me-2"></i>รายชื่อบุคลากรฝ่ายปฏิบัติการ</label>
                                <select name="staff_id" class="form-select form-select-custom" required>
                                    <option value="" selected disabled>-- คลิกเพื่อเลือกบุคลากร --</option>
                                    <?php if(empty($staff_list)): ?>
                                        <option value="" disabled>ไม่มีบุคลากรที่สามารถเลือกได้</option>
                                    <?php else: ?>
                                        <?php foreach ($staff_list as $staff): ?>
                                            <option value="<?php echo htmlspecialchars($staff['id']); ?>">
                                                <?php echo htmlspecialchars($staff['fname'] . ' ' . $staff['lname']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </select>
                            </div>

                            <div class="d-grid mt-5">
                                <button type="submit" name="assign_coordinator" class="btn btn-gradient btn-lg" <?php echo empty($staff_list) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-check2-circle me-2"></i> ยืนยันการมอบหมายสิทธิ์
                                </button>
                            </div>
                            
                            <!-- ปุ่มดูประวัติการมอบหมาย -->
                            <div class="text-center mt-4">
                                <button type="button" class="btn btn-link text-decoration-none text-muted fw-medium" data-bs-toggle="modal" data-bs-target="#historyModal" id="btnShowHistory">
                                    <i class="bi bi-clock-history me-1"></i> ดูประวัติการมอบหมายผู้ประสานงานทั้งหมด
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ฝั่งขวา: รายชื่อผู้ประสานงานปัจจุบัน -->
            <div class="col-lg-6">
                <div class="card custom-card h-100 border-top border-4" style="border-color: #2c5364 !important;">
                    <div class="card-body p-4 p-md-5 bg-light rounded-bottom-4">
                        <div class="d-flex align-items-center justify-content-between mb-4">
                            <h5 class="fw-bold mb-0" style="color: #2c5364;">
                                <i class="bi bi-star-fill text-warning me-2"></i>ผู้ประสานงานปัจจุบัน
                            </h5>
                            <span class="badge rounded-pill bg-dark bg-opacity-10 text-dark px-3 py-2">
                                <?php echo $total_coords; ?> ท่าน
                            </span>
                        </div>

                        <!-- ช่องค้นหาผู้ประสานงาน -->
                        <div class="mb-4 position-relative">
                            <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" id="searchCoordinator" class="form-control bg-white shadow-sm ps-5 py-2" style="border-radius: 1rem; border: 1px solid #edf2f7;" placeholder="ค้นหาชื่อผู้ประสานงาน...">
                        </div>

                        <div class="coordinator-list-container" id="coordinatorList" style="min-height: 300px; padding-right: 5px;">
                            <?php if(empty($coordinators)): ?>
                                <div class="text-center py-5">
                                    <img src="https://cdn-icons-png.flaticon.com/512/7486/7486747.png" alt="No Data" width="100" class="mb-3 opacity-50">
                                    <h6 class="text-muted fw-bold">ยังไม่มีผู้ประสานงาน</h6>
                                    <p class="text-muted small">โปรดเลือกบุคลากรจากเมนูด้านซ้ายเพื่อกำหนดสิทธิ์</p>
                                </div>
                            <?php else: ?>
                                <?php foreach($coordinators as $coord): 
                                    $initials = mb_substr($coord['fname'], 0, 1, 'UTF-8') . mb_substr($coord['lname'], 0, 1, 'UTF-8');
                                ?>
                                    <div class="coordinator-item shadow-sm">
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-circle">
                                                <?php echo htmlspecialchars($initials); ?>
                                            </div>
                                            <div>
                                                <h6 class="fw-bold mb-1 text-dark coordinator-name"><?php echo htmlspecialchars($coord['fname'] . ' ' . $coord['lname']); ?></h6>
                                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill px-2 py-1" style="font-size: 0.7rem;">
                                                    <i class="bi bi-shield-check me-1"></i>สิทธิ์: ผู้ประสานงาน
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <!-- ปุ่มปลดสิทธิ์ -->
                                     <form method="POST" action="manage_coordinator_operation.php" class="d-inline m-0 p-0" onsubmit="return confirm('ยืนยันการปลดสิทธิ์คุณ <?php echo htmlspecialchars($coord['fname']); ?> ออกจากการเป็นผู้ประสานงานใช่หรือไม่?');">
                                            <?php echo csrf_field(); ?>
                                            <input type="hidden" name="action" value="revoke_coordinator">
                                            <input type="hidden" name="user_id" value="<?php echo (int)$coord['id']; ?>">
                                            <button type="submit" class="btn btn-revoke border-0" title="ปลดสิทธิ์">
                                                <i class="bi bi-x-circle me-1"></i>ปลดสิทธิ์
                                            </button>
                                        </form> 
                                    </div>
                                <?php endforeach; ?>
                                <div id="noCoordinatorFound" class="text-center py-5" style="display: none;">
                                    <i class="bi bi-search text-muted fs-1 opacity-50 mb-3 d-block"></i>
                                    <h6 class="text-muted fw-bold">ไม่พบรายชื่อผู้ประสานงานที่ค้นหา</h6>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                     <?php if (isset($total_pages_coord) && $total_pages_coord > 1): ?>
    <?php 
        // ตรวจสอบและกำหนดค่าเริ่มต้นให้ $page_coord เพื่อป้องกัน Warning
        $current_page_coord = isset($page_coord) ? $page_coord : 1; 
        
        // เตรียม Query String ของ log_page ไว้ล่วงหน้าเพื่อให้โค้ดสะอาดและปลอดภัยขึ้น
        $log_param = '';
        if (isset($_GET['log_page'])) {
            $log_param = '&log_page=' . urlencode($_GET['log_page']) . '&show_history=1';
        }
    ?>
    <div class="d-flex justify-content-center mt-4">
        <nav>
            <ul class="pagination pagination-sm mb-0 shadow-sm rounded-3 flex-wrap justify-content-center gap-1">
                <li class="page-item <?php echo $current_page_coord <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?coord_page=<?php echo $current_page_coord - 1; ?><?php echo $log_param; ?>">
                        <i class="bi bi-chevron-left"></i> ก่อนหน้า
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $total_pages_coord; $i++): ?>
                <li class="page-item <?php echo $current_page_coord == $i ? 'active' : ''; ?>">
                    <a class="page-link" href="?coord_page=<?php echo $i; ?><?php echo $log_param; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo $current_page_coord >= $total_pages_coord ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?coord_page=<?php echo $current_page_coord + 1; ?><?php echo $log_param; ?>">
                        ถัดไป <i class="bi bi-chevron-right"></i>
                    </a>
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

<!-- Modal ประวัติการมอบหมายสิทธิ์ -->
<div class="modal fade" id="historyModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header border-bottom bg-light px-4 py-3">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-clock-history text-primary me-2"></i>ประวัติการจัดการสิทธิ์ผู้ประสานงาน</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-0 bg-light">
                <?php if(empty($coord_logs)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                        <span>ยังไม่มีประวัติการทำรายการ</span>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach($coord_logs as $log): ?>
                            <div class="list-group-item p-3 border-bottom bg-white">
                                <div class="d-flex justify-content-between align-items-start mb-1">
                                    <div>
                                        <?php if($log['action_type'] === 'ASSIGN_COORD'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill mb-2"><i class="bi bi-person-plus-fill me-1"></i> มอบหมายสิทธิ์</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle rounded-pill mb-2"><i class="bi bi-person-dash-fill me-1"></i> ปลดสิทธิ์</span>
                                        <?php endif; ?>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($log['action_detail']); ?></div>
                                    </div>
                                    <div class="text-end">
                                        <div class="text-muted small"><i class="bi bi-calendar3 me-1"></i> <?php echo date('d/m/Y', strtotime($log['created_at'])); ?></div>
                                        <div class="text-muted small"><i class="bi bi-clock me-1"></i> <?php echo date('H:i', strtotime($log['created_at'])); ?> น.</div>
                                    </div>
                                </div>
                                <div class="small text-secondary mt-2">
                                    <i class="bi bi-person-circle me-1"></i> ทำรายการโดย: <?php echo htmlspecialchars($log['performed_by']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
               <?php if (isset($total_pages_log) && $total_pages_log > 1): ?>
    <?php 
        // ตรวจสอบและกำหนดค่าเริ่มต้นให้ $page_log เพื่อป้องกัน Warning
        $current_page_log = isset($page_log) ? $page_log : 1; 
        
        // เตรียม Query String ของ coord_page ไว้ล่วงหน้าให้ปลอดภัย
        $coord_param = '';
        if (isset($_GET['coord_page'])) {
            $coord_param = '&coord_page=' . urlencode($_GET['coord_page']);
        }
    ?>
    <div class="d-flex justify-content-center p-3 border-top bg-white">
        <nav>
            <ul class="pagination pagination-sm mb-0 flex-wrap justify-content-center gap-1">
                <li class="page-item <?php echo $current_page_log <= 1 ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?log_page=<?php echo $current_page_log - 1; ?>&show_history=1<?php echo $coord_param; ?>">
                        <i class="bi bi-chevron-left"></i>
                    </a>
                </li>
                
                <?php for ($i = 1; $i <= $total_pages_log; $i++): ?>
                <li class="page-item <?php echo $current_page_log == $i ? 'active' : ''; ?>">
                    <a class="page-link" href="?log_page=<?php echo $i; ?>&show_history=1<?php echo $coord_param; ?>">
                        <?php echo $i; ?>
                    </a>
                </li>
                <?php endfor; ?>
                
                <li class="page-item <?php echo $current_page_log >= $total_pages_log ? 'disabled' : ''; ?>">
                    <a class="page-link" href="?log_page=<?php echo $current_page_log + 1; ?>&show_history=1<?php echo $coord_param; ?>">
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </li>
            </ul>
        </nav>
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

<script>
document.addEventListener("DOMContentLoaded", function() {
    // ซ่อน Alert อัตโนมัติใน 3 วินาที
    const alerts = document.querySelectorAll('.auto-close');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 3000);
    });

    // ล้าง URL Parameters หลังจากแสดง Alert
    if (window.history.replaceState) {
        const url = new URL(window.location.href);
        ['success', 'revoke_success'].forEach(param => url.searchParams.delete(param));
        window.history.replaceState({}, '', url.href);
    }
    
    // เปิด Modal ประวัติอัตโนมัติ หากเปลี่ยนหน้า Pagination ในส่วนของประวัติ
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('show_history')) {
        const historyModal = new bootstrap.Modal(document.getElementById('historyModal'));
        historyModal.show();
    }

    // ค้นหาผู้ประสานงาน (Real-time Filter)
    const searchInput = document.getElementById('searchCoordinator');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const filter = this.value.toLowerCase();
            const items = document.querySelectorAll('#coordinatorList .coordinator-item');
            let hasVisibleItem = false;

            items.forEach(function(item) {
                const name = item.querySelector('.coordinator-name').textContent.toLowerCase();
                if (name.includes(filter)) {
                    item.style.setProperty('display', 'flex', 'important');
                    hasVisibleItem = true;
                } else {
                    item.style.setProperty('display', 'none', 'important');
                }
            });

            let noDataEl = document.getElementById('noCoordinatorFound');
            if (!hasVisibleItem && items.length > 0) {
                if (noDataEl) noDataEl.style.display = 'block';
            } else {
                if (noDataEl) noDataEl.style.display = 'none';
            }
        });
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
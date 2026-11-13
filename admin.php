<?php
/**
 * User Role Management (Admin / Super Admin)
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/security.php';

require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

$conn = get_db_connection();
$callerRole = current_user_role();
$adminName = $_SESSION['username'] ?? 'Admin';

// 1. Process Role Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_id'], $_POST['new_role'])) {
    require_csrf();

    $user_id = clean_int($_POST['user_id']);
    $new_role = clean_string($_POST['new_role']);

    $stmt_old = $conn->prepare("SELECT id, fname, lname, role FROM users WHERE id = ?");
    $stmt_old->execute([$user_id]);
    $old_user = $stmt_old->fetch();

    if (!$old_user) {
        redirect('admin.php?error=' . urlencode('ไม่พบข้อมูลผู้ใช้งาน'));
    }

    [$canAssign, $denyReason] = can_assign_role($callerRole, $new_role, $old_user['role'], $user_id);

    if (!$canAssign) {
        redirect('admin.php?error=' . urlencode($denyReason));
    }

    if ($old_user['role'] !== $new_role) {
        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->execute([$new_role, $user_id]);

            $detail = "[ID:{$user_id}] {$old_user['fname']} {$old_user['lname']} | " . getRoleThai($old_user['role']) . " -> " . getRoleThai($new_role);
            $log_stmt = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (NULL, 'CHANGE_ROLE', ?, ?)");
            $log_stmt->execute([$detail, $adminName]);

            $conn->commit();
            redirect('admin.php?success=1');
        } catch (\Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Error updating role: ' . $e->getMessage());
            redirect('admin.php?error=' . urlencode('เกิดข้อผิดพลาดในการบันทึกข้อมูล'));
        }
    } else {
        redirect('admin.php');
    }
}

// 2. Fetch all users
$users = $conn->query("SELECT * FROM users ORDER BY id DESC")->fetchAll();

// 3. Fetch change role logs
$role_logs = $conn->query("SELECT * FROM system_logs WHERE action_type = 'CHANGE_ROLE' ORDER BY created_at DESC")->fetchAll();

// 4. Group users
$group_op = [];
$group_veh = [];
$group_admin = [];
$group_pending = [];

$has_head_operation = false;
$has_head_vehicle = false;

foreach ($users as $u) {
    $r = $u['role'];
    if ($r === ROLE_ADMIN || $r === ROLE_SUPER_ADMIN) {
        $group_admin[] = $u;
    } elseif (in_array($r, [ROLE_HEAD_OPERATION, ROLE_STAFF_OPERATION, ROLE_COORDINATOR_OPERATION], true)) {
        $group_op[] = $u;
        if ($r === ROLE_HEAD_OPERATION) $has_head_operation = true;
    } elseif (in_array($r, [ROLE_HEAD_VEHICLE, ROLE_STAFF_VEHICLE], true)) {
        $group_veh[] = $u;
        if ($r === ROLE_HEAD_VEHICLE) $has_head_vehicle = true;
    } else {
        $group_pending[] = $u;
    }
}

// Order within operations: Head -> Staff -> Coordinator
$role_order_op = [ROLE_HEAD_OPERATION => 1, ROLE_STAFF_OPERATION => 2, ROLE_COORDINATOR_OPERATION => 3];
usort($group_op, function($a, $b) use ($role_order_op) {
    $orderA = $role_order_op[$a['role']] ?? 99;
    $orderB = $role_order_op[$b['role']] ?? 99;
    return ($orderA === $orderB) ? ($b['id'] <=> $a['id']) : ($orderA <=> $orderB);
});

// Order within vehicles: Head -> Staff
$role_order_veh = [ROLE_HEAD_VEHICLE => 1, ROLE_STAFF_VEHICLE => 2];
usort($group_veh, function($a, $b) use ($role_order_veh) {
    $orderA = $role_order_veh[$a['role']] ?? 99;
    $orderB = $role_order_veh[$b['role']] ?? 99;
    return ($orderA === $orderB) ? ($b['id'] <=> $a['id']) : ($orderA <=> $orderB);
});

function getRoleBadge(string $role): string {
    return match ($role) {
        'super_admin'           => 'bg-danger text-white',
        'staff_operation'       => 'bg-primary text-white',
        'coordinator_operation' => 'bg-info text-dark',
        'staff_vehicle'         => 'bg-success text-white',
        'head_operation'        => 'bg-warning text-dark',
        'head_vehicle'          => 'bg-danger text-white',
        'admin'                 => 'bg-dark text-white',
        'pending'               => 'bg-secondary text-white',
        default                 => 'bg-info text-dark',
    };
}

function getRoleThai(string $role): string {
    $roles = [
        'super_admin'           => 'ผู้ดูแลระบบสูงสุด (Super Admin)',
        'staff_operation'       => 'ฝ่ายปฏิบัติงาน',
        'coordinator_operation' => 'ผู้ประสานงาน',
        'staff_vehicle'         => 'ฝ่ายยานยนต์',
        'head_operation'        => 'หัวหน้าฝ่ายปฏิบัติงาน',
        'head_vehicle'          => 'หัวหน้าฝ่ายยานยนต์',
        'admin'                 => 'ผู้ดูแลระบบ (Admin)',
        'pending'               => 'รออนุมัติ',
    ];
    return $roles[$role] ?? $role;
}

function renderUserTable(array $users_list, array $role_logs, int $page, string $param_name, bool $has_head_op = false, bool $has_head_veh = false) {
    $limit = 10;
    $total_items = count($users_list);
    $total_pages = ceil($total_items / $limit);
    if ($page < 1) $page = 1;
    if ($page > $total_pages && $total_pages > 0) $page = $total_pages;

    $offset = ($page - 1) * $limit;
    $current_items = array_slice($users_list, $offset, $limit);

    if (empty($current_items)) {
        echo '<div class="text-center py-5 text-muted"><i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>ยังไม่มีข้อมูลผู้ใช้งานในกลุ่มนี้</div>';
        return;
    }

    $callerRole = current_user_role();
    $csrfTokenField = csrf_field();

    echo '<div class="table-responsive"><table class="table table-hover mb-0"><thead><tr>';
    echo '<th class="ps-4">ข้อมูลผู้ใช้งาน</th>';
    echo '<th class="text-center">สถานะปัจจุบัน</th>';
    echo '<th class="text-center" style="width: 250px;">จัดการสิทธิ์ (Role)</th>';
    echo '<th class="text-center pe-4" style="width: 150px;">ประวัติการแก้ไข</th>';
    echo '</tr></thead><tbody>';

    foreach ($current_items as $row) {
        $rowName = htmlspecialchars($row['fname'] . ' ' . $row['lname'], ENT_QUOTES, 'UTF-8');
        $rowSso = htmlspecialchars($row['username_sso'] ?? '', ENT_QUOTES, 'UTF-8');

        echo '<tr class="user-row"><td class="ps-4">';
        echo '<div class="fw-semibold text-dark">' . $rowName . '</div>';
        echo '<div class="text-muted" style="font-size: 0.85rem;">SSO: ' . $rowSso . '</div>';

        if ($row['role'] === ROLE_COORDINATOR_OPERATION) {
            echo '<div class="mt-1"><span class="badge bg-info text-dark bg-opacity-25 border border-info rounded-pill" style="font-size: 0.75rem;"><i class="bi bi-person-lines-fill me-1"></i> คนในฝ่ายปฏิบัติงาน (เป็นผู้ประสานงาน)</span></div>';
        }

        echo '</td><td class="text-center">';
        echo '<span class="badge ' . getRoleBadge($row['role']) . ' custom-badge">' . e(getRoleThai($row['role'])) . '</span>';
        echo '</td><td class="text-center">';

        // Check if caller can edit this target user
        $canEditTarget = true;
        if ($callerRole !== ROLE_SUPER_ADMIN && $row['role'] === ROLE_SUPER_ADMIN) {
            $canEditTarget = false;
        }

        if (!$canEditTarget) {
            echo '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="bi bi-shield-lock-fill me-1"></i> สิทธิ์สูงสุด (แก้ไขไม่ได้)</span>';
        } else {
            echo '<form method="post" class="m-0" id="roleForm_' . (int)$row['id'] . '">';
            echo $csrfTokenField;
            echo '<input type="hidden" name="user_id" value="' . (int)$row['id'] . '">';
            echo '<select name="new_role" class="form-select role-select" data-original="' . e($row['role']) . '" data-name="' . $rowName . '" onchange="confirmRoleChange(this)">';

            $options = [
                ROLE_HEAD_OPERATION        => 'หัวหน้าฝ่ายปฏิบัติงาน',
                ROLE_STAFF_OPERATION       => 'ฝ่ายปฏิบัติงาน',
                ROLE_HEAD_VEHICLE          => 'หัวหน้าฝ่ายยานยนต์',
                ROLE_STAFF_VEHICLE         => 'ฝ่ายยานยนต์',
                ROLE_ADMIN                 => 'ผู้ดูแลระบบ (Admin)',
                ROLE_PENDING               => 'รออนุมัติ'
            ];

            if ($callerRole === ROLE_SUPER_ADMIN) {
                $options[ROLE_SUPER_ADMIN] = 'ผู้ดูแลระบบสูงสุด (Super Admin)';
            }

            if ($has_head_op && $row['role'] !== ROLE_HEAD_OPERATION) {
                unset($options[ROLE_HEAD_OPERATION]);
            }
            if ($has_head_veh && $row['role'] !== ROLE_HEAD_VEHICLE) {
                unset($options[ROLE_HEAD_VEHICLE]);
            }

            if ($row['role'] === ROLE_COORDINATOR_OPERATION) {
                $options[ROLE_COORDINATOR_OPERATION] = 'ผู้ประสานงาน';
            }

            foreach ($options as $val => $text) {
                $selected = ($row['role'] === $val) ? 'selected' : '';
                echo '<option value="' . e($val) . '" ' . $selected . '>' . e($text) . '</option>';
            }

            echo '</select></form>';
        }

        echo '</td>';
        echo '<td class="text-center pe-4">';
        echo '<button class="btn btn-sm btn-outline-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#logModal' . (int)$row['id'] . '"><i class="bi bi-clock-history"></i> ประวัติ</button>';
        echo '</td></tr>';
    }

    echo '<tr class="no-user-found" style="display: none;"><td colspan="4" class="text-center py-4 text-muted"><i class="bi bi-search fs-2 d-block mb-2 opacity-50"></i>ไม่พบข้อมูลผู้ใช้งานที่ค้นหา</td></tr>';
    echo '</tbody></table></div>';

    if ($total_pages > 1) {
        $qs_params = $_GET;
        unset($qs_params['success'], $qs_params['error']);

        echo '<div class="d-flex justify-content-center p-4 border-top bg-white"><nav><ul class="pagination pagination-sm mb-0 shadow-sm rounded-3 flex-wrap justify-content-center gap-1">';

        $qs_params[$param_name] = $page - 1;
        $prev_link = '?' . http_build_query($qs_params);
        $disabled_prev = $page <= 1 ? 'disabled' : '';
        echo "<li class=\"page-item $disabled_prev\"><a class=\"page-link px-3 py-2\" href=\"$prev_link\"><i class=\"bi bi-chevron-left\"></i> ก่อนหน้า</a></li>";

        for ($i = 1; $i <= $total_pages; $i++) {
            $qs_params[$param_name] = $i;
            $link = '?' . http_build_query($qs_params);
            $active = $page == $i ? 'active' : '';
            echo "<li class=\"page-item $active\"><a class=\"page-link px-3 py-2\" href=\"$link\">$i</a></li>";
        }

        $qs_params[$param_name] = $page + 1;
        $next_link = '?' . http_build_query($qs_params);
        $disabled_next = $page >= $total_pages ? 'disabled' : '';
        echo "<li class=\"page-item $disabled_next\"><a class=\"page-link px-3 py-2\" href=\"$next_link\">ถัดไป <i class=\"bi bi-chevron-right\"></i></a></li>";

        echo '</ul></nav></div>';
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - จัดการสิทธิ์พลับพลา</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        body, input, button, select, textarea, .badge {
            font-family: 'Prompt', sans-serif !important;
        }
        body {
            background-color: #f4f6f9;
        }
        .top-banner {
            background-color: #1e3a8a;
            padding: 2rem 0 5rem 0;
            position: relative;
        }
        .page-title-center {
            color: white;
            font-weight: 600;
            font-size: 1.5rem;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        .btn-back {
            background-color: white;
            color: #1e3a8a;
            border: none;
            border-radius: 6px;
            font-weight: 500;
            font-size: 0.9rem;
            padding: 0.4rem 1rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .btn-back:hover {
            background-color: #f8f9fa;
            color: #152960;
        }
        .main-content {
            margin-top: -3rem;
            margin-bottom: 3rem;
            position: relative;
            z-index: 10;
        }
        .admin-card {
            background: white;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            max-width: 1050px;
            margin: 0 auto;
        }
        .card-header-custom {
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #f0f2f5;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .header-title {
            color: #1e3a8a;
            font-weight: 600;
            font-size: 1.1rem;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .table th {
            background-color: #f8f9fa;
            color: #495057;
            font-weight: 500;
            border-bottom: none;
            padding: 1rem;
        }
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #f0f2f5;
        }
        .custom-badge {
            border-radius: 50rem;
            padding: 0.4em 1em;
            font-weight: 400;
            font-size: 0.85rem;
        }
        .role-select {
            border-radius: 6px;
            border: 1px solid #dee2e6;
            font-size: 0.88rem;
            padding: 0.35rem 1.75rem 0.35rem 0.75rem;
            color: #495057;
            text-align: center !important;
            text-align-last: center;
            min-width: 220px;
        }
        .accordion-button:not(.collapsed) {
            background-color: #f8f9fa;
            color: #1e3a8a;
            font-weight: 600;
        }
        .accordion-item {
            border: 1px solid #f0f2f5;
            border-radius: 8px !important;
            margin-bottom: 10px;
            overflow: hidden;
        }
    </style>
</head>
<body>

    <?php require 'menu.php'; ?>

    <div class="top-banner">
        <div class="container" style="max-width: 1050px;">
            <div class="row align-items-center">
                <div class="col-12 col-md-3 text-start">
                    <a href="home_admin.php" class="btn btn-back">
                        <i class="bi bi-arrow-left"></i> ย้อนกลับ
                    </a>
                </div>
                <div class="col-12 col-md-6 text-center">
                    <h1 class="page-title-center" style="font-size:26px;">
                        <i class="bi bi-person-lines-fill"></i> จัดการและกำหนดสิทธิ์ผู้ใช้งาน
                    </h1>
                </div>
                <div class="col-12 col-md-3"></div>
            </div>
        </div>
    </div>

    <div class="container main-content">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-3" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i> บันทึกการเปลี่ยนแปลงสิทธิ์เรียบร้อยแล้ว
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-3" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo e($_GET['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="admin-card">
            <div class="card-header-custom flex-column flex-md-row gap-3">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="header-title mb-0">
                        <i class="bi bi-list-ul"></i> รายชื่อผู้ใช้งานในระบบ
                    </h5>
                    <div class="badge bg-light text-dark border">
                        ทั้งหมด <?php echo count($users); ?> รายการ
                    </div>
                </div>
                
                <div class="position-relative w-100" style="max-width: 300px;">
                    <span class="position-absolute top-50 start-0 translate-middle-y ms-3 text-muted"><i class="bi bi-search"></i></span>
                    <input type="text" id="searchUser" class="form-control bg-light border-0 shadow-sm ps-5 py-2" style="border-radius: 20px;" placeholder="ค้นหาชื่อ, บทบาท...">
                </div>
            </div>
            
            <div class="card-body p-4">
                <div class="accordion" id="userRoleAccordion">
                    <?php
                        $p_op = clean_int($_GET['p_op'] ?? 1, 1);
                        $p_veh = clean_int($_GET['p_veh'] ?? 1, 1);
                        $p_adm = clean_int($_GET['p_adm'] ?? 1, 1);
                        $p_pen = clean_int($_GET['p_pen'] ?? 1, 1);
                        
                        $show_op = isset($_GET['p_op']) ? 'show' : '';
                        $show_veh = isset($_GET['p_veh']) ? 'show' : '';
                        $show_adm = isset($_GET['p_adm']) ? 'show' : '';
                        $show_pen = isset($_GET['p_pen']) ? 'show' : '';
                    ?>
                    
                    <!-- 1. ฝ่ายปฏิบัติงาน -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingOp">
                            <button class="accordion-button <?php echo $show_op ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseOp" aria-expanded="<?php echo $show_op ? 'true' : 'false'; ?>">
                                <i class="bi bi-tools text-primary me-2"></i> ฝ่ายปฏิบัติงาน <span class="badge bg-primary ms-2 rounded-pill"><?php echo count($group_op); ?></span>
                            </button>
                        </h2>
                        <div id="collapseOp" class="accordion-collapse collapse <?php echo $show_op; ?>" data-bs-parent="#userRoleAccordion">
                            <div class="accordion-body p-0">
                                <?php renderUserTable($group_op, $role_logs, $p_op, 'p_op', $has_head_operation, $has_head_vehicle); ?>
                            </div>
                        </div>
                    </div>

                    <!-- 2. ฝ่ายยานยนต์ -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingVeh">
                            <button class="accordion-button <?php echo $show_veh ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseVeh" aria-expanded="<?php echo $show_veh ? 'true' : 'false'; ?>">
                                <i class="bi bi-truck text-success me-2"></i> ฝ่ายยานยนต์ <span class="badge bg-success ms-2 rounded-pill"><?php echo count($group_veh); ?></span>
                            </button>
                        </h2>
                        <div id="collapseVeh" class="accordion-collapse collapse <?php echo $show_veh; ?>" data-bs-parent="#userRoleAccordion">
                            <div class="accordion-body p-0">
                                <?php renderUserTable($group_veh, $role_logs, $p_veh, 'p_veh', $has_head_operation, $has_head_vehicle); ?>
                            </div>
                        </div>
                    </div>

                    <!-- 3. ผู้ดูแลระบบ -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingAdm">
                            <button class="accordion-button <?php echo $show_adm ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAdm" aria-expanded="<?php echo $show_adm ? 'true' : 'false'; ?>">
                                <i class="bi bi-shield-lock-fill text-dark me-2"></i> ผู้ดูแลระบบ (Admin) <span class="badge bg-dark ms-2 rounded-pill"><?php echo count($group_admin); ?></span>
                            </button>
                        </h2>
                        <div id="collapseAdm" class="accordion-collapse collapse <?php echo $show_adm; ?>" data-bs-parent="#userRoleAccordion">
                            <div class="accordion-body p-0">
                                <?php renderUserTable($group_admin, $role_logs, $p_adm, 'p_adm', $has_head_operation, $has_head_vehicle); ?>
                            </div>
                        </div>
                    </div>

                    <!-- 4. รออนุมัติ -->
                    <div class="accordion-item">
                        <h2 class="accordion-header" id="headingPen">
                            <button class="accordion-button <?php echo $show_pen ? '' : 'collapsed'; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapsePen" aria-expanded="<?php echo $show_pen ? 'true' : 'false'; ?>">
                                <i class="bi bi-hourglass-split text-secondary me-2"></i> ผู้ใช้งานรออนุมัติสิทธิ์ <span class="badge bg-secondary ms-2 rounded-pill"><?php echo count($group_pending); ?></span>
                            </button>
                        </h2>
                        <div id="collapsePen" class="accordion-collapse collapse <?php echo $show_pen; ?>" data-bs-parent="#userRoleAccordion">
                            <div class="accordion-body p-0">
                                <?php renderUserTable($group_pending, $role_logs, $p_pen, 'p_pen', $has_head_operation, $has_head_vehicle); ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals for History Logs -->
    <?php foreach ($users as $u): ?>
        <div class="modal fade" id="logModal<?php echo (int)$u['id']; ?>" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow">
                    <div class="modal-header border-bottom">
                        <h6 class="modal-title fw-bold">
                            <i class="bi bi-clock-history me-1"></i> ประวัติการเปลี่ยนสิทธิ์: <?php echo e($u['fname'] . ' ' . $u['lname']); ?>
                        </h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body p-3">
                        <?php
                            $user_logs = array_filter($role_logs, function($l) use ($u) {
                                return strpos($l['action_detail'], "[ID:{$u['id']}]") !== false;
                            });
                        ?>
                        <?php if (empty($user_logs)): ?>
                            <div class="text-center py-4 text-muted small">
                                <i class="bi bi-journal-x fs-3 d-block mb-2 opacity-50"></i> ยังไม่มีประวัติการเปลี่ยนสิทธิ์
                            </div>
                        <?php else: ?>
                            <ul class="list-group list-group-flush small">
                                <?php foreach ($user_logs as $lg): ?>
                                    <li class="list-group-item px-0 py-2">
                                        <div class="fw-semibold text-dark"><?php echo e($lg['action_detail']); ?></div>
                                        <div class="text-muted d-flex justify-content-between mt-1">
                                            <span>โดย: <?php echo e($lg['performed_by']); ?></span>
                                            <span><?php echo e($lg['created_at']); ?></span>
                                        </div>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

    <?php require 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function confirmRoleChange(selectElement) {
            const userName = selectElement.getAttribute('data-name');
            const originalRole = selectElement.getAttribute('data-original');
            const newRoleText = selectElement.options[selectElement.selectedIndex].text;

            Swal.fire({
                title: 'ยืนยันการเปลี่ยนสิทธิ์?',
                html: `คุณต้องการเปลี่ยนสิทธิ์ของ <b>${userName}</b><br>เป็น <b>${newRoleText}</b> หรือไม่?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'ใช่, ยืนยันการเปลี่ยน',
                cancelButtonText: 'ยกเลิก'
            }).then((result) => {
                if (result.isConfirmed) {
                    selectElement.form.submit();
                } else {
                    selectElement.value = originalRole;
                }
            });
        }

        document.addEventListener("DOMContentLoaded", function() {
            const searchInput = document.getElementById('searchUser');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    const filter = this.value.toLowerCase();
                    const isSearching = filter.length > 0;
                    const accordions = document.querySelectorAll('.accordion-item');

                    accordions.forEach(function(accordion) {
                        const rows = accordion.querySelectorAll('tbody tr.user-row');
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

                        const noDataRow = accordion.querySelector('.no-user-found');
                        if (noDataRow) {
                            noDataRow.style.display = (!hasVisibleRow && rows.length > 0) ? '' : 'none';
                        }

                        const button = accordion.querySelector('.accordion-button');
                        const collapse = accordion.querySelector('.accordion-collapse');
                        if (button && collapse) {
                            if (isSearching && hasVisibleRow) {
                                button.classList.remove('collapsed');
                                button.setAttribute('aria-expanded', 'true');
                                collapse.classList.add('show');
                            } else {
                                button.classList.add('collapsed');
                                button.setAttribute('aria-expanded', 'false');
                                collapse.classList.remove('show');
                            }
                        }
                    });
                });
            }
        });
    </script>
</body>
</html>

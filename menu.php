<?php
/**
 * Navigation Bar Menu
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/security.php';

$user_role = current_user_role();
$current_page = basename($_SERVER['PHP_SELF']);

// Determine active zone for super_admin
$effective_role = $user_role;

if ($user_role === ROLE_SUPER_ADMIN) {
    $role_page_map = [
        'admin'                 => ['home_admin.php', 'admin.php', 'admin2.php'],
        'head_operation'        => ['home_head_operation.php', 'manage_coordinator_operation.php', 'manage_operation2.php', 'manage_team.php', 'view_system_logs.php'],
        'coordinator_operation' => ['home_operation.php', 'staff_view_operation2.php'],
        'staff_operation'       => ['staff_view_operation.php', 'view_detail.php'],
        'head_vehicle'          => ['home_head_vehicle.php', 'select_mission.php', 'manage_vehicles.php', 'manage_drivers.php', 'manage_vehicle.php'],
        'staff_vehicle'         => ['home_vehicle.php'],
    ];

    foreach ($role_page_map as $role_key => $pages) {
        if (in_array($current_page, $pages, true)) {
            $effective_role = $role_key;
            break;
        }
    }
}

$effective_role_label = match ($effective_role) {
    'admin'                 => 'ผู้ดูแลระบบ',
    'head_operation'        => 'หัวหน้าฝ่ายปฏิบัติการ',
    'coordinator_operation',
    'staff_operation'       => 'ฝ่ายปฏิบัติการ',
    'head_vehicle'          => 'หัวหน้าฝ่ายยานยนต์',
    'staff_vehicle'         => 'ฝ่ายยานยนต์',
    default                 => '',
};

$is_admin_zone = ($effective_role === 'admin' || ($user_role === ROLE_SUPER_ADMIN && $effective_role === ROLE_SUPER_ADMIN));

// Count unassigned documents for head_operation badge
$unassigned_count = 0;
if (($effective_role === ROLE_HEAD_OPERATION || $user_role === ROLE_SUPER_ADMIN) && isset($conn)) {
    try {
        $stmtBadge = $conn->query("SELECT COUNT(*) FROM documents d WHERE NOT EXISTS (SELECT 1 FROM document_assignments da WHERE da.doc_id = d.id)");
        $unassigned_count = (int)$stmtBadge->fetchColumn();
    } catch (\PDOException $e) {
        // silent
    }
}

$role_name = match ($user_role) {
    'admin'                 => 'ผู้ดูแลระบบ',
    'super_admin'           => 'ผู้ดูแลระบบสูงสุด',
    'coordinator_operation' => 'ฝ่ายปฏิบัติการ',
    'staff_operation'       => 'ฝ่ายปฏิบัติการ',
    'head_operation'        => 'หัวหน้าฝ่ายปฏิบัติการ',
    'head_vehicle'          => 'หัวหน้าฝ่ายยานยนต์',
    'staff_vehicle'         => 'ฝ่ายยานยนต์',
    'pending'               => 'รออนุมัติ',
    default                 => ''
};

$user_display_name = trim(($_SESSION['user_fname'] ?? $_SESSION['fname'] ?? '') . ' ' . ($_SESSION['lname'] ?? ''));
if (empty($user_display_name)) {
    $user_display_name = $_SESSION['username'] ?? '';
}
?>
<style>
.custom-navbar {
    background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
    padding: 0.75rem 1rem;
    position: sticky !important;
    top: 0 !important;
    width: 100% !important;
    z-index: 1030 !important;
}
.navbar-brand {
    font-size: 1.25rem;
    font-family: 'Prompt', sans-serif;
}
.nav-link {
    font-weight: 400;
    padding: 0.5rem 1rem !important;
    border-radius: 8px;
    transition: all 0.2s;
    color: rgba(255, 255, 255, 0.85) !important;
}
.nav-link:hover, .nav-link.active {
    background-color: rgba(255, 255, 255, 0.15);
    color: #fff !important;
}
.nav-profile-icon {
    width: 32px;
    height: 32px;
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* เมนู Role Admin จัดให้อยู่ตรงกลางของ Navbar */
@media (min-width: 992px) {
    .custom-navbar .container-fluid {
        position: relative;
    }
    .custom-navbar .navbar-nav-admin {
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%, -50%);
        margin: 0 !important;
        display: flex;
        flex-direction: row;
        align-items: center;
        justify-content: center;
        z-index: 1025;
        list-style: none;
        padding-left: 0;
    }
    .custom-navbar .navbar-nav-admin .nav-link {
        white-space: nowrap;
        padding-left: 1.25rem !important;
        padding-right: 1.25rem !important;
    }
}
@media (min-width: 992px) and (max-width: 1199.98px) {
    .custom-navbar .navbar-nav-admin .nav-link {
        padding-left: 0.75rem !important;
        padding-right: 0.75rem !important;
    }
}
@media (max-width: 991.98px) {
    .custom-navbar .navbar-nav-admin {
        width: 100%;
        text-align: center;
        margin: 0.5rem 0 1rem 0 !important;
        padding-left: 0;
        list-style: none;
    }
    .custom-navbar .navbar-nav-admin .nav-item {
        margin-bottom: 0.35rem;
    }
    .custom-navbar .navbar-nav-admin .nav-link {
        display: inline-block;
        width: 100%;
        padding: 0.6rem 1rem !important;
    }
}
</style>

<nav class="navbar navbar-expand-lg navbar-dark custom-navbar">
    <div class="container-fluid px-3">
        <div class="d-flex align-items-center">
            <a class="navbar-brand fw-semibold d-flex align-items-center me-2 me-xl-3" href="welcome.php">
                <i class="bi bi-bank2 me-2 text-info"></i>
                ระบบพลับพลาพิธี
                <?php if ($user_role === 'super_admin'): ?>
                    <span class="badge bg-danger ms-2" style="font-size: 0.65rem;">SUPER ADMIN</span>
                <?php endif; ?>
            </a>

            <?php if ($user_role === 'super_admin'): ?>
                <!-- Super Admin Section Switcher (Desktop Left) -->
                <div class="dropdown d-none d-lg-block">
                    <button class="btn btn-sm btn-outline-warning dropdown-toggle d-flex align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 8px; font-size: 0.82rem; padding: 0.35rem 0.65rem;">
                        <i class="bi bi-grid-fill me-1"></i> โซนระบบ
                    </button>
                    <ul class="dropdown-menu shadow">
                        <li><h6 class="dropdown-header text-primary fw-bold"><i class="bi bi-gear-fill me-1"></i> โซนผู้ดูแลระบบ</h6></li>
                        <li><a class="dropdown-item ps-4 <?php echo $current_page === 'home_admin.php' ? 'active' : ''; ?>" href="home_admin.php"><i class="bi bi-person-gear me-2"></i>ผู้ดูแลระบบ (Admin)</a></li>

                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header text-primary fw-bold"><i class="bi bi-building me-1"></i> โซนฝ่ายปฏิบัติการ</h6></li>
                        <li><a class="dropdown-item ps-4 <?php echo $current_page === 'home_head_operation.php' ? 'active' : ''; ?>" href="home_head_operation.php"><i class="bi bi-person-badge me-2"></i>หัวหน้าฝ่ายปฏิบัติการ</a></li>
                        <li><a class="dropdown-item ps-4 <?php echo $current_page === 'home_operation.php' ? 'active' : ''; ?>" href="home_operation.php"><i class="bi bi-people me-2"></i>ฝ่ายปฏิบัติการ</a></li>

                        <li><hr class="dropdown-divider"></li>
                        <li><h6 class="dropdown-header text-primary fw-bold"><i class="bi bi-truck me-1"></i> โซนฝ่ายยานยนต์</h6></li>
                        <li><a class="dropdown-item ps-4 <?php echo $current_page === 'home_head_vehicle.php' ? 'active' : ''; ?>" href="home_head_vehicle.php"><i class="bi bi-person-badge me-2"></i>หัวหน้าฝ่ายยานยนต์</a></li>
                        <li><a class="dropdown-item ps-4 <?php echo $current_page === 'home_vehicle.php' ? 'active' : ''; ?>" href="home_vehicle.php"><i class="bi bi-car-front me-2"></i>ฝ่ายยานยนต์</a></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="สลับเมนู">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="offcanvas offcanvas-start bg-dark text-white" tabindex="-1" id="navbarContent" aria-labelledby="navbarContentLabel">
            <div class="offcanvas-header border-bottom border-secondary d-lg-none">
                <h5 class="offcanvas-title" id="navbarContentLabel">
                    <i class="bi bi-person-circle me-1"></i> 
                    <?php if (!empty($user_display_name)): ?>
                        <?php echo e($user_display_name); ?>
                        <?php if (!empty($role_name)): ?>
                            <small class="d-block text-white-50 fs-6 fw-normal mt-1">(<?php echo e($role_name); ?>)</small>
                        <?php endif; ?>
                    <?php else: ?>
                        (<?php echo e($role_name); ?>)
                    <?php endif; ?>
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="ปิด"></button>
            </div>

            <div class="offcanvas-body d-flex flex-column flex-lg-row align-items-lg-center w-100">
                <?php if ($user_role === 'super_admin'): ?>
                    <!-- Super Admin Section Switcher (Mobile Drawer) -->
                    <div class="dropdown d-lg-none mb-3">
                        <button class="btn btn-sm btn-outline-warning dropdown-toggle w-100 text-start d-flex justify-content-between align-items-center" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-radius: 8px; padding: 0.5rem 0.75rem;">
                            <span><i class="bi bi-grid-fill me-1"></i> โซนระบบ</span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark w-100 shadow">
                            <li><h6 class="dropdown-header text-warning fw-bold"><i class="bi bi-gear-fill me-1"></i> โซนผู้ดูแลระบบ</h6></li>
                            <li><a class="dropdown-item ps-4 <?php echo $current_page === 'home_admin.php' ? 'active' : ''; ?>" href="home_admin.php"><i class="bi bi-person-gear me-2"></i>ผู้ดูแลระบบ (Admin)</a></li>

                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header text-warning fw-bold"><i class="bi bi-building me-1"></i> โซนฝ่ายปฏิบัติการ</h6></li>
                            <li><a class="dropdown-item ps-4 <?php echo $current_page === 'home_head_operation.php' ? 'active' : ''; ?>" href="home_head_operation.php"><i class="bi bi-person-badge me-2"></i>หัวหน้าฝ่ายปฏิบัติการ</a></li>
                            <li><a class="dropdown-item ps-4 <?php echo $current_page === 'home_operation.php' ? 'active' : ''; ?>" href="home_operation.php"><i class="bi bi-people me-2"></i>ฝ่ายปฏิบัติการ</a></li>

                            <li><hr class="dropdown-divider"></li>
                            <li><h6 class="dropdown-header text-warning fw-bold"><i class="bi bi-truck me-1"></i> โซนฝ่ายยานยนต์</h6></li>
                            <li><a class="dropdown-item ps-4 <?php echo $current_page === 'home_head_vehicle.php' ? 'active' : ''; ?>" href="home_head_vehicle.php"><i class="bi bi-person-badge me-2"></i>หัวหน้าฝ่ายยานยนต์</a></li>
                            <li><a class="dropdown-item ps-4 <?php echo $current_page === 'home_vehicle.php' ? 'active' : ''; ?>" href="home_vehicle.php"><i class="bi bi-car-front me-2"></i>ฝ่ายยานยนต์</a></li>
                        </ul>
                    </div>
                <?php endif; ?>

                <ul class="navbar-nav <?php echo $is_admin_zone ? 'navbar-nav-admin' : 'mx-auto'; ?> mb-2 mb-lg-0">
                    <?php if ($is_admin_zone): ?>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'home_admin.php' ? 'active' : ''; ?>" href="home_admin.php">
                                <i class="bi bi-house-door-fill me-1"></i> หน้าหลัก
                            </a>
                        </li>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'admin.php' ? 'active' : ''; ?>" href="admin.php">
                                <i class="bi bi-person-check-fill me-1"></i> กำหนดสิทธิ์
                            </a>
                        </li>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'admin2.php' ? 'active' : ''; ?>" href="admin2.php">
                                <i class="bi bi-file-earmark-arrow-down me-1"></i> รับหนังสือ
                            </a>
                        </li>

                    <?php elseif ($effective_role === 'head_operation'): ?>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'home_head_operation.php' ? 'active' : ''; ?>" href="home_head_operation.php">
                                <i class="bi bi-house-door-fill me-1"></i> หน้าหลัก
                            </a>
                        </li>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'manage_coordinator_operation.php' ? 'active' : ''; ?>" href="manage_coordinator_operation.php">
                                <i class="bi bi-shield-lock-fill me-1"></i> กำหนดสิทธิ์ผู้ประสานงาน
                            </a>
                        </li>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'manage_operation2.php' ? 'active' : ''; ?>" href="manage_operation2.php">
                                <i class="bi bi-person-badge me-1"></i> มอบหมายงานและอุปกรณ์
                                <?php if ($unassigned_count > 0): ?>
                                    <span class="badge bg-danger rounded-pill ms-1"><?php echo $unassigned_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </li>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'manage_team.php' ? 'active' : ''; ?>" href="manage_team.php">
                                <i class="bi bi-people me-1"></i> จัดชุดปฏิบัติงาน
                            </a>
                        </li>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'view_system_logs.php' ? 'active' : ''; ?>" href="view_system_logs.php">
                                <i class="bi bi-clock-history me-1"></i> ประวัติระบบ
                            </a>
                        </li>

                    <?php elseif ($effective_role === 'coordinator_operation'): ?>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'home_operation.php' ? 'active' : ''; ?>" href="home_operation.php">
                                <i class="bi bi-house-door-fill me-1"></i> หน้าหลัก
                            </a>
                        </li>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'staff_view_operation2.php' ? 'active' : ''; ?>" href="staff_view_operation2.php">
                                <i class="bi bi-clipboard-check me-1"></i> งานที่ได้รับมอบหมาย
                            </a>
                        </li>

                    <?php elseif ($effective_role === 'staff_operation'): ?>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'staff_view_operation.php' ? 'active' : ''; ?>" href="staff_view_operation.php">
                                <i class="bi bi-file-earmark-text me-1"></i> หนังสือที่ส่งถึงฉัน
                            </a>
                        </li>

                    <?php elseif ($effective_role === 'head_vehicle'): ?>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'home_head_vehicle.php' ? 'active' : ''; ?>" href="home_head_vehicle.php">
                                <i class="bi bi-house-door-fill me-1"></i> หน้าหลัก
                            </a>
                        </li>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo in_array($current_page, ['select_mission.php', 'manage_vehicle.php']) ? 'active' : ''; ?>" href="select_mission.php">
                                <i class="bi bi-car-front-fill me-1"></i> บริหารจัดการรถ
                            </a>
                        </li>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'manage_vehicles.php' ? 'active' : ''; ?>" href="manage_vehicles.php">
                                <i class="bi bi-truck me-1"></i> ข้อมูลรถ
                            </a>
                        </li>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'manage_drivers.php' ? 'active' : ''; ?>" href="manage_drivers.php">
                                <i class="bi bi-person-gear me-1"></i> ข้อมูลคนขับ
                            </a>
                        </li>

                    <?php elseif ($effective_role === 'staff_vehicle'): ?>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'home_vehicle.php' ? 'active' : ''; ?>" href="home_vehicle.php">
                                <i class="bi bi-house-door-fill me-1"></i> หน้าหลัก
                            </a>
                        </li>
                        <li class="nav-item mx-1">
                            <a class="nav-link <?php echo $current_page === 'select_mission.php' ? 'active' : ''; ?>" href="select_mission.php">
                                <i class="bi bi-car-front-fill me-1"></i> ภารกิจยานยนต์
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>

                <div class="d-flex align-items-center ms-lg-auto mt-3 mt-lg-0">
                    <div class="text-white text-end me-3 d-none d-lg-block">
                        <?php if (!empty($user_display_name)): ?>
                            <div class="fw-semibold small" style="line-height: 1.2;">
                                <?php echo e($user_display_name); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (!empty($role_name)): ?>
                            <small class="text-white-50" style="font-size: 0.75rem;">
                                (<?php echo e($role_name); ?>)
                            </small>
                        <?php endif; ?>
                    </div>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm text-white border-danger d-flex align-items-center" style="border-radius: 8px;">
                        <i class="bi bi-box-arrow-right me-1"></i> ออกจากระบบ
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

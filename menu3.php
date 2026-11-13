<?php
// ตรวจสอบ Role จาก Session (สมมติว่าคุณเก็บไว้ในชื่อนี้)
$user_role = $_SESSION['role'] ?? ''; 
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
    /* Navbar Styles - คงดีไซน์เดิมที่คุณชอบไว้ */
    .custom-navbar {
        background: linear-gradient(135deg, #0f2027, #203a43, #2c5364);
        box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        padding: 0.8rem 1rem;
    }
    .navbar-brand {
        font-size: 1.4rem;
        letter-spacing: 0.5px;
        font-family: 'Prompt', sans-serif;
    }
    .nav-link {
        font-weight: 400;
        padding: 0.5rem 1.2rem !important;
        border-radius: 8px;
        transition: all 0.2s;
        color: rgba(255,255,255,0.8) !important;
    }
    .nav-link:hover, .nav-link.active {
        background-color: rgba(255, 255, 255, 0.1);
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
    .dropdown-menu {
        border-radius: 12px;
        margin-top: 10px !important;
        box-shadow: 0 10px 25px rgba(0,0,0,0.1);
    }

/* 📱 การปรับแต่งสำหรับหน้าจอมือถือ/แท็บเล็ต */
@media (max-width: 991.98px) {
    .navbar-collapse {
        background: linear-gradient(135deg, #1c323c, #264654);
        padding: 1rem;
        border-radius: 12px;
        margin-top: 0.5rem;
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
    }
    .nav-link {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        margin-bottom: 5px;
    }
    .navbar-nav .nav-item:last-child .nav-link {
        border-bottom: none;
    }
}
</style>

<nav class="navbar navbar-expand-lg navbar-dark custom-navbar sticky-top">
    <div class="container-fluid px-4">
        <a class="navbar-brand fw-semibold d-flex align-items-center" href="index01.php">
            <i class="bi bi-bank2 me-2 text-info"></i>
            ระบบพลับพลาพิธี
        </a>

        <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="navbarContent">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <!-- เมนูหน้าหลัก (แสดงเสมอเพื่อให้เมนูไม่ว่างเปล่า) -->
                <li class="nav-item mx-1">
                    <a class="nav-link <?php echo ($current_page == 'home.php' || $current_page == 'index01.php') ? 'active' : ''; ?>" href="home.php">
                        <i class="bi bi-house-door-fill me-1"></i> หน้าหลัก
                    </a>
                </li>
                
                <?php if ($user_role == 'admin'): ?>
                    <li class="nav-item mx-1">
                        <a class="nav-link <?php echo $current_page == 'admin3.php' ? 'active' : ''; ?>" href="admin.php">
                            <i class="bi bi-person-lines-fill me-1"></i> กำหนดสิทธิ์
                        </a>
                    </li>
                    <li class="nav-item mx-1">
                        <a class="nav-link <?php echo $current_page == 'admin2.php' ? 'active' : ''; ?>" href="admin2.php">
                            <i class="bi bi-journal-arrow-down me-1"></i> รับหนังสือ
                        </a>
                    </li>

                <?php elseif ($user_role == 'staff_operation'): ?>
                    <li class="nav-item mx-1">
                        <a class="nav-link <?php echo $current_page == 'staff_view_operation.php' ? 'active' : ''; ?>" href="staff_view_operation.php">
                            <i class="bi bi-file-earmark-text me-1"></i> ดูหนังสือที่หัวหน้าส่งมา
                        </a>
                    </li>

                <?php elseif ($user_role == 'staff_vehicle'): ?>
                    <li class="nav-item mx-1">
                        <a class="nav-link <?php echo $current_page == 'staff_view_vehicle.php' ? 'active' : ''; ?>" href="staff_view_vehicle.php">
                            <i class="bi bi-car-front me-1"></i> ดูหนังสือที่หัวหน้าส่งมา
                        </a>
                    </li>

                <?php elseif ($user_role == 'head_operation'): ?>
                    <li class="nav-item mx-1">
                        <a class="nav-link <?php echo $current_page == 'head_operation.php' ? 'active' : ''; ?>" href="head_operation.php">
                            <i class="bi bi-clipboard-check me-1"></i> ประสานงานปฏิบัติการ
                        </a>
                    </li>

                <?php elseif ($user_role == 'head_vehicle'): ?>
                    <li class="nav-item mx-1">
                        <a class="nav-link <?php echo $current_page == 'head_vehicle.php' ? 'active' : ''; ?>" href="head_vehicle.php">
                            <i class="bi bi-bus-front me-1"></i> ประสานงานยานยนต์
                        </a>
                    </li>
                <?php endif; ?>

            </ul>

            <ul class="navbar-nav align-items-center">
                <?php if ($user_role == 'pending'): ?>
                    <li class="nav-item">
                        <span class="nav-link text-warning">
                            <i class="bi bi-exclamation-circle me-1"></i> แอดมินยังไม่กำหนดสิทธิ์
                        </span>
                    </li>
                <?php else: ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="profileDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <div class="nav-profile-icon me-2">
                                <i class="bi bi-person-fill text-white"></i>
                            </div>
                            <span><?php echo htmlspecialchars($user_role); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0" aria-labelledby="profileDropdown">
                            <li>
                                <a class="dropdown-item text-danger d-flex align-items-center py-2" href="logout.php">
                                    <i class="bi bi-box-arrow-right me-2"></i> ล็อคเอาท์
                                </a>
                            </li>
                        </ul>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
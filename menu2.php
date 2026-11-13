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
            </ul>

            <ul class="navbar-nav align-items-center">
                
            </ul>
        </div>
    </div>
</nav>
<?php

session_start();
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>หน้าหลัก - ระบบพับพลาพิธี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f4f6f9; margin: 0; padding: 0; }
        .content-wrapper { min-height: calc(100vh - 150px); padding-bottom: 3rem; }
        .hero-section { background-color: #1e293b; padding: 4rem 0; text-align: center; border-bottom: 4px solid #eab308; margin-bottom: 3rem; }
        .hero-title { color: #facc15; font-weight: 700; font-size: 2.5rem; margin-bottom: 0.5rem; }
        .hero-subtitle { color: #ffffff; font-weight: 400; font-size: 1.2rem; opacity: 0.9; margin-bottom: 0; }
        .role-card { display: block; background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem 1.5rem; text-align: center; text-decoration: none; color: #334155; transition: all 0.2s ease; height: 100%; position: relative; }
        .role-card:hover { background-color: #f8fafc; border-color: #eab308; color: #0f172a; transform: translateY(-5px); box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); }
        .role-card::after { content: "\F14B"; font-family: "bootstrap-icons"; position: absolute; top: 10px; right: 10px; font-size: 0.9rem; color: #cbd5e1; opacity: 0; transition: opacity 0.2s; }
        .role-card:hover::after { opacity: 1; }
        .role-icon { font-size: 2.5rem; color: #64748b; margin-bottom: 1rem; }
        .role-title { font-weight: 600; font-size: 1.1rem; margin: 0; }

        /* Responsive */
        @media (max-width: 767.98px) {
            .hero-section { padding: 2.5rem 0; margin-bottom: 2rem; }
            .hero-title { font-size: 1.8rem; }
            .hero-subtitle { font-size: 1rem; }
            .alert-lg { padding: 1.2rem !important; font-size: 1.05rem !important; }
            .alert-lg .bi { font-size: 1.2rem !important; }
            .alert-lg span { font-size: 0.9rem !important; }
        }
    </style>
</head>
<body>

    <?php if(file_exists('menu3.php')) require 'menu3.php'; ?>
    
    <div class="content-wrapper">
        <div class="hero-section">
            <div class="container">
                <h1 class="hero-title">ระบบพับพลาพิธี</h1>
                <h2 class="hero-subtitle">กรมโยธาธิการและผังเมือง ศูนย์เทคโนโลยีสารสนเทศ</h2>
            </div>
        </div>

        <div class="container">
            <div class="row g-4 justify-content-center">
            <div class="col-12">
                <div class="alert alert-info alert-lg" role="alert" style="padding: 1.5rem; font-size: 1.2rem;">
                    <i class="bi bi-info-circle" style="font-size: 1.5rem; margin-right: 0.5rem;"></i> 
                    <strong><?php echo (isset($user_role) && !empty($user_role) && $user_role !== 'pending') ? 'ยินดีต้อนรับ ' . htmlspecialchars($user_role) : 'แอดมินยังไม่กำหนดสิทธิ์'; ?></strong>
                    <br>
                    <?php if(isset($user_role) && !empty($user_role) && $user_role !== 'pending'): ?>
                        <strong>คลิกเลือกลิงก์ที่เมนูบาร์เพื่อเริ่มทดสอบระบบพับพลาพิธี</strong>
                    <?php endif; ?>
                </div></div>
               
            </div>
        </div>
    </div>

    <?php if(file_exists('footer.php')) require 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
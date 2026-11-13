<?php
session_start();
require 'db.php';

// ตรวจสอบ Session Timeout (2 ชั่วโมง = 7200 วินาที)
$timeout_duration = 7200;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: index.php"); // หรือกลับไปหน้า login
    exit();
}
$_SESSION['last_activity'] = time(); // อัปเดตเวลาใช้งานล่าสุด

// ==========================================
// ดึงข้อมูลสถิติจากฐานข้อมูล (Dashboard Data)
// ==========================================

// 1. คำนวณปีงบประมาณปัจจุบัน (ต.ค. - ก.ย.)
$current_month = (int)date('m');
$current_year_be = (int)date('Y') + 543;
$current_fiscal_year = ($current_month >= 10) ? $current_year_be + 1 : $current_year_be;

// 2. จำนวนงานทั้งหมดในปีงบประมาณนี้
$stmt = $conn->prepare("SELECT COUNT(*) FROM documents WHERE fiscal_year = ?");
$stmt->execute([$current_fiscal_year]);
$total_jobs = $stmt->fetchColumn() ?: 0;

// 3. จำนวนงานที่ "รอดำเนินการ / รอจัดชุด" (ยังไม่มีการส่งต่อให้ฝ่ายปฏิบัติการ และยังไม่ถูกมอบหมายให้ผู้ประสานงาน)
$stmt = $conn->prepare("
    SELECT COUNT(*) FROM documents 
    WHERE fiscal_year = ? 
    AND (sent_to IS NULL OR sent_to = '') 
    AND id NOT IN (SELECT doc_id FROM document_assignments)
");
$stmt->execute([$current_fiscal_year]);
$pending_jobs = $stmt->fetchColumn() ?: 0;

// 4. จำนวนงานที่ "ดำเนินการเสร็จสิ้น / จัดชุดแล้ว"
$completed_jobs = $total_jobs - $pending_jobs;

// 5. จำนวนยานพาหนะ (ทดสอบดึงจากตาราง vehicles หากมีตารางอยู่ หากไม่มีให้เป็น 0)
$ready_vehicles = 0;
try {
    $stmt_vehicles = $conn->query("SELECT COUNT(*) FROM vehicles");
    $ready_vehicles = $stmt_vehicles->fetchColumn() ?: 0;
} catch (Exception $e) {
    $ready_vehicles = 0; // fallback กรณีตาราง vehicles ยังไม่ถูกสร้าง
}

// 6. เตรียมข้อมูลสำหรับกราฟ "ปริมาณงานรายเดือน" ในปีงบประมาณ
$months_th = [
    1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.', 5 => 'พ.ค.', 6 => 'มิ.ย.',
    7 => 'ก.ค.', 8 => 'ส.ค.', 9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
];
$chart_labels = [];
$chart_values = [];

$stmt_chart = $conn->prepare("SELECT MONTH(received_date) as m, COUNT(*) as cnt FROM documents WHERE fiscal_year = ? AND received_date IS NOT NULL GROUP BY MONTH(received_date)");
$stmt_chart->execute([$current_fiscal_year]);
$db_monthly = $stmt_chart->fetchAll(PDO::FETCH_KEY_PAIR);

$fiscal_months = [10, 11, 12, 1, 2, 3, 4, 5, 6, 7, 8, 9]; // เรียงลำดับปีงบประมาณ (เริ่ม ต.ค.)
foreach ($fiscal_months as $m) {
    $chart_labels[] = $months_th[$m];
    $chart_values[] = isset($db_monthly[$m]) ? (int)$db_monthly[$m] : 0;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แดชบอร์ด (Dashboard) - ระบบพลับพลาพิธี</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">

    <style>
        body {
            font-family: 'Prompt', sans-serif;
            background-color: #f4f7f9;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }

        .content-wrapper {
            flex: 1 0 auto;
            padding-bottom: 3rem;
        }

        .page-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white;
            padding: 2.5rem 0;
            margin-bottom: 2rem;
            border-radius: 0 0 1.5rem 1.5rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }

        .stat-card {
            background: #ffffff;
            border-radius: 1.25rem;
            padding: 1.5rem;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            height: 100%;
            display: flex;
            align-items: center;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 70px;
            height: 70px;
            border-radius: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-right: 1.5rem;
            flex-shrink: 0;
        }

        .custom-card {
            border: none;
            border-radius: 1.25rem;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04);
            background: #ffffff;
            overflow: hidden;
        }

        .card-header-custom {
            background-color: transparent;
            border-bottom: 1px solid #f1f5f9;
            padding: 1.5rem;
        }

        /* -----------------------------------------
           Custom CSS for Dynamic Text
           ----------------------------------------- */
           
        /* สำหรับหน้าจอทั่วไป (มือถือ/แท็บเล็ต) ให้ข้อความขึ้นบรรทัดใหม่ได้ */
        .dynamic-text-wrap {
            white-space: normal;
            word-break: break-word;
        }

        /* สำหรับหน้าจอคอมพิวเตอร์ (ขนาดหน้าจอ 992px ขึ้นไป) บังคับให้อยู่บรรทัดเดียว */
        @media (min-width: 992px) {
            .dynamic-text-wrap {
                white-space: nowrap;
            }
        }
    </style>
</head>

<body>

    <?php if(file_exists('menu.php')) require 'menu.php'; ?>

    <div class="content-wrapper">
        <div class="page-header">
            <div class="container position-relative text-start">
                <div class="row align-items-center">
                    <div class="col-12 col-md-2 text-md-start mb-3 mb-md-0 text-center">
                        <a href="home.php" class="btn btn-outline-light rounded-pill d-inline-flex align-items-center mt-3 mt-md-0 text-nowrap">
                            <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
                        </a>
                    </div>
                    <div class="col-12 col-md-8 text-center">
                        <h2 class="fw-bold mb-2 mt-3 mt-md-0">
                            <i class="bi bi-bar-chart-fill me-2 text-warning"></i>แดชบอร์ด (Dashboard)
                        </h2>
                        <p class="text-white-50 mb-0 fs-5 dynamic-text-wrap">
                            ภาพรวมและสถิติระบบบริหารจัดการงานพลับพลาพิธี (ปีงบประมาณ <?php echo $current_fiscal_year; ?>)
                        </p>
                    </div>
                    <div class="col-12 col-md-2"></div>
                </div>
            </div>
        </div>

        <div class="container">
       <div class="row g-4 mb-4">
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="stat-card border-bottom border-primary border-4">
                        <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                            <i class="bi bi-journal-text"></i>
                        </div>
                        <style>
                            /* สำหรับมือถือและแท็บเล็ต ให้ขึ้นบรรทัดใหม่ได้ */
.card-text-responsive {
    white-space: normal;
    word-break: break-word;
}

/* สำหรับหน้าจอคอมพิวเตอร์ (ขนาด 992px ขึ้นไป) บังคับเป็นบรรทัดเดียว */
@media (min-width: 1400px) {
    .card-text-responsive {
        white-space: nowrap;
    }
}
                        </style>
<div>
    <h2 class="fw-bold text-dark mb-0"><?php echo number_format($total_jobs); ?></h2>
    <p class="text-muted mb-0 small fw-medium card-text-responsive">
        งานทั้งหมด (ปีงบประมาณนี้)
    </p>
</div> 
</div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="stat-card border-bottom border-warning border-4">
                        <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                            <i class="bi bi-hourglass-split"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold text-dark mb-0"><?php echo number_format($pending_jobs); ?></h2>
                            <p class="text-muted mb-0 small fw-medium" style="white-space: normal; word-break: break-word;">
                                รอดำเนินการ / รอจัดชุด
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="stat-card border-bottom border-success border-4">
                        <div class="stat-icon bg-success bg-opacity-10 text-success">
                            <i class="bi bi-check-circle-fill"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold text-dark mb-0"><?php echo number_format($completed_jobs); ?></h2>
                            <p class="text-muted mb-0 small fw-medium" style="white-space: normal; word-break: break-word;">
                                ดำเนินการเสร็จสิ้น
                            </p>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="stat-card border-bottom border-info border-4">
                        <div class="stat-icon bg-info bg-opacity-10 text-info">
                            <i class="bi bi-truck"></i>
                        </div>
                        <div>
                            <h2 class="fw-bold text-dark mb-0"><?php echo number_format($ready_vehicles); ?></h2>
                            <p class="text-muted mb-0 small fw-medium" style="white-space: normal; word-break: break-word;">
                                ยานพาหนะพร้อมใช้งาน
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-lg-12">
                    <div class="card custom-card h-100">
                        <div class="card-header-custom d-flex justify-content-between align-items-center">
                            <h5 class="fw-bold mb-0 text-dark">
                                <i class="bi bi-graph-up text-primary me-2"></i>สถิติปริมาณงานในแต่ละเดือน
                            </h5>
                        </div>
                        <div class="card-body p-4 d-flex align-items-center justify-content-center" style="min-height: 400px; background-color: #ffffff;">
                            <canvas id="monthlyChart" style="width: 100%; height: 100%; max-height: 380px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php if(file_exists('footer.php')) require 'footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const ctx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($chart_labels); ?>,
                datasets: [{
                    label: 'จำนวนงาน (รายการ)',
                    data: <?php echo json_encode($chart_values); ?>,
                    backgroundColor: 'rgba(42, 82, 152, 0.7)',
                    borderColor: 'rgba(30, 60, 114, 1)',
                    borderWidth: 1,
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    });
    </script>
</body>

</html>
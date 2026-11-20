<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

require_role([ROLE_STAFF_OPERATION, ROLE_COORDINATOR_OPERATION, ROLE_HEAD_OPERATION, ROLE_SUPER_ADMIN]);

$conn = get_db();
$my_id = (int)($_SESSION['user_id'] ?? 0);
$doc_id = clean_int($_GET['id'] ?? 0);

if (!$doc_id) {
    header("Location: staff_view_operation.php");
    exit();
}

$is_privileged = has_role([ROLE_SUPER_ADMIN, ROLE_HEAD_OPERATION]);

try {
    if ($is_privileged) {
        $sql = "SELECT d.*, u.fname as sender_fname, u.lname as sender_lname, u.role as sender_role 
                FROM documents d
                LEFT JOIN system_logs sl ON sl.id = (
                    SELECT MAX(id) FROM system_logs WHERE job_id = d.id AND action_type IN ('ASSIGN_STAFF', 'CHANGE_ASSIGN')
                )
                LEFT JOIN users u ON sl.performed_by = u.username_sso 
                WHERE d.id = :doc_id 
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':doc_id' => $doc_id]);
    } else {
        $sql = "SELECT d.*, u.fname as sender_fname, u.lname as sender_lname, u.role as sender_role 
                FROM documents d
                LEFT JOIN system_logs sl ON sl.id = (
                    SELECT MAX(id) FROM system_logs WHERE job_id = d.id AND action_type IN ('ASSIGN_STAFF', 'CHANGE_ASSIGN')
                )
                LEFT JOIN users u ON sl.performed_by = u.username_sso 
                WHERE d.id = :doc_id 
                  AND (d.sent_to = :my_id OR EXISTS (SELECT 1 FROM document_assignments da WHERE da.doc_id = d.id AND da.user_id = :my_id2))
                LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->execute([':doc_id' => $doc_id, ':my_id' => $my_id, ':my_id2' => $my_id]);
    }

    $doc = $stmt->fetch(PDO::FETCH_ASSOC);

    // ถ้าไม่พบข้อมูล หรือพยายามเข้าดูของคนอื่น (IDOR Guard)
    if (!$doc) {
        render_403_page("ไม่พบข้อมูลเอกสาร หรือคุณไม่มีสิทธิ์เข้าถึงเอกสารนี้", "staff_view_operation.php");
    }

} catch (PDOException $e) {
    error_log("Database Error in view_detail.php: " . $e->getMessage());
    die("<div class='container mt-5 alert alert-danger text-center'>ขออภัย ระบบขัดข้องชั่วคราว</div>");
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายละเอียดงาน - <?php echo htmlspecialchars($doc['doc_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { 
            font-family: 'Prompt', sans-serif; 
            background-color: #f0f4f8; 
            color: #334155;
        }
        .detail-card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            background: #ffffff;
            overflow: hidden;
        }
        .detail-header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            padding: 2.5rem 2rem;
            color: white;
        }
        .info-label {
            font-size: 0.85rem;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .info-content {
            font-size: 1.1rem;
            color: #1e293b;
            margin-bottom: 1.5rem;
        }
        .status-badge {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            padding: 0.5rem 1.2rem;
            border-radius: 50rem;
            font-size: 0.9rem;
        }
        .back-btn {
            transition: all 0.3s ease;
        }
        .back-btn:hover {
            transform: translateX(-5px);
        }
        .doc-body {
            padding: 2rem;
        }
        .meta-box {
            background-color: #f8fafc;
            border-radius: 12px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
        }
        
        /* --- รูปแบบสำหรับการพิมพ์แบบฟอร์มเอกสาร (Print Media) --- */
        @media print {
            body { background: white; }
            body * {
                visibility: hidden;
            }
            #printDocument, #printDocument * {
                visibility: visible;
            }
            #printDocument {
                display: block !important;
                position: absolute;
                left: 0;
                top: 0;
                width: 100%;
                margin: 0;
                padding: 0;
            }
            @page { margin: 1.5cm; size: A4; }
        }
    </style>
    <link rel="stylesheet" href="css/responsive.css">
</head>
<body>

  <?php require 'menu.php'; ?>
 <div class="content-wrapper">
        <div class="container mt-4 mb-5">
            
    </div>

<div class="container py-5">
    <div class="mb-4">
        <a href="staff_view_operation.php" class="text-decoration-none text-muted back-btn d-inline-block">
            <i class="bi bi-arrow-left-circle me-2"></i> กลับสู่หน้ารายการ
        </a>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="detail-card">
                <div class="detail-header text-center text-md-start">
                    <div class="d-md-flex justify-content-between align-items-center">
                        <div>
                            <span class="status-badge mb-3 d-inline-block">
                                <i class="bi bi-file-earmark-text me-1"></i> รายละเอียดงานเอกสาร
                            </span>
                            <h2 class="fw-bold mb-0"><?php echo htmlspecialchars($doc['doc_name']); ?></h2>
                        </div>
                        <div class="mt-3 mt-md-0 text-md-end">
                            <div class="small opacity-75">วันที่ได้รับมอบหมาย</div>
                            <div class="fw-medium"><?php echo date('d M Y | H:i', strtotime($doc['received_date'])); ?></div>
                        </div>
                    </div>
                </div>

                <div class="doc-body">
                    <div class="row">
                        <div class="col-12">
                            <div class="info-label">รายละเอียดงาน / คำสั่ง</div>
                            <div class="info-content p-3 border rounded-3 bg-light min-vh-25" style="white-space: pre-line; word-break: break-word; overflow-wrap: break-word;">
                                <?php echo htmlspecialchars($doc['doc_detail']); ?>
                            </div>
                        </div>

                        <div class="col-md-6 mt-4">
                            <div class="meta-box h-100">
                                <div class="info-label text-primary">ผู้มอบหมายงาน</div>
                                <div class="d-flex align-items-center mt-2">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                                        <i class="bi bi-person-badge fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($doc['sender_fname'] . " " . $doc['sender_lname']); ?></div>
                                        <div class="small text-muted"><?php echo htmlspecialchars($doc['sender_role']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mt-4">
                            <div class="meta-box h-100">
                                <div class="info-label text-primary">การจัดการ</div>
                                <div class="mt-2">
                                    <button class="btn btn-success w-100 mb-2 rounded-pill">
                                        <i class="bi bi-check2-all me-1"></i> ยืนยันรับทราบงาน
                                    </button>
                                    <button class="btn btn-outline-secondary w-100 rounded-pill" onclick="window.print()">
                                        <i class="bi bi-printer me-1"></i> พิมพ์เอกสาร
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-white py-3 text-center border-top">
                    <!-- <small class="text-muted">ID อ้างอิงเอกสาร: #<?php echo str_pad($doc['id'], 6, '0', STR_PAD_LEFT); ?></small> -->
                </div>
            </div>
        </div>
    </div>
</div>
</div>
          
<!-- แบบฟอร์มสำหรับพิมพ์หนังสือราชการ (ซ่อนไว้แสดงเฉพาะตอนพิมพ์) -->
<div id="printDocument" class="d-none">
    <div style="width: 100%; font-family: 'Sarabun', 'TH SarabunPSK', 'Prompt', sans-serif; color: black; background: white; line-height: 1.5;">
        <div style="display: flex; align-items: flex-start; justify-content: flex-start; margin-bottom: 20px;">
            <img src="https://info.dpt.go.th/App-implement/images/krut.jpg" alt="ตราครุฑ" style="width: 55px; height: auto; margin-right: 15px;">
            <div style="font-size: 24pt; font-weight: bold; margin-top: 15px;">บันทึกข้อความ</div>
        </div>
        
        <div style="font-size: 16pt;">
            <div style="display: flex; margin-bottom: 5px;">
                <div style="font-weight: bold; min-width: 100px;">ส่วนราชการ</div>
                <div>กองพลับพลาพิธี กรมโยธาธิการและผังเมือง</div>
            </div>
            <div style="display: flex; margin-bottom: 5px;">
                <div style="flex: 1; display: flex;">
                    <div style="font-weight: bold; min-width: 40px;">ที่</div>
                    <div>-</div>
                </div>
                <div style="flex: 1; display: flex;">
                    <div style="font-weight: bold; min-width: 60px;">วันที่</div>
                    <div><?php echo date('d/m/Y', strtotime($doc['received_date'])); ?></div>
                </div>
            </div>
            <div style="display: flex; margin-bottom: 20px;">
                <div style="font-weight: bold; min-width: 60px;">เรื่อง</div>
                <div><?php echo htmlspecialchars($doc['doc_name']); ?></div>
            </div>
            
            <div style="display: flex; margin-bottom: 20px;">
                <div style="font-weight: bold; min-width: 60px;">เรียน</div>
                <div>เจ้าหน้าที่ผู้รับผิดชอบ / ชุดปฏิบัติงาน</div>
            </div>
            
            <div style="margin-bottom: 40px; text-indent: 40px; text-align: justify; min-height: 100px;">
                <?php echo htmlspecialchars($doc['doc_detail'] ?? $doc['description']); ?>
            </div>
            
            <div style="display: flex; justify-content: flex-end; margin-top: 50px;">
                <div style="text-align: center; min-width: 250px;">
                    <div style="margin-bottom: 10px;">(ลงชื่อ)...........................................................</div>
                    <div style="margin-bottom: 5px;">(<?php echo htmlspecialchars($doc['sender_fname'] . " " . $doc['sender_lname']); ?>)</div>
                    <div><?php echo htmlspecialchars($doc['sender_role']); ?></div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
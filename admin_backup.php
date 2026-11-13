<?php
session_start();
require 'db.php';

// จัดการการเพิ่มข้อมูลหนังสือ
if (isset($_POST['add_document'])) {
    $doc_name = $_POST['doc_name'];
    $doc_detail = $_POST['doc_detail'];
    $received_date = $_POST['received_date'];
    
    $sql = "INSERT INTO documents (doc_name, doc_detail, received_date, created_by) VALUES (?, ?, ?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$doc_name, $doc_detail, $received_date, $_SESSION['username'] ?? 'Admin']); 
    
    header("Location: admin2.php?success=1");
    exit();
}

// เช็คว่ามี $conn ก่อนเรียกใช้เพื่อป้องกัน Error
if(isset($conn)) {
    $docs = $conn->query("SELECT * FROM documents ORDER BY id DESC")->fetchAll();
} else {
    $docs = []; 
}
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบรับหนังสือ</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <link rel="stylesheet" href="css/admin.css">

   
</head>

<body>

    <?php require 'menu.php'; ?>
    
    <div class="content-wrapper">
        <div class="page-header shadow-sm">
            <div class="container">
                <h4 class="fw-bold mb-1 text-dark">ระบบลงทะเบียนรับหนังสือ</h4>
                <p class="text-muted mb-0 small">จัดการข้อมูลหนังสือเข้าและเอกสารต่างๆ ภายในองค์กร</p>
            </div>
        </div>

        <div class="container">
            <?php if(isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 mb-4 rounded-3 d-flex align-items-center" role="alert" style="background-color: #c9f7f5; color: #1bc5bd;">
                <i class="bi bi-check-circle-fill fs-5 me-2"></i> 
                <strong>บันทึกสำเร็จ!</strong>&nbsp;เพิ่มข้อมูลหนังสือเรียบร้อยแล้ว
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>

            <div class="row g-4">
                <div class="col-lg-4">
                    <div class="card card-custom">
                        <div class="card-header-custom">
                            <div class="icon-box">
                                <i class="bi bi-journal-plus"></i>
                            </div>
                            <h6 class="mb-0 fw-bold text-dark">กรอกข้อมูลหนังสือ</h6>
                        </div>
                        <div class="card-body p-4">
                            <form method="post">
                                <div class="mb-4">
                                    <label class="form-label">ชื่อหนังสือ / เลขที่หนังสือ</label>
                                    <input type="text" name="doc_name" class="form-control" placeholder="ระบุเลขที่หนังสือ..." required>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">รายละเอียด</label>
                                    <textarea name="doc_detail" class="form-control" rows="4" placeholder="ระบุรายละเอียด..." required></textarea>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label">วันที่-เวลาที่รับ</label>
                                    <input type="datetime-local" name="received_date" class="form-control" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                                </div>
                                <button type="submit" name="add_document" class="btn btn-custom-primary w-100">
                                    <i class="bi bi-save me-2"></i> บันทึกข้อมูล
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="col-lg-8">
                    <div class="card card-custom">
                        <div class="card-header-custom justify-content-between">
                            <div class="d-flex align-items-center">
                                <div class="icon-box" style="background-color: #eee5ff; color: #8950fc;">
                                    <i class="bi bi-table"></i>
                                </div>
                                <h6 class="mb-0 fw-bold text-dark">รายการข้อมูลในระบบ</h6>
                            </div>
                            <span class="badge" style="background-color: #f3f6f9; color: #7e8299; font-size: 0.85rem; padding: 0.5rem 0.8rem;">
                                ทั้งหมด <?php echo count($docs); ?> รายการ
                            </span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-custom">
                                    <thead>
                                        <tr>
                                            <th style="width: 40%; color:black;">ชื่อ/เลขที่หนังสือ</th>
                                            <th style="width: 25%; color:black;">วันที่รับ</th>
                                            <th style="width: 20%; color:black;">ผู้บันทึก</th>
                                            <th class="text-center" style="width: 15%; color:black;">จัดการ</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($docs)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <i class="bi bi-inbox fs-1 d-block mb-2" style="color: #b5b5c3;"></i>
                                                ไม่พบข้อมูลหนังสือในระบบ
                                            </td>
                                        </tr>
                                        <?php endif; ?>

                                        <?php foreach ($docs as $doc): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($doc['doc_name']); ?></div>
                                                <div class="text-truncate-multi"><?php echo htmlspecialchars($doc['doc_detail']); ?></div>
                                            </td>
                                            <td>
                                                <div class="fw-medium text-dark small">
                                                    <i class="bi bi-calendar-event me-1 text-muted"></i> <?php echo date('d/m/Y', strtotime($doc['received_date'])); ?>
                                                </div>
                                                <div class="text-muted small mt-1">
                                                    <i class="bi bi-clock me-1"></i> <?php echo date('H:i', strtotime($doc['received_date'])); ?> น.
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge-user">
                                                    <i class="bi bi-person-circle me-1"></i>
                                                    <?php echo htmlspecialchars($doc['created_by']); ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <button class="btn btn-sm border-0" title="ดูรายละเอียด"><i class="bi bi-eye"></i></button>
                                                    <button class="btn btn-sm border-0" title="แก้ไข"><i class="bi bi-pencil"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php require 'footer.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
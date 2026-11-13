<?php
session_start();
require 'db.php';

// เปิดการแสดง Error (เอาออกเมื่อใช้งานจริง)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// --- 1. ส่วนจัดการการลบข้อมูล ---
if (isset($_GET['delete_id'])) {
    try {
        $id = $_GET['delete_id'];
        $stmt = $conn->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: manage_operation2.php?delete_success=1");
        exit();
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการลบข้อมูล: " . $e->getMessage());
    }
}

// --- 2. จัดการการเพิ่มข้อมูลงาน (INSERT) ---
if (isset($_POST['add_document'])) {
    try {
        $doc_number = $_POST['doc_number'] ?? '';
        $job_type = $_POST['job_type'] ?? '';
        $job_type_other = $_POST['job_type_other'] ?? '';
        
        if ($job_type === 'อื่นๆ' && !empty($job_type_other)) {
            $job_type = $job_type_other;
        }
        
        $doc_name = $_POST['doc_name'] ?? '';
        $description = $_POST['description'] ?? '';
        $date = new DateTime("now", new DateTimeZone('Asia/Bangkok'));
        $received_date = $date->format('Y-m-d H:i:s');
        $created_by = $_SESSION['username'] ?? 'Admin';
        
        $sql = "INSERT INTO documents (doc_number, job_type, doc_name, description, received_date, created_by) 
                VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$doc_number, $job_type, $doc_name, $description, $received_date, $created_by]); 
        
        header("Location: manage_operation2.php?success=1");
        exit();
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการบันทึกข้อมูล (เพิ่มข้อมูล): " . $e->getMessage());
    }
}

// --- 3. จัดการการแก้ไขข้อมูลงาน (UPDATE) ---
if (isset($_POST['edit_document'])) {
    try {
        $id = $_POST['id'] ?? '';
        $doc_number = $_POST['doc_number'] ?? '';
        $job_type = $_POST['job_type'] ?? '';
        $job_type_other = $_POST['job_type_other'] ?? '';
        
        if ($job_type === 'อื่นๆ' && !empty($job_type_other)) {
            $job_type = $job_type_other;
        }
        
        $doc_name = $_POST['doc_name'] ?? '';
        $description = $_POST['description'] ?? '';
        
        $sql = "UPDATE documents SET doc_number = ?, job_type = ?, doc_name = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$doc_number, $job_type, $doc_name, $description, $id]); 
        
        header("Location: manage_operation2.php?edit_success=1");
        exit();
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการบันทึกข้อมูล (แก้ไขข้อมูล): " . $e->getMessage());
    }
}

// --- 4. จัดการการ "ดึงหนังสือกลับ" (Cancel Forward) + เก็บ Log ---
if (isset($_GET['cancel_forward_id'])) {
    try {
        $id = $_GET['cancel_forward_id'];
        
        // ดึงข้อมูลผู้ประสานงานเดิมเพื่อนำไปบันทึก Log
        $stmt_old = $conn->prepare("SELECT u.fname, u.lname FROM documents d LEFT JOIN users u ON d.sent_to = u.id WHERE d.id = ?");
        $stmt_old->execute([$id]);
        $old_user = $stmt_old->fetch();
        $old_name = $old_user ? ($old_user['fname'] . ' ' . $old_user['lname']) : 'ไม่ระบุ';

        // ยกเลิกการส่งต่อ
        $stmt = $conn->prepare("UPDATE documents SET sent_to = NULL, is_acknowledged = 0 WHERE id = ?");
        $stmt->execute([$id]);

        // บันทึกตาราง system_logs
        $admin_name = $_SESSION['username'] ?? 'Admin';
        $log_stmt = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (?, 'CANCEL_ASSIGN', ?, ?)");
        $log_stmt->execute([$id, "ยกเลิกการมอบหมายงานผู้ประสานงาน: ($old_name)", $admin_name]);

        header("Location: manage_operation2.php?cancel_success=1");
        exit();
    } catch (PDOException $e) {
         die("เกิดข้อผิดพลาดในการยกเลิกการส่งต่อ: " . $e->getMessage());
    }
}

// --- 5. จัดการการส่งต่อหนังสือ (Inline Forward) + เก็บ Log ---
if (isset($_POST['forward_doc'])) {
    try {
        $doc_id = $_POST['doc_id'] ?? '';
        $send_to = $_POST['send_to'] ?? ''; 
        
        if (!empty($send_to)) {
            // เช็คว่าเป็นการมอบหมายใหม่ หรือ เปลี่ยนคน
            $stmt_check = $conn->prepare("SELECT sent_to FROM documents WHERE id = ?");
            $stmt_check->execute([$doc_id]);
            $old_sent_to = $stmt_check->fetchColumn();

            $sql = "UPDATE documents SET sent_to = :staff_id WHERE id = :doc_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':staff_id' => $send_to, ':doc_id' => $doc_id]);

            // ดึงชื่อคนที่ถูกมอบหมายใหม่
            $stmt_new = $conn->prepare("SELECT fname, lname FROM users WHERE id = ?");
            $stmt_new->execute([$send_to]);
            $new_user = $stmt_new->fetch();
            $new_name = $new_user['fname'] . ' ' . $new_user['lname'];

            $admin_name = $_SESSION['username'] ?? 'Admin';

            // บันทึกตาราง system_logs
            if ($old_sent_to && $old_sent_to != $send_to) {
                // กรณีเปลี่ยนคน
                $log_stmt = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (?, 'CHANGE_ASSIGN', ?, ?)");
                $log_stmt->execute([$doc_id, "เปลี่ยนผู้ประสานงานเป็น: $new_name", $admin_name]);
            } else if (!$old_sent_to) {
                // กรณีมอบหมายครั้งแรก
                $log_stmt = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (?, 'ASSIGN_STAFF', ?, ?)");
                $log_stmt->execute([$doc_id, "มอบหมายงานให้ผู้ประสานงาน: $new_name", $admin_name]);
            }

            header("Location: manage_operation2.php?forward_success=1");
            exit();
        } else {
            header("Location: manage_operation2.php");
            exit();
        }
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการส่งต่อหนังสือ: " . $e->getMessage());
    }
}

// --- 6. ส่วนบันทึกการ "จัดชุด" (จัดการแค่ชื่อชุดจาก Select Option) ---
if (isset($_POST['manage_sets'])) {
    try {
        $job_id = $_POST['job_id'] ?? '';
        $set_target = trim($_POST['existing_set'] ?? '');

        if (empty($set_target)) throw new Exception("กรุณาระบุชื่อชุดปฏิบัติงาน");

        if (!$conn->inTransaction()) {
            $conn->beginTransaction();
        }
        
        $stmt_doc = $conn->prepare("SELECT doc_name, job_type FROM documents WHERE id = ?");
        $stmt_doc->execute([$job_id]);
        $document = $stmt_doc->fetch();
        
        if (!$document) {
            throw new Exception("ไม่พบข้อมูลอ้างอิงเอกสารในระบบ");
        }
        
        $stmt_check_job = $conn->prepare("SELECT id FROM jobs WHERE id = ?");
        $stmt_check_job->execute([$job_id]);
        if (!$stmt_check_job->fetch()) {
            $job_type = !empty($document['job_type']) ? $document['job_type'] : 'อื่นๆ';
            $doc_name = !empty($document['doc_name']) ? $document['doc_name'] : 'ไม่มีชื่อ';
            
            $stmt_insert_job = $conn->prepare("INSERT INTO jobs (id, job_name, job_type) VALUES (?, ?, ?)");
            $stmt_insert_job->execute([$job_id, $doc_name, $job_type]);
        }
        
        $staff_role = $set_target; 
        
        $stmt_check_exist = $conn->prepare("SELECT COUNT(*) FROM job_assignments WHERE job_id = ? AND staff_role = ?");
        $stmt_check_exist->execute([$job_id, $staff_role]);

        if ($stmt_check_exist->fetchColumn() == 0) {
            $stmt_find_members = $conn->prepare("
    SELECT DISTINCT staff_name 
    FROM job_assignments 
    WHERE staff_role = ? AND job_id IS NULL AND staff_name != 'รอระบุบุคคล'
");
            $stmt_find_members->execute([$staff_role]);
            $master_members = $stmt_find_members->fetchAll(PDO::FETCH_COLUMN);

            if (count($master_members) > 0) {
                $stmt_insert_member = $conn->prepare("INSERT INTO job_assignments (job_id, staff_name, staff_role) VALUES (?, ?, ?)");
                foreach ($master_members as $member_name) {
                    $stmt_insert_member->execute([$job_id, $member_name, $staff_role]);
                }
            } else {
                $stmt = $conn->prepare("INSERT INTO job_assignments (job_id, staff_name, staff_role) VALUES (?, ?, ?)");
                $stmt->execute([$job_id, "รอระบุบุคคล", $staff_role]);
            }
        }

        $conn->commit();
        header("Location: manage_operation2.php?set_success=1&job_id=" . $job_id . "&modal=staff");
        exit();
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        die("เกิดข้อผิดพลาดในการจัดชุด: " . $e->getMessage());
    }
}

// --- 7. ส่วนเพิ่มบุคลากรเข้าชุด (ในไฟล์เดียวกัน) ---
if (isset($_POST['assign_staff_to_set'])) {
  try {
        $job_id = $_POST['job_id'] ?? '';
        $target_set = $_POST['target_set'] ?? '';
        $staff_name = trim($_POST['staff_name'] ?? '');
        
        if (!empty($target_set) && !empty($staff_name)) {
            $staff_role = $target_set; 
            
            $conn->prepare("DELETE FROM job_assignments WHERE job_id = ? AND staff_role = ? AND staff_name = 'รอระบุบุคคล'")
                 ->execute([$job_id, $staff_role]);
            
            $check = $conn->prepare("SELECT COUNT(*) FROM job_assignments WHERE job_id = ? AND staff_name = ? AND staff_role = ?");
            $check->execute([$job_id, $staff_name, $staff_role]);

            if ($check->fetchColumn() == 0) {
                $stmt = $conn->prepare("INSERT INTO job_assignments (job_id, staff_name, staff_role) VALUES (?, ?, ?)");
                $stmt->execute([$job_id, $staff_name, $staff_role]);
            }

            header("Location: manage_operation2.php?staff_success=1&job_id=" . $job_id . "&modal=staff");
            exit();
        }
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการเพิ่มบุคลากรเข้าชุด: " . $e->getMessage());
    }
}

// --- 8. ส่วนลบบุคลากรออกจากชุด ---
if (isset($_GET['remove_staff'])) {
    try {
        $job_id = $_GET['job_id'] ?? '';
        $staff_name = $_GET['staff_name'] ?? '';
        $staff_role = $_GET['staff_role'] ?? '';

        $stmt = $conn->prepare("DELETE FROM job_assignments WHERE job_id = ? AND staff_name = ? AND staff_role = ?");
        $stmt->execute([$job_id, $staff_name, $staff_role]);

        header("Location: manage_operation2.php?staff_del_success=1&job_id=" . $job_id . "&modal=staff");
        exit();
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการลบบุคลากร: " . $e->getMessage());
    }
}

// --- ดึงข้อมูลมาแสดง ---
try {
    if(isset($conn)) {
        // 1. ดึงข้อมูลงานทั้งหมด
        $docs = $conn->query("SELECT * FROM documents ORDER BY id DESC")->fetchAll();
        
        // 2. ดึงข้อมูลการจัดชุดทั้งหมด
        $all_assign_for_status = $conn->query("SELECT job_id, staff_name, staff_role FROM job_assignments")->fetchAll();
        $assignments_by_job = [];
        foreach($all_assign_for_status as $a) {
            $assignments_by_job[$a['job_id']][] = $a;
        }

        // 3. ดึงข้อมูล Master Sets
        $sql_master = "
            SELECT DISTINCT staff_role, staff_name 
            FROM job_assignments 
            WHERE staff_name != 'รอระบุบุคคล' 
            ORDER BY staff_role ASC, staff_name ASC
        ";
        $all_master_assign = $conn->query($sql_master)->fetchAll();
        $master_sets = []; 

        foreach($all_master_assign as $a) {
            $setName = $a['staff_role']; 
            if (!empty($setName)) {
                if (!isset($master_sets[$setName])) {
                    $master_sets[$setName] = [];
                }
                $master_sets[$setName][] = $a['staff_name'];
            }
        }
        uksort($master_sets, 'strnatcmp');
        $available_sets = array_keys($master_sets);

      // 4. ดึงรายชื่อเฉพาะผู้ประสานงานทั้งหมด
        $stmt_all_staff = $conn->prepare("
            SELECT id, fname, lname 
            FROM users 
            WHERE role = 'coordinator_operation' 
            ORDER BY fname ASC
        ");
        $stmt_all_staff->execute();
        $staff_list = $stmt_all_staff->fetchAll();

    } else {
        $docs = []; $assignments_by_job = []; $staff_list = []; $available_sets = []; $master_sets = [];
    }
} catch (PDOException $e) {
    die("เกิดข้อผิดพลาดในการดึงข้อมูลมาแสดง: " . $e->getMessage());
}

function getBadgeClass($type) {
    switch ($type) {
        case 'รับเสด็จฯ': case 'รับเสด็จ': return 'bg-warning text-dark';
        case 'ขอความอนุเคราะห์': return 'bg-info text-dark';
        default: return 'bg-primary text-white'; 
    }
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบรับข้อมูลงาน | Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        html, body { height: 100%; }
        body { display: flex; flex-direction: column; font-family: 'Prompt', sans-serif; background-color: #f8f9fa; }
        .content-wrapper { flex: 1 0 auto; }
        footer { flex-shrink: 0; }

        .page-header {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
            color: white; padding: 2.5rem 0; margin-bottom: -3rem;
            border-radius: 0 0 1.5rem 1.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .custom-card { 
            border: none; border-radius: 1.25rem; box-shadow: 0 0.5rem 1.5rem rgba(0,0,0,0.08); 
            background: #ffffff; margin-bottom: 2rem; overflow: hidden;
        }
        
        .form-control, .form-select { border-radius: 0.75rem; border: 1px solid #dee2e6; padding: 0.75rem 1rem; }
        .form-control:focus, .form-select:focus { border-color: #2a5298; box-shadow: 0 0 0 0.25rem rgba(42, 82, 152, 0.15); }
        
        .table-custom thead th { background-color: #f1f4f8; color: #495057; font-weight: 600; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; border-bottom: none; padding: 1rem; }
        .table-custom tbody td { padding: 1rem; vertical-align: middle; border-bottom: 1px solid #f1f4f8; }
        
        .badge-custom { padding: 0.5em 1em; border-radius: 2rem; font-weight: 500; letter-spacing: 0.3px; }
        
        .forward-box {
            background: #ffffff; border: 1px solid #e5e7eb; border-radius: 0.75rem;
            padding: 0.35rem 0.35rem 0.35rem 0.75rem; display: flex; align-items: center;
            gap: 0.5rem; width: 100%; margin: 0 auto;
        }

        .forward-select {
            border: none !important; box-shadow: none !important; padding: 0;
            cursor: pointer; font-weight: 500; font-size: 0.9rem; color: #495057;
            background-color: transparent; text-overflow: ellipsis; overflow: hidden; white-space: nowrap;
        }
        .forward-select:focus { outline: none; }

        .btn-submit-forward {
            border-radius: 0.5rem; padding: 0.4rem 0; font-size: 0.85rem; font-weight: 600;
            border: none; display: flex; align-items: center; justify-content: center;
            gap: 0.4rem; transition: all 0.2s; width: 125px; flex-shrink: 0; 
        }

        .btn-submit-forward.btn-active { background-color: #3b5998; color: #ffffff; }
        .btn-submit-forward.btn-active:hover { background-color: #2d4373; }
        .btn-submit-forward.btn-disabled { background-color: #9ca3af; color: #ffffff; cursor: not-allowed; }

        .btn-action {
            width: 35px; height: 35px; border-radius: 0.5rem; display: inline-flex;
            align-items: center; justify-content: center; transition: all 0.2s; border: none;
        }
        .btn-set { background-color: #e2e3e5; color: #383d41; border: 1px solid #d6d8db; }
        .btn-set:hover { background-color: #5c636a; color: #fff; border-color: #5c636a; }
        .btn-staff { background-color: #cfe2ff; color: #084298; border: 1px solid #b6d4fe; }
        .btn-staff:hover { background-color: #0d6efd; color: #fff; border-color: #0d6efd; }
        
        .modal-content { border-radius: 1.25rem; border: none; }
        select { text-align-last: center; text-align: center; }
        option { text-align: center; }
        
        .staff-list-item { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 0.5rem; margin-bottom: 0.5rem; padding: 0.5rem 1rem; display: flex; justify-content: space-between; align-items: center;}
        
        .forward-select.assigned-locked {
            background-color: #ffffff !important; 
            color: #198754 !important; 
            font-weight: 700;
            opacity: 1 !important; 
            appearance: none; 
            -webkit-appearance: none;
            -moz-appearance: none;
            cursor: default !important; 
            padding-left: 0.5rem;
        }
        
   </style>
</head>
<body>

<?php include 'menu.php'; ?>

<div class="content-wrapper">
    <div class="page-header text-center">
        <div class="container position-relative">
            <div class="row align-items-center">
                <div class="col-12 col-md-2 text-center text-md-start mb-3 mb-md-0">
                    <a href="home_head_operation.php" class="btn btn-outline-light rounded-pill d-inline-flex align-items-center" style="font-weight: 500; padding: 0.4rem 1.2rem; transition: all 0.2s;">
                        <i class="bi bi-arrow-left me-2"></i>ย้อนกลับ
                    </a>
                </div>
                <div class="col-12 col-md-8 text-center">
                    <h2 class="fw-bold mb-0"><i class="bi bi-inbox-fill me-2"></i>รับหนังสือและจัดการชุดปฏิบัติงาน</h2>
                </div>
                <div class="col-12 col-md-2"></div>
            </div>
            <br>
        </div>
    </div>
    
    <div class="container" style="position: relative;">
        <div class="row justify-content-center">
            <div class="col-lg-12">
                
                <?php if(isset($_GET['success'])): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close">
                    <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                    <div><h6 class="mb-0 fw-bold">เพิ่มข้อมูลสำเร็จ!</h6></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if(isset($_GET['set_success'])): ?>
                <div class="alert alert-primary border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close">
                    <i class="bi bi-diagram-3-fill fs-4 me-3 text-primary"></i>
                    <div><h6 class="mb-0 fw-bold">บันทึกชื่อชุดปฏิบัติงานสำเร็จ!</h6></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if(isset($_GET['staff_success'])): ?>
                <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close">
                    <i class="bi bi-person-check-fill fs-4 me-3 text-success"></i>
                    <div><h6 class="mb-0 fw-bold">เพิ่มบุคลากรเข้าชุดสำเร็จ!</h6></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <?php if(isset($_GET['staff_del_success'])): ?>
                <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close">
                    <i class="bi bi-person-x-fill fs-4 me-3 text-danger"></i>
                    <div><h6 class="mb-0 fw-bold">ลบบุคลากรออกจากชุดแล้ว!</h6></div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="card custom-card mb-5">
                    <div class="card-header bg-white border-bottom-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="bi bi-list-columns-reverse me-2 text-primary"></i>รายการงานหนังสือ (การประสานงาน)
                        </h5>
                    </div>
                    
                    <div class="card-body p-0">
                        <div class="table-responsive bg-white rounded-3 shadow-sm border" style="-webkit-overflow-scrolling: touch;">
                            <table class="table table-hover table-custom mb-0 align-middle" style="table-layout: fixed; width: 100%; min-width: 1050px;">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4" style="width: 10%;">วันที่</th> <th style="width: 10%;">รหัสงาน</th> <th style="width: 14%;">ประเภท</th>
                                        <th style="width: 20%;">ชื่องาน / รายละเอียด</th>
                                        <th class="text-center" style="width: 32%;">ดำเนินการมอบหมาย</th> 
                                        <th class="text-center pe-4" style="width: 14%;">จัดการชุด</th>                                      
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($docs)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center py-5 text-muted border-0">
                                                <i class="bi bi-folder-x fs-1 d-block mb-3 text-light"></i>
                                                <span class="fw-semibold">ยังไม่มีข้อมูลในระบบ</span>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    
                                    <?php foreach ($docs as $doc): 
                                        $has_sets = isset($assignments_by_job[$doc['id']]) && count($assignments_by_job[$doc['id']]) > 0;
                                        $is_acknowledged = isset($doc['is_acknowledged']) && $doc['is_acknowledged'] == 1 ? true : false;
                                    ?>
                                    <tr>
                                        <td class="ps-4">
                                            <div class="d-flex flex-column">
                                                <span class="fw-bold text-dark" style="font-size: 0.9rem;">
                                                    <i class="bi bi-calendar3 me-1 text-secondary"></i>
                                                    <?php echo date('d/m/Y', strtotime($doc['received_date'])); ?>
                                                </span>
                                                <small class="text-muted">
                                                    <i class="bi bi-clock me-1"></i>
                                                    <?php echo date('H:i', strtotime($doc['received_date'])); ?> น.
                                                </small>
                                            </div>
                                        </td>
										
<td class="text-primary" style="max-width: 200px;">
    <div class="fw-bold">
        <div class="text-content text-truncate" style="max-width: 180px;">
            <?php echo htmlspecialchars($doc['doc_number'] ?: '-'); ?>
        </div>
        
        <?php if ($doc['doc_number'] && $doc['doc_number'] !== '-'): ?>
        <div class="mt-1 btn-toggle-wrapper d-none"> <!-- ซ่อนไว้ก่อน -->
            <small class="text-muted" 
                   style="cursor: pointer; text-decoration: underline; font-size: 0.75rem;" 
                   onclick="toggleText(this)">
                อ่านต่อ
            </small>
        </div>
        <?php endif; ?>
    </div>
</td>
<script>
// ฟังก์ชันเช็คว่าข้อความล้นหรือไม่
function checkEllipsis() {
    const contents = document.querySelectorAll('.text-content');
    
    contents.forEach(content => {
        // ถ้าความยาวเนื้อหาข้างใน > ความยาวที่มองเห็น
        if (content.scrollWidth > content.clientWidth) {
            // หา wrapper ของปุ่มที่อยู่ถัดไป แล้วสั่งให้แสดง (เอา d-none ออก)
            const wrapper = content.nextElementSibling;
            if (wrapper && wrapper.classList.contains('btn-toggle-wrapper')) {
                wrapper.classList.remove('d-none');
            }
        }
    });
}

// เรียกทำงานทันทีที่โหลดหน้าเสร็จ
window.addEventListener('load', checkEllipsis);
// (Option) เรียกทำงานตอนเปลี่ยนขนาดหน้าจอ เผื่อจอยืดเข้ายืดออก
window.addEventListener('resize', checkEllipsis);

function toggleText(btn) {
    const container = btn.closest('.fw-bold');
    const content = container.querySelector('.text-content');
    
    if (content.classList.contains('text-truncate')) {
        content.classList.remove('text-truncate');
        content.style.maxWidth = 'none';
        content.style.whiteSpace = 'normal';
        content.style.wordBreak = 'break-all';
        btn.innerText = 'ย่อลง';
    } else {
        content.classList.add('text-truncate');
        content.style.maxWidth = '180px';
        content.style.whiteSpace = 'nowrap';
        btn.innerText = 'อ่านต่อ';
    }
}
</script>
                                        
                                        <td style="word-break: break-all;">
                                            <?php 
                                                $job_type_other_val = $doc['job_type_other'] ?? '';
                                                $job_type_text = ($doc['job_type'] === 'อื่นๆ' && !empty($job_type_other_val)) ? "อื่นๆ: " . $job_type_other_val : $doc['job_type'];
                                                $badge_class = getBadgeClass($doc['job_type']);
                                                echo '<span class="badge badge-custom ' . $badge_class . '" style="white-space: normal; text-align: left; display: inline-block; max-width: 100%;">';
                                                echo htmlspecialchars($job_type_text);
                                                echo '</span>';
                                            ?>
                                        </td>
                                        
  <td style="word-break: break-word; min-width: 250px;">
    <?php 
        $doc_name = htmlspecialchars($doc['doc_name']);
        $max_len = 50; 
        $unique_id = uniqid('doc_'); 
    ?>

    <div class="text-dark fw-medium">
        <?php if (mb_strlen($doc_name, 'UTF-8') > $max_len): ?>
            
            <!-- ส่วนที่ 1: ข้อความแบบย่อ -->
            <span id="short_<?php echo $unique_id; ?>">
                <?php echo mb_substr($doc_name, 0, $max_len, 'UTF-8') . '...'; ?>&nbsp;<a href="javascript:void(0)" class="text-primary fw-normal" style="font-size: 0.8rem; text-decoration: underline; white-space: nowrap;" onclick="document.getElementById('short_<?php echo $unique_id; ?>').style.display='none'; document.getElementById('full_<?php echo $unique_id; ?>').style.display='inline';">อ่านต่อ</a>
            </span>
            
            <!-- ส่วนที่ 2: ข้อความแบบเต็ม -->
            <span id="full_<?php echo $unique_id; ?>" style="display: none;">
                <?php echo $doc_name; ?>&nbsp;<a href="javascript:void(0)" class="text-muted fw-normal" style="font-size: 0.8rem; text-decoration: underline; white-space: nowrap;" onclick="document.getElementById('full_<?php echo $unique_id; ?>').style.display='none'; document.getElementById('short_<?php echo $unique_id; ?>').style.display='inline';">ย่อลง</a>
            </span>

        <?php else: ?>
            <?php echo $doc_name; ?>
        <?php endif; ?>
    </div>

    <!-- ส่วนของ Badge: ใช้ d-flex เพื่อให้เว้นระยะห่างจากข้อความข้างบนได้สวยขึ้น -->
    <?php if(!empty($doc['sent_to'])): ?>
        <div class="d-block mt-2">
            <?php if($is_acknowledged): ?>
                <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle">
                    <i class="bi bi-check-circle-fill me-1"></i>ผู้ประสานงานรับทราบแล้ว
                </span>
            <?php else: ?>
                <span class="badge rounded-pill bg-warning-subtle text-dark border border-warning-subtle">
                    <i class="bi bi-clock-history me-1"></i>รอผู้ประสานงานรับทราบ
                </span>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</td>
                                        
                                        <td class="text-center align-middle">
                                            <form method="post" class="forward-box">
                                                <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
<select name="send_to" 
        class="form-select flex-grow-1 forward-select <?php echo !empty($doc['sent_to']) ? 'assigned-locked' : ''; ?>" 
        required 
        data-original-val="<?php echo htmlspecialchars($doc['sent_to'] ?? ''); ?>"
        onchange="checkStaffStatus(this); this.blur();" 
        <?php echo $is_acknowledged ? 'disabled' : ''; ?>>
        
    <option value="" disabled <?php echo empty($doc['sent_to']) ? 'selected' : ''; ?>>-- เลือกเจ้าหน้าที่ --</option>
    
    <?php 
    $busy_staff_ids = array_filter(array_column($docs, 'sent_to')); 

    foreach($staff_list as $s): 
        $is_current_owner = ((string)($doc['sent_to'] ?? '') === (string)$s['id']);
        $is_already_assigned = in_array($s['id'], $busy_staff_ids);
        $should_be_disabled = $is_already_assigned && !$is_current_owner;
        $name = htmlspecialchars($s['fname'] . " " . $s['lname']);
    ?>
   <option value="<?php echo $s['id']; ?>" 
        class="<?php echo $is_current_owner ? 'text-success fw-bold' : ($should_be_disabled ? 'text-muted' : 'text-dark'); ?>"  
        data-name="<?php echo $name; ?>"
        <?php echo $is_current_owner ? 'selected' : ''; ?>
        <?php echo $should_be_disabled ? 'disabled' : ''; ?>>
    <?php 
        if ($is_current_owner) {
            echo "✓ " . $name; 
        } else {
            echo $name;
        }
    ?>
</option>
    <?php endforeach; ?>
</select>
<style>
/* ช่อง Select ปกติ */
.forward-select {
    transition: text-indent 0.1s ease, color 0.2s ease;
}

/* เมื่อเป็นสถานะ "มอบหมายแล้ว" (คนเดิมที่เลือกไว้) */
.forward-select.assigned-locked {
    /* ดันข้อความไปทางซ้ายประมาณ 18-20px เพื่อซ่อน '✓ ' ที่อยู่ข้างหน้า */
    text-indent: -18px; 
    /* เพิ่ม padding-left เพื่อให้ชื่อที่เหลืออยู่ขยับกลับมาตรงกลางสวยๆ */
    padding-left: 30px; 
}

/* บังคับให้ในรายการ Dropdown (ตอนกดเปิด) แสดงผลปกติ เห็นติ๊กถูกชัดเจน */
.forward-select option {
    text-indent: 0px;
    padding: 8px;
}
</style>
                                                <button type="submit" name="forward_doc" class="btn-submit-forward <?php echo empty($doc['sent_to']) ? 'btn-active' : 'btn-disabled'; ?>" <?php echo $is_acknowledged ? 'disabled' : ''; ?>>
                                                    <span class="btn-text"><?php echo empty($doc['sent_to']) ? 'มอบหมาย' : 'มอบหมายแล้ว'; ?></span>
                                                    <i class="<?php echo empty($doc['sent_to']) ? 'bi bi-send-fill' : 
													''
													//'bi bi-check-circle-fill'													
													; ?> small"></i>
                                                </button>
                                            </form>
                                        </td>
                                        
                                      <td class="text-center pe-4">
                                            <div class="d-flex flex-column align-items-center justify-content-center position-relative" style="height: 38px;">
                                                <?php if($has_sets): ?>
                                                  <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill position-absolute" style="margin-bottom:65px; font-size: 0.7rem; white-space: nowrap;">
                                                        <i class="bi bi-diagram-3-fill me-1"></i>จัดชุดแล้ว
                                                    </span> 
                                                <?php endif; ?>

                                                <div class="d-flex justify-content-center gap-2">
                                                    <!-- แก้ไข: ล็อกปุ่มหากยังไม่ได้รับทราบ -->
                                                    <?php if(!empty($doc['sent_to']) && !$is_acknowledged): ?>
                                                        <button type="button" class="btn-action btn-set" disabled title="ต้องรอผู้ประสานงานรับทราบเรื่องก่อน จึงจะจัดชุดได้" style="opacity: 0.5; cursor: not-allowed;">
                                                            <i class="bi bi-person-lines-fill"></i>
                                                        </button>
                                                    <?php else: ?>
                                                        <!-- ปุ่มปกติ ใช้งานได้เมื่อไม่มีคนรับผิดชอบ(เพื่อให้เห็นว่าต้องมอบหมายก่อน) หรือเมื่อรับทราบแล้ว -->
                                                        <button type="button" class="btn-action btn-staff" <?php echo empty($doc['sent_to']) ? 'disabled style="opacity:0.5;" title="กรุณามอบหมายผู้ประสานงานก่อน"' : 'data-bs-toggle="modal" data-bs-target="#manageStaffModal'.$doc['id'].'" title="จัดการชุด/คนเข้าชุด"'; ?>>
                                                            <i class="bi bi-person-lines-fill"></i>
                                                        </button>   
                                                    <?php endif; ?>
                                                </div>
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


<?php include 'footer.php'; ?>

<script>
function checkStaffStatus(selectElement) {
    const originalVal = selectElement.getAttribute('data-original-val');
    const currentVal = selectElement.value;
    const form = selectElement.closest('form');
    
    const submitBtn = form.querySelector('button[name="forward_doc"]');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnIcon = submitBtn.querySelector('i');

    if (originalVal && currentVal === originalVal) {
        // --- กรณีเลือกคนเดิม (มอบหมายแล้ว) ---
        selectElement.classList.add('assigned-locked'); // CSS จะซ่อนติ๊กถูกในช่องให้เอง
        selectElement.style.color = '#198754';
        
        submitBtn.disabled = true;
        submitBtn.className = 'btn-submit-forward btn-disabled';
        if (btnText) btnText.innerText = 'มอบหมายแล้ว';
        if (btnIcon) btnIcon.className = 'bi bi-check-circle-fill small';
        
    } else {
        // --- กรณีเปลี่ยนเป็นคนใหม่ (ปุ่มกลายเป็น 'มอบหมาย') ---
        selectElement.classList.remove('assigned-locked');
        selectElement.style.color = '#495057';
        
        submitBtn.disabled = false;
        submitBtn.className = 'btn-submit-forward btn-active';
        if (btnText) btnText.innerText = 'มอบหมาย';
        if (btnIcon) btnIcon.className = 'bi bi-send-fill small';
    }
}

function showCheckmark(selectElement) {
    const originalVal = selectElement.getAttribute('data-original-val');
    if (!originalVal) return;

    // 1. เติมติ๊กถูกให้ทุก Option ที่เป็นค่าดั้งเดิมก่อน (เพื่อให้ใน List กางออกมามีติ๊ก)
    Array.from(selectElement.options).forEach(opt => {
        const name = opt.getAttribute('data-name');
        if (opt.value === originalVal && name) {
            opt.text = '✓ ' + name;
        }
    });

    // 2. ใช้เทคนิค "คืนค่าชื่อ" เฉพาะตัวที่โชว์บนช่อง Display
    // เราจะใช้ setTimeout สั้นๆ เพื่อให้ Browser วาดลิสต์เสร็จก่อน แล้วค่อยแก้ข้อความตัวที่เลือกอยู่
    setTimeout(() => {
        const selectedOpt = selectElement.options[selectElement.selectedIndex];
        const name = selectedOpt.getAttribute('data-name');
        if (name) {
            // คืนค่าเป็นชื่อเพียวๆ (ทำให้ในช่อง Select ไม่มีติ๊กถูก)
            selectedOpt.text = name; 
        }
    }, 1); // รันแทบจะทันที
}

function hideCheckmark(selectElement) {
    // ล้างติ๊กถูกออกทั้งหมดเมื่อปิดหรือเลิกโฟกัส เพื่อให้ค่ากลับมาเป็นปกติ
    Array.from(selectElement.options).forEach(opt => {
        const name = opt.getAttribute('data-name');
        if (name) {
            opt.text = name;
        }
    });
}

function prepareCheckmark(selectElement) {
    const originalVal = selectElement.getAttribute('data-original-val');
    if (!originalVal) return;

    // 1. เติม ✓ ให้เฉพาะตัวที่ยังไม่มี เพื่อป้องกันการรันซ้ำ
    Array.from(selectElement.options).forEach(opt => {
        const name = opt.getAttribute('data-name');
        if (opt.value === originalVal && name && !opt.text.startsWith('✓')) {
            opt.text = '✓ ' + name;
        }
    });

    // 2. ไม่ต้องใช้ setTimeout ลบออกทันทีเหมือนเดิม 
    // แต่ปล่อยให้มันมีติ๊กถูกค้างไว้ "เฉพาะตอนที่ Dropdown กางอยู่" เท่านั้น
}

function finalizeSelection(selectElement) {
    // 3. ล้างติ๊กถูกออกทั้งหมด "เมื่อเลือกเสร็จแล้ว" (onchange) หรือ "เลิกโฟกัส" (onblur)
    // วิธีนี้หน้าจอจะไม่กระตุก เพราะเราเปลี่ยนข้อความตอนที่หน้าต่าง Dropdown ปิดไปแล้ว
    Array.from(selectElement.options).forEach(opt => {
        const name = opt.getAttribute('data-name');
        if (name && opt.text.startsWith('✓')) {
            opt.text = name;
        }
    });
}

document.addEventListener("DOMContentLoaded", function() {
    const alerts = document.querySelectorAll('.auto-close');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            const bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 3000);
    });

    const urlParams = new URLSearchParams(window.location.search);
    const reopenJobId = urlParams.get('job_id');
    const modalType = urlParams.get('modal');

    if (reopenJobId) {
        let modalId = '';
        if (modalType === 'staff' || modalType === 'set') { 
            modalId = 'manageStaffModal' + reopenJobId;
        }
        
        if (modalId) {
            const modalElement = document.getElementById(modalId);
            if (modalElement) {
                const myModal = new bootstrap.Modal(modalElement);
                myModal.show();
            }
        }
    }

    if (window.history.replaceState) {
        const url = new URL(window.location.href);
        ['success', 'edit_success', 'delete_success', 'cancel_success', 'set_success', 'staff_success', 'staff_del_success', 'forward_success', 'job_id', 'modal'].forEach(param => url.searchParams.delete(param));
        window.history.replaceState({}, '', url.href);
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
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
        $doc_number = $_POST['doc_number'];
        $job_type = $_POST['job_type'];
        if ($job_type === 'อื่นๆ' && !empty($_POST['job_type_other'])) {
            $job_type = $_POST['job_type_other'];
        }
        $doc_name = $_POST['doc_name'];
        $description = $_POST['description'];
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
        $id = $_POST['id'];
        $doc_number = $_POST['doc_number'];
        $job_type = $_POST['job_type'];
        if ($job_type === 'อื่นๆ' && !empty($_POST['job_type_other'])) {
            $job_type = $_POST['job_type_other'];
        }
        $doc_name = $_POST['doc_name'];
        $description = $_POST['description'];
        
        $sql = "UPDATE documents SET doc_number = ?, job_type = ?, doc_name = ?, description = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->execute([$doc_number, $job_type, $doc_name, $description, $id]); 
        
        header("Location: manage_operation2.php?edit_success=1");
        exit();
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการบันทึกข้อมูล (แก้ไขข้อมูล): " . $e->getMessage());
    }
}

// --- 4. จัดการการ "ดึงหนังสือกลับ" (Cancel Forward) ---
if (isset($_GET['cancel_forward_id'])) {
    try {
        $id = $_GET['cancel_forward_id'];
        $stmt = $conn->prepare("UPDATE documents SET sent_to = NULL WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: manage_operation2.php?cancel_success=1");
        exit();
    } catch (PDOException $e) {
         die("เกิดข้อผิดพลาดในการยกเลิกการส่งต่อ: " . $e->getMessage());
    }
}

// --- 5. จัดการการส่งต่อหนังสือ (Inline Forward) ---
if (isset($_POST['forward_doc'])) {
    try {
        $doc_id = $_POST['doc_id'];
        $send_to = $_POST['send_to'];
        
        if (!empty($send_to)) {
           $sql = "UPDATE documents SET sent_to = :staff_id WHERE id = :doc_id";
            $stmt = $conn->prepare($sql);
            $stmt->execute([':staff_id' => $send_to, ':doc_id' => $doc_id]);
            header("Location: manage_operation2.php?forward_success=1");
            exit();
        }
    } catch (PDOException $e) {
        die("เกิดข้อผิดพลาดในการส่งต่อหนังสือ: " . $e->getMessage());
    }
}

// --- 6. ส่วนบันทึกการ "จัดชุด" (จัดการแค่ชื่อชุดจาก Select Option) ---
if (isset($_POST['manage_sets'])) {
    try {
        $job_id = $_POST['job_id'];
        // รับค่าจาก Select Option เท่านั้น
        $set_target = trim($_POST['existing_set'] ?? '');

        if (empty($set_target)) throw new Exception("กรุณาระบุชื่อชุดปฏิบัติงาน");

        $conn->beginTransaction();
        
        $stmt_doc = $conn->prepare("SELECT doc_name, job_type FROM documents WHERE id = ?");
        $stmt_doc->execute([$job_id]);
        $document = $stmt_doc->fetch();
        
        $stmt_check_job = $conn->prepare("SELECT id FROM jobs WHERE id = ?");
        $stmt_check_job->execute([$job_id]);
        if (!$stmt_check_job->fetch()) {
            $job_type = !empty($document['job_type']) ? $document['job_type'] : 'อื่นๆ';
            $stmt_insert_job = $conn->prepare("INSERT INTO jobs (id, job_name, job_type) VALUES (?, ?, ?)");
            $stmt_insert_job->execute([$job_id, $document['doc_name'], $job_type]);
        }
        
        $staff_role = $set_target; 
        
        // เช็คว่าเคยเพิ่มชุดนี้ในงานนี้หรือยัง
        $stmt_check_exist = $conn->prepare("SELECT COUNT(*) FROM job_assignments WHERE job_id = ? AND staff_role = ?");
        $stmt_check_exist->execute([$job_id, $staff_role]);

        if ($stmt_check_exist->fetchColumn() == 0) {
            
            // 🌟 แก้ปัญหาที่นี่: ไปค้นหาว่า "ชุดนี้" เคยมีใครอยู่บ้างจากประวัติงานอื่นๆ
            $search_role = "%" . $staff_role . "%";
            $stmt_find_members = $conn->prepare("
                SELECT DISTINCT staff_name 
                FROM job_assignments 
                WHERE staff_role LIKE ? AND staff_name != 'รอระบุบุคคล'
            ");
            $stmt_find_members->execute([$search_role]);
            $master_members = $stmt_find_members->fetchAll(PDO::FETCH_COLUMN);

            if (count($master_members) > 0) {
                // ถ้าเจอว่าชุดนี้มีคนอยู่ ให้ดึงรายชื่อทั้งหมดมาผูกเข้ากับงานนี้อัตโนมัติเลย
                $stmt_insert_member = $conn->prepare("INSERT INTO job_assignments (job_id, staff_name, staff_role) VALUES (?, ?, ?)");
                foreach ($master_members as $member_name) {
                    $stmt_insert_member->execute([$job_id, $member_name, $staff_role]);
                }
            } else {
                // ถ้าเป็นชุดใหม่เอี่ยมที่ไม่เคยมีใครอยู่เลย ค่อยใส่ รอระบุบุคคล
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
        $job_id = $_POST['job_id'];
        $target_set = $_POST['target_set'];
        $staff_name = trim($_POST['staff_name']);
        
        if (!empty($target_set) && !empty($staff_name)) {
            
            $staff_role = $target_set; 
            
            // ลบแถว "รอระบุบุคคล" ที่เป็นตัวจองพื้นที่ของชุดนั้นๆ ออก
            $conn->prepare("DELETE FROM job_assignments WHERE job_id = ? AND staff_role = ? AND staff_name = 'รอระบุบุคคล'")
                 ->execute([$job_id, $staff_role]);
            
            // เช็คคนซ้ำในชุด
            $check = $conn->prepare("SELECT COUNT(*) FROM job_assignments WHERE job_id = ? AND staff_name = ? AND staff_role = ?");
            $check->execute([$job_id, $staff_name, $staff_role]);

            if ($check->fetchColumn() == 0) {
                // เพิ่มคนเข้าชุด
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
        $job_id = $_GET['job_id'];
        $staff_name = $_GET['staff_name'];
        $staff_role = $_GET['staff_role'];

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
        
        // 2. ดึงข้อมูลการจัดชุดทั้งหมดเพื่อใช้เช็คสถานะแต่ละงาน (สำหรับ Badge สีฟ้า "จัดชุดแล้ว")
        $all_assign_for_status = $conn->query("SELECT job_id, staff_name, staff_role FROM job_assignments")->fetchAll();
        $assignments_by_job = [];
        foreach($all_assign_for_status as $a) {
            $assignments_by_job[$a['job_id']][] = $a;
        }

        // 3. ดึงข้อมูล Master Sets (เฉพาะโครงสร้างจากงานล่าสุด เพื่อใช้พรีวิวใน Modal ให้เหมือนหน้า manage_team)
        $sql_master = "
            SELECT ja.staff_role, ja.staff_name 
            FROM job_assignments ja
            JOIN (
                SELECT staff_role, MAX(job_id) as max_job_id
                FROM job_assignments
                GROUP BY staff_role
            ) latest ON ja.staff_role = latest.staff_role AND ja.job_id = latest.max_job_id
            WHERE ja.staff_name != 'รอระบุบุคคล'
        ";
        $all_master_assign = $conn->query($sql_master)->fetchAll();
        $master_sets = []; 

        foreach($all_master_assign as $a) {
            $rawRole = $a['staff_role']; 
            $setName = $rawRole;
            
            // ตัดคำว่า "ผู้ประสานงาน (...)" ออกให้เหลือแค่ชื่อชุด
            if (preg_match('/ผู้ประสานงาน \((.*?)\)/u', $rawRole, $matches)) {
                $setName = $matches[1];
            }
            
            if (!empty($setName)) {
                if (!isset($master_sets[$setName])) {
                    $master_sets[$setName] = [];
                }
                // เก็บชื่อเข้า Array โดยตรง (เก็บชื่อซ้ำไว้ ไม่ต้องใช้ array_unique)
                $master_sets[$setName][] = $a['staff_name'];
            }
        }

        // เรียงลำดับชื่อชุดตามตัวอักษร
        uksort($master_sets, 'strnatcmp');
        $available_sets = array_keys($master_sets);

        // 4. ดึงรายชื่อพนักงานฝ่ายปฏิบัติการทั้งหมด (สำหรับ Dropdown)
        $stmt_all_staff = $conn->prepare("SELECT id, fname, lname FROM users WHERE role = 'staff_operation' ORDER BY fname ASC");
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
        
		/* ปุ่มย้อนกลับ */
		.btn-back-card {
            background-color: #ffffff;
            color: #495057;
            border: 1px solid #dee2e6;
            border-radius: 2rem;
            padding: 0.4rem 1.2rem;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.04);
            display: inline-flex;
            align-items: center;
        }
        .btn-back-card:hover {
            background-color: #f8f9fa;
            color: #2a5298;
            border-color: #2a5298;
            box-shadow: 0 0.25rem 0.5rem rgba(42, 82, 152, 0.1);
            transform: translateY(-1px);
        }
		
		
        .modal-content { border-radius: 1.25rem; border: none; }
        select { text-align-last: center; text-align: center; }
        option { text-align: center; }
        
        .staff-list-item { background: #f8f9fa; border: 1px solid #e9ecef; border-radius: 0.5rem; margin-bottom: 0.5rem; padding: 0.5rem 1rem; display: flex; justify-content: space-between; align-items: center;}
    </style>
</head>
<body>

<?php include 'menu.php'; ?>

<div class="content-wrapper">
    <div class="page-header text-center">
        <div class="container position-relative">
            <div class="row align-items-center">
                <div class="col-12 col-md-2 text-center text-md-start mb-3 mb-md-0">
                    <a href="javascript:history.back()" class="btn btn-outline-light rounded-pill d-inline-flex align-items-center" style="font-weight: 500; padding: 0.4rem 1.2rem; transition: all 0.2s;">
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
                                        
                                        <td class="text-primary" style="word-break: break-all;">
                                            <div class="fw-bold"><?php echo htmlspecialchars($doc['doc_number'] ?: '-'); ?></div>
                                        </td>
                                        
                                        <td style="word-break: break-all;">
                                            <?php 
                                                $job_type_text = ($doc['job_type'] === 'อื่นๆ' && !empty($doc['job_type_other'])) ? "อื่นๆ: " . $doc['job_type_other'] : $doc['job_type'];
                                                $badge_class = getBadgeClass($doc['job_type']);
                                                echo '<span class="badge badge-custom ' . $badge_class . '" style="white-space: normal; text-align: left; display: inline-block; max-width: 100%;">';
                                                echo htmlspecialchars($job_type_text);
                                                echo '</span>';
                                            ?>
                                        </td>
                                        
                                        <td style="word-break: break-all;">
                                            <div class="text-dark fw-medium text-truncate" style="max-width: 250px;" title="<?php echo htmlspecialchars($doc['doc_name']); ?>">
                                                <?php echo htmlspecialchars($doc['doc_name']); ?>
                                            </div>
                                        </td>
                                        
                                        <td class="text-center align-middle">
                                            <form method="post" class="forward-box">
                                                <input type="hidden" name="doc_id" value="<?php echo $doc['id']; ?>">
                                                <select name="send_to" class="form-select flex-grow-1 forward-select" required onchange="checkStaffStatus(this)" <?php echo $is_acknowledged ? 'disabled' : ''; ?>>
                                                    <option value="" disabled <?php echo empty($doc['sent_to']) ? 'selected' : ''; ?>>-- เลือกเจ้าหน้าที่ --</option>
                                                    <?php foreach($staff_list as $s): 
                                                        $already_sent = (($doc['sent_to'] ?? '') == $s['id']); 
                                                    ?>
                                                        <option value="<?php echo $s['id']; ?>" data-sent="<?php echo $already_sent ? 'true' : 'false'; ?>" class="<?php echo $already_sent ? 'text-success fw-bold' : 'text-dark'; ?>" <?php echo $already_sent ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($s['fname'] . " " . $s['lname']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <button type="submit" name="forward_doc" class="btn-submit-forward <?php echo empty($doc['sent_to']) ? 'btn-active' : 'btn-disabled'; ?>" <?php echo $is_acknowledged ? 'disabled' : ''; ?>>
                                                    <span class="btn-text"><?php echo empty($doc['sent_to']) ? 'มอบหมาย' : 'มอบหมายแล้ว'; ?></span>
                                                    <i class="<?php echo empty($doc['sent_to']) ? 'bi bi-send-fill' : 'bi bi-check-circle-fill'; ?> small"></i>
                                                </button>
                                            </form>
                                        </td>
                                        
                                      <td class="text-center pe-4">
                                            <div class="d-flex flex-column align-items-center justify-content-center position-relative" style="height: 38px;">
                                                <?php if($has_sets): ?>
                                                  <span style="margin-bottom:65px;" class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill position-absolute" style="font-size: 0.7rem; white-space: nowrap;">
                                                        <i class="bi bi-diagram-3-fill me-1"></i>จัดชุดแล้ว
                                                    </span> 
                                                <?php endif; ?>

                                                <div class="d-flex justify-content-center gap-2">
                                                    <button type="button" class="btn-action btn-staff" data-bs-toggle="modal" data-bs-target="#manageStaffModal<?php echo $doc['id']; ?>" title="จัดการชุด/คนเข้าชุด">
                                                        <i class="bi bi-person-lines-fill"></i>
                                                    </button>   
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

<?php foreach ($docs as $doc): 
    $has_sets = isset($assignments_by_job[$doc['id']]) && count($assignments_by_job[$doc['id']]) > 0;
    
    $assigned_set_name = "";
    if ($has_sets) {
        $rawRole = $assignments_by_job[$doc['id']][0]['staff_role'];
        $assigned_set_name = $rawRole;
        if (preg_match('/ผู้ประสานงาน \((.*?)\)/u', $rawRole, $matches)) {
            $assigned_set_name = $matches[1];
        }
    }

    $doc_assignments = $assignments_by_job[$doc['id']] ?? [];
    $sets = [];
    
    foreach ($doc_assignments as $row) {
        $fullNameRole = $row['staff_role'];
        $setName = $fullNameRole;
        if (preg_match('/ผู้ประสานงาน \((.*?)\)/u', $fullNameRole, $matches)) {
            $setName = $matches[1];
        }
        if (!isset($sets[$setName])) { $sets[$setName] = []; }
        $sets[$setName][] = $row['staff_name'];
    }
    
    foreach ($sets as $k => $v) {
        $sets[$k] = array_unique($v);
    }
    
    uksort($sets, 'strnatcmp');
?>
<div class="modal fade" id="manageStaffModal<?php echo $doc['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header border-bottom bg-primary text-white px-4 py-3">
                <div class="d-flex flex-column">
                    <h5 class="modal-title fw-bold"><i class="bi bi-diagram-3-fill me-2"></i>ระบุชุดปฏิบัติงาน</h5>
                    <small class="text-white-50 mt-1">อ้างอิง: <?php echo htmlspecialchars($doc['doc_name']); ?></small>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body p-4 bg-white">
                <?php if ($has_sets): ?>
                    <div class="text-center py-5">
                        <div class="mb-3">
                            <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-2">กำหนดชุดปฏิบัติงานแล้ว</h4>
                        <p class="text-muted mb-4">งานนี้ถูกมอบหมายให้กับชุดปฏิบัติงานด้านล่างเรียบร้อยแล้ว</p>
                        
                        <div class="d-inline-flex align-items-center bg-light border border-primary border-opacity-25 rounded-pill px-4 py-2 shadow-sm">
                            <i class="bi bi-collection-fill text-primary me-2 fs-5"></i>
                            <span class="fw-bold fs-5 text-primary"><?php echo htmlspecialchars($assigned_set_name); ?></span>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <div class="col-lg-5 border-end pe-lg-4">
                            <h6 class="fw-bold text-primary mb-3"><i class="bi bi-check2-square me-1"></i> เลือกชุดสำหรับงานนี้</h6>
                            
                            <form method="POST" action="manage_operation2.php" class="bg-light p-3 rounded-3 border">
                                <input type="hidden" name="manage_sets" value="1">
                                <input type="hidden" name="job_id" value="<?php echo htmlspecialchars($doc['id']); ?>">
                                
                                <div class="mb-3">
                                    <label class="form-label fw-bold small text-muted">เลือกชุดปฏิบัติงาน (เลือกจากรายการเท่านั้น):</label>
                                    <select name="existing_set" class="form-select border-primary" required>
                                        <option value="" selected disabled>-- กรุณาเลือกชุด --</option>
                                        <?php if(!empty($available_sets)): ?>
                                            <?php foreach ($available_sets as $setName): ?>
                                                <option value="<?php echo htmlspecialchars($setName); ?>"><?php echo htmlspecialchars($setName); ?></option>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <option value="" disabled>-- ไม่มีข้อมูลชุดในระบบ --</option>
                                        <?php endif; ?>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 fw-bold" <?php echo empty($available_sets) ? 'disabled' : ''; ?>>
                                    <i class="bi bi-save me-1"></i> บันทึกชุดปฏิบัติงาน
                                </button>
                            </form>
                        </div>
                        
                        <div class="col-lg-7 ps-lg-4">
                            <h6 class="fw-bold text-dark mb-3"><i class="bi bi-card-list me-1"></i> รายชื่อบุคลากรในชุด (ดูข้อมูล)</h6>
                            
                            <?php if(empty($master_sets)): ?>
                                <div class="text-center text-muted py-5 bg-light rounded-3">
                                    <i class="bi bi-inboxes fs-2 d-block mb-2 text-secondary"></i>
                                    <small>ยังไม่มีข้อมูลชุดปฏิบัติงานในระบบ</small>
                                </div>
                            <?php else: ?>
                                <div class="accordion" id="accordionStaff<?php echo $doc['id']; ?>">
                                    <?php $i=0; foreach($master_sets as $setName => $members): $i++; ?>
                                        <div class="accordion-item border-0 mb-2 shadow-sm rounded-3 overflow-hidden">
                                            <h2 class="accordion-header">
                                                <button class="accordion-button <?php echo $i>1 ? 'collapsed' : ''; ?> bg-white fw-bold text-primary" type="button" data-bs-toggle="collapse" data-bs-target="#collapseStaff_<?php echo $doc['id'].'_'.$i; ?>">
                                                    <i class="bi bi-collection me-2"></i> <?php echo htmlspecialchars($setName); ?> 
                                                    <span class="badge bg-secondary ms-2 rounded-pill"><?php echo count($members); ?> คน</span>
                                                </button>
                                            </h2>
                                            <div id="collapseStaff_<?php echo $doc['id'].'_'.$i; ?>" class="accordion-collapse collapse <?php echo $i==1 ? 'show' : ''; ?>" data-bs-parent="#accordionStaff<?php echo $doc['id']; ?>">
                                                <div class="accordion-body bg-light p-3">
                                                    <?php if(empty($members)): ?>
                                                        <div class="text-center text-muted small py-2">ยังไม่มีคนในชุดนี้</div>
                                                    <?php else: ?>
                                                        <?php foreach($members as $name): ?>
                                                            <div class="staff-list-item">
                                                                <span><i class="bi bi-person-fill text-secondary me-2"></i><?php echo htmlspecialchars($name); ?></span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endforeach; ?>

<?php include 'footer.php'; ?>

<script>
function checkStaffStatus(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const isSent = selectedOption.getAttribute('data-sent') === 'true';
    const form = selectElement.closest('form');
    const submitBtn = form.querySelector('button[name="forward_doc"]');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnIcon = submitBtn.querySelector('i');

    if (isSent) {
        selectElement.style.color = '#198754'; 
        selectElement.classList.add('fw-bold');
        submitBtn.disabled = true; 
        submitBtn.className = 'btn-submit-forward btn-disabled';
        btnText.innerText = 'มอบหมายแล้ว';
        btnIcon.className = 'bi bi-check-circle-fill small';
    } else {
        selectElement.style.color = '#495057'; 
        selectElement.classList.remove('fw-bold');
        submitBtn.disabled = false; 
        submitBtn.className = 'btn-submit-forward btn-active';
        btnText.innerText = 'มอบหมาย';
        btnIcon.className = 'bi bi-send-fill small';
    }
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

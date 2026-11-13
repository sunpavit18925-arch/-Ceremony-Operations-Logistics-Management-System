<?php
session_start();
require 'db.php';

// 1. Security Check: ถ้าไม่มี User ID ใน Session ให้ดีดไป Login
if ( !isset( $_SESSION[ 'user_id' ] ) ) {
    header( 'Location: login.php' );
    exit();
}

if(!isset($_SESSION['role']) || ($_SESSION['role'] !== 'staff_operation' && $_SESSION['role'] !== 'super_admin')) {
    header ("Location: home.php");
    exit();
}

// ตรวจสอบ Session Timeout (2 ชั่วโมง = 7200 วินาที)
$timeout_duration = 7200;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: index.php"); // หรือกลับไปหน้า login
    exit();
}
$_SESSION['last_activity'] = time(); // อัปเดตเวลาใช้งานล่าสุด

$my_id = $_SESSION[ 'user_id' ];
$success_msg = '';
$error_msg = '';

// ฟังก์ชันแปลงวันที่
function formatDateTH($date) {
    if(empty($date) || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') return '-';
    return date('d/m/Y', strtotime($date));
}

try {
    // เช็คข้อความสำเร็จจาก Session (ป้องกัน Form Resubmission)
    if (isset($_SESSION['success_status'])) {
        $success_msg = $_SESSION['success_status'];
        unset($_SESSION['success_status']);
    }

    // 2. อัปเดตข้อมูลผู้ใช้
    $stmt_user = $conn->prepare( 'SELECT fname, lname, role, email, username_sso FROM users WHERE id = ? LIMIT 1' );
    $stmt_user->execute( [ $my_id ] );
    $u_data = $stmt_user->fetch( PDO::FETCH_ASSOC );

    if ( !$u_data ) {
        session_destroy();
        header( 'Location: login.php?error=user_not_found' );
        exit();
    }

    $_SESSION[ 'user_fname' ] = $u_data[ 'fname' ];
    $_SESSION[ 'role' ]       = $u_data[ 'role' ];

    $user_name    = $u_data[ 'fname' ];
    $current_role = $u_data[ 'role' ];
    $username_sso = $u_data['username_sso'] ?? 'System';

    // ==========================================
    // 3. จัดการ ยืนยันรับทราบงาน (Acknowledge รายบุคคล)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge_doc'])) {
        $doc_id_to_ack = $_POST['acknowledge_doc_id'] ?? '';
        if(!empty($doc_id_to_ack)) {
            $stmt_ack = $conn->prepare("UPDATE document_assignments SET is_acknowledged = 1 WHERE doc_id = ? AND user_id = ?");
            $stmt_ack->execute([$doc_id_to_ack, $my_id]);
            $_SESSION['success_status'] = "ยืนยันรับทราบงานเรียบร้อยแล้ว";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // ==========================================
    // 4. จัดการบันทึกข้อมูล Town Planning Meetings & Meeting Reports (ยุบรวมกัน)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_meeting_details'])) {
        $document_id = $_POST['document_id']; 
        
        // ข้อมูลผังเมือง
        $meeting_type = $_POST['meeting_type'] ?? '';
        $meeting_code = $_POST['meeting_code'] ?? '';
        $meeting_name = $_POST['meeting_name'] ?? '';
        $meeting_no = $_POST['meeting_no'] ?? '';
        $meeting_year = $_POST['meeting_year'] ?? '';
        $meeting_date = !empty($_POST['meeting_date']) ? $_POST['meeting_date'] : null;
        $meeting_detail = $_POST['meeting_detail'] ?? ''; 
        $meeting_time = $_POST['meeting_time'] ?? '';
        $meeting_room = $_POST['meeting_room'] ?? '';
        $location = $_POST['location'] ?? '';
        $documents_link = $_POST['documents_link'] ?? '';

        // ข้อมูลรายงานการประชุม
        $doc_number = $_POST['doc_number'] ?? '';
        $doc_date = !empty($_POST['doc_date']) ? $_POST['doc_date'] : null;
        $report_meeting_date = !empty($_POST['report_meeting_date']) ? $_POST['report_meeting_date'] : null;
        $event_date = !empty($_POST['event_date']) ? $_POST['event_date'] : null;

        $check_stmt = $conn->prepare("SELECT id FROM town_planning_meetings WHERE document_id = ?");
        $check_stmt->execute([$document_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $error_msg = "เอกสารนี้มีการบันทึกรายละเอียดการประชุมไปแล้ว หากต้องการแก้ไขกรุณาไปที่เมนูแก้ไข";
        } else {
            $invitation_pdf = null;
            if (isset($_FILES['documents_file']) && $_FILES['documents_file']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/meetings/';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                $file_ext = strtolower(pathinfo($_FILES['documents_file']['name'], PATHINFO_EXTENSION));
                $new_filename = uniqid('meeting_') . '.' . $file_ext;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['documents_file']['tmp_name'], $upload_path)) {
                    $invitation_pdf = $new_filename;
                }
            }

           // บันทึกตารางผังเมือง
            $sql_insert = "INSERT INTO town_planning_meetings (
                            document_id, meeting_type, meeting_code, meeting_name, 
                            meeting_no, meeting_year, meeting_detail, meeting_date, meeting_time, 
                            meeting_room, location, documents_link, invitation_pdf, created_by
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($sql_insert);
            $stmt->execute([
                $document_id, $meeting_type, $meeting_code, $meeting_name, 
                $meeting_no, $meeting_year, $meeting_detail, $meeting_date, $meeting_time, 
                $meeting_room, $location, $documents_link, $invitation_pdf, $username_sso
            ]);

            // บันทึกตารางรายงานการประชุม (ใช้ document_id แทน job_id เพื่อการอ้างอิง)
            $stmt_report = $conn->prepare("INSERT INTO meeting_reports (job_id, doc_number, doc_date, meeting_date, event_date, created_by) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt_report->execute([$document_id, $doc_number, $doc_date, $report_meeting_date, $event_date, $username_sso]);

            $_SESSION['success_status'] = "บันทึกรายละเอียดการประชุมและรายงานเชื่อมโยงกับหนังสือสำเร็จ!";
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
    }

    // ==========================================
    // จัดการบันทึกข้อมูล จัดสถานที่ / ยืมครุภัณฑ์
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_equipment'])) {
        $job_id = $_POST['job_id'];
        $equipment_code = $_POST['equipment_code'];
        $quantity = $_POST['quantity'];

        $stmt = $conn->prepare("INSERT INTO equipment_borrowings (job_id, equipment_code, quantity, created_by) VALUES (?, ?, ?, ?)");
        $stmt->execute([$job_id, $equipment_code, $quantity, $username_sso]);
        
        $_SESSION['success_status'] = "บันทึกข้อมูลจัดสถานที่ / ยืมครุภัณฑ์สำเร็จ!";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    // รับค่าคำค้นหาจาก Form
    $search = $_GET['search'] ?? '';

    // ==========================================
    // 5. ดึงรายการหนังสือ + ข้อมูลจาก town_planning_meetings + meeting_reports
    // ==========================================
    $sql_docs = "SELECT d.*, u.fname as sender_fname, u.lname as sender_lname, u.role as sender_role,
                        tpm.id as tpm_id, tpm.meeting_type, tpm.meeting_code, tpm.meeting_name, 
                        tpm.meeting_no, tpm.meeting_year, tpm.meeting_date, tpm.meeting_time, 
                        tpm.meeting_room, tpm.location as tpm_location, tpm.documents_link, tpm.meeting_detail, tpm.invitation_pdf,
                        mr.doc_number as mr_doc_number, mr.doc_date as mr_doc_date, mr.meeting_date as mr_report_meeting_date, mr.event_date as mr_event_date,
                        da.is_acknowledged as my_ack_status
                 FROM documents d
                 JOIN document_assignments da ON d.id = da.doc_id
                 LEFT JOIN system_logs sl ON sl.id = (
                     SELECT MAX(id) FROM system_logs WHERE job_id = d.id AND action_type IN ('ASSIGN_STAFF', 'CHANGE_ASSIGN')
                 )
                 LEFT JOIN users u ON sl.performed_by = u.username_sso 
                 LEFT JOIN town_planning_meetings tpm ON tpm.document_id = d.id
                 LEFT JOIN meeting_reports mr ON mr.job_id = d.id
                 WHERE da.user_id = :my_id";

    // แก้ไข: แยกตัวแปรเป็น search1, search2, search3 เพื่อไม่ให้ PDO Error
    if (!empty($search)) {
        $sql_docs .= " AND (d.doc_name LIKE :search1 OR d.doc_number LIKE :search2 OR d.description LIKE :search3) ";
    }

    $sql_docs .= " ORDER BY d.received_date DESC";

    $stmt_docs = $conn->prepare( $sql_docs );
    $params = [ ':my_id' => $my_id ];
    
    // แก้ไข: กำหนดค่าให้ครบทั้ง 3 ตัวแปร
    if (!empty($search)) {
        $params[':search1'] = "%$search%";
        $params[':search2'] = "%$search%";
        $params[':search3'] = "%$search%";
    }
    $stmt_docs->execute( $params );
    $my_docs = $stmt_docs->fetchAll( PDO::FETCH_ASSOC );

    // ดึงข้อมูลครุภัณฑ์ที่ยืมของงานทั้งหมดที่เจอ เพื่อนำมาประมวลผลปุ่ม "ยืมครุภัณฑ์/ข้อมูลครุภัณฑ์"
    $doc_ids = array_column($my_docs, 'id');
    $equip_by_job = [];
    if (!empty($doc_ids)) {
        $in_clause = implode(',', array_fill(0, count($doc_ids), '?'));
        $stmt_equip = $conn->prepare("SELECT * FROM equipment_borrowings WHERE job_id IN ($in_clause)");
        $stmt_equip->execute($doc_ids);
        $equip_data = $stmt_equip->fetchAll(PDO::FETCH_ASSOC);
        foreach ($equip_data as $e) {
            $equip_by_job[$e['job_id']][] = $e;
        }
    }

    // --- ดึงข้อมูลอุปกรณ์ (Master Data) เพื่อนำไปแสดงใน Modal ---
    $stmt_equipments = $conn->query("SELECT * FROM equipments ORDER BY equipment_category ASC, equipment_name ASC");
    $equipments_list = $stmt_equipments->fetchAll(PDO::FETCH_ASSOC);

} catch ( PDOException $e ) {
    error_log( 'Database Error: ' . $e->getMessage() );
    $error_msg = 'ขออภัย ระบบขัดข้อง: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang='th'>
<head>
    <meta charset='UTF-8'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการงานมอบหมาย (ระบบปฏิบัติการ)</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css'>
    
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --bg-color: #f4f7fb;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
        }

        body {
            font-family: 'Prompt', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            -webkit-font-smoothing: antialiased;
        }

        .page-header { background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%); padding: 1.8rem 2rem; border-radius: 20px; border: 1px solid var(--border-color); box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03); margin-bottom: 2rem; margin-top: 1.5rem; }
        .user-profile-sm { display: flex; align-items: center; gap: 12px; text-align: right; }
        .avatar-circle { width: 45px; height: 45px; background: linear-gradient(135deg, #a855f7 0%, #3b82f6 100%); color: white; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 600; }
        .role-badge { font-size: 0.85rem; padding: 0.5rem 1.2rem; border-radius: 50rem; font-weight: 500; display: inline-flex; align-items: center; gap: 6px; background: <?php echo ($current_role == 'staff_operation' ? 'linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%)' : 'linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%)'); ?>; color: <?php echo ($current_role == 'staff_operation' ? '#166534' : '#0369a1'); ?>; }
        .card-custom { border: none; border-radius: 24px; background: var(--card-bg); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04); overflow: hidden; }
        .table-custom thead th { background-color: #f8fafc; color: var(--text-muted); font-weight: 600; font-size: 0.85rem; text-transform: uppercase; padding: 1.2rem 1.5rem; border-bottom: 2px solid var(--border-color); }
        .table-custom tbody tr { transition: all 0.2s ease-in-out; }
        .table-custom tbody tr:hover { background-color: #f8fafc; }
        .table-custom tbody td { padding: 1.2rem 1.5rem; vertical-align: middle; border-bottom: 1px solid #f1f5f9; }
        .doc-icon { width: 48px; height: 48px; background: #eef2ff; color: var(--primary-color); border-radius: 14px; display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; }
        .sender-badge { background-color: #f1f5f9; color: #475569; padding: 0.4rem 1rem; border-radius: 10px; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 8px; border: 1px solid #e2e8f0; }

        .btn-table-action { border: none; border-radius: 12px; padding: 0.5rem 0.8rem; font-size: 0.85rem; font-weight: 500; line-height: 1.3; transition: all 0.3s; text-align: center; min-width: 90px; }
        .btn-add-detail { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; box-shadow: 0 4px 10px rgba(17, 153, 142, 0.2); }
        .btn-add-detail:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(17, 153, 142, 0.4); color: white; }
        .btn-view-doc { background: #e0e7ff; color: #4f46e5; }
        .btn-view-doc:hover { background: #4f46e5; color: white; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(79, 70, 229, 0.2); }
        .btn-view-meeting { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.2); }
        .btn-view-meeting:hover { transform: translateY(-2px); box-shadow: 0 6px 15px rgba(245, 158, 11, 0.4); color: white; }
        
        .btn-equipment { background: linear-gradient(135deg, #0ea5e9 0%, #38bdf8 100%); color: white; }
        .btn-equipment:hover { transform: translateY(-2px); box-shadow: 0 4px 10px rgba(14, 165, 233, 0.3); color: white; }

        .form-control, .form-select { border-radius: 0.5rem; padding: 0.6rem 1rem; }
        .form-control:focus, .form-select:focus { border-color: #3b82f6; box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15); }
        .section-title { font-weight: 600; color: #1e293b; margin-bottom: 1rem; padding-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0; }

        .detail-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.2rem; height: 100%; transition: all 0.3s; }
        .detail-box:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.05); border-color: #cbd5e1; }
        .detail-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; margin-bottom: 1rem; }
        .detail-icon-type { background: #e0e7ff; color: #6366f1; }
        .detail-icon-code { background: #fef3c7; color: #f59e0b; }
        .detail-icon-location { background: #fee2e2; color: #ef4444; }
        .detail-icon-date { background: #dcfce7; color: #22c55e; }
        
        .detail-label { font-size: 0.85rem; color: #64748b; font-weight: 600; text-transform: uppercase; margin-bottom: 0.3rem; }
        .detail-value { font-size: 1rem; color: #1e293b; font-weight: 500; word-break: break-word; }

        .detail-header { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); padding: 2.5rem 2rem; color: white; position: relative; }
        .status-badge { background: rgba(255, 255, 255, 0.2); backdrop-filter: blur(4px); border: 1px solid rgba(255, 255, 255, 0.3); padding: 0.5rem 1.2rem; border-radius: 50rem; font-size: 0.9rem; }
    
        .padding {
            margin-top:-20px !important;
        }
    </style>
</head>

<body>
    <?php require 'menu.php'; ?>
    <div class="padding">
        <div class="content-wrapper">
            <div class="container mb-5">
                
                <div class='page-header'>
                    <div class='d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3'>
                        <div>
                        
                        <div class="mb-3">
                            <a href="home_operation.php" class="text-decoration-none d-inline-flex align-items-center gap-2 text-muted hover-primary transition-all">
                                <div style="width: 32px; height: 32px; background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; display: flex; align-items: center; justify-content: center; box-shadow: 0 2px 5px rgba(0,0,0,0.02);">
                                    <i class="bi bi-chevron-left"></i>
                                </div>
                                <span class="fw-medium">ย้อนกลับ</span>
                            </a>
                        </div>
                        
                            <h3 class='fw-bold mb-2'>ตรวจสอบงานที่ได้รับมอบหมายและการประชุม</h3>
                            <span class='role-badge'>
                                <i class='bi bi-shield-check'></i>
                                <?php
                                    if ( $current_role == 'coordinator_operation' ) echo 'ฝ่ายปฏิบัติการ';
                                    elseif ( $current_role == 'staff_vehicle' ) echo 'ฝ่ายยานพาหนะ';
                                    else echo 'ไม่ระบุฝ่าย (' . htmlspecialchars( $current_role ) . ')';
                                ?>
                            </span>
                        </div>
                        <div class='user-profile-sm'>
                            <div>
                                <div class='small text-muted mb-1'>เข้าสู่ระบบโดย</div>
                                <div class='fw-bold text-dark'><?php echo htmlspecialchars( $user_name ); ?></div>
                            </div>
                            <div class="avatar-circle">
                                <?php echo mb_substr(htmlspecialchars($user_name), 0, 1, "UTF-8"); ?>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if (!empty($success_msg)): ?>
                <div id="success-alert" class="alert alert-success fade show border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center">
                    <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                    <div>
                        <h6 class="mb-0 fw-bold">สำเร็จ!</h6>
                        <small><?php echo htmlspecialchars($success_msg); ?></small>
                    </div>
                    <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <?php if (!empty($error_msg)): ?>
                    <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                        <div>
                            <h6 class="mb-0 fw-bold">เกิดข้อผิดพลาด</h6>
                            <small><?php echo htmlspecialchars($error_msg); ?></small>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end mb-3 gap-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-list-task me-2" style="color: var(--primary-color);"></i>รายการงานของคุณ</h5>
                    
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-info fw-bold shadow-sm bg-white" data-bs-toggle="modal" data-bs-target="#equipmentModal">
                            <i class="bi bi-tools me-1"></i> รายการอุปกรณ์คงเหลือ
                        </button>
                    <form action="" method="GET" class="m-0">
                        <div class="input-group shadow-sm bg-white" style="border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color);">
                            <span class="input-group-text bg-transparent border-0 text-muted"><i class="bi bi-search"></i></span>
                            <input type="text" name="search" class="form-control border-0 shadow-none bg-transparent ps-0" placeholder="ค้นหาชื่องาน, รายละเอียด..." value="<?php echo htmlspecialchars($search); ?>" style="min-width: 250px;">
                            
                            <?php if(!empty($search)): ?>
                                <a href="<?php echo basename($_SERVER['PHP_SELF']); ?>" class="btn btn-link text-danger border-0 text-decoration-none d-flex align-items-center" title="ล้างการค้นหา"><i class="bi bi-x-circle-fill"></i></a>
                            <?php endif; ?>
                            
                            <button class="btn btn-primary border-0 px-4" type="submit" style="background: var(--primary-color);">
                                ค้นหา
                            </button>
                        </div>
                    </form>
                    </div>
                </div>

                <div class='card card-custom'>
                    <div class='card-body p-0'>
                        <div class='table-responsive'>
                            <table class='table table-custom align-middle'>
                                <thead>
                                    <tr>
                                        <th class='ps-4'>ข้อมูลหนังสือ / รายละเอียด</th>
                                        <th>ผู้ส่งมอบงาน</th>
                                        <th class='text-center'>วันที่ได้รับ</th>
                                        <th class='text-center' style="min-width: 300px;">การจัดการ</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ( empty( $my_docs ) ): ?>
                                    <tr>
                                        <td colspan='4' class='text-center p-5'>
                                            <i class="bi bi-inbox fs-1 text-muted opacity-50 mb-3 d-block"></i>
                                            <h5 class="fw-bold text-dark">
                                                <?php echo !empty($search) ? 'ไม่พบข้อมูลที่ค้นหา' : 'ยังไม่มีงานใหม่เข้ามาในขณะนี้'; ?>
                                            </h5>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ( $my_docs as $doc ): ?>
                                        <tr>
                                            <td class='ps-4'>
                                                <div class="d-flex align-items-center gap-3">
                                                    <div class="doc-icon"><i class="bi bi-file-earmark-text"></i></div>
                                                    <div>
                                                        <div class='fw-bold text-dark mb-1'>
                                                        <?php 
                                                            $doc_name = htmlspecialchars($doc['doc_name'] ?? '');
                                                            $max_len = 60; // จำนวนตัวอักษรที่จะให้ตัด
                                                            $unique_id = uniqid('doc_');
                                                            if (mb_strlen($doc_name, 'UTF-8') > $max_len): 
                                                        ?>
                                                            <span id="short_<?php echo $unique_id; ?>">
                                                                <?php echo mb_substr($doc_name, 0, $max_len, 'UTF-8') . '...'; ?>
                                                                <a href="javascript:void(0)" class="text-primary fw-normal ms-1" style="font-size: 0.8rem; text-decoration: underline; white-space: nowrap;" onclick="document.getElementById('short_<?php echo $unique_id; ?>').style.display='none'; document.getElementById('full_<?php echo $unique_id; ?>').style.display='inline';">อ่านต่อ</a>
                                                            </span>
                                                            <span id="full_<?php echo $unique_id; ?>" style="display: none;">
                                                                <?php echo $doc_name; ?>
                                                                <a href="javascript:void(0)" class="text-secondary fw-normal ms-1" style="font-size: 0.8rem; text-decoration: underline; white-space: nowrap;" onclick="document.getElementById('full_<?php echo $unique_id; ?>').style.display='none'; document.getElementById('short_<?php echo $unique_id; ?>').style.display='inline';">ย่อลง</a>
                                                            </span>
                                                        <?php else: ?>
                                                            <?php echo $doc_name; ?>
                                                        <?php endif; ?>
                                                            <?php if(isset($doc['my_ack_status']) && $doc['my_ack_status'] == 1): ?>
                                                                <span class="badge bg-success rounded-pill ms-2" style="font-size: 0.75rem;"><i class="bi bi-check-circle-fill"></i> รับทราบแล้ว</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-warning text-dark rounded-pill ms-2" style="font-size: 0.75rem;"><i class="bi bi-clock-fill"></i> รอรับทราบ</span>
                                                            <?php endif; ?>
                                                        </div>
                                                 <div class='small text-muted'><?php echo htmlspecialchars( $doc[ 'description' ] ?? 'ไม่มีรายละเอียดเพิ่มเติม' ); ?></div>   </div>
                                                </div>
                                            </td>
                                            <td class='text-nowrap'>
                                                <span class='sender-badge text-nowrap'>
                                                    <i class="bi bi-person-circle text-secondary"></i>
                                                    <?php echo !empty($doc['sender_fname']) ? htmlspecialchars($doc['sender_fname'].' '.$doc['sender_lname']) : 'ไม่ระบุชื่อ'; ?>
                                                </span>
                                            </td>
                                            <td class='text-center'>
                                                <div class="fw-medium text-dark"><?php echo date( 'd/m/Y', strtotime( $doc[ 'received_date' ] ) ); ?></div>
                                            </td>
                                            <td class='text-center'>
                                                <div class="d-flex flex-nowrap gap-2 justify-content-center align-items-center">
                                                    
                                                    <button type="button" class="btn-table-action btn-view-doc text-decoration-none text-nowrap"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#viewDocModal"
                                                        data-doc-id="<?php echo $doc['id']; ?>"
                                                        data-is-ack="<?php echo $doc['my_ack_status'] ?? 0; ?>"
                                                       data-doc-name="<?php echo htmlspecialchars($doc['doc_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-doc-date="<?php echo !empty($doc['received_date']) ? date('d/m/Y | H:i', strtotime($doc['received_date'])) : '-'; ?>"
                                                    data-doc-detail="<?php echo htmlspecialchars(str_replace(array("\r", "\n"), ' ', $doc['description'] ?? '-'), ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-sender-name="<?php echo htmlspecialchars(($doc['sender_fname'] ?? '') . ' ' . ($doc['sender_lname'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                                                    data-sender-role="<?php echo htmlspecialchars($doc['sender_role'] ?? 'ไม่ระบุ', ENT_QUOTES, 'UTF-8'); ?>"
                                                       onclick="openViewDocModal(this)">
                                                        <i class="bi bi-file-earmark-text me-1 fs-5"></i>
                                                        หนังสือ
                                                    </button>

                                                    <?php if(!empty($doc['tpm_id'])): ?>
                                                    <button type="button" class="btn-table-action btn-view-meeting text-decoration-none text-nowrap"
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#viewMeetingModal"
                                                        data-type="<?php echo htmlspecialchars($doc['meeting_type'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-code="<?php echo htmlspecialchars($doc['meeting_code'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-name="<?php echo htmlspecialchars($doc['meeting_name'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-no="<?php echo htmlspecialchars($doc['meeting_no'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-year="<?php echo htmlspecialchars($doc['meeting_year'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-date="<?php echo formatDateTH($doc['meeting_date']); ?>"
                                                        data-time="<?php echo htmlspecialchars($doc['meeting_time'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-room="<?php echo htmlspecialchars($doc['meeting_room'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-location="<?php echo htmlspecialchars($doc['tpm_location'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-link="<?php echo htmlspecialchars($doc['documents_link'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-detail="<?php echo htmlspecialchars($doc['meeting_detail'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-mr-doc-number="<?php echo htmlspecialchars($doc['mr_doc_number'] ?? '-', ENT_QUOTES, 'UTF-8'); ?>"
                                                        data-mr-doc-date="<?php echo formatDateTH($doc['mr_doc_date']); ?>"
                                                        data-mr-report-date="<?php echo formatDateTH($doc['mr_report_meeting_date']); ?>"
                                                        data-mr-event-date="<?php echo formatDateTH($doc['mr_event_date']); ?>"
                                                        onclick="openViewMeetingModal(this)">
                                                        <i class="bi bi-diagram-3 me-1 fs-5"></i>
                                                        ข้อมูลการประชุม
                                                    </button>
                                                    <?php elseif(isset($doc['my_ack_status']) && $doc['my_ack_status'] == 1): ?>
                                                    <button type="button" class="btn-table-action btn-add-detail text-nowrap" 
                                                        data-bs-toggle="modal"
                                                        data-bs-target="#addMeetingModal"
                                                        data-id="<?php echo $doc['id']; ?>"
                                                        data-name="<?php echo htmlspecialchars($doc['doc_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                        onclick="openAddMeetingModal(this)">
                                                        <i class="bi bi-plus-circle me-1 fs-5"></i>
                                                        รายงานการประชุม
                                                    </button>
                                                    <?php endif; ?>
                                                    
                                                    <?php 
                                                    $job_equips = $equip_by_job[$doc['id']] ?? [];
                                                    if(count($job_equips) > 0): 
                                                        $equip_json = htmlspecialchars(json_encode($job_equips), ENT_QUOTES, 'UTF-8');
                                                    ?>
                                                        <button type="button" class="btn-table-action text-nowrap text-white" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); box-shadow: 0 4px 10px rgba(99, 102, 241, 0.2);" 
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#viewEquipmentModal"
                                                            data-name="<?php echo htmlspecialchars($doc['doc_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            data-equip="<?php echo $equip_json; ?>"
                                                            onclick="openViewEquipmentModal(this)">
                                                            <i class="bi bi-boxes me-1 fs-5"></i>
                                                            ข้อมูลครุภัณฑ์
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="button" class="btn-table-action btn-equipment text-nowrap" 
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#addEquipmentModal"
                                                            data-id="<?php echo $doc['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($doc['doc_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            onclick="openAddEquipmentModal(this)">
                                                            <i class="bi bi-box-seam me-1 fs-5"></i>
                                                            ยืมครุภัณฑ์
                                                        </button>
                                                    <?php endif; ?>

                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div> 
        </div> 
    </div> <?php require 'footer.php'; ?>

    <div class="modal fade" id="addMeetingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form action="" method="POST" class="modal-content border-0 rounded-4 shadow" enctype="multipart/form-data">
                <div class="modal-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-diagram-3-fill me-2"></i> บันทึกข้อมูลการประชุม
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-success bg-opacity-10 border-0 rounded-3 mb-4">
                        <i class="bi bi-info-circle-fill me-2 text-success"></i> กำลังเพิ่มข้อมูลรายงานการประชุมสำหรับหนังสือ: <br>
                        <strong id="add_display_doc_name" class="ms-4 text-dark">-</strong>
                    </div>
                    <input type="hidden" name="document_id" id="modal_document_id" value="">
                    <input type="hidden" name="save_meeting_details" value="1">
                    
                    <h6 class="section-title"><i class="bi bi-info-square-fill me-2 text-primary"></i>ข้อมูลหลักการประชุม</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <label class="form-label fw-bold">ประเภทคณะกรรมการผังเมือง <span class="text-danger">*</span></label>
                            <select class="form-select" name="meeting_type" required>
                                <option value="" selected disabled>-- กรุณาเลือกประเภทคณะกรรมการ --</option>
                                <option value="คณะกรรมการที่ปรึกษาผังเมืองรวม ในเขตจังหวัด">คณะกรรมการที่ปรึกษาผังเมืองรวม ในเขตจังหวัด</option>
                                <option value="คณะกรรมการพิจารณาด้านการผังเมือง (กรมฯ)">คณะกรรมการพิจารณาด้านการผังเมือง ( กรมฯ )</option>
                                <option value="คณะอนุกรรมการผังเมือง เพื่อพิจารณาด้านการผังเมือง">คณะอนุกรรมการผังเมือง เพื่อพิจารณาด้านการผังเมือง</option>
                                <option value="คณะกรรมการผังเมือง">คณะกรรมการผังเมือง</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">รหัสการประชุม <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="meeting_code" placeholder="เช่น MP-2569-01" required>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">ชื่อการประชุม <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="meeting_name" placeholder="ระบุชื่อการประชุม" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ครั้งที่ <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" name="meeting_no" placeholder="เช่น 1" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">รายละเอียดการประชุม</label>
                            <textarea class="form-control" name="meeting_detail" rows="3" placeholder="ระบุรายละเอียดการประชุมเพิ่มเติม"></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ปีการประชุม <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="meeting_year" placeholder="เช่น 2569" required>
                        </div>
                    </div>

                    <div class="row g-3 mb-4 p-3 rounded-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                        <div class="col-12">
                            <h6 class="mb-2 fw-bold" style="color: #8b5cf6;"><i class="bi bi-journal-text me-2"></i>ข้อมูลรายงานการประชุม</h6>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ที่หนังสือ</label>
                            <input type="text" class="form-control" name="doc_number" placeholder="เช่น กก 0001/123">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">วันเดือนปี หนังสือ</label>
                            <input type="date" class="form-control" name="doc_date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ประชุม วันเดือนปี</label>
                            <input type="date" class="form-control" name="report_meeting_date">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">จัดงาน วัน เดือนปี</label>
                            <input type="date" class="form-control" name="event_date">
                        </div>
                    </div>

                    <h6 class="section-title"><i class="bi bi-geo-alt-fill me-2 text-primary"></i>วันเวลาและสถานที่</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">วัน เดือน ปี <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="meeting_date" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">เวลา</label>
                            <input type="time" class="form-control" name="meeting_time">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">ห้องประชุม</label>
                            <input type="text" class="form-control" name="meeting_room" placeholder="ระบุห้องประชุม">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">สถานที่ประชุม</label>
                            <input type="text" class="form-control" name="location" placeholder="ระบุสถานที่">
                        </div>
                    </div>
                    
                    <h6 class="section-title"><i class="bi bi-link-45deg me-2 text-primary"></i>เอกสารประกอบ</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">อัปโหลดไฟล์เอกสาร</label>
                            <input type="file" class="form-control" name="documents_file" accept=".pdf,.doc,.docx,.jpg,.png">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">หรือลิงก์ GDrive / อื่นๆ</label>
                            <input type="url" class="form-control" name="documents_link" placeholder="https://drive.google.com/...">
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0" style="border-bottom-left-radius: 1rem; border-bottom-right-radius: 1rem;">
                    <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4 rounded-pill shadow-sm fw-bold" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); border: none;">
                        <i class="bi bi-save me-2"></i> บันทึกรายละเอียด
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="addEquipmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <form action="" method="POST" class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header" style="background: linear-gradient(135deg, #0ea5e9 0%, #38bdf8 100%); color: white; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-box-seam me-2"></i> ข้อมูลจัดสถานที่ / ยืมครุภัณฑ์
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info bg-opacity-10 border-0 rounded-3 mb-4">
                        <strong>เอกสาร:</strong> <span id="equip_display_doc_name">-</span>
                    </div>
                    <input type="hidden" name="job_id" id="equip_job_id" value="">
                    <input type="hidden" name="save_equipment" value="1">

                    <div class="mb-3">
                        <label class="form-label">รหัสครุภัณฑ์ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="equipment_code" required placeholder="ระบุรหัสครุภัณฑ์">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">จำนวน <span class="text-danger">*</span></label>
                        <input type="number" class="form-control" name="quantity" required min="1" value="1">
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0" style="border-bottom-left-radius: 1rem; border-bottom-right-radius: 1rem;">
                    <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">ยกเลิก</button>
                    <button type="submit" class="btn btn-primary px-4 rounded-pill shadow-sm fw-bold" style="background: linear-gradient(135deg, #0ea5e9 0%, #38bdf8 100%); border: none;">
                        บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="modal fade" id="viewEquipmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header" style="background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%); color: white; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-boxes me-2"></i> ข้อมูลครุภัณฑ์ที่ยืม
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-info bg-opacity-10 border-0 rounded-3 mb-4">
                        <strong>เอกสาร:</strong> <span id="view_equip_doc_name">-</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="table-light">
                                <tr><th class="text-center" style="width: 20%;">ลำดับ</th><th style="width: 50%;">รหัสครุภัณฑ์</th><th class="text-center" style="width: 30%;">จำนวน</th></tr>
                            </thead>
                            <tbody id="view_equip_list"></tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0" style="border-bottom-left-radius: 1rem; border-bottom-right-radius: 1rem;">
                    <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewMeetingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-diagram-3-fill me-2"></i> ข้อมูลการประชุมคณะกรรมการผังเมือง
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <h5 class="fw-bold text-dark mb-1" id="view_meeting_name">-</h5>
                    <div class="text-muted mb-4 border-bottom pb-3">ประเภท: <span class="fw-medium text-primary" id="view_meeting_type">-</span></div>
                    
                    <h6 class="section-title"><i class="bi bi-info-square-fill me-2 text-primary"></i>ข้อมูลหลักการประชุม</h6>
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="detail-box">
                                <div class="detail-icon detail-icon-code"><i class="bi bi-upc-scan"></i></div>
                                <div class="detail-label">รหัสการประชุม</div>
                                <div class="detail-value fw-bold text-dark" id="view_meeting_code">-</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-box">
                                <div class="detail-icon detail-icon-type"><i class="bi bi-hash"></i></div>
                                <div class="detail-label">ครั้งที่ / ปีการประชุม</div>
                                <div class="detail-value" id="view_meeting_no_year">-</div>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="detail-box h-auto">
                                <div class="detail-label"><i class="bi bi-card-text me-1"></i> รายละเอียดเพิ่มเติม</div>
                                <div class="detail-value mt-2" id="view_meeting_detail" style="white-space: pre-line;">-</div>
                            </div>
                        </div>

                    <h6 class="section-title"><i class="bi bi-geo-alt-fill me-2 text-primary"></i>วันเวลาและสถานที่</h6>
                    <div class="row g-4 mb-4">
                        <div class="col-md-6">
                            <div class="detail-box">
                                <div class="detail-icon detail-icon-date"><i class="bi bi-calendar-event-fill"></i></div>
                                <div class="detail-label">วันเวลาที่ประชุม</div>
                                <div class="detail-value" id="view_meeting_datetime">-</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="detail-box">
                                <div class="detail-icon detail-icon-location"><i class="bi bi-geo-alt-fill"></i></div>
                                <div class="detail-label">สถานที่ / ห้องประชุม</div>
                                <div class="detail-value" id="view_meeting_location">-</div>
                            </div>
                        </div>
                    </div>

                    <h6 class="section-title"><i class="bi bi-journal-text me-2 text-primary"></i>ข้อมูลรายงานการประชุม</h6>
                    <div class="row g-3 mb-4 p-3 rounded-3" style="background-color: #f8fafc; border: 1px solid #e2e8f0;">
                        <div class="col-md-6 mb-2">
                            <div class="detail-label">ที่หนังสือ</div>
                            <div class="detail-value fw-medium" id="view_mr_doc_number">-</div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="detail-label">วันเดือนปี หนังสือ</div>
                            <div class="detail-value fw-medium" id="view_mr_doc_date">-</div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="detail-label">ประชุม วันเดือนปี</div>
                            <div class="detail-value fw-medium" id="view_mr_report_meeting_date">-</div>
                        </div>
                        <div class="col-md-6 mb-2">
                            <div class="detail-label">จัดงาน วัน เดือนปี</div>
                            <div class="detail-value fw-medium" id="view_mr_event_date">-</div>
                        </div>
                    </div>

                    <h6 class="section-title mt-2"><i class="bi bi-folder-fill me-2 text-warning"></i>เอกสารอ้างอิง</h6>
                    <div class="p-3 border rounded-3 bg-light">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="detail-label mb-2">ไฟล์เอกสารแนบ</div>
                                <span id="view_documents_file">-</span>
                            </div>
                            <div class="col-md-6">
                                <div class="detail-label mb-2">ลิงก์เอกสารประกอบการประชุม</div>
                                <a href="#" target="_blank" class="text-decoration-none fw-medium text-primary" id="view_documents_link" style="word-break: break-all;">-</a>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0" style="border-bottom-left-radius: 1rem; border-bottom-right-radius: 1rem;">
                    <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewDocModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header detail-header" style="border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                    <div class="w-100 d-md-flex justify-content-between align-items-center">
                        <div>
                            <span class="status-badge mb-3 d-inline-block">
                                <i class="bi bi-file-earmark-text me-1"></i> รายละเอียดงานเอกสาร
                            </span>
                            <h4 class="fw-bold mb-0 text-white" id="vd_doc_name">-</h4>
                        </div>
                        <div class="mt-3 mt-md-0 text-md-end text-white pe-4">
                            <div class="small opacity-75">วันที่ได้รับมอบหมาย</div>
                            <div class="fw-medium" id="vd_received_date">-</div>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white position-absolute top-0 end-0 m-3" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

              <div class="modal-body p-4 doc-body" style="background-color: #ffffff;">
                    <div class="row">
                        <div class="col-12" id="vd_alert_container"></div>
                        <div class="col-12">
                            <div class="text-muted" style="font-size: 0.85rem; text-transform: uppercase; font-weight: 600; margin-bottom: 0.5rem;">รายละเอียดงาน / คำสั่ง</div>
                            <div class="p-3 border rounded-3 bg-light min-vh-25" style="white-space: pre-line; font-size: 1.1rem; color: #1e293b; word-break: break-word; overflow-wrap: break-word;" id="vd_doc_detail">
                                -
                            </div>
                        </div>
                        <div class="col-md-6 mt-4">
                            <div class="h-100" style="background-color: #f8fafc; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                                <div class="text-primary" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase;">ผู้มอบหมายงาน</div>
                                <div class="d-flex align-items-center mt-2">
                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 45px; height: 45px;">
                                        <i class="bi bi-person-badge fs-5"></i>
                                    </div>
                                    <div>
                                        <div class="fw-bold" id="vd_sender_name">-</div>
                                        <div class="small text-muted" id="vd_sender_role">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 mt-4">
                            <div class="h-100" style="background-color: #f8fafc; border-radius: 12px; padding: 1.5rem; border: 1px solid #e2e8f0;">
                                <div class="text-primary" style="font-size: 0.85rem; font-weight: 600; margin-bottom: 0.5rem; text-transform: uppercase;">การจัดการ</div>
                                <div class="mt-2">
                                    <form method="POST" action="">
                                        <input type="hidden" name="acknowledge_doc_id" id="vd_ack_doc_id" value="">
                                        <button type="submit" name="acknowledge_doc" id="btn_acknowledge" class="btn btn-success w-100 mb-2 rounded-pill shadow-sm fw-bold">
                                            <i class="bi bi-check2-all me-1"></i> ยืนยันรับทราบงาน
                                        </button>
                                    </form>
                                    <button class="btn btn-outline-secondary w-100 rounded-pill" onclick="window.print()">
                                        <i class="bi bi-printer me-1"></i> พิมพ์เอกสาร
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'></script>
    <style>
    /* บังคับให้ Modal อยู่บนสุดเสมอ */
    .modal {
        z-index: 1060 !important;
    }
    .modal-backdrop {
        z-index: 1050 !important;
    }
    /* บังคับให้ Modal ลอยขึ้นมาอยู่บนสุดของหน้าจอเสมอ */
#viewDocModal {
    z-index: 9999 !important;
}
.modal-backdrop {
    z-index: 9990 !important;
}
</style>


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
                    <div id="print_date">-</div>
                </div>
            </div>
            <div style="display: flex; margin-bottom: 20px;">
                <div style="font-weight: bold; min-width: 60px;">เรื่อง</div>
                <div id="print_title">-</div>
            </div>
            
            <div style="display: flex; margin-bottom: 20px;">
                <div style="font-weight: bold; min-width: 60px;">เรียน</div>
                <div>เจ้าหน้าที่ผู้รับผิดชอบ / ชุดปฏิบัติงาน</div>
            </div>
            
            <div style="margin-bottom: 40px; text-indent: 40px; text-align: justify; min-height: 100px;" id="print_detail">
                -
            </div>
            
            <div style="display: flex; justify-content: flex-end; margin-top: 50px;">
                <div style="text-align: center; min-width: 250px;">
                    <div style="margin-bottom: 10px;">(ลงชื่อ)...........................................................</div>
                    <div style="margin-bottom: 5px;">(<span id="print_sender_name">-</span>)</div>
                    <div id="print_sender_role">-</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function openAddMeetingModal(btnElement) {
        document.getElementById('modal_document_id').value = btnElement.getAttribute('data-id');
        document.getElementById('add_display_doc_name').innerText = btnElement.getAttribute('data-name');
    }

    function openAddEquipmentModal(btnElement) {
        document.getElementById('equip_job_id').value = btnElement.getAttribute('data-id');
        document.getElementById('equip_display_doc_name').innerText = btnElement.getAttribute('data-name');
    }

    function openViewEquipmentModal(btnElement) {
        document.getElementById('view_equip_doc_name').innerText = btnElement.getAttribute('data-name');
        
        const equipList = JSON.parse(btnElement.getAttribute('data-equip'));
        const tbody = document.getElementById('view_equip_list');
        tbody.innerHTML = '';
        
        if(equipList.length > 0) {
            equipList.forEach((eq, index) => {
                tbody.innerHTML += `
                    <tr><td class="text-center">${index + 1}</td><td>${eq.equipment_code}</td><td class="text-center">${eq.quantity}</td></tr>
                `;
            });
        } else {
            tbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">ไม่พบข้อมูล</td></tr>';
        }
    }

    function openViewMeetingModal(btnElement, uploaded_file) {
        document.getElementById('view_meeting_type').innerText = btnElement.getAttribute('data-type');
        document.getElementById('view_meeting_code').innerText = btnElement.getAttribute('data-code');
        document.getElementById('view_meeting_name').innerText = btnElement.getAttribute('data-name');
        document.getElementById('view_meeting_no_year').innerText = "ครั้งที่ " + btnElement.getAttribute('data-no') + " / " + btnElement.getAttribute('data-year');
        
        let time = btnElement.getAttribute('data-time');
        let timeTxt = time && time !== '-' ? " เวลา " + time + " น." : "";
        document.getElementById('view_meeting_datetime').innerText = btnElement.getAttribute('data-date') + timeTxt;
        
        let room = btnElement.getAttribute('data-room');
        let location = btnElement.getAttribute('data-location');
        let locText = "";
        if(room && room !== '-') locText += "ห้อง: " + room + " ";
        if(location && location !== '-') locText += "(" + location + ")";
        document.getElementById('view_meeting_location').innerText = locText.trim() === "" ? "-" : locText;

        let detail = btnElement.getAttribute('data-detail');
        document.getElementById('view_meeting_detail').innerText = detail && detail !== '-' ? detail : "ไม่มีรายละเอียดเพิ่มเติม";
        
        let mr_doc_number = btnElement.getAttribute('data-mr-doc-number');
        let mr_doc_date = btnElement.getAttribute('data-mr-doc-date');
        let mr_report_date = btnElement.getAttribute('data-mr-report-date');
        let mr_event_date = btnElement.getAttribute('data-mr-event-date');
        
        document.getElementById('view_mr_doc_number').innerText = mr_doc_number && mr_doc_number !== '-' ? mr_doc_number : "-";
        document.getElementById('view_mr_doc_date').innerText = mr_doc_date && mr_doc_date !== '-' ? mr_doc_date : "-";
        document.getElementById('view_mr_report_meeting_date').innerText = mr_report_date && mr_report_date !== '-' ? mr_report_date : "-";
        document.getElementById('view_mr_event_date').innerText = mr_event_date && mr_event_date !== '-' ? mr_event_date : "-";

        let link = btnElement.getAttribute('data-link');
        let linkEl = document.getElementById('view_documents_link');
        if (link && link !== '-') {
            linkEl.innerText = link;
            linkEl.href = link;
        } else {
            linkEl.innerText = "ไม่มีลิงก์แนบ";
            linkEl.removeAttribute('href');
        }
    }
/**
 * ฟังก์ชันสำหรับเปิดและใส่ข้อมูลใน Modal ดูเอกสาร
 * @param {HTMLElement} btnElement - ปุ่มที่ถูกกด
 */
function openViewDocModal(btnElement) {
    try {
        // 1. ดึงค่าจาก Attribute ของปุ่ม
        const docId = btnElement.getAttribute('data-doc-id');
        const isAck = btnElement.getAttribute('data-is-ack');
        const docName = btnElement.getAttribute('data-doc-name') || '-';
        const docDate = btnElement.getAttribute('data-doc-date') || '-';
        const docDetail = btnElement.getAttribute('data-doc-detail') || '-';
        const senderName = btnElement.getAttribute('data-sender-name') || 'ไม่ระบุชื่อ';
        const senderRole = btnElement.getAttribute('data-sender-role') || '-';

        // 2. ตรวจสอบว่า ID ใน HTML มีอยู่จริงก่อนใช้งาน (สำคัญมาก)
        const elements = {
            'vd_doc_name': docName,
            'vd_received_date': docDate,
            'vd_doc_detail': docDetail,
            'vd_sender_name': senderName,
            'vd_sender_role': senderRole,
            'vd_ack_doc_id': docId,
            'print_title': docName,
            'print_date': docDate,
            'print_detail': docDetail,
            'print_sender_name': senderName || 'ไม่ระบุชื่อ',
            'print_sender_role': senderRole || '-'
        };

        // ใส่ค่าให้ Element ต่างๆ
        for (const [id, value] of Object.entries(elements)) {
            const el = document.getElementById(id);
            if (el) {
                if (el.tagName === 'INPUT') {
                    el.value = value;
                } else {
                    el.innerText = value;
                }
            } else {
                console.warn(`Element ID: ${id} ไม่พบในหน้านี้`);
            }
        }

        // 3. จัดการปุ่มและสถานะการรับทราบ
        const btnAck = document.getElementById('btn_acknowledge');
        const alertContainer = document.getElementById('vd_alert_container');

        if (btnAck && alertContainer) {
            if (isAck == '1') {
                btnAck.disabled = true;
                btnAck.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> รับทราบงานแล้ว';
                btnAck.className = 'btn btn-secondary w-100 mb-2 rounded-pill shadow-sm fw-bold';
                alertContainer.innerHTML = `
                    <div class="alert alert-success border-0 rounded-3 d-flex align-items-center mb-4" style="background-color: #dcfce7; color: #166534;">
                        <i class="bi bi-check-circle-fill fs-3 me-3 text-success"></i>
                        <div><strong class="d-block">สถานะ: รับทราบงานเรียบร้อยแล้ว</strong></div>
                    </div>`;
            } else {
                btnAck.disabled = false;
                btnAck.innerHTML = '<i class="bi bi-check2-all me-1"></i> ยืนยันรับทราบงาน';
                btnAck.className = 'btn btn-success w-100 mb-2 rounded-pill shadow-sm fw-bold';
                alertContainer.innerHTML = `
                    <div class="alert alert-warning border-0 rounded-3 d-flex align-items-center mb-4" style="background-color: #fef3c7; color: #92400e;">
                        <i class="bi bi-exclamation-triangle-fill fs-3 me-3 text-warning"></i>
                        <div><strong class="d-block">สถานะ: รอการยืนยันรับทราบ</strong></div>
                    </div>`;
            }
        }

        // 4. สั่งเปิด Modal (เพื่อความมั่นใจ)
        const myModal = new bootstrap.Modal(document.getElementById('viewDocModal'));
        myModal.show();

    } catch (error) {
        console.error("เกิดข้อผิดพลาดในการเปิด Modal:", error);
        alert("ไม่สามารถแสดงข้อมูลได้: " + error.message);
    }
}

    <?php if (!empty($error_msg) && isset($_POST['document_id'])): ?>
        document.addEventListener("DOMContentLoaded", function() {
            let fakeBtn = document.createElement('button');
            fakeBtn.setAttribute('data-id', '<?php echo $_POST['document_id']; ?>');
            fakeBtn.setAttribute('data-name', 'เกิดข้อผิดพลาด โปรดทำรายการใหม่อีกครั้ง');
            openAddMeetingModal(fakeBtn);
            // ใช้ getOrCreateInstance ป้องกันการบั๊กเวลาสั่งโชว์แบบออโต้เมื่อมี Error
            bootstrap.Modal.getOrCreateInstance(document.getElementById('addMeetingModal')).show();
        });
    <?php endif; ?>
    
    // โค้ดสำหรับซ่อน Alert ความสำเร็จเดิมของคุณ
    const successAlert = document.getElementById('success-alert');
    if (successAlert) {
        setTimeout(function() {
            const alert = new bootstrap.Alert(successAlert);
            alert.close();
        }, 2000);
    }
</script>
</body>
</html>
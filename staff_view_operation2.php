<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/equipment.php';
require_once __DIR__ . '/includes/storage.php';

require_role([ROLE_STAFF_OPERATION, ROLE_COORDINATOR_OPERATION, ROLE_SUPER_ADMIN]);

$conn = get_db();
$my_id = $_SESSION['user_id'] ?? 0;
$success_msg = '';
$error_msg = '';

// กำหนดค่าเริ่มต้นเพื่อป้องกันการแจ้งเตือนตัวแปร
$count_total = 0;
$count_pending = 0;
$count_acknowledged = 0;
$total_pages = 1;
$current_page = 1;
$my_docs = [];
$search = $_GET['search'] ?? '';

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
    $stmt_user = $conn->prepare('SELECT fname, lname, role, email, username_sso FROM users WHERE id = ? LIMIT 1');
    $stmt_user->execute([$my_id]);
    $u_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    // --- ส่วนตั้งค่า Pagination ---
    $items_per_page = 10;
    $current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($current_page < 1) $current_page = 1;
    $offset = ($current_page - 1) * $items_per_page;

    if (!$u_data) {
        session_destroy();
        header('Location: index.php?error=user_not_found');
        exit();
    }

    $_SESSION['user_fname'] = $u_data['fname'];
    $_SESSION['role']       = $u_data['role'];

    $user_name    = $u_data['fname'];
    $current_role = $u_data['role'];
    $username_sso = $u_data['username_sso'] ?? 'System';

    // ==========================================
    // 3. จัดการ ยืนยันรับทราบงาน (Acknowledge รายบุคคล)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledge_doc'])) {
        require_csrf();
        $doc_id_to_ack = clean_int($_POST['acknowledge_doc_id'] ?? 0);
        if ($doc_id_to_ack > 0) {
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
        require_csrf();
        $document_id = clean_int($_POST['document_id'] ?? 0);

        // IDOR Protection: Verify caller is assigned to this document or is super_admin
        if (!has_role([ROLE_SUPER_ADMIN])) {
            $stmtVerify = $conn->prepare("SELECT 1 FROM document_assignments WHERE doc_id = ? AND user_id = ? LIMIT 1");
            $stmtVerify->execute([$document_id, $my_id]);
            if (!$stmtVerify->fetchColumn()) {
                render_403_page("คุณไม่มีสิทธิ์บันทึกข้อมูลการประชุมสำหรับเอกสารนี้ เนื่องจากไม่ได้เป็นผู้ประสานงานที่ได้รับมอบหมาย", "staff_view_operation2.php");
            }
        }
        
        // ข้อมูลผังเมือง
        $meeting_type = clean_string($_POST['meeting_type'] ?? '');
        $meeting_code = clean_string($_POST['meeting_code'] ?? '');
        $meeting_name = clean_string($_POST['meeting_name'] ?? '');
        $meeting_no = clean_string($_POST['meeting_no'] ?? '');
        $meeting_year = clean_string($_POST['meeting_year'] ?? '');
        $meeting_date = !empty($_POST['meeting_date']) ? $_POST['meeting_date'] : null;
        $meeting_detail = clean_string($_POST['meeting_detail'] ?? ''); 
        $meeting_time = clean_string($_POST['meeting_time'] ?? '');
        $meeting_room = clean_string($_POST['meeting_room'] ?? '');
        $location = clean_string($_POST['location'] ?? '');
        $documents_link = clean_string($_POST['documents_link'] ?? '');

        // ข้อมูลรายงานการประชุม
        $doc_number = clean_string($_POST['doc_number'] ?? '');
        $doc_date = !empty($_POST['doc_date']) ? $_POST['doc_date'] : null;
        $report_meeting_date = !empty($_POST['report_meeting_date']) ? $_POST['report_meeting_date'] : null;
        $event_date = !empty($_POST['event_date']) ? $_POST['event_date'] : null;

        // เช็คว่าเคยบันทึกไปหรือยัง
        $check_stmt = $conn->prepare("SELECT id FROM town_planning_meetings WHERE document_id = ?");
        $check_stmt->execute([$document_id]);
        
        if ($check_stmt->rowCount() > 0) {
            $error_msg = "เอกสารนี้มีการบันทึกรายละเอียดการประชุมไปแล้ว หากต้องการแก้ไขกรุณาไปที่เมนูแก้ไข";
        } else {
            // จัดการอัปโหลดไฟล์
            $invitation_pdf = null;
            if (isset($_FILES['documents_file']) && $_FILES['documents_file']['error'] === UPLOAD_ERR_OK) {
                $upload_result = handle_pdf_upload($_FILES['documents_file'], __DIR__ . '/uploads/meetings');
                if (!$upload_result['success']) {
                    $error_msg = "เกิดข้อผิดพลาดในการอัปโหลดไฟล์: " . $upload_result['error'];
                } else {
                    $invitation_pdf = $upload_result['filename'];
                }
            }

            if (empty($error_msg)) {
                try {
                    $conn->beginTransaction();
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

                    $stmt_report = $conn->prepare("INSERT INTO meeting_reports (job_id, doc_number, doc_date, meeting_date, event_date, created_by) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt_report->execute([$document_id, $doc_number, $doc_date, $report_meeting_date, $event_date, $username_sso]);
                    $conn->commit();

                    $_SESSION['success_status'] = "บันทึกรายละเอียดการประชุมและรายงานเชื่อมโยงกับหนังสือสำเร็จ!";
                    header("Location: " . $_SERVER['PHP_SELF']);
                    exit();
                } catch (Exception $e) {
                    if ($conn->inTransaction()) {
                        $conn->rollBack();
                    }
                    error_log("Save meeting error: " . $e->getMessage());
                    $error_msg = "เกิดข้อผิดพลาดในการบันทึกข้อมูล";
                }
            }
        }
    }

    // ==========================================
    // 1. จัดการการยืมอุปกรณ์ (Borrow Equipment)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrow_equipment'])) {
        require_csrf();
        $job_id = clean_int($_POST['job_id'] ?? 0);
        $equipment_code = clean_string($_POST['equipment_code'] ?? '');
        $quantity = clean_int($_POST['quantity'] ?? 0);
        $page = clean_int($_GET['page'] ?? 1);

        if (!has_role([ROLE_SUPER_ADMIN])) {
            $stmtVerifyJob = $conn->prepare("SELECT 1 FROM document_assignments WHERE doc_id = ? AND user_id = ? LIMIT 1");
            $stmtVerifyJob->execute([$job_id, $my_id]);
            if (!$stmtVerifyJob->fetchColumn()) {
                render_403_page("คุณไม่มีสิทธิ์ยืมอุปกรณ์สำหรับภารกิจนี้ เนื่องจากไม่ได้เป็นผู้ได้รับมอบหมาย", "staff_view_operation2.php");
            }
        }

        $res = equipment_borrow($conn, $job_id, $equipment_code, $quantity, $username_sso ?? 'system');
        if ($res['success']) {
            header("Location: staff_view_operation2.php?page={$page}&borrow_success=1&open_modal={$job_id}");
        } else {
            header("Location: staff_view_operation2.php?page={$page}&borrow_error=" . urlencode($res['message']) . "&open_modal={$job_id}");
        }
        exit();
    }

    // ==========================================
    // 2. จัดการการคืนอุปกรณ์ (Return Equipment)
    // ==========================================
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return_item') {
        require_csrf();
        $borrow_id = clean_int($_POST['borrow_id'] ?? 0);
        $job_id = clean_int($_POST['job_id'] ?? 0);
        $page = clean_int($_GET['page'] ?? 1);

        if (!has_role([ROLE_SUPER_ADMIN])) {
            $stmtVerifyJob = $conn->prepare("SELECT 1 FROM document_assignments WHERE doc_id = ? AND user_id = ? LIMIT 1");
            $stmtVerifyJob->execute([$job_id, $my_id]);
            if (!$stmtVerifyJob->fetchColumn()) {
                render_403_page("คุณไม่มีสิทธิ์คืนอุปกรณ์สำหรับภารกิจนี้ เนื่องจากไม่ได้เป็นผู้ได้รับมอบหมาย", "staff_view_operation2.php");
            }
        }

        $res = equipment_return($conn, $borrow_id);
        if ($res['success']) {
            header("Location: staff_view_operation2.php?page={$page}&return_success=1&open_modal={$job_id}");
        } else {
            header("Location: staff_view_operation2.php?page={$page}&return_error=" . urlencode($res['message']) . "&open_modal={$job_id}");
        }
        exit();
    }

    // ==========================================
    // 5. ดึงรายการหนังสือ + ข้อมูลจาก town_planning_meetings + meeting_reports
    // ==========================================
    $sql_docs = "SELECT d.*, u.fname as sender_fname, u.lname as sender_lname, u.role as sender_role,
                        tpm.id as tpm_id, tpm.meeting_type, tpm.meeting_code, tpm.meeting_name, 
                        tpm.meeting_no, tpm.meeting_year, tpm.meeting_date, tpm.meeting_time, 
                        tpm.meeting_room, tpm.location as tpm_location, tpm.documents_link, tpm.meeting_detail, tpm.invitation_pdf,
                        mr.id as mr_id, mr.doc_number as mr_doc_number, mr.doc_date as mr_doc_date, mr.meeting_date as mr_report_meeting_date, mr.event_date as mr_event_date,
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
                 
    $params = [ ':my_id' => $my_id ];

    if (!empty($search)) {
        $sql_docs .= " AND (d.doc_name LIKE :search1 OR d.doc_number LIKE :search2 OR d.description LIKE :search3)";
        $params[':search1'] = "%$search%";
        $params[':search2'] = "%$search%";
        $params[':search3'] = "%$search%";
    }

    // นับจำนวนงานทั้งหมด
    $stmt_count = $conn->prepare("SELECT COUNT(*) FROM documents d JOIN document_assignments da ON d.id = da.doc_id WHERE da.user_id = :my_id" . (empty($search) ? "" : " AND (d.doc_name LIKE :search1 OR d.doc_number LIKE :search2 OR d.description LIKE :search3)"));
    $stmt_count->execute($params);
    $total_items = $stmt_count->fetchColumn();
    $total_pages = ceil($total_items / $items_per_page);

    // คำนวณจำนวนสรุปสถานะสำหรับส่วน Filter
    $count_total = $total_items;
    
    $stmt_all_stats = $conn->prepare("SELECT da.is_acknowledged FROM documents d JOIN document_assignments da ON d.id = da.doc_id WHERE da.user_id = :my_id" . (empty($search) ? "" : " AND (d.doc_name LIKE :search1 OR d.doc_number LIKE :search2 OR d.description LIKE :search3)"));
    $stmt_all_stats->execute($params);
    $all_stats = $stmt_all_stats->fetchAll(PDO::FETCH_COLUMN);
    foreach ($all_stats as $status) {
        if ($status == 1) $count_acknowledged++;
        else $count_pending++;
    }

    $sql_docs .= " ORDER BY d.received_date DESC LIMIT :limit OFFSET :offset";

    $stmt_docs = $conn->prepare($sql_docs);
    foreach ($params as $key => &$val) {
        $stmt_docs->bindParam($key, $val);
    }
    $stmt_docs->bindValue(':limit', $items_per_page, PDO::PARAM_INT);
    $stmt_docs->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_docs->execute();
    $my_docs = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

    // --- ดึงข้อมูลอุปกรณ์ (Master Data) เพื่อนำไปแสดงใน Modal ---
    $stmt_equipments = $conn->query("SELECT * FROM equipments ORDER BY equipment_category ASC, equipment_name ASC");
    $equipments_list = $stmt_equipments->fetchAll(PDO::FETCH_ASSOC);

    // --- ดึงข้อมูลการยืมที่มีอยู่แล้วทั้งหมด ---
    $stmt_borrowings = $conn->query("
        SELECT eb.*, e.equipment_name, e.unit 
        FROM equipment_borrowings eb
        JOIN equipments e ON eb.equipment_code = e.equipment_code
        ORDER BY eb.created_at DESC
    ");
    $borrowings_by_job = [];
    foreach ($stmt_borrowings->fetchAll(PDO::FETCH_ASSOC) as $b) {
        $borrowings_by_job[$b['job_id']][] = $b;
    }

} catch (PDOException $e) {
    error_log('Database Error: ' . $e->getMessage());
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
    
        .padding { margin-top:-20px !important; }

        .filter-card { transition: all 0.3s ease; border-radius: 1.25rem !important; border: none !important; }
        .filter-card:hover { transform: translateY(-5px); box-shadow: 0 10px 20px rgba(0,0,0,0.08) !important; }
        .filter-card.active { 
            background-color: #ffffff !important; 
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important; 
            outline: 2px solid var(--primary-color);
        }

        @media print {
            body * { visibility: hidden; }
            #printDocument, #printDocument * { visibility: visible; }
            #printDocument { display: block !important; position: absolute; left: 0; top: 0; width: 100%; margin: 0; padding: 0; }
            .modal, .modal-backdrop { display: none !important; }
            @page { margin: 1.5cm; size: A4; }
        }
        
        @media (max-width: 767.98px) {
            .user-profile-sm { justify-content: flex-start; text-align: left; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(0,0,0,0.05); }
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
                                <div class='fw-bold text-dark'><?php echo htmlspecialchars($user_name); ?></div>
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

                <?php if(isset($_GET['borrow_success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4 mb-4 d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                        <div><h6 class="mb-0 fw-bold">สำเร็จ!</h6><small>บันทึกการยืมอุปกรณ์เรียบร้อยแล้ว</small></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if(isset($_GET['return_success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4 mb-4 d-flex align-items-center" role="alert">
                        <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                        <div><h6 class="mb-0 fw-bold">สำเร็จ!</h6><small>บันทึกการคืนอุปกรณ์เรียบร้อยแล้ว</small></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                <?php if(isset($_GET['borrow_error']) || isset($_GET['return_error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-4 mb-4 d-flex align-items-center" role="alert">
                        <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                        <div><h6 class="mb-0 fw-bold">เกิดข้อผิดพลาด!</h6><small><?php echo htmlspecialchars($_GET['borrow_error'] ?? $_GET['return_error']); ?></small></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>

                <div class="row g-3 mb-4">
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100 filter-card active" onclick="filterByStatus('all', this)" style="cursor: pointer; border-left: 5px solid #6366f1 !important;">
                            <div class="card-body p-3 d-flex align-items-center">
                                <div class="rounded-circle bg-primary bg-opacity-10 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-collection-fill text-primary fs-4"></i>
                                </div>
                                <div>
                                    <div class="text-muted small fw-bold text-uppercase">งานทั้งหมด</div>
                                    <div class="h4 mb-0 fw-bold text-dark"><?php echo $count_total; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100 filter-card" onclick="filterByStatus('pending', this)" style="cursor: pointer; border-left: 5px solid #f59e0b !important;">
                            <div class="card-body p-3 d-flex align-items-center">
                                <div class="rounded-circle bg-warning bg-opacity-10 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-hourglass-split text-warning fs-4"></i>
                                </div>
                                <div>
                                    <div class="text-warning small fw-bold text-uppercase">รอรับทราบ</div>
                                    <div class="h4 mb-0 fw-bold text-dark"><?php echo $count_pending; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100 filter-card" onclick="filterByStatus('acknowledged', this)" style="cursor: pointer; border-left: 5px solid #10b981 !important;">
                            <div class="card-body p-3 d-flex align-items-center">
                                <div class="rounded-circle bg-success bg-opacity-10 p-3 me-3 d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                    <i class="bi bi-check-circle-fill text-success fs-4"></i>
                                </div>
                                <div>
                                    <div class="text-success small fw-bold text-uppercase">รับทราบแล้ว</div>
                                    <div class="h4 mb-0 fw-bold text-dark"><?php echo $count_acknowledged; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mb-3 d-flex flex-wrap align-items-center gap-2">
                    <div class="input-group shadow-sm" style="max-width: 420px;">
                        <span class="input-group-text bg-white border-end-0" style="border-radius: 14px 0 0 14px; border-color: #e2e8f0;">
                            <i class="bi bi-search text-muted"></i>
                        </span>
                        <input type="text" id="tableSearch" class="form-control border-start-0 ps-0" placeholder="ค้นหาชื่อหนังสือ, รายละเอียด, ผู้ส่งมอบ..." style="border-radius: 0 14px 14px 0; border-color: #e2e8f0; font-size: 0.95rem;">
                    </div>
                    <span class="text-muted small" id="searchResultCount"></span>
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
                                    <?php if (empty($my_docs)): ?>
                                    <tr>
                                        <td colspan='4' class='text-center p-5'>
                                            <i class="bi bi-inbox fs-1 text-muted opacity-50 mb-3 d-block"></i>
                                            <h5 class="fw-bold text-dark">ยังไม่มีงานใหม่เข้ามาในขณะนี้</h5>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($my_docs as $doc): ?>
                                        <tr class="doc-row" data-status="<?php echo (isset($doc['my_ack_status']) && $doc['my_ack_status'] == 1) ? 'acknowledged' : 'pending'; ?>">
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
                                                            <?php if(!empty($doc['tpm_id']) || !empty($doc['mr_id'])): ?>
                                                                <span class="badge bg-primary rounded-pill ms-1" style="font-size: 0.75rem;"><i class="bi bi-journal-check"></i> ข้อมูลการประชุมแล้ว</span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <div class='small text-muted'><?php echo htmlspecialchars($doc['doc_detail'] ?? 'ไม่มีรายละเอียดเพิ่มเติม'); ?></div>
                                                        <button type="button" class="btn btn-sm btn-outline-primary rounded-pill d-inline-flex align-items-center shadow-sm mt-2 text-nowrap" 
                                                            data-bs-toggle="modal" data-bs-target="#viewFullDocModal<?php echo $doc['id']; ?>" 
                                                            style="font-size: 0.75rem; padding: 0.2rem 0.75rem; border-width: 1px; transition: all 0.2s;">
                                                            <i class="bi bi-eye-fill me-1"></i> ดูรายละเอียดข้อมูลงาน
                                                        </button>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class='text-nowrap'>
                                                <span class='sender-badge text-nowrap'>
                                                    <i class="bi bi-person-circle text-secondary"></i>
                                                    <?php echo !empty($doc['sender_fname']) ? htmlspecialchars($doc['sender_fname'].' '.$doc['sender_lname']) : 'ไม่ระบุชื่อ'; ?>
                                                </span>
                                            </td>
                                            <td class='text-center'>
                                                <div class="fw-medium text-dark"><?php echo date('d/m/Y', strtotime($doc['received_date'])); ?></div>
                                            </td>
                                            <td class='text-center'>
                                                <div class="d-flex flex-nowrap gap-2 justify-content-center align-items-center">
                                                    
                                                    <button type="button" class="btn-table-action btn-view-doc"
                                                        onclick="openViewDocModal(
                                                            '<?php echo $doc['id']; ?>', 
                                                            '<?php echo $doc['my_ack_status'] ?? 0; ?>', 
                                                            '<?php echo htmlspecialchars($doc['doc_name'] ?? '-', ENT_QUOTES); ?>',
                                                            '<?php echo !empty($doc['received_date']) ? date('d/m/Y', strtotime($doc['received_date'])) : '-'; ?>',
                                                            '<?php echo htmlspecialchars(str_replace(["\r", "\n"], ' ', $doc['description'] ?? '-'), ENT_QUOTES); ?>',
                                                            '<?php echo htmlspecialchars(($doc['sender_fname'] ?? '') . ' ' . ($doc['sender_lname'] ?? ''), ENT_QUOTES); ?>',
                                                            '<?php echo htmlspecialchars($doc['sender_role'] ?? '-', ENT_QUOTES); ?>',
                                                            '<?php echo htmlspecialchars(!empty($doc['location']) ? $doc['location'] : (!empty($doc['venue_name']) ? $doc['venue_name'] : '-'), ENT_QUOTES); ?>',
                                                            '<?php echo formatDateTH($doc['pre_meeting_date'] ?? ''); ?>',
                                                            '<?php echo formatDateTH($doc['event_start_date'] ?? ''); ?>',
                                                            '<?php echo formatDateTH($doc['event_end_date'] ?? ''); ?>'
                                                        )">
                                                        <i class="bi bi-file-earmark-text d-block mb-1 fs-5"></i> หนังสือ
                                                    </button>

                                                    <?php if(!empty($doc['tpm_id']) || !empty($doc['mr_id'])): ?>
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
                                                            <i class="bi bi-diagram-3 d-block mb-1 fs-5"></i> ข้อมูลการประชุม
                                                        </button>
                                                    <?php elseif(isset($doc['my_ack_status']) && $doc['my_ack_status'] == 1): ?>
                                                        <button type="button" class="btn-table-action btn-add-detail text-nowrap" 
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#addMeetingModal"
                                                            data-id="<?php echo $doc['id']; ?>"
                                                            data-name="<?php echo htmlspecialchars($doc['doc_name'], ENT_QUOTES, 'UTF-8'); ?>"
                                                            onclick="openAddMeetingModal(this)">
                                                            <i class="bi bi-plus-circle d-block mb-1 fs-5"></i> บันทึกข้อมูล
                                                        </button>
                                                    <?php endif; ?>
                                                    
                                                    <button type="button" class="btn-table-action btn-equipment text-nowrap" 
                                                        data-bs-toggle="modal" data-bs-target="#borrowEquipmentModal<?php echo $doc['id']; ?>">
                                                        <i class="bi bi-box-seam d-block mb-1 fs-5"></i> ยืมอุปกรณ์
                                                    </button>
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

                <?php if ($total_pages > 1): ?>
                <nav aria-label="Page navigation" class="mt-4">
                    <ul class="pagination justify-content-center flex-wrap gap-1">
                        <li class="page-item <?php echo ($current_page <= 1) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" tabindex="-1">ก่อนหน้า</a>
                        </li>
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?php echo ($current_page == $i) ? 'active' : ''; ?>">
                                <a class="page-link" href="?page=<?php echo $i; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?php echo ($current_page >= $total_pages) ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $current_page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>">ถัดไป</a>
                        </li>
                    </ul>
                </nav>
                <?php endif; ?>

            </div> 
        </div> 
    </div>

    <div class="modal fade" id="addMeetingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <form action="" method="POST" class="modal-content border-0 rounded-4 shadow" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-header" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-diagram-3-fill me-2"></i> บันทึกข้อมูลการประชุม
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="alert alert-success bg-opacity-10 border-0 rounded-3 mb-4">
                        <i class="bi bi-info-circle-fill me-2 text-success"></i> กำลังเพิ่มข้อมูลการประชุมสำหรับหนังสือ: <br>
                        <strong id="add_display_doc_name" class="ms-4 text-dark">-</strong>
                    </div>
                    <input type="hidden" name="document_id" id="modal_document_id" value="">
                    <input type="hidden" name="save_meeting_details" value="1">
                    
                    <h6 class="section-title"><i class="bi bi-info-square-fill me-2 text-primary"></i>ข้อมูลการประชุม</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-12">
                            <!-- <label class="form-label fw-bold">ประเภทคณะกรรมการผังเมือง <span class="text-danger">*</span></label> -->
                            <!-- <select class="form-select" name="meeting_type" required>
                                <option value="" selected disabled>-- กรุณาเลือกประเภทคณะกรรมการ --</option>
                                <option value="คณะกรรมการที่ปรึกษาผังเมืองรวม ในเขตจังหวัด">คณะกรรมการที่ปรึกษาผังเมืองรวม ในเขตจังหวัด</option>
                                <option value="คณะกรรมการพิจารณาด้านการผังเมือง (กรมฯ)">คณะกรรมการพิจารณาด้านการผังเมือง ( กรมฯ )</option>
                                <option value="คณะอนุกรรมการผังเมือง เพื่อพิจารณาด้านการผังเมือง">คณะอนุกรรมการผังเมือง เพื่อพิจารณาด้านการผังเมือง</option>
                                <option value="คณะกรรมการผังเมือง">คณะกรรมการผังเมือง</option>
                            </select> -->
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
                        <div class="col-md-6">
                            <label class="form-label">ปีการประชุม <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="meeting_year" placeholder="เช่น 2569" required>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">รายละเอียดการประชุม</label>
                            <textarea class="form-control" name="meeting_detail" rows="3" placeholder="ระบุรายละเอียดการประชุมเพิ่มเติม"></textarea>
                        </div>
                    </div>

                   <div class="row g-3 mb-4 p-3 rounded-3 bg-white" style="border: 1px solid #e2e8f0;">
    <div class="col-12">
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
                            <label class="form-label">อัปโหลดไฟล์เอกสาร PDF/Word/Image</label>
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
                        <i class="bi bi-save me-2"></i> บันทึกข้อมูล
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($my_docs as $doc): ?>
    <!-- Modal ยืมอุปกรณ์ -->
    <div class="modal fade" id="borrowEquipmentModal<?php echo $doc['id']; ?>" tabindex="-1" aria-hidden="true" style="z-index: 1060;">
        <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-success-subtle border-bottom-0">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-box-seam-fill text-success me-2"></i>ยืมอุปกรณ์สำหรับงาน: <span class="text-success"><?php echo htmlspecialchars($doc['doc_name']); ?></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <div class="row g-4">
                        <!-- Left side: Form to borrow -->
                        <div class="col-md-5">
                            <div class="bg-white p-4 rounded-3 shadow-sm border h-100">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-plus-circle-fill text-success me-2"></i>เพิ่มรายการยืมใหม่</h6>
                                <form method="POST" action="staff_view_operation2.php?page=<?php echo $current_page; ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="job_id" value="<?php echo $doc['id']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label small fw-medium">เลือกอุปกรณ์</label>
                                        <select name="equipment_code" class="form-select" required>
                                            <option value="" disabled selected>-- เลือกอุปกรณ์ --</option>
                                            <?php foreach($equipments_list as $eq): ?>
                                                <?php if ($eq['available_quantity'] > 0): ?>
                                                    <option value="<?php echo htmlspecialchars($eq['equipment_code']); ?>">
                                                        <?php echo htmlspecialchars($eq['equipment_name']); ?> (คงเหลือ: <?php echo $eq['available_quantity']; ?> <?php echo $eq['unit']; ?>)
                                                    </option>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label small fw-medium">จำนวนที่ต้องการยืม</label>
                                        <input type="number" name="quantity" class="form-control" min="1" required placeholder="ระบุจำนวน">
                                    </div>
                                    <button type="submit" name="borrow_equipment" class="btn btn-success w-100 fw-bold shadow-sm">
                                        <i class="bi bi-save me-1"></i> บันทึกการยืม
                                    </button>
                                </form>
                            </div>
                        </div>
                        <!-- Right side: List of borrowed items -->
                        <div class="col-md-7">
                            <div class="bg-white p-4 rounded-3 shadow-sm border h-100">
                                <h6 class="fw-bold text-dark mb-3"><i class="bi bi-list-check text-primary me-2"></i>รายการที่ยืมแล้วสำหรับงานนี้</h6>
                                <?php 
                                    $current_borrowings = $borrowings_by_job[$doc['id']] ?? [];
                                    if (empty($current_borrowings)): 
                                ?>
                                    <div class="text-center text-muted py-5 border border-dashed rounded-3">
                                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                                        ยังไม่มีการยืมอุปกรณ์สำหรับงานนี้
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive" style="max-height: 300px;">
                                        <table class="table table-striped table-sm">
                                            <thead class="table-light">
                                                <tr>
                                                    <th>ชื่ออุปกรณ์</th>
                                                    <th class="text-center">จำนวน</th>
                                                    <th class="text-center">จัดการ</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                            <?php foreach($current_borrowings as $item): ?>
                                                <tr>
                                                    <td class="align-middle"><?php echo htmlspecialchars($item['equipment_name']); ?></td>
                                                    <td class="text-center align-middle"><?php echo htmlspecialchars($item['quantity']); ?> <?php echo htmlspecialchars($item['unit']); ?></td>
                                                    <td class="text-center">
                                                        <form method="POST" action="staff_view_operation2.php?page=<?php echo $current_page; ?>" style="display:inline;" onsubmit="return confirm('ยืนยันการคืนอุปกรณ์นี้?')">
                                                            <?= csrf_field() ?>
                                                            <input type="hidden" name="action" value="return_item">
                                                            <input type="hidden" name="borrow_id" value="<?php echo (int)$item['id']; ?>">
                                                            <input type="hidden" name="job_id" value="<?php echo (int)$doc['id']; ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="bi bi-arrow-return-left"></i> คืน</button>
                                                        </form>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <div class="modal fade" id="viewMeetingModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content border-0 rounded-4 shadow">
                <div class="modal-header" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; border-top-left-radius: 1rem; border-top-right-radius: 1rem;">
                    <h5 class="modal-title fw-bold">
                        <i class="bi bi-diagram-3-fill me-2"></i> ข้อมูลการประชุมและรายงานการประชุม
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <!-- <h5 class="fw-bold text-dark mb-1" id="view_meeting_name">-</h5> -->
                    <!-- <div class="text-muted mb-4 border-bottom pb-3">ประเภท: <span class="fw-medium text-primary" id="view_meeting_type">-</span></div> -->
                    
                    <h6 class="section-title"><i class="bi bi-info-square-fill me-2 text-primary"></i>ข้อมูลการประชุม</h6>
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

                    <!-- <h6 class="section-title"><i class="bi bi-journal-text me-2 text-primary"></i>ข้อมูลรายงานการประชุม</h6> -->
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
                        <div class="pe-4">
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
                        
                        <div class="col-12 mb-3 mt-2">
                            <div class="p-3 rounded-3" style="background-color: #fffbeb; border: 1px solid #fde68a;">
                                <h6 class="fw-bold mb-3" style="color: #b45309;"><i class="bi bi-info-circle-fill me-2"></i>ข้อมูลงานที่ได้รับมอบหมายเพิ่มเติม</h6>
                                <div class="row g-2">
                                    <div class="col-md-12">
                                        <span class="text-muted small">สถานที่จัดงาน / ประชุม:</span>
                                        <div class="fw-medium text-dark" id="vd_location">-</div>
                                    </div>
                                    <div class="col-md-6 mt-2">
                                        <span class="text-muted small">วันประชุมส่วนล่วงหน้า:</span>
                                        <div class="fw-medium text-dark" id="vd_pre_meeting_date">-</div>
                                    </div>
                                    <div class="col-md-6 mt-2">
                                        <span class="text-muted small">ระยะเวลางาน:</span>
                                        <div class="fw-medium text-dark" id="vd_event_date_range">-</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-12">
                            <div class="text-muted" style="font-size: 0.85rem; text-transform: uppercase; font-weight: 600; margin-bottom: 0.5rem;">รายละเอียดงาน / คำสั่ง</div>
                            <div class="p-3 border rounded-3 bg-light min-vh-25" style="white-space: pre-line; font-size: 1.1rem; color: #1e293b; word-break: break-word; overflow-wrap: break-word;" id="vd_doc_detail">-</div>
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
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="acknowledge_doc_id" id="vd_ack_doc_id" value="">
                                        <button type="submit" name="acknowledge_doc" id="btn_acknowledge" class="btn btn-success w-100 mb-2 rounded-pill shadow-sm fw-bold">
                                            <i class="bi bi-check2-all me-1"></i> ยืนยันรับทราบงาน
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php foreach ($my_docs as $doc): ?>
    <div class="modal fade" id="viewFullDocModal<?php echo $doc['id']; ?>" tabindex="-1" aria-hidden="true" style="z-index: 1080;">
        <div class="modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header border-bottom bg-light px-4 py-3">
                    <div class="d-flex align-items-center">
                        <div class="bg-info bg-opacity-10 text-info p-2 rounded-3 me-3 d-none d-md-flex">
                            <i class="bi bi-eye-fill fs-4"></i>
                        </div>
                        <h5 class="modal-title fw-bold text-dark mb-0 pe-4">รายละเอียดข้อมูลงาน<br class="d-md-none"><span class="fs-6 text-muted ms-md-2">(รหัส: <?php echo htmlspecialchars($doc['doc_number'] ?: $doc['id']); ?>)</span></h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body px-4 pb-4" style="background-color: #f8f9fa;">
                    
                    <div class="bg-white p-4 rounded-4 shadow-sm mb-4 mt-3">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-box-arrow-in-right me-2"></i>ข้อมูลการรับเรื่อง</h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <div class="text-muted small fw-bold">ปีงบประมาณ</div>
                                <div class="text-dark"><?php echo !empty($doc['fiscal_year']) ? htmlspecialchars($doc['fiscal_year']) : '-'; ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small fw-bold">เลขรับ กรมฯ</div>
                                <div class="text-dark"><?php echo !empty($doc['dpt_receive_number']) ? htmlspecialchars($doc['dpt_receive_number']) : '-'; ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small fw-bold">วันที่รับ กรมฯ</div>
                                <div class="text-dark"><?php echo !empty($doc['dpt_receive_date']) ? date('d/m/Y', strtotime($doc['dpt_receive_date'])) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">เลขรับ กอพ.</div>
                                <div class="text-dark"><?php echo !empty($doc['division_receive_number']) ? htmlspecialchars($doc['division_receive_number']) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">วันที่รับ กอพ.</div>
                                <div class="text-dark"><?php echo !empty($doc['division_receive_date']) ? date('d/m/Y', strtotime($doc['division_receive_date'])) : '-'; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-file-earmark-text me-2"></i>ข้อมูลหนังสือและชื่องาน</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">หน่วยงานต้นเรื่อง</div>
                                <div class="text-dark" style="word-break: break-word;"><?php echo !empty($doc['host_agency']) ? htmlspecialchars($doc['host_agency']) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">สังกัดของหน่วยงานต้นเรื่อง</div>
                                <div class="text-dark" style="word-break: break-word;"><?php echo !empty($doc['host_agency_affiliation']) ? htmlspecialchars($doc['host_agency_affiliation']) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">เลขที่หนังสือหน่วยงานต้นทาง</div>
                                <div class="text-dark" style="word-break: break-word;"><?php echo !empty($doc['source_doc_number']) ? htmlspecialchars($doc['source_doc_number']) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">ประเภทงาน</div>
                                <div class="text-dark" style="word-break: break-word;">
                                    <?php 
                                        $v_job_type_text = ($doc['job_type'] === 'อื่นๆ' && !empty($doc['job_type_other'])) 
                                                         ? "อื่นๆ: " . $doc['job_type_other'] 
                                                         : ($doc['job_type'] ?: '-');
                                        echo htmlspecialchars($v_job_type_text);
                                    ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="text-muted small fw-bold">ชื่องาน</div>
                                <div class="text-dark fw-bold" style="word-break: break-word;"><?php echo !empty($doc['doc_name']) ? htmlspecialchars($doc['doc_name']) : '-'; ?></div>
                            </div>
                            <div class="col-md-12">
                                <div class="text-muted small fw-bold">รายละเอียด</div>
                                <div class="text-dark" style="word-break: break-word;"><?php echo !empty($doc['description']) ? nl2br(htmlspecialchars($doc['description'])) : '-'; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-geo-alt me-2"></i>สถานที่จัดงาน</h6>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <div class="text-muted small fw-bold">หน่วยงาน / ชื่อสถานที่จัดงาน</div>
                                <div class="text-dark" style="word-break: break-word;"><?php echo !empty($doc['venue_name']) ? htmlspecialchars($doc['venue_name']) : '-'; ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small fw-bold">จังหวัด</div>
                                <div class="text-dark"><?php echo !empty($doc['venue_province']) ? htmlspecialchars($doc['venue_province']) : '-'; ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small fw-bold">อำเภอ/เขต</div>
                                <div class="text-dark"><?php echo !empty($doc['venue_district']) ? htmlspecialchars($doc['venue_district']) : '-'; ?></div>
                            </div>
                            <div class="col-md-4">
                                <div class="text-muted small fw-bold">ตำบล/แขวง</div>
                                <div class="text-dark"><?php echo !empty($doc['venue_subdistrict']) ? htmlspecialchars($doc['venue_subdistrict']) : '-'; ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white p-4 rounded-4 shadow-sm mb-4">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-calendar-event me-2"></i>กำหนดการและระยะเวลาดำเนินงาน</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">วันที่จัดงาน (เริ่ม)</div>
                                <div class="text-dark"><?php echo !empty($doc['event_start_date']) ? date('d/m/Y', strtotime($doc['event_start_date'])) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">วันที่จัดงาน (สิ้นสุด)</div>
                                <div class="text-dark"><?php echo !empty($doc['event_end_date']) ? date('d/m/Y', strtotime($doc['event_end_date'])) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">วันที่ประชุมล่วงหน้า</div>
                                <div class="text-dark"><?php echo !empty($doc['pre_meeting_date']) ? date('d/m/Y', strtotime($doc['pre_meeting_date'])) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-muted small fw-bold">วันที่ขอรับอุปกรณ์ยืม</div>
                                <div class="text-dark"><?php echo !empty($doc['equipment_borrow_date']) ? date('d/m/Y', strtotime($doc['equipment_borrow_date'])) : '-'; ?></div>
                            </div>
                            <hr class="my-3 text-muted">
                            <div class="col-md-6">
                                <div class="text-primary small fw-bold">ระยะเวลาทำงาน กอพ. (เริ่ม)</div>
                                <div class="text-dark"><?php echo !empty($doc['dpt_work_start']) ? date('d/m/Y', strtotime($doc['dpt_work_start'])) : '-'; ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="text-primary small fw-bold">ระยะเวลาทำงาน กอพ. (สิ้นสุด)</div>
                                <div class="text-dark"><?php echo !empty($doc['dpt_work_end']) ? date('d/m/Y', strtotime($doc['dpt_work_end'])) : '-'; ?></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-white p-4 rounded-4 shadow-sm mb-2">
                        <h6 class="fw-bold text-primary mb-3"><i class="bi bi-paperclip me-2"></i>ไฟล์แนบและหมายเหตุ</h6>
                        <div class="row g-3">
                            <div class="col-md-12">
                                <div class="text-muted small fw-bold">ไฟล์เอกสารแนบ (PDF)</div>
                                <div class="mt-1">
                                    <?php if (!empty($doc['attachment_file'])): ?>
                                        <a href="download.php?id=<?php echo $doc['id']; ?>" target="_blank" class="badge bg-danger text-decoration-none px-3 py-2">
                                            <i class="bi bi-file-earmark-pdf me-1"></i> ดาวน์โหลดไฟล์แนบ
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="text-muted small fw-bold mt-2">หมายเหตุ</div>
                                <div class="text-dark" style="word-break: break-word;"><?php echo !empty($doc['remark']) ? nl2br(htmlspecialchars($doc['remark'])) : '-'; ?></div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-top-0 px-4 pb-4 pt-3 bg-light">
                    <button type="button" class="btn btn-secondary rounded-pill px-5" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <?php require 'footer.php'; ?>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'></script>
    
    <script>
        // ฟังก์ชันจัดการ Modal สรุปเอกสาร
        function openViewDocModal(docId, isAck, docName, docDate, docDetail, senderName, senderRole, location, preMeetingDate, eventStartDate, eventEndDate) {
            document.getElementById('vd_doc_name').innerText = docName;
            document.getElementById('vd_received_date').innerText = docDate;
            document.getElementById('vd_doc_detail').innerText = docDetail;
            document.getElementById('vd_sender_name').innerText = senderName || 'ไม่ระบุชื่อ';
            document.getElementById('vd_sender_role').innerText = senderRole || '-';
            
            document.getElementById('vd_location').innerText = location || '-';
            document.getElementById('vd_pre_meeting_date').innerText = preMeetingDate || '-';

            let eventDateRange = '-';
            if (eventStartDate !== '-' && eventEndDate !== '-' && eventStartDate !== eventEndDate) {
                eventDateRange = eventStartDate + ' ถึง ' + eventEndDate;
            } else if (eventStartDate !== '-') {
                eventDateRange = eventStartDate;
            }
            document.getElementById('vd_event_date_range').innerText = eventDateRange;

            document.getElementById('vd_ack_doc_id').value = docId;
            const btnAck = document.getElementById('btn_acknowledge');
            const alertContainer = document.getElementById('vd_alert_container');
            
            if(isAck == '1') {
                btnAck.disabled = true;
                btnAck.innerHTML = '<i class="bi bi-check-circle-fill me-1"></i> รับทราบงานแล้ว';
                btnAck.className = 'btn btn-secondary w-100 mb-2 rounded-pill shadow-sm fw-bold';
                alertContainer.innerHTML = `
                    <div class="alert alert-success border-0 rounded-3 d-flex align-items-center mb-3" style="background-color: #dcfce7; color: #166534;">
                        <i class="bi bi-check-circle-fill fs-4 me-2"></i>
                        <div><strong>สถานะ:</strong> รับทราบงานเรียบร้อยแล้ว</div>
                    </div>`;
            } else {
                btnAck.disabled = false;
                btnAck.innerHTML = '<i class="bi bi-check2-all me-1"></i> ยืนยันรับทราบงาน';
                btnAck.className = 'btn btn-success w-100 mb-2 rounded-pill shadow-sm fw-bold';
                alertContainer.innerHTML = `
                    <div class="alert alert-warning border-0 rounded-3 d-flex align-items-center mb-3" style="background-color: #fffbeb; border: 1px solid #fde68a; color: #92400e;">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-2 text-warning"></i>
                        <div><strong>สถานะ:</strong> รอการยืนยันรับทราบ</div>
                    </div>`;
            }
            bootstrap.Modal.getOrCreateInstance(document.getElementById('viewDocModal')).show();
        }
        
        // ฟังก์ชันจัดการ Modal เพิ่มข้อมูลการประชุม
        function openAddMeetingModal(btnElement) {
            document.getElementById('modal_document_id').value = btnElement.getAttribute('data-id');
            document.getElementById('add_display_doc_name').innerText = btnElement.getAttribute('data-name');
            bootstrap.Modal.getOrCreateInstance(document.getElementById('addMeetingModal')).show();
        }

        // ฟังก์ชันจัดการเปิดดูข้อมูลการประชุมและรายงานที่บันทึกไว้
        function openViewMeetingModal(btnElement) {
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
            bootstrap.Modal.getOrCreateInstance(document.getElementById('viewMeetingModal')).show();
        }

        // แจ้งเตือน Success
        const successAlert = document.getElementById('success-alert');
        if (successAlert) {
            setTimeout(function() {
                const alert = new bootstrap.Alert(successAlert);
                alert.close();
            }, 2000);
        }

        // Auto-open modal logic for returning from equipment form submission
        const urlParams = new URLSearchParams(window.location.search);
        const openModalId = urlParams.get('open_modal');
        if (openModalId) {
            const modalElement = document.getElementById('borrowEquipmentModal' + openModalId);
            if (modalElement) {
                const myModal = new bootstrap.Modal(modalElement);
                myModal.show();
            }
        }

        // ระบบกรองข้อมูลการค้นหา
        const searchInput = document.getElementById('tableSearch');
        const resultCount = document.getElementById('searchResultCount');
        let currentStatus = 'all';

        function filterByStatus(status, element) {
            currentStatus = status;
            document.querySelectorAll('.filter-card').forEach(card => card.classList.remove('active'));
            element.classList.add('active');
            applyFilters();
        }

        function applyFilters() {
            const keyword = searchInput.value.trim().toLowerCase();
            const rows = document.querySelectorAll('.table-custom tbody tr.doc-row');
            let visible = 0;

            rows.forEach(row => {
                const docName    = row.querySelector('td:nth-child(1)')?.innerText.toLowerCase() || '';
                const senderName = row.querySelector('td:nth-child(2)')?.innerText.toLowerCase() || '';
                const rowStatus  = row.getAttribute('data-status');

                const matchKeyword = !keyword || docName.includes(keyword) || senderName.includes(keyword);
                const matchStatus  = currentStatus === 'all' || rowStatus === currentStatus;

                if (matchKeyword && matchStatus) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });

            resultCount.textContent = (keyword || currentStatus !== 'all') ? `พบ ${visible} รายการ` : '';
        }

        searchInput.addEventListener('input', applyFilters);
    </script>
</body>
</html>
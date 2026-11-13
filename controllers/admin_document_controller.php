<?php
/**
 * Controller for Admin Document Management (admin2.php)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/storage.php';
require_once __DIR__ . '/../includes/db.php';

function null_if_empty(mixed $val): ?string
{
    return (isset($val) && trim((string)$val) !== '') ? trim((string)$val) : null;
}

function handle_admin_document_actions(): void
{
    require_role([ROLE_ADMIN, ROLE_SUPER_ADMIN]);

    $conn = get_db_connection();
    $adminName = $_SESSION['username'] ?? 'Admin';

    // 1. DELETE DOCUMENT (POST only, with CSRF)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_document') {
        require_csrf();

        $id = clean_int($_POST['id'] ?? 0);
        if ($id <= 0) {
            redirect('admin2.php?delete_error=1');
        }

        try {
            // Check if forwarded or has assigned coordinators
            $stmtCheck = $conn->prepare("SELECT sent_to, attachment_file, doc_name FROM documents WHERE id = ?");
            $stmtCheck->execute([$id]);
            $doc = $stmtCheck->fetch();

            if (!$doc) {
                redirect('admin2.php?delete_error=1');
            }

            $stmtCoord = $conn->prepare("SELECT COUNT(*) FROM document_assignments WHERE doc_id = ?");
            $stmtCoord->execute([$id]);
            $coordCount = (int)$stmtCoord->fetchColumn();

            if (!empty($doc['sent_to']) || $coordCount > 0) {
                redirect('admin2.php?delete_error=1');
            }

            $conn->beginTransaction();

            // Delete document from DB
            $stmtDel = $conn->prepare("DELETE FROM documents WHERE id = ?");
            $stmtDel->execute([$id]);

            // Audit log
            $logDetail = "ลบหนังสือรับเรื่อง [ID: {$id}] " . ($doc['doc_name'] ?? '');
            $stmtLog = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (NULL, 'DELETE_DOC', ?, ?)");
            $stmtLog->execute([$logDetail, $adminName]);

            $conn->commit();

            // Delete file safely after DB transaction succeeds
            if (!empty($doc['attachment_file'])) {
                delete_uploaded_file($doc['attachment_file']);
            }

            redirect('admin2.php?delete_success=1');
        } catch (\Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Error deleting document: ' . $e->getMessage());
            redirect('admin2.php?delete_error=1');
        }
    }

    // 2. CANCEL FORWARD (POST only, with CSRF)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_forward') {
        require_csrf();

        $id = clean_int($_POST['id'] ?? 0);
        if ($id <= 0) {
            redirect('admin2.php');
        }

        try {
            $conn->beginTransaction();

            $stmt = $conn->prepare("UPDATE documents SET sent_to = NULL WHERE id = ?");
            $stmt->execute([$id]);

            $logDetail = "ยกเลิกการส่งต่อหนังสือ [ID: {$id}]";
            $stmtLog = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (?, 'CANCEL_FORWARD', ?, ?)");
            $stmtLog->execute([$id, $logDetail, $adminName]);

            $conn->commit();
            redirect('admin2.php?cancel_success=1');
        } catch (\Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            error_log('Error canceling forward: ' . $e->getMessage());
            redirect('admin2.php');
        }
    }

    // 3. ADD DOCUMENT (POST with CSRF)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_document'])) {
        require_csrf();

        try {
            $fiscalYear           = null_if_empty($_POST['fiscal_year'] ?? '');
            $dptReceiveNumber     = null_if_empty($_POST['dpt_receive_number'] ?? '');
            $dptReceiveDate       = null_if_empty($_POST['dpt_receive_date'] ?? '');
            $divisionReceiveNumber= null_if_empty($_POST['division_receive_number'] ?? '');
            $divisionReceiveDate  = null_if_empty($_POST['division_receive_date'] ?? '');

            $hostAgency           = null_if_empty($_POST['host_agency'] ?? '');
            $hostAgencyAffiliation= null_if_empty($_POST['host_agency_affiliation'] ?? '');
            $sourceDocNumber      = null_if_empty($_POST['source_doc_number'] ?? '');
            $docName              = clean_string($_POST['doc_name'] ?? '');
            $description          = null_if_empty($_POST['description'] ?? '');

            if (empty($docName)) {
                throw new Exception('กรุณาระบุชื่อเรื่อง / ชื่องาน');
            }

            $jobType = clean_string($_POST['job_type'] ?? '');
            if ($jobType === 'อื่นๆ' && !empty($_POST['job_type_other'])) {
                $jobType = clean_string($_POST['job_type_other']);
            }

            $venueName       = null_if_empty($_POST['venue_name'] ?? '');
            $venueProvince   = null_if_empty($_POST['venue_province'] ?? '');
            $venueDistrict   = null_if_empty($_POST['venue_district'] ?? '');
            $venueSubdistrict= null_if_empty($_POST['venue_subdistrict'] ?? '');

            $eventStartDate     = null_if_empty($_POST['event_start_date'] ?? '');
            $eventEndDate       = null_if_empty($_POST['event_end_date'] ?? '');
            $preMeetingDate     = null_if_empty($_POST['pre_meeting_date'] ?? '');
            $equipmentBorrowDate= null_if_empty($_POST['equipment_borrow_date'] ?? '');
            $dptWorkStart       = null_if_empty($_POST['dpt_work_start'] ?? '');
            $dptWorkEnd         = null_if_empty($_POST['dpt_work_end'] ?? '');

            $coord1Name  = null_if_empty($_POST['coordinator_1_name'] ?? '');
            $coord1Phone = null_if_empty($_POST['coordinator_1_phone'] ?? '');
            $coord2Name  = null_if_empty($_POST['coordinator_2_name'] ?? '');
            $coord2Phone = null_if_empty($_POST['coordinator_2_phone'] ?? '');

            $remark = null_if_empty($_POST['remark'] ?? '');
            $receivedDate = date('Y-m-d H:i:s');

            // Handle file upload
            $attachmentFile = null;
            if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                [$uploadSuccess, $uploadResult] = handle_pdf_upload($_FILES['attachment_file']);
                if (!$uploadSuccess) {
                    $_SESSION['upload_error'] = $uploadResult;
                    redirect('admin2.php');
                }
                $attachmentFile = $uploadResult;
            }

            $conn->beginTransaction();

            $sql = "INSERT INTO documents (
                fiscal_year, dpt_receive_number, dpt_receive_date, division_receive_number, division_receive_date,
                host_agency, host_agency_affiliation, source_doc_number, doc_name, description, job_type,
                venue_name, venue_province, venue_district, venue_subdistrict,
                event_start_date, event_end_date, pre_meeting_date, equipment_borrow_date, dpt_work_start, dpt_work_end,
                coordinator_1_name, coordinator_1_phone, coordinator_2_name, coordinator_2_phone,
                remark, attachment_file, received_date, created_by
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);
            $stmt->execute([
                $fiscalYear, $dptReceiveNumber, $dptReceiveDate, $divisionReceiveNumber, $divisionReceiveDate,
                $hostAgency, $hostAgencyAffiliation, $sourceDocNumber, $docName, $description, $jobType,
                $venueName, $venueProvince, $venueDistrict, $venueSubdistrict,
                $eventStartDate, $eventEndDate, $preMeetingDate, $equipmentBorrowDate, $dptWorkStart, $dptWorkEnd,
                $coord1Name, $coord1Phone, $coord2Name, $coord2Phone,
                $remark, $attachmentFile, $receivedDate, $adminName
            ]);

            $newDocId = (int)$conn->lastInsertId();

            // Audit log
            $logDetail = "สร้างหนังสือรับเรื่อง [ID: {$newDocId}] {$docName}";
            $stmtLog = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (?, 'CREATE_DOC', ?, ?)");
            $stmtLog->execute([$newDocId, $logDetail, $adminName]);

            $conn->commit();

            $_SESSION['success_msg'] = "บันทึกข้อมูลเข้าระบบเรียบร้อยแล้ว";
            redirect('admin2.php');
        } catch (\Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            if (!empty($attachmentFile)) {
                delete_uploaded_file($attachmentFile);
            }
            error_log('Error adding document: ' . $e->getMessage());
            $_SESSION['error_msg'] = 'เกิดข้อผิดพลาดในการบันทึกข้อมูล: ' . $e->getMessage();
            redirect('admin2.php');
        }
    }

    // 4. EDIT DOCUMENT (POST with CSRF)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_document'])) {
        require_csrf();

        try {
            $id = clean_int($_POST['id'] ?? 0);
            if ($id <= 0) {
                redirect('admin2.php');
            }

            // Fetch current document
            $stmtCur = $conn->prepare("SELECT attachment_file FROM documents WHERE id = ?");
            $stmtCur->execute([$id]);
            $curDoc = $stmtCur->fetch();
            if (!$curDoc) {
                redirect('admin2.php');
            }

            $fiscalYear           = null_if_empty($_POST['fiscal_year'] ?? '');
            $dptReceiveNumber     = null_if_empty($_POST['dpt_receive_number'] ?? '');
            $dptReceiveDate       = null_if_empty($_POST['dpt_receive_date'] ?? '');
            $divisionReceiveNumber= null_if_empty($_POST['division_receive_number'] ?? '');
            $divisionReceiveDate  = null_if_empty($_POST['division_receive_date'] ?? '');

            $hostAgency           = null_if_empty($_POST['host_agency'] ?? '');
            $hostAgencyAffiliation= null_if_empty($_POST['host_agency_affiliation'] ?? '');
            $sourceDocNumber      = null_if_empty($_POST['source_doc_number'] ?? '');
            $docName              = clean_string($_POST['doc_name'] ?? '');
            $description          = null_if_empty($_POST['description'] ?? '');

            if (empty($docName)) {
                throw new Exception('กรุณาระบุชื่อเรื่อง / ชื่องาน');
            }

            $jobType = clean_string($_POST['job_type'] ?? '');
            if ($jobType === 'อื่นๆ' && !empty($_POST['job_type_other'])) {
                $jobType = clean_string($_POST['job_type_other']);
            }

            $venueName       = null_if_empty($_POST['venue_name'] ?? '');
            $venueProvince   = null_if_empty($_POST['venue_province'] ?? '');
            $venueDistrict   = null_if_empty($_POST['venue_district'] ?? '');
            $venueSubdistrict= null_if_empty($_POST['venue_subdistrict'] ?? '');

            $eventStartDate     = null_if_empty($_POST['event_start_date'] ?? '');
            $eventEndDate       = null_if_empty($_POST['event_end_date'] ?? '');
            $preMeetingDate     = null_if_empty($_POST['pre_meeting_date'] ?? '');
            $equipmentBorrowDate= null_if_empty($_POST['equipment_borrow_date'] ?? '');
            $dptWorkStart       = null_if_empty($_POST['dpt_work_start'] ?? '');
            $dptWorkEnd         = null_if_empty($_POST['dpt_work_end'] ?? '');

            $coord1Name  = null_if_empty($_POST['coordinator_1_name'] ?? '');
            $coord1Phone = null_if_empty($_POST['coordinator_1_phone'] ?? '');
            $coord2Name  = null_if_empty($_POST['coordinator_2_name'] ?? '');
            $coord2Phone = null_if_empty($_POST['coordinator_2_phone'] ?? '');

            $remark = null_if_empty($_POST['remark'] ?? '');

            $attachmentSql = '';
            $params = [
                $fiscalYear, $dptReceiveNumber, $dptReceiveDate, $divisionReceiveNumber, $divisionReceiveDate,
                $hostAgency, $hostAgencyAffiliation, $sourceDocNumber, $docName, $description, $jobType,
                $venueName, $venueProvince, $venueDistrict, $venueSubdistrict,
                $eventStartDate, $eventEndDate, $preMeetingDate, $equipmentBorrowDate, $dptWorkStart, $dptWorkEnd,
                $coord1Name, $coord1Phone, $coord2Name, $coord2Phone,
                $remark
            ];

            $newAttachment = null;
            if (isset($_FILES['attachment_file']) && $_FILES['attachment_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                [$uploadSuccess, $uploadResult] = handle_pdf_upload($_FILES['attachment_file']);
                if (!$uploadSuccess) {
                    $_SESSION['upload_error'] = $uploadResult;
                    redirect('admin2.php');
                }
                $newAttachment = $uploadResult;
                $attachmentSql = ", attachment_file = ?";
                $params[] = $newAttachment;
            }

            $params[] = $id;

            $conn->beginTransaction();

            $sql = "UPDATE documents SET 
                fiscal_year = ?, dpt_receive_number = ?, dpt_receive_date = ?, division_receive_number = ?, division_receive_date = ?,
                host_agency = ?, host_agency_affiliation = ?, source_doc_number = ?, doc_name = ?, description = ?, job_type = ?,
                venue_name = ?, venue_province = ?, venue_district = ?, venue_subdistrict = ?,
                event_start_date = ?, event_end_date = ?, pre_meeting_date = ?, equipment_borrow_date = ?, dpt_work_start = ?, dpt_work_end = ?,
                coordinator_1_name = ?, coordinator_1_phone = ?, coordinator_2_name = ?, coordinator_2_phone = ?,
                remark = ?
                $attachmentSql
                WHERE id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->execute($params);

            // Audit log
            $logDetail = "แก้ไขข้อมูลหนังสือ [ID: {$id}] {$docName}";
            $stmtLog = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (?, 'EDIT_DOC', ?, ?)");
            $stmtLog->execute([$id, $logDetail, $adminName]);

            $conn->commit();

            // Safely delete old attachment only AFTER commit
            if ($newAttachment !== null && !empty($curDoc['attachment_file'])) {
                delete_uploaded_file($curDoc['attachment_file']);
            }

            redirect('admin2.php?edit_success=1');
        } catch (\Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            if (!empty($newAttachment)) {
                delete_uploaded_file($newAttachment);
            }
            error_log('Error editing document: ' . $e->getMessage());
            $_SESSION['error_msg'] = 'เกิดข้อผิดพลาดในการแก้ไขข้อมูล: ' . $e->getMessage();
            redirect('admin2.php');
        }
    }
}


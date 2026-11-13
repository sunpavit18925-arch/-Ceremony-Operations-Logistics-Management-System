<?php
/**
 * Controller for Manage Operation (manage_operation2.php)
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/equipment.php';

function handle_manage_operation_actions(): void
{
    require_role([ROLE_HEAD_OPERATION, ROLE_SUPER_ADMIN]);

    $conn = get_db_connection();
    $adminName = $_SESSION['username'] ?? 'Admin';
    $userId = current_user_id() ?? 0;
    $userRole = current_user_role();

    // Preserve query parameters for smooth redirect
    $page = clean_int($_GET['page'] ?? 1, 1);
    $qParams = [];
    if (!empty($_GET['search_text']))   $qParams['search_text'] = $_GET['search_text'];
    if (!empty($_GET['search_date']))   $qParams['search_date'] = $_GET['search_date'];
    if (!empty($_GET['search_type']))   $qParams['search_type'] = $_GET['search_type'];
    if (!empty($_GET['search_status'])) $qParams['search_status'] = $_GET['search_status'];
    $qStr = !empty($qParams) ? ('&' . http_build_query($qParams)) : '';

    // 1. DELETE DOCUMENT (POST only with CSRF)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_document') {
        require_csrf();

        $id = clean_int($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $conn->beginTransaction();

                $stmtDelAssign = $conn->prepare('DELETE FROM document_assignments WHERE doc_id = ?');
                $stmtDelAssign->execute([$id]);

                $stmt = $conn->prepare('DELETE FROM documents WHERE id = ?');
                $stmt->execute([$id]);

                $logDetail = "ลบงานและรายการมอบหมาย [Doc ID: {$id}]";
                $stmtLog = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (?, 'DELETE_DOC', ?, ?)");
                $stmtLog->execute([$id, $logDetail, $adminName]);

                $conn->commit();
                redirect("manage_operation2.php?page={$page}{$qStr}&delete_success=1");
            } catch (\Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Error deleting document in operation: " . $e->getMessage());
                redirect("manage_operation2.php?page={$page}{$qStr}&error=delete_failed");
            }
        }
    }

    // 2. CANCEL FORWARD / UNASSIGN ALL COORDINATORS (POST only with CSRF)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cancel_forward') {
        require_csrf();

        $id = clean_int($_POST['id'] ?? 0);
        if ($id > 0) {
            try {
                $conn->beginTransaction();

                $stmt = $conn->prepare('DELETE FROM document_assignments WHERE doc_id = ?');
                $stmt->execute([$id]);

                $logStmt = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (?, 'CANCEL_ASSIGN', ?, ?)");
                $logStmt->execute([$id, 'ยกเลิกการมอบหมายงานผู้ประสานงานทั้งหมด', $adminName]);

                $conn->commit();
                redirect("manage_operation2.php?page={$page}{$qStr}&cancel_success=1");
            } catch (\Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Error canceling forward: " . $e->getMessage());
                redirect("manage_operation2.php?page={$page}{$qStr}&error=cancel_failed");
            }
        }
    }

    // 3. ADD MULTI COORDINATOR (POST with CSRF)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_multi_coordinator'])) {
        require_csrf();

        $docId  = clean_int($_POST['doc_id'] ?? 0);
        $targetUserId = clean_int($_POST['user_id'] ?? 0);

        if ($docId > 0 && $targetUserId > 0) {
            try {
                $conn->beginTransaction();

                $check = $conn->prepare('SELECT COUNT(*) FROM document_assignments WHERE doc_id = ? AND user_id = ?');
                $check->execute([$docId, $targetUserId]);

                if ((int)$check->fetchColumn() === 0) {
                    $stmt = $conn->prepare('INSERT INTO document_assignments (doc_id, user_id) VALUES (?, ?)');
                    $stmt->execute([$docId, $targetUserId]);

                    $stmtNew = $conn->prepare('SELECT fname, lname FROM users WHERE id = ?');
                    $stmtNew->execute([$targetUserId]);
                    $newUser = $stmtNew->fetch();
                    $newName = $newUser ? ($newUser['fname'] . ' ' . $newUser['lname']) : 'ไม่ระบุ';

                    $stmtDoc = $conn->prepare('SELECT doc_name, doc_number FROM documents WHERE id = ?');
                    $stmtDoc->execute([$docId]);
                    $docInfo = $stmtDoc->fetch();
                    $docRef = $docInfo ? (!empty($docInfo['doc_number']) ? $docInfo['doc_number'] : $docInfo['doc_name']) : '';
                    $jobRefText = $docRef ? " (อ้างอิง: {$docRef})" : '';

                    $logStmt = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (?, 'ASSIGN_STAFF', ?, ?)");
                    $logStmt->execute([$docId, "เพิ่มผู้ประสานงาน: {$newName}{$jobRefText}", $adminName]);
                }

                $conn->commit();
                redirect("manage_operation2.php?page={$page}{$qStr}&coord_success=1&job_id={$docId}&modal=coord");
            } catch (\Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Error adding coordinator: " . $e->getMessage());
                redirect("manage_operation2.php?page={$page}{$qStr}&error=coord_failed");
            }
        }
    }

    // 4. REMOVE COORDINATOR (POST with CSRF)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'remove_coordinator') {
        require_csrf();

        $docId  = clean_int($_POST['doc_id'] ?? 0);
        $targetUserId = clean_int($_POST['user_id'] ?? 0);

        if ($docId > 0 && $targetUserId > 0) {
            try {
                $conn->beginTransaction();

                // Prevent removal if coordinator has acknowledged and submitted report
                $stmtCheckAck = $conn->prepare("
                    SELECT da.is_acknowledged, (SELECT COUNT(*) FROM meeting_reports mr WHERE mr.job_id = da.doc_id) as has_report 
                    FROM document_assignments da 
                    WHERE da.doc_id = ? AND da.user_id = ?
                ");
                $stmtCheckAck->execute([$docId, $targetUserId]);
                $checkStatus = $stmtCheckAck->fetch();

                if ($checkStatus && (int)$checkStatus['is_acknowledged'] === 1 && (int)$checkStatus['has_report'] > 0) {
                    throw new Exception('ไม่สามารถลบได้ เนื่องจากผู้ประสานงานท่านนี้รับทราบงานและส่งรายงานการประชุมแล้ว');
                }

                $stmtUser = $conn->prepare('SELECT fname, lname FROM users WHERE id = ?');
                $stmtUser->execute([$targetUserId]);
                $delUser = $stmtUser->fetch();
                $delName = $delUser ? ($delUser['fname'] . ' ' . $delUser['lname']) : 'ไม่ระบุ';

                $stmtDoc = $conn->prepare('SELECT doc_name, doc_number FROM documents WHERE id = ?');
                $stmtDoc->execute([$docId]);
                $docInfo = $stmtDoc->fetch();
                $docRef = $docInfo ? (!empty($docInfo['doc_number']) ? $docInfo['doc_number'] : $docInfo['doc_name']) : '';
                $jobRefText = $docRef ? " (อ้างอิง: {$docRef})" : '';

                $stmt = $conn->prepare('DELETE FROM document_assignments WHERE doc_id = ? AND user_id = ?');
                $stmt->execute([$docId, $targetUserId]);

                $logStmt = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (?, 'REMOVE_STAFF', ?, ?)");
                $logStmt->execute([$docId, "ลบผู้ประสานงาน: {$delName}{$jobRefText}", $adminName]);

                $conn->commit();
                redirect("manage_operation2.php?page={$page}{$qStr}&coord_del_success=1&job_id={$docId}&modal=coord");
            } catch (\Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Error removing coordinator: " . $e->getMessage());
                $_SESSION['operation_error'] = $e->getMessage();
                redirect("manage_operation2.php?page={$page}{$qStr}&job_id={$docId}&modal=coord");
            }
        }
    }

    // 5. MANAGE SETS (POST with CSRF)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['manage_sets'])) {
        require_csrf();

        $jobId = clean_int($_POST['job_id'] ?? 0);
        $setTarget = trim($_POST['existing_set'] ?? '');

        if ($jobId > 0 && !empty($setTarget)) {
            try {
                $conn->beginTransaction();

                $stmtDoc = $conn->prepare('SELECT doc_name, job_type FROM documents WHERE id = ?');
                $stmtDoc->execute([$jobId]);
                $document = $stmtDoc->fetch();

                if (!$document) {
                    throw new Exception('ไม่พบข้อมูลอ้างอิงเอกสารในระบบ');
                }

                $stmtCheckJob = $conn->prepare('SELECT id FROM jobs WHERE id = ?');
                $stmtCheckJob->execute([$jobId]);
                if (!$stmtCheckJob->fetch()) {
                    $jobType = !empty($document['job_type']) ? $document['job_type'] : 'อื่นๆ';
                    $docName = !empty($document['doc_name']) ? $document['doc_name'] : 'ไม่มีชื่อ';
                    $stmtJob = $conn->prepare('INSERT INTO jobs (id, job_code, job_name, job_type) VALUES (?, ?, ?, ?)');
                    $stmtJob->execute([$jobId, 'JOB-' . str_pad($jobId, 4, '0', STR_PAD_LEFT), $docName, $jobType]);
                }

                $stmtMembers = $conn->prepare('SELECT user_id, staff_name FROM job_assignments WHERE job_id IS NULL AND staff_role = ?');
                $stmtMembers->execute([$setTarget]);
                $members = $stmtMembers->fetchAll();

                if (empty($members)) {
                    throw new Exception("ไม่พบรายชื่อสมาชิกใน {$setTarget}");
                }

                $stmtClearOld = $conn->prepare('DELETE FROM job_assignments WHERE job_id = ?');
                $stmtClearOld->execute([$jobId]);

                $stmtInsertNew = $conn->prepare('INSERT INTO job_assignments (job_id, user_id, staff_name, staff_role) VALUES (?, ?, ?, ?)');
                $assignedNames = [];

                foreach ($members as $m) {
                    if (strpos($m['staff_name'], 'ยังไม่มีสมาชิก') === false) {
                        $stmtInsertNew->execute([$jobId, $m['user_id'], $m['staff_name'], $setTarget]);
                        $assignedNames[] = $m['staff_name'];
                    }
                }

                $memberListText = !empty($assignedNames) ? implode(', ', $assignedNames) : 'ไม่มีสมาชิก';
                $logStmt = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (?, 'ASSIGN_SET', ?, ?)");
                $logStmt->execute([$jobId, "จัดชุดปฏิบัติงาน: {$setTarget} ({$memberListText})", $adminName]);

                $conn->commit();
                redirect("manage_operation2.php?page={$page}{$qStr}&set_success=1&job_id={$jobId}&modal=team");
            } catch (\Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                error_log("Error managing sets: " . $e->getMessage());
                $_SESSION['operation_error'] = $e->getMessage();
                redirect("manage_operation2.php?page={$page}{$qStr}&job_id={$jobId}&modal=team");
            }
        }
    }

    // 6. BORROW EQUIPMENT (POST with CSRF)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['borrow_equipment'])) {
        require_csrf();

        $jobId = clean_int($_POST['job_id'] ?? 0);
        $equipmentCode = clean_string($_POST['equipment_code'] ?? '');
        $quantity = clean_int($_POST['quantity'] ?? 0);

        [$borrowOk, $borrowMsg] = equipment_borrow($conn, $jobId, $equipmentCode, $quantity, $adminName);

        if ($borrowOk) {
            redirect("manage_operation2.php?page={$page}{$qStr}&borrow_success=1&job_id={$jobId}&modal=borrow");
        } else {
            $_SESSION['operation_error'] = $borrowMsg;
            redirect("manage_operation2.php?page={$page}{$qStr}&job_id={$jobId}&modal=borrow");
        }
    }

    // 7. RETURN EQUIPMENT (POST only with CSRF)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'return_item') {
        require_csrf();

        $borrowId = clean_int($_POST['borrow_id'] ?? 0);
        $jobId    = clean_int($_POST['job_id'] ?? 0);

        [$returnOk, $returnMsg] = equipment_return($conn, $borrowId, $jobId, $userId, $userRole, $adminName);

        if ($returnOk) {
            redirect("manage_operation2.php?page={$page}{$qStr}&return_success=1&job_id={$jobId}&modal=borrow");
        } else {
            $_SESSION['operation_error'] = $returnMsg;
            redirect("manage_operation2.php?page={$page}{$qStr}&job_id={$jobId}&modal=borrow");
        }
    }
}


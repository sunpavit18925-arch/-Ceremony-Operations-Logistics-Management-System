<?php
/**
 * Protected Document Download / View Handler
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/storage.php';

require_login();

$docId = clean_int($_GET['id'] ?? 0);
$fileParam = trim($_GET['file'] ?? '');

$conn = get_db_connection();
$doc = null;

if ($docId > 0) {
    $stmt = $conn->prepare("SELECT * FROM documents WHERE id = ? LIMIT 1");
    $stmt->execute([$docId]);
    $doc = $stmt->fetch();
} elseif (!empty($fileParam)) {
    $safeName = safe_file_basename($fileParam);
    $stmt = $conn->prepare("SELECT * FROM documents WHERE attachment_file = ? LIMIT 1");
    $stmt->execute([$safeName]);
    $doc = $stmt->fetch();
}

if (!$doc || empty($doc['attachment_file'])) {
    http_response_code(404);
    die('<div style="text-align:center; margin-top:50px; font-family:sans-serif; color:#721c24;"><h3>404 - ไม่พบเอกสารแนบที่ระบุ</h3></div>');
}

// Authorization check: Verify user has permission to view this document
$userId = current_user_id();
$userRole = current_user_role();
$hasAccess = false;

if (in_array($userRole, [ROLE_SUPER_ADMIN, ROLE_ADMIN, ROLE_HEAD_OPERATION, ROLE_HEAD_VEHICLE], true)) {
    $hasAccess = true;
} elseif ($userRole === ROLE_COORDINATOR_OPERATION) {
    // Check if coordinator is assigned or sent_to
    $stmtCheck = $conn->prepare("SELECT 1 FROM document_assignments WHERE doc_id = ? AND user_id = ? LIMIT 1");
    $stmtCheck->execute([$doc['id'], $userId]);
    if ($stmtCheck->fetchColumn() || (int)$doc['sent_to'] === $userId) {
        $hasAccess = true;
    }
} elseif ($userRole === ROLE_STAFF_OPERATION) {
    if ((int)$doc['sent_to'] === $userId) {
        $hasAccess = true;
    }
} elseif ($userRole === ROLE_STAFF_VEHICLE) {
    // Check vehicle assignment
    $stmtVa = $conn->prepare("SELECT 1 FROM vehicle_assignments WHERE job_id = ? LIMIT 1");
    $stmtVa->execute([$doc['id']]);
    if ($stmtVa->fetchColumn()) {
        $hasAccess = true;
    }
}

if (!$hasAccess) {
    http_response_code(403);
    die('<div style="text-align:center; margin-top:50px; font-family:sans-serif; color:#721c24;"><h3>403 - คุณไม่มีสิทธิ์เข้าถึงเอกสารนี้</h3></div>');
}

$filename = safe_file_basename($doc['attachment_file']);
$uploadDir = ensure_upload_dir_protected();
$filePath = $uploadDir . $filename;

if (!file_exists($filePath) || !is_file($filePath)) {
    http_response_code(404);
    die('<div style="text-align:center; margin-top:50px; font-family:sans-serif; color:#721c24;"><h3>404 - ไฟล์เอกสารไม่พบบนเซิร์ฟเวอร์</h3></div>');
}

$fileSize = filesize($filePath);
$downloadName = clean_string($doc['doc_name'] ?? 'document') . '.pdf';

// Prevent header injection in filename
$downloadName = preg_replace('/[\r\n"]/', '', $downloadName);

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="' . rawurlencode($downloadName) . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

// Output file in chunks to prevent memory spikes
$fp = fopen($filePath, 'rb');
if ($fp) {
    while (!feof($fp)) {
        echo fread($fp, 65536);
        flush();
    }
    fclose($fp);
}
exit();


<?php
/**
 * Secure File Storage & Upload Manager
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/security.php';

const UPLOAD_DIR = __DIR__ . '/../uploads/documents/';
const MAX_UPLOAD_SIZE = 5 * 1024 * 1024; // 5 MB

/**
 * Ensure the upload directory exists and is protected from direct web access
 */
function ensure_upload_dir_protected(?string $targetDir = null): string
{
    $dir = $targetDir ? rtrim($targetDir, '/\\') . '/' : rtrim(UPLOAD_DIR, '/\\') . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $htaccess = $dir . '.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "# Protect direct web access to uploaded documents\n<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Deny from all\n</IfModule>\n");
    }

    return $dir;
}

/**
 * Validate and safely store an uploaded PDF file
 *
 * @param array $file $_FILES['input_name']
 * @param string|null $targetDir Custom target directory or null for default
 * @param int $maxBytes Maximum allowed file size in bytes
 * @return array ['success' => bool, 'filename' => string, 'error' => string]
 */
function handle_pdf_upload(array $file, ?string $targetDir = null, int $maxBytes = MAX_UPLOAD_SIZE): array
{
    if (!isset($file['error']) || is_array($file['error'])) {
        return [
            'success'  => false,
            'error'    => 'โครงสร้างไฟล์อัปโหลดไม่ถูกต้อง',
            'filename' => '',
            0          => false,
            1          => 'โครงสร้างไฟล์อัปโหลดไม่ถูกต้อง',
        ];
    }

    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['success' => false, 'error' => 'ไม่มีไฟล์ถูกเลือก', 'filename' => '', 0 => false, 1 => 'ไม่มีไฟล์ถูกเลือก'];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['success' => false, 'error' => 'ขนาดไฟล์เกินกำหนด (สูงสุด 5MB)', 'filename' => '', 0 => false, 1 => 'ขนาดไฟล์เกินกำหนด (สูงสุด 5MB)'];
        default:
            return ['success' => false, 'error' => 'เกิดข้อผิดพลาดในการอัปโหลดไฟล์ (รหัส: ' . $file['error'] . ')', 'filename' => '', 0 => false, 1 => 'เกิดข้อผิดพลาดในการอัปโหลด'];
    }

    if ($file['size'] <= 0 || $file['size'] > $maxBytes) {
        return ['success' => false, 'error' => 'ขนาดไฟล์ต้องไม่เกิน 5MB', 'filename' => '', 0 => false, 1 => 'ขนาดไฟล์ต้องไม่เกิน 5MB'];
    }

    $ext = strtolower(pathinfo($file['name'] ?? '', PATHINFO_EXTENSION));
    if ($ext !== 'pdf') {
        return ['success' => false, 'error' => 'ระบบอนุญาตเฉพาะไฟล์นามสกุล .pdf เท่านั้น', 'filename' => '', 0 => false, 1 => 'ระบบอนุญาตเฉพาะไฟล์นามสกุล .pdf เท่านั้น'];
    }

    if (!file_exists($file['tmp_name'])) {
        return ['success' => false, 'error' => 'ไม่พบไฟล์ชั่วคราว', 'filename' => '', 0 => false, 1 => 'ไม่พบไฟล์ชั่วคราว'];
    }

    // Verify true MIME type using finfo
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if ($mimeType !== 'application/pdf') {
        return ['success' => false, 'error' => 'เนื้อหาไฟล์ไม่ใช่เอกสาร PDF ที่ถูกต้อง (MIME: ' . e($mimeType) . ')', 'filename' => '', 0 => false, 1 => 'เนื้อหาไฟล์ไม่ใช่เอกสาร PDF ที่ถูกต้อง'];
    }

    // Check PDF header signature (%PDF-)
    $handle = fopen($file['tmp_name'], 'rb');
    $header = fread($handle, 5);
    fclose($handle);
    if ($header !== '%PDF-') {
        return ['success' => false, 'error' => 'ไฟล์ไม่ใช่รูปแบบ PDF ที่ถูกต้อง', 'filename' => '', 0 => false, 1 => 'ไฟล์ไม่ใช่รูปแบบ PDF ที่ถูกต้อง'];
    }

    $uploadDir = $targetDir ? rtrim($targetDir, '/\\') . '/' : ensure_upload_dir_protected();
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    ensure_upload_dir_protected($uploadDir);

    $safeFilename = 'doc_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.pdf';
    $targetPath = $uploadDir . $safeFilename;

    $moved = @move_uploaded_file($file['tmp_name'], $targetPath);
    // Allow copy in testing environment
    if (!$moved && env('APP_ENV') === 'testing') {
        $moved = @copy($file['tmp_name'], $targetPath);
    }

    if (!$moved) {
        return ['success' => false, 'error' => 'ไม่สามารถบันทึกไฟล์ลงเซิร์ฟเวอร์ได้', 'filename' => '', 0 => false, 1 => 'ไม่สามารถบันทึกไฟล์ลงเซิร์ฟเวอร์ได้'];
    }

    return [
        'success'  => true,
        'filename' => $safeFilename,
        'error'    => '',
        0          => true,
        1          => $safeFilename,
    ];
}

/**
 * Safely delete an uploaded file
 */
function delete_uploaded_file(?string $filename): void
{
    if (empty($filename)) {
        return;
    }

    $safeName = safe_file_basename($filename);
    $path = ensure_upload_dir_protected() . $safeName;

    if (file_exists($path) && is_file($path)) {
        @unlink($path);
    }
}

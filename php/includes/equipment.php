<?php
/**
 * Equipment Borrowing & Returning Service
 * Handles row locking (SELECT ... FOR UPDATE), transactions, concurrency, and audit logs.
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/security.php';

/**
 * Borrow equipment with strict row lock and stock validation
 */
function equipment_borrow(PDO $conn, int $jobId, string $equipmentCode, int $quantity, string $performedBy): array
{
    if ($jobId <= 0 || empty($equipmentCode) || $quantity <= 0) {
        return [
            'success'   => false,
            'message'   => 'ข้อมูลการยืมไม่ครบถ้วนหรือไม่ถูกต้อง (จำนวนต้องมากกว่า 0)',
            'borrow_id' => null,
            0           => false,
            1           => 'ข้อมูลการยืมไม่ครบถ้วนหรือไม่ถูกต้อง (จำนวนต้องมากกว่า 0)',
            2           => null,
        ];
    }

    $inTx = $conn->inTransaction();
    if (!$inTx) {
        $conn->beginTransaction();
    }

    try {
        // Lock equipment record for stock check
        $stmtCheck = $conn->prepare("SELECT id, equipment_name, available_quantity, total_quantity FROM equipments WHERE equipment_code = ? FOR UPDATE");
        $stmtCheck->execute([$equipmentCode]);
        $eq = $stmtCheck->fetch();

        if (!$eq) {
            throw new Exception("ไม่พบรหัสอุปกรณ์ที่ระบุ ({$equipmentCode})");
        }

        $available = (int)$eq['available_quantity'];
        if ($quantity > $available) {
            throw new Exception("จำนวนคงเหลือไม่เพียงพอ (ต้องการ {$quantity}, คงเหลือ {$available})");
        }

        // Insert borrowing record
        $stmtBorrow = $conn->prepare("INSERT INTO equipment_borrowings (job_id, equipment_code, quantity, status, created_by) VALUES (?, ?, ?, 'borrowed', ?)");
        $stmtBorrow->execute([$jobId, $equipmentCode, $quantity, $performedBy]);
        $borrowId = (int)$conn->lastInsertId();

        // Deduct inventory
        $stmtUpdate = $conn->prepare("UPDATE equipments SET available_quantity = available_quantity - ? WHERE equipment_code = ? AND available_quantity >= ?");
        $stmtUpdate->execute([$quantity, $equipmentCode, $quantity]);

        if ($stmtUpdate->rowCount() !== 1) {
            throw new Exception("ไม่สามารถตัดยอดสต็อกอุปกรณ์ได้ กรุณาลองใหม่อีกครั้ง");
        }

        // Audit log
        $logDetail = "ยืมอุปกรณ์ [{$equipmentCode}] {$eq['equipment_name']} จำนวน {$quantity} ชิ้น (Job ID: {$jobId})";
        $stmtLog = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (?, 'BORROW_EQUIPMENT', ?, ?)");
        $stmtLog->execute([$jobId, $logDetail, $performedBy]);

        if (!$inTx) {
            $conn->commit();
        }

        return [
            'success'   => true,
            'message'   => 'บันทึกการยืมอุปกรณ์สำเร็จ',
            'borrow_id' => $borrowId,
            0           => true,
            1           => 'บันทึกการยืมอุปกรณ์สำเร็จ',
            2           => $borrowId,
        ];
    } catch (Exception $e) {
        if (!$inTx && $conn->inTransaction()) {
            $conn->rollBack();
        }
        return [
            'success'   => false,
            'message'   => $e->getMessage(),
            'borrow_id' => null,
            0           => false,
            1           => $e->getMessage(),
            2           => null,
        ];
    }
}

/**
 * Return equipment with concurrency safety and audit trail
 */
function equipment_return(PDO $conn, int $borrowId, int $jobId = 0, int $userId = 0, string $userRole = '', string $performedBy = 'system'): array
{
    if ($borrowId <= 0) {
        return [
            'success' => false,
            'message' => 'ข้อมูลการคืนอุปกรณ์ไม่ครบถ้วน',
            0         => false,
            1         => 'ข้อมูลการคืนอุปกรณ์ไม่ครบถ้วน',
        ];
    }

    $inTx = $conn->inTransaction();
    if (!$inTx) {
        $conn->beginTransaction();
    }

    try {
        // Lock borrowing record
        $stmtBorrow = $conn->prepare("SELECT id, job_id, equipment_code, quantity, status FROM equipment_borrowings WHERE id = ? FOR UPDATE");
        $stmtBorrow->execute([$borrowId]);
        $borrow = $stmtBorrow->fetch();

        if (!$borrow) {
            throw new Exception("ไม่พบรายการยืมที่ระบุ");
        }

        if ($jobId > 0 && (int)$borrow['job_id'] !== $jobId) {
            throw new Exception("รายการยืมนี้ไม่ตรงกับภารกิจที่ระบุ");
        }
        $targetJobId = (int)$borrow['job_id'];

        if ($borrow['status'] === 'returned') {
            throw new Exception("รายการนี้ได้รับการคืนอุปกรณ์ไปเรียบร้อยแล้ว");
        }

        // Concurrency-safe state transition: status 'borrowed' -> 'returned'
        $stmtUpdateBorrow = $conn->prepare("UPDATE equipment_borrowings SET status = 'returned', returned_at = NOW(), returned_by = ? WHERE id = ? AND status = 'borrowed'");
        $stmtUpdateBorrow->execute([$userId > 0 ? $userId : null, $borrowId]);

        if ($stmtUpdateBorrow->rowCount() !== 1) {
            throw new Exception("รายการนี้ถูกคืนไปแล้วโดยคำขออื่น (ป้องกันการเพิ่มสต็อกซ้ำซ้อน)");
        }

        // Restore stock safely without exceeding total_quantity
        $qty = (int)$borrow['quantity'];
        $eqCode = $borrow['equipment_code'];

        $stmtRestore = $conn->prepare("UPDATE equipments SET available_quantity = LEAST(total_quantity, available_quantity + ?) WHERE equipment_code = ?");
        $stmtRestore->execute([$qty, $eqCode]);

        // Audit log
        $logDetail = "คืนอุปกรณ์ [{$eqCode}] จำนวน {$qty} ชิ้น (Borrow ID: {$borrowId}, Job ID: {$targetJobId})";
        $stmtLog = $conn->prepare("INSERT INTO system_logs (job_id, action_type, action_detail, performed_by) VALUES (?, 'RETURN_EQUIPMENT', ?, ?)");
        $stmtLog->execute([$targetJobId, $logDetail, $performedBy]);

        if (!$inTx) {
            $conn->commit();
        }

        return [
            'success' => true,
            'message' => 'บันทึกการคืนอุปกรณ์สำเร็จ',
            0         => true,
            1         => 'บันทึกการคืนอุปกรณ์สำเร็จ',
        ];
    } catch (Exception $e) {
        if (!$inTx && $conn->inTransaction()) {
            $conn->rollBack();
        }
        return [
            'success' => false,
            'message' => $e->getMessage(),
            0         => false,
            1         => $e->getMessage(),
        ];
    }
}

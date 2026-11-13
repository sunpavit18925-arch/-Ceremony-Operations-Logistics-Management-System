<?php
require 'db.php';

// แก้ไข SQL โดยใช้ตาราง system_logs เพื่อหาว่าใครเป็นคนส่งงานล่าสุด
$sql = "SELECT d.*, u.fname, u.lname, u.role 
        FROM documents d 
        LEFT JOIN (
            SELECT job_id, performed_by 
            FROM system_logs 
            WHERE action_type IN ('ASSIGN_STAFF', 'CHANGE_ASSIGN')
            GROUP BY job_id
        ) sl ON d.id = sl.job_id
        LEFT JOIN users u ON sl.performed_by = u.username_sso
        ORDER BY d.received_date DESC";

$stmt = $conn->query($sql);
$docs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <title>รายการเอกสาร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
</head>
<body class="p-4 bg-light">

<div class="container bg-white p-4 shadow rounded">
    <h3 class="mb-4">รายการเอกสารทั้งหมด</h3>
    <div class="table-responsive">
        <table class="table table-hover" style="min-width: 600px;">
            <thead>
                <tr>
                    <th>ชื่อเอกสาร</th>
                    <th>วันที่</th>
                    <th>การจัดการ</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($docs as $doc): ?>
                <tr>
                    <td><?php echo htmlspecialchars($doc['doc_name']); ?></td>
                    <td><?php echo date('d/m/Y', strtotime($doc['received_date'])); ?></td>
                    <td>
                        <button type="button" class="btn btn-primary btn-sm text-nowrap" 
                            data-bs-toggle="modal" 
                            data-bs-target="#viewDocModal"
                            data-doc-name="<?php echo htmlspecialchars($doc['doc_name']); ?>"
                            data-doc-date="<?php echo date('d/m/Y', strtotime($doc['received_date'])); ?>"
                            data-doc-detail="<?php echo htmlspecialchars($doc['description']); ?>"
                            data-sender-name="<?php echo htmlspecialchars(($doc['fname'] ?? 'ระบบ') . ' ' . ($doc['lname'] ?? '')); ?>"
                            data-sender-role="<?php echo htmlspecialchars($doc['role'] ?? '-'); ?>"
                            onclick="openViewDocModal(this)">
                            <i class="bi bi-eye"></i> เปิดดู
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="modal fade" id="viewDocModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="vd_doc_name">-</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>วันที่รับหนังสือ:</strong> <span id="vd_received_date"></span></p>
                <div class="p-3 bg-light border rounded">
                    <strong>รายละเอียด:</strong>
                    <div id="vd_doc_detail" style="word-break: break-word; overflow-wrap: break-word;"></div>
                </div>
                <hr>
                <p><strong>ผู้มอบหมาย:</strong> <span id="vd_sender_name"></span> <br>
                   <small class="text-muted">ตำแหน่ง: <span id="vd_sender_role"></span></small></p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function openViewDocModal(btn) {
        document.getElementById('vd_doc_name').innerText = btn.getAttribute('data-doc-name');
        document.getElementById('vd_received_date').innerText = btn.getAttribute('data-doc-date');
        document.getElementById('vd_doc_detail').innerText = btn.getAttribute('data-doc-detail');
        document.getElementById('vd_sender_name').innerText = btn.getAttribute('data-sender-name');
        document.getElementById('vd_sender_role').innerText = btn.getAttribute('data-sender-role');
    }
</script>
</body>
</html>
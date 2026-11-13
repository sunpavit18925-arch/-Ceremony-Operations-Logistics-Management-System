<?php
session_start();
require 'db.php';

if(!isset($_SESSION['role']) || ($_SESSION['role'] !== 'staff_vehicle' && $_SESSION['role'] !== 'super_admin')) {
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

// 1. Security Check: ถ้าไม่มี User ID ใน Session ให้ดีดไป Login
if ( !isset( $_SESSION[ 'user_id' ] ) ) {
    header( 'Location: login.php' );
    exit();
}

$my_id = $_SESSION[ 'user_id' ];

try {
    // 2. ดึงข้อมูลล่าสุดจาก DB ทุกครั้งที่มีการโหลดหน้า ( Refresh )
    $stmt_user = $conn->prepare( 'SELECT fname, lname, role, email FROM users WHERE id = ? LIMIT 1' );
    $stmt_user->execute( [ $my_id ] );
    $u_data = $stmt_user->fetch( PDO::FETCH_ASSOC );

    // ตรวจสอบว่ายังมี User นี้อยู่ในระบบหรือไม่
    if ( !$u_data ) {
        session_destroy();
        header( 'Location: login.php?error=user_not_found' );
        exit();
    }

    /** * --- จุดสำคัญ: อัปเดต Session ให้เป็นค่าล่าสุดเสมอ ---
    * วิธีนี้จะทำให้ค่าใน Session เปลี่ยนตาม Database ทันทีที่ Refresh หน้าจอ
    */
    $_SESSION[ 'user_fname' ] = $u_data[ 'fname' ];
    $_SESSION[ 'role' ]       = $u_data[ 'role' ];
    // สามารถอัปเดตค่าอื่นๆ เพิ่มเติมได้ที่นี่

    // กำหนดตัวแปรไว้ใช้ในหน้า HTML
    $user_name    = $u_data[ 'fname' ];
    $current_role = $u_data[ 'role' ];

    // 3. ดึงรายการหนังสือที่ส่งถึงคนนี้
    // --- แก้ไขจุดนี้ใน staff_view.php ---
    // --- Pagination Setup ---
    $limit = 10;
    $page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    $stmt_total = $conn->prepare("SELECT COUNT(*) FROM documents WHERE sent_to = :my_id");
    $stmt_total->execute([':my_id' => $my_id]);
    $total_rows = $stmt_total->fetchColumn();
    $total_pages = ceil($total_rows / $limit);

    $sql_docs = "SELECT d.*, u.fname as sender_fname, u.lname as sender_lname 
             FROM documents d
             LEFT JOIN system_logs sl ON sl.id = (
                 SELECT MAX(id) FROM system_logs WHERE job_id = d.id AND action_type IN ('ASSIGN_STAFF', 'CHANGE_ASSIGN')
             )
             LEFT JOIN users u ON sl.performed_by = u.username_sso 
             WHERE d.sent_to = :my_id 
             ORDER BY d.received_date DESC
             LIMIT :limit OFFSET :offset";

    $stmt_docs = $conn->prepare( $sql_docs );
    $stmt_docs->bindValue(':my_id', $my_id, PDO::PARAM_INT);
    $stmt_docs->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_docs->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_docs->execute();
    $my_docs = $stmt_docs->fetchAll( PDO::FETCH_ASSOC );

} catch ( PDOException $e ) {
    // แสดง Error แบบสุภาพ หรือบันทึกลง Log
    error_log( 'Database Error: ' . $e->getMessage() );
    $error_msg = 'ขออภัย ระบบขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้ง';
}
?>

<!DOCTYPE html>
<html lang='th'>

<head>
    <meta charset='UTF-8'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการงานมอบหมาย</title>
    
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

        /* ส่วนหัวของหน้า */
        .page-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            padding: 1.8rem 2rem;
            border-radius: 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03);
            margin-bottom: 2rem;
            margin-top: 1.5rem;
        }

        .user-profile-sm {
            display: flex;
            align-items: center;
            gap: 12px;
            text-align: right;
        }

        .avatar-circle {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #a855f7 0%, #3b82f6 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 600;
            box-shadow: 0 4px 10px rgba(59, 130, 246, 0.3);
        }

        /* ป้ายบอกสถานะ/ฝ่าย */
        .role-badge {
            font-size: 0.85rem;
            padding: 0.5rem 1.2rem;
            border-radius: 50rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: <?php echo ($current_role == 'staff_operation' ? 'linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%)' : 'linear-gradient(135deg, #e0f2fe 0%, #bae6fd 100%)'); ?>;
            color: <?php echo ($current_role == 'staff_operation' ? '#166534' : '#0369a1'); ?>;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
        }

        /* การ์ดตาราง */
        .card-custom {
            border: none;
            border-radius: 24px;
            background: var(--card-bg);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
        }

        /* สไตล์ของตาราง */
        .table-custom {
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table-custom thead th {
            background-color: #f8fafc;
            color: var(--text-muted);
            font-weight: 600;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1.2rem 1.5rem;
            border-bottom: 2px solid var(--border-color);
        }
        
        .table-custom thead th:first-child { border-top-left-radius: 24px; }
        .table-custom thead th:last-child { border-top-right-radius: 24px; }

        .table-custom tbody tr {
            transition: all 0.2s ease-in-out;
            cursor: pointer;
        }

        .table-custom tbody tr:hover {
            background-color: #f8fafc;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.03);
            position: relative;
            z-index: 1;
        }

        .table-custom tbody td {
            padding: 1.2rem 1.5rem;
            vertical-align: middle;
            border-bottom: 1px solid #f1f5f9;
        }

        .table-custom tbody tr:last-child td {
            border-bottom: none;
        }

        /* ไอคอนหน้าชื่อเอกสาร */
        .doc-info-wrapper {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .doc-icon {
            width: 48px;
            height: 48px;
            background: #eef2ff;
            color: var(--primary-color);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            flex-shrink: 0;
        }

        /* ป้ายชื่อผู้ส่ง */
        .sender-badge {
            background-color: #f1f5f9;
            color: #475569;
            padding: 0.4rem 1rem;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.9rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            border: 1px solid #e2e8f0;
        }

        /* ปุ่มดูรายละเอียด */
        .btn-modern {
            background: linear-gradient(135deg, var(--primary-color) 0%, #3b82f6 100%);
            border: none;
            color: white;
            padding: 0.5rem 1.5rem;
            border-radius: 50rem;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.3s;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);
        }

        .btn-modern:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(79, 70, 229, 0.4);
            color: white;
        }

        /* หน้าจอว่างเปล่า (Empty State) */
        .empty-state {
            padding: 4rem 2rem;
        }
        
        .empty-state-icon {
            width: 80px;
            height: 80px;
            background: #f1f5f9;
            color: #94a3b8;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin: 0 auto 1.5rem auto;
        }

    /* Pagination Styles */
    .pagination .page-link { color: #4f46e5; border: none; margin: 0 4px; border-radius: 8px !important; font-weight: 500; transition: all 0.2s; }
    .pagination .page-item.active .page-link { background-color: #4f46e5; color: white; box-shadow: 0 4px 10px rgba(79, 70, 229, 0.3); }
    .pagination .page-link:hover:not(.active) { background-color: #e0e7ff; color: #4338ca; }
    </style>
</head>

<body>

    <?php require 'menu.php'; ?>

    <div class="content-wrapper">
        <div class="container mb-5">
            
            <div class='page-header'>
                <div class='d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3'>
                    <div>
                        <h3 class='fw-bold mb-2' style="color: var(--text-main);">งานที่ได้รับมอบหมาย</h3>
                        <span class='role-badge'>
                            <i class='bi bi-shield-check'></i>
                            <?php
                                if ( $current_role == 'staff_operation' ) echo 'ฝ่ายปฏิบัติการ';
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

            <div class='card card-custom'>
                <div class='card-body p-0'>
                    <div class='table-responsive'>
                        <table class='table table-custom align-middle'>
                            <thead>
                                <tr>
                                    <th class='ps-4'>ข้อมูลหนังสือ / รายละเอียด</th>
                                    <th>ผู้ส่งมอบงาน</th>
                                    <th class='text-center'>วันที่ได้รับ</th>
                                    <th class='text-center'>การจัดการ</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ( empty( $my_docs ) ): ?>
                                <tr>
                                    <td colspan='4' class='text-center empty-state'>
                                        <div class="empty-state-icon">
                                            <i class="bi bi-inbox"></i>
                                        </div>
                                        <h5 class="fw-bold text-dark">ยังไม่มีงานใหม่เข้ามาในขณะนี้</h5>
                                        <p class="text-muted">เมื่อมีงานที่ได้รับมอบหมาย จะแสดงในหน้านี้</p>
                                    </td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ( $my_docs as $doc ): ?>
                                    <tr>
                                        <td class='ps-4'>
                                            <div class="doc-info-wrapper">
                                                <div class="doc-icon">
                                                    <i class="bi bi-file-earmark-text"></i>
                                                </div>
                                                <div>
                                                    <div class='fw-bold text-dark mb-1' style="font-size: 1.05rem;">
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
                                                    </div>
                                                    <div class='small text-muted'>
                                                        <?php echo htmlspecialchars( $doc[ 'doc_detail' ] ); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class='sender-badge'>
                                                <i class="bi bi-person-circle text-secondary"></i>
                                                <?php
                                                if ( !empty( $doc[ 'sender_fname' ] ) ) {
                                                    echo htmlspecialchars( $doc[ 'sender_fname' ] . ' ' . $doc[ 'sender_lname' ] );
                                                } else {
                                                    echo 'ไม่ระบุชื่อ';
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td class='text-center'>
                                            <div class="fw-medium text-dark">
                                                <?php echo date( 'd/m/Y', strtotime( $doc[ 'received_date' ] ) ); ?>
                                            </div>
                                            <div class="small text-muted">
                                                <i class="bi bi-clock me-1"></i><?php echo date( 'H:i', strtotime( $doc[ 'received_date' ] ) ); ?> น.
                                            </div>
                                        </td>
                                        <td class='text-center'>
                                            <a href="view_detail.php?id=<?php echo $doc['id']; ?>" class='btn btn-modern'>
                                                <i class="bi bi-search me-1"></i> ดูรายละเอียด
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php if (isset($total_pages) && $total_pages > 1): ?>
        <div class="d-flex justify-content-center mt-5">
            <nav>
                <ul class="pagination pagination-sm mb-0 shadow-sm rounded-3 flex-wrap justify-content-center gap-1">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link px-3 py-2" href="?page=<?php echo $page - 1; ?>"><i class="bi bi-chevron-left"></i> ก่อนหน้า</a>
                    </li>
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $page == $i ? 'active' : ''; ?>">
                        <a class="page-link px-3 py-2" href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link px-3 py-2" href="?page=<?php echo $page + 1; ?>">ถัดไป <i class="bi bi-chevron-right"></i></a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>

        </div> </div> <?php require 'footer.php'; ?>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'></script>
</body>

</html>
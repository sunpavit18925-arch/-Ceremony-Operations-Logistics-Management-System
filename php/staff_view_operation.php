<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/security.php';

require_role([ROLE_STAFF_OPERATION, ROLE_SUPER_ADMIN]);

$conn = get_db();
$my_id = (int)($_SESSION['user_id'] ?? 0);
$error_msg = '';

try {
    $stmt_user = $conn->prepare('SELECT fname, lname, role, email FROM users WHERE id = ? LIMIT 1');
    $stmt_user->execute([$my_id]);
    $u_data = $stmt_user->fetch(PDO::FETCH_ASSOC);

    if (!$u_data) {
        auth_logout();
        header('Location: index.php?error=user_not_found');
        exit();
    }

    $_SESSION['user_fname'] = $u_data['fname'];
    $_SESSION['role']       = $u_data['role'];

    $user_name    = $u_data['fname'];
    $current_role = $u_data['role'];

    // 3. ดึงรายการหนังสือที่ส่งถึงคนนี้ (หรือทุกฉบับถ้าเป็น super_admin)
    $limit = 10;
    $page = clean_int($_GET['page'] ?? 1);
    if ($page < 1) $page = 1;
    $offset = ($page - 1) * $limit;

    if ($current_role === ROLE_SUPER_ADMIN) {
        $stmt_total = $conn->query("SELECT COUNT(*) FROM documents");
        $total_rows = $stmt_total->fetchColumn();
        $total_pages = ceil($total_rows / $limit);

        $sql_docs = "SELECT d.*, u.fname as sender_fname, u.lname as sender_lname 
                     FROM documents d
                     LEFT JOIN users u ON d.created_by = u.username_sso 
                     ORDER BY d.received_date DESC
                     LIMIT :limit OFFSET :offset";
        $stmt_docs = $conn->prepare($sql_docs);
    } else {
        $stmt_total = $conn->prepare("SELECT COUNT(*) FROM documents WHERE sent_to = :my_id");
        $stmt_total->execute([':my_id' => $my_id]);
        $total_rows = $stmt_total->fetchColumn();
        $total_pages = ceil($total_rows / $limit);

        $sql_docs = "SELECT d.*, u.fname as sender_fname, u.lname as sender_lname 
                     FROM documents d
                     LEFT JOIN users u ON d.created_by = u.username_sso 
                     WHERE d.sent_to = :my_id 
                     ORDER BY d.received_date DESC
                     LIMIT :limit OFFSET :offset";
        $stmt_docs = $conn->prepare($sql_docs);
        $stmt_docs->bindValue(':my_id', $my_id, PDO::PARAM_INT);
    }

    $stmt_docs->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt_docs->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt_docs->execute();
    $my_docs = $stmt_docs->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log('Database Error in staff_view_operation: ' . $e->getMessage());
    $error_msg = 'ขออภัย ระบบขัดข้องชั่วคราว กรุณาลองใหม่อีกครั้ง';
    $my_docs = [];
}
?>

<!DOCTYPE html>
<html lang='th'>

<head>
    <meta charset='UTF-8'>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>รายการงานมอบหมาย</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css'>
    <style>
    body {
        font-family: 'Prompt', sans-serif;
        background-color: #f8fafc;
    }

    .page-header {
        background: white;
        padding: 1.5rem;
        border-bottom: 1px solid #e2e8f0;
        margin-bottom: 2rem;
    }

    .role-badge {
        font-size: 0.8rem;
        padding: 0.4rem 1rem;
        border-radius: 50rem;
        font-weight: 600;
        background: <?php echo ($current_role=='staff_operation'? '#dcfce7' : '#e0f2fe');
        ?>;
        color: <?php echo ($current_role=='staff_operation'? '#166534' : '#0369a1');
        ?>;
    }

    .card-custom {
        border: none;
        border-radius: 15px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
    }

    /* Pagination Styles */
    .pagination .page-link { color: #0ea5e9; border: none; margin: 0 4px; border-radius: 8px !important; font-weight: 500; transition: all 0.2s; }
    .pagination .page-item.active .page-link { background-color: #0ea5e9; color: white; box-shadow: 0 4px 10px rgba(14, 165, 233, 0.3); }
    .pagination .page-link:hover:not(.active) { background-color: #e0f2fe; color: #0284c7; }
    </style>
    <link rel="stylesheet" href="css/responsive.css">
</head>

<body>

<?php require 'menu.php'; ?>

    <div class="content-wrapper">
        <div class="container mt-4 mb-5">
            
    </div>
    <div class='page-header shadow-sm'>
        <div class='container d-flex justify-content-between align-items-center'>
            <div>
                <h4 class='fw-bold mb-1'>งานที่ได้รับมอบหมาย</h4>
                <span class='role-badge'>
                    <i class='bi bi-shield-check me-1'></i>
                    <?php
if ( $current_role == 'staff_operation' ) echo 'ฝ่ายปฏิบัติการ';
elseif ( $current_role == 'staff_vehicle' ) echo 'ฝ่ายยานพาหนะ';
else echo 'ไม่ระบุฝ่าย (' . htmlspecialchars( $current_role ) . ')';
?>
                </span>
            </div>
            <div class='text-end'>
                <small class='text-muted'>ผู้ใช้งาน:</small>
                <div class='fw-bold'><?php echo htmlspecialchars( $user_name );
?></div>
            </div>
        </div>
    </div>

    <div class='container'>
        <div class='card card-custom overflow-hidden'>
            <div class='card-body p-0'>
                <div class='table-responsive'>
                    <table class='table table-hover align-middle mb-0'>
                        <thead class='table-light'>
                            <tr>
                                <th class='ps-4'>ข้อมูลหนังสือ</th>
                                <th>ส่งมาจากหัวหน้า</th>
                                <th class='text-center'>วันที่ได้รับ</th>
                                <th class='text-center'>จัดการ</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ( empty( $my_docs ) ): ?>
                            <tr>
                                <td colspan='4' class='text-center py-5 text-muted'>ยังไม่มีงานในขณะนี้</td>
                            </tr>
                            <?php else: ?>
                            <?php foreach ( $my_docs as $doc ): ?>
                            <tr>
                                <td class='ps-4'>
                                    <div class='fw-bold'><?php echo htmlspecialchars( $doc[ 'doc_name' ] );
?></div>
                                    <div class='small text-muted'><?php echo htmlspecialchars( $doc[ 'doc_detail' ] );
?></div>
                                </td>
                                <td>
                                    <span class='badge bg-light text-dark border'>
                                        <?php
if ( !empty( $doc[ 'sender_fname' ] ) ) {
    // แสดงชื่อตามด้วยนามสกุล
    echo htmlspecialchars( $doc[ 'sender_fname' ] . ' ' . $doc[ 'sender_lname' ] );
} else {
    echo 'ไม่ระบุชื่อ';
}
?>
                                    </span>
                                </td>
                                <td class='text-center small'>
                                    <?php echo date( 'd/m/Y H:i', strtotime( $doc[ 'received_date' ] ) );
?>
                                </td>
                                <td class='text-center'>
                                    <a href="view_detail.php?id=<?php echo $doc['id']; ?>"
                                        class='btn btn-sm btn-primary rounded-pill px-3'>
                                        ดูรายละเอียด
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach;
?>
                            <?php endif;
?>
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
    </div>

    </div>

    <?php require 'footer.php'; ?>
    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'></script>
</body>

</html>
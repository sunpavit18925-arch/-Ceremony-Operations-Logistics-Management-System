<?php
/**
 * Head of Operation Management View
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/equipment.php';
require_once __DIR__ . '/controllers/manage_operation_controller.php';

require_role([ROLE_HEAD_OPERATION, ROLE_SUPER_ADMIN]);

// Handle all mutation actions (DELETE, UNASSIGN, ADD COORD, REMOVE COORD, MANAGE SETS, BORROW, RETURN)
handle_manage_operation_actions();

$conn = get_db_connection();
// --- ดึงข้อมูลมาแสดง ---
$docs = [];

$assignments_by_job = [];

$staff_list = [];

$available_sets = [];

$master_sets = [];

$coords_by_doc = [];

$jobs_by_coord = [];

$jobs_by_set = [];
$equipments_list = [];
$query_string = '';
try {
    if ( isset( $conn ) ) {
        // --- ส่วนที่เพิ่มใหม่: จัดการตัวกรองข้อมูล ( Search/Filter ) ---
        $search_text = trim( $_GET[ 'search_text' ] ?? '' );
        $search_date = $_GET[ 'search_date' ] ?? '';
        $search_type = $_GET[ 'search_type' ] ?? '';
        $search_status = $_GET[ 'search_status' ] ?? '';

        $where_clauses = [ '1=1' ];
        $params = [];

        // กรองชื่องาน หรือ รหัสงาน
        if ( !empty( $search_text ) ) {
            $where_clauses[] = '(d.doc_name LIKE ? OR d.doc_number LIKE ?)';
            $params[] = "%$search_text%";
            $params[] = "%$search_text%";
        }

        // กรองวันที่ ( เทียบเฉพาะวันที่ ไม่รวมเวลา )
        if ( !empty( $search_date ) ) {
            $where_clauses[] = 'DATE(d.received_date) = ?';
            $params[] = $search_date;
        }

        // กรองประเภทงาน
        if ( !empty( $search_type ) ) {
            $where_clauses[] = 'd.job_type = ?';
            $params[] = $search_type;
        }

        // กรองสถานะ ( ใช้เงื่อนไข Subquery ตรวจสอบตารางที่เกี่ยวข้อง )
        if ( !empty( $search_status ) ) {
            if ( $search_status === 'unassigned' ) {
                $where_clauses[] = 'NOT EXISTS (SELECT 1 FROM document_assignments da WHERE da.doc_id = d.id)';
            } elseif ( $search_status === 'pending' ) {
                $where_clauses[] = 'EXISTS (SELECT 1 FROM document_assignments da WHERE da.doc_id = d.id AND da.is_acknowledged = 0)';
            } elseif ( $search_status === 'acknowledged' ) {
                $where_clauses[] = "EXISTS (SELECT 1 FROM document_assignments da WHERE da.doc_id = d.id) 
                                    AND NOT EXISTS (SELECT 1 FROM document_assignments da WHERE da.doc_id = d.id AND da.is_acknowledged = 0)";
            } elseif ( $search_status === 'set_assigned' ) {
                $where_clauses[] = "EXISTS (SELECT 1 FROM job_assignments ja WHERE ja.job_id = d.id AND ja.staff_role IS NOT NULL AND ja.staff_role != '')";
            }
        }

        $where_sql = implode( ' AND ', $where_clauses );

        // --- Pagination Setup ---
        $limit = 10;
        $page = isset( $_GET[ 'page' ] ) && is_numeric( $_GET[ 'page' ] ) ? ( int )$_GET[ 'page' ] : 1;
        if ( $page < 1 ) $page = 1;
        $offset = ( $page - 1 ) * $limit;

        $stmt_count = $conn->prepare( "SELECT COUNT(*) FROM documents d WHERE $where_sql" );
        $stmt_count->execute( $params );
        $total_rows = $stmt_count->fetchColumn();
        $total_pages = ceil( $total_rows / $limit );

        // 1. ดึงข้อมูลงานทั้งหมด ( ใช้ตัวกรอง ) พร้อมแบ่งหน้า
        $stmt_docs = $conn->prepare( "SELECT d.* FROM documents d WHERE $where_sql ORDER BY d.id DESC LIMIT ? OFFSET ?" );
        $param_idx = 1;
        foreach ( $params as $val ) {
            $stmt_docs->bindValue( $param_idx++, $val );
        }
        $stmt_docs->bindValue( $param_idx++, $limit, PDO::PARAM_INT );
        $stmt_docs->bindValue( $param_idx, $offset, PDO::PARAM_INT );
        $stmt_docs->execute();
        $docs = $stmt_docs->fetchAll();

        if ( !empty( $search_text ) ) $query_string .= '&search_text='.urlencode( $search_text );
        if ( !empty( $search_date ) ) $query_string .= '&search_date='.urlencode( $search_date );
        if ( !empty( $search_type ) ) $query_string .= '&search_type='.urlencode( $search_type );
        if ( !empty( $search_status ) ) $query_string .= '&search_status='.urlencode( $search_status );

        // 1.5 ดึงข้อมูลผู้ประสานงานที่ถูกมอบหมาย ( Multi-Coordinators ) พร้อมเช็คการรายงานประชุม
        $all_assigned_coords = $conn->query( "
            SELECT da.doc_id, da.user_id, da.is_acknowledged, u.fname, u.lname,
                   (SELECT COUNT(*) FROM meeting_reports mr WHERE mr.job_id = da.doc_id) as has_report,
                   (SELECT created_at FROM system_logs sl WHERE sl.job_id = da.doc_id AND sl.action_type = 'ASSIGN_STAFF' AND sl.action_detail LIKE CONCAT('%', u.fname, '%', u.lname, '%') ORDER BY sl.id DESC LIMIT 1) as assigned_date
            FROM document_assignments da
            JOIN users u ON da.user_id = u.id
        " )->fetchAll();

        $coords_by_doc = [];
        foreach ( $all_assigned_coords as $c ) {
            $coords_by_doc[ $c[ 'doc_id' ] ][] = $c;
        }

        // 2. ดึงข้อมูลการจัดชุดทั้งหมด
        $all_assign_for_status = $conn->query( 'SELECT job_id, staff_name, staff_role FROM job_assignments' )->fetchAll();
        $assignments_by_job = [];
        foreach ( $all_assign_for_status as $a ) {
            $assignments_by_job[ $a[ 'job_id' ] ][] = $a;
        }

        // 3. ดึงข้อมูล Master Sets
        $sql_master = "
            SELECT DISTINCT staff_role, staff_name 
            FROM job_assignments 
            WHERE staff_name != 'รอระบุบุคคล' 
            ORDER BY staff_role ASC, staff_name ASC
        ";
        $all_master_assign = $conn->query( $sql_master )->fetchAll();
        $master_sets = [];

        foreach ( $all_master_assign as $a ) {
            $setName = $a[ 'staff_role' ];

            if ( !empty( $setName ) ) {
                if ( !isset( $master_sets[ $setName ] ) ) {
                    $master_sets[ $setName ] = [];
                }
                $master_sets[ $setName ][] = $a[ 'staff_name' ];
            }
        }
        uksort( $master_sets, 'strnatcmp' );
        $available_sets = array_keys( $master_sets );

        // 4. ดึงรายชื่อเฉพาะผู้ประสานงานทั้งหมด
        $stmt_all_staff = $conn->prepare( "
            SELECT id, fname, lname 
            FROM users 
            WHERE role = 'coordinator_operation' 
            ORDER BY fname ASC
        " );
        $stmt_all_staff->execute();
        $staff_list = $stmt_all_staff->fetchAll();

        $thai_months_short = [
            1 => 'ม.ค.', 2 => 'ก.พ.', 3 => 'มี.ค.', 4 => 'เม.ย.',
            5 => 'พ.ค.', 6 => 'มิ.ย.', 7 => 'ก.ค.', 8 => 'ส.ค.',
            9 => 'ก.ย.', 10 => 'ต.ค.', 11 => 'พ.ย.', 12 => 'ธ.ค.'
        ];

        // 4.5 ดึงงานทั้งหมดของผู้ประสานงานเพื่อไปแสดงใน Modal ย่อย
        $jobs_by_coord = [];
        $stmt_jobs_coord = $conn->query( "
            SELECT da.user_id, d.id as doc_id, d.doc_name, d.doc_number, d.received_date, d.dpt_work_start, d.dpt_work_end
            FROM document_assignments da
            JOIN documents d ON da.doc_id = d.id
            ORDER BY d.dpt_work_start ASC, d.received_date DESC
        " );
        $all_coord_jobs = $stmt_jobs_coord->fetchAll();
        foreach ( $all_coord_jobs as $cj ) {
            $start = $cj[ 'dpt_work_start' ];
            $end = $cj[ 'dpt_work_end' ];

            $date_text = 'ยังไม่ระบุเวลาทำการ';
            if ( $start && $end ) {
                $s_d = date( 'j', strtotime( $start ) );
                $s_m = $thai_months_short[ ( int )date( 'm', strtotime( $start ) ) ];
                $s_y = date( 'Y', strtotime( $start ) ) + 543;
                $e_d = date( 'j', strtotime( $end ) );
                $e_m = $thai_months_short[ ( int )date( 'm', strtotime( $end ) ) ];
                $e_y = date( 'Y', strtotime( $end ) ) + 543;

                if ( $start == $end ) {
                    $date_text = "$s_d $s_m $s_y";
                } elseif ( $s_m == $e_m && $s_y == $e_y ) {
                    $date_text = "$s_d - $e_d $s_m $s_y";
                } elseif ( $s_y == $e_y ) {
                    $date_text = "$s_d $s_m - $e_d $e_m $s_y";
                } else {
                    $date_text = "$s_d $s_m $s_y - $e_d $e_m $e_y";
                }
            } elseif ( $start ) {
                $s_d = date( 'j', strtotime( $start ) );
                $s_m = $thai_months_short[ ( int )date( 'm', strtotime( $start ) ) ];
                $s_y = date( 'Y', strtotime( $start ) ) + 543;
                $date_text = "เริ่ม $s_d $s_m $s_y";
            }

            $cj[ 'date_text' ] = $date_text;
            $jobs_by_coord[ $cj[ 'user_id' ] ][] = $cj;
        }

        // 5. ดึงข้อมูลภาระงานของแต่ละชุด ( ดึงจากระยะเวลา กอพ. dpt_work_start และ dpt_work_end )
        $jobs_by_set = [];
        $sql_set_jobs = "
            SELECT ja.staff_role, d.doc_name, d.dpt_work_start, d.dpt_work_end
            FROM job_assignments ja
            JOIN documents d ON ja.job_id = d.id
            WHERE ja.staff_role IS NOT NULL AND ja.staff_role != '' 
            GROUP BY ja.staff_role, ja.job_id, d.doc_name, d.dpt_work_start, d.dpt_work_end
            ORDER BY d.dpt_work_start ASC
        ";
        $stmt_set_jobs = $conn->prepare( $sql_set_jobs );
        $stmt_set_jobs->execute();
        $all_set_jobs = $stmt_set_jobs->fetchAll();

        foreach ( $all_set_jobs as $sj ) {
            $role = $sj[ 'staff_role' ];
            $start = $sj[ 'dpt_work_start' ];
            $end = $sj[ 'dpt_work_end' ];

            $date_text = 'ยังไม่ระบุเวลาทำการ';
            if ( $start && $end ) {
                $s_d = date( 'j', strtotime( $start ) );
                $s_m = $thai_months_short[ ( int )date( 'm', strtotime( $start ) ) ];
                $s_y = date( 'Y', strtotime( $start ) ) + 543;
                $e_d = date( 'j', strtotime( $end ) );
                $e_m = $thai_months_short[ ( int )date( 'm', strtotime( $end ) ) ];
                $e_y = date( 'Y', strtotime( $end ) ) + 543;

                if ( $start == $end ) {
                    $date_text = "$s_d $s_m $s_y";
                } elseif ( $s_m == $e_m && $s_y == $e_y ) {
                    $date_text = "$s_d - $e_d $s_m $s_y";
                } elseif ( $s_y == $e_y ) {
                    $date_text = "$s_d $s_m - $e_d $e_m $s_y";
                } else {
                    $date_text = "$s_d $s_m $s_y - $e_d $e_m $e_y";
                }
            } elseif ( $start ) {
                $s_d = date( 'j', strtotime( $start ) );
                $s_m = $thai_months_short[ ( int )date( 'm', strtotime( $start ) ) ];
                $s_y = date( 'Y', strtotime( $start ) ) + 543;
                $date_text = "เริ่ม $s_d $s_m $s_y";
            }

            $jobs_by_set[ $role ][] = [
                'doc_name' => $sj[ 'doc_name' ],
                'date_text' => $date_text
            ];
        }

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

    }
} catch ( PDOException $e ) {
    die( 'เกิดข้อผิดพลาดในการดึงข้อมูลมาแสดง: ' . $e->getMessage() );
}

function getBadgeClass( $type ) {
    switch ( $type ) {
        case 'รับเสด็จฯ': case 'รับเสด็จ': return 'bg-warning text-dark';
        case 'ขอความอนุเคราะห์': return 'bg-info text-dark';
        default: return 'bg-primary text-white';

    }
}
?>

<!DOCTYPE html>
<html lang='th'>

<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>ระบบรับข้อมูลงาน | Admin</title>
    <link href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' rel='stylesheet'>
    <link href='https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap' rel='stylesheet'>
    <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css'>
    <style>
    html,
    body {
        height: 100%;
    }

    body {
        display: flex;
        flex-direction: column;
        font-family: 'Prompt', sans-serif;
        background-color: #f8f9fa;
    }

    .content-wrapper {
        flex: 1 0 auto;
    }

    footer {
        flex-shrink: 0;
    }

    .page-header {
        background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        color: white;
        padding: 2.5rem 0;
        margin-bottom: -3rem;
        border-radius: 0 0 1.5rem 1.5rem;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
    }

    .custom-card {

        border: none;
        border-radius: 1.25rem;
        box-shadow: 0 0.5rem 1.5rem rgba(0, 0, 0, 0.08);

        background: #ffffff;
        margin-bottom: 2rem;
        overflow: hidden;
    }

    .form-control,
    .form-select {
        border-radius: 0.75rem;
        border: 1px solid #dee2e6;
        padding: 0.75rem 1rem;
    }

    .form-control:focus,
    .form-select:focus {
        border-color: #2a5298;
        box-shadow: 0 0 0 0.25rem rgba(42, 82, 152, 0.15);
    }

    .table-custom thead th {
        background-color: #f1f4f8;
        color: #495057;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        border-bottom: none;
        padding: 1rem;
    }

    .table-custom tbody td {
        padding: 1rem;
        vertical-align: middle;
        border-bottom: 1px solid #f1f4f8;
    }

    .badge-custom {
        padding: 0.5em 1em;
        border-radius: 2rem;
        font-weight: 500;
        letter-spacing: 0.3px;
    }

    .btn-action {
        width: 35px;
        height: 35px;
        border-radius: 0.5rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
        border: none;
    }

    .btn-set {
        background-color: #e2e3e5;
        color: #383d41;
        border: 1px solid #d6d8db;
    }

    .btn-set:hover {
        background-color: #5c636a;
        color: #fff;
        border-color: #5c636a;
    }

    .btn-staff {
        background-color: #cfe2ff;
        color: #084298;
        border: 1px solid #b6d4fe;
    }

    .btn-staff:hover {
        background-color: #0d6efd;
        color: #fff;
        border-color: #0d6efd;
    }

    .modal-content {
        border-radius: 1.25rem;
        border: none;
    }

    .staff-list-item {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
        padding: 0.5rem 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    /* Pagination */
    .pagination .page-link {
        color: #2a5298;
        border-radius: 0.5rem !important;
        margin: 0 3px;
        border: 1px solid #e2e8f0;
    }

    .pagination .page-item.active .page-link {
        background-color: #2a5298;
        border-color: #2a5298;
        color: white;
    }

    .doc-name-link {
        transition: color 0.2s ease;
    }

    .doc-name-link:hover {
        color: #0d6efd !important;
    }
    </style>
</head>

<body>

    <?php include 'menu.php';
?>

    <div class='content-wrapper'>
        <div class='page-header text-center'>
            <div class='container position-relative'>
                <div class='row align-items-center'>
                                    <div class='col-12 col-md-2 text-start mb-3 mb-md-0'>
                        <a href='home_head_operation.php'
                            class='btn btn-outline-light rounded-pill d-inline-flex align-items-center'
                            style='font-weight: 500; padding: 0.4rem 1.2rem; transition: all 0.2s;'>
                            <i class='bi bi-arrow-left me-2'></i>ย้อนกลับ
                        </a>
                    </div>
                    <div class='col-12 col-md-8 text-center'>
                        <h2 class='fw-bold mb-0'><i
                                class='bi bi-inbox-fill me-2'></i>มอบหมายผู้ประสานงานและจัดชุดสำหรับไปปฏิบัติงาน</h2>
                    </div>
                    <div class='col-12 col-md-2'></div>
                </div>
                <br>
            </div>
        </div>

        <div class='container' style='position: relative;'>
            <div class='row justify-content-center'>
                <div class='col-lg-12'>

                    <?php if ( isset( $_GET[ 'success' ] ) ): ?>
                    <div
                        class='alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close'>
                        <i class='bi bi-check-circle-fill fs-4 me-3 text-success'></i>
                        <div>
                            <h6 class='mb-0 fw-bold'>เพิ่มข้อมูลสำเร็จ!</h6>
                        </div>
                        <button type='button' class='btn-close ms-auto' data-bs-dismiss='alert'></button>
                    </div>
                    <?php endif;
?>

                    <?php if ( isset( $_GET[ 'set_success' ] ) ): ?>
                    <div
                        class='alert alert-primary border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close'>
                        <i class='bi bi-diagram-3-fill fs-4 me-3 text-primary'></i>
                        <div>
                            <h6 class='mb-0 fw-bold'>บันทึกชื่อชุดปฏิบัติงานสำเร็จ!</h6>
                        </div>
                        <button type='button' class='btn-close ms-auto' data-bs-dismiss='alert'></button>
                    </div>
                    <?php endif;
?>

                    <?php if ( isset( $_GET[ 'coord_success' ] ) ): ?>
                    <div
                        class='alert alert-info border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close'>
                        <i class='bi bi-person-plus-fill fs-4 me-3 text-info'></i>
                        <div>
                            <h6 class='mb-0 fw-bold'>มอบหมายผู้ประสานงานสำเร็จ!</h6>
                        </div>
                        <button type='button' class='btn-close ms-auto' data-bs-dismiss='alert'></button>
                    </div>
                    <?php endif;
?>

                    <?php if ( isset( $_GET[ 'staff_success' ] ) ): ?>
                    <div
                        class='alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close'>
                        <i class='bi bi-person-check-fill fs-4 me-3 text-success'></i>
                        <div>
                            <h6 class='mb-0 fw-bold'>เพิ่มบุคลากรเข้าชุดสำเร็จ!</h6>
                        </div>
                        <button type='button' class='btn-close ms-auto' data-bs-dismiss='alert'></button>
                    </div>
                    <?php endif;
?>

                    <?php if ( isset( $_GET[ 'staff_del_success' ] ) || isset( $_GET[ 'coord_del_success' ] ) ): ?>
                    <div
                        class='alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close'>
                        <i class='bi bi-person-x-fill fs-4 me-3 text-danger'></i>
                        <div>
                            <h6 class='mb-0 fw-bold'>ลบข้อมูลบุคลากรเรียบร้อยแล้ว!</h6>
                        </div>
                        <button type='button' class='btn-close ms-auto' data-bs-dismiss='alert'></button>
                    </div>
                    <?php endif;
?>

                    <?php if (!empty($_SESSION['operation_error'])): ?>
                    <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                        <div>
                            <h6 class="mb-0 fw-bold">เกิดข้อผิดพลาด!</h6>
                            <small><?php echo htmlspecialchars($_SESSION['operation_error']); unset($_SESSION['operation_error']); ?></small>
                        </div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <?php if(isset($_GET['borrow_success'])): ?>
                    <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close">
                        <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                        <div><h6 class="mb-0 fw-bold">บันทึกการยืมอุปกรณ์เรียบร้อยแล้ว!</h6></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <?php if(isset($_GET['return_success'])): ?>
                    <div class="alert alert-success border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close">
                        <i class="bi bi-check-circle-fill fs-4 me-3 text-success"></i>
                        <div><h6 class="mb-0 fw-bold">บันทึกการคืนอุปกรณ์เรียบร้อยแล้ว!</h6></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>
                    <?php if(isset($_GET['borrow_error']) || isset($_GET['return_error'])): ?>
                    <div class="alert alert-danger border-0 shadow-sm rounded-4 mb-4 d-flex align-items-center auto-close">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3 text-danger"></i>
                        <div><h6 class="mb-0 fw-bold">เกิดข้อผิดพลาด!</h6>
                        <small><?php echo htmlspecialchars($_GET['borrow_error'] ?? $_GET['return_error']); ?></small></div>
                        <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
                    </div>
                    <?php endif; ?>

                    <!-- ส่วนที่เพิ่มใหม่: ฟอร์มค้นหา ( Search Form ) -->
                    <div class='card border-0 shadow-sm rounded-4 mb-4 bg-white'>
                        <div class='card-body p-4'>
                            <form method='GET' action='manage_operation2.php' class='row g-3 align-items-end'>
                                <div class='col-md-3'>
                                    <label class='form-label small fw-bold text-muted'>ค้นหา ( รหัสงาน / ชื่องาน
                                        )</label>
                                    <input type='text' name='search_text' class='form-control'
                                        placeholder='พิมพ์คำค้นหา...'
                                        value="<?php echo htmlspecialchars($search_text ?? ''); ?>">
                                </div>
                                <div class='col-md-2'>
                                    <label class='form-label small fw-bold text-muted'>วันที่รับเรื่อง</label>
                                    <input type='date' name='search_date' class='form-control'
                                        value="<?php echo htmlspecialchars($search_date ?? ''); ?>">
                                </div>
                                <div class='col-md-3'>
                                    <label class='form-label small fw-bold text-muted'>ประเภทงาน</label>
                                    <select name='search_type' class='form-select'>
                                        <option value=''>-- ทั้งหมด --</option>
                                        <?php
// ดึงประเภทงานที่ไม่ซ้ำกันมาแสดงใน Dropdown
$type_stmt = $conn->query( "SELECT DISTINCT job_type FROM documents WHERE job_type IS NOT NULL AND job_type != ''" );
while( $type_row = $type_stmt->fetch( PDO::FETCH_ASSOC ) ):
$selected = ( ( $search_type ?? '' ) == $type_row[ 'job_type' ] ) ? 'selected' : '';
?>
                                        <option value="<?php echo htmlspecialchars($type_row['job_type']); ?>" <?php echo $selected;
?>>
                                            <?php echo htmlspecialchars( $type_row[ 'job_type' ] );
?>
                                        </option>
                                        <?php endwhile;
?>
                                    </select>
                                </div>
                                <div class='col-md-2'>
                                    <label class='form-label small fw-bold text-muted'>สถานะ</label>
                                    <select name='search_status' class='form-select'>
                                        <option value=''>-- ทั้งหมด --</option>
                                        <option value='unassigned' <?php echo ( $search_status ?? '' ) === 'unassigned' ? 'selected' : '';
?>>ยังไม่มอบหมาย</option>
                                        <option value='pending' <?php echo ( $search_status ?? '' ) === 'pending' ? 'selected' : '';
?>>รอรับทราบ</option>
                                        <option value='acknowledged' <?php echo ( $search_status ?? '' ) === 'acknowledged' ? 'selected' : '';
?>>รับทราบแล้วทุกคน</option>
                                        <option value='set_assigned' <?php echo ( $search_status ?? '' ) === 'set_assigned' ? 'selected' : '';
?>>จัดชุดแล้ว</option>
                                    </select>
                                </div>
                                <div class='col-md-2 d-flex gap-2'>
                                    <button type='submit' class='btn btn-primary w-100 fw-bold shadow-sm'><i
                                            class='bi bi-search me-1'></i> ค้นหา</button>
                                    <a href='manage_operation2.php' class='btn btn-light w-100 fw-bold border'><i
                                            class='bi bi-arrow-counterclockwise me-1'></i> ล้าง</a>
                                </div>
                            </form>
                        </div>
                    </div>
                    <!-- สิ้นสุดฟอร์มค้นหา -->

                    <div class='card custom-card mb-5'>
                        <div
                            class='card-header bg-white border-bottom-0 pt-4 pb-3 px-4 d-flex justify-content-between align-items-center flex-wrap gap-2'>
                            <h5 class='fw-bold text-dark mb-0'>
                                <i class='bi bi-list-columns-reverse me-2 text-primary'></i>รายการงานหนังสือ (
                                การประสานงาน )
                            </h5>
                            <button type="button" class="btn btn-outline-info fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#equipmentModal">
                                <i class="bi bi-tools me-1"></i> รายการอุปกรณ์
                            </button>
                        </div>

                        <div class='card-body p-0'>
                            <div class='table-responsive bg-white rounded-3 shadow-sm border'
                                style='-webkit-overflow-scrolling: touch;'>
                                <table class='table table-hover table-custom mb-0 align-middle'
                                    style='table-layout: fixed; width: 100%; min-width: 1050px;'>
                                    <thead class='table-light'>
                                        <tr>
                                            <th class='ps-4' style='width: 10%;'>วันที่</th>
                                            <th style='width: 10%;'>รหัสงาน</th>
                                            <th style='width: 14%;'>ประเภท</th>
                                            <th style='width: 25%;'>ชื่องาน / รายละเอียด</th>
                                            <th class='text-center' style='width: 27%;'>ดำเนินการมอบหมาย</th>
                                            <th class='text-center pe-4' style='width: 14%;'>จัดการชุด</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if ( empty( $docs ) ): ?>
                                        <tr>
                                            <td colspan='6' class='text-center py-5 text-muted border-0'>
                                                <i class='bi bi-folder-x fs-1 d-block mb-3 text-light'></i>
                                                <span class='fw-semibold'>ยังไม่มีข้อมูลในระบบ</span>
                                            </td>
                                        </tr>
                                        <?php endif;
?>

                                        <?php foreach ( $docs as $doc ):
$has_sets = isset( $assignments_by_job[ $doc[ 'id' ] ] ) && count( $assignments_by_job[ $doc[ 'id' ] ] ) > 0;

// ข้อมูลผู้ประสานงานของเอกสารนี้
$assigned_coords = $coords_by_doc[ $doc[ 'id' ] ] ?? [];
$coord_count = count( $assigned_coords );

// คำนวณจำนวนคนที่รับทราบแล้ว
$ack_count = 0;
foreach ( $assigned_coords as $c ) {
    if ( isset( $c[ 'is_acknowledged' ] ) && $c[ 'is_acknowledged' ] == 1 ) {
        $ack_count++;
    }
}

$is_fully_acknowledged = ( $coord_count > 0 && $ack_count == $coord_count );
$is_partially_acknowledged = ( $ack_count > 0 && $ack_count < $coord_count );
// ถือว่ามีคนรับทราบแล้ว ( ใช้สำหรับปลดล็อกจัดชุด )
$has_acknowledged = ( $ack_count > 0 );

// ต้องมีการรายงานการประชุมแล้ว ถึงจะสามารถจัดชุดปฏิบัติงานได้
$has_report = array_sum( array_column( $assigned_coords, 'has_report' ) ) > 0;
?>
                                        <tr>
                                            <td class='ps-4'>
                                                <div class='d-flex flex-column'>
                                                    <span class='fw-bold text-dark' style='font-size: 0.9rem;'>
                                                        <i class='bi bi-calendar3 me-1 text-secondary'></i>
                                                        <?php echo date( 'd/m/Y', strtotime( $doc[ 'received_date' ] ) );
?>
                                                    </span>
                                                    <small class='text-muted'>
                                                        <i class='bi bi-clock me-1'></i>
                                                        <?php echo date( 'H:i', strtotime( $doc[ 'received_date' ] ) );
?> น.
                                                    </small>
                                                </div>
                                            </td>

                                            <td class='text-primary' style='max-width: 200px;'>
                                                <div class='fw-bold'>
                                                    <div class='text-content text-truncate' style='max-width: 180px;'>
                                                        <?php echo htmlspecialchars( $doc[ 'doc_number' ] ?: '-' );
?>
                                                    </div>

                                                    <?php if ( $doc[ 'doc_number' ] && $doc[ 'doc_number' ] !== '-' ): ?>
                                                    <div class='mt-1 btn-toggle-wrapper d-none'>
                                                        <small class='text-muted'
                                                            style='cursor: pointer; text-decoration: underline; font-size: 0.75rem;'
                                                            onclick='toggleText(this)'>
                                                            อ่านต่อ
                                                        </small>
                                                    </div>
                                                    <?php endif;
?>
                                                </div>
                                            </td>

                                            <td style='word-break: break-all;'>
                                                <?php
$job_type_other_val = $doc[ 'job_type_other' ] ?? '';
$job_type_text = ( $doc[ 'job_type' ] === 'อื่นๆ' && !empty( $job_type_other_val ) ) ? 'อื่นๆ: ' . $job_type_other_val : $doc[ 'job_type' ];
$badge_class = getBadgeClass( $doc[ 'job_type' ] );
echo '<span class="badge badge-custom ' . $badge_class . '" style="white-space: normal; text-align: left; display: inline-block; max-width: 100%;">';
echo htmlspecialchars( $job_type_text );
echo '</span>';
?>
                                            </td>

                                            <td style='word-break: break-word; min-width: 250px;'>
                                                <?php
$doc_name = htmlspecialchars( $doc[ 'doc_name' ] );
$max_len = 50;

$unique_id = uniqid( 'doc_' );

?>

                                                <div class='text-dark fw-medium'>
                                                    <?php if ( mb_strlen( $doc_name, 'UTF-8' ) > $max_len ): ?>
                                                    <span id="short_<?php echo $unique_id; ?>">
                                                        <a href='javascript:void(0);'
                                                            class='text-dark text-decoration-none doc-name-link'
                                                            data-bs-toggle='modal'
                                                            data-bs-target="#viewDocModal<?php echo $doc['id']; ?>"
                                                            title='คลิกเพื่อดูรายละเอียดงาน'>
                                                            <?php echo mb_substr( $doc_name, 0, $max_len, 'UTF-8' ) . '...';
?>
                                                        </a>&nbsp;
                                                        <a href='javascript:void(0)' class='text-primary fw-normal'
                                                            style='font-size: 0.8rem; text-decoration: underline; white-space: nowrap;'
                                                            onclick="document.getElementById('short_<?php echo $unique_id; ?>').style.display='none'; document.getElementById('full_<?php echo $unique_id; ?>').style.display='inline';">อ่านต่อ</a>
                                                    </span>
                                                    <span id="full_<?php echo $unique_id; ?>" style='display: none;'>
                                                        <a href='javascript:void(0);'
                                                            class='text-dark text-decoration-none doc-name-link'
                                                            data-bs-toggle='modal'
                                                            data-bs-target="#viewDocModal<?php echo $doc['id']; ?>"
                                                            title='คลิกเพื่อดูรายละเอียดงาน'>
                                                            <?php echo $doc_name;
?>
                                                        </a>&nbsp;
                                                        <a href='javascript:void(0)' class='text-muted fw-normal'
                                                            style='font-size: 0.8rem; text-decoration: underline; white-space: nowrap;'
                                                            onclick="document.getElementById('full_<?php echo $unique_id; ?>').style.display='none'; document.getElementById('short_<?php echo $unique_id; ?>').style.display='inline';">ย่อลง</a>
                                                    </span>
                                                    <?php else: ?>
                                                    <a href='javascript:void(0);'
                                                        class='text-dark text-decoration-none doc-name-link'
                                                        data-bs-toggle='modal'
                                                        data-bs-target="#viewDocModal<?php echo $doc['id']; ?>"
                                                        title='คลิกเพื่อดูรายละเอียดงาน'>
                                                        <?php echo $doc_name;
?>
                                                    </a>
                                                    <?php endif;
?>
                                                </div>

                                                <!-- จัดปุ่มรายละเอียดไว้ซ้าย และป้ายสถานะรับทราบไว้ขวาในบรรทัดเดียวกัน -->
                                                <div
                                                    class='d-flex flex-wrap justify-content-between align-items-center mt-3 gap-2'>
                                                    <!-- ปุ่มเปิดดูรายละเอียดงานแบบมินิมอล -->
                                                    <button type='button'
                                                        class='btn btn-sm btn-outline-primary rounded-pill d-inline-flex align-items-center shadow-sm'
                                                        data-bs-toggle='modal'
                                                        data-bs-target="#viewDocModal<?php echo $doc['id']; ?>"
                                                        style='font-size: 0.75rem; padding: 0.2rem 0.75rem; border-width: 1px; transition: all 0.2s;'>
                                                        <i class='bi bi-eye-fill me-1'></i> ดูรายละเอียด
                                                    </button>

                                                    <?php if ( $coord_count > 0 ): ?>
                                                    <div class='text-end'>
                                                        <?php if ( $is_fully_acknowledged ): ?>
                                                        <span
                                                            class='badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2 py-1 fw-medium'
                                                            style='font-size: 0.75rem;'>
                                                            <i class='bi bi-check-circle-fill me-1'></i>รับทราบแล้วทุกคน
                                                            ( <?php echo $ack_count.'/'.$coord_count;
?> )
                                                        </span>
                                                        <?php elseif ( $is_partially_acknowledged ): ?>
                                                        <span
                                                            class='badge rounded-pill bg-info-subtle text-info border border-info-subtle px-2 py-1 fw-medium'
                                                            title='รับทราบเพียงบางส่วน ก็สามารถดำเนินงานขั้นต่อไปได้'
                                                            style='font-size: 0.75rem;'>
                                                            <i
                                                                class='bi bi-check-circle-fill me-1'></i>รับทราบแล้วบางส่วน
                                                            ( <?php echo $ack_count.'/'.$coord_count;
?> )
                                                        </span>
                                                        <?php else: ?>
                                                        <span
                                                            class='badge rounded-pill bg-warning-subtle text-dark border border-warning-subtle px-2 py-1 fw-medium'
                                                            style='font-size: 0.75rem;'>
                                                            <i
                                                                class='bi bi-clock-history me-1'></i>รอผู้ประสานงานรับทราบ
                                                        </span>
                                                        <?php endif;
?>

                                                        <?php if ( $has_report ): ?>
                                                        <span
                                                            class='badge rounded-pill bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 fw-medium mt-1'
                                                            style='font-size: 0.75rem; display: block; width: fit-content; margin-left: auto;'>
                                                            <i class='bi bi-journal-check me-1'></i>รายงานการประชุมแล้ว
                                                        </span>
                                                        <?php endif;
?>
                                                    </div>
                                                    <?php endif;
?>
                                                </div>
                                            </td>

                                            <td class='text-center align-middle'>
                                                <button type='button'
                                                    class="btn <?php echo $coord_count > 0 ? 'btn-primary' : 'btn-outline-primary'; ?> btn-sm fw-bold px-3 rounded-pill shadow-sm"
                                                    data-bs-toggle='modal'
                                                    data-bs-target="#assignCoordModal<?php echo $doc['id']; ?>">
                                                    <i
                                                        class="bi <?php echo $coord_count > 0 ? 'bi-people-fill' : 'bi-person-plus-fill'; ?> me-1"></i>
                                                    <?php echo $coord_count > 0 ? "มอบหมายแล้ว ($coord_count)" : 'มอบหมายงาน';
?>
                                                </button>
                                            </td>

                                            <td class='text-center pe-4'>
                                            <div class='d-flex flex-column align-items-center justify-content-center position-relative py-2'
                                                style='min-height: 38px;'>
                                                    <?php if ( $has_sets ): ?>
                                                    <span
                                                        class='badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-25 rounded-pill position-absolute'
                                                        style='margin-top:-40px;margin-right:0px;margin-bottom:65px; font-size: 0.7rem; white-space: nowrap; cursor: pointer;'
                                                        data-bs-toggle='modal'
                                                        data-bs-target="#manageStaffModal<?php echo $doc['id']; ?>"
                                                        title='คลิกเพื่อดูข้อมูลการจัดชุด'>
                                                        <i class='bi bi-diagram-3-fill me-1'></i>จัดชุดแล้ว
                                                    </span>
                                                    <?php endif;
?>

                                                <div class='d-flex justify-content-center flex-wrap gap-2 mt-1'>
                                                        <?php if ( $has_sets ): ?>
                                                        <button type='button' class='btn-action btn-staff'
                                                            data-bs-toggle='modal'
                                                            data-bs-target="#manageStaffModal<?php echo $doc['id']; ?>"
                                                            title='ดูข้อมูลการจัดชุด'>
                                                            <i class='bi bi-person-lines-fill'></i>
                                                        </button>
                                                        <?php elseif ( $coord_count > 0 && !$has_report ): ?>
                                                        <button type='button' class='btn-action btn-set' disabled
                                                            title='ต้องรอให้ผู้ประสานงานรายงานการประชุมก่อน จึงจะจัดชุดปฏิบัติงานได้'
                                                            style='opacity: 0.5; cursor: not-allowed;'>
                                                            <i class='bi bi-person-lines-fill'></i>
                                                        </button>
                                                        <?php else: ?>
                                                        <button type='button' class='btn-action btn-staff' <?php echo $coord_count == 0 ? 'disabled style="opacity:0.5;" title="กรุณามอบหมายผู้ประสานงานก่อน"' : 'data-bs-toggle="modal" data-bs-target="#manageStaffModal'.$doc[ 'id' ].'" title="จัดการชุด/คนเข้าชุด"';
?>>
                                                            <i class='bi bi-person-lines-fill'></i>
                                                        </button>
                                                         <?php endif; ?>
                                                       <button type="button" 
        class="d-inline-flex align-items-center justify-content-center bg-success text-white border-0 shadow-sm rounded-pill my-1 my-md-0" 
        data-bs-toggle="modal" 
        data-bs-target="#borrowEquipmentModal<?php echo $doc['id']; ?>" 
        title="ยืมอุปกรณ์" 
        style="padding: 8px 16px; min-width: max-content; height: fit-content; font-size: 14px;">
    <i class="bi bi-box-seam me-2" style="font-size: 16px; line-height: 1;"></i>
    <span style="line-height: 1;">ยืมอุปกรณ์</span>
</button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach;
?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if ( isset( $total_pages ) && $total_pages > 1 ): ?>
                            <?php
$current_page = $page ?? 1;

$q_str = $query_string ?? '';

?>
                            <div class='d-flex justify-content-center p-3 mt-2 bg-white border-top'>
                                <nav>
                                    <ul
                                        class='pagination pagination-sm mb-0 shadow-sm rounded-3 flex-wrap justify-content-center gap-1'>

                                        <li class="page-item <?php echo $current_page <= 1 ? 'disabled' : ''; ?>">
                                            <a class='page-link'
                                                href="?page=<?php echo $current_page - 1; ?><?php echo $q_str; ?>">
                                                <i class='bi bi-chevron-left'></i> ก่อนหน้า
                                            </a>
                                        </li>

                                        <?php for ( $i = 1; $i <= $total_pages; $i++ ): ?>
                                        <li class="page-item <?php echo $current_page == $i ? 'active' : ''; ?>">
                                            <a class='page-link' href="?page=<?php echo $i; ?><?php echo $q_str; ?>">
                                                <?php echo $i;
?>
                                            </a>
                                        </li>
                                        <?php endfor;
?>

                                        <li
                                            class="page-item <?php echo $current_page >= $total_pages ? 'disabled' : ''; ?>">
                                            <a class='page-link'
                                                href="?page=<?php echo $current_page + 1; ?><?php echo $q_str; ?>">
                                                ถัดไป <i class='bi bi-chevron-right'></i>
                                            </a>
                                        </li>

                                    </ul>
                                </nav>
                            </div>
                            <?php endif;
?>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php foreach ( $docs as $doc ):
$has_sets = isset( $assignments_by_job[ $doc[ 'id' ] ] ) && count( $assignments_by_job[ $doc[ 'id' ] ] ) > 0;

$assigned_set_name = '';
if ( $has_sets ) {
    $assigned_set_name = $assignments_by_job[ $doc[ 'id' ] ][ 0 ][ 'staff_role' ];
}

$assigned_coords = $coords_by_doc[ $doc[ 'id' ] ] ?? [];
?>

    <div class='modal fade' id="manageStaffModal<?php echo $doc['id']; ?>" tabindex='-1'>
        <div class='modal-dialog modal-dialog-centered modal-dialog-scrollable modal-xl'>
            <div class='modal-content shadow-lg border-0'>
                <div class='modal-header border-bottom bg-primary text-white px-4 py-3'>
                    <div class='d-flex flex-column'>
                        <h5 class='modal-title fw-bold'><i class='bi bi-diagram-3-fill me-2'></i>ระบุชุดปฏิบัติงาน</h5>
                        <small class='text-white-50 mt-1'>อ้างอิง: <?php echo htmlspecialchars( $doc[ 'doc_name' ] );
?></small>
                    </div>
                    <button type='button' class='btn-close btn-close-white' data-bs-dismiss='modal'></button>
                </div>

                <div class='modal-body p-4 bg-white'>
                    <?php if ( $has_sets ): ?>
                    <div class='row g-4'>
                        <div class='col-lg-6 border-end pe-lg-4'>
                            <h6 class='fw-bold text-dark mb-3'><i
                                    class='bi bi-person-badge me-2 text-primary'></i>ผู้ประสานงานในงานนี้</h6>
                            <div class='list-group list-group-flush border rounded-3 overflow-hidden shadow-sm'>
                                <?php if ( empty( $assigned_coords ) ): ?>
                                <div class='list-group-item text-center py-4 text-muted small'>ไม่มีผู้ประสานงาน</div>
                                <?php else: ?>
                                <?php foreach ( $assigned_coords as $coord ): ?>
                                <div class='list-group-item p-3 bg-light-subtle'>
                                    <div class='d-flex justify-content-between align-items-center mb-2'>
                                        <div class='d-flex align-items-center flex-wrap gap-2'>
                                            <span class='fw-bold text-primary'><?php echo htmlspecialchars( $coord[ 'fname' ].' '.$coord[ 'lname' ] );
?></span>
                                            <?php if ( !empty( $coord[ 'assigned_date' ] ) ): ?>
                                            <span class='text-muted text-nowrap' style='font-size: 0.75rem;'><i
                                                    class='bi bi-calendar3 me-1'></i>มอบหมายเมื่อ: <?php echo date( 'd/m/Y', strtotime( $coord[ 'assigned_date' ] ) );
?></span>
                                            <?php endif;
?>
                                        </div>
                                        <?php if ( isset( $coord[ 'is_acknowledged' ] ) && $coord[ 'is_acknowledged' ] == 1 ): ?>
                                        <?php if ( isset( $coord[ 'has_report' ] ) && $coord[ 'has_report' ] > 0 ): ?>
                                        <span class='badge bg-success rounded-pill' style='font-size: 0.65rem;'><i
                                                class='bi bi-check-circle'></i> รับทราบและรายงานแล้ว</span>
                                        <?php else: ?>
                                        <span class='badge bg-info rounded-pill' style='font-size: 0.65rem;'><i
                                                class='bi bi-file-earmark-text'></i> รอรายงานการประชุม</span>
                                        <?php endif;
?>
                                        <?php else: ?>
                                        <span class='badge bg-warning text-dark rounded-pill'
                                            style='font-size: 0.65rem;'><i class='bi bi-clock'></i> รอรับทราบ</span>
                                        <?php endif;
?>
                                    </div>
                                    <button type='button' class='btn btn-outline-secondary btn-sm w-100'
                                        data-bs-toggle='modal'
                                        data-bs-target="#coordJobsModal<?php echo $coord['user_id']; ?>">
                                        <i class='bi bi-eye me-1'></i> ดูงานทั้งหมด ( <?php echo count( $jobs_by_coord[ $coord[ 'user_id' ] ] ?? [] );
?> )
                                    </button>
                                </div>
                                <?php endforeach;
?>
                                <?php endif;
?>
                            </div>
                        </div>

                        <div class='col-lg-6 ps-lg-4'>
                            <div class='text-center py-4'>
                                <div class='mb-3'>
                                    <i class='bi bi-check-circle-fill text-success' style='font-size: 3rem;'></i>
                                </div>
                                <h5 class='fw-bold text-dark mb-2'>กำหนดชุดปฏิบัติงานแล้ว</h5>
                                <div
                                    class='d-inline-flex align-items-center bg-light border border-primary border-opacity-25 rounded-pill px-4 py-2 shadow-sm mb-4'>
                                    <i class='bi bi-collection-fill text-primary me-2'></i>
                                    <span class='fw-bold text-primary'><?php echo htmlspecialchars( $assigned_set_name );
?></span>
                                </div>
                                <div class='text-start bg-white p-3 border rounded-3 shadow-sm mx-auto'
                                    style='max-width: 450px;'>
                                    <h6 class='fw-bold text-dark border-bottom pb-2 mb-3 small'><i
                                            class='bi bi-people-fill me-2 text-primary'></i>รายชื่อบุคลากรที่ปฏิบัติงาน
                                    </h6>
                                    <ul class='list-unstyled mb-0 ps-2' style='max-height: 150px; overflow-y: auto;'>
                                        <?php
$members_in_this_set = $assignments_by_job[ $doc[ 'id' ] ];
$has_real_members = false;
foreach ( $members_in_this_set as $assigned_member ):
if ( $assigned_member[ 'staff_name' ] != 'รอระบุบุคคล' ):
$has_real_members = true;
?>
                                        <li class='mb-2 text-secondary small fw-medium'>
                                            <i class='bi bi-person-check-fill text-success me-2'></i> <?php echo htmlspecialchars( $assigned_member[ 'staff_name' ] );
?>
                                        </li>
                                        <?php
endif;
endforeach;

if ( !$has_real_members ):
?>
                                        <li class='text-muted text-center py-2 small'>ยังไม่มีการระบุตัวบุคคล</li>
                                        <?php endif;
?>
                                    </ul>

                                    <h6
                                        class='fw-bold text-dark border-bottom border-secondary border-opacity-25 pb-2 mt-4 mb-3 small'>
                                        <i class='bi bi-calendar-check me-1 text-success'></i> ภาระงานทั้งหมดของชุด</h6>
                                    <?php
$rawRoleAssigned = $assignments_by_job[ $doc[ 'id' ] ][ 0 ][ 'staff_role' ];
$assigned_jobs = $jobs_by_set[ $rawRoleAssigned ] ?? [];
?>
                                    <div class='bg-light p-2 rounded border border-secondary border-opacity-25 mb-2 shadow-sm small'
                                        style='max-height: 150px; overflow-y: auto;'>
                                        <?php if ( empty( $assigned_jobs ) ): ?>
                                        <div class='text-success ms-2 mt-1'>- ว่าง ( ยังไม่มีงาน ) -</div>
                                        <?php else: ?>
                                        <ul class='mb-0 mt-1 ps-3 text-secondary'>
                                            <?php foreach ( $assigned_jobs as $j ): ?>
                                            <li class='mb-2'>
                                                <span class='fw-medium text-dark'><?php echo htmlspecialchars( $j[ 'doc_name' ] );
?></span><br>
                                                <span class='text-primary'><i class='bi bi-calendar-event'></i> <?php echo $j[ 'date_text' ];
?></span>
                                            </li>
                                            <?php endforeach;
?>
                                        </ul>
                                        <?php endif;
?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class='row g-4'>
                        <div class='col-lg-6 border-end pe-lg-4'>
                            <h6 class='fw-bold text-primary mb-3'><i class='bi bi-check2-square me-1'></i>
                                เลือกชุดสำหรับงานนี้</h6>

                            <form method='POST' action='manage_operation2.php'
                                class='bg-light p-3 rounded-3 border mb-3'>
                            <?php echo csrf_field(); ?>
                                <input type='hidden' name='manage_sets' value='1'>
                                <input type='hidden' name='job_id' value="<?php echo htmlspecialchars($doc['id']); ?>">

                                <div class='mb-3'>
                                    <label class='form-label fw-bold small text-muted'>เลือกชุดปฏิบัติงาน (
                                        เลือกจากรายการเท่านั้น ):</label>
                                    <select name='existing_set' class='form-select border-primary'
                                        onchange="updateSetPreview(this, '<?php echo $doc['id']; ?>')" required>
                                        <option value='' selected disabled>-- กรุณาเลือกชุด --</option>
                                        <?php if ( !empty( $available_sets ) ): ?>
                                        <?php foreach ( $available_sets as $setName ): ?>
                                        <option value="<?php echo htmlspecialchars($setName); ?>"><?php echo htmlspecialchars( $setName );
?></option>
                                        <?php endforeach;
?>
                                        <?php else: ?>
                                        <option value='' disabled>-- ไม่มีข้อมูลชุดในระบบ --</option>
                                        <?php endif;
?>
                                    </select>
                                </div>

                                <button type='submit' class='btn btn-primary w-100 fw-bold' <?php echo empty( $available_sets ) ? 'disabled' : '';
?>>
                                    <i class='bi bi-save me-1'></i> บันทึกชุดปฏิบัติงาน
                                </button>
                            </form>

                            <div id="setPreview_<?php echo $doc['id']; ?>" class='d-none'>
                                <div class='card border-info shadow-sm'>
                                    <div class='card-header bg-info-subtle text-dark py-2 fw-bold'
                                        style='font-size: 0.9rem;'>
                                        <i class='bi bi-info-circle me-1'></i> ข้อมูล: <span
                                            id="previewTitle_<?php echo $doc['id']; ?>" class='text-primary'></span>
                                    </div>
                                    <div class='card-body p-3'
                                        style='font-size: 0.85rem; max-height: 280px; overflow-y: auto;'>
                                        <div class='fw-bold text-dark mb-2'><i
                                                class='bi bi-people-fill me-1 text-secondary'></i> สมาชิกในชุด:</div>
                                        <ul id="previewMembers_<?php echo $doc['id']; ?>"
                                            class='mb-3 ps-3 text-secondary'></ul>

                                        <div class='fw-bold text-dark mb-2 border-top pt-2'><i
                                                class='bi bi-calendar-check me-1 text-success'></i> ภาระงานของชุด (
                                            อ้างอิงช่วงเวลา กอพ. ):</div>
                                        <div id="preview_work_dates_<?php echo $doc['id']; ?>"
                                            class='bg-light p-2 rounded border border-secondary border-opacity-25 shadow-sm'>
                                        </div>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class='col-lg-6 ps-lg-4'>
                            <h6 class='fw-bold text-dark mb-3'><i class='bi bi-card-list me-1'></i>
                                ภาพรวมชุดปฏิบัติงานทั้งหมด</h6>

                            <?php if ( empty( $master_sets ) ): ?>
                            <div class='text-center text-muted py-5 bg-light rounded-3'>
                                <i class='bi bi-inboxes fs-2 d-block mb-2 text-secondary'></i>
                                <small>ยังไม่มีข้อมูลชุดปฏิบัติงานในระบบ</small>
                            </div>
                            <?php else: ?>
                            <div class='accordion' id="accordionStaff<?php echo $doc['id']; ?>">
                                <?php $i = 0;
foreach ( $master_sets as $setName => $members ): $i++;
?>
                                <div class='accordion-item border-0 mb-2 shadow-sm rounded-3 overflow-hidden'>
                                    <h2 class='accordion-header'>
                                        <button
                                            class="accordion-button <?php echo $i>1 ? 'collapsed' : ''; ?> bg-white fw-bold text-primary"
                                            type='button' data-bs-toggle='collapse'
                                            data-bs-target="#collapseStaff_<?php echo $doc['id'].'_'.$i; ?>">
                                            <i class='bi bi-collection me-2'></i> <?php echo htmlspecialchars( $setName );
?>
                                            <span class='badge bg-secondary ms-2 rounded-pill'><?php echo count( $members );
?> คน</span>
                                        </button>
                                    </h2>
                                    <div id="collapseStaff_<?php echo $doc['id'].'_'.$i; ?>"
                                        class="accordion-collapse collapse <?php echo $i==1 ? 'show' : ''; ?>"
                                        data-bs-parent="#accordionStaff<?php echo $doc['id']; ?>">
                                        <div class='accordion-body bg-light p-3'
                                            style='max-height: 400px; overflow-y: auto;'>
                                            <?php if ( empty( $members ) ): ?>
                                            <div class='text-center text-muted small py-2'>ยังไม่มีคนในชุดนี้</div>
                                            <?php else: ?>
                                            <h6
                                                class='fw-bold text-dark mb-2 border-bottom border-secondary border-opacity-25 pb-2 small'>
                                                <i class='bi bi-people-fill me-1 text-primary'></i> สมาชิกในชุด</h6>
                                            <div class='mb-3'>
                                                <?php foreach ( $members as $name ): ?>
                                                <div class='staff-list-item py-1 px-2 mb-1 shadow-sm bg-white'>
                                                    <span class='small'><i
                                                            class='bi bi-person-fill text-secondary me-2'></i><?php echo htmlspecialchars( $name );
?></span>
                                                </div>
                                                <?php endforeach;
?>
                                            </div>

                                            <h6
                                                class='fw-bold text-dark mb-2 border-bottom border-secondary border-opacity-25 pb-2 small mt-4'>
                                                <i class='bi bi-calendar-check me-1 text-success'></i>
                                                ภาระงานทั้งหมดของชุด</h6>
                                            <?php
$i_jobs = $jobs_by_set[ $setName ] ?? [];
?>
                                            <div
                                                class='bg-white p-2 rounded border border-secondary border-opacity-25 mb-2 shadow-sm small'>
                                                <?php if ( empty( $i_jobs ) ): ?>
                                                <div class='text-success ms-2 mt-1'>- ว่าง ( ยังไม่มีงาน ) -</div>
                                                <?php else: ?>
                                                <ul class='mb-0 mt-1 ps-3 text-secondary'>
                                                    <?php foreach ( $i_jobs as $j ): ?>
                                                    <li class='mb-2'>
                                                        <span class='fw-medium text-dark'><?php echo htmlspecialchars( $j[ 'doc_name' ] );
?></span><br>
                                                        <span class='text-primary'><i class='bi bi-calendar-event'></i> <?php echo $j[ 'date_text' ];
?></span>
                                                    </li>
                                                    <?php endforeach;
?>
                                                </ul>
                                                <?php endif;
?>
                                            </div>
                                            <?php endif;
?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach;
?>
                            </div>
                            <?php endif;
?>
                        </div>
                    </div>
                    <?php endif;
?>
                </div>
            </div>
        </div>
    </div>

    <div class='modal fade' id="assignCoordModal<?php echo $doc['id']; ?>" tabindex='-1'>
        <div class='modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg'>
            <div class='modal-content shadow-lg border-0'>
                <div class='modal-header border-bottom bg-white px-4 py-3'>
                    <div class='d-flex flex-column'>
                        <h5 class='modal-title fw-bold text-dark'><i
                                class='bi bi-person-lines-fill text-primary me-2'></i>ระบุผู้ประสานงาน</h5>
                        <small class='text-muted mt-1'>อ้างอิง: <?php echo htmlspecialchars( $doc[ 'doc_name' ] );
?></small>
                    </div>
                    <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                </div>

                <div class='modal-body p-4 bg-light'>
                    <div class='row g-4'>
                        <div
                            class='col-md-5 border-end border-md-end-0 border-bottom-md bg-white p-3 rounded-3 shadow-sm'>
                            <h6 class='fw-bold text-primary mb-3'><i class='bi bi-search me-1'></i> เลือกผู้ประสานงาน
                            </h6>

                            <form method='POST' action='manage_operation2.php'>
                            <?php echo csrf_field(); ?>
                                <input type='hidden' name='doc_id' value="<?php echo $doc['id']; ?>">

                                <div class='mb-3'>
                                    <select name='user_id' class='form-select border-primary'
                                        onchange="updateCoordPreview(this, '<?php echo $doc['id']; ?>')" required>
                                        <option value='' selected disabled>-- เลือกรายชื่อ --</option>
                                        <?php
$current_doc_user_ids = array_column( $assigned_coords, 'user_id' );
$current_start = $doc[ 'dpt_work_start' ] ?? null;
$current_end = $doc[ 'dpt_work_end' ] ?? null;

foreach ( $staff_list as $s ):
if ( in_array( $s[ 'id' ], $current_doc_user_ids ) ) continue;

$has_overlap = false;
if ( $current_start && $current_end && !empty( $jobs_by_coord[ $s[ 'id' ] ] ) ) {
    foreach ( $jobs_by_coord[ $s[ 'id' ] ] as $cj ) {
        if ( $cj[ 'dpt_work_start' ] <= $current_end && $cj[ 'dpt_work_end' ] >= $current_start ) {
            $has_overlap = true;
            break;
        }
    }
}
$overlap_text = $has_overlap ? ' (มีงานซ้อน)' : '';
?>
                                        <option value="<?php echo $s['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($s['fname'] . ' ' . $s['lname']); ?>"
                                            data-overlap="<?php echo $has_overlap ? 'true' : 'false'; ?>">
                                            <?php echo htmlspecialchars( $s[ 'fname' ] . ' ' . $s[ 'lname' ] ) . $overlap_text;
?>
                                        </option>
                                        <?php endforeach;
?>
                                    </select>
                                </div>
                                <button type='submit' name='add_multi_coordinator'
                                    class='btn btn-primary w-100 fw-bold'>
                                    <i class='bi bi-plus-circle me-1'></i> เพิ่มเข้าสู่หน้างาน
                                </button>
                            </form>

                            <div id="coordPreview_<?php echo $doc['id']; ?>" class='d-none mt-3'>
                                <div id="coordOverlapWarning_<?php echo $doc['id']; ?>"
                                    class='alert alert-danger py-2 px-3 small mb-2 d-none border-0 shadow-sm'>
                                    <i class='bi bi-exclamation-triangle-fill me-1'></i> <strong>แจ้งเตือน:</strong>
                                    ผู้ประสานงานท่านนี้มีงานซ้อนในช่วงเวลาเดียวกับภารกิจนี้
                                </div>
                                <div class='card border-info shadow-sm'>
                                    <div class='card-header bg-info-subtle text-dark py-2 fw-bold'
                                        style='font-size: 0.9rem;'>
                                        <i class='bi bi-journal-text me-1'></i> ภาระงานของ: <span
                                            id="previewCoordTitle_<?php echo $doc['id']; ?>"
                                            class='text-primary'></span>
                                    </div>
                                    <div class='card-body p-2'
                                        style='font-size: 0.85rem; max-height: 200px; overflow-y: auto;'>
                                        <div id="preview_coord_jobs_<?php echo $doc['id']; ?>"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class='col-md-7 ps-md-4'>
                            <h6 class='fw-bold text-dark mb-3'><i class='bi bi-list-check me-1'></i>
                                ผู้ประสานงานที่รับผิดชอบ</h6>

                            <?php if ( empty( $assigned_coords ) ): ?>
                            <div class='text-center text-muted py-4 bg-white rounded-3 border border-dashed'>
                                <i class='bi bi-person-x fs-3 d-block mb-2 text-secondary'></i>
                                <small>ยังไม่ได้ระบุผู้ประสานงาน</small>
                            </div>
                            <?php else: ?>
                            <div class='list-group shadow-sm'>
                                <?php foreach ( $assigned_coords as $coord ): ?>
                                <div
                                    class='list-group-item d-flex justify-content-between align-items-center border-0 mb-1 rounded bg-white'>
                                    <div class='d-flex align-items-center flex-wrap gap-2'>
                                        <div class='d-flex align-items-center text-nowrap'>
                                            <i class='bi bi-person-check-fill text-success me-2'></i>
                                            <span class='fw-medium'><?php echo htmlspecialchars( $coord[ 'fname' ] . ' ' . $coord[ 'lname' ] );
?></span>
                                        </div>

                                        <?php if ( !empty( $coord[ 'assigned_date' ] ) ): ?>
                                        <span class='text-muted text-nowrap' style='font-size: 0.75rem;'>
                                            <i class='bi bi-calendar3 me-1'></i>มอบหมายเมื่อ: <?php echo date( 'd/m/Y', strtotime( $coord[ 'assigned_date' ] ) );
?>
                                        </span>
                                        <?php endif;
?>

                                        <?php if ( isset( $coord[ 'is_acknowledged' ] ) && $coord[ 'is_acknowledged' ] == 1 ): ?>
                                        <?php if ( isset( $coord[ 'has_report' ] ) && $coord[ 'has_report' ] > 0 ): ?>
                                        <span class='badge bg-success-subtle text-success text-nowrap'
                                            style='font-size: 0.7rem;'><i class='bi bi-check-circle-fill'></i>
                                            รับทราบและรายงานแล้ว</span>
                                        <?php else: ?>
                                        <span class='badge bg-info-subtle text-info text-nowrap'
                                            style='font-size: 0.7rem;'><i class='bi bi-file-earmark-text-fill'></i>
                                            รอรายงานการประชุม</span>
                                        <?php endif;
?>
                                        <?php else: ?>
                                        <span class='badge bg-warning-subtle text-dark text-nowrap'
                                            style='font-size: 0.7rem;'><i class='bi bi-clock'></i> รอรับทราบ</span>
                                        <?php endif;
?>
                                    </div>

                                    <?php if ( isset( $coord[ 'is_acknowledged' ] ) && $coord[ 'is_acknowledged' ] == 1 && isset( $coord[ 'has_report' ] ) && $coord[ 'has_report' ] > 0 ): ?>
                                    <button type='button' class='btn btn-sm btn-outline-secondary border-0' disabled
                                        title='รับทราบและรายงานการประชุมแล้ว ไม่สามารถลบได้'>
                                        <i class='bi bi-lock-fill'></i>
                                    </button>
                                    <?php else: ?>
                                    <form method="POST" action="manage_operation2.php" class="d-inline m-0 p-0" onsubmit="return confirm('⚠️ ยืนยันการลบผู้ประสานงาน: \n\n&quot;<?php echo htmlspecialchars(addslashes($coord['fname'] . ' ' . $coord['lname'])); ?>&quot;\n\nออกจากงานนี้หรือไม่?');">
                                        <?php echo csrf_field(); ?>
                                        <input type="hidden" name="action" value="remove_coordinator">
                                        <input type="hidden" name="doc_id" value="<?php echo (int)$doc['id']; ?>">
                                        <input type="hidden" name="user_id" value="<?php echo (int)$coord['user_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger border-0" title="ลบผู้ประสานงาน">
                                            <i class="bi bi-trash3-fill"></i>
                                        </button>
                                    </form>
                                    <?php endif;
?>

                                </div>
                                <?php endforeach;
?>
                            </div>
                            <?php endif;
?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal ดูรายละเอียดงาน -->
    <div class='modal fade' id="viewDocModal<?php echo $doc['id']; ?>" tabindex='-1' aria-hidden='true'>
        <div class='modal-dialog modal-dialog-centered modal-xl modal-dialog-scrollable'>
            <div class='modal-content shadow-lg border-0'>
                <div class='modal-header border-bottom bg-light px-4 py-3'>
                    <div class='d-flex align-items-center'>
                        <div class='bg-info bg-opacity-10 text-info p-2 rounded-3 me-3 d-none d-md-flex'>
                            <i class='bi bi-eye-fill fs-4'></i>
                        </div>
                        <h5 class='modal-title fw-bold text-dark mb-0 pe-4'>รายละเอียดข้อมูลงาน<br
                                class='d-md-none'><span class='fs-6 text-muted ms-md-2'>( รหัส: <?php echo htmlspecialchars( $doc[ 'doc_number' ] ?: $doc[ 'id' ] );
?> )</span></h5>
                    </div>
                    <button type='button' class='btn-close' data-bs-dismiss='modal' aria-label='Close'></button>
                </div>
                <div class='modal-body px-4 pb-4' style='background-color: #f8f9fa;'>
                    <!-- ส่วนที่ 1 -->
                    <div class='bg-white p-4 rounded-4 shadow-sm mb-4 mt-3'>
                        <h6 class='fw-bold text-primary mb-3'><i
                                class='bi bi-box-arrow-in-right me-2'></i>ข้อมูลการรับเรื่อง</h6>
                        <div class='row g-3'>
                            <div class='col-md-4'>
                                <div class='text-muted small fw-bold'>ปีงบประมาณ</div>
                                <div class='text-dark'><?php echo !empty( $doc[ 'fiscal_year' ] ) ? htmlspecialchars( $doc[ 'fiscal_year' ] ) : '-';
?></div>
                            </div>
                            <div class='col-md-4'>
                                <div class='text-muted small fw-bold'>เลขรับ กรมฯ</div>
                                <div class='text-dark'><?php echo !empty( $doc[ 'dpt_receive_number' ] ) ? htmlspecialchars( $doc[ 'dpt_receive_number' ] ) : '-';
?></div>
                            </div>
                            <div class='col-md-4'>
                                <div class='text-muted small fw-bold'>วันที่รับ กรมฯ</div>
                                <div class='text-dark'><?php echo !empty( $doc[ 'dpt_receive_date' ] ) ? date( 'd/m/Y', strtotime( $doc[ 'dpt_receive_date' ] ) ) : '-';
?></div>
                            </div>
                            <div class='col-md-6'>
                                <div class='text-muted small fw-bold'>เลขรับ กอพ.</div>
                                <div class='text-dark'><?php echo !empty( $doc[ 'division_receive_number' ] ) ? htmlspecialchars( $doc[ 'division_receive_number' ] ) : '-';
?></div>
                            </div>
                            <div class='col-md-6'>
                                <div class='text-muted small fw-bold'>วันที่รับ กอพ.</div>
                                <div class='text-dark'><?php echo !empty( $doc[ 'division_receive_date' ] ) ? date( 'd/m/Y', strtotime( $doc[ 'division_receive_date' ] ) ) : '-';
?></div>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 2 -->
                    <div class='bg-white p-4 rounded-4 shadow-sm mb-4'>
                        <h6 class='fw-bold text-primary mb-3'><i
                                class='bi bi-file-earmark-text me-2'></i>ข้อมูลหนังสือและชื่องาน</h6>
                        <div class='row g-3'>
                            <div class='col-md-6'>
                                <div class='text-muted small fw-bold'>หน่วยงานต้นเรื่อง</div>
                                <div class='text-dark' style='word-break: break-word;'><?php echo !empty( $doc[ 'host_agency' ] ) ? htmlspecialchars( $doc[ 'host_agency' ] ) : '-';
?></div>
                            </div>
                            <div class='col-md-6'>
                                <div class='text-muted small fw-bold'>สังกัดของหน่วยงานต้นเรื่อง</div>
                                <div class='text-dark' style='word-break: break-word;'><?php echo !empty( $doc[ 'host_agency_affiliation' ] ) ? htmlspecialchars( $doc[ 'host_agency_affiliation' ] ) : '-';
?></div>
                            </div>
                            <div class='col-md-6'>
                                <div class='text-muted small fw-bold'>เลขที่หนังสือหน่วยงานต้นทาง</div>
                                <div class='text-dark' style='word-break: break-word;'><?php echo !empty( $doc[ 'source_doc_number' ] ) ? htmlspecialchars( $doc[ 'source_doc_number' ] ) : '-';
?></div>
                            </div>
                            <div class='col-md-6'>
                                <div class='text-muted small fw-bold'>ประเภทงาน</div>
                                <div class='text-dark' style='word-break: break-word;'>
                                    <?php
$v_job_type_text = ( $doc[ 'job_type' ] === 'อื่นๆ' && !empty( $doc[ 'job_type_other' ] ) )
? 'อื่นๆ: ' . $doc[ 'job_type_other' ]
: ( $doc[ 'job_type' ] ?: '-' );
echo htmlspecialchars( $v_job_type_text );
?>
                                </div>
                            </div>
                            <div class='col-md-12'>
                                <div class='text-muted small fw-bold'>ชื่องาน</div>
                                <div class='text-dark fw-bold' style='word-break: break-word;'><?php echo !empty( $doc[ 'doc_name' ] ) ? htmlspecialchars( $doc[ 'doc_name' ] ) : '-';
?></div>
                            </div>
                            <div class='col-md-12'>
                                <div class='text-muted small fw-bold'>รายละเอียด</div>
                                <div class='text-dark' style='word-break: break-word;'><?php echo !empty( $doc[ 'description' ] ) ? nl2br( htmlspecialchars( $doc[ 'description' ] ) ) : '-';
?></div>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 3 -->
                    <div class='bg-white p-4 rounded-4 shadow-sm mb-4'>
                        <h6 class='fw-bold text-primary mb-3'><i class='bi bi-geo-alt me-2'></i>สถานที่จัดงาน</h6>
                        <div class='row g-3'>
                            <div class='col-md-12'>
                                <div class='text-muted small fw-bold'>หน่วยงาน / ชื่อสถานที่จัดงาน</div>
                                <div class='text-dark' style='word-break: break-word;'><?php echo !empty( $doc[ 'venue_name' ] ) ? htmlspecialchars( $doc[ 'venue_name' ] ) : '-';
?></div>
                            </div>
                            <div class='col-md-4'>
                                <div class='text-muted small fw-bold'>จังหวัด</div>
                                <div class='text-dark'><?php echo !empty( $doc[ 'venue_province' ] ) ? htmlspecialchars( $doc[ 'venue_province' ] ) : '-';
?></div>
                            </div>
                            <div class='col-md-4'>
                                <div class='text-muted small fw-bold'>อำเภอ/เขต</div>
                                <div class='text-dark'><?php echo !empty( $doc[ 'venue_district' ] ) ? htmlspecialchars( $doc[ 'venue_district' ] ) : '-';
?></div>
                            </div>
                            <div class='col-md-4'>
                                <div class='text-muted small fw-bold'>ตำบล/แขวง</div>
                                <div class='text-dark'><?php echo !empty( $doc[ 'venue_subdistrict' ] ) ? htmlspecialchars( $doc[ 'venue_subdistrict' ] ) : '-';
?></div>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 4 -->
                    <div class='bg-white p-4 rounded-4 shadow-sm mb-4'>
                        <h6 class='fw-bold text-primary mb-3'><i
                                class='bi bi-calendar-event me-2'></i>กำหนดการและระยะเวลาดำเนินงาน</h6>
                        <div class='row g-3'>
                            <div class='col-md-6'>
                                <div class='text-muted small fw-bold'>วันที่จัดงาน ( เริ่ม )</div>
                                <div class='text-dark'><?php echo !empty( $doc[ 'event_start_date' ] ) ? date( 'd/m/Y', strtotime( $doc[ 'event_start_date' ] ) ) : '-';
?></div>
                            </div>
                            <div class='col-md-6'>
                                <div class='text-muted small fw-bold'>วันที่จัดงาน ( สิ้นสุด )</div>
                                <div class='text-dark'><?php echo !empty( $doc[ 'event_end_date' ] ) ? date( 'd/m/Y', strtotime( $doc[ 'event_end_date' ] ) ) : '-';
?></div>
                            </div>
                            <div class='col-md-6'>
                                <div class='text-muted small fw-bold'>วันที่ประชุมล่วงหน้า</div>
                                <div class='text-dark'><?php echo !empty( $doc[ 'pre_meeting_date' ] ) ? date( 'd/m/Y', strtotime( $doc[ 'pre_meeting_date' ] ) ) : '-';
?></div>
                            </div>
                            <div class='col-md-6'>
                                <div class='text-muted small fw-bold'>วันที่ขอรับอุปกรณ์ยืม</div>
                                <div class='text-dark'><?php echo !empty( $doc[ 'equipment_borrow_date' ] ) ? date( 'd/m/Y', strtotime( $doc[ 'equipment_borrow_date' ] ) ) : '-';
?></div>
                            </div>
                            <hr class='my-3 text-muted'>
                            <div class='col-md-6'>
                                <div class='text-primary small fw-bold'>ระยะเวลาทำงาน กอพ. ( เริ่ม )</div>
                                <div class='text-dark'><?php echo !empty( $doc[ 'dpt_work_start' ] ) ? date( 'd/m/Y', strtotime( $doc[ 'dpt_work_start' ] ) ) : '-';
?></div>
                            </div>
                            <div class='col-md-6'>
                                <div class='text-primary small fw-bold'>ระยะเวลาทำงาน กอพ. ( สิ้นสุด )</div>
                                <div class='text-dark'><?php echo !empty( $doc[ 'dpt_work_end' ] ) ? date( 'd/m/Y', strtotime( $doc[ 'dpt_work_end' ] ) ) : '-';
?></div>
                            </div>
                        </div>
                    </div>

                    <!-- ส่วนที่ 5 -->
                    <div class='bg-white p-4 rounded-4 shadow-sm mb-2'>
                        <h6 class='fw-bold text-primary mb-3'><i class='bi bi-paperclip me-2'></i>ไฟล์แนบและหมายเหตุ
                        </h6>
                        <div class='row g-3'>
                            <div class='col-md-12'>
                                <div class='text-muted small fw-bold'>ไฟล์เอกสารแนบ ( PDF )</div>
                                <div class='mt-1'>
                                    <?php if ( !empty( $doc[ 'attachment_file' ] ) ): ?>
                                    <a href="download.php?id=<?php echo (int)$doc['id']; ?>" target='_blank'
                                        class='badge bg-danger text-decoration-none px-3 py-2'>
                                        <i class='bi bi-file-earmark-pdf me-1'></i> ดูไฟล์แนบ
                                    </a>
                                    <?php else: ?>
                                    <span class='text-muted'>-</span>
                                    <?php endif;
?>
                                </div>
                            </div>
                            <div class='col-md-12'>
                                <div class='text-muted small fw-bold mt-2'>หมายเหตุ</div>
                                <div class='text-dark' style='word-break: break-word;'><?php echo !empty( $doc[ 'remark' ] ) ? nl2br( htmlspecialchars( $doc[ 'remark' ] ) ) : '-';
?></div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class='modal-footer border-top-0 px-4 pb-4 pt-3 bg-light'>
                    <button type='button' class='btn btn-secondary rounded-pill px-5'
                        data-bs-dismiss='modal'>ปิดหน้าต่าง</button>
                </div>
            </div>
        </div>
    </div>

    <?php endforeach;
?>

    <?php foreach ( $staff_list as $staff ):
$staff_id = $staff[ 'id' ];
$staff_jobs = $jobs_by_coord[ $staff_id ] ?? [];
?>
    <div class='modal fade' id="coordJobsModal<?php echo $staff_id; ?>" tabindex='-1' style='z-index: 1060;'>
        <div class='modal-dialog modal-dialog-centered modal-dialog-scrollable modal-lg'>
            <div class='modal-content shadow-lg border-0'>
                <div class='modal-header border-bottom bg-info px-4 py-3'>
                    <h5 class='modal-title fw-bold text-dark'><i class='bi bi-journal-text me-2'></i>ภาระงาน: <?php echo htmlspecialchars( $staff[ 'fname' ] . ' ' . $staff[ 'lname' ] );
?></h5>
                    <button type='button' class='btn-close' data-bs-dismiss='modal'></button>
                </div>
                <div class='modal-body p-4 bg-light' style='max-height: 60vh; overflow-y: auto;'>
                    <?php if ( empty( $staff_jobs ) ): ?>
                    <div class='text-center py-5 text-muted'>
                        <i class='bi bi-inbox fs-1 d-block mb-3 opacity-50'></i>
                        <span class='fw-medium'>ยังไม่ได้รับมอบหมายงาน</span>
                    </div>
                    <?php else: ?>
                    <div class='list-group shadow-sm'>
                        <?php foreach ( $staff_jobs as $job ): ?>
                        <div class='list-group-item p-3 border-0 mb-1 rounded bg-white shadow-sm'>
                            <div class='d-flex w-100 justify-content-between align-items-center mb-1'>
                                <h6 class='mb-1 fw-bold text-primary'><?php echo htmlspecialchars( $job[ 'doc_name' ] );
?></h6>
                                <small class='text-primary text-nowrap ms-3'><i class='bi bi-calendar-event me-1'></i> <?php echo $job[ 'date_text' ];
?></small>
                            </div>
                            <p class='mb-1 small text-secondary'><strong>รหัสงาน:</strong> <?php echo htmlspecialchars( $job[ 'doc_number' ] ?: '-' );
?></p>
                        </div>
                        <?php endforeach;
?>
                    </div>
                    <?php endif;
?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach;
?>

    <?php foreach ($docs as $doc): ?>
    <!-- Modal ยืมอุปกรณ์ -->
    <div class="modal fade" id="borrowEquipmentModal<?php echo $doc['id']; ?>" tabindex="-1" aria-hidden="true">
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
                                <?php 
                                    $current_page = $page ?? 1;
                                    $q_str = $query_string ?? '';
                                ?>
                                <form method="POST" action="manage_operation2.php?page=<?php echo $current_page; ?><?php echo $q_str; ?>">
                                    <?php echo csrf_field(); ?>
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
                                                    <td class="text-center"><form method="POST" action="manage_operation2.php?page=<?php echo $current_page; ?><?php echo $q_str; ?>" class="d-inline m-0 p-0" onsubmit="return confirm('ยืนยันการคืนอุปกรณ์นี้?');">
                                                        <?php echo csrf_field(); ?>
                                                        <input type="hidden" name="action" value="return_item">
                                                        <input type="hidden" name="borrow_id" value="<?php echo (int)$item['id']; ?>">
                                                        <input type="hidden" name="job_id" value="<?php echo (int)$doc['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="คืนอุปกรณ์">
                                                            <i class="bi bi-arrow-return-left"></i> คืน
                                                        </button>
                                                    </form></td>
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

    <!-- Modal รายการอุปกรณ์ -->
    <div class="modal fade" id="equipmentModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header border-bottom bg-info px-4 py-3">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-tools text-dark me-2"></i>รายการอุปกรณ์และสถานะคงเหลือ</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-light">
                    <?php if (empty($equipments_list)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="bi bi-inbox fs-1 d-block mb-3 opacity-50"></i>
                            <span class="fw-medium">ยังไม่มีข้อมูลอุปกรณ์ในระบบ</span>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive bg-white rounded-3 shadow-sm border" style="padding:10 10px;">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">รหัส / ชื่ออุปกรณ์</th>
                                        <th>หมวดหมู่</th>
                                        <th class="text-center">ทั้งหมด</th>
                                        <th class="text-center">คงเหลือ (พร้อมใช้)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($equipments_list as $eq): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($eq['equipment_name']); ?></div>
                                                <small class="text-muted">รหัส: <?php echo htmlspecialchars($eq['equipment_code']); ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-25">
                                                    <?php echo htmlspecialchars($eq['equipment_category'] ?? '-'); ?>
                                                </span>
                                            </td>
                                            <td class="text-center fw-medium">
                                                <?php echo htmlspecialchars($eq['total_quantity']); ?> <?php echo htmlspecialchars($eq['unit']); ?>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($eq['available_quantity'] > 0): ?>
                                                    <span class="badge bg-success-subtle text-success px-2 py-1" style="font-size: 0.85rem;">
                                                        <?php echo htmlspecialchars($eq['available_quantity']); ?> <?php echo htmlspecialchars($eq['unit']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger px-2 py-1" style="font-size: 0.85rem;">
                                                        หมด
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-top-0 bg-light">
                    <button type="button" class="btn btn-secondary rounded-pill px-4" data-bs-dismiss="modal">ปิดหน้าต่าง</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php';
?>

    <script>
    const setMasterData = <?php echo json_encode( [
    'members' => $master_sets,
    'jobs' => $jobs_by_set
], JSON_UNESCAPED_UNICODE );
?>;

    const coordMasterData = <?php echo json_encode( $jobs_by_coord, JSON_UNESCAPED_UNICODE );
?>;

    function updateCoordPreview(selectElement, docId) {
        const userId = selectElement.value;
        const previewBox = document.getElementById('coordPreview_' + docId);

        if (!userId) {
            previewBox.classList.add('d-none');
            return;
        }

        previewBox.classList.remove('d-none');
        const selectedOption = selectElement.options[selectElement.selectedIndex];
        document.getElementById('previewCoordTitle_' + docId).innerText = selectedOption.getAttribute('data-name');

        const warningBox = document.getElementById('coordOverlapWarning_' + docId);
        if (selectedOption.getAttribute('data-overlap') === 'true') {
            warningBox.classList.remove('d-none');
        } else {
            warningBox.classList.add('d-none');
        }

        const jobs = coordMasterData[userId] || [];
        const renderJobs = (jobList) => {
            if (!jobList || jobList.length === 0)
            return '<div class="text-success ms-2 mt-1">- ว่าง (ยังไม่มีงาน) -</div>';
          
            return '<ul class="mb-0 mt-1 ps-3 text-secondary">' + jobList.map(j => `<li class="mb-2"><span class="fw-medium text-dark">${j.doc_name}</span><br><span class="text-primary"><i class="bi bi-calendar-event"></i> ${j.date_text}</span></li>`).join('') + '</ul>';
        };

        document.getElementById('preview_coord_jobs_' + docId).innerHTML = renderJobs(jobs);
    }

    // ฟังก์ชันสำหรับสลับพรีวิว เมื่อเลือกชื่อชุดใน Dropdown

    function updateSetPreview(selectElement, docId) {
        const setName = selectElement.value;
        const previewBox = document.getElementById('setPreview_' + docId);

        if (!setName) {
            previewBox.classList.add('d-none');
            return;
        }

        previewBox.classList.remove('d-none');
        document.getElementById('previewTitle_' + docId).innerText = setName;

        // อัปเดตรายชื่อสมาชิก
        const members = setMasterData.members[setName] || [];
        const memUl = document.getElementById('previewMembers_' + docId);
        if (members.length > 0) {
          
              memUl.innerHTML = members.map(m => `<li>${m}</li>`).join('');
        } else {
            memUl.innerHTML = '<li class="text-muted">ไม่มีสมาชิก</li>';
        }

        // อัปเดตรายการภาระงานทั้งหมดของชุด
        const jobs = setMasterData.jobs[setName] || [];
        const renderJobs = (jobList) => {
            if (!jobList || jobList.length === 0)
            return '<div class="text-success mt-2">- ว่าง (ยังไม่มีงาน) -</div>';
          
         return '<ul class="mb-0 mt-2 ps-3 text-secondary">' + jobList.map(j => `<li class="mb-2"><span class="fw-medium text-dark">${j.doc_name}</span><br><span class="text-primary"><i class="bi bi-calendar-event"></i> ${j.date_text}</span></li>`).join('') + '</ul>';  };

        document.getElementById('preview_work_dates_' + docId).innerHTML = renderJobs(jobs);
    }

    function checkEllipsis() {
        const contents = document.querySelectorAll('.text-content');

        contents.forEach(content => {
            if (content.scrollWidth > content.clientWidth) {
                const wrapper = content.nextElementSibling;
                if (wrapper && wrapper.classList.contains('btn-toggle-wrapper')) {
                    wrapper.classList.remove('d-none');
                }
            }
        });
    }

    window.addEventListener('load', checkEllipsis);
    window.addEventListener('resize', checkEllipsis);

    function toggleText(btn) {
        const container = btn.closest('.fw-bold');
        const content = container.querySelector('.text-content');

        if (content.classList.contains('text-truncate')) {
            content.classList.remove('text-truncate');
            content.style.maxWidth = 'none';
            content.style.whiteSpace = 'normal';
            content.style.wordBreak = 'break-all';
            btn.innerText = 'ย่อลง';
        } else {
            content.classList.add('text-truncate');
            content.style.maxWidth = '180px';
            content.style.whiteSpace = 'nowrap';
            btn.innerText = 'อ่านต่อ';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const alerts = document.querySelectorAll('.auto-close');
        alerts.forEach(function(alert) {
            setTimeout(function() {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 3000);
        });

        const urlParams = new URLSearchParams(window.location.search);
        const reopenJobId = urlParams.get('job_id');
        const modalType = urlParams.get('modal');

        if (reopenJobId) {
            let modalId = '';
            if (modalType === 'staff' || modalType === 'set') {

                modalId = 'manageStaffModal' + reopenJobId;
            } else if (modalType === 'coord') {
                modalId = 'assignCoordModal' + reopenJobId;
            } else if (modalType === 'borrow') {
                modalId = 'borrowEquipmentModal' + reopenJobId;
            }

            if (modalId) {
                const modalElement = document.getElementById(modalId);
                if (modalElement) {
                    const myModal = new bootstrap.Modal(modalElement);
                    myModal.show();
                }
            }
        }

        if (window.history.replaceState) {
            const url = new URL(window.location.href);
            ['success', 'edit_success', 'delete_success', 'cancel_success', 'set_success', 'staff_success',
                'staff_del_success', 'coord_success', 'coord_del_success', 'job_id', 'modal',
                'borrow_success', 'return_success', 'borrow_error', 'return_error'
            ].forEach(param => url.searchParams.delete(param));
            window.history.replaceState({}, '', url.href);
        }
    });
    </script>

    <script src='https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js'></script>
</body>

</html>
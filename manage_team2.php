<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
//session start
session_start();
// 1. เชื่อมต่อฐานข้อมูล
require_once 'db.php'; 


if(!isset($_SESSION['role']) || ($_SESSION['role'] !== 'head_operation' && $_SESSION['role'] !== 'super_admin')) {
    header ("Location: home.php");
    exit();
}

// ตรวจสอบ Role จาก Session สำหรับ Navbar
$user_role = $_SESSION['role'] ?? ''; 
$current_page = basename($_SERVER['PHP_SELF']);

// สมมติว่าเรากำลังจัดการงาน ID = 9
$job_id = isset($_GET['job_id']) ? (int)$_GET['job_id'] : 9;

// ==========================================
// ส่วนที่ 2: จัดการคำสั่ง POST (เพิ่ม/ลบ ข้อมูล)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        
        // 2.1 สร้างชุดปฏิบัติงานใหม่
        if ($_POST['action'] === 'create_set') {
            $new_set_name = trim($_POST['new_set_name']);
            if (!empty($new_set_name)) {
                $stmt = $conn->prepare("INSERT INTO job_assignments (job_id, staff_name, staff_role) VALUES (?, 'รอระบุบุคคล', ?)");
                $stmt->execute([$job_id, $new_set_name]);
            }
        }
        
        // 2.2 เพิ่มคนเข้าชุด
        elseif ($_POST['action'] === 'assign_staff') {
            $staff_name = $_POST['staff_name'];
            $target_set = $_POST['target_set'];
            if (!empty($staff_name) && !empty($target_set)) {
                
                // เช็คก่อนว่าคนนี้ถูกเพิ่มในชุดใดๆ ของงานนี้ไปแล้วหรือยัง (Backend Validation)
                $check_stmt = $conn->prepare("SELECT COUNT(*) FROM job_assignments WHERE job_id = ? AND staff_name = ?");
                $check_stmt->execute([$job_id, $staff_name]);
                $is_already_assigned = $check_stmt->fetchColumn() > 0;

                if (!$is_already_assigned) {
                    $stmt = $conn->prepare("INSERT INTO job_assignments (job_id, staff_name, staff_role) VALUES (?, ?, ?)");
                    $stmt->execute([$job_id, $staff_name, $target_set]);
                    
                    // ลบตัวสำรอง "รอระบุบุคคล" ออกเมื่อมีคนจริงๆ ถูกเพิ่มเข้าไป
                    $stmt = $conn->prepare("DELETE FROM job_assignments WHERE job_id = ? AND staff_role = ? AND staff_name LIKE 'รอระบุบุคคล%'");
                    $stmt->execute([$job_id, $target_set]);
                }
            }
        }

        // 2.3 ลบคนออกจากชุด
        elseif ($_POST['action'] === 'remove_staff') {
            $assignment_id = (int)$_POST['assignment_id'];
            $stmt = $conn->prepare("DELETE FROM job_assignments WHERE id = ?");
            $stmt->execute([$assignment_id]);
        }
        
        header("Location: manage_team.php?job_id=" . $job_id);
        exit;
    }
}

// ==========================================
// ส่วนที่ 3: ดึงข้อมูลเพื่อแสดงผล
// ==========================================
$stmt_sets = $conn->prepare("SELECT DISTINCT staff_role FROM job_assignments WHERE job_id = ? ORDER BY staff_role ASC");
$stmt_sets->execute([$job_id]);
$existing_sets = $stmt_sets->fetchAll(PDO::FETCH_COLUMN);

try {
    // ดึงรายชื่อฝ่ายปฏิบัติงานทุกคน (ยกเว้นผู้ประสานงาน) และ "ตัดคนที่อยู่ในชุดของงานนี้ไปแล้ว" ออก
    $stmt_dropdown = $conn->prepare("
        SELECT DISTINCT 
            u.id, 
            u.fname, 
            u.lname, 
            u.depart_name 
        FROM users u
        WHERE u.depart_name = 'ฝ่ายปฏิบัติงาน' 
          AND u.id NOT IN (SELECT DISTINCT sent_to FROM documents WHERE sent_to IS NOT NULL)
          AND CONCAT(u.fname, ' ', u.lname) NOT IN (
              SELECT staff_name FROM job_assignments WHERE job_id = ?
          )
        ORDER BY u.fname ASC
    ");
    // ส่งค่า $job_id เข้าไปเพื่อให้ดึงข้อมูลเฉพาะงานนี้
    $stmt_dropdown->execute([$job_id]);
    $all_coordinators = $stmt_dropdown->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

$stmt_all = $conn->prepare("SELECT id, staff_name, staff_role FROM job_assignments WHERE job_id = ? ORDER BY staff_role ASC, staff_name ASC");
$stmt_all->execute([$job_id]);
$all_assignments = $stmt_all->fetchAll();

$grouped_sets = [];
foreach ($all_assignments as $row) {
    $grouped_sets[$row['staff_role']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบจัดการชุดปฏิบัติงาน | ระบบพลับพลาพิธี</title>
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <style>
        body { font-family: 'Prompt', sans-serif; background-color: #f4f6f9; }
        .table-container { border-radius: 1rem; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); }
    </style>
</head>

<body class="min-h-screen flex flex-col">

    <?php require_once 'menu.php'; ?>

    <main class="flex-grow w-full max-w-screen-2xl mx-auto p-6 lg:p-10">
        <!-- ปุ่มย้อนกลับ -->
        <a href="manage_operation2.php" class="inline-flex items-center gap-2 mb-4 text-slate-500 hover:text-blue-600 transition-colors font-medium">
            <i class="bi bi-arrow-left-circle-fill text-xl"></i>
            <span>ย้อนกลับไปหน้าจัดการงาน</span>
        </a>

        <!-- Header และ ปุ่มเปิด Modal สร้างชุดปฏิบัติงานใหม่ -->
        <header class="mb-8 flex flex-col md:flex-row md:items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <div class="h-10 w-2 bg-blue-600 rounded-full"></div>
                <h1 class="text-4xl font-extrabold text-slate-800">จัดการชุดปฏิบัติงานในการประสานงาน</h1>
            </div>
            <button onclick="openModal('modal-create-set')" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-bold shadow-md shadow-blue-200 transition-colors flex items-center justify-center gap-2">
                <i class="bi bi-folder-plus text-xl"></i> สร้างชุดปฏิบัติงานใหม่
            </button>
        </header>

        <!-- ส่วนของตารางแสดงชุดปฏิบัติงาน -->
        <section class="bg-white table-container border border-slate-200">
            <div class="px-8 py-6 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <h2 class="text-xl font-bold text-slate-800 flex items-center gap-3">
                    <i class="bi bi-table text-blue-500"></i>
                    รายชื่อชุดปฏิบัติงานและบุคลากร
                </h2>
                <span class="bg-blue-100 text-blue-700 px-4 py-1 rounded-full text-sm font-semibold">
                    รวมทั้งหมด <?= count($existing_sets) ?> ชุด
                </span>
            </div>
            
            <div class="overflow-x-auto">
                <table class="w-full text-left">
                    <thead>
                        <tr class="bg-[#172430] text-white text-sm uppercase tracking-wider">
                            <th class="px-8 py-4 font-bold w-1/3 border-r border-slate-600">ชื่อชุดปฏิบัติงาน</th>
                            <th class="px-8 py-4 font-bold">รายชื่อบุคลากรในชุด</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200">
                        <?php if (empty($grouped_sets)): ?>
                            <tr>
                                <td colspan="2" class="px-8 py-20 text-center bg-white">
                                    <i class="bi bi-inbox text-5xl text-slate-200 block mb-4"></i>
                                    <span class="text-slate-400 font-medium">ยังไม่มีข้อมูลการจัดสรรทีมในขณะนี้ ลองสร้างชุดงานด้วยปุ่มด้านบน</span>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($grouped_sets as $setName => $members): ?>
                                <tr class="hover:bg-slate-50 transition-colors bg-white">
                                    <td class="px-8 py-6 align-top border-r border-slate-200">
                                        <div class="font-bold text-blue-800 text-xl mb-3"><?= htmlspecialchars($setName) ?></div>
                                        <button onclick="openAssignModal('<?= htmlspecialchars($setName) ?>')" 
                                                class="bg-emerald-500 hover:bg-emerald-600 text-white px-4 py-2 rounded-lg text-sm font-bold shadow-sm transition-all flex items-center gap-2">
                                            <i class="bi bi-person-plus-fill"></i> เพิ่มคนเข้าชุดนี้
                                        </button>
                                    </td>
                                    
                                    <td class="px-8 py-6">
                                        <div class="flex flex-wrap gap-3">
                                            <?php 
                                            $hasPeople = false;
                                            foreach ($members as $m): 
                                                if ($m['staff_name'] !== 'รอระบุบุคคล'):
                                                    $hasPeople = true;
                                            ?>
                                                <div class="bg-white border border-slate-300 px-4 py-2 rounded-xl shadow-sm flex items-center gap-3 group hover:border-blue-400 transition-colors">
                                                    <div class="w-2 h-2 rounded-full bg-emerald-500"></div>
                                                    <span class="text-slate-700 font-medium"><?= htmlspecialchars($m['staff_name']) ?></span>
                                                    
                                                    <form method="POST" onsubmit="return confirm('ยืนยันการลบ <?= htmlspecialchars($m['staff_name']) ?> ออกจากชุดใช่หรือไม่?');" class="inline-block ml-1">
                                                        <input type="hidden" name="action" value="remove_staff">
                                                        <input type="hidden" name="assignment_id" value="<?= $m['id'] ?>">
                                                        <button type="submit" class="text-slate-300 hover:text-red-500 transition-colors focus:outline-none" title="ลบชื่อนี้">
                                                            <i class="bi bi-x-circle-fill"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php 
                                                endif;
                                            endforeach; 
                                            
                                            if (!$hasPeople): 
                                            ?>
                                                <span class="text-slate-400 italic text-sm py-2"><i class="bi bi-info-circle"></i> ยังไม่มีรายชื่อบุคลากร กดปุ่มเพิ่มคนด้านซ้ายเพื่อเลือกรายชื่อ</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- Modal 1: สร้างชุดปฏิบัติงานใหม่ -->
    <div id="modal-create-set" class="fixed inset-0 z-[60] hidden bg-slate-900/60 backdrop-blur-sm items-center justify-center p-4 opacity-0 transition-opacity duration-300">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-300">
            <div class="bg-slate-50 px-8 py-6 border-b flex justify-between items-center">
                <h3 class="text-xl font-bold text-slate-800 tracking-tight"><i class="bi bi-folder-plus text-blue-600 mr-2"></i>สร้างชุดปฏิบัติงานใหม่</h3>
                <button type="button" onclick="closeModal('modal-create-set')" class="text-slate-400 hover:text-slate-600 text-xl"><i class="bi bi-x-lg"></i></button>
            </div>
            
            <form method="POST">
                <div class="p-8">
                    <input type="hidden" name="action" value="create_set">
                    <label class="block text-sm font-medium text-slate-700 mb-2">ชื่อชุดปฏิบัติงาน <span class="text-red-500">*</span></label>
                    <input type="text" name="new_set_name" required 
                           class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 text-slate-700" 
                           placeholder="ระบุชื่อชุด เช่น ฝ่ายต้อนรับ, ชุดที่ 1...">
                </div>
                <div class="px-8 py-6 border-t border-slate-100 bg-slate-50 flex gap-3">
                    <button type="button" onclick="closeModal('modal-create-set')" class="flex-1 py-3 text-slate-600 bg-white border border-slate-300 rounded-xl font-bold hover:bg-slate-50 transition-all">ยกเลิก</button>
                    <button type="submit" class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl font-bold shadow-lg shadow-blue-200 transition-all flex justify-center items-center gap-2">
                        <i class="bi bi-save"></i> บันทึกชุดงาน
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal 2: เลือกบุคลากรเข้าชุด -->
    <div id="modal-assign-staff" class="fixed inset-0 z-[60] hidden bg-slate-900/60 backdrop-blur-sm items-center justify-center p-4 opacity-0 transition-opacity duration-300">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform scale-95 transition-transform duration-300">
            <div class="bg-slate-50 px-8 py-6 border-b flex justify-between items-center">
                <h3 class="text-xl font-bold text-slate-800 tracking-tight">เลือกบุคลากรเข้าชุด</h3>
                <button type="button" onclick="closeModal('modal-assign-staff')" class="text-slate-400 hover:text-slate-600 text-xl"><i class="bi bi-x-lg"></i></button>
            </div>
            
            <form method="POST">
                <div class="p-8">
                    <input type="hidden" name="action" value="assign_staff">
                    <input type="hidden" name="target_set" id="hidden_target_set">

                    <div class="bg-blue-50 text-blue-800 px-5 py-4 rounded-2xl border border-blue-100 flex items-center gap-3 mb-6">
                        <i class="bi bi-tags-fill text-xl text-blue-500"></i>
                        <span class="text-sm font-medium">เพิ่มลงใน: <strong id="display-target-set" class="font-bold text-blue-700 text-lg ml-1"></strong></span>
                    </div>

                    <label class="block text-sm font-medium text-slate-700 mb-2">ค้นหา / เลือกรายชื่อ <span class="text-red-500">*</span></label>
                    <select name="staff_name" required class="w-full border border-slate-300 rounded-xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-blue-500 bg-white text-slate-700 font-medium">
                        <?php if (empty($all_coordinators)): ?>
                            <option value="" selected disabled>-- ไม่พบรายชื่อผู้ประสานงานที่ว่าง --</option>
                        <?php else: ?>
                            <option value="" selected disabled>-- คลิกเพื่อเลือกรายชื่อ --</option>
                            <?php foreach ($all_coordinators as $staff): ?>  
                                <option value="<?= htmlspecialchars($staff['fname'] . ' ' . $staff['lname']) ?>">
                                    <?= htmlspecialchars($staff['fname'] . ' ' . $staff['lname'] . ' (' . $staff['depart_name'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                <div class="px-8 py-6 border-t border-slate-100 bg-slate-50 flex gap-3">
                    <button type="button" onclick="closeModal('modal-assign-staff')" class="flex-1 py-3 text-slate-600 bg-white border border-slate-300 rounded-xl font-bold hover:bg-slate-50 transition-all">ยกเลิก</button>
                    <?php if (!empty($all_coordinators)): ?>
                        <button type="submit" class="flex-1 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl font-bold shadow-lg shadow-emerald-200 transition-all flex justify-center items-center gap-2">
                            <i class="bi bi-check-circle"></i> ยืนยันเพิ่ม
                        </button>
                    <?php else: ?>
                        <button type="button" disabled class="flex-1 py-3 bg-slate-300 text-slate-500 rounded-xl font-bold cursor-not-allowed flex justify-center items-center gap-2">
                            ไม่มีคนให้เลือก
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <?php include 'footer.php'; ?>
    
    <script>
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            const content = modal.firstElementChild;
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            
            setTimeout(() => { 
                modal.classList.remove('opacity-0');
                modal.classList.add('opacity-100');
                content.classList.remove('scale-95');
                content.classList.add('scale-100');
            }, 20);
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            const content = modal.firstElementChild;
            
            modal.classList.remove('opacity-100');
            modal.classList.add('opacity-0');
            content.classList.remove('scale-100');
            content.classList.add('scale-95');
            
            setTimeout(() => { 
                modal.classList.add('hidden'); 
                modal.classList.remove('flex'); 
            }, 300);
        }

        function openAssignModal(setName) {
            document.getElementById('display-target-set').textContent = setName;
            document.getElementById('hidden_target_set').value = setName;
            openModal('modal-assign-staff');
        }

        // ปิด Modal เมื่อคลิกพื้นที่ว่างด้านนอก (ทำงานครอบคลุมทุก Modal ที่ id ขึ้นต้นด้วย modal-)
        document.querySelectorAll('[id^="modal-"]').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if(e.target === this) {
                    closeModal(this.id);
                }
            });
        });
    </script>
</body>
</html>
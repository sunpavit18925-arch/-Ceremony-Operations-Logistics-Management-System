<?php
$personnel_data = [
    'coordinators' => [
        ['name' => 'นายสมชาย ใจดี', 'position' => 'นักวิชาการ...', 'phone' => '081-xxx-xxxx'],
        ['name' => 'นายวิชาญ รักงาน', 'position' => 'เจ้าพนักงาน...', 'phone' => '082-xxx-xxxx'],
    ],
    'operations' => [
        '1' => [
            ['name' => 'นายพงษ์ศักดิ์ มั่นคง', 'position' => 'นายช่าง...', 'phone' => '083-xxx-xxxx'],
            ['name' => 'นายธนา มีทรัพย์', 'position' => 'พนักงาน...', 'phone' => '084-xxx-xxxx'],
        ],
        '2' => [
            ['name' => 'นายกิตติศักดิ์ สุดสวย', 'position' => 'นายช่าง...', 'phone' => '085-xxx-xxxx'],
        ],
    ],
    'drivers' => [
        ['name' => 'นายมานะ ขับขี่', 'position' => 'พขร.', 'phone' => '086-xxx-xxxx'],
        ['name' => 'นายอุดม อดทน', 'position' => 'พขร.', 'phone' => '087-xxx-xxxx'],
    ],
    'supplies' => [
        ['name' => 'นายหนึ่ง พัสดุ', 'position' => 'เจ้าหน้าที่พัสดุ', 'phone' => '088-xxx-xxxx'],
        ['name' => 'นายสอง พัสดุ', 'position' => 'เจ้าหน้าที่พัสดุ', 'phone' => '089-xxx-xxxx'],
        ['name' => 'นายสาม พัสดุ', 'position' => 'เจ้าหน้าที่พัสดุ', 'phone' => '090-xxx-xxxx'],
    ],
];
?>

<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>ข้อมูลบุคลากร</title>

<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;600&display=swap" rel="stylesheet">

<style>
body { font-family: 'Kanit', sans-serif; }
.category-header {
    padding: 10px 16px;
    font-weight: 600;
    border-left: 5px solid;
    border-radius: 8px;
    margin-top: 20px;
}
</style>
</head>

<body class="bg-gradient-to-br from-slate-100 to-slate-200 p-6">

<div class="max-w-6xl mx-auto">

    <!-- HEADER -->
    <div class="bg-white p-6 rounded-2xl shadow-lg mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-800">📋 ระบบข้อมูลบุคลากร</h1>
            <p class="text-slate-500">แสดงรายชื่อบุคลากรทั้งหมด</p>
        </div>

        <input type="text" id="searchInput"
            placeholder="🔍 ค้นหา..."
            class="px-4 py-2 border rounded-xl shadow-sm focus:ring-2 focus:ring-blue-400">
    </div>

    <!-- TABLE -->
    <div class="bg-white rounded-2xl shadow-lg overflow-hidden">

        <!-- HEADER ROW -->
        <div class="grid grid-cols-12 bg-slate-800 text-white p-4 text-sm font-semibold">
            <div class="col-span-1 text-center">ชุด</div>
            <div class="col-span-4">ชื่อ</div>
            <div class="col-span-4">ตำแหน่ง</div>
            <div class="col-span-3">โทร</div>
        </div>

        <div id="personnelList">

            <!-- ผู้ประสานงาน -->
            <div class="category-header bg-blue-50 text-blue-800 border-blue-500">
                👨‍💼 ผู้ประสานงาน
            </div>

            <?php foreach($personnel_data['coordinators'] as $p): ?>
            <div class="grid grid-cols-12 p-4 border-b hover:bg-blue-50 transition personnel-item">
                <div class="col-span-1 text-center">-</div>
                <div class="col-span-4 font-medium"><?php echo $p['name']; ?></div>
                <div class="col-span-4"><?php echo $p['position']; ?></div>
                <div class="col-span-3 text-blue-600"><?php echo $p['phone']; ?></div>
            </div>
            <?php endforeach; ?>

            <!-- ชุดปฏิบัติงาน -->
            <?php foreach($personnel_data['operations'] as $team_id => $members): ?>
            <div class="category-header bg-emerald-50 text-emerald-800 border-emerald-500">
                ⚙️ ชุดปฏิบัติงานที่ <?php echo $team_id; ?>
            </div>

            <?php foreach($members as $p): ?>
            <div class="grid grid-cols-12 p-4 border-b hover:bg-emerald-50 transition personnel-item">
                <div class="col-span-1 text-center font-bold text-emerald-600"><?php echo $team_id; ?></div>
                <div class="col-span-4 font-medium"><?php echo $p['name']; ?></div>
                <div class="col-span-4"><?php echo $p['position']; ?></div>
                <div class="col-span-3 text-blue-600"><?php echo $p['phone']; ?></div>
            </div>
            <?php endforeach; ?>
            <?php endforeach; ?>

            <!-- คนขับรถ -->
            <div class="category-header bg-amber-50 text-amber-800 border-amber-500">
                🚗 คนขับรถ
            </div>

            <?php foreach($personnel_data['drivers'] as $p): ?>
            <div class="grid grid-cols-12 p-4 border-b hover:bg-amber-50 transition personnel-item">
                <div class="col-span-1 text-center">-</div>
                <div class="col-span-4 font-medium"><?php echo $p['name']; ?></div>
                <div class="col-span-4"><?php echo $p['position']; ?></div>
                <div class="col-span-3 text-blue-600"><?php echo $p['phone']; ?></div>
            </div>
            <?php endforeach; ?>

            <!-- 🔥 ฝ่ายพัสดุ (เพิ่มใหม่) -->
            <div class="category-header bg-purple-50 text-purple-800 border-purple-500">
                📦 ฝ่ายพัสดุ
            </div>

            <?php foreach($personnel_data['supplies'] as $p): ?>
            <div class="grid grid-cols-12 p-4 border-b hover:bg-purple-50 transition personnel-item">
                <div class="col-span-1 text-center">-</div>
                <div class="col-span-4 font-medium"><?php echo $p['name']; ?></div>
                <div class="col-span-4"><?php echo $p['position']; ?></div>
                <div class="col-span-3 text-blue-600"><?php echo $p['phone']; ?></div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>
</div>

<script>
document.getElementById('searchInput').addEventListener('keyup', function() {
    let filter = this.value.toUpperCase();
    let items = document.querySelectorAll('.personnel-item');

    items.forEach(item => {
        let text = item.textContent;
        item.style.display = text.toUpperCase().includes(filter) ? "" : "none";
    });
});
</script>

</body>
</html>
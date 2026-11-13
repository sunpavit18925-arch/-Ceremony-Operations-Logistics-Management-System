<?php
// สมมติข้อมูลที่ดึงมาจากฐานข้อมูล
$job_id = "001/2569";
$job_type = "รับเสด็จฯ";
$job_name = "งานรับเสด็จฯ พระบาทสมเด็จพระเจ้าอยู่หัว";
$location = "สำนักงานโยธาธิการและผังเมืองจังหวัด... ตำบล... อำเภอ... จังหวัด...";
$host_unit = "จังหวัดเชียงราย";
$coordinator_main = "นายสมชาย ใจดี";
$phone = "081-234-5678";
$period = "1 เมษายน 2569 ถึง 5 เมษายน 2569";
$meeting_date = "25 มีนาคม 2569";
$dpt_period = "28 มีนาคม 2569 ถึง 6 เมษายน 2569";
?>

<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>แสดงข้อมูลงาน (Output) - ระบบพลับพลาพิธี</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
    body {
        font-family: 'Kanit', sans-serif;
        background-color: #f1f5f9;
    }

    @media print {
        .no-print {
            display: none;
        }

        body {
            background-color: white;
        }

        /* เปลี่ยนจาก shadow เป็น box-shadow */
        .print-card {
            border: none !important;
            box-shadow: none !important;
        }
    }
    </style>
</head>

<body class="p-4 md:p-8">

    <div class="max-w-5xl mx-auto space-y-6">

        <div class="flex justify-end gap-2 no-print">
            <button onclick="window.history.back()"
                class="px-4 py-2 bg-slate-500 text-white rounded-lg hover:bg-slate-600 transition shadow-sm">ย้อนกลับ</button>
            <button onclick="window.print()"
                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path
                        d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
                </svg>
                พิมพ์รายงาน
            </button>
        </div>

        <div class="bg-white rounded-2xl shadow-md border border-slate-200 overflow-hidden print-card">
            <div class="bg-slate-800 p-4 text-white">
                <h2 class="text-lg font-semibold flex items-center gap-2 uppercase tracking-wide">
                    แสดงข้อมูลงาน (Output)
                </h2>
            </div>

            <div class="p-6 md:p-8 space-y-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-4">
                    <div class="flex"><span>รหัสงาน:</span> <span class="label-dot"><?php echo $job_id; ?></span></div>
                    <div class="flex"><span>ประเภท:</span> <span class="label-dot"><?php echo $job_type; ?></span></div>
                    <div class="flex md:col-span-2"><span>ชื่องาน:</span> <span
                            class="label-dot"><?php echo $job_name; ?></span></div>
                    <div class="flex md:col-span-2"><span>สถานที่จัดงาน:</span> <span
                            class="label-dot"><?php echo $location; ?></span></div>
                    <div class="flex"><span>หน่วยงานเจ้าภาพ:</span> <span
                            class="label-dot"><?php echo $host_unit; ?></span></div>
                    <div class="flex"><span>ผู้ประสานงาน:</span> <span
                            class="label-dot"><?php echo $coordinator_main; ?> <b>โทร:</b> <?php echo $phone; ?></span>
                    </div>
                    <div class="flex md:col-span-2"><span>ระยะเวลางาน:</span> <span
                            class="label-dot"><?php echo $period; ?></span></div>
                    <div class="flex"><span>วันประชุมส่วนล่วงหน้า:</span> <span
                            class="label-dot"><?php echo $meeting_date; ?></span></div>
                    <div class="flex"><span>ระยะเวลาทำงาน กอพ.:</span> <span
                            class="label-dot"><?php echo $dpt_period; ?></span></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-md border border-slate-200 overflow-hidden print-card">
            <div class="bg-slate-700 p-4 text-white">
                <h2 class="text-lg font-semibold flex items-center gap-2">แสดงข้อมูลบุคลากร (Output)</h2>
            </div>
            <div class="p-6 md:p-8 grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h3 class="font-bold text-blue-700 border-b pb-1 mb-3">ผู้ประสานงาน</h3>
                    <ul class="text-sm space-y-1">
                        <li>1. นายสมชาย ใจดี</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-bold text-emerald-700 border-b pb-1 mb-3">ชุดปฏิบัติงาน</h3>
                    <div class="text-sm space-y-2">
                        <p class="font-medium text-slate-500">ชุดที่ 1</p>
                        <ul class="ml-2 space-y-1">
                            <li>1. นายมานะ อดทน</li>
                            <li>2. นายปิติ ยินดี</li>
                        </ul>
                    </div>
                </div>
                <div>
                    <h3 class="font-bold text-orange-700 border-b pb-1 mb-3">สนับสนุนเพิ่มเติม</h3>
                    <div class="text-sm space-y-3">
                        <div>
                            <span class="text-slate-500 font-medium">ฝ่ายพัสดุ:</span>
                            <span class="ml-1 text-slate-800">1. นายสมบัติ รักเรียน</span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-medium">ฝ่ายอาคาร:</span>
                            <span class="ml-1 text-slate-400 italic">-</span>
                        </div>
                        <div>
                            <span class="text-slate-500 font-medium">อื่นๆ:</span>
                            <span class="ml-1 text-slate-400 italic">-</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-2xl shadow-md border border-slate-200 overflow-hidden print-card">
            <div class="p-6 md:p-8">
                <h3 class="text-lg font-bold text-slate-800 mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0z" />
                        <path
                            d="M13 16V6a1 1 0 00-1-1H4a1 1 0 00-1 1v10a1 1 0 001 1h1m8-1a1 1 0 01-1 1H9m4-1V8a1 1 0 011-1h2.586a1 1 0 01.707.293l3.414 3.414a1 1 0 01.293.707V16a1 1 0 01-1 1h-1m-6-1a1 1 0 001 1h1M5 17a2 2 0 104 0m-4 0a2 2 0 114 0m6 0a2 2 0 104 0m-4 0a2 2 0 114 0" />
                    </svg>
                    ยานพาหนะ
                </h3>
                <div class="overflow-x-auto border rounded-xl">
                    <table class="w-full text-sm text-left">
                        <thead class="bg-slate-50 text-slate-600 border-b uppercase text-xs">
                            <tr>
                                <th class="p-4 font-semibold">ประเภทรถ</th>
                                <th class="p-4 font-semibold">ทะเบียน / คนขับ</th>
                                <th class="p-4 font-semibold text-center">ผู้ช่วย 1</th>
                                <th class="p-4 font-semibold text-center">ผู้ช่วย 2</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <tr class="hover:bg-slate-50/50">
                                <td class="p-4 font-medium text-slate-700">รถกระบะ 4 ประตู</td>
                                <td class="p-4">
                                    <div class="font-bold">กข 1234 กทม.</div>
                                    <div class="text-slate-500">นายสมชาย ขับขี่</div>
                                </td>
                                <td class="p-4 text-center text-slate-400">-</td>
                                <td class="p-4 text-center text-slate-400">-</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50">
                                <td class="p-4 font-medium text-slate-700">รถบรรทุก 6 ตัน</td>
                                <td class="p-4">
                                    <div class="font-bold">80-9999 เชียงราย</div>
                                    <div class="text-slate-500">นายมั่นคง ใจแกร่ง</div>
                                </td>
                                <td class="p-4 text-center text-slate-400">-</td>
                                <td class="p-4 text-center text-slate-400">-</td>
                            </tr>
                            <tr class="hover:bg-slate-50/50">
                                <td class="p-4 font-medium text-slate-700">รถห้องสรง</td>
                                <td class="p-4">
                                    <div class="font-bold">วว 555 กรุงเทพฯ</div>
                                    <div class="text-slate-500 text-xs">คนขับ: นายวีระพงศ์</div>
                                </td>
                                <td class="p-4 text-center">นายมานพ</td>
                                <td class="p-4 text-center">นายสวัสดิ์</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <script>
    // สามารถเพิ่มฟังก์ชันตรวจสอบข้อมูลก่อนพิมพ์ได้ที่นี่
    </script>
</body>

</html>
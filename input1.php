<script src="https://cdn.tailwindcss.com"></script>
<link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">

<style>
body {
    font-family: 'Kanit', sans-serif;
    background-color: #f8fafc;
}

.input-focus:focus {
    outline: none;
    border-color: #2563eb;
    /* ใช้ box-shadow เพื่อจำลอง ring ของ Tailwind */
    /* พารามิเตอร์: x y blur spread color */
    box-shadow: 0 0 0 2px #dbeafe; 
}
</style>

<div class="max-w-5xl mx-auto my-10 p-4">
    <form action="save_job.php" method="POST" class="space-y-8">

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden">
            <div class="bg-slate-800 p-4 flex items-center gap-3">
                <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01">
                    </path>
                </svg>
                <h2 class="text-white text-lg font-medium">ส่วนที่ 1: ข้อมูลงาน (Input)</h2>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-12 gap-6">
                <div class="md:col-span-3">
                    <label class="block text-sm font-medium text-slate-700 mb-1">รหัสงาน</label>
                    <input type="text" name="job_code" placeholder="001/2569"
                        class="w-full border border-slate-300 p-2.5 rounded-lg input-focus" required>
                </div>
                <div class="md:col-span-4">
                    <label class="block text-sm font-medium text-slate-700 mb-1">ประเภทงาน</label>
                    <select name="job_type" class="w-full border border-slate-300 p-2.5 rounded-lg input-focus">
                        <option value="รับเสด็จฯ">👑 รับเสด็จฯ</option>
                        <option value="ขอความอนุเคราะห์">🤝 ขอความอนุเคราะห์</option>
                        <option value="อื่นๆ">📝 อื่นๆ</option>
                    </select>
                </div>
                <div class="md:col-span-5">
                    <label class="block text-sm font-medium text-slate-700 mb-1">หน่วยงานเจ้าภาพ</label>
                    <input type="text" name="host_agency" placeholder="จังหวัด/กอง/สำนัก..."
                        class="w-full border border-slate-300 p-2.5 rounded-lg input-focus">
                </div>

                <div class="md:col-span-12">
                    <label class="block text-sm font-medium text-slate-700 mb-1">ชื่องาน </label>
                    <input type="text" name="job_name"
                        class="w-full border border-slate-300 p-2.5 rounded-lg input-focus">
                </div>

                <div class="md:col-span-12">
                    <label class="block text-sm font-medium text-slate-700 mb-1">สถานที่จัดงาน </label>
                    <textarea name="location" rows="2"
                        class="w-full border border-slate-300 p-2.5 rounded-lg input-focus"></textarea>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-200">
                <h3 class="text-slate-800 font-semibold mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z">
                        </path>
                    </svg>
                    วันงานและประชุม
                </h3>
                <div class="space-y-4">
                    <div>
                                               <label class="text-xs text-slate-500">ระยะเวลางาน (เริ่มต้น - สิ้นสุด)</label>
                        <div class="flex gap-2">
                            <input type="date" name="start_date" class="w-full border border-slate-300 p-2 rounded-lg">
                            <input type="date" name="end_date" class="w-full border border-slate-300 p-2 rounded-lg">
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-slate-500">วันประชุมส่วนล่วงหน้า</label>
                        <input type="date" name="pre_meeting_date"
                            class="w-full border border-slate-300 p-2 rounded-lg">
                    </div>
                </div>
            </div>

            <div class="bg-blue-50 p-6 rounded-2xl shadow-sm border border-blue-100">
                <h3 class="text-blue-900 font-semibold mb-4 flex items-center gap-2">
                    <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    ระยะเวลา ทำงาน กอพ.
                </h3>
                <div class="space-y-4">
                    <div class="flex flex-col">
                        <label class="text-xs text-blue-700">วันที่เริ่มเข้าทำงาน</label>
                        <input type="date" name="dpt_work_start" class="border border-blue-200 p-2 rounded-lg bg-white">
                    </div>
                    <div class="flex flex-col">
                        <label class="text-xs text-blue-700">วันที่สิ้นสุดงาน</label>
                        <input type="date" name="dpt_work_end" class="border border-blue-200 p-2 rounded-lg bg-white">
                    </div>
                </div>
            </div>
        </div>

        

        <div class="flex justify-end gap-4">
            <button type="reset"
                class="px-6 py-2.5 rounded-xl border border-slate-300 text-slate-600 hover:bg-slate-100 transition-all font-medium">ล้างข้อมูล</button>
            <button type="submit"
                class="px-8 py-2.5 rounded-xl bg-blue-600 text-white shadow-lg shadow-blue-200 hover:bg-blue-700 transition-all font-semibold flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                บันทึกคิวงานพับพลา
            </button>
        </div>

    </form>
</div>
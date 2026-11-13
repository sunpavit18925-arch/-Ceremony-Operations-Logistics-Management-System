<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <script src="https://cdn.tailwindcss.com"></script>

    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
<style>

    body { 
            font-family: 'Kanit', sans-serif; 
            background-color: #f8fafc; /* สีพื้นหลัง */
        }
    /* Custom CSS สำหรับระบบพับพลาพิธี */

/* 1. ปรับแต่ง Scrollbar แนวนอนของตารางยานพาหนะให้ดูโมเดิร์นคลีนๆ */
.overflow-x-auto::-webkit-scrollbar {
    height: 8px; /* ความหนาของ Scrollbar */
}
.overflow-x-auto::-webkit-scrollbar-track {
    background: #f8fafc; /* สีพื้นหลัง Track */
    border-radius: 8px;
}
.overflow-x-auto::-webkit-scrollbar-thumb {
    background: #cbd5e1; /* สีตัวเลื่อน */
    border-radius: 8px;
}
.overflow-x-auto::-webkit-scrollbar-thumb:hover {
    background: #94a3b8; /* สีตอนเอาเมาส์ชี้ตัวเลื่อน */
}

/* 2. เอฟเฟกต์ Hover สำหรับ Card ข้อมูลบุคลากร (ให้ดูลอยขึ้นนิดๆ) */
.grid > div {
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.3s ease;
}
.grid > div:hover {
    transform: translateY(-4px); /* ยกการ์ดขึ้น 4px */
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.01);
}

/* 3. เอฟเฟกต์ตารางยานพาหนะตอน Hover ให้ไฮไลต์ชัดเจนขึ้น */
tbody tr.group {
    transition: all 0.2s ease-in-out;
}
tbody tr.group:hover {
    background-color: #f1f5f9; /* เปลี่ยนสีพื้นหลังแถวให้เข้มขึ้นนิดนึง */
    transform: scale(1.002); /* ขยายขนาดแถวนิดๆ ให้ดูมีมิติ */
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
    position: relative;
    z-index: 10;
}

/* 4. ปรับจูน Input ให้สมูทและมีออร่าตอนคลิกพิมพ์ */
input[type="text"] {
    transition: all 0.2s ease;
}
input[type="text"]:focus {
    background-color: #ffffff;
    /* เปลี่ยนสีแสงออร่า (Box-shadow) ให้เข้ากับ Theme สีฟ้า */
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15) !important; 
    border-color: #60a5fa !important;
}

/* 5. ปุ่มเพิ่มรถ: เอฟเฟกต์ตอนกด (Active) */
button:active {
    transform: scale(0.95);
}
</style>
</head>


<body>
    
</body>
</html>

<div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
    
    <div class="bg-slate-50 p-5 border-b border-slate-200 flex justify-between items-center">
        <h2 class="text-slate-800 text-lg font-semibold flex items-center gap-2">
            <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            การจัดบุคลากรและยานพาหนะ
        </h2>
    </div>

    <div class="p-6 md:p-8 space-y-10">
        
        <div>
            <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider mb-4 border-b pb-2">ข้อมูลบุคลากร</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <div class="bg-blue-50/50 p-5 rounded-xl border border-blue-100">
                    <div class="flex items-center gap-2 mb-4 text-blue-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        <h4 class="font-semibold">ผู้ประสานงาน (Phase 1)</h4>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-slate-400 w-4">1.</span>
                            <input type="text" name="coordinator[]" placeholder="ระบุชื่อ-สกุล" class="flex-1 w-full text-sm bg-white border border-slate-200 rounded-md p-2 focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all shadow-sm">
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-slate-400 w-4">2.</span>
                            <input type="text" name="coordinator[]" placeholder="ระบุชื่อ-สกุล" class="flex-1 w-full text-sm bg-white border border-slate-200 rounded-md p-2 focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all shadow-sm">
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-slate-400 w-4">3.</span>
                            <input type="text" name="coordinator[]" placeholder="ระบุชื่อ-สกุล" class="flex-1 w-full text-sm bg-white border border-slate-200 rounded-md p-2 focus:ring-2 focus:ring-blue-100 focus:border-blue-400 outline-none transition-all shadow-sm">
                        </div>
                    </div>
                </div>

                <div class="bg-emerald-50/50 p-5 rounded-xl border border-emerald-100">
                    <div class="flex items-center gap-2 mb-4 text-emerald-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        <h4 class="font-semibold">ชุดปฏิบัติงาน</h4>
                    </div>
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-1 rounded w-14 text-center">ชุดที่ 1</span>
                            <input type="text" name="team_1[]" placeholder="ระบุผู้รับผิดชอบ" class="flex-1 w-full text-sm bg-white border border-slate-200 rounded-md p-2 focus:ring-2 focus:ring-emerald-100 focus:border-emerald-400 outline-none transition-all shadow-sm">
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-1 rounded w-14 text-center">ชุดที่ 2</span>
                            <input type="text" name="team_2[]" placeholder="ระบุผู้รับผิดชอบ" class="flex-1 w-full text-sm bg-white border border-slate-200 rounded-md p-2 focus:ring-2 focus:ring-emerald-100 focus:border-emerald-400 outline-none transition-all shadow-sm">
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2 py-1 rounded w-14 text-center">ชุดที่ 3</span>
                            <input type="text" name="team_3[]" placeholder="ระบุผู้รับผิดชอบ" class="flex-1 w-full text-sm bg-white border border-slate-200 rounded-md p-2 focus:ring-2 focus:ring-emerald-100 focus:border-emerald-400 outline-none transition-all shadow-sm">
                        </div>
                    </div>
                </div>

                <div class="bg-orange-50/50 p-5 rounded-xl border border-orange-100">
                    <div class="flex items-center gap-2 mb-4 text-orange-700">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        <h4 class="font-semibold">สนับสนุนเพิ่มเติม</h4>
                    </div>
                    <div class="space-y-4">
                        <div>
                            <span class="text-xs font-medium text-slate-500 block mb-1">ฝ่ายพัสดุ</span>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" name="supply_staff[]" placeholder="นาย..." class="w-full text-sm bg-white border border-slate-200 rounded-md p-2 focus:ring-2 focus:ring-orange-100 focus:border-orange-400 outline-none transition-all shadow-sm">
                                <input type="text" name="supply_staff[]" placeholder="นาย..." class="w-full text-sm bg-white border border-slate-200 rounded-md p-2 focus:ring-2 focus:ring-orange-100 focus:border-orange-400 outline-none transition-all shadow-sm">
                            </div>
                        </div>
                        <div>
                            <span class="text-xs font-medium text-slate-500 block mb-1">ฝ่ายอาคาร</span>
                            <div class="grid grid-cols-2 gap-2">
                                <input type="text" name="building_staff[]" placeholder="นาย..." class="w-full text-sm bg-white border border-slate-200 rounded-md p-2 focus:ring-2 focus:ring-orange-100 focus:border-orange-400 outline-none transition-all shadow-sm">
                                <input type="text" name="building_staff[]" placeholder="นาย..." class="w-full text-sm bg-white border border-slate-200 rounded-md p-2 focus:ring-2 focus:ring-orange-100 focus:border-orange-400 outline-none transition-all shadow-sm">
                            </div>
                            <div>
                       <span class="text-xs font-medium text-slate-500 block mb-1">อื่นๆ</span>
    <div class="grid grid-cols-2 gap-2">
        <input type="text" name="other_staff[]" placeholder="นาย..." 
            class="w-full text-sm bg-white border border-slate-200 rounded-md p-2 focus:ring-2 focus:ring-orange-100 focus:border-orange-400 outline-none transition-all shadow-sm">
        
        <input type="text" name="other_staff[]" placeholder="นาย..." 
            class="w-full text-sm bg-white border border-slate-200 rounded-md p-2 focus:ring-2 focus:ring-orange-100 focus:border-orange-400 outline-none transition-all shadow-sm">
    </div>
    </div>
</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

     <div class="bg-white rounded-2xl shadow-sm border border-slate-200 overflow-hidden mb-8">
    
    <div class="bg-slate-50 p-5 border-b border-slate-200 flex justify-between items-center">
        <!-- <h2 class="text-slate-800 text-lg font-semibold flex items-center gap-2">
            <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
            </svg>
            การจัดบุคลากรและยานพาหนะ
        </h2> -->
    </div>

    <div class="p-6 md:p-8 space-y-10">
        
        <div>
            <div class="flex justify-between items-end mb-4 border-b pb-2">
                <h3 class="text-sm font-semibold text-slate-500 uppercase tracking-wider">ข้อมูลยานพาหนะ</h3>
                <button type="button" class="text-xs font-medium text-blue-600 hover:text-blue-800 transition-colors flex items-center gap-1 bg-blue-50 px-3 py-1.5 rounded-md hover:bg-blue-100">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                    เพิ่มรถ
                </button>
            </div>
            
            <div class="overflow-x-auto rounded-xl border border-slate-200 shadow-sm">
                <table class="w-full text-sm text-left">
                    <thead>
                        <tr class="bg-slate-800 text-white uppercase text-xs tracking-wider">
                            <th class="p-4 font-medium rounded-tl-xl w-48">ประเภทรถ</th>
                            <th class="p-4 font-medium w-56">ทะเบียน</th>
                            <th class="p-4 font-medium">คนขับ</th>
                            <th class="p-4 font-medium">ผู้ช่วย 1</th>
                            <th class="p-4 font-medium rounded-tr-xl">ผู้ช่วย 2</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 bg-white">
                        
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td rowspan="3" class="p-4 align-top border-r border-slate-100 bg-white">
                                <div class="flex items-center gap-2 font-medium text-slate-700 mt-2">
                                    <span class="w-2 h-2 rounded-full bg-blue-400"></span>
                                    รถกระบะ 4 ประตู
                                </div>
                            </td>
                            <td class="p-2 border-b border-slate-50">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-400 w-3 text-right">1.</span>
                                    <input type="text" placeholder="ระบุทะเบียน" class="w-full p-2 border border-transparent rounded-md focus:border-blue-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white">
                                </div>
                            </td>
                            <td class="p-2 border-b border-slate-50"><input type="text" placeholder="ชื่อคนขับ" class="w-full p-2 border border-transparent rounded-md focus:border-blue-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2 border-b border-slate-50"><input type="text" placeholder="ผู้ช่วย 1" class="w-full p-2 border border-transparent rounded-md focus:border-blue-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2 border-b border-slate-50"><input type="text" placeholder="ผู้ช่วย 2" class="w-full p-2 border border-transparent rounded-md focus:border-blue-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                        </tr>
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="p-2 border-b border-slate-50">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-400 w-3 text-right">2.</span>
                                    <input type="text" placeholder="ระบุทะเบียน" class="w-full p-2 border border-transparent rounded-md focus:border-blue-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white">
                                </div>
                            </td>
                            <td class="p-2 border-b border-slate-50"><input type="text" placeholder="ชื่อคนขับ" class="w-full p-2 border border-transparent rounded-md focus:border-blue-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2 border-b border-slate-50"><input type="text" placeholder="ผู้ช่วย 1" class="w-full p-2 border border-transparent rounded-md focus:border-blue-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2 border-b border-slate-50"><input type="text" placeholder="ผู้ช่วย 2" class="w-full p-2 border border-transparent rounded-md focus:border-blue-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                        </tr>
                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="p-2 border-b-2 border-b-slate-100">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-400 w-3 text-right">3.</span>
                                    <input type="text" placeholder="ระบุทะเบียน" class="w-full p-2 border border-transparent rounded-md focus:border-blue-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white">
                                </div>
                            </td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ชื่อคนขับ" class="w-full p-2 border border-transparent rounded-md focus:border-blue-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ผู้ช่วย 1" class="w-full p-2 border border-transparent rounded-md focus:border-blue-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ผู้ช่วย 2" class="w-full p-2 border border-transparent rounded-md focus:border-blue-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                        </tr>

                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="p-4 align-top border-r border-slate-100 bg-white border-b-2 border-b-slate-100">
                                <div class="flex items-center gap-2 font-medium text-slate-700 mt-2">
                                    <span class="w-2 h-2 rounded-full bg-amber-500"></span>
                                    รถบรรทุก 6 ตัน
                                </div>
                            </td>
                            <td class="p-2 border-b-2 border-b-slate-100">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-400 w-3 text-right">1.</span>
                                    <input type="text" placeholder="ระบุทะเบียน" class="w-full p-2 border border-transparent rounded-md focus:border-amber-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white">
                                </div>
                            </td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ชื่อคนขับ" class="w-full p-2 border border-transparent rounded-md focus:border-amber-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ผู้ช่วย 1" class="w-full p-2 border border-transparent rounded-md focus:border-amber-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ผู้ช่วย 2" class="w-full p-2 border border-transparent rounded-md focus:border-amber-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                        </tr>

                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="p-4 align-top border-r border-slate-100 bg-white border-b-2 border-b-slate-100">
                                <div class="flex items-center gap-2 font-medium text-slate-700 mt-2">
                                    <span class="w-2 h-2 rounded-full bg-amber-300"></span>
                                    รถบรรทุก 4 ตัน
                                </div>
                            </td>
                            <td class="p-2 border-b-2 border-b-slate-100">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-400 w-3 text-right">1.</span>
                                    <input type="text" placeholder="ระบุทะเบียน" class="w-full p-2 border border-transparent rounded-md focus:border-amber-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white">
                                </div>
                            </td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ชื่อคนขับ" class="w-full p-2 border border-transparent rounded-md focus:border-amber-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ผู้ช่วย 1" class="w-full p-2 border border-transparent rounded-md focus:border-amber-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ผู้ช่วย 2" class="w-full p-2 border border-transparent rounded-md focus:border-amber-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                        </tr>

                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="p-4 align-top border-r border-slate-100 bg-white border-b-2 border-b-slate-100">
                                <div class="flex items-center gap-2 font-medium text-slate-700 mt-2">
                                    <span class="w-2 h-2 rounded-full bg-indigo-500"></span>
                                    รถตู้ทึบ 6 ตัน
                                </div>
                            </td>
                            <td class="p-2 border-b-2 border-b-slate-100">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-400 w-3 text-right">1.</span>
                                    <input type="text" placeholder="ระบุทะเบียน" class="w-full p-2 border border-transparent rounded-md focus:border-indigo-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white">
                                </div>
                            </td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ชื่อคนขับ" class="w-full p-2 border border-transparent rounded-md focus:border-indigo-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ผู้ช่วย 1" class="w-full p-2 border border-transparent rounded-md focus:border-indigo-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ผู้ช่วย 2" class="w-full p-2 border border-transparent rounded-md focus:border-indigo-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                        </tr>

                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="p-4 align-top border-r border-slate-100 bg-white border-b-2 border-b-slate-100">
                                <div class="flex items-center gap-2 font-medium text-slate-700 mt-2">
                                    <span class="w-2 h-2 rounded-full bg-indigo-300"></span>
                                    รถตู้ทึบ 4 ตัน
                                </div>
                            </td>
                            <td class="p-2 border-b-2 border-b-slate-100">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-400 w-3 text-right">1.</span>
                                    <input type="text" placeholder="ระบุทะเบียน" class="w-full p-2 border border-transparent rounded-md focus:border-indigo-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white">
                                </div>
                            </td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ชื่อคนขับ" class="w-full p-2 border border-transparent rounded-md focus:border-indigo-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ผู้ช่วย 1" class="w-full p-2 border border-transparent rounded-md focus:border-indigo-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2 border-b-2 border-b-slate-100"><input type="text" placeholder="ผู้ช่วย 2" class="w-full p-2 border border-transparent rounded-md focus:border-indigo-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                        </tr>

                        <tr class="hover:bg-slate-50 transition-colors group">
                            <td class="p-4 align-top border-r border-slate-100 bg-white">
                                <div class="flex items-center gap-2 font-medium text-slate-700 mt-2">
                                    <span class="w-2 h-2 rounded-full bg-rose-400"></span>
                                    รถห้องสรง
                                </div>
                            </td>
                            <td class="p-2">
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-bold text-slate-400 w-3 text-right">1.</span>
                                    <input type="text" placeholder="ระบุทะเบียน" class="w-full p-2 border border-transparent rounded-md focus:border-rose-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white">
                                </div>
                            </td>
                            <td class="p-2"><input type="text" placeholder="ชื่อคนขับ" class="w-full p-2 border border-transparent rounded-md focus:border-rose-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2"><input type="text" placeholder="ผู้ช่วย 1" class="w-full p-2 border border-transparent rounded-md focus:border-rose-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                            <td class="p-2"><input type="text" placeholder="ผู้ช่วย 2" class="w-full p-2 border border-transparent rounded-md focus:border-rose-300 focus:bg-white bg-slate-50/50 outline-none transition-all group-hover:bg-white"></td>
                        </tr>

                    </tbody>
                </table>
            </div>
        </div>
        
    </div>
</div>
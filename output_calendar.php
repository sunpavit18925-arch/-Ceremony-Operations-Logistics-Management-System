<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ระบบคิวงานพับพลาพิธี (Queue Display)</title>
    
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600&display=swap" rel="stylesheet">
    
    <style>
        body { font-family: 'Kanit', sans-serif; background-color: #f8fafc; }
        
        /* Custom Event Styles */
        .fc-event { cursor: pointer; border: none !important; padding: 2px 4px; border-radius: 4px !important; }
        .event-royal { background-color: #8b5cf6 !important; border-left: 4px solid #4c1d95 !important; } /* งานรับเสด็จฯ - สีม่วง */
        .event-request { background-color: #f97316 !important; border-left: 4px solid #9a3412 !important; } /* ขอความอนุเคราะห์ - สีส้ม */
        
        /* ปรับแต่ง Pin (จุดปักหมุด) */
        .pin-meeting { color: #ef4444; font-weight: bold; }
        .pin-work { color: #10b981; font-weight: bold; }

        #calendar { background: white; padding: 20px; border-radius: 16px; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
        
        /* ปรับความสูงของแถวปฏิทิน */
        .fc .fc-daygrid-day-frame { min-height: 100px; }
    </style>
</head>
<body class="p-4 md:p-8">

    <div class="max-w-7xl mx-auto">
        <div class="flex flex-col md:flex-row justify-between items-center mb-6 gap-4">
            <div>
                <h1 class="text-2xl font-bold text-slate-800">แสดงคิวงาน (Queue Output)</h1>
                <p class="text-slate-500 text-sm">ตรวจสอบตารางงานรับเสด็จฯ และงานสนับสนุน</p>
            </div>
            
            <div class="flex gap-2">
                <select id="filterMember" class="text-sm border rounded-lg px-3 py-2 outline-none focus:ring-2 focus:ring-blue-400">
                    <option value="">-- กรองตามชื่อคน/ยานพาหนะ --</option>
                    <option value="1">นายสมชาย (คนขับ)</option>
                    <option value="2">รถกระบะ กข-1234</option>
                </select>
                <button onclick="changeView('dayGridMonth')" class="px-4 py-2 bg-white border rounded-lg text-sm font-medium hover:bg-slate-50">รายเดือน</button>
                <button onclick="changeView('multiMonthYear')" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700 shadow-sm">ดู 3 เดือน</button>
            </div>
        </div>

        <div class="flex flex-wrap gap-4 mb-4 text-xs font-medium">
            <div class="flex items-center gap-1"><span class="w-3 h-3 bg-purple-500 rounded"></span> งานรับเสด็จฯ</div>
            <div class="flex items-center gap-1"><span class="w-3 h-3 bg-orange-500 rounded"></span> ขอความอนุเคราะห์</div>
            <div class="flex items-center gap-1 text-red-500">📍 วันประชุมส่วนล่วงหน้า</div>
            <div class="flex items-center gap-1 text-emerald-500">🚩 วันงาน</div>
        </div>

        <div id="calendar"></div>
    </div>

    <div id="detailModal" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-2xl max-w-lg w-full overflow-hidden shadow-2xl">
            <div id="modalHeader" class="p-4 text-white font-bold flex justify-between">
                <span>รายละเอียดงาน</span>
                <button onclick="closeModal()">✕</button>
            </div>
            <div class="p-6 space-y-4">
                <div id="modalContent">
                    </div>
                <button onclick="closeModal()" class="w-full py-2 bg-slate-100 rounded-lg font-medium text-slate-600 hover:bg-slate-200">ปิดหน้าต่าง</button>
            </div>
        </div>
    </div>

    <script>
        let calendar;

        document.addEventListener('DOMContentLoaded', function() {
            const calendarEl = document.getElementById('calendar');
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                locale: 'th',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: '' // เราสร้างปุ่ม Custom ข้างนอกแล้ว
                },
                // แสดง 3 เดือน (Multi-month view)
                views: {
                    multiMonthYear: {
                        type: 'multiMonth',
                        duration: { months: 3 }
                    }
                },
                events: [
                    // ตัวอย่างข้อมูลงาน 1
                    {
                        id: '1',
                        title: 'งานรับเสด็จฯ จ.เชียงราย',
                        start: '2026-04-24', // วันเริ่มเข้าทำงาน
                        end: '2026-04-30',   // วันสิ้นสุด (FullCalendar end date is exclusive)
                        className: 'event-royal',
                        extendedProps: {
                            type: 'งานรับเสด็จฯ',
                            meetingDate: '2026-04-20',
                            actualWorkDate: '2026-04-24',
                            location: 'ม.แม่ฟ้าหลวง',
                            staff: 'นายสมชาย, นายปิติ'
                        }
                    },
                    // หมุดวันประชุม (ปักแยกเป็น Event สั้นๆ)
                    {
                        title: '📍 ประชุมล่วงหน้า',
                        start: '2026-04-20',
                        display: 'list-item',
                        textColor: '#ef4444'
                    },
                    // หมุดวันงานจริง
                    {
                        title: '🚩 วันงานจริง',
                        start: '2026-04-24',
                        display: 'list-item',
                        textColor: '#10b981'
                    },
                    // ตัวอย่างงานขอความอนุเคราะห์
                    {
                        id: '2',
                        title: 'สนับสนุนพิธีวางศิลาฤกษ์',
                        start: '2026-04-10',
                        end: '2026-04-13',
                        className: 'event-request',
                        extendedProps: {
                            type: 'ขอความอนุเคราะห์',
                            location: 'กทม.',
                            staff: 'ชุดปฏิบัติงานที่ 2'
                        }
                    }
                ],
                eventClick: function(info) {
                    if (info.event.extendedProps.type) {
                        showDetail(info.event);
                    }
                }
            });
            
            calendar.render();
        });

        function changeView(viewName) {
            calendar.changeView(viewName);
        }

        function showDetail(event) {
            const props = event.extendedProps;
            const modal = document.getElementById('detailModal');
            const header = document.getElementById('modalHeader');
            const content = document.getElementById('modalContent');
            
            header.className = `p-4 text-white font-bold flex justify-between ${event.classNames.includes('event-royal') ? 'bg-purple-600' : 'bg-orange-600'}`;
            
            content.innerHTML = `
                <h3 class="text-xl font-bold text-slate-800 mb-2">${event.title}</h3>
                <div class="space-y-2 text-sm">
                    <p><strong>ประเภท:</strong> ${props.type}</p>
                    <p><strong>สถานที่:</strong> ${props.location}</p>
                    <p><strong>ระยะเวลาทำงาน:</strong> ${event.start.toLocaleDateString('th-TH')} - ${event.end ? event.end.toLocaleDateString('th-TH') : '-'}</p>
                    <hr>
                    <p><strong>บุคลากร:</strong> ${props.staff || 'ไม่ได้ระบุ'}</p>
                </div>
            `;
            
            modal.classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('detailModal').classList.add('hidden');
        }
    </script>
</body>
</html>
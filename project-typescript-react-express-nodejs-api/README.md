# ระบบบริหารจัดการพิธีการและโลจิสติกส์ — เวอร์ชัน TypeScript

[กลับหน้ารวม](../README.md) · [เวอร์ชัน PHP](../php/README.md)

Full Stack Demo ใช้ React ผ่าน Next.js เป็นหน้าเว็บ และ Express REST API บน Node.js สำหรับลงรับหนังสือ มอบหมายภารกิจ จัดรถและคนขับ ยืมคืนอุปกรณ์ บันทึกรายงาน และดูประวัติการทำงาน

เวอร์ชันนี้ใช้ข้อมูลจำลองในหน่วยความจำของ API ไม่ต้องติดตั้ง MySQL ข้อมูลที่แก้ไขจะกลับสู่ค่าเริ่มต้นเมื่อรีสตาร์ต API หรือกด Reset Demo และไม่เชื่อมกับฐานข้อมูลของเวอร์ชัน PHP

## เทคโนโลยีและโครงสร้าง

| ส่วน | เทคโนโลยี |
| --- | --- |
| Frontend | Next.js 16, React 19, TypeScript, Bootstrap 5 และ Bootstrap Icons |
| Backend | Node.js, Express, TypeScript, CORS และ Morgan |
| ข้อมูล | In-memory store พร้อมข้อมูลจำลองและคำสั่ง reset |
| ทดสอบ | Node.js Test Runner และ TypeScript compiler |

ใช้ Node.js 22 ขึ้นไปพร้อม npm โดย Next.js ที่ติดตั้งในโปรเจกต์กำหนดขั้นต่ำ Node.js 20.9

```text
project-typescript-react-express-nodejs-api/
├── README.md
├── package.json               # คำสั่งติดตั้ง รัน ทดสอบ และ build
├── start-demo.js               # เริ่ม frontend และ backend พร้อมกัน
├── backend/
│   ├── src/
│   │   ├── controllers/
│   │   ├── routes/
│   │   ├── middlewares/
│   │   ├── store/
│   │   ├── types/
│   │   ├── app.ts
│   │   └── server.ts
│   └── tests/
├── frontend/
│   ├── src/app/               # หน้าเว็บผ่าน Next.js App Router
│   ├── src/components/
│   ├── src/context/
│   ├── src/services/
│   ├── public/
│   └── tests/
└── docs/
    ├── ARCHITECTURE.md
    ├── ROLE_PERMISSIONS.md
    └── WORKFLOW.md
```

## ติดตั้งและรัน

เปิด Terminal จากโฟลเดอร์ repository:

```bash
cd project-typescript-react-express-nodejs-api
npm run install:all
npm run dev
```

- หน้าเว็บ: [http://localhost:3000](http://localhost:3000)
- API / Health check: [http://localhost:4000](http://localhost:4000)
- API ข้อมูลเดโม: [http://localhost:4000/api/demo](http://localhost:4000/api/demo)

หากรันบน PowerShell แล้วไม่อนุญาต `npm.ps1` ให้ใช้ `npm.cmd` แทน `npm` ในคำสั่ง

แยกเปิดสอง Terminal ได้ด้วย `npm run dev:backend` และ `npm run dev:frontend` จากโฟลเดอร์เวอร์ชันนี้

## การตั้งค่า

ค่าเริ่มต้นใช้ Frontend พอร์ต `3000` และ Backend พอร์ต `4000` จึงเริ่มเดโมได้โดยไม่สร้างไฟล์ environment

หากเปลี่ยนที่อยู่ API ให้คัดลอก `frontend/.env.example` เป็น `frontend/.env.local` แล้วแก้:

```ini
NEXT_PUBLIC_API_URL=http://localhost:4000/api/demo
```

รีสตาร์ต Frontend หลังแก้ค่า ตัวแปร `PORT` ของ Backend อ่านจาก process environment เช่นใน PowerShell:

```powershell
$env:PORT = "4001"
npm run dev:backend
```

ค่าใน `backend/.env.example` ใช้เป็นตัวอย่าง โดย entry point ปัจจุบันไม่ได้โหลดไฟล์ `.env` อัตโนมัติ หากเปลี่ยน origin ของ Frontend ให้ปรับรายการ CORS ใน `backend/src/app.ts` ให้ตรงกันด้วย

## วิธีทดลองระบบ

เลือกบทบาทจาก Role Switcher แล้วทดลองลงรับหนังสือ มอบหมายผู้ประสานงาน รับทราบภารกิจ บันทึกประชุม จัดสรรรถและคนขับ และยืมคืนอุปกรณ์ ใช้ HR Guided Demo เพื่อดูขั้นตอนแนะนำ หรือ Reset Demo เพื่อเริ่มข้อมูลใหม่

รองรับ 8 บทบาท: `super_admin`, `admin`, `head_operation`, `coordinator_operation`, `staff_operation`, `head_vehicle`, `staff_vehicle` และ `pending` รายละเอียดอยู่ใน [ตารางสิทธิ์](docs/ROLE_PERMISSIONS.md)

API จำลองสิทธิ์ด้วย header `x-demo-role` หรือ query `demo_role` และใช้ `super_admin` เป็นค่าเริ่มต้นเมื่อไม่ส่งบทบาท กลไกนี้ใช้สาธิตการทำงาน ก่อนนำไปใช้กับข้อมูลจริงต้องเพิ่มระบบยืนยันตัวตนและฐานข้อมูลถาวร

## การค้นหาและแบ่งหน้า

- `GET /api/demo/documents?page=1&limit=25&search=...` คืน `data`, `count` และ `pagination` จำกัด `limit` สูงสุด 100 รายการ การเรียกเอกสารโดยไม่ส่ง `page` หรือ `limit` ยังรองรับการคืนรายการทั้งหมด
- `GET /api/demo/logs?page=1&limit=25&search=...&actions=ASSIGN_COORD,REVOKE_COORD` กรองก่อนแบ่งหน้า ค่าเริ่มต้น 50 รายการและสูงสุด 100 รายการต่อหน้า
- หน้า `/admin2`, `/documents` และ `/view_system_logs` รอให้หยุดพิมพ์ 250 ms ก่อนค้นหา และยกเลิกคำขอเดิมเมื่อเปลี่ยนตัวกรอง
- Store เก็บ logs ล่าสุดไม่เกิน 10,000 รายการ และคืนค่าข้อมูลเริ่มต้นเมื่อ reset

## ทดสอบและ build

จากโฟลเดอร์ `project-typescript-react-express-nodejs-api`:

```bash
npm run test:backend
npm --prefix frontend test
npm run build
```

Backend tests คอมไพล์ TypeScript ก่อนตรวจ API, สิทธิ์, pagination, การค้นหา, reset, ID หลังลบข้อมูล และขอบเขตการเก็บ logs ส่วน Frontend tests ตรวจการตั้งค่าธีม

หลัง build เรียก `npm --prefix backend start` และ `npm --prefix frontend start` ในสอง Terminal

## เอกสารเพิ่มเติม

- [สถาปัตยกรรมระบบ](docs/ARCHITECTURE.md)
- [บทบาทและสิทธิ์](docs/ROLE_PERMISSIONS.md)
- [ขั้นตอนการทำงาน](docs/WORKFLOW.md)
- [คู่มือ Frontend](frontend/README.md)
- [การดาวน์โหลดบน Windows และข้อจำกัดความยาวพาธ](../README.md#ดาวน์โหลดบน-windows)

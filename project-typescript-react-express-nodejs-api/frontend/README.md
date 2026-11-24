# Frontend — ระบบบริหารจัดการพิธีการและโลจิสติกส์

React 19 ผ่าน Next.js 16 App Router และ TypeScript ใช้ Bootstrap 5 สำหรับหน้าเว็บ และเรียก Express API ของเวอร์ชัน TypeScript

ดู [คู่มือติดตั้งและรันทั้งระบบ](../README.md) และ [หน้ารวมสองเวอร์ชัน](../../README.md)

## รันเฉพาะ Frontend

จากโฟลเดอร์นี้:

```bash
npm install
npm run dev
```

เปิด [http://localhost:3000](http://localhost:3000) และรัน Backend แยกตาม [คู่มือหลัก](../README.md#ติดตั้งและรัน)

ค่า API เริ่มต้นคือ `http://localhost:4000/api/demo` หากต้องการเปลี่ยน ให้คัดลอก `.env.example` เป็น `.env.local` แล้วกำหนด `NEXT_PUBLIC_API_URL`

## โครงสร้างและคำสั่ง

- `src/app/`: หน้าเว็บและ layout ผ่าน Next.js App Router
- `src/components/`: ส่วนประกอบหน้าเว็บ
- `src/context/DemoContext.tsx`: สถานะเดโมและบทบาทผู้ใช้
- `src/services/api.ts`: เรียก Express API
- `npm test`: ทดสอบการตั้งค่าธีม
- `npm run build`: สร้าง production build
- `npm start`: รัน build ที่สร้างไว้

ข้อมูลเดโมเก็บที่ Backend และจะคืนค่าเริ่มต้นเมื่อรีสตาร์ต API หรือกด Reset Demo

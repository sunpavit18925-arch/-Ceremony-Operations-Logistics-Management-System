# ระบบบริหารจัดการพิธีการและโลจิสติกส์

Ceremony Operations & Logistics Management System สำหรับจัดการหนังสือราชการ ภารกิจงานพิธี เจ้าหน้าที่ ยานพาหนะ คนขับ และการยืมคืนอุปกรณ์

![PHP](https://img.shields.io/badge/PHP-777BB4?logo=php&logoColor=white)
![TypeScript](https://img.shields.io/badge/TypeScript-3178C6?logo=typescript&logoColor=white)
![React](https://img.shields.io/badge/React-20232A?logo=react)
![Express](https://img.shields.io/badge/Express-Node.js-339933)

## เลือกเวอร์ชัน

| เวอร์ชัน | เทคโนโลยีและการทำงาน | ข้อมูลตัวอย่าง | คู่มือติดตั้ง |
| --- | --- | --- | --- |
| [PHP](php/) | PHP 8 + PDO สร้างหน้าเว็บบน Apache | MySQL / MariaDB เก็บข้อมูลถาวร พร้อม schema และข้อมูลจำลอง | [README เวอร์ชัน PHP](php/README.md) |
| [TypeScript](project-typescript-react-express-nodejs-api/) | React ผ่าน Next.js + Express REST API บน Node.js | เก็บข้อมูลเดโมในหน่วยความจำ รีเซ็ตเมื่อเปิด API ใหม่ | [README เวอร์ชัน TypeScript](project-typescript-react-express-nodejs-api/README.md) |

ทั้งสองเวอร์ชันรันแยกกันได้และมีชุดข้อมูลของตนเอง การแก้ข้อมูลในเวอร์ชันหนึ่งจะไม่เปลี่ยนข้อมูลอีกเวอร์ชัน

## โครงสร้าง repository

```text
.
├── php/
│   ├── README.md
│   ├── .env.example
│   ├── index.php
│   ├── includes/
│   ├── controllers/
│   ├── database/
│   ├── tests/
│   └── uploads/
├── project-typescript-react-express-nodejs-api/
│   ├── README.md
│   ├── package.json
│   ├── start-demo.js
│   ├── backend/
│   ├── frontend/
│   └── docs/
└── README.md
```

## เริ่มใช้งาน

**PHP:** เปิด Apache และ MySQL ใน XAMPP นำเข้า `php/database/schema.sql` และ `php/database/seeds.sql` ตั้งค่า `php/.env` แล้วเปิด `http://localhost/pabpla/php/` หากเก็บ repository ไว้ที่ `C:\xampp\htdocs\pabpla` ดู [ขั้นตอนติดตั้ง PHP](php/README.md#การติดตั้งบน-xampp)

**TypeScript:** จากโฟลเดอร์ repository รัน:

```bash
cd project-typescript-react-express-nodejs-api
npm run install:all
npm run dev
```

เปิด [Frontend](http://localhost:3000) และ [API](http://localhost:4000) ดู [คู่มือ TypeScript](project-typescript-react-express-nodejs-api/README.md) สำหรับการตั้งค่าและทดสอบ

## ดาวน์โหลดบน Windows

ใช้พาธสั้น เช่น `C:\xampp\htdocs\pabpla` เพื่อให้ชื่อโฟลเดอร์และไฟล์ที่ซ้อนกันไม่ยาวเกินข้อจำกัด Windows:

```powershell
git -c core.longpaths=true clone https://github.com/sunpavit18925-arch/-Ceremony-Operations-Logistics-Management-System.git C:\xampp\htdocs\pabpla
```

หากดาวน์โหลด ZIP ให้แตกไปยังพาธสั้นด้วยเครื่องมือที่รองรับ long paths เช่น 7-Zip และตั้งชื่อโฟลเดอร์ repository เป็น `pabpla` ก่อนติดตั้ง หากพบ `0x80010135: Path too long` ให้แตกไฟล์ใหม่โดยไม่กดข้ามไฟล์

ชุดข้อมูลเดโมของแต่ละเวอร์ชันใช้สำหรับทดลองระบบ ส่วนค่าเชื่อมต่อจริงและไฟล์อัปโหลดจะถูกยกเว้นจาก Git

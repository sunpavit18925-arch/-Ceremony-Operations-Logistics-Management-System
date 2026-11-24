# ระบบบริหารจัดการพิธีการและโลจิสติกส์ — เวอร์ชัน PHP

[กลับหน้ารวม](../README.md) · [เวอร์ชัน TypeScript](../project-typescript-react-express-nodejs-api/README.md)

เว็บแอป PHP 8 สำหรับลงรับหนังสือราชการ มอบหมายภารกิจงานพิธี จัดทีมปฏิบัติการ จัดรถและคนขับ ยืมคืนอุปกรณ์ บันทึกรายงานการประชุม และตรวจประวัติการทำงาน ข้อมูลเก็บใน MySQL / MariaDB ผ่าน PDO

## เทคโนโลยีและโครงสร้าง

- PHP 8.0 ขึ้นไป พร้อม `pdo_mysql`, `fileinfo`, `mbstring`, `curl` และ `openssl`
- MySQL 5.7+ หรือ MariaDB 10.4+
- Apache / XAMPP โดยอนุญาต `.htaccess` ผ่าน `AllowOverride All`
- Session และสิทธิ์ตามบทบาท, CSRF tokens, PDO prepared statements และตัวตรวจไฟล์ PDF

```text
php/
├── index.php                  # หน้าเริ่มต้น
├── login_demo.php             # เข้าสู่ระบบด้วยบัญชีจำลอง
├── config.php                 # อ่านการตั้งค่า SSO
├── controllers/               # ประมวลผลคำขอ
├── includes/                  # ฐานข้อมูล สิทธิ์ CSRF และบริการส่วนกลาง
├── css/                       # รูปแบบหน้าเว็บ
├── images/                    # รูปประกอบ
├── database/
│   ├── schema.sql             # โครงสร้างฐานข้อมูล
│   ├── seeds.sql              # ข้อมูลจำลอง
│   └── migrate_performance.php
├── tests/                     # ทดสอบการทำงานและ query
├── thai-province-data-master/ # ข้อมูลจังหวัดและพื้นที่
├── uploads/                   # ไฟล์แนบ พร้อมกฎป้องกันการเข้าถึงโดยตรง
└── .env.example
```

## การติดตั้งบน XAMPP

1. เก็บ repository ไว้ที่ `C:\xampp\htdocs\pabpla` แล้วเปิด Apache และ MySQL จาก XAMPP Control Panel โค้ด PHP จะอยู่ที่ `C:\xampp\htdocs\pabpla\php`
2. ใน phpMyAdmin สร้างฐานข้อมูลสำหรับเดโมและทดสอบ:

   ```sql
   CREATE DATABASE pabpla_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE DATABASE pabpla_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

3. นำเข้า [database/schema.sql](database/schema.sql) แล้วตามด้วย [database/seeds.sql](database/seeds.sql) ลงใน `pabpla_demo` ทำซ้ำสำหรับ `pabpla_test` หากต้องการรันชุดทดสอบ
4. เปิด PowerShell ในโฟลเดอร์ repository แล้วคัดลอกไฟล์ตั้งค่า:

   ```powershell
   cd php
   Copy-Item .env.example .env
   ```

   บน macOS / Linux ใช้ `cp .env.example .env` จากภายในโฟลเดอร์ `php`

5. แก้ `php/.env` ให้ตรงกับฐานข้อมูลในเครื่อง:

   ```ini
   APP_ENV=development
   APP_DEBUG=true
   DEMO_MODE=true

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=pabpla_demo
   DB_USERNAME=root
   DB_PASSWORD=
   DB_CHARSET=utf8mb4
   ```

6. เปิด `http://localhost/pabpla/php/` หรือ `http://localhost/pabpla/php/login_demo.php` หากใช้ชื่อโฟลเดอร์ repository อื่น ให้เปลี่ยนส่วน `pabpla` ใน URL ตามนั้น ใน workspace นี้ URL คือ `http://localhost/pabpla_test2/php/`

โหมดเดโมใช้ได้โดยไม่เชื่อมต่อ SSO แต่ยังต้องมีฐานข้อมูล MySQL / MariaDB และนำเข้าข้อมูลจำลองก่อน

## บัญชีเดโมและสิทธิ์

รหัสผ่านของบัญชีจำลองทุกบัญชีคือ `password123` หรือเลือกบัญชีจากหน้า Demo Login ได้เมื่อเปิด `DEMO_MODE=true`

| Username | บทบาท | งานหลัก |
| --- | --- | --- |
| `superadmin_demo` | `super_admin` | ดูแลระบบทุกส่วนและสิทธิ์ผู้ใช้ |
| `admin_demo` | `admin` | ลงรับหนังสือและจัดการผู้ใช้ทั่วไป |
| `head_op_demo` | `head_operation` | มอบหมายภารกิจและจัดทีมปฏิบัติการ |
| `coord_op_demo` | `coordinator_operation` | รับทราบงานและบันทึกรายงานการประชุม |
| `staff_op_demo` | `staff_operation` | ตรวจและรับทราบงานที่ได้รับมอบหมาย |
| `head_veh_demo` | `head_vehicle` | จัดรถ คนขับ และอุปกรณ์ |
| `staff_veh_demo` | `staff_vehicle` | ดูภารกิจและปฏิบัติงานยานพาหนะ |
| `pending_demo` | `pending` | รอผู้ดูแลกำหนดสิทธิ์ |

ข้อมูลเริ่มต้นอยู่ใน [database/seeds.sql](database/seeds.sql) และแยกจากชุดข้อมูลของเวอร์ชัน TypeScript

## การทดสอบ

รันจากภายในโฟลเดอร์ `php` หลังนำเข้า schema และ seeds ลงฐานข้อมูล `pabpla_test`:

```bash
php tests/run_tests.php
php tests/performance_tests.php
```

`run_tests.php` กำหนดฐานข้อมูลเป็น `pabpla_test` และล้างข้อมูลทดสอบก่อนใส่ seeds ใหม่ ค่า host, port, username และ password อ่านจาก `.env` หรือ environment variables ส่วน `performance_tests.php` ตรวจ query การแบ่งหน้า งานซ้อน และ index พร้อม rollback ข้อมูลที่ใช้ทดสอบ

สำหรับฐานข้อมูลเดิม เพิ่ม index โดยเก็บข้อมูลเดิมด้วยคำสั่ง:

```bash
php database/migrate_performance.php
```

การติดตั้งใหม่จาก `schema.sql` มี index ชุดเดียวกันแล้ว

## การเชื่อมต่อ SSO และใช้งานจริง

ตั้ง `APP_ENV=production`, `APP_DEBUG=false` และ `DEMO_MODE=false` พร้อมใช้ HTTPS การกำหนด Secure Cookie อาศัยข้อมูล HTTPS ของคำขอใน `includes/bootstrap.php`

กำหนด `SSO_BASE_URL`, `SSO_CLIENT_ID`, `SSO_CLIENT_SECRET`, `SSO_REDIRECT_URL` และ `SSO_SCOPE` ให้ตรงกับระบบที่ลงทะเบียนไว้ โดย `SSO_REDIRECT_URL` ต้องตรงกับ callback URL ของระบบหลังย้ายโฟลเดอร์

เก็บ `.env` และไฟล์อัปโหลดจริงไว้เฉพาะเซิร์ฟเวอร์ ตรวจให้ Apache ใช้กฎใน `.htaccess` และให้ผู้ใช้ดาวน์โหลดไฟล์แนบผ่าน `download.php` ซึ่งตรวจ session และสิทธิ์ก่อนส่งไฟล์

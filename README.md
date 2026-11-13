# ระบบบริหารจัดการงานพลับพลาพิธี (Pabpla Ceremony Management System)

> **โครงงานปรับปรุงและพัฒนาเว็บแอปพลิเคชันสำหรับนำเสนอผลงานตำแหน่ง Junior PHP Developer**  
> ปรับปรุงสถาปัตยกรรม เพิ่มความปลอดภัยตามมาตรฐาน OWASP และจัดเตรียมข้อมูลทดสอบจำลอง (Synthetic Demo Data) สำหรับการทดสอบระบบได้อิสระโดยไม่ต้องพึ่งพาเซิร์ฟเวอร์ภายในของหน่วยงาน

---

## 1. ภาพรวมของระบบ (System Overview)

ระบบบริหารจัดการงานพลับพลาพิธี เป็นระบบสารสนเทศเชิงปฏิบัติการสำหรับบริหารจัดการหนังสือราชการ ภารกิจจัดเตรียมพลับพลาพิธี การมอบหมายชุดปฏิบัติการภาคสนาม การจัดสรรยานพาหนะและพนักงานขับรถ การยืม-คืนอุปกรณ์พิธีการ ตลอดจนการสรุปผลและรายงานการประชุม

### จุดเด่นของการปรับปรุงระบบ (Refactoring Highlights)
1. **Security Hardening (OWASP Compliance)**:
   - ป้องกัน **SQL Injection** 100% ด้วย Prepared Statements (PDO)
   - ป้องกัน **Cross-Site Scripting (XSS)** ด้วย Context-Aware Escaping (`htmlspecialchars` / `e()`)
   - ป้องกัน **Cross-Site Request Forgery (CSRF)** ด้วย Cryptographic Token Verification ทุกคำขอประเภท State-Changing (POST)
   - ป้องกัน **Insecure Direct Object References (IDOR)** โดยตรวจสอบสิทธิ์ระดับแถวข้อมูล (Row-Level Authorization)
   - ป้องกัน **Race Conditions** ในการยืม-คืนอุปกรณ์ด้วยฐานข้อมูลธุรกรรม (`SELECT ... FOR UPDATE` และ Atomic Decrement/Increment)
   - ป้องกัน **Arbitrary File Upload** ด้วยการตรวจ MIME Type จริง (`finfo`), Magic Header (`%PDF-`), และการสุ่มชื่อไฟล์เอกสาร
   - ป้องกัน **Privilege Escalation** โดยป้องกัน Admin ทั่วไปจากการเลื่อนขั้นเป็น Super Admin หรือลดสิทธิ์ Super Admin บัญชีสุดท้าย
2. **Environment & Secrets Segregation**:
   - ย้าย Credentials, Database Connection, และ SSO Client Secrets ออกจาก Source Code ไปยังไฟล์ `.env` พร้อมมี Loader และ `.env.example`
   - ปิด `display_errors` ใน Production และบันทึกข้อผิดพลาดลง `error_log` เพื่อไม่เปิดเผยข้อมูลโครงสร้างภายใน
3. **Standalone Demo Mode**:
   - สามารถติดตั้งและทดลองระบบบน Localhost (XAMPP) ได้ทันทีด้วยฐานข้อมูลจำลอง (`database/schema.sql` และ `database/seeds.sql`)
   - มีระบบสลับ Role หรือเข้าสู่ระบบด้วยรหัสผ่าน (`password_verify`) สำหรับการตรวจผลงาน โดยไม่ต้องต่อระบบ SSO ของรัฐ
   - **Production Isolation**: ระบบ Demo Mode จะถูกตัดการทำงานโดยอัตโนมัติเมื่อ `APP_ENV=production` หรือ `DEMO_MODE=false`
4. **Automated Testing Suite**:
   - ชุดทดสอบอัตโนมัติครอบคลุม 10 หมวดหมู่ความปลอดภัยและ Business Logic (`tests/run_tests.php`)

---

## 2. สถาปัตยกรรมและโครงสร้างไดเรกทอรี (Architecture & Directory Structure)

ระบบใช้สถาปัตยกรรมเชิงโมดูลาร์ (Modular Architecture) บนเทคโนโลยี PHP 8 / PDO โดยแยกส่วน Core Logic, Security, และ Database ออกเป็นบริการส่วนกลาง (Shared Services) ภายใต้ไดเรกทอรี `includes/`

```text
pabpla/
├── config.php                  # Legacy compatibility wrapper
├── db.php                      # PDO Connection wrapper สำหรับรองรับโค้ดเดิม
├── download.php                # ตัวควบคุมการดาวน์โหลดไฟล์แนบอย่างปลอดภัย (Auth & Permission Checked)
├── index.php                   # จุดเข้าสู่ระบบหลัก (SSO Landing & Demo Entry)
├── login_demo.php              # ระบบเข้าสู่ระบบสำหรับทดสอบ (Demo Switcher / Password Login)
├── sso_login.php               # OAuth2 / OIDC Authorization Redirect
├── sso_callback.php            # OAuth2 Callback Handler (TLS Check, State Check, Account Linking)
├── logout.php                  # ทำลาย Session และล้างคุกกี้อย่างปลอดภัย
├── welcome.php                 # Routing Hub นำทางผู้ใช้ไปยัง Dashboard ประจำ Role
│
├── controllers/                # ตัวประมวลผลคำขอ (Separated Business Controllers)
│   ├── admin_document_controller.php
│   └── manage_operation_controller.php
│
├── database/                   # DDL สคีมาและข้อมูลทดสอบจำลอง (Synthetic Datasets)
│   ├── schema.sql              # ตารางทั้งหมด พร้อม Foreign Keys, Constraints, และ Indexes
│   └── seeds.sql               # บัญชีทดสอบจำลองทั้ง 8 Role, อุปกรณ์, รถ, และเอกสารตัวอย่าง
│
├── includes/                   # Shared Security & Core Service Layer
│   ├── bootstrap.php           # Session Hardening, Error Handling, Environment Bootstrap
│   ├── env.php                 # Environment Variables Loader (.env Parser)
│   ├── db.php                  # PDO Singleton Connection Provider
│   ├── security.php            # XSS Escaping, Input Sanitizers, Path Traversal Protection
│   ├── csrf.php                # CSRF Token Generator & Validator
│   ├── auth.php                # Role Matrix, Session Timeout, Privilege Guard
│   ├── storage.php             # PDF Validation, Magic Byte Check, Directory Guard
│   ├── sso.php                 # Unified SSO Client (cURL, TLS Verify, Nonce/State Validator)
│   └── equipment.php           # Concurrency-Safe Equipment Borrowing & Returning
│
├── tests/                      # Automated Regression & Security Test Suite
│   └── run_tests.php           # สคริปต์รันการทดสอบ 28 รายการครอบคลุม 10 Scenarios
│
├── uploads/                    # ไดเรกทอรีเก็บไฟล์อัปโหลด (ป้องกัน Direct Access ผ่าน .htaccess)
│   ├── documents/              # ไฟล์แนบหนังสือราชการ (.pdf)
│   └── meetings/               # ไฟล์แนบการประชุม (.pdf)
│
├── .env.example                # ตัวอย่างการตั้งค่า Environment Variables
├── .gitignore                  # กฎการป้องกัน commit ข้อมูลจริงและ credentials
└── README.md                   # เอกสารประกอบโครงงานฉบับสมบูรณ์
```

---

## 3. ผังบทบาทผู้ใช้งานและสิทธิ์การเข้าถึง (Roles & Access Matrix)

ระบบแบ่งบทบาทการทำงานออกเป็น **8 บทบาทหลัก (Roles)** ตามโครงสร้างงานราชการ:

| บทบาท (Role) | คำอธิบายหน้าที่ | สิทธิ์การเข้าถึงหน้าเว็บและฟังก์ชัน |
| :--- | :--- | :--- |
| `super_admin` | ผู้ดูแลระบบสูงสุด | เข้าถึงได้ทุกหน้าในระบบ, จัดการสิทธิ์ผู้ใช้ทั้งหมด, ดูแลเอกสาร ยานพาหนะ และประวัติระบบ |
| `admin` | ผู้ดูแลระบบฝ่ายสารบรรณ | จัดการหนังสือราชการเข้า-ออก (เพิ่ม แก้ไข ลบ มอบหมายงาน), จัดการสิทธิ์ผู้ใช้ทั่วไป (ยกเว้น super_admin) |
| `head_operation` | หัวหน้าฝ่ายปฏิบัติการ | มอบหมายงานแก่ผู้ประสานงาน/เจ้าหน้าที่, จัดชุดปฏิบัติงานส่วนกลาง, บันทึกยืมอุปกรณ์, ดู Log ประวัติ |
| `coordinator_operation` | ผู้ประสานงานฝ่ายปฏิบัติการ | รับมอบหมายงานเฉพาะภารกิจ, ยืนยันรับทราบงาน, บันทึกข้อมูลการประชุมและผังเมือง |
| `staff_operation` | เจ้าหน้าที่ฝ่ายปฏิบัติการ | ดูรายละเอียดงานที่ได้รับมอบหมาย, ยืนยันรับทราบงาน, พิมพ์บันทึกข้อความ |
| `head_vehicle` | หัวหน้าฝ่ายยานพาหนะ | จัดสรรยานพาหนะและพนักงานขับรถให้ตรงตามชุดปฏิบัติงาน, จัดการคุณสมบัติคนขับ, ยืม-คืนอุปกรณ์ |
| `staff_vehicle` | เจ้าหน้าที่ฝ่ายยานพาหนะ | ดูรายการภารกิจที่ต้องจัดรถ, ยืมอุปกรณ์ประจำรถ |
| `pending` | ผู้ใช้ใหม่รออนุมัติสิทธิ์ | เข้าสู่ระบบได้แต่ไม่สามารถเข้าถึงข้อมูลปฏิบัติงานใด ๆ จนกว่า Admin จะกำหนดสิทธิ์ |

### Access Control Matrix

| เส้นทางหน้าเว็บ (Path) | สิทธิ์ที่อนุญาต (Authorized Roles) | หมายเหตุความปลอดภัย |
| :--- | :--- | :--- |
| `admin.php` | `admin`, `super_admin` | ตรวจสอบ CSRF, Whitelist Role, ป้องกันการเลื่อนขั้นตนเองเป็น super_admin |
| `admin2.php` | `admin`, `super_admin` | จัดการหนังสือราชการ เพิ่ม/แก้ไข/ลบ ตรวจสอบ CSRF ทุก Form |
| `home_head_operation.php` | `head_operation`, `super_admin` | แดชบอร์ดสรุปงานฝ่ายปฏิบัติการ |
| `manage_operation2.php` | `head_operation`, `super_admin` | มอบหมายงาน, จัดชุด, บันทึกยืมอุปกรณ์ (แปลง action เป็น POST) |
| `manage_coordinator_operation.php`| `head_operation`, `super_admin` | กำหนดผู้ประสานงานประจำภารกิจ ตรวจสอบ CSRF |
| `manage_team.php` | `head_operation`, `super_admin` | จัดการชุดปฏิบัติการส่วนกลาง ตรวจสอบ CSRF |
| `view_system_logs.php` | `head_operation`, `super_admin` | ประวัติการปฏิบัติงานในระบบ (Audit Trail) |
| `home_head_vehicle.php` | `head_vehicle`, `super_admin` | แดชบอร์ดฝ่ายยานพาหนะ |
| `select_mission.php` | `head_vehicle`, `staff_vehicle`, `super_admin` | เลือกภารกิจจัดรถ และยืม-คืนอุปกรณ์พิธีการ |
| `manage_vehicles.php` | `head_vehicle`, `super_admin` | จัดการทะเบียนและประเภทรถ (แก้บั๊กสิทธิ์ super_admin แล้ว) |
| `manage_drivers.php` | `head_vehicle`, `super_admin` | กำหนดคุณสมบัติคนขับรถ (แก้บั๊กสิทธิ์ super_admin แล้ว) |
| `manage_vehicle.php` | `head_vehicle`, `staff_vehicle`, `super_admin` | จับคู่รถ คนขับ และชุดปฏิบัติงาน ตรวจสอบเวลาซ้อน |
| `staff_view_operation.php` | `staff_operation`, `super_admin` | รายการหนังสือที่ส่งถึงตนเอง (IDOR Guard) |
| `staff_view_operation2.php` | `staff_operation`, `coordinator_operation`, `super_admin` | ดูรายการหนังสือ ยืนยันรับทราบ และบันทึกรายงานการประชุม |
| `download.php` | ผู้ใช้ล็อกอินตามสิทธิ์ที่เกี่ยวข้อง | ป้องกัน Path Traversal, ปิดกั้น Direct Access ผ่าน URL |

---

## 4. ขั้นตอนการติดตั้งบนเครื่อง Local สำหรับผู้ตรวจ (Installation Guide)

### 4.1 ความต้องการขั้นต่ำของระบบ (Prerequisites)
- **PHP**: เวอร์ชัน 8.0 ขึ้นไป (แนะนำ PHP 8.0, 8.1 หรือ 8.2)
- **PHP Extensions**: `pdo_mysql`, `fileinfo`, `mbstring`, `curl`, `openssl`
- **Database**: MySQL 5.7+ หรือ MariaDB 10.4+
- **Web Server**: Apache (XAMPP / WampServer) พร้อมเปิดใช้งาน `mod_rewrite` และ `AllowOverride All`

---

### 4.2 ขั้นตอนการติดตั้งทีละขั้น (Step-by-Step)

#### ขั้นตอนที่ 1: ติดตั้งซอร์สโค้ดใน Web Server Root
คัดลอกโฟลเดอร์โปรเจกต์ `pabpla` ไปไว้ที่ Web Root (เช่น `C:\xampp\htdocs\pabpla`)

#### ขั้นตอนที่ 2: สร้างฐานข้อมูลและนำเข้าข้อมูลจำลอง
เปิด MySQL Command-Line หรือ phpMyAdmin แล้วสร้างฐานข้อมูล:

```sql
CREATE DATABASE pabpla_demo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE pabpla_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

จากนั้นนำเข้าสคีมาและข้อมูลตัวอย่าง:
1. นำเข้า `database/schema.sql` เข้าสู่ฐานข้อมูล `pabpla_demo`
2. นำเข้า `database/seeds.sql` เข้าสู่ฐานข้อมูล `pabpla_demo`
3. ทำซ้ำขั้นตอนเดิมสำหรับ `pabpla_test` (ใช้สำหรับ Automated Testing)

*(หรือรันผ่าน Terminal)*:
```bash
mysql -u root -p pabpla_demo < database/schema.sql
mysql -u root -p pabpla_demo < database/seeds.sql
mysql -u root -p pabpla_test < database/schema.sql
mysql -u root -p pabpla_test < database/seeds.sql
```

#### ขั้นตอนที่ 3: กำหนดค่า Environment Variables (.env)
คัดลอกไฟล์ `.env.example` เป็น `.env`:
```bash
cp .env.example .env
```
ตรวจสอบและแก้ไขค่าใน `.env` ให้ตรงกับสภาพแวดล้อมเครื่องของคุณ:
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

#### ขั้นตอนที่ 4: เปิดใช้งานระบบบนเบราว์เซอร์
เปิดเบราว์เซอร์แล้วเข้าสู่ URL:
```text
http://localhost/pabpla/
```
ระบบจะแสดงหน้าเข้าสู่ระบบ พร้อมแถบ **"โหมดทดสอบระบบสำหรับผู้ตรวจ (Demo Login)"**

---

## 5. บัญชีทดสอบสำหรับ Demo (Demo Accounts)

ทุกบัญชีทดสอบในโหมด Demo ถูกสร้างขึ้นด้วยข้อมูลจำลอง (Synthetic Data) และเข้ารหัสผ่านด้วย `password_hash(PASSWORD_BCRYPT)`

> **รหัสผ่านเริ่มต้นสำหรับทุกบัญชีคือ**: `password123`

| ชื่อผู้ใช้งาน (Username) | รหัสผ่าน | ชื่อ - นามสกุล | บทบาท (Role) | หน้าแรกหลังเข้าสู่ระบบ |
| :--- | :--- | :--- | :--- | :--- |
| `superadmin_demo` | `password123` | สมศักดิ์ มหาดไทย | `super_admin` | `home.php` |
| `admin_demo` | `password123` | วิชัย สารบรรณ | `admin` | `home_admin.php` |
| `head_op_demo` | `password123` | กิตติศักดิ์ พลับพลา | `head_operation` | `home_head_operation.php` |
| `coord_op_demo` | `password123` | นครินทร์ ประสานกิจ | `coordinator_operation` | `staff_view_operation2.php` |
| `staff_op_demo` | `password123` | ประวิทย์ ปฏิบัติการ | `staff_operation` | `home_operation.php` |
| `head_veh_demo` | `password123` | สุรชัย พลขับ | `head_vehicle` | `home_head_vehicle.php` |
| `staff_veh_demo` | `password123` | วุฒิชัย ขับรถยนต์ | `staff_vehicle` | `home_vehicle.php` |
| `pending_demo` | `password123` | ธนาวัฒน์ รอยืนยัน | `pending` | `welcome.php` |

💡 **เคล็ดลับการทดสอบแบบรวดเร็ว**:  
ในหน้า `index.php` หรือ `login_demo.php` สามารถคลิกเลือก Role จากรายการ Dropdown เพื่อสลับบัญชีทดสอบได้ในคลิกเดียวโดยไม่ต้องพิมพ์รหัสผ่าน

---

## 6. การทดสอบอัตโนมัติ (Automated Testing)

ระบบมาพร้อมชุดทดสอบอัตโนมัติที่ครอบคลุม 10 หมวดหมู่ความปลอดภัยและการทำงานทางธุรกิจ 28 รายการทดสอบ

### วิธีรันชุดทดสอบ
เปิด Terminal ในโฟลเดอร์ของโปรเจกต์ แล้วรันคำสั่ง:
```bash
php tests/run_tests.php
```

### สรุปผลการทดสอบที่ครอบคลุม:
1. **Unauthenticated Protection**: ผู้ใช้ที่ไม่มี Session จะถูกปฏิเสธและ Redirect ทันที
2. **Role Matrix Access Control**: ตรวจสอบการอนุญาตและปิดกั้นสิทธิ์ข้าม Role
3. **Privilege Escalation Prevention**: ป้องกัน Admin เลื่อนขั้นตนเอง และป้องกันการปลด Super Admin คนสุดท้าย
4. **CSRF Protection**: ตรวจสอบการปฏิเสธคำขอที่ไม่มี Token หรือ Token ถูกปลอมแปลง
5. **IDOR / Row-Level Protection**: ป้องกันการเข้าดูเอกสารของผู้อื่นโดยไม่ได้รับอนุญาต
6. **Equipment Borrow Bounds**: ป้องกันการยืมจำนวนติดลบ หรือยืมเกินจำนวนคงเหลือในคลัง
7. **Equipment Return Idempotency**: คืนอุปกรณ์ซ้ำซ้อนไม่ทำให้สต็อกเพิ่มเกินสต็อกจริง (`LEAST(total_quantity, ...)`)
8. **Transaction Atomicity**: ข้อมูลถูก Rollback ทันทีเมื่อเกิดข้อผิดพลาดในการบันทึกหลายตาราง
9. **Secure File Upload & Traversal**: ปฏิเสธไฟล์อันตราย/MIME ปลอม และลบล้าง Path Traversal (`../../`)
10. **Demo Mode Isolation**: ยืนยันว่าระบบ Demo ถูกปิดการทำงานอย่างถาวรใน Production

---

## 7. ข้อควรระวังและแนวทางนำไปใช้บน Production จริง

เมื่อต้องการนำระบบขึ้นใช้งานจริง (Production Deployment) ต้องปฏิบัติตามขั้นตอนความปลอดภัยดังนี้:

1. **ปิดโหมดทดสอบและ Debug**:
   กำหนดค่าในไฟล์ `.env` บนเซิร์ฟเวอร์จริง:
   ```ini
   APP_ENV=production
   APP_DEBUG=false
   DEMO_MODE=false
   ```
   *เมื่อ `DEMO_MODE=false` หน้า `login_demo.php` จะคืนค่า `403 Forbidden` และปุ่ม Demo ทั้งหมดจะถูกซ่อนออกจากหน้าจอทันที*

2. **เปิดใช้งาน HTTPS และ Secure Cookies**:
   ตั้งค่า Web Server (Apache/Nginx) ให้บังคับใช้ TLS 1.3/HTTPS และกำหนด:
   ```ini
   SESSION_SECURE_COOKIE=true
   ```
   ทำให้เบราว์เซอร์ส่ง Session Cookie ผ่านช่องทางที่เข้ารหัสเท่านั้น

3. **การเชื่อมต่อ SSO จริงของหน่วยงาน**:
   - กำหนดค่า `SSO_CLIENT_ID`, `SSO_CLIENT_SECRET`, `SSO_REDIRECT_URI` ในไฟล์ `.env`
   - ตรวจสอบให้แน่ใจว่าเปิดใช้งาน `CURLOPT_SSL_VERIFYPEER = true` (เปิดใช้งานเป็นค่าเริ่มต้นใน `includes/sso.php`)
   - ห้าม commit ค่า Client Secret หรือ Database Password ลงใน Git Repository

4. **การป้องกัน Directory Traversal และการเข้าถึงไฟล์โดยตรง**:
   - ไดเรกทอรี `uploads/documents/` และ `uploads/meetings/` มีไฟล์ `.htaccess` ป้องกัน Direct Download ไว้อยู่แล้ว ให้ตรวจสอบว่า Apache เปิดใช้งานโมดูล `mod_authz_core`
   - ทุกการเข้าถึงไฟล์ต้องผ่าน `download.php?id=...` ซึ่งมีการตรวจสอบ Session และ Role ทุกครั้ง

---

## 8. ทักษะ PHP และแนวคิดที่ใช้ในผลงานนี้ (Technical Competencies)

- **Language & Runtime**: PHP 8 (Typed properties, Null coalescing operator, Match expressions)
- **Database & Persistence**: MySQL / MariaDB, PDO Prepared Statements, Row Locking (`FOR UPDATE`), Transactions (`beginTransaction`, `commit`, `rollBack`)
- **Web Security**: OWASP Top 10 Defenses (CSRF Tokens, Hash Equals, finfo MIME Verification, Context Escaping, Safe File Handling, Timing-Attack Resistant Comparison)
- **Architecture**: Modular Monolith, Clean Code Separation (Services, Helpers, Controllers, Views)
- **Quality Assurance**: Automated CLI Testing Suite, Unit & Integration Validation


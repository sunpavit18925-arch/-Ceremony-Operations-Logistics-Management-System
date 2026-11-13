-- =====================================================================
-- Pabpla Application - Synthetic Seed Data for Portfolio Demo
-- All names, documents, and credentials in this file are entirely fictional.
-- =====================================================================

-- 1. Synthetic Demo Users (Default password for all demo accounts: password123)
INSERT INTO `users` (`id`, `username_sso`, `password_hash`, `fname`, `lname`, `depart_code`, `depart_name`, `email`, `mobile_phone`, `cid`, `role`) VALUES
(1, 'superadmin_demo', '$2y$10$FxGZwyUjArtCQ5AWt5JOz.YqGWHr6pyx8ElfYBhPxJTZwgRCK0Ho6', 'สมชาย', 'ยอดบริหาร', 'DPT-001', 'ศูนย์เทคโนโลยีสารสนเทศ', 'somchai.s@demo.gov.th', '081-111-2222', '1100100000001', 'super_admin'),
(2, 'admin_demo', '$2y$10$FxGZwyUjArtCQ5AWt5JOz.YqGWHr6pyx8ElfYBhPxJTZwgRCK0Ho6', 'ประสิทธิ์', 'จัดการงาน', 'DPT-002', 'กองกลาง ฝ่ายสารบรรณ', 'prasit.m@demo.gov.th', '081-222-3333', '1100100000002', 'admin'),
(3, 'head_op_demo', '$2y$10$FxGZwyUjArtCQ5AWt5JOz.YqGWHr6pyx8ElfYBhPxJTZwgRCK0Ho6', 'วิโรจน์', 'นำปฏิบัติ', 'DPT-003', 'กลุ่มงานพิธีการและปฏิบัติการ', 'wiroj.h@demo.gov.th', '081-333-4444', '1100100000003', 'head_operation'),
(4, 'coord_op_demo', '$2y$10$FxGZwyUjArtCQ5AWt5JOz.YqGWHr6pyx8ElfYBhPxJTZwgRCK0Ho6', 'ณัฐพงษ์', 'ประสานมิตร', 'DPT-003', 'กลุ่มงานพิธีการและปฏิบัติการ', 'nattapong.c@demo.gov.th', '081-444-5555', '1100100000004', 'coordinator_operation'),
(5, 'staff_op_demo', '$2y$10$FxGZwyUjArtCQ5AWt5JOz.YqGWHr6pyx8ElfYBhPxJTZwgRCK0Ho6', 'กิตติ', 'พร้อมลงพื้นที่', 'DPT-003', 'กลุ่มงานพิธีการและปฏิบัติการ', 'kitti.s@demo.gov.th', '081-555-6666', '1100100000005', 'staff_operation'),
(6, 'head_veh_demo', '$2y$10$FxGZwyUjArtCQ5AWt5JOz.YqGWHr6pyx8ElfYBhPxJTZwgRCK0Ho6', 'อนันต์', 'คุมยานยนต์', 'DPT-004', 'ฝ่ายยานพาหนะและขนส่ง', 'anan.v@demo.gov.th', '081-666-7777', '1100100000006', 'head_vehicle'),
(7, 'staff_veh_demo', '$2y$10$FxGZwyUjArtCQ5AWt5JOz.YqGWHr6pyx8ElfYBhPxJTZwgRCK0Ho6', 'วิชัย', 'ขับปลอดภัย', 'DPT-004', 'ฝ่ายยานพาหนะและขนส่ง', 'wichai.d@demo.gov.th', '081-777-8888', '1100100000007', 'staff_vehicle'),
(8, 'pending_demo', '$2y$10$FxGZwyUjArtCQ5AWt5JOz.YqGWHr6pyx8ElfYBhPxJTZwgRCK0Ho6', 'มาณี', 'รออนุมัติ', 'DPT-005', 'สำนักผังเมืองรวม', 'manee.p@demo.gov.th', '081-888-9999', '1100100000008', 'pending');

-- 2. Master Equipments
INSERT INTO `equipments` (`id`, `equipment_code`, `equipment_name`, `equipment_category`, `total_quantity`, `available_quantity`, `unit`, `status`) VALUES
(1, 'EQ-AUDIO-01', 'ชุดเครื่องเสียงไร้สายภาคสนาม', 'ระบบเสียง', 5, 5, 'ชุด', 'active'),
(2, 'EQ-TENT-01', 'เต็นท์พิธีการทรงจั่ว 5x10 ม.', 'โครงสร้าง', 8, 8, 'หลัง', 'active'),
(3, 'EQ-ROYAL-01', 'แท่นและกรอบพระบรมฉายาลักษณ์', 'อุปกรณ์พิธีการ', 4, 4, 'ชุด', 'active'),
(4, 'EQ-CHAIR-01', 'ชุดเก้าอี้หลุยส์พิธีการ (ชุด 6 ตัว)', 'เฟอร์นิเจอร์', 10, 10, 'ชุด', 'active'),
(5, 'EQ-GEN-01', 'เครื่องกำเนิดไฟฟ้าสำรอง 10kVA', 'ระบบไฟฟ้า', 3, 3, 'เครื่อง', 'active'),
(6, 'EQ-MIC-01', 'ไมโครโฟนโพเดียมไร้สาย', 'ระบบเสียง', 6, 6, 'ตัว', 'active');

-- 3. Master Vehicle Types & Vehicles
INSERT INTO `vehicle_types` (`id`, `type_name`) VALUES
(1, 'รถตู้โดยสาร 11 ที่นั่ง'),
(2, 'รถบรรทุก 6 ล้อ สำหรับขนส่งอุปกรณ์'),
(3, 'รถยนต์ส่วนกลางตรวจการ 4 ประตู');

INSERT INTO `vehicles` (`id`, `vehicle_type`, `plate_letters`, `plate_numbers`, `plate_province`, `license_plate`, `status`) VALUES
(1, 'รถตู้โดยสาร 11 ที่นั่ง', 'นข', '4589', 'กรุงเทพมหานคร', 'นข 4589 กรุงเทพมหานคร', 'active'),
(2, 'รถบรรทุก 6 ล้อ สำหรับขนส่งอุปกรณ์', 'บท', '8812', 'นนทบุรี', 'บท 8812 นนทบุรี', 'active'),
(3, 'รถยนต์ส่วนกลางตรวจการ 4 ประตู', 'กข', '1234', 'กรุงเทพมหานคร', 'กข 1234 กรุงเทพมหานคร', 'active');

-- 4. Driver Qualifications
INSERT INTO `driver_qualifications` (`id`, `user_id`, `vehicle_type`) VALUES
(1, 6, 'รถตู้โดยสาร 11 ที่นั่ง'),
(2, 6, 'รถยนต์ส่วนกลางตรวจการ 4 ประตู'),
(3, 7, 'รถตู้โดยสาร 11 ที่นั่ง'),
(4, 7, 'รถบรรทุก 6 ล้อ สำหรับขนส่งอุปกรณ์'),
(5, 7, 'รถยนต์ส่วนกลางตรวจการ 4 ประตู');

-- 5. Synthetic Documents (Missions)
INSERT INTO `documents` (`id`, `fiscal_year`, `doc_number`, `source_doc_number`, `doc_name`, `description`, `job_type`, `host_agency`, `venue_name`, `venue_province`, `event_start_date`, `event_end_date`, `received_date`, `created_by`, `sent_to`, `doc_status`, `is_acknowledged`) VALUES
(1, '2567', 'มท 0701/101', 'ว 123/2567', 'โครงการจัดเตรียมพลับพลาพิธีเปิดอาคารศูนย์ราชการกระทรวงมหาดไทย', 'ภารกิจสนับสนุนจัดตั้งพลับพลาพิธีการ ระบบเครื่องเสียง และควบคุมยานพาหนะรับส่งผู้แทนหน่วยงาน', 'พิธีการ', 'สำนักงานปลัดกระทรวงมหาดไทย', 'ศูนย์ราชการกระทรวงมหาดไทย', 'กรุงเทพมหานคร', '2026-11-10', '2026-11-12', '2026-10-15 09:00:00', 'ประสิทธิ์ จัดการงาน', 4, 'assigned', 1),
(2, '2567', 'มท 0701/102', 'ว 145/2567', 'พิธีวางศิลาฤกษ์โครงการเขื่อนป้องกันตลิ่งริมแม่น้ำเจ้าพระยา', 'จัดเตรียมสถานที่ อุปกรณ์เครื่องเสียง และจัดชุดปฏิบัติการพร้อมรถบรรทุกขนส่งอุปกรณ์', 'วางศิลาฤกษ์', 'สำนักสนับสนุนและพัฒนาตามผังเมือง', 'ริมแม่น้ำเจ้าพระยา อ.เมือง', 'พระนครศรีอยุธยา', '2026-11-20', '2026-11-21', '2026-10-16 10:30:00', 'ประสิทธิ์ จัดการงาน', 4, 'pending', 0),
(3, '2567', 'มท 0701/103', 'ว 188/2567', 'การประชุมรับฟังความคิดเห็นของประชาชนด้านการวางผังเมืองรวมจังหวัดชลบุรี', 'ประสานงานห้องประชุม บันทึกรายงานการประชุม และสนับสนุนการลงพื้นที่สำรวจข้อมูลภาคสนาม', 'ประชุมรับฟังความคิดเห็น', 'สำนักผังเมืองรวม', 'ศาลากลางจังหวัดชลบุรี', 'ชลบุรี', '2026-11-25', '2026-11-26', '2026-10-18 14:00:00', 'ประสิทธิ์ จัดการงาน', NULL, 'pending', 0);

-- 6. Document Assignment for Demo
INSERT INTO `document_assignments` (`id`, `doc_id`, `user_id`, `assigned_at`, `is_acknowledged`) VALUES
(1, 1, 4, '2026-10-15 10:00:00', 1);

-- 7. Jobs & Vehicle Assignments
INSERT INTO `jobs` (`id`, `job_code`, `job_type`, `job_name`, `location`, `host_agency`, `coordinator_name`, `coordinator_phone`, `start_date`, `end_date`) VALUES
(1, 'JOB-2567-001', 'พิธีการ', 'โครงการจัดเตรียมพลับพลาพิธีเปิดอาคารศูนย์ราชการกระทรวงมหาดไทย', 'ศูนย์ราชการกระทรวงมหาดไทย', 'สำนักงานปลัดกระทรวงมหาดไทย', 'ณัฐพงษ์ ประสานมิตร', '081-444-5555', '2026-11-10', '2026-11-12');

INSERT INTO `vehicle_assignments` (`id`, `job_id`, `vehicle_type`, `license_plate`, `driver_name`, `assistant_1`, `assistant_2`) VALUES
(1, 1, 'รถตู้โดยสาร 11 ที่นั่ง', 'นข 4589 กรุงเทพมหานคร', 'วิชัย ขับปลอดภัย', 'กิตติ พร้อมลงพื้นที่', '');

-- 8. Audit Logs
INSERT INTO `system_logs` (`id`, `job_id`, `action_type`, `action_detail`, `performed_by`, `created_at`) VALUES
(1, 1, 'CREATE_DOC', 'สร้างหนังสือรับเรื่อง มท 0701/101', 'ประสิทธิ์ จัดการงาน', '2026-10-15 09:00:00'),
(2, 1, 'ASSIGN_STAFF', 'มอบหมายผู้ประสานงาน: ณัฐพงษ์ ประสานมิตร', 'วิโรจน์ นำปฏิบัติ', '2026-10-15 10:00:00'),
(3, 1, 'ASSIGN_VEHICLE', 'จัดรถตู้ นข 4589 กรุงเทพมหานคร คนขับ วิชัย ขับปลอดภัย', 'อนันต์ คุมยานยนต์', '2026-10-16 11:00:00');


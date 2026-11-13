-- =====================================================================
-- Pabpla Application - Clean Database Schema (UTF-8 / utf8mb4)
-- =====================================================================

CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username_sso` varchar(100) DEFAULT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `fname` varchar(100) NOT NULL,
  `lname` varchar(100) NOT NULL,
  `depart_code` varchar(20) DEFAULT NULL,
  `depart_name` varchar(255) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `mobile_phone` varchar(50) DEFAULT NULL,
  `cid` varchar(20) DEFAULT NULL,
  `role` enum('super_admin','admin','head_operation','coordinator_operation','staff_operation','head_vehicle','staff_vehicle','pending') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_users_username_sso` (`username_sso`),
  KEY `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fiscal_year` varchar(4) DEFAULT NULL,
  `doc_number` varchar(100) DEFAULT NULL,
  `source_doc_number` varchar(100) DEFAULT NULL,
  `doc_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `doc_detail` text DEFAULT NULL,
  `job_type` varchar(100) DEFAULT NULL,
  `job_type_other` varchar(255) DEFAULT NULL,
  `host_agency` varchar(255) DEFAULT NULL,
  `host_agency_affiliation` varchar(255) DEFAULT NULL,
  `coordinator` varchar(255) DEFAULT NULL,
  `coordinator_1_name` varchar(150) DEFAULT NULL,
  `coordinator_1_phone` varchar(50) DEFAULT NULL,
  `coordinator_2_name` varchar(150) DEFAULT NULL,
  `coordinator_2_phone` varchar(50) DEFAULT NULL,
  `job_id` varchar(50) DEFAULT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `venue_name` varchar(255) DEFAULT NULL,
  `venue_province` varchar(100) DEFAULT NULL,
  `venue_district` varchar(100) DEFAULT NULL,
  `venue_subdistrict` varchar(100) DEFAULT NULL,
  `event_start_date` date DEFAULT NULL,
  `event_end_date` date DEFAULT NULL,
  `pre_meeting_date` date DEFAULT NULL,
  `equipment_borrow_date` date DEFAULT NULL,
  `dpt_work_start` datetime DEFAULT NULL,
  `dpt_work_end` datetime DEFAULT NULL,
  `received_date` datetime DEFAULT NULL,
  `dpt_receive_number` varchar(100) DEFAULT NULL,
  `dpt_receive_date` date DEFAULT NULL,
  `division_receive_number` varchar(100) DEFAULT NULL,
  `division_receive_date` date DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `sent_to` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `doc_status` varchar(50) DEFAULT 'pending',
  `attachment_file` varchar(255) DEFAULT NULL,
  `remark` text DEFAULT NULL,
  `is_acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_documents_sent_to` (`sent_to`),
  KEY `idx_documents_doc_status` (`doc_status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `document_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `doc_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_acknowledged` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_doc_assign_doc` (`doc_id`),
  KEY `idx_doc_assign_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `jobs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_code` varchar(20) DEFAULT NULL,
  `job_type` varchar(255) DEFAULT NULL,
  `job_name` text DEFAULT NULL,
  `location` text DEFAULT NULL,
  `host_agency` varchar(255) DEFAULT NULL,
  `coordinator_name` varchar(150) DEFAULT NULL,
  `coordinator_phone` varchar(50) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `pre_meeting_date` date DEFAULT NULL,
  `dpt_work_start` datetime DEFAULT NULL,
  `dpt_work_end` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `job_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `staff_name` varchar(255) DEFAULT NULL,
  `staff_role` varchar(100) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_job_assign_job` (`job_id`),
  KEY `idx_job_assign_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `equipments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `equipment_code` varchar(100) NOT NULL,
  `equipment_name` varchar(255) NOT NULL,
  `equipment_category` varchar(100) DEFAULT NULL,
  `total_quantity` int(11) NOT NULL DEFAULT 0,
  `available_quantity` int(11) NOT NULL DEFAULT 0,
  `unit` varchar(50) DEFAULT 'ชุด',
  `status` varchar(50) DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_equipments_code` (`equipment_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `equipment_borrowings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `equipment_code` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `status` enum('borrowed','returned') NOT NULL DEFAULT 'borrowed',
  `returned_at` datetime DEFAULT NULL,
  `returned_by` int(11) DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_eq_borrow_job` (`job_id`),
  KEY `idx_eq_borrow_code` (`equipment_code`),
  KEY `idx_eq_borrow_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `system_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) DEFAULT NULL,
  `action_type` varchar(50) NOT NULL,
  `action_detail` text NOT NULL,
  `performed_by` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_logs_job` (`job_id`),
  KEY `idx_logs_action` (`action_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `vehicle_type` varchar(100) NOT NULL,
  `plate_letters` varchar(20) DEFAULT NULL,
  `plate_numbers` varchar(20) DEFAULT NULL,
  `plate_province` varchar(100) DEFAULT NULL,
  `license_plate` varchar(50) NOT NULL,
  `status` varchar(50) DEFAULT 'active',
  PRIMARY KEY (`id`),
  KEY `idx_vehicles_type` (`vehicle_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicle_types` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_vtype_name` (`type_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `vehicle_assignments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `vehicle_type` varchar(100) DEFAULT NULL,
  `license_plate` varchar(50) DEFAULT NULL,
  `driver_name` varchar(100) DEFAULT NULL,
  `assistant_1` varchar(100) DEFAULT NULL,
  `assistant_2` varchar(100) DEFAULT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_vassign_job` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `driver_qualifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `vehicle_type` varchar(100) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_driver_qual_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `town_planning_meetings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `document_id` int(11) DEFAULT NULL,
  `meeting_type` varchar(150) NOT NULL,
  `meeting_code` varchar(50) NOT NULL,
  `meeting_name` varchar(255) NOT NULL,
  `meeting_no` varchar(50) DEFAULT NULL,
  `meeting_year` varchar(10) DEFAULT NULL,
  `meeting_detail` text DEFAULT NULL,
  `meeting_date` date DEFAULT NULL,
  `meeting_time` varchar(20) DEFAULT NULL,
  `meeting_room` varchar(100) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `invitation_pdf` varchar(255) DEFAULT NULL,
  `documents_link` text DEFAULT NULL,
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_town_meeting_doc` (`document_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `meeting_reports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` varchar(50) DEFAULT NULL,
  `doc_number` varchar(100) DEFAULT NULL,
  `doc_date` date DEFAULT NULL,
  `meeting_date` date DEFAULT NULL,
  `meeting_time` varchar(20) DEFAULT NULL,
  `meeting_location` varchar(255) DEFAULT NULL,
  `chairman_name` varchar(255) DEFAULT NULL,
  `event_date` date DEFAULT NULL,
  `meeting_title` varchar(255) DEFAULT NULL,
  `meeting_detail` text DEFAULT NULL,
  `attachment_file` varchar(255) DEFAULT NULL,
  `report_status` varchar(50) DEFAULT 'submitted',
  `created_by` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_meeting_report_job` (`job_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


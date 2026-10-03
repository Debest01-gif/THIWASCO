-- ============================================================
-- THIWASCO WATER LOSS, METERS & REVENUE PROTECTION SYSTEM
-- Database Schema v1.0  |  Created: 2026-10-03
-- ============================================================
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+03:00";

CREATE DATABASE IF NOT EXISTS `thiwasco_db` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `thiwasco_db`;

-- ROLES
CREATE TABLE `roles` (
  `role_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_name` varchar(50) NOT NULL,
  `role_description` text,
  `permissions` longtext,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`role_id`), UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `roles` (`role_name`,`role_description`,`permissions`) VALUES
('Super Admin','Full system access','["*"]'),
('Admin','Administrative access','["customers","meters","billing","payments","reports","users"]'),
('Meter Reader','Field meter reading','["meters.read","readings.create","inspections.create"]'),
('Cashier','Payment processing','["payments.create","payments.view","receipts"]'),
('Supervisor','Supervisory oversight','["customers.view","meters.view","billing.view","reports"]'),
('Revenue Officer','Revenue protection','["revenue_protection","inspections","enforcement"]'),
('Accounts','Financial management','["payments","billing","reports.financial"]');

-- USERS
CREATE TABLE `users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `role_id` int(11) NOT NULL,
  `employee_no` varchar(20) DEFAULT NULL,
  `full_name` varchar(150) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `zone_assigned` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `last_login` timestamp NULL DEFAULT NULL,
  `login_attempts` int(11) DEFAULT 0,
  `locked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `username` (`username`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_role_fk` FOREIGN KEY (`role_id`) REFERENCES `roles` (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin (password: Admin@2026)
INSERT INTO `users` (`role_id`,`employee_no`,`full_name`,`email`,`phone`,`username`,`password_hash`) VALUES
(1,'EMP001','System Administrator','admin@thiwasco.co.ke','+254700000001','admin','$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi');

-- AUDIT LOGS
CREATE TABLE `audit_logs` (
  `log_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `module` varchar(50) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` longtext,
  `new_values` longtext,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`log_id`),
  KEY `user_id` (`user_id`), KEY `module` (`module`), KEY `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ZONES / DMA
CREATE TABLE `zones` (
  `zone_id` int(11) NOT NULL AUTO_INCREMENT,
  `zone_code` varchar(20) NOT NULL,
  `zone_name` varchar(100) NOT NULL,
  `zone_type` enum('Zone','DMA','Sub-Zone') DEFAULT 'Zone',
  `parent_zone_id` int(11) DEFAULT NULL,
  `area_description` text,
  `gps_boundary` longtext,
  `expected_connections` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`zone_id`),
  UNIQUE KEY `zone_code` (`zone_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `zones` (`zone_code`,`zone_name`,`zone_type`,`area_description`) VALUES
('ZN-001','Zone 1 - Town Centre','Zone','Main town centre distribution zone'),
('ZN-002','Zone 2 - Industrial Area','Zone','Industrial and commercial zone'),
('ZN-003','Zone 3 - Residential North','Zone','Northern residential estates'),
('ZN-004','Zone 4 - Residential South','Zone','Southern residential estates'),
('ZN-005','Zone 5 - Peri-Urban','Zone','Peri-urban and informal settlements');

-- BULK METERS
CREATE TABLE `bulk_meters` (
  `bulk_meter_id` int(11) NOT NULL AUTO_INCREMENT,
  `zone_id` int(11) NOT NULL,
  `meter_serial` varchar(50) NOT NULL,
  `meter_name` varchar(100) NOT NULL,
  `meter_size` varchar(20) DEFAULT NULL,
  `installation_date` date DEFAULT NULL,
  `location_description` text,
  `gps_lat` decimal(10,7) DEFAULT NULL,
  `gps_lng` decimal(10,7) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`bulk_meter_id`),
  KEY `zone_id` (`zone_id`),
  CONSTRAINT `bulk_zone_fk` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- BULK METER READINGS
CREATE TABLE `bulk_meter_readings` (
  `reading_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `bulk_meter_id` int(11) NOT NULL,
  `read_by` int(11) NOT NULL,
  `reading_date` date NOT NULL,
  `previous_reading` decimal(12,2) DEFAULT 0.00,
  `current_reading` decimal(12,2) NOT NULL,
  `notes` text DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reading_id`),
  KEY `bulk_meter_id` (`bulk_meter_id`), KEY `reading_date` (`reading_date`),
  CONSTRAINT `bulk_rdg_meter_fk` FOREIGN KEY (`bulk_meter_id`) REFERENCES `bulk_meters` (`bulk_meter_id`),
  CONSTRAINT `bulk_rdg_user_fk` FOREIGN KEY (`read_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- TARIFF CATEGORIES
CREATE TABLE `tariff_categories` (
  `tariff_id` int(11) NOT NULL AUTO_INCREMENT,
  `tariff_code` varchar(20) NOT NULL,
  `tariff_name` varchar(100) NOT NULL,
  `tariff_type` enum('Domestic','Commercial','Industrial','Institution','Standpipe','Kiosk') DEFAULT 'Domestic',
  `base_charge` decimal(10,2) DEFAULT 0.00,
  `rate_per_m3` decimal(10,4) DEFAULT 0.00,
  `sewer_percent` decimal(5,2) DEFAULT 0.00,
  `min_charge` decimal(10,2) DEFAULT 0.00,
  `tiered_rates` longtext,
  `is_active` tinyint(1) DEFAULT 1,
  `effective_from` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`tariff_id`), UNIQUE KEY `tariff_code` (`tariff_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `tariff_categories` (`tariff_code`,`tariff_name`,`tariff_type`,`base_charge`,`rate_per_m3`,`sewer_percent`,`min_charge`,`tiered_rates`,`effective_from`) VALUES
('DOM-01','Domestic - Standard','Domestic',150.00,52.00,60.00,300.00,'[{"from":0,"to":6,"rate":52},{"from":7,"to":20,"rate":62},{"from":21,"to":50,"rate":75},{"from":51,"to":999999,"rate":95}]','2024-01-01'),
('COM-01','Commercial - Standard','Commercial',300.00,85.00,60.00,500.00,'[{"from":0,"to":10,"rate":85},{"from":11,"to":50,"rate":98},{"from":51,"to":999999,"rate":115}]','2024-01-01'),
('IND-01','Industrial - Standard','Industrial',500.00,120.00,60.00,800.00,'[{"from":0,"to":999999,"rate":120}]','2024-01-01'),
('INST-01','Institution - Standard','Institution',300.00,72.00,60.00,500.00,'[{"from":0,"to":20,"rate":72},{"from":21,"to":999999,"rate":88}]','2024-01-01'),
('KIOSK-01','Water Kiosk','Kiosk',100.00,35.00,0.00,200.00,'[{"from":0,"to":999999,"rate":35}]','2024-01-01');

-- CUSTOMERS
CREATE TABLE `customers` (
  `customer_id` int(11) NOT NULL AUTO_INCREMENT,
  `account_no` varchar(30) NOT NULL,
  `zone_id` int(11) NOT NULL,
  `tariff_id` int(11) NOT NULL,
  `customer_type` enum('Individual','Company','Institution','Kiosk') DEFAULT 'Individual',
  `full_name` varchar(150) NOT NULL,
  `id_number` varchar(30) DEFAULT NULL,
  `phone` varchar(20) NOT NULL,
  `phone_alt` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `physical_address` text,
  `plot_no` varchar(50) DEFAULT NULL,
  `road_street` varchar(100) DEFAULT NULL,
  `connection_date` date DEFAULT NULL,
  `connection_size` varchar(20) DEFAULT NULL,
  `gps_lat` decimal(10,7) DEFAULT NULL,
  `gps_lng` decimal(10,7) DEFAULT NULL,
  `status` enum('Active','Inactive','Disconnected','Suspended','Defaulter') DEFAULT 'Active',
  `balance` decimal(12,2) DEFAULT 0.00,
  `deposit_paid` decimal(10,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`customer_id`),
  UNIQUE KEY `account_no` (`account_no`),
  KEY `zone_id` (`zone_id`), KEY `tariff_id` (`tariff_id`), KEY `status` (`status`),
  CONSTRAINT `cust_zone_fk` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`zone_id`),
  CONSTRAINT `cust_tariff_fk` FOREIGN KEY (`tariff_id`) REFERENCES `tariff_categories` (`tariff_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- METERS
CREATE TABLE `meters` (
  `meter_id` int(11) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `meter_serial` varchar(50) NOT NULL,
  `meter_make` varchar(50) DEFAULT NULL,
  `meter_model` varchar(50) DEFAULT NULL,
  `meter_size` varchar(20) DEFAULT NULL,
  `meter_type` enum('Analogue','Digital','Smart','Pre-paid') DEFAULT 'Analogue',
  `installation_date` date DEFAULT NULL,
  `seal_no` varchar(50) DEFAULT NULL,
  `initial_reading` decimal(12,2) DEFAULT 0.00,
  `current_reading` decimal(12,2) DEFAULT 0.00,
  `gps_lat` decimal(10,7) DEFAULT NULL,
  `gps_lng` decimal(10,7) DEFAULT NULL,
  `meter_photo` varchar(255) DEFAULT NULL,
  `status` enum('Active','Removed','Faulty','Stolen','Bypassed','Condemned') DEFAULT 'Active',
  `last_read_date` date DEFAULT NULL,
  `installed_by` int(11) DEFAULT NULL,
  `removed_date` date DEFAULT NULL,
  `removal_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`meter_id`),
  UNIQUE KEY `meter_serial` (`meter_serial`),
  KEY `customer_id` (`customer_id`), KEY `status` (`status`),
  CONSTRAINT `meters_cust_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- METER READINGS
CREATE TABLE `meter_readings` (
  `reading_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `meter_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `read_by` int(11) NOT NULL,
  `billing_month` varchar(7) NOT NULL,
  `reading_date` date NOT NULL,
  `previous_reading` decimal(12,2) NOT NULL DEFAULT 0.00,
  `current_reading` decimal(12,2) NOT NULL,
  `reading_type` enum('Actual','Estimated','Customer','Opening') DEFAULT 'Actual',
  `anomaly_flag` tinyint(1) DEFAULT 0,
  `anomaly_reason` varchar(100) DEFAULT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`reading_id`),
  UNIQUE KEY `meter_billing_month` (`meter_id`,`billing_month`),
  KEY `customer_id` (`customer_id`), KEY `billing_month` (`billing_month`),
  CONSTRAINT `rdg_meter_fk` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`meter_id`),
  CONSTRAINT `rdg_cust_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `rdg_user_fk` FOREIGN KEY (`read_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- INVOICES
CREATE TABLE `invoices` (
  `invoice_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(30) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `meter_id` int(11) NOT NULL,
  `reading_id` bigint(20) DEFAULT NULL,
  `billing_month` varchar(7) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `previous_reading` decimal(12,2) DEFAULT 0.00,
  `current_reading` decimal(12,2) DEFAULT 0.00,
  `consumption_m3` decimal(12,2) DEFAULT 0.00,
  `water_charge` decimal(12,2) DEFAULT 0.00,
  `sewer_charge` decimal(12,2) DEFAULT 0.00,
  `base_charge` decimal(12,2) DEFAULT 0.00,
  `penalties` decimal(12,2) DEFAULT 0.00,
  `other_charges` decimal(12,2) DEFAULT 0.00,
  `vat_amount` decimal(12,2) DEFAULT 0.00,
  `total_bill` decimal(12,2) NOT NULL,
  `arrears_brought_forward` decimal(12,2) DEFAULT 0.00,
  `total_payable` decimal(12,2) NOT NULL,
  `amount_paid` decimal(12,2) DEFAULT 0.00,
  `status` enum('Unpaid','Partial','Paid','Cancelled','Waived') DEFAULT 'Unpaid',
  `generated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`invoice_id`),
  UNIQUE KEY `invoice_no` (`invoice_no`),
  KEY `customer_id` (`customer_id`), KEY `billing_month` (`billing_month`), KEY `status` (`status`),
  CONSTRAINT `inv_cust_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `inv_meter_fk` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`meter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PAYMENT METHODS
CREATE TABLE `payment_methods` (
  `method_id` int(11) NOT NULL AUTO_INCREMENT,
  `method_name` varchar(50) NOT NULL,
  `method_code` varchar(20) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`method_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `payment_methods` (`method_name`,`method_code`) VALUES
('Cash','CASH'),('M-Pesa','MPESA'),('Bank Transfer','BANK'),('Cheque','CHEQUE'),('Credit/Debit Card','CARD'),('RTGS','RTGS');

-- PAYMENTS
CREATE TABLE `payments` (
  `payment_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `receipt_no` varchar(30) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `received_by` int(11) NOT NULL,
  `payment_method_id` int(11) NOT NULL,
  `payment_date` date NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `mpesa_ref` varchar(50) DEFAULT NULL,
  `bank_ref` varchar(100) DEFAULT NULL,
  `cheque_no` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `is_reversed` tinyint(1) DEFAULT 0,
  `reversal_reason` text DEFAULT NULL,
  `reversed_by` int(11) DEFAULT NULL,
  `reversed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`payment_id`),
  UNIQUE KEY `receipt_no` (`receipt_no`),
  KEY `customer_id` (`customer_id`), KEY `payment_date` (`payment_date`),
  CONSTRAINT `pay_cust_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `pay_user_fk` FOREIGN KEY (`received_by`) REFERENCES `users` (`user_id`),
  CONSTRAINT `pay_method_fk` FOREIGN KEY (`payment_method_id`) REFERENCES `payment_methods` (`method_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- PAYMENT ALLOCATIONS
CREATE TABLE `payment_allocations` (
  `allocation_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `payment_id` bigint(20) NOT NULL,
  `invoice_id` bigint(20) NOT NULL,
  `amount_allocated` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`allocation_id`),
  KEY `payment_id` (`payment_id`), KEY `invoice_id` (`invoice_id`),
  CONSTRAINT `alloc_pay_fk` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`payment_id`),
  CONSTRAINT `alloc_inv_fk` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`invoice_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- WATER PRODUCTION
CREATE TABLE `water_production` (
  `production_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `source_name` varchar(100) NOT NULL,
  `source_type` enum('Borehole','River','Dam','Spring','Purchased') DEFAULT 'Borehole',
  `record_date` date NOT NULL,
  `volume_m3` decimal(12,2) NOT NULL,
  `pumping_hours` decimal(8,2) DEFAULT NULL,
  `energy_kwh` decimal(10,2) DEFAULT NULL,
  `recorded_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`production_id`),
  KEY `record_date` (`record_date`),
  CONSTRAINT `prod_user_fk` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- NRW REPORTS
CREATE TABLE `nrw_reports` (
  `nrw_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `zone_id` int(11) DEFAULT NULL,
  `report_period` varchar(7) NOT NULL,
  `total_production_m3` decimal(14,2) DEFAULT 0.00,
  `total_billed_m3` decimal(14,2) DEFAULT 0.00,
  `nrw_percent` decimal(7,2) DEFAULT NULL,
  `commercial_losses_m3` decimal(14,2) DEFAULT 0.00,
  `physical_losses_m3` decimal(14,2) DEFAULT 0.00,
  `real_losses_m3` decimal(14,2) DEFAULT 0.00,
  `apparent_losses_m3` decimal(14,2) DEFAULT 0.00,
  `unbilled_authorised_m3` decimal(14,2) DEFAULT 0.00,
  `ifi` decimal(10,4) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`nrw_id`),
  UNIQUE KEY `zone_period` (`zone_id`,`report_period`),
  KEY `report_period` (`report_period`),
  CONSTRAINT `nrw_zone_fk` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`zone_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- INSPECTION TYPES
CREATE TABLE `inspection_types` (
  `type_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_name` varchar(100) NOT NULL,
  `description` text,
  PRIMARY KEY (`type_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `inspection_types` (`type_name`,`description`) VALUES
('Meter Tampering','Meter has been physically tampered with'),
('Illegal Connection','Unauthorized connection to water network'),
('Meter Bypass','Water flowing bypassing the meter'),
('Stolen Meter','Meter stolen from premises'),
('Revenue Leakage','Water consumed but not billed'),
('Meter Fast/Slow','Meter running abnormally fast or slow'),
('Broken Seal','Meter seal broken indicating tampering'),
('Meter Reading Fraud','Fraudulent meter reading captured'),
('Illegal Reconnection','Self-reconnection after disconnection');

-- FIELD INSPECTIONS
CREATE TABLE `field_inspections` (
  `inspection_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `inspection_ref` varchar(30) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `meter_id` int(11) DEFAULT NULL,
  `zone_id` int(11) NOT NULL,
  `inspection_type_id` int(11) NOT NULL,
  `inspected_by` int(11) NOT NULL,
  `inspection_date` date NOT NULL,
  `gps_lat` decimal(10,7) DEFAULT NULL,
  `gps_lng` decimal(10,7) DEFAULT NULL,
  `findings` text NOT NULL,
  `evidence_photo1` varchar(255) DEFAULT NULL,
  `evidence_photo2` varchar(255) DEFAULT NULL,
  `evidence_photo3` varchar(255) DEFAULT NULL,
  `estimated_loss_m3` decimal(10,2) DEFAULT NULL,
  `estimated_revenue_loss` decimal(12,2) DEFAULT NULL,
  `status` enum('Open','Under Investigation','Resolved','Closed','Referred') DEFAULT 'Open',
  `action_taken` text DEFAULT NULL,
  `penalty_amount` decimal(12,2) DEFAULT 0.00,
  `penalty_invoiced` tinyint(1) DEFAULT 0,
  `closed_by` int(11) DEFAULT NULL,
  `closed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`inspection_id`),
  UNIQUE KEY `inspection_ref` (`inspection_ref`),
  KEY `customer_id` (`customer_id`), KEY `zone_id` (`zone_id`), KEY `status` (`status`),
  CONSTRAINT `insp_cust_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `insp_meter_fk` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`meter_id`),
  CONSTRAINT `insp_zone_fk` FOREIGN KEY (`zone_id`) REFERENCES `zones` (`zone_id`),
  CONSTRAINT `insp_type_fk` FOREIGN KEY (`inspection_type_id`) REFERENCES `inspection_types` (`type_id`),
  CONSTRAINT `insp_user_fk` FOREIGN KEY (`inspected_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- DISCONNECTIONS
CREATE TABLE `disconnections` (
  `disconnection_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `customer_id` int(11) NOT NULL,
  `meter_id` int(11) NOT NULL,
  `disconnect_date` date NOT NULL,
  `reason` enum('Non-Payment','Tampering','Illegal Connection','Application','Other') NOT NULL,
  `arrears_at_disconnect` decimal(12,2) DEFAULT 0.00,
  `disconnected_by` int(11) NOT NULL,
  `reconnect_date` date DEFAULT NULL,
  `reconnect_fee` decimal(10,2) DEFAULT 0.00,
  `reconnected_by` int(11) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`disconnection_id`),
  KEY `customer_id` (`customer_id`),
  CONSTRAINT `disc_cust_fk` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`customer_id`),
  CONSTRAINT `disc_meter_fk` FOREIGN KEY (`meter_id`) REFERENCES `meters` (`meter_id`),
  CONSTRAINT `disc_user_fk` FOREIGN KEY (`disconnected_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== OPTIONAL MODULE 1: STORES & INVENTORY =====
CREATE TABLE `store_items` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_code` varchar(30) NOT NULL,
  `item_name` varchar(150) NOT NULL,
  `category` varchar(50) NOT NULL,
  `unit_of_measure` varchar(20) NOT NULL,
  `current_stock` decimal(12,2) DEFAULT 0.00,
  `reorder_level` decimal(12,2) DEFAULT 0.00,
  `unit_cost` decimal(12,2) DEFAULT 0.00,
  `location` varchar(100) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`item_id`), UNIQUE KEY `item_code` (`item_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `stock_transactions` (
  `transaction_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `transaction_type` enum('Received','Issued','Returned','Adjusted','Written-Off') NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `balance_after` decimal(12,2) NOT NULL,
  `unit_cost` decimal(12,2) DEFAULT 0.00,
  `reference_no` varchar(50) DEFAULT NULL,
  `purpose` text DEFAULT NULL,
  `issued_to` varchar(150) DEFAULT NULL,
  `project_ref` varchar(50) DEFAULT NULL,
  `transacted_by` int(11) NOT NULL,
  `transaction_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`transaction_id`),
  KEY `item_id` (`item_id`), KEY `transaction_date` (`transaction_date`),
  CONSTRAINT `stk_item_fk` FOREIGN KEY (`item_id`) REFERENCES `store_items` (`item_id`),
  CONSTRAINT `stk_user_fk` FOREIGN KEY (`transacted_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== OPTIONAL MODULE 2: PROCUREMENT =====
CREATE TABLE `suppliers` (
  `supplier_id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_code` varchar(20) NOT NULL,
  `company_name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `pin_no` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`supplier_id`), UNIQUE KEY `supplier_code` (`supplier_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `purchase_orders` (
  `po_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `lpo_no` varchar(30) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `order_date` date NOT NULL,
  `expected_delivery` date DEFAULT NULL,
  `total_amount` decimal(14,2) DEFAULT 0.00,
  `vat_amount` decimal(12,2) DEFAULT 0.00,
  `status` enum('Draft','Submitted','Approved','Delivered','Partial','Cancelled') DEFAULT 'Draft',
  `approved_by` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`po_id`), UNIQUE KEY `lpo_no` (`lpo_no`),
  KEY `supplier_id` (`supplier_id`),
  CONSTRAINT `po_supp_fk` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`supplier_id`),
  CONSTRAINT `po_user_fk` FOREIGN KEY (`created_by`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `po_items` (
  `po_item_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `po_id` bigint(20) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `quantity` decimal(12,2) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `unit_price` decimal(12,2) NOT NULL,
  `quantity_received` decimal(12,2) DEFAULT 0.00,
  PRIMARY KEY (`po_item_id`),
  KEY `po_id` (`po_id`),
  CONSTRAINT `po_items_fk` FOREIGN KEY (`po_id`) REFERENCES `purchase_orders` (`po_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== OPTIONAL MODULE 3: ASSET MANAGEMENT =====
CREATE TABLE `assets` (
  `asset_id` int(11) NOT NULL AUTO_INCREMENT,
  `asset_code` varchar(30) NOT NULL,
  `asset_name` varchar(150) NOT NULL,
  `asset_type` enum('Pipe','Pump','Tank','Borehole','Vehicle','Plant','Equipment','Building','Other') NOT NULL,
  `description` text DEFAULT NULL,
  `location` varchar(200) DEFAULT NULL,
  `gps_lat` decimal(10,7) DEFAULT NULL,
  `gps_lng` decimal(10,7) DEFAULT NULL,
  `purchase_date` date DEFAULT NULL,
  `purchase_cost` decimal(14,2) DEFAULT 0.00,
  `current_value` decimal(14,2) DEFAULT 0.00,
  `depreciation_rate` decimal(5,2) DEFAULT 0.00,
  `warranty_expiry` date DEFAULT NULL,
  `condition` enum('Excellent','Good','Fair','Poor','Condemned') DEFAULT 'Good',
  `status` enum('Active','Under Maintenance','Disposed','Condemned') DEFAULT 'Active',
  `last_maintenance` date DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`asset_id`), UNIQUE KEY `asset_code` (`asset_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `maintenance_records` (
  `maintenance_id` bigint(20) NOT NULL AUTO_INCREMENT,
  `asset_id` int(11) NOT NULL,
  `maintenance_type` enum('Preventive','Corrective','Emergency') NOT NULL,
  `maintenance_date` date NOT NULL,
  `description` text NOT NULL,
  `cost` decimal(12,2) DEFAULT 0.00,
  `performed_by` varchar(100) DEFAULT NULL,
  `next_maintenance` date DEFAULT NULL,
  `status` enum('Pending','In Progress','Completed') DEFAULT 'Completed',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`maintenance_id`),
  KEY `asset_id` (`asset_id`),
  CONSTRAINT `maint_asset_fk` FOREIGN KEY (`asset_id`) REFERENCES `assets` (`asset_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ===== OPTIONAL MODULE 4: PROJECTS & CAPITAL WORKS =====
CREATE TABLE `projects` (
  `project_id` int(11) NOT NULL AUTO_INCREMENT,
  `project_code` varchar(30) NOT NULL,
  `project_name` varchar(200) NOT NULL,
  `project_type` enum('Infrastructure','Rehabilitation','Expansion','WASH','Other') NOT NULL,
  `description` text,
  `zone_id` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `expected_end` date DEFAULT NULL,
  `actual_end` date DEFAULT NULL,
  `budget` decimal(16,2) DEFAULT 0.00,
  `spent` decimal(16,2) DEFAULT 0.00,
  `status` enum('Planning','Active','On Hold','Completed','Cancelled') DEFAULT 'Planning',
  `progress_percent` int(3) DEFAULT 0,
  `project_manager` varchar(100) DEFAULT NULL,
  `contractor` varchar(150) DEFAULT NULL,
  `funder` varchar(150) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`project_id`), UNIQUE KEY `project_code` (`project_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- SYSTEM SETTINGS
CREATE TABLE `system_settings` (
  `setting_id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text,
  `setting_group` varchar(50) DEFAULT 'general',
  `description` text,
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`setting_id`), UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `system_settings` (`setting_key`,`setting_value`,`setting_group`,`description`) VALUES
('company_name','Thika Water and Sewerage Company (THIWASCO)','general','Company full name'),
('company_short_name','THIWASCO','general','Company short name'),
('company_address','P.O. Box 4 - 01000, Thika','general','Company postal address'),
('company_phone','+254 67 20010','general','Company phone'),
('company_email','info@thiwasco.co.ke','general','Company email'),
('company_website','www.thiwasco.co.ke','general','Company website'),
('invoice_prefix','INV','billing','Invoice number prefix'),
('receipt_prefix','RCP','billing','Receipt number prefix'),
('nrw_target_percent','20','nrw','NRW target percentage (WASREB target)'),
('billing_day','1','billing','Day of month billing runs'),
('penalty_rate','5','billing','Late payment penalty rate %'),
('grace_period_days','30','billing','Grace period before penalty applies'),
('vat_rate','16','billing','VAT rate percentage'),
('currency','KES','general','Default currency'),
('mpesa_paybill','888300','payments','M-Pesa paybill number'),
('modules_enabled','["core","stores","procurement","assets","projects"]','modules','Enabled optional modules');

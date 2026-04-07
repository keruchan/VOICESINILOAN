-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Apr 07, 2026 at 05:50 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `voice2_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `barangay_id` int(10) UNSIGNED DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(60) DEFAULT NULL,
  `entity_id` int(10) UNSIGNED DEFAULT NULL,
  `description` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `user_id`, `barangay_id`, `action`, `entity_type`, `entity_id`, `description`, `ip_address`, `created_at`) VALUES
(1, 4, 1, 'blotter_filed', 'blotter', 1, 'Community report filed: BL-2026-001-0001', NULL, '2026-04-05 23:16:08'),
(2, 4, 1, 'blotter_filed', 'blotter', 2, 'Community report filed: BL-2026-001-0002 · 1 attachment(s)', NULL, '2026-04-05 23:18:08'),
(3, 4, 1, 'blotter_filed', 'blotter', 3, 'Community report filed: BL-2026-001-0003', NULL, '2026-04-05 23:42:29'),
(4, 4, 1, 'blotter_filed', 'blotter', 4, 'Community report filed: BL-2026-001-0004 · 1 attachment(s)', NULL, '2026-04-06 04:17:51'),
(5, 3, 1, 'status_updated', 'blotter', 2, 'Status → active. Approved by officer', NULL, '2026-04-06 05:10:43'),
(6, 3, 1, 'blotter_updated', 'blotter', 4, 'Status → transferred | Action → refer police | Remarks: sdfsd', NULL, '2026-04-06 05:30:44'),
(7, 3, 1, 'blotter_updated', 'blotter', 3, 'Status → closed | Action → document only', NULL, '2026-04-06 08:23:51'),
(8, 3, 1, 'mediation_completed', 'blotter', 3, 'Mediation completed — ', NULL, '2026-04-06 11:57:21'),
(9, 3, 1, 'mediation_no_show', 'blotter', 1, 'No show recorded for BL-2026-001-0001 (miss #1). respondent absent. Penalty ₱500 issued.', NULL, '2026-04-06 12:19:45'),
(10, 3, 1, 'mediation_scheduled', 'blotter', 1, 'Mediation hearing scheduled for Apr 8, 2026 at Barangay Hall', NULL, '2026-04-06 12:21:17'),
(11, 3, 1, 'mediation_no_show', 'blotter', 1, 'No show recorded for BL-2026-001-0001 (miss #2). respondent absent. Penalty ₱1000 issued.', NULL, '2026-04-06 12:22:06'),
(12, 3, 1, 'mediation_scheduled', 'blotter', 1, 'Mediation hearing scheduled for Apr 8, 2026 at Barangay Hall', NULL, '2026-04-06 12:22:38'),
(13, 3, 1, 'mediation_scheduled', 'blotter', 1, 'Mediation hearing scheduled for Apr 13, 2026 at Barangay Hall', NULL, '2026-04-06 14:43:19'),
(14, 3, 1, 'blotter_escalated', 'blotter', 1, 'Auto-escalated: BL-2026-001-0001 has 3 missed mediations.', NULL, '2026-04-06 14:44:34'),
(15, 3, 1, 'mediation_no_show', 'blotter', 1, 'No show recorded for BL-2026-001-0001 (miss #3). respondent absent. Penalty ₱2000 issued.', NULL, '2026-04-06 14:44:34'),
(16, 3, 1, 'mediation_scheduled', 'blotter', 2, 'Hearing scheduled for April 7, 2026 at Barangay Hall.', NULL, '2026-04-07 00:50:06'),
(17, 3, 1, 'respondent_no_show_1', 'blotter', 2, 'Respondent 1st miss — BL-2026-001-0002. Rescheduled to April 8, 2026.', NULL, '2026-04-07 00:51:17'),
(18, 3, 1, 'blotter_filed', 'blotter', 5, 'Blotter filed by officer: BL-2026-001-0005', NULL, '2026-04-07 01:46:32'),
(19, 4, 1, 'blotter_filed', 'blotter', 6, 'Community report filed: BL-2026-001-0006', NULL, '2026-04-07 01:50:17'),
(20, 3, 1, 'mediation_scheduled', 'blotter', 6, 'Hearing scheduled for April 7, 2026 at Barangay Hall.', NULL, '2026-04-07 01:56:20'),
(21, 3, 1, 'blotter_updated', 'blotter', 6, 'Status → mediation_set | Action → refer barangay', NULL, '2026-04-07 02:00:07'),
(22, 3, 1, 'blotter_updated', 'blotter', 6, 'Status → transferred | Action → refer police', NULL, '2026-04-07 02:00:18'),
(23, 3, 1, 'blotter_updated', 'blotter', 6, 'Status → transferred | Action → refer barangay', NULL, '2026-04-07 02:00:26'),
(24, 3, 1, 'blotter_updated', 'blotter', 6, 'Status → mediation_set | Action → mediation', NULL, '2026-04-07 02:00:34'),
(25, 5, 1, 'blotter_filed', 'blotter', 7, 'Community report filed: BL-2026-001-0007', NULL, '2026-04-07 02:05:19'),
(26, 3, 1, 'blotter_updated', 'blotter', 7, 'Status → active | Remarks: Approved by officer', NULL, '2026-04-07 02:14:50'),
(27, 3, 1, 'blotter_updated', 'blotter', 7, 'Status → closed | Action → document only', NULL, '2026-04-07 02:15:17'),
(28, 3, 1, 'respondent_updated', 'blotter', 7, 'Respondent details updated — Respondent: Dela Cruz, Juan Santos, Contact: 09123123533, Location: asdasd, Pandeno, Linked to user ID: 4', NULL, '2026-04-07 02:27:41');

-- --------------------------------------------------------

--
-- Table structure for table `barangays`
--

CREATE TABLE `barangays` (
  `id` int(10) UNSIGNED NOT NULL,
  `name` varchar(120) NOT NULL,
  `municipality` varchar(120) NOT NULL,
  `province` varchar(120) DEFAULT NULL,
  `psgc_code` varchar(20) DEFAULT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `email` varchar(120) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `captain_name` varchar(120) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangays`
--

INSERT INTO `barangays` (`id`, `name`, `municipality`, `province`, `psgc_code`, `contact_no`, `email`, `address`, `captain_name`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Barangay 1 - Ibaba del Sur', 'Paete', 'Laguna', '043429018', '(02) 8123-4567', 'pandeno@gmail.com', NULL, 'Juan dela Cruz', 1, '2026-03-23 05:33:12', '2026-04-07 11:11:05'),
(2, 'Barangay 2 - Maytoong', 'Paete', 'Laguna', '043429001', '(02) 8234-5678', 'acevida@gmail.com', NULL, 'Maria Santos', 1, '2026-03-23 05:33:12', '2026-04-07 11:11:05'),
(3, 'Barangay 3 - Ermita', 'Paete', 'Laguna', '043429020', '(02) 8345-6789', 'wawa@gmail.com', NULL, 'Pedro Reyes', 1, '2026-03-23 05:33:12', '2026-04-07 11:11:05'),
(4, 'Barangay 4 - Quinale', 'Siniloan', 'Laguna', '043429004', '(02) 8123-0004', 'b4@gmail.com', NULL, 'Captain 4', 1, '2026-04-07 11:11:35', '2026-04-07 11:11:35'),
(5, 'Barangay 5 - Ilaya del Sur', 'Siniloan', 'Laguna', '043429005', '(02) 8123-0005', 'b5@gmail.com', NULL, 'Captain 5', 1, '2026-04-07 11:11:35', '2026-04-07 11:11:35'),
(6, 'Barangay 6 - Ilaya del Norte', 'Siniloan', 'Laguna', '043429006', '(02) 8123-0006', 'b6@gmail.com', NULL, 'Captain 6', 1, '2026-04-07 11:11:35', '2026-04-07 11:11:35'),
(7, 'Barangay 7 - Bagumbayan', 'Siniloan', 'Laguna', '043429007', '(02) 8123-0007', 'b7@gmail.com', NULL, 'Captain 7', 1, '2026-04-07 11:11:35', '2026-04-07 11:11:35'),
(8, 'Barangay 8 - Bangkusay', 'Siniloan', 'Laguna', '043429008', '(02) 8123-0008', 'b8@gmail.com', NULL, 'Captain 8', 1, '2026-04-07 11:11:35', '2026-04-07 11:11:35'),
(9, 'Barangay 9 - Ibaba del Norte', 'Siniloan', 'Laguna', '043429009', '(02) 8123-0009', 'b9@gmail.com', NULL, 'Captain 9', 1, '2026-04-07 11:11:35', '2026-04-07 11:11:35');

-- --------------------------------------------------------

--
-- Table structure for table `barangay_name`
--

CREATE TABLE `barangay_name` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `barangay_name`
--

INSERT INTO `barangay_name` (`id`, `name`) VALUES
(1, 'Barangay 1 - Ibaba del Sur'),
(2, 'Barangay 2 - Maytoong'),
(3, 'Barangay 3 - Ermita'),
(4, 'Barangay 4 - Quinale'),
(5, 'Barangay 5 - Ilaya del Sur'),
(6, 'Barangay 6 - Ilaya del Norte'),
(7, 'Barangay 7 - Bagumbayan'),
(8, 'Barangay 8 - Bangkusay'),
(9, 'Barangay 9 - Ibaba del Norte');

-- --------------------------------------------------------

--
-- Table structure for table `blotters`
--

CREATE TABLE `blotters` (
  `id` int(10) UNSIGNED NOT NULL,
  `case_number` varchar(30) NOT NULL,
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `complainant_user_id` int(10) UNSIGNED DEFAULT NULL,
  `respondent_user_id` int(10) UNSIGNED DEFAULT NULL,
  `complainant_name` varchar(200) NOT NULL,
  `complainant_contact` varchar(20) DEFAULT NULL,
  `complainant_address` text DEFAULT NULL,
  `respondent_name` varchar(200) NOT NULL DEFAULT 'Unknown',
  `respondent_contact` varchar(20) DEFAULT NULL,
  `respondent_address` text DEFAULT NULL,
  `incident_type` varchar(100) NOT NULL,
  `violation_level` enum('minor','moderate','serious','critical') NOT NULL,
  `incident_date` date NOT NULL,
  `incident_time` time DEFAULT NULL,
  `incident_location` text DEFAULT NULL,
  `incident_lat` decimal(10,7) DEFAULT NULL,
  `incident_lng` decimal(10,7) DEFAULT NULL,
  `narrative` text DEFAULT NULL,
  `prescribed_action` enum('document_only','mediation','refer_barangay','refer_police','refer_vawc','escalate_municipality','pending') NOT NULL DEFAULT 'pending',
  `status` enum('pending_review','active','mediation_set','escalated','resolved','closed','transferred','dismissed','cfa_issued','repudiated') NOT NULL DEFAULT 'pending_review',
  `assigned_officer_id` int(10) UNSIGNED DEFAULT NULL,
  `filed_by_user_id` int(10) UNSIGNED DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `complainant_missed` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'How many mediation sessions complainant missed',
  `respondent_missed` tinyint(3) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'How many mediation sessions respondent missed',
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `blotters`
--

INSERT INTO `blotters` (`id`, `case_number`, `barangay_id`, `complainant_user_id`, `respondent_user_id`, `complainant_name`, `complainant_contact`, `complainant_address`, `respondent_name`, `respondent_contact`, `respondent_address`, `incident_type`, `violation_level`, `incident_date`, `incident_time`, `incident_location`, `incident_lat`, `incident_lng`, `narrative`, `prescribed_action`, `status`, `assigned_officer_id`, `filed_by_user_id`, `remarks`, `complainant_missed`, `respondent_missed`, `created_at`, `updated_at`) VALUES
(1, 'BL-2026-001-0001', 1, 4, NULL, 'Dela Cruz, Juan Santos', '09181234567', NULL, 'Santos, Maria', '09123123123', NULL, 'Drug-Related', 'serious', '2026-04-05', NULL, '123 L. De Leon, Acevida', NULL, NULL, 'Doing something not good', 'document_only', 'escalated', NULL, NULL, NULL, 0, 0, '2026-04-05 23:16:08', '2026-04-06 14:44:34'),
(2, 'BL-2026-001-0002', 1, 4, NULL, 'Dela Cruz, Juan Santos', '09181234567', NULL, '', '', NULL, 'BAd', 'minor', '2026-04-05', NULL, '123 JP Rizal, Pandeno', NULL, NULL, 'doing bad things on abcbcabc', 'document_only', 'mediation_set', NULL, NULL, NULL, 0, 1, '2026-04-05 23:18:08', '2026-04-07 00:51:17'),
(3, 'BL-2026-001-0003', 1, 4, NULL, 'Dela Cruz, Juan Santos', '09181234567', NULL, 'ABC, 123', '09123333123', NULL, 'bad', 'minor', '2026-04-05', NULL, '123, Halayhayin', NULL, NULL, 'asd asdeas jfhskdjf h asdj', 'document_only', 'resolved', NULL, NULL, NULL, 0, 0, '2026-04-05 23:42:29', '2026-04-06 11:57:21'),
(4, 'BL-2026-001-0004', 1, 4, NULL, 'Dela Cruz, Juan Santos', '09181234567', NULL, '', '', NULL, 'Noise Disturbance', 'minor', '2026-04-05', NULL, 'abc, Pandeno', NULL, NULL, 'sadasdasdasdas\r\nsad\r\nasd\r\nasd\r\nas\r\ndas\r\nfdas\r\ndas\r\nfas\r\nfa\r\ns', 'refer_police', 'transferred', NULL, NULL, 'sdfsd', 0, 0, '2026-04-06 04:17:51', '2026-04-06 05:30:44'),
(5, 'BL-2026-001-0005', 1, NULL, 5, 'Reyes, Maria Clara', '09123123123', NULL, 'Reyes, Maria Clara', '09123123123', NULL, 'abc abc', 'minor', '2026-04-06', NULL, 'asd, Pandeno', NULL, NULL, '123123sdfdgdfagadfhafdhadfhadfhadfha', 'document_only', 'pending_review', NULL, NULL, NULL, 0, 0, '2026-04-07 01:46:32', '2026-04-07 01:46:32'),
(6, 'BL-2026-001-0006', 1, 4, 5, 'Dela Cruz, Juan Santos', '09181234567', NULL, 'Reyes, Maria Clara', '', NULL, 'VAWC', 'critical', '2026-04-06', NULL, '123123123, Pandeno', NULL, NULL, 'dfgdfgdfgfsdfgsdgsdfgsdfgsdfgsd', 'mediation', 'mediation_set', NULL, NULL, NULL, 0, 0, '2026-04-07 01:50:17', '2026-04-07 02:00:34'),
(7, 'BL-2026-001-0007', 1, 5, 4, 'Reyes, Maria Clara', '09191234567', NULL, 'Dela Cruz, Juan Santos', '09123123533', NULL, 'Traffic Incident', 'minor', '2026-04-06', NULL, 'asdasd, Pandeno', NULL, NULL, 'fsghdfsghwsdfasdgahsdasfsa', 'document_only', 'closed', NULL, NULL, 'Approved by officer', 0, 0, '2026-04-07 02:05:19', '2026-04-07 02:27:41');

-- --------------------------------------------------------

--
-- Table structure for table `blotter_attachments`
--

CREATE TABLE `blotter_attachments` (
  `id` int(10) UNSIGNED NOT NULL,
  `blotter_id` int(10) UNSIGNED NOT NULL,
  `uploaded_by` int(10) UNSIGNED DEFAULT NULL,
  `file_path` varchar(500) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_size` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `mime_type` varchar(100) NOT NULL DEFAULT 'image/jpeg',
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blotter_attachments`
--

INSERT INTO `blotter_attachments` (`id`, `blotter_id`, `uploaded_by`, `file_path`, `original_name`, `file_size`, `mime_type`, `created_at`) VALUES
(1, 2, 4, 'uploads/blotters/2/att_69d27d3067c8f.jpg', 'download.jpg', 7258, 'image/jpeg', '2026-04-05 23:18:08'),
(2, 4, 4, 'uploads/blotters/4/att_69d2c36f3240a.jpg', 'download.jpg', 7258, 'image/jpeg', '2026-04-06 04:17:51');

-- --------------------------------------------------------

--
-- Table structure for table `mediation_schedules`
--

CREATE TABLE `mediation_schedules` (
  `id` int(10) UNSIGNED NOT NULL,
  `blotter_id` int(10) UNSIGNED NOT NULL,
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `mediator_user_id` int(10) UNSIGNED DEFAULT NULL,
  `hearing_date` date NOT NULL,
  `hearing_time` time NOT NULL,
  `venue` varchar(200) DEFAULT NULL,
  `status` enum('scheduled','completed','missed','rescheduled','cancelled') NOT NULL DEFAULT 'scheduled',
  `complainant_attended` tinyint(1) DEFAULT NULL,
  `respondent_attended` tinyint(1) DEFAULT NULL,
  `no_show_by` enum('none','complainant','respondent','both') NOT NULL DEFAULT 'none' COMMENT 'Who failed to attend',
  `reschedule_date` date DEFAULT NULL COMMENT 'New date when result is rescheduled',
  `reschedule_time` time DEFAULT NULL,
  `missed_session` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = counted as a missed session (no-show)',
  `notified_at` datetime DEFAULT NULL COMMENT 'When overdue notification was sent to officer',
  `action_issued` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = CFA or dismissal action already issued from this session',
  `action_type` varchar(50) DEFAULT NULL COMMENT 'cfa_issued | dismissed | warning_sent | rescheduled_1st | rescheduled_2nd',
  `penalty_issued` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = no-show penalty already issued',
  `outcome` text DEFAULT NULL,
  `next_steps` text DEFAULT NULL,
  `notify_sms` tinyint(1) NOT NULL DEFAULT 1,
  `notify_inapp` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `mediation_schedules`
--

INSERT INTO `mediation_schedules` (`id`, `blotter_id`, `barangay_id`, `mediator_user_id`, `hearing_date`, `hearing_time`, `venue`, `status`, `complainant_attended`, `respondent_attended`, `no_show_by`, `reschedule_date`, `reschedule_time`, `missed_session`, `notified_at`, `action_issued`, `action_type`, `penalty_issued`, `outcome`, `next_steps`, `notify_sms`, `notify_inapp`, `created_at`, `updated_at`) VALUES
(1, 3, 1, NULL, '2026-04-07', '18:33:00', 'Barangay Hall', 'completed', 1, 0, 'none', NULL, NULL, 0, NULL, 0, NULL, 0, '', '', 1, 1, '2026-04-06 05:32:14', '2026-04-06 11:57:21'),
(2, 1, 1, NULL, '2026-04-09', '01:00:00', 'Barangay Hall', 'missed', 1, 0, 'respondent', NULL, NULL, 1, '2026-04-06 12:19:45', 0, NULL, 1, 'asd', 'asd', 1, 1, '2026-04-06 12:00:15', '2026-04-06 12:19:45'),
(6, 2, 1, NULL, '2026-04-07', '09:00:00', 'Barangay Hall', 'missed', 1, 0, 'respondent', '2026-04-08', '09:00:00', 1, '2026-04-07 00:51:17', 1, 'rescheduled_1st', 0, 'dfgdfgdf', 'asgh', 1, 1, '2026-04-07 00:50:06', '2026-04-07 00:51:17'),
(7, 2, 1, NULL, '2026-04-08', '09:00:00', 'Barangay Hall', 'scheduled', NULL, NULL, 'none', NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 1, 1, '2026-04-07 00:51:17', '2026-04-07 00:51:17'),
(8, 6, 1, NULL, '2026-04-07', '09:00:00', 'Barangay Hall', 'scheduled', NULL, NULL, 'none', NULL, NULL, 0, NULL, 0, NULL, 0, NULL, NULL, 1, 1, '2026-04-07 01:56:20', '2026-04-07 01:56:20');

-- --------------------------------------------------------

--
-- Table structure for table `notices`
--

CREATE TABLE `notices` (
  `id` int(10) UNSIGNED NOT NULL,
  `blotter_id` int(10) UNSIGNED NOT NULL,
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `recipient_user_id` int(10) UNSIGNED DEFAULT NULL,
  `recipient_name` varchar(200) DEFAULT NULL,
  `notice_type` enum('summons','warning','penalty','escalation','general') NOT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `body` text DEFAULT NULL,
  `sent_via` set('sms','inapp','print') DEFAULT 'inapp',
  `sent_at` datetime DEFAULT NULL,
  `acknowledged_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `party_notifications`
--

CREATE TABLE `party_notifications` (
  `id` int(10) UNSIGNED NOT NULL,
  `blotter_id` int(10) UNSIGNED NOT NULL,
  `mediation_schedule_id` int(10) UNSIGNED DEFAULT NULL,
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `recipient_type` enum('complainant','respondent') NOT NULL,
  `recipient_user_id` int(10) UNSIGNED DEFAULT NULL,
  `recipient_name` varchar(200) NOT NULL,
  `recipient_contact` varchar(20) DEFAULT NULL,
  `notification_type` enum('hearing_scheduled','hearing_reminder','hearing_rescheduled','no_show_warning','case_dismissed','cfa_issued','mediation_completed','mediation_cancelled','case_escalated','general') NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `channel` set('inapp','sms','print') DEFAULT 'inapp',
  `status` enum('pending','sent','failed','read') NOT NULL DEFAULT 'pending',
  `sent_at` datetime DEFAULT NULL,
  `read_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `party_notifications`
--

INSERT INTO `party_notifications` (`id`, `blotter_id`, `mediation_schedule_id`, `barangay_id`, `recipient_type`, `recipient_user_id`, `recipient_name`, `recipient_contact`, `notification_type`, `subject`, `message`, `channel`, `status`, `sent_at`, `read_at`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 2, 6, 1, 'complainant', 4, 'Dela Cruz, Juan Santos', '09181234567', 'hearing_scheduled', 'Mediation Hearing Scheduled — BL-2026-001-0002', 'Dear Dela Cruz, Juan Santos, your mediation hearing for case BL-2026-001-0002 is scheduled on April 7, 2026 at 9:00 AM at Barangay Hall. Please make sure to attend.', 'inapp,sms', 'sent', '2026-04-07 00:50:16', NULL, 3, '2026-04-07 00:50:06', '2026-04-07 00:50:16'),
(2, 2, 7, 1, 'respondent', NULL, '', '', 'no_show_warning', '⚠️ Final Warning — BL-2026-001-0002', 'Dear , you failed to appear at the mediation hearing for case BL-2026-001-0002. This is your FIRST missed session. A new hearing has been scheduled on April 8, 2026 at 9:00 AM. A second absence may result in a Certification to File Action (CFA) being issued to the complainant, allowing them to pursue this case in court.', 'inapp', 'pending', NULL, NULL, 3, '2026-04-07 00:51:17', '2026-04-07 00:51:17'),
(3, 2, 7, 1, 'complainant', 4, 'Dela Cruz, Juan Santos', '09181234567', 'hearing_rescheduled', 'Hearing Rescheduled — BL-2026-001-0002', 'Dear Dela Cruz, Juan Santos, the respondent did not appear at today\'s hearing for case BL-2026-001-0002. The hearing has been rescheduled to April 8, 2026 at 9:00 AM.', 'inapp,sms', 'pending', NULL, NULL, 3, '2026-04-07 00:51:17', '2026-04-07 00:51:17'),
(4, 6, 8, 1, 'complainant', 4, 'Dela Cruz, Juan Santos', '09181234567', 'hearing_scheduled', 'Mediation Hearing Scheduled — BL-2026-001-0006', 'Dear Dela Cruz, Juan Santos, your mediation hearing for case BL-2026-001-0006 is scheduled on April 7, 2026 at 9:00 AM at Barangay Hall. Please make sure to attend.', 'inapp,sms', 'pending', NULL, NULL, 3, '2026-04-07 01:56:20', '2026-04-07 01:56:20'),
(5, 6, 8, 1, 'respondent', NULL, 'Reyes, Maria Clara', '', 'hearing_scheduled', 'Mediation Hearing Scheduled — BL-2026-001-0006', 'Dear Reyes, Maria Clara, you are required to appear at a mediation hearing for case BL-2026-001-0006 on April 7, 2026 at 9:00 AM at Barangay Hall.', 'inapp', 'pending', NULL, NULL, 3, '2026-04-07 01:56:20', '2026-04-07 01:56:20');

-- --------------------------------------------------------

--
-- Table structure for table `penalties`
--

CREATE TABLE `penalties` (
  `id` int(10) UNSIGNED NOT NULL,
  `blotter_id` int(10) UNSIGNED NOT NULL,
  `violation_id` int(10) UNSIGNED DEFAULT NULL,
  `mediation_schedule_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Which missed hearing caused this penalty',
  `missed_party` enum('complainant','respondent','both') DEFAULT NULL COMMENT 'Who the penalty is against',
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `reason` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `community_hours` int(10) UNSIGNED DEFAULT 0,
  `due_date` date DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `status` enum('pending','paid','waived','overdue') NOT NULL DEFAULT 'pending',
  `issued_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `penalties`
--

INSERT INTO `penalties` (`id`, `blotter_id`, `violation_id`, `mediation_schedule_id`, `missed_party`, `barangay_id`, `reason`, `amount`, `community_hours`, `due_date`, `paid_at`, `status`, `issued_by`, `created_at`) VALUES
(1, 1, NULL, 2, 'respondent', 1, 'Failure to appear at mediation (1st offense)', 500.00, 4, '2026-04-21', NULL, 'pending', 3, '2026-04-06 12:19:45'),
(2, 1, NULL, 3, 'respondent', 1, 'Failure to appear at mediation (2nd offense)', 1000.00, 8, '2026-04-21', NULL, 'pending', 3, '2026-04-06 12:22:06'),
(3, 1, NULL, 5, 'respondent', 1, 'Failure to appear at mediation (3rd offense)', 2000.00, 16, '2026-04-21', NULL, 'pending', 3, '2026-04-06 14:44:34');

-- --------------------------------------------------------

--
-- Table structure for table `sanctions_book`
--

CREATE TABLE `sanctions_book` (
  `id` int(10) UNSIGNED NOT NULL,
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `violation_type` varchar(100) NOT NULL,
  `violation_level` enum('minor','moderate','serious','critical') NOT NULL,
  `sanction_name` varchar(200) NOT NULL,
  `fine_amount` decimal(10,2) DEFAULT 0.00,
  `community_hours` int(10) UNSIGNED DEFAULT 0,
  `ordinance_ref` varchar(200) DEFAULT NULL,
  `description` text DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sanctions_book`
--

INSERT INTO `sanctions_book` (`id`, `barangay_id`, `violation_type`, `violation_level`, `sanction_name`, `fine_amount`, `community_hours`, `ordinance_ref`, `description`, `is_active`, `created_at`) VALUES
(1, 1, 'Attendance', 'minor', 'Failure to Appear (1st Offense)', 0.00, 0, 'KP Rule', 'First absence; subject to rescheduling with warning.', 1, '2026-04-07 01:38:42'),
(2, 1, 'Attendance', '', 'Failure to Appear (2nd Offense - Complainant)', 0.00, 0, 'KP Rule', 'Complaint may be dismissed and barred from court filing.', 1, '2026-04-07 01:38:42'),
(3, 1, 'Attendance', '', 'Failure to Appear (2nd Offense - Respondent)', 0.00, 0, 'KP Rule', 'Issuance of Certification to File Action.', 1, '2026-04-07 01:38:42'),
(4, 1, 'Behavior', 'moderate', 'Refusal to Participate in Mediation', 0.00, 2, 'KP Rule', 'Refusal to cooperate in mediation proceedings.', 1, '2026-04-07 01:38:42'),
(5, 1, 'Behavior', 'moderate', 'Disrespect to Barangay Authority', 500.00, 4, 'Barangay Ordinance 01-2020', 'Use of offensive language or behavior during proceedings.', 1, '2026-04-07 01:38:42'),
(6, 1, 'Settlement', '', 'Violation of Amicable Settlement', 1000.00, 6, 'KP Rule', 'Failure to comply with agreed settlement terms.', 1, '2026-04-07 01:38:42'),
(7, 1, 'Settlement', 'minor', 'Delay in Compliance of Settlement', 300.00, 2, 'KP Rule', 'Late compliance with settlement agreement.', 1, '2026-04-07 01:38:42'),
(8, 1, 'Public Disturbance', 'minor', 'Noise Complaint (1st Offense)', 300.00, 2, 'Ordinance 02-2021', 'Excessive noise disturbing neighbors.', 1, '2026-04-07 01:38:42'),
(9, 1, 'Public Disturbance', 'moderate', 'Noise Complaint (Repeat Offense)', 1000.00, 4, 'Ordinance 02-2021', 'Repeated excessive noise violation.', 1, '2026-04-07 01:38:42'),
(10, 1, 'Public Disturbance', 'moderate', 'Public Drunkenness', 500.00, 3, 'Ordinance 03-2021', 'Drunk and disorderly behavior in public.', 1, '2026-04-07 01:38:42'),
(11, 1, 'Environment', 'minor', 'Improper Garbage Disposal', 500.00, 4, 'Ordinance 04-2021', 'Failure to follow waste segregation rules.', 1, '2026-04-07 01:38:42'),
(12, 1, 'Environment', 'moderate', 'Illegal Dumping', 1500.00, 6, 'Ordinance 04-2021', 'Dumping garbage in unauthorized areas.', 1, '2026-04-07 01:38:42'),
(13, 1, 'Curfew', 'minor', 'Curfew Violation (Minor)', 0.00, 2, 'Ordinance 05-2021', 'Minor outside beyond curfew hours.', 1, '2026-04-07 01:38:42'),
(14, 1, 'Curfew', 'moderate', 'Curfew Violation (Repeat)', 500.00, 4, 'Ordinance 05-2021', 'Repeated curfew violation.', 1, '2026-04-07 01:38:42'),
(15, 1, 'Property', 'minor', 'Light Property Damage', 1000.00, 4, 'KP Rule', 'Minor damage subject to settlement.', 1, '2026-04-07 01:38:42'),
(16, 1, 'Property', 'moderate', 'Fence/Boundary Dispute Violation', 500.00, 2, 'KP Rule', 'Non-compliance with agreed boundary settlement.', 1, '2026-04-07 01:38:42'),
(17, 1, 'Procedural', 'minor', 'Failure to Comply with Barangay Order', 500.00, 3, 'KP Rule', 'Ignoring barangay directive.', 1, '2026-04-07 01:38:42'),
(18, 1, 'Procedural', 'moderate', 'Filing Without Barangay Clearance', 0.00, 0, 'KP Rule', 'Case may be dismissed in court.', 1, '2026-04-07 01:38:42'),
(19, 1, 'Community', 'minor', 'Community Service - Clean-Up Drive', 0.00, 6, 'Barangay Program', 'Participation in barangay clean-up as corrective action.', 1, '2026-04-07 01:38:42'),
(20, 1, 'Community', 'moderate', 'Community Service - Public Maintenance', 0.00, 8, 'Barangay Program', 'Assigned maintenance work as sanction.', 1, '2026-04-07 01:38:42');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `barangay_id` int(10) UNSIGNED DEFAULT NULL,
  `full_name` varchar(200) NOT NULL,
  `first_name` varchar(80) DEFAULT NULL,
  `middle_name` varchar(80) DEFAULT NULL,
  `last_name` varchar(80) DEFAULT NULL,
  `email` varchar(150) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `contact_number` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `role` enum('community','barangay','superadmin') NOT NULL DEFAULT 'community',
  `is_active` tinyint(1) NOT NULL DEFAULT 0,
  `last_login` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `barangay_id`, `full_name`, `first_name`, `middle_name`, `last_name`, `email`, `password_hash`, `contact_number`, `address`, `birth_date`, `role`, `is_active`, `last_login`, `created_at`, `updated_at`) VALUES
(1, NULL, 'System Administrator', 'System', NULL, 'Administrator', 'admin@voice2.gov.ph', '$2y$12$examplehashreplacewithrealhash', NULL, 'Barangay 3 - Ermita', NULL, 'superadmin', 1, NULL, '2026-03-23 05:33:12', '2026-04-07 11:02:59'),
(2, NULL, 'System Administrator', 'System', '', 'Administrator', 'admin@voice2.ph', '$2y$12$8.JsXOaK8n8wY9bCdy1Skut5fRCEaiRz8sgZ5QNITR1q4nDf9DPpG', '09000000000', 'Barangay 3 - Ermita', '1990-01-01', 'superadmin', 1, '2026-04-06 12:59:40', '2026-03-23 05:55:22', '2026-04-07 11:02:59'),
(3, 1, 'Santos, Pedro Reyes', 'Pedro', 'Reyes', 'Santos', 'barangay@voice2.ph', '$2y$12$2Dqo6FOBEnklPGWKaj2B0.anKVYtUzFfMBzBGeD8BtbQLt2BnzJDm', '09171234567', 'Barangay 3 - Ermita', '1985-06-15', 'barangay', 1, '2026-04-07 11:46:44', '2026-03-23 05:55:22', '2026-04-07 11:46:44'),
(4, 1, 'Dela Cruz Juan Santos', 'Juan', 'Santos', 'Dela Cruz', 'juan@community.ph', '$2y$12$rXO366dNhB5FGn0IZ0AqhO02eeeIQKL5JmOBPzApfgTaKnvEpnXlq', '09181234567', 'Barangay 3 - Ermita', '1995-03-22', 'community', 1, '2026-04-07 11:47:12', '2026-03-23 05:55:22', '2026-04-07 11:47:12'),
(5, 1, 'Reyes, Maria Clara', 'Maria', 'Clara', 'Reyes', 'maria@community.ph', '$2y$12$W5JEB1k5sEQDFdN/JyOTG.9j7iJoPiW0ijYKNrzAEBOo1eNKKtApG', '09191234567', 'Barangay 3 - Ermita', '1998-11-10', 'community', 1, '2026-04-07 02:03:10', '2026-03-23 05:55:22', '2026-04-07 11:46:53'),
(6, 2, 'Perez, Abby Mann', 'Abby', 'Mann', 'Perez', 'keruberos27@gmail.com', '$2y$10$KtcYVLeHbhraFPg3EfB.y.Rs43/0DfbTQBss6z4G5bFhW8FzjH8t.', '09123155255', 'Barangay 3 - Ermita', '1998-02-07', 'community', 0, NULL, '2026-04-07 02:35:57', '2026-04-07 11:02:59');

-- --------------------------------------------------------

--
-- Table structure for table `violations`
--

CREATE TABLE `violations` (
  `id` int(10) UNSIGNED NOT NULL,
  `blotter_id` int(10) UNSIGNED NOT NULL,
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `respondent_name` varchar(200) NOT NULL,
  `respondent_address` text DEFAULT NULL,
  `user_id` int(10) UNSIGNED DEFAULT NULL,
  `risk_score` tinyint(3) UNSIGNED DEFAULT 0,
  `missed_hearings` tinyint(3) UNSIGNED DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barangay_id` (`barangay_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_entity` (`entity_type`,`entity_id`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `barangays`
--
ALTER TABLE `barangays`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_name` (`name`);

--
-- Indexes for table `barangay_name`
--
ALTER TABLE `barangay_name`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `blotters`
--
ALTER TABLE `blotters`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `case_number` (`case_number`),
  ADD KEY `complainant_user_id` (`complainant_user_id`),
  ADD KEY `assigned_officer_id` (`assigned_officer_id`),
  ADD KEY `filed_by_user_id` (`filed_by_user_id`),
  ADD KEY `idx_case_number` (`case_number`),
  ADD KEY `idx_barangay` (`barangay_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_violation` (`violation_level`),
  ADD KEY `idx_incident_date` (`incident_date`),
  ADD KEY `idx_respondent_user_id` (`respondent_user_id`);

--
-- Indexes for table `blotter_attachments`
--
ALTER TABLE `blotter_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_blotter` (`blotter_id`),
  ADD KEY `idx_uploader` (`uploaded_by`);

--
-- Indexes for table `mediation_schedules`
--
ALTER TABLE `mediation_schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barangay_id` (`barangay_id`),
  ADD KEY `mediator_user_id` (`mediator_user_id`),
  ADD KEY `idx_blotter` (`blotter_id`),
  ADD KEY `idx_hearing_date` (`hearing_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `notices`
--
ALTER TABLE `notices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barangay_id` (`barangay_id`),
  ADD KEY `idx_blotter` (`blotter_id`),
  ADD KEY `idx_recipient` (`recipient_user_id`);

--
-- Indexes for table `party_notifications`
--
ALTER TABLE `party_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `mediation_schedule_id` (`mediation_schedule_id`),
  ADD KEY `barangay_id` (`barangay_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_blotter` (`blotter_id`),
  ADD KEY `idx_recipient` (`recipient_user_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `penalties`
--
ALTER TABLE `penalties`
  ADD PRIMARY KEY (`id`),
  ADD KEY `violation_id` (`violation_id`),
  ADD KEY `barangay_id` (`barangay_id`),
  ADD KEY `issued_by` (`issued_by`),
  ADD KEY `idx_blotter` (`blotter_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `sanctions_book`
--
ALTER TABLE `sanctions_book`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barangay_id` (`barangay_id`),
  ADD KEY `idx_level` (`violation_level`),
  ADD KEY `idx_type` (`violation_type`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_barangay` (`barangay_id`);

--
-- Indexes for table `violations`
--
ALTER TABLE `violations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `barangay_id` (`barangay_id`),
  ADD KEY `idx_blotter` (`blotter_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_risk_score` (`risk_score`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `barangays`
--
ALTER TABLE `barangays`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `barangay_name`
--
ALTER TABLE `barangay_name`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `blotters`
--
ALTER TABLE `blotters`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `blotter_attachments`
--
ALTER TABLE `blotter_attachments`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `mediation_schedules`
--
ALTER TABLE `mediation_schedules`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notices`
--
ALTER TABLE `notices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `party_notifications`
--
ALTER TABLE `party_notifications`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `penalties`
--
ALTER TABLE `penalties`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `sanctions_book`
--
ALTER TABLE `sanctions_book`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `violations`
--
ALTER TABLE `violations`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `activity_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `activity_log_ibfk_2` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `blotters`
--
ALTER TABLE `blotters`
  ADD CONSTRAINT `blotters_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  ADD CONSTRAINT `blotters_ibfk_2` FOREIGN KEY (`complainant_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `blotters_ibfk_3` FOREIGN KEY (`assigned_officer_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `blotters_ibfk_4` FOREIGN KEY (`filed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_blotters_respondent_user` FOREIGN KEY (`respondent_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `blotter_attachments`
--
ALTER TABLE `blotter_attachments`
  ADD CONSTRAINT `blotter_attachments_ibfk_1` FOREIGN KEY (`blotter_id`) REFERENCES `blotters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `blotter_attachments_ibfk_2` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `mediation_schedules`
--
ALTER TABLE `mediation_schedules`
  ADD CONSTRAINT `mediation_schedules_ibfk_1` FOREIGN KEY (`blotter_id`) REFERENCES `blotters` (`id`),
  ADD CONSTRAINT `mediation_schedules_ibfk_2` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  ADD CONSTRAINT `mediation_schedules_ibfk_3` FOREIGN KEY (`mediator_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `notices`
--
ALTER TABLE `notices`
  ADD CONSTRAINT `notices_ibfk_1` FOREIGN KEY (`blotter_id`) REFERENCES `blotters` (`id`),
  ADD CONSTRAINT `notices_ibfk_2` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  ADD CONSTRAINT `notices_ibfk_3` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `party_notifications`
--
ALTER TABLE `party_notifications`
  ADD CONSTRAINT `party_notifications_ibfk_1` FOREIGN KEY (`blotter_id`) REFERENCES `blotters` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `party_notifications_ibfk_2` FOREIGN KEY (`mediation_schedule_id`) REFERENCES `mediation_schedules` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `party_notifications_ibfk_3` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  ADD CONSTRAINT `party_notifications_ibfk_4` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `party_notifications_ibfk_5` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `penalties`
--
ALTER TABLE `penalties`
  ADD CONSTRAINT `penalties_ibfk_1` FOREIGN KEY (`blotter_id`) REFERENCES `blotters` (`id`),
  ADD CONSTRAINT `penalties_ibfk_2` FOREIGN KEY (`violation_id`) REFERENCES `violations` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `penalties_ibfk_3` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  ADD CONSTRAINT `penalties_ibfk_4` FOREIGN KEY (`issued_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sanctions_book`
--
ALTER TABLE `sanctions_book`
  ADD CONSTRAINT `sanctions_book_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `violations`
--
ALTER TABLE `violations`
  ADD CONSTRAINT `violations_ibfk_1` FOREIGN KEY (`blotter_id`) REFERENCES `blotters` (`id`),
  ADD CONSTRAINT `violations_ibfk_2` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  ADD CONSTRAINT `violations_ibfk_3` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

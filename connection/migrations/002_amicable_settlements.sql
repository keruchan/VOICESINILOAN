-- Migration: Amicable Settlement + Sec. 416/418 (RA 7160) repudiation window
-- Adds structured tracking for the settlement stage of the KP mediation process:
-- a settlement becomes final/executory 10 days after signing unless formally
-- repudiated within that window (fraud, violence, intimidation, mistake of fact).

USE `voice2_db`;

-- --------------------------------------------------------
-- Table: amicable_settlements
-- --------------------------------------------------------
CREATE TABLE `amicable_settlements` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `blotter_id` int(10) UNSIGNED NOT NULL,
  `mediation_schedule_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Hearing where the settlement was signed',
  `barangay_id` int(10) UNSIGNED NOT NULL,
  `terms` text NOT NULL COMMENT 'Kasunduan / agreed terms',
  `settled_date` date NOT NULL,
  `repudiation_deadline` date NOT NULL COMMENT 'settled_date + 10 days (Sec. 416, RA 7160)',
  `status` enum('active','final','repudiated') NOT NULL DEFAULT 'active',
  `repudiated_by` enum('complainant','respondent') DEFAULT NULL,
  `repudiation_reason` text DEFAULT NULL,
  `repudiated_at` datetime DEFAULT NULL,
  `finalized_at` datetime DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_blotter` (`blotter_id`),
  KEY `idx_mediation_schedule` (`mediation_schedule_id`),
  KEY `idx_barangay` (`barangay_id`),
  KEY `idx_status` (`status`),
  KEY `idx_deadline` (`repudiation_deadline`),
  CONSTRAINT `amicable_settlements_ibfk_1` FOREIGN KEY (`blotter_id`) REFERENCES `blotters` (`id`) ON DELETE CASCADE,
  CONSTRAINT `amicable_settlements_ibfk_2` FOREIGN KEY (`mediation_schedule_id`) REFERENCES `mediation_schedules` (`id`) ON DELETE SET NULL,
  CONSTRAINT `amicable_settlements_ibfk_3` FOREIGN KEY (`barangay_id`) REFERENCES `barangays` (`id`),
  CONSTRAINT `amicable_settlements_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------
-- party_notifications: add notification types for the settlement lifecycle
-- --------------------------------------------------------
ALTER TABLE `party_notifications`
  MODIFY `notification_type` enum(
    'hearing_scheduled','hearing_reminder','hearing_rescheduled','no_show_warning',
    'case_dismissed','cfa_issued','mediation_completed','mediation_cancelled',
    'case_escalated','general','settlement_repudiated','settlement_final'
  ) NOT NULL;

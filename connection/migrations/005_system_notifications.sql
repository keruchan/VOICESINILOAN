-- Migration: system_notifications — discrete, per-user notification bell
-- feed for the Barangay and Superadmin portals (mirrors party_notifications,
-- which already serves this purpose for the Community portal).

USE `voice2_db`;

CREATE TABLE `system_notifications` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `recipient_user_id` int(10) UNSIGNED NOT NULL,
  `portal` enum('barangay','superadmin') NOT NULL,
  `type` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `link_page` varchar(50) DEFAULT NULL,
  `link_blotter_id` int(10) UNSIGNED DEFAULT NULL,
  `status` enum('unread','read') NOT NULL DEFAULT 'unread',
  `read_at` datetime DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_recipient` (`recipient_user_id`),
  KEY `idx_portal` (`portal`),
  KEY `idx_status` (`status`),
  KEY `idx_link_blotter` (`link_blotter_id`),
  CONSTRAINT `system_notifications_ibfk_1` FOREIGN KEY (`recipient_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `system_notifications_ibfk_2` FOREIGN KEY (`link_blotter_id`) REFERENCES `blotters` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

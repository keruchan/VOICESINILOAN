-- Migration: Smart Case Finder — relevance-ranked search
-- Adds FULLTEXT indexes so a free-text description can be matched against
-- Sanctions Book entries (KP Law / ordinance categories) and past blotter
-- cases, ranked by relevance (MySQL/MariaDB's built-in TF-IDF-like scoring —
-- no external API, no cost).

USE `voice2_db`;

ALTER TABLE `sanctions_book`
  ADD FULLTEXT KEY `ft_sanctions_search` (`violation_type`, `sanction_name`, `description`, `legal_explanation`, `legal_basis`, `ordinance_ref`);

ALTER TABLE `blotters`
  ADD FULLTEXT KEY `ft_blotters_search` (`incident_type`, `narrative`, `remarks`);

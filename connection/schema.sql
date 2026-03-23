-- ═══════════════════════════════════════════════════════════════
-- VOICE2 Barangay Blotter System — Database Schema
-- ═══════════════════════════════════════════════════════════════
-- Run this file once to set up the initial database structure.
-- Command: mysql -u root -p < connection/schema.sql
--
-- Database: voice2_db
-- ═══════════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS voice2_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE voice2_db;

-- ─────────────────────────────────────────────────────────────
-- 1. BARANGAYS
--    Central registry of all barangays in the system.
--    Supports multi-barangay setup.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS barangays (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(120) NOT NULL,
    municipality  VARCHAR(120) NOT NULL,
    province      VARCHAR(120),
    psgc_code     VARCHAR(20),
    contact_no    VARCHAR(20),
    email         VARCHAR(120),
    address       TEXT,
    captain_name  VARCHAR(120),
    is_active     TINYINT(1) NOT NULL DEFAULT 1,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 2. USERS
--    All portal users: community, barangay officers, superadmin.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS users (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    barangay_id     INT UNSIGNED,                        -- NULL for superadmin
    full_name       VARCHAR(200) NOT NULL,
    first_name      VARCHAR(80),
    middle_name     VARCHAR(80),
    last_name       VARCHAR(80),
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    contact_number  VARCHAR(20),
    address         TEXT,
    birth_date      DATE,
    role            ENUM('community','barangay','superadmin') NOT NULL DEFAULT 'community',
    is_active       TINYINT(1) NOT NULL DEFAULT 0,       -- 0 = pending approval
    last_login      DATETIME,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_id) REFERENCES barangays(id) ON DELETE SET NULL,
    INDEX idx_email (email),
    INDEX idx_role  (role),
    INDEX idx_barangay (barangay_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 3. BLOTTERS
--    Core blotter/case records.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS blotters (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    case_number         VARCHAR(30) NOT NULL UNIQUE,       -- e.g. #2026-0001
    barangay_id         INT UNSIGNED NOT NULL,
    complainant_user_id INT UNSIGNED,                      -- NULL if walk-in
    complainant_name    VARCHAR(200) NOT NULL,
    complainant_contact VARCHAR(20),
    complainant_address TEXT,
    respondent_name     VARCHAR(200) NOT NULL DEFAULT 'Unknown',
    respondent_contact  VARCHAR(20),
    respondent_address  TEXT,
    incident_type       VARCHAR(100) NOT NULL,
    violation_level     ENUM('minor','moderate','serious','critical') NOT NULL,
    incident_date       DATE NOT NULL,
    incident_time       TIME,
    incident_location   TEXT,
    narrative           TEXT,
    prescribed_action   ENUM(
                          'document_only',
                          'mediation',
                          'refer_barangay',
                          'refer_police',
                          'refer_vawc',
                          'escalate_municipality'
                        ) NOT NULL DEFAULT 'document_only',
    status              ENUM(
                          'pending_review',
                          'active',
                          'mediation_set',
                          'escalated',
                          'resolved',
                          'closed',
                          'transferred'
                        ) NOT NULL DEFAULT 'pending_review',
    assigned_officer_id INT UNSIGNED,                      -- FK to users (barangay role)
    filed_by_user_id    INT UNSIGNED,                      -- barangay officer who entered it
    remarks             TEXT,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_id)         REFERENCES barangays(id),
    FOREIGN KEY (complainant_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_officer_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (filed_by_user_id)    REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_case_number  (case_number),
    INDEX idx_barangay     (barangay_id),
    INDEX idx_status       (status),
    INDEX idx_violation    (violation_level),
    INDEX idx_incident_date(incident_date)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 4. MEDIATION SCHEDULES
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS mediation_schedules (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blotter_id        INT UNSIGNED NOT NULL,
    barangay_id       INT UNSIGNED NOT NULL,
    mediator_user_id  INT UNSIGNED,
    hearing_date      DATE NOT NULL,
    hearing_time      TIME NOT NULL,
    venue             VARCHAR(200),
    status            ENUM('scheduled','completed','missed','rescheduled','cancelled') NOT NULL DEFAULT 'scheduled',
    complainant_attended TINYINT(1),                       -- 1=yes, 0=no, NULL=not yet
    respondent_attended  TINYINT(1),
    outcome           TEXT,
    next_steps        TEXT,
    notify_sms        TINYINT(1) NOT NULL DEFAULT 1,
    notify_inapp      TINYINT(1) NOT NULL DEFAULT 1,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (blotter_id)       REFERENCES blotters(id),
    FOREIGN KEY (barangay_id)      REFERENCES barangays(id),
    FOREIGN KEY (mediator_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_blotter      (blotter_id),
    INDEX idx_hearing_date (hearing_date),
    INDEX idx_status       (status)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 5. VIOLATIONS (Violator tracking — links users to blotters)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS violations (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blotter_id      INT UNSIGNED NOT NULL,
    barangay_id     INT UNSIGNED NOT NULL,
    respondent_name VARCHAR(200) NOT NULL,
    respondent_address TEXT,
    user_id         INT UNSIGNED,                          -- if respondent is a registered user
    risk_score      TINYINT UNSIGNED DEFAULT 0,            -- 0–100
    missed_hearings TINYINT UNSIGNED DEFAULT 0,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (blotter_id)  REFERENCES blotters(id),
    FOREIGN KEY (barangay_id) REFERENCES barangays(id),
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_blotter   (blotter_id),
    INDEX idx_user      (user_id),
    INDEX idx_risk_score(risk_score)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 6. PENALTIES
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS penalties (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blotter_id      INT UNSIGNED NOT NULL,
    violation_id    INT UNSIGNED,
    barangay_id     INT UNSIGNED NOT NULL,
    reason          VARCHAR(255) NOT NULL,
    amount          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    community_hours INT UNSIGNED DEFAULT 0,
    due_date        DATE,
    paid_at         DATETIME,
    status          ENUM('pending','paid','waived','overdue') NOT NULL DEFAULT 'pending',
    issued_by       INT UNSIGNED,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blotter_id)  REFERENCES blotters(id),
    FOREIGN KEY (violation_id)REFERENCES violations(id) ON DELETE SET NULL,
    FOREIGN KEY (barangay_id) REFERENCES barangays(id),
    FOREIGN KEY (issued_by)   REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_blotter (blotter_id),
    INDEX idx_status  (status)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 7. SANCTIONS BOOK
--    Generic per-barangay sanction entries. Linked to ordinances.
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS sanctions_book (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    barangay_id      INT UNSIGNED NOT NULL,
    violation_type   VARCHAR(100) NOT NULL,
    violation_level  ENUM('minor','moderate','serious','critical') NOT NULL,
    sanction_name    VARCHAR(200) NOT NULL,
    fine_amount      DECIMAL(10,2) DEFAULT 0.00,
    community_hours  INT UNSIGNED DEFAULT 0,
    ordinance_ref    VARCHAR(200),
    description      TEXT,
    is_active        TINYINT(1) NOT NULL DEFAULT 1,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (barangay_id) REFERENCES barangays(id),
    INDEX idx_level (violation_level),
    INDEX idx_type  (violation_type)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 8. NOTICES
--    Formal notices sent to parties (summons, warnings, etc.)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS notices (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    blotter_id      INT UNSIGNED NOT NULL,
    barangay_id     INT UNSIGNED NOT NULL,
    recipient_user_id INT UNSIGNED,
    recipient_name  VARCHAR(200),
    notice_type     ENUM('summons','warning','penalty','escalation','general') NOT NULL,
    subject         VARCHAR(255),
    body            TEXT,
    sent_via        SET('sms','inapp','print') DEFAULT 'inapp',
    sent_at         DATETIME,
    acknowledged_at DATETIME,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (blotter_id)         REFERENCES blotters(id),
    FOREIGN KEY (barangay_id)        REFERENCES barangays(id),
    FOREIGN KEY (recipient_user_id)  REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_blotter   (blotter_id),
    INDEX idx_recipient (recipient_user_id)
) ENGINE=InnoDB;

-- ─────────────────────────────────────────────────────────────
-- 9. ACTIVITY LOG (Audit trail)
-- ─────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS activity_log (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED,
    barangay_id INT UNSIGNED,
    action      VARCHAR(100) NOT NULL,     -- e.g. 'blotter_filed', 'user_login'
    entity_type VARCHAR(60),               -- e.g. 'blotter', 'mediation'
    entity_id   INT UNSIGNED,
    description TEXT,
    ip_address  VARCHAR(45),
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (barangay_id) REFERENCES barangays(id) ON DELETE SET NULL,
    INDEX idx_user      (user_id),
    INDEX idx_entity    (entity_type, entity_id),
    INDEX idx_created   (created_at)
) ENGINE=InnoDB;

-- ═══════════════════════════════════════════════════════════════
-- SEED DATA — initial records for testing
-- ═══════════════════════════════════════════════════════════════

-- Sample barangays
INSERT INTO barangays (name, municipality, province, contact_no, captain_name) VALUES
('San Roque',  'Quezon City', 'Metro Manila', '(02) 8123-4567', 'Juan dela Cruz'),
('Sto. Niño',  'Quezon City', 'Metro Manila', '(02) 8234-5678', 'Maria Santos'),
('Malaya',     'Quezon City', 'Metro Manila', '(02) 8345-6789', 'Pedro Reyes');

-- Sample superadmin user (password: Admin@1234)
INSERT INTO users (barangay_id, full_name, first_name, last_name, email, password_hash, role, is_active)
VALUES (NULL, 'System Administrator', 'System', 'Administrator',
        'admin@voice2.gov.ph',
        '$2y$12$examplehashreplacewithrealhash',
        'superadmin', 1);

-- Note: Generate a real bcrypt hash with:
--   php -r "echo password_hash('Admin@1234', PASSWORD_BCRYPT);"

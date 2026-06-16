-- Meal Expense Splitter — MySQL/MariaDB schema
-- Import once via phpMyAdmin (cPanel). Charset utf8mb4 throughout.
-- Authorization is enforced in PHP; every row is reachable only via its owner_user_id chain.
--
-- Primary keys are application-generated UUIDv4 strings (CHAR(36), ascii/binary
-- so FK joins are exact + fast). No AUTO_INCREMENT — PHP supplies the id on insert.

SET NAMES utf8mb4;
SET time_zone = '+00:00';

-- ---------------------------------------------------------------------------
-- users
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id                    CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  email                 VARCHAR(255) NOT NULL,
  password_hash         VARCHAR(255) NOT NULL,
  plan                  ENUM('free','pro','super') NOT NULL DEFAULT 'free',
  plan_expires_at       DATETIME NULL DEFAULT NULL,
  stripe_customer_id    VARCHAR(255) NULL DEFAULT NULL,
  stripe_subscription_id VARCHAR(255) NULL DEFAULT NULL,
  created_at            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_stripe_customer (stripe_customer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- usage_counters — monthly counters keyed by user + period (YYYY-MM)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usage_counters (
  id         CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  user_id    CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  period     CHAR(7) NOT NULL,            -- 'YYYY-MM'
  ocr_scans  INT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usage_user_period (user_id, period),
  CONSTRAINT fk_usage_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- contacts — account-level address book (reused across sessions).
--   Soft-deleted (deleted_at) so past sessions keep showing the name.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contacts (
  id            CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  owner_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  display_name  VARCHAR(120) NOT NULL,
  deleted_at    DATETIME NULL DEFAULT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_contacts_owner (owner_user_id),
  CONSTRAINT fk_contacts_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- sessions — the receipt container ("session" / "group" / "trip")
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS sessions (
  id            CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  owner_user_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name          VARCHAR(120) NOT NULL,
  currency      CHAR(3) NOT NULL DEFAULT 'USD',
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_sessions_owner (owner_user_id),
  CONSTRAINT fk_sessions_owner FOREIGN KEY (owner_user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- members — a contact's participation in one session (links session ↔ contact).
--   receipts/item_shares reference members.id (this session's participation).
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS members (
  id         CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  session_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  contact_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_member_session_contact (session_id, contact_id),
  KEY idx_members_session (session_id),
  KEY idx_members_contact (contact_id),
  CONSTRAINT fk_members_session FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE CASCADE,
  CONSTRAINT fk_members_contact FOREIGN KEY (contact_id) REFERENCES contacts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- receipts — one per place; many receipts per session
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS receipts (
  id                CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  session_id        CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  image_path        VARCHAR(255) NULL DEFAULT NULL,   -- relative path under uploads/
  merchant          VARCHAR(160) NULL DEFAULT NULL,   -- the "place"
  currency          CHAR(3) NOT NULL DEFAULT 'USD',
  subtotal          DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax               DECIMAL(12,2) NOT NULL DEFAULT 0,
  tip               DECIMAL(12,2) NOT NULL DEFAULT 0,
  total             DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_by_member_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  status            ENUM('processing','ready','failed') NOT NULL DEFAULT 'ready',
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_receipts_session (session_id),
  CONSTRAINT fk_receipts_session FOREIGN KEY (session_id) REFERENCES sessions (id) ON DELETE CASCADE,
  CONSTRAINT fk_receipts_payer FOREIGN KEY (paid_by_member_id) REFERENCES members (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- line_items — rows of a receipt
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS line_items (
  id         CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  receipt_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  name       VARCHAR(200) NOT NULL,
  quantity   DECIMAL(10,3) NOT NULL DEFAULT 1,
  unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
  total      DECIMAL(12,2) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY idx_line_items_receipt (receipt_id),
  CONSTRAINT fk_line_items_receipt FOREIGN KEY (receipt_id) REFERENCES receipts (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- item_shares — who shares each line item (uneven splits via weights)
--   personal item = 1 row; shared item = N rows (optionally weighted)
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS item_shares (
  id           CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  line_item_id CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  member_id    CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  weight       DECIMAL(8,3) NOT NULL DEFAULT 1,
  PRIMARY KEY (id),
  UNIQUE KEY uq_share_item_member (line_item_id, member_id),
  KEY idx_shares_member (member_id),
  CONSTRAINT fk_shares_line_item FOREIGN KEY (line_item_id) REFERENCES line_items (id) ON DELETE CASCADE,
  CONSTRAINT fk_shares_member FOREIGN KEY (member_id) REFERENCES members (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

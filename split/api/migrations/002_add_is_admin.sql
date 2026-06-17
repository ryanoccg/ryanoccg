-- Admin role flag on users.
ALTER TABLE users ADD COLUMN is_admin TINYINT(1) NOT NULL DEFAULT 0;

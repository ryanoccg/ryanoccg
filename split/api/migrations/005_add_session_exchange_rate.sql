-- Optional per-session currency conversion: convert the session currency into a
-- home currency at a manual rate (1 session-currency unit = exchange_rate home units).
ALTER TABLE sessions
  ADD COLUMN home_currency CHAR(3) NULL DEFAULT NULL,
  ADD COLUMN exchange_rate DECIMAL(16,6) NULL DEFAULT NULL;

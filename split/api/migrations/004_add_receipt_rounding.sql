-- Malaysian-style cash rounding adjustment on a receipt (can be negative).
ALTER TABLE receipts ADD COLUMN rounding DECIMAL(12,2) NOT NULL DEFAULT 0;

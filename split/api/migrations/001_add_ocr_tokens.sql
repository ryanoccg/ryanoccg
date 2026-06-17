-- Add the monthly OpenAI token counter (safe to skip if it already exists).
ALTER TABLE usage_counters ADD COLUMN ocr_tokens BIGINT UNSIGNED NOT NULL DEFAULT 0;

-- Rename activity_log columns from the legacy `Al*` prefix to plain snake_case.
-- Part of the column-naming modernisation (see docs-src/developer/schema-naming.md).
-- Idempotent: rename when only the old name exists; drop a stray duplicate if both
-- exist (fresh installs). Indexes (uq_activity_user_date, idx_activity_date) follow
-- the column rename automatically. activity_log has no foreign keys.
--
--   AlID -> id   AlUsID -> user_id   AlDate -> date
--   AlTermsCreated -> terms_created   AlTermsReviewed -> terms_reviewed   AlTextsRead -> texts_read

SET @db = DATABASE();

-- AlID -> id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'activity_log' AND column_name = 'AlID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'activity_log' AND column_name = 'id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE activity_log CHANGE COLUMN AlID id int(10) unsigned NOT NULL AUTO_INCREMENT', IF(@old > 0 AND @new > 0, 'ALTER TABLE activity_log DROP COLUMN AlID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- AlUsID -> user_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'activity_log' AND column_name = 'AlUsID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'activity_log' AND column_name = 'user_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE activity_log CHANGE COLUMN AlUsID user_id int(10) unsigned DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE activity_log DROP COLUMN AlUsID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- AlDate -> date
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'activity_log' AND column_name = 'AlDate');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'activity_log' AND column_name = 'date');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE activity_log CHANGE COLUMN AlDate date date NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE activity_log DROP COLUMN AlDate', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- AlTermsCreated -> terms_created
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'activity_log' AND column_name = 'AlTermsCreated');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'activity_log' AND column_name = 'terms_created');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE activity_log CHANGE COLUMN AlTermsCreated terms_created int(10) unsigned NOT NULL DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE activity_log DROP COLUMN AlTermsCreated', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- AlTermsReviewed -> terms_reviewed
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'activity_log' AND column_name = 'AlTermsReviewed');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'activity_log' AND column_name = 'terms_reviewed');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE activity_log CHANGE COLUMN AlTermsReviewed terms_reviewed int(10) unsigned NOT NULL DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE activity_log DROP COLUMN AlTermsReviewed', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- AlTextsRead -> texts_read
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'activity_log' AND column_name = 'AlTextsRead');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'activity_log' AND column_name = 'texts_read');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE activity_log CHANGE COLUMN AlTextsRead texts_read int(10) unsigned NOT NULL DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE activity_log DROP COLUMN AlTextsRead', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

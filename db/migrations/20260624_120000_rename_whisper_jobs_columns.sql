-- Rename whisper_jobs columns from the legacy `Wj*` prefix to plain snake_case.
-- Part of the column-naming modernisation (see docs-src/developer/schema-naming.md).
--
-- Idempotent and safe on fresh installs: the baseline already creates the new
-- names, so each column is renamed only when the old name still exists, and a
-- stray duplicate (old + new both present) is dropped rather than renamed.
--
--   WjJobID     -> job_id
--   WjUsID      -> user_id
--   WjCreatedAt -> created_at

-- The FK names WjUsID, so drop it before renaming and recreate it afterwards.
ALTER TABLE whisper_jobs DROP FOREIGN KEY IF EXISTS fk_whisper_jobs_user;

SET @db = DATABASE();

-- WjJobID -> job_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'whisper_jobs' AND column_name = 'WjJobID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'whisper_jobs' AND column_name = 'job_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE whisper_jobs CHANGE COLUMN WjJobID job_id varchar(64) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE whisper_jobs DROP COLUMN WjJobID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WjUsID -> user_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'whisper_jobs' AND column_name = 'WjUsID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'whisper_jobs' AND column_name = 'user_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE whisper_jobs CHANGE COLUMN WjUsID user_id int(10) unsigned DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE whisper_jobs DROP COLUMN WjUsID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- WjCreatedAt -> created_at
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'whisper_jobs' AND column_name = 'WjCreatedAt');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'whisper_jobs' AND column_name = 'created_at');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE whisper_jobs CHANGE COLUMN WjCreatedAt created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', IF(@old > 0 AND @new > 0, 'ALTER TABLE whisper_jobs DROP COLUMN WjCreatedAt', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Recreate the FK against the new column name (idempotent: dropped above).
ALTER TABLE whisper_jobs
    ADD CONSTRAINT fk_whisper_jobs_user FOREIGN KEY (user_id)
    REFERENCES users(UsID) ON DELETE CASCADE;

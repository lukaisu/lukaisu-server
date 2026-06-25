-- Rename the users and settings table columns from the legacy Us*/St* prefixes to
-- plain snake_case. Final stage of the column-naming modernisation (see
-- docs-src/developer/schema-naming.md).
-- Idempotent: rename when only the old name exists; drop a stray duplicate if both
-- exist; no-op when neither exists. Indexes follow the column rename automatically.
--
-- users is the root parent of the ownership graph: renaming its PK UsID -> id means
-- every child FK pointing at users(UsID) must be dropped first and recreated against
-- users(id) afterwards. All eight child tables are guaranteed present (baseline.sql
-- is re-applied before migrations run), but the DROPs use IF EXISTS and the re-adds
-- are guarded so the migration is safely re-runnable.
--
-- settings has no foreign key (StUsID/user_id defaults to 0 for global settings, so
-- it is intentionally not constrained to users); its three columns rename in place.
-- StKey -> name and StValue -> value (StKey is a SQL reserved word de-prefixed; the
-- schema-naming doc maps it to name, giving settings(name, user_id, value)).
--
-- FKs touched (dropped before the renames, recreated against users(id) after):
--   fk_languages_user, fk_texts_user, fk_words_user, fk_review_log_user,
--   fk_tags_user, fk_text_tags_user, fk_news_feeds_user, fk_whisper_jobs_user
SET @db = DATABASE();

ALTER TABLE languages DROP FOREIGN KEY IF EXISTS fk_languages_user;
ALTER TABLE texts DROP FOREIGN KEY IF EXISTS fk_texts_user;
ALTER TABLE words DROP FOREIGN KEY IF EXISTS fk_words_user;
ALTER TABLE review_log DROP FOREIGN KEY IF EXISTS fk_review_log_user;
ALTER TABLE tags DROP FOREIGN KEY IF EXISTS fk_tags_user;
ALTER TABLE text_tags DROP FOREIGN KEY IF EXISTS fk_text_tags_user;
ALTER TABLE news_feeds DROP FOREIGN KEY IF EXISTS fk_news_feeds_user;
ALTER TABLE whisper_jobs DROP FOREIGN KEY IF EXISTS fk_whisper_jobs_user;


-- UsID -> id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsID id int(10) unsigned NOT NULL AUTO_INCREMENT', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsUsername -> username
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsUsername');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'username');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsUsername username varchar(100) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsUsername', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsEmail -> email
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsEmail');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'email');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsEmail email varchar(255) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsEmail', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsEmailVerifiedAt -> email_verified_at
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsEmailVerifiedAt');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'email_verified_at');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsEmailVerifiedAt email_verified_at datetime DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsEmailVerifiedAt', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsEmailVerificationToken -> email_verification_token
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsEmailVerificationToken');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'email_verification_token');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsEmailVerificationToken email_verification_token varchar(255) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsEmailVerificationToken', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsEmailVerificationTokenExpires -> email_verification_token_expires
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsEmailVerificationTokenExpires');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'email_verification_token_expires');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsEmailVerificationTokenExpires email_verification_token_expires datetime DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsEmailVerificationTokenExpires', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsPasswordHash -> password_hash
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsPasswordHash');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'password_hash');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsPasswordHash password_hash varchar(255) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsPasswordHash', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsApiToken -> api_token
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsApiToken');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'api_token');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsApiToken api_token varchar(64) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsApiToken', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsApiTokenExpires -> api_token_expires
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsApiTokenExpires');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'api_token_expires');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsApiTokenExpires api_token_expires datetime DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsApiTokenExpires', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsRememberToken -> remember_token
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsRememberToken');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'remember_token');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsRememberToken remember_token varchar(64) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsRememberToken', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsRememberTokenExpires -> remember_token_expires
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsRememberTokenExpires');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'remember_token_expires');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsRememberTokenExpires remember_token_expires datetime DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsRememberTokenExpires', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsPasswordResetToken -> password_reset_token
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsPasswordResetToken');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'password_reset_token');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsPasswordResetToken password_reset_token varchar(64) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsPasswordResetToken', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsPasswordResetTokenExpires -> password_reset_token_expires
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsPasswordResetTokenExpires');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'password_reset_token_expires');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsPasswordResetTokenExpires password_reset_token_expires datetime DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsPasswordResetTokenExpires', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsRecoveryCodeHash -> recovery_code_hash
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsRecoveryCodeHash');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'recovery_code_hash');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsRecoveryCodeHash recovery_code_hash varchar(255) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsRecoveryCodeHash', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsWordPressId -> wordpress_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsWordPressId');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'wordpress_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsWordPressId wordpress_id int(10) unsigned DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsWordPressId', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsGoogleId -> google_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsGoogleId');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'google_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsGoogleId google_id varchar(255) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsGoogleId', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsMicrosoftId -> microsoft_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsMicrosoftId');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'microsoft_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsMicrosoftId microsoft_id varchar(255) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsMicrosoftId', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsCreated -> created_at
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsCreated');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'created_at');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsCreated created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsCreated', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsLastLogin -> last_login_at
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsLastLogin');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'last_login_at');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsLastLogin last_login_at timestamp NULL DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsLastLogin', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsIsActive -> is_active
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsIsActive');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'is_active');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsIsActive is_active tinyint(1) unsigned NOT NULL DEFAULT 1', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsIsActive', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- UsRole -> role
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'UsRole');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'users' AND column_name = 'role');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE users CHANGE COLUMN UsRole role enum(''user'',''admin'') NOT NULL DEFAULT ''user''', IF(@old > 0 AND @new > 0, 'ALTER TABLE users DROP COLUMN UsRole', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;


-- StKey -> name
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'settings' AND column_name = 'StKey');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'settings' AND column_name = 'name');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE settings CHANGE COLUMN StKey name varchar(40) NOT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE settings DROP COLUMN StKey', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- StUsID -> user_id
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'settings' AND column_name = 'StUsID');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'settings' AND column_name = 'user_id');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE settings CHANGE COLUMN StUsID user_id int(10) unsigned NOT NULL DEFAULT 0', IF(@old > 0 AND @new > 0, 'ALTER TABLE settings DROP COLUMN StUsID', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- StValue -> value
SET @old = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'settings' AND column_name = 'StValue');
SET @new = (SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = @db AND table_name = 'settings' AND column_name = 'value');
SET @sql = IF(@old > 0 AND @new = 0, 'ALTER TABLE settings CHANGE COLUMN StValue value varchar(40) DEFAULT NULL', IF(@old > 0 AND @new > 0, 'ALTER TABLE settings DROP COLUMN StValue', 'SELECT 1'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Ensure the settings composite primary key exists. On an upgrade the CHANGE COLUMN
-- above carries the (StKey, StUsID) PK over to (name, user_id) automatically. But on
-- a fresh install the historical settings_composite_pk migration runs against this
-- already-renamed baseline: its DROP PRIMARY KEY removes the (name, user_id) PK and
-- the following ADD PRIMARY KEY (StKey, StUsID) fails (StKey no longer exists),
-- leaving settings with no PK. Re-establish it here. Guarded so the upgrade path,
-- where the PK is already present, is a no-op.
SET @has_pk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'settings' AND constraint_type = 'PRIMARY KEY');
SET @sql = IF(@has_pk = 0, 'ALTER TABLE settings ADD PRIMARY KEY (name, user_id)', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Recreate the child FKs against users(id). The child user_id columns were all
-- renamed by earlier stages; re-adds are guarded so a re-run is a no-op.

SET @has_fk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'languages' AND constraint_name = 'fk_languages_user');
SET @sql = IF(@has_fk = 0, 'ALTER TABLE languages ADD CONSTRAINT fk_languages_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'texts' AND constraint_name = 'fk_texts_user');
SET @sql = IF(@has_fk = 0, 'ALTER TABLE texts ADD CONSTRAINT fk_texts_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'words' AND constraint_name = 'fk_words_user');
SET @sql = IF(@has_fk = 0, 'ALTER TABLE words ADD CONSTRAINT fk_words_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'review_log' AND constraint_name = 'fk_review_log_user');
SET @sql = IF(@has_fk = 0, 'ALTER TABLE review_log ADD CONSTRAINT fk_review_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'tags' AND constraint_name = 'fk_tags_user');
SET @sql = IF(@has_fk = 0, 'ALTER TABLE tags ADD CONSTRAINT fk_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'text_tags' AND constraint_name = 'fk_text_tags_user');
SET @sql = IF(@has_fk = 0, 'ALTER TABLE text_tags ADD CONSTRAINT fk_text_tags_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'news_feeds' AND constraint_name = 'fk_news_feeds_user');
SET @sql = IF(@has_fk = 0, 'ALTER TABLE news_feeds ADD CONSTRAINT fk_news_feeds_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_fk = (SELECT COUNT(*) FROM information_schema.table_constraints WHERE table_schema = @db AND table_name = 'whisper_jobs' AND constraint_name = 'fk_whisper_jobs_user');
SET @sql = IF(@has_fk = 0, 'ALTER TABLE whisper_jobs ADD CONSTRAINT fk_whisper_jobs_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE', 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
